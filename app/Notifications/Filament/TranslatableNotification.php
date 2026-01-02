<?php

namespace App\Notifications\Filament;

use App\Services\NotificationTranslator;
use Filament\Notifications\Notification as BaseNotification;

class TranslatableNotification extends BaseNotification
{
    protected ?string $titleKey = null;
    protected ?string $messageKey = null;

    protected array $params = [];
    protected array $payload = [];


    public function titleKey(string $key): static
    {
        $this->titleKey = $key;
        $this->title($key);
        return $this;
    }

    public function messageKey(string $key): static
    {
        $this->messageKey = $key;
        $this->body($key); // fallback
        return $this;
    }

    public function params(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    public function payload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }



    public function getTitle(): ?string
    {
        if ($this->titleKey) {
            return app(NotificationTranslator::class)
                ->translate($this->titleKey, $this->params);
        }

        return parent::getTitle();
    }

    public function getBody(): ?string
    {
        if ($this->messageKey) {
            return app(NotificationTranslator::class)
                ->translate($this->messageKey, $this->params);
        }

        return parent::getBody();
    }

    /* =========================
     |  Database Serialization
     ========================= */

    public function toArray(): array
    {
        return [
            ...parent::toArray(),

            // نفس البنية التي تستخدمها ToAdminNotification
            'title_key'   => $this->titleKey,
            'message_key' => $this->messageKey,
            'params'      => $this->params,
            'data'        => $this->payload,
        ];
    }

    public static function fromArray(array $data): static
    {
        /** @var static $notification */
        $notification = parent::fromArray($data);

        if (! empty($data['title_key'])) {
            $notification->titleKey($data['title_key']);
        }

        if (! empty($data['message_key'])) {
            $notification->messageKey($data['message_key']);
        }

        if (! empty($data['params'])) {
            $notification->params($data['params']);
        }

        if (! empty($data['data'])) {
            $notification->payload($data['data']);
        }

        return $notification;
    }
}
