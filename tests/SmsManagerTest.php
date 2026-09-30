<?php

namespace AlirezaMalekei\PeykadSms\Tests;

use Illuminate\Support\Facades\Http;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use AlirezaMalekei\PeykadSms\Facades\Sms;

class SmsManagerTest extends TestCase
{
    public function test_default_driver_is_log(): void
    {
        $result = Sms::send('0913*****10', 'درود');

        $this->assertTrue($result->isSuccessful());
    }

    public function test_missing_api_key_throws_clear_exception(): void
    {
        config(['sms.default' => 'panel']);

        $this->expectException(SmsException::class);

        Sms::send('0913*****10', 'درود');
    }

    public function test_runtime_api_key_override_is_used(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'success', 'tracking_code' => 'X1'], 200)]);
        config(['sms.drivers.panel.base_url' => 'https://api.test']);

        Sms::panel(['api_key' => 'customer-key', 'line_number' => '1000002121'])
            ->send('0913*****10', 'درود');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'customer-key'));
    }
}
