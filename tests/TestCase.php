<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'mysql'
            || $app['config']->get('database.connections.mysql.database') !== 'inventory_adjustment_testing') {
            throw new RuntimeException('Database tests must use MySQL inventory_adjustment_testing. Clear cached configuration before running tests.');
        }

        if ($app['db']->connection()->selectOne('select database() as name')->name !== 'inventory_adjustment_testing') {
            throw new RuntimeException('The actual database connection is not inventory_adjustment_testing.');
        }

        return $app;
    }
}
