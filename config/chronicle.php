<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Driver
    |--------------------------------------------------------------------------
    |
    | The driver Chronicle uses to persist audit entries. Built-in drivers:
    |
    |   'eloquent' — Synchronous write via Laravel's database layer. Default.
    |   'queue'    — Dispatches a queued job. Opt-in async path.
    |   'array'    — In-memory. For testing only.
    |   'null'     — Discards all entries silently. For testing or local dev.
    |
    */
    'driver' => env('CHRONICLE_DRIVER', 'eloquent'),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The named database connection Chronicle uses for its tables. Set this if
    | you want Chronicle to use a dedicated database separate from your
    | application — the recommended production setup.
    |
    | When null, the default Laravel connection is used.
    |
    */
    'connection' => env('CHRONICLE_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | The database table names used by Chronicle. Change these before running
    | migrations if the defaults conflict with your schema.
    |
    */
    'tables' => [
        'entries' => env('CHRONICLE_TABLE_ENTRIES', 'chronicle_entries'),
        'checkpoints' => env('CHRONICLE_TABLE_CHECKPOINTS', 'chronicle_checkpoints'),
    ],

    'signing' => [
        'provider' => \Chronicle\Signing\Ed25519SigningProvider::class,
        'key_id' => env('CHRONICLE_KEY_ID', 'chronicle-dev-key'),
        'private_key' => env('CHRONICLE_PRIVATE_KEY', 'YF2y2+F7j3lYF6V+4bA2e8v+7IhV3zQjX9D3a1B9Wl6mXh5L2M6z9F9Gm7cC3g4FQk9Tn4wWq+Z1pT2qg6x1BQ=='),
        'public_key' => env('CHRONICLE_PUBLIC_KEY', 'Z0kzL1Aq7l+5j3g0fPq8jF+7v9LkM8nC1Vt4qP3W9Q8='),
    ],
];
