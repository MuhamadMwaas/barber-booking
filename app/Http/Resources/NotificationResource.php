<?php

namespace App\Http\Resources;

use App\Services\NotificationService;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var NotificationService $notificationService */
        $notificationService = app(NotificationService::class);

        $locale = app()->getLocale();

        $params = $this->data['params'] ?? [];

        return [
            'id' => $this->id,

            'title' => $notificationService->translateKey(
                $this->data['title_key'],
                $params,
                $locale
            ),

            'message' => $notificationService->translateKey(
                $this->data['message_key'],
                $params,
                $locale
            ),

            'data' => $this->data['data'] ?? [],
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
