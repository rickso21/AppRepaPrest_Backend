<?php

namespace App\Http\Controllers\login;

use App\Http\Controllers\Controller;
use App\Models\UsuarioApi;
use App\Http\Requests\AutenticacionRequest;
use App\Http\Requests\RegistroRequest;
use App\Http\Requests\ActualizarUsuarioRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserApiController extends Controller
{
    /**
     * Create user for access to API
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function registro(RegistroRequest $request)
    {
        $user = new UsuarioApi();
        $user->nombre = $request->input('nombre');
        $user->email = $request->input('email');
        $user->password = Hash::make($request->input('password'));
        $user->apellido_p = $request->input('apellido_p');
        $user->apellido_m = $request->input('apellido_m');
        $user->telefono = $request->input('telefono');
        $user->otp=$request->input('otp');
        $user->rol_id  = $request->input('rol_id');
        $user->status_id  = $request->input('status_id');
        $now = Carbon::now();
        $user->created_at = $now;
        $user->updated_at = $now;
        $user->save();

        return response()->json([
            'res' => true,
            'msg' => 'Se creó el usuario para poder acceder a la API'
        ], 201);
    }


    public function login(AutenticacionRequest $request)
{
    $tiempo_expira = 5;
    $date = Carbon::now();
    $response = ["res" => false, "msg" => ""];

    $loginInput = $request->input('email');
    $password = $request->input('password');
    $usuario = UsuarioApi::where('email', $loginInput)
                         ->orWhere('telefono', $loginInput)
                         ->first();
    if ($usuario) {
        if (Hash::check($password, $usuario->password)) {
            // Token con Sanctum
            $token = $usuario->createToken("VivePlus_2022");

            $response = [
                "res" => true,
                "msg" => $token->plainTextToken,
                "created_at" => $date->format('Y-m-d H:i:s'),
                "expired_at" => $date->addMinutes($tiempo_expira)->format('Y-m-d H:i:s')
            ];
        } else {
            $response['msg'] = "Credenciales incorrectas.";
        }
    } else {
        $response['msg'] = "Usuario no encontrado.";
    }

    return response()->json($response);
}



public function update(ActualizarUsuarioRequest $request, $id)
{
    $user = UsuarioApi::find($id);

    if (!$user) {
        return response()->json([
            'res' => false,
            'msg' => 'Usuario no encontrado.'
        ], 404);
    }

    $user->nombre = $request->input('nombre');
    $user->email = $request->input('email');

    if ($request->filled('password')) {
        $user->password = Hash::make($request->input('password'));
    }

    $user->updated_at = Carbon::now();
    $user->save();

    return response()->json([
        'res' => true,
        'msg' => 'Usuario actualizado correctamente.'
    ], 200);
}
}
