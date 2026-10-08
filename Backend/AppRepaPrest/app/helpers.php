<?php

// FUNCIONES LOGIN (user)

//ROLES

if (!function_exists('user_rol_user'))        { function user_rol_user()        { return 1; } }
if (!function_exists('user_rol_admin'))       { function user_rol_admin()       { return 2; } }
if (!function_exists('user_rol_super_admin')) { function user_rol_super_admin() { return 3; } }
if (!function_exists('user_rol_comercio'))    { function user_rol_comercio()    { return 4; } }

if (!function_exists('user_status_activo'))   { function user_status_activo()   { return 1; } }
if (!function_exists('user_status_inactivo')) { function user_status_inactivo() { return 2; } }

if (!function_exists('user_token_expiration_minutes')) {
    function user_token_expiration_minutes() { return 60 * 24 * 7; }
}

// RESPUESTAS

if (!function_exists('user_error_response')) {
    function user_error_response(string $msg, int $status = 400, array $extra = []): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge([
            'res' => false,
            'msg' => $msg,
        ], $extra), $status);
    }
}

if (!function_exists('user_success_response')) {
    function user_success_response(string $msg, array $extra = [], int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge([
            'res' => true,
            'msg' => $msg,
        ], $extra), $status);
    }
}

// FORMATEO DE USUARIO

if (!function_exists('user_nombre_completo')) {
    function user_nombre_completo(\App\Models\User $user): string
    {
        return trim(
            ($user->nombre ?? '') . ' ' .
            ($user->apellido_p ?? '') . ' ' .
            ($user->apellido_m ?? '')
        );
    }
}

if (!function_exists('user_formatear_basico')) {
    function user_formatear_basico(\App\Models\User $user): array
    {
        return [
            'id'              => $user->id,
            'nombre'          => $user->nombre,
            'nombre_completo' => user_nombre_completo($user),
            'avatar_url'      => $user->avatar_url,
            'portada_url'     => $user->portada_url,
        ];
    }
}

