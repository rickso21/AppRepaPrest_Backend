<?php

namespace App\Http\Requests\login;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\User;
use App\Models\Grupo;

class registerAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'apellido_p' => 'required|string|max:255',
            'apellido_m' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:tbl_user,email',
            'telefono' => 'nullable|string|max:20|unique:tbl_user,telefono',
            'password' => 'required|string|min:8|confirmed',
            'name_group' => 'required|string|max:255|unique:tbl_group,group_name',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (empty($this->email) && empty($this->telefono)) {
                $validator->errors()->add('email', 'Debe proporcionar al menos email o teléfono');
                $validator->errors()->add('telefono', 'Debe proporcionar al menos email o teléfono');
            }
        });
    }

    public function messages()
    {
        return [
            // Nombre
            'name.required' => 'El nombre es obligatorio',
            'name.string' => 'El nombre debe ser texto',
            'name.max' => 'El nombre no puede exceder 255 caracteres',

            // Apellidos
            'apellido_p.required' => 'El apellido paterno es obligatorio',
            'apellido_p.string' => 'El apellido paterno debe ser texto',
            'apellido_p.max' => 'El apellido paterno no puede exceder 255 caracteres',
            'apellido_m.string' => 'El apellido materno debe ser texto',
            'apellido_m.max' => 'El apellido materno no puede exceder 255 caracteres',

            // Email
            'email.email' => 'El formato del email no es válido',
            'email.unique' => 'El email ya está registrado en el sistema',
            'email.max' => 'El email no puede exceder 255 caracteres',

            // Teléfono
            'telefono.unique' => 'El teléfono ya está registrado en el sistema',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres',

            // Password
            'password.required' => 'La contraseña es obligatoria',
            'password.string' => 'La contraseña debe ser texto',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'La confirmación de contraseña no coincide',

            // Grupo
            'name_group.required' => 'El nombre del grupo es obligatorio',
            'name_group.string' => 'El nombre del grupo debe ser texto',
            'name_group.max' => 'El nombre del grupo no puede exceder 255 caracteres',
            'name_group.unique' => 'El nombre del grupo ya existe, elija otro',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'res' => false,
            'msg' => 'Errores de validación',
            'errors' => $validator->errors()
        ], 422));
    }
}
