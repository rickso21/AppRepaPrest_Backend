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
public function Current_location(Request $request)
{
    try {
        $user = $request->user();

        $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
        ]);

        $ubicacion = Ubicacion::updateOrCreate(
            ['usuario_id' => $user->id],
            [
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'es_activa' => true,
                'created_at' => now()
            ]
        );

        EstadoRepartidor::updateOrCreate(
            ['usuario_id' => $user->id],
            [
                'ultima_actualizacion' => now()
            ]
        );

        // Obtener el estado actual del usuario para la respuesta
        $estadoActual = EstadoRepartidor::where('usuario_id', $user->id)->first();

        return response()->json([
            'res' => true,
            'msg' => 'Ubicación actualizada',
            'data' => [
                'usuario_id' => $user->id,
                'latitud' => (float) $request->latitud,
                'longitud' => (float) $request->longitud,
                'estado' => $estadoActual->estado ?? 'desconectado',
                'hora' => now()->toISOString()
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Error actualizando ubicación: ' . $e->getMessage());
        return response()->json([
            'res' => false,
            'msg' => 'Error al actualizar ubicación: ' . $e->getMessage()
        ], 500);
    }
}
 public function General_location(Request $request)
{
    try {
        $user = $request->user();

       $repartidores = DB::table('tbl_user as u')
    ->join('tbl_estado_repartidor as er', 'u.id', '=', 'er.usuario_id')
    ->leftJoin('tbl_ubicaciones as ub', function($join) {
        $join->on('u.id', '=', 'ub.usuario_id')
             ->where('ub.es_activa', '=', 1);
    })
    ->leftJoin('tbl_alertas_panico as ap', function($join) {
        $join->on('u.id', '=', 'ap.usuario_id')
             ->where('ap.estado', '=', 'activa');
    })
   // ->where('u.id', '!=', $user->id)
    ->where('u.status_id', 1)
    ->where('er.estado', 'conectado')
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

        // Log para depuración
        \Log::info('Repartidores encontrados:', [
            'total' => $repartidores->count(),
            'usuario_actual' => $user->id,
            'data' => $repartidores->map(function($item) {
                return [
                    'id' => $item->id,
                    'nombre' => $item->nombre,
                    'estado' => $item->estado_repartidor,
                    'tiene_ubicacion' => !is_null($item->latitud)
                ];
            })->toArray()
        ]);

        // Formatear respuesta
        $repartidoresFormateados = $repartidores->map(function($item) {
            return [
                'id' => (int) $item->id,
                'nombre' => trim($item->nombre . ' ' . ($item->apellido_p ?? '')),
                'telefono' => $item->telefono ?? '',
                'latitud' => $item->latitud ? (float) $item->latitud : 0,
                'longitud' => $item->longitud ? (float) $item->longitud : 0,
                'estado' => $item->estado_repartidor ?? 'desconectado',
                'ultima_ubicacion' => $item->ultima_ubicacion,
                'en_panico' => !is_null($item->alerta_panico_id),
                'tipo_emergencia' => $item->tipo_emergencia ?? null,
                'hora_panico' => $item->hora_panico ?? null
            ];
        });

        return response()->json([
            'res' => true,
            'data' => $repartidoresFormateados,
            'total' => $repartidoresFormateados->count()
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en obtenerRepartidores:', [
            'mensaje' => $e->getMessage(),
            'linea' => $e->getLine(),
            'archivo' => $e->getFile()
        ]);

        return response()->json([
            'res' => false,
            'msg' => 'Error al obtener repartidores: ' . $e->getMessage(),
            'data' => []
        ], 500);
    }
}


public function Specific_user($id)
{
    try {
        $repartidor = DB::table('tbl_user as u')
            ->join('tbl_estado_repartidor as er', 'u.id', '=', 'er.usuario_id')
            ->leftJoin('tbl_ubicaciones as ub', function($join) {
                $join->on('u.id', '=', 'ub.usuario_id')
                     ->where('ub.es_activa', '=', 1);
            })
            ->where('u.id', '=', $id)
            ->where('u.status_id', 1)
            ->select(
                'u.id',
                'u.nombre',
                'u.apellido_p',
                'u.telefono',
                'ub.latitud',
                'ub.longitud',
                'er.estado as estado_repartidor'
            )
            ->first();

        if (!$repartidor) {
            return response()->json([
                'res' => false,
                'msg' => 'Repartidor no encontrado'
            ], 404);
        }

        return response()->json([
            'res' => true,
            'data' => $repartidor
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'res' => false,
            'msg' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

  // En MapaController.php - cambiarEstado()
public function UserStatus(Request $request)
{
    try {
        $user = $request->user();

        //  Verificar autenticación
        if (!$user) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado'
            ], 401);
        }

        $request->validate([
            'estado' => 'required|in:conectado,desconectado'
        ]);

        // Mapear el estado del frontend al estado interno
        $estadoDB = $request->estado === 'conectado' ? 'conectado' : 'desconectado';

        // Actualizar estado explícitamente
        $estado = EstadoRepartidor::updateOrCreate(
            ['usuario_id' => $user->id],
            [
                'estado' => $estadoDB,
                'ultima_actualizacion' => now()
            ]
        );

        // Si se desconecta, desactivar ubicaciones
        if ($request->estado === 'desconectado') {
            Ubicacion::where('usuario_id', $user->id)
                ->update(['es_activa' => false]);

            \Log::info('Usuario desconectado:', [
                'usuario_id' => $user->id,
                'nombre' => $user->nombre,
                'ubicaciones_desactivadas' => true
            ]);
        } else {
            // Si se conecta, activar ubicación existente o crear una nueva
            $ubicacion = Ubicacion::where('usuario_id', $user->id)->first();
            if ($ubicacion) {
                $ubicacion->update(['es_activa' => true]);
            }

            \Log::info('Usuario conectado:', [
                'usuario_id' => $user->id,
                'nombre' => $user->nombre,
                'estado' => $estadoDB
            ]);
        }

        return response()->json([
            'res' => true,
            'msg' => "Estado cambiado a {$request->estado}",
            'data' => [
                'usuario_id' => (int) $user->id,
                'estado' => $request->estado,
                'estado_db' => $estadoDB,
                'hora' => now()->toISOString()
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error cambiando estado:', [
            'mensaje' => $e->getMessage(),
            'linea' => $e->getLine()
        ]);

        return response()->json([
            'res' => false,
            'msg' => 'Error al cambiar estado: ' . $e->getMessage()
        ], 500);
    }
}

   public function On_User_alert(Request $request)
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
