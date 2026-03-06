# Chronicle Data Model

Chronicle stores append-only audit entries in `chronicle_entries`.

## Entry Fields

- `id` (ULID, primary key)
- `actor_type` (string)
- `actor_id` (string)
- `action` (string)
- `subject_type` (string)
- `subject_id` (string)
- `payload` (json, canonical payload)
- `payload_hash` (string, SHA-256)
- `chain_hash` (string, SHA-256)
- `checkpoint_id` (nullable ULID FK)
- `metadata` (nullable json)
- `context` (nullable json)
- `tags` (nullable json array)
- `diff` (nullable json)
- `correlation_id` (nullable string)
- `created_at` (timestamp, UTC)

## Integrity Rules

- Entries are immutable after insert.
- `payload_hash = SHA256(canonical(payload))`
- `chain_hash = SHA256(previous_chain_hash + payload_hash)`
- The first entry uses `previous_chain_hash = "0"`.

## Querying And Access Patterns

Use Chronicle query scopes/reader APIs for:

- actor/subject filtering
- action filtering
- correlation/workflow grouping
- time-range filtering
- cursor pagination and stream processing
