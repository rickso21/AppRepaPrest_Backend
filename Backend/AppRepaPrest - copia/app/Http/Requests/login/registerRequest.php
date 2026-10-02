<?php

namespace App\Http\Requests\login;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\User;
use App\Models\Grupo;

class registerRequest extends FormRequest
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
            'email' => 'required|string|email|max:255|unique:tbl_user,email',
            'telefono' => 'required|string|max:20|unique:tbl_user,telefono',
            'password' => 'required|string|min:8|confirmed',
            'code' => 'required|string|exists:tbl_group,code',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar que el grupo existe y está activo
            if ($this->code) {
                $grupo = Grupo::where('code', $this->code)->first();
                if ($grupo && $grupo->status != 1) {
                    $validator->errors()->add('code', 'El grupo no está activo');
                }
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
            'email.required' => 'El email es obligatorio',
            'email.string' => 'El email debe ser texto',
            'email.email' => 'El formato del email no es válido',
            'email.max' => 'El email no puede exceder 255 caracteres',
            'email.unique' => 'El email ya está registrado en el sistema',

            // Teléfono
            'telefono.required' => 'El teléfono es obligatorio',
            'telefono.string' => 'El teléfono debe ser texto',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres',
            'telefono.unique' => 'El teléfono ya está registrado en el sistema',

            // Password
            'password.required' => 'La contraseña es obligatoria',
            'password.string' => 'La contraseña debe ser texto',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'La confirmación de contraseña no coincide',

            // Grupo
            'code.required' => 'El código de grupo es obligatorio',
            'code.string' => 'El código de grupo debe ser texto',
            'code.exists' => 'El código de grupo no es válido',
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
