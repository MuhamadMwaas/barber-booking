<?php

namespace App\Services;

use Illuminate\Support\Facades\App;

class NotificationTranslator
{
    public function translate(string $key, array $params = []): string
    {
        $locale = App::getLocale();

        $resolved = [];

        foreach ($params as $param => $config) {
            if ($config['type'] === 'translate') {
                $resolved[$param] = __($config['value'], [], $locale);
            } else {
                $resolved[$param] = $config['value'];
            }
        }

        return __($key, $resolved, $locale);
    }
}
