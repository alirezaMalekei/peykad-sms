# Peykad SMS

Send SMS in Laravel with support for multiple drivers.

## Compatibility

| Laravel | PHP       |
|---------|-----------|
| 10.x    | 8.1 - 8.3 |
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

```
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

**Warning:** Never hard-code the key or commit it to Git, and do not commit your `.env` file.
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

The `send` method returns an `SmsResponse` object:

```php
$result = Sms::send('09138332910', 'Hello');

$result->isSuccessful();  // true
$result->message();       // 'success'
$result->trackingCode();  // tracking code
$result->toArray();       // raw API response
```

If sending fails (an HTTP error, or `success` is `false`), an `SmsException` is thrown.

## Using a Different API Key at Runtime

If your project uses multiple keys (for example, one per customer), you can pass the values at send time:

```php
Sms::panel([
    'api_key'     => $customer->sms_api_key,
    'line_number' => $customer->sms_line,
])->send('09138332910', 'Hello');
```

**Warning:** Never hard-code the key or commit it to Git, and do not commit your `.env` file.

## Adding a Custom Driver

```php
Sms::extend('mygateway', fn () => new MyGatewayDriver());
```

Your class must implement `AlirezaMalekei\PeykadSms\Contracts\SmsDriver`.

## Error Handling

```php
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;

try {
    Sms::send('09138332910', 'Hello');
} catch (SmsException $e) {
    report($e);
}
```

## Testing

```bash
composer install
composer test
```

## License

MIT