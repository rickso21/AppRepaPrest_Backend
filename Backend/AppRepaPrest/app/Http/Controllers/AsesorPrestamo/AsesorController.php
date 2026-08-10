<?php

namespace App\Http\Controllers\AsesorPrestamo;

use App\Http\Controllers\Controller;
use App\Http\Requests\prestamo\AsesorPrestamoRequest;
use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\LineaCredito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;  

class AsesorController extends Controller
{
    /**
     * REGISTRAR UN PAGO/ABONO AL PRÉSTAMO (ASESOR)
     */
  public function registrarPago(Request $request)
    {
        try {
            $user_token = $request->user();

            if (!$user_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Validar datos
            $request->validate([
                'usuario_id' => 'required|exists:tbl_user,id',
                'monto_pago' => 'required|numeric|min:1',
                'referencia' => 'nullable|string|max:100',
                'observaciones' => 'nullable|string|max:500',
                'es_pago_adelantado' => 'nullable|boolean',
                'metodo_pago' => 'nullable|in:efectivo,transferencia,tarjeta,otro'
            ]);

            // BUSCAR PRÉSTAMO EN ESTADO APROBADO (2)
            $prestamo = Prestamo::where('usuario_id', $request->usuario_id)
                ->where('estado_prestamo_id', 2)
                ->orderBy('id', 'desc')
                ->first();

            if (!$prestamo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene un préstamo aprobado para realizar pagos',
                    'estados_permitidos' => [
                        '2' => 'Aprobado'
                    ]
                ], 404);
            }

            // VERIFICAR ESTADO DEL PRÉSTAMO
            if (in_array($prestamo->estado_prestamo_id, [3, 4])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este préstamo ya está ' . getEstadoTexto($prestamo->estado_prestamo_id),
                    'estado_actual' => $prestamo->estado_prestamo_id,
                    'estado_texto' => getEstadoTexto($prestamo->estado_prestamo_id)
                ], 400);
            }

            // VALIDACIÓN DE FECHA DE DESEMBOLSO
            $es_pago_adelantado = $request->es_pago_adelantado ?? false;
            
            if ($prestamo->estado_prestamo_id == 2) {
                $validacion = validarFechaDesembolso($prestamo, $es_pago_adelantado);
                
                if (!$validacion['valido']) {
                    return response()->json([
                        'success' => false,
                        'message' => $validacion['message'],
                        'fecha_desembolso' => $validacion['fecha_desembolso'] ?? null,
                        'fecha_actual' => $validacion['fecha_actual'] ?? null,
                        'dias_restantes' => $validacion['dias_restantes'] ?? null,
                        'accion' => $validacion['accion'] ?? 'Esperar la fecha de desembolso'
                    ], 400);
                }
            }

            // CONTINUAR CON EL PROCESO DE PAGO
            $deuda_actual = $prestamo->monto_restante ?? $prestamo->monto_total_pagar;
            
            if ($request->monto_pago > $deuda_actual) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto del pago excede la deuda actual',
                    'deuda_actual' => $deuda_actual,
                    'monto_pago' => $request->monto_pago,
                    'monto_maximo' => $deuda_actual
                ], 400);
            }

            $nuevo_restante = $deuda_actual - $request->monto_pago;
            $nuevos_pagos = ($prestamo->pagos_realizados ?? 0) + 1;

            $pago_quincenal = $prestamo->monto_total_pagar / $prestamo->numero_pagos;
            $tipo_pago = 'normal';
            
            if ($es_pago_adelantado || $request->monto_pago > $pago_quincenal) {
                $tipo_pago = 'anticipado';
            }
            if ($nuevo_restante <= 0) {
                $tipo_pago = 'completo';
                $nuevo_restante = 0;
            }

            // Registrar el pago
            $pago = Pago::create([
                'prestamo_id' => $prestamo->id,
                'usuario_id' => $request->usuario_id,
                'monto_pagado' => $request->monto_pago,
                'monto_restante' => $nuevo_restante,
                'saldo_antes_pago' => $deuda_actual,
                'tipo_pago' => $tipo_pago,
                'es_adelantado' => $es_pago_adelantado,
                'numero_quincena' => $nuevos_pagos,
                'fecha_pago' => now(),
                'referencia' => $request->referencia,
                'observaciones' => $request->observaciones,
                'metodo_pago' => $request->metodo_pago ?? 'efectivo',
                'usuario_registro' => $user_token->id,
                'estado_pago' => 'confirmado',
                'fecha_confirmacion' => now(),
                'fecha_desembolso_original' => $prestamo->fecha_desembolso
            ]);

            // Actualizar el préstamo
            $prestamo->monto_restante = $nuevo_restante;
            $prestamo->pagos_realizados = $nuevos_pagos;
            $prestamo->fecha_ultimo_pago = now();

            $credito_reactivado = false;
            $incremento_pendiente = false;


            $pdfGenerado = false;
            $tipoPdf = null;
            $rutaPdf = null;
            $urlPdf = null;


            // Si el préstamo está liquidado
            if ($nuevo_restante <= 0) {
                $prestamo->estado_prestamo_id = 3; // Pagado
                $prestamo->incremento_aplicado = 0;
                $prestamo->fecha_liquidacion = now();
                
                $linea_credito = LineaCredito::find($prestamo->linea_credito_id);
                if ($linea_credito) {
                    $linea_credito->estatus_id = 1; // Activa
                    $linea_credito->save();
                    $credito_reactivado = true;
                    $incremento_pendiente = true;
                }

                try {
        $pdfData = $this->generarPdfPrestamo($prestamo, 'liquidacion', $user_token);
        $pdfGenerado = true;
        $tipoPdf = 'liquidacion';
        $rutaPdf = $pdfData['ruta'] ?? null;
        $urlPdf = $pdfData['url'] ?? null;
        $folderPdf = $pdfData['folder'] ?? 'liquidacion';
        
        Log::info('PDF de liquidación generado por pago', [
            'prestamo_id' => $prestamo->id,
            'folio' => $prestamo->folio,
            'ruta_pdf' => $rutaPdf,
            'folder' => $folderPdf
        ]);
    } catch (\Exception $e) {
        Log::error('Error generando PDF de liquidación en registrarPago', [
            'prestamo_id' => $prestamo->id,
            'error' => $e->getMessage()
        ]);
    }

                Log::info('Préstamo liquidado - Línea de crédito reactivada con incremento pendiente', [
                    'prestamo_id' => $prestamo->id,
                    'folio' => $prestamo->folio,
                    'usuario_id' => $request->usuario_id,
                    'asesor_id' => $user_token->id,
                    'total_pagado' => $request->monto_pago,
                    'incremento_pendiente' => true
                ]);
            }

            $prestamo->save();

            $porcentaje_pagado = calcularPorcentajePagado($prestamo);
            
            $fecha_desembolso = $prestamo->fecha_desembolso;
            if (!$fecha_desembolso instanceof Carbon) {
                $fecha_desembolso = Carbon::parse($fecha_desembolso);
            }

            $proxima_fecha = $prestamo->estado_prestamo_id == 3 ? null : calcularProximaFechaPago($prestamo);

            $mensaje = $nuevo_restante <= 0 
                ? '¡Préstamo liquidado completamente! Línea de crédito reactivada. El incremento del 10% se aplicará en tu próxima solicitud.'
                : 'Pago registrado exitosamente';

            $responseData = [
            'pago' => [
                'id' => $pago->id,
                'monto_pagado' => (int) $pago->monto_pagado,
                'monto_restante' => (int) $pago->monto_restante,
                'tipo_pago' => $pago->tipo_pago,
                'es_adelantado' => $es_pago_adelantado,
                'fecha_pago' => $pago->fecha_pago->format('Y-m-d H:i:s')
            ],
            'prestamo' => [
                'id' => $prestamo->id,
                'folio' => $prestamo->folio,
                'monto_solicitado' => (int) $prestamo->monto_solicitado,
                'monto_total' => (int) $prestamo->monto_total_pagar,
                'monto_pagado' => (int) ($prestamo->monto_total_pagar - $prestamo->monto_restante),
                'monto_restante' => (int) $prestamo->monto_restante,
                'pagos_realizados' => $prestamo->pagos_realizados,
                'pagos_pendientes' => $prestamo->numero_pagos - $prestamo->pagos_realizados,
                'estado' => getEstadoTexto($prestamo->estado_prestamo_id),
                'estado_id' => $prestamo->estado_prestamo_id,
                'porcentaje_pagado' => $porcentaje_pagado,
                'fecha_desembolso' => $fecha_desembolso->format('Y-m-d'),
                'fecha_activacion' => $prestamo->fecha_activacion instanceof Carbon 
                    ? $prestamo->fecha_activacion->format('Y-m-d') 
                    : $prestamo->fecha_activacion,
                'fecha_liquidacion' => $prestamo->fecha_liquidacion instanceof Carbon 
                    ? $prestamo->fecha_liquidacion->format('Y-m-d H:i:s')
                    : $prestamo->fecha_liquidacion,
                'proxima_fecha_pago' => $proxima_fecha ? $proxima_fecha->format('Y-m-d') : null
            ],
            'linea_credito' => [
                'reactivada' => $credito_reactivado,
                'incremento_pendiente' => $incremento_pendiente,
                'mensaje_incremento' => $incremento_pendiente 
                    ? 'El incremento del 10% se aplicará automáticamente en tu próxima solicitud'
                    : null
            ],
            'pago_adelantado' => $es_pago_adelantado,
            'registrado_por' => 'asesor'
        ];

             if ($pdfGenerado && $rutaPdf) {
    $responseData['pdf'] = [
        'generado' => true,
        'tipo' => $tipoPdf,
        'carpeta' => $folderPdf ?? 'liquidacion',
        'ruta' => $rutaPdf,
        'url_descarga' => $urlPdf ?? asset("storage/prestamos/{$folderPdf}/" . basename($rutaPdf))
    ];
        }

        return response()->json([
            'success' => true,
            'message' => $mensaje,
            'data' => $responseData
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Error en registrarPago (Asesor)', [
            'usuario_id' => $request->usuario_id ?? null,
            'asesor_id' => $user_token->id ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al registrar el pago',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
    /**
     * CONSULTAR ESTADO DE CUENTA DEL PRÉSTAMO (ASESOR)
     */
    public function consultarEstadoCuenta(Request $request)
    {
        try {
            $user_token = $request->user();

            if (!$user_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $prestamo_id = $request->prestamo_id;
            $usuario_id = $request->usuario_id;

            $query = Prestamo::with(['pagos' => function($query) {
                $query->orderBy('fecha_pago', 'desc');
            }]);

            if ($prestamo_id) {
                $query->where('id', $prestamo_id);
            } elseif ($usuario_id) {
                $query->where('usuario_id', $usuario_id);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar prestamo_id o usuario_id'
                ], 400);
            }

            $prestamo = $query->first();

            if (!$prestamo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Préstamo no encontrado'
                ], 404);
            }

            $total_pagado = $prestamo->pagos->sum('monto_pagado');
            $deuda_actual = $prestamo->monto_restante ?? $prestamo->monto_total_pagar;

            return response()->json([
                'success' => true,
                'data' => [
                    'prestamo' => [
                        'id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $prestamo->usuario_id,
                        'monto_inicial' => $prestamo->monto_solicitado,
                        'monto_total' => $prestamo->monto_total_pagar,
                        'monto_pagado' => $total_pagado,
                        'monto_restante' => $deuda_actual,
                        'numero_pagos' => $prestamo->numero_pagos,
                        'pagos_realizados' => $prestamo->pagos_realizados ?? 0,
                        'pagos_pendientes' => $prestamo->numero_pagos - ($prestamo->pagos_realizados ?? 0),
                        'estado' => getEstadoTexto($prestamo->estado_prestamo_id),
                        'porcentaje_avance' => calcularPorcentajePagado($prestamo),
                        'fecha_solicitud' => $prestamo->fecha_solicitud,
                        'fecha_aprobacion' => $prestamo->fecha_aprobacion,
                        'fecha_desembolso' => $prestamo->fecha_desembolso,
                        'fecha_activacion' => $prestamo->fecha_activacion,
                        'fecha_liquidacion' => $prestamo->fecha_liquidacion,
                        'fecha_ultimo_pago' => $prestamo->fecha_ultimo_pago,
                        'linea_credito_id' => $prestamo->linea_credito_id
                    ],
                    'historial_pagos' => $prestamo->pagos->map(function($pago) {
                        return [
                            'id' => $pago->id,
                            'monto' => $pago->monto_pagado,
                            'tipo' => $pago->tipo_pago,
                            'es_adelantado' => $pago->es_adelantado ?? false,
                            'fecha' => $pago->fecha_pago,
                            'referencia' => $pago->referencia,
                            'observaciones' => $pago->observaciones,
                            'saldo_restante' => $pago->monto_restante,
                            'metodo_pago' => $pago->metodo_pago ?? 'efectivo',
                            'registrado_por' => $pago->usuario_registro
                        ];
                    })
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en consultarEstadoCuenta (Asesor)', [
                'usuario_id' => $user_token->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el estado de cuenta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 /**
     * ACTUALIZAR ESTADO DEL PRÉSTAMO CON GENERACIÓN AUTOMÁTICA DE PDF
     */
    public function actualizarEstadoPrestamo(AsesorPrestamoRequest $request)
    {
        try {
            $user_token = $request->user();

            if (!$user_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Buscar préstamo activo
            $prestamo = Prestamo::where('usuario_id', $request->usuario_id)
                ->whereNotIn('estado_prestamo_id', [3, 4])
                ->orderBy('id', 'desc')
                ->first();
            
            if (!$prestamo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene un préstamo activo para modificar'
                ], 404);
            }

            $estado_actual = $prestamo->estado_prestamo_id;
            $nuevo_estado = $request->estado_prestamo_id;

            // Validar transiciones
            if (!validarTransicionEstado($estado_actual, $nuevo_estado)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transición de estado no permitida',
                    'estado_actual' => $estado_actual,
                    'estado_actual_texto' => getEstadoTexto($estado_actual),
                    'nuevo_estado' => $nuevo_estado,
                    'nuevo_estado_texto' => getEstadoTexto($nuevo_estado),
                    'transiciones_permitidas' => getTransicionesPermitidas($estado_actual)
                ], 400);
            }

            if (esEstadoFinal($estado_actual)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede modificar un préstamo en estado final',
                    'estado_actual' => $estado_actual,
                    'estado_actual_texto' => getEstadoTexto($estado_actual)
                ], 400);
            }

            $linea_credito = LineaCredito::find($prestamo->linea_credito_id);
            $credito_reactivado = false;
            
            // Variable para controlar qué PDF generar
            $pdfGenerado = false;
            $tipoPdf = null;
            $rutaPdf = null;

            // ============================================
            // PROCESAR SEGÚN EL NUEVO ESTADO
            // ============================================
            switch ($nuevo_estado) {
                case 2: // Aprobado
                    // ============================================
                    // FECHAS DE APROBACIÓN Y DESEMBOLSO
                    // ============================================
                    $fecha_aprobacion = now();
                    $fecha_desembolso = $fecha_aprobacion->copy();
                    
                    $prestamo->fecha_aprobacion = $fecha_aprobacion;
                    $prestamo->fecha_desembolso = $fecha_desembolso;
                    $prestamo->fecha_activacion = $fecha_desembolso; 
                    $prestamo->fecha_primer_pago = $fecha_desembolso->copy()->addDays(15);
                    $prestamo->estado_prestamo_id = 2;
                    
                    // Actualizar línea de crédito
                    if ($linea_credito) {
                        $linea_credito->estatus_id = 2; // En uso
                        $linea_credito->save();
                    }
                    
                    // GENERAR PDF DE APROBACIÓN
                    $pdfData = $this->generarPdfPrestamo($prestamo, 'aprobacion', $user_token);
                    $pdfGenerado = true;
                    $tipoPdf = 'aprobacion';
                    $rutaPdf = $pdfData['ruta'] ?? null;
                    $folderPdf = $pdfData['folder'] ?? 'aprobacion';
                    break;
                                    
                    Log::info('Préstamo aprobado y PDF generado', [
                        'prestamo_id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $request->usuario_id,
                        'asesor_id' => $user_token->id,
                        'pdf_generado' => $pdfGenerado
                    ]);
                    break;

                case 3: // Pagado
                    // ============================================
                    // VALIDAR QUE EL PRÉSTAMO ESTÉ APROBADO
                    // ============================================
                    if ($prestamo->estado_prestamo_id != 2) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El préstamo debe estar en estado Aprobado para marcarlo como Pagado',
                            'estado_actual' => $estado_actual,
                            'estado_actual_texto' => getEstadoTexto($estado_actual),
                            'accion' => 'Primero debe aprobar el préstamo'
                        ], 400);
                    }

                    // Verificar fecha de desembolso
                    if (!$prestamo->fecha_desembolso) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El préstamo no tiene fecha de desembolso asignada',
                            'accion' => 'Primero debe aprobar el préstamo'
                        ], 400);
                    }

                    // Marcar como pagado
                    $prestamo->fecha_liquidacion = now();
                    $prestamo->estado_prestamo_id = 3;
                    $prestamo->incremento_aplicado = 0;
                    $prestamo->monto_restante = 0;
                    
                    // Reactivar línea de crédito
                    if ($linea_credito) {
                        $linea_credito->estatus_id = 1; // Activa
                        $linea_credito->save();
                        $credito_reactivado = true;
                    }
                    
                    // GENERAR PDF DE LIQUIDACIÓN
                    $pdfData = $this->generarPdfPrestamo($prestamo, 'liquidacion', $user_token);
                    $pdfGenerado = true;
                    $tipoPdf = 'liquidacion';
                    $rutaPdf = $pdfData['ruta'] ?? null;
                    $folderPdf = $pdfData['folder'] ?? 'liquidacion';
                    
                    Log::info('Préstamo liquidado y PDF generado', [
                        'prestamo_id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $request->usuario_id,
                        'asesor_id' => $user_token->id,
                        'pdf_generado' => $pdfGenerado
                    ]);
                    break;

                case 4: // Rechazado
                    // Rechazar préstamo
                    $prestamo->estado_prestamo_id = 4;
                    $prestamo->motivo_rechazo = $request->motivo_rechazo ?? 'No especificado';
                    
                    // Si estaba aprobado, limpiar fechas
                    if ($estado_actual == 2) {
                        $prestamo->fecha_aprobacion = null;
                        $prestamo->fecha_desembolso = null;
                        $prestamo->fecha_activacion = null; 
                        $prestamo->fecha_primer_pago = null;
                    }
                    
                    // Reactivar línea de crédito
                    if ($linea_credito) {
                        $linea_credito->estatus_id = 1; // Activa
                        $linea_credito->save();
                        $credito_reactivado = true;
                    }
                    
                    //  GENERAR PDF DE RECHAZO
                   $pdfData = $this->generarPdfPrestamo($prestamo, 'rechazo', $user_token);
                    $pdfGenerado = true;
                    $tipoPdf = 'rechazo';
                    $rutaPdf = $pdfData['ruta'] ?? null;
                    $folderPdf = $pdfData['folder'] ?? 'rechazo';
                    break;
                    
                    Log::info('Préstamo rechazado y PDF generado', [
                        'prestamo_id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $request->usuario_id,
                        'asesor_id' => $user_token->id,
                        'motivo' => $request->motivo_rechazo ?? 'No especificado',
                        'pdf_generado' => $pdfGenerado
                    ]);
                    break;
                
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Estado no válido',
                        'estado_recibido' => $nuevo_estado,
                        'estados_permitidos' => [2, 3, 4]
                    ], 400);
            }

            // ============================================
            // GUARDAR CAMBIOS
            // ============================================
            $prestamo->save();

            // ============================================
            // PREPARAR RESPUESTA
            // ============================================
            return response()->json([
                'success' => true,
                'message' => $nuevo_estado == 3 ? 'Préstamo marcado como pagado y línea de crédito reactivada' : 'Estado del préstamo actualizado correctamente',
                'data' => [
                    'prestamo' => [
                        'id' => $prestamo->id,
                        'folio' => $prestamo->folio,
                        'usuario_id' => $prestamo->usuario_id,
                        'monto_solicitado' => (float) $prestamo->monto_solicitado,
                        'monto_total' => (float) $prestamo->monto_total_pagar,
                        'monto_restante' => (float) $prestamo->monto_restante,
                        'numero_pagos' => $prestamo->numero_pagos,
                        'pagos_realizados' => $prestamo->pagos_realizados ?? 0,
                    ],
                    'estado' => [
                        'anterior' => $estado_actual,
                        'anterior_texto' => getEstadoTexto($estado_actual),
                        'nuevo' => $nuevo_estado,
                        'nuevo_texto' => getEstadoTexto($nuevo_estado)
                    ],
                    'fechas' => [
                        'fecha_aprobacion' => $prestamo->fecha_aprobacion instanceof \Carbon\Carbon 
                            ? $prestamo->fecha_aprobacion->format('Y-m-d H:i:s')
                            : $prestamo->fecha_aprobacion,
                        'fecha_desembolso' => $prestamo->fecha_desembolso instanceof \Carbon\Carbon 
                            ? $prestamo->fecha_desembolso->format('Y-m-d')
                            : $prestamo->fecha_desembolso,
                        'fecha_activacion' => $prestamo->fecha_activacion instanceof \Carbon\Carbon 
                            ? $prestamo->fecha_activacion->format('Y-m-d')
                            : $prestamo->fecha_activacion,
                        'fecha_primer_pago' => $prestamo->fecha_primer_pago instanceof \Carbon\Carbon 
                            ? $prestamo->fecha_primer_pago->format('Y-m-d')
                            : $prestamo->fecha_primer_pago,
                        'fecha_liquidacion' => $prestamo->fecha_liquidacion instanceof \Carbon\Carbon 
                            ? $prestamo->fecha_liquidacion->format('Y-m-d H:i:s')
                            : $prestamo->fecha_liquidacion,
                    ],
                    'linea_credito' => $linea_credito ? [
                        'id' => $linea_credito->id,
                        'limite_aprobado' => (int) $linea_credito->limite_aprobado,
                        'limite_disponible' => (int) $linea_credito->limite_disponible,
                        'estatus_id' => $linea_credito->estatus_id,
                        'estatus_texto' => $linea_credito->estatus_id == 1 ? 'Activa (Disponible)' : 'En Uso',
                        'credito_reactivado' => $credito_reactivado
                    ] : null,
                    // INCLUIR INFORMACIÓN DEL PDF GENERADO
                   'pdf' => $pdfGenerado ? [
                    'generado' => true,
                    'tipo' => $tipoPdf,
                    'carpeta' => $folderPdf ?? null,
                    'ruta' => $rutaPdf,
                    'url_descarga' => $rutaPdf ? asset("storage/prestamos/{$folderPdf}/" . basename($rutaPdf)) : null
                ] : [
                    'generado' => false
                ],
                    'registrado_por' => 'asesor',
                    'asesor_id' => $user_token->id,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en actualizarEstadoPrestamo (Asesor)', [
                'usuario_id' => $request->usuario_id ?? null,
                'asesor_id' => $user_token->id ?? null,
                'nuevo_estado' => $request->estado_prestamo_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
            'message' => 'Error al actualizar el estado',
            'error' => $e->getMessage(), // Esto mostrará el error real
            'trace' => $e->getTraceAsString() // Esto mostrará la traza
            ], 500);
        }
    }


 /**
 * GENERAR PDF DEL PRÉSTAMO
 */
