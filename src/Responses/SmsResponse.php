<?php

namespace AlirezaMalekei\PeykadSms\Responses;

class SmsResponse
{
    public function __construct(
        private bool $success,
        private ?string $message = null,
        private ?string $trackingCode = null,
        private ?string $balance = null,
        private array $raw = [],
    ) {}

    /** ساخت پاسخ از خروجی JSON ای‌پی‌آی: success, message, tracking_code */
    public static function fromArray(array $data): self
    {
        return new self(
            success:      (bool) ($data['success'] ?? false),
            message:      $data['message'] ?? null,
            trackingCode: $data['tracking_code'] ?? null,
            balance:      $data['balance'] ?? null,
            raw:          $data,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    /** کد رهگیری ارسال */
    public function trackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function balance(): ?string
    {
        return $this->balance;
    }

    /** پاسخ خام ای‌پی‌آی */
    public function toArray(): array
    {
        return $this->raw;
    }
}
