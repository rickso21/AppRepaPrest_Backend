<?php

namespace App\Http\Controllers\comunidad;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;

class AdController extends Controller
{
    // ================================================================
    // CONSTANTES
    // ================================================================

    /**
     * Planes disponibles con precio, días de duración y prioridad.
     * La prioridad más alta = aparece más arriba en la comunidad.
     */
    private const PLANES = [
        1 => ['nombre' => 'Básico',   'precio' => 199, 'dias' => 7,  'priority' => 1],
        2 => ['nombre' => 'Estándar', 'precio' => 349, 'dias' => 15, 'priority' => 5],
        3 => ['nombre' => 'Premium',  'precio' => 599, 'dias' => 30, 'priority' => 10],
    ];

    // ================================================================
    // ENDPOINTS PÚBLICOS (consumidos por usuarios de la comunidad)
    // ================================================================

    /**
     * GET /api/ads
     *
     * Lista los anuncios ACTIVOS para el grupo del usuario autenticado.
     * Se usa en la pantalla de Comunidad para intercalar anuncios en el feed.
     *
     * - Filtra por `status_id = 1` y fechas válidas (scope `active`).
     * - Solo muestra anuncios globales (group_id = null) o del mismo grupo.
     * - Ordena por prioridad (mayor primero) y fecha de creación.
     * - Límite máximo de 20 por request.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['res' => false, 'msg' => 'No autenticado'], 401);
        }

        $limit = min((int) $request->input('limit', 5), 20);

        $ads = Ad::active()
            ->where(function ($q) use ($user) {
                $q->whereNull('group_id')
                  ->orWhere('group_id', $user->grupo_id);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'res' => true,
            'ads' => $ads->map(fn ($ad) => $this->formatearAdParaComunidad($ad)),
        ], 200);
    }

    /**
     * POST /api/ads/{id}/impression
     *
     * Registra una impresión (visualización) del anuncio.
     * Se llama desde el hook `useAds` la primera vez que el usuario ve el anuncio.
     */
    public function impression(Request $request, $id)
    {
        $ad = Ad::find($id);

        if (!$ad) {
            return response()->json(['res' => false, 'msg' => 'No encontrado'], 404);
        }

        $ad->increment('impressions');

        return response()->json(['res' => true]);
    }

    /**
     * POST /api/ads/{id}/click
     *
     * Registra un clic en el botón CTA del anuncio.
     * Se llama desde `AdCard` antes de abrir el link o WhatsApp.
     */
    public function click(Request $request, $id)
    {
        $ad = Ad::find($id);

        if (!$ad) {
            return response()->json(['res' => false, 'msg' => 'No encontrado'], 404);
        }

        $ad->increment('clicks');

        return response()->json(['res' => true]);
    }

    // ================================================================
    // ENDPOINTS PROTEGIDOS (solo rol COMERCIO)
    // ================================================================

