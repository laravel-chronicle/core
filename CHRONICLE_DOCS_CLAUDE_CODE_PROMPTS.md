# Claude Code Session Prompts — Chronicle Docs Overhaul

Execution prompts for `CHRONICLE_DOCS_PLAN.md`. Run sessions in order. Each phase
gates on the previous one being merged and the build green.

**Before you start:** place `CHRONICLE_DOCS_PLAN.md` at the repo root (or pass its path)
so every session can read it. Sessions run against the real Chronicle 1.9.1 tree —
`src/`, `config/`, `routes/`, `database/migrations/`, and `laravel-chronicle.github.io/`.

**Standing constraints (apply to every session):**
- **Code is the source of truth.** Where the plan or existing docs disagree with `src/`,
  `config/chronicle.php`, `routes/ui.php`, or `database/migrations/`, the code wins.
- **No invented APIs.** Every code sample, signature, command flag, and config key must be
  verified against the actual source before it lands in a doc. If you can't find it in the
  tree, don't document it — flag it instead.
- **Build stays green.** `cd laravel-chronicle.github.io && npm run build` must pass with
  `onBrokenLinks: 'throw'` at the end of every phase. No phase is "done" with a red build.
- **Plan first, then wait.** Each session: produce a concrete file-by-file plan and ask me
  any open questions, then STOP. Do not edit files until I reply "go ahead".
- **Branch:** `docs/v1.9.1-overhaul`. One commit per logical unit; reference the phase.

---

## Session 0 — Orientation (read-only)

```
Read CHRONICLE_DOCS_PLAN.md in full. Then explore the repo to confirm the plan against
reality — do NOT edit anything this session.

Tasks:
1. Confirm the two doc trees exist: root /docs/ (the 14 .md files to migrate) and
   laravel-chronicle.github.io/docs/ (the published site).
2. Verify §1.2 inaccuracies are still present: grep for `Chronicle\Models\Entry`, the
   Laravel version string in installation.md, and the footer links in docusaurus.config.ts.
3. Verify the §1.3 gaps are real by checking the source files named in §8 (Web UI,
   HasChronicle/observer, events, testing, reports, prune, queued/database drivers).
4. Run `cd laravel-chronicle.github.io && npm ci && npm run build` to capture a baseline.
   Report whether it currently passes and list any existing broken links/warnings.
5. Open question to resolve before Phase 5: how should docs/superpowers/ be handled —
   moved to repo root, kept in place, or deleted? (§3)

Output: a short report confirming/correcting the plan's assumptions, the build baseline,
and any surprises. End with a list of anything in the plan that no longer matches the code.
Do not make changes.
```

---

## Session 1 — Phase 0: Audit & scaffold

```
Execute Phase 0 of CHRONICLE_DOCS_PLAN.md on branch docs/v1.9.1-overhaul.

Scope (mechanical, low-risk only):
- Repo-wide: replace `Chronicle\Models\Entry` with `Chronicle\Entry\Entry` everywhere it
  appears in laravel-chronicle.github.io/docs/ (verify the real class in src/Entry/Entry.php
  first). Do NOT touch `latestFirst()` — it is a valid scope.
- Fix the Laravel version range in docs/installation.md to match composer.json
  (`^11.0 || ^12.0 || ^13.0` — verify in composer.json).
- Delete laravel-chronicle.github.io/docs/tutorial-extras/ and
  laravel-chronicle.github.io/blog/ (blog is disabled in config — confirm `blog: false`).

Constraints: standing constraints above. Do NOT migrate or delete the root /docs/ files
this phase. Do NOT change the sidebar yet.

Plan first: list every file you'll change/delete and the exact replacements, then wait for
"go ahead".

Exit criteria:
- [ ] Zero occurrences of `Chronicle\Models\Entry` in the site docs
- [ ] installation.md version range matches composer.json
- [ ] tutorial-extras/ and blog/ removed
- [ ] `npm run build` passes green
```

---

## Session 2 — Phase 1: Correct & merge existing pages

```
Execute Phase 1 of CHRONICLE_DOCS_PLAN.md (branch docs/v1.9.1-overhaul). The root /docs/
files stay in place this phase — we only READ them to merge content in. Do not delete them.

Two parts:

A. Update these existing site pages, verifying each against the §8 source files:
   installation, quick-start, query-api, config-reference, architecture, data-model,
   security-model, transactions, exports, export-format, checkpoints, storage-drivers.
   - storage-drivers.md MUST gain the `queued` and `database` drivers per §5
     (single-worker requirement, queue config keys, EntryRecorded firing in the job).
   - config-reference.md MUST gain the `ui`, `prune`, and `queue` config blocks.
   - transactions.md MUST gain the Transaction Object API (`$tx->entry()...`, `$tx->id()`).

B. Merge the 14 root /docs/*.md into the site per the §3 migration map (content only;
   the files themselves are deleted in Phase 5). Fold ledger-model.md into data-model.md;
   create performance-and-indexing.md from performance.md + postgresql-json-indexes.md.

Constraints: standing constraints above. Keep intro.md `slug: /overview`. Don't add new
nav categories yet (sidebar rewrite is Phase 5) — but new files may be added to the sidebar
in their existing category so the build resolves.

Plan first: a file-by-file table (page → source files consulted → what changes). Wait for
"go ahead".

Exit criteria:
- [ ] All 12 listed pages reconciled against source; samples verified
- [ ] queued + database drivers documented; ui/prune/queue in config reference; tx object API present
- [ ] Every root /docs file's unique content is represented somewhere in the site
- [ ] `npm run build` passes green
```

---

## Session 3 — Phase 2: New reference & feature pages

