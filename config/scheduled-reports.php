<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Attachment Size
    |--------------------------------------------------------------------------
    |
    | The maximum file size in megabytes (MB) for report attachments.
    | Reports exceeding this size will fail with an error notification.
    |
    */
    'max_attachment_size_mb' => env('SCHEDULED_REPORTS_MAX_ATTACHMENT_SIZE_MB', 25),

    /*
    |--------------------------------------------------------------------------
    | Retry Delay
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait before retrying a failed report generation.
    | This provides time for transient issues to resolve before retrying.
    |
    */
    'retry_delay_seconds' => env('SCHEDULED_REPORTS_RETRY_DELAY_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Maximum Consecutive Failures
    |--------------------------------------------------------------------------
    |
    | The maximum number of consecutive failures before a schedule is
    | automatically disabled. The schedule owner will be notified when
    | this threshold is reached.
    |
    */
    'max_consecutive_failures' => env('SCHEDULED_REPORTS_MAX_CONSECUTIVE_FAILURES', 3),
];
