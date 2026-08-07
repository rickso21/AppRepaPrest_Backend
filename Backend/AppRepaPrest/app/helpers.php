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
if (!function_exists('calcula_interes')) {
    function calcula_interes($monto, $quincenas) {
        $interes = 0;
        switch ($quincenas) {
            case 1: $interes = $monto * 0.04; break;
            case 2: $interes = $monto * 0.08; break;
            case 3: $interes = $monto * 0.10; break;
            case 4: $interes = $monto * 0.13; break;
            case 5: $interes = $monto * 0.15; break;
            default: $interes = $monto * 0.20; break; // Para más de 5 quincenas
        }
        return $interes;
    }
}

?>
