# Laravel Chronicle

![Packagist Version](https://img.shields.io/packagist/v/laravel-chronicle/core)
[![Tests](https://github.com/laravel-chronicle/core/actions/workflows/run-tests.yml/badge.svg)](https://github.com/laravel-chronicle/core/actions)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/laravel-chronicle/core/badge)](https://scorecard.dev/viewer/?uri=github.com/laravel-chronicle/core)

Chronicle is a tamper-evident audit ledger for Laravel applications.

## Installation

```bash
composer require laravel-chronicle/core
php artisan vendor:publish --tag=chronicle-config
php artisan vendor:publish --tag=chronicle-migrations
php artisan migrate
```

## Signing Keys

Chronicle requires explicit signing keys. Configure them in your environment:

```dotenv
CHRONICLE_PRIVATE_KEY=base64-ed25519-secret-key
CHRONICLE_PUBLIC_KEY=base64-ed25519-public-key
CHRONICLE_KEY_ID=your-key-id
CHRONICLE_SIGNING_ENFORCE_ON_BOOT=true
```

`CHRONICLE_SIGNING_ENFORCE_ON_BOOT` controls boot-time signer sanity checks in non-testing environments.

## Record Entries

```php
use Chronicle\Facades\Chronicle;

Chronicle::record()
    ->actor($user)
    ->action('invoice.created')
    ->subject($invoice)
    ->metadata(['total' => 1000])
    ->context(['request_id' => (string) Str::uuid()])
    ->tags(['billing'])
    ->commit();
```

## Record Diffs

```php
Chronicle::record()
    ->actor($admin)
    ->action('invoice.amount_changed')
    ->subject($invoice)
    ->diff([
        'amount' => ['old' => 1000, 'new' => 500],
    ])
    ->commit();
```

## Correlation And Transactions

Closure form:

```php
Chronicle::transaction(function () {
    Chronicle::record()
        ->actor('system')
        ->action('batch.started')
        ->subject('ledger')
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('batch.finished')
        ->subject('ledger')
        ->commit();
});
```

Transaction object form:

```php
$tx = Chronicle::transaction();

$tx->entry()
    ->actor('system')
    ->action('import.started')
    ->subject('ledger')
    ->commit();

$tx->entry()
    ->actor('system')
    ->action('import.finished')
    ->subject('ledger')
    ->commit();
```

## Verify Ledger Integrity

```bash
php artisan chronicle:verify
```

## Storage Drivers

Built-in drivers:

- `eloquent` (default)
- `array` (testing/in-memory)
- `null` (testing/dev black-hole)

Custom drivers can be registered via `Chronicle::extendDriver(...)`.

## Export And Verify Dataset

```bash
php artisan chronicle:export /absolute/path/to/export-dir
php artisan chronicle:verify-export /absolute/path/to/export-dir
```

## Documentation

See `docs/` for detailed documentation.

## Security

See [SECURITY.md](SECURITY.md).

## License

[MIT](LICENSE.md)
