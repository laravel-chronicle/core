# Changelog

All notable changes to `laravel-chronicle` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

Semantic versioning applies from **v1.0.0** onwards. Pre-1.0 releases may contain
breaking changes between any two versions — see upgrade notes per version.

## [Unreleased]

### Added

- `UnknownSigningKeyException` (extends `ChronicleException`) thrown by `ConfigKeyRing::resolve()` when no key in the ring matches the requested algorithm and key ID.
- `KeyRing` interface with `active(): SigningProvider`, `resolve(string $algorithm, ?string $keyId): SigningProvider`, and `all(): array` — the registry seam for multi-key signing and rotation.
- `LegacySigningConfigAdapter` detects the pre-1.10 flat `signing` config shape (no `signing.keys` key) and promotes it to a one-entry `signing.keys` ring at runtime. A one-time deprecation notice is logged. Apps on the old flat config upgrade with zero code changes.
- `SigningProviderFactory` builds `SigningProvider` instances via `$container->makeWith($class, ['config' => $keyConfig])`, allowing any provider to declare `array $config` and have additional constructor dependencies (e.g. SDK clients) auto-injected by the container.
- `ConfigKeyRing` implements `KeyRing`: builds providers lazily from `signing.keys` config via `SigningProviderFactory`, caches them by key ID, and resolves by `(algorithm, keyId)` pair. Throws `UnknownSigningKeyException` on a miss.
- `VerificationFailure::UnknownKey` — returned when a checkpoint or export references an algorithm/key ID pair that is not present in the `KeyRing`. Distinct from `CheckpointSignatureInvalid` and `SignatureInvalid`.
- `ComplianceReport::verify(string $reportHash, string $signature, string $algorithm, ?string $keyId): bool` — resolves the verifier via the `KeyRing` so reports signed before key rotation remain verifiable with the original public key.
- `LocalVerifyProvider` — abstract `SigningProvider` base class that implements `verify()` locally via OpenSSL, dispatching on `algorithm()`. Designed for remote-sign / local-verify patterns (e.g. AWS KMS). Subclasses implement `sign()`, `algorithm()`, `keyId()`, and `cachedPublicKeyPem()`.
- `EcdsaSigningProvider` — ECDSA P-256 signing provider. Signs locally with `openssl_sign` (when a private key PEM is present); verifies locally via `LocalVerifyProvider`. `algorithm()` returns `'ecdsa-p256'`. Constructed from array config (`private_key`, `public_key`, `key_id`) for `SigningProviderFactory` / container `makeWith` compatibility.
- `chronicle:key:generate {--id=}` artisan command: generates an Ed25519 keypair and prints the base64-encoded private and public keys plus a ready-to-paste `signing.keys` config entry. Never writes to disk or environment. Warns to store the private key in a secret manager.
- `chronicle:key:list {--with-counts}` artisan command: tabulates all keys in the `signing.keys` ring with their ID, algorithm, provider, and active/verify-only status. `--with-counts` adds a per-key checkpoint count column.

---

### Changed

- `Ed25519SigningProvider` accepts an `array $config` constructor parameter for container-driven construction. When using the array-config path, `private_key` may be omitted or `null` to create a verify-only key. The existing positional constructor (`privateKey`, `publicKey`, `keyId`) is unchanged for direct/test use. `sign()` now throws `RuntimeException` with a clear message when called on a verify-only instance.
- `config/chronicle.php` signing block updated from a single flat key to `signing.active` (the key ID used to sign new artifacts) and `signing.keys[]` (the full key ring). Each key entry specifies `provider`, `algorithm`, `public_key`, and optionally `private_key`. Apps that have published the old flat config upgrade with zero changes via `LegacySigningConfigAdapter`.
- `ChronicleServiceProvider::registerSigning()` rewritten to bind `SigningProviderFactory` and `KeyRing` (singleton). `SigningProvider::class` continues to resolve the active provider so all existing callers (`ExportSigner`, `CheckpointCreator`, etc.) require zero changes. `enforce_on_boot` now validates the active key only — verify-only keys without private material no longer trip it.
- Dropped support for `Laravel 11`
- `IntegrityVerifier` resolves each checkpoint's verifier via `keyRing->resolve($checkpoint->algorithm, $checkpoint->key_id)` instead of the injected active signer. Ledgers whose checkpoints span multiple signing keys (before and after rotation) now verify end-to-end.
- `ExportVerifier` resolves the signing key from `signature.json`'s `algorithm` and `key_id` fields via the `KeyRing`. Exports signed by a retired key continue to verify as long as the public key remains in the ring. An unknown algorithm/key ID pair returns `VerificationFailure::UnknownKey`.
- `ComplianceReport` injects `KeyRing` instead of `SigningProvider`; `generate()` calls `keyRing->active()` for signing. Behavior is identical to callers; the class is now consistent with `IntegrityVerifier` and `ExportVerifier`.
- `composer.json` now explicitly requires `ext-openssl` (already available in all standard PHP environments; needed for `EcdsaSigningProvider`).

---

## [1.9.1] - 2026-06-03

### Added

- `VerificationFailure` enum centralises all verification failure code strings. Static analysis can now catch typos in failure code comparisons.
- `KeyRing` interface with `active(): SigningProvider`, `resolve(string $algorithm, ?string $keyId): SigningProvider`, and `all(): array` — the registry seam for multi-key signing and rotation.

---

### Changed

- `ChronicleUiController` no longer calls `$this->middleware()` in its constructor — the pattern
  was deprecated in Laravel 11. Middleware is now declared on the route group in `routes/ui.php`.
- Removed intermediate `@var view-string` variable annotations in `ChronicleUiController`. Larastan
  does not recognise `view-string` as a standalone variable type; the three known false-positive
  errors are now suppressed via `phpstan-baseline.neon`.
- `ChronicleUiController::stats()` no longer duplicates the query logic from `LedgerStats::compute()`.
  The stat action now delegates entirely to `LedgerStats::compute()`, so any fixes or improvements
  to `LedgerStats` are automatically reflected in the UI.
- Deleted `ChronicleServiceProvider::assertSigningConfiguration()` — the method had no callers and
  its enforcement logic had already been consolidated into `registerSigning()`. Its presence falsely
  implied a boot-time signing check existed.
- `chronicle:prune` dry-run now uses a single `SELECT COUNT(*), MIN(created_at), MAX(created_at)`
  query instead of three separate round-trips.
- `chronicle.prune.default_retention_days` now defaults to `null` (was `365`). Running
  `chronicle:prune` with no arguments on a fresh installation no longer silently deletes entries older
  than one year — an explicit retention policy must be configured. Set
  `CHRONICLE_RETENTION_DAYS=365` to restore the previous behavior. **(Breaking change for
  `chronicle.prune.default_retention_days`)**
- `IntegrityVerifier::verify()` no longer runs a separate `COUNT(*)` query before streaming
  entries. The count was used only for the progress-bar callback, which `VerifyEntryCommand`
  already computes independently. The `$onProgress` callback signature changes from
  `callable(int $processed, int $total)` to `callable(int $processed)`.
- **Breaking:** `LedgerQuery::paginate()` renamed to `LedgerQuery::cursorPaginate()`. The method
  has always returned a `CursorPaginator`; the old name implied `LengthAwarePaginator` (offset-based)
  never which it was. Update any call to `Chronicle::query()->paginate()` to
  `Chronicle::query()->cursorPaginate()`.
- `ChronicleModelObserver` now has a `protected array $ignoredFields = []` property that subclasses
  can override to exclude additional fields from the updated diff. Previously the ignore list was
  hardcoded to `['created_at', 'updated_at']` with no extension point, unlike `HasChronicle` which
  supports a per-model `$chronicleIgnore` property.
- `ComplianceReport::generate()` no longer constructs `ComplianceReportResult` twice. Previously it
  built a stub result with `html: ''`, passed it to `renderHtml()`, then rebuilt the result with the
  real HTML. Data is now collected once, HTML is rendered from the raw fields, and the result is
  constructed once.
- `chronicle:install` no longer calls `exec()` to open a browser tab when the user accepts the
  GitHub star prompt. The repo URL is printed to the console instead.
- `EntryBuilder::modelChanges()` is now marked `@deprecated`. Use `modelDiff()` instead; the alias
  will be removed in a future major version.
- Added docblock to `ChronicleManager::query()` explaining the intentional bypass of
  `LedgerReaderContract`.
- Policy classes (`AllowedActionsPolicy`, `ForbiddenActionsPolicy`, `RateLimitPolicy`, `ContextPolicy`) now read their config values once in the constructor rather than on every `enforce()` call.
- `ExportVerifier` now uses PHP 8.0+ constructor property promotion, consistent with all other classes in the package.
- `PruneCommand` now builds the base prune query in a single private `buildPruneQuery()` helper, eliminating the three separate identical query constructions.
- `ComplianceReport::collectStats()` now fetches `first_entry_id` and `last_entry_id` in a single `SELECT MIN/MAX` query, reducing four DB round-trips to three.
- Removed `@` error-suppression operator from `ComplianceReport::generate()` file write; PHP warnings now propagate to the configured error handler.
- `HasChronicle` now declares `$chronicleEvents` and `$chronicleIgnore` as trait properties with defaults, removing `property_exists()` duck-typing. Models override these properties normally. Ignored-field detection now uses `static::CREATED_AT` / `static::UPDATED_AT` so custom timestamp constants are respected.
- Diff-building logic extracted to `ModelDiffBuilder::build()` and shared by `HasChronicle` and `ChronicleModelObserver`. Previously both classes maintained identical (and drift-prone) copies.
- `ExportVerifier::decodeJsonFile()` renamed to `tryDecodeJsonFile()` to make the "string return means failure code" contract explicit at the call site.
- Export file names (`entries.ndjson`, `manifest.json`, `signature.json`) are now defined as constants in `ExportFormat` rather than as magic strings in each class.
- `RequestContextResolver` now receives `Illuminate\Http\Request` via constructor injection instead of pulling it from the global container with `app('request')`.
- `ChronicleModelObserver::resolveActor()` return type narrowed from `mixed` to `Model|string`.
- `ChronicleServiceProvider::registerSigning()` now uses `$app['config']->get()` consistently throughout the closure instead of mixing `$app['config']->get()` and the global `config()` helper.
- `src/README.md` removed.
- Chronicle UI default middleware changed from `['web', 'auth']` to `['web', 'auth', 'can:view-chronicle']`. The gate must be defined in your application. Set `chronicle.ui.middleware` back to `['web', 'auth']` to restore the previous permissive default.
- **Breaking:** The export signature now covers a canonical JSON payload containing all manifest fields (`dataset_hash`, `entry_count`, `first_entry_id`, `last_entry_id`, `chain_head`) instead of `dataset_hash` alone. Existing `signature.json` files produced by previous versions will fail verification — re-export to regenerate.
- `Entry` model `@property` docblocks now accurately reflect cast return types: `$tags` is `array<int, string>`, `$diff` is `array<string, array{old: mixed, new: mixed}>|null`, and `$created_at` is `\Carbon\CarbonImmutable`.

