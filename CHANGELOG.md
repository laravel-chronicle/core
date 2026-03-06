# Changelog

All notable changes to `laravel-chronicle` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

Semantic versioning applies from **v1.0.0** onwards. Pre-1.0 releases may contain
breaking changes between any two versions — see upgrade notes per version.

---

## [Unreleased]

### Added

- Added a progress bar phase (`Verifying entries`) to `chronicle:verify` for better visibility during long ledger verification runs.

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
- Added explicit note on current signing-provider behavior during verification.

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
- Kept full test + static analysis gates green for release.

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
handling very large audit ledgers.

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

Tags and transactions provide the foundation for future Chronicle
features including:

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

EntryBuilder now forwards built payloads to ChronicleManager which
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
