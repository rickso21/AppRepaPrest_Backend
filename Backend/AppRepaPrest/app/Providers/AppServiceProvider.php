<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // HTTPS en producción
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // 🛡️ Limitador de login: 5 intentos / 15 min por IP + email
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));
            $key = $email . '|' . $request->ip();

            return Limit::perMinutes(15, 5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'res' => false,
                        'msg' => 'Demasiados intentos de inicio de sesión. Espera 15 minutos antes de volver a intentar.',
                    ], 429, $headers);
                });
        });

        // 🛡️ Limitador de registro: 3 registros / hora por IP
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'res' => false,
                        'msg' => 'Has creado demasiadas cuentas desde esta red. Intenta más tarde.',
                    ], 429, $headers);
                });
        });

        // 🛡️ Limitador de forgot-password: 3 solicitudes / hora por IP
        RateLimiter::for('forgot-password', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));
            $key = $email . '|' . $request->ip();

            return Limit::perHour(3)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'res' => false,
                        'msg' => 'Demasiadas solicitudes de recuperación. Intenta más tarde.',
                    ], 429, $headers);
                });
        });
    }
}