---

### Deprecated
- `EntryBuilder::modelChanges()` now emits a `E_USER_DEPRECATED` notice and is marked for removal in v2.0. Use `modelDiff()` instead.

---

### Fixed

- `chronicle:install` now honors the `--migrate` flag to skip the interactive confirmation in non-TTY environments.
- `publishMigrations()` uses a fixed base date (`2026-01-01`) for migration timestamps instead of the current wall clock, making repeated installations produce deterministic file names.
- `chronicle:install` now skips the "publish views" and "star on GitHub" confirmation prompts when `--no-interaction` is set.
- `PersistChronicleEntryJob` was reading `chronicle.database.connection` (non-existent key) instead
  of `chronicle.connection`. On apps with a dedicated Chronicle database connection, the job silently
  wrote to the wrong database, corrupting the chain.
- `Ed25519SigningProvider::__destruct()` called `sodium_memzero()` on `$privateKey` without a null
  guard. If construction threw before assigning the key, the destructor produced a fatal `TypeError`
  at GC time.
- `QueuedDriver::store()` contained a dead dispatch path that coexisted with the identical dispatch
  logic in `ChronicleManager::runCommit()`. Any future refactor that called the full pipeline for all
  drivers would have dispatched two jobs per entry. `store()` now throws `LogicException` to make
  accidental direct calls immediately visible.
- Stat controller null-dereferences `->count` on `null` when `$dailyActivity->get($date)` has no
  entry for a given day. Changed to `?->count ?? 0`.
- `chronicle:prune --before=<invalid>` threw an uncaught `InvalidFormatException` stack trace
  instead of a human-readable CLI error. Bad dates now print "Invalid date format for --before"
  and exit non-zero.
- `Chronicle::fake()` bound `ArrayDriver` as the `StorageDriver` container singleton without
  providing a way to undo it. Tests that ran after a `fake()` test in the same process could
  unknowingly resolve `ArrayDriver` instead of the real driver.
  `ChronicleAssertions::restore()` is now available to clear the binding and reset the manager.
  `ChronicleManager::resetDriver()` is exposed as an `@internal` method for the same purpose.
- `ChronicleServiceProvider` now validates that the configured signing provider implements `SigningProvider` before instantiation, preventing arbitrary class construction from `.env` values.
- `enforce_on_boot = false` now correctly allows the app to boot without signing keys. Signing calls on a misconfigured instance throws at call time, not at boot. A `NullSigningProvider` wraps the original exception so the root cause is preserved.
- `ExportManager` no longer re-hashes the export file after writing it. The dataset hash is now computed inline during the write pass by `EntryExporter`, eliminating the TOCTOU window between writing and hash.
- Export directory is now created with mode `0700` (owner-only) instead of `0755`.
- All hash equality checks in the verification layer now use `hash_equals()` to prevent timing side-channel attacks.
- `ChronicleUiController::show()` validates the `$id` parameter as a ULID before use, returning HTTP 404 for invalid values and preventing unvalidated input from appearing in flash messages.
- `ChainHashEntry` now asserts it is running inside an open database transaction. A `LogicException` is thrown if called outside a transaction, preventing silent chain-fork bugs when custom storage drivers bypass the transaction wrapper.
- `PayloadSerializableValidator` now uses `JSON_THROW_ON_ERROR` per project convention, replacing the `json_last_error_msg()` check that is not safe under concurrency.
- Removed misleading `@var string` annotations on nullable locals in `ComplianceReport::collectStats()`. The variables are `string|null` for an empty ledger.
- `CanonicalPayloadSerializer::normalize()` now explicitly handles `Stringable` objects, backed enums (cast to their value), and unit enums (cast to their name). Non-serializable objects now throw `UnexpectedValueException` instead of silently passing through.
- `VerifyEntryCommand` no longer uses `assert()` (disabled in production by default) to guard against a null entry. An explicit check with a descriptive error message is used instead.
- Chain hash creation and verification now order by `id` only (ULID, monotonically sortable). The previous mixed `created_at + id` ordering could select different predecessor entries when two rows shared an identical `created_at` timestamp, producing false `chain_hash_mismatch` errors.
- `LedgerQuery::stream()` and `LedgerQuery::first()` now apply the same default `ORDER BY id ASC` ordering as `get()` and `cursorPaginate()`. Previously they returned rows in undefined database order.
- `CheckpointCreator` now uses a strict `=== null` check for the chain hash; a corrupt row with `chain_hash = '0'` no longer falsely triggers the "ledger is empty" error.
- `CanonicalPayloadSerializer::isAssoc()` now correctly classifies empty arrays as sequential (returns `false`), matching `json_encode` behaviour.
- `ExportVerifier` now skips blank lines for both the dataset hash and the chain check. Previously blank lines were included in the hash but skipped for chain verification, causing false dataset-hash-mismatch failures on exports with a trailing newline.
- `ChronicleAssertions` now calls `$this->driver->allEntries()` (instance method) instead of `ArrayDriver::all()` (static). The constructor parameter is now functional rather than dead code, enabling test isolation when multiple `ArrayDriver` instances are used.
- `ChronicleModelObserver` now uses `$model::CREATED_AT` and `$model::UPDATED_AT` when building diffs, so models with custom timestamp constants are handled correctly.
- `LedgerStats::compute()` — `dailyActivity()` no longer applies a hardcoded 30-day lower bound when a `$from` bound is supplied. The daily activity window now honors the full requested range.
- `LedgerStats::compute()` — `checkpointCount()` now respects the `$from`/`$to` bounds. Previously it always returned the total checkpoint count across all time.
- `RequestContextResolver` now redacts sensitive parameters (e.g. `access_token`, `token`) from URL fragments in addition to query strings, preventing OAuth implicit-flow and OIDC tokens from being stored verbatim in the audit log.
- `RateLimitPolicy` now uses an atomic increment-then-check pattern (`RateLimiter::hit()` before comparing against the limit) to prevent concurrent requests from briefly exceeding the configured rate limit.
- `SerializesEntryAttributes` now stores a `null` diff as SQL `NULL` instead of the JSON string `"null"`. `WHERE diff IS NULL` queries now correctly identify entries that have no diff.
- `DefaultReferenceResolver` now throws a clear `InvalidArgumentException` when passed an unsaved Eloquent model (one with no primary key), instead of silently producing a reference with a `null` ID.
- `TagsValidator` now rejects tags containing non-printable or non-ASCII characters. This prevents Unicode homoglyph attacks (e.g. Cyrillic characters visually identical to ASCII) from bypassing the tag-uniqueness check.
- `DatabaseDriver` and `ChainHashEntry` now apply the same defensive `DB::connection()` guard used elsewhere in the codebase: a missing or empty `chronicle.connection` config value correctly falls back to the default connection.

---

## [1.9.0] - 2026-05-25

### Added

- **Read-only Blade UI** (`CHRONICLE_UI_ENABLED=true`): an optional web interface for browsing the audit ledger, disabled by default.
  - **Entry index** (`GET /chronicle`): paginated list of entries with filters for action, actor ID, subject type, subject ID, tag, date range, and sort order.
  - **Entry detail** (`GET /chronicle/entries/{id}`): full entry view including payload, tags, correlation ID, hash chain values, and linked checkpoint if present.
  - **Stats** (`GET /chronicle/stats`): aggregate overview — total entry count, oldest/newest entry timestamps, checkpoint count, top 10 actions by frequency, and a 30-day daily activity chart.
- **`chronicle.ui` config block**: controls the web UI.
  - `ui.enabled` (`CHRONICLE_UI_ENABLED`, default `false`) — gate route registration; routes are not registered when disabled.
  - `ui.prefix` (`CHRONICLE_UI_PREFIX`, default `'chronicle'`) — URL prefix for all UI routes.
  - `ui.middleware` (default `['web', 'auth']`) — middleware stack applied to all UI routes.
  - `ui.per_page` (`CHRONICLE_UI_PER_PAGE`, default `25`) — pagination page size for the entry index.
- **`ChronicleUiEnabled` middleware** (`Chronicle\Http\Middleware\ChronicleUiEnabled`): aborts with `404` when `chronicle.ui.enabled` is `false`. Applied automatically to all UI routes.
- **`chronicle:install`** now publishes Blade views to `resources/views/vendor/chronicle/` under the `chronicle-views` tag.
- Named routes: `chronicle.entries.index`, `chronicle.entries.show`, `chronicle.stats`.

---

