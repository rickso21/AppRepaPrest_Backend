<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Commands registrados aquí si los tienes
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // =============================================
        // 1. VERIFICAR TRANSFERENCIAS PENDIENTES
        // =============================================
        // Cada 30 minutos verifica transferencias pendientes
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
        // Verificación adicional cada hora como respaldo
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
        // 3. LIMPIAR PAGOS EXPIRADOS (OPCIONAL)
        // =============================================
        // Marcar como expirados los pagos que no se completaron
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
        // Limpiar registros de transferencias antiguas (opcional)
        $schedule->call(function () {
            try {
                $fechaLimite = now()->subDays(30);

                $limpiados = \App\Models\Pago::where('status', 2) // Rechazados
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
        // 5. REPORTE DIARIO DE TRANSFERENCIAS (OPCIONAL)
        // =============================================
        // Enviar reporte diario de transferencias del día
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
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
