<?php

namespace App\Http\Requests\prestamo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class opcionesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'monto_solicitado' => 'required|numeric|min:1',
            'numero_pagos' => 'required|integer|min:1',
        ];
    }
    public function messages()
    {
        return [
            'monto_solicitado.required' => 'El monto solicitado es obligatorio.',
            'monto_solicitado.numeric' => 'El monto solicitado debe ser un número.',
            'monto_solicitado.min' => 'El monto solicitado debe ser al menos 1.',
            'numero_pagos.required' => 'El número de pagos es obligatorio.',
            'numero_pagos.integer' => 'El número de pagos debe ser un número entero.',
            'numero_pagos.min' => 'El número de pagos debe ser al menos 1.',
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
