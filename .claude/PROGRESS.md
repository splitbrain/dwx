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
