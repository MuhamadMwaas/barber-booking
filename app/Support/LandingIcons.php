<?php

namespace App\Support;

/**
 * The landing page's icon set.
 *
 * Inline SVG rather than an icon font or a sprite request: the whole set that a
 * page actually uses is a couple of KB of markup, it inherits `currentColor`
 * (so the gold accent comes from CSS, not from the asset), and it costs no
 * extra request on a first visit — which is the visit that matters for a
 * marketing page.
 *
 * `options()` feeds the icon picker in the Filament editor, so every icon an
 * admin can choose is guaranteed to exist here, and vice versa.
 */
class LandingIcons
{
    /**
     * Icon paths, drawn on a 24×24 grid and stroked by the renderer.
     * Anything needing a solid fill (brand marks) lives in FILLED below.
     *
     * @var array<string, string>
     */
    private const STROKED = [
        // ── Salon / craft ─────────────────────────────────────────────
        'scissors'  => '<circle cx="6" cy="18" r="2.6"/><circle cx="18" cy="18" r="2.6"/><path d="M7.8 16.2 18 4"/><path d="M16.2 16.2 6 4"/>',
        'comb'      => '<rect x="3" y="8" width="18" height="4" rx="1.4"/><path d="M6 12v5M9 12v7M12 12v5M15 12v7M18 12v5"/>',
        'razor'     => '<path d="M3 7h11a3 3 0 0 1 3 3v1a3 3 0 0 1-3 3H3z"/><path d="M17 11h2a2 2 0 0 1 2 2v7"/>',
        'droplet'   => '<path d="M12 3s-6 6.9-6 10.5a6 6 0 0 0 12 0C18 9.9 12 3 12 3Z"/><path d="M9 13.5a3 3 0 0 0 3 3"/>',
        'bottle'    => '<rect x="9.5" y="2.5" width="5" height="3" rx="1"/><path d="M10 5.5v2.6L8 11v8.5A2 2 0 0 0 10 21.5h4a2 2 0 0 0 2-2V11l-2-2.9V5.5"/><path d="M8 14h8"/>',
        'chair'     => '<path d="M6 11h12v5a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3z"/><path d="M8 11V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v5"/><path d="M12 19v2M9 21h6"/>',
        'mirror'    => '<ellipse cx="12" cy="9.5" rx="6" ry="7"/><path d="M12 16.5V20M9 20h6"/>',

        // ── Value props ───────────────────────────────────────────────
        'sparkle'   => '<path d="M12 3l1.8 5.4L19.2 10l-5.4 1.8L12 17l-1.8-5.2L4.8 10l5.4-1.6z"/><path d="M18.5 15.5l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0"/><path d="M16.5 5.2a3.2 3.2 0 0 1 0 5.9"/><path d="M17.6 14.4A6.2 6.2 0 0 1 21.4 20"/>',
        'chat'      => '<path d="M20.5 12a7.5 7.5 0 0 1-10.9 6.7L4 20l1.3-5.1A7.5 7.5 0 1 1 20.5 12Z"/><path d="M9 11h6M9 14h4"/>',
        'star'      => '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.7l5.9-.8z"/>',
        'shield'    => '<path d="M12 3l7.5 3v6c0 4.4-3.1 7.9-7.5 9.4C7.6 19.9 4.5 16.4 4.5 12V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'check'     => '<path d="m5 12.8 4.4 4.4L19 7.6"/>',
        'award'     => '<circle cx="12" cy="9" r="5.2"/><path d="m8.6 13.6-1.4 7 4.8-2.5 4.8 2.5-1.4-7"/>',

        // ── Contact ───────────────────────────────────────────────────
        'map-pin'   => '<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
        'phone'     => '<path d="M7 3.5h3l1.5 4-2 1.4a12 12 0 0 0 5.6 5.6l1.4-2 4 1.5v3a2 2 0 0 1-2.2 2A16.6 16.6 0 0 1 5.5 5.7 2 2 0 0 1 7 3.5Z"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.2V12l3.2 1.9"/>',
        'mail'      => '<rect x="3" y="5.5" width="18" height="13" rx="2.2"/><path d="m3.8 7 8.2 5.8L20.2 7"/>',
        'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.2"/><path d="M3.5 10h17M8 3.2v3.6M16 3.2v3.6"/><path d="M8 14h2M14 14h2M8 17.2h2M14 17.2h2"/>',
        'globe'     => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5a13 13 0 0 1 0 17 13 13 0 0 1 0-17Z"/>',
        'download'  => '<path d="M12 3.8v11"/><path d="m7.6 10.4 4.4 4.4 4.4-4.4"/><path d="M4.5 18.5h15"/>',
        'bell'      => '<path d="M18 16.5V11a6 6 0 1 0-12 0v5.5L4.5 19h15z"/><path d="M10 21.2a2.2 2.2 0 0 0 4 0"/>',
        'device'    => '<rect x="7" y="2.5" width="10" height="19" rx="2.4"/><path d="M10.5 5.2h3"/><circle cx="12" cy="18.4" r="1"/>',

        // ── UI ────────────────────────────────────────────────────────
        'arrow-right' => '<path d="M4.5 12h15"/><path d="m13.6 6.2 5.9 5.8-5.9 5.8"/>',
        'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'       => '<path d="m6 6 12 12M18 6 6 18"/>',
        'chevron-down'=> '<path d="m6 9.5 6 6 6-6"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        'minus'       => '<path d="M5 12h14"/>',
    ];

