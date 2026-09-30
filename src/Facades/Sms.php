<?php

namespace AlirezaMalekei\PeykadSms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AlirezaMalekei\PeykadSms\Responses\SmsResponse send(string|array $to, string $text, array $options = [])
 * @method static \AlirezaMalekei\PeykadSms\Contracts\SmsDriver driver(?string $driver = null)
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms';
    }
}
