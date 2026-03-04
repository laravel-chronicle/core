# Diff Engine

Chronicle includes a diff engine for tracking changes.

Example:

old:
amount = 1000

new:
amount = 500

Result:

```yaml
{
  "amount": {
    "old": 1000,
    "new": 500
  }
}
```