## [1.8.1] - 2026-05-24

### Changed

- `chronicle:install` now publishes migrations with the current timestamp,
  matching `php artisan make:migration` behaviour. Previously the files were
  copied verbatim via `vendor:publish`, which omitted a date prefix.
  Re-running `chronicle:install --force` republishes with a fresh timestamp;
  later calls without `--force` are skipped when a migration with the
  same base name already exists in `database/migrations`.
- Migrations consolidated: all Chronicle entries columns (payload, chain hash,
  payload hash, checkpoint reference, tags, correlation ID, diff) and indexes
  are now defined in a single `create_chronicle_entries_table` migration file.
  Fresh installations receive one file per table instead of nine incremental files.

---

## [1.8.0] - 2026-05-11

### Added

- **`Chronicle::fake()`**: swaps the active driver to `ArrayDriver`, rebuilds the `EntryPipeline` singleton so `PersistEntry` writes to in-memory storage, and returns a `ChronicleAssertions` helper. Entries committed while faking do not appear in the database. Calling `fake()` a second time flushes the previous batch.
- **`ChronicleAssertions`** (`Chronicle\Testing\ChronicleAssertions`): fluent test assertion helper returned by `Chronicle::fake()`.
  - `assertRecorded(?callable $filter = null)` — asserts at least one entry was recorded (optionally matching a filter).
  - `assertRecordedCount(int $count, ?callable $filter = null)` — asserts exactly N entries were recorded.
  - `assertNothingRecorded()` — asserts no entries were recorded.
  - `assertNotRecorded(callable $filter)` — asserts no entries matching the filter were recorded.
  - `entries()` — returns all recorded entries as a `Collection`.
  - All assertions throw `PHPUnit\Framework\AssertionFailedError` on failure, integrating cleanly with Pest and PHPUnit.
- **`EntryRecorded` event** (`Chronicle\Events\EntryRecorded`): dispatched by `PersistEntry` after an entry is successfully persisted. Carries the persisted `Entry` model (`$event->entry`). Suppressed when `NullDriver` is active (entries are not persisted). When using the `queued` driver the event fires inside the job worker, not during the HTTP request.
- **`EntryRejected` event** (`Chronicle\Events\EntryRejected`): dispatched by `ChronicleManager::commit()` when a `ChronicleException` is thrown (validation failure, policy violation, etc.). Carries the rejection reason (`$event->reason`) and the raw entry payload (`$event->payload`). The exception is always re-thrown after the event fires.
- **`ChronicleModelObserver`** (`Chronicle\Eloquent\ChronicleModelObserver`): base Eloquent observer for recording Chronicle audit entries on third-party models where `HasChronicle` cannot be added directly. Records `created`, `updated`, and `deleted` events. Touch-only `updated` events (only timestamp fields dirty) are silently skipped. The `updated` entry includes a `diff` of changed fields (excluding `created_at` / `updated_at`). All protected methods are overridable: `actionPrefix()`, `resolveActor()`, `recordedEvents()`, `ignoredFields()`, `shouldRecord()`.
- **`Chronicle::observe(string $model, ?string $observer = null)`**: registers `ChronicleModelObserver` (or a custom subclass) for a given model class. Register in a service provider's `boot()`:
  ```php
  Chronicle::observe(Invoice::class);
  Chronicle::observe(Invoice::class, InvoiceObserver::class);
  ```
- **`LedgerQuery::actionPrefix(string $prefix)`**: filters entries whose `action` begins with the given prefix using a `LIKE` query. The prefix is escaped to prevent LIKE injection (`!`, `%`, `_` are all handled). Chainable with other `LedgerQuery` filters.
- **`Entry::scopeActionPrefix(string $prefix)`**: Eloquent query scope equivalent of `LedgerQuery::actionPrefix()`.
- **`LedgerStats::compute(from:, to:)`**: `compute()` now accepts optional `?CarbonInterface $from` and `?CarbonInterface $to` parameters to scope `totalEntries`, `oldestEntryAt`, `newestEntryAt`, `topActions`, and `dailyActivity` to a date range. Checkpoint count remains global. Existing call sites with no arguments are unaffected.

### Changed

- `ChronicleManager::commit()` now wraps the inner commit logic in a `try/catch (ChronicleException)` — dispatches `EntryRejected` before re-throwing.
- `ChronicleManager::runCommit()` extracted from `commit()` as a `protected` method. Silently short-circuits (no pipeline, no event) when the active driver is `NullDriver`.
- `PersistEntry::process()` dispatches `EntryRecorded` after `store()` when `$stored->exists` is `true`.
- `Chronicle` facade docblock updated with `@method` annotations for `fake()` and `observe()`.

### Notes

- Register event listeners in your application's `EventServiceProvider` (or `AppServiceProvider` in Laravel 11+):
  ```php
  Event::listen(EntryRecorded::class, fn ($e) => /* ... */);
  Event::listen(EntryRejected::class, fn ($e) => /* ... */);
  ```
- `Chronicle::fake()` is designed for feature and integration tests. It is not safe to call in production — it mutates the container's `StorageDriver` binding.

---

## [1.7.0] - 2026-05-11

### Added
- **Queued driver** (`CHRONICLE_DRIVER=queued`): moves `ChainHashEntry` and `PersistEntry` to a background job (`PersistChronicleEntryJob`), eliminating the synchronous `lockForUpdate` from the HTTP request thread.
- **`database` driver alias**: `CHRONICLE_DRIVER=database` is now equivalent to `eloquent`.
- **`chronicle:prune` command**: prune entries by age (`--older-than=N`, `--before=DATE`), with `--dry-run` preview and `--force` to include checkpoint-anchored entries.
- **`queue` config block**: `chronicle.queue.connection` and `chronicle.queue.name` control the async queue.
- **`prune` config block**: `chronicle.prune.default_retention_days` and `chronicle.prune.respect_checkpoints`.

### Changed
- `ChronicleManager::commit()` is now queued-driver-aware: detects `QueuedDriver` and dispatches `PersistChronicleEntryJob` instead of running the full pipeline synchronously. Sync pipeline behavior is unchanged.

### Notes
- The Chronicle queue **must be processed by a single worker**. Multiple workers will corrupt the chain hash sequence.
- Entries written via the queued driver are not immediately visible in the database after `commit()`.

---

## [1.6.0] - 2026-05-08

### Added

- `Chronicle::query()` returns a `LedgerQuery` fluent builder for composable, chainable ledger queries. Filter by actor, subject, action, tags, date range, or correlation; terminate with `get()`, `first()`, `count()`, `exists()`, `paginate()`, or `stream()`. Defaults to ledger order (oldest-first) on `get()` and `paginate()` unless `latest()` or `oldest()` is called explicitly.
- `LedgerQuery::withAnyTag(array $tags)` — OR-semantics tag filter; returns entries carrying any of the given tags. Complements the existing `withTags()` which requires all tags to be present.
- `LedgerQuery::actions(array $actions)` — matches entries whose action is any of the given values (`whereIn` semantics).
- `LedgerQuery::since()` and `LedgerQuery::until()` accept both `CarbonInterface` and date strings; an unparseable string throws `InvalidArgumentException`.
- `LedgerStats::compute()` returns a `LedgerStats` value object with aggregate ledger statistics: total entry count, oldest/newest entry timestamps, checkpoint count, top 10 actions by frequency, and daily activity for the last 30 days. All queries run against Chronicle's configured DB connection via the query builder (no Eloquent overhead).
- `chronicle:stats` Artisan command displays ledger statistics as a formatted text report. Pass `--json` for machine-readable output suitable for monitoring or scripting.
- `chronicle:show {id}` Artisan command displays the full detail of a single Chronicle entry by ULID: actor, action, subject, tags, correlation ID, checkpoint ID, payload/chain hashes, metadata, context (nested keys flattened with dot notation), and diff (old/new per field). Exits 1 with an error message when the entry is not found.

### Fixed

- `Chronicle` facade `@method` annotation for `currentCorrelation()` was incorrectly declared as `currentCorrelationId()`. Corrected to match the actual `ChronicleManager` method name.

---

## [1.5.0] - 2026-05-07

### Added

- Added `Chronicle\Eloquent\HasChronicle` trait for Eloquent models. Adding the trait to a model automatically records Chronicle audit entries for `created`, `updated`, and `deleted` Eloquent events with no configuration required.
- **Default actor:** the currently authenticated user (`Auth::user()`), falling back to `system` when unauthenticated.
- **Default action prefix:** snake_case class basename (e.g. `BlogPost` → `blog_post.created`).
- **`$chronicleIgnore`:** list fields to exclude from the diff recorded on `updated` (the entry is still recorded; just those field changes are omitted).
- **`$chronicleEvents`:** restrict which events are recorded; set to `[]` to silence Chronicle for that model entirely.
- **`chronicleActor()`:** override to return any Chronicle-resolvable actor (Eloquent model, object with `$id`, or `'system'`).
- **`chronicleActionPrefix()`:** override to return a custom action prefix string.
- `chronicle:report {path}` command generates a signed, tamper-evident HTML compliance report for the audit ledger. The report contains entry count, chain head, reporting period, an SHA-256 report hash, and an Ed25519 signature block. Optional `--from` and `--to` flags restrict the report to a date range. The underlying `Chronicle\Reports\ComplianceReport` service is available for programmatic use.
- `chronicle:verify --entry=<id>` spot-checks a single ledger entry — verifies its payload hash and chain hash without scanning the full ledger. Exits `0` on success, `1` on tampering or missing entry, and displays the entry's action, subject, actor, and timestamp alongside the verification result. The underlying `Chronicle\Verification\EntryVerifier` service is available for programmatic use.

---

## [1.4.1] - 2026-05-07

### Security

