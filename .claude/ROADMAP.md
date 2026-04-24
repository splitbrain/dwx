# Psalm Taint Annotation Roadmap

Feature branch: `claude/psalm-taint-annotations-awJGq`
Owner: @splitbrain (reviewer)

The goal is to teach Psalm's taint analyzer about DokuWiki's input, sanitization,
and output flows so that downstream plugins and core contributors can catch XSS,
SQLi, path-traversal, and shell-injection regressions via `psalm --taint-analysis`.
Every annotation is a PHPDoc docblock — the source is untouched. If Psalm is
unavailable the annotations still document intent.

## Milestones

### M1 — Input sources
Annotate all public methods of the `dokuwiki\Input\Input` hierarchy
(`inc/Input/Input.php`, `inc/Input/Post.php`, `inc/Input/Get.php`,
`inc/Input/Server.php`) as `@psalm-taint-source input` or explicitly
non-tainting, with a one-line justification per method. The legacy path
`inc/Input.class.php` no longer exists — the active classes live under
`inc/Input/`.

### M2 — Obvious sanitizers
Identify and annotate core sanitizers in `inc/common.php`,
`inc/pageutils.php`, `inc/html.php`, and other obvious candidates
(`inc/SafeFN.class.php`, `Utf8\Clean`, etc.). Use narrow escape scopes
(`html`, `has_quotes`, `sql`, `file`, `shell`) rather than the generic
`@psalm-taint-escape`. When a function only partially sanitizes, annotate
narrowly and note the limitation.

### M3 — Triage run
Run `vendor/bin/psalm --taint-analysis` (if available), triage findings,
identify sanitizers missed in M2, annotate them, re-run. Iterate up to 3
times. If Psalm cannot be installed (see QUESTIONS.md), triage proceeds by
static inspection of obvious call sites.

### M4 — Output sinks
Annotate HTML-output wrappers (`ptln`, HTML emitters under `inc/html.php`,
`inc/Ui/*`, `inc/Form/*`) as `@psalm-taint-sink html` where appropriate.

### M5 — File-I/O sinks
Annotate file-I/O wrappers (`io_saveFile`, `io_readFile`, `wikiFN`-derived
path builders) as sinks or escapes per actual behavior.

### M6 — Developer docs
Draft `docs/SECURITY_ANNOTATIONS.md` explaining the taint model,
conventions, and how plugin authors should annotate their own code.
Draft only — @splitbrain edits the prose.

## Non-negotiable rules (mirror of task contract)

1. Never merge to main. Stay on the feature branch.
2. Never modify application logic — docblocks only. Log suspected bugs in
   QUESTIONS.md.
3. Narrow escape scopes only: `@psalm-taint-escape html`, not bare
   `@psalm-taint-escape`. Over-escaping hides real bugs.
4. Partial sanitizers get narrow marks (`cleanID` → `file` only).
5. When in doubt, under-escape and log.
6. Don't silently reclassify existing annotations — log as a REVIEW REQUEST.
