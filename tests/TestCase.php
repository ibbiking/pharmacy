<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** The only database tests may touch. RefreshDatabase drops every table in it. */
    public const TEST_DATABASE = 'pharmacy_testing';
}
