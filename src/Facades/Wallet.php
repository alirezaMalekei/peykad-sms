<?php

namespace AlirezaMalekei\PeykadSms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AlirezaMalekei\PeykadSms\Responses\WalletResponse get(array $overrides = [])
 */
class Wallet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms.wallet';
    }
}