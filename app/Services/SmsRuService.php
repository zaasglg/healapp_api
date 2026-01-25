<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsRuService
{
    protected string $baseUrl = 'https://sms.ru/sms/send';
    protected string $apiId;

    public function __construct()
    {
        $this->apiId = env('SMSRU_API_ID');
    }

    /**
     * Отправить SMS сообщение
     *
     * @param string $phone Номер телефона
     * @param string $message Текст сообщения
     * @return array
     */
    public function send(string $phone, string $message): array
    {
        if (app()->environment('local', 'testing') && !config('app.sms_enabled', false)) {
            Log::info("SMS Mock: To {$phone}, Msg: {$message}");
            return ['status' => 'OK', 'status_code' => 100, 'mock' => true];
        }

        try {
            $response = Http::get($this->baseUrl, [
                'api_id' => $this->apiId,
                'to' => $phone,
                'msg' => $message,
                'json' => 1,
                'test' => 1
            ]);

            $result = $response->json();

            if (isset($result['status']) && $result['status'] === 'OK') {
                Log::info("SMS sent successfully to {$phone}. Content: {$message}");
                return $result;
            } else {
                Log::error('SMS.ru error', ['response' => $result]);
                return $result;
            }
        } catch (\Exception $e) {
            Log::error('SMS.ru exception', ['message' => $e->getMessage()]);
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
}
