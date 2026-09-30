# 3A TrackPro Demo Preparation

**Project:** 3A TrackPro: Hardware Store Sales and Inventory Management System

**Team:** The Visionaries

**Members** (current documented order):

- Wariza
- Layupan
- Casipong
- Largo
- Amores

**Core application:** FEATURE FROZEN

**Current preparation status:** Demo Preparation deliverables are complete. The
final presentation deck and demo plan passed internal project-group review.
Demo Preparation is ready to close; actual dry runs and rehearsal remain in
Final Testing & Rehearsal.

## 1. Demo Principles

### READ-ONLY FIRST

Prefer prepared records and read-only evidence during the formal demo. Do not
consume or alter the stable demo dataset unless a specific live action has been
intentionally approved. If an action could change important prepared evidence,
show the existing record or an approved screenshot instead.

- Use `trackpro_demo` only. Never run the presentation against
  `trackpro_local`.
- Keep the prepared demo state intact for rehearsal and presentation.
- Never expose passwords, database credentials, tokens, terminal secrets, or
  private configuration in this document, slides, screenshots, or presentation
  output.
- Avoid destructive database commands and direct edits to business history.

## 2. Demo Environment

The approved local presentation environment is:

- **Database:** `trackpro_demo`
- **Local server:** <http://127.0.0.1:8016>
- **Admin account:** `demo_admin` — Admin
- **Staff account:** `demo_staff` — Staff

Start or restart Laravel with the approved runtime overrides:

```bash
DB_DATABASE=trackpro_demo \
SESSION_COOKIE=trackpro_demo_session \
APP_URL=http://127.0.0.1:8016 \
APP_DEBUG=false \
php artisan serve --host=127.0.0.1 --port=8016 --tries=1
```

Credentials must be stored and entered privately. Never place them in this
file, slides, screenshots, or material visible to the audience.

## 3. Pre-Demo Checklist

- [ ] Presentation machine is powered and ready.
- [ ] MySQL is running.
- [ ] The `trackpro_demo` database is available.
- [ ] Laravel is started with the `trackpro_demo` runtime override above.
- [ ] <http://127.0.0.1:8016> opens the application.
- [ ] Private sign-in checks succeed for `demo_admin` and `demo_staff`.
- [ ] Browser zoom and display are usable from the audience's viewing position.
- [ ] Developer Tools and terminals are closed or hidden from the audience.
- [ ] Prepared demo records listed below are intact.
- [ ] Register is **Closed**.
- [ ] PO #2 retains **7.000 m** outstanding demand.
- [ ] TRX-000001 is Completed; TRX-000002 is Voided.
- [ ] Reports contain representative rows.
- [ ] The screenshot fallback directory is accessible locally.
- [ ] Presentation slides are available locally, including without internet.
- [ ] Laptop power supply/charger is available.
- [ ] Normal local operation does not assume internet after dependencies and
      assets have been prepared; confirm this during Final Testing & Rehearsal.

The Windows laptop and its imported private demo database must be prepared and
checked using [Windows 11 Demo Laptop Setup](windows-11-demo-setup.md). This
run sheet does not claim that offline operation has already been rehearsed.

## 4. Prepared Demo Data

Use these identifiers to locate existing evidence. Do not edit records to make
them match this list during a presentation.

**Accounts:** `demo_admin` (Admin), `demo_staff` (Staff). Passwords are private
and are not recorded here.

**Catalog:** Plywood, G.I. Pipe, Common Nail, and Claw Hammer.

| Variant | Current stock / threshold | Prepared state |
| --- | --- | --- |
| Plywood | 2 / 5 sheets | Low stock; uncovered by an open PO |
| G.I. Pipe | 4.500 / 5.000 m | Low stock; covered by open procurement |
| Common Nail | 15 / 4 kg | Representative stock |
| Claw Hammer | 9 / 3 pieces | Representative stock |

**Procurement:**

- **Parent PO #1 — Demo Supplier A:** partial receiving evidence and damage
  evidence; remaining quantity was transferred; current outstanding quantity is
  0.
