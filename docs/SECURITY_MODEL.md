# Chronicle Security Model

This document describes the security guarantees provided by Chronicle.

Chronicle is designed to provide **tamper-detectable audit logging**
for Laravel applications.

The system achieves this through a combination of:

- append-only ledger design
- cryptographic hash chaining
- signed checkpoints
- verifiable export datasets

---

# Threat Model

Chronicle assumes the following threat scenarios:

- malicious database modifications
- accidental data corruption
- unauthorized deletion of audit records
- unauthorized modification of audit entries
- partial dataset exports

Chronicle is designed to **detect** these forms of tampering.

Chronicle is not designed to prevent them.

Instead, Chronicle ensures that tampering attempts become **detectable**.

---

# Security Guarantees

Chronicle provides the following guarantees.

### Entry Integrity

Each ledger entry contains a hash of its serialized payload.

`payload_hash=SHA256(serialized_payload)`

If a payload is modified after recording, the hash verification fails.

---

### Ledger Integrity

Chronicle links entries using a cryptographic hash chain.

`chain_hash(n)=SHA256(chain_has(n-1)+payload_hash(n))`

This ensures that:

- entries cannot be modified
- entries cannot be deleted
- entries cannot be reordered

without breaking the chain.

---

### Dataset Integrity

Chronicle exports include a dataset hash.

`dataset_hash=SHA256(entries.ndjson)`

If any exported entry is modified, the dataset hash changes.

---

### Dataset Authenticity

Exports are signed using a cryptographic signing key.

`signature=sign(dataset_hash)`

This allows external systems to verify that the dataset originated
from the expected Chronicle instance.

---

### Dataset Boundary Protection

Export manifests include the following anchors:

- entry_count
- first_entry_id
- last_entry_id
- chain_head

These values protect against:

- truncated exports
- inserted entries
- reordered entries

---

# Tampering Detection

Chronicle can detect the following tampering attempts:

| Attack               | Detection                      |
|----------------------|--------------------------------|
| Entry modification   | payload hash mismatch          |
| Entry deletion       | chain verification failure     |
| Entry insertion      | chain verification failure     |
| Entry reordering     | chain verification failure     |
| Dataset modification | dataset hash mismatch          |
| Dataset truncation   | boundary mismatch              |
| Dataset forgery      | signature verification failure |

---

# Trust Assumptions

Chronicle relies on the following assumptions:

1. The application records events honestly.
2. Signing keys are securely stored.
3. Verification is performed periodically.

Chronicle does not guarantee protection against:

- compromised application code
- malicious event recording
- stolen signing keys

---

# Recommended Operational Practices

To maximize the security benefits of Chronicle:

### Store signing keys securely

Signing keys should be stored outside the application codebase.

Examples:

- environment variables
- secret management systems
- hardware security modules

---

### Export and verify periodically

Regular exports allow external verification of the ledger.

Example workflow:

```
export ledger
store export externally
verify export integrity
```

---

### Store exports in independent storage

Exports should be stored outside the primary application database.

Examples:

- object storage
- backup systems
- external audit storage

---

### Protect database access

Chronicle detects tampering but does not prevent database compromise.

Standard database security practices should still be followed.

---

# Security Philosophy

Chronicle follows the principle:

**Make tampering detectable.**

The goal is not to prevent unauthorized changes to the database,
but to ensure that such changes cannot occur without leaving
cryptographic evidence.

This approach is similar to how:

- blockchains
- append-only logs
- transparency systems

provide integrity guarantees.

---

# Responsible Disclosure

If you discover a security vulnerability in Chronicle,
please report it responsibly.

See [SECURITY](../SECURITY.md) for details.
