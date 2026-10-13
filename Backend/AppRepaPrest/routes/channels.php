<?php

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('group.{groupId}', function (User $user, $groupId) {
    $grupoCanal = (int) $groupId;
    $miGrupo    = (int) $user->grupo_id;

    // 1) Es su propio grupo → autorizado
    if ($miGrupo === $grupoCanal) {
        return true;
    }

    // 2) Es un grupo visible (emergencia / monitoreo)
    try {
        $gruposVisibles = Grupo::gruposVisiblesDe($miGrupo);
        $autorizado = in_array($grupoCanal, $gruposVisibles, true);

        Log::info('[channels] verificación de canal', [
            'user_id'         => $user->id,
            'user_grupo_id'   => $miGrupo,
            'grupo_canal'     => $grupoCanal,
            'grupos_visibles' => $gruposVisibles,
            'autorizado'      => $autorizado,
        ]);

        return $autorizado;
    } catch (\Throwable $e) {
        Log::error('[channels] error verificando canal', [
            'user_id' => $user->id,
            'grupo'   => $grupoCanal,
            'error'   => $e->getMessage(),
        ]);
        return false;
    }
});
