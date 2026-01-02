<?php

namespace App\Notifications\Params;

use App\Notifications\contract\NotificationParamResolver;

class ValueParamResolver implements NotificationParamResolver
{
    public function resolve(array $data, string $locale, array $config = []): mixed
    {
        return $data['value']?? null;
    }
}