- `ExportVerifier` now re-derives `payload_hash` from the exported `payload` field per-entry, closing a gap where a tampered payload with an unmodified `payload_hash` field would pass export verification.
- `Ed25519SigningProvider` now zeroes the private key in memory via `sodium_memzero()` when the object is destroyed, reducing key exposure for compliance-sensitive deployments.
- `RequestContextResolver` now truncates `user_agent` strings to 512 characters and redacts sensitive query parameters (`password`, `token`, `api_token`, `secret`, `key`, `access_token`) from the logged URL, preventing column overflow and sensitive data leakage in audit records.
- `ExportManager::export()` now documents the write-then-hash race condition contract: the export path must not be writable by other processes between the `entries.ndjson` write and the subsequent `hashFile()` call.

---

### Added

- `LedgerReader` contract now declares `workflow()`, `withTag()`, and `withTags()` — methods that were already implemented in `EloquentLedgerReader` but missing from the interface.
- `DriverResolver::has(string $driver): bool` — lets callers check whether a custom driver is registered before calling `extend()`, preventing the duplicate-registration exception in third-party service providers.
- Added `lockForUpdate()` to `ChainHashEntry` to prevent duplicate chain hash records under concurrent writes.
- Added `@method` annotations to the `Chronicle` facade for IDE completion.
- Added null-check guard in `ExportCommand` for the chain head when the ledger is empty.
- Added `$model->exists = true` to `ArrayDriver::store()` so the returned `Entry` model correctly reports as persisted.

---

### Changed

- `DefaultReferenceResolver` now throws `InvalidArgumentException` when passed a scalar value (string, int, etc.), rather than silently using PHP's `gettype()` return value (e.g., `"integer"`) as the actor/subject type. Pass an Eloquent model or an object with a public `$id` property instead.
  **Upgrade note:** Any code passing raw scalars directly as actor or subject — other than the reserved `'system'` string handled by `EntryBuilder` — must be updated to pass a model or a plain object with a public `$id`.
- `ChronicleServiceProvider` no longer validates signing configuration in `boot()`. The check is now deferred to the first resolution of `SigningProvider` from the container, eliminating key-decoding overhead on every request in apps that do not use Chronicle signing on every route.
- `CanonicalizePayload` pipeline stage now calls `CanonicalPayloadSerializer::normalize()` directly instead of serializing to JSON and immediately decoding back to an array, eliminating a redundant encode/decode round-trip. `CanonicalPayloadSerializer::normalize()` is now `public`.
- `EntryBuilder::normalizeDiff()` now throws `InvalidArgumentException` when a diff entry is not an array with `old`/`new` keys, rather than silently replacing the value with `['old' => null, 'new' => null]`.
- Extracted `SerializesEntryAttributes` trait from `ArrayDriver`, `DatabaseDriver`, and `NullDriver`, replacing three identical copies of the JSON-encoding attribute array with a single shared `toEntryAttributes()` implementation. All `json_encode` calls in the trait use `JSON_THROW_ON_ERROR`.
- `EntryExportResult` now uses constructor property promotion consistently for all four properties.
- `ChronicleManager::swapDriver()` is now annotated `@internal` to discourage use outside test infrastructure.

---

### Fixed

- `ExportVerifier` now correctly catches payload tampering where the `payload` field is modified but `payload_hash` is left unchanged — previously this would pass verification undetected.
- `TimeWindowPolicy` now stores the parsed `Carbon` time bounds at construction rather than reparsing them on every `enforce()` call, eliminating a theoretical null-deref if `parseTime()` returned `null` inside `enforce()`.
- `EntryBuilder::change()` now calls `ksort()` after each field assignment, guaranteeing alphabetical diff key ordering consistent with `diff()`.
- `EntryExporter` now includes `metadata` and `context` as top-level fields in the exported NDJSON, making the export format consistent with the database schema.
- `Entry::scopeWorkflow()` now appends an explicit `ESCAPE '!'` clause to the `LIKE` query, ensuring correct behavior on PostgreSQL and other databases where the default LIKE escape character differs from MySQL.

---

## [1.4.0] - 2026-03-20

### Added

- Added `Chronicle\Contracts\EntryPolicy` interface with a single `enforce(PendingEntry): void` method as the public contract for policy extensions.
- Added `Chronicle\Policy\AbstractPolicy` abstract base class implementing `EntryExtension` in the `POLICY` stage; seals `stage()` and `process()` so concrete policies only implement `enforce()` and can never accidentally mutate the entry.
- Added `Chronicle\Exceptions\PolicyViolationException` base exception extending `ChronicleException`; callers who catch `ChronicleException` receive policy rejections without changes.
- Added `OnlyAuthenticatedUsersPolicy` to reject entries when no authenticated user session is active. Skips automatically in console and queue worker contexts via `runningInConsole()`.
- Added `AllowedActionsPolicy` to restrict entry recording to a configured list of allowed action patterns. Supports `Str::is()` wildcard syntax (e.g. `user.*`). An empty allowlist rejects every action.
- Added `ForbiddenActionsPolicy` to prevent recording of entries matching a configured denylist of action patterns. Supports the same wildcard syntax. An empty denylist passes all actions.
- Added `RateLimitPolicy` to cap entry creation per actor per time window. Uses Laravel's `RateLimiter` facade with a hashed cache key (`sha1("{actor_type}/{actor_id}")`) safe for all cache backends. Throws `RateLimitExceededException` with retry-after seconds.
- Added `TimeWindowPolicy` to restrict entry recording to configured hours and days of the week. Uses Carbon for timezone-aware comparison. Midnight-spanning windows are not supported; `start >= end` throws `\InvalidArgumentException` at construction time.
- Added `ContextPolicy` to enforce required top-level keys in the entry's `context` attribute. Treats `null` or non-array context as empty. An empty required-keys list is a no-op.
- Added six `PolicyViolationException` subclasses: `UnauthenticatedActorException`, `ActionNotAllowedException`, `ActionForbiddenException`, `RateLimitExceededException`, `OutsideTimeWindowException`, and `RequiredContextMissingException`, each with named constructors.
- Added `policy` configuration block in `config/chronicle.php` with defaults for all six built-in policies. All policies are registered as commented-out entries in the `extensions` array for discoverability.
- Added `AbstractPolicyTest`, `OnlyAuthenticatedUsersPolicyTest`, `AllowedActionsPolicyTest`, `ForbiddenActionsPolicyTest`, `RateLimitPolicyTest`, `TimeWindowPolicyTest`, `ContextPolicyTest`, and `CombinedPoliciesTest` (Feature).

---

## [1.3.0] - 2026-03-19

### Added

- Added `Chronicle\Contracts\ContextResolver` interface with `contextKey()` and `resolve()` methods as the public contract for context resolver extensions.
- Added `Chronicle\Context\AbstractContextResolver` abstract base class implementing `EntryExtension` in the `RESOLVE_CONTEXT` stage; handles the null-skip and namespaced context merge so concrete resolvers only define `contextKey()` and `resolve()`.
- Added `EnvironmentContextResolver` to attach Laravel environment information (`name`, `debug`) to every entry under `context.environment`.
- Added `RequestContextResolver` to attach HTTP request information (`ip_address`, `user_agent`, `url`, `method`, `request_id`) under `context.request`. Skips silently when running in a console or queue context. `request_id` reads the `X-Request-ID` header when present; otherwise it generates a stable UUID stored in request attributes, so all Chronicle entries within the same HTTP request share the same generated ID.
- Added `HostContextResolver` to attach the server hostname under `context.host`. Records an empty string if `gethostname()` fails.
- Added `ProcessContextResolver` to attach runtime process information (`id`, `runtime`, `version`) under `context.process`.
- Added `QueueContextResolver` to attach queue job metadata (`job_id`, `connection`, `queue`) under `context.queue`. Skips silently when no queue job is active.
- Added `QueueJobContext` singleton that tracks the currently executing queue job. `ChronicleServiceProvider` populates it via `JobProcessing`, `JobProcessed`, `JobFailed`, and `JobExceptionOccurred` listeners — no changes to application jobs required.
- All context resolvers are **opt-in**. `config/chronicle.php` includes them as commented-out entries for discoverability.
- Added `AbstractContextResolverTest` covering: `RESOLVE_CONTEXT` stage, null-resolve skip, context key assignment, existing-key preservation, non-array context coercion, and duplicate-resolver overwrite behavior.
- Added `QueueJobContextTest` covering: initial null state, set/current/clear lifecycle, and replacement on double-set.
- Added `EnvironmentContextResolverTest` covering: context key, stage, data shape, debug bool cast, fallback on missing env, and existing-key preservation.
- Added `RequestContextResolverTest` covering: context key, stage, console skip, HTTP data shape, `X-Request-ID` header usage, UUID generation, UUID stability across entries in the same request, and existing-key preservation.
- Added `HostContextResolverTest` covering: context key, stage, hostname recording, `gethostname()` false-return (empty string), and existing-key preservation.
- Added `ProcessContextResolverTest` covering: context key, stage, data shape, `id` as int, and existing-key preservation.
- Added `QueueContextResolverTest` covering: context key, stage, no-job skip, queue data shape, and existing-key preservation.

---

## [1.2.0] - 2026-03-18

### Added

