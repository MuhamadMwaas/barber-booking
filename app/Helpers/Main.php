<?php
namespace App\Helpers;

use App\Models\SalonSetting;

if (!function_exists('get_setting')) {

    function get_setting($key, $default = null)
    {
        $setting_record = SalonSetting::where('key', $key)->first();
        if ($setting_record) {
            return $setting_record->value;
        }
        return $default;
    }
}
