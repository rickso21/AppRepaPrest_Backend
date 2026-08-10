<?php

namespace App\Http\Requests\prestamo;

use Illuminate\Foundation\Http\FormRequest;

class AsesorPrestamoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'usuario_id' => 'required|exists:tbl_user,id',
            'estado_prestamo_id' => 'required|in:2,3,4,5',
            'motivo_rechazo' => 'nullable|string|max:500',
        ];

        // Si se está aprobando, validar fecha de desembolso
        if ($this->estado_prestamo_id == 2) {
            $rules['fecha_desembolso'] = 'nullable|date|after_or_equal:today';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'usuario_id.required' => 'El ID del usuario es requerido',
            'usuario_id.exists' => 'El usuario no existe',
            'estado_prestamo_id.required' => 'El estado es requerido',
            'estado_prestamo_id.in' => 'El estado no es válido',
            'fecha_desembolso.date' => 'Formato de fecha inválido',
            'fecha_desembolso.after_or_equal' => 'La fecha de desembolso debe ser hoy o en el futuro',
        ];
    }
}