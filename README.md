# Laravel Chronicle

Chronicle is a **tamper-evident audit ledger for Laravel applications**.

Unlike traditional activity logs, Chronicle provides:

- Append-only audit entries
- Deterministic payload hashing
- Hash chaining for tamper detection
- Signed checkpoints
- Signed exports
- Explicit actor/action/subject model
- Transport-agnostic logging
- Deterministic export format

Chronicle is designed for applications that require **strong audit guarantees**, such as:

- SaaS platforms
- fintech systems
- healthcare software
- compliance-sensitive systems

---

# Key Principles

Chronicle follows several strict design principles.

### Append-Only

Entries are immutable once recorded.  
There is no update or delete API.

Corrections must be recorded as **new entries**.

---

### Explicit Intent

Every entry must define:

- actor
- action
- subject

Chronicle never records ambiguous events.

---

### Stable Contracts

Exports are **versioned and deterministic**.  
Consumers can rely on export formats indefinitely.

---

### Low Magic

Chronicle intentionally avoids:

- model observers
- auto-logging traits
- implicit hooks

Every audit entry is a deliberate developer action.

---

### Transport-Agnostic

Chronicle works in:

- HTTP requests
- queue workers
- CLI commands
- scheduled jobs
- event listeners

---

# Installation

```bash
composer require laravel-chronicle/core
```

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag=chronicle-config
php artisan vendor:publish --tag=chronicle-migrations
php artisan migrate
```

---

# Basic Usage

```php
Chronicle::record(
    actor: $user,
    action: 'invoice.created',
    subject: $invoice,
    metadata: [
        'total' => 1000
    ],
    tags: ['billing']
);
```

---

# Diff Example

```php
Chronicle::record(
    actor: $admin,
    action: 'invoice.amount_changed',
    subject: $invoice,
    diff: Chronicle::diff(
        old: ['amount' => 1000],
        new: ['amount' => 500]
    )
);
```

---

# Correlation Example

```php
Chronicle::transport()->start();

Chronicle::record(...);
Chronicle::record(...);
Chronicle::record(...);
```

All entries share the same `correlation_id`.

---

# Integrity Verification

Verify ledger integrity:

```bash
php artisan chronicle:verify
```

---

# Export Ledger

```bash
php artisan chronicle:export
```

Signed export:

```bash
php artisan chronicle:export --sign
```

---

# Documentation 

See the `docs` directory for detailed documentation.

---

# Contributing

Please read [CONTRIBUTING](CONTRIBUTING.md)

---

# Security

Please report vulnerabilities through the process described in [SECURITY](SECURITY.md)

---

# License

[MIT](LICENSE.md)
