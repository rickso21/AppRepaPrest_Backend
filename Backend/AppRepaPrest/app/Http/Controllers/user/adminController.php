<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Requests\login\registerAdminRequest;
use App\Http\Requests\prestamo\aprobarRequest;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class adminController extends Controller
{
    // FUNCION PARA GENERAR USUARIO QUE APRUEBA PRESTAMOS
    public function index(registerAdminRequest $request)
    {
        if ($request->codigo != env('CODIGO_SEGURIDAD')) {
            return response()->json(['res' => false, 'msg' => 'No es posible generar el usuario'], 401);
        }
        $resp=['res' => false, 'msg' => 'No puede generarse el usuario sin telefono o email'];
        $status_resp = 400;
        if ($request->email == null && $request->telefono == null) {
            return response()->json($resp, $status_resp);
        }
        $user = new User();
        $user->nombre = $request->name;
        $user->apellido_p = $request->apellido_p;
        $user->apellido_m = $request->apellido_m;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->telefono = $request->telefono;
        $user->rol_id = 3;
        $user->status_id = 1;
        $resp['msg'] = "Se genero el usuario con Exito";
        $status_resp = 201;
        try {
            $user->save();
            $grupo = new Grupo();
            $grupo->code = "externo";
            $grupo->group_name = $request->name_group;
            $grupo->user_leader_id = $user->id;
            $grupo->status = 1;
            $grupo->save();
            $user->grupo_id = $grupo->id;
            $user->save();
        } catch (\Throwable $th) {
            $resp['msg'] = $th->getMessage();
            $status_resp = 409;
        }
        return response()->json($resp, $status_resp);
    }

    public function ve_prestamos(Request $request)
    {
        $user_token = $request->user();

        if ($user_token->rol_id != 3) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado'
            ], 401);
        }

        $prestamos = Prestamo::whereIn('estado_prestamo_id', [1, 2, 3])->get(); // Aprobado o Rechazado
        $listado = [];
        foreach ($prestamos as $prestamo) {
            $usuario = $prestamo->usuario->nombre . ' ' . $prestamo->usuario->apellido_p . ' ' . $prestamo->usuario->apellido_m;
            $monto_solicitado = $prestamo->monto_solicitado;
            $folio = $prestamo->folio;
            $monto_total_pagar = $prestamo->monto_total_pagar;
            $numero_pagos = $prestamo->numero_pagos;
            $pagos_realizados = $prestamo->pagos_realizados;

            $listado= [
                'usuario' => $usuario,
                'monto_solicitado' => $monto_solicitado,
                'folio' => $folio,
                'monto_total_pagar' => $monto_total_pagar,
                'numero_pagos' => $numero_pagos,
                'pagos_realizados' => $pagos_realizados
            ];
            // var_dump($listado);
        }
        return response()->json($listado, 200);
    }

    public function aprobar_prestamo(aprobarRequest $request, int $id)
    {
        $user_token = $request->user();

        if ($user_token->rol_id != 3) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado'
            ], 401);
        }

        $prestamo = Prestamo::find($id);

        if (!$prestamo) {
            return response()->json([
                'res' => false,
                'msg' => 'Préstamo no encontrado'
            ], 404);
        }

        // Cambiar el estado del préstamo a aprobado (2)
        $prestamo->estado_prestamo_id = $request->estado_prestamo_id; // 2 para aprobado, 3 para rechazado
        $prestamo->save();

        return response()->json([
            'res' => true,
            'msg' => 'Préstamo aprobado o rechazado exitosamente'
        ], 200);
    }
}