<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;

/**
 * Single entry point for reading and writing user-facing application options.
 *
 * Reads merge the per-user override (UserSetting) with the option's default
 * (AppSetting.default_value). Writes are validated against the rule string that
 * lives ON the AppSetting row (so adding/changing an option never requires code
 * changes), then stored as a typed value.
 */
class UserSettingService
{
    /**
     * Full options catalog for the settings screen: every active option together
     * with the value currently in effect for this user (override or default).
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(User $user): array
    {
        $definitions = AppSetting::active()->orderBy('sort_order')->get();
        $overrides = UserSetting::where('user_id', $user->id)->get()->keyBy('key');
        $locale = $user->locale ?? app()->getLocale();

        return $definitions->map(function (AppSetting $definition) use ($overrides, $locale) {
            $hasOverride = $overrides->has($definition->key);
            $raw = $hasOverride ? $overrides->get($definition->key)->value : $definition->default_value;

            return [
                'key' => $definition->key,
                'label' => $definition->label($locale),
                'description' => $definition->description($locale),
                'type' => $definition->type,
                'group' => $definition->group,
                'value' => $this->castToType($raw, $definition->type),
                'is_default' => ! $hasOverride,
            ];
        })->values()->all();
    }

    /**
     * The value currently in effect for $user / $key (override, else default).
     * Returns null when the option key does not exist.
     */
    public function get(User $user, string $key): mixed
    {
        $definition = AppSetting::where('key', $key)->first();
        if (! $definition) {
            return null;
        }

        $override = UserSetting::where('user_id', $user->id)
            ->where('key', $key)
            ->first();

        $raw = $override ? $override->value : $definition->default_value;

        return $this->castToType($raw, $definition->type);
    }

    /**
     * Persist a user's chosen value for an option.
     *
     * Validation rules come from the AppSetting row (DB-stored config), so this
     * method works for any present or future option without modification.
     *
     * @throws ModelNotFoundException        when the option key is unknown / inactive
     * @throws \Illuminate\Validation\ValidationException when the value fails the stored rules
     */
    public function set(User $user, string $key, mixed $value): UserSetting
    {
        $definition = AppSetting::active()->where('key', $key)->first();
        if (! $definition) {
            throw (new ModelNotFoundException())->setModel(AppSetting::class, [$key]);
        }

        // Validate the incoming value against the rules stored on the option row.
        $rules = $definition->validation ?: 'nullable';
        Validator::make(['value' => $value], ['value' => $rules])->validate();

        $typed = $this->castToType($value, $definition->type);

        return UserSetting::updateOrCreate(
            ['user_id' => $user->id, 'key' => $key],
            ['value' => $typed],
        );
    }

    /**
     * Normalise a raw stored/incoming value into the option's declared type so
     * the API always returns a clean, correctly-typed value (true vs "true").
     */
    protected function castToType(mixed $value, string $type): mixed
    {
        if (is_null($value)) {
            return null;
        }

        return match ($type) {
            AppSetting::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            AppSetting::TYPE_INTEGER => (int) $value,
            AppSetting::TYPE_DECIMAL => (float) $value,
            AppSetting::TYPE_STRING => (string) $value,
            default => $value, // json / unknown → as-is
        };
    }
}
