<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Role
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user() || ! $request->user()->hasRole($roles)) {
            $referer = $request->headers->get('referer');
            $currentUrl = $request->url();

            if ($referer && rtrim($referer, '/') === rtrim($currentUrl, '/')) {
                return redirect()->route('welcome')->with('error', 'You are not authorized to access this page.');
            }

            return back()->with('error', 'You are not authorized to access this page.');
        }

        return $next($request);
    }
}
