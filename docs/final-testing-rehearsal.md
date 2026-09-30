# 3A TrackPro Final Testing & Rehearsal

## 1. Purpose

This is the checklist and result record for Final Testing & Rehearsal.
[Project Tracker](project-tracker.md) remains authoritative for phase status.
Reuse established release correctness evidence and verify presentation readiness
using the [Demo Preparation run sheet](demo-preparation.md). Keep the
read-only-first strategy and feature freeze; only critical defects should reopen
implementation through a separately scoped task.

**Phase closeout: PENDING.** Development-machine preflight does not complete
the actual presentation-machine check or human group rehearsal.

## 2. Baseline

| Item | Recorded value |
| --- | --- |
| Git baseline before this task | `74da207ef67e416131ab901847ceb59da81197b2` |
| Branch / remote before this task | `main`; HEAD = origin/main = baseline |
| Worktree / index before this task | Clean |
| Preflight date | October 1, 2026 |
| Preflight machine | Current Linux development machine — DEVELOPMENT-MACHINE PREFLIGHT |
| Final presentation machine | TO BE CONFIRMED DURING REHEARSAL |
| Presentation | [TrackPro_Final_Presentation.pptx](presentation/TrackPro_Final_Presentation.pptx) |
| Slides | 14 |
| PPTX SHA-256 | `e7df9d94f052ea4ef53d6f450076c252c005f35caf2518c14aba865c34915a65` |
| Presentation database | `trackpro_demo` |
| Local URL | `http://127.0.0.1:8016` |

No credentials are recorded. The final machine must have the approved frozen
application and final presentation artifacts; record its actual revision and
repeat the bounded smoke checks there before accepting readiness.

## 3. Existing Evidence Reused

These are retained results, not tests run by this preflight. Keep totals separate.

| Evidence | Existing result |
| --- | --- |
| FT18 current-scope manual validation | 19 Pass / 0 Fail / 0 Blocked / 0 Not Run / 19 Total |
| Ordinary SQLite suite | PASS — 558 tests / 6,112 assertions |
| Reports suite | PASS — 52 tests / 752 assertions |
| Same-revision PO edit verification | PASS — 3 / 3 focused repeats |
| Containing guarded MySQL class | PASS — 5 tests / 178 assertions |
| Historical FT17 | 8 Pass / 1 Fail / 0 Blocked / 28 Remaining; `TC-AUTH-006` remains historical FAIL |

Feature freeze is declared and Integration & Bug Fixing is complete. Historical
FT17 is not reinterpreted as a current implementation failure.

## 4. Regression Rerun Decision

| Check | Decision | Performed in this task? |
| --- | --- | --- |
| Full SQLite suite | NOT REQUIRED | No |
| Reports suite | NOT REQUIRED | No |
| Guarded MySQL concurrency suite | NOT REQUIRED | No |
| FT18 | NOT REQUIRED | No |
| Browser mutation testing | NOT REQUIRED | No |
| npm build | OPTIONAL only if presentation assets are missing or stale | No; current manifest-listed assets exist and return HTTP 200 |

The frozen build has existing passing release evidence. This phase checks
presentation readiness. A newly discovered defect or an authorized implementation
change must determine its own relevant recheck scope.

## 5. Technical Preflight

**DEVELOPMENT-MACHINE PREFLIGHT ONLY.** PASS below applies only to checks actually
performed on the current Linux machine. All final-machine results remain
**NOT VERIFIED ON FINAL PRESENTATION MACHINE**. Human checks are pending.

