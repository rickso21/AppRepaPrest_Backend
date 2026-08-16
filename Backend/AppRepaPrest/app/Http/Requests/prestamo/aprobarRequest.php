<?php

namespace App\Http\Requests\prestamo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class aprobarRequest extends FormRequest
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
            'estado_prestamo_id' => 'required|integer|in:2,3', // 2 para aprobado, 3 para rechazado
        ];
    }
    public function messages()
    {
        return [
            'estado_prestamo_id.required' => 'El estado del préstamo es obligatorio.',
            'estado_prestamo_id.integer' => 'El estado del préstamo debe ser un número entero.',
            'estado_prestamo_id.in' => 'El estado del préstamo debe ser 2 (aprobado) o 3 (rechazado).',
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
