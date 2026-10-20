<?php

namespace App\Http\Controllers;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use Illuminate\Http\Request;

/**
 * Walkie-talkie: LiveKit ya es WebSocket/WebRTC, no necesita Reverb.
 * composer require agence104/livekit-server-sdk
 * .env: LIVEKIT_URL=wss://xxxx.livekit.cloud  LIVEKIT_API_KEY=...  LIVEKIT_API_SECRET=...
 *
 * NOTA: este archivo NO venía en tu zip; es una versión base. Si ya tenías uno,
 * conserva el tuyo y solo asegúrate de que la sala sea por grupo ("grupo-{id}").
 */
class LiveKitController extends Controller
{
    public function Get_Token(Request $request)
    {
        $user = $request->user();
        if (!$user->grupo_id) {
            return response()->json(['res' => false, 'msg' => 'Usuario sin grupo'], 403);
        }

        $opts = (new AccessTokenOptions())
            ->setIdentity((string) $user->id)
            ->setName(trim($user->nombre . ' ' . ($user->apellido_p ?? '')))
            ->setTtl(3600);

        $grant = (new VideoGrant())
            ->setRoomJoin()
            ->setRoomName('grupo-' . $user->grupo_id)   // una sala por grupo
            ->setCanPublish(true)
            ->setCanSubscribe(true);

        $jwt = (new AccessToken(config('services.livekit.key'), config('services.livekit.secret')))
            ->init($opts)->setGrant($grant)->toJwt();

        return response()->json([
            'res'  => true,
            'data' => ['token' => $jwt, 'url' => config('services.livekit.url')],
        ]);
    }
}
