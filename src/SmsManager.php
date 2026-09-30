<?php

namespace AlirezaMalekei\PeykadSms;

use Illuminate\Support\Manager;
use AlirezaMalekei\PeykadSms\Drivers\LogDriver;
use AlirezaMalekei\PeykadSms\Drivers\PanelDriver;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;

class SmsManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('sms.default', 'log');
    }

    protected function createPanelDriver(): PanelDriver
    {
        return $this->makePanelDriver($this->config->get('sms.drivers.panel', []));
    }

    protected function createLogDriver(): LogDriver
    {
        return new LogDriver();
    }

    /**
     * ساخت درایور پنل با تنظیمات دلخواه در زمان اجرا.
     * مقادیر داده‌شده روی تنظیمات فایل کانفیگ اولویت دارند.
     *
     * مثال: Sms::panel(['api_key' => $customer->sms_key, 'line_number' => $customer->line])
     */
    public function panel(array $overrides = []): PanelDriver
    {
        $config = array_merge($this->config->get('sms.drivers.panel', []), $overrides);

        return $this->makePanelDriver($config);
    }

    private function makePanelDriver(array $c): PanelDriver
    {
        foreach (['base_url', 'api_key', 'line_number'] as $key) {
            if (empty($c[$key])) {
                throw new SmsException(
                    "تنظیم «{$key}» برای درایور panel مقداردهی نشده است. "
                    . 'آن را در فایل .env یا config/sms.php وارد کنید.'
                );
            }
        }

        return new PanelDriver(
            baseUrl:    $c['base_url'],
            endpoint:   $c['endpoint'] ?? '/',
            apiKey:     $c['api_key'],
            lineNumber: $c['line_number'],
        );
    }
}
