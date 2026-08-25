<?php

namespace App\Http\Controllers\pago;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LineaCredito;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\BrevoService;

class pagoController extends Controller
{

   /**
     * REGISTRAR UN PAGO/ABONO AL PRÉSTAMO (ASESOR)
     */
    public function registrarPago(Request $request)
{
    try {
        $user_token = $request->user();

        if (! $user_token) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        $request->validate([
            'usuario_id'         => 'required|exists:tbl_user,id',
            'monto_pago'         => 'required|numeric|min:1',
            'referencia'         => 'nullable|string|max:100',
            'observaciones'      => 'nullable|string|max:500',
            'es_pago_adelantado' => 'nullable|boolean',
            'metodo_pago'        => 'nullable|string|max:50',
        ]);

        $prestamo = Prestamo::where('usuario_id', $request->usuario_id)
            ->where('estado_prestamo_id', 2)
            ->orderBy('id', 'desc')
            ->first();

        if (! $prestamo) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene un préstamo aprobado para realizar pagos',
            ], 404);
        }

        if (in_array($prestamo->estado_prestamo_id, [3, 4])) {
            return response()->json([
                'success' => false,
                'message' => 'Este préstamo ya está ' . getEstadoTexto($prestamo->estado_prestamo_id),
            ], 400);
        }

        $es_pago_adelantado = $request->es_pago_adelantado ?? false;

        if ($prestamo->estado_prestamo_id == 2) {
            $validacion = validarFechaDesembolso($prestamo, $es_pago_adelantado);

            if (! $validacion['valido']) {
                return response()->json([
                    'success' => false,
                    'message' => $validacion['message'],
                ], 400);
            }
        }

        $deuda_actual = $prestamo->monto_restante ?? $prestamo->monto_total_pagar;

        if ($request->monto_pago > $deuda_actual) {
            return response()->json([
                'success'      => false,
                'message'      => 'El monto del pago excede la deuda actual',
                'deuda_actual' => $deuda_actual,
                'monto_maximo' => $deuda_actual,
            ], 400);
        }

        // 🔹 BUSCAR PAGO PENDIENTE
        $pagoPendiente = Pago::where('prestamo_id', $prestamo->id)
            ->where('status', 0)
            ->first();

        // 🔹 SI EXISTE PAGO PENDIENTE, ACTUALIZARLO EN LUGAR DE CREAR UNO NUEVO
        if ($pagoPendiente) {
            // ✅ ACTUALIZAR EL PAGO EXISTENTE
            $nuevo_restante = $deuda_actual - $request->monto_pago;
            $nuevos_pagos   = ($prestamo->pagos_realizados ?? 0) + 1;
            $pago_quincenal = $prestamo->monto_total_pagar / $prestamo->numero_pagos;

            $tipo_pago = 'normal';
            if ($es_pago_adelantado || $request->monto_pago > $pago_quincenal) {
                $tipo_pago = 'anticipado';
            }
            if ($nuevo_restante <= 0) {
                $tipo_pago = 'completo';
            }

            // Actualizar los datos del pago
            $pagoPendiente->monto_pagado = $request->monto_pago;
            $pagoPendiente->monto_restante = $nuevo_restante;
            $pagoPendiente->saldo_antes_pago = $deuda_actual;
            $pagoPendiente->tipo_pago = $tipo_pago;
            $pagoPendiente->es_adelantado = $es_pago_adelantado;
            $pagoPendiente->numero_quincena = $nuevos_pagos;
            $pagoPendiente->referencia = $request->referencia;
            $pagoPendiente->observaciones = $request->observaciones;
            $pagoPendiente->metodo_pago = $request->metodo_pago ?? 'Mercado Pago';
            $pagoPendiente->save();

            // 🔹 SI TIENE PREFERENCE_ID, ACTUALIZARLA EN MERCADO PAGO
            if ($pagoPendiente->preference_id) {
                // Generar nueva preferencia en Mercado Pago
                $token = config('services.mercadopago.access_token');

                if (empty($token) || $token === 'null') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Token de Mercado Pago no configurado',
                    ], 500);
                }

                $isSandbox = config('app.env') !== 'production';

                $notificationUrl = $isSandbox
                    ? 'https://deliverysobreruedas.com/verificar_pago'
                    : 'https://tudominio.com/api/webhooks/mercadopago';

                $preferenceData = [
                    'items' => [
                        [
                            'id'          => 'pago_prestamo_' . $pagoPendiente->id,
                            'title'       => 'Pago de préstamo - Folio: ' . $prestamo->folio,
                            'description' => 'Pago quincenal de préstamo #' . $prestamo->folio,
                            'quantity'    => 1,
                            'unit_price'  => (float) $request->monto_pago,
                            'currency_id' => 'MXN',
                        ],
                    ],
                    'payer' => [
                        'name'  => $user_token->name ?? 'Cliente',
                        'email' => $user_token->email ?? 'cliente@email.com',
                    ],
                    'back_urls' => [
                        'success' => $isSandbox
                            ? 'https://thing-climatic-driller.ngrok-free.dev/pago-exitoso'
                            : 'https://tudominio.com/pago-exitoso',
                        'failure' => $isSandbox
                            ? 'https://thing-climatic-driller.ngrok-free.dev/pago-fallido'
                            : 'https://tudominio.com/pago-fallido',
                        'pending' => $isSandbox
                            ? 'https://thing-climatic-driller.ngrok-free.dev/pago-pendiente'
                            : 'https://tudominio.com/pago-pendiente',
                    ],
                    'notification_url'   => $notificationUrl,
                    'external_reference' => (string) $pagoPendiente->id,
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
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error    = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error de conexión con Mercado Pago: ' . $error,
                    ], 500);
                }

                $data = json_decode($response, true);

                if ($httpCode !== 201 && $httpCode !== 200) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al actualizar preferencia: ' . ($data['message'] ?? 'Error desconocido'),
                    ], 500);
                }

                // Actualizar preference_id
                $pagoPendiente->preference_id = $data['id'];
                $pagoPendiente->save();

                // Generar datos bancarios actualizados
                $banco = config('services.banco.nombre', 'BBVA Bancomer');
                $clabe = config('services.banco.clabe', '012180004123456789');
                $cuenta = config('services.banco.cuenta', '1234567890');
                $beneficiario = config('services.banco.beneficiario', 'Tu Empresa SA de CV');
                $referencia = 'REF-' . $pagoPendiente->id . '-' . now()->format('YmdHis');

                $pagoPendiente->clabe_interbancaria = $clabe;
                $pagoPendiente->numero_cuenta = $cuenta;
                $pagoPendiente->banco_emisor = $banco;
                $pagoPendiente->nombre_beneficiario = $beneficiario;
                $pagoPendiente->referencia_banco = $referencia;
                $pagoPendiente->fecha_expiracion = now()->addDays(3);
                $pagoPendiente->save();

                 $instrucciones = [
                'pasos' => [
                    '1. Ingresa a la App Mercado Pago ',
                    '2. Selecciona tu medio de pago ',
                    '3. Corrobora el monto exacto a pagar: $' . number_format($request->monto_pago, 2) . ' MXN',
                    '4. Agrega tu tarjeta de debito o credito da clic en pagar o transfiere desde tu banca',
                    '5. La transferencia se reflejará en 24-48 horas hábiles pago con tarjeta al instante',
                ],
                'tiempo_estimado' => '24-48 horas hábiles',
            ];


                $base_url = config('app.env') === 'production'
                    ? 'https://www.mercadopago.com.mx'
                    : 'https://www.mercadopago.com.mx';

                return response()->json([
                    'success' => true,
                    'message' => 'Pago actualizado exitosamente',
                    'data'    => [
                        'pago_id'           => $pagoPendiente->id,
                        'preference_id'     => $pagoPendiente->preference_id,
                        'init_point'        => "{$base_url}/checkout/v1/redirect?pref_id={$pagoPendiente->preference_id}",
                        'monto'             => $pagoPendiente->monto_pagado,
                        'prestamo_folio'    => $prestamo->folio,
                        'es_actualizado'    => true,
                        'datos' => [
                                'instrucciones'=> $instrucciones,
                        ],
                    ],
                ], 200);
            }
        }

        // 🔹 CREAR NUEVO PAGO (SI NO EXISTE PENDIENTE)
        $nuevo_restante = $deuda_actual - $request->monto_pago;
        $nuevos_pagos   = ($prestamo->pagos_realizados ?? 0) + 1;
        $pago_quincenal = $prestamo->monto_total_pagar / $prestamo->numero_pagos;

        $tipo_pago = 'normal';
        if ($es_pago_adelantado || $request->monto_pago > $pago_quincenal) {
            $tipo_pago = 'anticipado';
        }
        if ($nuevo_restante <= 0) {
            $tipo_pago = 'completo';
        }

        $pago = Pago::create([
            'prestamo_id'               => $prestamo->id,
            'usuario_id'                => $request->usuario_id,
            'monto_pagado'              => $request->monto_pago,
            'monto_restante'            => $nuevo_restante,
            'saldo_antes_pago'          => $deuda_actual,
            'tipo_pago'                 => $tipo_pago,
            'es_adelantado'             => $es_pago_adelantado,
            'numero_quincena'           => $nuevos_pagos,
            'fecha_pago'                => now(),
            'referencia'                => $request->referencia,
            'observaciones'             => $request->observaciones,
            'metodo_pago'               => $request->metodo_pago ?? 'Mercado Pago',
            'usuario_registro'          => $user_token->id,
            'estado_pago'               => 'pendiente',
            'fecha_confirmacion'        => null,
            'fecha_desembolso_original' => $prestamo->fecha_desembolso,
            'status'                    => 0,
        ]);

        // 🔹 GENERAR PREFERENCIA EN MERCADO PAGO
        $token = config('services.mercadopago.access_token');

        if (empty($token) || $token === 'null') {
            $pago->delete();
            return response()->json([
                'success' => false,
                'message' => 'Token de Mercado Pago no configurado',
            ], 500);
        }

        $isSandbox = config('app.env') !== 'production';

        $notificationUrl = $isSandbox
            ? 'https://deliverysobreruedas.com/verificar_pago'
            : 'https://tudominio.com/api/webhooks/mercadopago';

        $preferenceData = [
            'items' => [
                [
                    'id'          => 'pago_prestamo_' . $pago->id,
                    'title'       => 'Pago de préstamo - Folio: ' . $prestamo->folio,
                    'description' => 'Pago quincenal de préstamo #' . $prestamo->folio,
                    'quantity'    => 1,
                    'unit_price'  => (float) $request->monto_pago,
                    'currency_id' => 'MXN',
                ],
            ],
            'payer' => [
                'name'  => $user_token->name ?? 'Cliente',
                'email' => $user_token->email ?? 'cliente@email.com',
            ],
            'back_urls' => [
                'success' => $isSandbox
                    ? 'https://thing-climatic-driller.ngrok-free.dev/pago-exitoso'
                    : 'https://tudominio.com/pago-exitoso',
                'failure' => $isSandbox
                    ? 'https://thing-climatic-driller.ngrok-free.dev/pago-fallido'
                    : 'https://tudominio.com/pago-fallido',
                'pending' => $isSandbox
                    ? 'https://thing-climatic-driller.ngrok-free.dev/pago-pendiente'
                    : 'https://tudominio.com/pago-pendiente',
            ],
            'notification_url'   => $notificationUrl,
            'external_reference' => (string) $pago->id,
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $pago->delete();
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión con Mercado Pago: ' . $error,
            ], 500);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 201 && $httpCode !== 200) {
            $pago->delete();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear preferencia: ' . ($data['message'] ?? 'Error desconocido'),
            ], 500);
        }

        $pago->preference_id = $data['id'];
        $pago->save();

        // 🔹 GENERAR DATOS DE TRANSFERENCIA
        $banco = config('services.banco.nombre', 'BBVA Bancomer');
        $clabe = config('services.banco.clabe', '012180004123456789');
        $cuenta = config('services.banco.cuenta', '1234567890');
        $beneficiario = config('services.banco.beneficiario', 'Tu Empresa SA de CV');
        $referencia = 'REF-' . $pago->id . '-' . now()->format('YmdHis');

        $pago->clabe_interbancaria = $clabe;
        $pago->numero_cuenta = $cuenta;
        $pago->banco_emisor = $banco;
        $pago->nombre_beneficiario = $beneficiario;
        $pago->referencia_banco = $referencia;
        $pago->fecha_expiracion = now()->addDays(3);
        $pago->save();

         $instrucciones = [
                'pasos' => [
                    '1. Ingresa a la App Mercado Pago ',
                    '2. Selecciona tu medio de pago ',
                    '3. Corrobora el monto exacto a pagar: $' . number_format($request->monto_pago, 2) . ' MXN',
                    '4. Agrega tu tarjeta de debito o credito da clic en pagar o transfiere desde tu banca',
                    '5. La transferencia se reflejará en 24-48 horas hábiles pago con tarjeta al instante',
                ],
                'tiempo_estimado' => '24-48 horas hábiles',
            ];

        return response()->json([
            'success' => true,
            'message' => 'Preferencia de pago creada exitosamente',
            'data'    => [
                'pago_id'            => $pago->id,
                'preference_id'      => $data['id'],
                'init_point'         => $data['init_point'],
                'sandbox_init_point' => $data['sandbox_init_point'] ?? null,
                'prestamo_folio'     => $prestamo->folio,
                'datos' => [
                    'instrucciones'=> $instrucciones,
                ],
            ],
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al crear la preferencia de pago',
            'error'   => config('app.debug') ? $e->getMessage() : null,
        ], 200);
    }
}


    public function verificarPago(Request $request)
    {
        try {

            if ($request->has('type') && $request->type === 'payment') {
                $payment_id = $request->input('data.id');

                if (! $payment_id) {
                    return response()->json(['message' => 'No payment id provided'], 400);
                }

                $token = config('services.mercadopago.access_token');

                if (empty($token) || $token === 'null') {
                    return response()->json(['message' => 'Token not configured'], 500);
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$payment_id}");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);


                 $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error    = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    return response()->json(['message' => 'Error de conexión'], 500);
                }

                if ($httpCode !== 200) {
                    return response()->json(['message' => 'Error al consultar pago'], 500);
                }

                $pago_mp = json_decode($response, true);
                $status = $pago_mp['status'] ?? null;

                // LOG DE LA RESPUESTA DE MERCADO PAGO
                \Log::info('RESPUESTA DE MERCADO PAGO', [
                    'payment_id' => $payment_id,
                    'status' => $status,
                    'external_reference' => $pago_mp['external_reference'] ?? null,
                    'preference_id' => $pago_mp['preference_id'] ?? null,
                    'transaction_amount' => $pago_mp['transaction_amount'] ?? null
                ]);

                // BUSCAR EL PAGO - PRIORIDAD CORRECTA
                $pago = null;

                // 1. PRIMERO: Por external_reference (más confiable)
                $pago_id = $pago_mp['external_reference'] ?? null;
                if ($pago_id) {
                    $pago = Pago::find($pago_id);
                    if ($pago) {
                        \Log::info('PAGO ENCONTRADO POR external_reference', [
                            'pago_id' => $pago->id,
                            'prestamo_id' => $pago->prestamo_id
                        ]);
                    }
                }

                // 2. SEGUNDO: Por preference_id
                if (!$pago) {
                    $preference_id = $pago_mp['preference_id'] ?? null;
                    if ($preference_id) {
                        $pago = Pago::where('preference_id', $preference_id)
                            ->where('status', 0)
                            ->first();
                        if ($pago) {
                            \Log::info('PAGO ENCONTRADO POR preference_id', [
                                'pago_id' => $pago->id,
                                'preference_id' => $preference_id
                            ]);
                        }
                    }
                }

                // 3. TERCERO: Por monto y fecha (solo si hay coincidencia exacta)
                if (!$pago) {
                    $monto = $pago_mp['transaction_amount'] ?? null;
                    $fecha = $pago_mp['date_created'] ?? null;

                    if ($monto && $fecha) {
                        $pago = Pago::where('monto_pagado', $monto)
                            ->where('status', 0)
                            ->where('fecha_pago', '>=', Carbon::parse($fecha)->subMinutes(10))
                            ->where('fecha_pago', '<=', Carbon::parse($fecha)->addMinutes(10))
                            ->first();
                        if ($pago) {
                            \Log::info('PAGO ENCONTRADO POR monto y fecha', [
                                'pago_id' => $pago->id,
                                'monto' => $monto
                            ]);
                        }
                    }
                }

                 if (!$pago) {
                \Log::warning('PAGO NO ENCONTRADO', [
                    'external_reference' => $pago_mp['external_reference'] ?? null,
                    'preference_id' => $pago_mp['preference_id'] ?? null,
                    'payment_id' => $payment_id
                ]);
                return response()->json(['message' => 'Pago no encontrado'], 404);
            }

              if ($pago->status == 1) {
                \Log::info('PAGO YA CONFIRMADO ANTERIORMENTE', [
                    'pago_id' => $pago->id,
                    'prestamo_id' => $pago->prestamo_id
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Pago ya confirmado anteriormente'
                ], 200);
            }

            // LOG DEL PAGO ENCONTRADO
            \Log::info('PAGO ENCONTRADO PARA PROCESAR', [
                'pago_id' => $pago->id,
                'prestamo_id' => $pago->prestamo_id,
                'monto_pagado' => $pago->monto_pagado,
                'status_actual' => $pago->status,
                'status_mp' => $status
            ]);

                $status = $pago_mp['status'] ?? null;

                $pago->no_pago_mp         = $payment_id;
                $pago->fecha_verificacion = now();
                $pago->status_mp          = $status;
                $pago->payment_method     = $pago_mp['payment_method_id'] ?? $pago->metodo_pago;

                if (isset($pago_mp['transaction_details']['external_resource_url'])) {
                    $pago->url_pdf = $pago_mp['transaction_details']['external_resource_url'];
                }

                if ($status === 'approved') {
                    $result = $this->aplicarPagoAlPrestamo($pago);

                    if ($result['success']) {
                        $pago->status             = 1;
                        $pago->estado_pago        = 'confirmado';
                        $pago->fecha_confirmacion = now();
                        $pago->usuario_confirmo   = $pago->usuario_id;
                        $pago->metodo_pago        = 'Mercado Pago Online';
                        $pago->save();

                        $pdfGenerado = false;
                        $rutaPdf     = null;

                        $prestamo = Prestamo::with(['usuario', 'lineaCredito'])->find($pago->prestamo_id);

                        if ($prestamo && $prestamo->monto_restante <= 0) {
                            $asesor = null;
                            if ($pago->usuario_registro) {
                                $asesor = \App\Models\User::find($pago->usuario_registro);
                            }


                            $pdfResult = $this->generarPdfLiquidacionWebhook($prestamo, $asesor);



                            if ($pdfResult['success']) {
                                $pdfGenerado = true;
                                $rutaPdf = $pdfResult['ruta'];
                                $absolutePath = $pdfResult['absolute_path'] ?? null;
                            }


                             if ($absolutePath && file_exists($absolutePath)) {
                                try {
                                    $brevoService = new \App\Services\BrevoService();
                                    $brevoService->sendLoanLiquidatedEmail($prestamo, $prestamo->usuario, $absolutePath);

                                    \Log::info('Correo de liquidación enviado desde webhook', [
                                        'folio' => $prestamo->folio,
                                        'pago_id' => $pago->id
                                    ]);
                                } catch (\Exception $e) {
                                    \Log::error('Error al enviar correo de liquidación desde webhook', [
                                        'folio' => $prestamo->folio,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            } else {
                                \Log::warning('No se pudo enviar correo de liquidación desde webhook', [
                                    'folio' => $prestamo->folio,
                                    'absolute_path' => $absolutePath,
                                    'pdf_generado' => $pdfGenerado
                                ]);
                            }
                        }

                        return response()->json([
                            'success' => true,
                            'message' => 'Pago confirmado exitosamente',
                            'data'    => array_merge($result['data'], [
                                'pdf_liquidacion' => $pdfGenerado ? [
                                    'generado'     => true,
                                    'ruta'         => $rutaPdf,
                                    'url_descarga' => $rutaPdf ? asset("storage/prestamos/liquidacion/" . basename($rutaPdf)) : null,
                                ] : [
                                    'generado' => false,
                                    'mensaje'  => 'El préstamo no está completamente liquidado o no se pudo generar el PDF',
                                ],
                            ]),
                        ], 200);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Error al aplicar el pago: ' . $result['message'],
                        ], 500);
                    }
                } elseif ($status === 'pending') {
                    $pago->estado_pago = 'pendiente_mp';
                    $pago->save();
                    return response()->json([
                        'success' => true,
                        'message' => 'Pago pendiente de confirmación',
                    ], 200);
                } elseif ($status === 'rejected') {
                    $pago->estado_pago = 'rechazado';
                    $pago->status      = 2;
                    $pago->save();
                    return response()->json([
                        'success' => false,
                        'message' => 'Pago rechazado',
                    ], 200);
                } else {
                    $pago->estado_pago = $status ?? 'desconocido';
                    $pago->save();
                    return response()->json([
                        'success' => true,
                        'message' => 'Estado recibido: ' . ($status ?? 'desconocido'),
                    ], 200);
                }
            }

            return response()->json(['message' => 'Notificación procesada'], 200);
        } catch (\Exception $e) {
           \Log::error('ERROR EN WEBHOOK', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error procesando webhook',
        ], 500);
        }
    }


     /**
     * GENERAR PDF DE LIQUIDACIÓN DESDE WEBHOOK
     */
    private function generarPdfLiquidacionWebhook($prestamo, $usuario = null)
    {
        try {
            if ($prestamo->monto_restante > 0) {
                return ['success' => false, 'message' => 'El préstamo no está liquidado'];
            }

            $prestamo->load(['usuario', 'lineaCredito', 'pagos' => function ($query) {
                $query->where('status', 1)->orderBy('fecha_pago', 'asc');
            }]);

            if (! $usuario && $prestamo->pagos->isNotEmpty()) {
                $ultimoPago = $prestamo->pagos->last();
                if ($ultimoPago->usuario_registro) {
                    $usuario = \App\Models\User::find($ultimoPago->usuario_registro);
                }
            }

            $data = [
                'prestamo'          => $prestamo,
                'usuario'           => $prestamo->usuario,
                'linea_credito'     => $prestamo->lineaCredito,
                'fecha_generacion'  => now()->format('d/m/Y H:i:s'),
                'estado_texto'      => 'LIQUIDADO',
                'tipo_documento'    => 'liquidacion',
                'asesor'            => $usuario,
                'pagos'             => $prestamo->pagos,
                'total_pagado'      => $prestamo->pagos->sum('monto_pagado'),
                'fecha_liquidacion' => $prestamo->fecha_liquidacion ?? now(),
            ];

            $pdf = Pdf::loadView('pdf.prestamo-liquidacion', $data);
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
            ]);

            $filename = "liquidacion_{$prestamo->folio}_" . now()->format('Ymd_His') . ".pdf";
            $folder   = 'liquidacion';

            $dbPath       = "public/prestamos/{$folder}/" . $filename;
            $basePath     = storage_path('app/public/prestamos/' . $folder);
            $absolutePath = $basePath . DIRECTORY_SEPARATOR . $filename;

            if (! is_dir($basePath)) {
                if (! mkdir($basePath, 0777, true)) {
                    throw new \Exception("No se pudo crear el directorio: {$basePath}");
                }
            }

            $pdfContent = $pdf->output();
            file_put_contents($absolutePath, $pdfContent);

            $prestamo->ruta_pdf_liquidacion = $dbPath;
            $prestamo->save();

            return [
                'success' => true,
            'ruta' => $dbPath,
            'nombre' => $filename,
            'folder' => $folder,
            'absolute_path' => $absolutePath,
            'url' => asset("storage/prestamos/{$folder}/" . $filename),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * APLICAR PAGO AL PRÉSTAMO
     */
   private function aplicarPagoAlPrestamo($pago)
{
    try {
        if ($pago->status == 1) {
            return ['success' => false, 'message' => 'Pago ya aplicado anteriormente'];
        }

        DB::beginTransaction();

        $prestamo = Prestamo::where('id', $pago->prestamo_id)
            ->lockForUpdate()
            ->first();

        if (!$prestamo) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Préstamo no encontrado'];
        }

        $deuda_actual = $prestamo->monto_restante ?? $prestamo->monto_total_pagar;
        $monto_pagado = $pago->monto_pagado;

        if ($monto_pagado > $deuda_actual) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'El monto del pago excede la deuda actual',
                'deuda_actual' => $deuda_actual,
                'monto_pagado' => $monto_pagado,
            ];
        }

        $nuevo_restante = $deuda_actual - $monto_pagado;
        $prestamo->monto_restante = max(0, $nuevo_restante);
        $prestamo->pagos_realizados = ($prestamo->pagos_realizados ?? 0) + 1;
        $prestamo->fecha_ultimo_pago = now();

        $prestamo_quedo_liquidado = false;

        if ($prestamo->monto_restante <= 0) {
            $prestamo->estado_prestamo_id = 3; // Pagado
            $prestamo->fecha_fin = now();
            $prestamo->fecha_liquidacion = now();
            $prestamo->monto_restante = 0;
            $prestamo_quedo_liquidado = true;
        }

        $prestamo->save();

        // SI EL PRÉSTAMO QUEDÓ LIQUIDADO, APLICAR INCREMENTO
        if ($prestamo_quedo_liquidado) {
            $this->aplicarIncrementoPorHistorial($prestamo);
        }

        $pago->monto_restante = $prestamo->monto_restante;
        $pago->save();

        DB::commit();

        return [
            'success' => true,
            'data' => [
                'prestamo_id' => $prestamo->id,
                'nuevo_restante' => $prestamo->monto_restante,
                'estado' => $prestamo->estado_prestamo_id,
                'pagos_realizados' => $prestamo->pagos_realizados,
                'fecha_ultimo_pago' => $prestamo->fecha_ultimo_pago,
            ],
        ];
    } catch (\Exception $e) {
        DB::rollBack();
        return [
            'success' => false,
            'message' => 'Error al aplicar el pago: ' . $e->getMessage(),
        ];
    }
}

