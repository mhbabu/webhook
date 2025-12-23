<?php

return [
    'inactivity_alert_minutes' => env('INACTIVITY_ALERT_MINUTES', 2), // Default to 2 minutes
    'second_alert_minutes'     => env('SECOND_ALERT_MINUTES', 3), // Default to 3 minutes
    'third_alert_minutes'      => env('THIRD_ALERT_MINUTES', 3), // Default to 3 minutes
    'minutes_limit'            => env('MINUTES_LIMIT', 4), // Default to 3 minutes
];
