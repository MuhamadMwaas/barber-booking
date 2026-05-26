<?php

return [

    /* ── General ── */
    'page_not_found' => 'Page not found.',

    /* ── Language labels ── */
    'lang' => [
        'ar' => 'Arabic',
        'en' => 'English',
        'de' => 'German',
    ],

    /* ── Block type labels ── */
    'blocks' => [
        'heading'         => 'Heading',
        'paragraph'       => 'Paragraph',
        'title_paragraph' => 'Title + Paragraph',
        'ordered_list'    => 'Ordered List',
        'unordered_list'  => 'Unordered List',
        'divider'         => 'Divider',
        'link'            => 'Link',
        'image'           => 'Image',
        'warning_box'     => 'Warning Box',
    ],

    /* ── Block section labels ── */
    'block_sections' => [
        'display_options' => 'Display Options',
        'translations'    => 'Translations',
    ],

    /* ── Shared field labels ── */
    'fields' => [
        'is_active'        => 'Active',
        'text'             => 'Text',
        'title'            => 'Title',
        'heading_level'    => 'Heading Level',
        'alignment'        => 'Alignment',
        'color'            => 'Color',
        'background_color' => 'Background Color',
        'orientation'      => 'Orientation',
        'size'             => 'Size',
        'items'            => 'Items',
        'item'             => 'Item',
        'label'            => 'Label',
        'url'              => 'URL',
        'target'           => 'Open in',
        'image'            => 'Image',
        'alt'              => 'Alt Text',
    ],

    /* ── Alignment options ── */
    'alignment' => [
        'auto'    => 'Auto (by language)',
        'left'    => 'Left',
        'right'   => 'Right',
        'center'  => 'Center',
        'justify' => 'Justify',
    ],

    /* ── Orientation options ── */
    'orientation' => [
        'horizontal' => 'Horizontal',
        'vertical'   => 'Vertical',
    ],

    /* ── Size options ── */
    'size' => [
        'sm' => 'Thin',
        'md' => 'Medium',
        'lg' => 'Thick',
    ],

    /* ── Link target options ── */
    'target' => [
        'same'     => 'Same screen / WebView',
        'external' => 'External browser',
    ],

    /* ── Filament Resource ── */
    'resource' => [
        'navigation_label'   => 'App Pages',
        'model_label'        => 'Page',
        'plural_model_label' => 'App Pages',

        'section_page_info'      => 'Page Identity',
        'section_page_info_desc' => 'The internal name and slug determine how the page is requested via API.',
        'section_blocks'         => 'Page Content',
        'section_blocks_desc'    => 'Add blocks and sort them in the order they should appear in the app.',

        'field_name'             => 'Internal Name',
        'field_name_placeholder' => 'e.g. Privacy Policy',
        'field_slug_hint'        => 'Used to request this page via the API — do not change after publishing',
        'field_is_active'        => 'Page Active',
        'field_is_active_hint'   => 'Inactive pages return 404 and are hidden from the app',
        'field_blocks'           => 'Blocks',
        'add_block'              => '+ Add Block',

        'api_preview_label'    => 'API Endpoint',
        'api_slug_placeholder' => 'your-page-slug',
        'api_lang_options'     => 'Supported languages',

        'blocks_hint' => 'Drag blocks to reorder • Click to expand or collapse • Use Clone to duplicate a block',

        'col_name'       => 'Name',
        'col_is_active'  => 'Active',
        'col_updated_at' => 'Last Modified',
        'slug_copied'    => 'Slug copied',

        'action_preview_api' => 'API Response',
        'action_preview'     => 'Preview',

        // Preview page
        'preview_label'           => 'Mobile Preview',
        'preview_back'            => 'Back to Admin',
        'preview_edit'            => 'Edit Page',
        'preview_language'        => 'Language',
        'preview_stats'           => 'Page Stats',
        'preview_total_blocks'    => 'Total Blocks',
        'preview_active_blocks'   => 'Active',
        'preview_inactive_blocks' => 'Inactive',
        'preview_no_blocks'       => 'No blocks added yet',
        'preview_status_active'   => 'Active',
        'preview_status_inactive' => 'Inactive',
    ],
];