| Check | Result | Evidence / Note |
| --- | --- | --- |
| Git baseline correct | PASS | HEAD and origin/main both match §2 baseline; branch main |
| Git worktree/index clean before task | PASS | No modified, staged, or untracked files before work |
| Presentation PPTX exists | PASS | Exact §2 path; 3,393,169 bytes |
| PPTX integrity valid | PASS | `file` identifies ZIP; `unzip -t` succeeds; SHA-256 matches §2 |
| Expected 14 slides present | PASS | 14 `ppt/slides/slideN.xml` entries counted read-only |
| Approved screenshot fallback directory exists | PASS | `docs/images/user-guide/`; 18 PNGs present |
| PHP available | PASS | PHP CLI 8.4.26 |
| Laravel project dependencies available | PASS | `vendor/autoload.php` present; Laravel boot succeeds; framework 13.30.1 |
| MySQL reachable | PASS | PDO MySQL connection established using private existing configuration |
| `trackpro_demo` accessible | PASS | `SELECT DATABASE()` confirms only `trackpro_demo` |
| Migration status aligned/read-only | PASS | SELECT of applied migrations matches all 19 repository migrations; 0 pending / 0 extra; no migrations executed |
| Laravel server starts with demo override | PASS | Task-owned server started on 127.0.0.1:8016; stopped after GET checks |
| Local URL responds | PASS | Root resolves to login; `/login` HTTP 200; username/password inputs present |
| Frontend assets present | PASS | Build manifest and both listed files exist; CSS and JavaScript GETs return HTTP 200 |
| Browser can load application | HUMAN CHECK REQUIRED | No connected browser available; HTTP checks are not browser verification |
| No obvious missing asset/layout problem | HUMAN CHECK REQUIRED | Asset delivery passed; visual layout not inspected |
| `demo_admin` authentication | HUMAN CHECK REQUIRED | No authorized private credentials/session used; no login submitted |
| `demo_staff` authentication | HUMAN CHECK REQUIRED | No authorized private credentials/session used; no login submitted |
| Admin Dashboard available | HUMAN CHECK REQUIRED | Authenticated navigation not performed |
| Purchase Orders available | HUMAN CHECK REQUIRED | Authenticated navigation not performed |
| POS available | HUMAN CHECK REQUIRED | Authenticated navigation not performed; do not open register or submit sale |
| Reports available | HUMAN CHECK REQUIRED | Authenticated navigation not performed |
| Staff Dashboard available | HUMAN CHECK REQUIRED | Authenticated navigation not performed |
| Movement History available | HUMAN CHECK REQUIRED | Page unverified; database contains the five expected movement types |
| Plywood stock / threshold / coverage | PASS | Read-only snapshot: 2.000 / 5.000 sheets; 0.000 open coverage; low and uncovered |
| G.I. Pipe stock / threshold / coverage | PASS | Read-only snapshot: 4.500 / 5.000 m; 7.000 open coverage; low and covered |
| Register remains Closed | PASS | 0 cash-register sessions with `active_slot = 1` |
| PO #2 retains 7.000 m outstanding | PASS | Pending PO #2; ordered minus accepted and transferred quantities = 7.000 using exact decimal arithmetic |
| TRX-000001 remains Completed | PASS | Sale ID 1 is completed; total 500.00 |
| TRX-000002 remains Voided | PASS | Sale ID 2 is voided |
| Representative report data present | PASS | Read-only source counts: 2 sales, 2 POs, 2 damage items |
| Representative reports remain available in UI | HUMAN CHECK REQUIRED | Data presence does not verify page rendering or selected date range |
| PPTX opens locally in presentation software | HUMAN CHECK REQUIRED | Structural validation passed; slideshow not opened |

Database checks ran inside `START TRANSACTION READ ONLY`, verified the database
name before inspecting business records, and ended with ROLLBACK. Movement types
were `INITIAL_STOCK`, `RESTOCK`, `SALE`, `CORRECTION`, and `SALE_VOID`.
No business writes, schema changes, fixture recreation, or protected-database
access occurred. HTTP checks used GET only; runtime sessions used file storage.

The initial sandbox connection could not reach MySQL. The approved outside-sandbox
run connected successfully. Two temporary inspection-script query mistakes
(a missing relationship column and an incorrect movement column name) were
corrected; the final complete read-only run succeeded. These were preflight
script defects, not application defects or regression-test results.

