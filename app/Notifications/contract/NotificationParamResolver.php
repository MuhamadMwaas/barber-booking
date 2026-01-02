<?php

namespace App\Notifications\contract;

interface NotificationParamResolver
{
    public function resolve(
        array $data,
        string $locale,
        array $config = []
    ): mixed;
}
