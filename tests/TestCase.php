<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Runs before RefreshDatabase: never reset the development database by mistake.
        if (! $app->environment('testing')
            || $app->configurationIsCached()
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'maktoobe_test'
            || config('database.connections.mysql.username') !== 'maktoobe_test'
            || filled(config('database.connections.mysql.url'))) {
            throw new LogicException('Tests require uncached configuration and the isolated maktoobe_test MySQL database/user.');
        }

        return $app;
    }
}
