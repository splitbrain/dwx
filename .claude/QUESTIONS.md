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

- **Plugin scope.** All M1/M2 work is inside `inc/`. Plugin code in
  `lib/plugins/` is out of scope for now. If you want core annotations
  to also help plugin authors, M6's developer doc should call this out
  explicitly.
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