```
Execute Phase 2 of CHRONICLE_DOCS_PLAN.md (branch docs/v1.9.1-overhaul).

Create these pages, each verified against the §8 source map and spec'd in §4:
- Recording Entries (§4.1) — full EntryBuilder API
- Auditing Eloquent Models (§4.2) — HasChronicle, ChronicleModelObserver, Chronicle::observe
- Pruning & Retention (§4.3) — chronicle:prune
- Compliance Reports (§4.4) — chronicle:report
- Read-Only Web UI (§4.5) — config ui.*, routes, middleware/Gate, read-only note
- Artisan Commands reference (§4.6) — ALL commands with verified signatures; correct the
  `chronicle:verify` vs `--entry=` drift
- Events Reference (§4.7) — EntryRecorded (note queued-job timing), EntryRejected
- Testing Helpers (§4.8) — Chronicle::fake() + all ChronicleAssertions methods

Constraints: standing constraints above. Verify every command flag against
src/Console/Commands/*. Verify assertion method names against src/Testing/ChronicleAssertions.php.

Plan first: list the new files + the source files you'll read for each. Wait for "go ahead".

Exit criteria:
- [ ] All 8 pages created, samples verified against source
- [ ] Artisan reference lists install, checkpoint, export, verify, verify-export, stats,
      show, prune, report with correct signatures
- [ ] `npm run build` passes green
```

---

## Session 4 — Phase 3: Extending Chronicle section

```
Execute Phase 3 of CHRONICLE_DOCS_PLAN.md (branch docs/v1.9.1-overhaul).

Create the "Extending Chronicle" pages per §4.9, verified against src/Contracts/*,
src/Pipeline/*, src/Storage/*, src/Signing/*, src/Support/*:
- Extension Architecture (pipeline order, ExtensionStage enum values, PrioritizedEntryExtension,
  registration paths, PendingEntry usage)
- Custom Validators
- Custom Policies
- Custom Context Resolvers
- Custom Storage Drivers (reserved names, single-registration, statelessness)
- Custom Signing Providers (KMS-style example; note active-provider verification caveat)
- Custom Reference Resolvers
- Listening to Events (cross-link Events Reference)

Cross-link the existing entry-extensions / policies / context-resolvers / storage-drivers /
signing-and-keys pages into this section rather than duplicating them.

Constraints: standing constraints above. Mirror real patterns from the codebase
(e.g. ActionValidator for validators, RequestContextResolver redaction for context).

Plan first. Wait for "go ahead".

Exit criteria:
- [ ] All §4.9 pages created; each shows a working, source-verified contract implementation
- [ ] Existing extension docs cross-linked, not duplicated
- [ ] `npm run build` passes green
```

---

## Session 5 — Phase 4: Guides / How-To section

```
Execute Phase 4 of CHRONICLE_DOCS_PLAN.md (branch docs/v1.9.1-overhaul).

Create the task-oriented guides listed in §2 / §4.10. Each guide: ≤ ~1 screen, copy-paste
runnable end to end, composed from the reference docs, ending with a "Verify it worked" step:
- Audit Eloquent models automatically
- Audit an incoming API request
- Use a dedicated audit database
- Run Chronicle writes on a queue
- Schedule checkpoints & exports
- Rotate signing keys
- Test code that records audit entries
- Produce a compliance report for an auditor

Constraints: standing constraints above. Guides must link out to the relevant reference/
extension pages instead of re-explaining APIs. Verify each recipe against the source.

Plan first. Wait for "go ahead".

Exit criteria:
- [ ] All 8 guides created and internally linked to their reference pages
- [ ] `npm run build` passes green
```

---

## Session 6 — Phase 5: Navigation, polish, cleanup

```
Execute Phase 5 of CHRONICLE_DOCS_PLAN.md (branch docs/v1.9.1-overhaul). This is the final
phase and includes the destructive cleanup — proceed carefully.

DECISION REQUIRED FIRST: docs/superpowers/ handling — [FILL IN: move to repo root / keep /
delete]. Do not delete the root /docs folder until this is settled.

Tasks:
- Rewrite laravel-chronicle.github.io/sidebars.ts to the §2 information architecture.
- Verify/repair docusaurus.config.ts navbar + footer links (the /docs/overview footer link
  resolves via intro.md slug — confirm; fix any dead `to:` targets).
- Add the Project section: Upgrade Guide (§4.11 — modelChanges→modelDiff deprecation),
  plus links to GitHub CHANGELOG.md, CONTRIBUTING, SECURITY.
- Update the landing page src/pages/index.tsx + index.module.css if still Docusaurus
  boilerplate (Chronicle copy, correct links). Low priority — flag if time-boxed.
- DELETE the root /docs/ folder (after the superpowers decision above).
- Repoint or remove the root README.md "Architecture" links that pointed at docs/*.md.

Constraints: standing constraints above. After deleting root /docs, re-run the build and a
full link check — nothing in README or the site may point at the removed files.

Plan first, INCLUDING the superpowers decision and the exact README link edits. Wait for
"go ahead" before the deletion step specifically.

Exit criteria (also the plan's §7 global criteria):
- [ ] sidebars.ts matches §2; every entry click-through works
- [ ] No reference to `Chronicle\Models\Entry` anywhere; no dead links
- [ ] Root /docs/ deleted; superpowers handled per decision; README links repointed
- [ ] Landing page + footer reflect Chronicle, not boilerplate
- [ ] `npm run build` passes green with onBrokenLinks:'throw'
- [ ] Every public feature in §1.3 has a page; every command in §4.6 is documented
```

---

## Notes
- If any session uncovers a code/plan mismatch, it should report it and pause rather than
  silently documenting around it — the plan is subordinate to the source.
- Phases 2–4 are independent enough to parallelize across sessions if you want, but Phase 1
  should land first (it fixes shared pages and seeds merged content) and Phase 5 must be last.
