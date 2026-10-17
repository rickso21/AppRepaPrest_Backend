<?php

namespace App\Listeners;

use App\Events\PrestamoSolicitado;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotificarPrestamoSolicitado
{
   public function __construct(
        protected PushNotificationService $push
    ) {}

    public function handle(PrestamoSolicitado $event): void
    {
        try {
            $prestamo = $event->prestamo;
            $usuario = $prestamo->usuario; // Asumiendo que tienes esta relación en el modelo Prestamo

            if (!$usuario || empty($usuario->expo_push_token)) return;

            $this->push->sendToUser(
                user: $usuario,
                title: 'Delivery Sobre Ruedas',
                body: "📝 Tu solicitud de préstamo por $" . number_format($prestamo->monto_solicitado, 0) . " ha sido recibida.",
                data: [
                    'screen' => 'SolicitudPrestamo',
                    'prestamoId' => $prestamo->id,
                ]
            );

            Log::info('[push.prestamo] Solicitud notificada', ['prestamo_id' => $prestamo->id]);
        } catch (\Throwable $e) {
            Log::error('[push.prestamo] Error solicitud: ' . $e->getMessage());
        }
    }
}
