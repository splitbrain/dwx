# Tasks

Format: `- [status] TASK-ID — short description (file:symbol)`

Statuses: `pending`, `in_progress`, `done`, `blocked`, `failed`.

One task per *method* or per *thematic grouping* that fits one reviewable
commit. Tasks in this milestone all touch different files (when possible) so
they can be dispatched in parallel.

---

## M1 — Input sources ✅ complete

- [done] M1-01 — Annotate base `Input` class (inc/Input/Input.php) — commit 74e6aebf
- [done] M1-02 — Annotate `Get` subclass (inc/Input/Get.php) — commit ea939a91
- [done] M1-03 — Annotate `Post` subclass (inc/Input/Post.php) — commit 410dd78d
- [done] M1-04 — Annotate `Server` subclass (inc/Input/Server.php) — commit bf162c8b

### M1 method inventory (kept for future reference)

**inc/Input/Input.php** (`dokuwiki\Input\Input`)
- `__construct()` — not a source (creates subobjects).
- `applyfilter($data)` — protected; applies user-set filter callback.
- `filter($filter = 'stripctl')` — returns a cloned input. Still tainted.
- `has($name): bool` — not a source (boolean membership check).
- `remove($name): void` — not a source (mutator).
- `param($name, $default = null, $nonempty = false): mixed` — **source**.
- `set($name, $value): void` — not a source (mutator); `$value` is not a sink.
- `&ref($name, $default = '', $nonempty = false): mixed` — **source**.
- `int($name, ..): int` — 5-scope escape (html, sql, shell, file, has_quotes) via (int) cast.
- `str($name, ..): string` — **source**.
- `valid($name, $valids, $default = null)` — no annotation; result comes from caller `$valids`.
- `bool($name, ..): bool` — 5-scope escape via (bool) cast.
- `arr($name, $default = [], $nonempty = false): array` — **source**.
- `extract($name): Input` — not a source.

**Get / Post / Server** — class-level docblock notes only; taint inherited.

---

## M2 — Obvious sanitizers ✅ complete

Break down by file. Annotate narrow escape scopes only. When in doubt,
return `status: blocked` with reasoning — do NOT over-escape.

High-confidence candidates should annotate cleanly. Medium-confidence
candidates require the subagent to read the function body and decide
whether to annotate or escalate. Anything NOT a sanitizer (just
normalization/validation) gets no annotation and should be skipped.

### Task list

- [done] M2-01 — `inc/common.php` — commit dff8c7f7. hsc/buildAttributes/formText annotated html+has_quotes; stripctl and idfilter reviewed and skipped (QUESTIONS).
- [done] M2-02 — `inc/pageutils.php` — commit f057f660. cleanID annotated file; prettyprint_id html+has_quotes; utf8_encodeFN escalated.
- [done] M2-03 — `inc/SafeFN.class.php` — commit c0a4fd8a. encode skipped (`.` and `/` are plain); decode/validateSafe never in scope.
- [done] M2-04 — `inc/Utf8/Clean.php` — commit 586f3452. stripspecials annotated html+has_quotes; strip skipped.
- [done] M2-05 — `inc/actions.php` — commit cd8485c6. act_clean annotated 5-scope.
- [done] M2-06 — `inc/auth.php` — commit f2ca3d7e. auth_nameencode annotated html+has_quotes+file+shell.
- [done] M2-07 — `inc/fetch.functions.php` — commit ee76c1f5. rfc2231_encode skipped (escalated).

### M2 general notes for subagents

- `cleanText()`, `utf8_decodeFN()`, `SafeFN::decode()`, `SafeFN::validateSafe()`, `Clean::replaceBadBytes()`, `Clean::deaccent()`, `Clean::romanize()`, `Clean::isASCII()`, `Clean::isUtf8()` are **not** sanitizers. Do not annotate them.
- No core SQL or shell sanitizers were found in discovery — none expected in M2.
- If a function wraps another already-annotated sanitizer (e.g. `buildAttributes` → `hsc`), prefer marking the wrapper with the same scope AND add a one-line justification mentioning the delegation.

---

## M3 — Triage run

Run `vendor/bin/psalm --taint-analysis`, triage findings, identify
sanitizers missed in M2, annotate them, re-run. Iterate up to 3 times.

Each iteration is a separate task so the orchestrator can dispatch a
fresh triage subagent per iteration with a clean context.

### Task list

- [done] M3-01 — Baseline psalm-taint run. **1 taint finding**:
  TaintedExtract at inc/Logger.php:190. No html/file/shell/sql/etc.
  findings. Environment is clean (no php-ixr loader noise). Subagent
  blocked on `.claude/` writes; orchestrator finished bookkeeping.
- [done] M3-02 — Triage iteration 1. Single finding disposed as known
  FP. `Logger::formatLogLines` is protected with literal-key `$data`,
  so `extract()` cannot create attacker-named variables. Reasoning in
  PROGRESS.md.
