# Transactions And Correlation IDs

Chronicle groups related entries with `correlation_id` values.

## Closure API

```php
use Chronicle\Facades\Chronicle;

Chronicle::transaction(function () {
    Chronicle::record()
        ->actor('system')
        ->action('job.started')
        ->subject('ledger')
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('job.finished')
        ->subject('ledger')
        ->commit();
});
```

## Transaction Object API

```php
$tx = Chronicle::transaction();

$tx->entry()
    ->actor('system')
    ->action('sync.started')
    ->subject('ledger')
    ->commit();

$tx->entry()
    ->actor('system')
    ->action('sync.finished')
    ->subject('ledger')
    ->commit();

$correlationId = $tx->id();
```
