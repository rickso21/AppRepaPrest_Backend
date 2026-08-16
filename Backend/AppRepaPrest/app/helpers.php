<?php

// ============================================
// FUNCIONES DE UTILIDAD GENERAL
// ============================================

if (!function_exists('numeroAleatorio')) {
    function numeroAleatorio($min, $max) {
        return rand($min, $max);
    }
}

if (!function_exists('genera_token')) {
    function genera_token(){
        $code = "";
        $pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
        $max = strlen($pattern)-1;
        for ($i=0; $i < 40; $i++) {
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

// ============================================
// FUNCIONES PARA CÁLCULO DE INTERÉS
// ============================================

if (!function_exists('calcula_interes_detallado')) {
    function calcula_interes_detallado($monto, $quincenas) {
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
    function calcula_interes($monto, $quincenas) {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        return $detalle['total_interes'];
    }
}

if (!function_exists('pago_quincenal')) {
    function pago_quincenal($monto, $quincenas) {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        return $detalle['pago_quincenal'];
    }
}

if (!function_exists('tasa_segun_plazo')) {
    function tasa_segun_plazo($quincenas) {
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
    function pagos_quincenales($monto, $quincenas) {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        return array_column($detalle['desglose_quincenal'], 'pago_total');
    }
}

if (!function_exists('total_a_pagar')) {
    function total_a_pagar($monto, $quincenas) {
        $detalle = calcula_interes_detallado($monto, $quincenas);
        return $detalle['total_pagar'];
    }
}

if (!function_exists('verificar_pagos_exactos')) {
    function verificar_pagos_exactos($monto, $quincenas) {
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

// ============================================
// FUNCIONES DE ESTADO Y TRANSICIONES
// ============================================

if (!function_exists('getEstadoTexto')) {
    function getEstadoTexto($estado_id)
    {
        $estados = [
            1 => 'Solicitado',
            2 => 'Aprobado',
            3 => 'Pagado',
            4 => 'Rechazado'
        ];

        return $estados[$estado_id] ?? 'Desconocido';
    }
}

if (!function_exists('validarTransicionEstado')) {
    function validarTransicionEstado($estado_actual, $nuevo_estado)
    {
        $transiciones = [
            1 => [2, 4],    // Solicitado → Aprobado, Rechazado
            2 => [3, 4],    // Aprobado → Pagado, Rechazado
            3 => [],        // Pagado → (ninguna)
            4 => []         // Rechazado → (ninguna)
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
            1 => ['2 (Aprobado)', '4 (Rechazado)'],
            2 => ['3 (Pagado)', '4 (Rechazado)'],
            3 => ['Ninguna (Estado final)'],
            4 => ['Ninguna (Estado final)']
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

// ============================================
// FUNCIONES DE FECHAS
// ============================================

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

        $pagos_realizados = $prestamo->pagos_realizados ?? 0;
        $proxima_fecha = $fecha_desembolso->copy()->addDays($pagos_realizados * 15);

        while ($proxima_fecha->lt(now())) {
            $proxima_fecha->addDays(15);
        }

        return $proxima_fecha;
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
    function calcularPorcentajePagado($prestamo)
    {
        if ($prestamo->monto_total_pagar <= 0) {
            return 0;
        }

        $monto_pagado = $prestamo->monto_total_pagar - ($prestamo->monto_restante ?? 0);
        return round(($monto_pagado / $prestamo->monto_total_pagar) * 100, 2);
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

// ============================================
// FUNCIONES DE DÍAS HÁBILES
// ============================================

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

        // ============================================
        // 1. MONTOS BASE (escalones fijos)
        // ============================================
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

        // ============================================
        // 2. MONTOS PROPORCIONALES (25%, 50%, 75%, 100%)
        // ============================================
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

        // ============================================
        // 3. MONTOS INTERMEDIOS (múltiplos de 50)
        // ============================================
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

        // ============================================
        // 4. SIEMPRE INCLUIR EL MONTO MÁXIMO
        // ============================================
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

        // ============================================
        // 5. LIMPIAR DUPLICADOS Y ORDENAR POR MONTO
        // ============================================
        $montos_unicos = [];
        $montos_vistos = [];

        foreach ($montos as $m) {
            if (!in_array($m['monto'], $montos_vistos)) {
                $montos_unicos[] = $m;
                $montos_vistos[] = $m['monto'];
            }
        }

        // Ordenar por monto ascendente
        usort($montos_unicos, function($a, $b) {
            return $a['monto'] - $b['monto'];
        });

        // ============================================
        // 6. SELECCIONAR OPCIONES REPRESENTATIVAS
        // ============================================
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
            usort($seleccionados, function($a, $b) {
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



