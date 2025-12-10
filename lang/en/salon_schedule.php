<?php

return [
    // Navigation & Page Titles
    'page_title' => 'Manage Salon Schedules',
    'page_heading' => 'Manage Salon Work Hours',
    'page_subheading' => 'Set opening and closing hours for each branch throughout the week',
    'navigation_label' => 'Salon Schedules',
    'navigation_group' => 'Salon Management',

    // Branch Selection
    'select_branch' => 'Select Branch',
    'select_branch_description' => 'Choose a branch to manage its working hours',
    'choose_branch' => 'Choose a branch...',
    'branch' => 'Branch',
    'branch_info' => 'Branch Information',
    'managing_schedule' => 'Managing weekly schedule for the selected branch',

    // Weekly Schedule
    'weekly_schedule' => 'Weekly Schedule',
    'weekly_schedule_description' => 'Set opening and closing hours for each day of the week',
    'manage_opening_hours' => 'Manage opening and closing hours for each day',

    // Days of the Week
    'days' => [
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
    ],

    // Time Fields
    'is_open' => 'Open',
    'open_time' => 'Opening Time',
    'close_time' => 'Closing Time',
    'working_hours' => 'Working Hours',
    'open' => 'Open',
    'closed' => 'Closed',

    // Summary
    'summary' => 'Weekly Summary',
    'summary_description' => 'Overview of weekly working hours',
    'total_weekly_hours' => 'Total Weekly Hours',
    'open_days_count' => 'Open Days',
    'average_daily_hours' => 'Average Daily Hours',
    'days_unit' => 'days',

    // Actions
    'save_schedule' => 'Save Schedule',
    'reload' => 'Reload',
    'schedule_saved' => 'Schedule Saved',
    'schedule_saved_successfully' => 'Salon schedule has been saved successfully',
    'save_error' => 'Error Saving Schedule',
    'please_select_branch' => 'Please select a branch first',

    // Validation
    'validation' => [
        'close_time_must_differ' => 'Closing time must be different from opening time',
        'invalid_time_format' => 'Invalid time format',
    ],
    'open_time_required' => 'Opening time is required for :day',
    'close_time_required' => 'Closing time is required for :day',

    // Messages
    'messages' => [
        'day_copied' => ':day schedule copied',
        'day_pasted' => 'Schedule pasted to :day',
        'day_applied_to_week' => ':day schedule applied to all days',
    ],

    // Errors
    'errors' => [
        'nothing_to_paste' => 'Nothing to paste. Copy a day first.',
    ],

    // Additional
    'schedule_reloaded' => 'Schedule reloaded successfully',
];
