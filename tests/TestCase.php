<?php

namespace AlirezaMalekei\PeykadSms\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use AlirezaMalekei\PeykadSms\SmsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SmsServiceProvider::class];
    }
}
