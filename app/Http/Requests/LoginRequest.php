<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * LoginRequest — validación del formulario de inicio de sesión de oficina.
 *
 * Reglas (contrato v2 §5): el login es el único endpoint público de la
 * frontera web; el Sincronizador aplica rate limit (5 intentos/IP/minuto)
 * y aquí Laravel aplica un throttle local equivalente en la ruta.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