/**
 * APLICAR INCREMENTO POR BUEN HISTORIAL DE PAGO
 */
private function aplicarIncrementoPorHistorial($prestamo)
{
    $usuario_id = $prestamo->usuario_id;

    // 1. Buscar si hay préstamos pagados sin incremento
    $ultimo_prestamo_pagado = Prestamo::where('usuario_id', $usuario_id)
        ->where('estado_prestamo_id', 3)
        ->where('incremento_aplicado', 0)
        ->where('id', '!=', $prestamo->id) // Excluir el actual
        ->orderBy('id', 'desc')
        ->first();

    if (!$ultimo_prestamo_pagado) {
        // No hay incremento pendiente, solo reactivar línea de crédito
        $lineaCredito = LineaCredito::find($prestamo->linea_credito_id);
        if ($lineaCredito) {
            $lineaCredito->estatus_id = 1;
            $lineaCredito->save();
        }
        return null;
    }

    // 2. Buscar línea de crédito
    $linea_credito = LineaCredito::where('usuario_id', $usuario_id)
        ->where('estatus_id', 1)
        ->first();

    if (!$linea_credito) {
        // Si no hay línea activa, crear una nueva
        $nuevo_limite = 200;
        $linea_credito = LineaCredito::create([
            'usuario_id' => $usuario_id,
            'limite_aprobado' => $nuevo_limite,
            'limite_disponible' => $nuevo_limite,
            'estatus_id' => 1,
        ]);
        return null;
    }

    // 3. APLICAR INCREMENTO ENTERO
    $incremento = calcular_incremento_entero($ultimo_prestamo_pagado->monto_total_pagar, 'ceil');
    $nuevo_limite = (int) ($linea_credito->limite_aprobado + $incremento);
    $nuevo_disponible = (int) ($linea_credito->limite_disponible + $incremento);

    $linea_credito->limite_aprobado = $nuevo_limite;
    $linea_credito->limite_disponible = $nuevo_disponible;
    $linea_credito->estatus_id = 1; // Activa
    $linea_credito->save();

    // 4. Marcar el préstamo como incrementado
    $ultimo_prestamo_pagado->incremento_aplicado = 1;
    $ultimo_prestamo_pagado->fecha_incremento = now();
    $ultimo_prestamo_pagado->save();

    // 5. También asegurarse de que el préstamo actual no tenga incremento pendiente
    $prestamo->incremento_aplicado = 0; // Este será el que genere el próximo incremento
    $prestamo->save();

    return $incremento;
}


}
