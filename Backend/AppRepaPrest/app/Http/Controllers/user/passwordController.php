<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Requests\password\olvideRequest;
use App\Models\User;
use App\Services\BrevoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class passwordController extends Controller
{
    // ================================================================
    // CONSTANTES
    // ================================================================

    /** Longitud de la contraseña generada automáticamente */
    private const PASSWORD_LENGTH = 10;

    /** Longitud mínima permitida para la nueva contraseña */
    private const PASSWORD_MIN_LENGTH = 8;

    // ================================================================
    // ENDPOINTS PÚBLICOS (sin auth)
    // ================================================================

    /**
     * POST /api/forgot-password
     *
     * Genera una nueva contraseña aleatoria y la envía por correo.
     *
     * Flujo:
     * 1. Busca al usuario por email o teléfono.
     * 2. Valida que esté activo (status_id = 1).
     * 3. Genera una contraseña segura aleatoria.
     * 4. Hashea y guarda en BD.
     * 5. Revoca todos los tokens de Sanctum (fuerza logout).
     * 6. Envía la nueva contraseña por correo con Brevo.
     *
     * Por seguridad, siempre responde lo mismo aunque el usuario no exista
     * (para evitar enumeración de cuentas).
     */
    public function olvide_password(olvideRequest $request)
    {
        $resp        = ['res' => false, 'msg' => 'Algo salió mal'];
        $status_resp = 400;

        try {
            // --------------------------------------------------------
            // 1. Buscar usuario por email o teléfono
            // --------------------------------------------------------
            $user = User::where('email', $request->email)
                ->orWhere('telefono', $request->email)
                ->first();

            // Respuesta genérica si no existe (anti-enumeración)
            if (!$user) {
                return response()->json([
                    'res' => true,
                    'msg' => 'Si el correo está registrado, recibirás una nueva contraseña en breve.',
                ], 200);
            }

            // --------------------------------------------------------
            // 2. Validar que la cuenta esté activa
            // --------------------------------------------------------
            if ((int) $user->status_id !== 1) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Tu cuenta está inhabilitada. Contacta a soporte para reactivarla.',
                ], 403);
            }

            // --------------------------------------------------------
            // 3. Validar que tenga correo registrado
            // --------------------------------------------------------
            if (empty($user->email)) {
                return response()->json([
                    'res' => false,
                    'msg' => 'Tu cuenta no tiene un correo registrado. Contacta a soporte.',
                ], 400);
            }

            // --------------------------------------------------------
            // 4. Generar y guardar nueva contraseña
            // --------------------------------------------------------
            $nuevaPassword = $this->generarPasswordSegura(self::PASSWORD_LENGTH);

            $user->password = Hash::make($nuevaPassword);
            $user->save();

            // --------------------------------------------------------
            // 5. Revocar tokens (forzar logout en otros dispositivos)
            // --------------------------------------------------------
            $user->tokens()->delete();

            // --------------------------------------------------------
            // 6. Enviar correo con Brevo
            // --------------------------------------------------------
            try {
                $brevoService = new BrevoService();
                $brevoService->sendPasswordResetEmail($user, $nuevaPassword);

                $resp['res'] = true;
                $resp['msg'] = 'Te enviamos una nueva contraseña a tu correo.';
                $status_resp = 200;

            } catch (\Throwable $th) {
                // No revelamos el error de correo al usuario
                \Log::error('[olvide_password] Error enviando correo', [
                    'user_id' => $user->id,
                    'error'   => $th->getMessage(),
                ]);

                $resp['res'] = true;
                $resp['msg'] = 'Si el correo está registrado, recibirás una nueva contraseña en breve.';
                $status_resp = 200;
            }

        } catch (\Throwable $th) {
            \Log::error('[olvide_password] Error general', [
                'error' => $th->getMessage(),
            ]);
            $resp['msg'] = 'Error al procesar la solicitud. Intenta más tarde.';
            $status_resp = 500;
        }

        return response()->json($resp, $status_resp);
    }

    // ================================================================
    // ENDPOINTS PROTEGIDOS (con auth:sanctum)
    // ================================================================

    /**
     * POST /api/change-password
     *
     * Cambia la contraseña de un usuario autenticado.
     * Requiere la contraseña actual para confirmar identidad.
     *
     * Flujo:
     * 1. Valida los campos (password_actual, password_nueva, password_nueva_confirmation).
     * 2. Verifica que la contraseña actual coincida con la de BD.
     * 3. Verifica que la nueva sea diferente a la actual.
     * 4. Guarda la nueva contraseña.
     * 5. Revoca todos los tokens EXCEPTO el actual.
     */
    public function cambiar_password(Request $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado',
            ], 401);
        }

        // --------------------------------------------------------
        // 1. Validación de campos
        // --------------------------------------------------------
        $request->validate([
            'password_actual' => 'required|string',
            'password_nueva'  => 'required|string|min:' . self::PASSWORD_MIN_LENGTH . '|confirmed',
        ], [
            'password_actual.required' => 'Ingresa tu contraseña actual.',
            'password_nueva.required'  => 'Ingresa la nueva contraseña.',
            'password_nueva.min'       => 'La nueva contraseña debe tener al menos ' . self::PASSWORD_MIN_LENGTH . ' caracteres.',
            'password_nueva.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        // --------------------------------------------------------
        // 2. Validar contraseña actual
        // --------------------------------------------------------
        if (!Hash::check($request->password_actual, $user_token->password)) {
            return response()->json([
                'res' => false,
                'msg' => 'La contraseña actual es incorrecta.',
            ], 401);
        }

        // --------------------------------------------------------
        // 3. Validar que la nueva sea diferente a la actual
        // --------------------------------------------------------
        if (Hash::check($request->password_nueva, $user_token->password)) {
            return response()->json([
                'res' => false,
                'msg' => 'La nueva contraseña no puede ser igual a la actual.',
            ], 422);
        }

        // --------------------------------------------------------
        // 4. Guardar nueva contraseña
        // --------------------------------------------------------
        try {
            $user = User::find($user_token->id);
            $user->password = Hash::make($request->password_nueva);
            $user->save();

            // Revocar todos los tokens EXCEPTO el actual
            // (el usuario sigue logueado en este dispositivo)
            $currentTokenId = $user_token->currentAccessToken()?->id;

            if ($currentTokenId) {
                $user->tokens()->where('id', '!=', $currentTokenId)->delete();
            }

            return response()->json([
                'res' => true,
                'msg' => 'Contraseña actualizada correctamente.',
            ], 200);

        } catch (\Throwable $th) {
            \Log::error('[cambiar_password] Error: ' . $th->getMessage());

            return response()->json([
                'res' => false,
                'msg' => 'Error al actualizar la contraseña.',
            ], 500);
        }
    }

    // ================================================================
    // HELPERS PRIVADOS
    // ================================================================

    /**
     * Genera una contraseña segura aleatoria.
     *
     * Garantiza al menos:
     * - 1 letra mayúscula
     * - 1 letra minúscula
     * - 1 número
     *
     * Evita caracteres confusos (0/O, 1/l/I) para que sea fácil de leer
     * en un correo electrónico.
     */
    private function generarPasswordSegura(int $length = self::PASSWORD_LENGTH): string
    {
        $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // sin I, O
        $minusculas = 'abcdefghijkmnpqrstuvwxyz';   // sin l, o
        $numeros    = '23456789';                    // sin 0, 1

        $todos = $mayusculas . $minusculas . $numeros;

        // Garantizar al menos 1 de cada tipo
        $password  = $mayusculas[random_int(0, strlen($mayusculas) - 1)];
        $password .= $minusculas[random_int(0, strlen($minusculas) - 1)];
        $password .= $numeros[random_int(0, strlen($numeros) - 1)];

        // Rellenar el resto con caracteres aleatorios
        for ($i = 3; $i < $length; $i++) {
            $password .= $todos[random_int(0, strlen($todos) - 1)];
        }

        // Mezclar para evitar patrones predecibles
        return str_shuffle($password);
    }
}
