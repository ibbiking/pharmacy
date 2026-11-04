<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * Guard: refuse to boot tests against anything but the dedicated test database. This runs
     * before RefreshDatabase (which drops every table), so a misconfigured .env/phpunit.xml can
     * never wipe real data.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($database !== TestCase::TEST_DATABASE) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}]. Tests must use [".TestCase::TEST_DATABASE.'] '.
                '(set in phpunit.xml) because they wipe and rebuild it.'
            );
        }

        return $app;
    }
}
