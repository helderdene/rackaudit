<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scheduled Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the scheduled discrepancy detection job. When enabled, the
    | job runs at the configured time to detect discrepancies across all
    | datacenters.
    |
    */
    'schedule' => [
        'enabled' => env('DISCREPANCY_SCHEDULE_ENABLED', true),
        'time' => env('DISCREPANCY_SCHEDULE_TIME', '02:00'),
        'timezone' => env('DISCREPANCY_SCHEDULE_TIMEZONE', config('app.timezone')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure notification thresholds and which roles receive discrepancy
    | notifications by default.
    |
    */
    'notifications' => [
        // Number of new discrepancies that triggers a threshold notification
        'threshold' => env('DISCREPANCY_NOTIFICATION_THRESHOLD', 10),

        // Roles that receive discrepancy notifications by default
        'roles' => ['it_manager', 'auditor'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Finding Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic promotion of persistent discrepancies into findings.
    | Discrepancies that remain open beyond the threshold are auto-promoted
    | to ensure they get tracked and assigned.
    |
    */
    'auto_findings' => [
        'enabled' => env('DISCREPANCY_AUTO_FINDINGS_ENABLED', true),
        'persistence_threshold_days' => env('DISCREPANCY_AUTO_FINDINGS_THRESHOLD_DAYS', 3),
        'due_date_days' => env('DISCREPANCY_AUTO_FINDINGS_DUE_DATE_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure behavior of the discrepancy detection process.
    |
    */
    'detection' => [
        // Whether to detect configuration mismatches (cable type, length)
        'check_configuration_mismatch' => env('DISCREPANCY_CHECK_CONFIG_MISMATCH', true),

        // Whether to detect port type mismatches
        'check_port_type_mismatch' => env('DISCREPANCY_CHECK_PORT_TYPE_MISMATCH', true),
    ],
];
