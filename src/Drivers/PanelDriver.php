<?php

namespace AlirezaMalekei\PeykadSms\Drivers;

use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use AlirezaMalekei\PeykadSms\Contracts\SmsDriver;
use AlirezaMalekei\PeykadSms\Exceptions\SmsException;
use AlirezaMalekei\PeykadSms\Responses\SmsResponse;

class PanelDriver implements SmsDriver
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $endpoint,
        private readonly string $apiKey,
        private readonly ?string $lineNumber = null
    ) {}

    /**
     * @throws ConnectionException
     */
    public function send(
        string|array $to,
        string $text,
        array $options = []
    ): SmsResponse
    {
        $phones = implode(',', $this->parsePhones($to));

        $lineNumber = $options['line_number'] ?? $this->lineNumber;

        if (empty($lineNumber)) {
            throw new SmsException(
                'شماره خط مشخص نشده است. آن را در options با کلید line_number بدهید '
                . 'یا SMS_PANEL_LINE را در .env تنظیم کنید.'
            );
        }

        $scheduledAt = $options['scheduled_at'] ?? null;
        if ($scheduledAt instanceof DateTimeInterface) {
            $scheduledAt = $scheduledAt->format('Y-m-d H:i:s');
        }

        $payload = [
            'name'         => $options['name'] ?? 'کارزار ارسال اس.ام.اس از طریق ای.پی.آی',
            'text'         => $text,
            'phones'       => $phones,
            'line_number'  => $options['line_number'] ?? $this->lineNumber,
            'scheduled_at' => $scheduledAt,
        ];

        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders(['Authorization' => $this->apiKey])
            ->acceptJson()
            ->asJson()
            ->post($this->endpoint, $payload);

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->failed() || ($data['success'] ?? false) !== true) {
            $reason = $data['message'] ?? $response->body();

            throw new SmsException(
                "ارسال پیامک ناموفق بود (HTTP {$response->status()}): {$reason}"
            );
        }

        return SmsResponse::fromArray($data);
    }

    /**
     * Accepts a single number, a comma-separated string ("913..., 920..."),
     * a newline-separated string, or an array (whose items may themselves
     * be comma-separated strings).
     */
    private function parsePhones(string|array $to): array
    {
        $numbers = [];

        foreach ((array) $to as $item) {
            foreach (preg_split('/[,\r\n]+/', (string) $item, -1, PREG_SPLIT_NO_EMPTY) as $number) {
                $normalized = $this->normalize($number);

                if ($normalized !== '') {
                    $numbers[] = $normalized;
                }
            }
        }

        return $numbers;
    }

    public function normalize(string $value): ?string
    {
        $value = trim($value);
        $value = str_replace(' ', '', $value);

        return preg_replace('/^(?:\+989|989|09)/', '9', $value);
    }
}
