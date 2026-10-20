<?php

namespace AlirezaMalekei\PeykadSms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AlirezaMalekei\PeykadSms\Responses\UserRegisterResponse register(array $data, array $overrides = [])
 */
class UserRegister extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms.user-register';
    }
}
