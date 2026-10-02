<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiveKitController extends Controller
{
    public function Get_Token(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Usuario no autenticado'
                ], 401);
            }

            // Sala por grupo: todos los repartidores del mismo grupo se escuchan entre sí.
            // Si el usuario no tiene grupo_id, se usa una sala personal.
            $roomName = $user->grupo_id
                ? "grupo-{$user->grupo_id}"
                : "repartidor-{$user->id}";

            // Nombre visible: nombre + apellido_p, igual que en MapaController
            $displayName = trim(
                ($user->nombre ?? '') . ' ' . ($user->apellido_p ?? '')
            ) ?: "Usuario {$user->id}";

            $tokenOptions = (new AccessTokenOptions())
                ->setIdentity((string) $user->id)
                ->setName($displayName);

            $videoGrant = (new VideoGrant())
                ->setRoomJoin()
                ->setRoomName($roomName)
                ->setCanPublish(true)
                ->setCanSubscribe(true);

            $token = (new AccessToken(
                config('services.livekit.key'),
                config('services.livekit.secret')
            ))
                ->init($tokenOptions)
                ->setGrant($videoGrant)
                ->toJwt();

            Log::info('LiveKit token generado:', [
                'usuario_id' => $user->id,
                'grupo_id' => $user->grupo_id,
                'room' => $roomName,
            ]);

            return response()->json([
                'res' => true,
                'msg' => 'Token generado',
                'data' => [
                    'token' => $token,
                    'url' => config('services.livekit.url'),
                    'room' => $roomName,
                    'identity' => (string) $user->id,
                    'name' => $displayName,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generando token LiveKit:', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

            return response()->json([
                'res' => false,
                'msg' => 'Error al generar token: ' . $e->getMessage(),
            ], 500);
        }
    }
}
