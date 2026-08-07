<?php

namespace App\Http\Requests\login;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class registerAdminRequest extends FormRequest
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
            'email' => 'string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'name' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'apellido_p' => 'required|string|max:255',
            'apellido_m' => 'required|string|max:255',
            'name_group' => 'required|string',
        ];
    }
    public function messages()
    {
        return [
            'required' => ':attribute is required',
            'name.string' => 'Name must be a string',
            'name.max' => 'Name must be less than 255 characters',
            'email.string' => 'Email must be a string',
            'email.email' => 'Email must be a valid email address',
            'email.max' => 'Email must be less than 255 characters',
            'email.unique' => 'Email already exists',
            'password.string' => 'Password must be a string',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'telefono.string' => 'Phone number must be a string',
            'telefono.max' => 'Phone number must be less than 20 characters',
            'apellido_p.string' => 'First surname must be a string',
            'apellido_p.max' => 'First surname must be less than 255 characters',
            'apellido_m.string' => 'Second surname must be a string',
            'apellido_m.max' => 'Second surname must be less than 255 characters',
            'name_group.string' => 'Nombre del grupo must be a string',
            'name_group.required' => 'Nombre del grupo is required',
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
