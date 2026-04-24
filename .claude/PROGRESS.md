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
