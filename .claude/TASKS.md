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

## M3 — Triage run (placeholder — populated after M2 review)

## M4 — Output sinks (placeholder)

## M5 — File-I/O sinks (placeholder)

## M6 — Developer docs (placeholder)
