<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsurePermission — middleware `permission:<permiso[,permiso...]>`.
 *
 * Segunda barrera local de la autorización doble (guidelines §1.3 y plan §5.4):
 * bloquea navegación y acciones de UI a partir de session('permissions'),
 * poblada con los claims del JWT web (contrato v2 §5.1). La API remota valida
 * el mismo permiso con la identidad del token; ocultar la UI no sustituye la
 * validación del Sincronizador.
 *
 * EnsureRole se conserva para compatibilidad de rutas previas; las rutas
 * nuevas se protegen por permisos explícitos.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $userPermissions = (array) session('permissions', []);

        if (count(array_intersect($userPermissions, $permissions)) === 0) {
            abort(403, __('No tienes permiso para acceder a esta sección.'));
        }

        return $next($request);
    }
}
