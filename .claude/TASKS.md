# Tasks

Format: `- [status] TASK-ID — short description (file:symbol)`

Statuses: `pending`, `in_progress`, `done`, `blocked`, `failed`.

One task per *method* or per *thematic grouping* that fits one reviewable
commit. Tasks in this milestone all touch different files (when possible) so
they can be dispatched in parallel.

---

## M1 — Input sources

Annotate public methods of the `dokuwiki\Input\Input` hierarchy. Each task
annotates one file end-to-end because the methods of a single class belong
together and commit cleanly as a unit.

- [pending] M1-01 — Annotate base `Input` class (inc/Input/Input.php)
- [pending] M1-02 — Annotate `Get` subclass (inc/Input/Get.php)
- [pending] M1-03 — Annotate `Post` subclass (inc/Input/Post.php)
- [pending] M1-04 — Annotate `Server` subclass (inc/Input/Server.php)

### M1 method inventory (for subagent reference)

**inc/Input/Input.php** (`dokuwiki\Input\Input`)
- `__construct()` — not a source (creates subobjects).
- `applyfilter($data)` — protected; applies user-set filter callback.
- `filter($filter = 'stripctl')` — returns a cloned input. Still tainted.
- `has($name): bool` — not a source (boolean membership check).
- `remove($name): void` — not a source (mutator).
- `param($name, $default = null, $nonempty = false): mixed` — **source**.
- `set($name, $value): void` — not a source (mutator); `$value` is not a sink.
- `&ref($name, $default = '', $nonempty = false): mixed` — **source** (returns a reference into the superglobal).
- `int($name, ..): int` — not a source (cast to int strips string content).
- `str($name, ..): string` — **source** (returns raw string from superglobal).
- `valid($name, $valids, $default = null)` — not a source (returns a whitelisted value from `$valids`, which is untainted at callsite).
- `bool($name, ..): bool` — not a source (cast to bool strips content).
- `arr($name, $default = [], $nonempty = false): array` — **source** (values inside array are attacker-controlled).
- `extract($name): Input` — not a source (returns `$this`; mutates access via `set`).

**inc/Input/Get.php** (`dokuwiki\Input\Get extends Input`)
- `__construct()` — binds `$_GET`.
- `set($name, $value)` — mutator; also writes `$_REQUEST`.

**inc/Input/Post.php** (`dokuwiki\Input\Post extends Input`)
- `__construct()` — binds `$_POST`.
- `set($name, $value)` — mutator; also writes `$_REQUEST`.

**inc/Input/Server.php** (`dokuwiki\Input\Server extends Input`)
- `__construct()` — binds `$_SERVER`.
- No overridden methods. All inherited accessors are still sources because
  `$_SERVER` is attacker-controllable (HTTP headers, query strings, etc.).

### Non-negotiable rules reminder for subagents

- Narrow escape scopes only. No bare `@psalm-taint-escape`.
- Preserve existing docblock content exactly — only add the `@psalm-taint-*`
  lines and a one-line justification above them.
- Don't touch implementation code.
- Run `php -l` on touched files before returning.

---

## M2 — Obvious sanitizers (break down after M1 review)

(placeholder — populated at M1 milestone boundary)

## M3 — Triage run (placeholder)

## M4 — Output sinks (placeholder)

## M5 — File-I/O sinks (placeholder)

## M6 — Developer docs (placeholder)
