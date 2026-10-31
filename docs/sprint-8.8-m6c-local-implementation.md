# Sprint 8.8 M6C — Local artifact implementation

M6C — IMPLEMENTED / CORE, JOURNAL AND SUPPLEMENTAL NATIVE GATES PASS /
ACCEPTANCE REVIEW PENDING. M6C formal closure — PENDING.

The [current acceptance record](sprint-8.8-m6c-native-acceptance.md) reconciles three
distinct results: core20 at `2ab14c7d8212d1170aafe6d8dcdf3b178dd7d5bf`,
1,315 = 805 case + 510 setup/identity assertions; journal3 at
`ccbc708b62891c70071c6ea6e42dcfaf5a3976d7`, 593 = 54 case + 539 outside-case;
and supplement17 at `910c1345221dcf78cf0e50f998449e2b9a50ec62`,
1,316 = 776 case + 540 outside-case (setup/identity plus final shared assertion).
Each profile passed with launcher exit 0. They are not one run, one image or an
all-77-case result; the later documentation head is not natively executed.

The October 31 documentation task reused/rehashed 37 core originals and retrieved/
hash-verified 79 journal/supplement originals from the four authorized directories.
Recorded strict-validator PASS and operational observations remain attributed to the
original runs; this task performed only data/document/Git checks, no runtime work.
[G1–G4 are satisfied for scoped M6C review](sprint-8.8-m6c-native-acceptance.md#four-gap-dispositions-and-remaining-decisions).
Remaining decisions: documentation-head review, owner evidence acceptance, B14/B16
browser allocation (recommended M6E served policy / M6G integrated browser, still
unapproved), and separate merge/formal-closeout authorization.

The supplemental image is recorded at `910c134`; journal retains its separate
`ccbc708` image. The historical failed `ccbc708` supplemental run remains FAILED
with packet/transaction outcome UNESTABLISHED. The supplemental report's profile-local
journal limitation remains intact; separate journal3 supplies that evidence.
The dated sections below are historical authoring/correction snapshots, not current
unexecuted/failed status. Their original results and limits remain source-specific.

M6 overall IN PROGRESS; M6B COMPLETE / STAGING PASS / FORMALLY CLOSED;
M6D–M6G NOT STARTED; production UNAUTHORIZED / NOT DEPLOYED. Last operator-reported
deployed application remains `f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`.
Migration 025 is APPLIED TO WORKING STAGING; do not reapply. Both backup copies and
receipts remain retained; restore NOT EXECUTED. No merge, deployment or closure here.

## Supplemental exact COMMIT correction, October 29, 2026

**Historical snapshot — superseded for current status by the October 31 handoff above.**

Clean starting branch `codex/sprint-8.8-m6c-artifact-builder`, head
`ccbc708b62891c70071c6ea6e42dcfaf5a3976d7`; no unexpected work was overwritten.
The prior [exact-head automated review](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6485415512)
reported no major issues. This is an operator-failure correction, not a review
finding. The [native record](sprint-8.8-m6c-native-validation.md#supplemental-native-failure-and-exact-commit-correction-october-29-2026)
preserves the supplied run, six passing case counts, failure and distinct hashes.
No server evidence was retrieved or independently hash-verified by this task.

The original relay parser compared only `$payload === "\x03COMMIT"` (Git blob
`eb6cd2a31f0257ca6775fe97cf837fc5d1a5d292`). Existing packet fixtures constructed
only that bare form. The coordinator waits for the worker's completion `COMMIT`
barrier, verifies the connection identity, arms the one relay, observes `ARMED`,
then resumes the worker. The wrapper sets completion after the release insertion
and invokes its hook immediately before the real inner `PDO::commit()`; no manual
SQL substitutes for that call. A parser exception precedes the proxy's `FAULT`
emission; the proxy's `finally` closes any remaining client/server/listener resources.

Official PHP **php-8.4.27** source confirms the compatibility defect:
[PDO mysql_handle_commit](https://github.com/php/php-src/blob/php-8.4.27/ext/pdo_mysql/mysql_driver.c)
calls `mysql_commit`;
[the mysqlnd compatibility macro](https://github.com/php/php-src/blob/php-8.4.27/ext/mysqlnd/mysqlnd_libmysql_compat.h)
passes no options and a null name to
[mysqlnd_commit](https://github.com/php/php-src/blob/php-8.4.27/ext/mysqlnd/mysqlnd.h),
which dispatches to
[tx_commit_or_rollback](https://github.com/php/php-src/blob/php-8.4.27/ext/mysqlnd/mysqlnd_connection.c#L1650-L1677).
Its `COMMIT%s %s` formatting leaves exactly one trailing ASCII space when both
substitutions are empty. The COM_QUERY byte `0x03` is separate from the SQL text.
This source-supported mismatch does **not** recover the historical native packet
or establish its transaction outcome.

Before editing, the private probe required the actual unchanged relay class and
constructed handshake, command and response packets. Both modes (`before-commit`,
`drop-ack`), both command forms and whole/one-byte delivery produced eight scenarios.
Windows **PHP 8.4.24 CLI ZTS**, no INI: four bare-form scenarios passed; all four
single-space scenarios raised exactly `RuntimeException('wire_expected_commit')`,
numeric code 0, with no returned target bytes or successful fault. The probe
completed with **expected exit 1**, 0.3749214 seconds, 991 stdout / 0 stderr bytes.
The separately supplied Linux PHP 8.4.24 NTS eight-scenario probe of the same blob
is mechanism evidence only, not this desktop execution or a native capture.

The correction is an explicit two-form equality: only `"\x03COMMIT"` or
`"\x03COMMIT "`. Framing, sequence-zero targeting, capability rejection and bounds
are unchanged. No trim, prefix/case/comment matching, fallback, packet rewriting
or SQL logging was introduced. Drop-ack forwards the original packet/header bytes,
retains no fault before a complete validated matching server OK, then suppresses
that OK. Before-commit suppresses the target and records both flags false.
The OK fixtures demonstrate parser behavior only, not a MySQL commit.

Fresh sequential commands used Node **24.22.1** as an outside-Git runner, with a
60-second / 65,536-byte per-stream bound and owned temporary directories:

```powershell
php -n C:/Users/fvd83/.ssh/m6c-commit-wire-20261029/reproduce.php 'C:/Users/fvd83/My Drive/Development/ultimate-back-office/tests/support/WebsitePlatformM6CSupplementWire.php'
php -n tests/WebsitePlatformM6CSupplementWireTest.php
php -n -d 'auto_append_file=C:/Users/fvd83/.ssh/m6c-commit-wire-20261029/actual-observation.php' tests/WebsitePlatformM6CSupplementProtocolTest.php
```

| Fresh after-fix validation | Completion | Exit | Seconds | Stdout / stderr bytes |
| --- | --- | ---: | ---: | ---: |
| Same private probe | Eight accepted / zero recognizer failures | 0 | 0.3818712 | 915 / 0 |
| New actual-parser wire regression | 4,591 assertions / 562 scenarios | 0 | 0.2945765 | 109 / 0 |
| Existing protocol/channel regression | 236 assertions | 0 | 7.0340364 | 352 / 0 |

The [wire regression](../tests/WebsitePlatformM6CSupplementWireTest.php) independently
pins literal headers and covers whole packets, every two-part split and one-byte
fragments, unarmed pass-through, no premature fault, exact forwarding and suppressed
OK. Ninety-three negative payload neighbors run in both commit modes with whole
and byte delivery; rejection requires the exact exception class/message/code.
They include other/empty/incomplete SQL, modifiers, comments, semicolons,
multi-statements, case changes, extra spaces, all ASCII control bytes, another
command byte and embedded COMMIT text. Capability, malformed/oversized/truncated
frame, response error/shape/sequence/transaction-state, duplicate-arm/second-command,
completed-fault reuse and separate drop-query checks remain explicit.

The existing protocol suite was rerun because it imports the changed parser and
also exercises the relay error channel and strict non-PASS evidence contracts.
It is byte-unchanged, including the earlier killed-child normalization correction.
The private append observer reports the actual Windows child: exit **255**, signal
**0**, signaled **false**, termination requested **true**, running **false**;
recorded values match, pending owned cleanup **0**. Synthetic negative/missing exits
still normalize to null independently of signals. Every local run left its owned
temporary directory empty. Selection, standalone diagnostics, journal/core and the
large launcher suites were not rerun: their code and dependency paths did not change.

Both changed PHP files pass `php -n -l`, exit 0 with empty stderr. Documentation
checks pass **78 local links / 50 anchors / 49 external URL syntax checks / seven
balanced fence pairs**. The working integrity comparison preserves all **485 other
preexisting files** by raw bytes, Git blobs and modes, including **267 paths outside
tests/docs** and all **25 migrations**. Working diff checks pass; staged/committed
checks and final source identity are retained in the private evidence. Git's
LF-to-CRLF notices describe checkout conversion, not PHP diagnostics or test failures.

Exact change inventory: the test-only wire recognizer, one new packet test, and
these three existing implementation/native-validation/acceptance records. Production,
inner PDO commit, completion/recovery/authorization semantics, core20/supplement17/
journal3 inventories and acceptance meaning, journal extension/admission/receipt
writer/build recipe, launcher isolation/deadlines/limits/transport, all 25 migrations
and integrity pins remain unchanged. Migration 025 stays applied to working staging,
with canonical SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Commands, complete outputs, checks and `SUMMARY.txt` are outside Git at
`C:\Users\fvd83\.ssh\m6c-commit-wire-20261029`.

No new native run or production correction occurred. The six native passes belong
to `ccbc708`, not this correction. The current image belongs to `ccbc708`; a later
separately authorized task must establish reviewed-new-source provenance, verify
the affected wire/protocol paths and perform the still-needed supplemental native
run. Do not relabel the old image or edit receipts, or automatically repeat core20,
journal3, backup, transport smoke or every earlier preparation test. Formal
acceptance remains pending; browser allocation stays an owner decision;
backups/receipts untouched; restore **NOT EXECUTED**.

## Journal receipt boolean correction, October 28, 2026

Starting head: `4d49e5f35b943a5352dac6d7eb5d6af79328c3e1`, on the existing
`codex/sprint-8.8-m6c-artifact-builder` branch with a clean worktree. Its completed
[automated review](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6484486988)
reported no major issues. This is an operator-failure correction, not an automated
review finding. The [preparation record](sprint-8.8-m6c-native-validation.md#journal-helper-preparation-and-receipt-correction-october-28-2026)
preserves operator-reported compilation/linking and recipe startup-load **PASS**,
followed by receipt admission **FAILED**, exit 255, at that exact source. Eight
nonfatal compiler warnings were retained by the operator and were not assessed here.

The source-confirmed mismatch has two layers. The build writer serializes actual
`PHP_ZTS`, and `verify()` forwards that constant unchanged. The original `receipt()`
required `int $zts`, checked integer zero, and expected `'zts' => 0`; its fixture
used integer zero on both sides. The official PHP 8.4 migration reference,
[Type Change of Constants](https://www.php.net/manual/en/migration84.incompatible.php#migration84.incompatible.core.constants),
confirms that `PHP_ZTS` changed from integer to boolean. JSON `false` is not JSON `0`.

Before repository edits, an outside-Git strict caller invoked the actual committed
`receipt()` using explicitly synthetic identities and literal JSON, never `verify()`:

| Before-fix case | Actual outcome |
| --- | --- |
| Boolean `false` runtime argument | `TypeError`: argument #4 must be int, false given |
| Decoded JSON `false`, accepted integer-zero argument | `RuntimeException('journal_helper_receipt')`: strict schema mismatch |
| Original integer-zero fixture and argument | Accepted, despite not representing PHP 8.4 writer output |

The first probe exited 255 because its diagnostic check expected the words `bool
given`; PHP printed `false given`. That original output is retained. Correcting only
the private probe's expected diagnostic confirmed all three cases before the fix:
exit 0, 0.307323 seconds, 508 stdout / 0 stderr bytes. The private probe's successful
completion records the expected pre-fix failures; it is not receipt admission PASS.

The minimal validator correction is `bool $zts`, `$zts === false` for NTS and
strict expected receipt `'zts' => false`. No conversion, union/mixed type, loose
equality, default or exception suppression is used. The writer, real-constant
forwarding, exact version/source/PHP/hash/field/order requirements and all fixed
filesystem admission checks remain unchanged. The existing policy fixture now
uses boolean false and requires the intended rejection reason, so a TypeError
cannot hide as a successful negative receipt test.

The independent [receipt regression](../tests/WebsitePlatformM6CJournalReceiptTest.php)
calls the real predicate with literal JSON and synthetic hashes. It covers boolean
NTS and JSON round trips; independently mismatched source/binary identities;
self-consistent ZTS rejection; true, numeric, string, null, missing, array/object
and malformed ZTS values; wrong/malformed versions, SHA and hashes; missing/extra
fields, field order, and private/control data. Semantic negatives require
`RuntimeException('journal_helper_receipt')`; strict argument-type negatives require
the expected `TypeError`. No predicate is copied and no extracted source is evaluated.

Actual local runtime: **Windows PHP 8.4.24 ZTS**, `get_debug_type(PHP_ZTS) = 'bool'`,
`PHP_ZTS = true`, no loaded INI. The writer-shaped JSON uses actual `PHP_VERSION`
and `PHP_ZTS` unchanged, preserving `"php":"8.4.24","zts":true`; the actual
strict call reaches intentional `journal_helper_receipt` rejection, not TypeError.
This proves local ZTS rejection, not Linux NTS or filesystem admission. Synthetic
PHP 8.4 NTS cases pass. The portable test explicitly handles an older integer
constant as outside the helper's PHP-8.4-only contract; no PHP 8.3 execution or
host upgrade is claimed. Host controller PHP may remain 8.3.

Fresh sequential commands, using Node **24.22.1** only as the private bounded runner:

```powershell
php -n tests/WebsitePlatformM6CJournalReceiptTest.php
php -n tests/WebsitePlatformM6CJournalTest.php
php -n -l tests/support/WebsitePlatformM6CJournalAdmission.php
php -n -l tests/WebsitePlatformM6CJournalTest.php
php -n -l tests/WebsitePlatformM6CJournalReceiptTest.php
```

| Fresh validation | Assertions | Exit | Seconds | Stdout / stderr bytes |
| --- | ---: | ---: | ---: | ---: |
| Receipt/type/serialization | 84 | 0 | 0.384108 | 547 / 0 |
| Journal policy/state | 130 | 0 | 1.099755 | 133 / 0 |
| Admission lint | — | 0 | 0.289538 | 82 / 0 |
| Policy test lint | — | 0 | 0.282034 | 69 / 0 |
| Receipt test lint | — | 0 | 0.274180 | 76 / 0 |

Each command has a 60-second bound, 1 MiB capture bound, actual exit and expected
completion marker, plus an empty owned TMP/TEMP root after completion. Negative
cases enforce their intended exception/diagnostic contract; empty whole-suite stderr
is not a new requirement. Raw commands, original failures, outputs, versions,
durations and working/staged/committed checks are outside Git at
`C:\Users\fvd83\.ssh\m6c-journal-receipt-20261028\SUMMARY.txt`.

Bounded receipt audit and disposition:

| Relevant match | Disposition |
| --- | --- |
| `tests/native/m6c/journal/build.sh` writer | Actual constants; unchanged |
| `tests/support/WebsitePlatformM6CJournalAdmission.php` | Only parameter, NTS comparison and expected ZTS representation corrected; `verify()` unchanged |
| `tests/WebsitePlatformM6CJournalTest.php` | Original integer fixture corrected; explicit receipt rejection reason |
| `tests/WebsitePlatformM6CJournalReceiptTest.php` | Independent literal and actual-runtime writer regressions |
| `tests/support/WebsitePlatformM6CJournalCases.php` | Native caller of `verify()` unchanged and unexecuted |
| `tests/support/WebsitePlatformM6CJournalWorker.php` | Existing runtime ZTS guard has no integer receipt assumption; unchanged |

Journal process and selection tests use channel/contract/synthetic/transport and
guarded discovery paths, not `M6CJournalAdmission`; their dependencies did not change,
so they were not rerun. Shared launcher, supplemental protocol/selection and transport
smoke likewise were not rerun. Reuse their unaffected historical evidence with its
actual source attribution; do not relabel earlier counts as fresh checks.

The six-file correction is limited to three test files and these three existing
records. All other 483 preexisting tracked files, including production, C source,
recipe, Dockerfile, shared process/diagnostics, core20/supplement17/journal3 inventories,
all 25 migrations and integrity pins, retain their bytes/modes/blobs. Changed-file
PHP lint, local document links/anchors/fences and diff/integrity checks accompany the
private evidence. Migration 025 remains applied to working staging, unchanged at
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.

The separate runtime startup-load verification, all six runtime suites and both
profile admissions remain **UNEXECUTED**. Arming, status calls, actual interception,
MySQL and native recovery/acceptance remain unexecuted. A later separately authorized
preparation must establish exact-new-source image/receipt provenance, test the
corrected receipt and complete those missing stages. The old image and failed receipt
remain historical `4d49e5f` artifacts; no editing, relabeling or workaround is proposed.
Core native PASS stays at `2ab14c7`; formal acceptance remains pending. Backups and
receipts are untouched, restore NOT EXECUTED, and browser allocation remains an owner
decision. This task performs zero server, SQL/migration, helper-load/compiler,
image/container/service, installation, deployment, backup/restore/prune or merge work.

## Producer-journal local authoring, October 28, 2026

Historical Windows authoring at `4d49e5f`, before the later operator preparation.
Build/load-unverified statements below describe that authoring task; the current
reported compilation PASS and receipt failure are recorded above.

Starting head: `5ff2b601a3ae07a47094bd4815d6f4cfc90db1ea`; its completed automated
review reported no findings. This narrowly adds test-only authoring for producer
journal flush, fsync and pending-to-final publication failure. Production code is
unchanged. The [native guide's boundary table and mechanism](sprint-8.8-m6c-native-validation.md#producer-journal-fault-profile-october-28-2026)
defines precisely what the source-authored extension must prove on Linux.

`journal-v1` selects the separate fixed `m6c-native-journal-v1` profile with three
instrumented cases: `producer-journal-flush-failure`,
`producer-journal-fsync-failure`, and `producer-journal-publication-failure`.
Core20 and supplement17 retain their exact inventories and meanings; their report
formats cannot satisfy this additional profile. The reviewed killed-child test,
unknown-exit normalization, shared process behavior, strict individual Git stderr
rule and privacy/error precedence remain intact.

The child-local PHP extension targets actual production PHP handlers and forces a
single false return before the requested operation. It does not claim a failed
syscall, spontaneous disk error or durability proof. A fixed shared PHP policy
records call order and accepted stages; literal local traces test flush short circuit,
separate fsync failure and publication prerequisites. Real private-file/fake-PDO
tests establish that a complete-looking pending journal stays unknown, forces
service-driven reconciliation, and remains blocked after distinct recovery without
rerender, counter reset, releases or validations. Their pending-file setup is
explicitly modeled; it is **not** local execution of the Linux fault helper.

The source-authored native cases require successful same-helper no-fault controls,
off-target pass-through, exactly one matched injection, actual caller error,
retained pending bytes and fresh uninstrumented recovery. Capability/identity,
unreached/duplicate/wrong-phase/lost evidence and incomplete control outcomes fail
closed. A finally-close failure cannot erase the original injected-operation error
and cannot count as accepted close. The native extension's filesystem identity and
ABI checks still require Linux compilation and execution; synthetic policy tests
are not substituted for those checks.

Fresh sequential local validation uses Windows PHP **8.4.24** with `php -n`, Node
**24.22.1**, fresh private TMP/TEMP roots and separately captured real exits/output.
The initial journal selection attempt exited 255 because sandboxed MSYS startup
was denied (`NtCreateDirectoryObject`, `0xC0000022`). The same bounded test passed
with the authorized same-user sandbox exception; no host ACL/configuration changed.
Earlier successful intermediate checks and final rechecks are retained separately.

| Fresh suite | Assertions / additional counts | Actual exit | Seconds |
| --- | --- | ---: | ---: |
| Journal policy/state | 130; synthetic API traces, real files and fake PDO | 0 | 1.012 |
| Journal process | 33; actual bounded children, synthetic packets | 0 | 2.909 |
| Journal selection/transport | 39; fake admission, actual private transport | 0 | 8.247 |
| Existing supplemental selection | 41 | 0 | 9.719 |
| Existing supplemental protocol | 236 | 0 | 6.482 |
| Shared native setup/report | 751 / 62 scenarios | 0 | 0.813 |
| Shared diagnostics/transport | 95 / 41 scenarios | 0 | 3.265 |
| Shared observation | 273 / 103 scenarios / 45 synthetic children | 0 | 9.394 |
| M6C scope | 53 | 0 | 8.949 |
| Shared launcher | 7,388 / 104 fake-command scenarios | 0 | 693.068 |

The affected shared launcher regression was rerun because profile dispatch changed.
Its private Windows-only Bash file-limit double models unsupported `ulimit -f 4`; it
does not change repository Linux limits or establish kernel resource-limit proof.
All selected suite completion markers and negative diagnostic expectations must
pass, outputs remain bounded, and owned temporary roots are checked after exit.
Zero stderr in these selected runs does not impose a new general empty-stderr rule.
The final policy recheck includes preexisting control-directory collision rejection
and preservation of the unrelated marker; cleanup takes ownership only after new
private-directory creation. The journal process and selection suites were rerun
after that change. PHP lint passed for all 13 changed/new PHP files; Bash syntax
passed for three changed/new shell files, with an additional POSIX-sh syntax check
for the build recipe. Documentation checks verified 68 local links, 42 anchors and
five balanced fence pairs. Integrity checks cover 466 unchanged preexisting files,
including 267 paths outside tests/docs, and unchanged inline core case bodies.

Only test helpers, test selection/report wiring, the test image recipe and these
three existing records change. The recipe adds a fixed PHP extension build/load
prerequisite; it is not an unchanged runtime recipe. No installed compiler/phpize
was available here, so C compilation and native helper loading are **NOT VERIFIED**.
No binary hash, image identity or successful Linux capability probe is invented.
Changed-file lint, shell syntax, documentation links/fences and working/staged/
committed diff/integrity results accompany the outside-Git evidence at
`C:\Users\fvd83\.ssh\m6c-journal-faults-20261028\SUMMARY.txt`.

All production/protected application paths, all 25 migrations and integrity pins
remain byte-identical to the starting head. Migration 025 remains applied to
working staging, SHA-256
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`, without operational
verification or reapplication. Existing supplemental case bodies, core acceptance,
resource limits and prior evidence are preserved. Browser allocation remains an
owner decision; backups/receipts are untouched and restore remains NOT EXECUTED.

**CORE NATIVE GATE — HISTORICAL PASS at `2ab14c7`.
STORAGE FAULT COVERAGE — AUTHORED / PARTIAL (Linux API interception, helper ABI/load
and native recovery disposition remain unverified).
NATIVE HELPER BUILD/LOAD — NOT VERIFIED.
SUPPLEMENTAL NATIVE EXECUTION — NOT PERFORMED.
FORMAL M6C ACCEPTANCE — PENDING.** No broader acceptance package is closed through
this authoring. No server, real SQL/migration, image/container/service, installation,
deployment, backup/restore/prune, provider/Narrator, approval or merge action occurs.

## Supplemental protocol exit-normalization correction, October 28, 2026

The [P2 finding](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4407103223)
on `4512ae54f4716aaa9d15a65b667e25cf4f5d5b4a` identified a test expectation mismatch:
`stop(9)` returns raw process state, while `M6CSupplementChannel::record()` uses
`M6CNativeDiagnostics::summary()`. The reviewer reported Linux raw exit `-1` /
signal `9`, recorded exit `null`, and suite exit 255. That Linux reproduction is
reviewer-reported; this correction was verified on Windows only.

Before editing the assertion, a controlled **synthetic** result traversed the actual
recording/summary path: raw exit remained `-1`, recorded exit was `null`, signal
remained `9`, running was `false`, and termination requested was `true`. The old
raw-versus-recorded comparison was false. This separately retained proof exited
**1 as expected**, in **0.701 s**, with 424 stdout / 0 stderr bytes and clean owned
child cleanup. It is not an observed Windows or Linux SIGKILL result.

The real-child test now compares exit with the diagnostic summary and checks the
actual signal independently. Six literal recording cases separately constrain
negative, missing/null, zero and seven exits; known/unknown signals; typed true,
false and null flags; and preservation of the raw input. The real child, observed
READY boundary, termination request, stopped state and owned-child cleanup remain.
Nearby assertions already use known literal exits 0/7; no other instance of this
mismatch was found. Shared process, diagnostic and production behavior is unchanged.

Fresh sequential Windows / PHP **8.4.24** results (private runner Node **24.22.1**):

| Suite | Actual exit | Completion marker counts | Seconds | Stdout / stderr bytes |
| --- | ---: | --- | ---: | ---: |
| Supplement protocol | 0 | 236 assertions | 7.928 | 423 / 0 |
| Native diagnostics/transport | 0 | 95 assertions / 41 scenarios | 3.244 | 149 / 0 |
| Native observation | 0 | 273 assertions / 103 scenarios / 45 real synthetic children | 10.538 | 147 / 0 |

Exact PHP commands, in that order:

```powershell
php -n -d 'auto_append_file=C:/Users/fvd83/.ssh/m6c-exit-normalization-20261028/actual-observation.php' tests/WebsitePlatformM6CSupplementProtocolTest.php
php -n tests/WebsitePlatformM6CNativeDiagnosticsTest.php
php -n tests/WebsitePlatformM6CNativeObservationTest.php
```

The private append file only reports the completed protocol suite's actual raw and
recorded fields and remaining owned cleanup entries. Actual Windows termination
returned **exit 255, signal 0, signaled false, termination requested true, running
false**; recorded values matched. No POSIX signal value was fabricated. All suites
completed within the private 60-second bound, with bounded output and empty owned
temporary roots; protocol cleanup had zero pending owned children. Deliberate
no-INI profiles and negative diagnostic expectations remain intact; individual Git
verification still requires empty stderr. No large launcher suite was rerun.

Only this protocol test and the two existing implementation/validation records
changed. Changed-file PHP lint, document links/fences and working/staged/committed
diff checks accompany the private evidence. All other 472 tracked files retain
their baseline bytes, modes and blobs, including shared helpers, production,
contracts, launchers, all 25 migrations and integrity pins. Migration 025 remains
applied to working staging, unchanged at SHA-256
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Commands, outputs, durations, checks and `SUMMARY.txt` are retained outside Git in
`C:\Users\fvd83\.ssh\m6c-exit-normalization-20261028`; before-proof and successful
rechecks are separate. Historical results below were not rerun by this correction.

**CORE 20-CASE NATIVE GATE — HISTORICAL PASS at `2ab14c7`.
SUPPLEMENTAL COVERAGE — PARTIALLY IMPLEMENTED LOCALLY.
SUPPLEMENTAL NATIVE EXECUTION — NOT PERFORMED. FORMAL M6C ACCEPTANCE — PENDING.**
No Linux execution environment was available to this Windows-only task; the corrected
actual Linux child path remains for later authorized Linux verification. At that
normalization-correction head, flush/fsync and producer-journal-publication failure
injection remained unimplemented; the later journal-profile authoring is recorded above.
The exact core 20/supplemental 17 inventories, lanes, strict validation and runtime
limits remain unchanged. Browser allocation remains an owner decision; backups,
receipts and historical failures remain untouched. No native run or production
correction occurred.

## Supplemental local validation, October 28, 2026

The following authoring results are historical at `4512ae5`; the focused correction
and fresh results are recorded above.

The separate `m6c-native-supplement-v1` profile implements 17 fixed instrumented cases
from the assessment, beginning at `fa617fd7eeda92d8febdce66c46f973a5ae23a7e`.
The [native guide](sprint-8.8-m6c-native-validation.md#supplemental-authoring-october-28-2026)
contains the complete A/B/C/D inventory and mechanisms. Production code, migrations,
integrity pins, core case definitions and the 77-case plan remain unchanged. New
source requires its own exact-head review and source-matching runtime preparation.

The local tests use actual artifact/service code, private files, fake PDO/clocks,
pure packet parsing and bounded synthetic children. They establish implementation
and controller behavior, **not** real MySQL transactions, native lock order,
SIGKILL/kernel permissions/ENOSPC or power-loss durability. The service test's fake
PDO acknowledgement loss is modeled post-commit behavior; the authored native relay
instead drops the actual server response before PDO can receive it. Native proof
is still AUTHORED / NOT EXECUTED. Missing C mechanisms are real flush/fsync failure
and producer-journal publication failure. Actual low-space and unavailable-probe
refusal are authored through scoped faults in fresh disposable directories.

The local positive PDF is a valid inert document with computed object/xref offsets,
not renamed text. It passes the existing `pricing_list` link selection, real renderer,
validator, content-addressed store and result path, with earlier customer/current
internal approval on a non-material revision. Narrow negative neighbors reject
unknown rights, wrong business ownership, changed digest and ambiguous approval.

Local results are recorded below after sequential execution; raw commands, exits,
versions, timings, original failures and rechecks remain in the private authoring
evidence directory and `SUMMARY.txt`, outside Git. Intentional negative-test
diagnostics do not turn a successful suite into failure and do not relax the
individual Git verifier's strict empty-stderr rule.

The selected completed runs all exited **0** on Windows (PHP **8.4.24**, Node
**24.22.1**, Git **2.56.0.windows.2**, GNU Bash **5.3.15(2)**). Each PHP suite uses
`php -n tests/<filename>`; a private runner supplies a newly created TMP/TEMP root,
captures the actual exit/signal before logging, and retains stdout/stderr separately.

| Suite filename | Assertions | Relevant scope |
| --- | ---: | --- |
| `WebsitePlatformM6CSupplementProtocolTest.php` | 211 | Fixed inventory, packet faults, startup/timeout/early exit, every supported barrier, actual local exit/signal and cleanup evidence, privacy and unknown outcome |
| `WebsitePlatformM6CSupplementSelectionTest.php` | 41 | Explicit selection, guarded entries, exact report transport and actual launcher capture/acceptance with fake admission/reader/limit leaves |
| `WebsitePlatformM6CSupplementServiceTest.php` | 103 | Real service/artifacts with fake PDO/clocks; third execution, recovery/budget/operator, supported PDF and narrow negatives |
| `WebsitePlatformM6CArtifactTest.php` | 45 | Unchanged artifact regression |
| `WebsitePlatformM6CIntegrationTest.php` | 54 | Fake SQL plus real files |
| `WebsitePlatformM6CRecoveryTest.php` | 135 | Real files, modeled SQL/clocks/interruption |
| `WebsitePlatformM6CNativeSetupTest.php` | 751 | 62 setup/report scenarios; modeled Linux permissions and actual local publication |
| `WebsitePlatformM6CNativeObservationTest.php` | 273 | 103 scenarios / 45 real synthetic children |
| `WebsitePlatformM6CNativeDiagnosticsTest.php` | 95 | 41 diagnostics/transport scenarios |
| `WebsitePlatformM6CNativeGitSourceTest.php` | 113 | 49 scenarios using installed Git and synthetic subprocesses |
| `WebsitePlatformM6CNativeLauncherTest.php` | 7,385 | 104 fake-command scenarios, including publication and repeated interruption precedence; 884.474 seconds |
| `WebsitePlatformM6CScopeTest.php` | 53 | Protected M6C scope |
| `WebsitePlatformM6BScopeTest.php` | 42 | Protected M6B scope |

The successful service, integration and Git-source suites intentionally emitted
51, 102 and 50 stderr bytes respectively. Individual verifier stderr is still strict.
The launcher regression uses a **private Windows-only Bash environment double** for
unsupported `ulimit -f 4`; fake Docker/systemd/OS admission was already part of that
suite. Repository Linux limits are unchanged. This run supplies modeled local
controller evidence, not Linux resource-limit or kernel proof.

Original failures remain retained: default sandbox TEMP journal rename failures
(also reproduced by the unchanged artifact suite); a new fixture snapshot assertion;
MSYS/Git sandbox startup denials; new stage-cleanup bookkeeping; an invalid-profile
exception outside coordinator handling; unsupported Windows `ulimit`; and a private
300-second runner timeout before the full launcher suite completed. The corrections
use new private TEMP roots, test-only fixes, authorized same-user sandbox exceptions,
the explicit Windows limit double and a longer private runner bound. No native
deadline or host ACL/configuration changed. Two initially overlapping scope sessions
were rechecked sequentially. An initial diff check that forced `autocrlf=false`
misread existing CRLF working files; the repository's ordinary diff check passed.

Final verification includes 16 PHP lints, two Bash syntax checks, 41 local document
links, balanced fences and diff checks. Baseline preservation covers 462 tracked
files: 454 outside the eight edited existing paths remain byte-identical, including
267 protected production/other files with identical modes/blobs at both `fa617fd`
and `2ab14c7`. All 25 migrations and integrity pins, core contract/case files and
inline core case bodies are unchanged. No production defect was demonstrated.

Previous handoff at `2ab14c7`: **INSTRUMENTED GIT ENVIRONMENT ISOLATED / NATIVE CAUSE
NOT YET CONFIRMED**. Historical `abac019` remains FAILED (3 PASS / 1 FAIL /
16 UNREACHED, 673/673 assertions); its exact cause remains UNDETERMINED.
[Instrumented Git environment isolation](#instrumented-git-environment-isolation)
retains the source difference and local evidence. All earlier sections below retain
their dated source/run scope; outstanding native statements there describe historical
handoffs, not the current status.

Previous handoff, October 23 operator evidence: **DIAGNOSTICS IMPROVED / NATIVE ROOT
CAUSE STILL UNKNOWN**. At `79e6d63`, three protected cases explicitly passed,
including independent artifact verification and canonical input/release binding;
instrumented concurrent-claims failed, with 16 cases unreached. Transport passed.
This desktop task performs no native rerun. See
[instrumented worker observation](#instrumented-worker-observation) for the
operator evidence, local reproductions, bounded diagnostic changes and validation.

Previous handoff, October 22: the operator reports the native run at
`8118138c890105f689fa145de7f5566f3263eee5` **FAILED**, with successful acknowledged
report capture. A canonical-input hash defect is reproduced and corrected locally;
the historical native exception origin remains unconfirmed. See
[stored JSON input correction](#stored-json-input-verification-correction) for the
source trace, before/after evidence, exact scope and remaining native gate.
The subsequent exact-head P1 at `9a35b59` identifies the same raw-text assumption
in the native assertion. Its [harness-only follow-up](#native-input-assertion-review-correction)
preserves the production fix; no native run of `9a35b59` has been reported.

Previous handoff, October 21: the operator reports native attempts at 740431f and
`e2998fdadaeb19048556d303cc9d310fde2c2276` **FAILED**. The retry has one explicit
startup-denials PASS, one source-order-attributed protected-multipage-build failure
and **18 UNREACHED**; the underlying build cause is unknown. The local diagnostic/
transport correction is **IMPLEMENTED LOCALLY / REVIEW REQUIRED**, with no native
rerun here. See [child diagnostics and tmpfs transport](#native-child-diagnostics-and-tmpfs-transport),
[historical storage setup correction](#native-storage-setup-and-report-correction)
and the [operating guide](sprint-8.8-m6c-native-validation.md). Earlier outstanding
Linux/native statements below describe their dated correction, not this handoff.
The subsequent `648fd0d` review findings and their local corrections are recorded
under [native teardown review corrections](#native-teardown-review-corrections).

Baseline: `7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45`; current origin/main was verified
at that exact merged PR #127 commit. No intervening main changes were found.
Branch: `codex/sprint-8.8-m6c-artifact-builder`. The operator-reported deployed app
remains `f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Earlier M6B native results are
not attributed to these changed executable inputs. Original dated evidence is retained.

PR #128 correction work follows the completed review of original head
`d06f9e5ffbd24b8488e61c9ffb307aa9b0125984`. See the
[review corrections](#pr-128-review-corrections) for current behavior and fresh validation.
The original local PASS and reviewer observations below remain separate evidence.
The subsequent review of `7787bec` is addressed in
[complete inventory admission](#complete-inventory-admission); earlier validation totals
remain historical results for their respective corrections.
The review of `3e6871321820f02ddccab7872ae5f69976b91821` is addressed in
[pre-output execution denials](#pre-output-execution-denials). Its current handoff
records the operator-reported completed backup/download checkpoint below.
The subsequent operator-observed CLI failure at `d4fb3d6` is addressed in
[CLI execution-child profile and diagnostics](#cli-execution-child-profile-and-diagnostics).
That test/documentation-only correction preserves the earlier Windows results and
records the partial Linux result separately; the Linux gate has not passed.
The completed review of `8f04073325b3e01a1851aa0faaa4493fec3d059f` is addressed in
[execution-fixture prerequisite and initialization review corrections](#execution-fixture-prerequisite-and-initialization-review-corrections).
The earlier CLI-profile correction's validation remains historical evidence.
The review of `e067f05bd16ca41a46a1dc174d6471f4c826f5b8` is addressed in
[execution-fixture cleanup prerequisites](#execution-fixture-cleanup-prerequisites).
Its basename finding and new validation form a separate evidence layer.
The review of `b8c20c479bdbf29f4659add30d97e9b57e01cac9` is addressed in
[supporting fake-database initialization prerequisites](#supporting-fake-database-initialization-prerequisites).

## Implementation and wiring

| File | Responsibility |
| --- | --- |
| `private/classes/SiteArtifactBuilder.php` | Reproject exact stored source, compare persisted input, build separate documents, copy verified bytes, create marker/metadata and seal. |
| `private/classes/SiteArtifactValidator.php` | Exact expected inventory, bounded bytes, MIME, DOM/CSP/links/navigation, canonical metadata and all hash layers. |
| `private/classes/SiteArtifactStore.php` | Small candidate/write/seal/inspect/verified-read/full-verification boundary. |
| `private/classes/LocalSiteArtifactStore.php` | Attempt-private candidates, per-job writer lock, immutable release namespace, producer journals and bounded inspection. |
| `private/classes/SitePublicRenderContext.php` | Explicit public facts/configuration projection and approved contact/content-hash URL context. |
| `private/classes/SiteArtifactFiles.php` | Relative-path checks, bounded regular-file reads/writes, inventory, link checks and fixed worker exceptions. |
| `private/classes/SiteArtifactAssetResolver.php` | Exact site/revision/usage/asset binding; approved legacy bytes read-only. |
| `private/classes/SiteBuildRuntime.php` | Protected Linux CLI factory, pre-resolved worker evidence, exact checkout/toolchain identity, real dependency adapter. |
| `private/site-static/static-review-v1.css` | Repository-owned standalone CSS plus validated theme-variable rules. |
| `scripts/site-build-worker.php` | Explicit one-shot execution or automatic recovery for one explicit job; no daemon or scheduler. |
| `private/classes/SiteCompositionRenderer.php` | `renderPage` selects one main while retaining complete navigation; combined preview behavior remains. |
| `private/classes/SiteBuildService.php` | Real execute/reconcile gates, service-owned lease/source checks, private runtime factory. Existing result/replay/recovery transactions remain the lifecycle owner. |
| `private/classes/SiteBuildDependencies.php` | Adds internal `execute(job, producer, source, pulse)`; no public dependency setter. |
| `private/classes/SiteRevisionManager.php` | Returns already locked asset metadata privately from `lockBuildEligibility`; no copied lifecycle SQL or new migration. |

The path is explicit site/revision/expected hash → existing locked eligibility and
current actor checks → public projection and byte verification outside SQL → persisted
M6B input identity → execution lease → fresh locked source and requester check → render,
validate and seal outside SQL → independently verified producer evidence → existing
atomic release/validation/job/attempt/event commit. Lease heartbeats run between bounded
external operations, including immediately before seal. No SQL transaction encloses
rendering, Git inspection, asset reads or storage operations. Worker authentication
occurs before a CLI invocation can open its configured database.

Successful-history replay still precedes new-build eligibility. It does not require
current source approval, public projection, asset availability or file verification.
Current authorized readers/workers and stored immutable identity remain required.
New success, including recovery adoption, rechecks the original requester, source,
lease and ownership. EMD/internal-demo, active/published transitions, production
profiles and operator identity controls remain future-gated. No customer/admin route
or normal GET/POST/M5 approval starts a worker.

## DTO and public-field contracts

`prepareInput` returns exactly `public_composition`, `public_facts`, and
`ordered_asset_digests`, matching SiteBuildContract. Ordered asset entries are
`{usage_key:string, sha256:hex64, byte_size:int, mime_type:string}` sorted by usage.
No storage locator or metadata row is serialized into those DTOs.

Public facts permit business display name, phone, email and public address fields;
profile language, short/long descriptions and value proposition; selected/custom
service names/category names; public service-area mode/radius/location; and website/all
FAQ question/answer pairs. Private base street address requires the stored public-visit
flag. The snapshot builder already projects the approved public business address.
Briefs, references, internal IDs, guidance, pricing instructions, escalation/notification
rules, feedback, approval comments and credentials are excluded by construction.
Canary tests cover nested objects as well as top-level fields. The HTML scan supplements
this projection; it is not a serializer/redactor security boundary.

Public composition permits page key/title/slug/type/navigation label/order, selected
SEO and presentation fields, exact component/variant/schema/version and validated
configuration, section key/order, and theme key/version/colors/typography/layouts.
Repository component schemas reject unknown configuration fields recursively. Asset IDs,
source references, content hashes and database component IDs are omitted. Existing
legacy snapshot compositions are refused; legacy local asset bytes are the supported
compatibility input. No legacy generated pages or live business-profile refresh is used.

Context permits only content-hash asset URLs, validated tel/mailto links and an existing
contact page. Each complete document has language, charset, viewport, page title,
site/release marker, one main and repository layout/component semantics. Selected theme
colors, fonts, scale, spacing, corners, buttons and page widths are applied. Forms retain
fields, required flags and label text as disabled controls with no form element/action.
No JavaScript, remote stylesheet, application CSS, authenticated preview or legacy upload
URL is present. CSP is embedded in the document; no HTTP header deployment is claimed.

Private locked source additionally contains `asset_metadata` (the existing locked
site_assets rows), used only with the exact composition usage/asset ID. Resolver checks
site/business ownership, ready state, current permitted rights, size, digest and MIME.
Only `source=legacy_247sp` plus a contained `/uploads/...` locator under the configured
legacy root is supported. All reads are read-only; arbitrary URLs, Spaces, unsupported
sources and missing roots fail with bounded errors. PNG/JPEG/WebP and conservative
passive PDFs are permitted. Active/encrypted/object-stream/ambiguous PDFs are rejected;
this is not a general PDF sanitizer. All SVG is currently refused: no repository-owned
SVG asset/digest exception is needed or enabled by this bundle.

## Package and deterministic hashes

```text
<site_key>/<reserved-release_key>/
  public/index.html
  public/<validated-slug>/index.html
  public/assets/<sha256>.<allowlisted-extension>
  public/_release.json
  metadata/manifest.json
  metadata/validation.json
```

Only `public/` is a future serving boundary. There is no alias, vhost, activation link
or download route. A database release ID is assigned only by accepted M6B success.

File entries are `{relative_path, sha256, byte_size, mime_type}`, sorted by path.
`public_payload_hash` hashes the entries excluding the marker. `_release.json` contains
exactly contract_version, site_key, release_key and public_payload_hash. `artifact_hash`
hashes all public entries including the marker. `validation_hash` hashes exact canonical
validation-file bytes. `manifest_hash` hashes exact canonical manifest bytes; the manifest
does not contain its own hash. Neither metadata file participates in artifact_hash.
The verifier reconstructs expected metadata and compares actual bytes/inventory, rather
than trusting claimed file hashes. HTML contains site/release identity, never artifact_hash.

Private manifest fields are explicitly constructed: package/schema, public identity,
source revision ID/number/hash, generic composition mode/version, exact selected
component/theme versions, input identity, builder SHA/version, registry/toolchain
identity, profile/options, file entries/totals, job-created timestamp, hash layers,
validation summary and unavailable capabilities. It contains no raw source or actor,
worker, correlation, host URL, credentials or temporary paths. Producer journal data
is separate from deterministic package metadata.

Builder identity includes exact clean Git HEAD, component/theme manifest digest,
static bundle digest, canonicalization/render contract, PHP/libxml versions and relevant
extension presence. Identity excludes wall clock, environment, worker and actor.
Metadata uses the persisted job created_at value, never a new retry timestamp. Same fixed
job/input generated into two disposable stores produces identical artifact and
manifest hashes, including across different producer attempts. A different reserved
release intentionally changes HTML/marker hashes.
The canonical UTF-8/NULL/integer/list/map golden digest was independently checked using
.NET SHA256. Existing M6B input/idempotency contract tests remain enabled.

## Store, sealing and recovery

Configured work/artifact roots must already exist, be distinct, outside the repository,
and on the same filesystem. Missing configuration has no default root. The desktop
creates only random private temporary test directories, never proposed `/var/lib` roots.
Runtime Linux configuration additionally requires root-owned protected ancestors and private worker-owned
work/artifact roots. The store API rejects traversal, encoded/absolute paths, alternate
separators, dotfiles, Windows device names, duplicate/colliding inventory, symlinks,
hardlinks, special files and unlisted content. Private roots exclude hostile concurrent
path replacement; PHP lstat/fstat checks do not substitute for OS ownership isolation.

An exclusive nonblocking per-job lock spans construction. The database uniquely reserves
release_key; cooperating writers for that job share its lock. A producer journal is
written/fsynced before candidate content. A separate seal proof binds the exact job,
original execution ID/number, source/input/builder and verified manifest digest before
rename. Files use exclusive creation, bounded writes and fsync. Complete inventory and
bytes are rechecked before and after same-filesystem rename. Existing sealed output is
never copied over or overwritten. The API does not permit write access to sealed paths.

`verifyRelease` uses the persisted job and original producer to find the journal; a
receipt UUID is only a hint. It independently checks binding, proof and all bytes.
Recovery takes the existing writer lock without creating missing candidate/journal
content. An active writer, missing journal, mismatched evidence, tampered sealed output
or prepared seal without final bytes returns unknown. A matching journal with no seal
proof/final directory and no active writer proves safe absence or private quarantine
of a bounded pre-seal candidate. That candidate cannot resume or become public. Existing
M6B retry policy may retry a transient failure below its execution limit; recovery itself
never renders, renames, fills missing files or creates replacement content.

After three executions, recovery still uses the existing separate automatic/operator
budgets. Valid sealed output can be adopted only through current requester/source checks.
Missing/tampered final output blocks; original producer remains expired. Lost SQL commit
acknowledgement is resolved through existing committed-history replay, with no compensation.
Known safe failure is distinct from unknown outcome. Current CLI exposes automatic recovery
only; it does not invent an operator identity or implement M6F controls.

Cleanup accepts an in-memory random ownership handle, checks the entire candidate
inventory against files that handle wrote, and deletes only that candidate. Unknown
orphans, another attempt and sealed releases are retained. Test cleanup checks the exact
random run root and never follows symlinks. Package limits are 100 pages, 2,000 total
files including metadata, 20 MiB/asset, 1 MiB/HTML, 100 MiB/public package, 1 MiB/manifest
and 16 KiB/validation. The existing service evidence summary remains capped at 16,000 bytes.
Candidate creation preflights enough available filesystem space for the maximum package
and metadata; this is advisory and does not replace bounded write-failure handling.

Atomicity means cooperating-process flock plus same-filesystem rename under protected
roots. The adapter fsyncs file contents but does not claim directory-entry fsync or
Linux power-loss durability. A crash may leave journals/candidates needing inspection;
ambiguity fails closed. Windows tests are not proof of Linux mount, permission,
cross-process scheduling, directory durability or MySQL/filesystem integration.

## Trusted CLI configuration contract (not provisioned)

Only the exact repository `scripts/site-build-worker.php` entry under Linux CLI and a
matching non-root POSIX UID can initialize the factory. Fixed root-owned, non-writable
`/etc/ubo-site-build-worker.json` must contain exactly: version=1, environment=staging,
worker_id, worker_uid, builder_code_sha, work_root, artifact_root and legacy_upload_root
(nullable when no local assets are needed). No arbitrary configuration path is accepted.
The checkout and tracked code must be root-owned/protected and clean at the configured
SHA. Ignored `private/config/env.php` must be protected and APP_ENV must match staging;
database details come only from that existing trusted configuration. No environment
variable, CLI DSN, actor, dependency class, path or trusted flag overrides these checks.
The only external executable is fixed `/usr/bin/git`, with bounded output/time and argv.
External process/configuration evidence is resolved before lifecycle transactions. Builder identity rechecks clean HEAD and bundle/registry/runtime identity outside locks; workerIdentity itself is an in-memory check. The fixed Git invocation permits only its verified root-owned checkout via safe.directory.

The worker requires existing PHP DOM/libxml, filter, image metadata functions, fsync
and POSIX identity support. Linux `php -n` may lack DOM/POSIX even though Windows builds
include DOM: use the approved installed invocation profile later; missing capabilities
fail closed. No PHP configuration or package was installed/changed here. `--help`, invalid
arguments and web SAPI return before runtime bootstrap; `--once` claims at most one job,
and `--recover JOB_ID` performs at most one automatic recovery claim. No scheduling,
service provisioning, operator controls, deployment or publication is implemented.

## Original local validation and later native gate

The local counts in this section describe original head `d06f9e5`, not the corrected head.

M6C suites cover artifacts, security, service integration, bounds, CLI and scope. Real
file bytes are used with fake lifecycle SQL. Scope guards retain existing negative
protection and add exact M6C paths only; historical migration integrity pins remain.

The six M6C suites pass 274 assertions: artifact 45, security 91, service integration 54,
bounds 19, CLI 12 and scope 53. Security ran a real Windows hardlink rejection; symlink
creation was unavailable (one of two native local link cases executed). CLI checks
include one actual CGI denial. Bounds use test-only counter doubles for 100 MiB and
2,000-file edges, with real bounded files for other cases. Artifact checks also pass
with configured PHP and `php -n -d disable_functions=ctype_digit,mb_strlen` (45 each).
Windows directory-renaming tests required the normal approved temporary-file execution
context after the sandbox denied rename; this is not Linux evidence. No browser or
Narrator matrix was run.

All 66 available standalone `*Test.php` suites pass after focused corrections. The
initial 65-suite enumeration predated the new bounds suite; that suite passed separately.
The initial M2 scope failure was corrected with an exact file-primitive allowance while
retaining network/provider/lifecycle guards; the M6C scope check was corrected to inspect
only the added metadata return in the existing revision owner. Neither change removes a
negative guard. The existing launcher suite passes 2,131 assertions across 161 isolated
Bash scenarios, 47 mount cases and 27 no-ctype port/type cases per mount form, using three
PHP profiles and fake commands only. The existing migration-runner suite passes 848
simulated assertions without SQL; the M6B behavior suite passes 401 assertions.

All 236 tracked/new PHP files pass lint. The 25 changed PHP files and final runtime/store
edits were re-linted. Three script syntax checks pass: Bash `RunM6BMySql.sh`, PowerShell
`RunM6BMySql.ps1` and JavaScript `WebsitePlatformM5CBrowser.cjs`; these were syntax checks,
not native harness or browser execution. Eleven modified Markdown documents have balanced
fences and eight added local links resolve. A bounded current-versus-historical status
review preserved dated evidence and corrected live M6B/M6C status. `git diff --check`
passes. Raw initial results, corrected final results and hashes are retained outside the
repository with the task summary.

| Planned cases | This task's layer | Remaining boundary |
| --- | --- | --- |
| B01–B05 | Executed local: M6B material/non-material/source gates; M6C real output and successor rejection with fake SQL. | Native MySQL plus real approved asset integration, not reattribution of M6B evidence. |
| B06, B09, B22 | Executed local decision/clock doubles and existing replay tests; concurrency simulated only. | Independent native connections/process barriers and lease races. |
| B07–B08, B11 | Executed local real candidate/seal/conflict/quarantine with fault doubles; stable reserved identity and bounded retry. | Disk-full/permission/process-kill and native durability cases authored below, unexecuted. |
| B10, B12–B16 | Executed local hash golden/reproducibility, privacy, DOM/CSP/navigation/forms, MIME/path/rights/bounds and tampering. | Linux symlink/device/permissions and browser/actual-byte review; no production claim. |
| B17–B21, B26 | Executed local preserved M6B authorization tests and CLI/CGI denial; current roles/budgets retained. | Real trusted Linux startup and isolated MySQL gates. |
| B23–B25 | Executed local real artifacts plus fake-SQL revocation/adoption/lost-ack/replay faults. | Native lock/commit-ack races across storage and SQL. |
| C03, C04, C05, C07, C09, C11–C12 (build portions), U01/U02 | Executed local ownership/quarantine, expired producer adoption, no rerender, counters and bounded recovery doubles. | Native process faults/locks; operator CLI identity is future control scope. |
| D01–D26, R01–R06, deployment portions of C01–C13, U03 | Later scope: M6D–M6G. | No deployment, activation, restore, production grant, or new UI tested/implemented. |
| S01–S03 | M6B schema remains unchanged; local canonical-source guards execute. | Existing dated M6B native evidence retained; no migrations/SQL executed in this task. |

Later separately approved isolated staging BUILD-ONLY validation must use an isolated
fixture database and private run roots, never the working staging database or the
destructive M6B fixture runner aimed at it. Required authored native cases:

1. Verify exact reviewed SHA, existing extensions/invocation, UID/owner/modes, protected
   ancestors, same device and private serving boundaries; dirty/wrong-SHA/config/UID
   startup must create no DB work. Do not provision or deploy without separate authority.
2. Use synthetic approved material/non-material revisions and approved local image/PDF
   bytes; prove exact source/tenant/rights/expiry and byte verification without live providers.
3. Run execution through actual MySQL 8.4, checking no external I/O under SQL locks and
   all release/validation/event writes atomically; test requester/source races and lost ack.
4. Kill separate worker processes before journal, during candidate write, after seal proof,
   after rename, and before/after SQL commit. Prove active-writer exclusion, unknown vs
   quarantine, immutable winner, original producer binding and exhausted-budget adoption.
5. Test Linux symlink/hardlink/device/permissions, collision, quota/disk-full and same-mount
   rename assumptions, including directory durability and retained evidence after restart.
6. Reconcile exact run-owned files/rows and preserve unrelated data/orphans. Verify zero
   deployment targets/pointers/approvals/provider actions. No activation or restore.

No migrations were changed or added. All 25 canonical Git blobs remain byte-identical
to baseline; reviewed 017/019 pins and existing Windows checkout line endings are retained.
Migration 025 raw-file and canonical SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
No SSH, live DB, SQL/migration execution, provider call, Spaces upload, host provisioning,
runtime installation, deployment, publication, merge or auto-merge occurred.

## Original implementation changed-file inventory

37 files: 19 added and 18 modified. No deletions or renames.

| Change | File |
| --- | --- |
| Modified | `docs/247sp-website-generation-architecture.md` |
| Modified | `docs/codex-handoff.md` |
| Modified | `docs/database-plan.md` |
| Modified | `docs/first-customer-checklist.md` |
| Modified | `docs/production-readiness-review.md` |
| Modified | `docs/sprint-8.8-m6-implementation-plan.md` |
| Modified | `docs/sprint-8.8-m6b-closeout.md` |
| Modified | `docs/sprint-8.8-m6b-linux-validation.md` |
| Modified | `docs/sprint-8.8-m6b-local-implementation.md` |
| Added | `docs/sprint-8.8-m6c-local-implementation.md` |
| Modified | `docs/sprint-8.8.md` |
| Added | `private/classes/LocalSiteArtifactStore.php` |
| Added | `private/classes/SiteArtifactAssetResolver.php` |
| Added | `private/classes/SiteArtifactBuilder.php` |
| Added | `private/classes/SiteArtifactFiles.php` |
| Added | `private/classes/SiteArtifactStore.php` |
| Added | `private/classes/SiteArtifactValidator.php` |
| Modified | `private/classes/SiteBuildDependencies.php` |
| Added | `private/classes/SiteBuildRuntime.php` |
| Modified | `private/classes/SiteBuildService.php` |
| Modified | `private/classes/SiteCompositionRenderer.php` |
| Added | `private/classes/SitePublicRenderContext.php` |
| Modified | `private/classes/SiteRevisionManager.php` |
| Added | `private/site-static/static-review-v1.css` |
| Added | `scripts/site-build-worker.php` |
| Modified | `tests/WebsitePlatformM2ScopeTest.php` |
| Modified | `tests/WebsitePlatformM6BScopeTest.php` |
| Added | `tests/WebsitePlatformM6CArtifactTest.php` |
| Added | `tests/WebsitePlatformM6CBoundsTest.php` |
| Added | `tests/WebsitePlatformM6CCliTest.php` |
| Added | `tests/WebsitePlatformM6CIntegrationTest.php` |
| Added | `tests/WebsitePlatformM6CScopeTest.php` |
| Added | `tests/WebsitePlatformM6CSecurityTest.php` |
| Modified | `tests/support/WebsitePlatformM6BDependencies.php` |
| Modified | `tests/support/WebsitePlatformM6BScope.php` |
| Added | `tests/support/WebsitePlatformM6CFixture.php` |
| Added | `tests/support/WebsitePlatformM6CScope.php` |

## PR #128 review corrections

M6C remains **IMPLEMENTED LOCALLY / REVIEW REQUIRED**. Native build-only validation is
pending. This correction does not reopen M6B or authorize any operational worker run.
The existing branch and PR remain the only implementation review; no new PR is created.

### Reproductions and corrections

The four review comments at original head `d06f9e5` had no replies when inspected.
Original reported local PASS (66 standalone suites, 274 M6C assertions) is retained above.
The completed review is a separate observation, including the bounds failure and unsafe
Linux CLI behavior. Fresh before/after stdout, stderr, exits, profiles and summary are
saved outside the repository in the correction evidence directory.

| Finding | Before observation | Correction |
| --- | --- | --- |
| [4304835568](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835568) producer evidence | Reproduced on Windows PHP 8.4.24 with fake SQL/real private files: remove or change asset bytes after request/claim; execution throws `artifact_invalid`, verifier returns `unknown`, completion becomes `reconciliation_required`. | Compare cheap stored input/identity first, then create the existing candidate/journal before execution asset-byte validation. Request-time preparation still writes no journal or attempt. |
| [4304835573](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835573) verification heartbeat | Reproduced with a controlled database clock advanced during actual file verification: the final completion rejects an expired lease and inserts no release. No multi-minute sleep or native Linux claim. | Service-owned pulse flows through dependency, real runtime adapter, store, inventory, chunked reads and validator for every active verification path. |
| [4304835576](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835576) bounds alias | Exact original `php -n tests/WebsitePlatformM6CBoundsTest.php` exits 0 with 19 assertions locally, empty stderr; the reviewer failure did not reproduce. A deliberately retained PHP reference deterministically makes the shallow saved copy inherit 2,000 files. | Reconstruct only independent scalar/file counters, restore those fields in `finally`, and retain the original live lock/ownership state. A forced-reference regression proves isolation, restoration, later valid write, duplicate denial and orphan preservation. |
| [4304835581](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835581) CLI safety | Review identified that Linux passes the OS gate and can reach protected configuration or operational work. No real `--once`/`--recover` was run to reproduce this on a host. | Valid arguments run only in isolated child fixtures using actual runtime/entry source with deny-only namespace doubles. Production runtime selection/configuration stays fixed. Missing capability checks now precede configuration access. |

Instrumentation of the original bounds test's unchanged counter sequence under the
current isolated fixture reports an absent saved candidate reference, restored bytes=0
and file_count=0, and the original 19 assertions pass. The forced-reference case is
separate and deterministic; it does not establish a native Linux reproduction.

### Producer evidence and failure settlement

Execution retains current requester/source/lease checks before external writes, compares
the stored public input and exact asset descriptors, and calls `createCandidate` before
fallible asset reads. The existing fsynced journal binds job/site/release/input/builder
and original execution attempt; candidate content follows that evidence. The builder's
`finally` releases the writer handle on asset, callback, validation and seal failures.
Successful package/hash definitions and deterministic metadata are unchanged.
The private journal is written to `.json.pending` and published by same-directory rename
only after write/flush/fsync/mode checks succeed. Even complete-looking pending JSON is
unknown evidence; a failed journal write cannot masquerade as verified safe absence.
This does not add a directory-entry power-loss guarantee.

An intact journal plus an exclusively observed empty/private candidate permits quarantine
settlement. Missing or corrupt asset bytes remain integrity failures, never automatic
transient retries. A simulated unreadable source or interrupted copy returns the existing
storage failure and can retry under unchanged policy with a new authorized attempt,
same requester/reserved release and incremented execution count. No missing journal is
interpreted as safe absence. Before-journal interruption, a partial journal-write fault
and seal-proof-without-final-output remain unknown/reconciliation states. These storage
faults are explicit local doubles; real disk/fsync/permission failures remain native gates.
Repeated failure/recovery completion returns recorded history without extra effects.
Recovery never regenerates output, removes unknown data or changes execution budgets.

### Active verification leases

`SiteBuildService::leasePulse` shares the existing 25-second monotonic cadence and forced
checkpoint behavior. It captures only the active execution or recovery lease. The optional
clock argument is private and used through Reflection only in isolated cadence tests.
The 120-second lease and persisted 900-second execution / 300-second recovery deadlines
are unchanged. Renewal is a short existing transaction between external operations.

Ordinary success, failure verification, `reconcileBuild`, and recovery completion's
independent reinspection all pass that pulse to `verifyOutcome`, then `verifyRelease`.
Inventory checkpoints occur for directories/entries; reads check progress around each
at-most-1-MiB chunk; file validation/hashing and pre-render asset verification also pulse.
The store distinguishes a callback exception from corrupt evidence and propagates it,
releasing its lock. Final locked requester/source/lease/ownership checks remain in place.
A single blocking OS read cannot be interrupted by a PHP checkpoint; native filesystem
latency/crash behavior remains a separate validation requirement.

Controlled-clock tests span 125 seconds for ordinary completion and failure inspection,
and 125 seconds each for recovery reconciliation and independent adoption within its
original 300-second deadline. The original producer stays expired and unchanged.
Natural absolute-deadline exhaustion, mid-verification ownership/lease loss, callback
exceptions and replacement worker identity stop processing without release/audit success.
Already committed completion returns before verification and heartbeat writes. Read-only
manifest/history inspection has no lease allocation or renewal.

### Safe cross-platform CLI tests

Actual entry processes cover help, invalid arguments and available CGI 404 denial only.
Every valid `--once`/`--recover` test invokes `WebsitePlatformM6CCliChild.php`, never the
real host worker. The child evaluates copies of the actual entry/runtime source in a
test namespace; a checked count of bootstrap imports is replaced with sentinels.
OS/SAPI/POSIX/capability/path/stat/config/process/service doubles fail closed. Filesystem
configuration reads are synthetic; external Git/process calls are blocked; no DB/queue
or artifact write is available. A configured-host double is included, so safety never
depends on absence of `/etc/ubo-site-build-worker.json`. No host file is renamed or hidden.

Assertions check actual child exit/stdout/stderr plus no-bootstrap (help/invalid/web),
no-host-config, no-DB, no-claim and no-artifact-output traces. Parent-owned temporary
stdout/stderr captures are distinct from worker output and cleaned exactly. The parent
enforces ten-second / 64-KiB bounds without blocking on Windows anonymous pipes. A stalled
child is terminated in a one-second timeout regression. Linux owner/UID/config/capability
branches are deterministic simulations, not a native Linux startup PASS.

During fixture development, Windows `dirname` semantics caused a simulated Linux path
walk to stall; only those isolated test processes were stopped. The fixture now models
POSIX path ancestors explicitly. A simulated `proc_open` signature was corrected to
retain its by-reference pipes argument. These intermediate fixture failures are retained
separately from final results, along with corrected DTO/producer assertions in the new
recovery suite. No operational worker or new environment was used.

### Correction validation and inventory

Fresh full regression: **67 standalone suites, all exit 0; 593 M6C assertions**.
Every affected suite was first run individually with `php -n tests/<Suite>Test.php`,
then every available `tests/*Test.php` was run individually with `php -n`, including
all M2-M6B suites and the new recovery suite. No suite was disabled or skipped.

| M6C suite | Assertions | Exit |
| --- | ---: | ---: |
| Artifact/determinism | 45 | 0 |
| Bounds | 29 | 0 |
| Safe CLI | 186 | 0 |
| Integration | 54 | 0 |
| Recovery/progress | 135 | 0 |
| Scope | 53 | 0 |
| Security | 91 | 0 |

The existing M6B launcher test passed 2,131 assertions, 161 isolated Bash scenarios,
47 mount cases and 27 no-ctype port/type cases per mount form across three PHP profiles.
Its commands are fake: no native Linux/container/MySQL execution. The migration-runner
suite passed 848 simulated-lifecycle assertions without a database. Actual Windows
private-file and child-process tests include one CGI denial and one hardlink case;
SQL, clocks, disk faults and Linux startup/identity branches use explicit doubles.

`php -n -l` passed for all 238 tracked/new PHP files. Syntax-only checks passed:
`node --check tests/WebsitePlatformM5CBrowser.cjs`,
`bash -n tests/RunM6BMySql.sh`, and PowerShell `Parser.ParseFile` for
`tests/RunM6BMySql.ps1` (zero parser errors). The changed document's local link/anchor,
fences and milestone-status checks passed. Working and staged `git diff --check`
passed; the committed check is recorded in the outside-repository final report.
Actual per-suite exits/stdout/stderr are retained. Expected injected-error diagnostics
and Git checkout CRLF warnings were not silently relabeled as empty stderr.

Local runtime: PHP 8.4.24 ZTS, Visual C++ 2022 x64; libxml 2.11.9, Windows CLI.
Both default and `-n` invocations report no loaded php.ini. DOM, image metadata, fsync,
filter and ctype are available; POSIX and mb_strlen are absent. Additional artifact checks
use `-n -d disable_functions=ctype_digit,mb_strlen`; no installed PHP configuration is changed.
Script tools: Node v24.22.0, PowerShell 7.6.5, Git Bash 5.3.15. Initial Bash syntax-only
startup was blocked by Windows sandbox named-object permissions; the approved normal
execution context passes the same `bash -n` command. Initial diagnostics remain recorded.

Correction-only inventory relative to `d06f9e5`: 16 files, 14 modified and two added.

| Change | File |
| --- | --- |
| Modified | `private/classes/LocalSiteArtifactStore.php` |
| Modified | `private/classes/SiteArtifactAssetResolver.php` |
| Modified | `private/classes/SiteArtifactBuilder.php` |
| Modified | `private/classes/SiteArtifactFiles.php` |
| Modified | `private/classes/SiteArtifactStore.php` |
| Modified | `private/classes/SiteArtifactValidator.php` |
| Modified | `private/classes/SiteBuildDependencies.php` |
| Modified | `private/classes/SiteBuildRuntime.php` |
| Modified | `private/classes/SiteBuildService.php` |
| Modified | `tests/WebsitePlatformM6CBoundsTest.php` |
| Modified | `tests/WebsitePlatformM6CCliTest.php` |
| Modified | `tests/support/WebsitePlatformM6BDependencies.php` |
| Modified | `tests/support/WebsitePlatformM6CFixture.php` |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` |
| Added | `tests/WebsitePlatformM6CRecoveryTest.php` |
| Added | `tests/support/WebsitePlatformM6CCliChild.php` |

All 25 canonical migrations and reviewed 017/019 pins remain unchanged from baseline;
025 remains applied to working staging, with the canonical/raw hash recorded above.
No schema, canonical migration runner, M6B launcher, wrapper, TLS or host-permission
changes occur. M6B identity, authorization, lock order, result/audit atomicity and budgets
remain the lifecycle authority. Native Linux startup/permissions, process/crash races,
power-loss behavior and actual MySQL/filesystem integration remain pending. No server,
Docker/WSL/package setup, live database, migration, deployment, provider, M6D–M6G or
Narrator action is part of this correction.

## Complete inventory admission

This focused correction follows [finding 4304989392](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304989392)
on `7787bec22b8eb5a8cfed91c4ed183b4f2fe2e511`. The branch and PR are unchanged.
The previous producer-evidence, active-lease, independent-counter and isolated-CLI
corrections remain intact. M6C is **IMPLEMENTED LOCALLY / REVIEW REQUIRED**;
native build-only validation is **PENDING**.

### Admission and accounting

`SiteArtifactBuilder::prepareInput` now constructs the public projection and bounded
asset descriptors, calls the existing `SiteArtifactValidator::expected` inventory
validator, and only then verifies asset bytes. No second asset-count formula is added.
The existing service ordering remains current authorization/source eligibility,
preparation outside SQL locks, then a locked recheck and insertion. Successful-history
replay still precedes new-request preparation; the service itself is unchanged.

Total package files are distinct content-addressed media output paths + each HTML page
plus generated CSS + public release marker + two private metadata files. Thus total files
are distinct media + pages + 4, bounded by the unchanged `MAX_FILES = 2000`.
Multiple usages of the same digest/extension occupy one physical path. The existing
1,997-descriptor cap, 1-MiB input/manifest limits and byte/security limits remain separate
and unchanged. Deduplication does not waive them. Public manifest/DTO `file_count` still
counts media + pages + CSS + release marker; it excludes the two private metadata files.
No serialized field, profile or hash definition changes. Per-write, final inventory,
observed-byte validation and immutable sealing checks remain in place.

### Before/after evidence and boundaries

The original preparation path reached the first asset-validation call for metadata
projections of 1 page/1,996 assets, 2 pages/1,995 assets and 100 pages/1,897 assets,
while `expected` rejected those same 2,001-file projections as `artifact_invalid`.
Their actual canonical BuildInput sizes were 274,738, 275,078 and 308,670 bytes,
respectively, below the unchanged 1-MiB limit. MIME and digest formats, per-asset and
aggregate declared sizes, routes, theme and page-count inventory rules were valid.

These metadata tests deliberately stop at the resolver's existing guard before byte
validation. Synthetic digests do not establish real byte integrity, and this is not a
full large-package or native build reproduction. The one-/two-page metadata fixtures
isolate projection/inventory admission; they do not claim authoring eligibility for
thousands of usages within component/section cardinality limits.

Separate actual new-request reproductions use valid 80-page/1,917-asset and
100-page/1,897-asset compositions, authored through the existing composition owner:
one hero section per page, at most 24 usages per section, real stored snapshot hashes,
current fixture approvals/rights and unchanged source-eligibility validation. Both
previously reached the deliberate first-byte-boundary stop. After correction, both
return `artifact_invalid` with zero asset-validation calls. No invented asset file is
read or accepted. Both before/after captures retain actual exit/stdout/stderr outside
the repository; the prior reports remain untouched.

| Pages | Distinct media | Total package files | File-count gate |
| ---: | ---: | ---: | --- |
| 1 | 1,995 | 2,000 | Accept |
| 1 | 1,996 | 2,001 | Reject before asset reads |
| 2 | 1,994 | 2,000 | Accept |
| 2 | 1,995 | 2,001 | Reject before asset reads |
| 100 | 1,896 | 2,000 | Accept |
| 100 | 1,897 | 2,001 | Reject before asset reads |
| 1 | 0 | 5 | Accept; preparation completes without media reads |
| 1 | 1 from 1,997 usages | 6 | Accept inventory; byte integrity not claimed |

An additional 1,998-usage duplicate-path case still fails the descriptor bound, and
oversized serialized input still fails its independent limit. Acceptance in this table
means the file-count gate only. No thousands of physical files or oversized payloads
were generated to exercise arithmetic.

The new-request regressions compare complete fake-database snapshots and mutation
queries: no job, attempt, requested/start event, execution budget or recovery allocation,
and no work-directory entry, journal, candidate or sealed output. Exact successful
historical replay returns the same release without new eligibility/preparation I/O,
events or attempts. A small real private-filesystem build verifies one deduplicated
asset, three pages, CSS and marker (public count 6), plus two metadata files (total 8),
and seals successfully with the existing artifact hash. The full existing determinism,
producer-failure/heartbeat and safe-CLI suites remain enabled.

Fixture development initially exceeded hero cardinality in the two-page authoring
sample; the valid service sample now uses 80/100 pages without relaxing eligibility.
The deliberate stop uses the existing service exception boundary so its signal is not
wrapped as an unrelated dependency error. An initial sandbox run reached the unchanged
private-journal rename and failed there; the same admission suite passed in the normal
Windows execution context. Those intermediate results remain separate from final PASS.

### Admission correction validation and inventory

Fresh execution: **68 standalone suites, all exit 0; eight M6C suites with 651 assertions**.
The new admission suite passes 58 assertions. The existing M6C suites pass artifact 45,
bounds 29, CLI 186, integration 54, recovery/progress 135, scope 53 and security 91.
Each M6C suite ran individually first, then every available `tests/*Test.php` ran with
`php -n`. The unchanged fake launcher passed 2,131 assertions across 161 Bash scenarios,
47 mount cases and 27 no-ctype port/type cases per mount form across three profiles;
the simulated migration runner passed 848 assertions. Neither suite executed Linux,
containers or a live database. Actual Windows checks include the small sealed package,
one hardlink case and one CGI denial. Native/platform limits remain explicit.

`php -n -l` passed all 239 tracked/new PHP files. Syntax-only checks passed for
`node --check tests/WebsitePlatformM5CBrowser.cjs`,
`C:\Git\bin\bash.exe -n tests/RunM6BMySql.sh` and PowerShell `Parser.ParseFile` on
`tests/RunM6BMySql.ps1` (zero errors). This document's two local links/anchors, fences
and milestone/backup status checks pass. Working/staged diff checks pass; committed
checks are retained in the outside-repository report. Per-suite exits/stdout/stderr
are captured; expected injected-error diagnostics and Git CRLF warnings remain visible.

Runtime: Windows PHP 8.4.24 CLI ZTS Visual C++ 2022 x64, libxml 2.11.9; no php.ini in
default or `-n` profiles. Node v24.22.0, PowerShell 7.6.5 and Git Bash 5.3.15.
These are newly observed results; the preceding 67-suite/593-assertion record belongs
to the prior correction. Neither historical evidence nor intermediate failures was erased.

Correction-only inventory relative to `7787bec`: three files.

| Change | File |
| --- | --- |
| Modified | `private/classes/SiteArtifactBuilder.php` |
| Added | `tests/WebsitePlatformM6CAdmissionTest.php` |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` |

All 25 migration files retain their raw bytes and baseline Git contents, including
017/019 and the existing pins. Migration 025 remains applied to working staging and
unchanged at SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
No migration, native-test infrastructure, M6B lifecycle, configuration or permission
change is part of this correction. Native Linux startup/permissions, actual MySQL/
filesystem integration and native crash/race/durability validation remain pending.

### Historical admission-correction handoff: backup checkpoint not yet reported

The following was the handoff at the end of the admission correction. The current
operator report in the next section supersedes its instruction to prepare a backup.

**NOT EXECUTED. This local correction does not authorize server access or a backup.**
Before the next M6C droplet runtime validation or environment change, prepare a fresh
backup of the **deployed staging application**, local uploads, relevant recovery
configuration and an **actual `ubo_staging` SQL export**, including hashes and recovery
notes. Use the data volume without exhausting the boot disk. Preserve private
credentials and distinguish any required manual administrator step from restricted
Codex execution. Do not automatically overwrite or delete existing backups.

After preparing that future backup, pause for **Frank's WinSCP download and verification
of the Windows copies** before continuing the runtime validation/environment change.
This backup/download checkpoint is separate from the pending native build-only gates.
M6B remains formally closed; M6D-M6G remain NOT STARTED; production remains
UNAUTHORIZED / NOT DEPLOYED. No server, backup, live database, migration, deployment,
provider, M6D-M6G or Narrator action occurred in this local admission correction.

## Pre-output execution denials

Correction for PR #128 comment `4305089747`, relative to
`3e6871321820f02ddccab7872ae5f69976b91821`. This is local code/test work only.

### Reproduced failure and settlement boundary

Before the change, the actual service with fake SQL and private temporary storage
reproduced requester revocation, deletion, role loss, source approval loss, builder
unavailability and builder mismatch after a committed claim. Each left the service
attempt running with one execution, one attempt, one start event, no outcome event,
and no artifact I/O, journal, candidate or seal. Controlled expiry followed by actual
recovery service calls returned `blocked` / `reconciliation_required`.

The isolated actual CLI source reproduced exit 1 with fixed `unauthorized` or
`conflict` diagnostics for revocation/mismatch, leaving the attempt running. Its
builder-unavailable catch instead recorded an abandoned attempt and required
reconciliation, because journal verification could not establish absence. The
no-work control returned exit 0 and `no_work_claimed`. These are observed local
results, not native MySQL/Linux claims; before/after outputs are retained outside Git.

Construction now has an explicit nontransferable, single-use service contract.
Only `claimBuild` after an acknowledged fresh allocation places authority in the
service's private process memory. Its key binds the exact job/attempt/kind/token;
its value binds the trusted worker and environment, process ID, runtime object and
database connection. The lease DTO exposes none of this authority. A new process,
copied lease, fork with a different PID, runtime/connection substitution, or a
previous invocation cannot reconstruct it from a missing journal. No schema field
is reused as an entry flag. No migration or filesystem marker is added.

`executeBuild` first authenticates and resolves committed database history under
owner locks. It then consumes the authority before builder identification or any
execution callback. Consumption has no callback/yield point. Reentrant calls cannot
reach either the denial branch or construction. The sole consuming invocation can
either settle a gate denial in a short locked transaction or enter the artifact
dependency after that transaction commits. Settlement rechecks current trusted
worker, exact token, current attempt ownership, lease/deadline, requester, source,
input, builder and policy. Ownership transitions use the existing lifecycle locks;
the first execution pulse rechecks ownership before artifact work after lock release.
All filesystem/render work stays outside SQL transactions.

This is stronger than a local “not written yet” flag: **all service construction
requires the unexported authority**, and consumption prevents every later invocation
of that lease from entering construction or claiming non-entry. The protected
one-shot CLI already claims and executes in the same process. Persisting a lease
for construction in another process is deliberately unsupported; such a process
can only resolve history or use the existing evidence-based recovery path. Direct
artifact dependencies remain trusted internal wiring, not public entry points.

The pre-output result uses existing post-claim `failed` semantics. It preserves the
attempt, start event, requester provenance, timestamps and all execution/attempt
counters. One system-authored `site_build_failed` event commits atomically with the
job and attempt. Existing categories distinguish requester authorization, source
eligibility, input mismatch and builder/policy configuration. These gates are
nontransient and schedule no retry. Existing transient storage/network retry rules
after independently verified output remain unchanged.

The result is returned as `completed` only after commit. CLI prints the fixed
`Build-only outcome: failed.` and exits 1, distinct from `no_work_claimed` / exit 0.
There is no broad CLI exception-to-safe-failure conversion. The unchanged result
fingerprint supports repeat execution/completion history without new events,
heartbeats, artifact inspection or eligibility checks. Committed success returns
the recorded release before new gates and cannot be rewritten by a later denial.

Audit failure rolls the whole settlement back. Consumed authority is never restored,
including after rollback or a lost commit acknowledgement; unresolved work remains
conservative. An uncertain commit throws a safe database failure, and only a later
authoritative read can return an actually committed result. An unacknowledged claim
never issues construction authority. Neither case triggers blind compensation.

Once the artifact dependency is entered, failures use existing independent producer
verification, even if the journal is missing. Pending/corrupt journal, interrupted
write/flush and uncertain seal still remain unknown. Private partial output stays
retained; recovery never fills it, rerenders, deletes unknown files, or revives an
expired producer. Producer evidence still precedes fallible execution asset reads.
The 25-second renewal cadence and 120/900/300-second lease/execution/recovery bounds,
budgets, 2,000-file admission, byte limits, layout, hashes and public allowlists remain.

### Execution correction validation and inventory

Fresh run: **69 standalone suites, all exit 0; nine M6C suites, 888 assertions**.
The new execution suite passes 216 assertions; CLI passes 207, recovery/progress 135,
integration 54, admission 58, artifact 45, bounds 29, scope 53 and security 91.
All nine M6C suites and four affected M6B suites ran individually first, then every
available `tests/*Test.php` ran with `php -n`. The unchanged launcher passed 2,131
assertions across 161 isolated Bash scenarios, 47 mount cases and 27 no-ctype port/type
cases per mount form across three PHP profiles. The simulated migration runner passed
848 assertions. Neither executed Linux, containers or a live database.

`php -n -l` passed all 241 tracked/new PHP files. JavaScript and Bash syntax checks
passed (exit 0) for `tests/WebsitePlatformM5CBrowser.cjs` and `tests/RunM6BMySql.sh`;
PowerShell `Parser.ParseFile` found zero errors in `tests/RunM6BMySql.ps1`.
All three local anchor links, both fences and current/historical milestone/backup
status checks pass. Working and staged diff checks pass; committed checks are retained
outside Git. Per-suite commands, exits, stdout/stderr and durations are retained there.
Expected injected PDO diagnostics and Git CRLF warnings remain visible. The first new
test run exposed an incorrect audit-column assertion with PHP warnings; the assertion
was corrected to require `actor_user_id` before the successful focused/full runs.

Observed runtime: Windows PHP 8.4.24 CLI ZTS Visual C++ 2022 x64, libxml 2.11.9,
no php.ini in default or `-n` profiles; Node v24.22.0, PowerShell 7.6.5, Git Bash
5.3.15. One Windows hardlink case and one actual CGI denial executed; the symlink case
was unavailable. Earlier 68-suite/651-assertion results remain historical, not reused.

Focused cases include
revoked/missing/deleted/role-changed requesters, lost source eligibility, unavailable/
mismatched/malformed builders, input/policy failure, wrong token/owner/worker/
environment, lease/deadline loss, identity-time ownership changes, database errors,
audit rollback, lost claim/settlement acknowledgements and committed-success replay.
Controlled callbacks reenter during identity, artifact entry and journaled progress;
substituted private authority tests model process/runtime/connection loss. These are
simulated interleavings and process-identity faults, not native multi-process proof.
The isolated CLI child uses the actual entry and service with fake SQL and private
temporary files; no protected host configuration, operational queue or live database.

Correction-only inventory: seven files, two production changes.

| Change | File | Purpose |
| --- | --- | --- |
| Modified | `private/classes/SiteBuildService.php` | Issue/consume construction authority; atomically settle known non-entry; resolve history first. |
| Modified | `scripts/site-build-worker.php` | Report service-completed claims and return nonzero for failed outcomes. |
| Added | `tests/WebsitePlatformM6CExecutionTest.php` | Focused lifecycle, ownership, interleaving, rollback and replay regressions. |
| Modified | `tests/WebsitePlatformM6CCliTest.php` | Assert actual isolated CLI failed-claim/no-work reporting. |
| Added | `tests/support/WebsitePlatformM6CExecutionChild.php` | Actual CLI/service fixture with fake SQL and private temporary storage. |
| Modified | `tests/support/WebsitePlatformM6CFixture.php` | Test-only builder/worker hooks; production wiring has no new selector. |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` | This evidence, boundary and current handoff. |

All 25 migrations and integrity pins remain unchanged. Migration 025 remains applied
to working staging, canonical SHA-256
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
No applied SQL, launcher, wrapper, TLS configuration, route, scheduler, provider or
publication behavior changes. Native Linux startup/permissions, actual MySQL locks/
FKs/commit behavior, multi-process races and filesystem crash/durability validation
remain pending under separately authorized build-only validation. The 77-case M6
acceptance matrix is not claimed passed.

### Current next-run handoff: completed backup and download checkpoint

**BACKUP/DOWNLOAD CHECKPOINT — COMPLETE. SERVER BACKUP — VERIFIED.
WINDOWS DOWNLOAD — VERIFIED from operator-supplied checksum results.
RESTORE TEST — NOT EXECUTED.**

The operator reports that all seven hashes, including `SHA256SUMS`, matched. A separate
private confirmation receipt was retained on the server. Original backup files and
manifest and the Windows copy were not deleted. This local correction did not retrieve
or independently verify them; no path, hash value, timestamp or receipt content is
inferred. The historical “not executed in this local task” passage remains above.

Later authorized droplet validation must reference this completed checkpoint and retain
the private backups. A local branch correction alone is not a reason to repeat the
backup. No recreation, download, inspection, restore or deletion is authorized here.

M6B remains formally closed. M6C remains IMPLEMENTED LOCALLY / REVIEW REQUIRED with
native build-only validation pending. M6D–M6G remain NOT STARTED; production remains
UNAUTHORIZED / NOT DEPLOYED. The last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. This task performs zero server, backup,
live SQL, migration, deployment, Docker, provider, M6D–M6G or Narrator operations.

## CLI execution-child profile and diagnostics

This correction starts at `d4fb3d696fafabc93923915c4b294321d630d21b` on the existing
PR #128 branch. It changes only the tests/helpers and this implementation record.
Application, service, runtime, worker, renderer, store, shared M6C fixture and fake
database implementations are unchanged. This is an operator-observed test failure,
not a new reviewer finding or a resolved review conversation.

### Operator-reported Linux result at d4fb3d6

| Suite | Result | Assertions |
| --- | --- | --- |
| Admission | PASS | 58 |
| Artifact | PASS | 45 |
| Bounds | PASS | 29 |
| CLI | FAILED, exit 255 | Final total not emitted |
| Execution | PASS | 216 |
| Integration | PASS | 54 |
| Recovery | PASS | 135 |
| Scope | PASS | 53 |
| Security | PASS | 92 |

The eight passing suites reported **682 assertions**; all **241 tracked PHP files**
passed lint. Real Linux filesystem operations and both hardlink/symlink rejection
cases ran. SQL, clocks and interleavings remained simulated. The CLI stopped at
“Execution child reports trace.” Inspection found the committed `-n` execution-child
profile lacked PDO/PDOStatement needed by the fixture. Its raw child capture had
already been deleted: the precise original fatal diagnostic is **not retained** and
is not inferred here. No Windows CLI assertion count is substituted for that run.

The operator supplied the evidence directory
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-linux-20261018T-readiness`.
No full report filename or checksum was supplied. This desktop task does not access
that directory or independently verify the reported Linux evidence. The earlier
Windows 69-suite/888-assertion results remain historical results at their tested SHA.

### Dependency path, reproduction and selected profile

The execution child loads `WebsitePlatformM6CFixture`, whose fake SQL path is
M6BDatabase → M5ADatabase → M3ServiceDatabase. Their database and statement classes
extend PDO/PDOStatement, use PDO constants and PDOException, and override connection
operations with in-memory behavior. Empty PDO constructors and reflection-injected
fake connections need **PDO types, not a PDO MySQL driver or a real connection**.
Fixture construction also needs reflection, date, SPL filesystem iterators, JSON,
DOM/libxml, hashing/randomness, ctype/filter, image metadata and filesystem/fsync
functions. The new preflight checks these before requiring any fixture class.

On the available Windows PHP 8.4.24 build, PDO/PDOStatement are built in under `-n`
and PDO reports no available drivers. The old `-n` revoked execution child succeeds
in reporting its expected failed-claim trace (exit 1); the operator's missing-PDO
Linux behavior cannot be reproduced naturally here. A separately captured old-child
run with process-only `disable_classes=PDO,PDOStatement` exits 255 without a trace.
This is a controlled capability failure, not the missing original Linux diagnostic.
With the correction, controlled disabled PDO, DOM and fsync cases fail explicitly
with prerequisite exit 78 before fixture construction and without a fabricated trace.

The four normal execution scenarios use the same `PHP_BINARY`, without unconditional
`-n`. A loaded parent INI is passed with `-c`; otherwise the selected executable may
load its installed default configuration. Relevant effective parent `-d` directives
(`extension_dir`, disabled functions/classes and temporary directory) are carried
explicitly. Arbitrary command-line settings are not assumed to propagate. Memory is
capped at 256 MiB, preserving a smaller positive parent limit; execution time is 10
seconds, with a separate parent wall-clock deadline. Auto-prepend/append are disabled,
exception arguments are hidden, and errors go to captured stderr rather than logs.

A capability-only child runs first. If a required PDO/DOM/ctype/filter module is
missing but already loaded by the parent (for example with `-d extension`), the helper
can add its existing standard-named module from the parent's `extension_dir` to this
child only and recheck. It does not install modules, require a database driver, change
INI files, search PATH for another binary, or silently skip normal execution tests.
Nonstandard module filenames are not guessed; a still-incapable profile reports a
retained prerequisite failure and needs an existing capable invocation profile.
The standard shared-module fallback is not exercised on this built-in Windows build.

The no-INI argument, help, web and runtime-denial tests retain their purpose. Valid
`--once`/`--recover` remain confined to isolated children. Operational host calls are
limited to help/invalid arguments and CGI denial. The execution fixture still uses
actual service/entry code with fake SQL and private files, without protected host
configuration, a live database, operational claims or non-fixture artifact output.

### Retained diagnostics and cleanup

The small process helper keeps file captures to avoid blocking Windows pipes. The
existing 64 KiB aggregate output threshold and 10-second default wall timeout remain;
termination has bounded soft/forced waits and records the observed exit or signal
and whether the child is still running. Polling can observe output beyond the
threshold before termination. Evidence reads only a 32 KiB prefix per stream and
retains at most 4 KiB of sanitized diagnostics per stream, plus byte counts/limits.

Trace parsing and execution assertions now run while captures still exist. Missing
or malformed traces, startup errors, timeouts, overflow and failed validation retain
case, profile label, actual exit/termination state and sanitized stdout/stderr in an
owned temporary `ubo-m6c-cli-failure-<random>/failure.json` outside Git, before raw
captures are removed. The thrown summary identifies that evidence. Known engine
capability identities and fixed safe diagnostics are allowlisted; arbitrary messages,
paths, payloads, arguments, environment and unrecognized identifiers are redacted.
No assumed success trace or zero-effect counters replace a missing child trace.

Directories/files request 0700/0600. Windows uses the user's inherited temporary
directory ACLs; POSIX mode assertions run only on POSIX. Successful captures are
removed. Intentional negative regressions verify and consume only their own retained
evidence, leaving unrelated failures intact. A failed evidence write leaves the
original failure and sanitized summary intact and removes partial evidence. Tests
use an isolated process-only temporary root so concurrent suites cannot affect
cleanup observations. No installed PHP configuration is edited.

### Fresh desktop validation and exact inventory

The affected CLI suite passes **207 assertions**, including all four real execution
traces (revoked, builder-unavailable, builder-mismatch, no-work), 32 isolated valid
argument denials and **one actual CGI invocation**. The new process suite passes
**85 assertions**: real startup fatal, missing/malformed trace, nonzero exit despite a
parseable trace, timeout, overflow, payload/identifier redaction, evidence-write
failure, ownership-scoped cleanup, controlled missing PDO/DOM/fsync/JSON encoding/
stderr writing, and parent `-c` and relevant `-d` forwarding. Disabled diagnostic
functions still produce prerequisite exit 78 using a language-level output fallback.
All ten M6C suites pass **973 assertions** in the fresh standalone validation with
final affected-suite rechecks: 58/45/29/85/207/216/54/135/53/91 for admission, artifact,
bounds, CLI process, CLI, execution, integration, recovery, scope and security.

All 245 PHP files pass `php -n -l`; final edited helpers pass lint again. Node syntax,
Bash syntax and the PowerShell parser pass for the existing browser/launcher scripts.
The Bash sandbox launch was denied by Windows shared-memory permissions; the same
syntax-only command passes in normal Windows context. Four local document anchors,
balanced fences, seven-file inventory and working/staged diff checks pass.
The full run completed **70 standalone suites, all exit 0**. The unchanged synthetic
launcher passed 2,131 assertions across 161 Bash scenarios, 47 mount cases and 27
no-ctype cases per mount form across three PHP profiles; the simulated migration
runner passed 848 assertions. No native harness, container or live database ran.
Expected injected PDO/service failure diagnostics remain visible in retained stderr.

Observed runtime: Windows PHP 8.4.24 CLI ZTS Visual C++ 2022 x64, libxml 2.11.9,
no loaded INI in default or `-n` profiles; Node v24.22.0, PowerShell 7.6.5 and Git Bash
5.3.15. One hardlink rejection case ran; symlink creation was unavailable. These are
local filesystem results, not native Linux permissions or real MySQL integration.
Commands, versions, per-suite exits/stdout/stderr/durations, reproduction captures,
lint, migration and documentation checks are retained outside Git in this desktop
task's `m6c-cli-profile` evidence directory. The full run uses `php -n tests/*Test.php`
one standalone file at a time; only execution children select the capable profile.
The focused development runs also caught a shared-temp cleanup assertion race and
an overly short 200 ms startup/timeout expectation; the tests now isolate their temp
root and use the existing one-second controlled timeout budget. Failed development
records are retained separately from successful focused/full results.

Correction-only inventory: **seven files; zero production changes**.

| Change | File | Purpose |
| --- | --- | --- |
| Modified | `tests/WebsitePlatformM6CCliTest.php` | Select capable execution profile; validate real traces before capture cleanup. |
| Modified | `tests/support/WebsitePlatformM6CExecutionChild.php` | Capability probe and prerequisite failure before fixture loading. |
| Added | `tests/support/WebsitePlatformM6CExecutionProfile.php` | Selected-binary, bounded process-only fixture profile and dependency checks. |
| Added | `tests/support/WebsitePlatformM6CCliProcess.php` | Bounded file captures, sanitized failure retention and owned cleanup. |
| Added | `tests/support/WebsitePlatformM6CCliProcessChild.php` | Isolated deterministic process faults and profile inspection. |
| Added | `tests/WebsitePlatformM6CCliProcessTest.php` | Capability, diagnostic, bounds, cleanup and profile regressions. |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` | Operator evidence, correction, actual local results and next deliverable. |

All 25 migrations and their integrity pins remain byte-identical to the prior head.
Migration 025 remains applied to working staging; its canonical SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
The backup/download checkpoint remains **operator-reported COMPLETE** with both
copies and the separate receipt retained. Restore remains **NOT EXECUTED**. This
task neither accesses backups nor independently verifies their contents.

### Next Linux check and separate native harness

A later authorized Linux rerun must run the corrected **CLI suite first**, followed
by its new CLI process regressions. Preserve actual PHP/profile, exits, output,
optional CGI/link coverage and assertion totals; Windows and Linux totals need not
match. The eight passing Linux suites remain recorded at `d4fb3d6`. Further suites
should follow changes to shared fixtures, rather than automatically rerunning them
because a documentation commit changed HEAD. This correction changes no shared
M6C/fake-database fixture. No server rerun is performed or authorized here.

The operator inspected the reviewed checkout and found **no executable M6C native
integration harness**, an existing M6B harness with synthetic build dependencies,
and absent host protected-worker configuration. These are missing deliverables,
not a failed native application run. `RunM6BMySql.sh` is not a substitute M6C test;
no test is improvised against `ubo_staging` and no worker configuration is installed.

The next substantive implementation is a separately reviewed M6C native harness:
disposable test database/private artifacts, isolated protected-worker configuration
and exact checkout, non-root worker under actual ownership rules, **same-process
ordinary claim/construction**, bounded cross-process recovery/fault cases, and
ownership-scoped cleanup with retained diagnostics. This correction implements no
native harness or infrastructure expansion and opens no planning-only PR. Earlier
Docker/WSL proposals are not installation authorization.

M6B remains formally closed; M6C remains IMPLEMENTED LOCALLY / REVIEW REQUIRED with
native validation pending. M6D–M6G and Narrator remain untouched. PR #128 stays open,
non-draft and unmerged with auto-merge disabled. No review conversations are resolved
by this correction. Zero server, live database, SQL/migration, backup/restore,
deployment, installation, container, provider or production operations occur here.

## Execution-fixture prerequisite and initialization review corrections

This test/helper/documentation-only correction starts at
`8f04073325b3e01a1851aa0faaa4493fec3d059f` on the existing PR #128 branch and addresses
the two completed-review findings:

- [4305441338 — Normalize a missing disable_classes directive](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4305441338).
- [4305441342 — Check base64_decode before constructing the fixture](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4305441342).

The reviewer's PHP 8.5.7-dev reproduction is reviewer-supplied evidence. It is not a
PHP version observed by this desktop task or a claim of full application support.
The earlier Windows 70-suite/973-M6C-assertion results above describe the prior
correction; they are not reused as fresh validation for these findings.

### Operator-reported review-gate stop

The latest droplet task **STOPPED at the completed-review gate**. It performed no
fetch or checkout, and neither requested suite ran. It produced no new tested SHA,
assertion total, child-profile result or lint result. No runtime resources were
created. Deployed invariants were not independently rechecked after that stop.
This is a blocked validation attempt, not another executed CLI test failure.

Operator-reported evidence file:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-cli-profile-20261018T235522Z-blocked/M6C-LINUX-CLI-PROFILE-VERIFICATION.txt`.
Reported SHA-256:
`02c376cea15cab66b7958ffe1e4112f0b15af7eead2952332ba9d4991cbb7e77`.
The desktop task does not retrieve this file or independently verify its checksum.

The earlier Linux run at `d4fb3d696fafabc93923915c4b294321d630d21b` remains separate:
eight passing suites / **682 assertions**, the CLI failure at exit 255 without a
final assertion total, and 241 passing PHP lint results. Its original child fatal
was not retained and is not reconstructed. The blocked run supplies no replacement
execution evidence for any of those results.

### Optional directive and class-capability coverage

The prior-head source snapshot reproduces the unavailable-directive failure under
strict types on local PHP 8.4.24: a source-local namespace control returns `false`
from `ini_get('disable_classes')`, and the original `explode` path exits 255 with a
TypeError. This is a controlled false-return reproduction, not native PHP 8.5
execution. An initial external reproduction harness accidentally omitted the eval
source's strict declaration, so that child exited 0; the resulting failed
assertion/evidence remains retained. The corrected strict-types harness reproduced
the failure and exited 0 after checking that actual child result.

The small pure `m6ExecutionDisabledClasses(string|false)` boundary maps `false` to an
empty list, trims and lowercases names, and removes empty entries. The actual
preflight continues to check `class_exists` for every required class. An unavailable
directive therefore does not imply that the class exists. The bounded nearby audit
also guards unavailable `memory_limit` before matching it and unavailable/empty
`extension_dir` before trimming it for the existing-module fallback. No production
configuration or general PHP compatibility framework changes.

Class-negative regressions first establish a capable baseline using the selected
child profile, then inspect whether `disable_classes` is available and whether it
actually removed known internal PDO/DOM methods. Only a supported,
demonstrably effective directive is credited with real process-level disabled-class
coverage. Separately, an isolated strict source copy exercises false optional
directives and controlled missing PDO/DOM class decisions while retaining actual
`class_exists` behavior for other classes. This coverage remains available when PHP
ignores or removes the directive. These controls exist only in the deterministic
test fault child; no production PDO replacements or worker/configuration bypasses
are introduced.

On this Windows build both actual PDO/DOM directive groups were effective and ran,
alongside both controlled false-directive/missing-class groups. A controlled
false-directive capability probe with no denied classes also reported the real
capable class state without constructing the fixture.

The local executable/package/location checks found PHP 8.4.24 and no PHP 8.5
installation in the checked PATH, WinGet PHP package records or common PHP locations.
No PHP version was installed or downloaded. The reviewer's PHP 8.5.7-dev result stays
reviewer evidence; the controlled local case does not become a native version test.

### Fixture prerequisites and partial initialization cleanup

With the prior-head source snapshots and actual process-only
`-d disable_functions=base64_decode`, the capability probe exits 0 and incorrectly
reports an empty missing list. The normal constructor child exits 255 on the missing
function, emits no execution trace and leaves one actual tracked fixture root.
The reproduction confines that root to its own disposable temporary directory; the
existing fixture cleanup removes that exact owned root and leaves that directory
empty. Actual exits and sanitized failure evidence are retained before raw-capture
cleanup. The original sanitizer redacts the unrecognized function identity; the
corrected bounded allowlist separately preserves the exact `base64_decode` identity
from the captured pre-fix diagnostic. No original Linux fatal is inferred from this
local reproduction.

`base64_decode` now belongs to the actual preflight and diagnostic allowlist. A bounded
comparison of the real fixture constructor/request path also found nine concrete
function dependencies: `preg_match_all`, `preg_replace`, `bin2hex`, `hash_equals`,
`clearstatcache`, `lstat`, `is_link`, `fstat` and `feof`. They are checked before loading
fixture classes and are accepted as exact safe diagnostic identities. The capable
profile still carries the parent's disabled-function restriction; it does not
re-enable a function, replace the fixture image or install a module.

The execution child now registers cleanup before construction or request preparation.
A nullable fixture reference and a separate readiness flag prevent execution-trace
generation until setup has returned and completed. The shutdown callback always
calls the existing static tracked-root cleanup in `finally`, including when the
constructor never returns an instance or trace generation throws. It neither searches
for roots nor follows unknown paths. The shared fixture and its deletion mechanism
remain unchanged. A controlled constructor fault is injected immediately after the
fixture tracks its owned root, and a separate trace fault tests the `finally` path;
both controls are confined to the source-copy test child.

After correction, the actual selected-parent `base64_decode` restriction reaches the
nested capability probe, which exits 78 and lists the missing function. A normal
execution case also exits 78, retains the named prerequisite diagnostic and emits
no execution trace. Separate test-only observations confirm all five fixture,
fake-database, database, build-service and runtime classes are unloaded, with no
connection or tracked roots; the owned temporary directory is unchanged. No fixture,
request, claim, candidate or artifact is created. These observations are not
substituted execution-trace counters. All 13 actual disabled-function cases pass,
covering the ten added functions and existing fsync/JSON-encoding/stderr-writing
requirements.

The controlled constructor fault exits 255 before returning an instance or setting
trace readiness. The separate trace-generation fault exits 255 after both are set.
In both cases the observer sees the real fake-database connection and one existing
child-owned tracked root before cleanup; the parent verifies that exact root is
absent after exit. Both preserve the controlled undefined-function diagnostic and
actual exit without an execution trace. Retained sanitized failure evidence is
checked before each intentional negative case consumes only its own evidence.

The callback cannot clean after a forcibly killed process that executes no shutdown
handlers; that is not claimed by these controlled PHP-failure cases. Private file
modes, bounded output/time limits and sanitized evidence retention before raw-capture
cleanup remain in force. No zero-effect execution trace substitutes for incomplete
initialization or a missing trace.

### Fresh local validation and correction-only inventory

The corrected CLI and process suites ran individually with exit 0: **207 CLI
assertions** and **212 process assertions**. The CLI retains revoked,
builder-unavailable, builder-mismatch and no-work execution cases, deliberate no-INI
help/argument/runtime denials and confinement of valid work arguments to isolated
children. The process suite checks the normalization inputs, actual and controlled
class mechanisms, all 13 disabled-function cases, selected-parent propagation,
partial construction and trace-failure cleanup, and the existing diagnostic bounds.
The final focused process run includes the selected-child baseline correction.

The fresh standalone run passes all **ten M6C suites / 1,100 assertions**: admission
58, artifact 45, bounds 29, CLI process 212, CLI 207, execution 216, integration 54,
recovery 135, scope 53 and security 91. One actual CGI denial and one Windows hardlink
case execute; symlink creation is unavailable, so one of two local link cases runs.
These are actual local processes and filesystem operations with fake SQL. Controlled
false-directive/class decisions and injected constructor/trace faults are separately
identified; no native PHP 8.5 or Linux rerun is claimed.

All **245 PHP files** pass `php -n -l`. Three script checks pass: Node `--check` for
`tests/WebsitePlatformM5CBrowser.cjs`, Bash `-n` for `tests/RunM6BMySql.sh`, and
PowerShell `Parser.ParseFile` for `tests/RunM6BMySql.ps1`. These are syntax checks,
not a native harness or browser run. Observed versions are Windows PHP 8.4.24 CLI
ZTS Visual C++ 2022 x64, Node v24.22.0, PowerShell 7.6.5, Git Bash 5.3.15 and Git
2.56.0.windows.2. The available standalone set runs one `php -n tests/*Test.php`
file per process; only its execution children select the capable profile.
Commands, versions, exits, per-suite stdout/stderr/durations, before/after evidence
and integrity checks are retained outside Git in `m6c-bootstrap-review`.

The full available set completes **70 standalone suites, all exit 0; zero failures**,
with 1,169.402 seconds of summed suite duration. Eleven stderr captures retain
expected simulated-fault diagnostics.
The unchanged synthetic launcher passes 2,131 assertions across 161 isolated Bash
scenarios, 47 mount-inspection cases and 27 no-ctype port/type cases per mount form,
using three PHP profiles and fake commands only. Its final run takes 1,036.1 seconds;
it executes no Linux/container/MySQL harness. Documentation checks enumerate 13
Markdown links, verify all five internal anchors and two balanced fence markers,
and confirm the six-file inventory and current/historical status requirements.
At document preparation, working-diff and staged five-file PHP diff checks pass.
Final six-file staged and committed diff results will be recorded in the external
`SUMMARY.txt` with the commit evidence.

Correction-only inventory: **six modified files; zero production changes**.

| File | Purpose |
| --- | --- |
| `tests/support/WebsitePlatformM6CExecutionProfile.php` | Normalize optional directives; check concrete fixture dependencies before loading. |
| `tests/support/WebsitePlatformM6CExecutionChild.php` | Register owned-root cleanup before construction; gate traces on completed setup. |
| `tests/support/WebsitePlatformM6CCliProcess.php` | Allowlist the exact added prerequisite identities in bounded diagnostics. |
| `tests/support/WebsitePlatformM6CCliProcessChild.php` | Isolated directive/capability observations and controlled initialization/trace faults. |
| `tests/WebsitePlatformM6CCliProcessTest.php` | Version-aware class checks, function propagation and partial-initialization cleanup regressions. |
| `docs/sprint-8.8-m6c-local-implementation.md` | Both findings, preserved operator evidence, correction and fresh validation. |

The main CLI suite, shared M6C fixture and fake-database implementations are unchanged.

### Preserved state and later validation

The exact six-file diff contains no production, worker/runtime, build-service,
renderer, store, resource-limit, shared-fixture or main-CLI-test change. All 26 tracked
SQL files retain their raw hashes from this task's starting snapshot. The canonical
contents of all 25 migrations match the M6C baseline
`7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45` using the existing line-ending normalization;
no migration or integrity pin was edited. Migration 025's canonical hash matches the
value below. No applied SQL or deployed invariant is changed or remotely rechecked.

Migration 025 remains operator-reported applied to working staging, with canonical
SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
The backup/download checkpoint remains **operator-reported COMPLETE**: both backup
copies and the separate confirmation receipt remain retained. Restore remains
**NOT EXECUTED**. This task does not retrieve, recreate, inspect, restore or delete
backups, and does not independently recheck deployed invariants.

A later authorized Linux check must run the corrected **CLI suite first**, then the
CLI process regressions, retaining its own actual profile, exit, diagnostic and
assertion evidence. That rerun is not performed here. The separately reviewed M6C
native harness remains a missing deliverable; it requires a disposable database and
private artifacts, protected configuration and exact checkout, a non-root worker,
same-process ordinary claim/construction, bounded cross-process recovery/fault cases,
and owned cleanup with retained diagnostics. This correction implements no native
harness, runs no M6B harness as a substitute, and provisions no worker environment.

At commit preparation, both original findings are verified locally. Post-push
responses/resolutions and the observed exact-new-head review state are recorded in
PR #128 and the external `SUMMARY.txt`; this pre-commit record does not claim an
unobserved clean review. Only the two named conversations are eligible for resolution.

M6B remains formally closed; M6C remains IMPLEMENTED LOCALLY / REVIEW REQUIRED with
native validation pending. M6D–M6G and Narrator remain untouched. PR #128 must remain
open, non-draft and unmerged with auto-merge disabled. This correction authorizes zero
server, live SQL/database, migration, backup/restore, deployment, installation,
container, provider or Narrator actions.

## Execution-fixture cleanup prerequisites

This test/helper/documentation-only correction starts at
`e067f05bd16ca41a46a1dc174d6471f4c826f5b8` on the existing PR #128 branch and addresses
[4309456808 — Preflight basename before constructing the fixture](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4309456808).
The reviewer reports that a selected child profile disabling `basename` passes
preflight, constructs the fixture and emits an execution trace, then fails during
owned-root cleanup with exit 255 and leaves its root behind. Presence of a trace,
process exit and cleanup outcome are separate observations.

The earlier local results remain attached to their own corrections and SHAs. In
particular, the prior 70-suite/1,100-M6C-assertion record is historical, not fresh
validation for this finding. The Linux run at `d4fb3d6` still records eight passing
suites / 682 assertions and the separate CLI exit-255 failure without its final
assertion count or retained original fatal diagnostic. The subsequent
[review-gate stop](#operator-reported-review-gate-stop) performed no fetch, checkout
or tests and produced no new tested SHA, assertion, child-profile or lint result;
it created no runtime resources and did not independently recheck deployed
invariants. Its operator-supplied report path/checksum remain recorded above and
are not retrieved or independently verified here. This basename finding is not
attributed to a droplet execution.

### Basename reproduction and corrected admission

The actual prior-head isolated child reproduces the finding on local Windows PHP
8.4.24 under process-only `-d disable_functions=basename`. Its capability-only probe
exits 0 and incorrectly reports an empty missing list. The normal `no-work` child
emits a real `M6C_EXECUTION_TRACE`, then exits 255 with an undefined-`basename` fatal.
One owned fixture root remains, containing `work`, `sealed`, `uploads` and
`uploads/approved.png`: five observed residual paths including the root. Trace
presence does not make that process or cleanup successful.

The reproduction confines all child state to a private disposable parent directory.
Actual exit, bounded stdout/stderr, trace, observed initialization state and residual
paths are captured in external `m6c-cleanup-prerequisites/basename-before.json`
before cleanup. The capable parent uses the established fixture cleanup for that
exact observed owned root, verifies the parent is empty and removes the empty
parent. No other test root or retained failure evidence is swept. This is local
Windows process/filesystem evidence, not a native Linux reproduction.

With the correction, the actual basename-disabled capability probe exits 78 and
reports `missing: [basename]`. The normal execution child also exits 78 with the
named prerequisite diagnostic and no execution trace. Its separate test observation
records all five fixture/database/service/runtime classes unloaded, no connection
or tracked roots, and false fixture-returned/trace-ready states. No fixture,
request, claim, candidate or artifact is created. There are zero residual paths;
the private parent is verified empty and removed. These actual observations and
cleanup confirmation are retained with `basename-after.json`; no zero-effect
execution trace is substituted for missing initialization.

The selected-parent/profile forwarding regressions pass for both `basename` and
`base64_decode`: their actual nested capability and normal-execution children retain
the restriction and exit 78 before fixture effects. Normal capable-profile CLI
execution still passes for revoked, builder-unavailable, builder-mismatch and
no-work. Deliberate no-INI help, invalid-argument and isolated runtime-denial cases
remain; valid work arguments never reach operational host configuration or queues.

### Bounded initialization, trace and cleanup dependency inventory

The audit follows initialization, shutdown registration, trace and tracked-root
cleanup in the existing helpers/fixture, including concrete immediate initialization
callees. It adds **33 function checks**: 12 direct lifecycle/cleanup functions, 11
bootstrap/reporting functions and ten immediate initialization dependencies. Each
added name is also an exact allowed diagnostic identity. This bounded audit does
not enumerate all recursive validators, fake-SQL behavior or transitive production
dependencies; early cleanup still handles other constructor failures.

| Path | Concrete dependencies | Admission and coverage boundary |
| --- | --- | --- |
| Optional-directive normalization and class inspection | `ini_get`, `explode`, `trim`, `strtolower`, `array_map`, `array_filter`, `array_values`, `class_exists`, `in_array`, `defined` | Newly checked before normalization or class inspection can call them. The existing false/empty/mixed-case directive normalization and real class-existence checks remain. |
| Capability metadata | `php_ini_loaded_file`, `ini_get` | Checked before fixture loading; metadata fields use `null` when the corresponding accessor is unavailable. |
| Direct fixture initialization/composition | `dirname`, `sys_get_temp_dir`, `array_replace`, `strlen`, `str_repeat`, `ucfirst`; existing `bin2hex`, `random_bytes`, `mkdir`, `base64_decode`, `file_put_contents`, `hash`, `realpath` | Concrete omissions are added; existing checks remain. Reflection construction/injection uses the already checked `ReflectionClass` and `ReflectionProperty`. |
| Immediate initialization callees | `rtrim`, `str_starts_with`, `extension_loaded`, `is_array`, `ksort`, `array_key_exists`, `array_diff`, `array_keys`, `is_string`, `min` | Added for concrete calls reached through store construction, canonical JSON, draft composition replacement and runtime identity/contract/CSS reads. This does not claim an exhaustive audit of those production classes. |
| Early shutdown registration | `register_shutdown_function` | Newly checked before fixture classes load; registration still precedes construction and request preparation. |
| Trace projection and serialization | `array_intersect_key`, `array_flip`, `substr`, `strlen`, `count`; `array_map`, `array_values` and event-filter `array_filter`; existing `json_encode`, `fwrite` | All are checked before side effects. `RecursiveDirectoryIterator`, `RecursiveIteratorIterator` and `FilesystemIterator` remain checked classes; trace traversal uses their constructors, iterator protocol and `getPathname`. |
| Root ownership/path admission | `basename`, `dirname`, `sys_get_temp_dir`, `realpath`, `preg_match` | The first three are added; existing realpath/regular-expression checks remain. Cleanup still requires the tracked random root under the exact current temporary parent. |
| Owned traversal and mutation | `DirectoryIterator`, its constructor, iterator protocol, `isDot`, `getPathname`, `isLink`, `isDir`; `chmod`, `unlink`, `rmdir` | Existing class/function checks remain. Methods are covered through the SPL class and actual cleanup paths; they are not independent `disable_functions` names. Link handling, ownership checks and deletion scope are unchanged. |
| Actual entry source loading | `dirname`, `file_get_contents`, `preg_replace` | `dirname` is added; the existing bounded source-boundary checks and other prerequisites remain. |

The existing PDO/PDOStatement/PDOException, date, DOM/libxml and JSON class/constant
checks remain for the loaded fixture path. Fake SQL still needs PDO types and
constants, not a PDO database driver or live connection.

The required-function scan now runs first and uses only `function_exists` and PHP
language constructs. Missing normalization/class-inspection primitives return the
function report before those primitives are called. An unrelated missing fixture
function still permits the safe class/constant scan, preserving a complete combined
module report for the selected profile's existing parent-module forwarding. Rejection
text uses language-level iteration/concatenation, removing its `implode` dependency.
When JSON encoding or stderr writing is disabled, the existing language-level output
fallback still reports the prerequisite failure. No disabled function is restored
or replaced.

`function_exists` itself and the PHP engine language constructs are the explicit
bootstrap requirement. Disabling that probe can fail before fixture loading, but
is outside the prerequisite-status-78 guarantee. The capable parent controller is
also required for profile selection, bounded capture and safe cleanup; child-only
restriction tests do not disable the controller's dependencies. This boundary adds
no PHP configuration framework, production selector or PDO driver requirement.

The table-driven regression individually restricts all eight cleanup functions
(`basename`, `dirname`, `sys_get_temp_dir`, `realpath`, `preg_match`, `chmod`, `unlink`,
`rmdir`) and separately restricts `register_shutdown_function`. Existing base64,
fsync, JSON-encoding and stderr-writing restrictions remain individual cases.
Additional grouped restrictions require the exact complete missing set in both
capability and normal-execution checks:

- Initialization, 22 names: `array_replace`, `str_repeat`, `ucfirst`, `rtrim`,
  `str_starts_with`, `extension_loaded`, `is_array`, `ksort`, `array_key_exists`,
  `array_diff`, `array_keys`, `is_string`, `min`, `preg_match_all`, `preg_replace`,
  `bin2hex`, `hash_equals`, `clearstatcache`, `lstat`, `is_link`, `fstat`, `feof`.
- Trace projection, five names: `array_intersect_key`, `array_flip`, `substr`,
  `strlen`, `count`.
- Bootstrap/reporting, the 11 names listed in the first two inventory rows.

The parent controller remains capable. When disabled bootstrap or shutdown
primitives also prevent the test observer from operating, direct-child cases
record actual exit, diagnostic, trace and filesystem observations only; they do
not claim unobserved class state. Existing version-aware PDO/DOM negative tests
retain their real-versus-controlled distinction. Actual constructor, trace and
cleanup paths exercise SPL methods through the admitted classes.
A combined case applies a real child-only `ctype_digit` restriction and controlled
PDO/PDOStatement absence together, checking that the report retains both function
and class identities without initializing the fixture.

All listed individual and grouped restriction cases pass in the corrected process
suite. Capability probes identify the exact required sets; normal rejection retains
exit 78 and the named diagnostics without an execution trace or new fixture roots.
The combined real-disabled-ctype/controlled-PDO case reports `ctype_digit`, `PDO`
and `PDOStatement` together, with observed unloaded fixture state. The actual
child-only `function_exists` restriction instead exits 255 with its named fatal,
no execution trace and no new root, confirming the documented bootstrap boundary.

The constructor-failure and trace-generation-failure regressions retain their actual
255 exits and missing traces while removing only the root observed as owned by that
child. A further controlled fault emits a real parseable execution trace, then
exits 255; validation rejects it despite the trace, retains the fatal and verifies
owned cleanup. No partial initialization is replaced with invented success counters.

Missing/malformed traces, nonzero exit, privacy redaction, timeout, output overflow
and secondary evidence-write failure checks pass. Each phase also retains a separate
real failed-child evidence record and an unrelated owned sentinel root throughout
the child cases, then verifies both hashes are unchanged. Only those controller-owned
sentinels are removed after verification. Successful and expected-negative cases
leave no accumulating captures or fixture data; unexpected retained failure evidence
is not swept. Private modes, bounded capture and failure-before-capture-cleanup
ordering remain intact.

The shared fixture is unchanged. Early shutdown registration, `finally` cleanup,
ownership/path/symlink checks, false/empty directive normalization, version-aware
class tests and base64 admission all remain. The controlled PHP-failure cases do
not claim cleanup after a forced process termination that runs no shutdown handler.

### Fresh validation and exact correction inventory

The individually run CLI suite passes **207 assertions**, exit 0, in 32.711 seconds;
the corrected process suite passes **295 assertions**, exit 0, in 24.641 seconds.
All three existing
script syntax checks pass: Node `--check` for `tests/WebsitePlatformM5CBrowser.cjs`,
Bash `-n` for `tests/RunM6BMySql.sh`, and PowerShell `Parser.ParseFile` for
`tests/RunM6BMySql.ps1`. These are syntax checks, not browser or native-harness runs.
Fresh observed versions are Windows PHP 8.4.24 CLI ZTS Visual C++ 2022 x64, Node
v24.22.0, PowerShell 7.6.5, Git 2.56.0.windows.2 and Git Bash 5.3.15.

Expanded sequential regression coverage exposed the actual Windows controller's
10-second PHP execution limit; the fatal diagnostic is retained with the failed
development runs. The process suite now schedules five explicit phases: process,
classes, cleanup, capabilities and fixtures. Each uses the same selected PHP
profile and its 10-second execution bound; a single aggregate 30-second parent
wall deadline covers all phases and final cleanup. This preserves the original
bounds while scheduling the added cases. Successful outer cleanup must leave the
private parent empty and remove it, with a final deadline check after cleanup.

All **245 tracked PHP files** pass lint, including refreshed checks of the final
edited code. The actual local process/filesystem runs are distinct from the labeled
source-copy class/fault controls; no native Linux or other PHP-version run is
claimed. Commands, versions, per-suite exits/stdout/stderr, before/after captures,
failed development runs and successful final runs are retained outside Git in
`m6c-cleanup-prerequisites`.

Fresh standalone validation with final affected-suite rechecks passes **ten M6C
suites / 1,183 assertions**: admission 58, artifact 45, bounds 29, CLI process 295,
CLI 207, execution 216, integration 54, recovery 135, scope 53 and security 91.
The final standalone CLI/process rechecks exit 0 in 25.084/26.739 seconds respectively.
One actual CGI invocation and 32 isolated valid-argument denials run. Security executes
one Windows hardlink case; symlink creation is unavailable, so one of two local link
cases runs. The earlier 291-assertion development passes precede the combined
function/class report regression and are retained separately from the final 295.

The suites run as individual `php -n tests/<suite>Test.php` commands; the focused
commands are `php -n tests/WebsitePlatformM6CCliTest.php` and
`php -n tests/WebsitePlatformM6CCliProcessTest.php`. Execution children select the
capable bounded profile. Lint uses `php -n -l` for each tracked PHP file. No installed
INI or PHP binary is changed.

The full available set completes **70 standalone suites, all exit 0; zero failures**,
with final affected-suite rechecks retained in the combined results. The unchanged
synthetic launcher exits 0 in 1,431.728 seconds and passes 2,131 assertions across
161 isolated Bash scenarios, 47 mount cases and 27 no-ctype port/type cases per mount
form using three PHP profiles. It uses fake commands only; no Linux/container/MySQL
harness is executed. Documentation checks enumerate 16 Markdown links, verify all
seven internal anchors and two balanced fence markers, and confirm preserved
history/status and the exact six-file inventory. There are no local file links to
resolve. The working diff check passes. Final staged and committed diff results
will be recorded with the commit evidence in the external `SUMMARY.txt`.

Correction-only inventory: **six modified files; zero production changes**.

| File | Purpose |
| --- | --- |
| `tests/support/WebsitePlatformM6CExecutionProfile.php` | Check bounded cleanup/initialization/bootstrap dependencies while retaining complete safe class/module reporting. |
| `tests/support/WebsitePlatformM6CExecutionChild.php` | Reject missing prerequisites before effects; make rejection/metadata reporting safe when helper primitives are unavailable. |
| `tests/support/WebsitePlatformM6CCliProcess.php` | Allowlist the exact added diagnostic identities without changing capture/resource limits. |
| `tests/support/WebsitePlatformM6CCliProcessChild.php` | Isolated observations and trace-then-fatal/combined-capability controls. |
| `tests/WebsitePlatformM6CCliProcessTest.php` | Bounded phased cleanup/bootstrap coverage, profile propagation and unrelated-state preservation. |
| `docs/sprint-8.8-m6c-local-implementation.md` | Finding, bounded audit, before/after evidence, actual validation and preserved history. |

Production code, operational worker/runtime, shared fixtures, the main CLI suite and
resource limits remain unchanged. All 26 tracked SQL files retain their raw hashes
from this task's starting snapshot. The canonical contents of all 25 migrations
match baseline `7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45`; migration 025's required
canonical hash and the unchanged integrity pins are checked. No SQL is edited or
reapplied.

### Preserved state, review and later Linux check

Migration 025 remains operator-reported applied to working staging, with canonical
SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
The backup/download checkpoint remains **operator-reported COMPLETE**; the server
and Windows copies and separate confirmation receipt remain retained. Restore
remains **NOT EXECUTED**. This task does not retrieve, recreate, restore or delete
backups, or independently recheck deployed invariants.

The M6C native integration harness remains a separate missing deliverable. This
correction neither implements it nor runs the M6B harness as its substitute, and
does not provision a protected worker environment. A later authorized Linux run
must execute the corrected **CLI suite first**, then the process regressions and
retain its own actual profile, exits, diagnostics and assertions. No Linux/server
rerun occurs in this desktop correction.

The post-push reply/resolution for finding 4309456808 and observed exact-new-head
review state will be recorded in PR #128 and the external `SUMMARY.txt`. Only this
conversation is eligible for resolution after verifying the fix and checking for a
substantive rebuttal; no unobserved clean review is claimed in this pre-commit record.

M6B remains formally closed; M6C remains IMPLEMENTED LOCALLY / REVIEW REQUIRED with
native validation pending. M6D–M6G and Narrator remain untouched. PR #128 remains
open, non-draft and unmerged with auto-merge disabled. Scope remains zero server,
live SQL/database, migration, backup/restore, deployment, installation, container,
provider or Narrator operations.

## Supporting fake-database initialization prerequisites

This test/helper/documentation correction starts at
`b8c20c479bdbf29f4659add30d97e9b57e01cac9` and addresses
[4319523117 — Add all fixture-init functions to the prerequisite scan](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4319523117).
It independently reproduces the three reported restrictions on local Windows PHP
8.4.24. This is not a new droplet result. The preceding 70-suite/1,183-M6C-assertion
record remains historical evidence for its own correction.

### Individual reproduction and correction

Each process-only restriction runs separately through the existing isolated
execution child/observer in a private disposable parent. The capable controller
retains actual capability result, exit, bounded stdout/stderr, loaded classes,
connection, constructor/trace readiness and owned paths before any parent cleanup.

| Disabled function | Before capability / execution exits | Actual before bootstrap observation and diagnostic | Corrected capability / execution exits |
| --- | --- | --- | --- |
| `str_contains` | 0 / 255 | All five observed bootstrap classes loaded; fake `WebsitePlatformM3ServiceDatabase` connection initialized; read error wrapped as `SiteServiceException`. | 78 / 78, missing `str_contains` |
| `usort` | 0 / 255 | All five classes loaded; fake M3 connection initialized; transaction error wrapped as `SiteServiceException`. | 78 / 78, missing `usort` |
| `str_replace` | 0 / 255 | All five classes loaded; connection still null; actual undefined-function fatal in `ComponentRegistry::component`. | 78 / 78, missing `str_replace` |

Every before case reports constructor-returned false and trace-ready false, no
execution trace, no tracked root and zero residual paths. None of these three
cases produces an orphan; no parent root deletion is needed. Each empty disposable
parent is verified and removed. A fake connection is an in-process SQL double,
not live MySQL. The first two original errors are wrapped by the existing service;
their captured output is not misrepresented as a retained undefined-function fatal.

After correction, all three cases report the five observed fixture/database/service/
runtime classes unloaded, connection and roots null, and constructor-returned/
trace-ready false. There is no request, claim, root, journal, artifact or execution
trace. The process regression independently forwards each of these three restrictions
through the selected parent profile, checks the exact one-function capability list,
then checks normal exit 78 and observed no-bootstrap state. Restrictions stay active.

### Bounded supporting-path inventory

The source audit starts with `WebsitePlatformM6CFixture::__construct`, follows
`WebsitePlatformM6BDatabase::fixture` into M5A/M3 construction and wiring, then the
`initialize_new` composition operation, stored snapshot hashing, M6C composition
replacement, store/runtime identity setup, and the common `requestBuild` preparation
used by the three non-empty execution scenarios. It ends at trace readiness and the
existing trace/owned-cleanup paths. It includes reached SQL-double dispatch, fetch,
sort, placeholder and LIMIT branches, not just the top-level fixture calls.

The following **24 additions** were identified from those call sites, independently
of the preflight list. Twenty-one extend the three reviewer-named dependencies. Each
is added to both preflight and the bounded diagnostic identity allowlist. Every row
also has a fixed source-text call-site assertion and a diagnostic identity assertion;
normal test execution does not scan for or load arbitrary application code.

| Function | Actual reached call site | Preflight / regression mechanism |
| --- | --- | --- |
| `str_contains` | M3 `executeSql` dispatch, then M5A/M6B SQL routing | Added; independent forwarded capability and normal rejection; group below |
| `usort` | M3 `hashPages`/`hashSections`/`hashAssets`; composition ordering | Added; independent forwarded capability and normal rejection; group below |
| `str_replace` | `ComponentRegistry::component` variant labels; M5A SQL classification | Added; independent forwarded capability and normal rejection; group below |
| `array_shift` | M3 statement `fetch`/`fetchColumn` | Added; exact-list grouped rejection |
| `array_unique` | M3/M5A/M6B `prepare` placeholder uniqueness | Added; exact-list grouped rejection |
| `array_slice` | M6B `executeSql` LIMIT branch during request history lookup | Added; exact-list grouped rejection |
| `ucwords`, `array_fill_keys` | `ComponentRegistry::component` and `manifest` | Added; exact-list grouped rejection |
| `array_count_values`, `array_column` | `SiteAuthoringCatalog::forActor`; authorization role projection and page types | Added; exact-list grouped rejection |
| `array_intersect` | `SiteAuthorizationPolicy::actorContext` role membership | Added; exact-list grouped rejection |
| `is_int`, `is_bool` | `ComponentSchemaValidator::validate`; build identity validation | Added; exact-list grouped rejection |
| `strtoupper` | `ThemeRegistry::normalize` color normalization | Added; exact-list grouped rejection |
| `method_exists` | `SiteRevisionManager::lockMutableRevisionForComposition` and snapshot application | Added; exact-list grouped rejection |
| `sort` | `SiteBuildService::actors` lock ordering during request preparation | Added; exact-list grouped rejection |
| `strcmp` | `SiteBuildContract::input` ordered asset-digest validation | Added; exact-list grouped rejection |
| `is_scalar` | `SitePublicRenderContext::fields` projection | Added; exact-list grouped rejection |
| `str_ends_with` | `SiteArtifactFiles::path` during route/asset admission | Added; exact-list grouped rejection |
| `chr`, `ord` | `SiteServiceSupport::uuidV4` job/release identities | Added; exact-list grouped rejection |
| `implode` | `SiteBuildStore::insert` request persistence | Added; exact-list grouped rejection |
| `array_key_first`, `array_push` | `SiteCompositionEditor::draftSelection` and `assertAuthorable` during `initialize_new` | Added; exact-list grouped rejection |

Separate external before-admission-fix child probes confirm that each of the twenty-one
additional omissions stops construction or common request preparation before trace
readiness. Request-stage cases can return the fixture and track a root first; the
unchanged child cleanup removes those exact roots. Actual observations are retained,
without assigning zero-effect counters to partial initialization. The observer's
normal-return sentinel exit 99 is recorded as such where no-work does not reach a
particular request dependency; the subsequent revoked case establishes its use.

The added group truly disables all 24 names together. It requires the capability
report to equal the entire explicit inventory, not merely to fail because one member
is missing; its normal child also rejects before bootstrap. The three named cases
remain independent. Existing 22-function initialization, five-function trace and
11-function bootstrap groups remain additional coverage. Grouped after-fix results
are not claimed as individual after-fix processes for every added function.

Previously admitted dependencies are traced through the same path: JSON/hash and
canonicalization (`is_array`, `array_is_list`, `array_map`, `ksort`), schema and SQL
normalization (`is_string`, `array_keys`, `array_diff`, `array_key_exists`, `count`,
`trim`, `strtolower`, `preg_match`, `preg_match_all`, `preg_replace`, `array_values`,
`array_filter`, `in_array`), composition/identity (`array_replace`, `str_repeat`,
`ucfirst`, `bin2hex`, `random_bytes`, `hash_equals`), and the file/trace/cleanup
inventory in the preceding section. Callback review includes the role `is_string`
filter, canonicalization/JSON callbacks, SQL predicate/order closures and trace
projection closures; these use admitted functions or language operators.

The current schemas' optional `mb_strlen` path retains its existing `strlen`
fallback. `is_float` numeric-schema branches, expiry-time branches for non-null
rights, unrelated editing operations and unreached SQL branches are outside this
fixed fixture path. Separate source/case checks do not show `is_callable` or
`array_merge` as a new pre-trace prerequisite here, so they are not added. This
inventory does not claim exhaustive transitive application compatibility.
The documented `function_exists` bootstrap limitation remains unchanged.

### Preserved behavior, fresh checks and handoff

The committed five phases, 10-second selected child profile and aggregate 30-second
deadline remain. Independent new forwarding cases run in the existing process
phase; existing base64/basename cases remain in the fixture phase. No assertion is
removed and no bound is increased. False/empty directive normalization, real versus
controlled class restrictions, mixed function/class reporting, early registration,
constructor-without-instance and trace-fault cleanup, trace-present/nonzero rejection,
sanitized evidence/privacy and primary-error preservation all remain covered.

Earlier checks during this correction passed CLI **207 assertions**, exit 0, 20.948
seconds and process **379 assertions**, exit 0, 21.765 seconds. Those checks preceded
the two final editor-path additions; refreshed final results are recorded below.
The CLI retains all four execution cases,
one actual CGI invocation and 32 isolated valid-argument denials. These are local
Windows results; no native Linux or PHP 8.5 execution is claimed.

Final focused CLI passes **207 assertions**, exit 0 in 29.995 seconds;
final focused process passes **383 assertions**, exit 0 in 24.115 seconds.
The available standalone set has **70 suites with successful fresh results**
after affected-suite rechecks. Its **10 M6C suites total 1271 assertions**.
The final standalone CLI passes in 15.656 seconds, and the serialized final
process run passes in 14.947 seconds. The unchanged synthetic launcher
regression passes in 994.791 seconds; it invokes fake commands, with no
native Docker, SQL or M6C harness execution. The initial pass is not claimed clean.

Actual invocations use the existing PHP executable with `-n` and each standalone
test path, including `tests/WebsitePlatformM6CCliTest.php` and
`tests/WebsitePlatformM6CCliProcessTest.php`. Complete executable paths, process-only
flags, versions, commands, exits, durations and stdout/stderr remain outside Git in
the `m6c-fake-db-init` evidence directory. Observed versions are PHP 8.4.24 CLI ZTS
(Visual C++ 2022 x64), Node v24.22.0, PowerShell 7.6.5,
Git 2.56.0.windows.2 and GNU Bash 5.3.15(2).

All **245 tracked PHP files** pass `php -n -l`; the three changed PHP files
were refreshed after the final source edits. All **3 existing script checks**
pass: `node --check tests/WebsitePlatformM5CBrowser.cjs`, Bash `-n` for
`tests/RunM6BMySql.sh`, and PowerShell parser validation for
`tests/RunM6BMySql.ps1`. Documentation checks pass for 18 links, eight internal
anchors, no missing local links, two balanced fence markers, historical status
evidence and the exact four-file inventory. Working, staged and committed diff
checks pass. Raw SQL hashes and canonical migration comparisons remain unchanged.

The first standalone process run, concurrent with the unchanged synthetic launcher
and lint jobs, exits 255 after 31.068 seconds: the aggregate deadline expires in
the fixtures phase. Its retained `M6CChildFailure` reports timeout, termination
requested and the actual child exit 255. This failed run remains separate evidence;
the final process recheck passes without concurrent validation jobs in 14.947
seconds. No time limit or assertion is relaxed to obtain a pass.

Correction-only inventory: four modified files:
`tests/support/WebsitePlatformM6CExecutionProfile.php`,
`tests/support/WebsitePlatformM6CCliProcess.php`,
`tests/WebsitePlatformM6CCliProcessTest.php`, and
`docs/sprint-8.8-m6c-local-implementation.md`.
Production code, execution/observer child helpers, shared database/artifact fixtures,
main CLI suite and resource limits are unchanged. All 25 migration contents and
the task-start raw hashes of all 26 tracked SQL files are checked unchanged;
migration 025's canonical SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0` and it is not reapplied.

Earlier Windows evidence stays at its own SHAs; Linux `d4fb3d6` retains eight passing
suites / 682 assertions and the separate original CLI failure. Later review-gate
stops ran no tests and supplied no new tested SHA or independent deployed-invariant
check. Backup/download remains **operator-reported COMPLETE**, both copies and the
confirmation receipt retained, restore **NOT EXECUTED**. Operator-performed removal
of two obsolete validation-user Codex releases reportedly recovered 709,808,128 bytes;
last reported root usage was 81% with 1,775,374,336 bytes available. These are historical
capacity figures, not a measurement or additional maintenance in this task.

The post-push reply/resolution and exact-new-head review observation are recorded
in PR #128 and the external `m6c-fake-db-init/SUMMARY.txt`. Only finding 4319523117's
conversation is eligible for resolution after verification and a rebuttal check.
PR #128 stays open, non-draft and unmerged with auto-merge disabled. Later authorized
Linux order remains corrected CLI first, process regressions second. The executable
M6C native integration harness remains a separate missing deliverable; it is not
implemented or provisioned here. Zero server, live SQL, migration, backup, deployment,
installation, container, provider or Narrator actions occur in this correction.

## Native harness implementation

Authoring starts from clean branch/head `96715eb35ac1ae2eb69c3a39a14ec009048128ad`.
PR #128's exact-head review was completed with no findings/unresolved findings in
[the canonical review summary](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6242505861)
before this work. The new harness changes require their own review; the earlier
clean result is not attributed to them. M6C implementation baseline remains
`7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45`.

The [native operating guide](sprint-8.8-m6c-native-validation.md) specifies executable
help/check-only/run commands, exact source/image prerequisites, separately authorized
image preparation, containment/resource/evidence/cleanup contracts, 20 authored
acceptance cases and partial B/C case mappings. All native cases remain UNEXECUTED.
The protected lane runs the actual production factory/CLI as a capability-free
non-root worker. The explicitly instrumented lane uses real PDO, builder, validator
and store with process barriers and targeted SQL faults. Synthetic launcher tests
are not native acceptance evidence. There are no production fault hooks or runtime
authorization changes.

### Newly completed operator Linux prerequisite result

Operator-tested SHA: `96715eb35ac1ae2eb69c3a39a14ec009048128ad`.
CLI: exit 0, **206 assertions**, **2.854755 seconds**, profile
`parent-ini;bounded;parent-directives`; four normal cases and 32 isolated runtime
denials passed. CGI unavailable; **zero actual CGI calls**.
Process: exit 0, **389 assertions**, **5.150861 seconds**; all five phases passed,
original deadlines unchanged. Six changed PHP files linted successfully; cleanup
completed with no surviving processes or temporary fixtures.

Operator report:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-cli-process-20261020T000240Z-1gba0qjh/M6C-LINUX-CLI-PROCESS-VERIFICATION.txt`.
Operator-supplied SHA-256:
`585cb438af58046d99717a00111d87e2474a82a805b446de78507e463a715885`.
The operator verified **26/26 manifest entries**. This desktop task did not retrieve
the report or independently verify its checksum. The focused suites were not rerun.
Earlier Linux `d4fb3d6` eight-suite/**682-assertion** results and its separate original
CLI failure remain at that SHA; later blocked tasks executed no tests. Counts are
not combined into an all-suite run at `96715eb`.

### Fresh desktop checks and retained failures

The final new synthetic launcher suite passes **292 assertions / 25 fake-command
scenarios**, exit 0 in **57.877231 seconds**. It exercises pure host/source/socket/
volume/image/resource/container/network contracts; actual launcher cached-image,
startup, cleanup and evidence functions with fake commands; JSON report boundaries;
failed/timeout/output-limited PHP children; and a real disposable local Git export
that omits ignored and deleted historical canaries while preserving the exact commit.
Ordinary test discovery cannot invoke the native entries. No real engine or SQL is
involved. Admission OS/service leaves in shell fixtures are explicitly doubled;
effective Linux cgroups, image recipes and worker startup remain unexecuted.

Retained attempts, in order: (1) uninitialized typed pipe array, exit 255;
(2) Windows anonymous-pipe hang, verified synthetic parent/child stopped, exit -1;
(3) source-export fixture could not find Windows Git under Linux's minimal PATH,
exit 255; (4) the discovery guard's self-path comparison mixed Windows separators,
exit 255; (5) corrected suite passed 273 assertions/21 scenarios in 54.918738 s;
(6) expanded suite passed 292 assertions/25 scenarios in 59.592542 s;
(7) the final capability-contract recheck passed as above. File-backed capture fixed the blocking
read; no timeout was raised to obtain a pass. The initial sandbox Bash syntax call
also failed to initialize its MSYS synchronization object; the authorized syntax-only
retry outside that sandbox passed. Failures and rechecks remain separate files in
the external `m6c-native-harness` evidence directory.

Sequential scope checks pass: M6B **42 assertions** (12.903642 s), M6C **53 assertions**
(8.066212 s). PHP `-n -l` passes **253 files** (245 tracked plus eight new), with all
eight new files refreshed after the final edits. Bash `-n` passes both new scripts,
both documented command blocks and the extracted Dockerfile RUN block: **five
syntax-only checks**, no commands executed. Documentation checks pass **28 links,
nine local anchors, six balanced fence markers**. Versions: PHP **8.4.24 CLI ZTS**
(Visual C++ 2022 x64), Git **2.56.0.windows.2**, GNU Bash **5.3.15(2)**,
Node **v24.22.0**, PowerShell **7.6.5**. No prior CLI/process runtime suite was rerun.
The current raw checksum comparison confirms **264 protected tracked files** are
byte-identical to task start. All 25 migration files and canonical pins are retained;
025 SHA-256 is `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
No structural guard exemption was needed.

### Exact harness change inventory

1. `tests/RunM6CNative.sh` — explicit admission, isolated orchestration and cleanup.
2. `tests/WebsitePlatformM6CNative.php` — isolated coordinator and protected cases.
3. `tests/WebsitePlatformM6CNativeLauncherTest.php` — synthetic discovery-safe suite.
4. `tests/support/WebsitePlatformM6CNativeContract.php` — pure inspection contracts.
5. `tests/support/WebsitePlatformM6CNativeProcess.php` — bounded children/barriers/capture.
6. `tests/support/WebsitePlatformM6CNativeSource.php` — exact raw tracked-blob checks.
7. `tests/support/WebsitePlatformM6CNativeFixture.php` — real-service fixture/request adapter.
8. `tests/support/WebsitePlatformM6CNativeWorker.php` — protected exec and instrumented children.
9. `tests/support/WebsitePlatformM6CNativeCases.php` — remaining native acceptance assertions.
10. `tests/support/WebsitePlatformM6CNativeLauncherFixture.sh` — fake-command scenarios.
11. `tests/native/m6c/Dockerfile` — unexecuted digest-pinned preparation recipe.
12. `docs/sprint-8.8-m6c-native-validation.md` — operating and coverage guide.
13. `docs/sprint-8.8-m6c-local-implementation.md` — this implementation/evidence record.

The last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Working-staging migration 025 is already
applied and is not reapplied. Backup/download remains operator-reported COMPLETE,
both copies and the confirmation receipt retained; restore remains NOT EXECUTED.
Historical disk figures above are not new measurements. No server, real database,
image pull/build, container, installation, backup/restore, maintenance, deployment,
provider, public activation or Narrator operation occurs in this authoring task.
PR #128 remains open, non-draft, unmerged and without auto-merge. The exact new-head
review request/observation and nine-point handoff are saved outside Git in
`m6c-native-harness/SUMMARY.txt`; no old threads are bulk-resolved.

## Native teardown review corrections

Starting branch/head: `codex/sprint-8.8-m6c-artifact-builder`,
`648fd0dd6c7da8d9ef19e5c6e2d276959595e32b`, initially clean. Harness authoring baseline
remains `96715eb35ac1ae2eb69c3a39a14ec009048128ad`. Only the five files listed below
change in this correction. Native image preparation and execution remain
**NOT PERFORMED**. This section supersedes the prior harness section's launcher
status semantics; its earlier counts remain historical results.

### Reproductions and correction

[P1](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4320474823)
reported Linux/Bash 5.2 exiting 255 at `lost-create-reply`: fake inspection printed
the correct runtime ID but a bare return retained the incoming EXIT-trap failure,
leading to fallback and retained synthetic markers. Locally, the unchanged suite
passed **292 assertions / 25 scenarios**, exit 0 in **57.179448 s**. A separate
actual EXIT trap after operation exit 17 printed the exact 64-character runtime ID,
inspection status 0, branch `owned_identity`, verified-absence status 0 and process
exit 17. That minimal probe intentionally retained its runtime/network marker files;
it did not remove a runtime. There was no local first failed assertion in the
unchanged suite. This Git Bash 5.3.15 result does not reproduce the reviewer's Bash
5.2 behavior. No installed Bash 5.2 was found; native Linux/Bash 5.2 was not tested.

Every successful fake output/file/test leaf now returns an explicit numeric result.
Failed writes, copies and removals propagate their status, as do uncached images,
create/start/stop failures, lost create acknowledgment, missing/malformed reports,
foreign ownership and unexpected commands. Network/container listings reflect the
private synthetic markers. The launcher's absent-resource fallback returns zero
only for a successful empty listing; a present or uncertain resource fails closed.
Exact owner/token/name/role/image/network guards are unchanged.

[P2](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4320474831)
was reproduced against the unchanged launcher in **12 real-signal probes**: INT and
TERM at runtime stop, DB cleanup, network cleanup, status preparation, manifest
hashing and receipt publication. Every intended handler ran (130/143 recorded),
but every process exited 0 with cleanup PASS, a zero final status and a published
receipt. The corrected probe run completed in **43.889702 s**. An initial probe-generator template-literal syntax error is retained separately.
The first generated signal probe then had a JavaScript replacement-string `$$`/PID error, then failed its handshake
and capture cleanup; that failed attempt is retained separately and is not signal
proof. All probes used private files/fake commands and foreground dedicated Bash
children, with bounded handshakes and no signal to a terminal or process group.

The launcher now retains the original operation exit separately from interruption.
Precedence is original nonzero operation, first accepted INT=130/TERM=143, secondary
cleanup/publication failure=2, then zero. Recording traps remain active through
substantive cleanup, provisional status and payload hashing. They never recurse
into teardown or reset its 90-second deadline. The 1200-second suite limit remains.

The precise terminal boundary is the single `trap '' INT TERM` builtin in
`m6n_close_signals`, after substantive evidence preparation. The outcome is refreshed
then, status is regenerated once and its finalized hash appended to the prehashed
payload manifest. One bounded commit (4 seconds plus 1-second kill grace) atomically
renames the receipt last. One failure diagnostic attempt (2 plus 1 seconds) removes
any receipt and records publication failure. Both fit inside the original cleanup
budget; earlier cleanup/preparation commands reserve those eight seconds. Accepted
signals before the boundary affect final status; later INT/TERM cannot alter the
committed decision. There is no unbounded retry or payload rehash loop.

Status, console and receipt record primary, interruption, cleanup, publication and
final exit separately. The receipt binds `SHA256SUMS`, which covers finalized
`.txt`/`.json` bytes. Failed publication leaves no completion receipt; if failure
status itself cannot be written, console/exit remain the diagnosis and files are
incomplete evidence. Publication PASS can describe a valid failure report. The
coordinator's `native.json` is preserved as observed, including native-case PASS
followed by a launcher interruption. No production reader or native contract change
was needed: the directly affected launcher-format reader lives in its regression
suite and accepts GNU checksum text/binary markers while verifying exact hashes.

### Correction validation

The unchanged baseline result above is separate from correction runs. Expanded
attempt 1 exited 255 in 17.987200 s: the new checksum reader initially accepted
only GNU's text marker, while Git Bash emitted its valid binary marker. The reader
now accepts either marker and still verifies each exact SHA-256. Expanded attempt
2 passed **3813 assertions / 80 scenarios**, exit 0 in **359.048950 s**. A final
recheck adds receipt-header/committed-status write faults and explicit deadline
reservation/expiry assertions. Every attempt and its synthetic evidence directory
is retained separately; no timeout or resource limit was increased to obtain PASS.

Final synthetic suite: **3981 assertions / 86 fake-command scenarios**, exit 0,
**390.008614 s**. This includes the actual pure-contract/JSON evidence reader checks,
34 successful/deliberately failing fake-command leaves in a nonzero EXIT trap,
verified absence versus uncertainty/foreign ownership, real signal handshakes,
first/repeated/mixed signal precedence, publication faults/timeouts and finalized
byte checks. All original 25 scenarios remain. No detached finalization algorithm
or operational worker was substituted for the real launcher functions.

Sequential commands/results:

- `php -n tests/WebsitePlatformM6CNativeLauncherTest.php` — results above; optional `M6C_NATIVE_TEST_EVIDENCE` pointed to the existing private external evidence directory.
- `php -n tests/WebsitePlatformM6BScopeTest.php` — **42 assertions**, exit 0, **11.605460 s**.
- `php -n tests/WebsitePlatformM6CScopeTest.php` — **53 assertions**, exit 0, **6.907649 s**.
- `php -n -l PATH`, separately for all **253 tracked PHP files** — exit 0 for every file, **49.835970 s** total.

Versions: PHP **8.4.24 CLI ZTS**, Visual C++ 2022 x64; GNU Git Bash **5.3.15(2)**,
Git **2.56.0.windows.2**, Node **v24.22.0**, PowerShell **7.6.5**. No timed suite
ran concurrently with lint or other validation. No completed Linux CLI/process
runtime suite was rerun.

Bash `-n` passed the launcher, fixture and two documented Bash blocks: **four
syntax-only checks**, exit 0, **0.644399 s**, with no script bodies executed.
Documentation checks pass **34 links** (local destinations and external URL syntax),
**11 local anchors** and **six balanced fence markers**. Raw SHA-256 comparison
against the task-start snapshot confirms exactly five changed files and **441
other tracked files byte-identical**; all 25 migrations and canonical pins remain
unchanged. Working/staged/committed diff checks are retained with the final Git
handoff outside the repository.

### Exact correction inventory and preserved limits

1. `tests/RunM6CNative.sh` — explicit absence result, first-signal recording, bounded cleanup/evidence finalization and coherent outcome fields.
2. `tests/support/WebsitePlatformM6CNativeLauncherFixture.sh` — explicit fake command statuses, real child-only signal handshakes, synthetic file and publication faults.
3. `tests/WebsitePlatformM6CNativeLauncherTest.php` — EXIT-trap, signal/lifecycle, checksum and outcome consistency regressions; optional retained synthetic evidence.
4. `docs/sprint-8.8-m6c-native-validation.md` — exact signal boundary, deadlines, evidence semantics and local reproduction limits.
5. `docs/sprint-8.8-m6c-local-implementation.md` — this correction record.

All 20 native cases, their protected/instrumented lane distinction, isolation,
privilege/network/storage guards, production application/worker/runtime/renderer/
artifact-store bytes, all 25 migrations/pins and the older M6B launcher remain
unchanged. Migration 025 remains already applied to working staging, canonical
SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`; it was not reapplied.

The operator-reported Linux prerequisite PASS at `96715eb` remains **206 CLI / 389
process assertions**, independently reported, not rerun or combined with these
synthetic results. Last reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Backup/download remains operator-reported
COMPLETE, both copies and receipt retained; restore NOT EXECUTED. No server, live
SQL, migration, backup, deployment, installation, real container, provider or
Narrator action occurs. PR #128 remains open/non-draft/unmerged with no auto-merge.
Exact pushed SHA, the two thread replies/resolutions, one new-head review observation
and the nine-point plain-text handoff are retained outside Git in
`m6c-native-teardown-fixes/SUMMARY.txt`.

## Native storage setup and report correction

October 21, 2026. Local harness correction only. Starting branch
`codex/sprint-8.8-m6c-artifact-builder` was clean at
`740431fd1f3cae6f747983d75f6bb5309ecaddc3`; PR #128 was open, non-draft, unmerged,
with no auto-merge. Its exact-head review had completed with no findings. This
correction responds to an operator-observed runtime failure, not a new review
finding; no review-thread ID is attributed to it. The authoring baseline remains
`96715eb35ac1ae2eb69c3a39a14ec009048128ad`.

### Failed native history and evidence limits

The operator reports **one** full invocation at 740431f: launcher exit **1** after
**18.60 s**, first error `chmod(): Operation not permitted`, in coordinator storage
setup before SQL or worker execution. Stdout's **465 assertions** are not 465
passing native cases. Overall **FAILED** and **all 20 cases UNREACHED** remain
history. Host `native.json` was missing. Cleanup and evidence publication PASS,
original launcher manifest 11/11 verified, both containers/network removed,
Docker restored inactive/disabled with one start/one stop, deployed source
unchanged and Apache active are all operator-supplied facts. No retry, patch,
merge or deployment occurred in that run.

Evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-isolated-native-operator-20261021-z12w5b24`.
Files: `M6C-ISOLATED-NATIVE-VALIDATION.txt` and `SUMMARY.txt`.
Operator-supplied **SUMMARY.txt-only** SHA-256:
`dc452d0fa040670f26a17b85760ee3fffd14a257d8d0263998b51df2f65a5957`.
Operator manifest: **47/47 verified**. This desktop task did not access the server,
retrieve those files or independently verify those hashes/manifests. The summary
hash is not assigned to the validation report or this task's new SUMMARY.txt.

### Diagnosis, correction and bounded audit

The prior mkdir/chown(2000)/chgrp(2000)/chmod(0700) sequence transfers ownership
before a caller-owner check. Linux chmod requires matching ownership or
CAP_FOWNER. CHOWN allows UID/GID changes; DAC_OVERRIDE bypasses file access
permissions, not chmod ownership. The approved coordinator lacks FOWNER. This
explains the source defect and is consistent with the operator's error; no local
Linux reproduction was performed. References:
[chmod(2)](https://man7.org/linux/man-pages/man2/chmod.2.html),
[capabilities(7)](https://man7.org/linux/man-pages/man7/capabilities.7.html).

`m6nSetup` now uses the actual testable `beforeDatabase` gate. It verifies protected
root-owned ancestors and all three absent names before mutation. Work/sealed are
created restrictively, verified as new root-owned directories on the expected
device, chmodded/verified while still root-owned, then chgrp/verified, then chown
**last**. Cache-cleared final metadata must be directory, **UID 2000 / GID 2000 /
0700**, on that same device. Uploads remain **root:root / 0755**. No recursive
repair, suppressed chmod, broadened mode or capability is introduced. Every
mutation's false return or warning becomes a bounded step-specific failure.
Configuration writes, connections and subsequent worker launch cannot run after
partial storage setup. Config ownership/modes remain **root:2000 / 0440** for
env.php and **root:root / 0444** for worker config/context; writes and metadata
are now checked before connecting.

The one related-path audit covered coordinator setup, startup-denial restoration,
fixture/case mutations and process capture files. Root-owned config/assets and
new coordinator-owned pending journals have no worker-owner-before-chmod sequence.
Startup denial transfers the checkout script back to root via CHOWN and chmods
the separate config that remained root-owned; its existing mutations now check
their results. The worker-owned sealed-byte tamper remains a write through the
already-approved DAC_OVERRIDE, without chmod/chown. Fixture, cases and process
helper files are byte-identical. The 20 case names/lanes, production factory,
normal worker UID 2000 and complete capability drop remain intact; the pre-existing
wrong-UID startup-denial case remains negative coverage. Launcher/contract enforce
exactly CHOWN, DAC_OVERRIDE, KILL, SETUID, SETGID, SETPCAP for setup; no FOWNER,
SYS_ADMIN, privileged mode or additional capability is added.

### Separate report investigation

Before this correction, `safeReport=true` was reached after argument, namespace,
launcher identity and resource binding checks, before source/storage checks.
The included Linux guard installs its warning handler only when directly invoked
as its own CLI; it does not install one in the coordinator. A chmod warning/false
result is followed by the throwing `m6nCheck`; an exception also reaches the outer
Throwable catch. Catch records FAIL, and finally cleans children then attempts
JSON serialization and a direct write to the final report path. Size/write checks
existed; chmod's boolean result was unchecked. Reporting exceptions emitted only
a generic message. This trace does **not** establish the original container's
unseen state or the exact missing-report cause.

The main launcher capture records `native-report-capture=PASS/FAIL` in report.txt,
retains main docker-cp stderr on the caller's stderr and preserves the primary
native exit. Its fallback copy suppresses output/errors before teardown. Those
paths remain byte-identical. Host absence alone cannot distinguish a missing
container report, failed finalization, failed capture or another cause. Original
cause: **UNPROVEN**; no server diagnostics were run to resolve it.

The actual catch/finally now call the tested reporter. After attestation it pins
the fixed protected report directory's metadata; publication rechecks identity.
FAIL, stage, attempted assertions, separate successfully completed assertions,
primary error and 20 UNREACHED cases survive early setup failure. One exclusive
pending file, one write bounded to 1 MiB, flush, mode, regular-file ownership/
device/link-count/size checks, close and one final rename publish the report under
the unchanged overall deadline. Failed pending bytes are never a completed report.
Primary failure and secondary publication failure have distinct bounded sanitized
stderr diagnostics. No secret, raw exception, config or environment dump is added.
Unattested invocation has no publication target; no CLI report path exists.
Cleanup/publication success does not change a native FAIL into PASS. The unchanged
evidence reader still rejects missing/failed/incomplete native results.

### Sequential local validation

All timed validation ran sequentially. Actual commands, outputs, exits, durations,
initial failures, later rechecks and raw-byte snapshots are retained outside Git
in `m6c-native-storage-setup` under the authorized desktop evidence directory.

- `php -n tests/WebsitePlatformM6CNativeSetupTest.php`, attempt 1: exit **255**, **0.3682076 s**. The expected report was absent and the test tried to decode it.
- Same command, attempt 2 with a clearer test assertion: exit **255**, **0.5673583 s**, exposing `report_rename`. A minimal independent private-file probe reproduced Windows sandbox rename access denial after successful write/flush/chmod/close. Its output is retained; this is not Linux permission evidence.
- Same command outside that sandbox, attempt 3: exit **0**, **682 assertions / 60 scenarios**, **2.0019934 s**. No limits were increased and no publication check was removed.
- Final recheck after adding actual local storage creation/existing-path coverage: exit **0**, **734 assertions / 62 scenarios**, **1.0195261 s**.
- `php -n tests/WebsitePlatformM6BScopeTest.php`: exit **0**, **42 assertions**, **13.7497797 s**.
- `php -n tests/WebsitePlatformM6CScopeTest.php`: exit **0**, **53 assertions**, **8.4790726 s**.

The focused suite executes the actual storage gate and reporter, including the old
ordering's modeled rejection; exact mutation order/final metadata; false/warning
failures for mkdir/chmod/chgrp/chown; lying transitions; wrong UID/GID/mode/type/
device and failed stat; unsafe ancestors/existing/link paths without repair; and
configuration/connection/worker continuation exclusion. Real local directory
creation, file writes/flush/chmod/rename and cleanup complement the explicitly
simulated Linux ownership/capability model. Reporting faults include short/failed
write, serialization/size, flush, chmod/stat/metadata/close/rename, pre-existing
or substituted target and cleanup failure. Actual direct coordinator invocations
reject missing attestation and arbitrary report arguments before setup. These are
local synthetic results, **not native PASS**. No Linux, container, MySQL or
protected-worker acceptance was executed here.

Runtime versions: PHP **8.4.24 CLI ZTS**, Visual C++ 2022 x64; GNU Git Bash
**5.3.15(2)**, Git **2.56.0.windows.2**, Node **v24.22.0**, PowerShell **7.6.5**.
`php -n -l PATH` passed all **256 tracked/new PHP files**, each separately, exit
0, **53.0003889 s** total. Bash `-n` passed **four syntax-only checks** (launcher,
fixture and two documented blocks), exit 0, **0.5500166 s**; no bodies executed.
The external `check-docs-integrity.cjs` check passed **40 links** (local destinations
and external URL syntax), **13 local anchors**, **six balanced fence markers** and
current failure/uncertainty/migration status, exit 0, **0.9526573 s**. The final
documentation-only completion and Git handoff receive a separate retained recheck.
Raw SHA-256 comparison with the 446-file task-start snapshot found **443 tracked
files byte-identical**, exactly three tracked edits and three additions listed
below. All 25 migrations and the launcher/fixture/contract/cases/process boundaries
are unchanged. Working, staged and committed diff checks accompany the final
handoff outside Git.

### Exact inventory and next authorized work

1. `tests/WebsitePlatformM6CNative.php` — storage gate, bounded checked setup/denial mutations, stages, successful assertion count and reporter integration.
2. `tests/support/WebsitePlatformM6CNativeSetup.php` — fixed private-root setup order and test operation seam; bounded step codes.
3. `tests/support/WebsitePlatformM6CNativeReport.php` — report state/failure preservation, fixed trusted location and checked pending-file publication.
4. `tests/WebsitePlatformM6CNativeSetupTest.php` — actual-helper ordering/failure/report tests, simulated permissions and real local files.
5. `docs/sprint-8.8-m6c-native-validation.md` — failed operator history, correction/diagnostic limits and later image/rerun requirements.
6. `docs/sprint-8.8-m6c-local-implementation.md` — this correction record.

Production application/worker/runtime/renderer/store, all **25 migrations** and
canonical integrity pins remain byte-identical. Migration 025 is already applied
to working staging, SHA-256
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`; no reapply.
Last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Backup/download remains **COMPLETE**, both
copies and receipt retained; restore **NOT EXECUTED**.

Historical operator Linux CLI/process PASS at 96715eb remains **206 / 389
assertions**. Linux launcher regression PASS remains **3981 assertions / 86
scenarios**. Image preparation/check-only PASS at 740431f precedes the failed
native run above. None is rerun or relabeled. Launcher/fixture/contract paths are
unchanged, so the launcher regression and historical CLI/process suites were
**not rerun** for this correction.

The prepared runtime image is source-labeled for 740431f. A later separately
authorized image refresh must satisfy provenance for the **new exact reviewed
commit**, before check-only and another separately authorized isolated native run.
Existing pinned base/MySQL images may be reused only if verified. No old-label
rewrite, relaxed source check, invented digest or claim of a newly prepared image
is permitted. No image pull/build or real container run occurred here.

One concise PR update and at most one exact-new-head review request are authorized;
no invented finding thread, duplicate request, repeated polling, merge or
auto-merge. Final pushed SHA, actual review state and nine-point handoff are saved
outside Git in `m6c-native-storage-setup/SUMMARY.txt`. Zero server, live SQL,
migration, image/container, backup, deployment, host-configuration, provider or
Narrator actions occur in this desktop correction.

## Native child diagnostics and tmpfs transport

October 21 desktop correction, starting from clean branch
`codex/sprint-8.8-m6c-artifact-builder` at
`e2998fdadaeb19048556d303cc9d310fde2c2276`. PR #128 was open/non-draft/unmerged,
auto-merge off; that exact head's review had completed with no findings. This is
an operator-failure correction, not a fabricated review finding or thread reply.
No speculative production/multipage-build fix is included.

### Retained evidence and its limits

Operator OBSERVED: retry exit **1**, duration **24.156694505 seconds**; migrations
001–025 explicitly recorded as applied to the new disposable schema; last explicit
PASS `PASS protected-startup-denials (protected)` at child.stdout.txt:26;
**560 attempted / 559 completed assertions**, including setup. The coordinator
emitted a primary diagnostic and terminal summary, with no `Native report
publication FAIL` message. Host docker-cp reported it could not find
`/run/m6c/report.json` in the runtime; fallback outcome was suppressed and
host native.json is absent. Cleanup/evidence publication succeeded and both
containers/network were removed. Working staging was not used; overall FAILED.

SOURCE-ORDER INFERENCE: the next failure is protected-multipage-build, before
success-text/artifact verification. One explicit PASS, one attributed failure,
**18 UNREACHED**; five protected launches inferred from progression, not a surviving
per-child process inventory. Instrumented workers were not reached. The excerpt
does not provide the exact first primary error; `actual_child_exit` is not an
established historical error merely because it occurs in source. No SQLSTATE/driver
or exact container PHP/MySQL version was retained. Successful in-container report
publication remains **UNPROVEN**; the protected build's underlying cause remains
**UNKNOWN**. These changes do not recover lost historical child output.

Operator-only diagnosis directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-retry-failure-diagnosis-20261022-aub94vi6`.
Reported `M6C-RETRY-FAILURE-DIAGNOSIS.txt` SHA-256:
`5eee6e59ecb00b559e51428c55c9f86271490a957493901018b5295ce84020e3`.
Reported operator `SUMMARY.txt` SHA-256:
`53e5357061417b6e93a9ff0eb631a68a44eac0ca94e77660dc6ba17b8374691d`.
The operator reports eight payloads verified. This task did not retrieve or verify
server evidence; these distinct hashes are not assigned to this desktop SUMMARY.
The supplied directory's 20261022 name is retained verbatim.

The earlier 740431f setup failure remains separate: exit 1 / 18.60 seconds / 465
reported assertions / all 20 UNREACHED. The prior storage correction, historical
Linux CLI/process 206/389, launcher 3981/86, and image/check-only results are not
relabeled as new validation or native success.

### Child evidence independent of JSON

The existing process helper observes stream sizes before close removes private
capture files and retains captured bytes. An observation can report a live child
with null exit; negative/unknown exits are never invented as zero or a signal exit.
The existing line/finish/stop timeout, child ownership and termination behavior is
preserved. `m6nRead` and `m6nFinish` use a small diagnostic helper around the actual
process calls and original assertions. Unexpected result/barrier/JSON/timeout/
overflow failures emit a bounded `M6C CHILD FAILURE` record to existing coordinator
stderr before the generic assertion propagates. Expected nonzero startup denials
retain expected/actual status without unexpected-failure diagnostics. The native
business-case assertions and normal protected lane are unchanged.

Sanitized entries contain case/phase/last barrier, expected exit when known,
observed exit/signal/running/termination state, actual stream sizes, bounded captured
lengths, truncation flags and SHA-256 with complete/prefix scope. Only exact known
worker outcome/error lines are allowlisted, at most two per stream. Unknown output
is not printed; it contributes safe metadata/digest. No commands, credentials,
environment/config dumps, raw SQL or lease tokens are emitted. JSON escapes controls.
Lines are capped at 2 KiB and aggregate emitted child diagnostics at 16 KiB.
Structured report children and final cleanup entries use the same sanitized
summaries. Diagnostic write/record failures are secondary flags and do not replace
the primary exception or turn a failure into PASS.

### Publication, capture and acceptance remain separate

The report publisher now returns exact byte count/hash only after its checked
final rename. It emits a single source/run-bound `M6C REPORT PUBLISHED` line for
both FAIL and PASS reports. This is publication acknowledgement, not acceptance or
power-loss durability. No report JSON is dumped to ordinary console. Publication
failure retains its distinct sanitized message; acknowledgement/sink failure cannot
overwrite the primary native result. The tested storage setup order is unchanged.

[Docker documents tmpfs among copy corner cases](https://docs.docker.com/reference/cli/docker/container/cp/#corner-cases).
Both former docker-cp paths now call the same narrow `m6n_capture`. That is a
source-level transport correction, not proof that the missing historical report
existed or that tmpfs caused that particular absence. Normal and at most one
fallback capture run before owned runtime teardown. Exact ID/token/name/role/image/
network mode, the sole current network attachment and Running are checked before
exec; unknown, foreign, merely name-matching, disconnected, additionally networked,
stopped or unavailable containers are not executed or restarted.

The fixed no-bootstrap PHP reader accepts only `/run/m6c/report.json`, root-owned
0644 regular single-link file on the protected report filesystem, canonical paths,
size 1 through 1,048,576 bytes. It compares opened metadata and reads at most limit
plus one byte to detect change/partial transport. Pending/symlink/special files are
rejected before open. No archive, TTY, shell, new privilege/mount or application/SQL
worker is used. A host consumer bounds the pipe before creating a private attempt
pending file; separate producer (reader/Docker exec) and consumer exits must both
succeed. The combined exec exit does not fabricate a separately unobserved reader
status. Valid-looking stdout with a nonzero producer exit is never promoted.

Host verification checks complete JSON, exact source/run, native=true, PASS/FAIL,
nonnegative assertion count and exact case/status inventory. FAIL reports are
preserved without changing their bytes. Full PASS acceptance still uses the original
strict validator requiring all 20 cases and correct lanes/positive assertions.
Acknowledgement observation is ABSENT or MATCH; when present, exactly one correctly
bound byte count/hash must match, otherwise capture fails. Absent acknowledgement
is not retroactive proof of failed publication. Only verified complete pending
captures become native.json; malformed, empty, truncated or over-limit data does not.
Host Linux directory/file owner checks use the effective UID from a bounded
`/proc/self/status` read, without relying on POSIX being loaded under `php -n`.

Each attempt has one 15-second budget shared across stages, clipped to existing
1200-second execution or 90-second cleanup/evidence time, preserving terminal
publication reserve. Normal/fallback metadata and bounded reader/consumer/verification
diagnostics are finalized before evidence hashing. No file is appended after final
manifest/receipt publication. Original primary, first signal and secondary failure
precedence, signal acceptance through cleanup/hashing, terminal boundary and exact
cleanup ownership are preserved. Fallback success cannot erase the normal failure;
native exit zero plus capture failure cannot yield launcher PASS. Evidence
publication PASS remains independent of native success.

### Local validation and exact inventory

Timed suites run sequentially; actual output/exit/duration, initial attempts and
rechecks are retained outside Git in `m6c-native-child-transport`. Diagnostics use
real local PHP children/private files. Linux owner/mode metadata and Docker leaves
are explicitly simulated; no local result is native/container proof.

Initial focused results: `php -n tests/WebsitePlatformM6CNativeDiagnosticsTest.php`
passed **91 assertions / 36 scenarios**, exit 0, **3.061311 s**. This covers
unexpected exit after IDENTITY, expected nonzero denial, pre-barrier exit, timeout,
overflow, malformed barrier JSON, failed/throwing diagnostic sink, secret/control
canaries, unknown running exit, aggregate bound, fixed-reader metadata faults,
partial bytes, exact/over limit, PASS/FAIL envelope, source/run and acknowledgement
hash/size/binding mismatches. `php -n tests/WebsitePlatformM6CNativeSetupTest.php`
passed **751 assertions / 62 scenarios**, exit 0, **0.8673126 s**, including
acknowledgement only after successful publication and the unchanged storage order.
Initial launcher run passed **7227 assertions / 102 synthetic scenarios**, exit 0,
**647.2216248 s**. Review then tightened host `-n` compatibility, live network
attachment validation, the inspection step's capture deadline and pending-size
consistency; the pending-only fixture now contains only an unpublished file.
Focused diagnostic recheck passed **96 assertions / 41 scenarios**, exit 0,
**3.2375518 s**, adding bounded effective-UID parsing checks with modeled proc data.
Final launcher recheck passed **7378 assertions / 104 synthetic
scenarios**, exit 0, **656.8067472 s**, including rejection without exec for
disconnected or additionally networked runtimes. No local regression failed; both
complete passing fixture trees and all initial/recheck logs are retained.
The first Bash syntax launch was blocked before parsing by Windows sandbox MSYS
named-object access (`NtCreateDirectoryObject`, `0xC0000022`). Its raw failure is
retained: wrapper exit 1; exact native exit/duration were not saved because the
wrapper threw before writing metadata. All five syntax checks passed in a separate
authorized recheck outside the sandbox. No source syntax or approval-review failure
was observed.

Additional sequential checks, all exit 0:

- `php -n tests/WebsitePlatformM6BScopeTest.php`: **42 assertions**, **16.437832 s**.
- `php -n tests/WebsitePlatformM6CScopeTest.php`: **53 assertions**, **10.4777418 s**.
- `php -n -l FILE` for every `rg --files -g '*.php'` entry: **259 PHP files**, **53.634092 s**.
- Git Bash `bash -n -- FILE`: **5 syntax checks**, **0.8253992 s**, covering launcher, fixture and all three guide Bash blocks; no proposed commands executed.
- Documentation checker: **45 links / 16 local anchors / 8 balanced fence markers**; external URLs checked for syntax.

Tools: Windows PowerShell **7.6.5**, PHP **8.4.24 CLI ZTS VC++ 2022 x64**,
Git Bash **5.3.15(2)**, Git **2.56.0.windows.2**, Node **v24.22.0**. Exact executable
arguments, elapsed seconds, exits, full lint/syntax inventories and raw output are
retained outside Git. Working/staged/committed diff checks accompany the final
handoff. Raw SHA-256 comparison of the 449-file starting inventory finds **439
unchanged**, **10 tracked edits**, plus **3 new files**. All 25 migrations, protected
native business/setup/acceptance sections and eight guarded launcher functions
remain unchanged. Linux UID/mode/proc data are modeled here, not real Linux proof.

Exact correction inventory:

1. `tests/WebsitePlatformM6CNative.php` — use tested child diagnostic wrappers; sanitize expected-kill report entries.
2. `tests/support/WebsitePlatformM6CNativeProcess.php` — retain observed stream lengths and expose bounded observations without fabricating exits.
3. `tests/support/WebsitePlatformM6CNativeDiagnostics.php` — new bounded allowlisted child summaries and failure-safe emission.
4. `tests/support/WebsitePlatformM6CNativeReport.php` — sanitized cleanup entries, publication acknowledgement and secondary sink failures.
5. `tests/support/WebsitePlatformM6CNativeContract.php` — minimal FAIL/PASS transport envelope and exact running-runtime capture admission; strict acceptance remains unchanged.
6. `tests/support/WebsitePlatformM6CNativeTransport.php` — new fixed-path reader, bounded host consumer and verified pending promotion.
7. `tests/RunM6CNative.sh` — shared verified exec capture for normal/fallback with visible independent statuses and existing deadlines.
8. `tests/support/WebsitePlatformM6CNativeLauncherFixture.sh` — fake exec/copy visibility and capture-failure leaves, no real engine.
9. `tests/WebsitePlatformM6CNativeLauncherTest.php` — transport, FAIL preservation, byte/hash/exit and capture-order scenarios alongside existing signal/receipt tests; explicit discovery allowance for the established deny-only setup test.
10. `tests/WebsitePlatformM6CNativeSetupTest.php` — publication-acknowledgement regressions.
11. `tests/WebsitePlatformM6CNativeDiagnosticsTest.php` — new real-child/private-file diagnostics and transport regressions with modeled Linux metadata.
12. `docs/sprint-8.8-m6c-native-validation.md` — current evidence limits, capture contract and proposed unexecuted transport smoke procedure.
13. `docs/sprint-8.8-m6c-local-implementation.md` — this record.

Production application/runtime/worker/renderer/store, native business-case files,
storage setup helper, capability set and all 25 migrations/pins remain unchanged.
Migration 025 is already applied to working staging; canonical SHA-256
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`; no reapply.
Last operator-reported deployed SHA remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Backup/download remains COMPLETE with both
copies/receipt retained; restore NOT EXECUTED. No backup or disk maintenance.

The existing prepared runtime image is source-labeled for **e2998fd**. Later
authorized preparation must satisfy the new exact reviewed SHA's provenance.
The guide proposes a separately approved one-runtime tmpfs transport smoke check
using the same capture routine, a known bounded FAIL report, no MySQL and no worker.
It was authored and syntax-checked only, not provisioned or executed. It does not
replace later separately authorized native acceptance or diagnose the multipage
build. No pulls/builds/relabeling occur here.

The current PR description must identify both failed native attempts and retain
older no-execution language as historical. One concise operator-correction update
and one exact-new-head review request are permitted; no fabricated review finding,
duplicate request, repeated polling, bulk resolution, merge or auto-merge.
Exact committed/pushed SHA, actual review observation and nine-point handoff live
outside Git in `m6c-native-child-transport/SUMMARY.txt`. No server, live SQL,
migration, image/container, deployment, backup, installation, provider or Narrator
action occurs in this desktop task.

## Stored JSON input verification correction

October 22, 2026; starting branch/head verified clean at
`8118138c890105f689fa145de7f5566f3263eee5`, PR #128 open, non-draft, unmerged,
auto-merge disabled. **REPRODUCED CANONICAL-INPUT DEFECT / CORRECTED LOCALLY /
NATIVE ACCEPTANCE STILL REQUIRED.** This is a small functional production change,
not a diagnostic-only or test-only change. No diagnostic fallback was added because
the contract violation reproduced with the actual builder, resolver, renderer,
validator and store before changing production behavior.

### Operator-reported run at 8118138

The operator reports one native invocation: **27.711082493 s, launcher exit 1**;
`actual_child_exit`, stage `native_cases`, case `protected-multipage-build`,
expected child exit 0 / actual 1 / signal 0, last barrier `IDENTITY` and
`Build-only invocation stopped: artifact_invalid.` The result remains **FAILED**:
**1 PASS / 1 FAIL / 18 UNREACHED**, **563 attempted / 562 completed assertions**
including setup. Startup denials passed; five protected children were recorded
(four expected denials and the failed multipage build); no instrumented case ran.
Reported runtime versions: PHP **8.4.27**, MySQL **8.4.11**; all 25 migrations ran
only in the disposable schema.

Report transport succeeded: publication acknowledgement **MATCH**, capture
identity **PASS**, reader/Docker, consumer and verification exits **0/0/0**;
`native.json` **8502 bytes**, SHA-256
`4d62ae8f98070343f18a608f308903cdbb1939877a614c8e6ae13f8c25e760fd`.
No fallback was needed and FAIL was preserved, not accepted as PASS.

The launcher evidence directory is
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-20261022T224833Z-255afaa6947b3c56b8be5e22b16fc35b`;
its MANIFEST SHA-256 is
`e6f8fc92ecae3810e51e55b3e6dbb029705680c3a7e38500159d15c4c0438dcf`
(operator verified **17/17 entries and receipt**). Operator evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-retry-operator-20261022T224733Z-c4f684fb35eda2cd`;
its MANIFEST SHA-256 is
`ab133c380ecf109179d8a6faf68a8040338e10f804a2f3728484d8f4a96d14a6`
(operator verified **73/73 entries and receipt**). These hashes identify the
specified manifests, not SUMMARY.txt. This desktop task did not download or
independently verify any server evidence.

The operator reports cleanup/publication PASS and Docker inactive/disabled.
Exact scratch removal is launcher-attested, not independently path-verified.
The reported new `moby-detect-rro` temporary directory remains untouched.
Neither that directory nor the last barrier establishes the exception's cause.

### Exception boundary and reproduced predicate

Source references below name repository paths and line numbers in this correction
(the rejected old hash comparison was validator line 139 at `8118138`).

| Phase | Actual call path and possible artifact_invalid origins |
| --- | --- |
| Harness handoff | `tests/support/WebsitePlatformM6CNativeWorker.php:32` prints IDENTITY **before** `pcntl_exec` at line 33 transfers to the real CLI. `tests/WebsitePlatformM6CNative.php:46` checks that barrier; line 231 starts the protected multipage case. It proves no production initialization, claim, render or seal progress. |
| Before claim | `scripts/site-build-worker.php:26` → `SiteBuildService::claimBuild` (`private/classes/SiteBuildService.php:163`) → `worker`/`dependencies` (818/799) → `SiteBuildRuntime::fromTrustedCli` (`private/classes/SiteBuildRuntime.php:14`). Protected config/checkout/ancestor checks call `SiteArtifactFiles::regular` (files helper line 41); config/CSS reads call its bounded read (66), and local-store roots are checked at construction. Missing/nonregular/link/hardlink/integrity failures can produce artifact_invalid here; identity/ownership/configuration predicates often instead produce builder_unavailable. |
| Claim / execute admission | Service claim line 196 and execute lines 362–426 validate source, requester, policy and canonical recorded input before the dependency executes outside SQL. `SiteBuildContract::recordedInput` (`private/classes/SiteBuildContract.php:139`) already canonicalizes stored JSON. No arbitrary database recasting or eligibility changes are needed. |
| Execute files | `SiteArtifactBuilder::build` (`private/classes/SiteArtifactBuilder.php:24`) checks projection, creates a candidate, resolves and validates asset bytes, renders pages and writes the exact inventory. Resolver (`private/classes/SiteArtifactAssetResolver.php:29`) can reject missing/ambiguous metadata, rights/site/status/MIME/size/hash mismatch; files helper can reject unsafe paths, missing/nonregular/link/hardlink files, bounded reads or inventory. Store binding/write checks can also emit artifact_invalid. This classification is not HTML-specific. |
| Seal / verify | Builder line 83 → `LocalSiteArtifactStore::sealIfAbsent` (`private/classes/LocalSiteArtifactStore.php:119`, validator call at 124) → `SiteArtifactValidator::verify` (`private/classes/SiteArtifactValidator.php:136`). Input digest precedes inventory, public HTML/CSS/assets, marker and exact metadata comparisons. Store verifies again after rename. The local failure was specifically the **input digest**, before any seal proof or rename. |
| Completion / masking boundary | CLI lines 29–31 catch the original execute SiteArtifactException, call `completeBuildFailure`, then rethrow it. Common service completion (`private/classes/SiteBuildService.php:624`, external verification at 652) → runtime `verifyOutcome` (132) → store `verifyRelease` (142). Store binding/progress errors can escape; most internal artifact inspection errors become unknown evidence, with service recovery/state handling. A later thrown completion error can mask the original in the CLI outer catch. The local reproduction observed completion status failed and **no completion exception**; no masking claim is made about the native run. CLI line 42 emits the unchanged human classification and exit 1. |

Migration `database/migrations/025_site_build_deployment.sql:24` declares
`input_manifest_json JSON NOT NULL`. `SiteBuildContract::input` (line 109) derives
`build_input_hash` from `CanonicalJson::encode`; recorded input uses the same
canonical representation. The old validator instead hashed the stored JSON
**text**, rejecting equivalent values after database serialization. MySQL documents
normalized separators and object-key ordering whose exact order is not guaranteed:
[MySQL 8.4 JSON normalization](https://dev.mysql.com/doc/refman/8.4/en/json.html#json-normalization).
The regression models these representation differences; it does not claim to have
captured the native row bytes or to emulate MySQL.

`tests/WebsitePlatformM6CStoredInputTest.php` uses the existing fake PDO lifecycle
fixture and the native fixture's pure `m6nComposition` definition, never its real
SQL seeder/adapter/coordinator. It proves equality with the local three-page
composition and replaces the local fixture's extra facts with the exact native
Synthetic Café facts. It retains home/roof-cleaning/contact, header/footer/mobile
CTA/navigation, self-canonical SEO, the shared PNG's two usage references, inert
lead fields, public projection, inventory and hash contracts. Stored input,
options and policy JSON get only separators/key-order changes; decoded scalar
types, list order and values stay intact. No IDs, URLs, PHP version or OS are
coerced to make it pass. SQL remains fake; trusted ownership is fixture wiring,
not execution of the Linux factory or proof of Linux UID/mode behavior.

Before production edits, the canonical case built, sealed, verified, completed
and replayed. The formatted JSON case passed claim and recorded-input validation,
then threw artifact_invalid through files helper line 18 → validator line 139 →
store line 124 → builder line 83 → service execution. **Eight candidate files**
existed, **no seal proof**, **no release**; raw text hash mismatched while canonical
hash matched. Failure completion returned **failed**, without a secondary
exception. Both private fixture roots were observed removed. Only bounded
repository-relative code locations, classification and phase/count booleans were
recorded privately: no arguments, raw traces, configuration, SQL, credentials,
generated HTML or source content.

The minimal functional correction hashes the already decoded input with
`CanonicalJson::hash` in validator verification. It preserves the hash equality
check and all later integrity checks. The identical regression then passed:
canonical and JSON-column representations seal/verify/complete/replay; equivalent
pretty/escaped representations retain manifest identity; modified content,
scalar type, page list order, hash, malformed and oversized JSON remain rejected.
No duplicate build/release/validation/event or altered output bytes occur on replay.
Existing authorization, asset, immutable-storage and recovery regressions remain
required. No public API, diagnostic flag, bypass, production logging or CLI change
was introduced. Invalid artifact error classification/message/exit remain intact.

This establishes the local contract defect and its correction. It is consistent
with the reported native symptom, but **does not prove that the native run reached
this predicate**; its last recorded barrier precedes application initialization.
The historical native result remains FAILED and its exact origin unconfirmed.

### Validation and exact scope

All post-fix checks below passed sequentially, exit 0. Command for each named
suite: `php -n -d zend.exception_ignore_args=1 tests/WebsitePlatform<Suite>Test.php`.
The focused StoredInput before-fix run exited 1 in **3.1165446 s**;
the identical post-fix test passed **95 assertions in 2.1790962 s**.
Both runs observed removal of their two private fixture roots.

The retained initial probe used configured PHP (no `-n`), exit 1,
**2.0384544 s**, rejecting an equivalent verification encoding.
The next `-n` probe, exit 1, **1.6867205 s**, located that
rejection at validator line 139. Refining the test to reach the full build path
exposed a test-authoring error (private classification property instead of its
accessor), exit 1, **1.6362704 s**. That accessor was corrected
before the definitive before-fix run; no production edit preceded that run.
These earlier failures and the final before/after records remain separate.

| Suite | Assertions | Seconds |
| --- | ---: | ---: |
| M6BAuthorization | 83 | 0.7922538 |
| M6BBehavior | 401 | 2.6854842 |
| M6BContract | 58 | 0.4859621 |
| M6BRecovery | 49 | 0.5749691 |
| M6BReplayComparison | 141 | 0.3370068 |
| M6BSchema | 110 | 0.9570075 |
| M6BScope | 42 | 25.1285606 |
| M6CAdmission | 58 | 3.2426400 |
| M6CArtifact | 45 | 2.6485830 |
| M6CBounds | 29 | 0.7131213 |
| M6CCli | 207 | 21.3635295 |
| M6CCliProcess | 383 | 27.8274461 |
| M6CExecution | 216 | 4.5943817 |
| M6CIntegration | 54 | 5.7870329 |
| M6CNativeDiagnostics | 96 | 4.0082807 |
| M6CRecovery | 135 | 9.3361366 |
| M6CScope | 53 | 14.7084325 |
| M6CSecurity | 91 | 1.8365086 |

Including StoredInput: **19 suites / 2346 assertions**. CLI includes one actual
local CGI invocation and 32 isolated valid-argument denials; its Linux/runtime
branches are simulated. CLI-process faults are controlled. Security ran one of
two native local link cases; unavailable Windows link coverage is not a pass.
Diagnostics/transport covers **41 scenarios**, real local children/files and
simulated Linux metadata. Fake-database error messages in fault tests are expected.
No test invokes real SQL, a native coordinator, protected host worker or container.

`php -n -l FILE` for every `rg --files -g '*.php'` entry passed **260 PHP files**
in **85.3563492 s**. Existing Git Bash `bash -n -- FILE` passed **five
syntax checks** in **1.4124268 s** (launcher, fixture, three unchanged
guide blocks; no proposed command executed). Initial `node check-docs-integrity.cjs`
passed **48 links / 18 local anchors / eight balanced fence markers** in
**2.6067355 s**; external URLs were checked for syntax only.
The same checker is rerun after this evidence text is filled. Working/staged/committed
diff checks and raw integrity comparisons are retained with the final handoff.

Tools observed: PHP **8.4.24 CLI ZTS VC++ 2022 x64**, PowerShell **7.6.5**, Git
**2.56.0.windows.2**, Git Bash **5.3.15(2)**, Node **v24.22.0**. Full commands,
durations, exits, assertion counts and inventory logs are outside Git under
`C:\Users\fvd83\.codex\visualizations\2026\10\17\01a14be9-d0ff-7ae1-96f5-649327b11ec1\m6c-artifact-origin`.
The passing local results establish no native PHP 8.4.27/MySQL 8.4.11, rootless
ownership/capability, concurrency or image-provenance coverage.

Exact changed-file inventory from `8118138`:

1. `private/classes/SiteArtifactValidator.php` — functional canonical input-hash verification (one predicate plus explanatory comment).
2. `tests/WebsitePlatformM6CStoredInputTest.php` — new native-content/fake-SQL/real-files before/after regression and invalid neighbors.
3. `docs/sprint-8.8-m6c-local-implementation.md` — this implementation/evidence record.
4. `docs/sprint-8.8-m6c-native-validation.md` — current failed result, correction limits and later verification gate.

All other 449 of the 452 starting tracked files are byte-identical, including
transport/acknowledgement/hash validation, launcher signals/cleanup/finalization,
native worker/cases, CLI, all 25 migrations and integrity pins. No full launcher
regression is repeated: its capture/validation/finalization path and dependencies
are unchanged. Earlier transport smoke and failed native attempts retain their
own SHAs, including the 8118138 run; none become results for this correction.

The cached runtime image belongs to **8118138**. A later separately authorized
task must establish the new reviewed SHA's image provenance, run the focused
stored-input and relevant artifact verification checks in that runtime, then
perform protected native acceptance. No automatic repeat of the successful
transport smoke is required for this unchanged transport. No image ID is invented,
no provenance check is relaxed, and this task authorizes no native execution.

Migration 025 remains applied to working staging; canonical SHA-256
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Backup/download is operator-reported
COMPLETE, both copies and receipt retained; restore NOT EXECUTED.
No server, live SQL/migration, image/container, deployment, installation/host
configuration, backup/restore/prune, provider, M6D–M6G or Narrator action occurs.
Cleanup is limited to this task's owned local fixture trees. PR #128 stays open,
non-draft and unmerged; one concise update and one exact-head review are recorded
with final SHA/status in the external `m6c-artifact-origin/SUMMARY.txt`.

## Native input assertion review correction

October 22, 2026; clean starting head `9a35b593f5214773c04de664ea283fb256b0350a`.
Verified review **5622132063**, [P1 comment 4354571137](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4354571137):
**Canonicalize the native input-hash assertion**, at `tests/WebsitePlatformM6CNative.php:100`.
This is **HARNESS/REGRESSION/DOCUMENTATION ONLY**. The production validator's
canonical-hash correction and every other production file remain byte-identical.

The old `m6nVerify` predicate was first extracted unchanged into the pure
`M6CNativeInput::matches` test-support helper. Both the coordinator and the new
executable regression call it; the coordinator is never included or executed by
the regression. The existing stored-input fixture/model functions were moved
unchanged into a shared support file. The regression uses that same UTF-8
three-page composition, fake lifecycle SQL, real artifact files and an actual
fake-DB completed release. It changes only input serialization in copies, never
persisted rows or expected hashes. Modeled JSON formatting is neither captured
MySQL row data nor a MySQL emulator.

Before correction, canonical input passed the actual predicate, while both
modeled database formatting and reordered/pretty/escaped JSON failed. All three
had the same canonical digest and valid release/job binding; only the two
noncanonical raw-text digests differed. The retained regression exited 1 before
changing the helper. The helper now uses the existing bounded decoder and
`CanonicalJson::hash`, AND the unchanged strict release/job digest comparison;
the coordinator retains its single `input_hash` assertion and failure meaning.

After correction all three representations pass. Changed content, scalar type,
list order, job digest and release digest fail; malformed/oversized JSON still
throw the existing decoder's `invalid_request`. The independent release-negative
first proves canonical input/job hashing succeeds. This small predicate does not
claim to detect a coherent rewrite of input and all trusted expected hashes;
source/authorization checks retain that responsibility. The production
stored-input artifact/hash/privacy/replay regression retains all 95 assertions.
Both focused attempts observed removal of their one owned private fixture root.

One bounded `rg` audit covered M6 input creation/readback/verification and M6
tests/support, searching `input_manifest_json`, `build_input_hash`, `hash` and
`CanonicalJson` calls. Relevant dispositions:

| Inspected path(s) | Disposition |
| --- | --- |
| `SiteBuildContract::input`, `recordedInput`, policy/options checks | Raw hash at input creation deliberately hashes freshly canonical `self::json` bytes; readback canonicalizes decoded values. Correct, unchanged. |
| `SiteBuildService` successful replay, input admission and evidence; `LocalSiteArtifactStore` binding | Strict persisted identity comparisons and canonical contract checks; no second raw stored-input digest found. Unchanged. |
| `SiteArtifactValidator::verify` | Already corrected canonical decoded-input digest at `9a35b59`; unchanged. |
| `m6nVerify` / new `M6CNativeInput::matches` | Sole additional demonstrated contract mistake; corrected here. |
| StoredInput/NativeInput regressions; M6B contract/behavior/MySQL test definitions | Raw checks are deliberate canonical-byte expectations or evidence that representations differ. Synthetic tamper/coherent-rewrite cases are not production readback checks. No SQL tests executed. |
| Builder/validator/store artifact bytes; native validation file/marker/file checks; NativeReport/Transport/Diagnostics, source and migration pins | Exact-byte checksums or existing canonical manifest/payload identities, each retained as designed. No global hash replacement or canonicalization of file/report bytes. |

Fresh sequential validation uses `php -n -d zend.exception_ignore_args=1
tests/WebsitePlatform<Suite>Test.php`. Before-fix NativeInput exited **1** in
**1.0635724 s**; the raw predicate accepted canonical input and
rejected both equivalent noncanonical inputs. The initial after-fix run passed
**29 assertions**, exit 0, **1.5858443 s**. One supplemental
coordinator-source read assertion was subsequently removed from the ordinary test
to respect the unchanged launcher's discovery rule; source wiring is instead
verified privately. The final executable checks below all exited **0**:

| Suite | Assertions | Seconds |
| --- | ---: | ---: |
| M6CNativeInput | 28 | 1.6611009 |
| M6CStoredInput | 95 | 2.1034186 |
| M6CArtifact | 45 | 2.3713811 |
| M6CIntegration | 54 | 5.5945146 |
| M6BScope | 42 | 22.8683199 |
| M6CScope | 53 | 15.6806031 |

Total: **six suites / 317 assertions**. This is fresh coverage, not a reuse of the
earlier production-fix 19-suite/2346-assertion result. Existing integration fault
tests intentionally emit bounded fake-PDO error labels. No real DB, operational
worker, native coordinator or container was invoked. Fixture cleanup was observed.

`php -n -l FILE` for all `rg --files -g '*.php'` entries passed **263 PHP files**
in **82.5746869 s**. Git Bash `bash -n -- FILE` passed **five syntax checks**
in **1.2924568 s** (launcher, fixture and three unchanged guide blocks;
no proposed command executed). The initial private `node check-integrity.cjs`
passed in **3.1741591 s**, checking **52 links / 20 local anchors /
eight balanced fence markers**, unchanged extracted functions and regression body,
the exact two-line coordinator wiring change, and **73 ordinary tests** against
the unchanged launcher discovery rule. External URLs were checked for syntax.
It is rechecked after filling this record; working/staged/committed diff checks
and committed integrity are retained outside Git. Raw hashes show **449 of 453**
starting tracked files unchanged, four tracked edits and three new test files.

Observed tools: PHP **8.4.24 CLI ZTS VC++ 2022 x64**, PowerShell **7.6.5**, Git
**2.56.0.windows.2**, Git Bash **5.3.15(2)**, Node **v24.22.0**. Exact commands,
durations, exits, before/after output and audit inventory are retained outside Git
in `m6c-native-input-review`. Windows/fake-SQL checks establish no real MySQL
serialization, Linux ownership/runtime or protected native acceptance.

Exact seven-file correction inventory:

- `tests/WebsitePlatformM6CNative.php` — call the shared predicate, load it after existing admission/source verification; all other native assertions unchanged.
- `tests/support/WebsitePlatformM6CNativeInput.php` — new pure bounded canonical-input AND strict release-binding predicate.
- `tests/WebsitePlatformM6CNativeInputTest.php` — new executable positive/negative predicate regression.
- `tests/support/WebsitePlatformM6CStoredInputFixture.php` — existing fixture/model functions moved unchanged for reuse.
- `tests/WebsitePlatformM6CStoredInputTest.php` — require shared fixture; original regression body unchanged.
- `docs/sprint-8.8-m6c-local-implementation.md` — this focused record.
- `docs/sprint-8.8-m6c-native-validation.md` — current harness correction and remaining gate.

Raw integrity checks preserve all production code, protected CLI/worker,
diagnostics/transport/acknowledgement, launcher admission/signals/cleanup/deadlines/
finalization and all 25 migrations/pins. The large launcher suite is not rerun:
its actual capture/validation/finalization dependencies are unchanged. Its
ordinary-test discovery restriction is checked separately; the optional
coordinator source-wiring check lives in the private integrity checker, not an
ordinary test that names a destructive entry. No allowlist or guard was relaxed.

The 8118138 native run remains FAILED and its exact exception origin unconfirmed;
no native run of `9a35b59` or this correction is reported. Earlier production-fix
and transport results remain at their own SHAs. No automatic transport smoke
repeat is needed. A later authorized task must satisfy the **new reviewed SHA's**
runtime-image provenance and protected native acceptance; cached 8118138 images
are not relabeled as evidence. Migration 025 remains applied to working staging,
SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Backup/download remains operator-reported COMPLETE, both copies/receipt retained,
restore NOT EXECUTED; no evidence or backup retrieved here. No server, real SQL,
migration execution, image/container, deployment, installation/host change,
backup/restore/prune, provider, M6D–M6G or Narrator action. Only the exact finding
may be replied to/resolved after checking rebuttals; one new-head review request,
no repeated polling. Final SHA, reply/resolution and observed review state are in
the external `m6c-native-input-review/SUMMARY.txt`. PR #128 stays open, non-draft,
unmerged and without auto-merge.

## Instrumented worker observation

Starting branch/head/worktree: clean `codex/sprint-8.8-m6c-artifact-builder` at
`79e6d63e63a018f0f2f8dcf4aa75cee341b7bf8e`. **DIAGNOSTICS IMPROVED / NATIVE ROOT
CAUSE STILL UNKNOWN**. The [current native guide](sprint-8.8-m6c-native-validation.md#instrumented-worker-failure-at-79e6d63)
records the operator-supplied invocation and distinct report, summary and manifest
hashes. No evidence was fetched from the server. The reported result is **3 PASS /
1 FAIL / 16 UNREACHED**, **671/671 assertions**, exit 1, **26.527271014 s** at
two-second monitoring cadence. Protected startup denials, multipage build and replay
explicitly passed; independent artifact and canonical input/release verification
completed. Instrumented concurrent-claims failed with `native_child_before_barrier`;
both cleanup-recorded children exited 1, signal 0, without retained cause/context.
Those facts do not prove two claims, deadlock, lease failure or a concurrency race.
Transport/capture succeeded; native rerun **NOT PERFORMED** in this desktop task.

Source trace (read-only production inspection; phases mean attempted operations):

| Interval | Existing operations and possible failures |
| --- | --- |
| Before READY: context/config | Fixed Linux/container/context ownership guards, JSON decode/version and builder SHA binding; mode/UID validation and fixture class loading. Literal context/argument failures, JSON or engine errors can occur here. |
| Before READY: database | Database::config reads/validates the existing private environment; worker compares host/schema/user, opens PDO, queries UUID/hostname/database and checks disposable-server identity. Configuration, PDO and binding failures can precede READY. No new query was added. |
| Before READY: dependencies | Positive integer arguments, actual LocalSiteArtifactStore/asset resolver/builder construction, dependency wiring; audit/validation install their existing forwarding PDO adapter. Store root/guard checks and construction errors can occur here. |
| READY to CLAIM/RESULT | Worker writes READY, waits for exact CONTINUE, calls claimBuild and checks job identity. Service worker/source identity, database reads/transactions and claim logic run inside existing contracts. No claim authority is inferred until valid observed output; RESULT with claimed=false is only normal when the child exits 0. |
| Subsequent execution | CLAIM waits for release; executeBuild can return a settled denial or build receipt; SEALED/VERIFY barriers precede completion/replay. Recovery uses RECOVERY and the existing reconcile/complete path. No ordering, retry or production lifecycle behavior changed. |

Three confirmed observability defects were separate from the unknown native cause:
the concurrency loop directly called line()/json_decode without the diagnostic
reader; cleanup mapped raw results to context-free summaries; and the worker catch
reduced ordinary RuntimeException literals to native_child_failure while its JSON
was excluded by the production-only message filter. SiteServiceSupport::transaction
and SiteBuildService::external can wrap errors without a previous cause; new
diagnostics preserve what reaches the worker, not information those wrappers lost.

Before correction, the exact concurrent reader was extracted unchanged into the
definitions-only diagnostics helper and wired into its real caller. An external
local driver used this helper and actual process/report helpers for five controlled
scenarios: pre-READY failure, post-release failure, failure with a waiting peer, two
self-exiting children, and a normal CLAIM/losing RESULT sequence. The original
supervisor exception, synthetic available output, report and missing fields are in
`m6c-instrumented-observation/before.json`, outside Git. The fixture used the old
worker's generic JSON wire form for a known binding error; it did not run the guarded
worker or coordinator. The desired retention contract failed (driver exit 1).
Including the separate **300-child** read/exit probe, it took **67.9860162 s**.
That probe missed **0/300** final lines. The terminal output handling is unchanged;
there is no claim that a read/exit race caused the native exit-1 observations.

The new worker error helper emits a fixed prefix and bounded, strict schema:
mode, attempted phase, known class, service/artifact classification or literal
harness code. Unknown class/message information becomes generic. Only a valid
five-character SQLSTATE and integer driver code from PDO within four existing
causes can enter the optional SQL field. No message parsing, stack, SQL, DSN,
parameters, credentials, paths or tokens are emitted. Consumer validation rejects
extra/missing fields, wrong types/enums, duplicate keys, controls, oversized and
noncanonical records; arbitrary JSON remains size/digest metadata. Existing
Build-only recognition remains. The record limit is 768 bytes; stream capture,
2 KiB per diagnostic and 16 KiB aggregate output limits remain unchanged.

The same shared reader now handles explicit CLAIM/RESULT alternatives and all
existing barrier tags with payload validation. It records case/PID/mode, expected
messages/exit, last successful barrier, actual observed state and recognized worker
error before propagating the original exception. Both claimers still reach READY
before release; both are released before either result is read. All owner/loser,
job identity, one-attempt/render/release and exact replay assertions are unchanged.
Cleanup preserves context and separately records first-failure and pre-cleanup
state for each child. A waiting peer has unknown exit until observed; later
termination is explicitly secondary. Intentional SIGKILL records retain context
without being relabeled unexpected. Diagnostic write failures remain secondary.
Expected audit/validation/stale results and the protected exec block are unchanged.

The new ordinary regression exercises actual shared helpers with real children,
including invalid neighboring payloads, missing/malformed/oversized/unexpected
lines, final buffered output, timeout/overflow, SQL present/absent, secret/control
canaries, both-child/peer cleanup, worker and supervisor sink failures, expected
fault RESULTs and a nonzero child impersonating a normal loser. No native PASS is
manufactured. The actual native dependency constructor/wire also succeeds with
private canonical roots and existing fake PDO. Its initial test fixture used mixed
Windows separators and correctly failed the unchanged store root contract (exit
255, **8.9691805 s**); only the test paths were corrected with realpath. That local
fixture error is not a native adapter defect. Intermediate focused passes preceded
additional result-shape, sink and control coverage; those attempts are separate.
A final control-character case failed (exit 255, **0.5923422 s**) because legacy
production CRLF normalization ran before strict worker-record parsing. Parsing
now runs first, rejecting the tainted worker record while preserving existing
production text handling. The final focused recheck below passes.

Fresh sequential local validation (PHP with `-n -d zend.exception_ignore_args=1`):

| Suite | Assertions / scenarios | Seconds | Exit |
| --- | ---: | ---: | ---: |
| M6CNativeObservation | 273 / 103; 45 real synthetic children | 10.3511309 | 0 |
| M6CNativeDiagnostics | 95 / 41 | 2.8954811 | 0 |
| M6CNativeSetup | 751 / 62 | 0.7887233 | 0 |
| M6CNativeInput | 28 | 0.5788778 | 0 |
| M6CStoredInput | 95 | 1.2952274 | 0 |
| M6CArtifact | 45 | 1.6289570 | 0 |
| M6CIntegration | 54 | 3.8953509 | 0 |
| M6BScope | 42 | 19.1974979 | 0 |
| M6CScope | 53 | 10.6748813 | 0 |
| M6CNativeLauncher | 7381 / 104 | 671.2586778 | 0 |

Final total: **ten suites / 8817 assertions**, all exit 0. These are local results,
not native acceptance. The final launcher export retained all 104 synthetic scenario
directories outside Git and removed its owned temporary root.

The first full launcher run reached **7381 assertions / 104 scenarios**, then
failed its test-only evidence export because the external runner had supplied a
destination directory that did not yet exist: **exit 255 / 700.4089729 s**.
That is a failed attempt, not a clean suite pass. The identified owned 104-scenario
fixture was preserved as `launcher-initial-evidence` outside Git, removing its
temporary source by an explicitly checked move. Only the external destination was
prepared; no launcher source, assertion, deadline or retry policy was changed.
The full recheck above obtained a clean exit and observed evidence export.

PHP lint (`php -n -l FILE` for `rg --files -g '*.php'`) passed **265 files** in
**59.5845921 s**; the two files changed for final control-character coverage were
linted again, exit 0. Git Bash `bash -n -- FILE` passed **five syntax checks** in
**0.9599351 s**: launcher, fixture and three unchanged guide blocks. No guide command
was executed. Tools: PHP **8.4.24 CLI ZTS VC++ 2022 x64**, PowerShell **7.6.5**, Git
**2.56.0.windows.2**, Node **v24.22.0**, Git Bash **5.3.15(2)**. Documentation/raw
integrity checks cover **55 links / 23 local anchors / eight balanced fence markers**,
**74 ordinary tests** against the unchanged discovery rule and **447/456** unchanged
starting tracked files. The private checker initially needed whitespace normalization
for a wrapped status phrase; the final checker passes. External links were checked
for URL syntax. Full commands, measured durations, exits, before/after reports and
working/staged/committed checks are retained in `m6c-instrumented-observation` outside Git.

Exact eleven-file inventory:

- `tests/support/WebsitePlatformM6CNativeWorkerError.php` — new pure error producer/parser and fixed enums.
- `tests/support/WebsitePlatformM6CNativeWorker.php` — attempted phases and unexpected instrumented error emission.
- `tests/support/WebsitePlatformM6CNativeDiagnostics.php` — strict record recognition, payload validation, observed alternatives and context summaries.
- `tests/support/WebsitePlatformM6CNativeProcess.php` — retain child context and separate failure/cleanup state; execution/read/deadline logic unchanged.
- `tests/support/WebsitePlatformM6CNativeReport.php` — retain cleanup context through the existing finalizer; publication unchanged.
- `tests/support/WebsitePlatformM6CNativeCases.php` — observed concurrent read and expected-alternative context; all business assertions unchanged.
- `tests/WebsitePlatformM6CNative.php` — establish child context, expected protected exit and intentional-stop evidence.
- `tests/WebsitePlatformM6CNativeObservationTest.php` — new local process/schema/protocol/adapter regression.
- `tests/WebsitePlatformM6CNativeDiagnosticsTest.php` — replace incomplete IDENTITY fixture with its actual payload shape.
- `docs/sprint-8.8-m6c-local-implementation.md` — this record.
- `docs/sprint-8.8-m6c-native-validation.md` — operator result and current diagnostic/native boundary.

Production, canonical predicate/release binding, all 20 requirements, all 25
migrations/pins, protected CLI/exec, transport publication/acknowledgement, launcher
admission/signals/finalization, image recipe and capability/deadline settings remain
unchanged. The launcher suite is rerun because its process cleanup helper and
transitive report/diagnostics dependency changed; historical launcher results stay
at their original SHAs. No automatic transport smoke or backup repeat is prescribed.
Later authorized preparation must satisfy the new reviewed SHA's image provenance;
the cached image remains source-labeled 79e6d63. Migration 025 remains applied,
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Backup/download remains operator-reported COMPLETE with both copies/receipt retained;
restore NOT EXECUTED. No server, live SQL/migrations, image/container, deployment,
installation/configuration/permission, backup/restore/prune, provider, M6D–M6G or
Narrator action; `moby-detect-rro` directories untouched. Final commit/review state
and complete commands/results are in the external task SUMMARY.txt. This is an
operator failure update, not an invented review finding. PR #128 remains open,
non-draft, unmerged and without auto-merge.

## Instrumented Git environment isolation

This local harness/regression/documentation correction starts at
`abac019a23b2801ec4d62187806f93330b9e2ff2` on the existing PR #128 branch.
**INSTRUMENTED GIT ENVIRONMENT ISOLATED / NATIVE CAUSE NOT
YET CONFIRMED**. The new local results are kept separate from the operator run and
all earlier source-specific passes and failures.

### Latest operator observation at abac019

The operator reports **one native run**, launcher exit **1**, duration
**28.662524117 seconds**, **3 PASS / 1 FAIL / 16 UNREACHED** and **673 attempted /
673 completed assertions**, including setup. Protected startup denials, multipage
build and replay passed. The multipage/replay evidence retains the source-specific
canonical input/release verification; it does not turn the native verdict into PASS.

`concurrent-claims` failed with `native_child_before_barrier`. Both instrumented
build children reached READY and retained `worker_error` with `phase=claim`,
`class=SiteServiceException`, `code=conflict`. Expected exit was 0; actual exit was
1, with no observed CLAIM or RESULT. At failure time and cleanup time both were
stopped, signal 0, with no termination requested. No typed PDO SQLSTATE/driver was
retained. The phase names an attempted operation, not a committed claim; missing
SQL diagnostics do not prove that every database operation succeeded or exclude a
database-related failure. The underlying native cause remains **UNDETERMINED**.

The operator reports PHP **8.4.27** / MySQL **8.4.11**, with all 25 migrations run
only in the disposable schema. Transport, acknowledgement, cleanup and evidence
publication passed. These passes and the failed native case retain their separate
meanings. The desktop task retrieves and independently verifies none of this evidence.

Operator evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-observation-native-20261023T015403Z-6f317a8c9142e0bd`.
Launcher evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-20261023T020049Z-699e906cf93f46f76697d239070838a9`.

| Operator-supplied record | Reported SHA-256 |
| --- | --- |
| Native report | `4e5e9eb2010ded1494c5b2e28aaf713f2fff150aa61b2a42b40eb1211e6b200b` |
| M6C-OBSERVATION-NATIVE-ACCEPTANCE.txt | `684c8ff1491235d0726f1cd5dc83d6f91bb54d6aab7968835903a08c9eda6685` |
| Operator SUMMARY.txt | `53e943e94802a54ccc9278046168efea4e700d355affe8456197119caef5d0a1` |
| Operator MANIFEST | `5de8949a5c921d6b550e0dfe70b58bde5af64654189f1b85f0203465cdb13f67` |
| Launcher MANIFEST | `0f37655b5439f8d4f39e84e61bf3e439328b66d5b8039416cae9441182e00` |

### Confirmed source difference and bounded correction

At the starting head, `M6CNativeDependencies::builderIdentity()` calls the shared
native process helper for both Git checks. That helper passes a null environment
to `proc_open`, so the instrumented Git children inherit the parent environment.
The protected production `SiteBuildRuntime::git()` instead supplies exactly
`PATH=/usr/bin:/bin`, `HOME=/nonexistent`, `GIT_CONFIG_NOSYSTEM=1`, `LANG=C`.
Both lanes require the exact expected HEAD and clean worktree. The instrumented
predicate also rejects any nonempty stderr, including when Git exits 0.

The service calls builder identity through `external()` before reading claim
candidates. That unchanged wrapper can replace a dependency RuntimeException with
generic `SiteServiceException/conflict` without preserving its cause. Its database
access and transaction guard, and earlier worker validation, remain possible
failure boundaries. The observed claim/conflict record is compatible with a source
verification failure but does not establish that this was the native origin.
Neither historical HOME nor Git stderr from those native children has been recovered.

The definitions-only `WebsitePlatformM6CNativeGitSource.php` shares the actual
caller-used verification path with the regression. The Git environment is
the protected implementation's four-key replacement, never a merge with inherited
HOME/XDG/GIT_DIR/GIT_WORK_TREE/GIT_INDEX_FILE/GIT_CONFIG overrides. The process helper
appends an optional environment argument to its existing constructor/capture
parameters; ordinary callers retain null/inheritance behavior. The native Git lane
keeps absolute `/usr/bin/git`, `--no-optional-locks`, `core.fsmonitor=false`, exact
`safe.directory`, explicit `-C`, exact HEAD, clean `--porcelain --untracked-files=all`,
zero exit and empty stderr. Output/time/process-count and cleanup bounds are retained.

The before-correction Windows driver uses the extracted unchanged predicate and
actual shared process path, with fixture-owned repositories/configuration and
temporary process environment restored in `finally`. It passes **12 assertions /
nine scenarios**, exit 0, in **4.7163092 seconds** externally measured
(4.3374983 seconds inside the driver). Those assertions confirm the defects; they
do not mark every source-verification attempt successful:

| Controlled Windows case | Actual original behavior |
| --- | --- |
| Ordinary controlled environment, clean exact repository | Accepted. |
| Malformed fixture-owned HOME, XDG or explicit global Git configuration | Source verification rejected each contaminated case. |
| Genuine untracked file, ordinary control | Rejected. |
| Inherited `GIT_CONFIG_COUNT` configuring fixture `core.excludesFile` | Falsely accepted the same repository after hiding its untracked file. |
| Tracked modification in the intended target, ordinary control | Rejected. |
| Inherited `GIT_DIR`, `GIT_WORK_TREE` and `GIT_INDEX_FILE` pointing to a clean foreign fixture repository with the same HEAD | Falsely accepted verification of the dirty target by observing the foreign repository. |
| Actual public `claimBuild()` with fake PDO and the malformed-HOME source dependency | Threw `SiteServiceException/conflict`, with null previous exception and zero query statements observed. |

The final case executes the real production wrapper with fake PDO; it neither
opens a real database nor establishes what happened in the native run. Raw local
before evidence and measured results are retained in `before-reproduction.log` and
`before-run.json` under external `m6c-git-environment`. No real user Git configuration
or credentials were edited. These Windows defects demonstrate unsafe environment
inheritance independently of the unconfirmed native permission-warning hypothesis.

The corrected source regression passes **113 assertions / 49 scenarios**, exit 0,
in **18.0079252 seconds**. Actual installed Windows Git accepts the clean exact
fixture under the explicit profile, ignores the owned inherited HOME/XDG/global/
count-based configuration contaminants and cannot be redirected by foreign
repository/worktree/index settings. Wrong expected SHA, tracked modifications,
genuine untracked files and an actual nonzero Git exit remain rejected. Parent
environment keys/values are restored exactly and only owned fixtures are cleaned.

The actual Windows Git profile is exactly `PATH=C:/Git/cmd`, HOME at the owned empty
fixture home, `GIT_CONFIG_NOSYSTEM=1`, `LANG=C`; no SystemRoot/TEMP additions were
needed in this run. This is a Windows test profile, not the Linux native contract.
The helper's exact Linux four-key default and unchanged Git arguments are separately
forwarded through two real synthetic PHP children. Ordinary process calls with
the default null environment still inherit, while an explicit replacement excludes
parent overrides. No process-global environment mutation implements the harness fix.

Real synthetic children model exit-0/nonempty-stderr results separately at HEAD and
status; both remain rejected. These model warning behavior and are not a Windows
or Linux permission-failure reproduction. Existing 10-second and 65,536-byte
subprocess bounds reject timeout and overflow, with owned cleanup preserved.

The separately supplied Linux/Git 2.47.3 mechanism probe is **not this desktop
task's result and not a reproduction of the native run**. It used a root-owned
synthetic repository and UID/GID 2000 with capabilities dropped; inaccessible HOME
produced attributes/ignore permission warnings on stderr despite status exit 0
and empty stdout. An explicit clean environment removed those warnings. That probe
did not run this service or establish the droplet's historical environment/error.
Windows configuration-contamination results and any modeled permission behavior
must remain distinct from that supplied Linux evidence.

### Safe source observation before generic wrapping

The bounded test-only helper reuses the strict worker-error protocol with fixed
`source_head` / `source_status` phases and typed source facts: observed exit,
`stderr_nonempty` and expected-output match. Diagnostics retain the first source
observation separately as `source_error`, preserving an outer `worker_error` such
as claim/conflict. Coordinator request preparation defaults to coordinator mode;
the instrumented worker supplies its actual validated mode. A source observation
is emitted before rethrowing the original dependency exception where possible;
diagnostic failure must remain secondary.

Unobserved source facts remain null. The source object has exactly `exit`,
`stderr_nonempty` and `matched`; the latter two accept only booleans or null. The
existing 768-byte strict record limit, canonical encoding, fixed enums and
2 KiB individual / 16 KiB aggregate diagnostic limits remain. Unknown or malformed
records are bounded metadata. No raw Git stderr, paths, environment, command lines,
configuration, arbitrary exception text, SQL or secret/control-character payload
belongs in the record.
The production wrapper and public error semantics remain untouched; a useful
separate dependency observation does not convert conflict to success.

The fake-PDO public claim regression now deliberately supplies the wrong expected
SHA through the real shared verifier. `claimBuild()` still throws generic conflict
with null previous cause and zero query statements. Its emitted dependency record
survives as `source_error/source_head`; the existing worker-error producer encodes
the caught actual service exception for the synthetic protocol check, and the
parser retains it separately as outer claim/conflict `worker_error`. Both records
survive changes of stream/order. This proves the local wrapper/diagnostic boundary,
not execution of the guarded native worker on Windows.

The 49 source scenarios also cover coordinator/unknown modes, typed null facts for
a modeled pre-start failure, malformed neighboring schemas, controls, noncanonical
wire forms, secret/path/configuration canaries and throwing/false/short-write sinks.
Failed diagnostic emission leaves the original dependency exception primary.
The fresh observation suite passes its concurrent synthetic child protocols, including
normal winner/loser behavior and failed losers; neither retries nor serialized
claimers are introduced. These remain local process tests, not native race proof.

### Fresh local validation, exact inventory and remaining gate

Fresh sequential local commands use
`php -n -d zend.exception_ignore_args=1 tests/WebsitePlatform<Suite>Test.php`.
Actual commands and measured results are retained in external
`m6c-git-environment/regression-runs.json` with per-suite stdout/stderr.

| Suite | Assertions / scenarios | Seconds | Exit |
| --- | ---: | ---: | ---: |
| M6CNativeGitSource | 113 / 49 | 18.0079252 | 0 |
| M6CNativeObservation | 273 / 103; 45 real synthetic children | 9.5465505 | 0 |
| M6CNativeDiagnostics | 95 / 41 | 2.8116687 | 0 |
| M6CNativeSetup | 751 / 62 | 0.7956275 | 0 |
| M6CNativeInput | 28 | 0.5926373 | 0 |
| M6CStoredInput | 95 | 0.9999495 | 0 |
| M6CArtifact | 45 | 1.2929787 | 0 |
| M6CIntegration | 54 | 3.2549900 | 0 |
| M6BScope | 42 | 14.2324545 | 0 |
| M6CScope | 53 | 8.7685557 | 0 |
| M6CNativeLauncher | 7,382 / 104 fake-command scenarios | 676.7201238 | 0 |

All **11 sequential suites pass 8,931 assertions**, each exit 0 on its first run.
The synthetic launcher uses fake commands; it executes no native/container/MySQL
acceptance. Its evidence export is retained under
`m6c-git-environment/launcher/ubo-m6c-native-test-f7ee2bf29eb7f0f0`.
The separate before-correction defect probe also exited 0; its assertions document
the original defects and are not added to this regression total.

PHP lint (`php -n -l FILE`) passes **268 files**, exit 0, in **65.7676048 seconds**.
Git Bash `bash -n -- FILE` passes **five syntax checks**, exit 0, in **0.9484133
seconds**: the launcher, its fixture and three unchanged guide blocks. No guide
command is executed. Tools observed: PHP **8.4.24 CLI ZTS VC++ 2022 x64**, Git
**2.56.0.windows.2**, PowerShell **7.6.5**, Node **v24.22.0**, Git Bash **5.3.15(2)**.
An initial restricted-context Bash version probe failed with `0xC0000022`; it ran
no application test. The authorized normal-context version probe exited 0 in
0.1520588 seconds, with the first attempt retained separately in
`versions-sandbox-attempt.json`. No application suite failed or needed a recheck.

Exact correction inventory: **ten files, seven modified and three added**.

| Change | File | Purpose |
| --- | --- | --- |
| Added | `tests/support/WebsitePlatformM6CNativeGitSource.php` | Shared actual Git verification, exact replacement environment and safe pre-wrapper observation. |
| Added | `tests/support/WebsitePlatformM6CGitFixture.php` | Disposable repositories/configuration, reversible process environment and owned cleanup for local reproduction. |
| Added | `tests/WebsitePlatformM6CNativeGitSourceTest.php` | Actual Git contamination/isolation, strict predicate, wrapper, diagnostic and bounds regressions. |
| Modified | `tests/support/WebsitePlatformM6CNativeProcess.php` | Append optional environment forwarding; preserve null inheritance for ordinary callers. |
| Modified | `tests/support/WebsitePlatformM6CNativeFixture.php` | Use shared verifier and default coordinator observation mode. |
| Modified | `tests/support/WebsitePlatformM6CNativeWorker.php` | Pass the actual validated instrumented mode to the adapter. |
| Modified | `tests/support/WebsitePlatformM6CNativeWorkerError.php` | Fixed source phases and typed bounded source facts in the strict protocol. |
| Modified | `tests/support/WebsitePlatformM6CNativeDiagnostics.php` | Retain the first source error separately from the outer worker error. |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` | Latest evidence, confirmed difference, local correction and remaining uncertainty. |
| Modified | `docs/sprint-8.8-m6c-native-validation.md` | Current operator result and source-specific later runtime handoff. |

Raw integrity comparison against abac019 verifies **451 of 458 starting tracked
files unchanged**, with only the seven declared tracked edits and three additions.
All **237 production-path files** and **25 migrations** are byte-identical, including
the canonical validator correction, protected CLI/runtime and claim/lease behavior;
migration pins remain unchanged. All 20 native requirements, canonical native
input/release binding, report transport/acknowledgement, launcher isolation/limits/
deadlines/finalization and the image recipe remain intact. The three Bash guide
blocks are unchanged. Ordinary discovery contains **75 tests** under the unchanged
discovery rule. Documentation checks cover **58 links / 26 local anchors / eight
balanced fence markers**; external URLs are checked for syntax, not fetched.
Final working/staged/committed check evidence and observed commit/review state will
be retained with the external `SUMMARY.txt`.

At this historical correction, the operator-reported runtime image belonged to
**abac019**. Subsequent runtime work needed new reviewed-source provenance, then a focused source-identity check
under the actual UID/runtime environment, followed by separately authorized native
acceptance. No image is built, pulled or relabeled here. Unchanged transport smoke
and backups are not automatically repeated. The native cause remains unconfirmed;
this correction makes no claim that concurrency is fixed or native acceptance passed.

Last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Migration 025 remains applied to working
staging with canonical SHA-256
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Backup/download remains operator-reported COMPLETE, both copies and receipt retained;
restore NOT EXECUTED. All `moby-detect-rro` directories remain untouched. This task
performs zero server, real SQL/migration, image/container, deployment, installation,
host configuration/privilege, backup/restore/prune, provider or Narrator operations.
M6D–M6G remain untouched. PR #128 stays open, non-draft and unmerged without auto-merge.
The operator-failure update, exact-new-head review request and observed review state
will be recorded in the PR and external `SUMMARY.txt`, without an invented review
finding, conversation resolution or unobserved clean-review claim.
