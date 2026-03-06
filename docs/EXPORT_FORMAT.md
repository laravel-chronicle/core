# Chronicle Export Format Specification

This document defines the structure of Chronicle export datasets.

The export format allows Chronicle ledgers to be exported,
shared, and independently verified outside the originating
application.

This specification is part of the Chronicle public contract
starting with Chronicle v1.0.0.

---

# Overview

A Chronicle export is a directory containing three files:
```bash
chronicle-export/
├─ entries.ndjson
├─ manifest.json
└─ signature.json
```

Each file has a specific role in ensuring dataset integrity
and authenticity.

---

# entries.ndjson

The `entries.ndjson` file contains the exported ledger entries.

Format:
- NDJSON (Newline Delimited JSON)


Each line represents a single Chronicle entry.

Example:

```json
{"id":"01HVWJZX4E2R8P1C7W4H4S2C3J","recorded_at":"2026-03-06T11:32:14Z","actor_type":"App\\Models\\User","actor_id":42,"action":"order.created","subject_type":"App\\Models\\Order","subject_id":981,"payload":{"amount":5000},"payload_hash":"6a3c8a...","chain_hash":"7bc9c4...","checkpoint_id":3,"tags":["orders"],"diff":null,"correlation_id":"01HVWFH1QY7X8R9D4G3"}
```

Properties:

- entries appear in ledger order
- each entry occupies exactly one line
- entries are encoded using UTF-8
- serialization is deterministic

---

# Entry Ordering

Entries must appear in strict ledger order.

Ledger order is defined by the entry identifier (`id`).

This guarantees deterministic export datasets.

---

## manifest.json

The manifest describes the exported dataset.

Example:
```json
{
  "version": "1.0",
  "generated_at": "2026-03-06T11:40:00Z",
  "entry_count": 1523,
  "first_entry_id": "01HVWJZX4E2R8P1C7W4H4S2C3J",
  "last_entry_id": "01HVWJZX4E2R8P1C7W4H4S2C4F",
  "chain_head": "9fa2b3c4...",
  "dataset_hash": "a93f47b8...",
  "algorithm": "ed25519"
}
```
Fields:

### version

Export format version.

Example: `1.0`

---

### generated_at

Timestamp indicating when the export was generated.

Format: `ISO-8601 timestamp`

Example: `2026-03-06T11:40:00Z`

---

### entry_count

Total number of exported entries.

Used to detect truncation or insertion.

---

### first_entry_id

Identifier of the first exported entry.

Used to detect dataset truncation.

---

### last_entry_id

Identifier of the final exported entry.

Used to detect dataset truncation.

---

### chain_head

Hash chain head of the exported ledger.

This corresponds to the `chain_hash` of the final entry.

---

### dataset_hash

SHA-256 hash of the `entries.ndjson` file.

This protects the dataset from modification.

Computation: `dataset_hash = SHA256(entries.ndjson)`

---

### algorithm

Signing algorithm used to sign the dataset.

Example: `ed25519`

---

## signature.json

The signature file contains the cryptographic signature
of the dataset.

Example:

```json
{
  "signature": "base64-encoded-signature"
}
```

The signature signs the `dataset_hash` value from the manifest.

Verification process:

`verify(signature, dataset_hash, public_key)`

The public key must be obtained from the Chronicle system
that generated the export.

---

# Verification Procedure

To verify an export dataset:
1. Verify that all required files exist.
2. Recompute the dataset hash.
3. Compare the computed hash to the manifest.
4. Verify the dataset signature.
5. Verify the ledger hash chain.
6. Validate dataset boundaries.

---

## Step 1 — Dataset Hash Verification

Compute:

`SHA256(entries.ndjson)`

Compare with:

`manifest.dataset_hash`

If the hashes differ, the dataset has been modified.

---

## Step 2 — Signature Verification

Verify the signature using the public key:

`verify(signature, dataset_hash, public_key)`

If verification fails, the dataset authenticity cannot be trusted.

---

## Step 3 — Chain Integrity Verification

For each entry:

`chain_hash(n) = SHA256(chain_hash(n-1) + payload_hash(n))`

If any entry has been modified or reordered, the chain verification fails.

---

## Step 4 — Dataset Boundary Verification

Validate the dataset anchors:

```
first_entry_id
last_entry_id
entry_count
```

If any value does not match the exported data,
the dataset may have been truncated.

---

# Security Guarantees

Chronicle exports detect the following tampering attempts:

| Attack             | Detection                      |
|--------------------|--------------------------------|
| Entry modification | dataset hash mismatch          |
| Entry deletion     | dataset hash mismatch          |
| Entry insertion    | dataset hash mismatch          |
| Entry reorder      | chain verification failure     |
| Dataset truncation | boundary mismatch              |
| Dataset forgery    | signature verification failure |

---

# Deterministic Serialization

Chronicle exports must use deterministic serialization to ensure
the dataset hash is stable across environments.

Requirements:
- UTF-8 encoding
- consistent JSON serialization
- deterministic key ordering

---

# Compatibility

The export format is versioned.

Future versions may add fields but will not remove or alter
existing fields defined in version 1.0.

Consumers should ignore unknown fields to ensure forward compatibility.

---

# Use Cases

The Chronicle export format supports:
- independent ledger verification
- external audits
- regulatory compliance
- data archiving
- forensic analysis
- migration between Chronicle systems

---

# Summary

The Chronicle export format provides a portable representation
of the audit ledger that can be independently verified using:
- dataset hashing
- cryptographic signatures
- hash chain validation
- dataset boundary checks

This ensures exported Chronicle datasets maintain strong
integrity guarantees even outside the original system.
