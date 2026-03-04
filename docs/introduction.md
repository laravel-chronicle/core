# Introduction

Laravel Chronicle is a tamper-evident audit ledger designed for Laravel applications.

Chronicle records immutable audit entries and ensures their integrity using
deterministic hashing and hash chaining.

Unlike traditional activity logs, Chronicle provides strong guarantees about:

- data immutability
- event ordering
- tamper detection
- export stability

Chronicle is designed for applications that require strong audit trails,
including:

- SaaS platforms
- fintech products
- healthcare software
- compliance-sensitive systems

---

# Core Features

- Append-only audit ledger
- Actor / Action / Subject model
- Deterministic canonical payloads
- SHA-256 payload hashing
- Hash chaining
- Signed checkpoints
- Signed export files
- Diff-based change tracking
- Correlation IDs
- Tag-based grouping

---

# Example

```php
Chronicle::entry()
    ->actor($user)
    ->action('invoice.created')
    ->subject($invoice)
    ->metadata([
        'total' => 1000
    ])
    ->tags(['billing'])
    ->record();
```

Chronicle stores this event in an immutable ledger entry.

---

# Integrity Guarantees

Chronicle ensures ledger integrity through:
- deterministic payload hashing
- cryptographic hash chaining
- periodic signed checkpoints
- signed exports

These mechanisms allow systems to verify that the ledger has not been modified.
