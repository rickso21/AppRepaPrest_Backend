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
use App\Events\UbicacionActualizada;
use App\Events\EstadoCambiado;
use App\Events\PanicoActivado;
use App\Events\PanicoDesactivado;
use App\Services\PanicPush;

class MapaController extends Controller
{
    /**
     * POST /mapa/ubicacion
     */
    public function Current_location(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'latitud'  => 'required|numeric|between:-90,90',
                'longitud' => 'required|numeric|between:-180,180',
            ]);

            Ubicacion::updateOrCreate(
                ['usuario_id' => $user->id],
                [
                    'latitud'    => $request->latitud,
                    'longitud'   => $request->longitud,
                    'es_activa'  => true,
                    'created_at' => now(),
                ]
            );

            /*
            $estadoActual = EstadoRepartidor::updateOrCreate(
                ['usuario_id' => $user->id],
                ['ultima_actualizacion' => now()]
            );
            */

            repartidor_marcar_conectado($user->id);
            $estadoActual = EstadoRepartidor::where('usuario_id', $user->id)->first();

            // WebSocket: avisa a los miembros del grupo
            if (($estadoActual->estado ?? 'desconectado') === 'conectado' && $user->grupo_id) {
                $this->emitir(new UbicacionActualizada(
                    (int) $user->grupo_id,
                    (int) $user->id,
                    trim($user->nombre . ' ' . ($user->apellido_p ?? '')),
                    (float) $request->latitud,
                    (float) $request->longitud,
                    'conectado',
                    $user->avatar_url,
                    $user->avatar_circular_url,
                ));
            }

            return response()->json([
                'res' => true,
                'msg' => 'Ubicación actualizada',
                'data' => [
                    'usuario_id' => $user->id,
                    'latitud'    => (float) $request->latitud,
                    'longitud'   => (float) $request->longitud,
                    'estado'     => $estadoActual->estado ?? 'desconectado',
                    'hora'       => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando ubicación: ' . $e->getMessage());
            return response()->json([
                'res' => false,
                'msg' => 'Error al actualizar ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /mapa/repartidores
     */
    public function General_location(Request $request)
    {
        try {
            $user = $request->user();


             repartidor_desconectar_inactivos($user->grupo_id);

            $repartidores = DB::table('tbl_user as u')
                ->join('tbl_estado_repartidor as er', 'u.id', '=', 'er.usuario_id')
                ->leftJoin('tbl_ubicaciones as ub', function ($join) {
                    $join->on('u.id', '=', 'ub.usuario_id')
                         ->where('ub.es_activa', '=', 1)
                         ->where('ub.created_at', '>=', repartidor_limite_inactividad());
                })
                ->leftJoin('tbl_alertas_panico as ap', function ($join) {
                    $join->on('u.id', '=', 'ap.usuario_id')
                         ->where('ap.estado', '=', 'activa');
                })
                ->where('u.status_id', 1)
                ->where('er.estado', 'conectado')
                ->where('u.grupo_id', $user->grupo_id)
                ->where('u.id', '!=', $user->id)
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

            // Mismos accesores que usa el WebSocket → la URL del avatar es IDÉNTICA
            // por HTTP y por WS (antes HTTP usaba asset() y WS el accesor).
            $usuarios = User::whereIn('id', $repartidores->pluck('id'))
                ->get()
                ->keyBy('id');

            $formateados = $repartidores->map(function ($item) use ($usuarios) {
                $u = $usuarios[$item->id] ?? null;

                return [
                    'id'                  => (int) $item->id,
                    'nombre'              => trim($item->nombre . ' ' . ($item->apellido_p ?? '')),
                    'telefono'            => $item->telefono ?? '',
                    'avatar_url'          => $u?->avatar_url,
                    'avatar_circular_url' => $u?->avatar_circular_url,
                    'latitud'             => $item->latitud ? (float) $item->latitud : 0,
                    'longitud'            => $item->longitud ? (float) $item->longitud : 0,
                    'estado'              => $item->estado_repartidor ?? 'desconectado',
                    'ultima_ubicacion'    => $item->ultima_ubicacion,
                    'en_panico'           => !is_null($item->alerta_panico_id),
                    'tipo_emergencia'     => $item->tipo_emergencia ?? null,
                    'hora_panico'         => $item->hora_panico ?? null,
                ];
            })->values();

            return response()->json([
                'res'   => true,
                'data'  => $formateados,
                'total' => $formateados->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error en obtenerRepartidores:', [
                'mensaje' => $e->getMessage(),
                'linea'   => $e->getLine(),
                'archivo' => $e->getFile(),
            ]);

            return response()->json([
                'res'  => false,
                'msg'  => 'Error al obtener repartidores: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * GET /mapa/repartidor/{id}
     */
    public function Specific_user(Request $request, $id)
    {
        try {
            $user = $request->user();

            $repartidor = DB::table('tbl_user as u')
                ->join('tbl_estado_repartidor as er', 'u.id', '=', 'er.usuario_id')
                ->leftJoin('tbl_ubicaciones as ub', function ($join) {
                    $join->on('u.id', '=', 'ub.usuario_id')
                         ->where('ub.es_activa', '=', 1)
                         ->where('ub.created_at', '>=', repartidor_limite_inactividad());
                })
                ->where('u.id', '=', $id)
                ->where('u.status_id', 1)
                ->where('u.grupo_id', $user->grupo_id)
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
                    'msg' => 'Repartidor no encontrado o no pertenece a tu grupo',
                ], 404);
            }

            $u = User::find($repartidor->id);

            return response()->json([
                'res'  => true,
                'data' => [
                    'id'                  => (int) $repartidor->id,
                    'nombre'              => trim($repartidor->nombre . ' ' . ($repartidor->apellido_p ?? '')),
                    'telefono'            => $repartidor->telefono ?? '',
                    'avatar_url'          => $u?->avatar_url,
                    'avatar_circular_url' => $u?->avatar_circular_url,
                    'latitud'             => $repartidor->latitud ? (float) $repartidor->latitud : 0,
                    'longitud'            => $repartidor->longitud ? (float) $repartidor->longitud : 0,
                    'estado'              => $repartidor->estado_repartidor ?? 'desconectado',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'res' => false,
                'msg' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /mapa/estado
     */
    public function UserStatus(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Usuario no autenticado',
                ], 401);
            }

            $request->validate([
                'estado' => 'required|in:conectado,desconectado',
            ]);

            $estadoDB = $request->estado;


            /*
            EstadoRepartidor::updateOrCreate(
                ['usuario_id' => $user->id],
                [
                    'estado'               => $estadoDB,
                    'ultima_actualizacion' => now(),
                ]
            );
            */

           if ($estadoDB === 'desconectado') {
        repartidor_desconectar_usuario($user->id, 'UserStatus manual'); // setea estado + es_activa=false
            } else {
                repartidor_marcar_conectado($user->id);                          // setea estado + timestamp
                Ubicacion::where('usuario_id', $user->id)->update(['es_activa' => true]);
            }
            // ──────────────────────────────────

            if ($user->grupo_id) {
                $this->emitir(new EstadoCambiado((int) $user->grupo_id, (int) $user->id, $estadoDB));
            }

            return response()->json([
                'res' => true,
                'msg' => "Estado cambiado a {$request->estado}",
                'data' => [
                    'usuario_id' => (int) $user->id,
                    'estado'     => $request->estado,
                    'estado_db'  => $estadoDB,
                    'hora'       => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error cambiando estado:', [
                'mensaje' => $e->getMessage(),
                'linea'   => $e->getLine(),
            ]);

            return response()->json([
                'res' => false,
                'msg' => 'Error al cambiar estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /mapa/panico
     */
    public function On_User_alert(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'accion'          => 'required|in:activar,desactivar',
                'latitud'         => 'required_if:accion,activar|numeric|between:-90,90',
                'longitud'        => 'required_if:accion,activar|numeric|between:-180,180',
                'tipo_emergencia' => 'required_if:accion,activar|in:asaltado,accidente,medico,otro',
            ]);

            if ($request->accion === 'activar') {
                // Desactivar alertas activas anteriores de este usuario
                AlertaPanico::where('usuario_id', $user->id)
                    ->where('estado', 'activa')
                    ->update([
                        'estado'              => 'desactivada',
                        'fecha_desactivacion' => now(),
                        'razon_desactivacion' => 'Nueva alerta activada',
                    ]);

                $alerta = AlertaPanico::create([
                    'usuario_id'            => $user->id,
                    'latitud'               => $request->latitud,
                    'longitud'              => $request->longitud,
                    'tipo_emergencia'       => $request->tipo_emergencia,
                    'descripcion_adicional' => 'Biker en peligro',
                    'estado'                => 'activa',
                    'fecha_activacion'      => now(),
                ]);

                if ($user->grupo_id) {
                    $gid = (int) $user->grupo_id;
                    $uid = (int) $user->id;
                    $nom = trim($user->nombre . ' ' . ($user->apellido_p ?? ''));
                    $aid = (int) $alerta->id;
                    $lat = (float) $request->latitud;
                    $lng = (float) $request->longitud;

                    $this->emitir(new PanicoActivado(
                        $gid, $uid, $nom, $lat, $lng, $request->tipo_emergencia, $aid
                    ));

                    // Se ejecuta al terminar la respuesta (sin cola). `static` evita capturar $this.
                    app()->terminating(static fn () => PanicPush::enviar($gid, $uid, $nom, $aid, $lat, $lng));
                }

                return response()->json([
                    'res' => true,
                    'msg' => 'Alerta de pánico activada',
                    'data' => [
                        'alerta_id'       => (int) $alerta->id,
                        'estado'          => 'activa',
                        'tipo_emergencia' => $request->tipo_emergencia,
                        'hora'            => now()->toISOString(),
                    ],
                ]);
            }

            // Desactivar
            $alerta = AlertaPanico::where('usuario_id', $user->id)
                ->where('estado', 'activa')
                ->first();

            if (!$alerta) {
                return response()->json([
                    'res' => false,
                    'msg' => 'No tienes ninguna alerta de pánico activa',
                ], 404);
            }

            $alerta->update([
                'estado'                     => 'desactivada',
                'fecha_desactivacion'        => now(),
                'razon_desactivacion'        => $request->razon_desactivacion ?? 'Desactivada por usuario',
                'desactivada_por_usuario_id' => $user->id,
                'activo'                     => 0,
            ]);

            if ($user->grupo_id) {
                $this->emitir(new PanicoDesactivado((int) $user->grupo_id, (int) $user->id, (int) $alerta->id));
            }

            return response()->json([
                'res' => true,
                'msg' => 'Alerta de pánico desactivada',
                'data' => [
                    'alerta_id' => (int) $alerta->id,
                    'estado'    => 'desactivada',
                    'hora'      => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en togglePanico: ' . $e->getMessage());
            return response()->json([
                'res' => false,
                'msg' => 'Error al procesar pánico: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Emite un evento por WebSocket sin poder romper la operación principal:
     * si Reverb está caído, el pánico / ubicación igual se guardan en BD.
     */
    private function emitir($event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::error('Broadcast falló (' . class_basename($event) . '): ' . $e->getMessage());
        }
    }
}
