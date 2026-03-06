# Checkpoints

Checkpoints anchor the ledger state.

A checkpoint stores:

- `id` (ULID)
- `chain_hash`
- `signature`
- `algorithm`
- `key_id` (nullable)
- `metadata` (nullable)
- `created_at`

---

# Why Checkpoints Exist

Without checkpoints, an attacker could theoretically rewrite the entire chain.

Checkpoints prevent this by creating externally verifiable anchors.

---

# Creating a Checkpoint

```bash
php artisan chronicle:checkpoint
```

---

# Verifying Checkpoints

```bash
php artisan chronicle:verify
```