- **Follow-up PO #2 — Demo Supplier B:** parent/source relationship is visible;
  current outstanding quantity is 7.000 m.
- **Damage-only evidence:** RST-000002.

**Sales and register:**

- TRX-000001 — Completed — ₱500.00.
- TRX-000002 — Voided; reason: “Transaction entered twice during demonstration.”
- Register — Closed.

Movement History includes Opening Inventory, Stock In, Sale, Stock Correction,
and Sale Void evidence.

## 5. Live Versus Read-Only Strategy

| Feature / Evidence | Role | Mode | Reason |
| --- | --- | --- | --- |
| Sign in and role-labelled navigation | Admin, then Staff | LIVE NAVIGATION | Demonstrates authentication and role boundary without changing business records. |
| Admin Dashboard | Admin | READ-ONLY / LIVE NAVIGATION | Show prepared summaries and recent activity; avoid actions that change the dataset. |
| Product and Variant details | Admin | READ-ONLY | Show saved stock, units, and thresholds. |
| PO low-stock prioritization | Admin | READ-ONLY | Open the PO creation screen if useful, but do not submit a new PO. |
| Parent PO #1 receiving and damage evidence | Admin | READ-ONLY | Preserves accepted, damaged, and transferred history. |
| Follow-up PO #2 | Admin | READ-ONLY | Show source lineage and the 7.000 m outstanding quantity. |
| Pending Purchase Orders report | Admin | READ-ONLY | Existing report data demonstrates open procurement. |
| Unfulfilled Items report | Admin | READ-ONLY | Existing report data demonstrates outstanding demand. |
| Damaged Items report | Admin | READ-ONLY | Existing report data demonstrates damage evidence. |
| POS opening cash | Admin | READ-ONLY | Show the closed-register state/form; do not open the register. |
| Completed receipt and Sales History | Admin | READ-ONLY | Use TRX-000001 and its preserved receipt. |
| Sale Void | Admin | READ-ONLY | Show TRX-000002; do not void another transaction. |
| Reports hub / Sales Summary | Admin | READ-ONLY | Show the prepared report state without changing sale or report totals. |
| User Management | Admin | OPTIONAL READ-ONLY | Include only if time and group plan allow; do not change an account. |
| Audit Logs | Admin | OPTIONAL READ-ONLY | Include only if time and group plan allow. |
| Staff Dashboard | Staff | READ-ONLY | Show shared content and Staff navigation. |
| Stock In form | Staff | READ-ONLY | Open the form if useful; do not submit a receipt. |
| Movement History | Staff | READ-ONLY | Show recorded movement evidence without changing it. |

Do not make live mutation the default. Opening the register, completing a sale,
voiding a sale, creating or receiving a PO, recording Stock In, or changing an
account can alter prepared state or report results.

## 6. Recommended Demo Flow

Use one business story and keep Admin work together. Navigate existing records;
do not submit forms during the formal demo.

### Opening — Layupan: Project Overview

Introduce the project title, problem/context, purpose, objectives, intended
users, and Admin/Staff roles. Give a short introduction to TrackPro, then hand
off to Wariza for the inventory and procurement workflow.

### Segment A — Admin: Inventory and Procurement

**Presenter: Wariza**

1. Sign in as Admin.
2. Show the Dashboard and role-labelled navigation.
3. Open Product/Variant details for Plywood and G.I. Pipe.
4. Explain the complementary low-stock states: Plywood is uncovered; G.I. Pipe
   is already covered by open procurement.
5. Open the PO creation screen read-only to show low-stock prioritization.
6. Open Parent PO #1 and explain the ordered quantity, partial accepted
   receiving, damage evidence, damage-only receipt RST-000002, and transfer of
   remaining demand.
7. Open Follow-up PO #2 and show its parent/source relationship and 7.000 m
   outstanding quantity.
8. Show the Pending Purchase Orders, Unfulfilled Items, and Damaged Items
   reports.