    /**
     * Filled marks — mostly third-party logos, whose shapes are solid by
     * definition and must not be re-stroked.
     *
     * @var array<string, string>
     */
    private const FILLED = [
        'instagram' => '<path d="M12 2.2c3.2 0 3.6 0 4.85.07 1.25.06 2.1.26 2.85.55.77.3 1.43.7 2.08 1.35.65.65 1.05 1.3 1.35 2.08.29.74.49 1.6.55 2.85.06 1.25.07 1.65.07 4.85s0 3.6-.07 4.85c-.06 1.25-.26 2.1-.55 2.85a5.75 5.75 0 0 1-1.35 2.08 5.75 5.75 0 0 1-2.08 1.35c-.74.29-1.6.49-2.85.55-1.25.06-1.65.07-4.85.07s-3.6 0-4.85-.07c-1.25-.06-2.1-.26-2.85-.55a5.75 5.75 0 0 1-2.08-1.35 5.75 5.75 0 0 1-1.35-2.08c-.29-.74-.49-1.6-.55-2.85C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.85c.06-1.25.26-2.1.55-2.85.3-.77.7-1.43 1.35-2.08A5.75 5.75 0 0 1 6.25 2.87c.74-.29 1.6-.49 2.85-.55C10.35 2.2 10.75 2.2 12 2.2Zm0 1.98c-3.14 0-3.51.01-4.75.07-1.15.05-1.77.24-2.18.4-.55.22-.94.47-1.35.88-.41.41-.66.8-.88 1.35-.16.41-.35 1.03-.4 2.18-.06 1.24-.07 1.61-.07 4.75s.01 3.51.07 4.75c.05 1.15.24 1.77.4 2.18.22.55.47.94.88 1.35.41.41.8.66 1.35.88.41.16 1.03.35 2.18.4 1.24.06 1.61.07 4.75.07s3.51-.01 4.75-.07c1.15-.05 1.77-.24 2.18-.4.55-.22.94-.47 1.35-.88.41-.41.66-.8.88-1.35.16-.41.35-1.03.4-2.18.06-1.24.07-1.61.07-4.75s-.01-3.51-.07-4.75c-.05-1.15-.24-1.77-.4-2.18a3.7 3.7 0 0 0-.88-1.35 3.7 3.7 0 0 0-1.35-.88c-.41-.16-1.03-.35-2.18-.4-1.24-.06-1.61-.07-4.75-.07Zm0 3.37a4.45 4.45 0 1 1 0 8.9 4.45 4.45 0 0 1 0-8.9Zm0 7.34a2.89 2.89 0 1 0 0-5.78 2.89 2.89 0 0 0 0 5.78Zm5.67-7.52a1.04 1.04 0 1 1-2.08 0 1.04 1.04 0 0 1 2.08 0Z"/>',
        'facebook'  => '<path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06C2 17.08 5.66 21.25 10.44 22v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.49-3.92 3.77-3.92 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.9h2.78l-.45 2.91h-2.33V22C18.34 21.25 22 17.08 22 12.06Z"/>',
        'whatsapp'  => '<path d="M12.04 2C6.6 2 2.18 6.42 2.18 11.86c0 1.74.46 3.44 1.32 4.94L2.1 22l5.34-1.38a9.8 9.8 0 0 0 4.6 1.16h.01c5.43 0 9.85-4.42 9.85-9.86A9.8 9.8 0 0 0 18.99 4.9 9.75 9.75 0 0 0 12.04 2Zm0 1.98c2.1 0 4.08.82 5.57 2.3a7.83 7.83 0 0 1 2.3 5.58c0 4.35-3.53 7.88-7.88 7.88a7.8 7.8 0 0 1-3.98-1.09l-.29-.17-2.96.77.79-2.89-.19-.3a7.83 7.83 0 0 1-1.2-4.2c0-4.35 3.54-7.88 7.84-7.88Zm-3.5 4.2c-.16 0-.42.06-.64.3-.22.24-.85.83-.85 2.02s.87 2.34.99 2.5c.12.16 1.7 2.6 4.14 3.64.58.25 1.03.4 1.38.51.58.19 1.11.16 1.53.1.47-.07 1.43-.59 1.63-1.15.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28-.24-.12-1.43-.7-1.65-.79-.22-.08-.38-.12-.54.12-.16.24-.62.79-.76.95-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2-.72-.64-1.2-1.43-1.34-1.67-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.53-1.31-.74-1.79-.19-.46-.39-.4-.53-.41h-.46Z"/>',
        'tiktok'    => '<path d="M16.6 2h-3.1v13.4a2.6 2.6 0 1 1-2.6-2.6c.24 0 .47.03.7.1V9.7a5.86 5.86 0 0 0-.7-.05 5.75 5.75 0 1 0 5.75 5.75V8.9a6.9 6.9 0 0 0 4.05 1.3V7.1a4.03 4.03 0 0 1-4.1-4.05V2Z"/>',
        'youtube'   => '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.76-1.77C18.25 5 12 5 12 5s-6.25 0-7.84.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.76 1.77C5.75 19 12 19 12 19s6.25 0 7.84-.43a2.5 2.5 0 0 0 1.76-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15.1V8.9l5.2 3.1-5.2 3.1Z"/>',
        'x-twitter' => '<path d="M17.53 3h3.06l-6.69 7.64L21.75 21h-6.16l-4.82-6.3L5.25 21H2.19l7.15-8.17L2.25 3h6.31l4.36 5.77L17.53 3Zm-1.07 16.2h1.7L7.62 4.7H5.8l10.66 14.5Z"/>',
        'apple'     => '<path d="M16.36 12.68c-.03-2.6 2.12-3.85 2.22-3.91-1.21-1.77-3.1-2.02-3.77-2.05-1.6-.16-3.13.94-3.94.94-.81 0-2.07-.92-3.4-.9-1.75.03-3.36 1.02-4.26 2.58-1.82 3.15-.46 7.82 1.3 10.38.87 1.25 1.9 2.66 3.25 2.61 1.31-.05 1.8-.85 3.38-.85 1.58 0 2.02.85 3.4.82 1.4-.02 2.29-1.28 3.15-2.54.99-1.45 1.4-2.86 1.42-2.93-.03-.01-2.72-1.04-2.75-4.15ZM13.9 5.1c.72-.87 1.2-2.08 1.07-3.28-1.03.04-2.28.69-3.02 1.55-.66.77-1.24 2-1.09 3.18 1.15.09 2.32-.58 3.04-1.45Z"/>',
        'google-play' => '<path d="M3.6 2.4a1.4 1.4 0 0 0-.6 1.16v16.88c0 .47.22.9.6 1.16l9.03-9.6L3.6 2.4Zm10.28 8.28 2.7-2.87-9.9-5.6a1.4 1.4 0 0 0-.44-.16l7.64 8.63Zm0 2.64L6.24 21.95c.15-.03.3-.08.44-.16l9.9-5.6-2.7-2.87Zm1.36-1.32 3.05 1.72c.7.4.7 1.4 0 1.8l-3.05 1.72-3.1-3.3 3.1-3.3-.01-.01.01.01v-.01l3.05-1.72-3.05 1.72-3.1 3.3 3.1-3.3Z"/>',
    ];