    /**
     * POST /api/ads/create
     *
     * Crea un anuncio en estado "pendiente de pago" (status_id = 0).
     * Solo accesible para usuarios con rol_id = 4 (comercio).
     *
     * - Valida que tenga al menos un link o teléfono de contacto.
     * - Guarda la imagen en `public/img/ads/`.
     * - El anuncio se activa hasta que el webhook de MercadoPago lo confirma.
     */
    public function create(Request $request)
    {
        $user = $request->user();

        if (!$user || (int) $user->rol_id !== 4) {
            return response()->json([
                'res' => false,
                'msg' => 'Solo comercios pueden publicar anuncios',
            ], 403);
        }

        $request->validate([
            'title'   => 'required|string|max:150',
            'plan_id' => 'required|integer|in:1,2,3',
        ]);

        // Validar que al menos uno exista
        $linkUrl = trim($request->link_url ?? '');
        $phone   = trim($request->phone ?? '');

        if ($linkUrl === '' && $phone === '') {
            return response()->json([
                'res' => false,
                'msg' => 'Debes proporcionar al menos un link o un teléfono de contacto.',
            ], 422);
        }

        try {
            $ad = new Ad();
            $ad->merchant_id = $user->id;
            $ad->group_id    = null;
            $ad->title       = $request->title;
            $ad->description = $request->description ?? null;
            $ad->link_url    = $linkUrl ?: null;
            $ad->phone       = $phone ?: null;
            $ad->cta_text    = $request->cta_text ?? 'Ver más';
            $ad->priority    = 0;
            $ad->status_id   = 0;   // Pendiente de pago
            $ad->start_at    = null;
            $ad->end_at      = null;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $ext  = $file->getClientOriginalExtension() ?: 'jpg';
                $name = time() . '_' . $user->id . '.' . $ext;

                if (!file_exists(public_path('img/ads'))) {
                    mkdir(public_path('img/ads'), 0775, true);
                }

                $file->move(public_path('img/ads'), $name);
                $ad->image = $name;
            }

            $ad->save();

            return response()->json([
                'res' => true,
                'msg' => 'Anuncio creado. Procede al pago.',
                'ad'  => [
                    'id'     => $ad->id,
                    'title'  => $ad->title,
                    'status' => $ad->status_id,
                ],
            ], 201);

        } catch (\Throwable $th) {
            \Log::error('[ads.create] Error: ' . $th->getMessage());

            return response()->json([
                'res' => false,
                'msg' => 'Error al crear el anuncio: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/ads/{id}/pay
     *
     * Genera una preferencia de pago en MercadoPago para el anuncio.
     * Devuelve `init_point` para que el frontend abra el checkout.
     *
     * - Usa `external_reference` con formato "ad_{id}_plan_{planId}"
     *   para identificar el anuncio y plan cuando llegue el webhook.
     * - El anuncio NO se activa aquí; solo cuando MP confirme.
     */
    public function pay(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['res' => false, 'msg' => 'No autenticado'], 401);
        }

        $ad = Ad::where('id', $id)->where('merchant_id', $user->id)->first();

        if (!$ad) {
            return response()->json(['res' => false, 'msg' => 'Anuncio no encontrado'], 404);
        }

        $planId = (int) $request->input('plan_id', 1);
        $plan   = self::PLANES[$planId] ?? self::PLANES[1];

        // Token de MercadoPago
        $token = config('services.mercadopago.access_token');

        if (empty($token) || $token === 'null') {
            return response()->json([
                'res' => false,
                'msg' => 'Token de Mercado Pago no configurado',
            ], 500);
        }

        // URL base (ngrok en desarrollo, dominio en producción)
        $baseUrl = config('app.env') === 'production'
            ? config('app.url')
            : 'https://thing-climatic-driller.ngrok-free.dev';

        $preferenceData = [
            'items' => [
                [
                    'id'          => 'ad_' . $ad->id,
                    'title'       => "Anuncio {$plan['nombre']} - {$ad->title}",
                    'description' => "Publicación de anuncio por {$plan['dias']} días",
                    'quantity'    => 1,
                    'unit_price'  => (float) $plan['precio'],
                    'currency_id' => 'MXN',
                ],
            ],
            'payer' => [
                'name'  => trim(($user->nombre ?? '') . ' ' . ($user->apellido_p ?? '')),
                'email' => $user->email ?? 'comercio@email.com',
            ],
            'back_urls' => [
                'success' => "{$baseUrl}/pago-exitoso",
                'failure' => "{$baseUrl}/pago-fallido",
                'pending' => "{$baseUrl}/pago-pendiente",
            ],
            'notification_url'   => "{$baseUrl}/api/ads/webhook",
            'external_reference' => "ad_{$ad->id}_plan_{$planId}",
            'auto_return'        => 'approved',
        ];

        // Llamada con curl a MercadoPago
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/checkout/preferences');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preferenceData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        // Manejo de errores
        if ($error) {
            \Log::error('[ads.pay] Error de conexión MP', ['error' => $error]);

            return response()->json([
                'res' => false,
                'msg' => 'Error de conexión con Mercado Pago: ' . $error,
            ], 500);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 201 && $httpCode !== 200) {
            \Log::error('[ads.pay] Error MP', [
                'httpCode' => $httpCode,
                'response' => $data,
            ]);

            return response()->json([
                'res'   => false,
                'msg'   => 'Error al crear preferencia: ' . ($data['message'] ?? 'Error desconocido'),
                'debug' => config('app.debug') ? $data : null,
            ], 500);
        }

        return response()->json([
            'res'                => true,
            'preference_id'      => $data['id'],
            'init_point'         => $data['init_point'],
            'sandbox_init_point' => $data['sandbox_init_point'] ?? null,
        ], 200);
    }

    /**
     * GET /api/ads/my-ads
     *
     * Lista todos los anuncios del comercio autenticado (cualquier estado).
     * Se usa en el Panel del Comercio para mostrar el historial de anuncios.
     */
    public function myAds(Request $request)
    {
        $user = $request->user();

        if (!$user || (int) $user->rol_id !== 4) {
            return response()->json([
                'res' => false,
                'msg' => 'Solo comercios pueden ver sus anuncios',
            ], 403);
        }

        $ads = Ad::where('merchant_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'res' => true,
            'ads' => $ads->map(fn ($ad) => $this->formatearAdParaPanel($ad)),
        ], 200);
    }

    // ================================================================
    // WEBHOOK (público, sin auth)
    // ================================================================

