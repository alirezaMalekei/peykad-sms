<?php

namespace AlirezaMalekei\PeykadSms\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use AlirezaMalekei\PeykadSms\Responses\WalletResponse;

class WalletDriver
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $endpoint,
        private readonly string $apiKey,
    ) {}

    public function get(?string $recordKey = null, string $balanceKey = 'balance'): WalletResponse
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders(['Authorization' => $this->apiKey])
                ->acceptJson()
                ->get($this->endpoint);
        } catch (ConnectionException $e) {
            throw new SmsException('Wallet request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = $response->json();

        if ($response->failed() || ! is_array($data) || ($data['success'] ?? true) === false) {
            throw new SmsException("Wallet request failed (HTTP {$response->status()}).");
        }

        return WalletResponse::fromArray($data, $recordKey, $balanceKey);
    }
}
