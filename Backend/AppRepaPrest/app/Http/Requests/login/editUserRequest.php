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
            'nombre'     => 'sometimes|string|max:200',
            'apellido_p' => 'sometimes|nullable|string|max:200',
            'apellido_m' => 'sometimes|nullable|string|max:200',
            'email'      => 'sometimes|email|max:200',
            'telefono'   => 'sometimes|nullable|string|max:200',
            'ciudad'     => 'sometimes|nullable|string|max:50',
            'password'   => 'sometimes|nullable|string|min:6',
            'avatar'     => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:5120',
            'portada'    => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:8192',

        ];
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
        ], 400));
    }
}
