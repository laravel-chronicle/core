# Chronicle Data Model Specification

This document describes the structure and guarantees of the Chronicle
audit ledger data model.

The Chronicle data model is designed to support append-only audit
logging with strong integrity guarantees.

This specification is part of the Chronicle public contract and is
considered stable starting from Chronicle v1.0.0.

---

# Core Principles

Chronicle entries follow these rules:

1. **Append-only**
    - Entries are never updated or deleted after creation.

2. **Immutable**
    - All recorded data must remain unchanged after persistence.

3. **Explicit intent**
    - Every entry records:
        - actor
        - action
        - subject

4. **Cryptographic integrity**
    - Entries are protected by hash chaining.

5. **Transport agnostic**
    - Entries may be recorded from HTTP requests, CLI commands,
      queue workers, or background processes.

---

# Ledger Entries

Ledger entries are stored in the `chronicle_entries` table.

Each entry represents a single recorded event in the system.

Entries are ordered by insertion and form a cryptographic chain.

---

# Entry Fields

## id

Unique identifier of the entry.

Type:

- ULID (recommended)
- UUID (supported)

Properties:

- globally unique
- sortable
- immutable

Example:

- `01HVWJZX4E2R8P1C7W4H4S2C3J`

---

## recorded_at

Timestamp representing when the entry was recorded.

Type:

- timestamp

Example:

- `App\Models\User`
- `system`

---

## actor_id

Identifier of the actor instance.

Examples:

- `42`
- `admin`

---

## action

The action that occurred.

Actions should follow a consistent naming scheme.

Example:

- `order.created`
- `invoice.uppdated`
- `user.login`

---

## subject_type

The class or identifier of the affected entity.

Example:

- `App\Models\Order`
- `App\Models\Invoice`
- `ledger`

---

## subject_id

Identifier of the affected entity.

Example:

- `981`

---

## payload

Structured metadata describing the event.

Type:

- JSON

Example:

```json
{
  "amount": 5000,
  "currency": "USD"
}
```

The payload is application-defined.

---

## payload_hash

SHA-256 hash of the canonical serialized payload.

This ensures payload integrity.

Example:

- `6a3c8a4c98c19b71a6e0b43e6d67f9...`

---

## chain_hash

SHA-256 hash linking this entry to the previous entry.

Computation:

- `chain_hash = SHA256(previous_chain_hash + payload_hash)`

This creates a cryptographic chain across all entries.

If any entry is modified or removed, the chain becomes invalid.

---

## checkpoint_id

Reference to a cryptographic checkpoint.

Checkpoints periodically sign the ledger chain to anchor it
against external tampering.

Example:

- `3`
- `tags`

Optional list of tags used for grouping or categorization.

Type:

- JSON array

Example:

```json
["orders", "checkout"]
```

---

## diff

Optional structured representation of state changes.

Example:

```json
{
  "status": {
    "old": "pending",
    "new": "paid"
  }
}
```

This field is used by the Chronicle Diff Engine.

---

## correlation_id

Identifier used to group related entries into a transaction or workflow.

Example:

- `01HVWFH1QY7X8R9D4G3`

Nested workflows may extend this identifier.

---

# Hash Chain Integrity

Chronicle entries form a continuous hash chain.

For entry n:

`chain_hash(n) = SHA256(chain_hash(n-1) + payload_hash(n))`

The first entry uses:

`previous_chain_hash = null`

This ensures:

- entries cannot be reordered
- entries cannot be removed
- entries cannot be modified

without detection.

---

# Checkpoints

Checkpoints provide external anchoring for the ledger.

A checkpoint records:

- current chain head
- entry count
- timestamp
- cryptographic signature

This allows auditors to verify ledger integrity even if
the database is compromised.

---

# Export Format

Chronicle supports exporting the ledger as a portable dataset.

Exports include:

- `entries.ndjson`
- `manifest.json`
- `signature.json`

The dataset can be independently verified.

---

# Dataset Integrity

Exports include the following anchors:

- `entry_count`
- `first_entry_id`
- `last_entry_id`
- `dataset_hash`
- `chain_head`

These values protect against:

- dataset truncation
- entry modification
- entry insertion
- entry reordering

---

# Query Model

Chronicle supports querying entries using:

- actor
- subject
- action
- correlation id
- tags
- time ranges

Queries are optimized using database indexes.

---

# Ordering Guarantees

Ledger order is defined by the entry identifier (`id`).

Entries are append-only and inserted sequentially.

This allows efficient cursor pagination and streaming.

---

# Streaming

Chronicle supports streaming ledger entries using database cursors.

Streaming allows processing large ledgers with constant memory usage.

Example:

```php
Entry::stream()->each(function ($entry) {
// process entry
});
```

---

# Stability Guarantees

Starting with Chronicle v1.0.0 the following contracts are considered stable:

- entry schema
- hash chaining mechanism
- checkpoint structure
- export format
- dataset verification model

Future versions may extend the data model but will not
break existing structures.

---

# Summary

The Chronicle data model provides:

- immutable audit entries
- cryptographic integrity
- verifiable export datasets
- scalable query access
- append-only guarantees

This design allows Chronicle to function as a reliable
audit ledger suitable for security, compliance, and
operational observability.
