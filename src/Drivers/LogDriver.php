<?php

namespace AlirezaMalekei\PeykadSms\Drivers;

use Illuminate\Support\Facades\Log;
use AlirezaMalekei\PeykadSms\Contracts\SmsDriver;
use AlirezaMalekei\PeykadSms\Responses\SmsResponse;

class LogDriver implements SmsDriver
{
    public function send(string|array $to, string $message, array $options = []): SmsResponse
    {
        $numbers = implode(',', (array) $to);
        Log::info("SMS to {$numbers}: {$message}", $options);

        return new SmsResponse(true, 'logged');
    }
}
