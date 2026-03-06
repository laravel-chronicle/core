# Chronicle - Verifiable Audit Logging for Laravel

<!--
keywords:
laravel audit log
laravel audit trail
append only log
immutable audit log
tamper proof audit log
cryptographic audit log
-->

⭐ If you find Chronicle useful, please consider starring the repository.

![Packagist Version](https://img.shields.io/packagist/v/laravel-chronicle/core)
[![Tests](https://github.com/laravel-chronicle/core/actions/workflows/run-tests.yml/badge.svg)](https://github.com/laravel-chronicle/core/actions)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![All Contributors](https://img.shields.io/github/all-contributors/laravel-chronicle/core?color=ee8449&style=flat-square)](#contributors)
[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/laravel-chronicle/core/badge)](https://scorecard.dev/viewer/?uri=github.com/laravel-chronicle/core)

**Chronicle** is a cryptographically verifiable audit ledger for Laravel.

Unlike traditional activity log packages, Chronicle records events in an **append-only ledger protected by hash chaining**, allowing audit history to be **verified for tampering**.

Chronicle is designed for systems that require reliable audit trails such as:

- security logging
- financial systems
- compliance and regulatory reporting
- forensic analysis
- operational observability

---

# Why Chronicle?

Most activity log packages store events in a database table.

Those records can usually be:

- modified
- deleted
- reordered

This makes them unreliable for **security auditing or compliance purposes**.

Chronicle takes a different approach.

Chronicle records events in an **append-only ledger protected by cryptographic hashing**.

Each entry is linked to the previous entry using a **hash chain**.

If any entry is modified, deleted, or reordered, the ledger verification fails.

This makes Chronicle logs **tamper-detectable**.

---

# Feature Comparison

| Feature            | Chronicle | Traditional Activity Logs  |
|--------------------|-----------|----------------------------|
| Append-only ledger | ✓         | ✗                          |
| Immutable entries  | ✓         | ✗                          |
| Hash chaining      | ✓         | ✗                          |
| Tamper detection   | ✓         | ✗                          |
| Verifiable exports | ✓         | ✗                          |
| Signed checkpoints | ✓         | ✗                          |

---

## Installation

```bash
composer require laravel-chronicle/core
php artisan vendor:publish --tag=chronicle-config
php artisan vendor:publish --tag=chronicle-migrations
php artisan migrate
```

---

# Quick Example

Recording an audit entry:

```php
use Chronicle\Facades\Chronicle;

Chronicle::record()
    ->actor($user)
    ->action('order.created')
    ->subject($order)
    ->metadata([
    'total' => 1000,
    'currency' => 'USD',
    ])
    ->commit();
])
```

This records an immutable ledger entry.

---

# Hash Chaining

Chronicle protects the ledger using a **cryptographic hash chain**.

Each entry references the previous entry:

`chain_hash(n) = SHA256(chain_hash(n-1) + payload_hash(n))`

If any entry is modified or removed, the chain becomes invalid.

---

# Querying Entries

Chronicle provides expressive query scopes:

```php
use Chronicle\Models\Entry;

Entry::forActor($user);
Entry::forSubect($order);
Entry::action('order.created');
Entry::withTag('orders');
```

These queries are optimized with database indexes.

---

# Streaming Large Ledgers

Chronicle supports streaming entries using database cursors.

This allows processing very large ledgers with constant memory usage.

```php
use Chronicle\Models\Entry;

Entry::stream()->each(function ($entry) {
    // process entry
});
```

---

# Cursor Pagination

Chronicle includes cursor pagination for efficient browsing of large audit logs.

```php
use Chronicle\Models\Entry;

Entry::cursorPaginateLedger(50);
```

---

# Checkpoints

Chronicle can create cryptographic checkpoints that anchor the ledger.

A checkpoint records:

- current chain head
- entry count
- timestamp
- cryptographic signature

This allows auditors to verify ledger integrity even if the database is compromised.

---

# Verifiable Exports

Chronicle can export the ledger as a **verifiable dataset**.

Exports include:

```
entries.ndjson
manifest.json
signature.json
```

Example:

```bash
php artisan chronicle:export
```

---

# Export Verification

Exported datasets can be independently verified.

```bash
php artisan chronicle:verify-export
```

Verification checks:

- dataset hash
- digital signature
- hash chain integrity
- dataset boundaries

---

# Architecture

Chronicle is designed as a deterministic ledger engine.

```
Application
↓
Chronicle API
↓
Entry Builder
↓
Hash Chain
↓
Ledger Storage
```

See:

- [ARCHITECTURE](docs/ARCHITECTURE.md)
- [DATA_MODEL](docs/DATA_MODEL.md)
- [EXPORT_FORMAT](docs/EXPORT_FORMAT.md)

for detailed documentation.

---

# Design Principles

Chronicle is build around several core principles.

## Append-only

Entries cannot be modified or deleted.

## Explicit intent

Every entry must include:

- actor
- action
- subject

## Cryptographic integrity

Entries are protected using hash chaining and signatures.

## Low magic

Chronicle avoids automatic logging and hidden behavior.

Entries are recorded explicitly.

## Transport agnostic

Chronicle works in:

- HTTP request
- queue workers
- CLI commands
- scheduled jobs

---

# Roadmap

Planned improvements for future versions:

- validation pipeline
- context resolvers
- policy enforcement
- Filament admin UI
- Nova integration
- Chronicle Cloud

---

# Contributing

Contributions are welcome.

Please read: [CONTRIBUTING](CONTRIBUTING.md)

before submitting pull requests.

---

# Contributors

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore-start -->
<!-- markdownlint-disable -->

<!-- markdownlint-restore -->
<!-- prettier-ignore-end -->

<!-- ALL-CONTRIBUTORS-LIST:END -->---

---
# Security

If you discover a security vulnerability, please report it responsibly.

See: [SECURITY](SECURITY.md)

for details.

---

# License

Chronicle is open-source software licensed under the [MIT](LICENSE.md) license.

---

# Credits

Chronicle was created to provide **verifiable audit logging for Laravel applications**.

If you find Chronicle useful, consider starring the repository ⭐
