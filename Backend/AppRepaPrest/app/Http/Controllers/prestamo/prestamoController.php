<?php

namespace App\Http\Controllers\prestamo;

use App\Http\Controllers\Controller;
use App\Http\Requests\prestamo\opcionesRequest;
use App\Models\LineaCredito;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class prestamoController extends Controller
{
    /**
     * FUNCIÓN PARA GENERAR SOLICITUD DE PRÉSTAMO
     * Verifica si el usuario puede solicitar un préstamo
     */
    public function solicitar_credito(Request $request)
    {
        try {
            $user_token = $request->user();

            if (! $user_token) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Usuario no autenticado',
                ], 401);
            }

            // 1. VERIFICAR PRÉSTAMOS EN ESTADO SOLICITADO (1) O APROBADO (2)
            $prestamo_pendiente_o_aprobado = Prestamo::where('usuario_id', $user_token->id)
                ->whereIn('estado_prestamo_id', [1, 2])
                ->exists();

            if ($prestamo_pendiente_o_aprobado) {
                $prestamo = Prestamo::where('usuario_id', $user_token->id)
                    ->whereIn('estado_prestamo_id', [1, 2])
                    ->first();

                $mensaje = $prestamo->estado_prestamo_id == 1
                    ? 'El usuario ya tiene un préstamo pendiente de aprobación'
                    : 'El usuario ya tiene un préstamo aprobado pendiente de pago';

                return response()->json([
                    'res'           => false,
                    'msg'           => $mensaje,
                    'estado_actual' => $prestamo->estado_prestamo_id,
                    'estado_texto'  => getEstadoTexto($prestamo->estado_prestamo_id),
                ], 400);
            }

            // 2. BUSCAR LÍNEA DE CRÉDITO ACTIVA
            $linea_credito = LineaCredito::where('usuario_id', $user_token->id)
                ->where('estatus_id', 1)
                ->first();

            // 3. SI NO TIENE LÍNEA DE CRÉDITO ACTIVA, CREAR UNA NUEVA
            if (! $linea_credito) {
                $nuevo_limite  = 200;
                $linea_credito = LineaCredito::create([
                    'usuario_id'        => $user_token->id,
                    'limite_aprobado'   => $nuevo_limite,
                    'limite_disponible' => $nuevo_limite,
                    'estatus_id'        => 1,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Línea de crédito creada exitosamente',
                    'data'    => [
                        'linea_credito_id' => $linea_credito->id,
                        'monto_aprobado'   => $nuevo_limite,
                        'monto_disponible' => $nuevo_limite,
                        'es_nuevo_usuario' => true,
                    ],
                ], 200);
            }

            // 4. BUSCAR ÚLTIMO PRÉSTAMO PAGADO SIN INCREMENTO
            $ultimo_prestamo_pagado = Prestamo::where('usuario_id', $user_token->id)
                ->where('estado_prestamo_id', 3)
                ->where('incremento_aplicado', 0)
                ->orderBy('id', 'desc')
                ->first();

            // 5. SI NO TIENE PRÉSTAMOS PAGADOS PENDIENTES DE INCREMENTO
            if (! $ultimo_prestamo_pagado) {
                $tiene_prestamos_pagados = Prestamo::where('usuario_id', $user_token->id)
                    ->where('estado_prestamo_id', 3)
                    ->exists();

                $montos_sugeridos = generarMontosSugeridos((int) $linea_credito->limite_disponible);

                // 🔥 OBTENER PLAZOS SEGÚN EL MONTO DISPONIBLE
                $monto_disponible = (int) $linea_credito->limite_disponible;
                $plazos_disponibles = getPlazosDisponibles($monto_disponible);

                return response()->json([
                    'success' => true,
                    'message' => $tiene_prestamos_pagados
                        ? 'Línea de crédito disponible (incremento ya aplicado)'
                        : 'Línea de crédito disponible (sin incremento pendiente)',
                    'data'    => [
                        'linea_credito_id'        => $linea_credito->id,
                        'monto_aprobado'          => (int) $linea_credito->limite_aprobado,
                        'monto_disponible'        => $monto_disponible,
                        'montos_sugeridos'        => $montos_sugeridos,
                        'plazos_disponibles'      => $plazos_disponibles,
                        'tiene_prestamos_pagados' => $tiene_prestamos_pagados,
                        'incremento_pendiente'    => false,
                    ],
                ], 200);
            }

            // 6. APLICAR INCREMENTO ENTERO (SIN DECIMALES)
            $incremento       = calcular_incremento_entero($ultimo_prestamo_pagado->monto_total_pagar, 'ceil');
            $nuevo_limite     = (int) ($linea_credito->limite_aprobado + $incremento);
            $nuevo_disponible = (int) ($linea_credito->limite_disponible + $incremento);

            $linea_credito->limite_aprobado   = $nuevo_limite;
            $linea_credito->limite_disponible = $nuevo_disponible;
            $linea_credito->save();

            $ultimo_prestamo_pagado->incremento_aplicado = 1;
            $ultimo_prestamo_pagado->fecha_incremento    = now();
            $ultimo_prestamo_pagado->save();

            $montos_sugeridos = generarMontosSugeridos($nuevo_disponible);

            // 🔥 OBTENER PLAZOS SEGÚN EL NUEVO MONTO DISPONIBLE
            $plazos_disponibles = getPlazosDisponibles($nuevo_disponible);

            return response()->json([
                'success' => true,
                'message' => 'Línea de crédito actualizada por buen historial de pago',
                'data'    => [
                    'linea_credito_id'        => $linea_credito->id,
                    'monto_aprobado'          => $nuevo_limite,
                    'monto_disponible'        => $nuevo_disponible,
                    'montos_sugeridos'        => $montos_sugeridos,
                    'plazos_disponibles'      => $plazos_disponibles,
                    'incremento_aplicado'     => $incremento,
                    'ultimo_prestamo_pagado'  => [
                        'id'                  => $ultimo_prestamo_pagado->id,
                        'folio'               => $ultimo_prestamo_pagado->folio,
                        'monto_total'         => (int) $ultimo_prestamo_pagado->monto_total_pagar,
                        'incremento_aplicado' => true,
                    ],
                    'tiene_prestamos_pagados' => true,
                    'incremento_pendiente'    => false,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'res'   => false,
                'msg'   => 'Error al procesar la solicitud',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
            ], 500);
        }
    }
    /**
     * FUNCIÓN PARA GENERAR OPCIONES DE PRÉSTAMO Y CREAR SOLICITUD
     */
    public function generar_solicitud(opcionesRequest $request)
    {
        try {
            $user_token = $request->user();

            if (! $user_token) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Usuario no autenticado',
                ], 401);
            }

            $prestamo_existente = Prestamo::where('usuario_id', $user_token->id)
                ->whereIn('estado_prestamo_id', [1, 2])
                ->first();

            if ($prestamo_existente) {
                $mensaje = $prestamo_existente->estado_prestamo_id == 1
                    ? 'El usuario ya tiene un préstamo pendiente de aprobación'
                    : 'El usuario ya tiene un préstamo aprobado pendiente de pago';

                return response()->json([
                    'res'           => false,
                    'msg'           => $mensaje,
                    'estado_actual' => $prestamo_existente->estado_prestamo_id,
                    'estado_texto'  => getEstadoTexto($prestamo_existente->estado_prestamo_id),
                    'folio'         => $prestamo_existente->folio,
                ], 400);
            }

            $linea_credito = LineaCredito::where('usuario_id', $user_token->id)
                ->where('estatus_id', 1)
                ->first();

            if (! $linea_credito) {
                return response()->json([
                    'res' => false,
                    'msg' => 'El usuario no tiene línea de crédito activa',
                ], 400);
            }

            $limite_disponible = (int) $linea_credito->limite_disponible;

            if ($request->monto_solicitado > $limite_disponible) {
                return response()->json([
                    'res'               => false,
                    'msg'               => 'El monto solicitado excede el límite disponible',
                    'limite_disponible' => $limite_disponible,
                    'monto_solicitado'  => $request->monto_solicitado,
                ], 400);
            }

            $numero_pagos = $request->numero_pagos;

            if (! in_array($numero_pagos, [1, 2])) {
                return response()->json([
                    'res'                => false,
                    'msg'                => 'El número de pagos debe ser 1 o 2 quincenas',
                    'valores_permitidos' => [1, 2],
                ], 400);
            }

            // Valida que el monto no sea mayor a 400 si el número de pagos es 1
            if ($numero_pagos == 1 && $request->monto_solicitado > 399) {
                return response()->json([
                    'res' => false,
                    'msg' => 'El número de pagos debe ser de 2 quincenas para montos mayores a 400',
                    'valores_permitidos' => [2]
                ], 400);
            }

            $detalle = calcula_interes_detallado(
                $request->monto_solicitado,
                $numero_pagos
            );

            $solo_simulacion = $request->solo_simulacion ?? false;

            if ($solo_simulacion) {
                $fecha_inicio = now()->startOfDay();

                return response()->json([
                    'success' => true,
                    'message' => 'Simulación de préstamo calculada correctamente',
                    'data'    => [
                        'monto_solicitado'  => (int) $request->monto_solicitado,
                        'numero_pagos'      => $numero_pagos,
                        'plazo'             => $numero_pagos . ' quincena' . ($numero_pagos > 1 ? 's' : ''),
                        'pago_por_quincena' => (int) $detalle['pago_quincenal'],
                        'monto_total_pagar' => (int) $detalle['total_pagar'],
                        'interes_total'     => (int) $detalle['total_interes'],
                        'fecha_primer_pago' => $fecha_inicio->copy()->addDays(15)->format('d/m/Y'),
                        'fecha_ultimo_pago' => $fecha_inicio->copy()->addDays($numero_pagos * 15)->format('d/m/Y'),
                        'desglose_pagos'    => $detalle['desglose_quincenal'],
                    ],
                ], 200);
            }

            $folio       = generarFolio();
            $monto_total = $request->monto_solicitado + calcula_interes($request->monto_solicitado, $numero_pagos);

            $prestamo                      = new Prestamo();
            $prestamo->folio               = $folio;
            $prestamo->usuario_id          = $user_token->id;
            $prestamo->linea_credito_id    = $linea_credito->id;
            $prestamo->monto_solicitado    = $request->monto_solicitado;
            $prestamo->monto_total_pagar   = $monto_total;
            $prestamo->monto_restante      = $monto_total;
            $prestamo->numero_pagos        = $numero_pagos;
            $prestamo->pagos_realizados    = 0;
            $prestamo->pago_quincenal      = $detalle['pago_quincenal'];
            $prestamo->periodicidad        = 'quincenal';
            $prestamo->fecha_solicitud     = now();
            $prestamo->estado_prestamo_id  = 1;
            $prestamo->incremento_aplicado = 0;
            $prestamo->save();

            $linea_credito->estatus_id = 2;
            $linea_credito->save();

           try {
            $brevoService = new \App\Services\BrevoService();
            $brevoService->sendLoanRequestEmail($prestamo, $user_token, $detalle);
            } catch (\Exception $e) {
                \Log::error('Error al enviar correo con Brevo: ' . $e->getMessage());
                // No interrumpir el flujo principal
            }

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de préstamo generada exitosamente',
                'data'    => [
                    'prestamo'       => [
                        'id'                => $prestamo->id,
                        'folio'             => $folio,
                        'monto_solicitado'  => (int) $request->monto_solicitado,
                        'monto_total_pagar' => (int) $monto_total,
                        'numero_pagos'      => $numero_pagos,
                        'pago_quincenal'    => (int) $detalle['pago_quincenal'],
                        'estado'            => getEstadoTexto(1),
                    ],
                    'desglose_pagos' => $detalle['desglose_quincenal'],
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la solicitud',
                'error'   => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * CONSULTAR ESTADO DE CUENTA DEL PRÉSTAMO (USUARIO)
     */
    public function consultarEstadoCuenta(Request $request)
    {
        try {
            $user_token = $request->user();

            if (! $user_token) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Usuario no autenticado',
                ], 401);
            }

            $prestamo_id = $request->prestamo_id;

            if (! $prestamo_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ID del préstamo es requerido',
                ], 400);
            }

            $prestamo = Prestamo::with(['pagos' => function ($query) {
                $query->orderBy('fecha_pago', 'desc');
            }])
                ->where('id', $prestamo_id)
                ->where('usuario_id', $user_token->id)
                ->first();

            if (! $prestamo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Préstamo no encontrado',
                ], 404);
            }

            $total_pagado      = $prestamo->pagos->sum('monto_pagado');
            $deuda_actual      = $prestamo->monto_restante ?? $prestamo->monto_total_pagar;
            $porcentaje_avance = calcularPorcentajePagado($prestamo);

            $proxima_fecha_pago = null;
            if ($prestamo->estado_prestamo_id == 2) {
                $proxima_fecha_pago = $prestamo->fecha_primer_pago;
            } elseif ($prestamo->estado_prestamo_id == 3) {
                $proxima_fecha_pago = null;
            } else {
                $proxima_fecha_pago = calcularProximaFechaPago($prestamo);
            }

            $dias_restantes = null;
            if ($proxima_fecha_pago) {
                $dias_restantes = calcularDiasRestantes($proxima_fecha_pago);
            }

            $tiene_prestamos_previos = Prestamo::where('usuario_id', $user_token->id)
                ->where('estado_prestamo_id', 3)
                ->where('id', '!=', $prestamo->id)
                ->exists();

            return response()->json([
                'success' => true,
                'data'    => [
                    'prestamo'        => [
                        'id'                      => $prestamo->id,
                        'folio'                   => $prestamo->folio,
                        'monto_inicial'           => (int) $prestamo->monto_solicitado,
                        'monto_total'             => (int) $prestamo->monto_total_pagar,
                        'monto_pagado'            => (int) $total_pagado,
                        'monto_restante'          => (int) $deuda_actual,
                        'numero_pagos'            => $prestamo->numero_pagos,
                        'pagos_realizados'        => $prestamo->pagos_realizados ?? 0,
                        'pagos_pendientes'        => $prestamo->numero_pagos - ($prestamo->pagos_realizados ?? 0),
                        'pago_quincenal'          => (int) $prestamo->pago_quincenal,
                        'estado'                  => getEstadoTexto($prestamo->estado_prestamo_id),
                        'estado_id'               => $prestamo->estado_prestamo_id,
                        'porcentaje_avance'       => $porcentaje_avance,
                        'tiene_prestamos_previos' => $tiene_prestamos_previos,
                    ],
                    'fechas'          => [
                        'fecha_solicitud'          => $prestamo->fecha_solicitud instanceof \Carbon\Carbon
                            ? $prestamo->fecha_solicitud->format('Y-m-d H:i:s')
                            : $prestamo->fecha_solicitud,
                        'fecha_aprobacion'         => $prestamo->fecha_aprobacion instanceof \Carbon\Carbon
                            ? $prestamo->fecha_aprobacion->format('Y-m-d H:i:s')
                            : $prestamo->fecha_aprobacion,
                        'fecha_desembolso'         => $prestamo->fecha_desembolso instanceof \Carbon\Carbon
                            ? $prestamo->fecha_desembolso->format('Y-m-d')
                            : $prestamo->fecha_desembolso,
                        'fecha_activacion'         => $prestamo->fecha_activacion instanceof \Carbon\Carbon
                            ? $prestamo->fecha_activacion->format('Y-m-d')
                            : $prestamo->fecha_activacion,
                        'fecha_primer_pago'        => $prestamo->fecha_primer_pago instanceof \Carbon\Carbon
                            ? $prestamo->fecha_primer_pago->format('Y-m-d')
                            : $prestamo->fecha_primer_pago,
                        'fecha_ultimo_pago'        => $prestamo->fecha_ultimo_pago instanceof \Carbon\Carbon
                            ? $prestamo->fecha_ultimo_pago->format('Y-m-d H:i:s')
                            : $prestamo->fecha_ultimo_pago,
                        'fecha_liquidacion'        => $prestamo->fecha_liquidacion instanceof \Carbon\Carbon
                            ? $prestamo->fecha_liquidacion->format('Y-m-d H:i:s')
                            : $prestamo->fecha_liquidacion,
                        'proxima_fecha_pago'       => $proxima_fecha_pago instanceof \Carbon\Carbon
                            ? $proxima_fecha_pago->format('Y-m-d')
                            : $proxima_fecha_pago,
                        'dias_restantes_para_pago' => $dias_restantes,
                    ],
                    'historial_pagos' => $prestamo->pagos->map(function ($pago) {
                        return [
                            'id'             => $pago->id,
                            'monto'          => (int) $pago->monto_pagado,
                            'monto_restante' => (int) $pago->monto_restante,
                            'tipo'           => $pago->tipo_pago,
                            'es_adelantado'  => (bool) ($pago->es_adelantado ?? false),
                            'fecha'          => $pago->fecha_pago instanceof \Carbon\Carbon
                                ? $pago->fecha_pago->format('Y-m-d H:i:s')
                                : $pago->fecha_pago,
                            'referencia'     => $pago->referencia,
                            'observaciones'  => $pago->observaciones,
                            'metodo_pago'    => $pago->metodo_pago ?? 'efectivo',
                        ];
                    }),
                    'linea_credito'   => [
                        'id'                => $prestamo->linea_credito_id,
                        'limite_aprobado'   => (int) ($prestamo->lineaCredito->limite_aprobado ?? 0),
                        'limite_disponible' => (int) ($prestamo->lineaCredito->limite_disponible ?? 0),
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el estado de cuenta',
                'error'   => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
            ], 500);
        }
    }
}
