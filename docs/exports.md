# Exports

Chronicle exports allow the ledger to be archived or shared.

Exports are deterministic and versioned.

---

# Export Command

php artisan chronicle:export

---

# Signed Export

php artisan chronicle:export --sign

---

# Export Format

```yaml
{
  "schema": "chronicle.export.v1",
  "generated_at": "...",
  "entries": [...]
}
```
