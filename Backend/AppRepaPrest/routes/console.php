<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $caducados = \App\Models\EstadoRepartidor::where('estado', 'conectado')
        ->where('ultima_actualizacion', '<', now()->subMinutes(3))->get();
    foreach ($caducados as $e) {
        $e->update(['estado' => 'desconectado']);
        \App\Models\Ubicacion::where('usuario_id', $e->usuario_id)->update(['es_activa' => false]);
        $u = \App\Models\User::find($e->usuario_id);
        if ($u?->grupo_id) event(new \App\Events\EstadoCambiado((int) $u->grupo_id, (int) $u->id, 'desconectado'));
    }
})->everyMinute();
