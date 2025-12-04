<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;
use JWTAuth;

class JWTMiddleWare
{
    
    public function handle(Request $request, Closure $next)
    {
        $user = JWTAuth::parseToken()->authenticate();
        return $next($request);
    }
}
