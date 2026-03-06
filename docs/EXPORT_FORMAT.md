# Chronicle Export Format

A Chronicle export directory contains:

```bash
chronicle-export/
├─ entries.ndjson
├─ manifest.json
└─ signature.json
```

## `entries.ndjson`

Each line is one exported entry in ledger order (`created_at`, then `id`).

Example:

```json
{"id":"01HVWJZX4E2R8P1C7W4H4S2C3J","actor_type":"App\\Models\\User","actor_id":"42","action":"order.created","subject_type":"App\\Models\\Order","subject_id":"981","payload":{"amount":5000},"payload_hash":"6a3c8a...","chain_hash":"7bc9c4...","checkpoint_id":null,"tags":["orders"],"diff":null,"correlation_id":"01HVWFH1QY7X8R9D4G3","created_at":"2026-03-06T11:32:14.000000Z"}
```

## `manifest.json`

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

## `signature.json`

```json
{
  "signature": "base64-encoded-signature",
  "algorithm": "ed25519",
  "key_id": "your-key-id"
}
```

## Verification Steps

1. Ensure all files exist.
2. Validate JSON structure for `manifest.json` and `signature.json`.
3. Validate `entries.ndjson` line-by-line format.
4. Verify hash chain integrity across all entries.
5. Verify `first_entry_id`, `last_entry_id`, and `entry_count` boundaries.
6. Recompute `dataset_hash` and compare with manifest.
7. Verify signature against `dataset_hash`.
