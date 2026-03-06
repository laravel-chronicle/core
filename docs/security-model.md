# Security Model

Chronicle protects against:

- entry modification
- entry deletion
- entry reordering

This is achieved through:

- deterministic payload hashing
- hash chaining
- signed checkpoints
- signed exports

---

# Threat Model

Chronicle assumes an attacker may have:

- database access
- filesystem access

Integrity verification ensures tampering is detectable.

## Signing Provider Behavior

Checkpoint and export artifacts persist `algorithm` and `key_id` metadata.
Current verification uses the configured active `SigningProvider` instance; it
does not dynamically resolve historical providers by `key_id`.
