<?php

namespace Sage\Credible\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sage\Credible\CredibleServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [CredibleServiceProvider::class];
    }
}
