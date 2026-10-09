<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
    if ((int) $user->grupo_id === (int) $groupId) {
        return true;
    }

    $gruposVisibles = \App\Models\Grupo::gruposVisiblesDe((int) $user->grupo_id);

    $autorizado = in_array((int) $groupId, $gruposVisibles, true);

    Log::info('[channels] verificación de canal', [
        'user_id'          => $user->id,
        'user_grupo_id'    => $user->grupo_id,
        'grupo_canal'      => $groupId,
        'grupos_visibles'  => $gruposVisibles,
        'autorizado'       => $autorizado,
    ]);

    return $autorizado;
}, ['guards' => ['sanctum']]);
