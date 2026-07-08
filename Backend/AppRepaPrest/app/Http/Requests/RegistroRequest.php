<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegistroRequest extends FormRequest
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
            "user" => "required|string|max:255|unique:tbl_users_api,user",
            "password" => "required|string|min:5"
        ];
    }

    public function messages()
    {
        return [
            "user.required" => "Usuario obligatorio",
            "user.string" => "El nombre de usuario debe ser una cadena de texto",
            "user.max" => "El nombre de usuario debe ser de maximo 255 caracteres",
            "user.unique" => "Usuario ya registrado",
            "password.required" => "El password es obligatorio",
            "password.string" => "El password debe ser una cadena de texto",
            "password.min" => "El password debe tener una longitud minima de 5 caracteres"
        ];
    }
}
