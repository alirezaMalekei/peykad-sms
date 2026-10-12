<?php

namespace AlirezaMalekei\PeykadSms\Responses;

use AlirezaMalekei\PeykadSms\Exceptions\SmsException;

class WalletResponse
{
    public function __construct(
        private readonly array $record,
        private readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        if ($data === []) {
            throw new SmsException('پاسخ کیف پول خالی است.');
        }

        // API-level success flag (if present).
        if (array_key_exists('success', $data) && $data['success'] === false) {
            throw new SmsException(
                (string) ($data['message'] ?? 'دریافت کیف پول با خطا مواجه شد.')
            );
        }

        // The wallet record is the "data" node when present, otherwise the whole payload.
        $record = is_array($data['data'] ?? null) && $data['data'] !== []
            ? $data['data']
            : $data;

        return new self($record, $data);
    }

    /** مقدار یک فیلد از رکورد کیف پول (dot notation پشتیبانی می‌شود) */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->record, $key, $default);
    }

    /** موجودی کیف پول به صورت عدد، یا null اگر موجود نباشد */
    public function balance(): int|float|null
    {
        $value = $this->get('balance');

        return is_numeric($value) ? $value + 0 : null;
    }

    /** پیام پاسخ ای‌پی‌آی (مثلاً «کیف پول دریافت شد») */
    public function message(): ?string
    {
        $message = $this->raw['message'] ?? null;

        return is_string($message) ? $message : null;
    }

    /** رکورد کیف پول */
    public function toArray(): array
    {
        return $this->record;
    }

    /** پاسخ کامل ای‌پی‌آی */
    public function raw(): array
    {
        return $this->raw;
    }
}
