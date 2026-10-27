<?php

namespace App\Http\Controllers\soporte;

use App\Http\Controllers\Controller;
use App\Models\Soporte;

class SoporteController extends Controller
{
    /**
     * Devuelve el contacto de soporte activo.
     * Público (no requiere token), porque el login también podría necesitarlo.
     */
    public function show()
    {
        $soporte = Soporte::where('activo', true)
            ->orderByDesc('id')
            ->first();

        if (!$soporte) {
            return response()->json([
                'res' => false,
                'msg' => 'No hay soporte configurado.',
            ], 404);
        }

        return response()->json([
            'res' => true,
            'soporte' => [
                'id'              => $soporte->id,
                'nombre'          => $soporte->nombre,
                'telefono'        => $soporte->telefono,
                'mensaje_default' => $soporte->mensaje_default,
                'horario'         => $soporte->horario,
                'email'           => $soporte->email,
            ],
        ]);
    }
}
