<?php

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/**
 * Autorización de canales privados por grupo.
 *
 * CAUSA RAÍZ DEL BUG: los eventos (UbicacionActualizada, EstadoCambiado, PanicoActivado,
 * PanicoDesactivado) y la app usan el canal "grupo.{id}", pero aquí solo estaba
 * registrado "group.{groupId}". Sin coincidencia, /broadcasting/auth responde 403 y la app
 * nunca queda suscrita → no recibe NINGÚN evento (ni pánico ni ubicación).
 */
$autorizarGrupo = function (User $user, $grupoId) {
    $grupoCanal = (int) $grupoId;
    $miGrupo    = (int) $user->grupo_id;

    // 1) Su propio grupo → autorizado
    if ($miGrupo === $grupoCanal) {
        return true;
    }

    // 2) Grupo visible (emergencia / monitoreo / subgrupos)
    try {
        return in_array($grupoCanal, Grupo::gruposVisiblesDe($miGrupo), true);
    } catch (\Throwable $e) {
        Log::error('[channels] error verificando canal', [
            'user_id' => $user->id,
            'grupo'   => $grupoCanal,
            'error'   => $e->getMessage(),
        ]);
        return false;
    }
};

// Canal que usan los eventos y la app móvil
Broadcast::channel('grupo.{grupoId}', $autorizarGrupo);

// Se conserva el nombre anterior por compatibilidad (si algo más lo usa)
Broadcast::channel('group.{groupId}', $autorizarGrupo);