Server startup used the run sheet's port 8016 pattern with explicit
`DB_CONNECTION=mysql`, empty `DB_URL`, and file session/cache overrides to keep
runtime storage away from business tables. The port was free before startup;
only the task-owned server was stopped. Do not substitute the generic Windows
setup guide's port 8015 for the approved presentation URL.

## 6. Presentation-Machine Checklist — HUMAN

Use [Windows 11 Demo Laptop Setup](windows-11-demo-setup.md) for setup guidance;
use the demo run sheet's database and port 8016 startup pattern. Complete these
on the actual presentation machine and repeat §5's bounded smoke checks there.

- [ ] Final presentation laptop identified and actual Git revision recorded.
- [ ] Charger/power ready.
- [ ] PHP/Laravel available, including BCMath and PDO MySQL.
- [ ] MySQL available.
- [ ] Project and locked dependencies available.
- [ ] Built frontend assets available.
- [ ] `trackpro_demo` available; prepared records intact.
- [ ] Presentation PPTX stored locally and opens in presentation software.
- [ ] Fallback screenshots stored locally and accessible.
- [ ] Browser available; display and zoom suitable for the audience.
- [ ] Local URL works at `http://127.0.0.1:8016`.
- [ ] Admin login works privately; required Admin pages load.
- [ ] Staff login works privately; required Staff pages load.
- [ ] Normal demo works without internet, if intended; record the actual check.
- [ ] No terminal, Developer Tools, credentials, or secrets visible to audience.
- [ ] Backup/fallback strategy confirmed privately; no dump or credentials committed.

Do not reset, import over, or recreate any protected database to complete this
checklist. Any missing installation or data-transfer prerequisite requires a
separately authorized preparation task.

## 7. Full Rehearsal Run — HUMAN

| Field | Value |
| --- | --- |
| Rehearsal attempt | TBD |
| Date | TBD |
| Laptop operator | TBD |
| Final presentation laptop / revision | TBD |
| Optional User Management / Audit Logs inclusion | TBD; optional read-only scope; omit if not selected |

Preflight/startup and opening the deck precede this sequence:

1. Layupan — Opening / Project Overview.
2. Wariza — Admin Inventory & Procurement.
3. Largo — Admin POS / Sales / Sale Void evidence.
4. Admin logout → Staff login, with credentials entered privately.
5. Amores — Staff Workflow / Movement History.
6. Casipong — Reports screenshots / Testing / Closing.
7. Q&A practice.

Follow the detailed demo run sheet without submitting business mutation forms.
Keep Admin work together; the planned flow has one Admin → Staff transition.
Casipong uses approved report screenshots to avoid another role switch.

**INTERNAL ONLY:** approximately 12–15 minutes before Q&A. No fixed instructor
cutoff was communicated; explain the system fully without rushing. Segment
targets in the run sheet are approximate and must be reconciled using actual
elapsed time. Q&A is recorded separately from the structured target.

| Timing / observation | Recorded result |
| --- | --- |
| Layupan elapsed time | TBD |
| Wariza elapsed time | TBD |
| Largo elapsed time | TBD |
| Amores elapsed time | TBD |
| Casipong elapsed time | TBD |
| Total structured time | TBD |
| Q&A time, separate | TBD |
| Navigation delays / long pauses | TBD |
| Repeated explanations | TBD |
| Overall flow result | PENDING |
| Final group feedback / follow-up | TBD |

## 8. Handoff Checklist — HUMAN

- [ ] Layupan → Wariza.
- [ ] Wariza → Largo.
- [ ] Largo → Amores.
- [ ] Amores → Casipong.
- [ ] Casipong → Q&A.
- [ ] One Admin → Staff role transition practiced.
- [ ] Credentials entered privately.
- [ ] No unnecessary role switching.
- [ ] Laptop operator coordinates navigation with the speaker.

## 9. Q&A Readiness — HUMAN

Every presenter practices basic questions for their assigned section and
understands the overall flow. The relevant presenter answers first; Wariza is
the secondary technical backup for deeper or cross-module questions. Use short
prompts rather than memorized scripts.

