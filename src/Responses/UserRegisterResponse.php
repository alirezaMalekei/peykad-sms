<?php

namespace AlirezaMalekei\PeykadSms\Responses;

class UserRegisterResponse
{
    public function __construct(
        private bool $success,
        private ?string $message = null,
        private array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success: (bool) ($data['success'] ?? false),
            message: $data['message'] ?? null,
            raw: $data,
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

    public function toArray(): array
    {
        return $this->raw;
    }
}
