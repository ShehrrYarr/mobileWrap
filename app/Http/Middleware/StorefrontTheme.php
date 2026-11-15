<?php

namespace App\Http\Middleware;

use App\Support\Theme;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active storefront theme for the request and points
 * Livewire's full-page layout at it. Signed-in staff can preview any
 * theme with ?preview_theme=key (and leave with ?exit_preview=1).
 */
class StorefrontTheme
{
    public function __construct(protected Theme $theme) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->boolean('exit_preview')) {
            $request->session()->forget('preview_theme');

            return redirect($request->url());
        }

        if ($request->filled('preview_theme') && auth('web')->check() && $this->theme->exists($request->query('preview_theme'))) {
            $request->session()->put('preview_theme', $request->query('preview_theme'));

            return redirect($request->url());
        }

        if (session('preview_theme') && ! auth('web')->check()) {
            $request->session()->forget('preview_theme');
        }

        config(['livewire.layout' => $this->theme->view('layout')]);

        return $next($request);
    }
}