- [ ] Layupan: project purpose, objectives, intended users, Admin vs Staff.
- [ ] Wariza: inventory quantities/DECIMAL arithmetic, low-stock logic, PO
      prioritization, partial receiving, damage, and follow-up PO lineage.
- [ ] Largo: opening cash, Sale Void, stock restoration, and preserved history.
- [ ] Amores: authorization/Staff boundaries and Movement History.
- [ ] Casipong: reports, testing strategy, and historical FT17 vs current FT18.
- [ ] Technical handoff practiced when needed: Laravel MVC, database
      transactions, authorization, concurrency, and stale revision protection.
- [ ] Every member knows their preceding/following handoff and can explain
      basic questions about their own demonstrated features.

## 10. Fallback / Recovery Rehearsal — HUMAN

Fallback path: `docs/images/user-guide/`. A single safe simulated fallback
during group rehearsal is sufficient; record the scenario and result.

- [ ] Presenter switches to an approved screenshot if a live page fails.
- [ ] Stopped-server recovery understood using the approved demo startup.
- [ ] Expired-session recovery understood.
- [ ] Wrong-role recovery understood: sign out and sign in privately.
- [ ] Internet-loss strategy understood; local deck/screenshots accessible.
- [ ] Prepared-data-change response understood: preserve state, use screenshots,
      and record the issue; no reset or history rewrite.
- [ ] No destructive reset used.
- [ ] No live debugging in front of the audience.
- [ ] Failed live action is never falsely described as successful.

Simulated scenario: **TBD**. Result: **PENDING**. Do not deliberately mutate demo
data or perform destructive failure simulation.

Never use `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `DROP`,
`TRUNCATE`, destructive SQL, fixture recreation, or test data insertion. Do not
mutate `trackpro_local`, `trackpro_demo`, `trackpro_test`, `trackpro_ft17_test`,
or `trackpro_24e_test`.

## 11. Issues Found

No rehearsal issues recorded yet. Rehearsal has not occurred; this is not a
claim that no issues exist. The temporary script corrections are documented in
§5 and did not require application changes.

| ID | Issue | Severity | Owner | Resolution | Recheck | Presentation blocker? |
| --- | --- | --- | --- | --- | --- | --- |
| — | No rehearsal issues recorded yet | — | — | — | — | Unknown until rehearsal |

Record genuine defects and preserve failing evidence. Only critical defects
may reopen implementation through separate authorization; recheck the affected
scope after a fix.

## 12. Human Rehearsal Result

| Item | Result |
| --- | --- |
| Full group rehearsal | PENDING |
| Timing reviewed | PENDING |
| Speaker handoffs | PENDING |
| Q&A responsibilities | PENDING |
| Fallback path exercised | PENDING |
| Final presentation machine verified | PENDING |
| Unresolved presentation blocker | UNKNOWN UNTIL REHEARSAL |
| Group readiness decision | PENDING |

## 13. Closeout Criteria

Final Testing & Rehearsal can close only when all are evidenced:

- [ ] Final presentation machine passes bounded smoke checks.
- [ ] Admin/Staff access required by the demo works.
- [ ] Prepared demo data is intact.
- [ ] PPTX opens locally.
- [ ] Approved screenshots are locally accessible.
- [ ] Complete speaker flow rehearsed.
- [ ] Handoffs rehearsed.
- [ ] Timing reviewed.
- [ ] Q&A responsibility understood.
- [ ] Fallback path exercised.
- [ ] No unresolved presentation-blocking defect.
- [ ] Group agrees ready for Final Presentation.

Internal group readiness acceptance is sufficient; no instructor/client approval
is required for this phase. Tracker status remains unchanged by this record's
preparation: 69 Completed / 0 In Progress / 2 Not Started / 71 Total.

## 14. Final Presentation Boundary

Final Presentation remains a separate Not Started phase. It owns the actual
presentation/defense, delivery to the instructor/panel, and any required
post-presentation/submission closeout. Rehearsal does not complete that phase.