if (!function_exists('user_formatear_completo')) {
    function user_formatear_completo(\App\Models\User $user): array
    {
        return [
            'id'              => $user->id,
            'nombre'          => $user->nombre,
            'nombre_completo' => user_nombre_completo($user),
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
}

if (!function_exists('user_formatear_login')) {
    function user_formatear_login(\App\Models\User $user): array
    {
        return [
            'id'              => $user->id,
            'nombre'          => $user->nombre,
            'nombre_completo' => user_nombre_completo($user),
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

//CREACIÓN DE USUARIO

if (!function_exists('user_crear_desde_request')) {
    /**
     * Crea un usuario base desde un request.
     * Procesa avatar/portada y asigna rol/status.
     */
    function user_crear_desde_request($request, int $rolId): \App\Models\User
    {
        $user = new \App\Models\User();
        $user->nombre     = trim($request->name);
        $user->apellido_p = $request->apellido_p ? trim($request->apellido_p) : null;
        $user->apellido_m = $request->apellido_m ? trim($request->apellido_m) : null;
        $user->email      = $request->email;
        $user->password   = \Illuminate\Support\Facades\Hash::make($request->password);
        $user->telefono   = $request->telefono;
        $user->ciudad     = $request->ciudad;
        $user->rol_id     = $rolId;
        $user->status_id  = user_status_activo();

        // Archivos
        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('users/avatars', 'public');
            \Illuminate\Support\Facades\Log::info('[crearUsuario] Avatar guardado', ['path' => $user->avatar]);
        }

        if ($request->hasFile('portada')) {
            $user->portada = $request->file('portada')->store('users/portadas', 'public');
            \Illuminate\Support\Facades\Log::info('[crearUsuario] Portada guardada', ['path' => $user->portada]);
        }

        $user->save();

        return $user;
    }
}

//ACTUALIZACIÓN DE USUARIO

if (!function_exists('user_actualizar_datos_basicos')) {
    /**
     * Actualiza los datos básicos del usuario desde el request.
     */
    function user_actualizar_datos_basicos(\App\Models\User $user, $request): void
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
}

if (!function_exists('user_actualizar_archivos')) {
    /**
     * Reemplaza avatar y portada del usuario, eliminando los anteriores.
     */
    function user_actualizar_archivos(\App\Models\User $user, $request): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if ($request->hasFile('avatar')) {
            if ($user->avatar && $disk->exists($user->avatar)) {
                $disk->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('users/avatars', 'public');
        }

        if ($request->hasFile('portada')) {
            if ($user->portada && $disk->exists($user->portada)) {
                $disk->delete($user->portada);
            }
            $user->portada = $request->file('portada')->store('users/portadas', 'public');
        }
    }
}

//PRÉSTAMOS BLOQUEANTES

if (!function_exists('user_buscar_prestamo_bloqueante')) {
    /**
     * Busca un préstamo activo que bloquee la inhabilitación de cuenta.
     */
    function user_buscar_prestamo_bloqueante(int $userId)
    {
        return \App\Models\Prestamo::where('usuario_id', $userId)
            ->whereIn('estado_prestamo_id', [1, 2, 3])
            ->orderBy('id', 'desc')
            ->first();
    }
}

if (!function_exists('user_mensaje_prestamo_bloqueante')) {
    /**
     * Mensaje según el estado del préstamo bloqueante.
     */
    function user_mensaje_prestamo_bloqueante(int $estadoId): string
    {
        return match ($estadoId) {
            1 => 'No puedes eliminar tu cuenta porque tienes un préstamo pendiente de aprobación.',
            2 => 'No puedes eliminar tu cuenta porque tienes un préstamo aprobado (PAGOS EN PROCESO).',
            3 => 'No puedes eliminar tu cuenta porque tienes un préstamo activo en curso.',
            default => 'No puedes inhabilitar tu cuenta porque tienes un préstamo en proceso.',
        };
    }
}


if (!function_exists('user_validate_auth')) {
    function user_validate_auth(?\App\Models\User $user): ?\Illuminate\Http\JsonResponse
    {
        if (!$user) {
            return user_error_response('Usuario no autenticado', 401);
        }
        return null;
    }
}

if (!function_exists('user_validate_admin')) {
    function user_validate_admin(?\App\Models\User $user): ?\Illuminate\Http\JsonResponse
    {
        if (!$user || !in_array($user->rol_id, [user_rol_admin(), user_rol_super_admin()])) {
            return user_error_response('No tienes permisos para esta acción.', 403);
        }
        return null;
    }
}

// ---------- GRUPOS ----------

if (!function_exists('user_generar_codigo_grupo_unico')) {
    /**
     * Genera un código único de grupo (8 caracteres alfanuméricos en mayúsculas).
     */
    function user_generar_codigo_grupo_unico(): string
    {
        do {
            $codigo = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8));
        } while (\App\Models\Grupo::where('code', $codigo)->exists());

        return $codigo;
    }
}

if (!function_exists('user_grupo_payload')) {
    /**
     * Payload simple de un grupo.
     */
    function user_grupo_payload(?\App\Models\Grupo $grupo): ?array
    {
        if (!$grupo) return null;
        return [
            'id'   => $grupo->id,
            'name' => $grupo->group_name,
        ];
    }
}

if (!function_exists('user_admin_de_grupo')) {
    /**
     * Busca al admin de un grupo: primero SUPER_ADMIN, luego user_leader_id.
     */
    function user_admin_de_grupo(\App\Models\Grupo $grupo): ?\App\Models\User
    {
        $admin = \App\Models\User::where('grupo_id', $grupo->id)
            ->where('rol_id', user_rol_super_admin())
            ->first();

        if (!$admin && $grupo->user_leader_id) {
            $admin = \App\Models\User::find($grupo->user_leader_id);
        }

        return $admin;
    }
}

if (!function_exists('user_subgrupo_payload')) {
    /**
     * Payload simple de un subgrupo (emergencia/monitoreo).
     */
    function user_subgrupo_payload(?\App\Models\Grupo $grupo): ?array
    {
        if (!$grupo) return null;
        return [
            'id'         => $grupo->id,
            'group_name' => $grupo->group_name,
            'status'     => $grupo->status,
        ];
    }
}

// ---------- AUTH / LOGIN ----------

if (!function_exists('user_buscar_por_email_o_telefono')) {
    /**
     * Busca un usuario por email o teléfono.
     */
    function user_buscar_por_email_o_telefono(string $valor): ?\App\Models\User
    {
        return \App\Models\User::where(function ($query) use ($valor) {
            $query->where('email', $valor)
                ->orWhere('telefono', $valor);
        })->first();
    }
}

if (!function_exists('user_crear_token')) {
    /**
     * Crea un token Sanctum y devuelve el payload completo.
     */
    function user_crear_token(\App\Models\User $user): array
    {
        $token = $user->createToken('Palabra_Secreta');
        $date  = \Carbon\Carbon::now();

        return [
            'token'      => $token->plainTextToken,
            'created_at' => $date->format('Y-m-d H:i:s'),
            'expired_at' => $date->copy()
                ->addMinutes(user_token_expiration_minutes())
                ->format('Y-m-d H:i:s'),
        ];
    }
}

// FUNCIONES DE UTILIDAD GENERAL

if (!function_exists('numeroAleatorio')) {
    function numeroAleatorio($min, $max)
    {
        return rand($min, $max);
    }
}

if (!function_exists('genera_token')) {
    function genera_token()
    {
        $code = "";
        $pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
        $max = strlen($pattern) - 1;
        for ($i = 0; $i < 40; $i++) {
            $code .= $pattern[crypto_rand_secure(0, $max)];
        }
        return $code;
    }
}

if (!function_exists('crypto_rand_secure')) {
    function crypto_rand_secure($min, $max)
    {
        $range = $max - $min;
        if ($range < 1) return $min;
        $log = ceil(log($range, 2));
        $bytes = (int) ($log / 8) + 1;
        $bits = (int) $log + 1;
        $filter = (int) (1 << $bits) - 1;
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter;
        } while ($rnd > $range);
        return $min + $rnd;
    }
}

// FUNCIONES PARA CÁLCULO DE INTERÉS

if (!function_exists('calcula_interes_detallado')) {
    function calcula_interes_detallado($monto, $quincenas)
    {
        // 1. TASA DE INTERÉS QUINCENAL SEGÚN PLAZO
        $tasas = [
            1 => 0.0795,
            2 => 0.0795,
            3 => 0.0795,
            4 => 0.0795,
            5 => 0.0795,
        ];

        $tasa_quincenal = $tasas[$quincenas] ?? 0.0675;
        $iva = 0.16;

        $interes_quincenal = $monto * $tasa_quincenal;
        $iva_quincenal = $interes_quincenal * $iva;
        $total_interes_quincenal = $interes_quincenal + $iva_quincenal;
        $capital_quincenal = $monto / $quincenas;
        $pago_quincenal = $capital_quincenal + $total_interes_quincenal;

        $interes_quincenal = (int) round($interes_quincenal);
        $iva_quincenal = (int) round($iva_quincenal);
        $total_interes_quincenal = (int) round($total_interes_quincenal);
        $capital_quincenal = (int) round($capital_quincenal);
        $pago_quincenal = (int) round($pago_quincenal);

        $total_interes = $total_interes_quincenal * $quincenas;
        $total_pagar = $monto + $total_interes;

        $desglose = [];
        $total_pagos = 0;

        for ($i = 0; $i < $quincenas; $i++) {
            $desglose[] = [
                'quincena' => $i + 1,
                'capital' => $capital_quincenal,
                'interes' => $interes_quincenal,
                'iva' => $iva_quincenal,
                'total_interes' => $total_interes_quincenal,
                'pago_total' => $pago_quincenal
            ];
            $total_pagos += $pago_quincenal;
        }

        $diferencia = $total_pagar - $total_pagos;
        if ($diferencia != 0 && $quincenas > 0) {
            $desglose[$quincenas - 1]['pago_total'] += $diferencia;
            $desglose[$quincenas - 1]['total_interes'] += $diferencia;
            $total_pagar = array_sum(array_column($desglose, 'pago_total'));
            $total_interes = $total_pagar - $monto;
        }

        $pago_quincenal_promedio = (int) round($total_pagar / $quincenas);

        return [
            'monto' => (int) $monto,
            'quincenas' => $quincenas,
            'tasa_quincenal' => $tasa_quincenal * 100 . '%',
            'capital_quincenal' => $capital_quincenal,
            'interes_quincenal' => $interes_quincenal,
            'iva_quincenal' => $iva_quincenal,
            'total_interes_quincenal' => $total_interes_quincenal,
            'pago_quincenal' => $pago_quincenal_promedio,
            'total_interes' => $total_interes,
            'total_pagar' => $total_pagar,
            'desglose_quincenal' => $desglose,
            'ajuste_aplicado' => $diferencia != 0,
            'diferencia_ajuste' => $diferencia
        ];
    }
}

if (!function_exists('calcula_interes')) {
    function calcula_interes($monto, $quincenas)
    {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        return $detalle['total_interes'];
    }
}

if (!function_exists('pago_quincenal')) {
    function pago_quincenal($monto, $quincenas)
    {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        return $detalle['pago_quincenal'];
    }
}

if (!function_exists('tasa_segun_plazo')) {
    function tasa_segun_plazo($quincenas)
    {
        $tasas = [
            1 => 0.0925,   // 9.25%
            2 => 0.1000,   // 10.00%
            3 => 0.1100,   // 11.00%
            4 => 0.1200,   // 12.00%
            5 => 0.1300,   // 13.00%
            6 => 0.1400,   // 14.00%
            7 => 0.1500,   // 15.00%
            9 => 0.1600,   // 16.00%
        ];

        if ($quincenas > 10) {
            $base = 0.1600;
            $incremento = ($quincenas - 10) * 0.005;
            return min($base + $incremento, 0.3500);
        }

        return $tasas[$quincenas] ?? 0.0795;
    }
}

if (!function_exists('calcular_incremento_entero')) {
    function calcular_incremento_entero($monto_total_pagar, $tipo_redondeo = 'ceil')
    {
        $incremento_base = $monto_total_pagar * 0.10;

        switch ($tipo_redondeo) {
            case 'ceil':
                return (int) ceil($incremento_base);
            case 'floor':
                return (int) floor($incremento_base);
            case 'round':
                return (int) round($incremento_base);
            case 'multiple_10':
                return (int) (ceil($incremento_base / 10) * 10);
            case 'multiple_50':
                return (int) (ceil($incremento_base / 50) * 50);
            case 'multiple_100':
                return (int) (ceil($incremento_base / 100) * 100);
            default:
                return (int) ceil($incremento_base);
        }
    }
}

if (!function_exists('pagos_quincenales')) {
    function pagos_quincenales($monto, $quincenas)
    {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        return array_column($detalle['desglose_quincenal'], 'pago_total');
    }
}

if (!function_exists('total_a_pagar')) {
    function total_a_pagar($monto, $quincenas)
    {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        return $detalle['total_pagar'];
    }
}

if (!function_exists('verificar_pagos_exactos')) {
    function verificar_pagos_exactos($monto, $quincenas)
    {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        $pagos = array_column($detalle['desglose_quincenal'], 'pago_total');
        $suma = array_sum($pagos);

        return [
            'es_exacto' => $suma === $detalle['total_pagar'],
            'total_pagos' => $suma,
            'total_esperado' => $detalle['total_pagar'],
            'diferencia' => $suma - $detalle['total_pagar']
        ];
    }
}

// FUNCIONES DE ESTADO Y TRANSICIONES

if (!function_exists('getEstadoTexto')) {
    function getEstadoTexto($estado_id)
    {
        $estados = [
            1 => 'Solicitado',
            2 => 'Aprobado',
            3 => 'activo',
            4 => 'pagado',
            5 => 'Rechazado'
        ];

        return $estados[$estado_id] ?? 'Desconocido';
    }
}

if (!function_exists('validarTransicionEstado')) {
    function validarTransicionEstado($estado_actual, $nuevo_estado)
    {
        $transiciones = [
            1 => [2, 5],  // solicitado → aprobado o rechazado
            2 => [4, 5],  // aprobado   → pagado o rechazado
            3 => [4, 5],  // activo     → pagado o rechazado
            4 => [],      // pagado (final)
            5 => [],      // rechazado (final)
        ];

        if (!isset($transiciones[$estado_actual])) {
            return false;
        }

        return in_array($nuevo_estado, $transiciones[$estado_actual]);
    }
}

if (!function_exists('getTransicionesPermitidas')) {
    function getTransicionesPermitidas($estado_actual)
    {
        $transiciones = [
            1 => ['2 (Aprobado)', '5 (Rechazado)'],
            2 => ['4 (Pagado)', '5 (Rechazado)'],
            3 => ['4 (Pagado)', '5 (Rechazado)'],
            4 => ['Ninguna (Estado final)'],
            5 => ['Ninguna (Estado final)'],
        ];

        return $transiciones[$estado_actual] ?? ['Ninguna'];
    }
}

if (!function_exists('getEstadosFinales')) {
    function getEstadosFinales()
    {
        return [3, 4]; // Pagado, Rechazado
    }
}

if (!function_exists('esEstadoFinal')) {
    function esEstadoFinal($estado_id)
    {
        return in_array($estado_id, getEstadosFinales());
    }
}

if (!function_exists('getSiguientesEstados')) {
    function getSiguientesEstados($estado_actual)
    {
        $transiciones = [
            1 => [2, 4],
            2 => [3, 4],
            3 => [],
            4 => []
        ];

        return $transiciones[$estado_actual] ?? [];
    }
}

if (!function_exists('getEstadosDisponibles')) {
    function getEstadosDisponibles()
    {
        return [
            ['id' => 1, 'nombre' => 'Solicitado', 'descripcion' => 'Solicitud creada, esperando aprobación'],
            ['id' => 2, 'nombre' => 'Aprobado', 'descripcion' => 'Aprobado por asesor, listo para desembolso'],
            ['id' => 3, 'nombre' => 'Pagado', 'descripcion' => 'Completamente liquidado'],
            ['id' => 4, 'nombre' => 'Rechazado', 'descripcion' => 'Solicitud rechazada']
        ];
    }
}

if (!function_exists('getEstadosParaSelector')) {
    function getEstadosParaSelector()
    {
        return [
            ['value' => 1, 'label' => 'Solicitado'],
            ['value' => 2, 'label' => 'Aprobado'],
            ['value' => 3, 'label' => 'Pagado'],
            ['value' => 4, 'label' => 'Rechazado']
        ];
    }
}

if (!function_exists('calcularProximaFechaPago')) {
    function calcularProximaFechaPago($prestamo)
    {
        if (!$prestamo->fecha_desembolso) {
            return null;
        }

        $fecha_desembolso = $prestamo->fecha_desembolso;
        if (!$fecha_desembolso instanceof \Carbon\Carbon) {
            $fecha_desembolso = \Carbon\Carbon::parse($fecha_desembolso);
        }

        $pagos_realizados = (int) ($prestamo->pagos_realizados ?? 0);

        // Sin pagos → próxima es el primer pago
        if ($pagos_realizados === 0) {
            $primer_pago = $prestamo->fecha_primer_pago ?? null;

            if ($primer_pago) {
                if (!$primer_pago instanceof \Carbon\Carbon) {
                    $primer_pago = \Carbon\Carbon::parse($primer_pago);
                }
                return $primer_pago;
            }

            return $fecha_desembolso->copy()->addDays(15);
        }

        // Con pagos → base = fecha real del último pago
        $ultimo_pago = $prestamo->fecha_ultimo_pago ?? null;

        if (!$ultimo_pago) {
            return $fecha_desembolso->copy()->addDays($pagos_realizados * 15);
        }

        if (!$ultimo_pago instanceof \Carbon\Carbon) {
            $ultimo_pago = \Carbon\Carbon::parse($ultimo_pago);
        }

        return $ultimo_pago->copy()->startOfDay()->addDays(15);
    }
}


if (!function_exists('calcularDiasRestantes')) {
    function calcularDiasRestantes($fecha_futura)
    {
        if (!$fecha_futura instanceof \Carbon\Carbon) {
            $fecha_futura = \Carbon\Carbon::parse($fecha_futura);
        }

        $hoy = now()->startOfDay();
        $fecha_futura_inicio = $fecha_futura->copy()->startOfDay();

        if ($fecha_futura_inicio->lt($hoy)) {
            return 0;
        }

        return $hoy->diffInDays($fecha_futura_inicio);
    }
}

if (!function_exists('validarFechaDesembolso')) {
    function validarFechaDesembolso($prestamo, $es_pago_adelantado = false)
    {
        if (!$prestamo->fecha_desembolso) {
            return [
                'valido' => false,
                'message' => 'El préstamo está aprobado pero aún no tiene fecha de desembolso',
                'accion' => 'Esperar la asignación de fecha de desembolso'
            ];
        }

        $fecha_desembolso = $prestamo->fecha_desembolso;
        if (!$fecha_desembolso instanceof \Carbon\Carbon) {
            $fecha_desembolso = \Carbon\Carbon::parse($fecha_desembolso);
        }

        $hoy = now()->startOfDay();
        $fecha_desembolso_inicio = $fecha_desembolso->copy()->startOfDay();

        if ($fecha_desembolso_inicio->eq($hoy)) {
            return [
                'valido' => true,
                'message' => 'Préstamo se desembolsa hoy, pago permitido'
            ];
        }

        if ($fecha_desembolso_inicio->lt($hoy)) {
            return [
                'valido' => true,
                'message' => 'Préstamo con fecha de desembolso pasada, pago permitido'
            ];
        }

        if ($fecha_desembolso_inicio->gt($hoy)) {
            if ($es_pago_adelantado) {
                $dias_para_desembolso = $hoy->diffInDays($fecha_desembolso_inicio);

                if ($dias_para_desembolso > 15) {
                    return [
                        'valido' => false,
                        'message' => 'No se pueden realizar pagos adelantados con tanta anticipación',
                        'fecha_desembolso' => $fecha_desembolso->format('d/m/Y'),
                        'dias_para_desembolso' => $dias_para_desembolso,
                        'limite_dias' => 15
                    ];
                }

                return [
                    'valido' => true,
                    'message' => 'Pago adelantado permitido',
                    'dias_para_desembolso' => $dias_para_desembolso
                ];
            }

            $dias_restantes = $hoy->diffInDays($fecha_desembolso_inicio);

            return [
                'valido' => false,
                'message' => 'El préstamo será desembolsado el ' . $fecha_desembolso->format('d/m/Y'),
                'fecha_desembolso' => $fecha_desembolso->format('Y-m-d'),
                'fecha_actual' => $hoy->format('Y-m-d'),
                'dias_restantes' => $dias_restantes,
                'dias_habiles_restantes' => diasHabilesEntre($hoy, $fecha_desembolso_inicio),
                'es_pago_adelantado_disponible' => true,
                'accion' => 'Esperar la fecha de desembolso o realizar pago adelantado'
            ];
        }

        return [
            'valido' => true,
            'message' => 'Fecha válida para pago'
        ];
    }
}

if (!function_exists('calcularPorcentajePagado')) {
    /**
     * Calcula el porcentaje pagado del préstamo.
     *
     * Regla:
     * - Si aún hay saldo pendiente (monto_restante > 0), el porcentaje
     *   NUNCA debe llegar a 100. Se redondea hacia abajo con 2 decimales
     *   y se topa en 99.99%.
     * - Si el saldo es 0, devuelve 100.
     *
     * @param  \App\Models\Prestamo  $prestamo
     * @return float
     */
    function calcularPorcentajePagado($prestamo)
    {
        if ($prestamo->monto_total_pagar <= 0) {
            return 0;
        }

        $monto_restante = $prestamo->monto_restante ?? 0;

        //Si ya no hay saldo pendiente → 100%
        if ($monto_restante <= 0) {
            return 100.00;
        }

        $monto_pagado = $prestamo->monto_total_pagar - $monto_restante;

        //Redondear hacia abajo con 2 decimales para no inflar el progreso
        $porcentaje = floor(($monto_pagado / $prestamo->monto_total_pagar) * 10000) / 100;

        //nunca 100 si aún hay deuda
        $porcentaje = min($porcentaje, 99.99);

        return round($porcentaje, 2);
    }
}

if (!function_exists('generarFolio')) {
    function generarFolio()
    {
        $prefijo = 'PRE-';
        $fecha = date('Ymd');
        $aleatorio = strtoupper(substr(uniqid(), -6));
        $folio = $prefijo . $fecha . '-' . $aleatorio;

        while (\App\Models\Prestamo::where('folio', $folio)->exists()) {
            $aleatorio = strtoupper(substr(uniqid(), -6));
            $folio = $prefijo . $fecha . '-' . $aleatorio;
        }

        return $folio;
    }
}

// FUNCIONES DE DÍAS HÁBILES

if (!function_exists('sumarDiasHabiles')) {
    function sumarDiasHabiles($fecha, $dias, $festivos = [])
    {
        if (!$fecha instanceof \Carbon\Carbon) {
            try {
                $fecha = \Carbon\Carbon::parse($fecha);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Fecha inválida: ' . $e->getMessage());
            }
        }

        if ($dias < 0) {
            throw new \InvalidArgumentException('El número de días debe ser positivo');
        }

        $fecha_resultado = $fecha->copy();
        $dias_agregados = 0;

        if ($dias == 0) {
            return $fecha_resultado;
        }

        while ($dias_agregados < $dias) {
            $fecha_resultado->addDay();

            $es_dia_habile = true;

            if (!$fecha_resultado->isWeekday()) {
                $es_dia_habile = false;
            }

            if ($es_dia_habile && !empty($festivos)) {
                $fecha_str = $fecha_resultado->format('Y-m-d');
                if (in_array($fecha_str, $festivos)) {
                    $es_dia_habile = false;
                }
            }

            if ($es_dia_habile) {
                $dias_agregados++;
            }
        }

        return $fecha_resultado;
    }
}

if (!function_exists('contarDiasHabiles')) {
    function contarDiasHabiles($fecha_inicio, $fecha_fin, $festivos = [])
    {
        if (!$fecha_inicio instanceof \Carbon\Carbon) {
            try {
                $fecha_inicio = \Carbon\Carbon::parse($fecha_inicio);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Fecha de inicio inválida: ' . $e->getMessage());
            }
        }

        if (!$fecha_fin instanceof \Carbon\Carbon) {
            try {
                $fecha_fin = \Carbon\Carbon::parse($fecha_fin);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Fecha final inválida: ' . $e->getMessage());
            }
        }

        if ($fecha_fin->lt($fecha_inicio)) {
            return 0;
        }

        $dias = 0;
        $fecha_actual = $fecha_inicio->copy()->addDay();

        while ($fecha_actual->lte($fecha_fin)) {
            $es_dia_habile = true;

            if (!$fecha_actual->isWeekday()) {
                $es_dia_habile = false;
            }

            if ($es_dia_habile && !empty($festivos)) {
                $fecha_str = $fecha_actual->format('Y-m-d');
                if (in_array($fecha_str, $festivos)) {
                    $es_dia_habile = false;
                }
            }

            if ($es_dia_habile) {
                $dias++;
            }

            $fecha_actual->addDay();
        }

        return $dias;
    }
}

if (!function_exists('diasHabilesEntre')) {
    function diasHabilesEntre($fecha_inicio, $fecha_fin, $festivos = [])
    {
        return contarDiasHabiles($fecha_inicio, $fecha_fin, $festivos);
    }
}

if (!function_exists('esDiaHabile')) {
    function esDiaHabile($fecha, $festivos = [])
    {
        if (!$fecha instanceof \Carbon\Carbon) {
            try {
                $fecha = \Carbon\Carbon::parse($fecha);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Fecha inválida: ' . $e->getMessage());
            }
        }

        if (!$fecha->isWeekday()) {
            return false;
        }

        if (!empty($festivos)) {
            $fecha_str = $fecha->format('Y-m-d');
            if (in_array($fecha_str, $festivos)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('proximaFechaHabil')) {
    function proximaFechaHabil($fecha, $festivos = [])
    {
        if (!$fecha instanceof \Carbon\Carbon) {
            try {
                $fecha = \Carbon\Carbon::parse($fecha);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Fecha inválida: ' . $e->getMessage());
            }
        }

        $fecha_resultado = $fecha->copy();

        if (esDiaHabile($fecha_resultado, $festivos)) {
            return $fecha_resultado;
        }

        while (!esDiaHabile($fecha_resultado, $festivos)) {
            $fecha_resultado->addDay();
        }

        return $fecha_resultado;
    }
}

if (!function_exists('calcularFechaPrimerPago')) {
    function calcularFechaPrimerPago($fecha_desembolso, $dias_habiles = 15, $festivos = [])
    {
        return sumarDiasHabiles($fecha_desembolso, $dias_habiles, $festivos);
    }
}

if (!function_exists('calcularDiasParaPrimerPago')) {
    function calcularDiasParaPrimerPago($fecha_desembolso, $fecha_primer_pago = null, $festivos = [])
    {
        if (!$fecha_desembolso instanceof \Carbon\Carbon) {
            $fecha_desembolso = \Carbon\Carbon::parse($fecha_desembolso);
        }

        if (!$fecha_primer_pago) {
            $fecha_primer_pago = sumarDiasHabiles($fecha_desembolso, 15, $festivos);
        } elseif (!$fecha_primer_pago instanceof \Carbon\Carbon) {
            $fecha_primer_pago = \Carbon\Carbon::parse($fecha_primer_pago);
        }

        $dias_habiles = contarDiasHabiles($fecha_desembolso, $fecha_primer_pago, $festivos);
        $dias_naturales = $fecha_desembolso->diffInDays($fecha_primer_pago);

        return [
            'fecha_desembolso' => $fecha_desembolso->format('Y-m-d'),
            'fecha_primer_pago' => $fecha_primer_pago->format('Y-m-d'),
            'dias_habiles' => $dias_habiles,
            'dias_naturales' => $dias_naturales,
            'dia_semana_desembolso' => $fecha_desembolso->format('l'),
            'dia_semana_primer_pago' => $fecha_primer_pago->format('l'),
            'es_fin_semana_desembolso' => $fecha_desembolso->isWeekend(),
            'es_fin_semana_primer_pago' => $fecha_primer_pago->isWeekend()
        ];
    }
}

if (!function_exists('getFestivosMexico')) {
    function getFestivosMexico($year = null)
    {
        if (!$year) {
            $year = date('Y');
        }

        $festivos = [
            $year . '-01-01',
            $year . '-02-05',
            $year . '-03-21',
            $year . '-05-01',
            $year . '-09-16',
            $year . '-11-20',
            $year . '-12-25',
        ];

        $festivos_ajustados = [];
        foreach ($festivos as $fecha) {
            $carbon = \Carbon\Carbon::parse($fecha);
            if ($carbon->isSaturday() || $carbon->isSunday()) {
                $lunes = $carbon->copy()->nextWeekday();
                $festivos_ajustados[] = $lunes->format('Y-m-d');
            } else {
                $festivos_ajustados[] = $fecha;
            }
        }

        if ($year == 2026) {
            $festivos_ajustados[] = '2026-04-02';
            $festivos_ajustados[] = '2026-04-03';
        }

        return $festivos_ajustados;
    }
}


if (!function_exists('getPlazosDisponibles')) {
    /**
     * OBTENER PLAZOS DISPONIBLES SEGÚN EL MONTO
     *
     * @param int|null $monto Monto a evaluar (si es null, devuelve todos los plazos)
     * @return array Lista de plazos disponibles (en quincenas)
     *
     * @example
     * getPlazosDisponibles()        // [1, 2] - Todos los plazos
     * getPlazosDisponibles(300)     // [1, 2] - Monto ≤ 400
     * getPlazosDisponibles(500)     // [2]    - Monto > 400
     * getPlazosDisponibles(430)     // [2]    - Monto > 400
     */
    function getPlazosDisponibles($monto = null)
    {
        // Si no se pasa monto, devolver todos los plazos disponibles
        if ($monto === null) {
            return [1, 2];
        }

        // Si el monto es menor o igual a 400, permitir 1 y 2 quincenas
        if ($monto <= 399) {
            return [1, 2];
        }

        // Si el monto es mayor a 400, solo permitir 2 quincenas
        return [2];
    }
}


if (!function_exists('generarMontosSugeridos')) {
    /**
     * GENERAR MONTOS SUGERIDOS CON INDICACIÓN DE PLAZOS DISPONIBLES POR MONTO
     *
     * @param int $limite_disponible Límite de crédito disponible
     * @param int $max_opciones Número máximo de opciones a mostrar (por defecto 10)
     * @return array Lista de montos sugeridos con sus plazos disponibles
     */
    function generarMontosSugeridos($limite_disponible, $max_opciones = 10)
    {
        $montos = [];
        $limite_disponible = (int) $limite_disponible;

        // Si el límite es menor a 100, retornar solo el límite
        if ($limite_disponible < 100) {
            $plazos = getPlazosDisponibles($limite_disponible);
            return [
                [
                    'monto' => $limite_disponible,
                    'plazos_disponibles' => $plazos,
                    'puede_1_quincena' => in_array(1, $plazos),
                    'puede_2_quincenas' => in_array(2, $plazos)
                ]
            ];
        }

        // 1. MONTOS BASE (escalones fijos)
        $escalones = [100, 200, 300, 400, 500, 600, 700, 800, 900, 1000, 1500, 2000, 2500, 3000, 5000, 10000];

        foreach ($escalones as $escalon) {
            if ($escalon <= $limite_disponible) {
                $plazos = getPlazosDisponibles($escalon);
                $montos[] = [
                    'monto' => $escalon,
                    'plazos_disponibles' => $plazos,
                    'puede_1_quincena' => in_array(1, $plazos),
                    'puede_2_quincenas' => in_array(2, $plazos)
                ];
            }
        }

        // 2. MONTOS PROPORCIONALES (25%, 50%, 75%, 100%)
        if ($limite_disponible > 500) {
            $proporciones = [0.25, 0.50, 0.75, 1.0];
            foreach ($proporciones as $prop) {
                $monto_prop = (int) ($limite_disponible * $prop);
                // Redondear a la decena más cercana
                $monto_prop = round($monto_prop / 10) * 10;

                if ($monto_prop > 0 && $monto_prop <= $limite_disponible) {
                    // Verificar si ya existe
                    $existe = false;
                    foreach ($montos as $m) {
                        if ($m['monto'] == $monto_prop) {
                            $existe = true;
                            break;
                        }
                    }

                    if (!$existe) {
                        $plazos = getPlazosDisponibles($monto_prop);
                        $montos[] = [
                            'monto' => $monto_prop,
                            'plazos_disponibles' => $plazos,
                            'puede_1_quincena' => in_array(1, $plazos),
                            'puede_2_quincenas' => in_array(2, $plazos)
                        ];
                    }
                }
            }
        }

        // 3. MONTOS INTERMEDIOS (múltiplos de 50)
        if ($limite_disponible >= 100) {
            $base = 100;
            $contador = 0;

            while ($base <= $limite_disponible && count($montos) < $max_opciones + 5) {
                // Verificar si ya existe
                $existe = false;
                foreach ($montos as $m) {
                    if ($m['monto'] == $base) {
                        $existe = true;
                        break;
                    }
                }

                if (!$existe) {
                    $plazos = getPlazosDisponibles($base);
                    $montos[] = [
                        'monto' => $base,
                        'plazos_disponibles' => $plazos,
                        'puede_1_quincena' => in_array(1, $plazos),
                        'puede_2_quincenas' => in_array(2, $plazos)
                    ];
                }

                $base += 50;
                $contador++;

                // Si ya tenemos suficientes opciones, salir
                if ($contador > 20 && count($montos) >= 6) {
                    break;
                }
            }
        }

        // 4. SIEMPRE INCLUIR EL MONTO MÁXIMO
        $existe_maximo = false;
        foreach ($montos as $m) {
            if ($m['monto'] == $limite_disponible) {
                $existe_maximo = true;
                break;
            }
        }

        if (!$existe_maximo) {
            $plazos = getPlazosDisponibles($limite_disponible);
            $montos[] = [
                'monto' => $limite_disponible,
                'plazos_disponibles' => $plazos,
                'puede_1_quincena' => in_array(1, $plazos),
                'puede_2_quincenas' => in_array(2, $plazos)
            ];
        }

        // 5. LIMPIAR DUPLICADOS Y ORDENAR POR MONTO
        $montos_unicos = [];
        $montos_vistos = [];

        foreach ($montos as $m) {
            if (!in_array($m['monto'], $montos_vistos)) {
                $montos_unicos[] = $m;
                $montos_vistos[] = $m['monto'];
            }
        }

        // Ordenar por monto ascendente
        usort($montos_unicos, function ($a, $b) {
            return $a['monto'] - $b['monto'];
        });

        // 6. SELECCIONAR OPCIONES REPRESENTATIVAS
        if (count($montos_unicos) > $max_opciones) {
            // Si tenemos más opciones de las permitidas, seleccionar las más representativas

            // Siempre incluir el primer elemento (monto mínimo)
            $seleccionados = [];
            $total = count($montos_unicos);

            // Agregar primeros 3
            for ($i = 0; $i < min(3, $total); $i++) {
                $seleccionados[] = $montos_unicos[$i];
            }

            // Agregar algunos del medio
            if ($total > 6) {
                $medio_inicio = (int) ($total * 0.3);
                $medio_fin = (int) ($total * 0.7);

                for ($i = $medio_inicio; $i <= $medio_fin && count($seleccionados) < $max_opciones - 3; $i += max(1, (int) (($medio_fin - $medio_inicio) / 3))) {
                    if (!in_array($montos_unicos[$i]['monto'], array_column($seleccionados, 'monto'))) {
                        $seleccionados[] = $montos_unicos[$i];
                    }
                }
            }

            // Agregar últimos 3
            for ($i = max(0, $total - 3); $i < $total; $i++) {
                if (!in_array($montos_unicos[$i]['monto'], array_column($seleccionados, 'monto'))) {
                    $seleccionados[] = $montos_unicos[$i];
                }
            }

            // Asegurar que el máximo esté incluido
            $maximo = end($montos_unicos);
            if (!in_array($maximo['monto'], array_column($seleccionados, 'monto'))) {
                $seleccionados[] = $maximo;
            }

            // Ordenar nuevamente
            usort($seleccionados, function ($a, $b) {
                return $a['monto'] - $b['monto'];
            });

            $montos_unicos = $seleccionados;
        }

        return $montos_unicos;
    }
}

if (!function_exists('getMontosPorRango')) {
    /**
     * OBTENER MONTOS SUGERIDOS POR RANGO
     *
     * @param int $limite_disponible Límite disponible
     * @param int $rango Rango de incremento (por defecto 100)
     * @return array Lista de montos
     */
    function getMontosPorRango($limite_disponible, $rango = 100)
    {
        $montos = [];
        $actual = $rango;

        while ($actual <= $limite_disponible) {
            $montos[] = $actual;
            $actual += $rango;
        }

        return $montos;
    }
}


// VALIDACIÓN DE SALDO MÍNIMO

if (!function_exists('validarSaldoMinimo')) {
    /**
     * Valida que el saldo restante después de un pago no quede en un rango inválido.
     *
     * Regla: el saldo restante debe ser 0 (liquidación) o >= SALDO_MINIMO_PERMITIDO.
     * No se permite dejar saldos entre 1 y (SALDO_MINIMO_PERMITIDO - 1).
     *
     * @param float $deuda_actual     Deuda actual del préstamo
     * @param float $monto_pago       Monto que el usuario quiere pagar
     * @param float $saldo_minimo     Saldo mínimo permitido (default 10)
     * @return array ['valido' => bool, 'message' => string, 'saldo_resultante' => float, 'monto_sugerido' => float]
     */
    function validarSaldoMinimo($deuda_actual, $monto_pago, $saldo_minimo = 10)
    {
        $deuda_actual = (float) $deuda_actual;
        $monto_pago   = (float) $monto_pago;
        $saldo_resultante = $deuda_actual - $monto_pago;

        // Si el pago excede la deuda → inválido (ya se valida aparte, pero por seguridad)
        if ($monto_pago > $deuda_actual) {
            return [
                'valido'            => false,
                'message'           => 'El monto del pago excede la deuda actual',
                'saldo_resultante'  => $saldo_resultante,
                'monto_sugerido'    => $deuda_actual,
            ];
        }

        // Si el saldo resultante es 0 → liquidación total, permitido
        if ($saldo_resultante <= 0) {
            return [
                'valido'            => true,
                'message'           => 'Pago permitido (liquidación total)',
                'saldo_resultante'  => 0,
                'monto_sugerido'    => $monto_pago,
            ];
        }

        // Si el saldo resultante es >= saldo_minimo → permitido
        if ($saldo_resultante >= $saldo_minimo) {
            return [
                'valido'            => true,
                'message'           => 'Pago permitido',
                'saldo_resultante'  => $saldo_resultante,
                'monto_sugerido'    => $monto_pago,
            ];
        }

        // Saldo resultante entre 1 y (saldo_minimo - 1) → inválido
        // Sugerir dos opciones: liquidar todo o dejar exactamente el saldo mínimo
        $monto_para_liquidar = $deuda_actual;
        $monto_para_dejar_minimo = $deuda_actual - $saldo_minimo;

        return [
            'valido'                    => false,
            'message'                   => "No puedes dejar un saldo menor a \${$saldo_minimo}. " .
                "Puedes pagar \${$monto_para_liquidar} para liquidar el préstamo " .
                "o \${$monto_para_dejar_minimo} para dejar un saldo de \${$saldo_minimo}.",
            'saldo_resultante'          => $saldo_resultante,
            'monto_sugerido'            => $monto_para_liquidar,
            'monto_para_liquidar'       => $monto_para_liquidar,
            'monto_para_dejar_minimo'   => $monto_para_dejar_minimo,
            'saldo_minimo'              => $saldo_minimo,
        ];
    }

    function arch_adjunto($file, $file_name)
    {
        $ifp = fopen(public_path('/img/group/' . $file_name), 'wb');
        $data = explode(',', $file);
        fwrite($ifp, base64_decode($data[1]));
        fclose($ifp);
        return $file_name;
    }

    function arch_adjunto_publish($file, $file_name)
    {
        $ifp = fopen(public_path('/img/publish/' . $file_name), 'wb');
        $data = explode(',', $file);
        // we could add validation here with ensuring count( $data ) > 1
        fwrite($ifp, base64_decode($data[1]));
        // clean up the file resource
        fclose($ifp);
        return $file_name;
    }
}

// FUNCIONES DE WHATSAPP

if (!function_exists('normalizarTelefonoWhatsApp')) {
    /**
     * Normaliza un teléfono a formato internacional apto para WhatsApp.
     *
     * Reglas:
     *  - Quita todo lo que no sea dígito (+, espacios, guiones, paréntesis).
     *  - Quita ceros a la izquierda.
     *  - 10 dígitos           → se asume México y antepone 52.
     *  - 12 dígitos con 52    → ya viene con lada MX.
     *  - 11 o más dígitos     → se asume lada internacional.
     *  - Menos de 10 dígitos  → inválido (null).
     *
     * @param  string|null $telefono
     * @param  string      $paisDefault Código de país por defecto (52 = México)
     * @return string|null
     */
    function normalizarTelefonoWhatsApp($telefono, $paisDefault = '52')
    {
        if (!$telefono) {
            return null;
        }

        // Quitar todo lo que no sea dígito
        $num = preg_replace('/\D+/', '', (string) $telefono);

        if ($num === '') {
            return null;
        }

        // Quitar ceros a la izquierda (marcación local)
        $num = ltrim($num, '0');

        // Ya viene con lada de México (52 + 10 = 12 dígitos)
        if (strlen($num) === 12 && strpos($num, '52') === 0) {
            return $num;
        }

        // 10 dígitos → asumimos México
        if (strlen($num) === 10) {
            return $paisDefault . $num;
        }

        // 11 o más dígitos → asumimos que ya trae lada internacional
        if (strlen($num) >= 11) {
            return $num;
        }

        // Menos de 10 dígitos → inválido
        return null;
    }
}

if (!function_exists('telefonoEsWhatsAppValido')) {
    /**
     * ¿El teléfono es apto para WhatsApp?
     *
     * @param  string|null $telefono
     * @return bool
     */
    function telefonoEsWhatsAppValido($telefono)
    {
        return normalizarTelefonoWhatsApp($telefono) !== null;
    }
}

if (!function_exists('generarLinkWhatsApp')) {
    /**
     * Genera la URL de WhatsApp con mensaje pre-llenado.
     * Devuelve null si el teléfono no es válido.
     *
     * @param  string|null $telefono
     * @param  string      $mensaje
     * @return string|null
     */
    function generarLinkWhatsApp($telefono, $mensaje = '')
    {
        $num = normalizarTelefonoWhatsApp($telefono);

        if (!$num) {
            return null;
        }

        $url = 'https://wa.me/' . $num;

        if ($mensaje !== '') {
            $url .= '?text=' . rawurlencode($mensaje);
        }

        return $url;
    }
}

if (!function_exists('mensajeWhatsAppPorEstado')) {
    /**
     * Mensaje contextual según estado del préstamo y alerta de pago.
     *
     * @param  int         $estadoId  1=Solicitado, 2=Aprobado, 3=Activo, 4=Pagado, 5=Rechazado
     * @param  string      $alerta    'normal' | 'precaucion' | 'urgente' | 'vencido'
     * @param  string|null $nombre    Nombre del cliente para personalizar
     * @return string
     */
    function mensajeWhatsAppPorEstado($estadoId, $alerta = 'normal', $nombre = null)
    {
        $saludo = $nombre ? "Hola {$nombre}, " : 'Hola, ';

        switch ((int) $estadoId) {
            case 1:
                return $saludo . 'te contactamos para dar seguimiento a tu solicitud de préstamo.';

            case 2:
                switch ($alerta) {
                    case 'vencido':
                        return $saludo . 'te recordamos que tienes un pago VENCIDO. ¿Podemos coordinar tu pago?';
                    case 'urgente':
                        return $saludo . 'tu pago vence mañana. ¿Te apoyamos con el proceso?';
                    case 'precaucion':
                        return $saludo . 'tu próximo pago está cerca. ¿Te recordamos el monto?';
                    default:
                        return $saludo . 'te contactamos para dar seguimiento a tu préstamo aprobado.';
                }

            case 3:
                return $saludo . 'te contactamos para dar seguimiento a tu préstamo activo.';

            case 4:
                return $saludo . '¡Gracias por tu pago! Tu préstamo está liquidado.';

            case 5:
                return $saludo . 'te contactamos respecto a tu solicitud de préstamo.';

            default:
                return $saludo . 'te contactamos de Financiera.';
        }
    }
}

if (!function_exists('calcularAlertaPago')) {
    /**
     * Calcula la alerta de pago a partir de un préstamo.
     * Devuelve:
     *   [
     *     'dias_restantes' => int|null,
     *     'alerta'         => 'normal'|'precaucion'|'urgente'|'vencido',
     *     'proxima_fecha'  => Carbon|null,
     *   ]
     *
     * @param  \App\Models\Prestamo $prestamo
     * @return array
     */
    function calcularAlertaPago($prestamo)
    {
        $resultado = [
            'dias_restantes' => null,
            'alerta'         => 'normal',
            'proxima_fecha'  => null,
        ];

        // Solo para préstamos aprobados o activos
        if (!in_array((int) $prestamo->estado_prestamo_id, [2, 3])) {
            return $resultado;
        }

        $proxima = calcularProximaFechaPago($prestamo);

        if (!$proxima) {
            return $resultado;
        }

        $dias = (int) now()->startOfDay()->diffInDays(
            $proxima->copy()->startOfDay(),
            false
        );

        $alerta = 'normal';
        if ($dias < 0) {
            $alerta = 'vencido';
        } elseif ($dias <= 1) {
            $alerta = 'urgente';
        } elseif ($dias <= 3) {
            $alerta = 'precaucion';
        }

        return [
            'dias_restantes' => $dias,
            'alerta'         => $alerta,
            'proxima_fecha'  => $proxima,
        ];
    }
}

if (!function_exists('whatsappUrlParaPrestamo')) {
    /**
     * Atajo: dado un préstamo, devuelve la URL de WhatsApp lista para abrir.
     * Usa el teléfono del usuario, calcula la alerta y arma el mensaje contextual.
     * Devuelve null si el teléfono no es válido.
     *
     * @param  \App\Models\Prestamo $prestamo  (debe traer relación 'usuario' cargada)
     * @param  string|null          $mensaje   Mensaje override opcional
     * @return string|null
     */
    function whatsappUrlParaPrestamo($prestamo, $mensaje = null)
    {
        if (!$prestamo || !$prestamo->usuario) {
            return null;
        }

        $telefono = $prestamo->usuario->telefono ?? null;

        if (!telefonoEsWhatsAppValido($telefono)) {
            return null;
        }

        $alertaData = calcularAlertaPago($prestamo);

        $texto = $mensaje ?: mensajeWhatsAppPorEstado(
            (int) $prestamo->estado_prestamo_id,
            $alertaData['alerta'],
            $prestamo->usuario->nombre ?? null
        );

        return generarLinkWhatsApp($telefono, $texto);
    }
}

// FUNCIONES  PUBLICACIONES (comunidad)


if (!function_exists('post_reacciones_validas')) {
    function post_reacciones_validas(): array
    {
        return ['like', 'love', 'haha', 'sad', 'angry'];
    }
}

if (!function_exists('post_reacciones_vacias')) {
    function post_reacciones_vacias(): array
    {
        return [
            'like'  => 0,
            'love'  => 0,
            'haha'  => 0,
            'sad'   => 0,
            'angry' => 0,
        ];
    }
}


if (!function_exists('post_error_response')) {
    function post_error_response(string $msg, int $status = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'res' => false,
            'msg' => $msg,
        ], $status);
    }
}

if (!function_exists('post_success_response')) {
    function post_success_response(string $msg, array $extra = [], int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge([
            'res' => true,
            'msg' => $msg,
        ], $extra), $status);
    }
}


if (!function_exists('post_validate_auth')) {
    function post_validate_auth(?\App\Models\User $user): ?\Illuminate\Http\JsonResponse
    {
        if (!$user) {
            return post_error_response('Usuario no autenticado', 401);
        }
        return null;
    }
}

if (!function_exists('post_find_or_fail')) {
    function post_find_or_fail(int $id): \App\Models\Post|\Illuminate\Http\JsonResponse
    {
        $post = \App\Models\Post::find($id);

        if (!$post) {
            return post_error_response('Publicación no encontrada', 404);
        }

        return $post;
    }
}

if (!function_exists('post_validate_ownership')) {
    function post_validate_ownership(\App\Models\User $user, \App\Models\Post $post, string $accion = 'editar'): ?\Illuminate\Http\JsonResponse
    {
        if ($post->user_id != $user->id) {
            return post_error_response("No tienes permiso para {$accion} esta publicación", 403);
        }
        return null;
    }
}

//GRUPOS / PERMISOS

if (!function_exists('post_grupos_visibles_de')) {
    /**
     * Devuelve los IDs de grupos visibles para un usuario.
     */
    function post_grupos_visibles_de(\App\Models\User $user): array
    {
        if (!$user->grupo_id) {
            return [];
        }

        return \App\Models\Grupo::gruposVisiblesDe((int) $user->grupo_id);
    }
}

if (!function_exists('post_usuario_puede_publicar_en')) {
    /**
     * Valida que el usuario pueda publicar en el grupo indicado.
     */
    function post_usuario_puede_publicar_en(\App\Models\User $user, int $grupoId): bool
    {
        if (!$user->grupo_id) return false;

        $visibles = post_grupos_visibles_de($user);

        return in_array($grupoId, $visibles, true);
    }
}


if (!function_exists('post_nombre_completo')) {
    function post_nombre_completo($user): string
    {
        return trim(
            ($user->nombre ?? '') . ' ' .
            ($user->apellido_p ?? '') . ' ' .
            ($user->apellido_m ?? '')
        );
    }
}

if (!function_exists('post_nombre_publico')) {

    function post_nombre_publico($user, string $scope = 'group'): string
    {
        if ($scope === 'global') {
            return trim($user->nombre ?? 'Usuario');
        }

        return post_nombre_completo($user);
    }
}

// ---------- ARCHIVOS ----------

if (!function_exists('post_guardar_archivo')) {
    /**
     * Guarda un archivo en `public/{$carpeta}`.
     */
    function post_guardar_archivo($file, string $carpeta, string $baseName): string
    {
        $ext  = $file->getClientOriginalExtension() ?: 'bin';
        $name = $baseName . '.' . $ext;
        $size = $file->getSize();
        $mime = $file->getMimeType();

        $path = public_path($carpeta);
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }

        $file->move($path, $name);

        \Log::info('[publicacion] Archivo guardado', [
            'carpeta' => $carpeta,
            'file'    => $name,
            'size'    => $size,
            'mime'    => $mime,
        ]);

        return $name;
    }
}

