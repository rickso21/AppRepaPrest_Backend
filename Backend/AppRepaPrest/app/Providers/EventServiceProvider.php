<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

use App\Events\LoanApplicationCreated;
use App\Listeners\SendWhatsAppLoanNotification;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Eventos por defecto de Laravel
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        //NUESTRO EVENTO PERSONALIZADO PARA NOTIFICACIONES WHATSAPP
        LoanApplicationCreated::class => [
            SendWhatsAppLoanNotification::class,
        ],

        // OPCIONAL: PARA REGISTRAR INICIOS DE SESIÓN (LOGS)
        Login::class => [
            // Puedes agregar listeners aquí si quieres
        ],

        Logout::class => [
            // Puedes agregar listeners aquí si quieres
        ],

        Failed::class => [
            // Puedes agregar listeners aquí si quieres
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // AQUÍ PUEDES REGISTRAR EVENTOS ADICIONALES CON CLOSURES
        // O SUSCRIPCIONES DE EVENTOS

        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        // SI QUIERES QUE LARAVEL DESCUBRA AUTOMÁTICAMENTE LOS EVENTOS
        // EN LA CARPETA app/Listeners, DEJA false PARA DESACTIVARLO
        return false;
    }

    /**
     * Get the listener directories that should be used for event discovery.
     *
     * @return array<int, string>
     */
    protected function discoverEventsWithin(): array
    {
        return [
            $this->app->path('Listeners'),
        ];
    }
}
