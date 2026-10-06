<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Requests\login\editUserRequest;
use App\Http\Requests\login\loginRequest;
use App\Http\Requests\login\registerAdminRequest;
use App\Http\Requests\login\registerRequest;
use App\Models\Grupo;
use App\Models\User;
use App\Services\BrevoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class loginController extends Controller
{
    // ================================================================
    // CONSTANTES
    // ================================================================

    private const TOKEN_EXPIRATION_MINUTES = 60 * 24 * 7;

    /** Roles del sistema */
    private const ROL_USER        = 1;
    private const ROL_ADMIN       = 2;
    private const ROL_SUPER_ADMIN = 3;
    private const ROL_COMERCIO    = 4;

    /** Estados del usuario */
    private const STATUS_ACTIVO   = 1;
    private const STATUS_INACTIVO = 2;

    // ================================================================
    // REGISTROS
    // ================================================================

    public function register_admin(registerAdminRequest $request)
    {
        if ($request->email === null && $request->telefono === null) {
            return response()->json([
                'res' => false,
                'msg' => 'No puede generarse el usuario sin teléfono o email',
            ], 400);
        }

        // Generar código único de grupo
        do {
            $codigo = Str::upper(Str::random(8));
        } while (Grupo::where('code', $codigo)->exists());

        try {
            DB::beginTransaction();

            $user = $this->crearUsuario($request, self::ROL_ADMIN);


            // Grupo PRINCIPAL
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
                'res'             => true,
                'msg'             => 'Se generó el usuario con éxito',
                'code'            => $codigo,
                'grupo_principal' => [
                    'id'   => $grupoPrincipal->id,
                    'name' => $grupoPrincipal->group_name,
                ],
                'grupo_emergencia' => [
                    'id'   => $grupoEmergencia->id,
                    'name' => $grupoEmergencia->group_name,
                ],
                'grupo_monitoreo' => [
                    'id'   => $grupoMonitoreo->id,
                    'name' => $grupoMonitoreo->group_name,
                ],
                'user' => $this->formatearUsuarioBasico($user),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            //Log::error('[register_admin] Error: ' . $th->getMessage());

            return response()->json([
                'res' => false,
                'msg' => $th->getMessage(),
            ], 409);
        }
    }


    public function register(registerRequest $request)
    {
        try {
            // Buscar grupo por código
            // Solo debe encontrar la PRINCIPAL (parent_id NULL)
            $grupoPrincipal = Grupo::where('code', $request->code)
                ->whereNull('parent_id')
                ->where('tipo_grupo', Grupo::TIPO_PRINCIPAL)
                ->first();

            if (!$grupoPrincipal) {
                return response()->json(['res' => false, 'msg' => 'El código de grupo no es válido'], 404);
            }

            if ($grupoPrincipal->status != 1) {
                return response()->json(['res' => false, 'msg' => 'El grupo no está activo'], 403);
            }

            DB::beginTransaction();

            $user = $this->crearUsuario($request, self::ROL_USER);
            $user->grupo_id = $grupoPrincipal->id;
            $user->save();

            DB::commit();

            $user->load(['configuracionPanico', 'estadoRepartidor']);

            // Derivamos los subgrupos
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
                    'usuario'       => $this->formatearUsuarioCompleto($user),
                    'configuracion' => $user->configuracionPanico,
                    'estado'        => $user->estadoRepartidor,
                    'grupos'        => [
                        'principal'  => ['id' => $grupoPrincipal->id,  'name' => $grupoPrincipal->group_name],
                        'emergencia' => $grupoEmergencia ? ['id' => $grupoEmergencia->id, 'name' => $grupoEmergencia->group_name] : null,
                        'monitoreo'  => $grupoMonitoreo  ? ['id' => $grupoMonitoreo->id,  'name' => $grupoMonitoreo->group_name]  : null,
                    ],
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[register] Error: ' . $th->getMessage(), [
                'request' => $request->all(),
                'trace'   => $th->getTraceAsString(),
            ]);

            return response()->json([
                'res' => false,
                'msg' => 'Error al generar el usuario: ' . $th->getMessage(),
            ], 409);
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

            $user = $this->crearUsuario($request, self::ROL_COMERCIO);
            $user->nombre_comercio = trim($request->nombre_comercio);
            $user->save();

            DB::commit();

            return response()->json([
                'res'  => true,
                'msg'  => 'Comercio registrado con éxito',
                'user' => [
                    'id'              => $user->id,
                    'nombre'          => $this->nombreCompleto($user),
                    'nombre_comercio' => $user->nombre_comercio,
                    'email'           => $user->email,
                    'rol_id'          => $user->rol_id,
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[register_comercio] Error: ' . $th->getMessage());

            return response()->json([
                'res' => false,
                'msg' => 'Error al registrar comercio: ' . $th->getMessage(),
            ], 409);
        }
    }

    // ================================================================
    // AUTENTICACIÓN
    // ================================================================
    public function login(loginRequest $request)
    {
        $resp        = ['res' => false, 'msg' => 'Algo salió mal'];
        $status_resp = 400;

        $user = User::where(function ($query) use ($request) {
            $query->where('email', $request->email)
                ->orWhere('telefono', $request->email);
        })->first();

        if (!$user) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no encontrado, vuelve a intentarlo',
            ], 404);
        }

        // Validar cuenta activa
        if ((int) $user->status_id !== self::STATUS_ACTIVO) {
            return response()->json([
                'res' => false,
                'msg' => 'Tu cuenta está inhabilitada. Contacta a soporte para reactivarla.',
            ], 403);
        }

        // Validar contraseña
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'res' => false,
                'msg' => 'Contraseña incorrecta.',
            ], 401);
        }

        // Crear token
        $token = $user->createToken('Palabra_Secreta');
        $date  = Carbon::now();

        return response()->json([
            'res'        => true,
            'token'      => $token->plainTextToken,
            'created_at' => $date->format('Y-m-d H:i:s'),
            'expired_at' => $date->copy()
                ->addMinutes(self::TOKEN_EXPIRATION_MINUTES)
                ->format('Y-m-d H:i:s'),
            'msg'        => numeroAleatorio(1, 10),
            'user'       => $this->formatearUsuarioLogin($user),
        ], 200);
    }

    // ================================================================
    // PERFIL DEL USUARIO
    // ================================================================

    /**
     * PUT/POST /api/update
     *
     * Actualiza los datos del usuario autenticado.
     * Permite cambiar nombre, apellidos, teléfono, ciudad, email,
     * contraseña, avatar y portada.
     */
    public function edit_user(editUserRequest $request)
    {
        $user_token = $request->user();

        try {
            $user = User::findOrFail($user_token->id);
        } catch (\Throwable $th) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no encontrado',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $this->actualizarDatosBasicos($user, $request);

            // Validar email único si cambia
            if ($request->filled('email') && $request->email !== $user->email) {
                $exists = User::where('email', $request->email)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($exists) {
                    DB::rollBack();
                    return response()->json([
                        'res' => false,
                        'msg' => 'El correo ya está registrado por otro usuario',
                    ], 409);
                }

                $user->email = $request->email;
            }

            // Contraseña (si viene)
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            // Archivos (avatar/portada)
            $this->actualizarArchivos($user, $request);

            $user->save();
            DB::commit();

            return response()->json([
                'res'  => true,
                'msg'  => 'Se editó el usuario con éxito',
                'user' => [
                    'id'              => $user->id,
                    'nombre'          => $user->nombre,
                    'nombre_completo' => $this->nombreCompleto($user),
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

            return response()->json([
                'res' => false,
                'msg' => 'Error al editar el usuario: ' . $th->getMessage(),
            ], 409);
        }
    }

    /**
     * GET /api/user/{id}
     *
     * Devuelve la información pública de un usuario.
     */
    public function show_user(Request $request, $id)
    {
        $user = User::with('grupo')->find($id);

        if (!$user) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json([
            'res'  => true,
            'user' => [
                'id'                 => $user->id,
                'nombre'             => $this->nombreCompleto($user),
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

    /**
     * GET /api/grupo/{id}
     *
     * Devuelve la información de un grupo y su administrador.
     * El admin se busca primero por rol SUPER_ADMIN y si no existe,
     * se usa el líder del grupo (user_leader_id).
     */
    public function show_grupo(Request $request, $id)
    {
        $grupo = Grupo::with(['emergencia', 'monitoreo'])->find($id);

        if (!$grupo) {
            return response()->json(['res' => false, 'msg' => 'Grupo no encontrado'], 404);
        }

        // Si llegan con el ID de un subgrupo, subimos al principal
        if (!is_null($grupo->parent_id)) {
            $grupo = Grupo::with(['emergencia', 'monitoreo'])->find($grupo->parent_id);
        }

        $adminGrupo = User::where('grupo_id', $grupo->id)
            ->where('rol_id', self::ROL_SUPER_ADMIN)
            ->first();

        if (!$adminGrupo && $grupo->user_leader_id) {
            $adminGrupo = User::find($grupo->user_leader_id);
        }

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
                    'nombre'      => $this->nombreCompleto($adminGrupo),
                    'logo_url'    => $adminGrupo->logo_url ?? null,
                    'portada_url' => $adminGrupo->portada_url,
                    'avatar_url'  => $adminGrupo->avatar_url,
                ] : null,
                'grupo_emergencia' => $grupo->emergencia ? [
                    'id'         => $grupo->emergencia->id,
                    'group_name' => $grupo->emergencia->group_name,
                    'status'     => $grupo->emergencia->status,
                ] : null,
                'grupo_monitoreo' => $grupo->monitoreo ? [
                    'id'         => $grupo->monitoreo->id,
                    'group_name' => $grupo->monitoreo->group_name,
                    'status'     => $grupo->monitoreo->status,
                ] : null,
            ],
        ], 200);
    }



    // ================================================================
    // GESTIÓN DE CUENTA
    // ================================================================

    /**
     * DELETE /api/user/delete-account
     *
     * Inhabilita la cuenta del usuario autenticado (status_id = 2).
     * No permite la acción si tiene préstamos activos (estados 1, 2, 3).
     * Revoca todos los tokens de Sanctum.
     */
    public function delete_account(Request $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado',
            ], 401);
        }

        $user = User::find($user_token->id);

        if (!$user) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no encontrado',
            ], 404);
        }

        // Validar préstamos activos
        $prestamo = $this->buscarPrestamoBloqueante($user->id);

        if ($prestamo) {
            return response()->json([
                'res'  => false,
                'msg'  => $this->mensajePrestamoBloqueante($prestamo->estado_prestamo_id),
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

            $user->status_id = self::STATUS_INACTIVO;
            $user->save();

            // Revocar tokens
            $user->tokens()->delete();

            DB::commit();

            return response()->json([
                'res' => true,
                'msg' => 'Tu cuenta ha sido inhabilitada. Si deseas reactivarla, contacta a soporte.',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[delete_account] Error: ' . $th->getMessage());

            return response()->json([
                'res' => false,
                'msg' => 'Error al inhabilitar la cuenta: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/admin/user/reactivate
     *
     * Reactiva una cuenta inhabilitada. Solo admin/super admin.
     */
    public function reactivate_account(Request $request)
    {
        $admin = $request->user();

        if (!$admin || !in_array($admin->rol_id, [self::ROL_ADMIN, self::ROL_SUPER_ADMIN])) {
            return response()->json([
                'res' => false,
                'msg' => 'No tienes permisos para reactivar cuentas.',
            ], 403);
        }

        $request->validate([
            'user_id' => 'required|integer|exists:tbl_user,id',
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no encontrado.',
            ], 404);
        }

        if ((int) $user->status_id === self::STATUS_ACTIVO) {
            return response()->json([
                'res' => false,
                'msg' => 'El usuario ya está activo.',
            ], 400);
        }

        $user->status_id = self::STATUS_ACTIVO;
        $user->save();

        return response()->json([
            'res' => true,
            'msg' => 'Cuenta reactivada correctamente.',
        ], 200);
    }


    /**
     * GET /api/grupo/{id}/miembros
     * Devuelve los miembros del grupo (principal o emergencias).
     */
    public function miembros_grupo(Request $request, $id)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json(['res' => false, 'msg' => 'Grupo no encontrado'], 404);
        }

        // Si es emergencia, subimos al principal
        if (!is_null($grupo->parent_id)) {
            $grupo = Grupo::find($grupo->parent_id);
        }

        $miembros = User::where('grupo_id', $grupo->id)
            ->where('status_id', self::STATUS_ACTIVO)
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'nombre'     => $this->nombreCompleto($u),
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


     public function savePushToken(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado',
            ], 401);
        }

        $request->validate([
            'expo_push_token' => 'required|string|max:255',
        ]);

        $user->expo_push_token = $request->expo_push_token;
        $user->save();

        return response()->json([
            'res' => true,
            'msg' => 'Token guardado correctamente',
        ], 200);
    }
    // ================================================================
    // HELPERS PRIVADOS
    // ================================================================

    /**
     * Crea un usuario base desde un request.
     * Procesa avatar/portada y asigna rol/status.
     */
    private function crearUsuario($request, int $rolId): User
    {
        $user = new User();
        $user->nombre     = trim($request->name);
        $user->apellido_p = $request->apellido_p ? trim($request->apellido_p) : null;
        $user->apellido_m = $request->apellido_m ? trim($request->apellido_m) : null;
        $user->email      = $request->email;
        $user->password   = Hash::make($request->password);
        $user->telefono   = $request->telefono;
        $user->ciudad     = $request->ciudad;
        $user->rol_id     = $rolId;
        $user->status_id  = self::STATUS_ACTIVO;

        // Archivos
        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('users/avatars', 'public');
            Log::info('[crearUsuario] Avatar guardado', ['path' => $user->avatar]);
        }

        if ($request->hasFile('portada')) {
            $user->portada = $request->file('portada')->store('users/portadas', 'public');
            Log::info('[crearUsuario] Portada guardada', ['path' => $user->portada]);
        }

        $user->save();

        return $user;
    }

    /**
     * Actualiza los datos básicos del usuario desde el request.
     * Solo actualiza los campos que vienen en el request.
     */
    private function actualizarDatosBasicos(User $user, Request $request): void
    {
        if ($request->filled('nombre')) {
            $nombreLimpio = trim(explode(' ', trim($request->nombre))[0] ?? '');
            if ($nombreLimpio !== '') {
                $user->nombre = $nombreLimpio;
            }
        }

        if ($request->filled('apellido_p')) $user->apellido_p = trim($request->apellido_p);
        if ($request->filled('apellido_m')) $user->apellido_m = trim($request->apellido_m);
        if ($request->filled('telefono'))   $user->telefono   = $request->telefono;
        if ($request->filled('ciudad'))     $user->ciudad     = $request->ciudad;
    }

    /**
     * Reemplaza avatar y portada del usuario, eliminando los anteriores.
     */
    private function actualizarArchivos(User $user, Request $request): void
    {
        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('users/avatars', 'public');
        }

        if ($request->hasFile('portada')) {
            if ($user->portada && Storage::disk('public')->exists($user->portada)) {
                Storage::disk('public')->delete($user->portada);
            }
            $user->portada = $request->file('portada')->store('users/portadas', 'public');
        }
    }

    /**
     * Busca un préstamo activo que bloquee la inhabilitación de cuenta.
     */
    private function buscarPrestamoBloqueante(int $userId)
    {
        return \App\Models\Prestamo::where('usuario_id', $userId)
            ->whereIn('estado_prestamo_id', [1, 2, 3])
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Mensaje según el estado del préstamo bloqueante.
     */
    private function mensajePrestamoBloqueante(int $estadoId): string
    {
        return match ($estadoId) {
            1 => 'No puedes eliminar tu cuenta porque tienes un préstamo pendiente de aprobación.',
            2 => 'No puedes eliminar tu cuenta porque tienes un préstamo aprobado (PAGOS EN PROCESO).',
            3 => 'No puedes eliminar tu cuenta porque tienes un préstamo activo en curso.',
            default => 'No puedes inhabilitar tu cuenta porque tienes un préstamo en proceso.',
        };
    }

    /**
     * Devuelve el nombre completo de un usuario.
     */
    private function nombreCompleto(User $user): string
    {
        return trim(
            ($user->nombre ?? '') . ' ' .
                ($user->apellido_p ?? '') . ' ' .
                ($user->apellido_m ?? '')
        );
    }

    /**
     * Formato de respuesta del usuario para `register_admin`.
     */
    private function formatearUsuarioBasico(User $user): array
    {
        return [
            'id'              => $user->id,
            'nombre'          => $user->nombre,
            'nombre_completo' => $this->nombreCompleto($user),
            'avatar_url'      => $user->avatar_url,
            'portada_url'     => $user->portada_url,
        ];
    }

    /**
     * Formato completo del usuario para `register`.
     */
    private function formatearUsuarioCompleto(User $user): array
    {
        return [
            'id'              => $user->id,
            'nombre'          => $user->nombre,
            'nombre_completo' => $this->nombreCompleto($user),
            'apellido_p'      => $user->apellido_p,
            'apellido_m'      => $user->apellido_m,
            'email'           => $user->email,
            'telefono'        => $user->telefono,
            'ciudad'          => $user->ciudad,
            'rol_id'          => $user->rol_id,
            'status_id'       => $user->status_id,
            'grupo_id'        => $user->grupo_id,
            'avatar_url'      => $user->avatar_url,
            'portada_url'     => $user->portada_url,
            'created_at'      => $user->created_at,
        ];
    }

    /**
     * Formato del usuario para `login`.
     */
    private function formatearUsuarioLogin(User $user): array
    {
        return [
            'id'              => $user->id,
            'nombre'          => $user->nombre,
            'nombre_completo' => $this->nombreCompleto($user),
            'nombre_raw'      => $user->nombre,
            'apellido_p'      => $user->apellido_p,
            'apellido_m'      => $user->apellido_m,
            'email'           => $user->email,
            'telefono'        => $user->telefono,
            'ciudad'          => $user->ciudad,
            'grupo_id'        => $user->grupo_id,
            'rol_id'          => $user->rol_id,
            'avatar_url'      => $user->avatar_url,
            'portada_url'     => $user->portada_url,
            'nombre_comercio' => $user->nombre_comercio ?? null,
        ];
    }
}
