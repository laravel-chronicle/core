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
        'default_retention_days' => env('CHRONICLE_RETENTION_DAYS'),
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
        'checkpoint_anchors' => env('CHRONICLE_TABLE_CHECKPOINT_ANCHORS', 'chronicle_checkpoint_anchors'),
        'verification_runs' => env('CHRONICLE_TABLE_VERIFICATION_RUNS', 'chronicle_verification_runs'),
    ],

    'signing' => [
        /*
        |----------------------------------------------------------------------
        | Signing Enforcement
        |----------------------------------------------------------------------
        |
        | When true, Chronicle will throw a RuntimeException at boot if the
        | active signing key cannot be resolved (e.g. missing private key).
        | Only the active key is validated — verify-only keys are not checked.
        |
        */
        'enforce_on_boot' => env('CHRONICLE_SIGNING_ENFORCE_ON_BOOT', false),

        /*
        |----------------------------------------------------------------------
        | Active Key
        |----------------------------------------------------------------------
        |
        | The ID of the key used to sign new checkpoints and exports.
        | Must match a key defined in `signing.keys`.
        |
        */
        'active' => env('CHRONICLE_ACTIVE_KEY', 'chronicle-dev-key'),

        /*
        |----------------------------------------------------------------------
        | Key Ring
        |----------------------------------------------------------------------
        |
        | All signing keys, past and present. Chronicle resolves the correct
        | verifier from this ring using the (algorithm, key_id) stored in each
        | checkpoint/export, so retired keys must remain here with at least
        | their public_key to allow historic verification.
        |
        | Each entry requires:
        |   provider   — a class implementing Chronicle\Contracts\SigningProvider
        |   algorithm  — e.g. 'ed25519', 'ecdsa-p256'
        |   public_key — base64-encoded public key (always required)
        |   private_key — base64-encoded private key (omit or null for verify-only)
        |
        */
        'keys' => [
            'chronicle-dev-key' => [
                'provider' => Ed25519SigningProvider::class,
                'algorithm' => 'ed25519',
                'private_key' => env('CHRONICLE_PRIVATE_KEY'),
                'public_key' => env('CHRONICLE_PUBLIC_KEY'),
            ],
        ],
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

    /*
    |--------------------------------------------------------------------------
    | Web UI
    |--------------------------------------------------------------------------
    |
    | Chronicle ships an optional read-only Blade interface. It is disabled
    | by default. Set CHRONICLE_UI_ENABLED=true to activate it.
    |
    | Routes are registered under `prefix` and protected by `middleware`.
    | The default middleware stack requires an authenticated web session.
    | Add your own guards (e.g. 'can:view-chronicle') to the array.
    | Note: `middleware` is a plain PHP array — it is not driven by an env var
    | so that arbitrary middleware class names can be added.
    |
    | `per_page` controls how many entries appear per page on the index.
    |
    */
    'ui' => [
        'enabled' => env('CHRONICLE_UI_ENABLED', false),
        'prefix' => env('CHRONICLE_UI_PREFIX', 'chronicle'),
        /*
        |--------------------------------------------------------------------------
        | UI Middleware
        |--------------------------------------------------------------------------
        | The 'can:view-chronicle' gate must be defined in your AuthServiceProvider.
        | Example:
        |   Gate::define('view-chronicle', fn ($user) => $user->isAdmin());
        |
        | Set to ['web', 'auth'] to allow any authenticated user.
        */
        'middleware' => ['web', 'auth', 'can:view-chronicle'],
        'per_page' => env('CHRONICLE_UI_PER_PAGE', 25),
    ],
];
