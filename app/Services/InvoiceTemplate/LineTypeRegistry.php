<?php

namespace App\Services\InvoiceTemplate;

class LineTypeRegistry
{
    /**
     * Get all available line types
     */
    public static function getAllTypes(): array
    {
        return config('invoice-line-types.types', []);
    }

    /**
     * Get line type configuration
     */
    public static function getType(string $type): ?array
    {
        return config("invoice-line-types.types.{$type}");
    }

    /**
     * Get line types for a specific section
     */
    public static function getTypesForSection(string $section): array
    {
        $allTypes = self::getAllTypes();
        $sectionTypes = [];

        foreach ($allTypes as $typeKey => $typeConfig) {
            if (in_array($section, $typeConfig['sections'] ?? [])) {
                $sectionTypes[$typeKey] = $typeConfig;
            }
        }

        return $sectionTypes;
    }

    /**
     * Get formatted options for Filament select
     */
    public static function getOptionsForSelect(string $section = null): array
    {
        $types = $section ? self::getTypesForSection($section) : self::getAllTypes();
        $options = [];

        foreach ($types as $typeKey => $typeConfig) {
            $options[$typeKey] = $typeConfig['label'] ?? $typeKey;
        }

        return $options;
    }

    /**
     * Get grouped options for Filament select (by category)
     */
    public static function getGroupedOptionsForSelect(?string $section = null): array
    {
        $types = $section ? self::getTypesForSection($section) : self::getAllTypes();
        $grouped = [
            'Basic' => [],
            'Invoice Components' => [],
            'QR & Barcode' => [],
            'TSE/Fiskaly' => [],
            'Messages' => [],
        ];

        foreach ($types as $typeKey => $typeConfig) {
            $label = $typeConfig['label'] ?? $typeKey;

            // Categorize
            if (in_array($typeKey, ['text', 'separator', 'spacer', 'image', 'two_column'])) {
                $grouped['Basic'][$typeKey] = $label;
            } elseif (in_array($typeKey, ['invoice_number', 'invoice_date', 'customer_info', 'items_table', 'totals_summary', 'payment_info'])) {
                $grouped['Invoice Components'][$typeKey] = $label;
            } elseif (in_array($typeKey, ['qr_code', 'barcode'])) {
                $grouped['QR & Barcode'][$typeKey] = $label;
            } elseif (in_array($typeKey, ['tse_info'])) {
                $grouped['TSE/Fiskaly'][$typeKey] = $label;
            } else {
                $grouped['Messages'][$typeKey] = $label;
            }
        }

        // Remove empty groups
        return array_filter($grouped);
    }

    /**
     * Check if a line type is unique
     */
    public static function isUnique(string $type): bool
    {
        $typeConfig = self::getType($type);
        return $typeConfig['unique'] ?? false;
    }

    /**
     * Get blade view for a line type
     */
    public static function getBladeView(string $type): string
    {
        $typeConfig = self::getType($type);
        return $typeConfig['blade_view'] ?? 'invoices.line-types.default';
    }

    /**
     * Get default properties for a line type
     */
    public static function getDefaultProperties(string $type): array
    {
        $typeConfig = self::getType($type);
        return $typeConfig['properties'] ?? [];
    }

    /**
     * Get icon for a line type
     */
    public static function getIcon(string $type): string
    {
        $typeConfig = self::getType($type);
        return $typeConfig['icon'] ?? 'heroicon-o-document';
    }

    /**
     * Validate if line type exists
     */
    public static function exists(string $type): bool
    {
        return self::getType($type) !== null;
    }

    /**
     * Register a new line type dynamically (for custom extensions)
     */
    public static function registerType(string $key, array $config): void
    {
        $types = config('invoice-line-types.types', []);
        $types[$key] = $config;
        config(['invoice-line-types.types' => $types]);
    }
}
