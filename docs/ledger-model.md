# Ledger Model

Each Chronicle entry represents an immutable audit event.

---

# Entry Fields

| Field          | Description                      |
|----------------|----------------------------------|
| actor_type     | actor class/type                 |
| actor_id       | actor identifier                 |
| action         | event name                       |
| subject_type   | subject class/type               |
| subject_id     | subject identifier               |
| payload        | canonical payload                |
| payload_hash   | SHA-256 of canonical payload     |
| chain_hash     | SHA-256(previous + payload_hash) |
| metadata       | domain-specific information      |
| diff           | structured change information    |
| tags           | grouping labels                  |
| correlation_id | event grouping ID                |
| checkpoint_id  | optional checkpoint reference    |
| created_at     | immutable event timestamp (UTC)  |

---

# Example Entry

```json
{
  "id": "01JNV8EJFKJVEWRS2R1W4GD5E6",
  "actor_type": "App\\Models\\User",
  "actor_id": "42",
  "action": "invoice.sent",
  "subject_type": "App\\Models\\Invoice",
  "subject_id": "91",
  "payload": {
    "email": "client@example.com"
  },
  "payload_hash": "d5d8...",
  "chain_hash": "6ac4...",
  "checkpoint_id": null,
  "metadata": {
    "email": "client@example.com"
  },
  "diff": null,
  "tags": ["billing", "email"],
  "correlation_id": null,
  "created_at": "2026-03-06T12:34:56.000000Z"
}
```
