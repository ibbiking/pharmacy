<?php

namespace Tests\Support;

use App\Models\Platform\PlatformAdmin;

/** Sign in to the separate super admin portal (/platform, guard `platform`). */
trait PlatformPortal
{
    protected function platformAdmin(string $email = 'root@platform.test'): PlatformAdmin
    {
        return PlatformAdmin::query()->firstOrCreate(['email' => $email], ['name' => 'Root', 'password' => 'Platform123', 'is_active' => true]);
    }

    protected function asPlatformAdmin(?PlatformAdmin $admin = null): static
    {
        $this->actingAs($admin ?? $this->platformAdmin(), 'platform');
        // A real request starts on the `web` guard; the portal middleware switches to `platform` itself.
        $this->app['auth']->shouldUse('web');

        return $this;
    }
}
