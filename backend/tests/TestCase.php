<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against a real database: in Docker the container's env vars
     * would otherwise win over phpunit.xml and RefreshDatabase would wipe it.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];

        if ($config->get('database.default') !== 'sqlite' || $config->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Tests must run on the in-memory SQLite database. Check the <server> entries in phpunit.xml.');
        }

        return $app;
    }
}
