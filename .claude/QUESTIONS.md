# Questions for the reviewer

Log format: one `###` per question with a bold task id prefix. The
orchestrator appends here when a subagent returns `blocked`. It also
appends a `## MILESTONE N REVIEW REQUEST` section at each milestone
boundary.

---

### **M2-01** `idfilter()` is a conditional sanitizer

`inc/common.php:462` `idfilter($id, $ue = true)` rawurlencodes when
`$ue=true`, making the output `html`/`has_quotes`-safe. When `$ue=false`
it only does `strtr(':','/')` or `strtr(':',';')` and returns — no
escaping. Because Psalm escape annotations apply unconditionally, I did
not annotate.

Options: (a) leave unannotated (current); (b) annotate `html` +
`has_quotes` and accept a false-negative when a caller passes `$ue=false`
into an HTML sink; (c) split the function into two. Recommend (a) plus a
grep of callers in M3 to see whether any caller uses `$ue=false` before
an HTML sink.

### **M2-01** `buildAttributes()` does not escape attribute *keys*

`inc/common.php:373` HTML-encodes values via `hsc()` but emits keys raw
into `$key . '="'...`. I annotated the return as `html` + `has_quotes`
because that is true of the structural output, but a caller that passes
a user-controlled key (unusual but possible) will punch through the
escape. Worth a scan during M3 for callers that build `$params` from
`$INPUT->*` keys.

### **M2-07** `rfc2231_encode()` is a header builder, not a taint sanitizer

`inc/fetch.functions.php:121` produces a Content-Disposition-style
fragment `name=value` or `name*=charset'lang'value`. The `$name`
parameter is raw-concatenated (no encoding at all), and the `$value`
parameter takes a no-encoding fast path when it does not contain any
of the regex's special characters. Because the regex omits `&`, an
`&` in `$value` passes through unencoded into the `name="value"`
form, breaking an html escape claim. The function is intended as a
header sink helper — escape semantics differ between the two return
branches, so I left it unannotated. Recommend treating its caller
sites as direct header sinks during M5 instead.

### **M2-04** `Clean::strip()` deliberately unannotated

`inc/Utf8/Clean.php:63` strips bytes >=128, leaving only ASCII. ASCII
still includes `<`, `>`, `"`, `'`, `&`, `..`, `/`, etc., so this is
not a sanitizer for any narrow scope. Companion to `stripspecials()`
which IS annotated.

### **M2-03** `SafeFN::encode()` encodes bytes, does not block path traversal

`inc/SafeFN.class.php:49` produces ASCII output using only
`0-9a-z_.-%/[]`. The `plain` set includes both `.` and `/`, so an input
containing `../foo/bar` is returned verbatim (all characters are already
plain). It protects against non-ASCII bytes, null bytes, backslashes,
and control characters — but not path traversal. Following the same
reasoning as utf8_encodeFN, I left it unannotated. If the reviewer
wants `file` scope applied on the grounds that the caller is expected
to pre-clean the input (in practice `cleanID()` runs upstream), say so
and I will add the annotation with a justification note.

### **M2-02** `utf8_encodeFN()` is not a path-traversal sanitizer

`inc/pageutils.php:703` has two passthrough branches: (a)
`$conf['fnencode'] == 'utf-8'` returns the file name unchanged, (b)
`$safe=true` plus the regex `^[a-zA-Z0-9/_\-\.%]+$` returns the name
unchanged — and that regex matches `../../etc/passwd`. Only the
`SafeFN::encode` and `urlencode` branches actually transform the input.
Because the escape would not hold in the common cases, I did not
annotate `file`. TASKS.md suggested `file` here; recommend leaving
unannotated and treating path-traversal prevention as `cleanID()`'s
responsibility upstream of this function.

### **M2-01** `stripctl()` deliberately unannotated

`inc/common.php:96` strips only `\x00-\x1F`. That does not make a string
safe for `html`, `sql`, `shell`, or `file` sinks (none of those
characters are control chars). Not a taint sanitizer — no annotation
added, no further action needed. Logging here so the decision is visible.

---

## MILESTONE 2 REVIEW REQUEST

All seven M2 tasks closed. Eight functions across six files now carry
narrow `@psalm-taint-escape` docblock entries; six functions were
reviewed and intentionally left unannotated.

