<?php

namespace App\Http\Controllers\prestamo;

use App\Http\Controllers\Controller;
use App\Http\Requests\prestamo\opcionesRequest;
use App\Models\LineaCredito;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class prestamoController extends Controller
{
    // FUNCION PARA GENERAR SOLICITUD DE PRESTAMO
    public function solicita_user(Request $request)
    {
        try {
            $user_token = $request->user();

            if (!$user_token) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Usuario no autenticado'
                ], 401);
            }

            // ============================================
            // 1. VERIFICAR PRÉSTAMOS EN ESTADO SOLICITADO (1) O APROBADO (2)
            // ============================================
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
                    'res' => false,
                    'msg' => $mensaje,
                    'estado_actual' => $prestamo->estado_prestamo_id,
                    'estado_texto' => getEstadoTexto($prestamo->estado_prestamo_id)
                ], 400);
            }

            // ============================================
            // 2. BUSCAR LÍNEA DE CRÉDITO ACTIVA
            // ============================================
            $linea_credito = LineaCredito::where('usuario_id', $user_token->id)
                ->where('estatus_id', 1)
                ->first();

            // ============================================
            // 3. SI NO TIENE LÍNEA DE CRÉDITO ACTIVA, CREAR UNA NUEVA
            // ============================================
            if (!$linea_credito) {
                $nuevo_limite = 200;
                $linea_credito = LineaCredito::create([
                    'usuario_id' => $user_token->id,
                    'limite_aprobado' => $nuevo_limite,
                    'limite_disponible' => $nuevo_limite,
                    'estatus_id' => 1
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Línea de crédito creada exitosamente',
                    'data' => [
                        'linea_credito_id' => $linea_credito->id,
                        'monto_aprobado' => $nuevo_limite,
                        'monto_disponible' => $nuevo_limite,
                        'es_nuevo_usuario' => true
                    ]
                ], 200);
            }

            // ============================================
            // 4. BUSCAR ÚLTIMO PRÉSTAMO PAGADO SIN INCREMENTO
            // ============================================
            $ultimo_prestamo_pagado = Prestamo::where('usuario_id', $user_token->id)
                ->where('estado_prestamo_id', 3) // 3 = Pagado
                ->where('incremento_aplicado', 0)
                ->orderBy('id', 'desc')
                ->first();

            // ============================================
            // 5. SI NO TIENE PRÉSTAMOS PAGADOS PENDIENTES DE INCREMENTO
            // ============================================
            if (!$ultimo_prestamo_pagado) {
                $tiene_prestamos_pagados = Prestamo::where('usuario_id', $user_token->id)
                    ->where('estado_prestamo_id', 3) // 3 = Pagado
                    ->exists();

                // Generar montos sugeridos con el límite disponible
                $montos_sugeridos = generarMontosSugeridos((int) $linea_credito->limite_disponible);

                return response()->json([
                    'success' => true,
                    'message' => $tiene_prestamos_pagados 
                        ? 'Línea de crédito disponible (incremento ya aplicado)'
                        : 'Línea de crédito disponible (sin incremento pendiente)',
                    'data' => [
                        'linea_credito_id' => $linea_credito->id,
                        'monto_aprobado' => (int) $linea_credito->limite_aprobado,
                        'monto_disponible' => (int) $linea_credito->limite_disponible,
                        'montos_sugeridos' => $montos_sugeridos,
                        'plazos_disponibles' => getPlazosDisponibles(),
                        'tiene_prestamos_pagados' => $tiene_prestamos_pagados,
                        'incremento_pendiente' => false
                    ]
                ], 200);
            }

            // ============================================
            // 6. APLICAR INCREMENTO ENTERO (SIN DECIMALES)
            // ============================================
            $incremento = calcular_incremento_entero($ultimo_prestamo_pagado->monto_total_pagar, 'ceil');
            $nuevo_limite = (int) ($linea_credito->limite_aprobado + $incremento);
            $nuevo_disponible = (int) ($linea_credito->limite_disponible + $incremento);

            // ACTUALIZAR LA LÍNEA DE CRÉDITO
            $linea_credito->limite_aprobado = $nuevo_limite;
            $linea_credito->limite_disponible = $nuevo_disponible;
            $linea_credito->save();

            // MARCAR EL PRÉSTAMO COMO QUE YA SE APLICÓ EL INCREMENTO
            $ultimo_prestamo_pagado->incremento_aplicado = 1;
            $ultimo_prestamo_pagado->fecha_incremento = now();
            $ultimo_prestamo_pagado->save();

            // Generar montos sugeridos con el nuevo límite disponible
            $montos_sugeridos = generarMontosSugeridos($nuevo_disponible);

            Log::info('Línea de crédito actualizada por préstamo pagado (INCREMENTO ENTERO)', [
                'usuario_id' => $user_token->id,
                'prestamo_id' => $ultimo_prestamo_pagado->id,
                'folio' => $ultimo_prestamo_pagado->folio,
                'incremento' => $incremento,
                'nuevo_limite' => $nuevo_disponible
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Línea de crédito actualizada por buen historial de pago',
                'data' => [
                    'linea_credito_id' => $linea_credito->id,
                    'monto_aprobado' => $nuevo_limite,
                    'monto_disponible' => $nuevo_disponible,
                    'montos_sugeridos' => $montos_sugeridos,
                    'plazos_disponibles' => getPlazosDisponibles(),
                    'incremento_aplicado' => $incremento,
                    'ultimo_prestamo_pagado' => [
                        'id' => $ultimo_prestamo_pagado->id,
                        'folio' => $ultimo_prestamo_pagado->folio,
                        'monto_total' => (int) $ultimo_prestamo_pagado->monto_total_pagar,
                        'incremento_aplicado' => true
                    ],
                    'tiene_prestamos_pagados' => true,
                    'incremento_pendiente' => false
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en solicita_user', [
                'usuario_id' => $user_token->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'res' => false,
                'msg' => 'Error al procesar la solicitud',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }
    // FUNCION PARA GENERAR OPCIONES DE PRESTAMO
    public function genera_opciones(opcionesRequest $request)
    {
        try {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado'
            ], 401);
        }

        // ============================================
        // 1. VERIFICAR PRÉSTAMOS EN ESTADO SOLICITADO (1) O APROBADO (2)
        // ============================================
        $prestamo_existente = Prestamo::where('usuario_id', $user_token->id)
            ->whereIn('estado_prestamo_id', [1, 2])
            ->first();

        if ($prestamo_existente) {
            $mensaje = $prestamo_existente->estado_prestamo_id == 1 
                ? 'El usuario ya tiene un préstamo pendiente de aprobación'
                : 'El usuario ya tiene un préstamo aprobado pendiente de pago';
            
            return response()->json([
                'res' => false,
                'msg' => $mensaje,
                'estado_actual' => $prestamo_existente->estado_prestamo_id,
                'estado_texto' => getEstadoTexto($prestamo_existente->estado_prestamo_id),
                'folio' => $prestamo_existente->folio
            ], 400);
        }

        // ============================================
        // 2. BUSCAR LÍNEA DE CRÉDITO ACTIVA
        // ============================================
        $linea_credito = LineaCredito::where('usuario_id', $user_token->id)
            ->where('estatus_id', 1)
            ->first();

        if (!$linea_credito) {
            return response()->json([
                'res' => false,
                'msg' => 'El usuario no tiene línea de crédito activa'
            ], 400);
        } 

        // ============================================
        // 3. VERIFICAR MONTO SOLICITADO
        // ============================================
        $limite_disponible = (int) $linea_credito->limite_disponible;
        
        if ($request->monto_solicitado > $limite_disponible) {
            return response()->json([
                'res' => false,
                'msg' => 'El monto solicitado excede el límite disponible',
                'limite_disponible' => $limite_disponible,
                'monto_solicitado' => $request->monto_solicitado
            ], 400);
        }

        // ============================================
        // 4. VALIDAR NÚMERO DE PAGOS (1 o 2 quincenas)
        // ============================================
        $numero_pagos = $request->numero_pagos;

        if (!in_array($numero_pagos, [1, 2]) ) {
            return response()->json([
                'res' => false,
                'msg' => 'El número de pagos debe ser 1 o 2 quincenas',
                'valores_permitidos' => [1, 2]
            ], 400);
        }

        // Valida que el monto no sea mayor a 400 si el número de pagos es 1
        if ( $numero_pagos == 1 && $request->monto_solicitado > 400 ) {
            return response()->json([
                'res' => false,
                'msg' => 'El número de pagos debe ser de 2 quincenas para montos mayores a 400',
                'valores_permitidos' => [2]
            ], 400);
        }

        // ============================================
        // 5. CALCULAR INTERESES
        // ============================================
        $detalle = calcula_interes_detallado(
            $request->monto_solicitado, 
            $numero_pagos
        );

        // ============================================
        // 6. SI ES SOLO SIMULACIÓN 
        // ============================================
        $solo_simulacion = $request->solo_simulacion ?? false;
        
        if ($solo_simulacion) {
            $fecha_inicio = now()->startOfDay();
            
            return response()->json([
                'success' => true,
                'message' => 'Simulación de préstamo calculada correctamente',
                'data' => [
                    'monto_solicitado' => (int) $request->monto_solicitado,
                    'numero_pagos' => $numero_pagos,
                    'plazo' => $numero_pagos . ' quincena' . ($numero_pagos > 1 ? 's' : ''),
                    'pago_por_quincena' => (int) $detalle['pago_quincenal'],
                    'monto_total_pagar' => (int) $detalle['total_pagar'],
                    'interes_total' => (int) $detalle['total_interes'],
                    'fecha_primer_pago' => $fecha_inicio->copy()->addDays(15)->format('d/m/Y'),
                    'fecha_ultimo_pago' => $fecha_inicio->copy()->addDays($numero_pagos * 15)->format('d/m/Y'),
                    'desglose_pagos' => $detalle['desglose_quincenal']
                ]
            ], 200);
        }

        // ============================================
        // 7. CREAR LA SOLICITUD
        // ============================================
        $folio = generarFolio();
        $monto_total = $request->monto_solicitado + calcula_interes($request->monto_solicitado, $numero_pagos);

        $prestamo = new Prestamo();
        $prestamo->folio = $folio;
        $prestamo->usuario_id = $user_token->id;
        $prestamo->linea_credito_id = $linea_credito->id;
        $prestamo->monto_solicitado = $request->monto_solicitado;
        $prestamo->monto_total_pagar = $monto_total;
        $prestamo->monto_restante = $monto_total;
        $prestamo->numero_pagos = $numero_pagos;
        $prestamo->pagos_realizados = 0;
        $prestamo->pago_quincenal = $detalle['pago_quincenal'];
        $prestamo->periodicidad = 'quincenal';
        $prestamo->fecha_solicitud = now();
        $prestamo->estado_prestamo_id = 1;
        $prestamo->incremento_aplicado = 0;
        $prestamo->save();

        // ============================================
        // 8. DESHABILITAR LÍNEA DE CRÉDITO
        // ============================================
        $linea_credito->estatus_id = 2;
        $linea_credito->save();

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de préstamo generada exitosamente',
            'data' => [
                'prestamo' => [
                    'id' => $prestamo->id,
                    'folio' => $folio,
                    'monto_solicitado' => (int) $request->monto_solicitado,
                    'monto_total_pagar' => (int) $monto_total,
                    'numero_pagos' => $numero_pagos,
                    'pago_quincenal' => (int) $detalle['pago_quincenal'],
                    'estado' => getEstadoTexto(1)
                ],
                'desglose_pagos' => $detalle['desglose_quincenal']
            ]
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Error en genera_opciones', [
            'usuario_id' => $user_token->id ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al generar la solicitud',
            'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
        ], 500);
    }
    }
}