    /**
     * POST /api/ads/webhook
     *
     * Recibe notificaciones de MercadoPago cuando un pago cambia de estado.
     * Este endpoint DEBE ser público (sin auth:sanctum).
     *
     * Flujo:
     * 1. Verifica el tipo de notificación (payment).
     * 2. Consulta el pago en la API de MP.
     * 3. Si está aprobado, extrae `ad_id` y `plan_id` del external_reference.
     * 4. Activa el anuncio (status_id = 1) y programa su expiración.
     */
    public function webhook(Request $request)
    {
        try {
            \Log::info('[ads.webhook] Notificación recibida', $request->all());

            if (!$request->has('type') || $request->type !== 'payment') {
                return response()->json(['message' => 'Notificación procesada'], 200);
            }

            $paymentId = $request->input('data.id');

            if (!$paymentId) {
                return response()->json(['message' => 'No payment id'], 400);
            }

            $token = config('services.mercadopago.access_token');

            if (empty($token) || $token === 'null') {
                return response()->json(['message' => 'Token not configured'], 500);
            }

            // Consultar el pago en MP
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$paymentId}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                \Log::error('[ads.webhook] Error consultando pago', ['httpCode' => $httpCode]);
                return response()->json(['message' => 'Error consultando pago'], 500);
            }

            $pago_mp = json_decode($response, true);
            $status  = $pago_mp['status'] ?? null;

            \Log::info('[ads.webhook] Pago consultado', [
                'payment_id'         => $paymentId,
                'status'             => $status,
                'external_reference' => $pago_mp['external_reference'] ?? null,
            ]);

            if ($status !== 'approved') {
                return response()->json(['status' => 'not_approved']);
            }

            // Extraer ad_id y plan_id del external_reference
            $ref = $pago_mp['external_reference'] ?? null;

            if (!preg_match('/ad_(\d+)_plan_(\d+)/', $ref, $matches)) {
                \Log::warning('[ads.webhook] external_reference inválido', ['ref' => $ref]);
                return response()->json(['status' => 'invalid_ref']);
            }

            $adId   = (int) $matches[1];
            $planId = (int) $matches[2];

            if (!isset(self::PLANES[$planId])) {
                return response()->json(['status' => 'invalid_plan']);
            }

            $plan = self::PLANES[$planId];

            // Activar el anuncio
            $ad = Ad::find($adId);

            if (!$ad) {
                \Log::error('[ads.webhook] Anuncio no encontrado', ['ad_id' => $adId]);
                return response()->json(['status' => 'ad_not_found']);
            }

            $ad->status_id = 1;
            $ad->start_at  = now();
            $ad->end_at    = now()->addDays($plan['dias']);
            $ad->priority  = $plan['priority'];
            $ad->save();

            \Log::info('[ads.webhook] Anuncio activado', [
                'ad_id'      => $ad->id,
                'payment_id' => $paymentId,
                'end_at'     => $ad->end_at,
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Throwable $th) {
            \Log::error('[ads.webhook] Error', [
                'message' => $th->getMessage(),
                'trace'   => $th->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    // ================================================================
    // HELPERS PRIVADOS
    // ================================================================

    /**
     * Formatea un anuncio para la respuesta de la comunidad.
     * Solo devuelve los campos necesarios para renderizar el AdCard.
     */
    private function formatearAdParaComunidad(Ad $ad): array
    {
        return [
            'id'          => $ad->id,
            'title'       => $ad->title,
            'description' => $ad->description,
            'image_url'   => $ad->image_url,
            'video_url'   => $ad->video_url,
            'link_url'    => $ad->link_url,
            'phone'       => $ad->phone,
            'cta_text'    => $ad->cta_text,
            'merchant'    => [
                'id'     => $ad->merchant?->id,
                'nombre' => trim(
                    ($ad->merchant?->nombre ?? '') . ' ' .
                    ($ad->merchant?->apellido_p ?? '')
                ),
            ],
        ];
    }

    /**
     * Formatea un anuncio para la respuesta del panel del comercio.
     * Incluye estado, métricas y fechas.
     */
    private function formatearAdParaPanel(Ad $ad): array
    {
        return [
            'id'          => $ad->id,
            'title'       => $ad->title,
            'description' => $ad->description,
            'image_url'   => $ad->image_url,
            'link_url'    => $ad->link_url,
            'phone'       => $ad->phone,
            'cta_text'    => $ad->cta_text,
            'status_id'   => $ad->status_id,
            'status_text' => $this->getStatusTexto($ad->status_id),
            'priority'    => $ad->priority,
            'impressions' => $ad->impressions,
            'clicks'      => $ad->clicks,
            'start_at'    => $ad->start_at?->format('Y-m-d H:i'),
            'end_at'      => $ad->end_at?->format('Y-m-d H:i'),
            'created_at'  => $ad->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Traduce el `status_id` a un texto legible para el comercio.
     */
    private function getStatusTexto(int $statusId): string
    {
        return match ($statusId) {
            0 => 'Pendiente de pago',
            1 => 'Activo',
            2 => 'Expirado',
            3 => 'Rechazado',
            default => 'Desconocido',
        };
    }
}
