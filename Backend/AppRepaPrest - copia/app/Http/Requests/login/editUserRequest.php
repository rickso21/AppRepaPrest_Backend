<?php

namespace App\Http\Requests\login;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class editUserRequest extends FormRequest
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
            'nombre' => 'required|string|max:255',
            'apellido_p' => 'required|string|max:255',
            'apellido_m' => 'required|string|max:255',
            'password' => 'string|min:8|confirmed',
            'telefono' => 'required|string|min:10|max:15',        ];
    }
        public function messages()
    {
        return [
            'required' => ':attribute is required',
            'numer' => 'attribute must be at least 10 digits',
            'string' => ':attribute must be a string',
            'min' => ':attribute must be at least 10 characters', // Specific message for min
            'max' => ':attribute must not exceed 15 characters', // Added max message
            'confirmed' => ':attribute confirmation does not match',
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