//LINKS

if (!function_exists('post_extraer_urls')) {
    /**
     * Extrae las URLs del texto de una publicación.
     */
    function post_extraer_urls(string $texto): array
    {
        if (empty($texto)) return [];

        $pattern = '/https?:\/\/[^\s<>"\')\]]+/i';
        preg_match_all($pattern, $texto, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}

if (!function_exists('post_detectar_tipo_link')) {

    function post_detectar_tipo_link(string $url): ?array
    {
        $dominios = [
            'youtube'   => '/(youtube\.com|youtu\.be)/i',
            'facebook'  => '/facebook\.com/i',
            'instagram' => '/instagram\.com/i',
            'tiktok'    => '/tiktok\.com/i',
            'twitter'   => '/(twitter\.com|x\.com)/i',
            'vimeo'     => '/vimeo\.com/i',
            'spotify'   => '/spotify\.com/i',
            'linkedin'  => '/linkedin\.com/i',
            'whatsapp'  => '/(wa\.me|whatsapp\.com)/i',
        ];

        foreach ($dominios as $tipo => $regex) {
            if (preg_match($regex, $url)) {
                return ['tipo' => $tipo, 'url' => $url];
            }
        }

        return null;
    }
}

if (!function_exists('post_extraer_links_detectados')) {

    function post_extraer_links_detectados(?string $texto): array
    {
        return array_values(array_filter(
            array_map(
                fn($url) => post_detectar_tipo_link($url),
                post_extraer_urls($texto ?? '')
            )
        ));
    }
}

//REACCIONES

if (!function_exists('post_contar_reacciones')) {
    /**
     * Cuenta las reacciones de un post.
     */
    function post_contar_reacciones(\App\Models\Post $post, int $userId): array
    {
        $counts     = post_reacciones_vacias();
        $miReaccion = null;

        foreach ($post->reactions()->get() as $r) {
            if (isset($counts[$r->type])) {
                $counts[$r->type]++;
            }
            if ($r->user_id === $userId) {
                $miReaccion = $r->type;
            }
        }

        return ['counts' => $counts, 'mi_reaccion' => $miReaccion];
    }
}

//FORMATEO

if (!function_exists('post_formatear_comentario')) {
    function post_formatear_comentario($comment, string $scope = 'group'): array
    {
        $autor       = $comment->user;  // ✅ ya viene cargado con with('user')
        $nombreAutor = post_nombre_publico($autor, $scope);

        return [
            'nombre'     => $nombreAutor,
            'comentario' => $comment->comment,
            'fecha'      => $comment->created_at->format('d/m/Y'),
            'hora'       => $comment->created_at->format('H:i'),
            'avatar_url' => $scope === 'group' ? ($autor->avatar_url ?? null) : null,
        ];
    }
}

if (!function_exists('post_formatear')) {
    function post_formatear(\App\Models\Post $post, \App\Models\User $user_token, string $scope = 'group'): array
    {
        // ✅ Si ya vienen cargados, usamos la colección en memoria (sin queries)
        if ($post->relationLoaded('comments')) {
            $comentarios = $post->comments
                ->where('activo', 1)
                ->filter(fn($c) => optional($c->user)->status_id === 1)
                ->map(fn($c) => post_formatear_comentario($c, $scope))
                ->values()
                ->toArray();
        } else {
            $comentarios = $post->comments()
                ->where('activo', 1)
                ->whereHas('user', fn($q) => $q->where('status_id', 1))
                ->with('user')
                ->get()
                ->map(fn($c) => post_formatear_comentario($c, $scope))
                ->toArray();
        }

        // ✅ Reacciones desde la colección en memoria si está cargada
        $counts     = post_reacciones_vacias();
        $miReaccion = null;

        if ($post->relationLoaded('reactions')) {
            foreach ($post->reactions as $r) {
                if (isset($counts[$r->type])) {
                    $counts[$r->type]++;
                }
                if ($r->user_id === $user_token->id) {
                    $miReaccion = $r->type;
                }
            }
        } else {
            $tmp = post_contar_reacciones($post, $user_token->id);
            $counts     = $tmp['counts'];
            $miReaccion = $tmp['mi_reaccion'];
        }

        $autor       = $post->user;
        $nombreAutor = post_nombre_publico($autor, $scope);

        if ($scope === 'group') {
            $user_data = [
                'id'         => $autor->id,
                'nombre'     => $nombreAutor,
                'avatar_url' => $autor->avatar_url ?? null,
                // ↑ accessor, funciona porque 'avatar' viene en el with()
            ];
        } else {
            $user_data = [
                'nombre'     => $nombreAutor,
                'avatar_url' => $autor->avatar_url ?? null,
            ];
        }

        return [
            'id'      => $post->id,
            'user_id' => $scope === 'group' ? $post->user_id : null,
            'post'    => $post->post,
            'image'   => $post->image,
            'video'   => $post->video,
            'audio'   => $post->audio,
            'user'    => $nombreAutor,
            'user_data'   => $user_data,
            'fecha'       => $post->created_at->format('d/m/Y'),
            'hora'        => $post->created_at->format('H:i'),
            'comentarios' => $comentarios,
            'reacciones'  => $counts,
            'mi_reaccion' => $miReaccion,
            'links'       => post_extraer_links_detectados($post->post),
        ];
    }
}

if (!function_exists('post_formatear_nuevo')) {
    /**
     * Formatea un post recién creado.
     */
    function post_formatear_nuevo(\App\Models\Post $post, \App\Models\User $user_token): array
    {
        $nombreAutor = post_nombre_completo($user_token);

        return [
            'id'      => $post->id,
            'user_id' => $post->user_id,
            'post'    => $post->post,
            'image'   => $post->image,
            'video'   => $post->video,
            'audio'   => $post->audio,
            'user'    => $nombreAutor,

            'user_data' => [
                'id'         => $user_token->id,
                'nombre'     => $nombreAutor,
                'avatar_url' => $user_token->avatar_url ?? null,
            ],

            'fecha'       => $post->created_at->format('d/m/Y'),
            'hora'        => $post->created_at->format('H:i'),
            'comentarios' => [],
            'reacciones'  => post_reacciones_vacias(),
            'mi_reaccion' => null,

            'links' => post_extraer_links_detectados($post->post),
        ];
    }
}

//SUBIDA DE ARCHIVOS DEL POST

if (!function_exists('post_procesar_archivos_request')) {
    /**
     * Procesa img/video/audio del request y los asigna al post.
     */
    function post_procesar_archivos_request(\App\Models\Post $post, $request, int $userId): void
    {
        if ($request->hasFile('img')) {
            $post->image = post_guardar_archivo(
                $request->file('img'),
                'img/publish',
                time() . '_' . $userId
            );
        }

        if ($request->hasFile('video')) {
            $post->video = post_guardar_archivo(
                $request->file('video'),
                'img/publish',
                time() . '_' . $userId . '_video'
            );
        }

        if ($request->hasFile('audio')) {
            $post->audio = post_guardar_archivo(
                $request->file('audio'),
                'audio/publish',
                time() . '_' . $userId . '_audio'
            );
        }
    }
}

// SCOPE

if (!function_exists('post_normalizar_scope')) {
    /**
     * Normaliza el scope recibido ('group' por defecto).
     */
    function post_normalizar_scope(?string $scope): string
    {
        if (!in_array($scope, ['group', 'global'])) {
            return 'group';
        }

        return $scope;
    }
}

//NOTIFICACIONES

if (!function_exists('post_notificar_miembros_grupo')) {
    /**
     * Notifica a los miembros del grupo (excepto al autor) sobre un nuevo post.
     */
    function post_notificar_miembros_grupo(\App\Models\Post $post): void
    {
        \App\Models\User::where('grupo_id', $post->group_id)
            ->where('id', '!=', $post->user_id)
            ->get()
            ->each(function ($user) use ($post) {
                $user->notify(new \App\Notifications\NewPostPublished($post));
            });
    }
}


// FUNCIONES (comunidad) comentarios

if (!function_exists('comment_error_response')) {
    function comment_error_response(string $msg, int $status = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'res' => false,
            'msg' => $msg,
        ], $status);
    }
}

