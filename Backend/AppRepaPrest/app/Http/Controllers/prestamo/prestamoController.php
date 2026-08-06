<?php

namespace App\Http\Controllers\prestamo;

use App\Http\Controllers\Controller;
use App\Http\Requests\prestamo\opcionesRequest;
use App\Models\User;
use Illuminate\Http\Request;

class prestamoController extends Controller
{
    // FUNCION PARA GENERAR SOLICITUD DE PRESTAMO
    public function solicita_user(Request $request)
    {

        $resp=['res' => false, 'msg' => 'El usuario no existe'];
        $status_resp = 400;
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json($resp, $status_resp);
        }
        // BUSCA PRESTAMOS ACTIVOS DEL USUARIO
        $prestamos_previos = $user_token->prestamos()->where('estado_prestamo_id', 3)->get();
        // SI EL USUARIO YA TIENE UN PRESTAMO ACTIVO, NO SE PUEDE SOLICITAR OTRO
        if($prestamos_previos->count() > 0){
            $resp=['res' => false, 'msg' => 'El usuario ya tiene un préstamo activo'];
            return response()->json($resp, $status_resp);
        }
        // BUSCA LINEA DE CREDITO DEL USUARIO
        $linea_credito = $user_token->linea_credito()->get();
        // SI EL USUARIO NO TIENE LINEA DE CREDITO, SE CREA UNA NUEVA EN CASO DE TENER UNA AJUSTAR CREDITO
        if($linea_credito->count() == 0){
            $resp=['res' => true, 'msg' => 'Credito aprobado', 'monto_aprobado' => 1000, 'monto_disponible' => 200];
            $status_resp = 200;
            $linea_credito = $user_token->linea_credito()->create([
                'usuario_id' => $user_token->id,
                'limite_aprobado' => 1000, // Monto aprobado
                'limite_disponible' => 200, // Monto disponible
                'estatus_id' => 1 // Estatus activo
            ]);
        } else {
            $linea_credito_actual = $linea_credito->where('estatus_id', 1)->last();
            $prestamos_previos = $user_token->prestamos()->orderBy('id', 'desc')->first();
            if(!$prestamos_previos ) {
                $resp=['res' => true, 'msg' => 'Credito aprobado', 'monto_aprobado' => $linea_credito_actual->limite_aprobado, 'monto_disponible' => $linea_credito_actual->limite_disponible];
                $status_resp = 200;
            } else {
                $nuevo_limite = $linea_credito_actual->limite_aprobado + ($prestamos_previos->monto_total_pagar * 0.10);
                $nuevo_disponible = $linea_credito_actual->limite_disponible + ($prestamos_previos->monto_total_pagar * 0.10);
                $user_token->linea_credito()->create([
                    'usuario_id' => $user_token->id,
                    'limite_aprobado' => $nuevo_limite, // Monto aprobado
                    'limite_disponible' => $nuevo_disponible, // Monto disponible
                    'estatus_id' => 1 // Estatus activo
                ]);
                $resp=['res' => true, 'msg' => 'Credito aprobado', 'monto_aprobado' => $nuevo_limite, 'monto_disponible' => $nuevo_disponible];
                $status_resp = 200;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de préstamo generada exitosamente',
            'resp' => $resp
        ], 200);
    }
    // FUNCION PARA GENERAR OPCIONES DE PRESTAMO
    public function genera_opciones(opcionesRequest $request)
    {
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json(['res' => false, 'msg' => 'El usuario no existe'], 400);
        }
        // BUSCA LINEA DE CREDITO DEL USUARIO
        $linea_credito = $user_token->linea_credito()->where('estatus_id', 1)->first();
        if (!$linea_credito) {
            return response()->json(['res' => false, 'msg' => 'El usuario no tiene línea de crédito activa'], 400);
        }

        $interes = calcula_interes($request->monto_solicitado, $request->numero_pagos);

        return response()->json([
            'success' => true,
            'message' => 'Opciones de préstamo generadas exitosamente',
            'interes' => $interes,
            'monto_total_pagar' => $request->monto_solicitado + $interes
        ], 200);
    }
}