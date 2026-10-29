<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        // Commands registrados aquí si los tienes
    ];

    protected function schedule(Schedule $schedule)
    {
        // =============================================
        // 1. VERIFICAR TRANSFERENCIAS PENDIENTES
        // =============================================
        $schedule->call(function () {
            try {
                $controller = new \App\Http\Controllers\tranferencia\tranferenciaController();
                $result = $controller->verificarTransferenciasPendientes();

                Log::info('Cron: Verificación de transferencias ejecutada', [
                    'resultado' => $result->getData(true)
                ]);
            } catch (\Exception $e) {
                Log::error('Cron: Error en verificación de transferencias', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        })->everyThirtyMinutes()
          ->name('verificar-transferencias')
          ->withoutOverlapping();

        // =============================================
        // 2. VERIFICAR TRANSFERENCIAS PENDIENTES (RESPALDO)
        // =============================================
        $schedule->call(function () {
            try {
                $controller = new \App\Http\Controllers\tranferencia\tranferenciaController();
                $controller->verificarTransferenciasPendientes();
            } catch (\Exception $e) {
                Log::error('Cron: Error en verificación de transferencias (backup)', [
                    'error' => $e->getMessage()
                ]);
            }
        })->hourly()
          ->name('verificar-transferencias-backup')
          ->withoutOverlapping();

        // =============================================
        // 3. LIMPIAR PAGOS EXPIRADOS
        // =============================================
        $schedule->call(function () {
            try {
                $expirados = \App\Models\Pago::where('status', 0)
                    ->where('estado_pago', 'esperando_transferencia')
                    ->where('fecha_expiracion', '<', now())
                    ->update([
                        'estado_pago' => 'expirado',
                        'observaciones' => \DB::raw("CONCAT(observaciones, ' - Expirado automáticamente: ', NOW())")
                    ]);

                if ($expirados > 0) {
                    Log::info('Cron: Pagos expirados marcados', ['cantidad' => $expirados]);
                }
            } catch (\Exception $e) {
                Log::error('Cron: Error al marcar pagos expirados', [
                    'error' => $e->getMessage()
                ]);
            }
        })->daily()
          ->name('limpiar-pagos-expirados');

        // =============================================
        // 4. LIMPIAR LOGS DE TRANSFERENCIAS ANTIGUOS
        // =============================================
        $schedule->call(function () {
            try {
                $fechaLimite = now()->subDays(30);

                $limpiados = \App\Models\Pago::where('status', 2)
                    ->where('fecha_pago', '<', $fechaLimite)
                    ->delete();

                if ($limpiados > 0) {
                    Log::info('Cron: Pagos antiguos eliminados', ['cantidad' => $limpiados]);
                }
            } catch (\Exception $e) {
                Log::error('Cron: Error al limpiar pagos antiguos', [
                    'error' => $e->getMessage()
                ]);
            }
        })->weekly()
          ->name('limpiar-pagos-antiguos');

        // =============================================
        // 5. REPORTE DIARIO DE TRANSFERENCIAS
        // =============================================
        $schedule->call(function () {
            try {
                $hoy = now()->startOfDay();
                $manana = now()->endOfDay();

                $pendientes = \App\Models\Pago::where('status', 0)
                    ->where('estado_pago', 'esperando_transferencia')
                    ->whereBetween('fecha_pago', [$hoy, $manana])
                    ->count();

                $aprobados = \App\Models\Pago::where('status', 1)
                    ->where('estado_pago', 'confirmado')
                    ->whereBetween('fecha_confirmacion', [$hoy, $manana])
                    ->count();

                $rechazados = \App\Models\Pago::where('status', 2)
                    ->whereBetween('fecha_pago', [$hoy, $manana])
                    ->count();

                Log::info('Reporte diario de transferencias', [
                    'fecha' => now()->toDateString(),
                    'pendientes' => $pendientes,
                    'aprobados' => $aprobados,
                    'rechazados' => $rechazados,
                    'total' => $pendientes + $aprobados + $rechazados
                ]);

            } catch (\Exception $e) {
                Log::error('Cron: Error en reporte diario', [
                    'error' => $e->getMessage()
                ]);
            }
        })->dailyAt('23:59')
          ->name('reporte-diario-transferencias');

        // =============================================
        // 6. EXPIRAR ANUNCIOS VENCIDOS
        // =============================================
        $schedule->call(function () {
            try {
                $expirados = \App\Models\Ad::where('status_id', 1)
                    ->whereNotNull('end_at')
                    ->where('end_at', '<', now())
                    ->update([
                        'status_id' => 2,   // 2 = expirado
                        'updated_at' => now(),
                    ]);

                if ($expirados > 0) {
                    Log::info('Cron: Anuncios expirados marcados', [
                        'cantidad' => $expirados,
                        'fecha'    => now()->toDateString(),
                    ]);
                } else {
                    Log::info('Cron: Sin anuncios expirados hoy');
                }
            } catch (\Exception $e) {
                Log::error('Cron: Error al expirar anuncios', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        })->dailyAt('03:00')
          ->name('expirar-anuncios')
          ->withoutOverlapping();

        // =============================================
        // 7. LIMPIAR ANUNCIOS RECHAZADOS ANTIGUOS (opcional)
        // =============================================
        $schedule->call(function () {
            try {
                $limpiados = \App\Models\Ad::where('status_id', 0)
                    ->where('created_at', '<', now()->subDays(7))
                    ->delete();

                if ($limpiados > 0) {
                    Log::info('Cron: Anuncios pendientes eliminados', [
                        'cantidad' => $limpiados,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Cron: Error al limpiar anuncios pendientes', [
                    'error' => $e->getMessage(),
                ]);
            }
        })->weekly()
          ->name('limpiar-anuncios-pendientes');

        // =============================================
        // 8. 👇 NUEVO: AUTO-DESCONECTAR REPARTIDORES INACTIVOS
        // =============================================
        // Se ejecuta cada 5 minutos.
        //
        // Marca como "desconectado" a los repartidores que llevan más de
        // REPARTIDOR_TIMEOUT_MIN minutos (default 30) sin enviar ubicación.
        //
        // ⚠️ Este cron REEMPLAZA la llamada que antes se hacía dentro de
        // General_location(). Si se deja en General_location, cada snapshot
        // marca al otro rider como desconectado → los marcadores parpadean.
        $schedule->call(function () {
    try {
        $afectados = repartidor_desconectar_inactivos();
        if ($afectados > 0) {
            Log::info('Cron: Repartidores auto-desconectados por inactividad', [
                'cantidad' => $afectados,
                'timeout_min' => repartidor_timeout_minutos(),
                'fecha' => now()->toDateTimeString(),
            ]);
        }
    } catch (\Exception $e) {
        Log::error('Cron: Error al auto-desconectar repartidores', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
})->everyMinute()   // ✅ cada 1 min (antes everyFiveMinutes)
  ->name('repartidor-desconectar-inactivos')
  ->withoutOverlapping();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
