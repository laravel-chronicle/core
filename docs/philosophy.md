# Chronicle Philosophy

Chronicle is built on several guiding principles.

---

## Append-Only Ledger

Entries are immutable once written.

Chronicle intentionally does not provide APIs to:

- update entries
- delete entries
- rewrite history

If a mistake occurs, a correction must be recorded as a new entry.

---

## Explicit Intent

Every entry must include:

- actor
- action
- subject

Chronicle avoids ambiguous logs like:

"Something changed"

Instead:

"user.updated_email"

---

## Stable Contracts

Export formats are versioned.

Chronicle never silently changes export structures.

---

## Low Magic

Chronicle intentionally avoids:

- automatic model observers
- hidden framework hooks
- automatic activity logging

Every audit entry must be an explicit developer decision.

---

## Transport Agnostic

Chronicle works equally well in:

- HTTP requests
- queue workers
- CLI commands
- background jobs
