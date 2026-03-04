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
