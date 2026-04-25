# Progress log

Format: `YYYY-MM-DD TASK-ID status files=... commit=... notes=...`

---

2026-04-24 setup — created .claude/STATUS, TASKS.md, QUESTIONS.md, PROGRESS.md, psalm.xml; installed psalm.phar at vendor/bin/psalm (standalone, outside composer due to dokudeps/php-ixr requiring SSH git). commit=dc29d7f1
2026-04-24 M1-01 done files=inc/Input/Input.php commit=74e6aebf — 14 @psalm-taint-* lines on base Input; param/str/arr/ref as sources, int/bool with 5-scope escape
2026-04-24 M1-02 done files=inc/Input/Get.php commit=ea939a91 — class docblock note only (taint inherited)
2026-04-24 M1-03 done files=inc/Input/Post.php commit=410dd78d — class docblock note only (taint inherited)
2026-04-24 M1-04 done files=inc/Input/Server.php commit=bf162c8b — class docblock note only; $_SERVER trusted/attacker-controlled split documented

## Milestone 1 summary

All four tasks done, no blockers. 14 `@psalm-taint-*` annotations added across the Input hierarchy (all on the base class — subclasses inherit). 3 class-level docblock paragraphs added (Get/Post/Server) documenting the inheritance path for future readers.

Sources marked: `Input::param`, `Input::str`, `Input::arr`, `Input::ref` → `@psalm-taint-source input`.
Escapes marked: `Input::int`, `Input::bool` → 5-scope escape (html, sql, shell, file, has_quotes) via PHP cast semantics.
Deliberately not annotated: `__construct`, `applyfilter`, `filter`, `has`, `remove`, `set`, `valid`, `extract`.

Psalm finding count: not yet run (deferred to M3).

Next milestone (M2) broken down into 7 tasks spanning common.php, pageutils.php, SafeFN, Utf8\Clean, actions.php, auth.php, fetch.functions.php. High-confidence html/file sanitizers dominate; several medium-confidence candidates may end up as blocked review items.

2026-04-25 M2-01 done files=inc/common.php commit=dff8c7f7 — annotated hsc/buildAttributes/formText (html+has_quotes); stripctl + idfilter reviewed and skipped (logged in QUESTIONS).
2026-04-25 M2-02 done files=inc/pageutils.php commit=f057f660 — annotated cleanID (file) and prettyprint_id (html+has_quotes); utf8_encodeFN escalated to QUESTIONS.
2026-04-25 M2-03 done files=.claude/QUESTIONS.md commit=c0a4fd8a — SafeFN::encode reviewed and skipped (escalated): plain set includes `.` and `/`, no traversal protection.
2026-04-25 M2-04 done files=inc/Utf8/Clean.php commit=586f3452 — annotated stripspecials (html+has_quotes) using specialChars table; Clean::strip skipped (only strips >=128).
2026-04-25 M2-05 done files=inc/actions.php commit=cd8485c6 — act_clean annotated as 5-scope escape (html+has_quotes+file+shell+sql); regex strict to `[1-9a-z_]`.
2026-04-25 M2-06 done files=inc/auth.php commit=f2ca3d7e — auth_nameencode annotated as html+has_quotes+file+shell escape; sql skipped due to literal `%` in wildcard names.
2026-04-25 M2-07 done files=.claude/QUESTIONS.md commit=ee76c1f5 — rfc2231_encode reviewed and skipped (escalated): two return branches with diverging escape claims, omits `&`.

## Milestone 2 summary

7 of 7 tasks closed. 5 sanitizer functions annotated, 6 entries logged in QUESTIONS.md (5 review escalations + 1 informational note about the buildAttributes key-injection caveat already covered in the docblock). No application logic changed; docblocks only.

Annotations added (M2):
- `hsc()` — html, has_quotes
- `buildAttributes()` — html, has_quotes (with key-injection caveat)
- `formText()` — html, has_quotes
- `cleanID()` — file
- `prettyprint_id()` — html, has_quotes (delegates to hsc)
- `Clean::stripspecials()` — html, has_quotes (with $repl caveat)
- `act_clean()` — html, has_quotes, file, shell, sql
- `auth_nameencode()` — html, has_quotes, file, shell

Skipped with reasoning (in QUESTIONS.md):
- `stripctl()` — only strips `\x00-\x1F`, no narrow scope.
- `idfilter()` — conditional on `$ue=true`.
- `utf8_encodeFN()` — passthrough branches don't block traversal.
- `SafeFN::encode()` — `.` and `/` are plain.
- `Clean::strip()` — only removes >=128 bytes.
- `rfc2231_encode()` — diverging branch semantics.

M3 should run psalm-taint and triage from here. Items most likely to yield false negatives next milestone: the conditional sanitizers (`idfilter`, `utf8_encodeFN`) and the buildAttributes key-injection caveat.

