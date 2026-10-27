<?php

return [
    'retention' => [
        'idle_minutes' => (int) env('PUBLIC_TRIAL_IDLE_MINUTES', 30),
        'absolute_hours' => (int) env('PUBLIC_TRIAL_ABSOLUTE_HOURS', 24),
        'legacy_grace_minutes' => (int) env('PUBLIC_TRIAL_LEGACY_GRACE_MINUTES', 30),
        'cleanup_enabled' => (bool) env('PUBLIC_TRIAL_CLEANUP_ENABLED', false),
        'cleanup_batch_size' => 100,
        'cleanup_max_candidates' => 1000,
        'cleanup_max_seconds' => 60,
    ],
    'creation_limits' => [
        'ten_minutes' => ['attempts' => 10, 'seconds' => 600],
        'hour' => ['attempts' => 50, 'seconds' => 3600],
    ],
    'source_test_ulid' => env('PUBLIC_TRIAL_SOURCE_TEST_ULID', '01KW8MCQXY5V528GATFHM44YK8'),
    'tests' => [
        'reading_writing' => env('PUBLIC_TRIAL_RW_TEST_ULID', '01M5WJD3DSPQJ3XTA2NY4Z035S'),
        'math' => env('PUBLIC_TRIAL_MATH_TEST_ULID', '01M5WJD3DSPQJ3XTA2NY4Z035T'),
        'full' => env('PUBLIC_TRIAL_FULL_TEST_ULID', '01KW8MCQXY5V528GATFHM44YK8'),
    ],
];
