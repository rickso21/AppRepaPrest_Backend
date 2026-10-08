<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
    // ✅ 1. Si es su grupo exacto, permitir
    if ((int) $user->grupo_id === (int) $groupId) {
        return true;
    }

    // ✅ 2. Si es un grupo visible para él (emergencia, monitoreo, etc.), permitir
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
