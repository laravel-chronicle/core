# Chronicle Documentation Overhaul — Plan (v1.9.1)

**Goal:** Bring the Docusaurus site (`laravel-chronicle.github.io/`) to full, accurate
coverage of Chronicle 1.9.1; add task-oriented **Guides/How-To** and an **Extending
Chronicle** section; fold the 14 root `docs/*.md` files into the site and delete that
folder so the Docusaurus install is the single source of truth.

> **Source of truth for every doc is the code.** Where this plan and the current docs
> disagree with `src/`, `config/chronicle.php`, `routes/ui.php`, or `database/migrations/`,
> the code wins. Each doc below lists the files to verify against.

---

## 1. Current State Assessment

### 1.1 Two doc trees today
- **Root `/docs/`** (14 internal `.md` files) — to be **migrated then deleted**:
  `ARCHITECTURE.md`, `DATA_MODEL.md`, `EXPORT_FORMAT.md`, `SECURITY_MODEL.md`,
  `checkpoints.md`, `diff-engine.md`, `exports.md`, `hashing.md`, `introduction.md`,
  `ledger-model.md`, `performance.md`, `philosophy.md`, `security-model.md`,
  `transactions.md`. (The `docs/superpowers/` folder is internal planning, **not** docs —
  leave it alone.)
- **`laravel-chronicle.github.io/docs/`** (25 published `.md` files) — the keeper, to be
  corrected and expanded.

### 1.2 Inaccuracies to fix (verified against source)
| Issue | Where | Correct value |
|---|---|---|
| Wrong Entry class namespace | `quick-start.md:80`, `query-api.md:9,19`, `config-reference.md:36` | `Chronicle\Entry\Entry` (not `Chronicle\Models\Entry`) |
| Laravel version range | `installation.md:11` | `^11.0 \|\| ^12.0 \|\| ^13.0` (per `composer.json`) |
| Broken footer link | `docusaurus.config.ts` footer → `/docs/overview` | `intro.md` has `slug: /overview`, so this *resolves*, but **verify** it builds; the second footer column also points at `/docs/quick-start` (ok). Confirm no other `to:` targets are dead. |
| `latestFirst()` | `query-api.md` | **Valid** — `scopeLatestFirst` exists. Keep. |

