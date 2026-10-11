<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta el acceso a las cuentas cuya empresa está desactivada.
 *
 * Desactivar una empresa (`companies.is_active = false`) es la forma de dar
 * de baja a un cliente de la plataforma sin borrar sus datos ni tocar los
 * roles de su gente: a partir de ese momento su plantilla deja de entrar al
 * backoffice —tanto a las pantallas de `routes/web.php` como a la API, que
 * es contra la que van esas pantallas— y se le devuelve un 403.
 *
 * Lo que NO toca: la consulta pública de seguimiento (`shipments.show` vive
 * fuera del grupo autenticado), ni las cuentas sin empresa, ni al
 * superadministrador — ver {@see User::belongsToActiveCompany()}.
 */
class EnsureCompanyIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->belongsToActiveCompany()) {
            abort(403, __('La empresa a la que pertenece tu cuenta está desactivada. Contacta con el administrador de la plataforma.'));
        }

        return $next($request);
    }
}
