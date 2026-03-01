<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * Check if the authenticated user's status is active.
     * If not, log them out and redirect with an appropriate message.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $user->isSuspended()
                ? 'Your account has been suspended. Please contact an administrator.'
                : 'Your account is currently inactive. Please contact an administrator.';

            return redirect()->route('login')->with('status', $message);
        }

        return $next($request);
    }
}
