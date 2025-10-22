<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class KeycloakRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $roles = session('kc_roles', []);
        if (! in_array($role, $roles)) {
            abort(403, 'Access denied.');
        }
        return $next($request);
    }
}
