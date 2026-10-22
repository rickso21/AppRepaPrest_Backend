<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push de pánico a TODOS los miembros del grupo (app cerrada o en segundo plano).
 * El WebSocket cubre a quien tiene la app abierta; esto cubre al resto.
 *
 * Correcciones: la columna real es `expo_push_token` (antes decía `push_token`, que no
 * existe → nunca se enviaba nada) y esta clase ahora SÍ se llama desde MapaController.
 */
class PanicPush
{
    public static function enviar(int $grupoId, int $userId, string $nombre, int $alertaId, float $lat, float $lng): void
    {
        try {
            $tokens = DB::table('tbl_user')
                ->where('grupo_id', $grupoId)
                ->where('id', '!=', $userId)
                ->where('status_id', 1)
                ->whereNotNull('expo_push_token')
                ->pluck('expo_push_token')
                ->filter(fn ($t) => str_starts_with((string) $t, 'ExponentPushToken')
                                 || str_starts_with((string) $t, 'ExpoPushToken'))
                ->unique()
                ->values();

            if ($tokens->isEmpty()) {
                Log::info('PanicPush: sin tokens en el grupo', ['grupo' => $grupoId]);
                return;
            }

            $mensajes = $tokens->map(fn ($t) => [
                'to'               => $t,
                'title'            => '🚨 SOLICITUD DE APOYO',
                'body'             => "$nombre solicitó apoyo, acude a su auxilio",
                'sound'            => 'default',
                'priority'         => 'high',
                'channelId'        => 'panico',          // canal Android creado en la app
                'interruptionLevel' => 'time-sensitive', // iOS: rompe el modo concentración
                'ttl'              => 120,
                'data'             => [
                    'tipo' => 'panico', 'alertaId' => $alertaId, 'userId' => $userId,
                    'nombre' => $nombre, 'latitud' => $lat, 'longitud' => $lng,
                ],
            ])->all();

            foreach (array_chunk($mensajes, 100) as $lote) {
                $r = Http::timeout(8)->acceptJson()->post('https://exp.host/--/api/v2/push/send', $lote);
                if ($r->failed()) {
                    Log::warning('PanicPush: Expo respondió error', ['status' => $r->status(), 'body' => $r->body()]);
                }
            }
            Log::info('PanicPush enviado', ['grupo' => $grupoId, 'destinatarios' => $tokens->count()]);
        } catch (\Throwable $e) {
            Log::error('Push pánico falló: ' . $e->getMessage());
        }
    }
}
