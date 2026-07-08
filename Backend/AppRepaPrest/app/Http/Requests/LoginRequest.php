<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            "user" => "required|string|max:255|exists:tbl_users_api,user",
            "password" => "required|string|max:255" 
        ];
    }

    public function messages()
    {
        return [
            "user.required" => "El nombre de usuario es obligatorio",
            "user.string" => "El nombre de usuario debe ser una cadena de caracteres",
            "user.max" => "El nombre de usuario debe tener maximo 255 caracteres",
            "user.exists" => "Nombre de usuario no registrado",

            "password.required" => "El password es obligatorio",
            "password.string" => "El password debe ser una cadena de caracteres",
            "password.max" => "Longitud de password mayor a la maxima"
        ];
    }
}
