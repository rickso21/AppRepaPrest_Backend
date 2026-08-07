<?php

namespace App\Http\Controllers\prestamo;

use App\Http\Controllers\Controller;
use App\Http\Requests\prestamo\opcionesRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Prestamo; // ← Usar el modelo

class prestamoController extends Controller
{
     // FUNCION PARA GENERAR SOLICITUD DE PRESTAMO
    public function solicita_user(Request $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado'
            ], 401);
        }

        // VERIFICAR PRESTAMOS ACTIVOS
        $prestamo_activo = $user_token->prestamos()
            ->where('estado_prestamo_id', 3)
            ->exists();

        if ($prestamo_activo) {
            return response()->json([
                'res' => false,
                'msg' => 'El usuario ya tiene un préstamo activo'
            ], 400);
        }

        // BUSCAR LÍNEA DE CRÉDITO ACTIVA
        $linea_credito = $user_token->linea_credito()
            ->where('estatus_id', 1)
            ->first();

        // SI NO TIENE LÍNEA DE CRÉDITO, CREAR UNA NUEVA
        if (!$linea_credito) {
            $nuevo_limite = 200;
            $user_token->linea_credito()->create([
                'usuario_id' => $user_token->id,
                'limite_aprobado' => $nuevo_limite,
                'limite_disponible' => $nuevo_limite,
                'estatus_id' => 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Línea de crédito creada exitosamente',
                'data' => [
                    'monto_aprobado' => $nuevo_limite,
                    'monto_disponible' => $nuevo_limite
                ]
            ], 200);
        }

        // SI TIENE LÍNEA DE CRÉDITO, CALCULAR NUEVO LÍMITE
        $ultimo_prestamo = $user_token->prestamos()
            ->orderBy('id', 'desc')
            ->first();

        if (!$ultimo_prestamo) {
            // No tiene préstamos previos
            return response()->json([
                'success' => true,
                'message' => 'Línea de crédito disponible',
                'data' => [
                    'monto_aprobado' => $linea_credito->limite_aprobado,
                    'monto_disponible' => $linea_credito->limite_disponible
                ]
            ], 200);
        }

        // CALCULAR NUEVO LÍMITE BASADO EN EL ÚLTIMO PRÉSTAMO
        $incremento = $ultimo_prestamo->monto_total_pagar * 0.10;
        $nuevo_limite = $linea_credito->limite_aprobado + $incremento;
        $nuevo_disponible = $linea_credito->limite_disponible + $incremento;

        // CREAR NUEVA LÍNEA DE CRÉDITO
        $user_token->linea_credito()->create([
            'usuario_id' => $user_token->id,
            'limite_aprobado' => $nuevo_limite,
            'limite_disponible' => $nuevo_disponible,
            'estatus_id' => 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Línea de crédito actualizada exitosamente',
            'data' => [
                'monto_aprobado' => $nuevo_limite,
                'monto_disponible' => $nuevo_disponible,
                'incremento_aplicado' => $incremento
            ]
        ], 200);
    }

    // FUNCION PARA GENERAR OPCIONES DE PRESTAMO
    public function genera_opciones(opcionesRequest $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado'
            ], 401);
        }

        // BUSCAR LÍNEA DE CRÉDITO ACTIVA
        $linea_credito = $user_token->linea_credito()
            ->where('estatus_id', 1)
            ->first();

        if (!$linea_credito) {
            return response()->json([
                'res' => false,
                'msg' => 'El usuario no tiene línea de crédito activa'
            ], 400);
        }

        // VERIFICAR QUE EL MONTO SOLICITADO NO SUPERE EL LÍMITE DISPONIBLE
        if ($request->monto_solicitado > $linea_credito->limite_disponible) {
            return response()->json([
                'res' => false,
                'msg' => 'El monto solicitado excede el límite disponible',
                'limite_disponible' => $linea_credito->limite_disponible
            ], 400);
        }

        // CALCULAR INTERÉS
        $interes = calcula_interes($request->monto_solicitado, $request->numero_pagos);
        $monto_total = $request->monto_solicitado + $interes;

        try {
            // GENERAR FOLIO ÚNICO
            $folio = $this->generarFolio();

            // CREAR LA SOLICITUD DE PRÉSTAMO
            $prestamo = new Prestamo();
            $prestamo->folio = $folio;
            $prestamo->usuario_id = $user_token->id;
            $prestamo->linea_credito_id = $linea_credito->id;
            $prestamo->monto_solicitado = $request->monto_solicitado;
            $prestamo->monto_total_pagar = $monto_total;
            $prestamo->numero_pagos = $request->numero_pagos;
            $prestamo->periodicidad = 'quincenal';
            $prestamo->fecha_solicitud = now();
            $prestamo->fecha_aprobacion = null;
            $prestamo->fecha_desembolso = null;
            $prestamo->fecha_primer_pago = null;
            $prestamo->estado_prestamo_id = 1; // 1 = Pendiente
            $prestamo->save();

            $linea_credito->estatus_id = 2; // Deshabilitada/En uso
            $linea_credito->save();

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de préstamo generada y línea de crédito deshabilitada',
                'data' => [
                    'prestamo_id' => $prestamo->id,
                    'folio' => $folio,
                    'monto_solicitado' => $request->monto_solicitado,
                    'numero_pagos' => $request->numero_pagos,
                    'interes' => $interes,
                    'monto_total_pagar' => $monto_total,
                    'periodicidad' => 'quincenal',
                    'fecha_solicitud' => $prestamo->fecha_solicitud,
                    'estado' => 'Pendiente de aprobación',
                    'linea_credito_estatus' => 'Deshabilitada (en proceso)',
                    'limite_disponible_anterior' => $linea_credito->limite_disponible
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // FUNCIÓN PARA GENERAR FOLIO ÚNICO
    private function generarFolio()
    {
        $prefijo = 'PRE-';
        $fecha = date('Ymd');
        $aleatorio = strtoupper(substr(uniqid(), -6));
        $folio = $prefijo . $fecha . '-' . $aleatorio;
        
        // VERIFICAR QUE EL FOLIO NO EXISTA
        while (Prestamo::where('folio', $folio)->exists()) {
            $aleatorio = strtoupper(substr(uniqid(), -6));
            $folio = $prefijo . $fecha . '-' . $aleatorio;
        }
        
        return $folio;
    }
}
