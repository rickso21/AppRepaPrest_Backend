<?php

namespace App\Listeners;

use App\Events\PrestamoSolicitado;
use App\Services\PushNotificationService;
use App\Models\User; // ✅ Necesitamos el modelo User
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

            // 🔍 BUSCAR A LOS ASESORES DEL GRUPO
            $asesores = User::where('grupo_id', $usuarioSolicitante->grupo_id)
                ->whereIn('rol_id', [3, 5]) // Rol 3 = Asesor, 5 = Admin
                ->where('status_id', 1)
                ->whereNotNull('expo_push_token')
                ->get();

            if ($asesores->isEmpty()) {
                Log::info('[push.prestamo] No se encontraron asesores para notificar', ['grupo_id' => $usuarioSolicitante->grupo_id]);
                return;
            }

            // Notificar a todos los asesores encontrados
            $this->push->sendToUsers(
                users: $asesores,
                title: 'Delivery Sobre Ruedas',
                body: "🔔 Nueva solicitud de préstamo de " . $usuarioSolicitante->full_name . " por $" . number_format($prestamo->monto_solicitado, 0),
                data: [
                    'screen' => 'SolicitudPrestamo',
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
