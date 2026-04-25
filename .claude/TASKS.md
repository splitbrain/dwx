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

- [done] M2-supp-01/M4-supp-01 — `lib/exe/`. js.php::js_runonstart
  annotated as html sink (commit 4901b4aa). Scan of remaining 13
  entry points (ajax/css/detail/fetch/indexer/jquery/jsonrpc/manifest/
  mediamanager/openapi/opensearch/taskrunner/xmlrpc) found no
  candidates: linear top-level dispatchers covered by psalm's
  builtin echo sink, no parameter-echoing helpers.
- [done] M2-supp-02/M4-supp-02 — `lib/tpl/dokuwiki/`. 35 files
  scanned (5 root templates + 1 CLI helper + 29 lang files); no
  function-level annotations needed. Top-level template chrome
  covered by builtin sink; lang/ files are array assignments only.
- [done] M2-supp-03/M4-supp-03 — bundled plugins. 11 of 15 modified:
    * authplain (M2-supp commit f883be3d)
    * acl (02a370a2), authad (4b8ddb38), authldap (93919e44),
      config (ae319008), extension (d82fcda1), logviewer (bafbd5ca),
      popularity (a716f46c), revert (24ecf549), styling (5599cd8a),
      usermanager (7dc3a475)
  Skipped (correctly): authpdo (PDO bind, not a string sanitizer),
  info (syntax → renderer doc), safefnrecode (action plugin),
  testing (action plugin).
- [done] M4-supp-04 — standalone plugin base files
  `lib/plugins/{action,admin,auth,cli,remote,syntax}.php` are 9-line
  deprecated autoload-only stubs with no class declarations. Nothing
  to annotate.
- [done] M4-supp-99 — psalm re-run after all supplemental work.
  Byte-identical to baseline (1 carryover Logger TaintedExtract,
  0 new, 0 resolved). Reports at /tmp/psalm-supp.{json,txt}.
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

## M5 — File-I/O sinks

Goal: annotate file-system I/O wrappers as `@psalm-taint-sink file`
(or `shell` for `io_exec`) and the path-builder helpers (wikiFN family)
as `@psalm-taint-escape file` where they sanitize unconditionally.

Scope:
- `inc/io.php` (19 io_ functions; ~885 lines).
- `inc/pageutils.php` path-builders: `wikiFN`, `metaFN`, `mediaFN`,
  `localeFN`, `resolve_id`, `resolve_pageid`, `resolve_mediaid`.
  Already annotated for `cleanID` (file escape) — these compose
  cleanID with a base directory. Check whether they sanitize
  unconditionally or accept a `$clean=false` bypass; under-annotate
  the conditional ones (consistent with the M2 `idfilter` decision).
- Other inc/ file ops: ad-hoc `fopen` / `file_put_contents` /
  `file_get_contents` outside of `io.php`. Survey by grep.
- `lib/exe` + `lib/plugins`: typically use the io_ wrappers, but
  flag any direct file ops that take user input.

### Task list

- [pending] M5-01 — Survey + annotate `inc/io.php`. 19 functions.
  Most take `$file` (a path) as first arg → `@psalm-taint-sink file`
  on that param. Special cases:
    * `io_exec($cmd, ...)` — shell sink, NOT file. Annotate as
      `@psalm-taint-sink shell` on `$cmd`.
    * `io_download($url, $file, ...)` — `$url` is an SSRF concern
      (not in our scope set today; consider scope `ssrf` if psalm
      supports it, else skip with a note); `$file` is a file sink.
    * `io_grep($file, $pattern, ...)` — `$file` is file sink;
      `$pattern` is a regex, not a sink.
    * `io_readWikiPage` / `io_writeWikiPage` — `$file` is file
      sink; `$id` is descriptive metadata, not a sink.
    * `io_mktmpdir()` — no params, no sink, skip.
  One commit per logical group (or one per function, orchestrator's
  choice). Lint each file.
- [pending] M5-02 — Survey + annotate `inc/pageutils.php` path
  builders. If `wikiFN($raw_id, '', $clean=true)` strictly calls
  cleanID, mark return as `@psalm-taint-escape file`. If
  `$clean=false` is a bypass branch, leave UN-ANNOTATED and log to
  QUESTIONS.md (mirroring the M2-01 idfilter convention).
- [pending] M5-03 — Grep for ad-hoc file I/O across `inc/`,
  `lib/exe`, `lib/plugins` (file_put_contents, file_get_contents,
  fopen, fwrite, unlink, mkdir, rename, copy used outside of io.php
  wrappers). Annotate any wrapper functions that pass tainted paths
  through. Skip top-level scripts (psalm builtins handle PHP
  natives at the immediate callsite).
- [pending] M5-04 — Re-run `vendor/bin/psalm --taint-analysis`,
  compute delta vs M4-supp-99 (1 carryover, 0 new). New findings
  expected here: file path sinks may light up if any input source
  reaches one without passing cleanID/SafeFN. Triage A/B/C as
  before. Reports go to /tmp/psalm-m5.{json,txt}.
- [pending] M5-05 — Triage new findings: real bugs → log to
  QUESTIONS.md; annotation gaps → fix and re-run; FPs → document.
  Iterate up to 2 times max.

### Notes for M5 subagents

- `@psalm-taint-sink file` flags any tainted string flowing into
  the parameter. Combined with the M2 cleanID/SafeFN escapes, this
  should produce real signal: if an input source reaches a file
  sink without passing through one of the escapes, it's flagged.
- DO NOT annotate the underlying `fopen`/`fwrite`/etc. — those are
  PHP natives that psalm models internally. Annotate only the
  DokuWiki wrapper functions.
- Path BUILDERS (wikiFN, metaFN, etc.) are escape candidates; path
  CONSUMERS (io_readFile, io_saveFile, etc.) are sink candidates.
  A function can be both if it builds a path and reads/writes it.

## M6 — Developer docs (placeholder — populated after M5)
