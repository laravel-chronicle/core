# Performance

Chronicle is designed for high-volume append-only audit writes.

Recommended indexes:

- `action`
- `(actor_type, actor_id)`
- `(subject_type, subject_id)`
- `correlation_id`
- `created_at`

Large-ledger patterns:

- cursor pagination
- streaming exports
- chunk/cursor based verification
