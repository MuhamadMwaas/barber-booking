<?php

/**
 * English translations for Schedule Management
 *
 * ملف الترجمة الإنجليزية لنظام إدارة الشفتات
 */

return [
    // Page titles
    'page_title' => 'Provider Schedule Management',
    'page_heading' => 'Manage Provider Work Schedules',
    'page_subheading' => 'Configure weekly shifts and working hours for service providers',
    'managing_schedule_for' => 'Managing schedule for :name',
    'navigation_label' => 'Provider Schedules',
    'navigation_label2' => 'Provider Schedules Managment',
    'navigation_group' => 'Provider Management',

    // Provider selection
    'select_provider' => 'Select Provider',
    'choose_provider' => '-- Choose a provider --',
    'select_provider_prompt' => 'Select a Provider to Manage',
    'select_provider_desc' => 'Choose a service provider from the dropdown above to view and manage their weekly schedule.',

    // Days of week
    'sunday' => 'Sunday',
    'monday' => 'Monday',
    'tuesday' => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday' => 'Thursday',
    'friday' => 'Friday',
    'saturday' => 'Saturday',

    // Shift management
    'shift' => 'Shift',
    'shifts' => 'Shifts',
    'add_shift' => 'Add Shift',
    'remove_shift' => 'Remove',
    'no_shifts' => 'No shifts',
    'start' => 'Start',
    'end' => 'End',
    'break_minutes' => 'Break (min)',
    'hours' => 'hours',
    'total_hours' => 'Total weekly hours',
    'total_shifts' => 'Total Shifts',

    // Branch schedule
    'branch' => 'Branch',
    'branch_hours' => 'Branch Hours',

    // Legend
    'legend' => 'Legend',
    'shift_block' => 'Work Shift',
    'day_off_indicator' => 'Day Off',
    'view_timeline' => 'View Timeline',

    // Actions
    'save_schedule' => 'Save Schedule',
    'cancel' => 'Cancel',
    'reset' => 'Reset',
    'clear_all' => 'Clear All',
    'copy' => 'Copy',
    'paste' => 'Paste',
    'copy_day' => 'Copy Day',
    'paste_day' => 'Paste Day',
    'copy_week' => 'Copy Week',
    'paste_week' => 'Paste Week',
    'copy_from_user' => 'Copy from Another',
    'bulk_paste' => 'Paste to Multiple',
    'clear_day' => 'Clear Day',
    'apply_to_all_days' => 'Apply to All Days',
    'apply_to_selected' => 'Apply to Selected',
    'mark_as_off' => 'Mark as Day Off',
    'mark_as_work' => 'Mark as Work Day',

    // Bulk paste modal
    'bulk_paste_title' => 'Paste Schedule to Multiple Providers',
    'bulk_paste_desc' => 'Select the providers you want to apply the copied schedule to:',
    'selected_count' => ':count selected',

    // Status messages
    'unsaved_changes' => 'You have unsaved changes',
    'clipboard_has_day' => 'Day schedule in clipboard',
    'clipboard_has_week' => 'Week schedule in clipboard',

    // Success messages
    'messages' => [
        'saved_successfully' => ':count shift(s) saved successfully!',
        'reset_successfully' => 'Schedule reset to last saved state',
        'all_cleared' => 'All shifts cleared',
        'day_cleared' => ':day shifts cleared',
        'day_copied' => ':day schedule copied',
        'day_pasted' => 'Schedule pasted to :day',
        'week_copied' => 'Week schedule copied to clipboard',
        'week_pasted' => 'Week schedule pasted successfully',
        'copied_from_user' => 'Schedule copied from :name',
        'day_applied_to_week' => ':day schedule applied to all days',
        'bulk_paste_success' => 'Schedule applied to :count provider(s)',
    ],

    // Error messages
    'errors' => [
        'select_provider' => 'Please select a provider',
        'select_provider_first' => 'Please select a provider first',
        'start_time_required' => 'Start time is required',
        'end_time_required' => 'End time is required',
        'invalid_time_format' => 'Invalid time format',
        'nothing_to_paste' => 'Nothing to paste. Copy a day or week first.',
        'copy_week_first' => 'Please copy a week schedule first',
        'select_users_to_paste' => 'Please select at least one provider',
        'shift_missing_start' => ':day Shift #:shift: Start time is missing',
        'shift_missing_end' => ':day Shift #:shift: End time is missing',
        'shift_invalid_time_range' => ':day Shift #:shift: Start time (:start) must be before end time (:end)',
        'shifts_overlap' => ':day: Shift #:shift1 (:time1) overlaps with Shift #:shift2 (:time2)',
        'save_failed' => 'Failed to save schedule',
        'bulk_paste_failed' => 'Failed to apply schedule to selected providers',
    ],
    'errors_found' => 'Please fix the following errors:',

    // Confirmations
    'confirm_reset_title' => 'Reset Schedule?',
    'confirm_reset_message' => 'This will discard all unsaved changes and reload the last saved schedule.',
    'confirm_reset' => 'Reset',
    'confirm_clear_title' => 'Clear All Shifts?',
    'confirm_clear_message' => 'This will remove all shifts from all days. You can still cancel before saving.',
    'confirm_clear' => 'Clear All',
    'confirm_remove_shift' => 'Are you sure you want to remove this shift?',

    // Info section
    'info_title' => 'Schedule Management',
    'info_description' => 'Manage working hours and shifts for your service providers',
    'feature_shifts_title' => 'Multiple Shifts',
    'feature_shifts_desc' => 'Add multiple shifts per day with flexible timing',
    'feature_copy_title' => 'Copy & Paste',
    'feature_copy_desc' => 'Copy schedules between days or providers',
    'feature_validation_title' => 'Smart Validation',
    'feature_validation_desc' => 'Automatic overlap detection prevents conflicts',

    // Help section
    'help_title' => 'Help & Tips',
    'help_how_to_use' => 'How to Use',
    'help_step_1' => 'Select a provider from the dropdown menu',
    'help_step_2' => 'Add shifts to each day by clicking "Add Shift"',
    'help_step_3' => 'Set start and end times for each shift',
    'help_step_4' => 'Optionally add break time in minutes',
    'help_step_5' => 'Click "Save Schedule" to save your changes',

    'help_tips_title' => 'Tips',
    'help_tip_1' => 'Use "Apply to All Days" to quickly copy a day\'s schedule to the entire week',
    'help_tip_2' => 'The system will automatically detect overlapping shifts',
    'help_tip_3' => 'You can copy schedules between different providers',
    'help_tip_4' => 'Mark a shift as "Day Off" if the provider is not working that shift',

    'help_shortcuts_title' => 'Quick Actions',
    'help_shortcut_copy_day' => 'Copy shifts from one day',
    'help_shortcut_apply_all' => 'Apply day to entire week',
    'help_shortcut_bulk' => 'Apply to multiple providers at once',
];
