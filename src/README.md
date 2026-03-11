# Chronicle Source Layout

This directory contains the core implementation of **Chronicle**, an append-only audit ledger for Laravel.

The codebase is organized by **domain**, reflecting the major components of the Chronicle architecture.

Understanding these domains will help contributors navigate the project more easily.

---

# High-Level Architecture

Chronicle records audit events through a deterministic processing pipeline.

EntryBuilder  
↓  
PendingEntry  
↓  
Extension Pipeline  
├── Validators  
├── Context Resolvers  
├── Policies  
└── Processors  
↓  
Payload Serialization  
↓  
Entry Hashing  
↓  
Chain Hashing  
↓  
Entry Storage

Each step ensures that ledger entries are valid, deterministic, and tamper-detectable.

---

# Directory Overview

## Entry

Handles entry creation and persistence.

`Entry/`

Key components:

- `Entry` — Eloquent model representing a recorded ledger entry
- `PendingEntry` — in-memory representation of an entry before persistence
- `EntryBuilder` — developer-facing builder used to construct entries
- `EntryStore` — responsible for storing entries in the ledger

---

## Pipeline

The entry processing pipeline.

`Pipeline/`

The pipeline runs before serialization and hashing.

It allows extensions such as:

- validators
- context resolvers
- policies
- custom processors

Pipeline stages ensure deterministic processing of entries.

---

## Validation

Entry validators ensure structural correctness before entries are recorded.

`Validation/`

Examples include:

- `ActorPresenceValidator`
- `ActionValidator`
- `SubjectValidator`
- `TagsValidator`
- `DiffStructureValidator`

Validators run in the **VALIDATE pipeline stage**.

They must not mutate entries.

---

## Context

Context resolvers automatically attach metadata to entries.

`Context/`

Examples:

- environment
- request information
- hostname
- process identifiers

Context resolvers run in the **RESOLVE_CONTEXT pipeline stage**.

---

## Hashing

Implements Chronicle’s cryptographic integrity mechanisms.

`Hashing/`

Responsibilities include:

- payload hashing
- chain hashing
- ledger verification

These mechanisms ensure that entries cannot be modified without detection.

---

## Checkpoints

Checkpoint creation and signing.

`Checkpoints/`

Checkpoints anchor the ledger and allow external verification.

They typically include:

- chain head hash
- entry count
- timestamp
- signature

---

## Export

Export infrastructure for producing verifiable datasets.

`Exports/`

Exports allow the ledger to be shared and independently verified.

Export datasets typically include:
- entries.ndjson
- manifest.json
- signature.json

---

## Verification

Integrity verification tools.

`Verification/`

These components allow Chronicle to verify:

- ledger integrity
- hash chains
- export datasets

Verification can be performed both internally and on exported datasets.

---

## Console

Artisan commands provided by Chronicle.

`Console/`

Examples:

- `chronicle:verify`
- `chronicle:export`
- `chronicle:checkpoint`

---

## Support

Utility classes used throughout the codebase.

`Support/`

Examples include:

- canonical payload serialization
- reference resolution
- helper utilities

---

## Exceptions

Custom exceptions used by Chronicle.

`Exceptions/`

These represent various failure scenarios such as:

- invalid entries
- verification failures
- export validation errors

---

# Design Principles

Chronicle follows several core architectural principles.

### Append-only

Entries cannot be modified or deleted once recorded.

### Deterministic behavior

The same input must always produce the same ledger output.

### Explicit intent

Entries must explicitly declare actor, action, and subject.

### Transport agnostic

Chronicle works in:

- HTTP requests
- queue workers
- CLI commands
- scheduled jobs

---

# Contributing

If you plan to contribute to Chronicle, please read: [CONTRIBUTING.md](../CONTRIBUTING.md)

This document explains the development workflow, coding standards, and testing requirements.

---

# Questions

If you are exploring the codebase and have questions, feel free to open a discussion or issue.

Contributions and feedback are welcome.
