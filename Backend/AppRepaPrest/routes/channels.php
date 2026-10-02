<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
    Log::info('[channels] callback ejecutado', [
        'user_id' => $user->id,
        'user_email' => $user->email,
        'user_grupo_id' => $user->grupo_id,
        'grupo_id_del_canal' => $groupId,
        'coinciden' => (int) $user->grupo_id === (int) $groupId,
    ]);

    return (int) $user->grupo_id === (int) $groupId;
}, ['guards' => ['sanctum']]);
