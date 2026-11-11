<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Side drawer (resources/js/Components/Drawer.tsx): a save made inside a drawer sends `X-Drawer-Return: <page under
 * the drawer>`. A successful save is then redirected back to that page (it reloads with fresh data and the flash
 * message) instead of the screen's own full-page target. Failed validation is left alone (Laravel already goes back).
 * Only same-site URLs are accepted, so the header can't be used as an open redirect.
 *
 * A controller whose save leads to a next step (e.g. new product → packaging) flashes `drawer_follow`; that target is
 * then handed to the drawer as `flash.drawer_next` and opens in the same drawer.
 */
class DrawerRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $return = $request->header('X-Drawer-Return');
        if (! $return || $request->isMethod('GET') || ! $response instanceof RedirectResponse) {
            return $response;
        }
        if ($request->hasSession() && $request->session()->has('errors')) {
            return $response;
        }
        if (parse_url($return, PHP_URL_HOST) !== $request->getHost()) {
            return $response;
        }

        if ($request->hasSession() && $request->session()->pull('drawer_follow')) {
            $request->session()->flash('drawer_next', $response->getTargetUrl());
        }

        return $response->setTargetUrl($return);
    }
}
