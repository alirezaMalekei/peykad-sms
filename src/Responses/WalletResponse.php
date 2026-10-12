<?php

namespace AlirezaMalekei\PeykadSms\Responses;

use AlirezaMalekei\PeykadSms\Exceptions\SmsException;

class WalletResponse
{
    public function __construct(
        private array $record,
        private array $raw = [],
        private string $balanceKey = 'balance',
    ) {}

    /**
     * @param string|null $recordKey  Where the wallet record lives in the JSON response
     *                                (dot notation, e.g. "data"). null = the whole response.
     * @param string      $balanceKey Key of the balance inside the record (dot notation supported).
     */
    public static function fromArray(array $data, ?string $recordKey = null, string $balanceKey = 'balance'): self
    {
        $record = ($recordKey === null || $recordKey === '') ? $data : data_get($data, $recordKey);

        if (! is_array($record) || $record === []) {
            throw new SmsException(
                "رکورد کیف پول در پاسخ پنل با کلید «{$recordKey}» پیدا نشد. "
                . 'کلید درست را در SMS_WALLET_KEY تنظیم کنید.'
            );
        }

        return new self($record, $data, $balanceKey);
    }

    /** مقدار یک فیلد از رکورد کیف پول (dot notation پشتیبانی می‌شود) */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->record, $key, $default);
    }

    /** موجودی (اگر کلید balance_key در رکورد باشد و عددی باشد)، در غیر این صورت null */
    public function balance(): int|float|null
    {
        $value = $this->get($this->balanceKey);

        return is_numeric($value) ? $value + 0 : null;
    }

    /** رکورد کیف پول */
    public function toArray(): array
    {
        return $this->record;
    }

    /** پاسخ خام ای‌پی‌آی */
    public function raw(): array
    {
        return $this->raw;
    }
}