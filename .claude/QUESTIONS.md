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

### **M2-01** `stripctl()` deliberately unannotated

`inc/common.php:96` strips only `\x00-\x1F`. That does not make a string
safe for `html`, `sql`, `shell`, or `file` sinks (none of those
characters are control chars). Not a taint sanitizer — no annotation
added, no further action needed. Logging here so the decision is visible.
