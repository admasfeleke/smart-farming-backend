<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'inference' => [
        'enabled' => env('INFERENCE_ENABLED', false),
        'base_url' => env('INFERENCE_BASE_URL'),
        'token' => env('INFERENCE_TOKEN'),
        'timeout_seconds' => (int) env('INFERENCE_TIMEOUT_SECONDS', 15),
        'retry_attempts' => (int) env('INFERENCE_RETRY_ATTEMPTS', 2),
        'retry_sleep_ms' => (int) env('INFERENCE_RETRY_SLEEP_MS', 500),
        'strict_precheck' => (bool) env('INFERENCE_STRICT_PRECHECK', false),
        'endpoint' => env('INFERENCE_ENDPOINT', '/predict'),
        'health_endpoint' => env('INFERENCE_HEALTH_ENDPOINT', '/health'),
        'min_confidence' => (float) env('INFERENCE_MIN_CONFIDENCE', 0.75),
        'not_plant_threshold' => (float) env('INFERENCE_NOT_PLANT_THRESHOLD', 0.80),
        'expert_high_confidence_threshold' => (float) env('INFERENCE_EXPERT_HIGH_CONFIDENCE_THRESHOLD', 0.85),
        'review_only_mode' => env('INFERENCE_REVIEW_ONLY_MODE', false),
        'expected_model_version' => trim((string) env('INFERENCE_EXPECTED_MODEL_VERSION', '')),
        'expected_pixel_scale' => trim(strtolower((string) env('INFERENCE_EXPECTED_PIXEL_SCALE', ''))),
        'expected_labels_count' => (int) env('INFERENCE_EXPECTED_LABELS_COUNT', 0),
        'kpi_window_days' => max(1, (int) env('INFERENCE_KPI_WINDOW_DAYS', 7)),
        'max_uncertain_rate' => (float) env('INFERENCE_MAX_UNCERTAIN_RATE', 0.25),
        'max_family_mismatch_rate' => (float) env('INFERENCE_MAX_FAMILY_MISMATCH_RATE', 0.10),
        'max_reviewing_backlog' => (int) env('INFERENCE_MAX_REVIEWING_BACKLOG', 200),
        'max_reviewing_age_hours' => max(1, (int) env('INFERENCE_MAX_REVIEWING_AGE_HOURS', 24)),
        'enforce_crop_scope' => (bool) env('INFERENCE_ENFORCE_CROP_SCOPE', false),
        'supported_crop_families' => array_values(array_filter(array_map(
            static fn (string $value): string => trim(strtolower($value)),
            explode(',', (string) env('INFERENCE_SUPPORTED_CROP_FAMILIES', ''))
        ))),
        'release_gate' => [
            'controlled' => [
                'max_uncertain_rate' => (float) env('INFERENCE_CONTROLLED_MAX_UNCERTAIN_RATE', 0.25),
                'max_family_mismatch_rate' => (float) env('INFERENCE_CONTROLLED_MAX_FAMILY_MISMATCH_RATE', 0.10),
                'max_reviewing_backlog' => (int) env('INFERENCE_CONTROLLED_MAX_REVIEWING_BACKLOG', 200),
                'max_reviewing_age_hours' => max(1, (int) env('INFERENCE_CONTROLLED_MAX_REVIEWING_AGE_HOURS', 24)),
                'min_ai_reports_window' => (int) env('INFERENCE_CONTROLLED_MIN_AI_REPORTS_WINDOW', 100),
                'min_reports_per_crop' => (int) env('INFERENCE_CONTROLLED_MIN_REPORTS_PER_CROP', 20),
            ],
            'autonomous' => [
                'max_uncertain_rate' => (float) env('INFERENCE_AUTONOMOUS_MAX_UNCERTAIN_RATE', 0.08),
                'max_family_mismatch_rate' => (float) env('INFERENCE_AUTONOMOUS_MAX_FAMILY_MISMATCH_RATE', 0.03),
                'max_reviewing_backlog' => (int) env('INFERENCE_AUTONOMOUS_MAX_REVIEWING_BACKLOG', 20),
                'max_reviewing_age_hours' => max(1, (int) env('INFERENCE_AUTONOMOUS_MAX_REVIEWING_AGE_HOURS', 8)),
                'min_ai_reports_window' => (int) env('INFERENCE_AUTONOMOUS_MIN_AI_REPORTS_WINDOW', 400),
                'min_reports_per_crop' => (int) env('INFERENCE_AUTONOMOUS_MIN_REPORTS_PER_CROP', 50),
            ],
        ],
    ],

    'mobile_auth' => [
        'allow_non_farmer_login' => (bool) env('MOBILE_ALLOW_NON_FARMER_LOGIN', false),
        'ttl_minutes' => max(1, (int) env('MOBILE_AUTH_TTL_MINUTES', 1440)),
    ],

];
