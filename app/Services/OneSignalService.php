<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OneSignalService
{
    protected string $appId;
    protected string $restApiKey;
    protected string $baseUrl;

    protected string $appName;


    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id');
        $this->restApiKey = config('services.onesignal.rest_api_key');
        $this->baseUrl = rtrim(config('services.onesignal.base_url', 'https://onesignal.com'), '/');
        $this->appName = config('app.name', 'Laravel');

    }

    /**
     * إعداد الـ Client مع الهيدرز المطلوبة
     */
    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => 'Basic ' . $this->restApiKey, // مفتاح REST
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'OneSignal-Usage' => env('APP_NAME') . '| Laravel Backend',
        ]);
    }

    /**
     * دالة عامة لإرسال الطلب للـ OneSignal
     */
    protected function postNotification(array $payload): array
    {
        try {
            $url = $this->baseUrl . '/api/v1/notifications';

            $response = $this->client()->post($url, $payload);

            if (!$response->successful()) {
                Log::error('OneSignal API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ];
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::error('OneSignal exception', ['exception' => $e]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function normalizeLocalizedField(string|array $value): array
    {
        if (is_array($value)) {
            return array_filter(
                $value,
                static fn ($translation, $locale) => is_string($locale) && is_string($translation) && $translation !== '',
                ARRAY_FILTER_USE_BOTH
            );
        }

        return ['en' => $value];
    }

    /**
     * إرسال إشعار لكل الأجهزة (كل المشتركين)
     */
    public function sendToAll(string|array $title, string|array $body, array $data = []): array
    {
        $payload = [
            'app_id' => $this->appId,
            'included_segments' => ['All'],
            'headings' => $this->normalizeLocalizedField($title),
            'contents' => $this->normalizeLocalizedField($body),
        ];
        if (!empty($data)) {
            $payload['data'] = $data;
        }
        return $this->postNotification($payload);
    }

    /**
     * إرسال إشعار لجهاز واحد عن طريق player_id
     */
    public function sendToDevice(string $playerId, string|array $title, string|array $body, array $data = []): array
    {
        $payload = [
            'app_id' => $this->appId,
            'include_player_ids' => [$playerId],
            'headings' => $this->normalizeLocalizedField($title),
            'contents' => $this->normalizeLocalizedField($body),
        ];
        if (!empty($data)) {
            $payload['data'] = $data;
        }
        return $this->postNotification($payload);
    }

    /**
     * إرسال إشعار لمجموعة أجهزة (أكثر من player_id)
     */
    public function sendToMany(array $playerIds, string|array $title, string|array $body, array $data = []): array
    {
        $playerIds = array_filter($playerIds, fn($id) => !empty($id));

        $payload = [
            'app_id' => $this->appId,
            'include_player_ids' => $playerIds,
            'headings' => $this->normalizeLocalizedField($title),
            'contents' => $this->normalizeLocalizedField($body),
            'data' => $data,
        ];

        return $this->postNotification($payload);
    }
}
