<?php

namespace App\Http\Requests\aprobar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class aprobarRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
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
            'estado_prestamo_id.required' => 'El estado es requerido',
            'estado_prestamo_id.in' => 'El estado no es válido',
        ];
    }

     public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => false,
            'message'   => 'Validation errors',
            'data'      => $validator->errors()
        ],400));
    }
}
