<?php

namespace App\Http\Controllers\comunidad;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;

class AdController extends Controller
{
    // CONSTANTES

    private const PLANES = [
        1 => ['nombre' => 'Básico',   'precio' => 199, 'dias' => 7,  'priority' => 1],
        2 => ['nombre' => 'Estándar', 'precio' => 349, 'dias' => 15, 'priority' => 5],
        3 => ['nombre' => 'Premium',  'precio' => 599, 'dias' => 30, 'priority' => 10],
    ];

    //ENDPOINTS PÚBLICOS

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
            'ads' => $ads->map(fn($ad) => $this->formatearAdParaComunidad($ad)),
        ], 200);
    }

    public function impression(Request $request, $id)
    {
        $ad = Ad::find($id);

        if (!$ad) {
            return response()->json(['res' => false, 'msg' => 'No encontrado'], 404);
        }

        $ad->increment('impressions');

        return response()->json(['res' => true]);
    }

    public function click(Request $request, $id)
    {
        $ad = Ad::find($id);

        if (!$ad) {
            return response()->json(['res' => false, 'msg' => 'No encontrado'], 404);
        }

        $ad->increment('clicks');

        return response()->json(['res' => true]);
    }


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
            $ad->status_id   = 0;
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
                'next' => [
                    'action' => 'pay',
                    'method' => 'POST',
                    'url'    => "/ads/{$ad->id}/pay",
                    'body'   => ['plan_id' => (int) $request->plan_id],
                ],
            ], 201);
        } catch (\Throwable $th) {
            \Log::error('[ads.create] Error: ' . $th->getMessage());

            return response()->json([
                'res' => false,
                'msg' => config('app.debug')
                    ? 'Error al crear el anuncio: ' . $th->getMessage()
                    : 'Error al crear el anuncio',
            ], 500);
        }
    }

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

        // 🔑 SI YA ESTÁ ACTIVO → no permitir pagar de nuevo
        if ((int) $ad->status_id === 1 && $ad->end_at && $ad->end_at->isFuture()) {
            return response()->json([
                'res'          => true,
                'already_paid' => true,
                'msg'          => 'Tu pago ya fue completado. Este anuncio ya está activo.',
                'ad'           => [
                    'id'          => $ad->id,
                    'title'       => $ad->title,
                    'status_id'   => $ad->status_id,
                    'status_text' => 'Activo',
                    'start_at'    => $ad->start_at?->format('Y-m-d H:i'),
                    'end_at'      => $ad->end_at?->format('Y-m-d H:i'),
                ],
            ], 200);
        }

        $planId = (int) $request->input('plan_id', 1);
        $plan   = self::PLANES[$planId] ?? self::PLANES[1];

        $token = config('services.mercadopago.access_token');

        if (empty($token) || $token === 'null') {
            return response()->json([
                'res' => false,
                'msg' => 'Token de Mercado Pago no configurado',
            ], 500);
        }

        $notificationUrl = config('services.mercadopago.notification_url');
        $frontendUrl     = rtrim(config('services.mercadopago.frontend_url'), '/');

        if (empty($notificationUrl)) {
            return response()->json([
                'res' => false,
                'msg' => 'MERCADOPAGO_NOTIFICATION_URL no configurada',
            ], 500);
        }

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
                'success' => "{$frontendUrl}/pago-exitoso",
                'failure' => "{$frontendUrl}/pago-fallido",
                'pending' => "{$frontendUrl}/pago-pendiente",
            ],
            'notification_url'   => $notificationUrl,
            'external_reference' => "ad_{$ad->id}_plan_{$planId}",
            'auto_return'        => 'approved',
        ];

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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, config('app.env') === 'production');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

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
            'ads' => $ads->map(fn($ad) => $this->formatearAdParaPanel($ad)),
        ], 200);
    }

    // PROCESAMIENTO DE PAGO DE ANUNCIO

    public function procesarPagoDeAnuncio(array $pago_mp): array
    {
        $status = $pago_mp['status'] ?? null;

        if ($status !== 'approved') {
            return [
                'procesado' => false,
                'status'    => 'not_approved',
                'message'   => "Pago con status '{$status}' no aprobado",
            ];
        }

        $ref = $pago_mp['external_reference'] ?? null;

        if (!preg_match('/^ad_(\d+)_plan_(\d+)$/', (string) $ref, $matches)) {
            return [
                'procesado' => false,
                'status'    => 'invalid_ref',
                'message'   => 'external_reference no corresponde a un anuncio',
            ];
        }

        $adId   = (int) $matches[1];
        $planId = (int) $matches[2];

        if (!isset(self::PLANES[$planId])) {
            return [
                'procesado' => false,
                'status'    => 'invalid_plan',
                'message'   => "Plan {$planId} no existe",
            ];
        }

        $plan = self::PLANES[$planId];

        $ad = Ad::find($adId);

        if (!$ad) {
            return [
                'procesado' => false,
                'status'    => 'ad_not_found',
                'message'   => "Anuncio {$adId} no encontrado",
            ];
        }

        if ((int) $ad->status_id === 1 && $ad->end_at && $ad->end_at->isFuture()) {
            return [
                'procesado' => true,
                'status'    => 'already_active',
                'message'   => 'El anuncio ya estaba activo',
                'ad_id'     => $ad->id,
            ];
        }

        $ad->status_id = 1;
        $ad->start_at  = now();
        $ad->end_at    = now()->addDays($plan['dias']);
        $ad->priority  = $plan['priority'];
        $ad->save();

        \Log::info('[ads.procesarPagoDeAnuncio] Anuncio activado', [
            'ad_id'      => $ad->id,
            'payment_id' => $pago_mp['id'] ?? null,
            'plan_id'    => $planId,
            'end_at'     => $ad->end_at,
        ]);

        return [
            'procesado' => true,
            'status'    => 'success',
            'message'   => 'Anuncio activado correctamente',
            'ad_id'     => $ad->id,
        ];
    }

    // ================================================================
    // HELPERS PRIVADOS
    // ================================================================

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