2026-04-25 M3-01 done files=.gitignore,.claude/PROGRESS.md commit=PENDING — baseline psalm-taint run (psalm 6.16.1, 5.53s, 364.6 MB, 80.4% inferred).

## M3-01 baseline triage block

- Run date: 2026-04-25
- Psalm version: 6.16.1
- Exit code: 2
- Total taint findings: **1**
- Breakdown:
  - TaintedHtml: 0
  - TaintedFile: 0
  - TaintedShell: 0
  - TaintedSql: 0
  - TaintedHasQuotes: 0
  - TaintedInput: 0
  - TaintedTextWithQuotes: 0
  - TaintedExtract: 1
- Environmental noise (UndefinedClass / MissingFile from php-ixr SSH dep): 0
- Stderr: empty

Representative path:
- `inc/Logger.php:190` — TaintedExtract on `extract($data)` inside
  `Logger::formatLogLines()`. Source: `Throwable::getTraceAsString()`
  → `inc/ErrorHandler.php:138` → `Logger::log` $details param →
  `$data['details']` (line 134) → `$data` (line 130) →
  `formatLogLines($data)` (line 150) → `extract($data)` (line 190).

Process note: the M3-01 subagent ran psalm successfully via
`vendor/bin/psalm --report`, but its sandbox denied Edit/Write/redirect
operations under `.claude/`. The orchestrator finished the bookkeeping
(this block, gitignore additions). Future triage subagents will need
the `.claude/` write permission relaxed, or follow the "psalm writes
report files, orchestrator transcribes" pattern used here.

Baseline artifacts (now in .gitignore):
- `.claude/psalm-baseline.json` — full taint_trace
- `.claude/psalm-baseline.txt` — one-line text report
- `.claude/psalm-baseline.stderr.txt` — empty

2026-04-25 M3-02 done files=(none, disposition only) commit=PENDING — TaintedExtract triaged as known false positive.

**M3-02 (TaintedExtract @ inc/Logger.php:190)** — Disposition: known false positive, no source edit. `Logger::formatLogLines($data)` is a `protected` method called from exactly two sites inside `Logger::log` (lines 144, 150). `$data` is built at `Logger.php:130` with a fixed set of literal string keys (`facility`, `datetime`, `message`, `details`, `file`, `line`, `loglines`, `logfile`); none of those keys derive from caller input — only the values do, and `extract()` only uses keys to create variable names. `Logger::log`'s public signature accepts scalars (`$message`, `$details`, `$file`, `$line`), never an array that becomes `$data`. Therefore `extract($data)` can only produce that closed, developer-controlled set of variable names regardless of how tainted `$details` (e.g. `Throwable::getTraceAsString()` from `inc/ErrorHandler.php:138`) is. Per project rules we prefer leaving a single isolated finding documented here over adding the file's only `@psalm-suppress` annotation. If a future refactor either changes `formatLogLines` visibility or feeds externally-provided arrays into `$data`, revisit.

2026-04-25 M3-03/04 skipped — single residual finding already disposed; no further iterations needed.

2026-04-25 M3-05 done — residual finding set: 1 finding (the disposed TaintedExtract above). Categorisation: known FP. Real-bug count: 0. Annotation-gap count: 0. M4 task scaffold below is the unchanged plan from ROADMAP.md — the baseline didn't surface any sink-side gaps that would change M4's shape.

## Milestone 3 summary

Goal: "run Psalm taint-analysis, triage, identify missed sanitizers, iterate up to 3 times". 1 of 5 tasks needed real work; the rest were dispatched-and-merged because the baseline surfaced only one finding.

Tasks closed: M3-01 done (baseline run), M3-02 done (triage + disposition), M3-03/04 skipped (no residuals to iterate on), M3-05 done (residual is a single known-FP, no source changes).

Findings: 1 finding total, 0 in any narrow scope (html/file/shell/sql/has_quotes), 0 environmental. The M1+M2 annotation set produced **zero** TaintedHtml / TaintedFile / TaintedShell / TaintedSql / TaintedHasQuotes findings — Psalm is satisfied that every input source eventually reaches a recognised escape (or is suppressed by an inferred path).

This is a strong signal but not a guarantee: it means the analyzer cannot construct a taint flow from a known source to a known sink given the current annotation set. False *positives* would still be visible (this is how we caught the Logger one). False *negatives* — a tainted flow to an UNANNOTATED sink — won't show up until M4/M5 lay down sink annotations. M4 is therefore expected to *increase* the finding count as new sinks are annotated.

Next milestone (M4) breaks down into ptln() and the inc/Ui/* + inc/Form/* HTML emitters. Tasks scaffolded in TASKS.md.
