<?php

namespace App\Http\Controllers\comunidad;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    private const COUPON_CODE       = 'SUSCRIPCION-ANUAL';
    private const ANNUAL_FREE_DAYS  = 365;
    private const MONTHLY_PRICE_MXN = 120;
    private const MONTHLY_DAYS      = 30;

    // ================================================================
    // POST /subscription/redeem  — canjea el cupón anual gratuito
    // ================================================================
      public function redeem(Request $request)
    {
        $user = $request->user();

        if (!$user || (int) $user->rol_id !== 4) {
            return response()->json(['res' => false, 'msg' => 'Solo comercios'], 403);
        }

        $request->validate(['coupon' => 'required|string']);
        $couponCode = strtoupper(trim($request->coupon));

        // 1. Si ya tiene una suscripción activa, no lo dejamos canjear otra
        $existing = Subscription::activeForUser($user->id);
        if ($existing) {
            return response()->json([
                'res'              => true,
                'already_redeemed' => true,
                'subscription'     => Subscription::statusPayloadForUser($user->id),
                'msg'              => 'Ya tienes una suscripción activa.',
            ]);
        }

        // 2. Validar el cupón en la base de datos (existe, activo, con usos y dentro del rango de fecha)
        $cupon = DB::selectOne(
            'SELECT * FROM tbl_coupons
             WHERE code = ?
               AND status = 1
               AND type = ?
               AND used_count < max_uses
             LIMIT 1',
            [$couponCode, 'subscription'] // ✅ 2 tokens, 2 parámetros
        );

        if (!$cupon) {
            return response()->json([
                'res' => false,
                'msg' => 'Cupón inválido o sin usos disponibles.',
                'code' => 'invalid_coupon',
            ], 422);
        }

        // 3. Validar que la fecha de HOY esté dentro del rango del cupón
        $now = now();
        if ($now < $cupon->valid_from) {
            return response()->json([
                'res' => false,
                'msg' => 'Este cupón aún no está vigente.',
                'code' => 'coupon_not_started',
            ], 422);
        }

        if ($now > $cupon->valid_until) {
            return response()->json([
                'res' => false,
                'msg' => 'Este cupón ya expiró.',
                'code' => 'coupon_expired',
            ], 422);
        }

        // 4. Verificar que este usuario no haya usado este cupón
        $alreadyUsedByUser = DB::selectOne(
            'SELECT id FROM tbl_subscriptions WHERE user_id = ? AND coupon_code = ? LIMIT 1',
            [$user->id, $couponCode]
        );

        if ($alreadyUsedByUser) {
            return response()->json([
                'res' => false,
                'msg' => 'Ya usaste este cupón anteriormente.',
                'code' => 'coupon_already_used',
            ], 409);
        }

        try {
            DB::beginTransaction();

            // 5. Registrar la suscripción con las FECHAS EXACTAS del cupón
            DB::insert(
                'INSERT INTO tbl_subscriptions
                 (user_id, plan, status, price_mxn, coupon_code, starts_at, expires_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $user->id,
                    $cupon->plan,
                    'active',
                    0,
                    $couponCode,
                    $cupon->valid_from,
                    $cupon->valid_until,
                ]
            );

            // 6. Aumentar el contador de usos
            DB::update(
                'UPDATE tbl_coupons SET used_count = used_count + 1, updated_at = NOW() WHERE id = ?',
                [$cupon->id]
            );

            DB::commit();

            return response()->json([
                'res'          => true,
                'subscription' => Subscription::statusPayloadForUser($user->id),
                'msg'          => '¡Suscripción activada con éxito!',
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[subscription.redeem] ' . $th->getMessage());
            return response()->json([
                'res' => false,
                'msg' => 'No se pudo activar el cupón.',
            ], 500);
        }
    }

    // ================================================================
    // POST /subscription/renew — pago mensual $120 vía MercadoPago
    // ================================================================
        public function renew(Request $request)
    {
        $user = $request->user();

        if (!$user || (int) $user->rol_id !== 4) {
            return response()->json(['res' => false, 'msg' => 'Solo comercios'], 403);
        }

        $token = config('services.mercadopago.access_token');
        if (empty($token) || $token === 'null') {
            return response()->json(['res' => false, 'msg' => 'Token de Mercado Pago no configurado'], 500);
        }

        $notificationUrl = config('services.mercadopago.notification_url');
        $frontendUrl     = rtrim(config('services.mercadopago.frontend_url'), '/');

        if (empty($notificationUrl)) {
            return response()->json(['res' => false, 'msg' => 'MERCADOPAGO_NOTIFICATION_URL no configurada'], 500);
        }

        try {
            DB::beginTransaction();

            // 🔑 1. LIMPIEZA: Borrar intentos de pago abandonados (más de 24h)
            DB::delete(
                'DELETE FROM tbl_subscriptions
                 WHERE user_id = ?
                   AND status = ?
                   AND created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
                [$user->id, 'pending_payment']
            );

            // 🔑 2. BUSCAR SI YA EXISTE UNA SOLICITUD PENDIENTE RECIENTE
            $pending = DB::selectOne(
                'SELECT id, preference_id FROM tbl_subscriptions
                 WHERE user_id = ?
                   AND status = ?
                   AND plan = ?
                 ORDER BY id DESC LIMIT 1',
                [$user->id, 'pending_payment', 'monthly_paid']
            );

            // 3. GENERAR PREFERENCIA EN MERCADO PAGO
            $preferenceData = [
                'items' => [[
                    'id'          => 'sub_' . $user->id,
                    'title'       => 'Suscripción mensual Delivery Sobre Ruedas',
                    'description' => 'Publicaciones ilimitadas por 30 días',
                    'quantity'    => 1,
                    'unit_price'  => self::MONTHLY_PRICE_MXN,
                    'currency_id' => 'MXN',
                ]],
                'payer' => [
                    'name'  => trim(($user->nombre ?? '') . ' ' . ($user->apellido_p ?? '')),
                    'email' => $user->email ?? 'comercio@email.com',
                ],
                'back_urls' => [
                    'success' => "{$frontendUrl}/pago-pendiente",
                    'failure' => "{$frontendUrl}/pago-pendiente",
                    'pending' => "{$frontendUrl}/pago-pendiente",
                ],
                'notification_url'   => $notificationUrl,
                'external_reference' => "sub_{$user->id}",
                'auto_return'        => 'approved',
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://api.mercadopago.com/checkout/preferences',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($preferenceData),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => config('app.env') === 'production',
                CURLOPT_TIMEOUT        => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                DB::rollBack();
                return response()->json(['res' => false, 'msg' => 'Error de conexión con Mercado Pago: ' . $error], 500);
            }

            $data = json_decode($response, true);

            if ($httpCode !== 201 && $httpCode !== 200) {
                DB::rollBack();
                return response()->json([
                    'res' => false,
                    'msg' => 'Error al crear preferencia: ' . ($data['message'] ?? 'Error desconocido'),
                ], 500);
            }

            // 4. GUARDAR O ACTUALIZAR EN LA BASE DE DATOS
            if ($pending) {
                // 🔑 4A. SI YA EXISTE → ACTUALIZAMOS LA PREFERENCIA, INIT_POINT Y FECHAS
                DB::update(
                    'UPDATE tbl_subscriptions
                     SET preference_id = ?,
                         init_point = ?,
                         starts_at = NOW(),
                         expires_at = DATE_ADD(NOW(), INTERVAL ? DAY),
                         updated_at = NOW()
                     WHERE id = ?',
                    [
                        $data['id'],          // ? preference_id
                        $data['init_point'],  // ? init_point
                        self::MONTHLY_DAYS,   // ? INTERVAL DAY
                        $pending->id,         // ? WHERE id
                    ]
                );

                $subscriptionId = $pending->id;
                $isUpdate = true;

            } else {
                // 🔑 4B. SI NO EXISTE → INSERTAMOS UNA NUEVA SOLICITUD
                DB::insert(
                    'INSERT INTO tbl_subscriptions
                     (user_id, plan, status, price_mxn, preference_id, init_point, starts_at, expires_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), NOW(), NOW())',
                    [
                        $user->id,             // ? user_id
                        'monthly_paid',        // ? plan
                        'pending_payment',     // ? status
                        self::MONTHLY_PRICE_MXN, // ? price_mxn
                        $data['id'],           // ? preference_id
                        $data['init_point'],   // ? init_point
                        self::MONTHLY_DAYS,    // ? INTERVAL DAY
                    ]
                );

                $subscriptionId = DB::getPdo()->lastInsertId();
                $isUpdate = false;
            }

            DB::commit();

            return response()->json([
                'res'             => true,
                'init_point'      => $data['init_point'],
                'preference_id'   => $data['id'],
                'subscription_id' => (int) $subscriptionId,
                'is_update'       => $isUpdate,
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('[subscription.renew] ' . $th->getMessage());
            return response()->json([
                'res' => false,
                'msg' => 'No se pudo generar la preferencia de pago.',
            ], 500);
        }
    }
    // ================================================================
    // GET /subscription/status
    // ================================================================
   public function status(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['res' => false, 'msg' => 'No autenticado'], 401);
    }

    // 1. Limpieza: marcar como 'expired' las que ya vencieron
    DB::update(
        'UPDATE tbl_subscriptions
         SET status = ?, updated_at = NOW()
         WHERE user_id = ? AND status = ? AND expires_at <= NOW()',
        ['expired', $user->id, 'active']
    );

    // 2. Buscar si hay un pending_payment activo
    $pending = DB::selectOne(
        'SELECT id, preference_id, init_point, created_at
         FROM tbl_subscriptions
         WHERE user_id = ? AND status = ?
         ORDER BY id DESC LIMIT 1',
        [$user->id, 'pending_payment']
    );

    // 🔑 3. Buscar si el último intento fue cancelado/rechazado
    $rejected = DB::selectOne(
        'SELECT id, preference_id, init_point, created_at
         FROM tbl_subscriptions
         WHERE user_id = ? AND status = ?
         ORDER BY id DESC LIMIT 1',
        [$user->id, 'cancelled']
    );

    $payload = Subscription::statusPayloadForUser($user->id);

    // 4. Añadir info extra si hay pago pendiente
    if ($pending) {
        $payload['pending'] = [
            'id'            => $pending->id,
            'preference_id' => $pending->preference_id,
            'init_point'    => $pending->init_point,
            'created_at'    => $pending->created_at,
        ];
    }

    // 🔑 5. Añadir info si el último pago fue rechazado
    if ($rejected && !$payload['is_active']) {
        $payload['rejected'] = [
            'id'            => $rejected->id,
            'preference_id' => $rejected->preference_id,
            'init_point'    => $rejected->init_point,
            'created_at'    => $rejected->created_at,
        ];
    }

    return response()->json([
        'res'          => true,
        'subscription' => $payload,
    ]);
}

    // ================================================================
    // Webhook: procesa pago "sub_XXX" de MercadoPago
    // ================================================================
    public function procesarPagoDeSuscripcion(array $pago_mp): array
    {
        $status = $pago_mp['status'] ?? null;

        if (!in_array($status, ['approved', 'pending', 'rejected', 'cancelled'], true)) {
            return ['procesado' => false, 'status' => 'ignored'];
        }

        $ref = $pago_mp['external_reference'] ?? '';

        if (!preg_match('/^sub_(\d+)$/', (string) $ref, $m)) {
            return ['procesado' => false, 'status' => 'invalid_ref'];
        }

        $userId = (int) $m[1];

        if ($status === 'approved') {
            $pending = DB::selectOne(
                'SELECT id FROM tbl_subscriptions
                 WHERE user_id = ? AND status = ?
                 ORDER BY id DESC LIMIT 1',
                [$userId, 'pending_payment']
            );

            $paymentId = (string) ($pago_mp['id'] ?? '');

            if ($pending) {
                $current = Subscription::activeForUser($userId);
                $base = $current ? $current->expires_at : now();

                DB::update(
                    'UPDATE tbl_subscriptions
                     SET status = ?,
                         payment_id = ?,
                         starts_at = COALESCE(starts_at, NOW()),
                         expires_at = DATE_ADD(?, INTERVAL ? DAY),
                         updated_at = NOW()
                     WHERE id = ?',
                    [
                        'active',
                        $paymentId,
                        $base,
                        self::MONTHLY_DAYS,
                        $pending->id,
                    ]
                );
            } else {
                DB::insert(
                    'INSERT INTO tbl_subscriptions
                     (user_id, plan, status, price_mxn, payment_id, starts_at, expires_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), NOW(), NOW())',
                    [
                        $userId,
                        'monthly_paid',
                        'active',
                        self::MONTHLY_PRICE_MXN,
                        $paymentId,
                        self::MONTHLY_DAYS,
                    ]
                );
            }

            Log::info('[subscription.procesarPago] aprobada', ['user_id' => $userId]);
            return ['procesado' => true, 'status' => 'success', 'user_id' => $userId];
        }

        DB::update(
            'UPDATE tbl_subscriptions
             SET status = ?, payment_id = ?, updated_at = NOW()
             WHERE user_id = ? AND status = ?',
            [
                $status === 'pending' ? 'pending_payment' : 'cancelled',
                (string) ($pago_mp['id'] ?? ''),
                $userId,
                'pending_payment',
            ]
        );

        return ['procesado' => true, 'status' => $status, 'user_id' => $userId];
    }

    public function availableCoupons(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['res' => false, 'msg' => 'No autenticado'], 401);

        try {
            // Buscamos SOLO los cupones de tipo suscripción, activos y con usos
            $cupones = DB::select(
                'SELECT code, type, plan, valid_until
                 FROM tbl_coupons
                 WHERE status = 1
                   AND type = ?
                   AND used_count < max_uses
                 ORDER BY id DESC',
                ['subscription']
            );

            return response()->json([
                'res'     => true,
                'coupons' => $cupones
            ]);
        } catch (\Throwable $th) {
            \Log::error('[availableCoupons] Error SQL: ' . $th->getMessage());
            return response()->json([
                'res' => false,
                'msg' => 'Error al consultar cupones en la BD'
            ], 500);
        }
    }
}