public function generarPdfPrestamo($prestamo, $tipo, $usuario)
{
    try {
        // Cargar relaciones
        $prestamo->load(['usuario', 'lineaCredito']);
        
        // Preparar datos para la vista
        $data = [
            'prestamo' => $prestamo,
            'usuario' => $prestamo->usuario,
            'linea_credito' => $prestamo->lineaCredito,
            'fecha_generacion' => now()->format('d/m/Y H:i:s'),
            'estado_texto' => getEstadoTexto($prestamo->estado_prestamo_id),
            'tipo_documento' => $tipo,
            'motivo_rechazo' => $prestamo->motivo_rechazo ?? null,
            'asesor' => $usuario
        ];

        // Determinar qué vista usar según el tipo
        $vista = match($tipo) {
            'aprobacion' => 'pdf.prestamo-aprobacion',
            'liquidacion' => 'pdf.prestamo-liquidacion',
            'rechazo' => 'pdf.prestamo-rechazo',
            default => 'pdf.prestamo-general'
        };

        $pdf = Pdf::loadView($vista, $data);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false
        ]);

        // RUTA CON CARPETA POR TIPO
        $filename = "prestamo_{$prestamo->id}_{$tipo}_" . now()->format('Ymd_His') . ".pdf";
        
        // Construir ruta con subcarpeta según el tipo
        $folder = match($tipo) {
            'aprobacion' => 'aprobacion',
            'liquidacion' => 'liquidacion',
            'rechazo' => 'rechazo',
            default => 'otros'
        };
        
        // Ruta relativa para la BD
        $dbPath = "public/prestamos/{$folder}/" . $filename;
        
        // Ruta absoluta para el sistema de archivos
        $basePath = storage_path('app/public/prestamos/' . $folder);
        $absolutePath = $basePath . DIRECTORY_SEPARATOR . $filename;
        
        Log::info('Ruta absoluta', [
            'path' => $absolutePath,
            'folder' => $folder,
            'tipo' => $tipo
        ]);

        // CREAR DIRECTORIO CON FILE SYSTEM (incluyendo subcarpeta)
        if (!is_dir($basePath)) {
            Log::info('Creando directorio: ' . $basePath);
            if (!mkdir($basePath, 0777, true)) {
                throw new \Exception("No se pudo crear el directorio: {$basePath}");
            }
        }

        // VERIFICAR PERMISOS DE ESCRITURA
        if (!is_writable($basePath)) {
            throw new \Exception("El directorio no tiene permisos de escritura: {$basePath}");
        }

        // GUARDAR EL PDF
        $pdfContent = $pdf->output();
        $bytesWritten = file_put_contents($absolutePath, $pdfContent);
        
        if ($bytesWritten === false || $bytesWritten === 0) {
            throw new \Exception("No se pudo escribir el PDF. Bytes escritos: {$bytesWritten}");
        }

        // VERIFICAR QUE EL ARCHIVO EXISTE
        if (!file_exists($absolutePath)) {
            throw new \Exception("El archivo no existe después de guardarlo: {$absolutePath}");
        }

        // Guardar la ruta en la base de datos
        $campo = "ruta_pdf_{$tipo}";
        $prestamo->$campo = $dbPath;
        $prestamo->save();

        Log::info('PDF generado exitosamente', [
            'prestamo_id' => $prestamo->id,
            'absolute_path' => $absolutePath,
            'db_path' => $dbPath,
            'bytes' => $bytesWritten,
            'folder' => $folder
        ]);

        return [
            'success' => true,
            'ruta' => $dbPath,
            'nombre' => $filename,
            'folder' => $folder,
            'url' => asset("storage/prestamos/{$folder}/" . $filename)
        ];

    } catch (\Exception $e) {
        Log::error('ERROR GENERANDO PDF', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}


/**
 * DESCARGAR PDF ESPECÍFICO
 */
public function descargarPdfPrestamo($prestamo_id, $tipo)
{
    try {
        $prestamo = Prestamo::findOrFail($prestamo_id);
        
        // Construir la ruta según el tipo
        $campo = "ruta_pdf_{$tipo}";
        if (!isset($prestamo->$campo) || !Storage::exists($prestamo->$campo)) {
            return response()->json([
                'success' => false,
                'message' => "No existe PDF de {$tipo} para este préstamo"
            ], 404);
        }

        // Obtener el nombre del archivo desde la ruta
        $fullPath = $prestamo->$campo;
        $filename = basename($fullPath);
        
        // Determinar la carpeta según el tipo
        $folder = match($tipo) {
            'aprobacion' => 'aprobacion',
            'liquidacion' => 'liquidacion',
            'rechazo' => 'rechazo',
            default => 'otros'
        };

        // Descargar el archivo
        return Storage::download(
            $fullPath, 
            "prestamo_{$prestamo->folio}_{$tipo}.pdf"
        );

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al descargar el PDF',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * LISTAR TODOS LOS PRÉSTAMOS (ASESOR)
     */
    public function listarPrestamos(Request $request)
    {
        try {
            $user_token = $request->user();

            if (!$user_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $estado = $request->estado;
            $usuario_id = $request->usuario_id;
            $folio = $request->folio;

            $query = Prestamo::with(['usuario', 'lineaCredito']);

            if ($estado) {
                $query->where('estado_prestamo_id', $estado);
            }

            if ($usuario_id) {
                $query->where('usuario_id', $usuario_id);
            }

            if ($folio) {
                $query->where('folio', 'LIKE', "%{$folio}%");
            }

            $prestamos = $query->orderBy('id', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'prestamos' => $prestamos->map(function($prestamo) {
                        return [
                            'id' => $prestamo->id,
                            'folio' => $prestamo->folio,
                            'usuario_id' => $prestamo->usuario_id,
                            'usuario_nombre' => $prestamo->usuario->name ?? null,
                            'monto_solicitado' => $prestamo->monto_solicitado,
                            'monto_total' => $prestamo->monto_total_pagar,
                            'monto_restante' => $prestamo->monto_restante,
                            'estado' => getEstadoTexto($prestamo->estado_prestamo_id),
                            'estado_id' => $prestamo->estado_prestamo_id,
                            'fecha_solicitud' => $prestamo->fecha_solicitud,
                            'fecha_desembolso' => $prestamo->fecha_desembolso,
                            'pagos_realizados' => $prestamo->pagos_realizados ?? 0,
                            'total_pagos' => $prestamo->numero_pagos,
                            'porcentaje_pagado' => calcularPorcentajePagado($prestamo)
                        ];
                    }),
                    'pagination' => [
                        'current_page' => $prestamos->currentPage(),
                        'total' => $prestamos->total(),
                        'per_page' => $prestamos->perPage(),
                        'last_page' => $prestamos->lastPage()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en listarPrestamos (Asesor)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al listar los préstamos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
}