### Decisions that need your sign-off

The six items above (idfilter, buildAttributes-keys, rfc2231_encode,
Clean::strip, SafeFN::encode, utf8_encodeFN, stripctl) are the calls I
made unilaterally where the code is genuinely ambiguous. I prefer
under-escaping per the rules, but several of these would be reasonable
to annotate if you read them differently:

- **`SafeFN::encode()`** — strongest case for adding `file` if you
  consider the typical caller pipeline (cleanID -> SafeFN). Say the
  word and I will add it with a justification note.
- **`utf8_encodeFN()`** — same situation. If you confirm cleanID is
  always upstream in the file-sink path, I will annotate.
- **`idfilter()`** — could split or could blanket-annotate `html` and
  accept a false-negative for `$ue=false` callers. Recommend a grep
  in M3 to see if any caller passes `$ue=false` before an HTML sink.
- **`buildAttributes()` keys** — annotated as html-safe under the
  assumption keys are static. M3 should grep for callers passing
  `$INPUT->*` keys into the `$params` array.

### Architectural questions

- **Plugin scope.** ~~All M1/M2 work is inside `inc/`. Plugin code in
  `lib/plugins/` is out of scope for now.~~ **Reviewer correction
  (2026-04-25):** all repo code except `_test/` is in scope, including
  `lib/exe`, `lib/tpl/dokuwiki`, and bundled plugins
  (`lib/plugins/{acl,authad,authldap,authpdo,authplain,config,extension,info,logviewer,popularity,revert,safefnrecode,styling,testing,usermanager}`
  plus `lib/plugins/{action,admin,auth,cli,remote,syntax}.php` standalone
  base classes). Supplemental tasks added to TASKS.md (M2-supp, M4-supp,
  M5-supp). psalm.xml now also includes `lib/tpl/dokuwiki`. M3-01
  baseline already covered `inc/`, `lib/plugins`, `lib/exe`; the
  re-baseline after lib/tpl inclusion belongs to the supplemental
  tasks.
- **`$INPUT->server` granularity.** The whole class is a source. Some
  members (e.g. `SCRIPT_NAME`) are typically trusted; others (`HTTP_*`,
  `QUERY_STRING`) are attacker-controlled. Splitting into trusted and
  untrusted accessors would require code changes — out of scope. The
  catch-all source annotation produces noise but is correct.
- **SQL scope.** Core has no raw-SQL sinks; the sqlite helper plugin
  uses prepared statements. The `sql` taint scope is reserved but will
  not see traffic in M3-M5 unless we also pull in the sqlite plugin.

### M3 readiness

Psalm is installed at `vendor/bin/psalm` (standalone phar). M3 tasks
are scaffolded in TASKS.md ready for the next run. The first task is
the baseline psalm run — the finding count delta drives the rest.

---

## MILESTONE 3 REVIEW REQUEST

M3 closed without a stop at the gate per direct instruction to
continue. The full milestone produced a single finding (TaintedExtract
in `inc/Logger.php`), disposed as a known false positive — see
PROGRESS.md for the reasoning. No source edits were made in M3.

### Decisions worth your eye

1. **Logger TaintedExtract.** I chose to *document* the finding rather
   than add `@psalm-suppress TaintedExtract` to `formatLogLines()`.
   Rationale in PROGRESS.md. If you'd rather have an explicit
   suppression in the source for future-readability, say so and I'll
   add it.
2. **Skipped iteration loop.** TASKS.md provisioned three triage
   iterations. With only one finding to triage, M3-03 and M3-04 were
   marked `skipped`. The implication: once a baseline is clean, the
   iterate-up-to-3-times rule from ROADMAP becomes a no-op.

### Notable gap (not a question, just visibility)

The "0 narrow-scope findings" result is *consistent with* M1+M2 doing
their job — but absence of findings does not prove correctness. M4
will turn on sink-side annotations and is *expected* to produce real
findings; that's the proof point.

### Process note

The M3-01 baseline subagent's sandbox denied writes under `.claude/`.
The orchestrator transcribed the data the subagent had captured to
`.claude/psalm-baseline*` (now gitignored). M3-02's subagent did not
need to write under `.claude/` so the issue did not recur. If future
triage tasks need direct subagent writes, the sandbox config will need
the path opened.

---

