<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QiscusWaService
{
    protected $appId;
    protected $secretKey;
    protected $channelId;
    protected $baseUrl;

    public function __construct()
    {
        $this->appId = config('services.qiscus.app_id');
        $this->secretKey = config('services.qiscus.secret_key');
        $this->channelId = config('services.qiscus.channel_id');
        $this->baseUrl = rtrim(config('services.qiscus.base_url', 'https://multichannel.qiscus.com'), '/');
    }

    /**
     * Format phone number to international code (e.g. 0812 -> 62812)
     */
    public function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * Send WhatsApp Template Notification
     */
    public function sendTemplateNotification($to, $templateName, $parameters = [])
    {
        if (empty($this->appId) || empty($this->secretKey)) {
            Log::warning("Qiscus WA Service: Credentials not configured.");
            return [
                'success' => false,
                'message' => 'Credentials not configured'
            ];
        }

        $formattedPhone = $this->formatPhoneNumber($to);
        $url = "{$this->baseUrl}/api/v2/channels/templates/send";

        $bodyParameters = [];
        foreach ($parameters as $param) {
            $bodyParameters[] = [
                'type' => 'text',
                'text' => (string) $param
            ];
        }

        $payload = [
            'to' => $formattedPhone,
            'channel_id' => (int) $this->channelId,
            'template_name' => $templateName,
            'language' => [
                'code' => 'id'
            ],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => $bodyParameters
                ]
            ]
        ];

        try {
            Log::info("Qiscus WA Service: Sending template '{$templateName}' to {$formattedPhone}", $payload);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Qiscus-App-Id' => $this->appId,
                'Qiscus-Secret-Key' => $this->secretKey
            ])->post($url, $payload);

            $resBody = $response->json();
            Log::info("Qiscus WA Service Response: ", ['status' => $response->status(), 'body' => $resBody]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $resBody
                ];
            }

            return [
                'success' => false,
                'message' => $resBody['error']['message'] ?? 'API request failed'
            ];

        } catch (\Exception $e) {
            Log::error("Qiscus WA Service Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send Plain Text Message (Fallback/Testing helper if session is active)
     */
    public function sendPlainMessage($to, $messageText)
    {
        if (empty($this->appId) || empty($this->secretKey)) {
            Log::warning("Qiscus WA Service: Credentials not configured.");
            return [
                'success' => false,
                'message' => 'Credentials not configured'
            ];
        }

        $formattedPhone = $this->formatPhoneNumber($to);
        $url = "{$this->baseUrl}/api/v1/chat/message";

        $payload = [
            'to' => $formattedPhone,
            'channel_id' => (int) $this->channelId,
            'message' => $messageText
        ];

        try {
            Log::info("Qiscus WA Service: Sending plain message to {$formattedPhone}");

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Qiscus-App-Id' => $this->appId,
                'Qiscus-Secret-Key' => $this->secretKey
            ])->post($url, $payload);

            $resBody = $response->json();
            Log::info("Qiscus WA Service Response: ", ['status' => $response->status(), 'body' => $resBody]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $resBody
                ];
            }

            return [
                'success' => false,
                'message' => $resBody['error']['message'] ?? 'API request failed'
            ];

        } catch (\Exception $e) {
            Log::error("Qiscus WA Service Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