- Added `ActorPresenceValidator` to enforce that persisted entries always include both `actor_type` and `actor_id`.
- Added validation coverage for missing, blank, and `system` actor references as part of PR #74.
- Added `ActionValidator` as a built-in entry extension to enforce Chronicle action naming rules before persistence.
- Added `InvalidActionException` for explicit failures when actions are not strings, exceed the configured maximum length, or do not use two-segment dot notation such as `domain.event`.
- Added `chronicle.validation.action_max_length` configuration to control the maximum allowed action length.
- Added `SubjectValidator` as a built-in entry extension to enforce that persisted entries always carry a valid `subject_type` and `subject_id`.
- `SubjectValidator` allows entries produced by system actors (`actor_type=system`) to omit the subject, supporting system-level events that do not act on a specific entity.
- Added `PayloadSerializableValidator` as a built-in entry extension to ensure user-supplied payload fields (`metadata`, `context`, `diff`) can be deterministically serialized to JSON. Rejects closures, resources, all objects (including `JsonSerializable` implementations), and non-serializable scalars such as `INF` and `NAN`.
- Added `UnserializablePayloadException` with named constructors `containsClosure()`, `containsResource()`, `containsObject()`, and `notJsonSerializable()` for precise failure reporting.
- Added `TagsValidator` as a built-in entry extension to validate the `tags` attribute. Enforces that tags are an array, contain only non-empty strings, are unique (case-sensitive), and each tag respects the configurable `chronicle.validation.tag_max_length` limit (default 50 characters, env `CHRONICLE_TAG_MAX_LENGTH`).
- Added `InvalidTagsException` with named constructors `mustBeArray()`, `mustContainOnlyStrings()`, `mustNotBeEmpty()`, `mustBeUnique()`, and `tagExceedsMaxLength()` for precise failure reporting.
- Added `TagLimitValidator` as a built-in entry extension to cap the number of tags per entry. Rejects entries whose `tags` array exceeds the configurable `chronicle.validation.tag_limit` (default 10, env `CHRONICLE_TAG_LIMIT`).
- Added `InvalidTagsException::exceedsTagLimit()` factory method for precise tag-count failure reporting.
- Added `CorrelationValidator` as a built-in entry extension to validate the optional `correlation_id` field. Accepts `null` (no correlation); when present, enforces that the value is a non-blank string within the configurable `chronicle.validation.correlation_id_max_length` limit (default 255 characters, env `CHRONICLE_CORRELATION_ID_MAX_LENGTH`).
- Added `InvalidCorrelationIdException` with named constructors `mustBeString()`, `mustNotBeBlank()`, and `exceedsMaxLength()` for precise failure reporting.
- Added `DiffStructureValidator` as a built-in entry extension to validate the optional `diff` field. Accepts `null` (no diff); when present, enforces that the diff is an array where each entry has exactly the keys `old` and `new` (no extras, no missing), and that neither value is a Closure, resource, or object.
- Added `InvalidDiffException` with named constructors `mustBeArray()`, `entryMustBeArray()`, `missingKey()`, `extraKeys()`, `valueContainsClosure()`, `valueContainsResource()`, and `valueContainsObject()` for precise failure reporting.
- Added `PayloadSizeValidator` as a built-in entry extension to prevent extremely large payloads from being persisted. Measures the combined JSON size of `metadata`, `context`, and `diff` after serialization and rejects entries exceeding the configurable `chronicle.validation.max_payload_size` limit (default 65,536 bytes / 64 KB, env `CHRONICLE_MAX_PAYLOAD_SIZE`).
- Added `InvalidPayloadSizeException` with named constructor `exceedsMaxSize()` reporting the actual and maximum byte counts.
- Added `workflow()`, `withTag()`, and `withTags()` methods to `EloquentLedgerReader`, surfacing the corresponding `Entry` query scopes through the `LedgerReader` abstraction.
- Added `failureCode()`, `entryCount()`, `datasetHash()`, and `chainHead()` accessor methods to `ExportVerificationResult`.

---

### Changed

- `EntryBuilder::actor('system')` now normalizes to `actor_type=system` and `actor_id=system` so system-generated entries are stored consistently.
- Chronicle now registers `ActionValidator` by default through `chronicle.extensions`, so invalid action names are rejected during the validation stage.
- Updated tests and examples to use valid dot-notation action names consistently.
- `ExportVerificationResult` properties are now `protected` with accessor methods, matching the encapsulation pattern established by `VerificationResult`. Direct property access (`$result->failure`, `$result->entryCount`, etc.) has been replaced by `$result->failureCode()`, `$result->entryCount()`, `$result->datasetHash()`, and `$result->chainHead()`.
- `ChronicleManager::transaction()` now pushes the correlation ID to the stack for both callback-style and manual-style transactions. Manual transactions must call `$tx->end()` to pop from the stack when complete.
- `IntegrityVerifier` now caches verified checkpoints in memory during a single verification run, reducing checkpoint queries from one per entry to one per unique checkpoint.
- `ExportWriteException::directoryCreationFailed()` now accepts an optional `$reason` parameter and appends the OS-level error message to the exception when available.
- `ExportManager` no longer suppresses the `mkdir` error with `@`; the OS failure reason is captured via `error_get_last()` and included in `ExportWriteException`.
- `ArrayDriver` now `json_encode`s all JSON columns (`payload`, `metadata`, `context`, `tags`, `diff`) and fully populates the returned `Entry` model (`chain_hash`, `checkpoint_id`, `correlation_id`, `diff`, `tags`), matching `EloquentDriver` behaviour.
- `EntryExtensionRegistry::LEGACY_CLASS_MAP` converted from a constant to a `private static array` property with an explicit `@var array<string, string>` annotation to satisfy PHPStan level 9 type checking.

### Fixed

- Fixed `ArrayDriver` incorrectly storing raw PHP arrays in Eloquent cast columns, which caused `json_decode()` on an array to silently return `null` on attribute access.
- Fixed `ArrayDriver` returning incomplete `Entry` models with `tags`, `chain_hash`, `checkpoint_id`, `correlation_id`, and `diff` missing (commented out with wrong legacy key names).

### Testing

