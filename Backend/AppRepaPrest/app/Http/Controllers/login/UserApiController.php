<?php

namespace App\Http\Controllers\login;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistroRequest;
use App\Models\UserAPI;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class UserApiController extends Controller
{
    public function register(RegistroRequest $request)
    {
        $validate = $request->validated();

        try {
            $password_hash = Hash::make($validate["password"]);
            $user_to_create = UserAPI::create([
                "user" => $validate["user"],
                "password" => $password_hash
            ]);
            return response()->json([
                "response" => true,
                "message" => "Usuario creado correctamente"
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                "response" => false,
                "message" => "Error en creacion de usuario",
                "error" => $th->getMessage()
            ]);
        }
    }

    public function login(LoginRequest $request)
    {
        $validate = $request->validated();
        try {
            $user_to_found = UserAPI::where("user", $validate["user"])->first();
            $password_compare = Hash::check($validate["password"], $user_to_found->password);
            if (!$password_compare) {
                return response()->json([
                    "status" => false,
                    "message" => "Credenciales no validas"
                ], 403);
            }
            $token_sanctum = $user_to_found->createToken("auth_token")->plainTextToken;
            return response()->json([
                "status" => true,
                "token" => $token_sanctum,
                "expiration" => Carbon::now()->addMinutes(60)->setTimezone('America/Mexico_City')->toDateTimeString()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function example_route()
    {
        try {
            return response()->json([
                "status" => true,
                "message" => "Ejemplo de ruta protegida"
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "messsage" => $th->getMessage()
            ], 500);
        }
    }
}
