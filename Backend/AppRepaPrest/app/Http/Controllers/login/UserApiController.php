<?php

namespace App\Http\Controllers\login;

use App\Http\Controllers\Controller;
use App\Models\UsuarioApi;
use App\Http\Requests\AutenticacionRequest;
use App\Http\Requests\RegistroRequest;
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
        $user->user = $request->user;
        $user->password = Hash::make($request->password);
        $user->save();
        return response()->json([
            'res' => true,
            'msg' => 'Se creo el usuario para poder acceder a la API'
        ]);
    }
    /**
     * Give a Token for auth
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(AutenticacionRequest $request)
    {
        $tiempo_expira=5;
        $date = Carbon::now();
        $user = new UsuarioApi();
        //VALIDAR LOS DATOS SE VERIFICA APARTE
        $response = ["res" => false, "msg"=>""];
        $data = json_decode($request->getContent());
        $usuario = $user::where('user',$data->user)->first();
        if($usuario){
            if(Hash::check($data->password, $usuario->password)){
                $token = $usuario->createToken("VivePlus_2022");
                $response = [
                    "res" => true,
                    "msg"=>$token->plainTextToken,
                    "created_at"=>$date->format('Y-m-d H:i:s'),
                    "expired_at"=>$date->addMinutes($tiempo_expira)->format('Y-m-d H:i:s')
                ];
            }else{
                $response['msg'] = "Credenciales incorrectas.";
            }
        }else{
            $response['msg'] = "Usuario no encontrado.";
        }
        return response()->json($response);
    }
}
