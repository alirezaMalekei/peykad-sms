<?php

namespace AlirezaMalekei\PeykadSms\Contracts;

use AlirezaMalekei\PeykadSms\Responses\SmsResponse;

interface SmsDriver
{
    /**
     * @param  string|array  $to       یک شماره یا آرایه‌ای از شماره‌ها
     * @param  array         $options  name, line_number, scheduled_at
     */
    public function send(string|array $to, string $text, array $options = []): SmsResponse;
}
