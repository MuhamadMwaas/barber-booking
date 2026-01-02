<?php

namespace App\Notifications\Params;

use App\Notifications\contract\NotificationParamResolver;

class TranslateParamResolver implements NotificationParamResolver
{
    public function resolve(mixed $data, string $locale, array $config = []): mixed
    {
        if (!is_string($data['value'])) {
            return $data['value'];
        }

        return __($data['value'], $config , $locale);
    }
}
