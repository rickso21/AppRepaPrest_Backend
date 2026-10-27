<?php

namespace App\Http\Controllers\comunidad;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;

class AdController extends Controller
{
    private const PLANES = [
        1 => ['nombre' => 'Básico',   'precio' => 199, 'dias' => 7,  'priority' => 1],
        2 => ['nombre' => 'Estándar', 'precio' => 349, 'dias' => 15, 'priority' => 5],
        3 => ['nombre' => 'Premium',  'precio' => 599, 'dias' => 30, 'priority' => 10],
    ];

    // ================================================================
    // ENDPOINTS PÚBLICOS
    // ================================================================

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['res' => false, 'msg' => 'No autenticado'], 401);
        }

        $limit = min((int) $request->input('limit', 5), 20);

        $ads = Ad::active()
            ->where(function ($q) use ($user) {
                $q->whereNull('group_id')->orWhere('group_id', $user->grupo_id);
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
        if (!$ad) return response()->json(['res' => false, 'msg' => 'No encontrado'], 404);
        $ad->increment('impressions');
        return response()->json(['res' => true]);
    }

    public function click(Request $request, $id)
    {
        $ad = Ad::find($id);
        if (!$ad) return response()->json(['res' => false, 'msg' => 'No encontrado'], 404);
        $ad->increment('clicks');
        return response()->json(['res' => true]);
    }

    // ================================================================
    // CREAR ANUNCIO
    // ================================================================

    public function create(Request $request)
    {
        $user = $request->user();

        if (!$user || (int) $user->rol_id !== 4) {
            return response()->json([
                'res' => false,
                'msg' => 'Solo comercios pueden publicar anuncios',
            ], 403);
        }

        // ✅ VALIDAR SUSCRIPCIÓN ACTIVA
        $active = \App\Models\Subscription::activeForUser($user->id);

        if (!$active) {
            return response()->json([
                'res'  => false,
                'code' => 'subscription_required',
                'msg'  => 'Necesitas una suscripción activa para publicar.',
                'subscription' => \App\Models\Subscription::statusPayloadForUser($user->id),
            ], 402);
        }

        $request->validate([
            'title' => 'required|string|max:150',
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
            $ad = new \App\Models\Ad();
            $ad->merchant_id = $user->id;
            $ad->group_id    = null;
            $ad->title       = $request->title;
            $ad->description = $request->description ?? null;
            $ad->link_url    = $linkUrl ?: null;
            $ad->phone       = $phone ?: null;
            $ad->cta_text    = $request->cta_text ?? 'Ver más';
            $ad->priority    = 5;
            $ad->status_id   = 1;
            $ad->start_at    = now();
            $ad->end_at      = $active->expires_at;
            $ad->plan_id     = null;
            $ad->amount_paid = 0;

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
                'msg' => 'Anuncio publicado correctamente.',
                'ad'  => [
                    'id'           => $ad->id,
                    'title'        => $ad->title,
                    'status_id'    => $ad->status_id,
                    'status_text'  => 'Activo',
                    'whatsapp_url' => $this->whatsappUrlParaAd($ad),
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

    // ================================================================
    // ACTUALIZAR ANUNCIO
    // ================================================================

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || (int) $user->rol_id !== 4) {
            return response()->json([
                'res' => false,
                'msg' => 'Solo comercios pueden editar anuncios',
            ], 403);
        }

        $ad = Ad::where('id', $id)->where('merchant_id', $user->id)->first();

        if (!$ad) {
            return response()->json([
                'res' => false,
                'msg' => 'Anuncio no encontrado',
            ], 404);
        }

        $request->validate([
            'title' => 'required|string|max:150',
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
            $ad->title       = $request->title;
            $ad->description = $request->description ?? null;
            $ad->link_url    = $linkUrl ?: null;
            $ad->phone       = $phone ?: null;
            $ad->cta_text    = $request->cta_text ?? 'Ver más';

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $ext  = $file->getClientOriginalExtension() ?: 'jpg';
                $name = time() . '_' . $user->id . '.' . $ext;

                if (!file_exists(public_path('img/ads'))) {
                    mkdir(public_path('img/ads'), 0775, true);
                }

                if ($ad->image && file_exists(public_path('img/ads/' . $ad->image))) {
                    @unlink(public_path('img/ads/' . $ad->image));
                }

                $file->move(public_path('img/ads'), $name);
                $ad->image = $name;
            }

            $ad->save();

            return response()->json([
                'res' => true,
                'msg' => 'Anuncio actualizado correctamente.',
                'ad'  => [
                    'id'           => $ad->id,
                    'title'        => $ad->title,
                    'description'  => $ad->description,
                    'image_url'    => $ad->image_url,
                    'link_url'     => $ad->link_url,
                    'phone'        => $ad->phone,
                    'whatsapp_url' => $this->whatsappUrlParaAd($ad),
                ],
            ], 200);
        } catch (\Throwable $th) {
            \Log::error('[ads.update] Error: ' . $th->getMessage());

            return response()->json([
                'res' => false,
                'msg' => config('app.debug')
                    ? 'Error al actualizar: ' . $th->getMessage()
                    : 'Error al actualizar el anuncio',
            ], 500);
        }
    }

    // ================================================================
    // PANEL COMERCIO
    // ================================================================

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

    // ================================================================
    // CONSULTA MANUAL
    // ================================================================

    public function paymentStatus(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) return response()->json(['res' => false, 'msg' => 'No autenticado'], 401);

        $ad = Ad::where('id', $id)->where('merchant_id', $user->id)->first();
        if (!$ad) return response()->json(['res' => false, 'msg' => 'Anuncio no encontrado'], 404);

        return response()->json([
            'res' => true,
            'ad'  => [
                'id'                    => $ad->id,
                'title'                 => $ad->title,
                'status_id'             => $ad->status_id,
                'status_text'           => $this->getStatusTexto((int) $ad->status_id),
                'payment_status'        => $ad->payment_status,
                'payment_status_detail' => $ad->payment_status_detail,
                'payment_id'            => $ad->payment_id,
                'preference_id'         => $ad->preference_id,
                'amount_paid'           => $ad->amount_paid,
                'plan_id'               => $ad->plan_id,
                'paid_at'               => $ad->paid_at?->format('Y-m-d H:i'),
                'start_at'              => $ad->start_at?->format('Y-m-d H:i'),
                'end_at'                => $ad->end_at?->format('Y-m-d H:i'),
            ],
        ], 200);
    }

    // ================================================================
    // PROCESAMIENTO (llamado desde verificarPago)
    // ================================================================

    public function procesarPagoDeAnuncio(array $pago_mp): array
    {
        $status = $pago_mp['status'] ?? null;

        if (empty($pago_mp['external_reference']) && !empty($pago_mp['id'])) {
            $consultado = $this->consultarPagoMP((string) $pago_mp['id']);
            if ($consultado) {
                $pago_mp = array_merge($pago_mp, $consultado);
                $status  = $pago_mp['status'] ?? $status;
            }
        }

        $validStatuses = ['approved', 'pending', 'in_process', 'rejected', 'cancelled', 'refunded', 'charged_back'];

        if (!in_array($status, $validStatuses, true)) {
            return [
                'procesado' => false,
                'status'    => 'ignored',
                'message'   => "Status '{$status}' no manejado",
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
        $ad   = Ad::find($adId);

        if (!$ad) {
            return [
                'procesado' => false,
                'status'    => 'ad_not_found',
                'message'   => "Anuncio {$adId} no encontrado",
            ];
        }

        $incomingPaymentId = isset($pago_mp['id']) ? (string) $pago_mp['id'] : null;

        if ($ad->payment_status === 'approved'
            && $incomingPaymentId
            && $ad->payment_id === $incomingPaymentId) {
            return [
                'procesado' => true,
                'status'    => 'already_processed',
                'message'   => 'Este pago ya fue procesado',
                'ad_id'     => $ad->id,
            ];
        }

        $ad->payment_status        = $status;
        $ad->payment_status_detail = $pago_mp['status_detail'] ?? null;
        $ad->payment_id            = $incomingPaymentId ?? $ad->payment_id;
        $ad->preference_id         = $pago_mp['preference_id'] ?? $ad->preference_id;
        $ad->payment_method        = $pago_mp['payment_method_id'] ?? $ad->payment_method;
        $ad->payment_notified_at   = now();
        $ad->payment_payload       = $pago_mp;
        $ad->plan_id               = $planId;

        if ($status === 'approved') {
            $debeActivar = (int) $ad->status_id !== 1
                || !$ad->end_at
                || $ad->end_at->isPast();

            if ($debeActivar) {
                $ad->status_id = 1;
                $ad->start_at  = now();
                $ad->end_at    = now()->addDays($plan['dias']);
                $ad->priority  = $plan['priority'];
            }

            $ad->paid_at     = $ad->paid_at ?? now();
            $ad->amount_paid = $ad->amount_paid ?? (float) $plan['precio'];
        }

        $ad->save();

        \Log::info('[ads.procesarPagoDeAnuncio]', [
            'ad_id'          => $ad->id,
            'payment_id'     => $ad->payment_id,
            'payment_status' => $ad->payment_status,
            'plan_id'        => $planId,
            'status_id'      => $ad->status_id,
            'end_at'         => $ad->end_at,
        ]);

        return [
            'procesado'      => true,
            'status'         => $status === 'approved' ? 'success' : $status,
            'message'        => $status === 'approved'
                ? 'Anuncio activado correctamente'
                : "Pago en estado {$status} registrado",
            'ad_id'          => $ad->id,
            'payment_status' => $ad->payment_status,
        ];
    }

    public function consultarPagoMP(string $paymentId): ?array
    {
        $token = config('services.mercadopago.access_token');
        if (empty($token) || $token === 'null') return null;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://api.mercadopago.com/v1/payments/{$paymentId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => config('app.env') === 'production',
            CURLOPT_TIMEOUT        => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            \Log::error('[ads.consultarPagoMP]', [
                'payment_id' => $paymentId,
                'httpCode'   => $httpCode,
                'error'      => $error,
            ]);
            return null;
        }

        return json_decode($response, true);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function whatsappUrlParaAd(Ad $ad): ?string
    {
        if (empty($ad->phone)) {
            return null;
        }

        $num = normalizarTelefonoWhatsApp($ad->phone);

        if (!$num) {
            $digits = preg_replace('/\D+/', '', (string) $ad->phone);

            if (strlen($digits) === 10) {
                $num = '52' . $digits;
            } elseif (strlen($digits) >= 11) {
                $num = $digits;
            } else {
                return null;
            }
        }

        $mensaje = "Hola, vi tu anuncio \"{$ad->title}\" en la comunidad y me interesa más información.";

        return 'https://wa.me/' . $num . '?text=' . rawurlencode($mensaje);
    }

    /**
     * 🔑 Formato del anuncio para la comunidad (feed global)
     * - Prioriza `nombre_comercio` del usuario.
     * - Incluye `logo_url` del comercio.
     */
    private function formatearAdParaComunidad(Ad $ad): array
    {
        $merchant = $ad->merchant;

        // 🔑 Nombre comercial con fallback
        $nombreComercio = $merchant?->nombre_comercio
            ?: trim(
                ($merchant?->nombre ?? '') . ' ' .
                ($merchant?->apellido_p ?? '')
            );

        return [
            'id'           => $ad->id,
            'title'        => $ad->title,
            'description'  => $ad->description,
            'image_url'    => $ad->image_url,
            'video_url'    => $ad->video_url,
            'link_url'     => $ad->link_url,
            'phone'        => $ad->phone,
            'whatsapp_url' => $this->whatsappUrlParaAd($ad),
            'cta_text'     => $ad->cta_text,
            'merchant'     => [
                'id'       => $merchant?->id,
                'nombre_comercio'   => $nombreComercio,
                'logo_url' => $merchant?->avatar_url,
            ],
        ];
    }

    /**
     * 🔑 Formato del anuncio para el panel del comercio (dueño)
     * - Incluye `merchant_name` con el nombre comercial.
     */
    private function formatearAdParaPanel(Ad $ad): array
    {
        $merchant = $ad->merchant;

        $nombreComercio = $merchant?->nombre_comercio
            ?: trim(
                ($merchant?->nombre ?? '') . ' ' .
                ($merchant?->apellido_p ?? '')
            );

        return [
            'id'                    => $ad->id,
            'title'                 => $ad->title,
            'description'           => $ad->description,
            'image_url'             => $ad->image_url,
            'link_url'              => $ad->link_url,
            'phone'                 => $ad->phone,
            'whatsapp_url'          => $this->whatsappUrlParaAd($ad),
            'cta_text'              => $ad->cta_text,
            'status_id'             => $ad->status_id,
            'status_text'           => $this->getStatusTexto((int) $ad->status_id),
            'payment_status'        => $ad->payment_status,
            'payment_status_detail' => $ad->payment_status_detail,
            'payment_id'            => $ad->payment_id,
            'preference_id'         => $ad->preference_id,
            'payment_method'        => $ad->payment_method,
            'amount_paid'           => $ad->amount_paid,
            'plan_id'               => $ad->plan_id,
            'paid_at'               => $ad->paid_at?->format('Y-m-d H:i'),
            'priority'              => $ad->priority,
            'impressions'           => $ad->impressions,
            'clicks'                => $ad->clicks,
            'start_at'              => $ad->start_at?->format('Y-m-d H:i'),
            'end_at'                => $ad->end_at?->format('Y-m-d H:i'),
            'created_at'            => $ad->created_at?->format('Y-m-d H:i'),
            // 🔑 Nombre del comercio para el dueño
            'merchant_name'         => $nombreComercio,
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
