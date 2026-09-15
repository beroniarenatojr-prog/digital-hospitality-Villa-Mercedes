<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PersistRememberedSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $hasRecaller = $guard instanceof SessionGuard
            && $request->cookies->has($guard->getRecallerName());
        $keep = $request->hasSession() && $request->session()->get('auth.keep');

        if ($hasRecaller || $keep) {
            config(['session.lifetime' => (int) config('auth.remember_session_lifetime')]);
        }

        return $next($request);
    }
}
