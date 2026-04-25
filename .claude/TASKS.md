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

- [done] M4-01 — `inc/deprecated.php::ptln()` annotated.
- [done] M4-02 — survey of inc/html.php (43 funcs); 13 emit_dynamic.
- [done] M4-03 — 13 dynamic emitters in inc/html.php annotated.
- [done] M4-04 — survey of inc/Ui/* (25 files); 21 emit_dynamic methods.
- [done] M4-05 — 20 inc/Ui files annotated, 21 show methods + 14
  constructor/setter params marked, 20 file-grained commits.
- [done] M4-06 — psalm re-run: 1 carryover, 0 new, 0 resolved
  (byte-identical to baseline).

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

## Supplemental scope (added 2026-04-25 per reviewer)

Original M1/M2 scope was inc/ only. Reviewer clarified that all repo
code except `_test/` and `vendor/` is in scope, including `lib/exe`,
`lib/tpl/dokuwiki`, and the 16 bundled plugins. psalm.xml now
includes `lib/tpl/dokuwiki`. The work below is **additive** — M1, M2,
M3-baseline, and the inc/ portion of M4 stand as already done.

- [pending] M2-supp-01 — Sanitizer scan of `lib/exe/*.php` (entry-point
  scripts: ajax.php, css.php, fetch.php, indexer.php, js.php,
  manifest.php, mediamanager.php, opensearch.php, taskrunner.php,
  xmlrpc.php). Likely few sanitizers; mostly entry-point scaffolding
  that calls into inc/ code. Survey first, annotate any local
  sanitizers, escalate the rest.
- [pending] M2-supp-02 — Sanitizer scan of `lib/tpl/dokuwiki/`. The
  template has its own `tpl_*` helpers; identify any that strip or
  encode strings.
- [pending] M2-supp-03 — Sanitizer scan of bundled plugins. One subagent
  per plugin or grouped by 3-4 plugins, depending on size. Plugins:
  acl, authad, authldap, authpdo, authplain, config, extension, info,
  logviewer, popularity, revert, safefnrecode, styling, testing,
  usermanager. Plus the standalone base classes
  `lib/plugins/{action,admin,auth,cli,remote,syntax}.php`. Each plugin
  task ends with one commit covering that plugin's annotations.
- [pending] M4-supp-01 — Output-sink scan of `lib/exe/*.php`. Entry
  points typically end with `header()` + `echo` patterns; mark sinks.
- [pending] M4-supp-02 — Output-sink scan of `lib/tpl/dokuwiki/`.
  Templates emit lots of HTML; mark `tpl_*` helpers that echo
  parameter-derived content as html sinks.
- [pending] M4-supp-03 — Output-sink scan of bundled plugins (one
  task per plugin like M2-supp-03). Pay attention to admin-plugin
  `html()` methods (admin.php base class declares it) and
  syntax-plugin `render()` methods (syntax.php base class).
- [pending] M5-supp — File-I/O sinks across `lib/exe`, `lib/tpl/dokuwiki`,
  bundled plugins. Will be planned in detail when M5 starts.

### Per-plugin sub-task template (use for M2-supp-03 and M4-supp-03)

For each plugin under lib/plugins/:
1. Subagent reads `<plugin>/*.php`.
2. Identifies sanitizer functions (encoding/stripping helpers) — these
   become @psalm-taint-escape candidates.
3. Identifies emit-vs-build methods (admin plugins have `html()`
   that echoes; syntax plugins have `render()` that calls
   `$renderer->doc .= ...`).
4. Returns JSON with `sanitizers: []`, `emit_methods: []`,
   `build_methods: []`, `notes`.
5. Orchestrator commits one annotation batch per plugin.

---

## M5 — File-I/O sinks (placeholder — populated after M4 inc/ + M4-supp)

## M6 — Developer docs (placeholder — populated after M5)