- Added `SubjectValidatorTest` with 18 assertions covering: stage and priority ordering, valid subject acceptance, system-actor bypass (null, empty, and blank subjects), rejection of missing/blank/non-string `subject_type` and `subject_id`, rejection when both fields are absent, and case-sensitivity of the system-actor bypass.
- Added `PayloadSerializableValidatorTest` with 27 assertions covering: stage and priority ordering; acceptance of empty, scalar, and nested-array payloads; rejection of closures, resources, and objects (including `JsonSerializable`) in `metadata`, `context`, and `diff`; and rejection of `INF`/`NAN` via the `json_encode` catch-all.
- Added `TagsValidatorTest` with assertions covering: stage and priority ordering, acceptance of empty/single/multiple valid tag arrays, rejection of non-array tag values, rejection of non-string elements (integer, null, boolean, array, object) with offending index in a message, rejection of empty and whitespace-only tags, rejection of duplicates with tag value in a message, case-sensitive uniqueness, and max-length enforcement (boundary and over-limit).
- Added `TagLimitValidatorTest` with assertions covering: stage and priority ordering, acceptance of empty and at-limit tag arrays, silent pass-through of non-array tag values (type enforcement is `TagsValidator`'s concern), rejection when count exceeds the limit (one over and many over), count and limit values present in the exception message, and config-driven limit reading.
- Added `CorrelationValidatorTest` with assertions covering: stage and priority ordering, acceptance of null and valid string values (including UUID style and at-boundary-length), rejection of non-string non-null values with type name in a message, rejection of blank strings, and max-length enforcement with offending value in a message.
- Added `DiffStructureValidatorTest` with assertions covering: stage and priority ordering, acceptance of null and empty diffs and valid single/multi-entry diffs (including scalar and nested-array values), rejection of non-array diffs with type name in message, rejection of non-array entry values with key and type name in message, rejection of entries missing `old` or `new` (verifying `old` is checked first), rejection of extra keys with key and extra-key name in message, and rejection of Closure/resource/object in `old` or `new` values with diff key, side, and (for objects) class name in message.
- Added `PayloadSizeValidatorTest` with assertions covering: stage and priority ordering; acceptance of empty, under-limit, and exactly-at-limit payloads; rejection of payloads one byte over the limit; per-field rejection (metadata, context, diff independently); and exception message content validation (actual bytes and max bytes present).
- Updated `ExportVerifierTest` and `EntryExporterTest` to use `ExportVerificationResult` accessor methods.

---

## [1.1.0] - 2026-03-09

### Added

- Added a typed entry extension pipeline with deterministic stage ordering via `ExtensionStage`:
  - `VALIDATE`
  - `RESOLVE_CONTEXT`
  - `POLICY`
  - `PROCESS`
- Added extension contracts for third-party integration:
  - `Chronicle\Contracts\EntryExtension`
  - `Chronicle\Contracts\PrioritizedEntryExtension`
- Added `EntryExtensionRegistry` with deterministic ordering (`stage`, `priority`, class name, registration index).
- Added `RunExtensions` pipeline processor, executed before canonicalization/hashing/persistence.
- Added runtime extension registration API via `ChronicleManager::extendEntry(...)` and facade support.
- Added `chronicle.extensions` configuration for declarative extension registration.
- Added new `chronicle:install` Artisan command to streamline package setup in host applications.
- `chronicle:install` now publishes both package assets:
  - `chronicle-config`
  - `chronicle-migrations`
- Added interactive install prompts to optionally:
  - run migrations immediately
  - open the Chronicle GitHub repository for starring
- Added a progress bar phase (`Verifying entries`) to `chronicle:verify` for better visibility during long ledger verification runs.

---

### Changed

- Entry processing flow now supports optional extension hooks with zero behavioral impact when no extensions are registered.
- Updated README installation instructions to use the single installation command flow (`php artisan chronicle:install --migrate`).
- Improved `chronicle:verify` CLI output with clearer verification steps and final status messaging.

---

### Testing

- Added unit coverage for extension pipeline ordering and no-op behavior when the registry is empty.
- Added and updated coverage for extension registration via service container/facade/manager.
- Added and updated feature coverage for `chronicle:install`, including:
  - command registration assertion
  - interactive prompt handling (skip path and run-migrations path)
  - publish output side effects (config and migration files)

---

## [1.0.2] - 2026-03-06

### Fixed

- Fixed install/Artisan bootstrap failure where `InvalidArgumentException: Missing CHRONICLE_PRIVATE_KEY` could still be thrown on fresh apps.
- Root cause: `chronicle:verify` command used constructor injection for `IntegrityVerifier`, which forced early `SigningProvider` resolution during command registration.
- `VerifyEntryCommand` now uses lazy method injection (`handle(IntegrityVerifier $verifier)`) so signer resolution only happens when the verify command is actually executed.

---

### Notes

- If you were blocked on `1.0.1`, this patch removes the remaining early-resolution path.

---

## [1.0.1] - 2026-03-06

### Fixed

- Fixed fresh Laravel install boot failures caused by eager signing validation when `chronicle.signing.enforce_on_boot` was absent at runtime config resolution.
- `ChronicleServiceProvider::assertSigningConfiguration()` now defaults enforcement fallback to `false` when the config key is missing, matching package defaults and preventing `Missing CHRONICLE_PRIVATE_KEY` during install-time package discovery.
- Added regression test coverage for the missing `chronicle.signing.enforce_on_boot` configuration path.

---

### CI

- Updated `run-tests` workflow to inject deterministic base64 test signing keys (`CHRONICLE_PRIVATE_KEY`, `CHRONICLE_PUBLIC_KEY`) at job scope.
- Prevents install-stage failures in CI during Composer package discovery before test runtime config overrides are applied.

---

## [1.0.0] - 2026-03-06

### Added

#### Driver Extension API Hardening

- Added collision safeguards for custom driver registration.
- Reserved built-in drivers (`eloquent`, `array`, `null`) can no longer be overridden.
- Duplicate custom driver registration now fails explicitly.

---

#### Export Write-Path Contracts

- Added `Chronicle\Exceptions\ExportWriteException`.
- Export write flow now enforces explicit failure contracts for:
  - export directory creation
  - entries NDJSON open/encode/write
  - manifest encode/write
  - signature encode/write

---

#### Boot-Time Signing Enforcement Toggle

- Added `chronicle.signing.enforce_on_boot` (`CHRONICLE_SIGNING_ENFORCE_ON_BOOT`).
- Default remains `true`.
- Allows controlled opt-out of signer sanity checks in non-testing environments.

---

#### Multi-Database Migration Rollback CI Coverage

- Added migration rollback semantics test coverage and CI matrix execution for:
  - sqlite
  - mysql
  - pgsql

---

### Changed

#### Export Command Failure Handling

- `chronicle:export` now consistently surfaces command-level failures with:
  - `Export failed.`
  - underlying exception message
  - non-zero exit code (`1`)

---

#### Deterministic Export Verification IO Behavior

- `ExportChainVerifier` and `ExportVerifier` now handle missing/unreadable paths deterministically.
- Removed warning-driven behavior in verifier file access paths.

---

#### Checkpoint Transaction Connection Semantics

- `CheckpointCreator` now executes transactions on `chronicle.connection` instead of implicitly using the default DB connection.
- Ensures checkpoint creation atomicity matches Chronicle storage connection semantics.

---

#### Migration Index Naming

- Added explicit index names in Chronicle migrations.
- Rollback paths now drop indexes by explicit names for deterministic schema operations.

---

### Fixed

- Fixed stale `recorded_at` assumptions in tests/docs by aligning behavior to `created_at`.
- Fixed export-chain failure-path assertions to match stable command output contracts.
- Fixed export verifier unreadable-file handling across manifest/signature/entries paths.
- Fixed entry export handling to fail on short writes and encode failures.

---

### Security

- Removed default signing key material from package behavior assumptions; key configuration is now explicit and validated.
- Added signer boot-time sanity checks in non-testing environments (config-toggleable).
- Hardened filesystem failure-path handling for export generation and verification.

---

### Documentation

- Aligned README and docs with implemented API and behavior.
- Corrected storage driver documentation to reflect supported built-ins (`eloquent`, `array`, `null`).
- Corrected checkpoint field documentation to match the persisted checkpoint model.
- Updated export verification step ordering to match implementation.
- Added an explicit note on current signing-provider behavior during verification.

---

### Testing

- Refactored test suite structure into clearer unit/feature domains.
- Added regression coverage for documentation examples.
- Added filesystem failure-path tests for export flows.
- Added non-default `chronicle.connection` integration test proving checkpoint atomic rollback behavior.
- Added export verifier unreadable-file failure-path tests.
- Expanded core coverage across ledger reader, export pipeline, command failure contracts, and connection semantics.

---

### CI

- Added DB-matrix CI coverage for migration rollback semantics.
- Kept full test and static analysis gates green for release.

---

### Notes

- Stable SemVer guarantees begin at `1.0.0`.
- `algorithm` / `key_id` metadata is persisted for checkpoints/exports; verification currently uses the active configured signing provider.

## [0.9.0] - 2026-03-06

### Added

#### Query API

Chronicle now provides a fluent query API for retrieving ledger entries.

The `Entry` model now includes query scopes designed for common
audit-log access patterns.

Available scopes:

- `forActor($actor)`
- `forSubject($subject)`
- `action(string $action)`
- `correlation(string $correlationId)`
- `workflow(string $rootCorrelation)`
- `withTag(string $tag)`
- `withTags(array $tags)`
- `between($start, $end)`
- `latestFirst()`

These scopes provide a readable and expressive interface for querying
the Chronicle ledger.

Example:
```php
Entry::forSubject($order)
    ->action('order.updated')
    ->latestFirst()
    ->limit(20)
    ->get();
```

---

#### Cursor Pagination

Chronicle now supports cursor-based pagination for efficient traversal
of large ledgers.

Cursor pagination avoids the performance issues associated with
offset-based pagination and enables scalable browsing of large
audit datasets.

Example:
```php
Entry::cursorPaginateLedger(50);
```

Reverse pagination is also available:
```php
Entry::cursorPaginateLatest(50);
```

Cursor pagination uses the entry identifier as a stable ordering key,
ensuring deterministic ledger traversal.

---

#### Ledger Streaming

Chronicle entries can now be streamed using database cursors.

Streaming allows processing extremely large ledgers while maintaining
constant memory usage.

Example:
```php
Entry::stream()->each(function ($entry) {
    // process entry
});
```

Reverse streaming is also supported:
```php
Entry::streamLatest();
```

Streaming operations rely on primary-key ordering to provide efficient
sequential access to ledger entries.

---

#### LedgerReader Abstraction

Chronicle now includes a `LedgerReader` abstraction that provides
a stable read API for accessing the ledger.

The reader exposes common read operations without requiring direct
interaction with the underlying Eloquent model.

Example:
```php
Chronicle::reader()->paginate(50);
Chronicle::reader()->forSubject($order);
Chronicle::reader()->stream();
```

This abstraction allows external packages such as UI dashboards
or cloud services to interact with Chronicle without coupling to
internal implementation details.

---

### Performance

#### Database Indexes

Additional database indexes have been added to optimize common
ledger queries.

Indexes now include:

- `(actor_type, actor_id)`
- `(subject_type, subject_id)`
- `correlation_id`
- `action`
- `recorded_at`

These indexes significantly improve performance for actor history,
entity timelines, and correlation-based queries.

---

#### Primary-Key Ledger Ordering

Streaming and cursor pagination now rely on primary-key ordering
to optimize sequential ledger access.

This allows databases to perform efficient index scans when
processing large datasets.

---

### Internal

- Added query scopes to the `Entry` model
- Added cursor pagination helpers
- Added streaming helpers for ledger traversal
- Introduced `LedgerReader` read abstraction
- Added database indexes for common query patterns
- Added test coverage for query scopes, pagination, and streaming

---

### Notes

This release focuses on improving Chronicle's read-side capabilities
and performance characteristics.

With the addition of streaming queries, cursor pagination, and
a stable read abstraction, Chronicle is now capable of efficiently
handling huge audit ledgers.

Version `0.9.0` represents the final feature release before the
Chronicle `1.0.0` stable release.

## [0.8.0] - 2026-03-06

### Added

#### Verifiable Dataset Exports

Chronicle can now export the ledger as a portable, cryptographically verifiable dataset.

Exports contain three files:

chronicle-export/
├─ entries.ndjson
├─ manifest.json
└─ signature.json

entries.ndjson  
Contains the serialized ledger entries in deterministic NDJSON format.

manifest.json  
Describes the exported dataset and includes:

- export format version
- export timestamp
- entry count
- first entry identifier
- last entry identifier
- chain head
- dataset hash
- signing algorithm

signature.json  
Contains the cryptographic signature of the dataset hash.

This allows Chronicle datasets to be shared and verified independently
of the originating application.

---

#### ExportManager

Added an export orchestration service responsible for coordinating
the Chronicle export pipeline.

The export process includes:

1. streaming entries from the ledger
2. computing the dataset hash
3. generating the export manifest
4. signing the dataset

The manager returns an `ExportResult` value object describing the
export outcome.

---

#### EntryExporter

Added a streaming exporter responsible for writing Chronicle entries
to NDJSON format.

Features:

- deterministic export ordering
- stable serialization
- chunked database streaming
- constant memory usage

The exporter returns an `EntryExportResult` describing the exported
dataset boundaries and entry count.

---

#### ExportHasher

Added a streaming SHA-256 hasher used to compute the dataset fingerprint
for exported datasets.

The hash is computed directly from `entries.ndjson` to guarantee dataset
integrity without loading the dataset into memory.

---

#### ExportManifestBuilder

Added a builder responsible for producing the `manifest.json` document.

The manifest provides a stable export contract and includes:

- export format version
- export timestamp
- entry count
- first entry identifier
- last entry identifier
- chain head
- dataset hash
- signing algorithm

---

#### ExportSigner

Added a dataset signing service that signs the dataset hash using the
configured `SigningProvider`.

The resulting signature is written to `signature.json`.

This allows exported datasets to be verified independently of the
original Chronicle installation.

---

#### ExportVerifier

Added a verification service capable of validating Chronicle export
datasets.

Verification performs the following checks:

1. dataset integrity (SHA-256 hash verification)
2. signature authenticity
3. hash chain integrity
4. dataset boundary validation

Verification results are returned via an `ExportVerificationResult`
value object.

---

#### Export Chain Verification

Added a streaming chain verifier capable of validating the integrity
of the entire ledger chain within exported datasets.

The verifier recomputes every chain hash sequentially using the
exported entries.

This ensures that:

- entries cannot be reordered
- entries cannot be removed
- entries cannot be modified

without detection.

---

#### Dataset Boundary Protection

Exports now include `first_entry_id` and `last_entry_id` anchors in
the manifest to prevent dataset truncation attacks.

During verification Chronicle ensures that:

- the exported entry count matches the manifest
- the first entry identifier matches the manifest
- the last entry identifier matches the manifest

This guarantees the exported dataset represents the exact ledger
state at the moment of export.

---

#### Artisan Commands

Added new console commands for exporting and verifying Chronicle datasets.

chronicle:export

Exports the Chronicle ledger to a verifiable dataset.

Example:

php artisan chronicle:export storage/chronicle-export

chronicle:verify-export

Verifies the integrity and authenticity of an exported dataset.

Example:

php artisan chronicle:verify-export storage/chronicle-export

These commands allow Chronicle datasets to be exported and verified
without direct database access.

---

### Internal

- Added export pipeline services:
    - `ExportManager`
    - `EntryExporter`
    - `ExportHasher`
    - `ExportManifestBuilder`
    - `ExportSigner`
    - `ExportVerifier`
    - `ExportChainVerifier`
- Added export result value objects:
    - `ExportResult`
    - `EntryExportResult`
    - `ExportVerificationResult`
- Added deterministic NDJSON export serialization
- Added streaming dataset hashing
- Added streaming export chain verification
- Added dataset boundary validation
- Added console commands for exporting and verifying Chronicle datasets
- Added tests covering export and verification workflows

---

### Notes

This release introduces portable, cryptographically verifiable Chronicle
datasets.

Exported datasets can now be independently verified by external systems,
auditors, or automated tooling while preserving strong integrity guarantees.

With dataset hashing, digital signatures, chain verification, and
boundary validation, Chronicle exports now provide full audit-grade
verification of ledger history.

---

## [0.7.0] - 2026-03-05

### Added

#### Diff Engine

Chronicle now supports structured diffs, allowing entries to record
field-level changes.

Diffs capture the previous and new value of changed attributes, enabling
precise audit trails and timeline reconstruction.

Example:

```php
Chronicle::entry()
->actor($user)
->action('invoice.updated')
->subject($invoice)
->diff([
'amount' => [
'old' => 1000,
'new' => 500,
],
])
->record();
```

Stored structure:
```yaml
{
  "diff": {
    "amount": {
      "old": 1000,
      "new": 500
    }
  }
}
```

Diff data becomes part of the canonical payload and is included in
Chronicle’s hashing pipeline.

---

#### change() Builder Helper

Added a convenience method for recording individual field changes.

Example:

Chronicle::entry()
->change('status', 'draft', 'paid')
->change('amount', 1000, 500);

Multiple changes can be recorded incrementally without constructing
a full diff array.

---

#### modelDiff() Helper

Chronicle can now generate diffs automatically from Eloquent model
changes.

Example:

$invoice->amount = 500;
$invoice->status = 'paid';

Chronicle::entry()
->actor($user)
->action('invoice.updated')
->subject($invoice)
->modelDiff($invoice)
->record();

This method inspects the model’s dirty attributes using Laravel’s
built-in change tracking and generates the corresponding diff.

Ignored attributes:

- created_at
- updated_at

This helper improves developer ergonomics while preserving Chronicle’s
explicit logging philosophy.

---

### Changed

#### EntryBuilder

EntryBuilder now supports:

- `diff(array $changes)`
- `change(string $field, mixed $old, mixed $new)`
- `modelDiff(Model $model)`

Diff data is normalized and sorted to ensure deterministic canonical
payload serialization.

This guarantees stable payload hashes regardless of the order in which
diff fields are defined.

---

### Internal

- Added diff normalization logic
- Added `modelDiff()` support using Eloquent dirty attributes
- Added `change()` helper for incremental diff construction
- Ensured deterministic diff ordering for canonical hashing
- Added comprehensive tests for diff generation and normalization
- Added test fixture models for package test isolation

---

### Notes

The Diff Engine enables Chronicle to capture the exact state changes
associated with an event rather than simply recording that an action
occurred.

This feature significantly improves Chronicle’s usefulness for:

- audit trails
- financial systems
- administrative timelines
- compliance reporting
- debugging production incidents

The diff system is intentionally explicit and avoids automatic model
observers to preserve Chronicle’s low-magic design philosophy.

---

## [0.6.0] - 2026-03-05

### Added

#### Tags

Chronicle entries now support tags for structured classification and querying.

Tags are stored as a JSON array and are normalized during entry creation:

- trimmed
- lowercased
- duplicates removed
- sorted alphabetically

Example:

Chronicle::entry()
->actor($user)
->action('order.created')
->subject($order)
->tags(['orders', 'checkout'])
->record();

Tags enable filtering, grouping, and analytics on Chronicle data.

A `tag()` convenience method is also available for attaching single tags.

Example:

Chronicle::entry()
->tag('security')
->tag('authentication')

---

#### Correlation / Transactions

Chronicle now supports correlation identifiers for grouping entries belonging
to the same logical workflow.

Transactions automatically assign a shared `correlation_id` to all entries
created within the transaction.

Example:

Chronicle::transaction(function () use ($user, $order) {

    Chronicle::entry()
        ->actor($user)
        ->action('order.created')
        ->subject($order)
        ->record();

    Chronicle::entry()
        ->actor($user)
        ->action('payment.captured')
        ->subject($order)
        ->record();

});

All entries recorded within the transaction share the same correlation id.

---

#### Transaction Object API

Transactions can also be created as objects:

$tx = Chronicle::transaction();

$tx->entry()->action('order.created')->record();
$tx->entry()->action('payment.captured')->record();

This allows explicit control over correlation context.

---

#### Hierarchical Transactions

Transactions support nesting.

Nested transactions generate hierarchical correlation identifiers, allowing
Chronicle to represent complex workflows and sub-operations.

Example:

Root transaction:

01HVABC

Child transaction:

01HVABC.01HVXYZ

This allows reconstructing workflow trees without introducing additional
database columns.

---

#### Current Transaction Accessor

Added `Chronicle::currentTransaction()`.

This method returns the currently active transaction (if one exists),
allowing entries to be attached to the active workflow from anywhere
in the application.

Example:

Chronicle::currentTransaction()?->entry()
->actor('system')
->action('cache.invalidated')
->subject($product)
->record();

This improves integration with:

- service layers
- middleware
- queue jobs
- CLI scripts

---

#### Entry Query Helpers

Added query helpers for working with correlated entries.

Example:

Entry::correlation($id)->get();

This allows retrieving all entries belonging to a specific workflow.

---

### Changed

#### EntryBuilder

EntryBuilder now supports:

- tags
- correlation identifiers
- automatic transaction context inheritance

Entries created inside a transaction automatically inherit the
current correlation id.

---

### Internal

- Added `ChronicleTransaction` class
- Added transaction context stack to `ChronicleManager`
- Added tag normalization logic to `EntryBuilder`
- Added `tag()` and `tags()` builder methods
- Added `correlation()` builder method
- Added correlation query scope to `Entry` model
- Added transaction context resolution in the entry pipeline
- Added comprehensive tests for tags and transactions

---

### Notes

With this release Chronicle evolves from a simple append-only audit log
into a structured event ledger capable of representing workflows,
operations, and nested processes.

Tags and transactions provide the foundation for future Chronicle features, including:

- timeline reconstruction
- workflow visualization
- analytics dashboards
- Chronicle UI packages

---

## [0.5.0] - 2026-03-05

### Added

#### Checkpoints

Introduced cryptographic checkpoints that anchor the Chronicle ledger.

A checkpoint signs the current ledger `chain_hash`, preventing attackers
from recomputing the entire chain after tampering.

New database table:

- `chronicle_checkpoints`

Each checkpoint stores:

- `chain_hash` – the ledger state being anchored
- `signature` – cryptographic signature of the chain hash
- `algorithm` – signing algorithm used
- `key_id` – identifier of the signing key
- `metadata` – optional extensibility metadata
- `created_at` – timestamp of checkpoint creation

Entries may reference a checkpoint using the new `checkpoint_id` column.

#### SigningProvider Contract

Added a `SigningProvider` contract responsible for generating and verifying
cryptographic signatures.

Chronicle delegates all signing operations to this provider, allowing the
package to remain cryptography-agnostic.

Default implementation:

- `Ed25519SigningProvider` (using libsodium)

This abstraction enables future integrations with:

- AWS KMS
- Hashicorp Vault
- hardware security modules
- Chronicle Cloud signing services

#### CheckpointCreator

Added the `CheckpointCreator` service responsible for generating checkpoints.

Responsibilities include:

- resolving the current ledger head (`chain_hash`)
- generating a cryptographic signature
- creating the checkpoint record
- anchoring the ledger state

#### Artisan Command: chronicle:checkpoint

Added a new Artisan command to create checkpoints manually.

```bash
php artisan chronicle:checkpoint
```


This command anchors the current Chronicle ledger state with a cryptographic
signature.

#### IntegrityVerifier Upgrade

The `IntegrityVerifier` now performs full ledger validation including:

- payload hash verification
- chain hash verification
- checkpoint signature verification

Verification now detects attempts to recompute the ledger after tampering.

#### VerificationResult

Added `VerificationResult`, a value object representing the outcome of a
ledger verification process.

The result includes:

- verification status
- failure type
- entry where corruption begins
- number of verified entries

#### chronicle:verify Command

Introduced the `chronicle:verify` command for auditing Chronicle ledger
integrity.

```bash
php artisan chronicle:verify
```


This command validates:

- entry payload hashes
- ledger chain hashes
- checkpoint signatures

It reports the exact entry where corruption begins if integrity violations
are detected.

---

### Changed

#### Integrity Verification Architecture

Verification logic has been extracted into a reusable service
(`IntegrityVerifier`) allowing verification to be used by:

- CLI commands
- scheduled integrity checks
- monitoring systems
- Chronicle Cloud services

The `chronicle:verify` command now acts as a presentation layer for the
verification engine.

---

### Security

Checkpoint anchoring introduces the third cryptographic integrity layer
in Chronicle.

The ledger now protects against:

- payload modification
- entry deletion
- entry insertion
- entry reordering
- chain recomputation attacks

Attackers with database access can no longer modify historical entries
without detection unless they also possess the signing key.

---

### Internal

- Added `Checkpoint` Eloquent model
- Added `CheckpointCreator` service
- Added `SigningProvider` contract
- Added `Ed25519SigningProvider` implementation
- Added `VerificationResult` value object
- Upgraded `IntegrityVerifier`
- Added `chronicle:checkpoint` command
- Added `chronicle:verify` command
- Updated tests to support signing providers
- Added testing `FakeSigningProvider`

---

### Notes

With checkpoints and full verification implemented, Chronicle now functions
as a tamper-evident ledger system rather than a simple audit log.

Upcoming releases will focus on:

- dataset exports
- signed export manifests
- external verification tools
- federation between Chronicle datasets

---

## [0.4.0] - 2026-03-05

### Added

#### Hash Chaining

Introduced cryptographic hash chaining between Chronicle entries.

Each entry now includes a `chain_hash` computed using:

SHA256(previous_chain_hash + payload_hash)

This mechanism links every entry to the previous one, forming a
tamper-evident ledger.

The first entry in the ledger uses `"0"` as the previous chain hash.

Hash chaining allows Chronicle to detect:

- deletion of entries
- insertion of forged entries
- reordering of entries
- payload tampering in earlier entries

New component:

- `ChainHasher`

#### Chain Hash Pipeline Processor

Added a new pipeline processor:

- `ChainHashEntry`

This processor computes the chain hash for a pending entry before it is
persisted to the database.

The Chronicle processing pipeline is now:

EntryBuilder  
↓  
PendingEntry  
↓  
CanonicalizePayload  
↓  
HashPayload  
↓  
ChainHashEntry  
↓  
PersistEntry

This architecture ensures that entries are chained before they are
written to the ledger.

#### Database Schema

Added a new column to the `chronicle_entries` table:

- `chain_hash` (64-character SHA-256 hash)

This column stores the computed chain hash for each entry.

---

### Security

Hash chaining introduces the second cryptographic integrity layer in
Chronicle.

With both `payload_hash` and `chain_hash`, the system can now detect:

- payload modification
- entry deletion
- entry insertion
- entry reordering

Any modification of an entry will invalidate the hashes of all
subsequent entries in the chain.

---

### Internal

- Added `ChainHasher` service
- Added `ChainHashEntry` pipeline processor
- Updated `PendingEntry` to store chain hashes
- Updated pipeline configuration to include chain hashing
- Added unit tests for chain hashing
- Added integration tests verifying chain creation

---

### Notes

Hash chaining transforms Chronicle from an append-only audit log into a
tamper-evident ledger.

The next release will introduce:

- ledger integrity verification (`chronicle:verify`)
- `IntegrityVerifier` service
- detailed verification reporting

These tools will allow applications to audit the integrity of the
entire Chronicle ledger.

---

## [0.3.0] - 2026-03-04

### Added

#### Payload Hashing

Introduced cryptographic hashing of Chronicle entry payloads.

Each entry now includes a `payload_hash` computed using:
`SHA256(canonical_payload)`

The payload hash allows Chronicle to detect tampering of stored entry data.

New components:

- `EntryHasher`
- `HashPayload` pipeline processor

#### Database Schema

Added a new column to the `chronicle_entries` table:

- `payload_hash` (SHA-256 hash stored as a 64-character string)

This column stores the hash of the canonical payload representation.

#### Pipeline Integration

Payload hashing has been integrated into the Chronicle processing pipeline.

The pipeline now executes the following processors:
EntryBuilder  
↓  
PendingEntry  
↓  
CanonicalizePayload  
↓  
HashPayload  
↓  
PersistEntry

This architecture allows future integrity processors to be added without
modifying the Chronicle manager.

---

### Security

Payload hashing introduces the first cryptographic integrity layer in Chronicle.

If the canonical payload stored in the database is modified, the computed hash
will no longer match the stored `payload_hash`, allowing integrity verification
tools to detect tampering.

---

### Internal

- Added `EntryHasher` service for SHA-256 payload hashing
- Introduced `HashPayload` pipeline processor
- Updated `PendingEntry` to store payload hash
- Updated pipeline tests to cover payload hashing
- Updated feature tests to assert that payload hashes are persisted

---

### Notes

Payload hashing is the first step in Chronicle's tamper-evident ledger model.

Upcoming releases will introduce:

- hash chaining between entries
- ledger verification tools (`chronicle:verify`)
- checkpoint anchoring
- signed exports

These features will allow Chronicle to detect modification, deletion,
or reordering of entries in the audit log.

---

## [0.2.0] - 2026-03-04

### Added

#### Canonical Payload Serialization

Introduced deterministic payload serialization to ensure stable entry
representations across environments.

New component:

- `CanonicalPayloadSerializer`

This serializer produces canonical JSON used for future hashing,
chain verification, and export signing.

#### Payload Storage

Added a `payload` column to the `chronicle_entries` table.

The payload stores the canonical representation of an entry used for:

- deterministic exports
- payload hashing
- dataset verification
- debugging and inspection

#### Entry Processing Pipeline

Introduced a modular processing pipeline for Chronicle entries.

New architecture:
EntryBuilder  
↓  
ChronicleManager  
↓  
EntryPipeline  
↓  
Processors


Initial processors:

- `CanonicalizePayload`
- `PersistEntry`

This pipeline architecture enables future processors such as:

- `EntryHasher`
- `ChainHasher`
- `CheckpointProcessor`

without modifying existing components.

#### PendingEntry Value Object

Added `PendingEntry`, a value object representing an entry currently
being processed by Chronicle before persistence.

`PendingEntry` flows through the pipeline and stores intermediate state
such as:

- canonical payload
- payload hash (future)
- chain hash (future)
- checkpoint linkage (future)

This replaces the previous array-based payload handling and provides
stronger typing and safer mutation during processing.

---

### Changed

#### ChronicleManager

ChronicleManager now delegates entry processing to the `EntryPipeline`
instead of directly handling serialization and persistence.

This keeps the manager small and stable while allowing the pipeline to
grow as Chronicle gains new features.

#### EntryBuilder

EntryBuilder now forwards built payloads to ChronicleManager, which
dispatches them into the processing pipeline.

---

### Internal

- Introduced `EntryProcessor` contract
- Added pipeline processor architecture
- Improved separation of concerns between builder, manager, and storage
- Updated test suite to reflect the new pipeline flow

---

### Notes

This release introduces the architectural foundation required for
Chronicle’s cryptographic integrity model.

Upcoming releases will introduce:

- payload hashing
- hash chaining
- checkpoint anchoring
- signed exports
- dataset verification tools

These features will transform Chronicle from an append-only audit log
into a tamper-evident ledger.

---

## [0.1.0] - 2026-03-04

### Added

Initial public release of Laravel Chronicle.

This release introduces the foundational architecture for an append-only audit
logging system designed for Laravel applications.

#### Core Architecture

- Chronicle service container integration
- Chronicle facade for developer-friendly API
- ChronicleManager for orchestrating entry creation and persistence
- EntryBuilder for constructing audit entries
- ReferenceResolver system for deterministic actor and subject references
- EntryStore abstraction for pluggable storage backends

#### Database Storage

- DatabaseEntryStore implementation for persisting entries
- chronicle_entries migration
- Entry Eloquent model

#### Entry System

- Actor / Action / Subject audit entry model
- Metadata support
- Context support
- Tags support
- Correlation ID support
- ULID entry identifiers

#### Data Integrity Principles

- Append-only ledger design
- Immutable entry model (updates and deletes prevented)
- Explicit intent validation (actor, action, subject required)

#### Exceptions

Added validation exceptions:

- MissingActorException
- MissingActionException
- MissingSubjectException

#### Package Infrastructure

- Laravel service provider
- Configuration publishing
- Migration publishing
- Facade access
- Dependency injection bindings

#### Testing

Full Pest test suite covering:

- Entry model immutability
- EntryBuilder behavior
- ReferenceResolver
- DatabaseEntryStore
- ChronicleManager
- Facade integration
- Service container bindings
- Exceptions

---

### Security

Chronicle enforces immutability at the model level by preventing:

- record updates
- record deletion
- force deletion

This ensures the audit ledger remains append-only.

---

### Notes

This release establishes the core Chronicle architecture.

Future releases will introduce:

- canonical payload serialization
- deterministic hashing
- hash chaining
- signed checkpoints
- signed exports
- integrity verification

These features will transform Chronicle into a tamper-evident audit ledger.

---
