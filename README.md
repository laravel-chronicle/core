# Chronicle – Verifiable Audit Logging for Laravel

<!--
keywords:
laravel audit log
laravel audit trail
append only log
immutable audit log
tamperproof audit log
cryptographic audit log
-->

⭐ If you find Chronicle useful, please consider starring the repository.

![Packagist Version](https://img.shields.io/packagist/v/laravel-chronicle/core)
[![Tests](https://github.com/laravel-chronicle/core/actions/workflows/run-tests.yml/badge.svg)](https://github.com/laravel-chronicle/core/actions)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![All Contributors](https://img.shields.io/github/all-contributors/laravel-chronicle/core?color=ee8449&style=flat-square)](#contributors)

**Chronicle** is a cryptographically verifiable audit ledger for Laravel.

Unlike traditional activity log packages, Chronicle records events in an **append-only ledger protected by hash chaining**, allowing audit history to be **verified for tampering**.

Chronicle is designed for systems that require reliable audit trails such as:

- security logging
- financial systems
- compliance and regulatory reporting
- forensic analysis
- operational observability

📚 **Full documentation:** https://laravel-chronicle.github.io

---

## Why Chronicle?

Most activity-log packages store events in a database table. Those records can usually be modified, deleted, or reordered — which makes them unreliable for security auditing or compliance.

Chronicle takes a different approach. Events are recorded in an **append-only ledger protected by cryptographic hashing**, and each entry is linked to the previous one through a **hash chain**. If any entry is modified, deleted, or reordered, ledger verification fails. This makes Chronicle logs **tamper-detectable**.

| Feature            | Chronicle | Traditional Activity Logs |
|--------------------|-----------|---------------------------|
| Append-only ledger | ✓         | ✗                         |
| Immutable entries  | ✓         | ✗                         |
| Hash chaining      | ✓         | ✗                         |
| Tamper detection   | ✓         | ✗                         |
| Verifiable exports | ✓         | ✗                         |
| Signed checkpoints | ✓         | ✗                         |
| Key rotation       | ✓         | ✗                         |

---

## Requirements

- PHP `^8.2`
- Laravel `^12.0`, or `^13.0`
- The `ext-sodium` extension (Ed25519 signing)
- The `ext-openssl` extension (ECDSA P-256 signing and verification)

---

## Installation

```bash
composer require laravel-chronicle/core
php artisan chronicle:install
```

`chronicle:install` publishes the config file and migrations and offers to run them. See the [Installation guide](https://laravel-chronicle.github.io/docs/installation) for signing-key setup and the recommended production configuration.

---

## Recording an entry

Every entry requires an `actor`, an `action`, and a `subject`:

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
    ->tags(['orders', 'billing'])
    ->commit();
```

Chronicle generates a ULID, resolves the actor and subject, canonicalizes the payload, computes the payload and chain hashes, and persists an immutable entry inside a database transaction.

### Automatic model auditing

Add the `HasChronicle` trait to audit an Eloquent model's lifecycle events automatically:

```php
use Chronicle\Eloquent\HasChronicle;

class Invoice extends Model
{
    use HasChronicle;
}
```

`created`, `updated`, and `deleted` events are recorded automatically, with a structured diff for updates. For models you don't own, register an observer instead with `Chronicle::observe(Invoice::class)`. See [Auditing Eloquent Models](https://laravel-chronicle.github.io/docs/auditing-eloquent-models).

---

## Hash chaining

Chronicle protects the ledger with a cryptographic hash chain. Each entry references the previous one:

`chain_hash(n) = SHA256(chain_hash(n-1) + payload_hash(n))`

If any entry is modified or removed, the chain becomes invalid. See [Hashing](https://laravel-chronicle.github.io/docs/hashing).

---

## Signing and key rotation

Checkpoints, exports, and compliance reports are signed. Chronicle holds its signing keys in a **key ring**: one key is *active* and signs new artifacts, while every key (active or retired) remains available to **verify** the artifacts it produced. Each artifact records the `algorithm` and `key_id` it was signed with, and verification resolves the matching key from the ring — so **rotating keys never invalidates existing checkpoints or exports**.

```php
// config/chronicle.php
'signing' => [
    'active' => env('CHRONICLE_ACTIVE_KEY', 'chronicle-key-1'),

    'keys' => [
        'chronicle-key-1' => [
            'provider'    => Chronicle\Signing\Ed25519SigningProvider::class,
            'algorithm'   => 'ed25519',
            'private_key' => env('CHRONICLE_PRIVATE_KEY'), // null once retired
            'public_key'  => env('CHRONICLE_PUBLIC_KEY'),  // keep for verification
        ],
    ],
],
```

Chronicle ships two built-in providers: `Ed25519SigningProvider` (libsodium) and `EcdsaSigningProvider` (ECDSA P-256 via OpenSSL, verified locally against a cached public key).

### Rotating a key

```bash
php artisan chronicle:key:generate --id=chronicle-key-2   # mint a new keypair
# add the printed entry to signing.keys, then:
php artisan chronicle:key:rotate chronicle-key-2          # anchors a boundary checkpoint
# set CHRONICLE_ACTIVE_KEY=chronicle-key-2 and deploy
```

`chronicle:key:rotate` always creates a boundary checkpoint at the current ledger head before handing over, so the epoch boundary between keys is itself verifiable. When you eventually retire a key, **keep its `public_key`** in the ring — drop only the `private_key`. See [Signing and Keys](https://laravel-chronicle.github.io/docs/signing-and-keys) and the [key rotation guide](https://laravel-chronicle.github.io/docs/guide-rotate-signing-keys).

### External signing providers (KMS / HSM)

Signing providers are pluggable, so the private key can live outside the application entirely. Providers sign remotely and verify locally against a cached public key, keeping verification offline and fast. An official AWS KMS adapter is available as a companion package:

```bash
composer require laravel-chronicle/kms-aws
```

To build your own (GCP KMS, Vault, HSM, …), see [Custom Signing Providers](https://laravel-chronicle.github.io/docs/custom-signing-providers).

> **Upgrading from 1.9.x?** The previous flat `signing` config (a single `provider` / `private_key` / `public_key` / `key_id`) continues to work unchanged — Chronicle adapts it to a single-key ring automatically. Migrating to the `signing.active` + `signing.keys` shape is recommended but not required.

---

## Querying the ledger

Chronicle provides an expressive query API with database-indexed scopes:

```php
use Chronicle\Entry\Entry;

Entry::forActor($user)->get();
Entry::forSubject($order)->get();
Entry::action('order.created')->get();
Entry::withTag('orders')->get();
```

For large ledgers, Chronicle supports cursor pagination and constant-memory streaming:

```php
Entry::stream()->each(fn ($entry) => /* process */);
Entry::cursorPaginateLedger(50);
```

See the [Query API reference](https://laravel-chronicle.github.io/docs/query-api).

---

## Checkpoints

Chronicle can create cryptographic checkpoints that anchor the ledger. A checkpoint signs the current chain head along with an entry count and timestamp, so auditors can verify integrity even if the database is later compromised.

```bash
php artisan chronicle:checkpoint
```

See [Checkpoints](https://laravel-chronicle.github.io/docs/checkpoints).

---

## Verifiable exports

Chronicle can export the ledger as a verifiable dataset (`entries.ndjson`, `manifest.json`, `signature.json`) that can be verified independently of the application:

```bash
php artisan chronicle:export storage/app/chronicle-export
php artisan chronicle:verify-export storage/app/chronicle-export
```

Verification checks the dataset hash, digital signature, hash-chain integrity, and dataset boundaries — resolving the signing key from the key ring, so exports signed by a now-retired key still verify. See [Exports](https://laravel-chronicle.github.io/docs/exports) and the [Export Format](https://laravel-chronicle.github.io/docs/export-format).

---

## Artisan commands

| Command                          | Purpose                                                                                |
|----------------------------------|----------------------------------------------------------------------------------------|
| `chronicle:install`              | Publish config and migrations (`--force`, `--migrate`)                                 |
| `chronicle:checkpoint`           | Create a signed checkpoint                                                             |
| `chronicle:export {path}`        | Export the ledger as a verifiable dataset                                              |
| `chronicle:verify`               | Verify the full ledger (or one entry with `--entry=<ULID>`)                            |
| `chronicle:verify-export {path}` | Verify an exported dataset                                                             |
| `chronicle:stats`                | Display ledger statistics (`--json`)                                                   |
| `chronicle:show {id}`            | Display a single entry by ULID                                                         |
| `chronicle:prune`                | Prune entries by retention policy (`--older-than`, `--before`, `--dry-run`, `--force`) |
| `chronicle:report {path}`        | Generate a signed compliance report (`--from`, `--to`)                                 |
| `chronicle:key:generate`         | Generate an Ed25519 keypair for `signing.keys` (`--id`)                                |
| `chronicle:key:list`             | List the signing keys in the key ring (`--with-counts`)                                |
| `chronicle:key:rotate {keyId}`   | Create a boundary checkpoint and print activation instructions for a new key           |

See the [Artisan Commands reference](https://laravel-chronicle.github.io/docs/artisan-commands).

---

## Features at a glance

- **Append-only ledger** with immutable Eloquent entries
- **Hash chaining** and deterministic canonical-payload hashing
- **Signing** with Ed25519 or ECDSA P-256; signed checkpoints, exports, and compliance reports
- **Key rotation** with a multi-key ring — retired keys keep verifying their own artifacts
- **External signing providers** (e.g. AWS KMS) with remote signing and local verification
- **Verifiable exports** with independent verification
- **Automatic model auditing** via the `HasChronicle` trait or observers
- **Transactions & correlation IDs** for grouping related events
- **Diff engine** for capturing field-level changes
- **Extensible pipeline** — validators, policies, and context resolvers
- **Storage drivers** — `eloquent`/`database`, `queued`, `array`, `null`
- **Retention & pruning** with checkpoint-aware deletion
- **Read-only web UI** (optional Blade interface)
- **Events** — `EntryRecorded` and `EntryRejected`
- **Testing helpers** — `Chronicle::fake()` with fluent assertions

---

## Design principles

- **Append-only.** Entries cannot be modified or deleted; corrections are recorded as new entries.
- **Explicit intent.** Every entry names an actor, action, and subject — no ambiguous "something changed" logs.
- **Cryptographic integrity.** Entries are protected with hash chaining and signatures.
- **Low magic.** Automatic auditing is opt-in; nothing is logged behind your back.
- **Transport agnostic.** Works in HTTP requests, queue workers, CLI commands, and scheduled jobs.

Read more in [Philosophy](https://laravel-chronicle.github.io/docs/philosophy) and the [Architecture](https://laravel-chronicle.github.io/docs/architecture) and [Security Model](https://laravel-chronicle.github.io/docs/security-model) docs.

---

## Extending Chronicle

Chronicle is designed to be extended. You can write custom validators, policies, and context resolvers, swap in custom storage drivers or signing providers (for example, AWS KMS), and listen to ledger events. See the [Extending Chronicle](https://laravel-chronicle.github.io/docs/extending-chronicle) guide.

---

## Roadmap

Planned for upcoming releases:

- external anchoring of checkpoints (S3 Object Lock, RFC 3161 timestamping, transparency logs)
- incremental, checkpoint-based verification for large ledgers
- additional external signing adapters (GCP KMS, HashiCorp Vault)
- a dedicated Filament admin integration

---

## Contributing

Contributions are welcome. Please read: [CONTRIBUTING](CONTRIBUTING.md) before submitting pull requests.

---

# Contributors

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore-start -->
<!-- markdownlint-disable -->
<table>
  <tbody>
    <tr>
      <td align="center" valign="top" width="14.28%"><a href="https://poornachandradinesh.netlify.app/"><img src="https://avatars.githubusercontent.com/u/69423861?v=4?s=100" width="100px;" alt="Poorna Chandra Dinesh"/><br /><sub><b>Poorna Chandra Dinesh</b></sub></a><br /><a href="#code-Poorna-Chandra-D" title="Code">💻</a></td>
      <td align="center" valign="top" width="14.28%"><a href="https://github.com/ntoufoudis"><img src="https://avatars.githubusercontent.com/u/93659348?v=4?s=100" width="100px;" alt="Vasileios Ntoufoudis"/><br /><sub><b>Vasileios Ntoufoudis</b></sub></a><br /><a href="#code-ntoufoudis" title="Code">💻</a></td>
      <td align="center" valign="top" width="14.28%"><a href="https://jamesking.dev"><img src="https://avatars.githubusercontent.com/u/253237?v=4?s=100" width="100px;" alt="James King"/><br /><sub><b>James King</b></sub></a><br /><a href="#doc-Jamesking56" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="14.28%"><a href="https://github.com/devc4rlos"><img src="https://avatars.githubusercontent.com/u/196385361?v=4?s=100" width="100px;" alt="Carlos Alexandre"/><br /><sub><b>Carlos Alexandre</b></sub></a><br /><a href="#code-devc4rlos" title="Code">💻</a></td>
    </tr>
  </tbody>
</table>

<!-- markdownlint-restore -->
<!-- prettier-ignore-end -->

<!-- ALL-CONTRIBUTORS-LIST:END -->

---

# Security

If you discover a security vulnerability, please report it responsibly. See: [SECURITY](SECURITY.md) for details.

---

# License

Chronicle is open-source software licensed under the [MIT](LICENSE.md) license.

---

# Credits

Chronicle was created to provide **verifiable audit logging for Laravel applications**.

If you find Chronicle useful, consider starring the repository ⭐
