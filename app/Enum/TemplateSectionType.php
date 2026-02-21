<?php

namespace App\Enum;

use Illuminate\Support\Facades\Lang;

/**
 * Enum for invoice template sections.
 *
 * This enum defines the three main sections of an invoice template:
 * - Header: For company info, invoice details, customer info.
 * - Body: For the items table.
 * - Footer: For totals, payment info, TSE data, QR code.
 *
 * It provides helper methods for getting lists, labels, and translations.
 */
enum TemplateSectionType: string
{
    case Header = 'header';
    case Body = 'body';
    case Footer = 'footer';

    /**
     * Get a list of all section types (values).
     * Useful for dropdowns, validation rules, etc.
     *
     * @return array<int, string>
     */
    public static function toList(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a key-value array of all section types for form selects.
     * The key is the enum value, and the value is the translated label.
     *
     * @return array<string, string>
     */
    public static function toSelectArray(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    /**
     * Get the translated label for the section type.
     * It looks for a translation key in the format:
     * 'enums.template_section_type.{value}'
     *
     * Example: For Header, it looks for 'enums.template_section_type.header'
     *
     * @return string
     */
    public function label(): string
    {
        // The translation key format: 'enums.template_section_type.header'
        $translationKey = 'enums.template_section_type.' . $this->value;

        // Use Laravel's translation helper. If not found, it returns the key.
        // We use ucfirst() to capitalize the first letter for display.
        return ucfirst(Lang::get($translationKey, $this->value));
    }

    /**
     * Get a user-friendly, translated name for the section.
     * This is an alias for the label() method for better readability in some contexts.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->label();
    }

    /**
     * Find a section type by its string value.
     * This is a wrapper around the built-in `from()` method for clarity.
     *
     * @param string $value
     * @return static|null
     */
    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Check if the section is the header.
     *
     * @return bool
     */
    public function isHeader(): bool
    {
        return $this === self::Header;
    }

    /**
     * Check if the section is the body.
     *
     * @return bool
     */
    public function isBody(): bool
    {
        return $this === self::Body;
    }

    /**
     * Check if the section is the footer.
     *
     * @return bool
     */
    public function isFooter(): bool
    {
        return $this === self::Footer;
    }
}
