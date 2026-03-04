# Hashing Model

Chronicle uses two types of hashes.

---

# Payload Hash

Each entry generates an SHA-256 hash from its canonical payload.

payload_hash = SHA256(canonical_payload)

---

# Chain Hash

Entries are linked together.

chain_hash = SHA256(previous_chain_hash + payload_hash)

This creates a tamper-evident chain.

---

# Tamper Detection

If any entry is modified:

- payload hash changes
- chain hash breaks
- verification fails
