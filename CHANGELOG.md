# Changelog

All notable changes to `laravel-chronicle` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

Semantic versioning applies from **v1.0.0** onwards. Pre-1.0 releases may contain
breaking changes between any two versions — see upgrade notes per version.

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
