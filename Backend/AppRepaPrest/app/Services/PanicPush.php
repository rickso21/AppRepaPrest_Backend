<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notificación push de pánico (llega con la app cerrada o en segundo plano).
 * AJUSTA TABLA/COLUMNA a donde tu savePushToken guarda el token de Expo.
 */
class PanicPush
{
    private const TABLA = 'tbl_user';
    private const COL_TOKEN = 'push_token';

    public static function enviar(int $grupoId, int $userId, string $nombre, int $alertaId, float $lat, float $lng): void
    {
        try {
            $tokens = DB::table(self::TABLA)
                ->where('grupo_id', $grupoId)->where('id', '!=', $userId)
                ->whereNotNull(self::COL_TOKEN)->pluck(self::COL_TOKEN)
                ->filter(fn ($t) => str_starts_with((string) $t, 'ExponentPushToken'))->values();

            if ($tokens->isEmpty()) return;

            $mensajes = $tokens->map(fn ($t) => [
                'to' => $t,
                'title' => '🚨 SOLICITUD DE APOYO',
                'body' => "$nombre solicitó apoyo, acude a su auxilio",
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'panico',
                'ttl' => 60,
                'data' => ['tipo' => 'panico', 'alertaId' => $alertaId, 'userId' => $userId,
                           'latitud' => $lat, 'longitud' => $lng],
            ])->all();

            foreach (array_chunk($mensajes, 100) as $lote) {
                Http::timeout(5)->post('https://exp.host/--/api/v2/push/send', $lote);
            }
        } catch (\Throwable $e) {
            Log::error('Push pánico falló: ' . $e->getMessage());
        }
    }
}
