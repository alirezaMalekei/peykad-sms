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
composer require alireza_malekei/peykad-sms:^2
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

### Optional settings

| Variable                     | Example value | Description                                                                 |
|------------------------------|---------------|-----------------------------------------------------------------------------|
| `SMS_WALLET_KEY`             | `data`        | Where the wallet record is in the JSON response. Empty means the whole response. |
| `SMS_WALLET_BALANCE_KEY`     | `balance`     | Balance field inside the record. Dot notation is supported.                 |

### Usage

```php
use AlirezaMalekei\PeykadSms\Facades\Wallet;

$wallet = Wallet::get();

// Use a specific key, for example one per customer
$wallet = Wallet::get(['api_key' => $customer->sms_api_key]);

$wallet->balance();        // 125000, or null if the balance field is missing
$wallet->toArray();        // the wallet record
$wallet->get('currency');  // a single field (dot notation supported)
$wallet->raw();            // raw API response
```

An `SmsException` is thrown if the endpoint is not configured, the request fails or times out, the API returns `success: false`, or the wallet record is not found in the response.

If a class also imports your own `Wallet` model, alias one of them:

```php
use AlirezaMalekei\PeykadSms\Facades\Wallet as SmsWallet;
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