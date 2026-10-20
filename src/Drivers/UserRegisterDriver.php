<?php

namespace AlirezaMalekei\PeykadSms\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use AlirezaMalekei\PeykadSms\Responses\UserRegisterResponse;

class UserRegisterDriver
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $endpoint,
        private readonly string $apiKey,
    ) {}

    public function register(array $data): UserRegisterResponse
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders(['Authorization' => $this->apiKey])
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint, $data);
        } catch (ConnectionException $e) {
            throw new SmsException('Registration request failed: ' . $e->getMessage(), 0, $e);
        }

        $result = $response->json();

        if ($response->status() === 422 && is_array($result)) {
            throw new HttpResponseException(new JsonResponse($result, 422));
        }

        if ($response->failed() || ! is_array($result) || ($result['success'] ?? true) === false) {
            $reason = is_array($result) ? ($result['message'] ?? $response->body()) : $response->body();
            $reason = is_string($reason) ? $reason : json_encode($reason);

            throw new SmsException(
                "Registration request failed (HTTP {$response->status()}): {$reason}"
            );
        }

        return UserRegisterResponse::fromArray($result);
    }
}