> Run a repo-wide grep for `Chronicle\Models\` and fix every hit; the canonical model is
> `Chronicle\Entry\Entry`.

### 1.3 Feature areas with NO documentation (gaps)
1. **Web UI** — read-only Blade interface (`config 'ui'`, `routes/ui.php`,
   `Http/Controllers/ChronicleUiController`, `resources/views/*`). Major 1.9 feature.
2. **Automatic Eloquent auditing** — `Eloquent/HasChronicle` trait,
   `Eloquent/ChronicleModelObserver`, `Chronicle::observe()`, `ModelDiffBuilder`.
3. **Events** — `Events/EntryRecorded`, `Events/EntryRejected`.
4. **Testing helpers** — `Chronicle::fake()` + `Testing/ChronicleAssertions`
   (`assertRecorded`, `assertRecordedCount`, `assertNothingRecorded`, `assertNotRecorded`,
   `entries`, `restore`).
5. **Compliance reports** — `chronicle:report`, `Reports/ComplianceReport`.
6. **Pruning / retention** — `chronicle:prune`, `config 'prune'`.
7. **Consolidated Artisan command reference** — no single page lists all commands.
8. **`queued` + `database` storage drivers** — `storage-drivers.md` omits both
   (`Storage/QueuedDriver`, `Storage/DatabaseDriver`, `Jobs/PersistChronicleEntryJob`,
   single-worker requirement).
9. **Guides / How-To layer** — none.
10. **Explicit "Extending Chronicle" section** — extension docs exist but are scattered and
    incomplete (no custom validators, custom reference resolvers, or events-as-extension-point).

### 1.4 Boilerplate / build hygiene
- `docs/tutorial-extras/img/*` (orphaned images, no longer referenced) → delete folder.
- `blog/2021-08-26-welcome/docusaurus-plushie-banner.jpeg` (blog is `false` in config) → delete `blog/`.
- `src/pages/index.tsx` + `index.module.css` — verify the landing page reflects Chronicle,
  not Docusaurus boilerplate; update copy/links if needed (low priority).

---

## 2. Target Information Architecture (new `sidebars.ts`)

```
Getting Started
  - Overview                     (intro.md, keep slug /overview)
  - Installation                 (UPDATE)
  - Quick Start                  (UPDATE — fix Entry namespace)
  - Recording Entries            (NEW — full builder API)

Core Concepts
  - Philosophy                   (KEEP; merge root philosophy.md)
  - Architecture                 (UPDATE; merge root ARCHITECTURE.md)
  - Data Model                   (UPDATE; merge DATA_MODEL.md + ledger-model.md, add `context`)
  - Hashing                      (KEEP; merge root hashing.md)
  - Security Model               (UPDATE; merge SECURITY_MODEL.md + security-model.md tables)

Recording & Auditing
  - Recording Entries            (see Getting Started — single page, linked here too)
  - Auditing Eloquent Models     (NEW — HasChronicle, observer, Chronicle::observe)
  - Transactions & Correlations  (UPDATE; merge transactions.md object-API)
  - The Diff Engine              (UPDATE; merge root diff-engine.md)

Validation, Policies & Context
  - Validation                   (KEEP)
  - Policies                     (KEEP)
  - Context Resolvers            (KEEP)

Storage & Performance
  - Storage Drivers              (UPDATE — add queued + database)
  - Performance & Indexing       (NEW/MERGE — performance.md + postgresql-json-indexes.md)
  - Pruning & Retention          (NEW — chronicle:prune)

Verification & Integrity
  - Integrity Verification       (KEEP)
  - Checkpoints                  (UPDATE; merge root checkpoints.md)
  - Signing & Keys               (KEEP — already covers custom providers)

Exports & Compliance
  - Exports                      (UPDATE; merge root exports.md)
  - Export Format                (UPDATE; merge root EXPORT_FORMAT.md)
  - Export Verification          (KEEP)
  - Compliance Reports           (NEW — chronicle:report)

Web UI
  - Read-Only Web UI             (NEW)

Guides / How-To                  (NEW — task-oriented)
  - Audit Eloquent models automatically
  - Audit an incoming API request
  - Use a dedicated audit database
  - Run Chronicle writes on a queue
  - Schedule checkpoints & exports
  - Rotate signing keys
  - Test code that records audit entries
  - Produce a compliance report for an auditor

Extending Chronicle              (NEW)
  - Extension Architecture       (pipeline, ExtensionStage, registry, PendingEntry)
  - Writing Entry Extensions     (deepen entry-extensions.md, link here)
  - Custom Validators
  - Custom Policies
  - Custom Context Resolvers
  - Custom Storage Drivers       (deepen)
  - Custom Signing Providers     (KMS example; cross-link signing-and-keys.md)
  - Custom Reference Resolvers
  - Listening to Events

Reference
  - Artisan Commands             (NEW — full CLI reference)
  - Query API                    (UPDATE — fix Entry namespace)
  - Configuration Reference      (UPDATE — fix Entry namespace, add ui/prune/queue blocks)
  - Reference Resolution         (KEEP)
  - Events Reference             (NEW)
  - Testing Helpers              (NEW)

Project
  - Upgrade Guide                (NEW — deprecations: modelChanges→modelDiff)
  - Changelog                    (link to GitHub CHANGELOG.md)
  - Contributing                 (link)
  - Security Policy              (link)
```

> The sidebar uses categories; set `sidebar_position` only where ordering matters within a
> category, or rely on array order. Keep `intro.md` `slug: /overview` so the footer link works.

---

## 3. Root `/docs` → Docusaurus Migration Map

| Root file | Destination in site | Action |
|---|---|---|
| `introduction.md` | `intro.md` | Already covered; copy any unique example, then drop |
| `philosophy.md` | `philosophy.md` | Reconcile, keep best version |
| `ARCHITECTURE.md` | `architecture.md` | Merge component list + pipeline stage names |
| `DATA_MODEL.md` | `data-model.md` | Add `context` field, full field table, integrity rules |
| `ledger-model.md` | `data-model.md` | Fold entry-field table + JSON example in |
| `hashing.md` | `hashing.md` | Reconcile |
| `SECURITY_MODEL.md` | `security-model.md` | Merge threat model + tampering-detection table + ops practices |
| `security-model.md` | `security-model.md` | Merge signing-provider behavior note |
| `checkpoints.md` | `checkpoints.md` | Reconcile (site version is fuller) |
| `diff-engine.md` | `diff-engine.md` | Reconcile |
| `transactions.md` | `transactions.md` | **Add the Transaction Object API** (`$tx->entry()...`, `$tx->id()`) |
| `exports.md` | `exports.md` | Reconcile (note `path` arg is required) |
| `EXPORT_FORMAT.md` | `export-format.md` | Merge manifest/signature JSON examples + verification steps |
| `performance.md` | `performance-and-indexing.md` (NEW) | Recommended indexes + large-ledger patterns |

After every file's content is represented in the site and links are reconciled:
**delete the entire root `/docs/` folder** (keep `docs/superpowers/`? No — that's under
`/docs/`; if it must survive, move `superpowers/` up to repo root first, otherwise it's
internal and can go. **Confirm with maintainer before deleting `superpowers/`.**)

> Also update root `README.md` "Architecture" links that point to `docs/ARCHITECTURE.md`
> etc. — repoint to the published site URLs (or remove), since those files will be gone.

---

## 4. New Document Specifications

Each new page: H1 title, short intro, runnable code blocks verified against source, and a
"See also" footer. Keep prose tight; prefer real signatures over prose descriptions.

### 4.1 Recording Entries  *(src: `Entry/EntryBuilder.php`, `ChronicleManager::record`)*
Full fluent API: `actor()`, `action()` (dot-notation rule, max length), `subject()`,
`metadata()`, `context()`, `diff()`, `change($field,$old,$new)`, `tags()` (normalization:
lowercase/trim/unique/sort), `correlation()`, `modelDiff()` (auto-ignores timestamps),
`build()` vs `commit()`. Note `actor('system')` special-case. Note required fields throw
`MissingActor/Action/SubjectException`.

### 4.2 Auditing Eloquent Models  *(src: `Eloquent/HasChronicle.php`, `ChronicleModelObserver.php`, `ModelDiffBuilder.php`, `ChronicleManager::observe`)*
- `HasChronicle` trait: `$chronicleEvents`, `$chronicleIgnore`, overridable
  `chronicleActor()`, `chronicleActionPrefix()`, `chronicleIgnoredFields()`. Default action
  prefix = snake_case class basename; actor = `Auth::user() ?? 'system'`.
- Observer route for third-party models: `Chronicle::observe(Invoice::class)` /
  `Chronicle::observe(Invoice::class, InvoiceObserver::class)`; extend `ChronicleModelObserver`,
  override `recordedEvents()`, `resolveActor()`, `actionPrefix()`, `ignoredFields()`.
- Explain "low magic": this is opt-in, not global.

### 4.3 Pruning & Retention  *(src: `Console/Commands/PruneCommand.php`, config `prune`)*
`chronicle:prune` options: `--older-than=N`, `--before=Y-m-d`, `--dry-run`, `--force`.
Config: `default_retention_days`, `respect_checkpoints`. Explain checkpoint-anchored entries
are protected unless `--force`.

### 4.4 Compliance Reports  *(src: `Console/Commands/ReportCommand.php`, `Reports/ComplianceReport.php`, `ComplianceReportResult.php`)*
`chronicle:report {path} --from= --to=` → signed HTML report. Document the report fields
(entry count, chain head, report hash, signature, algorithm, key id, period) and that it is
signed by the active `SigningProvider`.

### 4.5 Read-Only Web UI  *(src: `routes/ui.php`, `Http/Controllers/ChronicleUiController.php`, `Http/Middleware/ChronicleUiEnabled.php`, `resources/views/*`, config `ui`)*
Enable via `CHRONICLE_UI_ENABLED=true`. Config: `prefix`, `middleware`
(default `['web','auth','can:view-chronicle']` — show the Gate definition example),
`per_page`. Routes: `chronicle.entries.index`, `chronicle.entries.show`, `chronicle.stats`.
Note it is **read-only**. Publishing/overriding views (`--tag` for views, verify tag name in
`ChronicleServiceProvider`).

### 4.6 Artisan Commands (reference)  *(src: `Console/Commands/*`)*
One table + per-command section. Full set, with verified signatures:
- `chronicle:install {--force} {--migrate}`
- `chronicle:checkpoint`
- `chronicle:export {path}`
- `chronicle:verify {--entry=}`  ← single-entry verify is a **flag**, not a separate command
- `chronicle:verify-export {path}`
- `chronicle:stats {--json}`
- `chronicle:show {id}`
- `chronicle:prune {--older-than=} {--before=} {--dry-run} {--force}`
- `chronicle:report {path} {--from=} {--to=}`

> Fix README/CLAUDE.md drift: older docs imply `chronicle:verify` verifies a single entry —
> it verifies the **full ledger**; use `--entry=<ULID>` for one entry.

### 4.7 Events Reference  *(src: `Events/EntryRecorded.php`, `Events/EntryRejected.php`, dispatch sites in `ChronicleManager`/`PersistEntry`)*
- `EntryRecorded(Entry $entry)` — fired after persist; **note** it fires inside the queued
  job when `driver=queued`, not in the HTTP request.
- `EntryRejected(Throwable $reason, array $payload)` — fired when a validator/policy rejects.
- Example listener registration.

### 4.8 Testing Helpers  *(src: `ChronicleManager::fake`, `Testing/ChronicleAssertions.php`)*
`Chronicle::fake()` swaps in `ArrayDriver`, returns assertions object. Document
`entries()`, `assertRecorded(?callable)`, `assertRecordedCount(int,?callable)`,
`assertNothingRecorded()`, `assertNotRecorded(callable)`, `restore()`. Pest example.

### 4.9 Extending Chronicle (section)  *(src: `Contracts/*`, `Pipeline/*`, `Storage/*`, `Signing/*`, `Support/*`)*
- **Extension Architecture**: pipeline order
  `RunExtensions → CanonicalizePayload → HashPayload → ChainHashEntry → PersistEntry`;
  `ExtensionStage` enum (`VALIDATE=100`, `RESOLVE_CONTEXT=200`, `POLICY=300`, `PROCESS=400`);
  `PrioritizedEntryExtension::priority()` (lower first); registration via config `extensions`
  or `Chronicle::extendEntry()`; working with `PendingEntry` (`attribute()`, immutability).
- **Custom Validators**: implement `EntryExtension` at `VALIDATE`; throw a
  `ChronicleException` subclass; mirror `ActionValidator` pattern (incl. the
  `/** @var int */` config-cast gotcha for PHPStan level 9).
- **Custom Policies**: implement `EntryPolicy` (`enforce()` throws `PolicyViolationException`);
  extend `AbstractPolicy`; register under config `extensions` (POLICY stage). List built-ins.
- **Custom Context Resolvers**: implement `ContextResolver` (`contextKey()`, `resolve(): ?array`,
  null = skip); extend `AbstractContextResolver`; sensitive-field redaction pattern from
  `RequestContextResolver`.
- **Custom Storage Drivers**: `StorageDriver::store(array): Entry`; register via
  `extendDriver()`; reserved names (`eloquent`,`array`,`null`), single-registration rule;
  statelessness requirement.
- **Custom Signing Providers**: implement `SigningProvider` (`sign`,`verify`,`algorithm`,`keyId`);
  bind in config `signing.provider`; KMS example; note verification uses the *active*
  provider (no historical key_id resolution yet).
- **Custom Reference Resolvers**: `ReferenceResolver::resolve(mixed): Reference`; default is
  `DefaultReferenceResolver`; rebind the contract.
- **Listening to Events**: cross-link Events Reference.

### 4.10 Guides / How-To (section)
Short, opinionated, end-to-end recipes that compose the reference docs. Each ≤ ~1 screen,
copy-paste runnable, ending with "Verify it worked". Topics listed in §2.

### 4.11 Upgrade Guide
Versioning policy (export formats are versioned/stable). Deprecations:
`EntryBuilder::modelChanges()` → `modelDiff()` (removed in 2.0). Any config keys added in 1.x
(`queue`, `prune`, `ui`).

---

## 5. Storage Drivers — required update  *(src: `Storage/QueuedDriver.php`, `DatabaseDriver.php`, `DriverResolver.php`, `Jobs/PersistChronicleEntryJob.php`, config `driver`/`queue`)*
Add to `storage-drivers.md`:
- `eloquent` / `database` (alias) — synchronous default.
- `queued` — async via queue. **Must run a single worker**
  (`queue:work --queue=chronicle --tries=1`); multiple workers fork the chain. Config
  `queue.connection` / `queue.name`. Note `EntryRecorded` fires inside the job.
- Keep `array` / `null` (test/dev).

---

## 6. Execution Phases (hand-off ready)

> Suggested branch: `docs/v1.9.1-overhaul`. One PR per phase, or one PR with phase commits.

**Phase 0 — Audit & scaffold**
- [ ] Repo-wide grep + fix `Chronicle\Models\Entry` → `Chronicle\Entry\Entry`
- [ ] Fix Laravel version range in `installation.md`
- [ ] `npm i && npm run build` baseline; confirm `onBrokenLinks:'throw'` currently passes
- [ ] Delete `docs/tutorial-extras/`, `blog/`; verify build still green

**Phase 1 — Correct & merge existing pages** (no new nav yet)
- [ ] Update: installation, quick-start, query-api, config-reference, architecture,
      data-model, security-model, transactions, exports, export-format, checkpoints,
      storage-drivers
- [ ] Merge each root `/docs/*.md` per §3 map (do NOT delete root yet)
- [ ] Build green

**Phase 2 — New reference pages**
- [ ] Recording Entries, Artisan Commands, Events Reference, Testing Helpers,
      Performance & Indexing, Pruning & Retention, Compliance Reports, Read-Only Web UI
- [ ] Build green

**Phase 3 — Extending Chronicle section**
- [ ] All §4.9 pages; cross-link from existing extension/driver/signing docs
- [ ] Build green

**Phase 4 — Guides / How-To section**
- [ ] All §2 guides
- [ ] Build green

**Phase 5 — Nav, polish, cleanup**
- [ ] Rewrite `sidebars.ts` to §2 IA
- [ ] Verify/repair `docusaurus.config.ts` footer + navbar links
- [ ] Add Upgrade Guide + Project links (Changelog/Contributing/Security)
- [ ] Update landing page (`src/pages/index.tsx`) if still boilerplate
- [ ] **Delete root `/docs/` folder** (after confirming `superpowers/` handling); repoint
      root `README.md` doc links
- [ ] Final `npm run build` green; manual click-through of every sidebar entry

---

## 7. Global Exit Criteria
- [ ] Every public feature in §1.3 has a page; every command in §4.6 is documented.
- [ ] Every code sample is copied from / verified against `src/` (no invented APIs).
- [ ] No reference to `Chronicle\Models\Entry` anywhere.
- [ ] Root `/docs/*.md` deleted; no dangling links to them in README or site.
- [ ] `npm run build` passes with `onBrokenLinks:'throw'` (zero broken links).
- [ ] Sidebar matches §2; landing page and footer reflect Chronicle, not boilerplate.

---

## 8. Source-of-Truth Quick Map (for the implementer)
- Public API surface: `src/Facades/Chronicle.php` (`@method` list), `src/ChronicleManager.php`
- Builder: `src/Entry/EntryBuilder.php`
- Query: `src/Query/LedgerQuery.php`, `src/Entry/Entry.php` (scopes), `src/Contracts/LedgerReader.php`
- Config: `config/chronicle.php` (driver, connection, queue, prune, tables, signing, validation, policy, extensions, ui)
- CLI: `src/Console/Commands/*`
- Pipeline/extensions: `src/Pipeline/*`, `src/Contracts/*`, `src/Validation/*`, `src/Policy/*`, `src/Context/*`
- Storage: `src/Storage/*`, `src/Jobs/PersistChronicleEntryJob.php`
- Crypto/integrity: `src/Hashing/*`, `src/Signing/*`, `src/Checkpoints/*`, `src/Verification/*`
- Exports/reports: `src/Exports/*`, `src/Reports/*`
- Eloquent: `src/Eloquent/*`
- Web UI: `routes/ui.php`, `src/Http/*`, `resources/views/*`
- Events/testing: `src/Events/*`, `src/Testing/ChronicleAssertions.php`
- Schema: `database/migrations/*`
