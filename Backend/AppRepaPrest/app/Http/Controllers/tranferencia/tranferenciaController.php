<?php

namespace App\Http\Controllers\tranferencia;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LineaCredito;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class tranferenciaController extends Controller
{
    /**
     * REGISTRAR UN PAGO CON TRANSFERENCIA (SPEI/PIX)
     */
    public function registrarPagoTransferencia(Request $request)
    {
        try {
            $user_token = $request->user();

            if (!$user_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $request->validate([
                'usuario_id' => 'required|exists:tbl_user,id',
                'monto_pago' => 'required|numeric|min:1',
                'referencia' => 'nullable|string|max:100',
                'observaciones' => 'nullable|string|max:500',
                'es_pago_adelantado' => 'nullable|boolean',
                'metodo_pago' => 'nullable|string|max:50',
                'tipo_transferencia' => 'nullable|in:spei,pix',
            ]);

            // Validar préstamo
            $prestamo = Prestamo::where('usuario_id', $request->usuario_id)
                ->where('estado_prestamo_id', 2)
                ->orderBy('id', 'desc')
                ->first();

            if (!$prestamo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene un préstamo aprobado',
                ], 404);
            }

            if (in_array($prestamo->estado_prestamo_id, [3, 4])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este préstamo ya está ' . getEstadoTexto($prestamo->estado_prestamo_id),
                ], 400);
            }

            $deuda_actual = $prestamo->monto_restante ?? $prestamo->monto_total_pagar;

            if ($request->monto_pago > $deuda_actual) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto del pago excede la deuda actual',
                    'deuda_actual' => $deuda_actual,
                ], 400);
            }

            // Verificar pago pendiente
            $pagoPendiente = Pago::where('prestamo_id', $prestamo->id)
                ->where('status', 0)
                ->where('metodo_pago', 'LIKE', '%Transferencia%')
                ->first();

            if ($pagoPendiente && $pagoPendiente->clabe_interbancaria) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ya existe una solicitud de transferencia pendiente',
                    'data' => [
                        'pago_id' => $pagoPendiente->id,
                        'datos_transferencia' => [
                            'banco' => $pagoPendiente->banco_emisor,
                            'clabe' => $pagoPendiente->clabe_interbancaria,
                            'cuenta' => $pagoPendiente->numero_cuenta,
                            'beneficiario' => $pagoPendiente->nombre_beneficiario,
                            'referencia' => $pagoPendiente->referencia_banco,
                            'monto' => $pagoPendiente->monto_pagado,
                            'fecha_expiracion' => $pagoPendiente->fecha_expiracion,
                        ],
                    ],
                ], 200);
            }

            // Crear registro de pago
            $nuevo_restante = $deuda_actual - $request->monto_pago;
            $nuevos_pagos = ($prestamo->pagos_realizados ?? 0) + 1;
            $pago_quincenal = $prestamo->monto_total_pagar / $prestamo->numero_pagos;

            $tipo_pago = 'normal';
            if ($request->es_pago_adelantado || $request->monto_pago > $pago_quincenal) {
                $tipo_pago = 'anticipado';
            }
            if ($nuevo_restante <= 0) {
                $tipo_pago = 'completo';
            }

            $pago = Pago::create([
                'prestamo_id' => $prestamo->id,
                'usuario_id' => $request->usuario_id,
                'monto_pagado' => $request->monto_pago,
                'monto_restante' => $nuevo_restante,
                'saldo_antes_pago' => $deuda_actual,
                'tipo_pago' => $tipo_pago,
                'es_adelantado' => $request->es_pago_adelantado ?? false,
                'numero_quincena' => $nuevos_pagos,
                'fecha_pago' => now(),
                'referencia' => $request->referencia,
                'observaciones' => $request->observaciones,
                'metodo_pago' => $request->tipo_transferencia === 'pix' ? 'PIX' : 'Transferencia SPEI',
                'usuario_registro' => $user_token->id,
                'estado_pago' => 'esperando_transferencia',
                'fecha_confirmacion' => null,
                'fecha_desembolso_original' => $prestamo->fecha_desembolso,
                'status' => 0,
            ]);

            // GENERAR DATOS DE TRANSFERENCIA
            $datosTransferencia = $this->generarDatosTransferenciaMP($pago, $request->tipo_transferencia ?? 'spei');

            if (!$datosTransferencia['success']) {
                $pago->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar los datos de transferencia: ' . $datosTransferencia['message'],
                    'detalles' => $datosTransferencia['detalles'] ?? null,
                ], 500);
            }

            // Guardar TODOS los datos de transferencia en el pago
            $pago->preference_id = $datosTransferencia['preference_id'] ?? null;
            $pago->clabe_interbancaria = $datosTransferencia['clabe'] ?? null;
            $pago->numero_cuenta = $datosTransferencia['cuenta'] ?? null;
            $pago->banco_emisor = $datosTransferencia['banco'] ?? null;
            $pago->nombre_beneficiario = $datosTransferencia['beneficiario'] ?? null;
            $pago->referencia_banco = $datosTransferencia['referencia'] ?? null;
            $pago->fecha_expiracion = $datosTransferencia['fecha_expiracion'] ?? null;
            $pago->save();

            return response()->json([
                'success' => true,
                'message' => 'Datos de transferencia generados exitosamente',
                'data' => [
                    'pago_id' => $pago->id,
                    'preference_id' => $pago->preference_id,
                    'init_point' => $datosTransferencia['init_point'] ?? null,
                    'sandbox_init_point' => $datosTransferencia['sandbox_init_point'] ?? null,
                    'datos_transferencia' => [
                        'banco' => $pago->banco_emisor ?? config('services.banco.nombre', 'BBVA Bancomer'),
                        'clabe' => $pago->clabe_interbancaria ?? config('services.banco.clabe', '012180004123456789'),
                        'cuenta' => $pago->numero_cuenta ?? config('services.banco.cuenta', '1234567890'),
                        'beneficiario' => $pago->nombre_beneficiario ?? config('services.banco.beneficiario', 'Tu Empresa SA de CV'),
                        'referencia' => $pago->referencia_banco ?? 'REF-' . $pago->id,
                        'monto' => (float) $pago->monto_pagado,
                        'concepto' => "Pago préstamo {$prestamo->folio}",
                        'fecha_expiracion' => $pago->fecha_expiracion ?? now()->addDays(3)->toDateTimeString(),
                        'instrucciones' => $this->getInstruccionesTransferencia($request->tipo_transferencia ?? 'spei'),
                    ],
                    'prestamo_folio' => $prestamo->folio,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en registrarPagoTransferencia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar datos de transferencia',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GENERAR DATOS DE TRANSFERENCIA CON MERCADO PAGO
     */
    private function generarDatosTransferenciaMP($pago, $tipo = 'spei')
    {
        try {
            $token = config('services.mercadopago.access_token');

            if (empty($token) || $token === 'null') {
                return ['success' => false, 'message' => 'Token de Mercado Pago no configurado'];
            }

            $prestamo = Prestamo::find($pago->prestamo_id);
            $usuario = User::find($pago->usuario_id);

            // 1. CREAR PREFERENCIA EN MERCADO PAGO
            $preferenceData = [
    // 1. CONFIGURACIÓN DE MÉTODOS DE PAGO (UNIFICADA)
    'payment_methods' => [
        // Excluir tarjetas de crédito/débito y otros métodos
        'excluded_payment_methods' => [
            ['id' => 'amex'],
            ['id' => 'visa'],
            ['id' => 'master'],
            ['id' => 'maestro'],
            ['id' => 'cabal'],
            ['id' => 'argencard'],
            ['id' => 'naranja'],
            ['id' => 'tarshop'],
            ['id' => 'cencosud'],
            ['id' => 'cordobesa'],
            ['id' => 'cmr'],
        ],
        'excluded_payment_types' => [
            ['id' => 'credit_card'],
            ['id' => 'debit_card'],
            ['id' => 'ticket'],        // Oxxo, 7-Eleven, etc.
            ['id' => 'atm'],           // Retiro en efectivo
            ['id' => 'digital_currency'],
            ['id' => 'wallet_purchase'],
        ],
        'excluded_payment_types' => [
            ['id' => 'credit_card'],
            ['id' => 'debit_card'],
            ['id' => 'ticket'],
            ['id' => 'atm'],
            ['id' => 'digital_currency'],
            ['id' => 'wallet_purchase'],
        ],
        'installments' => 1,
    ],

    // 2. DATOS DEL PRODUCTO/SERVICIO
    'items' => [
        [
            'id' => 'pago_prestamo_' . $pago->id,
            'title' => 'Pago de préstamo - Folio: ' . ($prestamo->folio ?? ''),
            'description' => 'Pago quincenal de préstamo #' . ($prestamo->folio ?? ''),
            'quantity' => 1,
            'unit_price' => (float) $pago->monto_pagado,
            'currency_id' => 'MXN',
        ],
    ],

    // 3. DATOS DEL PAGADOR
    'payer' => [
        'name' => $usuario->name ?? 'Cliente',
        'email' => $usuario->email ?? 'cliente@email.com',
    ],

    // 4. URLS DE RETORNO
    'back_urls' => [
        'success' => config('app.env') !== 'production'
            ? 'https://thing-climatic-driller.ngrok-free.dev/pago-exitoso'
            : 'https://tudominio.com/pago-exitoso',
        'failure' => config('app.env') !== 'production'
            ? 'https://thing-climatic-driller.ngrok-free.dev/pago-fallido'
            : 'https://tudominio.com/pago-fallido',
        'pending' => config('app.env') !== 'production'
            ? 'https://thing-climatic-driller.ngrok-free.dev/pago-pendiente'
            : 'https://tudominio.com/pago-pendiente',
    ],

    // 5. WEBHOOK PARA NOTIFICACIONES
    'notification_url' => config('app.env') !== 'production'
        ? 'https://thing-climatic-driller.ngrok-free.dev/verificar_pago'
        : 'https://tudominio.com/api/webhooks/mercadopago',

    // 6. REFERENCIA EXTERNA (VINCULO CON TU BD)
    'external_reference' => (string) $pago->id,

    // 7. CONFIGURACIÓN ADICIONAL
    'auto_return' => 'approved',
    'expires' => true,
    'expiration_date_to' => now()->addDays(3)->toIso8601String(),

    'default_payment_method_id' => 'transferencia', // Solo si está disponible en tu país
];

            // Llamar a la API de Mercado Pago
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
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'message' => 'Error de conexión: ' . $error];
            }

            $data = json_decode($response, true);

            if ($httpCode !== 201 && $httpCode !== 200) {
                $errorMsg = $data['message'] ?? 'Error desconocido';
                Log::error('Error MP Transferencia', [
                    'http_code' => $httpCode,
                    'response' => $data
                ]);

                return [
                    'success' => false,
                    'message' => 'Error al crear preferencia: ' . $errorMsg,
                    'detalles' => $data,
                ];
            }

            // 2. GENERAR DATOS DE TRANSFERENCIA
            $datosTransferencia = [
                'success' => true,
                'preference_id' => $data['id'] ?? null,
                'init_point' => $data['init_point'] ?? null,
                'sandbox_init_point' => $data['sandbox_init_point'] ?? null,
                'clabe' => config('services.banco.clabe', '012180004123456789'),
                'cuenta' => config('services.banco.cuenta', '1234567890'),
                'banco' => config('services.banco.nombre', 'BBVA Bancomer'),
                'beneficiario' => config('services.banco.beneficiario', 'Tu Empresa SA de CV'),
                'referencia' => 'REF-' . $pago->id . '-' . date('YmdHis'),
                'fecha_expiracion' => now()->addDays(3)->toDateTimeString(),
            ];

            return $datosTransferencia;

        } catch (\Exception $e) {
            Log::error('Error en generarDatosTransferenciaMP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * VERIFICAR PAGO POR TRANSFERENCIA (Subir comprobante)
     */
    public function verificarTransferencia(Request $request)
    {
        try {
            $request->validate([
                'pago_id' => 'required|exists:tbl_pagos,id',
                'comprobante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            ]);

            $pago = Pago::with('prestamo')->find($request->pago_id);

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado',
                ], 404);
            }

            if ($pago->status == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago ya fue confirmado',
                ], 400);
            }

            if ($pago->status == 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago ya fue rechazado',
                ], 400);
            }

            // Verificar primero en Mercado Pago (si tiene preference_id)
            if ($pago->preference_id) {
                $verificacionMP = $this->consultarPagoPorPreferenceId($pago->preference_id);

                if ($verificacionMP['success'] && $verificacionMP['status'] === 'approved') {
                    // El pago ya fue aprobado en MP, procesarlo
                    $result = $this->procesarPagoAprobado($pago, $verificacionMP['payment_id']);
                    return response()->json($result);
                }
            }

            // Subir comprobante
            if ($request->hasFile('comprobante')) {
                $comprobante = $request->file('comprobante');
                $path = $comprobante->store('comprobantes/transferencias', 'public');

                $pago->comprobante_url = $path;
                $pago->observaciones = ($pago->observaciones ?? '') . " - Comprobante subido: " . now();
                $pago->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Comprobante subido correctamente, pendiente de verificación manual',
                    'data' => [
                        'pago_id' => $pago->id,
                        'comprobante_url' => asset('storage/' . $path),
                        'estado' => $pago->estado_pago,
                    ],
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Para verificar transferencia, debe subir el comprobante o esperar la notificación automática de Mercado Pago',
                'data' => [
                    'pago_id' => $pago->id,
                    'estado_actual' => $pago->estado_pago,
                    'preference_id' => $pago->preference_id,
                ],
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error en verificarTransferencia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar transferencia',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * VALIDAR TRANSFERENCIA MANUALMENTE (ASESOR)
     */
    public function validarTransferenciaManual(Request $request)
    {
        try {
            $user_token = $request->user();

            if (!$user_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $request->validate([
                'pago_id' => 'required|exists:tbl_pagos,id',
                'accion' => 'required|in:aprobar,rechazar',
                'observaciones' => 'nullable|string|max:500',
                'monto_verificado' => 'nullable|numeric|min:0',
            ]);

            $pago = Pago::with('prestamo')->find($request->pago_id);

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado',
                ], 404);
            }

            if ($pago->status == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago ya fue confirmado anteriormente',
                ], 400);
            }

            if ($pago->status == 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago ya fue rechazado anteriormente',
                ], 400);
            }

            if (!str_contains($pago->metodo_pago ?? '', 'Transferencia') && $pago->metodo_pago !== 'PIX') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este método de pago no requiere validación manual',
                ], 400);
            }

            if (!$pago->comprobante_url && $request->accion === 'aprobar') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede aprobar sin comprobante. El usuario debe subir el comprobante primero.',
                ], 400);
            }

            DB::beginTransaction();

            if ($request->accion === 'aprobar') {
                // ✅ APROBAR TRANSFERENCIA
                if ($pago->preference_id) {
                    $verificacionMP = $this->consultarPagoPorPreferenceId($pago->preference_id);

                    if ($verificacionMP['success'] && $verificacionMP['status'] === 'approved') {
                        $result = $this->aplicarPagoAlPrestamo($pago);
                    } else {
                        $result = $this->aplicarPagoAlPrestamo($pago);
                    }
                } else {
                    $result = $this->aplicarPagoAlPrestamo($pago);
                }

                if (!$result['success']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al aplicar el pago: ' . $result['message'],
                    ], 500);
                }

                $pago->status = 1;
                $pago->estado_pago = 'confirmado';
                $pago->fecha_confirmacion = now();
                $pago->usuario_confirmo = $user_token->id;
                $pago->observaciones = ($pago->observaciones ?? '') . " - Aprobado manualmente por: " . $user_token->name . " - " . now();
                if ($request->monto_verificado) {
                    $pago->monto_verificado = $request->monto_verificado;
                }
                $pago->save();

                DB::commit();

                Log::info('Transferencia aprobada manualmente', [
                    'pago_id' => $pago->id,
                    'aprobado_por' => $user_token->id,
                    'monto' => $pago->monto_pagado
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '✅ Transferencia aprobada exitosamente. La deuda ha sido reducida.',
                    'data' => [
                        'pago_id' => $pago->id,
                        'prestamo_id' => $pago->prestamo_id,
                        'nuevo_restante' => $pago->monto_restante,
                        'aprobado_por' => $user_token->name,
                        'fecha_aprobacion' => now(),
                        'liquidado' => $pago->monto_restante <= 0,
                    ],
                ], 200);

            } else {
                // ❌ RECHAZAR TRANSFERENCIA
                $pago->status = 2;
                $pago->estado_pago = 'rechazado';
                $pago->observaciones = ($pago->observaciones ?? '') . " - Rechazado manualmente por: " . $user_token->name . " - " . now() . " - Motivo: " . ($request->observaciones ?? 'No especificado');
                $pago->save();

                DB::commit();

                Log::info('Transferencia rechazada manualmente', [
                    'pago_id' => $pago->id,
                    'rechazado_por' => $user_token->id,
                    'motivo' => $request->observaciones
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '❌ Transferencia rechazada',
                    'data' => [
                        'pago_id' => $pago->id,
                        'rechazado_por' => $user_token->name,
                        'fecha_rechazo' => now(),
                        'motivo' => $request->observaciones,
                    ],
                ], 200);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en validarTransferenciaManual', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al validar la transferencia: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * VERIFICAR TRANSFERENCIAS PENDIENTES (CRON JOB)
     */
    public function verificarTransferenciasPendientes()
    {
        try {
            Log::info('Iniciando verificación de transferencias pendientes');

            $pagosPendientes = Pago::where('status', 0)
                ->where('estado_pago', 'esperando_transferencia')
                ->whereNotNull('preference_id')
                ->where('fecha_pago', '>=', now()->subDays(7))
                ->get();

            $aprobados = 0;
            $rechazados = 0;
            $errores = [];

            foreach ($pagosPendientes as $pago) {
                try {
                    Log::info('Verificando pago', ['pago_id' => $pago->id, 'preference_id' => $pago->preference_id]);

                    $verificacion = $this->consultarPagoPorPreferenceId($pago->preference_id);

                    if ($verificacion['success']) {
                        if ($verificacion['status'] === 'approved') {
                            // ✅ PAGO APROBADO
                            DB::beginTransaction();

                            $result = $this->aplicarPagoAlPrestamo($pago);

                            if ($result['success']) {
                                $pago->status = 1;
                                $pago->estado_pago = 'confirmado';
                                $pago->fecha_confirmacion = now();
                                $pago->no_pago_mp = $verificacion['payment_id'];
                                $pago->save();

                                DB::commit();
                                $aprobados++;

                                Log::info('Transferencia verificada automáticamente - APROBADA', [
                                    'pago_id' => $pago->id,
                                    'nuevo_restante' => $pago->monto_restante
                                ]);
                            } else {
                                DB::rollBack();
                                $errores[] = "Pago {$pago->id}: " . $result['message'];
                            }
                        } elseif ($verificacion['status'] === 'rejected') {
                            // ❌ PAGO RECHAZADO
                            $pago->status = 2;
                            $pago->estado_pago = 'rechazado';
                            $pago->save();
                            $rechazados++;

                            Log::info('Transferencia verificada automáticamente - RECHAZADA', [
                                'pago_id' => $pago->id
                            ]);
                        } else {
                            Log::info('Transferencia aún pendiente', [
                                'pago_id' => $pago->id,
                                'status' => $verificacion['status']
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    $errores[] = "Pago {$pago->id}: " . $e->getMessage();
                    Log::error('Error verificando pago individual', [
                        'pago_id' => $pago->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Verificación de transferencias completada',
                'data' => [
                    'total_procesados' => $pagosPendientes->count(),
                    'aprobados' => $aprobados,
                    'rechazados' => $rechazados,
                    'errores' => $errores,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error en verificarTransferenciasPendientes', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar transferencias: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * CONSULTAR ESTADO DE UNA TRANSFERENCIA
     */
    public function consultarEstadoTransferencia(Request $request)
    {
        try {
            $request->validate([
                'pago_id' => 'required|exists:tbl_pagos,id',
            ]);

            $pago = Pago::find($request->pago_id);

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado',
                ], 404);
            }

            if (!$pago->preference_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago no tiene preferencia en Mercado Pago',
                    'data' => [
                        'pago_id' => $pago->id,
                        'estado' => $pago->estado_pago,
                        'status' => $pago->status,
                    ],
                ], 400);
            }

            $verificacion = $this->consultarPagoPorPreferenceId($pago->preference_id);

            if (!$verificacion['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al consultar en Mercado Pago: ' . $verificacion['message'],
                ], 500);
            }

            if ($verificacion['status'] === 'approved' && $pago->status == 0) {
                $result = $this->procesarPagoAprobado($pago, $verificacion['payment_id']);

                if ($result['success']) {
                    return response()->json([
                        'success' => true,
                        'message' => '✅ Pago aprobado y procesado correctamente',
                        'data' => [
                            'pago_id' => $pago->id,
                            'estado' => 'confirmado',
                            'status' => 1,
                            'nuevo_restante' => $pago->monto_restante,
                            'liquidado' => $pago->monto_restante <= 0,
                        ],
                    ], 200);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Estado de la transferencia consultado',
                'data' => [
                    'pago_id' => $pago->id,
                    'preference_id' => $pago->preference_id,
                    'estado_mp' => $verificacion['status'],
                    'estado_local' => $pago->estado_pago,
                    'status_local' => $pago->status,
                    'monto' => $pago->monto_pagado,
                    'monto_restante' => $pago->monto_restante,
                    'fecha_pago' => $pago->fecha_pago,
                    'fecha_confirmacion' => $pago->fecha_confirmacion,
                    'comprobante_url' => $pago->comprobante_url ? asset('storage/' . $pago->comprobante_url) : null,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en consultarEstadoTransferencia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * CONSULTAR PAGO EN MERCADO PAGO POR PREFERENCE_ID
     */
    private function consultarPagoPorPreferenceId($preferenceId)
    {
        try {
            $token = config('services.mercadopago.access_token');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/checkout/preferences/{$preferenceId}");
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
                return ['success' => false, 'message' => 'No se encontró la preferencia'];
            }

            $data = json_decode($response, true);
            $collection_id = $data['collection_id'] ?? null;

            if (!$collection_id) {
                return ['success' => false, 'message' => 'No hay pagos asociados a esta preferencia'];
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$collection_id}");
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
                return ['success' => false, 'message' => 'No se encontró el pago'];
            }

            $pagoData = json_decode($response, true);

            return [
                'success' => true,
                'status' => $pagoData['status'] ?? null,
                'payment_id' => $pagoData['id'] ?? null,
                'transaction_amount' => $pagoData['transaction_amount'] ?? null,
                'data' => $pagoData,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * PROCESAR PAGO APROBADO
     */
    private function procesarPagoAprobado($pago, $paymentId = null)
    {
        try {
            DB::beginTransaction();

            if ($paymentId) {
                $pago->no_pago_mp = $paymentId;
            }
            $pago->status = 1;
            $pago->estado_pago = 'confirmado';
            $pago->fecha_confirmacion = now();
            $pago->usuario_confirmo = $pago->usuario_id;
            $pago->metodo_pago = 'Transferencia Bancaria';
            $pago->save();

            $result = $this->aplicarPagoAlPrestamo($pago);

            DB::commit();

            return [
                'success' => true,
                'message' => '✅ Transferencia confirmada exitosamente',
                'data' => $result['data'] ?? [],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en procesarPagoAprobado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error al procesar el pago: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * APLICAR PAGO AL PRÉSTAMO (REDUCE LA DEUDA)
     */
    private function aplicarPagoAlPrestamo($pago)
    {
        try {
            if ($pago->status == 1) {
                return [
                    'success' => false,
                    'message' => 'Pago ya aplicado anteriormente'
                ];
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

            // 🔥 REDUCIR LA DEUDA
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

            if ($prestamo_quedo_liquidado) {
                $this->aplicarIncrementoPorHistorial($prestamo);
            }

            $pago->monto_restante = $prestamo->monto_restante;
            $pago->save();

            DB::commit();

            Log::info('Pago aplicado al préstamo', [
                'pago_id' => $pago->id,
                'prestamo_id' => $prestamo->id,
                'monto_pagado' => $monto_pagado,
                'deuda_anterior' => $deuda_actual,
                'deuda_nueva' => $prestamo->monto_restante,
                'liquidado' => $prestamo_quedo_liquidado
            ]);

            return [
                'success' => true,
                'data' => [
                    'prestamo_id' => $prestamo->id,
                    'nuevo_restante' => $prestamo->monto_restante,
                    'estado' => $prestamo->estado_prestamo_id,
                    'pagos_realizados' => $prestamo->pagos_realizados,
                    'fecha_ultimo_pago' => $prestamo->fecha_ultimo_pago,
                    'liquidado' => $prestamo_quedo_liquidado,
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en aplicarPagoAlPrestamo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
        try {
            $usuario_id = $prestamo->usuario_id;

            $ultimo_prestamo_pagado = Prestamo::where('usuario_id', $usuario_id)
                ->where('estado_prestamo_id', 3)
                ->where('incremento_aplicado', 0)
                ->where('id', '!=', $prestamo->id)
                ->orderBy('id', 'desc')
                ->first();

            if (!$ultimo_prestamo_pagado) {
                $lineaCredito = LineaCredito::find($prestamo->linea_credito_id);
                if ($lineaCredito) {
                    $lineaCredito->estatus_id = 1;
                    $lineaCredito->save();
                }
                return null;
            }

            $linea_credito = LineaCredito::where('usuario_id', $usuario_id)
                ->where('estatus_id', 1)
                ->first();

            if (!$linea_credito) {
                $nuevo_limite = 200;
                $linea_credito = LineaCredito::create([
                    'usuario_id' => $usuario_id,
                    'limite_aprobado' => $nuevo_limite,
                    'limite_disponible' => $nuevo_limite,
                    'estatus_id' => 1,
                ]);
                return null;
            }

            $incremento = calcular_incremento_entero($ultimo_prestamo_pagado->monto_total_pagar, 'ceil');
            $nuevo_limite = (int) ($linea_credito->limite_aprobado + $incremento);
            $nuevo_disponible = (int) ($linea_credito->limite_disponible + $incremento);

            $linea_credito->limite_aprobado = $nuevo_limite;
            $linea_credito->limite_disponible = $nuevo_disponible;
            $linea_credito->estatus_id = 1;
            $linea_credito->save();

            $ultimo_prestamo_pagado->incremento_aplicado = 1;
            $ultimo_prestamo_pagado->fecha_incremento = now();
            $ultimo_prestamo_pagado->save();

            $prestamo->incremento_aplicado = 0;
            $prestamo->save();

            Log::info('Incremento aplicado por buen historial', [
                'usuario_id' => $usuario_id,
                'prestamo_id' => $ultimo_prestamo_pagado->id,
                'incremento' => $incremento,
                'nuevo_limite' => $nuevo_limite
            ]);

            return $incremento;

        } catch (\Exception $e) {
            Log::error('Error en aplicarIncrementoPorHistorial', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * OBTENER INSTRUCCIONES DE TRANSFERENCIA
     */
    private function getInstruccionesTransferencia($tipo = 'spei')
    {
        if ($tipo === 'pix') {
            return [
                'pasos' => [
                    '1. Abre tu aplicación bancaria',
                    '2. Selecciona la opción PIX',
                    '3. Escanea el código QR o copia la clave PIX',
                    '4. Ingresa el monto exacto',
                    '5. Confirma la transferencia',
                ],
                'tiempo_estimado' => 'Instantáneo',
            ];
        }

        return [
            'pasos' => [
                '1. Realiza la transferencia SPEI desde tu cuenta bancaria',
                '2. Utiliza la CLABE interbancaria proporcionada',
                '3. Indica el monto exacto a pagar',
                '4. Usa la referencia proporcionada en el concepto',
                '5. La transferencia se reflejará en 24-48 horas hábiles',
            ],
            'tiempo_estimado' => '24-48 horas hábiles',
        ];
    }
}
