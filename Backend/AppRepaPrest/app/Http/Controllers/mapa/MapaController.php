<?php

namespace App\Http\Controllers\mapa;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ubicacion;
use App\Models\EstadoRepartidor;
use App\Models\AlertaPanico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MapaController extends Controller
{
   public function actualizarUbicacion(Request $request)
{
    try {
        $user = $request->user();

        \Log::info('Recibiendo ubicación:', [
            'user_id' => $user->id,
            'user_name' => $user->nombre,
            'latitud' => $request->latitud,
            'longitud' => $request->longitud,
            'token' => $request->bearerToken()
        ]);

        $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
        ]);

        $ubicacion = Ubicacion::create([
            'usuario_id' => $user->id,
            'latitud' => $request->latitud,
            'longitud' => $request->longitud,
            'es_activa' => true,
            'created_at' => now()
        ]);

        \Log::info('Ubicación guardada:', [
            'id' => $ubicacion->id,
            'usuario_id' => $user->id
        ]);

        $estado = EstadoRepartidor::updateOrCreate(
            ['usuario_id' => $user->id],
            [
                'estado' => 'disponible',
                'ultima_actualizacion' => now()
            ]
        );

        return response()->json([
            'res' => true,
            'msg' => 'Ubicación actualizada',
            'data' => [
                'usuario_id' => $user->id,
                'latitud' => (float) $request->latitud,
                'longitud' => (float) $request->longitud,
                'estado' => $estado->estado,
                'hora' => now()->toISOString()
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('❌ Error actualizando ubicación: ' . $e->getMessage());
        return response()->json([
            'res' => false,
            'msg' => 'Error al actualizar ubicación: ' . $e->getMessage()
        ], 500);
    }
}
  public function obtenerRepartidores(Request $request)
{
    try {
        $user = $request->user();

        // CONSULTA CORREGIDA - Solo usuarios CONECTADOS
        $repartidores = DB::table('tbl_user as u')
            ->join(
                DB::raw('(SELECT usuario_id, MAX(created_at) as ultima_fecha
                          FROM tbl_ubicaciones
                          WHERE es_activa = 1
                          AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                          GROUP BY usuario_id) as ultima_ubicacion'),
                'u.id', '=', 'ultima_ubicacion.usuario_id'
            )
            ->join('tbl_ubicaciones as ub', function($join) {
                $join->on('u.id', '=', 'ub.usuario_id')
                     ->on('ub.created_at', '=', 'ultima_ubicacion.ultima_fecha');
            })
            ->join('tbl_estado_repartidor as er', 'u.id', '=', 'er.usuario_id')
            ->leftJoin('tbl_alertas_panico as ap', function($join) {
                $join->on('u.id', '=', 'ap.usuario_id')
                     ->where('ap.estado', '=', 'activa');
            })
            ->where('u.id', '!=', $user->id)
            ->where('u.status_id', 1)
            ->where('ub.es_activa', 1)
            ->where('er.estado', 'disponible')
            ->select(
                'u.id',
                'u.nombre',
                'u.apellido_p',
                'u.telefono',
                'ub.latitud',
                'ub.longitud',
                'ub.created_at as ultima_ubicacion',
                'er.estado as estado_repartidor',
                'ap.id as alerta_panico_id',
                'ap.tipo_emergencia',
                'ap.fecha_activacion as hora_panico'
            )
            ->orderBy('ub.created_at', 'desc')
            ->get();

        \Log::info('Repartidores CONECTADOS encontrados:', [
            'cantidad' => $repartidores->count(),
            'data' => $repartidores->toArray()
        ]);

        $repartidoresFormateados = $repartidores->map(function($item) {
            return [
                'id' => (int) $item->id,
                'nombre' => $item->nombre . ' ' . ($item->apellido_p ?? ''),
                'telefono' => $item->telefono ?? '',
                'latitud' => (float) $item->latitud,
                'longitud' => (float) $item->longitud,
                'estado' => $item->estado_repartidor ?? 'desconectado',
                'ultima_ubicacion' => $item->ultima_ubicacion,
                'en_panico' => $item->alerta_panico_id ? true : false,
                'tipo_emergencia' => $item->tipo_emergencia ?? null,
                'hora_panico' => $item->hora_panico ?? null
            ];
        });

        return response()->json([
            'res' => true,
            'data' => $repartidoresFormateados
        ]);

    } catch (\Exception $e) {
        \Log::error('Error obteniendo repartidores: ' . $e->getMessage());
        return response()->json([
            'res' => false,
            'msg' => 'Error al obtener repartidores: ' . $e->getMessage(),
            'data' => []
        ], 500);
    }
}
   public function cambiarEstado(Request $request)
{
    try {
        $user = $request->user();

        $request->validate([
            'estado' => 'required|in:conectado,desconectado'
        ]);

        $nuevoEstado = $request->estado;
        $estadoDB = $nuevoEstado === 'conectado' ? 'disponible' : 'desconectado';

        // Actualizar estado
        $estado = EstadoRepartidor::updateOrCreate(
            ['usuario_id' => $user->id],
            [
                'estado' => $estadoDB,
                'ultima_actualizacion' => now()
            ]
        );

        // Si se desconecta, desactivar ubicaciones anteriores
        if ($nuevoEstado === 'desconectado') {
            Ubicacion::where('usuario_id', $user->id)
                ->where('es_activa', true)
                ->update(['es_activa' => false]);
        }

        \Log::info('Estado cambiado:', [
            'usuario_id' => $user->id,
            'nombre' => $user->nombre,
            'nuevo_estado' => $nuevoEstado,
            'estado_db' => $estadoDB
        ]);

        return response()->json([
            'res' => true,
            'msg' => "Estado cambiado a {$nuevoEstado}",
            'data' => [
                'usuario_id' => (int) $user->id,
                'estado' => $nuevoEstado,
                'hora' => now()->toISOString()
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error cambiando estado: ' . $e->getMessage());
        return response()->json([
            'res' => false,
            'msg' => 'Error al cambiar estado: ' . $e->getMessage()
        ], 500);
    }
}

   public function togglePanico(Request $request)
{
    try {
        $user = $request->user();

        $request->validate([
            'accion' => 'required|in:activar,desactivar',
            'latitud' => 'required_if:accion,activar|numeric|between:-90,90',
            'longitud' => 'required_if:accion,activar|numeric|between:-180,180',
            'tipo_emergencia' => 'required_if:accion,activar|in:asaltado,accidente,medico,otro'
        ]);

        if ($request->accion === 'activar') {
            // DESACTIVAR TODAS las alertas activas anteriores de este usuario
            AlertaPanico::where('usuario_id', $user->id)
                ->where('estado', 'activa')
                ->update([
                    'estado' => 'desactivada',
                    'fecha_desactivacion' => now(),
                    'razon_desactivacion' => 'Nueva alerta activada'
                ]);

            // Crear UNA SOLA alerta nueva
            $alerta = AlertaPanico::create([
                'usuario_id' => $user->id,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'tipo_emergencia' => $request->tipo_emergencia,
                'estado' => 'activa',
                'fecha_activacion' => now()
            ]);

            \Log::info('Alerta de pánico activada:', [
                'usuario_id' => $user->id,
                'alerta_id' => $alerta->id
            ]);

            return response()->json([
                'res' => true,
                'msg' => 'Alerta de pánico activada',
                'data' => [
                    'alerta_id' => (int) $alerta->id,
                    'estado' => 'activa',
                    'tipo_emergencia' => $request->tipo_emergencia,
                    'hora' => now()->toISOString()
                ]
            ]);

        } else {
            // Desactivar alerta
            $alerta = AlertaPanico::where('usuario_id', $user->id)
                ->where('estado', 'activa')
                ->first();

            if (!$alerta) {
                return response()->json([
                    'res' => false,
                    'msg' => 'No tienes ninguna alerta de pánico activa'
                ], 404);
            }

            $alerta->update([
                'estado' => 'desactivada',
                'fecha_desactivacion' => now(),
                'razon_desactivacion' => $request->razon_desactivacion ?? 'Desactivada por usuario'
            ]);

            \Log::info('Alerta de pánico desactivada:', [
                'usuario_id' => $user->id,
                'alerta_id' => $alerta->id
            ]);

            return response()->json([
                'res' => true,
                'msg' => 'Alerta de pánico desactivada',
                'data' => [
                    'alerta_id' => (int) $alerta->id,
                    'estado' => 'desactivada',
                    'hora' => now()->toISOString()
                ]
            ]);
        }

    } catch (\Exception $e) {
        \Log::error('Error en togglePanico: ' . $e->getMessage());
        return response()->json([
            'res' => false,
            'msg' => 'Error al procesar pánico: ' . $e->getMessage()
        ], 500);
    }
}

}
