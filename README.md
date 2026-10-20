# Peykad SMS

Send SMS in Laravel with support for multiple drivers. Send single, bulk and scheduled messages, and check your wallet with the same API key.

## Contents

- [Compatibility](#compatibility)
- [Installation](#installation)
- [Getting an API Key](#getting-an-api-key)
- [Setting the API Key](#setting-the-api-key)
- [Usage](#usage)
- [The `send` Response](#the-send-response)
- [Getting the Wallet](#getting-the-wallet)
- [Registering Users](#registering-users)
- [Adding a Custom Driver](#adding-a-custom-driver)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [License](#license)

## Compatibility

| Laravel | PHP       |
|---------|-----------|
| 10.x    | 8.1 - 8.4 |
| 11.x    | 8.2+      |
| 12.x    | 8.2+      |

## Installation

```bash
composer require alireza_malekei/peykad-sms
php artisan vendor:publish --tag=sms-config
```

## Getting an API Key

Each developer must obtain their own API key and line number from their account panel at the service provider. This package does not ship with any default key.

The key is sent in the `Authorization` header of every request.

## Setting the API Key

You can provide the key in one of three ways.

### 1. Environment file (recommended)

Add the key to your `.env` file:

```dotenv
SMS_DRIVER=panel
SMS_PANEL_URL=https://api.example.com
SMS_PANEL_ENDPOINT=/api/v1/send
SMS_PANEL_API_KEY=your-api-key
SMS_PANEL_LINE=1000002121
```

If your configuration is cached, clear it after changing `.env`:

```bash
php artisan config:clear
```

### 2. Config file

Publish the config file and edit `config/sms.php` in your project:

```bash
php artisan vendor:publish --tag=sms-config
```

```php
'drivers' => [
    'panel' => [
        'api_key' => env('SMS_PANEL_API_KEY'),
    ],
],
```

Keep reading the key with `env()` instead of hard-coding it, because the config file is committed to Git.

### 3. At runtime

If your application uses multiple keys (for example, one per customer), pass the key when sending:

```php
Sms::panel([
    'api_key'     => $customer->sms_api_key,
    'line_number' => $customer->sms_line,
])->send('09131234567', 'Hello');
```

Values passed at runtime take priority over `.env` and config values. Any option you omit falls back to the configured value.

### Priority

1. Values passed at runtime with `Sms::panel([...])`
2. Values in your published `config/sms.php`
3. Values in `.env`

If `SMS_PANEL_API_KEY` or `SMS_PANEL_URL` is missing, an `SmsException` is thrown before any request is sent.

> **Warning:** Never hard-code the key or commit it to Git, and do not commit your `.env` file.

## Usage

```php
use AlirezaMalekei\PeykadSms\Facades\Sms;

// Simple send
Sms::send('09138332910', 'Hello');

// Bulk send + scheduling + campaign name + custom line number
Sms::send(
    ['09138332910', '09208332910'],
    'Hello',
    [
        'name'         => 'Test campaign',  // optional
        'line_number'  => '1000003030',     // optional, falls back to SMS_PANEL_LINE
        'scheduled_at' => now()->addHour(), // optional
    ]
);

// Custom line number (falls back to SMS_PANEL_LINE when omitted)
Sms::send('09138332910', 'Hello', ['line_number' => '1000003030']);

// Choose a driver
Sms::driver('panel')->send('09138332910', 'Hi');
```

## The `send` Response

```php
$result = Sms::send('09138332910', 'Hello');

$result->isSuccessful();  // true
$result->message();       // 'success'
$result->trackingCode();  // tracking code
$result->toArray();       // raw API response
```

If sending fails (an HTTP error, or `success` is `false`), an `SmsException` is thrown.

## Getting the Wallet

Check how much credit your account has left. The wallet uses a separate `WalletDriver`, service, config block and facade.

Wallet requests always use **GET with no payload** and share `SMS_PANEL_URL` and `SMS_PANEL_API_KEY` with SMS sending. The only required wallet setting is the endpoint:

```dotenv
SMS_WALLET_ENDPOINT=/api/v1/wallet
```

### Usage

```php
use AlirezaMalekei\PeykadSms\Facades\Wallet;

$wallet = Wallet::get();

// Use a specific key, for example one per customer
$wallet = Wallet::get(['api_key' => $customer->sms_api_key]);

$wallet->balance();              // 50000, or null if the balance field is missing
$wallet->toArray();              // the wallet record
$wallet->get('panel.name');      // a single field (dot notation supported)
$wallet->raw();                  // raw API response (includes success, message, data)
```

An `SmsException` is thrown if the endpoint is not configured, the request fails or times out, the API returns `success: false`, or the wallet record is not found in the response.

If a class also imports your own `Wallet` model, alias one of them:

```php
use AlirezaMalekei\PeykadSms\Facades\Wallet as SmsWallet;
```

## Registering Users

Use `Panel::register($data)` to register a user through your panel API. Registration always sends a JSON **POST** request and shares `SMS_PANEL_URL` with SMS sending. Set `USER_REGISTRATION_API_KEY` to use a separate registration key; if it is not set, registration falls back to `SMS_PANEL_API_KEY`. The selected key is sent in the `Authorization` header.

### Configuration

Set the registration endpoint and, optionally, a separate API key in your application's `.env`:

```dotenv
PANEL_REGISTRATION_ENDPOINT=/your-registration-endpoint
# Optional: omit this line to use SMS_PANEL_API_KEY
USER_REGISTRATION_API_KEY=your-registration-api-key
```

Replace the placeholder with your actual API route. Your published `config/sms.php` must include:

```php
'registration' => [
    'endpoint' => env('PANEL_REGISTRATION_ENDPOINT'),
    'api_key' => env('USER_REGISTRATION_API_KEY', env('SMS_PANEL_API_KEY')),
],
```

If the config was published before registration was added, add this block to it. The registration key is optional; no HTTP method setting is needed. Key priority is: runtime `api_key` override, configured registration key, then the SMS panel key. The fallback reads the value of `SMS_PANEL_API_KEY`, rather than using its name as a literal key. If your configuration is cached, clear or rebuild it after updating these settings.

### Usage

Pass a user data array to the facade. Validation stays in your project; the package forwards the data without local validation or phone normalization.

```php
use AlirezaMalekei\PeykadSms\Facades\Panel;

// Values below come from data validated in your project.
// Include fields relevant to the selected user type; omit unused fields.
$result = Panel::register([
    'first_name' => $firstName,
    'last_name' => $lastName,
    'phone' => $phone,
    'email' => $email,
    'address' => $address,
    'national_id' => $nationalId,             // Required for individual users
    'type' => $type,                         // Your API's UserTypeEnum value
    'company_name' => $companyName,          // Required for legal users
    'company_phone' => $companyPhone,
    'company_email' => $companyEmail,
    'company_address' => $companyAddress,
    'company_national_id' => $companyNationalId, // Required for legal users
]);
```

### Registration fields

The table below documents the API's validation rules. Your project validates the data before calling `Panel::register()`; this package only sends the supplied data.

| Field | Required | Accepted value |
| --- | --- | --- |
| `first_name` | No | String or `null`; may be omitted. |
| `last_name` | No | String or `null`; may be omitted. |
| `phone` | Yes | String accepted by `ValidPhoneRule`, using `PhonePrefixEnum::fromNumber()` for the prefix. |
| `email` | No | Valid email address or `null`; may be omitted. |
| `address` | No | String or `null`; may be omitted. |
| `national_id` | For `UserTypeEnum::Individual` | Valid Iranian national code (`ir_national_code`). Nullable when not required. |
| `type` | Yes | A valid backing value of your API's `UserTypeEnum`. |
| `company_name` | For `UserTypeEnum::Legal` | String when supplied. |
| `company_phone` | No | Mobile number accepted by `ValidPhoneRule` **or** landline accepted by `ir_phone_area`; may be omitted or `null`. |
| `company_email` | No | Valid email address or `null`; may be omitted. |
| `company_address` | No | String or `null`; may be omitted. |
| `company_national_id` | For `UserTypeEnum::Legal` | Valid Iranian company ID (`ir_company_id`) when supplied. |

Use the actual `Individual` or `Legal` backing value defined by your API for `type`. These enums and custom validation rules belong to your application/API; the package does not provide or execute them. For optional fields without a nullable rule (`company_name` and `company_national_id`), omit unused fields rather than sending `null`.

You can also pass the validated data from your own Form Request:

```php
$result = Panel::register($request->validated());
```

### Registration response

Like `Sms::send()`, registration returns a response object. `UserRegisterResponse` provides:

```php
$result->isSuccessful(); // API success flag (false if absent)
$result->message();      // API message, or null
$result->toArray();      // Complete decoded JSON response, including user data
```

Registration previously returned an array. Access the raw response through `$result->toArray()` when updating existing callers. The `UserRegister::register()` facade returns the same response object.

### Runtime overrides

To override the registration endpoint, base URL or selected API key for one request:

```php
$result = Panel::register($data, [
    'endpoint' => '/your-registration-endpoint',
    'api_key' => $customer->registration_api_key,
]);
```

Supported overrides are `endpoint`, `base_url` and `api_key`. Omitted values use the configured settings; requests always use POST.

### Errors

When the registration API returns HTTP **422** with a JSON object or array, the package throws Laravel's `HttpResponseException` containing the original response body and status. Laravel returns the API's message and field errors as HTTP 422 to your caller. Let this exception reach Laravel's exception handler; a broad `catch (\Throwable $e)` that converts every exception to HTTP 500 would override this behavior.

Missing settings, connection failures, other HTTP errors, invalid JSON response shapes (including a non-JSON 422 response), and other API responses containing `success: false` throw `SmsException`:

```php
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;

try {
    $result = Panel::register($data);
} catch (SmsException $e) {
    report($e);
}
```

## Adding a Custom Driver

Register your driver with `extend`:

```php
Sms::extend('mygateway', fn () => new MyGatewayDriver());
```

Your class must implement `AlirezaMalekei\PeykadSms\Contracts\SmsDriver`:

```php
use AlirezaMalekei\PeykadSms\Contracts\SmsDriver;
use AlirezaMalekei\PeykadSms\Responses\SmsResponse;

class MyGatewayDriver implements SmsDriver
{
    public function send(string|array $to, string $text, array $options = []): SmsResponse
    {
        // call your gateway here

        return new SmsResponse(true, 'sent', 'TRACKING-CODE');
    }
}
```

Custom drivers do not need anything for the wallet. `Wallet::get()` does not use drivers.

## Error Handling

```php
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use Illuminate\Http\Client\ConnectionException;

try {
    Sms::send('09138332910', 'Hello');
} catch (SmsException $e) {
    // missing configuration, HTTP error, or success was false
    report($e);
} catch (ConnectionException $e) {
    // the panel could not be reached (DNS, timeout, refused connection)
    report($e);
}
```

`Wallet::get()` converts connection problems into `SmsException`, so it only needs the first `catch`.

If you send from a queued job, set a job timeout so a slow provider cannot block your workers.

## Testing

```bash
composer install
composer test
```

In your own application, set `SMS_DRIVER=log` locally so no real messages are sent, or fake the HTTP calls:

```php
Http::fake([
    '*' => Http::response([
        'success'       => true,
        'message'       => 'success',
        'tracking_code' => 'ABC123',
    ], 200),
]);
```

## License

MIT