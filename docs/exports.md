# Exports

Chronicle exports a deterministic dataset that can be verified independently.

## Export

```bash
php artisan chronicle:export /absolute/path/to/export-dir
```

## Verify Export

```bash
php artisan chronicle:verify-export /absolute/path/to/export-dir
```

## Output Files

An export directory contains:

- `entries.ndjson`
- `manifest.json`
- `signature.json`
