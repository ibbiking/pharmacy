<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** The only database tests may touch. RefreshDatabase drops every table in it. */
    public const TEST_DATABASE = 'pharmacy_testing';

    /**
     * Switching user inside one test keeps the same session. `AuthenticateSession` (13e) would see the previous user's
     * password hash there and sign the new user out, which never happens in the app (a real sign-in starts after a
     * sign-out). Forget the stored hash, like a fresh sign-in.
     */
    public function be(UserContract $user, $guard = null)
    {
        $session = $this->app['session']->driver();
        if ($session->has($key = 'password_hash_'.($guard ?? $this->app['auth']->getDefaultDriver()))) {
            $session->forget($key);
            $session->save(); // the array handler is re-read when the next request starts the session
        }

        return parent::be($user, $guard);
    }
}
