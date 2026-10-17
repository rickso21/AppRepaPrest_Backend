<?php

namespace App\Listeners;

use App\Events\PrestamoSolicitado;
use App\Services\PushNotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificarPrestamoSolicitado
{
    public function __construct(
        protected PushNotificationService $push
    ) {}

    public function handle(PrestamoSolicitado $event): void
    {
        try {
            $prestamo = $event->prestamo;
            $usuarioSolicitante = $prestamo->usuario;

            if (!$usuarioSolicitante) return;

            // 🔍 BUSCAR A LOS ASESORES Y ADMINS A NIVEL GLOBAL
            // Buscamos cualquier usuario cuyo rol sea super admin (3) o Asesor (5)
            $asesores = User::whereIn('rol_id', [3, 5]) // 3=super admin, 5=Asesor
                ->where('status_id', 1) // Activos
                ->whereNotNull('expo_push_token')
                ->where('expo_push_token', '!=', '')
                ->get();

            if ($asesores->isEmpty()) {
                Log::info('[push.prestamo] No se encontraron asesores con token registrado');
                return;
            }

            $this->push->sendToUsers(
                users: $asesores,
                title: 'Delivery Sobre Ruedas',
                body: "🔔 Nueva solicitud de préstamo de " . $usuarioSolicitante->full_name . " por $" . number_format($prestamo->monto_solicitado, 0),
                data: [
                    'screen' => 'PanelAsesor',
                    'prestamoId' => $prestamo->id,
                ]
            );

            Log::info('[push.prestamo] Solicitud notificada a asesores', [
                'prestamo_id' => $prestamo->id,
                'asesores_notificados' => $asesores->count()
            ]);

        } catch (\Throwable $e) {
            Log::error('[push.prestamo] Error solicitud: ' . $e->getMessage());
        }
    }
}
