<?php

declare(strict_types=1);

use Chronicle\Encryption\LocalKeyEncryptionProvider;
use Chronicle\Entry\Entry;
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
    | 'eloquent' / 'database' - Synchronous write via Laravel's database layer. Default.
    | 'queued' - Async write via queue (single-worker required).
    | 'array' - In-memory. For testing only.
    | 'null' - Discards all entries silently. For testing or local dev.
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
    | application - the recommended production setup.
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
        'subject_keys' => env('CHRONICLE_TABLE_SUBJECT_KEYS', 'chronicle_subject_keys'),
        'legal_holds' => env('CHRONICLE_TABLE_LEGAL_HOLDS', 'chronicle_legal_holds'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent Models
    |--------------------------------------------------------------------------
    |
    | The Eloquent model Chronicle uses for audit entries. Point this at your
    | own subclass of Chronicle\Entry\Entry to add accessors, relationships, or
    | casts. The override MUST extend Chronicle\Entry\Entry so the immutability
    | guarantee and hash-chain contract are preserved. Leave as the default for
    | identical behavior to previous versions.
    |
    */
    'models' => [
        'entry' => Entry::class,
    ],

    'signing' => [
        /*
        |----------------------------------------------------------------------
        | Signing Enforcement
        |----------------------------------------------------------------------
        |
        | When true, Chronicle will throw a RuntimeException at boot if the
        | active signing key cannot be resolved (e.g. missing private key).
        | Only the active key is validated - verify-only keys are not checked.
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
        |   provider   - a class implementing Chronicle\Contracts\SigningProvider
        |   algorithm  - e.g. 'ed25519', 'ecdsa-p256'
        |   public_key - base64-encoded public key (always required)
        |   private_key - base64-encoded private key (omit or null for verify-only)
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

    'anchoring' => [
        /*
        |----------------------------------------------------------------------
        | External Anchoring
        |----------------------------------------------------------------------
        |
        | Opt-in. When enabled, each new checkpoint is anchored with every
        | configured provider after the checkpoint transaction commits. Anchor
        | failures never roll a checkpoint back.
        |
        */
        'enabled' => env('CHRONICLE_ANCHORING_ENABLED', false),

        // Optional queue/connection for AnchorCheckpointJob (null = default).
        'queue' => env('CHRONICLE_ANCHORING_QUEUE'),

        // name => ['provider' => class, ...provider config]
        'providers' => [
            // 'rfc3161' => [
            //     'provider' => \Chronicle\Anchoring\Rfc3161TimestampAnchor::class,
            //     'tsa_url' => env('CHRONICLE_TSA_URL'),
            //     'tsa_certificate' => env('CHRONICLE_TSA_CERTIFICATE'), // path to PEM
            // ],
        ],
    ],

    'encryption' => [
        /*
        |----------------------------------------------------------------------
        | Payload Encryption (Crypto-Shredding)
        |----------------------------------------------------------------------
        |
        | Opt-in. When enabled, the configured payload fields are encrypted
        | under a per-subject DEK before hashing, so destroying a subject's
        | key (GDPR Art. 17 erasure) renders their content permanently
        | unreadable while the ledger still verifies. Disabled => behaviour is
        | identical to pre-1.12.
        |
        */
        'enabled' => env('CHRONICLE_ENCRYPTION_ENABLED', false),

        // PII-bearing payload fields encrypted per subject DEK (decision D3).
        'fields' => ['metadata', 'context', 'diff'],

        /*
        | The Key Encryption Key. The default local provider derives the KEK
        | from CHRONICLE_ENCRYPTION_KEY (a dedicated base64 32-byte key - NOT
        | the app key). Swap `provider` for a KMS-backed implementation to keep
        | the KEK outside the app. `id` is recorded per subject key for KEK
        | rotation.
        */
        'kek' => [
            'provider' => LocalKeyEncryptionProvider::class,
            'key' => env('CHRONICLE_ENCRYPTION_KEY'),
            'id' => env('CHRONICLE_ENCRYPTION_KEK_ID', 'local'),
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

        // Optional context resolvers - uncomment to enable:
        // \Chronicle\Context\EnvironmentContextResolver::class,
        // \Chronicle\Context\RequestContextResolver::class,
        // \Chronicle\Context\HostContextResolver::class,
        // \Chronicle\Context\ProcessContextResolver::class,
        // \Chronicle\Context\QueueContextResolver::class,

        // Optional policies - uncomment to enable:
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
    | Note: `middleware` is a plain PHP array - it is not driven by an env var
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
