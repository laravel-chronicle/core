# Ledger Model

Each Chronicle entry represents an immutable audit event.

---

# Entry Fields

| Field          | Description                      |
|----------------|----------------------------------|
| actor          | entity that initiated the action |
| action         | event name                       |
| subject        | entity affected                  |
| metadata       | domain-specific information      |
| diff           | structured change information    |
| tags           | grouping labels                  |
| correlation_id | event grouping ID                |

---

# Example Entry

```json
{
  "actor": {
    "type": "App\\Models\\User",
    "id": 42
  },
  "action": "invoice.sent",
  "subject": {
    "type": "App\\Models\\Invoice",
    "id": 91
  },
  "metadata": {
    "email": "client@example.com"
  },
  "tags": ["billing","email"]
}
```
