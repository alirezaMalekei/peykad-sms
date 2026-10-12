<?php

namespace AlirezaMalekei\PeykadSms;

use Illuminate\Contracts\Config\Repository;
use AlirezaMalekei\PeykadSms\Drivers\WalletDriver;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use AlirezaMalekei\PeykadSms\Responses\WalletResponse;

class WalletService
{
    public function __construct(private readonly Repository $config) {}

    public function get(array $overrides = []): WalletResponse
    {
        $panel = $this->config->get('sms.drivers.panel', []);
        $config = array_merge($this->config->get('sms.wallet', []), [
            'base_url' => $panel['base_url'] ?? null,
            'api_key'  => $panel['api_key'] ?? null,
        ], $overrides);

        foreach (['base_url', 'endpoint', 'api_key'] as $key) {
            if (empty($config[$key])) {
                throw new SmsException("Wallet setting «{$key}» is not configured.");
            }
        }

        $driver = new WalletDriver(
            baseUrl:  $config['base_url'],
            endpoint: $config['endpoint'],
            apiKey:   $config['api_key'],
        );

        return $driver->get(
            $config['record_key'] ?? null,
            $config['balance_key'] ?? 'balance',
        );
    }
}
