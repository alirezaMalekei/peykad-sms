<?php

namespace AlirezaMalekei\PeykadSms;

use Illuminate\Contracts\Config\Repository;
use AlirezaMalekei\PeykadSms\Drivers\UserRegisterDriver;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use AlirezaMalekei\PeykadSms\Responses\UserRegisterResponse;

class UserRegisterService
{
    public function __construct(
        private readonly Repository $config
    ) {}

    public function register(array $data, array $overrides = []): UserRegisterResponse
    {
        $panel = $this->config->get('sms.drivers.panel', []);
        $config = array_merge($this->config->get('sms.registration', []), [
            'base_url' => $panel['base_url'] ?? null,
            'api_key' => $panel['api_key'] ?? null,
        ], $overrides);

        foreach (['base_url', 'endpoint', 'api_key'] as $key) {
            if (empty($config[$key])) {
                throw new SmsException("Registration setting «{$key}» is not configured.");
            }
        }

        $driver = new UserRegisterDriver(
            baseUrl: $config['base_url'],
            endpoint: $config['endpoint'],
            apiKey: $config['api_key'],
        );

        return $driver->register($data);
    }
}
