<?php

function numeroAleatorio($min, $max) {
    return rand($min, $max);
}
//FUNCTION CREATE CODE CONFIRMATION
function genera_token(){
    $code = "";
    $pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
    $max = strlen($pattern)-1;
    for ($i=0; $i < 40; $i++) {
        $code .= $pattern[crypto_rand_secure(0, $max)];
    }
    return $code;
}
// CODE THAT HELP TO GENERATE RANDOM CHAR
function crypto_rand_secure($min, $max)
{
    $range = $max - $min;
    if ($range < 1) return $min; // not so random...
    $log = ceil(log($range, 2));
    $bytes = (int) ($log / 8) + 1; // length in bytes
    $bits = (int) $log + 1; // length in bits
    $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
    do {
        $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
        $rnd = $rnd & $filter; // discard irrelevant bits
    } while ($rnd > $range);
    return $min + $rnd;
}
// FUNCION PARA CALCULAR INTERRES SEGUN EL MONTO Y LOS DIAS
function calcula_interes($monto, $quincenas) {
    $interes = 0;
    switch ($quincenas) {
        case 1:
            $interes = $monto * 0.04;
            break;
        case 2:
            $interes = $monto * 0.08;
            break;
        case 3:
            $interes = $monto * 0.10;
            break;
        case 4:
            $interes = $monto * 0.13;
            break;
        case 5:
            $interes = $monto * 0.15;
            break;
    }
    return $interes;
}
if (!function_exists('generarMontosSugeridos')) {
    /**
     * GENERAR MONTOS SUGERIDOS DINÁMICOS BASADOS EN EL LÍMITE DISPONIBLE
     * 
     * @param int $limite_disponible Límite de crédito disponible
     * @param int $max_opciones Número máximo de opciones a mostrar (por defecto 8)
     * @return array Lista de montos sugeridos
     * 
     * @example
     * generarMontosSugeridos(556) // [100, 200, 300, 400, 556]
     * generarMontosSugeridos(1000) // [100, 200, 300, 400, 500, 750, 1000]
     */
    function generarMontosSugeridos($limite_disponible, $max_opciones = 8)
    {
        $montos = [];
        
        // ============================================
        // 1. MONTOS BASE (escalones fijos)
        // ============================================
        $escalones = [100, 200, 300, 400, 500, 1000, 2000, 3000, 5000, 10000];
        
        foreach ($escalones as $escalon) {
            if ($escalon <= $limite_disponible) {
                $montos[] = $escalon;
            }
        }
        
        // ============================================
        // 2. MONTOS PROPORCIONALES
        // ============================================
        if ($limite_disponible > 500) {
            $proporciones = [0.25, 0.50, 0.75, 1.0];
            foreach ($proporciones as $prop) {
                $monto_prop = (int) ($limite_disponible * $prop);
                // Redondear a la decena más cercana
                $monto_prop = round($monto_prop / 10) * 10;
                if ($monto_prop > 0 && $monto_prop <= $limite_disponible) {
                    $montos[] = $monto_prop;
                }
            }
        }
        
        // ============================================
        // 3. MONTOS PERSONALIZADOS (múltiplos de 50)
        // ============================================
        if ($limite_disponible >= 100) {
            // Agregar montos intermedios cada 50
            $base = 100;
            while ($base <= $limite_disponible && count($montos) < $max_opciones) {
                if (!in_array($base, $montos)) {
                    $montos[] = $base;
                }
                $base += 50;
            }
        }
        
        // ============================================
        // 4. SIEMPRE INCLUIR EL MONTO MÁXIMO
        // ============================================
        if (!in_array($limite_disponible, $montos)) {
            $montos[] = $limite_disponible;
        }
        
        // ============================================
        // 5. LIMPIAR Y ORDENAR
        // ============================================
        $montos = array_unique($montos);
        sort($montos);
        
        // ============================================
        // 6. LIMITAR A MÁXIMO DE OPCIONES
        // ============================================
        if (count($montos) > $max_opciones) {
            // Mantener el mínimo, el máximo y algunos intermedios
            $primeros = array_slice($montos, 0, 3);
            $ultimos = array_slice($montos, -3);
            $medios = [];
            
            if (count($montos) > 6) {
                $medio = array_slice($montos, 3, count($montos) - 6);
                if (!empty($medio)) {
                    $medios = array_slice($medio, 0, 2);
                }
            }
            
            $montos = array_unique(array_merge($primeros, $medios, $ultimos));
            sort($montos);
        }
        
        return $montos;
    }
}
if (!function_exists('getPlazosDisponibles')) {
    /**
     * OBTENER PLAZOS DISPONIBLES PARA PRÉSTAMOS
     * 
     * @return array Lista de plazos disponibles (en quincenas)
     * 
     * @example
     * getPlazosDisponibles() // [2] - Solo 2 quincenas
     */
    function getPlazosDisponibles()
    {
        // Solo permitimos 2 quincenas
        return [1,2];
    }
}

if (!function_exists('getMontoMaximoSugerido')) {
    /**
     * OBTENER EL MONTO MÁXIMO SUGERIDO (REDONDEADO)
     * 
     * @param int $limite_disponible Límite disponible
     * @return int Monto máximo redondeado
     */
    function getMontoMaximoSugerido($limite_disponible)
    {
        // Redondear a la decena más cercana
        return round($limite_disponible / 10) * 10;
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

?>