if (!function_exists('comment_success_response')) {
    function comment_success_response(string $msg, array $data = [], int $status = 200): \Illuminate\Http\JsonResponse
    {
        $payload = [
            'res' => true,
            'msg' => $msg,
        ];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }
}

if (!function_exists('comment_validate_auth')) {
    function comment_validate_auth(?\App\Models\User $user): ?\Illuminate\Http\JsonResponse
    {
        if (!$user) {
            return comment_error_response('Usuario no autenticado', 401);
        }

        return null;
    }
}

if (!function_exists('comment_validate_post')) {
    function comment_validate_post(?object $post): ?\Illuminate\Http\JsonResponse
    {
        if (!$post || !$post->activo) {
            return comment_error_response('Publicación no encontrada o inactiva', 404);
        }

        return null;
    }
}

if (!function_exists('comment_find_or_fail')) {
    function comment_find_or_fail(int $id): \App\Models\Comment|\Illuminate\Http\JsonResponse
    {
        $comment = \App\Models\Comment::find($id);

        if (!$comment) {
            return comment_error_response('Comentario no existe', 404);
        }

        return $comment;
    }
}

if (!function_exists('comment_validate_ownership')) {
    function comment_validate_ownership(\App\Models\User $user, \App\Models\Comment $comment): ?\Illuminate\Http\JsonResponse
    {
        if ($user->id != $comment->user_id) {
            return comment_error_response('No autorizado', 403);
        }

        return null;
    }
}

if (!function_exists('comment_build_full_name')) {
    function comment_build_full_name(\App\Models\User $user): string
    {
        return trim(
            ($user->nombre ?? '') . ' ' .
            ($user->apellido_p ?? '') . ' ' .
            ($user->apellido_m ?? '')
        );
    }
}

if (!function_exists('comment_format_payload')) {
    function comment_format_payload(\App\Models\Comment $comment, \App\Models\User $user): array
    {
        return [
            'id'         => $comment->id,
            'nombre'     => comment_build_full_name($user),
            'comentario' => $comment->comment,
            'fecha'      => $comment->created_at->format('d/m/Y'),
            'hora'       => $comment->created_at->format('H:i'),
            'avatar_url' => $user->avatar_url ?? null,
        ];
    }
}

if (!function_exists('comment_log_error')) {
    function comment_log_error(string $context, \Throwable $th): void
    {
        \Log::error("[{$context}] Error", ['error' => $th->getMessage()]);
    }
}



