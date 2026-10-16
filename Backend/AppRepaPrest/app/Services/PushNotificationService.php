<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Endpoint oficial de Expo.
     */
    protected const EXPO_ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * Expo acepta máximo 100 mensajes por request.
     */
    protected const CHUNK_SIZE = 100;

    /**
     * Envía una notificación a un usuario específico.
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
        ?string $sound = 'default'
    ): bool {
        if (empty($user->expo_push_token)) {
            return false;
        }

        return $this->sendToTokens([$user->expo_push_token], $title, $body, $data, $sound) > 0;
    }

    /**
     * Envía a varios usuarios, excluyendo opcionalmente al actor.
     */
    public function sendToUsers(
        Collection|array $users,
        string $title,
        string $body,
        array $data = [],
        ?int $excludeUserId = null
    ): int {
        $tokens = collect($users)
            ->when($excludeUserId, fn($c) => $c->where('id', '!=', $excludeUserId))
            ->pluck('expo_push_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            return 0;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Envía a una lista cruda de tokens.
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        ?string $sound = 'default'
    ): int {
        $tokens = array_values(array_filter(array_unique($tokens)));

        if (empty($tokens)) {
            return 0;
        }

        $enviados = 0;

        foreach (array_chunk($tokens, self::CHUNK_SIZE) as $chunk) {
            $messages = array_map(fn($token) => [
                'to'        => $token,
                'sound'     => $sound,
                'title'     => $title,
                'body'      => $body,
                'data'      => $data,
                'priority'  => 'high',
                'channelId' => 'default',
            ], $chunk);

            try {
                $response = Http::withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])->post(self::EXPO_ENDPOINT, $messages);

                if ($response->failed()) {
                    Log::warning('[push] Expo respondió con error', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    continue;
                }

                $this->cleanInvalidTokens($response->json('data') ?? [], $chunk);
                $enviados += count($chunk);
            } catch (\Throwable $e) {
                Log::error('[push] Error enviando a Expo: ' . $e->getMessage());
            }
        }

        return $enviados;
    }

    /**
     * Limpia tokens inválidos (DeviceNotRegistered).
     */
    protected function cleanInvalidTokens(array $results, array $tokens): void
    {
        foreach ($results as $i => $result) {
            $status  = $result['status'] ?? null;
            $details = $result['details']['error'] ?? null;

            if ($status === 'error' && $details === 'DeviceNotRegistered') {
                $badToken = $tokens[$i] ?? null;
                if ($badToken) {
                    User::where('expo_push_token', $badToken)
                        ->update(['expo_push_token' => null]);
                    Log::info("[push] Token inválido eliminado: {$badToken}");
                }
            }
        }
    }
}