## MILESTONE 4 (incl. supplemental scope) REVIEW REQUEST

### Summary

Inc/-side M4 (M4-01..06) plus supplemental scope (lib/exe, lib/tpl/dokuwiki,
11 of 15 bundled plugins) is closed. 50+ sink/escape annotations added
across 21 inc/ files + 11 plugins + 1 lib/exe entry point. Three
independent psalm runs (baseline, post-M4-inc, post-supplemental) all
produce **byte-identical** results: 1 finding total, the documented
Logger TaintedExtract FP. Zero new findings, zero resolved.

### Decisions worth your eye

1. **Three psalm runs, identical output.** Each annotation pass added
   real sinks but produced no new findings. We have two interpretations
   (closed-graph correctness vs. trace damping through framework
   indirection) and one weak data point against trace damping
   (usermanager.htmlInputField callers all pass literals/concatenations
   that wouldn't trigger even a perfectly-traced analysis). A synthetic
   tainted-source experiment is the proper proof; logged below as
   future work.
2. **authpdo, info, safefnrecode, testing skipped.** authpdo uses PDO
   bindValue (not a string sanitizer). info is a syntax plugin (writes
   to `$renderer->doc`, not a direct sink). safefnrecode and testing
   are action plugins with no UI sinks. Skipping means these plugins
   contribute zero annotations; if you want stub `@psalm-suppress`
   notes for future readers, say so.
3. **lib/exe and lib/tpl/dokuwiki produced 0 commits each.** lib/exe
   entry points are linear top-level dispatchers (psalm builtin echo
   sink covers them); lib/tpl/dokuwiki files are top-level template
   chrome plus 29 lang/*.php array assignments. The one exception is
   lib/exe/js.php::js_runonstart, annotated separately.
4. **Standalone plugin base files are deprecated stubs.** All six of
   `lib/plugins/{action,admin,auth,cli,remote,syntax}.php` are 9-line
   DebugHelper deprecation warnings. Real base classes are in
   `inc/Extension/`. Nothing to annotate.

### Future work (logged, not blocking)

- **Synthetic-source experiment.** Inject a known-tainted value at a
  callsite that should reach a marked sink (e.g. directly bind a
  `@psalm-taint-source input` to one of usermanager.htmlInputField's
  $value-flow callers) and confirm psalm flags the resulting trace.
  This distinguishes closed-graph correctness from trace damping. One
  branch, one test annotation, one psalm run; revert when done.
- **M5 file-I/O sinks.** fopen/file_put_contents/io_saveFile and
  related across inc/, lib/exe, lib/tpl/dokuwiki, and the bundled
  plugins. Scaffolded in TASKS.md as M5-supp.
- **htmlInputField $id/$name caveat.** The parameters are interpolated
  unescaped. Current callers all pass literals; if a future caller
  passes user input through, the sink annotation will fire. Worth a
  comment in the function body, but not in scope for this branch.

---

### **M5-02** wikiFN/mediaFN/resolve_id are conditional sanitizers

Three path-builder functions in `inc/pageutils.php` accept a `$clean = true` bypass parameter:

- `wikiFN($raw_id, $rev = '', $clean = true)` (line 336) — `if ($clean) $id = cleanID($id);`
- `mediaFN($id, $rev = '', $clean = true)` (line 461) — `if ($clean) $id = cleanID($id);`
- `resolve_id($ns, $id, $clean = true)` (line 515, deprecated) — `if ($clean) $id = cleanID($id);`

When the caller passes `$clean = false`, the input is concatenated into a filesystem path with no in-function sanitization. Same shape as the M2-01 `idfilter()` decision: Psalm escape annotations apply unconditionally, so a blanket `@psalm-taint-escape file` would produce a false negative for `$clean = false` callers.

Known call sites in core that pass `false` exist: `page_exists()`, `media_exists()` (this file), `resolve_pageid`/`resolve_mediaid` (which then re-feed into wikiFN/mediaFN). Those callers do their own pre-clean via `cleanID()` or via `MediaResolver`/`PageResolver` (which themselves call `cleanID`), so the trace from a real user-input source through `$clean=false` would have to bypass that pre-clean — possible but path-dependent.

Options: (a) leave unannotated (current); (b) annotate `file` on all three and accept FNs for `$clean=false` callers that don't pre-clean; (c) split each function into a `*_clean` and `*_raw` pair (invasive). Recommend (a) plus an audit of every `$clean=false` call site to confirm pre-cleaning.

Follow-up: `localeFN($id, $ext='txt')` at line 486 has *no* sanitization at all; it relies entirely on caller obligation (core callers pass static literals like `'editrev'`, `'denied'`). Not annotated — flagged here for visibility, not for action.

### **M5-03** Audit suggestions from ad-hoc file-I/O scan (informational)

Three call sites flagged during the scan as worth a closer look but **not** annotated; all currently safe-by-construction:

- `inc/auth.php:1242` — `$tfile` path is built from a token but the token is `preg_replace`'d to hex-only before path use. Same pattern in `inc/Action/Resendpwd.php`. Safe today; if the regex ever loosens, the path becomes a sink.
- `inc/media.php:316` — `media_upload_xhr` reads `php://input` then writes to a tmp path `md5($id)`. ID flows from `$INPUT->get->str('qqfile')` and is hashed before path use. Safe by hash, but worth a future audit if the hash step ever changes.
- `lib/plugins/extension/Installer.php:132` — `installFromUpload` moves `$_FILES tmp_name` to `$tmp/$tmpbase.archive` where `$tmpbase` comes from `fileToBase($_FILES[$field]['name'])`. The wrapper `installFromArchive` is annotated; the upload-name flow into `fileToBase` could use a sanitizer audit (does `fileToBase` strip path separators? does it block `.`-prefixed names?).

---

## MILESTONE 5 FOLLOW-UP AUDITS

After M5-04, the orchestrator ran four targeted caller-grep audits to follow through on the latent concerns logged above and from M2/M3. Resolutions below; one new finding surfaced.

### **M2-01** `idfilter($id, $ue=false)` — RESOLVED

Audit found exactly two non-test callers in core that pass `$ue=false`:

- `inc/html.php:212` (`html_btn`) — result is concatenated into a `<form action="$script">` attribute; the input is the global `$ID`/page-id which is already `cleanID`-sanitized upstream. The only characters `idfilter($id,false)` introduces are the colon→slash/semicolon `strtr` — neither is an HTML metacharacter.
- `inc/media.php:1746` — value passed to `media_managerURL()` → `wl()` URL-builder, which re-encodes. Flow dies at a non-HTML sink.

Verdict: zero risky-html-flow callers. Concern closed. No annotation change needed.

### **M2-01** `buildAttributes()` keys — RESOLVED

~50 call sites across `inc/` and bundled plugins surveyed. The dominant pattern is a freshly-built array literal with hardcoded keys directly above the `buildAttributes()` call. Four superficially-dynamic groups traced one frame up:

- `inc/template.php:433` (`_tpl_metaheaders_action`) — keys come from `TPL_METAHEADER_OUTPUT` event subscribers. Plugin-author trust boundary, not request taint.
- `inc/template.php:1243` (`_tpl_img_action`) — `$data['params']` originates with literal keys (`width`/`height`/`class`/`alt`/`title`/`src`); `TPL_IMG_DISPLAY` event likewise plugin-author surface.
- `inc/parser/xhtml.php:1939`/`:2011` (`_video`/`_audio`) — `$atts` built one frame up at `xhtml.php:1744` with literal keys.
- `inc/Form/*` and `inc/form.php` element helpers — `Element::attr($name, ...)`'s `$name` is a hardcoded string at every observed call site.

Verdict: zero `$INPUT`-to-key flows in core. Concern closed. The function's docblock correctly delegates key-control to the caller as a contract.

### **M2-07** `rfc2231_encode($name, $value)` — RESOLVED

Two callers in core, both at `inc/fetch.functions.php:82` and `:87` inside `sendFile()`:

- Both pass the **literal string** `'filename'` as `$name`. Header-injection via `$name` is structurally impossible.
- Both pass `PhpString::basename($orig)` as `$value`, where `$orig` traces to a media file path resolved from a `cleanID`-sanitized media ID.

Correction to the original concern: the regex `\x00-\x20` **does** cover `\r` (0x0D) and `\n` (0x0A). The original claim that the regex "omits CR/LF" was wrong; CR/LF are encoded into the rfc2231 form, so header injection via `$value` is not reachable even if a `FETCH_MEDIA_STATUS` plugin tampered with `$orig`.

Verdict: zero risky-user-input callers. Concern closed.

### **M5-02** `wikiFN`/`mediaFN`/`resolve_id` `$clean=false` callers — RESOLVED

- **Direct `$clean=false` callers**: zero non-test callers for `wikiFN`, `mediaFN`, or `resolve_id`. (Side note: `resolve_id` has **zero live callers anywhere** in the project — candidate for removal in a future cleanup branch.)
- **Indirect via wrappers**: `page_exists($id, $rev, $clean)` and `media_exists($id, $rev, $clean)` forward `$clean` directly into `wikiFN`/`mediaFN` in one hop.
- **Eight in-tree call sites pass `false` to those wrappers**: 7 to `page_exists` (`inc/fulltext.php:159`, `:197`, `:231`; `inc/parser/xhtml.php:906`; `inc/Cache/CacheRenderer.php:50`; `inc/Search/Indexer.php:641`; `inc/pageutils.php:627`) and 1 to `media_exists` (`inc/pageutils.php:596`).

Every one of the eight is `safe-pre-cleaned`: the ID arg has either (a) been written by the resolver chain (`PageResolver::resolveId` / `MediaResolver::resolveId`, both terminating with `cleanID()`), or (b) come from an internal datastore (search index, metadata `references` map) whose entries are only written by code paths that already `cleanID`'d the value.

Verdict: zero risky-raw-id callers. The trust chain holds. The existing `@psalm-taint-escape` annotations on `resolve_pageid`/`resolve_mediaid` correctly model the resolver guarantee. No annotation change needed.

### **M5-02** `localeFN($id, $ext='txt')` callers — RESOLVED

Two direct callers, both wrappers:

- `p_locale_xhtml()` (`inc/parserutils.php:129`) — 22 transitive callers, all static literals (`'denied'`, `'norev'`, `'login'`, `'admin'`, etc.). The single nominally-dynamic case (`inc/Ui/Editor.php:160` `$data['intro_locale']`) gets values from an internal switch over `'edit'`/`'editrev'`/`'read'` literals, exposed through `EDIT_FORM_ADDTEXTAREA` event for plugin tampering.
- `rawLocale()` (`inc/common.php:1024`) — 5 callers; all static literals (`'mailwrap'`, `'password'`, `'pwconfirm'`, plus `SubscriptionSender::send`'s `$template` which is itself one of 4 hardcoded literals).

`$ext` is only ever `'txt'` (default) or `'html'` (`Mailer.class.php:217`). Plugin-event mutation is plugin-trust-API scope, not request taint.

Verdict: zero risky-user-input callers. Concern closed.

### **M5-03** Installer upload-name flow — RESOLVED

`fileToBase($name)` at `lib/plugins/extension/Installer.php:431`:

1. `PhpString::basename()` strips path separators.
2. `preg_replace` strips known archive-extension suffixes (`tar.gz`, `zip`, `archive`, etc.).
3. `preg_replace('/\W+/', '', $base)` — **whitelist** filter, only `[A-Za-z0-9_]` survives.
4. Falls back to the literal `'upload'` if the result is empty.

No passthrough branches. Concatenated with a server-generated `io_mktmpdir()` directory and a fixed `'.archive'` suffix before reaching `move_uploaded_file`/`installFromArchive`. Verdict: **verified-safe** for the upload-name flow specifically.

Archive extraction (Tar/Zip via `splitbrain/php-archive`): `FileInfo::cleanPath()` (`vendor/splitbrain/php-archive/src/FileInfo.php:281-297`) converts backslashes, drops empty/`.` segments, pops on `..`, trims leading slashes. Classic Zip Slip on entry names is **mitigated**.

Authn/authz: admin-only via `AdminPlugin::forAdminOnly()`; `checkSecurityToken()` enforced at `lib/plugins/extension/admin.php:37`.

### **M5-03** Installer plugin.info.txt `base` path traversal — **NEW FINDING**

The upload-name and zip-extraction audits both came back clean, but the audit surfaced a separate write-path-traversal in the install step that was not in the original concern list:

- `lib/plugins/extension/Extension.php:129-133` — `Extension::initFromDirectory` reads `$localInfo['base']` from a parsed `plugin.info.txt` **with no sanitization**.
- `lib/plugins/extension/Extension.php:230-239` — `getInstallDir()` returns `fullpath(DOKU_PLUGIN . $this->base)`. `fullpath()` resolves `..` segments, so `base = "../../somewhere"` normalises to a path **outside** `DOKU_PLUGIN`.
- `lib/plugins/extension/Installer.php:178-181` — `dircopy` writes the extracted plugin tree to `getInstallDir()`.

**Attack scenario.** An admin uploads or installs (via the extension manager URL) a third-party plugin archive whose `plugin.info.txt` declares `base = ../../some/path`. After extraction, `dircopy` lands the files at the resolved location — anywhere the web user can write within `DOKU_INC` (`conf/`, `data/`, `lib/tpl/`, etc.). Combined with the ability to write `.php` files, this is RCE for an attacker who controls the published plugin contents.

**Mitigations in place.** Admin auth + CSRF token are required to reach `installFromArchive`. The standard threat model says "an admin could already write files anywhere via FTP" — but on hosted DokuWiki installations where the admin role does **not** imply filesystem access (e.g. SaaS, multi-tenant), this represents a real escalation: a malicious plugin author can place files outside `DOKU_PLUGIN` without filesystem credentials.

**Recommendation.** `Extension::initFromDirectory` should either (a) validate that `$localInfo['base']` matches `^[A-Za-z0-9_-]+$`, or (b) `getInstallDir()` should re-assert `str_starts_with($resolved, DOKU_PLUGIN . DIRECTORY_SEPARATOR)` after `fullpath()` and bail otherwise. Either is a tight fix.

This is **out of scope** for the taint-annotation branch (it's a code change, not an annotation change), but worth a separate issue/PR. Logging here so it isn't lost.

### **M3-02** Logger `formatLogLines` TaintedExtract — RESOLVED on stronger grounds

The original disposition ("log file is trusted") was right for the wrong reason. Re-audit found:

- `formatLogLines` is the **writer-side formatter**, not a reader. It is called synchronously from `Logger::log` lines 144/150 with the in-memory `$data` array constructed at lines 130-139.
- The `$data` keys are **eight hardcoded string literals**: `facility`, `datetime`, `message`, `details`, `file`, `line`, `loglines`, `logfile`. Built one stack frame up; never round-trips through the filesystem.
- `extract($data)` with default `EXTR_OVERWRITE` therefore introduces only those eight variable names. Attacker key control is structurally impossible regardless of how tainted the values are.
- Post-extract sinks: `json_encode` + tab/newline string concat into a log line, `date('Y-m-d H:i:s', $datetime)` (static format), `io_saveFile` to a path derived from `$conf['logdir']` independently of any extracted variable. No `eval`, callable, `unserialize`, SQL, shell, or dynamic path concatenation.
- Attacker-controllable values (`$message`/`$details` — User-Agent, URL, etc.) are at most a log-injection / newline-forgery concern, unrelated to `extract()`.

Verdict: **true FP**. Suggested follow-up: add a targeted `@psalm-suppress TaintedExtract` at `inc/Logger.php:190` with a comment pointing to the static-key construction at lines 130-139, so a future reader sees the structural argument inline. Not applied in this branch — keeping the documentation-only disposition consistent with the M3-02 decision; flagging here for the reviewer to choose.

### Summary of follow-up audits

Of seven concerns flagged in QUESTIONS.md before the audit pass:

| Concern | Outcome |
|---|---|
| M2-01 idfilter $ue=false | resolved-safe |
| M2-01 buildAttributes keys | resolved-safe |
| M2-07 rfc2231_encode | resolved-safe (original concern based on a misreading of the regex) |
| M5-02 wikiFN/mediaFN/resolve_id $clean=false | resolved-safe (resolve_id has no live callers — unrelated cleanup opportunity) |
| M5-02 localeFN | resolved-safe |
| M5-03 Installer upload-name + zip extraction | resolved-safe (whitelist filter + cleanPath normaliser) |
| M3-02 Logger TaintedExtract | confirmed FP on stronger structural grounds |

One new concrete finding surfaced: **plugin.info.txt `base` path traversal** at install time (admin+CSRF-gated, but escalates capability beyond what an admin already has on hosted/multi-tenant deployments). Recommended fix described above.
