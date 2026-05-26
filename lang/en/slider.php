<?php

return [
    'navigation_label'  => 'Slider Management',
    'navigation_group'  => 'Content',
    'page_title'        => 'Home Page Slider',
    'page_heading'      => 'Home Slider Management',

    // ── Stats ──
    'stat_total'        => 'Total Slides',
    'stat_total_sub'    => 'All added slides',
    'stat_active'       => 'Active & Visible',
    'stat_active_sub'   => 'Currently shown in the app',
    'stat_scheduled'    => 'Scheduled',
    'stat_scheduled_sub'=> 'Will appear on time',
    'stat_inactive'     => 'Inactive',
    'stat_inactive_sub' => 'Hidden from users',

    // ── Preview Section ──
    'preview_title'     => 'Currently Visible Slides Preview',
    'preview_empty'     => 'No active slides yet',
    'preview_empty_sub' => 'Add a new slide or activate an existing one',
    'active_count'      => ':count active',

    // ── Table ──
    'table_section_title' => 'Manage Slides',
    'table_section_hint'  => 'Drag ⠿ to reorder • Click ⋮ for options',
    'col_image'         => 'Image',
    'col_title'         => 'Title',
    'col_status'        => 'Status',
    'col_starts_at'     => 'Publish Start',
    'col_ends_at'       => 'Publish End',
    'col_order'         => '#',
    'empty_heading'     => 'No slides yet',
    'empty_desc'        => 'Add your first slide using the "Add Slide" button above.',

    // ── Status Labels ──
    'status_active'     => 'Active',
    'status_permanent'  => 'Permanent',
    'status_scheduled'  => 'Scheduled',
    'status_expired'    => 'Expired',
    'status_inactive'   => 'Inactive',

    // ── Actions ──
    'action_add'        => 'Add Slide',
    'action_edit'       => 'Edit',
    'action_toggle_off' => 'Disable',
    'action_toggle_on'  => 'Enable',
    'action_delete'     => 'Delete',

    // ── Modals ──
    'modal_add_heading'   => 'Add New Slide',
    'modal_edit_heading'  => 'Edit Slide',
    'modal_submit_label'  => 'Save Slide',
    'modal_save_changes'  => 'Save Changes',
    'modal_disable_heading' => 'Disable Slide?',
    'modal_delete_heading'  => 'Delete Slide',
    'modal_delete_desc'     => 'Are you sure you want to delete this slide? This action cannot be undone.',

    // ── Form Sections ──
    'section_image'         => 'Slide Image',
    'section_image_desc'    => 'Upload a high quality image — 16:9 ratio recommended',
    'section_content'       => 'Content & Translations',
    'section_content_desc'  => 'Enter content for each language — Arabic is required at minimum',
    'section_publish'       => 'Publish Settings',
    'section_publish_desc'  => 'Control when the slide appears — leave both empty for permanent display',

    // ── Form Fields ──
    'field_image'           => 'Image',
    'field_image_help'      => 'Max size: 8MB — Supported formats: JPG, PNG, WEBP, GIF',
    'field_is_active'       => 'Activate Slide',
    'field_is_active_help'  => 'Disabling hides the slide immediately regardless of dates',
    'field_starts_at'       => 'Publish Start Date',
    'field_starts_at_help'  => 'Leave empty to publish immediately',
    'field_ends_at'         => 'Publish End Date',
    'field_ends_at_help'    => 'Leave empty for permanent display with no expiry',

    // ── Notifications ──
    'notif_created'         => 'Slide Added',
    'notif_created_body'    => 'New slide has been added successfully',
    'notif_updated'         => 'Changes Saved',
    'notif_updated_body'    => 'Slide has been updated successfully',
    'notif_enabled'         => 'Slide Enabled',
    'notif_disabled'        => 'Slide Disabled',

    // ── Scheduling pills ──
    'pill_permanent'        => '♾ Permanent',
    'pill_timed'            => '⏰ Timed',
    'pill_scheduled'        => '📅 Scheduled',

    // ── Permanent label ──
    'permanent'             => 'Permanent ♾',
];
