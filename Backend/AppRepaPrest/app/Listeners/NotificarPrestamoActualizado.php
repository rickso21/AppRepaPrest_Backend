<?php

namespace App\Listeners;

use App\Events\PrestamoActualizado;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotificarPrestamoActualizado
{
      public function __construct(
        protected PushNotificationService $push
    ) {}

    public function handle(PrestamoActualizado $event): void
    {
        try {
            $prestamo = $event->prestamo;
            $usuario = $prestamo->usuario;

            if (!$usuario || empty($usuario->expo_push_token)) return;

            $title = 'Delivery Sobre Ruedas';
            $body = '';
            $screen = 'SolicitudPrestamo';

            // Evaluamos el nuevo estado
            switch ($event->estadoNuevo) {
                case 2: // Aprobado
                    $body = "✅ ¡Buenas noticias! Tu préstamo por $" . number_format($prestamo->monto_solicitado, 0) . " ha sido APROBADO.";
                    $screen = 'PrestamoActivo'; // O la pantalla correspondiente
                    break;
                case 4: // Pagado
                    $body = "🎉 ¡Felicidades! Has liquidado tu préstamo. Tu línea de crédito ha sido reactivada.";
                    break;
                case 5: // Rechazado
                    $body = "❌ Tu solicitud de préstamo ha sido rechazada. Motivo: " . ($prestamo->motivo_rechazo ?? 'No especificado');
                    break;
                default:
                    return; // Si es otro estado, no notificamos
            }

            $this->push->sendToUser(
                user: $usuario,
                title: $title,
                body: $body,
                data: [
                    'screen' => $screen,
                    'prestamoId' => $prestamo->id,
                ]
            );

            Log::info('[push.prestamo] Estado actualizado notificado', [
                'prestamo_id' => $prestamo->id,
                'nuevo_estado' => $event->estadoNuevo
            ]);
        } catch (\Throwable $e) {
            Log::error('[push.prestamo] Error actualizado: ' . $e->getMessage());
        }
    }
}
