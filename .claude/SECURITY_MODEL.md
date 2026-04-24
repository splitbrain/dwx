# DokuWiki Security / Taint Model (working notes)

These are per-run orientation notes for the annotation subagents. Update as
you learn more. They are NOT authoritative documentation — that's M6.

## Input sources (taint origins)

DokuWiki wraps PHP's superglobals in the `dokuwiki\Input\*` classes
(`inc/Input/`). A global `$INPUT` of type `\dokuwiki\Input\Input` is created
early in the request lifecycle (see `inc/init.php`) and is the canonical
way core code reads request data.

| Class           | Underlying global   | Role                                            |
| --------------- | ------------------- | ----------------------------------------------- |
| `Input`         | `$_REQUEST` (ref)   | Base class, default accessor.                   |
| `Post`          | `$_POST` (ref)      | Only POST data.                                 |
| `Get`           | `$_GET` (ref)       | Only GET data.                                  |
| `Server`        | `$_SERVER` (ref)    | Headers, request metadata — high-trust-looking, attacker-controllable for most members (e.g. `HTTP_*`, `QUERY_STRING`). |

Public methods that return attacker-controlled data are taint sources.
Typed accessors like `int()`, `bool()` return primitives whose *type*
(not content) is safe, but they're still sourced from input — if they
produce a string form again (e.g. via `(string)$int`), downstream Psalm
can still treat the integer as safe from HTML/SQL/shell/file sinks.

`arr()` returns arrays whose *elements* are attacker-controlled strings.
Psalm's taint tracking propagates through arrays, so marking `arr()`'s
return tainted is correct.

The constructor `filter()` applies an arbitrary callback. We can't assume
it sanitizes anything specific — leave its return tainted.

## Sanitizers (escape points)

| Function                  | File                    | Narrow scope                         |
| ------------------------- | ----------------------- | ------------------------------------ |
| `hsc()`                   | `inc/common.php:36`     | `html` (wraps `htmlspecialchars`)    |
| `htmlspecialchars`        | PHP native              | `html` (Psalm knows this already)    |
| `cleanID()`               | `inc/pageutils.php:124` | `file` (pagename → filesystem-safe)  |
| `cleanText()`             | `inc/common.php:977`    | line-ending normalization — not a taint sanitizer |
| `formText()`              | `inc/common.php:1000`   | CRLF normalization — not a taint sanitizer |
| `stripctl()`              | `inc/common.php:96`     | strips control chars — partial; annotate carefully, not `html` |
| `SafeFN::encode()`        | `inc/SafeFN.class.php`  | `file`                               |
| `idfilter()`              | `inc/common.php:462`    | URL-safe id building — likely `html`/`url` scope |
| `buildURLparams()`        | `inc/common.php:357`    | builds `&amp;`-joined query strings — `html` |
| `buildAttributes()`       | `inc/common.php:373`    | builds HTML attributes — `html`      |

*(These are candidate sanitizers to confirm in M2. Subagents must read the
function body before annotating and annotate only the scopes the code
actually handles.)*

## Sinks (danger points)

- `echo`, `print` — implicit HTML sinks when output to the browser.
- `ptln()` — explicit HTML-emitter wrapper (M4).
- `io_saveFile()`, `io_readFile()`, `fopen`, `file_get_contents`, `file_put_contents`
   — file sinks (M5). Any argument used as a path is a `file` sink.
- `exec`, `shell_exec`, `passthru`, `system`, `popen`, `proc_open`
   — shell sinks. DokuWiki generally avoids these; confirm with grep if in doubt.
- SQL: DokuWiki core uses the sqlite helper plugin, not raw SQL. Plugin code
  is out of scope for M1–M5 but the `sql` scope is reserved for future use.

## Conventions for this codebase

1. The legacy filename `inc/Input.class.php` does not exist. Active code is
   in `inc/Input/{Input,Get,Post,Server}.php`. Map any task referring to the
   legacy path to the new locations.
2. Annotations are added only to docblocks. If no docblock exists, add a
   minimal `/** ... */` with only the annotation.
3. Existing `@param`, `@return`, `@author` tags are preserved verbatim.
4. Prefer `@psalm-taint-source input` over the synonym `@psalm-taint-source`
   because the `input` scope is the most common and clearest.
5. For typed accessors that strip tainted string content (`bool`, `int`),
   annotate `@psalm-taint-escape html`, `@psalm-taint-escape sql`,
   `@psalm-taint-escape shell`, `@psalm-taint-escape file`,
   `@psalm-taint-escape has_quotes` — the *cast* removes string content
   entirely, so all string-contextual sinks are safe.
6. `ref()` returns a reference into the superglobal; mark it tainted.
   Assignments through the reference propagate back into `$_REQUEST` — but
   Psalm can't track that, and it's an app-logic issue. Note for M3.
7. `set()` is not a source; it's an input-mutation helper. Annotate its
   `$value` parameter with `@psalm-taint-sink` only if we later discover
   somewhere it lands that must be clean. For now, leave unannotated.

## Open questions (move to QUESTIONS.md when triggered)

- Does `stripctl()` remove enough for any escape scope, or is it purely
  cosmetic? (Read body in M2.)
- `$INPUT->server->*` includes `HTTP_HOST`, `REMOTE_ADDR`, etc. Some are
  validated upstream by PHP-FPM/Apache, some aren't. We mark the whole
  class as a source and let downstream sinks catch issues — accept the
  noise.
