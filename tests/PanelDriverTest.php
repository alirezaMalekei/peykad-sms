<?php

namespace AlirezaMalekei\PeykadSms\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use AlirezaMalekei\PeykadSms\Drivers\PanelDriver;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;

class PanelDriverTest extends TestCase
{
    private function driver(): PanelDriver
    {
        return new PanelDriver('https://api.test', '/send', 'secret', '1000002121');
    }

    /**
     * @throws ConnectionException
     */
    public function test_it_sends_expected_payload_and_header(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'success', 'tracking_code' => 'ABC123'], 200)]);

        $this->driver()->send(['0913*****10', '0920*****10'], 'درود', ['name' => 'test']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'secret')
                && $request['phones'] === '913*****10,920*****10'
                && $request['line_number'] === '1000002121'
                && $request['text'] === 'درود'
                && $request['scheduled_at'] === null;
        });
    }

    /**
     * @throws ConnectionException
     */
    public function test_it_returns_parsed_response(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'success', 'tracking_code' => '123456789'], 200)]);

        $result = $this->driver()->send('0913*****10', 'درود');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('success', $result->message());
        $this->assertSame('123456789', $result->trackingCode());
    }

    /**
     * @throws ConnectionException
     */
    public function test_it_throws_on_http_failure(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);

        $this->expectException(SmsException::class);

        $this->driver()->send('0913*****10', 'درود');
    }

    /**
     * @throws ConnectionException
     */
    public function test_it_throws_when_success_is_false(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'invalid line'], 200)]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('invalid line');

        $this->driver()->send('0913*****10', 'درود');
    }

    /**
     * @throws ConnectionException
     */
    public function test_it_accepts_comma_separated_string(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'success', 'tracking_code' => '1234656789'], 200)]);

        $this->driver()->send('09131234567, 09201234567', 'Hello');

        Http::assertSent(fn (Request $request) => $request['phones'] === '9131234567,9201234567');
    }

    /**
     * @throws ConnectionException
     */
    public function test_line_number_option_overrides_default(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'success', 'tracking_code' => '1234656789'], 200)]);

        $this->driver()->send('0913*****10', 'Hello', ['line_number' => '1000003030']);

        Http::assertSent(fn (Request $request) => $request['line_number'] === '1000003030');
    }

    /**
     * @throws ConnectionException
     */
    public function test_it_throws_when_no_line_number_available(): void
    {
        $driver = new PanelDriver('https://api.test', '/send', 'secret');

        $this->expectException(SmsException::class);

        $driver->send('0913*****10', 'Hello');
    }
}
