<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'regex:/^[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀÈÌÒÙàèìòùÇç\' -]+$/u',
            ],
            'correo' => [
                'required',
                'email:rfc',
                'max:120',
            ],
            'pais' => [
                'required',
                'string',
                'max:60',
            ],
            'prefijo' => [
                'required',
                'string',
                'max:6',
            ],
            'telefono' => [
                'required',
                'regex:/^\d{7,15}$/',
            ],
            'mensaje' => [
                'required',
                'string',
                'min:10',
                'max:1000',
                'not_regex:/<[^>]*>/', // Bloquea HTML (anti-XSS)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.min'        => 'El nombre debe tener al menos 2 caracteres.',
            'nombre.max'        => 'El nombre no puede superar los 80 caracteres.',
            'nombre.regex'      => 'El nombre contiene caracteres no válidos.',

            'correo.required'   => 'El correo es obligatorio.',
            'correo.email'      => 'Introduce un correo electrónico válido.',
            'correo.max'        => 'El correo no puede superar los 120 caracteres.',

            'pais.required'     => 'El país es obligatorio.',
            'prefijo.required'  => 'El prefijo es obligatorio.',

            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.regex'    => 'El teléfono debe contener entre 7 y 15 dígitos.',

            'mensaje.required'  => 'El mensaje es obligatorio.',
            'mensaje.min'       => 'El mensaje debe tener al menos 10 caracteres.',
            'mensaje.max'       => 'El mensaje no puede superar los 1000 caracteres.',
            'mensaje.not_regex' => 'No se permiten etiquetas HTML en el mensaje.',
        ];
    }
}
