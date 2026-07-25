<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateFromCookie
{
    /**
     * Handle an incoming request.
     * @deprecated Cookie auth disabled in favor of Bearer Token in Authorization header.
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
