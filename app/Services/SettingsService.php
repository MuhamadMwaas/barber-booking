<?php
namespace App\Services;


class SettingsService
{



    public static function get($key, $default = null)
    {

        return get_setting($key, $default);

    }
}
