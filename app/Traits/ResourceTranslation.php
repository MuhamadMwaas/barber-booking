<?php

namespace App\Traits;

trait ResourceTranslation
{
    /**
     * Get translation namespace
     */
    protected static function translationNamespace(): string
    {
        return 'resources';
    }

    /**
     * Get resource key
     */
    protected static function resourceKey(): string
    {
        $className = class_basename(static::class);
        return str($className)->replace('Resource', '')->snake()->toString();
    }

    /**
     * Translate resource key
     */
    protected static function translate(string $key, array $replace = []): string
    {
        $fullKey = static::translationNamespace() . '.' . static::resourceKey() . '.' . $key;
        $translated = __($fullKey, $replace);
        return $translated;
        // Fallback to formatted key name
        return $translated === $fullKey
            ? str($key)->headline()->toString()
            : $translated;
    }

    /**
     * Filament v4 methods
     */
    public static function getModelLabel(): string
    {
        return static::translate('label');
    }

    public static function getPluralModelLabel(): string
    {
        return static::translate('plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return static::translate('navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        if (!isset(static::$navigationGroup)) {
            return null;
        }

        return __('navigation.' . static::$navigationGroup);
    }

    public static function getTitleCaseModelLabel(): string
    {
        return static::translate('title');
    }
}
