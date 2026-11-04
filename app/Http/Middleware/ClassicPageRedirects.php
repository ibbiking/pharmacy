<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * While the app is half Inertia, half classic Blade (Roadmap step 5 converts the rest):
 * when an Inertia request is redirected to a screen that is NOT an Inertia page, answer with
 * Inertia::location() (409) so the browser does a full page load instead of trying to read HTML as
 * an Inertia response. Flash messages are mirrored into the classic format (message + alert-type).
 *
 * Inertia screens are listed in config('inertia.screens'); add a screen there when converting it.
 */
class ClassicPageRedirects
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $request->header('X-Inertia') || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $target = $response->getTargetUrl();
        if ($this->isInertiaScreen($target)) {
            return $response;
        }

        foreach (['success' => 'success', 'error' => 'error'] as $key => $type) {
            if (session()->has($key) && ! session()->has('message')) {
                session()->flash('message', session($key));
                session()->flash('alert-type', $type);
            }
        }

        return Inertia::location($target);
    }

    private function isInertiaScreen(string $url): bool
    {
        try {
            $route = app('router')->getRoutes()->match(Request::create($url, 'GET'));
        } catch (HttpExceptionInterface) {
            return false;
        }

        $name = $route->getName();

        return $name !== null && Str::is(config('inertia.screens', []), $name);
    }
}
