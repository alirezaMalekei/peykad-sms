<?php

namespace AlirezaMalekei\PeykadSms\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use AlirezaMalekei\PeykadSms\Facades\Panel;
use AlirezaMalekei\PeykadSms\Facades\UserRegister;
use AlirezaMalekei\PeykadSms\Responses\UserRegisterResponse;

class PanelRegisterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sms.drivers.panel', [
            'base_url' => 'https://panel.test',
            'api_key' => 'panel-key',
        ]);
        config()->set('sms.registration', ['endpoint' => '/register']);

        Http::preventStrayRequests();
    }

    private function userData(): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '09131234567',
            'email' => 'user@example.test',
            'address' => 'Test address',
            'national_id' => null,
            'type' => 'legal-test-value',
            'company_name' => 'Test Company',
            'company_phone' => '02112345678',
            'company_email' => 'company@example.test',
            'company_address' => 'Company address',
            'company_national_id' => 'test-company-id',
        ];
    }

    public function test_it_posts_all_user_fields_as_json_with_the_shared_api_key(): void
    {
        $data = $this->userData();
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($data);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://panel.test/register'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'panel-key')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('Accept', 'application/json')
            && $request->data() === $data);
    }

    public function test_it_returns_the_response_object_and_complete_api_payload(): void
    {
        $body = [
            'success' => true,
            'message' => 'User registered',
            'data' => ['id' => 7, 'phone' => '09131234567'],
        ];
        Http::fake(['*' => Http::response($body, 201)]);

        $result = Panel::register($this->userData());

        $this->assertInstanceOf(UserRegisterResponse::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertSame('User registered', $result->message());
        $this->assertSame($body, $result->toArray());
    }

    public function test_it_accepts_an_individual_payload_without_company_fields(): void
    {
        $data = [
            'phone' => '09131234567',
            'type' => 'individual-test-value',
            'national_id' => 'test-national-id',
        ];
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        $this->assertTrue(Panel::register($data)->isSuccessful());

        Http::assertSent(fn (Request $request) => $request->data() === $data);
    }

    public function test_it_preserves_nullable_values_and_omitted_optional_fields(): void
    {
        $data = ['phone' => '09131234567', 'type' => 'individual-test-value', 'email' => null];
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($data);

        Http::assertSent(fn (Request $request) => $request->data() === $data
            && array_key_exists('email', $request->data())
            && ! array_key_exists('company_name', $request->data()));
    }

    public function test_it_forwards_data_without_local_validation_or_phone_normalization(): void
    {
        $data = ['phone' => '+98 913 123 4567', 'type' => 'application-defined-type'];
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($data);

        Http::assertSent(fn (Request $request) => $request->data() === $data);
    }

    public function test_it_forwards_an_empty_payload_for_the_api_to_validate(): void
    {
        Http::fake(['*' => Http::response(['message' => 'The phone field is required.'], 422)]);

        try {
            Panel::register([]);
            $this->fail('Expected the API validation error.');
        } catch (SmsException $e) {
            $this->assertStringContainsString('HTTP 422', $e->getMessage());
        }

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->data() === []);
    }

    public function test_it_supports_runtime_connection_overrides_without_changing_config(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($this->userData(), [
            'base_url' => 'https://customer.test',
            'endpoint' => '/users',
            'api_key' => 'customer-key',
        ]);
        Panel::register($this->userData());

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://customer.test/users'
            && $request->hasHeader('Authorization', 'customer-key'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://panel.test/register'
            && $request->hasHeader('Authorization', 'panel-key'));
        $this->assertSame('panel-key', config('sms.drivers.panel.api_key'));
        $this->assertSame('/register', config('sms.registration.endpoint'));
    }

    public function test_partial_overrides_keep_the_other_shared_settings(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($this->userData(), ['api_key' => 'customer-key']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://panel.test/register'
            && $request->hasHeader('Authorization', 'customer-key'));
    }

    public function test_registration_key_takes_priority_while_the_base_url_stays_shared(): void
    {
        config()->set('sms.registration.api_key', 'registration-key');
        config()->set('sms.registration.base_url', 'https://unused.test');
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($this->userData());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://panel.test/register'
            && $request->hasHeader('Authorization', 'registration-key'));
    }

    public function test_a_runtime_key_overrides_the_registration_key_for_one_call_only(): void
    {
        config()->set('sms.registration.api_key', 'registration-key');
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($this->userData(), ['api_key' => 'runtime-key']);
        Panel::register($this->userData());

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'runtime-key'));
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'registration-key'));
        $this->assertSame('registration-key', config('sms.registration.api_key'));
        $this->assertSame('panel-key', config('sms.drivers.panel.api_key'));
    }

    public function test_a_null_registration_key_falls_back_to_the_sms_panel_key(): void
    {
        config()->set('sms.registration.api_key', null);
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($this->userData());

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'panel-key'));
    }

    public function test_a_registration_key_works_without_an_sms_panel_key(): void
    {
        config()->set('sms.drivers.panel.api_key', null);
        config()->set('sms.registration.api_key', 'registration-key');
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        $this->assertTrue(Panel::register($this->userData())->isSuccessful());

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'registration-key'));
    }

    public function test_missing_registration_and_sms_keys_fail_before_sending(): void
    {
        config()->set('sms.drivers.panel.api_key', null);
        config()->set('sms.registration.api_key', null);
        Http::fake();

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('api_key');

        try {
            Panel::register($this->userData());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_null_runtime_key_does_not_silently_use_a_configured_key(): void
    {
        config()->set('sms.registration.api_key', 'registration-key');
        Http::fake();

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('api_key');

        try {
            Panel::register($this->userData(), ['api_key' => null]);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_always_uses_post_even_with_method_overrides(): void
    {
        config()->set('sms.registration.method', 'GET');
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        Panel::register($this->userData(), ['method' => 'PATCH']);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_it_does_not_require_an_sms_line_or_resolve_the_sms_driver(): void
    {
        config()->set('sms.drivers.panel.line_number', null);
        config()->set('sms.default', 'unavailable-driver');
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        $this->assertTrue(Panel::register($this->userData())->isSuccessful());
        $this->assertFalse($this->app->resolved('sms'));
        Http::assertSentCount(1);
    }

    public function test_it_rejects_each_missing_setting_before_sending_a_request(): void
    {
        Http::fake();

        foreach (['base_url', 'endpoint', 'api_key'] as $missing) {
            try {
                Panel::register($this->userData(), [$missing => null]);
                $this->fail("Expected SmsException for missing {$missing}.");
            } catch (SmsException $e) {
                $this->assertStringContainsString($missing, $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_it_rejects_a_missing_configured_endpoint(): void
    {
        config()->set('sms.registration.endpoint', null);
        Http::fake();

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('endpoint');

        try {
            Panel::register($this->userData());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_throws_for_authentication_validation_and_server_errors(): void
    {
        $statuses = [401, 403, 422, 500, 503];
        $sequence = Http::sequence();
        foreach ($statuses as $status) {
            $sequence->push(['message' => 'Request rejected'], $status);
        }
        Http::fake(['*' => $sequence]);

        foreach ($statuses as $status) {
            try {
                Panel::register($this->userData());
                $this->fail("Expected SmsException for HTTP {$status}.");
            } catch (SmsException $e) {
                $this->assertStringContainsString("HTTP {$status}", $e->getMessage());
                $this->assertStringContainsString('Request rejected', $e->getMessage());
            }
        }
    }

    public function test_it_throws_when_the_api_reports_failure_with_http_200(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Duplicate user'], 200)]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Duplicate user');

        Panel::register($this->userData());
    }

    public function test_it_rejects_invalid_json_and_non_array_json_responses(): void
    {
        $bodies = ['invalid JSON', 'null', '"text"', 'true', '123', ''];
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body, 200);
        }
        Http::fake(['*' => $sequence]);

        foreach ($bodies as $body) {
            try {
                Panel::register($this->userData());
                $this->fail('Expected SmsException for an invalid response.');
            } catch (SmsException $e) {
                $this->assertStringContainsString('Registration request failed', $e->getMessage());
            }
        }
    }

    public function test_http_errors_without_a_message_include_the_response_body(): void
    {
        Http::fake(['*' => Http::response('Service unavailable', 503)]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Service unavailable');

        Panel::register($this->userData());
    }

    public function test_structured_error_messages_are_reported_without_a_type_error(): void
    {
        Http::fake(['*' => Http::response(['message' => ['phone' => ['Invalid number']]], 422)]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Invalid number');

        Panel::register($this->userData());
    }

    public function test_connection_errors_keep_the_original_exception(): void
    {
        $connection = new ConnectionException('Connection timed out');
        Http::fake(function () use ($connection) {
            throw $connection;
        });

        try {
            Panel::register($this->userData());
            $this->fail('Expected SmsException for a connection failure.');
        } catch (SmsException $e) {
            $this->assertStringContainsString('Connection timed out', $e->getMessage());
            $this->assertSame($connection, $e->getPrevious());
        }
    }

    public function test_missing_success_and_message_fields_use_the_documented_defaults(): void
    {
        $body = ['data' => ['id' => 7]];
        Http::fake(['*' => Http::response($body, 201)]);

        $result = Panel::register($this->userData());

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->message());
        $this->assertSame($body, $result->toArray());
    }

    public function test_the_legacy_facade_returns_the_same_response_type(): void
    {
        $body = ['success' => true, 'message' => 'Registered', 'data' => ['id' => 7]];
        Http::fake(['*' => Http::response($body, 201)]);

        $result = UserRegister::register($this->userData());

        $this->assertInstanceOf(UserRegisterResponse::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertSame($body, $result->toArray());
    }

    public function test_the_panel_alias_is_declared_for_laravel_package_discovery(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

        $this->assertSame(Panel::class, $composer['extra']['laravel']['aliases']['Panel']);
    }
}
