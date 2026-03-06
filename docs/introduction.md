# Introduction

Laravel Chronicle is a tamper-evident audit ledger for Laravel applications.

Chronicle records immutable entries and verifies integrity with deterministic
payload hashing, chain hashing, checkpoints, and signed exports.

## Example

```php
use Chronicle\Facades\Chronicle;

Chronicle::record()
    ->actor($user)
    ->action('invoice.created')
    ->subject($invoice)
    ->metadata(['total' => 1000])
    ->tags(['billing'])
    ->commit();
```
