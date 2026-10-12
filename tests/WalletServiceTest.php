<?php

namespace AlirezaMalekei\PeykadSms\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use AlirezaMalekei\PeykadSms\Drivers\WalletDriver;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use AlirezaMalekei\PeykadSms\Facades\Wallet;

class WalletServiceTest extends TestCase
{
    private function configure(array $wallet = []): void
    {
        config()->set('sms.drivers.panel', [
            'base_url' => 'https://api.test',
            'api_key'  => 'secret',
        ]);
        config()->set('sms.wallet', array_merge([
            'endpoint' => '/wallet',
        ], $wallet));
    }

    public function test_it_returns_the_wallet_record(): void
    {
        $this->configure();
        $body = ['success' => true, 'id' => 7, 'balance' => 125000, 'currency' => 'IRR'];
        Http::fake(['*' => Http::response($body, 200)]);

        $wallet = Wallet::get();

        $this->assertSame($body, $wallet->toArray());
        $this->assertSame(125000, $wallet->balance());
        $this->assertSame('IRR', $wallet->get('currency'));
        $this->assertSame($body, $wallet->raw());
    }

    public function test_it_unwraps_the_data_node_when_present(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([
            'success' => true,
            'message' => 'کیف پول دریافت شد',
            'data'    => ['id' => 9, 'balance' => 2500.5, 'panel_id' => 134],
        ], 200)]);

        $wallet = Wallet::get();

        $this->assertSame(
            ['id' => 9, 'balance' => 2500.5, 'panel_id' => 134],
            $wallet->toArray()
        );
        $this->assertSame(2500.5, $wallet->balance());
        $this->assertSame(134, $wallet->get('panel_id'));
    }

    public function test_balance_is_null_when_field_missing(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['id' => 1], 200)]);

        $this->assertNull(Wallet::get()->balance());
    }

    public function test_runtime_api_key_overrides_config(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['id' => 1], 200)]);

        Wallet::get(['api_key' => 'customer-key']);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'customer-key'));
    }

    public function test_it_works_with_only_an_api_key_and_no_sms_line_number(): void
    {
        config()->set('sms.drivers.panel', ['base_url' => 'https://api.test', 'line_number' => null]);
        config()->set('sms.wallet', ['endpoint' => '/wallet']);
        Http::fake(['*' => Http::response(['id' => 1, 'balance' => 7], 200)]);

        $this->assertSame(7, Wallet::get(['api_key' => 'k'])->balance());
    }

    public function test_it_uses_the_same_api_key_and_url_as_sending_sms(): void
    {
        config()->set('sms.drivers.panel', [
            'base_url' => 'https://panel.test',
            'api_key'  => 'panel-key',
        ]);
        config()->set('sms.wallet.endpoint', '/wallet'); // the only wallet-specific setting
        Http::fake(['*' => Http::response(['id' => 1, 'balance' => 3], 200)]);

        $this->assertSame(3, Wallet::get()->balance());

        Http::assertSent(fn (Request $r) => $r->url() === 'https://panel.test/wallet'
            && $r->hasHeader('Authorization', 'panel-key'));
    }

    public function test_wallet_specific_api_key_does_not_override_the_shared_panel_key(): void
    {
        config()->set('sms.drivers.panel', ['base_url' => 'https://panel.test', 'api_key' => 'panel-key']);
        config()->set('sms.wallet', ['endpoint' => '/wallet', 'api_key' => 'wallet-key']);
        Http::fake(['*' => Http::response(['id' => 1], 200)]);

        Wallet::get();

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'panel-key'));
    }

    public function test_it_throws_a_clear_error_for_each_missing_setting(): void
    {
        foreach (['base_url', 'endpoint', 'api_key'] as $missing) {
            $this->configure();
            config()->set($missing === 'endpoint' ? 'sms.wallet.endpoint' : "sms.drivers.panel.{$missing}", null);

            try {
                Wallet::get();
                $this->fail("Expected SmsException when {$missing} is missing");
            } catch (SmsException $e) {
                $this->assertStringContainsString($missing, $e->getMessage());
            }
        }
    }

    public function test_the_wallet_request_never_touches_the_sms_driver(): void
    {
        $this->configure();
        config()->set('sms.default', 'log'); // not a real sender, wallet must not care
        Http::fake(['*' => Http::response(['id' => 1], 200)]);

        Wallet::get();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.test/wallet'
            && $r->method() === 'GET'
            && $r->body() === '');
    }

    public function test_it_always_uses_get_even_with_a_method_override(): void
    {
        $this->configure(['method' => 'POST']);
        Http::fake(['*' => Http::response(['balance' => 5], 200)]);

        Wallet::get(['method' => 'POST']);

        Http::assertSent(fn (Request $r) => $r->method() === 'GET');
    }

    public function test_it_rejects_http_errors_api_errors_and_invalid_json(): void
    {
        $this->configure();

        foreach ([[['message' => 'unavailable'], 503], [['success' => false], 200], ['invalid JSON', 200]] as [$body, $status]) {
            Http::fake(['*' => Http::response($body, $status)]);

            try {
                Wallet::get();
                $this->fail('Expected SmsException for an unsuccessful wallet response');
            } catch (SmsException $e) {
                $this->assertStringContainsString('Wallet request failed', $e->getMessage());
            }
        }
    }

    public function test_it_converts_connection_errors_to_sms_exceptions(): void
    {
        $this->configure();
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Connection timed out');

        Wallet::get();
    }

    public function test_wallet_driver_sends_get_without_a_payload(): void
    {
        Http::fake(['*' => Http::response(['balance' => 12], 200)]);
        $driver = new WalletDriver('https://panel.test', '/wallet', 'panel-key');

        $this->assertSame(12, $driver->get()->balance());

        Http::assertSent(fn (Request $r) => $r->url() === 'https://panel.test/wallet'
            && $r->method() === 'GET'
            && $r->body() === ''
            && $r->hasHeader('Authorization', 'panel-key'));
    }
}