    /**
     * Render an icon as an inline <svg> string.
     *
     * @param  string  $name   Icon key; unknown keys render nothing.
     * @param  string  $class  CSS classes for the <svg> element.
     */
    public static function svg(string $name, string $class = 'size-5'): string
    {
        $attributes = sprintf(
            'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="%s" aria-hidden="true" focusable="false"',
            e($class),
        );

        if (isset(self::FILLED[$name])) {
            return "<svg {$attributes} fill=\"currentColor\">" . self::FILLED[$name] . '</svg>';
        }

        if (isset(self::STROKED[$name])) {
            return "<svg {$attributes} fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\">"
                . self::STROKED[$name]
                . '</svg>';
        }

        return '';
    }

    public static function has(string $name): bool
    {
        return isset(self::STROKED[$name]) || isset(self::FILLED[$name]);
    }

    /**
     * Icon keys for the picker in the Filament editor, as value => label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $keys = [...array_keys(self::STROKED), ...array_keys(self::FILLED)];

        return array_combine(
            $keys,
            array_map(fn (string $key) => ucwords(str_replace('-', ' ', $key)), $keys),
        );
    }

    /**
     * Keys of the social/brand marks, used to render the "Folge uns" row.
     *
     * @return array<string, string>
     */
    public static function socialOptions(): array
    {
        $social = ['instagram', 'facebook', 'whatsapp', 'tiktok', 'youtube', 'x-twitter', 'globe', 'mail', 'phone'];

        return array_combine(
            $social,
            array_map(fn (string $key) => ucwords(str_replace('-', ' ', $key)), $social),
        );
    }
}
