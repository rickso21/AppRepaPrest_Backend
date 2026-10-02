<?php

namespace App\Http\Requests\comunidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class savePublishRequest extends FormRequest
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
            'txt'   => 'nullable|string|max:5000',
            'img'   => 'nullable|file|image|max:20480',              // 20 MB
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm,mkv|max:512000',
        ];
    }
    public function messages()
    {
        return [
            'required' => 'El :attribute es requerido',
            'string' => 'El :attribute debe ser de tipo texto'
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
