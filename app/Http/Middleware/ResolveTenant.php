<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $tenant = auth()->user()->tenant;
            app()->instance('current.tenant', $tenant);
        }

        return $next($request);
    }
}
