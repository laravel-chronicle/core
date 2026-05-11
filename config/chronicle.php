<?php

use Chronicle\Signing\Ed25519SigningProvider;
use Chronicle\Validation\ActionValidator;
use Chronicle\Validation\ActorPresenceValidator;
use Chronicle\Validation\CorrelationValidator;
use Chronicle\Validation\DiffStructureValidator;
use Chronicle\Validation\PayloadSerializableValidator;
use Chronicle\Validation\PayloadSizeValidator;
use Chronicle\Validation\SubjectValidator;
use Chronicle\Validation\TagLimitValidator;
use Chronicle\Validation\TagsValidator;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Driver
    |--------------------------------------------------------------------------
    |
    | The driver Chronicle uses to persist audit entries. Built-in drivers:
    |
    | 'eloquent' / 'database' — Synchronous write via Laravel's database layer. Default.
    | 'queued' - Async write via queue (single-worker required).
    | 'array' — In-memory. For testing only.
    | 'null' — Discards all entries silently. For testing or local dev.
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
    | Async Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Used when driver = 'queued'. Chronicle chain hashes are order-sensitive,
    | so this queue MUST be processed by a single worker:
    |
    |   php artisan queue:work --queue=chronicle --tries=1
    |
    | Running multiple workers on this queue will produce chain forks.
    |
    */
    'queue' => [
        'connection' => env('CHRONICLE_QUEUE_CONNECTION'),
        'name' => env('CHRONICLE_QUEUE', 'chronicle'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Used by `chronicle:prune`. Set default_retention_days to null to
    | disable automatic pruning.
    |
    */
    'prune' => [
        'default_retention_days' => env('CHRONICLE_RETENTION_DAYS', 365),
        'respect_checkpoints' => true,
    ],

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
        'provider' => Ed25519SigningProvider::class,
        'key_id' => env('CHRONICLE_KEY_ID', 'chronicle-dev-key'),
        'private_key' => env('CHRONICLE_PRIVATE_KEY'),
        'public_key' => env('CHRONICLE_PUBLIC_KEY'),
        'enforce_on_boot' => env('CHRONICLE_SIGNING_ENFORCE_ON_BOOT', false),
    ],

    'validation' => [
        'action_max_length' => env('CHRONICLE_ACTION_MAX_LENGTH', 255),
        'tag_max_length' => env('CHRONICLE_TAG_MAX_LENGTH', 50),
        'tag_limit' => env('CHRONICLE_TAG_LIMIT', 10),
        'correlation_id_max_length' => env('CHRONICLE_CORRELATION_ID_MAX_LENGTH', 255),
        'max_payload_size' => env('CHRONICLE_MAX_PAYLOAD_SIZE', 65536),
    ],

    'policy' => [
        'allowed_actions' => [],
        'forbidden_actions' => [],
        'rate_limit' => [
            'max_entries' => 60,
            'decay_seconds' => 60,
        ],
        'time_window' => [
            'start' => '00:00',
            'end' => '23:59:59',
            'days' => [],
            'timezone' => null,
        ],
        'required_context_keys' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Entry Extensions
    |--------------------------------------------------------------------------
    |
    | Optional extension classes that execute before Chronicle's built-in
    | canonicalize/hash/chain/persist processors.
    |
    | Extensions must implement Chronicle\Contracts\EntryExtension.
    |
    */
    'extensions' => [
        ActorPresenceValidator::class,
        SubjectValidator::class,
        ActionValidator::class,
        CorrelationValidator::class,
        TagLimitValidator::class,
        TagsValidator::class,
        DiffStructureValidator::class,
        PayloadSerializableValidator::class,
        PayloadSizeValidator::class,

        // Optional context resolvers — uncomment to enable:
        // \Chronicle\Context\EnvironmentContextResolver::class,
        // \Chronicle\Context\RequestContextResolver::class,
        // \Chronicle\Context\HostContextResolver::class,
        // \Chronicle\Context\ProcessContextResolver::class,
        // \Chronicle\Context\QueueContextResolver::class,

        // Optional policies — uncomment to enable:
        // \Chronicle\Policy\OnlyAuthenticatedUsersPolicy::class,
        // \Chronicle\Policy\AllowedActionsPolicy::class,
        // \Chronicle\Policy\ForbiddenActionsPolicy::class,
        // \Chronicle\Policy\RateLimitPolicy::class,
        // \Chronicle\Policy\TimeWindowPolicy::class,
        // \Chronicle\Policy\ContextPolicy::class,
    ],
];