This segment covers PO prioritization, PO-based delivery/receiving, partial
receiving, follow-up procurement, outstanding demand, and damage evidence and
reporting.

### Segment B — Admin: Sales and Control

**Presenter: Largo**

9. Show the closed-register POS/opening-cash state without submitting. Explain
   that opening cash is register state, not sales revenue.
10. Show the TRX-000001 completed receipt and Sales History.
11. Show TRX-000002 as Voided. Explain that the original transaction is
    preserved, stock was restored, and the reason, actor, and time are retained.
12. If selected by the group, show User Management and Audit Logs read-only.
13. Hand off to Amores for the Staff daily-operation view.

Do not open the register, create another sale, or void another transaction.

### Segment C — Staff: Daily Operations

**Presenter: Amores**

14. Sign out of Admin and sign in as Staff.
15. Show the Staff Dashboard and its role difference from Admin.
16. Open the Stock In form read-only.
17. If useful, show PO detail/receiving access without submitting a receipt.
18. Show Movement History and point out the absence of Admin-only navigation
    and actions.

After Segment C, hand off to Casipong for reports, testing evidence, and the
closing summary. Avoid further role switching.

### Closing — Casipong: Reports, Quality Evidence, and Q&A

Use the approved screenshot evidence to show how the Reports hub groups Sales,
Inventory, and Procurement, and point to representative Sales, Inventory, and
Procurement reports. This avoids another role switch after the Staff segment.
Summarize the validation evidence and feature-frozen stable release-candidate
state, then invite instructor questions.

Useful report images include `11-reports-sales-summary.png`,
`12-inventory-report.png`, `13-pending-po-report.png`,
`14-unfulfilled-items-report.png`, and `15-damaged-items-report.png` from
`docs/images/user-guide/`.

Concise evidence for the closing may include:

- FT18 current-scope validation: 19 / 19 Pass.
- Ordinary SQLite: 558 tests / 6,112 assertions PASS.
- Same-revision PO edit verification: 3 / 3 focused repeats PASS; its
  containing guarded MySQL class passed 5 tests / 178 assertions.

Keep these results separate; do not combine them into a grand total.

**Speaking handoffs** should be short cues, not memorized scripts:

- **Layupan → Wariza:** introduce the inventory/procurement workflow after the
  project overview.
- **Wariza → Largo:** move from procurement/report evidence to daily sales and
  register operations.
- **Largo → Amores:** move from Admin-side sales controls to Staff daily
  operations.
- **Amores → Casipong:** move from Staff workflow and Movement History to
  reports, verification, and closing.
- **Casipong → Q&A:** summarize the system and invite instructor questions.

## 7. Teacher-Scope Coverage

| Teacher-requested item | Demo step | Evidence |
| --- | --- | --- |
| POS opening cash | Segment B, step 9 | Closed-register POS/opening-cash state |
| Create PO prioritizing low inventory | Segment A, steps 3–5 | PO creation screen; Plywood uncovered and G.I. Pipe covered |
| Delivery/receiving based on PO | Segment A, step 6 | Parent PO #1 receiving history |
| Partial receiving | Segment A, step 6 | Accepted quantity in PO #1 receiving evidence |
| Follow-up PO for unfulfilled quantity | Segment A, step 7 | PO #2 source lineage and 7.000 m outstanding |
| Pending PO report | Segment A, step 8 | Pending Purchase Orders report |
| Unfulfilled Items report | Segment A, step 8 | Unfulfilled Items report |
| Damaged-item recording and report | Segment A, steps 6 and 8 | PO #1 damage evidence / RST-000002 and Damaged Items report |

## 8. Speaking Assignments

The group has confirmed these balanced primary responsibilities:

| Segment / responsibility | Assigned member | Topics |
| --- | --- | --- |
| Opening / Project Overview | Layupan | Project title, problem/context, purpose, objectives, intended users, Admin/Staff overview, introduction, and handoff. |
| Admin Inventory & Procurement | Wariza | Dashboard, Plywood and G.I. Pipe stock/coverage, PO prioritization, PO #1 receiving/damage, PO #2 lineage, and Pending PO, Unfulfilled Items, and Damaged Items reports. Wariza anchors this interconnected workflow based on deep end-to-end project and technical context. |
| POS / Sales / Sale Void | Largo | Closed register, opening-cash concept, TRX-000001 receipt and history, and TRX-000002 void status, preserved details, reason, and stock-restoration concept. |
| Staff Workflow / Movement History | Amores | Staff login/dashboard, role boundary, Stock In form, useful PO access, and Movement History. |
| Reports / Testing / Closing / Q&A Support | Casipong | Reports organization and representative reports, concise testing evidence, feature-freeze/release-candidate summary, closing, and question invitation. |

Team members available for assignment, in documented order:

- Wariza
- Layupan
- Casipong
- Largo
- Amores

Each member must understand the full system even if responsible for one primary
segment. Wariza is also the **secondary technical Q&A backup**; this does not
make Wariza the default answerer for every question. The member responsible for
the relevant section answers first. If a question crosses modules or needs
deeper implementation detail, that presenter may hand off to Wariza. Other
members should understand the complete system and contribute where appropriate.
Each member should be able to answer basic questions about their demonstrated
features and know the handoff before and after their section; this understanding
has not yet been confirmed for all members.

Technical handoff topics may include database transactions, concurrency,
stale-revision protection, authorization boundaries, inventory arithmetic,
Sale Void stock restoration, PO lineage, guarded MySQL testing, and data
integrity. This support role does not mean other members lack system knowledge.

## 9. Timing

**Official presentation allowance:** Open-ended / until the group finishes
explaining the system. The group reports that the instructor communicated no
fixed time limit; this is not presented as a formally published school rule.

**INTERNAL TARGET — approximately 12–15 minutes for the structured
presentation/demo, excluding open-ended Q&A.** This target is intended to keep
the presentation focused, avoid repetition, leave time for questions, and cover
the important teacher-requested workflows. It is not an instructor-mandated
limit.

| Presenter / segment | Internal pacing target |
| --- | --- |
| Layupan — Opening / Project Overview | 1.5–2 minutes |
| Wariza — Inventory & Procurement | 4–5 minutes |
| Largo — POS / Sales / Sale Void | 2.5–3 minutes |
| Amores — Staff Workflow / Movement History | 2–2.5 minutes |
| Casipong — Reports / Testing / Closing | 2–3 minutes |
| **Structured presentation/demo total** | **Approximately 12–15 minutes** |
| Q&A | Open-ended / instructor-directed |

These are internal rehearsal targets only, not official time limits. Refine
final pacing during Final Testing & Rehearsal.

## 10. Screenshot Fallback

The 18 final privacy-reviewed screenshots are in
[`docs/images/user-guide/`](images/user-guide/). If a prepared live page cannot
be shown safely, continue with an approved screenshot rather than modifying
demo data.

Useful fallbacks for higher-risk workflows:

- `04-po-low-stock-prioritization.png`
- `05-pos-opening-cash.png`
- `06-completed-sale-receipt.png`
- `08-voided-sale-details.png`
- `09-po-partial-damage-evidence.png`
- `10-follow-up-po-lineage.png`
- `11-reports-sales-summary.png`
- `13-pending-po-report.png`
- `14-unfulfilled-items-report.png`
- `15-damaged-items-report.png`
- `18-movement-history.png`

## 11. Recovery and Contingency

**Server stopped:** Verify MySQL is running, restart Laravel with the approved
`trackpro_demo` runtime command in §2, and reopen
<http://127.0.0.1:8016>.

**Wrong role:** Sign out normally and sign in privately with the intended demo
account. Do not change account roles to continue.

**Session expired:** Return to Login, sign in privately, and continue from the
next planned step.

**Register state changed:** Do not manipulate history to restore its appearance.
Use the approved opening-cash screenshot and read-only explanation, then record
the issue for rehearsal analysis.

