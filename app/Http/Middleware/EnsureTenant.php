<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->has('current.tenant')) {
            abort(403, 'Tenant context required.');
        }

        return $next($request);
    }
}
