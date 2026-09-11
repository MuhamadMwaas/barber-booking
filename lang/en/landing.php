<?php

/*
|--------------------------------------------------------------------------
| Landing page — interface strings
|--------------------------------------------------------------------------
|
| Two groups live here:
|
|   • the handful of strings the public page renders itself (accessibility
|     labels, form fallbacks) — everything a visitor actually reads as content
|     comes from `landing_sections`, not from here;
|   • every label in the "Landing Page" editor in the admin panel.
|
*/

return [

    /* ── Public site ─────────────────────────────────────────────── */

    'skip_to_content' => 'Skip to content',
    'primary_navigation' => 'Primary navigation',
    'footer_navigation' => 'Footer navigation',
    'footer_services' => 'Services',
    'footer_legal' => 'Legal',

    /* ── Legal documents (/page/{slug}) ──────────────────────────── */
    'legal_eyebrow' => 'Legal information',
    'legal_contents' => 'Contents',
    'legal_updated' => 'Last updated :date',
    'legal_other' => 'Other legal pages',
    'legal_back' => 'Back to the homepage',

    'open_menu' => 'Open menu',
    'change_language' => 'Change language',
    'email_address' => 'Email address',
    'subscribe' => 'Subscribe',
    'qr_alt' => 'QR code to download the app',
    'app_coming_soon' => 'The app is on its way. Please call us to book in the meantime.',

    /* ── Admin: page chrome ──────────────────────────────────────── */

    'navigation_label' => 'Landing Page',
    'page_title' => 'Landing Page',
    'page_subheading' => 'Everything the public website shows at / and /app.',

    'save' => 'Save changes',
    'saved_title' => 'Landing page updated',
    'saved_body' => 'The public page has been refreshed.',
    'preview' => 'Open page',
    'preview_app' => 'Open app page',
    'clear_cache' => 'Clear cache',
    'cache_cleared' => 'Cached content cleared.',

    'section_enabled' => 'Section visible',
    'section_enabled_hint' => 'Turn off to hide this section from the website without losing its content.',
    'sort_order' => 'Position on the page',
    'sort_order_hint' => 'Lower numbers appear first.',

    /* ── Admin: tabs ─────────────────────────────────────────────── */

    'tabs' => [
        'brand' => 'Brand',
        'seo' => 'SEO',
        'navbar' => 'Navigation',
        'hero' => 'Hero',
        'services' => 'Services',
        'features' => 'Why us',
        'gallery' => 'Gallery',
        'cta' => 'Call to action',
        'info' => 'Contact strip',
        'footer' => 'Footer',
        'app_page' => 'App page',
    ],

    'tab_hints' => [
        'brand' => 'Logo, wordmark and tagline used across the site.',
        'seo' => 'Browser title, search-result description and share image.',
        'navbar' => 'The sticky top bar: its links and its button.',
        'hero' => 'The first screen: headline, photo and the two buttons.',
        'services' => 'The four service cards. Independent of the booking services list.',
        'features' => 'The "more than a haircut" block and its four value boxes.',
        'gallery' => 'The row of work photos.',
        'cta' => 'The bordered banner near the bottom of the page.',
        'info' => 'Address, phone, opening hours and social links.',
        'footer' => 'Footer columns, newsletter and the legal links.',
        'app_page' => 'The standalone /app download page every booking button leads to.',
    ],

    /* ── Admin: field labels ─────────────────────────────────────── */

    'fields' => [
        'logo' => 'Logo',
        'favicon' => 'Favicon',
        'brand_name' => 'Brand name',
        'brand_suffix' => 'Brand suffix',
        'tagline' => 'Tagline',

        'seo_title' => 'Browser title',
        'seo_description' => 'Meta description',
        'seo_keywords' => 'Keywords',
        'og_image' => 'Share image',

        'cta_label' => 'Button text',
        'cta_url' => 'Button link',
        'links' => 'Links',
        'label' => 'Text',
        'url' => 'Link',
        'new_tab' => 'Open in a new tab',
        'show_language_switcher' => 'Show the language switcher',

        'eyebrow' => 'Small label above the heading',
        'title' => 'Heading',
        'title_gold' => 'Heading — gold line',
        'title_light' => 'Heading — white line',
        'description' => 'Description',
        'image' => 'Image',
        'image_alt' => 'Image description (for screen readers)',
        'icon' => 'Icon',

        'primary_cta_label' => 'Primary button text',
        'primary_cta_url' => 'Primary button link',
        'secondary_cta_label' => 'Secondary button text',
        'secondary_cta_url' => 'Secondary button link',

        'badges' => 'Highlight badges',
        'items' => 'Items',
        'link_label' => 'Card link text',
        'is_wide' => 'Wide tile',
        'is_active' => 'Visible',
        'caption' => 'Caption',
        'alt' => 'Image description',

        'value' => 'Value',
        'social_label' => 'Social heading',
        'social_links' => 'Social links',
        'platform' => 'Platform',

        'nav_title' => 'Navigation column heading',
        'nav_links' => 'Navigation column links',
        'services_title' => 'Services column heading',
        'services_links' => 'Services column links',
        'closing' => 'Closing line',
        'copyright' => 'Copyright line',
        'legal_links' => 'Legal links',

        'newsletter_enabled' => 'Show the newsletter block',
        'newsletter_title' => 'Newsletter heading',
        'newsletter_description' => 'Newsletter text',
        'newsletter_placeholder' => 'Input placeholder',
        'newsletter_action_url' => 'Mailing-list form URL',
        'newsletter_field_name' => 'Email field name',
        'newsletter_email' => 'Fallback email address',
        'newsletter_button_label' => 'Fallback button text',

        'app_store_url' => 'App Store link',
        'app_store_label' => 'App Store button text',
        'google_play_url' => 'Google Play link',
        'google_play_label' => 'Google Play button text',
        'mockup_image' => 'Phone image',
        'qr_image' => 'QR code',
        'qr_note' => 'Text beside the QR code',
        'coming_soon_note' => 'Text shown while no store link is set',
        'steps_eyebrow' => 'Steps — small label',
        'steps_title' => 'Steps — heading',
        'steps' => 'Steps',
        'features' => 'App highlights',
    ],

    'hints' => [
        'anchor_url' => 'Use #hero, #services, #features, #gallery, #cta or #info to jump to a section, or a full URL for anything else.',
        'icon' => 'Pick from the built-in icon set.',
        'newsletter_action' => 'The form URL from your mailing-list provider (Mailchimp, Brevo, CleverReach). Leave empty to show a plain email button instead — this app does not store subscribers.',
        'newsletter_field' => 'The name your provider expects for the email input. Mailchimp uses EMAIL.',
        'seo_description' => 'Around 150–160 characters shows in full in search results.',
        'is_wide' => 'Makes this tile twice as wide on large screens. The reference design marks one tile in the middle.',
        'image_default' => 'Leave empty to keep the artwork shipped with the site. Uploading a file replaces it permanently.',
        'store_links' => 'Leave empty until the app is published; the page then shows an explanatory note instead of a dead button.',
    ],

    'add' => [
        'link' => 'Add link',
        'badge' => 'Add badge',
        'card' => 'Add card',
        'feature' => 'Add box',
        'image' => 'Add image',
        'item' => 'Add item',
        'social' => 'Add social link',
        'step' => 'Add step',
    ],
];
