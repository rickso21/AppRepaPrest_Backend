<?php

namespace App\Http\Controllers\contacto;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactoRequest;
use App\Models\Contacto;
use Illuminate\Http\JsonResponse;

class ContactoController extends Controller
{
  public function store(ContactoRequest $request): JsonResponse
    {
        $contacto = Contacto::create([
            'nombre'     => trim($request->nombre),
            'correo'     => strtolower(trim($request->correo)),
            'pais'       => $request->pais,
            'prefijo'    => $request->prefijo,
            'telefono'   => $request->telefono,
            'mensaje'    => trim($request->mensaje),
            'ip_origen'  => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'estado'     => 'nuevo',
        ]);

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Mensaje recibido correctamente.',
            'id'      => $contacto->id,
        ], 201);
    }
    }
