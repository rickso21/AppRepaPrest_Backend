<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Requests\login\editUserRequest;
use App\Http\Requests\login\loginRequest;
use App\Http\Requests\login\registerRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class loginController extends Controller
{
    /**
     * FUNCION PARA GUARDAR DEL USUARIO
     *
     * @param  App\Http\Requests\login\registerRequest  $request
     *
     * @bodyParam string email required The email of the user. Example: john.doe@mail.com
     * @bodyParam integer id_grupo required Number indicates group that belong. Example: 1
     * @bodyParam string nombre required First name of the user. Example: John
     * @bodyParam string apellidos required The last name of the user. Example: Doe
     * @bodyParam integer id_rol required Number indicates permission that have. Example: 1
     *
     * @response 201 {
     *   "res": true,
     *   "msg": "Se genero el usuario con Exito"
     * }
     *
     * @response 400 {
     *   "res": false,
     *   "msg": "No es posible generar el usuario"
     * }
     *
     * @response 409 {
     *   "res": false,
     *   "msg": "Error al generar el usuario"
     * }
     *
    */
    public function register(registerRequest $request)
    {
        $resp=['res' => false, 'msg' => 'No es posible generar el usuario'];
        $status_resp = 400;
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $resp['msg'] = "Se genero el usuario con Exito";
        $status_resp = 201;
        try {
            $user->save();
        } catch (\Throwable $th) {
            $resp['msg'] = "Error al generar el usuario";
            $status_resp = 409;
            // $resp['error'] = $th->getMessage();
        }
        return response()->json($resp, $status_resp);
    }

    // FUNCION PARA VALIDAR DEL USUARIO
   public function login(loginRequest $request)
    {
        $resp = ['res' => false, 'msg' => 'Algo salió mal'];
        $status_resp = 400;

        // Buscar usuario por email o teléfono
         $user = User::where(function($query) use ($request) {
        $query->where('email', $request->email)
              ->orWhere('telefono', $request->email);
    })->first();

        // BUSCA SI EL USUARIO EXISTE
        if ($user) {
            // EL USUARIO SE LOGUEA DIRECTAMENTE SI NO ES CONTRASEÑA POR DEFECTO
            $tiempo_expira = 5;
            $date = Carbon::now();

            if (Hash::check($request->password, $user->password)) {
                $token = $user->createToken("Palabra_Secreta");
                $resp = [
                    "res" => true,
                    "token" => $token->plainTextToken,
                    "created_at" => $date->format('Y-m-d H:i:s'),
                    "expired_at" => $date->addMinutes($tiempo_expira)->format('Y-m-d H:i:s'),
                    "msg" => "Inicio de sesión exitoso",
                     "user" => [ // Opcional: incluir datos del usuario
                        "id" => $user->id,
                        "nombre" => $user->nombre   ,
                        "email" => $user->email,
                        "telefono" => $user->telefono
                    ]
                ];
                $status_resp = 200;
            } else {
                $resp['msg'] = "Contraseña incorrecta.";
                $status_resp = 401;
            }
        } else {
            $resp['msg'] = "Usuario no encontrado.";
            $status_resp = 404;
        }

        return response()->json($resp, $status_resp);
    }
    // FUNCION PARA EDITAR INFORMACIÓN DEL USUARIO
    public function edit_user(editUserRequest $request, int $id)
    {
        $resp=['res' => false, 'msg' => 'No es posible editar el usuario'];
        $status_resp = 400;
        $user_token = $request->user();
        try {
            $user = User::findOrFail($id);
        } catch (\Throwable $th) {
            $resp=['res' => false, 'msg' => 'Usuario no encontrado'];
            $status_resp = 404;
            return response()->json($resp, $status_resp);
        }
        if( $user_token->idusuario != $id ){
            return response()->json($resp, $status_resp);
        }
        // SE MODIFICA LA INFORMACIÓN DEL USUARIO
        $user->nombre = $request->nombre;
        $user->apellidos = $request->apellidos;
        $user->password = Hash::make($request->password);
        try {
            $user->save();
            $resp['msg'] = "Se edito el usuario con Exito";
            $resp['res'] = true;
            $status_resp = 200;
        } catch (\Throwable $th) {
            $resp['msg'] = "Error al editar el usuario";
            $status_resp = 409;
        }
        return response()->json($resp, $status_resp);
    }
}