- [skipped] M3-03 — Triage iteration 2.
- [skipped] M3-04 — Triage iteration 3.
- [done] M3-05 — Residual documentation. 1 finding, classified as known
  FP, 0 real bugs, 0 annotation gaps. M4/M5 scaffolds below stand. Read the M3-01 finding list,
  identify obviously-missed sanitizers (functions clearly named
  `*encode*`, `*sanitize*`, `*escape*`, `*clean*`, `*safe*` or wrapping
  `htmlspecialchars`/`rawurlencode`/`preg_replace` with stripping
  semantics) and annotate them following the M2 conventions. Up to ~10
  annotations max in this iteration. Re-run psalm and record the new
  finding count.
- [pending] M3-03 — Triage iteration 2. Same instructions as M3-02 but
  on the residual findings. Stop earlier if findings have converged
  (delta < 5%). Investigate any reviewer guidance from M2 review (e.g.
  buildAttributes key callers, idfilter `$ue=false` callers).
- [pending] M3-04 — Triage iteration 3 (only if needed). Same
  instructions. After this, the residual finding set is the reportable
  baseline.
- [pending] M3-05 — Document residual findings. Categorize remaining
  psalm-taint findings as (a) real bugs needing app-logic fixes (log
  to QUESTIONS.md, do NOT fix), (b) annotation gaps to defer to M4/M5
  (HTML sinks / file-I/O sinks specifically), (c) known false
  positives. Write the categorized list into PROGRESS.md and adjust
  the M4/M5 task scaffolds in TASKS.md to incorporate any new sinks
  discovered.

### M3 general notes for subagents

- Psalm phar lives at `vendor/bin/psalm`. Config is at `psalm.xml`.
- Run with `--taint-analysis` flag. Pipe stderr separately so config
  errors are easy to spot.
- If psalm cannot resolve some classes (the dokudeps/php-ixr SSH issue
  noted at setup), filter those errors out — they are environmental,
  not analysis findings.
- DO NOT annotate functions that are not sanitizers just to silence a
  finding. That's the over-escape antipattern. When unsure, leave
  unannotated and add to the M3-05 residual set.
- Each iteration is one commit per annotation batch (could be multiple
  if grouping by file makes sense). Keep commits small.

---

## M4 — Output sinks

Annotate functions that emit HTML directly (echo / print / templated
output) with `@psalm-taint-sink html`. Functions that merely *return*
HTML strings are NOT sinks — they hand the string to an upstream
caller, which may or may not be a sink. Be especially careful with the
Form/Ui classes: most have `toHTML()` methods returning strings, so
those should be skipped.

The expected effect of M4 is that psalm finding counts go *up* as new
sinks come online and previously-untraced flows are caught.

### Task list

- [pending] M4-01 — `inc/deprecated.php`: `ptln()` always echoes its
  argument. Annotate `@psalm-taint-sink html`. Note `@deprecated` tag
  in the existing docblock — annotation still useful for plugin
  back-compat scans.
- [pending] M4-02 — Survey `inc/html.php` (43 functions) — classify
  each as `emit` (echoes/prints) vs `build` (returns string). No
  source edits in this task; produce a list back to the orchestrator
  via JSON `survey: { emit: [...], build: [...] }`. The orchestrator
  uses this to scope M4-03.
- [pending] M4-03 — Annotate the M4-02 `emit` set with
  `@psalm-taint-sink html`. Group commits by reasonable thematic
  chunks if the list is large; one commit per file is fine here since
  the file is one big collection of free functions.
- [pending] M4-04 — Survey `inc/Ui/*` — for each class, classify the
  `show()` / `toHTML()` / `tplCommon()` etc. method as emit vs build
  vs neither. Same JSON-back pattern as M4-02. Note any classes that
  use Form/HTMLElement internally — those are likely return-only.
- [pending] M4-05 — Annotate the M4-04 `emit` set. Per-class commits.
- [pending] M4-06 — Re-run `vendor/bin/psalm --taint-analysis`,
  capture finding count and breakdown deltas vs the M3-01 baseline.
  If new findings surface, append a triage block to PROGRESS.md and,
  if any are real bugs, log to QUESTIONS.md. (M5 will further triage
  these once file-I/O sinks are also annotated.)

### Notes for M4 subagents

- Form classes (`inc/Form/*`) appear to use a return-string pattern
  (`Form::toHTML()` builds a string, callers echo it). Skip Form/*
  in M4 unless a survey turns up an emitter — log to QUESTIONS.md
  if you find one.
- `inc/Ui/Login.php` and similar emit `print` directly inside their
  `show()` methods; those *are* sinks.
- A function that takes a string parameter and emits it via `echo`
  is the canonical taint-sink shape: `@psalm-taint-sink html` on the
  parameter.
- Functions that emit STATIC strings (no parameters or only literal
  output) are NOT sinks; nothing to annotate.

---

## M5 — File-I/O sinks (placeholder — populated after M4)

## M6 — Developer docs (placeholder — populated after M5)
