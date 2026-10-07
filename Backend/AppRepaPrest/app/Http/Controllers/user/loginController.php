<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Requests\login\editUserRequest;
use App\Http\Requests\login\loginRequest;
use App\Http\Requests\login\registerAdminRequest;
use App\Http\Requests\login\registerRequest;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class loginController extends Controller
{

    public function register_admin(registerAdminRequest $request)
    {
        if ($request->email === null && $request->telefono === null) {
            return user_error_response('No puede generarse el usuario sin teléfono o email', 400);
        }

        $codigo = user_generar_codigo_grupo_unico();

        try {
            DB::beginTransaction();

            $user = user_crear_desde_request($request, user_rol_admin());

            $idPrincipal  = Grupo::generarIdUnico();
            $idEmergencia = Grupo::generarIdUnico();
            $idMonitoreo  = Grupo::generarIdUnico();

            // Grupo PRINCIPAL
            $grupoPrincipal = new Grupo();
            $grupoPrincipal->id             = $idPrincipal;
            $grupoPrincipal->code           = $codigo;
            $grupoPrincipal->group_name     = $request->name_group;
            $grupoPrincipal->user_leader_id = $user->id;
            $grupoPrincipal->status         = 1;
            $grupoPrincipal->parent_id      = null;
            $grupoPrincipal->tipo_grupo     = Grupo::TIPO_PRINCIPAL;
            $grupoPrincipal->save();

            // Grupo EMERGENCIAS
            $grupoEmergencia = new Grupo();
            $grupoEmergencia->id             = $idEmergencia;
            $grupoEmergencia->code           = null;
            $grupoEmergencia->group_name     = ($request->name_group ?? 'Grupo') . ' - EMERGENCIAS';
            $grupoEmergencia->user_leader_id = $user->id;
            $grupoEmergencia->status         = 1;
            $grupoEmergencia->parent_id      = $grupoPrincipal->id;
            $grupoEmergencia->tipo_grupo     = Grupo::TIPO_EMERGENCIA;
            $grupoEmergencia->save();

            // Grupo MONITOREO
            $grupoMonitoreo = new Grupo();
            $grupoMonitoreo->id             = $idMonitoreo;
            $grupoMonitoreo->code           = null;
            $grupoMonitoreo->group_name     = ($request->name_group ?? 'Grupo') . ' - MONITOREO';
            $grupoMonitoreo->user_leader_id = $user->id;
            $grupoMonitoreo->status         = 1;
            $grupoMonitoreo->parent_id      = $grupoPrincipal->id;
            $grupoMonitoreo->tipo_grupo     = Grupo::TIPO_MONITOREO;
            $grupoMonitoreo->save();

            $user->grupo_id = $grupoPrincipal->id;
            $user->save();

            DB::commit();

            return response()->json([
                'res'              => true,
                'msg'              => 'Se generó el usuario con éxito',
                'code'             => $codigo,
                'grupo_principal'  => user_grupo_payload($grupoPrincipal),
                'grupo_emergencia' => user_grupo_payload($grupoEmergencia),
                'grupo_monitoreo'  => user_grupo_payload($grupoMonitoreo),
                'user'             => user_formatear_basico($user),
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'res' => false,
                'msg' => $th->getMessage(),
            ], 409);
        }
    }

    public function register(registerRequest $request)
    {
        try {
            $grupoPrincipal = Grupo::where('code', $request->code)
                ->whereNull('parent_id')
                ->where('tipo_grupo', Grupo::TIPO_PRINCIPAL)
                ->first();

            if (!$grupoPrincipal) {
                return user_error_response('El código de grupo no es válido', 404);
            }

            if ($grupoPrincipal->status != 1) {
                return user_error_response('El grupo no está activo', 403);
            }

            DB::beginTransaction();

            $user = user_crear_desde_request($request, user_rol_user());
            $user->grupo_id = $grupoPrincipal->id;
            $user->save();

            DB::commit();

            $user->load(['configuracionPanico', 'estadoRepartidor']);

            $grupoEmergencia = Grupo::where('parent_id', $grupoPrincipal->id)
                ->where('tipo_grupo', Grupo::TIPO_EMERGENCIA)
                ->first();

            $grupoMonitoreo = Grupo::where('parent_id', $grupoPrincipal->id)
                ->where('tipo_grupo', Grupo::TIPO_MONITOREO)
                ->first();

            return response()->json([
                'res'     => true,
                'msg'     => 'Usuario creado con éxito',
                'user_id' => $user->id,
                'data'    => [
                    'usuario'       => user_formatear_completo($user),
                    'configuracion' => $user->configuracionPanico,
                    'estado'        => $user->estadoRepartidor,
                    'grupos'        => [
                        'principal'  => user_grupo_payload($grupoPrincipal),
                        'emergencia' => user_grupo_payload($grupoEmergencia),
                        'monitoreo'  => user_grupo_payload($grupoMonitoreo),
                    ],
                ],
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[register] Error: ' . $th->getMessage(), [
                'request' => $request->all(),
                'trace'   => $th->getTraceAsString(),
            ]);

            return user_error_response('Error al generar el usuario: ' . $th->getMessage(), 409);
        }
    }

    public function register_comercio(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:100',
            'email'           => 'required|email|unique:tbl_user,email',
            'telefono'        => 'required|string|max:20|unique:tbl_user,telefono',
            'password'        => 'required|string|min:6|confirmed',
            'nombre_comercio' => 'required|string|max:150',
        ]);

        try {
            DB::beginTransaction();

            $user = user_crear_desde_request($request, user_rol_comercio());
            $user->nombre_comercio = trim($request->nombre_comercio);
            $user->save();

            DB::commit();

            return response()->json([
                'res'  => true,
                'msg'  => 'Comercio registrado con éxito',
                'user' => [
                    'id'              => $user->id,
                    'nombre'          => user_nombre_completo($user),
                    'nombre_comercio' => $user->nombre_comercio,
                    'email'           => $user->email,
                    'rol_id'          => $user->rol_id,
                ],
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[register_comercio] Error: ' . $th->getMessage());

            return user_error_response('Error al registrar comercio: ' . $th->getMessage(), 409);
        }
    }

    // AUTENTICACIÓN

    public function login(loginRequest $request)
    {
        $user = user_buscar_por_email_o_telefono($request->email);

        if (!$user) {
            return user_error_response('Usuario no encontrado, vuelve a intentarlo', 404);
        }

        if ((int) $user->status_id !== user_status_activo()) {
            return user_error_response(
                'Tu cuenta está inhabilitada. Contacta a soporte para reactivarla.',
                403
            );
        }

        if (!Hash::check($request->password, $user->password)) {
            return user_error_response('Contraseña incorrecta.', 401);
        }

        $tokenData = user_crear_token($user);

        return response()->json([
            'res'        => true,
            'token'      => $tokenData['token'],
            'created_at' => $tokenData['created_at'],
            'expired_at' => $tokenData['expired_at'],
            'msg'        => numeroAleatorio(1, 10),
            'user'       => user_formatear_login($user),
        ], 200);
    }

    // PERFIL DEL USUARIO

    public function edit_user(editUserRequest $request)
    {
        $user_token = $request->user();

        if ($response = user_validate_auth($user_token)) {
            return $response;
        }

        try {
            $user = User::findOrFail($user_token->id);
        } catch (\Throwable $th) {
            return user_error_response('Usuario no encontrado', 404);
        }

        try {
            DB::beginTransaction();

            user_actualizar_datos_basicos($user, $request);

            // Validar email único si cambia
            if ($request->filled('email') && $request->email !== $user->email) {
                $exists = User::where('email', $request->email)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($exists) {
                    DB::rollBack();
                    return user_error_response('El correo ya está registrado por otro usuario', 409);
                }

                $user->email = $request->email;
            }

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            user_actualizar_archivos($user, $request);

            $user->save();
            DB::commit();

            return response()->json([
                'res'  => true,
                'msg'  => 'Se editó el usuario con éxito',
                'user' => [
                    'id'              => $user->id,
                    'nombre'          => $user->nombre,
                    'nombre_completo' => user_nombre_completo($user),
                    'apellido_p'      => $user->apellido_p,
                    'apellido_m'      => $user->apellido_m,
                    'email'           => $user->email,
                    'telefono'        => $user->telefono,
                    'ciudad'          => $user->ciudad,
                    'avatar_url'      => $user->avatar_url,
                    'portada_url'     => $user->portada_url,
                ],
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[edit_user] Error: ' . $th->getMessage());

            return user_error_response('Error al editar el usuario: ' . $th->getMessage(), 409);
        }
    }

    public function show_user(Request $request, $id)
    {
        $user = User::with('grupo')->find($id);

        if (!$user) {
            return user_error_response('Usuario no encontrado', 404);
        }

        return response()->json([
            'res'  => true,
            'user' => [
                'id'                 => $user->id,
                'nombre'             => user_nombre_completo($user),
                'telefono'           => $user->telefono,
                'email'              => $user->email,
                'ciudad'             => $user->ciudad,
                'avatar_url'         => $user->avatar_url,
                'portada_url'        => $user->portada_url,
                'rol'                => $user->rol_id,
                'grupo'              => $user->grupo->group_name ?? null,
                'grupo_id'           => $user->grupo_id,
                'grupo_principal_id' => $user->grupo->parent_id ?? $user->grupo_id,
            ],
        ], 200);
    }

    public function show_grupo(Request $request, $id)
    {
        $grupo = Grupo::with(['emergencia', 'monitoreo'])->find($id);

        if (!$grupo) {
            return user_error_response('Grupo no encontrado', 404);
        }

        // Si llegan con el ID de un subgrupo, subimos al principal
        if (!is_null($grupo->parent_id)) {
            $grupo = Grupo::with(['emergencia', 'monitoreo'])->find($grupo->parent_id);
        }

        $adminGrupo = user_admin_de_grupo($grupo);

        return response()->json([
            'res'   => true,
            'grupo' => [
                'id'         => $grupo->id,
                'code'       => $grupo->code,
                'group_name' => $grupo->group_name,
                'status'     => $grupo->status,
                'img_url'    => $grupo->img_url ?? null,
                'admin'      => $adminGrupo ? [
                    'id'          => (int) $adminGrupo->id,
                    'nombre'      => user_nombre_completo($adminGrupo),
                    'logo_url'    => $adminGrupo->logo_url ?? null,
                    'portada_url' => $adminGrupo->portada_url,
                    'avatar_url'  => $adminGrupo->avatar_url,
                ] : null,
                'grupo_emergencia' => user_subgrupo_payload($grupo->emergencia),
                'grupo_monitoreo'  => user_subgrupo_payload($grupo->monitoreo),
            ],
        ], 200);
    }

    // GESTIÓN DE CUENTA

    public function delete_account(Request $request)
    {
        $user_token = $request->user();

        if ($response = user_validate_auth($user_token)) {
            return $response;
        }

        $user = User::find($user_token->id);

        if (!$user) {
            return user_error_response('Usuario no encontrado', 404);
        }

        $prestamo = user_buscar_prestamo_bloqueante($user->id);

        if ($prestamo) {
            return response()->json([
                'res'  => false,
                'msg'  => user_mensaje_prestamo_bloqueante($prestamo->estado_prestamo_id),
                'data' => [
                    'prestamo_id'      => $prestamo->id,
                    'folio'            => $prestamo->folio,
                    'estado'           => $prestamo->estado_prestamo_id,
                    'monto_restante'   => (int) ($prestamo->monto_restante ?? $prestamo->monto_total_pagar),
                    'pago_quincenal'   => (int) $prestamo->pago_quincenal,
                    'numero_pagos'     => (int) $prestamo->numero_pagos,
                    'pagos_realizados' => (int) ($prestamo->pagos_realizados ?? 0),
                ],
            ], 409);
        }

        try {
            DB::beginTransaction();

            $user->status_id = user_status_inactivo();
            $user->save();

            $user->tokens()->delete();

            DB::commit();

            return user_success_response(
                'Tu cuenta ha sido inhabilitada. Si deseas reactivarla, contacta a soporte.'
            );

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[delete_account] Error: ' . $th->getMessage());

            return user_error_response('Error al inhabilitar la cuenta: ' . $th->getMessage(), 500);
        }
    }

    public function reactivate_account(Request $request)
    {
        $admin = $request->user();

        if ($response = user_validate_admin($admin)) {
            return $response;
        }

        $request->validate([
            'user_id' => 'required|integer|exists:tbl_user,id',
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return user_error_response('Usuario no encontrado.', 404);
        }

        if ((int) $user->status_id === user_status_activo()) {
            return user_error_response('El usuario ya está activo.', 400);
        }

        $user->status_id = user_status_activo();
        $user->save();

        return user_success_response('Cuenta reactivada correctamente.');
    }

    // GRUPOS - MIEMBROS

    public function miembros_grupo(Request $request, $id)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return user_error_response('Grupo no encontrado', 404);
        }

        if (!is_null($grupo->parent_id)) {
            $grupo = Grupo::find($grupo->parent_id);
        }

        $miembros = User::where('grupo_id', $grupo->id)
            ->where('status_id', user_status_activo())
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'nombre'     => user_nombre_completo($u),
                'avatar_url' => $u->avatar_url,
                'telefono'   => $u->telefono,
                'rol_id'     => $u->rol_id,
            ]);

        return response()->json([
            'res'      => true,
            'grupo_id' => $grupo->id,
            'total'    => $miembros->count(),
            'miembros' => $miembros,
        ], 200);
    }

    // PUSH TOKENS

    public function savePushToken(Request $request)
    {
        $user = $request->user();

        if ($response = user_validate_auth($user)) {
            return $response;
        }

        $request->validate([
            'expo_push_token' => 'required|string|max:255',
        ]);

        $user->expo_push_token = $request->expo_push_token;
        $user->save();

        return user_success_response('Token guardado correctamente');
    }
}
