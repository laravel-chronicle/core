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
![Packagist Downloads](https://img.shields.io/packagist/dt/laravel-chronicle/core)
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

Most activity-log packages store events in a database table. Those records can usually be modified, deleted, or reordered - which makes them unreliable for security auditing or compliance.

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
| External anchoring | ✓         | ✗                         |
| GDPR erasure       | ✓         | ✗                         |

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

Checkpoints, exports, and compliance reports are signed. Chronicle holds its signing keys in a **key ring**: one key is *active* and signs new artifacts, while every key (active or retired) remains available to **verify** the artifacts it produced. Each artifact records the `algorithm` and `key_id` it was signed with, and verification resolves the matching key from the ring - so **rotating keys never invalidates existing checkpoints or exports**.

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

`chronicle:key:rotate` always creates a boundary checkpoint at the current ledger head before handing over, so the epoch boundary between keys is itself verifiable. When you eventually retire a key, **keep its `public_key`** in the ring - drop only the `private_key`. See [Signing and Keys](https://laravel-chronicle.github.io/docs/signing-and-keys) and the [key rotation guide](https://laravel-chronicle.github.io/docs/guide-rotate-signing-keys).

### External signing providers (KMS / HSM)

Signing providers are pluggable, so the private key can live outside the application entirely. Providers sign remotely and verify locally against a cached public key, keeping verification offline and fast. An official AWS KMS adapter is available as a companion package:

```bash
composer require laravel-chronicle/kms-aws
```

To build your own (GCP KMS, Vault, HSM, …), see [Custom Signing Providers](https://laravel-chronicle.github.io/docs/custom-signing-providers).

> **Upgrading from 1.9.x?** The previous flat `signing` config (a single `provider` / `private_key` / `public_key` / `key_id`) continues to work unchanged - Chronicle adapts it to a single-key ring automatically. Migrating to the `signing.active` + `signing.keys` shape is recommended but not required.

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

### Resolving references

Entries store actors and subjects as a `(type, id)` pair, where `type` is the
class name (or a configured morph alias). To turn one back into something
displayable, use the reverse resolver - it honours `Relation::morphMap()` and
**does not touch the database**:

```php
use Chronicle\Facades\Chronicle;

$ref = Chronicle::resolveReference($entry->subject_type, $entry->subject_id);
$ref->class;   // resolved FQCN, or null when unknown
$ref->label;   // e.g. "Order #123" - humanised basename + id
$ref->exists();

Chronicle::referenceLabel($entry->actor_type, $entry->actor_id); // "System", "User #5", …
```
When you actually want the row, opt in to hydration (this is the only path that
queries). The label can read a configurable attribute (`chronicle.references.label_attribute`,
default `name`):

```php
$model = Chronicle::referenceModel($entry->subject_type, $entry->subject_id); // ?Model
$name  = Chronicle::referenceLabel($entry->subject_type, $entry->subject_id, hydrate: true);
```

Bind your own `Chronicle\Contracts\ReferenceLookup` implementation in the
container to fully customise resolution or labelling.

---

### Seeding a ledger in tests

Building DB-backed test data one `Chronicle::record()->commit()` at a time is slow,
and raw factory inserts produce a ledger that fails verification (they skip the
hash chain and signing). `Chronicle\Testing\LedgerSeeder` drives the real write
path inside a single transaction, so the result verifies:

```php
use Chronicle\Testing\LedgerSeeder;

$seeded = LedgerSeeder::make()
    ->count(1000)
    ->checkpointEvery(100)            // periodic signed checkpoints (+ a final one)
    ->action(fn (int $i) => "order.$i")
    ->subject(fn (int $i) => Order::factory()->create())
    ->seed();

$seeded->entries;            // 1000
$seeded->checkpoints;        // 10
$seeded->lastCheckpointId;   // string|null
```

Requires the eloquent driver (`$this->useEloquentDriver()` in the package test
suite) and a configured signing key. The seeded chain passes both
`IntegrityVerifier::verify()` and `CheckpointChainVerifier::verify()`.

---

## Checkpoints

Chronicle can create cryptographic checkpoints that anchor the ledger. A checkpoint signs the current chain head along with an entry count and timestamp, so auditors can verify integrity even if the database is later compromised.

```bash
php artisan chronicle:checkpoint
```

See [Checkpoints](https://laravel-chronicle.github.io/docs/checkpoints).

---

## External anchoring

Hash chaining and signed checkpoints detect tampering by anyone who can't forge a checkpoint signature. But an attacker holding **both** the database and the signing key could rewrite the chain and re-sign every checkpoint. External anchoring closes that gap: each checkpoint's digest is published to an **append-only sink in a separate trust domain**, so a rewritten ledger fails verification at the first anchored checkpoint - the attacker cannot forge the external attestation.

Chronicle ships an RFC 3161 trusted-timestamp anchor in core (standards-based, no cloud SDK; verified offline against the TSA certificate). Anchoring is **opt-in** and runs after each checkpoint commits - an anchor failure never invalidates the checkpoint.

```php
// config/chronicle.php
'anchoring' => [
    'enabled' => env('CHRONICLE_ANCHORING_ENABLED', false),
    'providers' => [
        'tsa' => [
            'provider' => Chronicle\Anchoring\Rfc3161TimestampAnchor::class,
            'tsa_url' => env('CHRONICLE_TSA_URL'),
            'tsa_certificate' => env('CHRONICLE_TSA_CERTIFICATE'),
        ],
    ],
],
```

```bash
php artisan chronicle:checkpoint --anchor   # anchor synchronously
php artisan chronicle:anchor:retry          # retry pending/failed anchors
php artisan chronicle:anchor:verify         # attest stored anchors against their sinks
```

An official AWS S3 Object Lock (WORM) adapter is available as a companion package:

```bash
composer require laravel-chronicle/anchor-s3
```

See [Anchoring](https://laravel-chronicle.github.io/docs/anchoring).

---

## Scalable verification

A full `chronicle:verify` recomputes every entry's hash - the ground-truth check. On large, ever-growing ledgers you usually don't need to re-walk all history on every run. Because checkpoints now form a verifiable chain and record the entries they cover, Chronicle can verify incrementally:

```bash
php artisan chronicle:verify                        # full ledger (default)
php artisan chronicle:verify --since-last-checkpoint # trust the last checkpoint, verify only the tail
php artisan chronicle:verify --from-checkpoint=<id>  # verify a single segment (add --to-checkpoint=<id>)
php artisan chronicle:verify --checkpoints-only      # checkpoint-chain attestation, O(checkpoints)
php artisan chronicle:verify --resume                # continue from the last recorded run
```

Combine `--checkpoints-only` with `--anchors` for a fast, externally-rooted integrity proof: an **anchored** checkpoint is a trusted fast-forward point, so verifying the recent tail since one gives strong assurance without re-walking the whole ledger.

```bash
php artisan chronicle:verify --checkpoints-only --anchors
```

Upgrading an existing ledger? Run `chronicle:checkpoints:backfill` once so historical checkpoints gain their head/count/link metadata; until then the incremental modes safely fall back to a full verify. See [Scalable Verification](https://laravel-chronicle.github.io/docs/scalable-verification).

### Verifying an arbitrary entry range

To verify a span of entries without working out checkpoint bounds yourself, pass
the first and last entry ULIDs:

```bash
php artisan chronicle:verify --from={first-entry-ulid} --to={last-entry-ulid}
```

Chronicle resolves the **signed** checkpoints that enclose the range, verifies
their signatures, and recomputes the chain between them - so the verification of
the requested rows rides on the signed checkpoint anchors, never on an entry's
own stored hash. Programmatically:

```php
use Chronicle\Verification\IntegrityVerifier;

$result = app(IntegrityVerifier::class)->verifyEntryRange($fromSequence, $toSequence);
```

If the range extends past the last checkpoint, the unanchored tail is recomputed
from the last enclosing signed checkpoint to the head - the same trust as
`--since-last-checkpoint`.

---

## GDPR erasure (crypto-shredding)

An append-only ledger seems to collide with the GDPR Article 17 right to erasure - you can't delete an entry without breaking the chain. Chronicle resolves this with **crypto-shredding**: PII-bearing payload fields are encrypted under a **per-subject key** *before* hashing, so the chain is computed over ciphertext. To honour an erasure request you destroy that subject's key. The ciphertext stays in place (the ledger still verifies, byte-for-byte), but the content is permanently unreadable. What remains is the pseudonymised *fact* that an event occurred - the evidence, not the personal data.

Encryption is **opt-in** and, when disabled, behaviour is identical to pre-1.12.

```php
// config/chronicle.php
'encryption' => [
    'enabled' => env('CHRONICLE_ENCRYPTION_ENABLED', false),
    'fields'  => ['metadata', 'context', 'diff'], // PII-bearing fields, per-subject DEK
    'kek' => [
        'provider' => Chronicle\Encryption\LocalKeyEncryptionProvider::class,
        'key'      => env('CHRONICLE_ENCRYPTION_KEY'),  // dedicated base64 32-byte key, NOT the app key
        'id'       => env('CHRONICLE_ENCRYPTION_KEK_ID', 'local'),
    ],
],
```

Fields are encrypted with the subject's data key (DEK) using XChaCha20-Poly1305-IETF, with the entry envelope bound in as associated data. Each DEK is wrapped by a key-encryption key (KEK); the default KEK is local, or you can keep it in a KMS, so it never lives in the app.

```bash
php artisan chronicle:subject:erase patient 01H...   # destroy the DEK; records a PII-free proof
php artisan chronicle:subject:keys --status=erased   # inspect subject key state (no key material)
php artisan chronicle:legal-hold place patient 01H... # block erasure/pruning under litigation hold
php artisan chronicle:encryption:rotate-kek --old-key=... # re-wrap all DEKs under a new KEK
```

Erasing a subject is itself recorded as a verifiable, PII-free `subject.erased` entry, so you can prove to a regulator that erasure happened and when. The cleartext envelope (actor, action, subject reference, timestamp, tags) stays queryable; reads of erased fields return a tombstone. Legal holds block erasure and pruning of subjects under litigation hold.

> Crypto-shredding guarantees erasure in the live store. Backups taken before erasure still contain recoverable data and are governed by your backup-retention policy. Whether retaining a pseudonymised event record satisfies a given erasure request is a legal/DPO determination - this is not legal advice.

See [Crypto-Shredding](https://laravel-chronicle.github.io/docs/crypto-shredding) and the [GDPR erasure guide](https://laravel-chronicle.github.io/docs/gdpr-erasure).

---

## Verifiable exports

Chronicle can export the ledger as a verifiable dataset (`entries.ndjson`, `manifest.json`, `signature.json`) that can be verified independently of the application:

```bash
php artisan chronicle:export storage/app/chronicle-export
php artisan chronicle:verify-export storage/app/chronicle-export
```

Verification checks the dataset hash, digital signature, hash-chain integrity, and dataset boundaries - resolving the signing key from the key ring, so exports signed by a now-retired key still verify. See [Exports](https://laravel-chronicle.github.io/docs/exports) and the [Export Format](https://laravel-chronicle.github.io/docs/export-format).

---

## Artisan commands

| Command                                     | Purpose                                                                                               |
|---------------------------------------------|-------------------------------------------------------------------------------------------------------|
| `chronicle:install`                         | Publish config and migrations (`--force`, `--migrate`)                                                |
| `chronicle:checkpoint`                      | Create a signed checkpoint                                                                            |
| `chronicle:export {path}`                   | Export the ledger as a verifiable dataset                                                             |
| `chronicle:verify`                          | Verify the full ledger, one entry (`--entry=<ULID>`), or an entry range (`--from=<ULID> --to=<ULID>`) |
| `chronicle:verify-export {path}`            | Verify an exported dataset                                                                            |
| `chronicle:stats`                           | Display ledger statistics (`--json`)                                                                  |
| `chronicle:show {id}`                       | Display a single entry by ULID                                                                        |
| `chronicle:prune`                           | Prune entries by retention policy (`--older-than`, `--before`, `--dry-run`, `--force`)                |
| `chronicle:report {path}`                   | Generate a signed compliance report (`--from`, `--to`)                                                |
| `chronicle:checkpoints:backfill`            | Backfill head/count/link metadata on existing checkpoints (`--chunk`, `--dry-run`)                    |
| `chronicle:anchor:retry`                    | Re-attempt outstanding checkpoint anchors (`--status=pending\|failed`)                                |
| `chronicle:anchor:verify`                   | Verify stored checkpoint anchors against their providers (`--checkpoint=`)                            |
| `chronicle:key:generate`                    | Generate an Ed25519 keypair for `signing.keys` (`--id`)                                               |
| `chronicle:key:list`                        | List the signing keys in the key ring (`--with-counts`)                                               |
| `chronicle:key:rotate {keyId}`              | Create a boundary checkpoint and print activation instructions for a new key                          |
| `chronicle:subject:erase {type} {id}`       | Destroy a subject's encryption key (GDPR erasure); records a PII-free proof (`--reason`)              |
| `chronicle:subject:keys`                    | Inspect subject key state, never key material (`--subject`, `--status`, `--json`)                     |
| `chronicle:legal-hold {action} {type} {id}` | Place/release a litigation hold that blocks erasure and pruning                                       |
| `chronicle:encryption:rotate-kek`           | Re-wrap all subject DEKs under a new KEK (`--old-key`, `--old-kek-id`, `--chunk`)                     |
| `chronicle:encrypt-backfill`                | Re-baseline migration: encrypt historical entries' PII (`--from`, `--chunk`, `--dry-run`, `--force`)  |

See the [Artisan Commands reference](https://laravel-chronicle.github.io/docs/artisan-commands).

---

## Features at a glance

- **Append-only ledger** with immutable Eloquent entries
- **Hash chaining** and deterministic canonical-payload hashing
- **Signing** with Ed25519 or ECDSA P-256; signed checkpoints, exports, and compliance reports
- **Key rotation** with a multi-key ring - retired keys keep verifying their own artifacts
- **External signing providers** (e.g. AWS KMS) with remote signing and local verification
- **External anchoring** of checkpoints (RFC 3161 timestamping in core; S3 Object Lock adapter) to detect tampering even under full internal compromise
- **Scalable verification** - incremental, segment, and checkpoint-only modes for large ledgers
- **GDPR erasure (crypto-shredding)** - per-subject payload encryption with key destruction; the ledger still verifies after erasure
- **Verifiable exports** with independent verification
- **Reference resolution** - turn stored `(type, id)` actors/subjects back into models or display labels, honouring morph maps
- **Automatic model auditing** via the `HasChronicle` trait or observers
- **Transactions & correlation IDs** for grouping related events
- **Diff engine** for capturing field-level changes
- **Extensible pipeline** - validators, policies, and context resolvers
- **Storage drivers** - `eloquent`/`database`, `queued`, `array`, `null`
- **Retention & pruning** with checkpoint-aware deletion
- **Read-only web UI** (optional Blade interface)
- **Events** - `EntryRecorded` and `EntryRejected`
- **Testing helpers** - `Chronicle::fake()` with fluent assertions, plus `LedgerSeeder` for verifiable DB-backed test ledgers

---

## Design principles

- **Append-only.** Entries cannot be modified or deleted; corrections are recorded as new entries.
- **Explicit intent.** Every entry names an actor, action, and subject - no ambiguous "something changed" logs.
- **Cryptographic integrity.** Entries are protected with hash chaining and signatures.
- **Low magic.** Automatic auditing is opt-in; nothing is logged behind your back.
- **Transport agnostic.** Works in HTTP requests, queue workers, CLI commands, and scheduled jobs.

Read more in [Philosophy](https://laravel-chronicle.github.io/docs/philosophy) and the [Architecture](https://laravel-chronicle.github.io/docs/architecture) and [Security Model](https://laravel-chronicle.github.io/docs/security-model) docs.

---

## Extending Chronicle

Chronicle is designed to be extended. You can write custom validators, policies, and context resolvers, swap in custom storage drivers or signing providers (for example, AWS KMS), and listen to ledger events. See the [Extending Chronicle](https://laravel-chronicle.github.io/docs/extending-chronicle) guide.

### Custom entry model

Chronicle resolves its Eloquent entry model from `chronicle.models.entry` (default `\Chronicle\Entry\Entry::class`). Point it at your own subclass to add accessors, relationships, or casts:

```php
// config/chronicle.php
'models' => [
    'entry' => \App\Models\AuditEntry::class,
],
```

```php
namespace App\Models;

use Chronicle\Entry\Entry;

class AuditEntry extends Entry
{
    // add accessors / relationships here
}
```

The override **must** extend `Chronicle\Entry\Entry` - Chronicle validates this and throws `InvalidEntryModelException` otherwise, so the append-only immutability guarantee and the hash-chain contract are always preserved. `Chronicle\Entry\Entry` is stable, subclassable public API: do not override `save()`, `update()`, `delete()`, the `$fillable` set, or the hashed columns.

---

## Roadmap

Planned for upcoming releases:

- additional anchor adapters (Sigstore/Rekor transparency log)
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
      <td style="text-align: center; vertical-align: top; width: 14.28%"><a href="https://poornachandradinesh.netlify.app/"><img src="https://avatars.githubusercontent.com/u/69423861?v=4?s=100" width="100px;" alt="Poorna Chandra Dinesh"/><br /><sub><b>Poorna Chandra Dinesh</b></sub></a><br /><a href="#code-Poorna-Chandra-D" title="Code">💻</a></td>
      <td style="text-align: center; vertical-align: top; width: 14.28%"><a href="https://github.com/ntoufoudis"><img src="https://avatars.githubusercontent.com/u/93659348?v=4?s=100" width="100px;" alt="Vasileios Ntoufoudis"/><br /><sub><b>Vasileios Ntoufoudis</b></sub></a><br /><a href="#code-ntoufoudis" title="Code">💻</a></td>
      <td style="text-align: center; vertical-align: top; width: 14.28%"><a href="https://jamesking.dev"><img src="https://avatars.githubusercontent.com/u/253237?v=4?s=100" width="100px;" alt="James King"/><br /><sub><b>James King</b></sub></a><br /><a href="#doc-Jamesking56" title="Documentation">📖</a></td>
      <td style="text-align: center; vertical-align: top; width: 14.28%"><a href="https://github.com/devc4rlos"><img src="https://avatars.githubusercontent.com/u/196385361?v=4?s=100" width="100px;" alt="Carlos Alexandre"/><br /><sub><b>Carlos Alexandre</b></sub></a><br /><a href="#code-devc4rlos" title="Code">💻</a></td>
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