**PO or Sales data changed:** Do not run destructive reset commands. Use
existing screenshots/read-only evidence. Restore only from a separately
approved private demo backup if one is prepared later.

**Internet unavailable:** The prepared local application should not intentionally
depend on internet during normal operation once dependencies and frontend assets
are available. Confirm this in Final Testing & Rehearsal. Keep slides and
screenshots available locally.

**Application defect:** Do not debug live in front of the audience. Switch to
approved screenshot or record evidence, describe only what is supported, and
investigate after the presentation segment. Do not claim a workflow succeeded
if it visibly failed.

## 12. Demo Database Protection

- **Protected local development database:** `trackpro_local`
- **Presentation database:** `trackpro_demo`
- Never restore or reset one into the other.
- Never run `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or `db:wipe`.
- Do not use `DROP` or `TRUNCATE` against protected project databases.
- If the group wants a resettable rehearsal copy, create it only in a separately
  authorized task.

A private pre-rehearsal backup or disposable duplicate of `trackpro_demo` is
recommended, but is not created by this run sheet. Keep any backup and its
credentials private; never commit a dump or credentials to Git.

## 13. Slide Deck Brief

The final presentation deck is available at
[`docs/presentation/TrackPro_Final_Presentation.pptx`](presentation/TrackPro_Final_Presentation.pptx)
and was accepted in the internal project-group review. These are recommended
topics, not a fixed slide count if a later presentation requirement specifies
otherwise:

1. Title
2. Project problem and purpose
3. Objectives
4. Users and role boundaries
5. System architecture and technology stack
6. Core inventory workflow
7. Procurement / Purchase Order workflow
8. POS / register / sales workflow
9. Damage, follow-up, and reporting workflow
10. Testing and quality evidence
11. Final feature scope and feature freeze
12. Transition to the live demo
13. Key lessons and project outcome
14. Questions and answers

Use documented project evidence only. Do not fabricate client results, adoption,
deployment, or metrics.

## 14. Final Testing & Rehearsal Operational Checks

- [x] Confirm presentation-duration policy: open-ended until the group finishes
      explaining the system; no fixed limit was communicated by the instructor.
- [x] Assign all five speaking/demo roles as listed above.
- [ ] Confirm who controls the laptop during the demo.
- [ ] Decide during rehearsal whether User Management and Audit Logs are
      included live; both remain optional read-only scope.
- [ ] Confirm the presentation laptop and perform its smoke test.
- [ ] Confirm backup/fallback strategy before rehearsal.
- [ ] Confirm every member can deliver their assigned part and handoffs.

## 15. Final Testing & Rehearsal Handoff

The later Final Testing & Rehearsal phase owns:

- actual dry runs and full end-to-end rehearsal;
- presentation timing and speaker handoff practice;
- presentation-machine smoke test and local/offline readiness confirmation;
- demo account/login confirmation and role-transition checks;
- checking the prepared `trackpro_demo` state;
- testing fallback screenshots/slides; and
- discovery and correction of defects found during rehearsal.

None of these activities is marked complete by this run sheet.

## 16. Demo Preparation Closeout

- [x] This run sheet exists and is current.
- [x] Demo environment and stable dataset are prepared.
- [x] Teacher-requested scope is mapped to demo evidence.
- [x] Demo sequence and read-only/live strategy are prepared.
- [x] Startup, recovery, and screenshot fallback instructions are prepared.
- [x] Primary speaking assignments for all five members are established.
- [x] Presentation-duration policy and internal pacing target are established.
- [x] Final presentation slide deck exists.
- [x] Group reviewed and accepted the revised slide deck and demo plan.
- [x] Demo Preparation plan is ready to enter Final Testing & Rehearsal.

Internal project-group review passed. No instructor approval, client
acceptance, external signoff, or presentation completion is implied. Actual
member delivery practice and the operational checks in §14 remain with Final
Testing & Rehearsal; no rehearsal is claimed here.
