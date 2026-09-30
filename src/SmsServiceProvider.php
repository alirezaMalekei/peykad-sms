<?php

namespace AlirezaMalekei\PeykadSms;

use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sms.php', 'sms');

        $this->app->singleton('sms', fn ($app) => new SmsManager($app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sms.php' => config_path('sms.php'),
            ], 'sms-config');
        }
    }
}
