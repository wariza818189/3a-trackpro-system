# 3A TrackPro — Project Status

Last updated: 2026-09-09

## Latest Completed Application Checkpoint

Branch:

`main`

Latest completed application/code checkpoint:

`31b5b95f8d4de3136c15924bc541b52a6d2fa846`

Commit:

`Add dashboard and sales reports`

This is the completed Tracker #16 — Dashboard & Reports application
baseline. Documentation-only updates may follow without changing this latest
completed application checkpoint.

For the repository's actual current HEAD, use:

`git rev-parse HEAD`

Expected after a completed checkpoint:

- branch `main`
- `HEAD == origin/main`
- working tree clean
- index empty

## Project

3A TrackPro: Hardware Store Sales and Inventory Management System

Team:

The Visionaries

Final presentation:

October 22, 2026

## Current Development Position

Completed:

- Stage 0 — Application foundation
- Stage 1 — Database and model foundation
- Stage 2 — Authentication and authorization
- Stage 3A — Product catalog management
- Stage 3B — Opening Inventory / INITIAL_STOCK
- Stage 3C — Normal Stock In / RESTOCK
- Branding mini-checkpoint — TrackPro branding
- Stage 3D — Admin Stock Correction / CORRECTION — COMPLETED
- Tracker #12 — Sales / POS Module — COMPLETED
- Tracker #13 — Receipt & Sales History — COMPLETED
- Responsive Navigation UI mini-checkpoint — IMPLEMENTED / VERIFIED; committed and pushed
- Tracker #16 — Dashboard & Reports — COMPLETED; committed and pushed
- Tracker #3 — Client Problem & Requirements — COMPLETED; documentation reviewed, corrected, committed, and pushed
- Tracker #4 — Product Data Planning — COMPLETED; source reconciled, planning artifacts committed, and pushed
- Tracker #5 — UI/UX Planning — COMPLETED; retrospective planning baseline reviewed, committed, and pushed

Latest completed formal engineering stage:

Tracker #16 — Dashboard & Reports

Status:

COMPLETED — explicit design/authorization approval, implementation, focused
ordinary SQLite tests, full ordinary regression, security/privacy review, route
audit, Pint/build/diff checks, manual browser smoke, read-only inventory
confirmation, and the application Git checkpoint and normal push are complete
and approved. Tracker #16 required no dedicated MySQL concurrency proof.

Latest completed application work: Tracker #16 — Dashboard & Reports.
Implementation, ordinary verification, source/security audit, desktop/mobile
browser smoke, receipt integration, application commit, and normal push are
complete. Responsive Navigation remains a completed, separately verified UI
mini-checkpoint outside the formal 23-item tracker count.

## Current Team Tracker Position

Tracker item:

#5 — UI/UX Planning

Status:

COMPLETED

Tracker #5 is formally closed as a retrospective/project-derived UI/UX planning
tracker on the reviewed baseline recorded below. The existing interface was
inventoried and formalized without redesign implementation. Tracker #16 remains
the latest completed application stage; Trackers #3, #4, and #12 remain
COMPLETED.

Overall completed tracker count: **13 / 23**.

Completed tracker items:

- #1 Create Tracking Document
- #2 Project Scope Planning
- #3 Client Problem & Requirements
- #4 Product Data Planning
- #5 UI/UX Planning
- #6 Workflow & Business Rules
- #9 Database & System Design
- #10 Authentication & User Roles
- #11 Product & Inventory Module
- #12 Sales / POS Module
- #13 Receipt & Sales History
- #14 Stock-In & Stock Movements
- #16 Dashboard & Reports

All other tracker statuses remain unchanged. These remain pending/in progress:

- #7 Test Case Preparation
- #15 Functional Testing
- #17 Edge Case & Permission Testing

These remain Not Started:

- #8 Documentation Outline
- #18 User Guide & Screenshots
- #19 Final Integration & Bug Fixing
- #20 Project Documentation Finalization
- #21 Presentation & Demo Preparation
- #22 Final System Testing & Rehearsal
- #23 Final Presentation

Module verification does not formally complete the pending testing trackers.
Tracker #13 remains COMPLETED. Tracker #7 may be the next formal planning focus,
but it is not started by this documentation checkpoint.

Previously completed tracker (unchanged):

#14 — Stock-In & Stock Movements

Status:

COMPLETED

Completed within tracker #14:

- Opening Inventory / INITIAL_STOCK
- Normal Stock In / RESTOCK
- Admin Stock Correction / CORRECTION
- historical purchase-cost recording
- latest Variant cost reference

Stage 3D completed the previously remaining controlled Stock Correction
requirement. Implementation review, ordinary tests, MySQL integrity proofs,
manual browser smoke, and the application Git checkpoint/push are complete.

## Tracker #3 Client Problem & Requirements Closeout

Status: COMPLETED — formal documentation closeout on 2026-09-09.

The approved baseline is [docs/requirements.md](docs/requirements.md), linked
from README.md. It is a **TEAM-APPROVED / PROJECT-DERIVED REQUIREMENTS BASELINE**,
reconciled with approved design and existing implementation/verification. It
claims no client interview evidence, client quotation, formal client sign-off,
exact prior manual tools, quantified business losses/errors, or employee/job-title
mapping. No client evidence was fabricated; unverified client-specific facts
remain explicitly open.

The document contains project context, a project-derived problem statement,
assumptions, Admin/Staff responsibilities, functional requirements with observable
acceptance outcomes, non-functional/quality requirements, business rules,
implemented/planned/excluded scope, acceptance/traceability mapping,
open/unverified client-specific facts, and a review record. Stable IDs comprise
53 functional requirements, 13 non-functional/quality requirements, 14 business
rules, and 7 assumptions: **87 unique IDs**.

### Problem direction and application roles

3A TrackPro addresses the need for one controlled system coordinating hardware
catalog information, product variants, stock quantities, Opening Inventory,
Stock In, Stock Correction, cash sales, receipts/history, low-stock visibility,
and management summaries. Its purpose is centralized, authorized, traceable
operational records. This project-derived direction does not assert client
reports of notebook, Excel, manual-receipt problems, or quantified losses.

Primary roles are Admin and Staff. These define application permissions only;
Admin does not imply owner, Staff does not imply cashier, and no named employee
assignment is established.

### Requirements coverage and corrected history semantics

Functional coverage includes Authentication/roles, Categories, Products,
Variants, Opening Inventory, Stock In, Stock Correction, POS/checkout, Sales
receipts, Sales History, responsive navigation, Dashboard, and Reports.

FR-SALES-01 permits active Admin and Staff to browse historical Sales regardless
of the recording user. Sales History is status-neutral, preserves historical
status, and is not restricted to completed-only browsing. Dashboard/Reports
analytics explicitly require `status = completed` only. SALE_VOID remains
future/unimplemented; the application has no void transition or stock-restoration
workflow and does not currently generate voided Sales through such a workflow.

Quality and integrity requirements capture nonnegative stock, StockMovement
evidence, atomic/concurrency-safe stock-changing workflows, server-authoritative
checkout values, role/backend authorization, privacy and internal-token
protection, historical immutability, decimal-safe arithmetic, Asia/Manila
reporting semantics, responsive navigation, accessibility/focus behavior,
browser receipt printing, and the approved Laravel/Blade/Tailwind architecture.
Business boundaries retain cash-only v1 with no discounts, credit/utang,
returns/refunds, partial void, unit conversion, FIFO/weighted-average COGS,
or formal profit calculation.

### Scope classification

- **Current implemented baseline:** Authentication/roles, Catalog (Categories
  and Products), Variants, Opening Inventory, Stock In, Stock Correction, POS,
  receipt/reprint, Sales History, responsive navigation, Dashboard, and Sales
  Summary Reports.
- **Planned current-project future work:** SALE_VOID / Admin full-sale void,
  User Management UI, and final integration/testing/documentation/presentation
  work. These remain unimplemented or unfinished and require their own scope.
- **Deferred / excluded current v1:** supplier management/purchase orders,
  customer accounts, credit/utang, returns/refunds, discounts, partial void,
  unit conversion, accounting integration, FIFO/weighted-average COGS, profit
  reporting, CSV/PDF report export, and advanced additional reports. Exclusion
  is not a promise that all items will be delivered in Phase 2.

### Traceability, verification, and tracker boundaries

Requirements map to completed #10 Authentication, #11 Catalog/Product/Inventory,
#12 POS, #13 Receipt & Sales History, #14 Stock workflows, #16 Dashboard & Reports,
and the Responsive Navigation mini-checkpoint. Navigation remains outside the
formal 23-item count. These references link requirements to existing
implementation and verification evidence.

The recorded ordinary software baseline remains **191 tests / 2,023 assertions**,
27.576 seconds, isolated SQLite `:memory:`; the application route baseline is
**40**. These are historical results, not rerun for this documentation closeout.
Tests prove software behavior, not client interviews. No tests, builds, or
database access occurred for this closeout.

Tracker #3 establishes why, what, for whom, constraints, acceptance outcomes,
and scope. At its closeout, it did not complete or start these separate trackers:

- #4 Product Data Planning: subsequently completed in the formal closeout below.
- #5 UI/UX Planning: screen, user-flow, and design planning evidence.
- #7 Test Case Preparation: formal detailed test cases, procedures, and expected
  results.

#15 Functional Testing and #17 Edge Case & Permission Testing also retain their
pending/in-progress status. Completed #13, #14, and #16 statuses are unchanged.

### Requirements documentation checkpoints

- `2bdbd227c4c7b46ded5ef84c5be1c00796b2641b` — Document client problem and requirements.
- `04ae3edba272d9d4fbf11884567133223ec3fd95` — Clarify sales history requirement.

Both are committed and pushed documentation checkpoints, separate from the
completed application checkpoint chain below. README.md links the baseline,
reflects scope through Tracker #16, and corrects stale SQLite fixture descriptions.
The latest completed application checkpoint remains
`31b5b95f8d4de3136c15924bc541b52a6d2fa846` — Add dashboard and sales reports.
Use `git rev-parse HEAD` for actual repository HEAD; this closeout does not
predict its own documentation commit hash.

## Tracker #4 Product Data Planning Closeout

Status: COMPLETED — formal documentation closeout on 2026-09-09.

Tracker #4 is a **PRODUCT-DATA PLANNING** tracker. Completion means the supplied
real source is fully accounted for; its structure and ambiguities are
reconciled; Category → Product → ProductVariant mapping, normalization, units,
quantity modes, price interpretation, stock-pool risks, and entry-readiness
criteria are documented; and a structured local staging dataset is created and
validated. Missing and ambiguous evidence remains visible, unsupported values
are not invented, and no database load occurred.

Completion does not mean every source row is entry-ready, every missing price or
threshold is resolved, opening stock is counted, or Products are loaded into the
application. HOLD records do not invalidate this planning closeout.

### Source, deliverables, and reconciliation

The supplied source is `.local-source/3A-DURIAN-PRICING.pdf`, a local-only,
ignored, untracked 22-page file with SHA-256
`656355b98027a4c366ee48ecca2dc04d313d9ccb30a9d6b103d48650206de064`.
The complete real price list is not reproduced in tracked documentation.

Audited source distinctions:

| Source measure | Count |
| --- | ---: |
| Source groups | 59 |
| Printed item rows | 321 |
| Printed price positions | 400 |
| Numeric price positions | 312 |
| Blank price positions | 88 |
| Printed item rows with no numeric price anywhere | 31 |

The 321 printed rows are not 321 Products, and the 400 price positions are not
400 ProductVariants. Matrix columns and alternate selling bases create multiple
price positions for some printed rows.

Tracked deliverables:

- `docs/product-data-plan.md` records the approved planning method,
  normalization rules, source audit, category plan, stock-pool risks,
  price/cost handling, ambiguity rules, and entry-readiness criteria.
- `docs/product-data-staging-template.csv` contains the exact public 33-column
  staging schema and header only.

The complete derived dataset,
`.local-source/3A-DURIAN-PRICING-staging.csv`, remains local-only, ignored, and
untracked. Its 400 data records reconcile to 321 unique source rows, 312 numeric
source-price records, 88 blank-price records, and 31 source rows with no numeric
price anywhere. The real-price rows are not tracked.

Initial local staging review results are **205 `normalized_pending_review`, 195
`hold`, and 0 `approved_for_entry`**. The zero approved count is intentional:
required catalog decisions such as low-stock thresholds are absent, and several
source-unit and stock-pool questions remain. These 400 source positions are not
described as ready for database entry.

### Catalog, identity, category, and normalization plan

Planning maps source data into Category → Product → ProductVariant. The exact
ProductVariant identity remains `(product_id, size, type_series, thickness,
unit)`. Price and `quantity_mode` do not create distinct identities by
themselves. Archived Variants continue occupying their identities, and
duplicate-looking records require review rather than automatic merging.

The team/project planning categories, which are not source-authored categories,
are:

- Steel Pipes & Tubes
- Steel Bars & Sections
- Roofing & Sheet Metal
- Wire, Screens & Netting
- Ceiling & Framing
- Insulation & Coverings
- Rope
- Drainage & Sanitary
- Welding Supplies
- Plumbing Pipes, Fittings & Valves
- Nails & Fasteners
- Boards & Panels

Original source wording is preserved separately from normalized/proposed text.
Spelling and formatting issues are documented instead of silently rewritten.
Unlabelled 1.2, 1.5, 0.8, and 1.0 thickness-like values may be proposed as
`thickness`, but remain review-sensitive because the source does not explicitly
label their dimension unit. Composite dimensions remain intact when splitting
would require an assumption.

The initial tentative identity check found **10 collision groups covering 20
P.E. fitting staging records**. Multiple printed source columns map toward the
same proposed per-piece identities. All remain HOLD/review-required. No database
collision query was performed.

### Unit, price, currency, stock, and ambiguity rules

Team-proposed unit and quantity-mode mappings are:

| Source/item basis | Proposed unit | Proposed quantity mode |
| --- | --- | --- |
| Discrete or fixed item | piece | whole |
| Sheet or board-type good | sheet | whole |
| Explicit per-kilo | kg | fractional |
| Explicit per-meter | m | fractional |
| Explicit per-roll | roll | whole |

These are planning mappings; a generic source PRICE does not prove its selling
unit. TrackPro has no unit conversion. Different units are independent stock
pools, so the same physical roll must not be counted as both roll stock and
meter stock. Likely shared-stock or unclear families remain HOLD, including
Thick Screen, Thin Screen, Insulation Foam, Tent Black, and other meter/roll
screen, net, rope, and P.E. cases.

The P.E. source table visually places fitting rows below a PER ROLL / PER METER
header; review confirmed that this is source layout rather than an extraction
error. P.E. fittings are proposed as `piece` / `whole`, while retaining the
ambiguous header evidence and HOLD status. The plan does not claim that the
source explicitly states "per piece."

Numeric source PRICE values are treated as candidate selling prices. The PDF
contains no explicit currency label. PHP / Philippine peso is a project and
application assumption, while every source-currency field remains blank.
Purchase cost is not supplied, so `cost_price` remains blank/nullable and is
never derived from selling price.

Blank source price positions remain blank with `missing_source_price` and HOLD.
Zero, `0.00`, neighboring prices, averages, and interpolation are not substitutes.

The source does not provide `current_stock`, an opening inventory quantity, or
an approved low-stock threshold. No opening or current stock was staged, and
thresholds remain `not_provided_by_source`. Actual Opening Inventory remains a
separate physical-count workflow. Later catalog creation may require an approved
threshold; Tracker #4 does not invent one.

### Historical evidence, scope, and checkpoint boundaries

Test Tools / Test Hammer / 16oz · Claw remains legitimate historical local
development evidence. It is no longer the preferred representative product-data
planning example. Tracker #4 did not delete, rename, repurpose, rewrite, or
access it through the database, and its historical Sales/StockMovement evidence
remains preserved. Future documentation and demonstrations should prefer
approved real-source product families.

Tracker #4 covers source catalog review, normalization, grouping, Variant
mapping, unit/mode planning, price-source interpretation, and ambiguity/HOLD
handling. At the Tracker #4 closeout, #5 UI/UX Planning, #7 Test Case
Preparation, #15 Functional Testing, and #17 Edge Case & Permission Testing
remained pending/in progress. Opening Inventory remains a separate physical-count
workflow. No other tracker was completed by that closeout.

No schema, migration, model, controller, service, application source, or
dependency changed. No product seed/import ran and no database was accessed.
No test or build gate was required. The historical ordinary software baseline
remains **191 tests / 2,023 assertions**, 27.576 seconds, isolated SQLite
`:memory:`; the application route baseline remains **40**. These checks were not
rerun for Tracker #4.

The completed product-data planning/documentation checkpoint is
`4358436085f51507d12bf178721bd875b4eb46a1` —
`Plan product data from pricing source`. It is not an application/code
checkpoint and is not added to the application checkpoint chain below. The
latest completed application checkpoint remains
`31b5b95f8d4de3136c15924bc541b52a6d2fa846` —
`Add dashboard and sales reports`. Use `git rev-parse HEAD` for the repository's
actual current HEAD; this closeout does not predict its own commit hash.

## Tracker #5 UI/UX Planning Closeout

Status: COMPLETED — formal documentation closeout on 2026-09-09.

Tracker #5 is a **RETROSPECTIVE / PROJECT-DERIVED UI/UX PLANNING
BASELINE** because much of the interface existed before this formal planning
artifact. Completion means the current implemented UI was fully inventoried;
Admin/Staff usage differences, information and navigation architecture,
principal flows, implemented visual and interaction conventions, responsive
behavior, accessibility evidence and limitations, feedback/error/empty-state
patterns, and receipt-print behavior were documented; genuine UX risks were
recorded; and future recommendations were separated from current features.

Completion does not mean a redesign or any recommendation was implemented,
every UX risk was fixed, usability testing or client UI approval occurred,
pre-development wireframes existed, screenshots or user-guide work occurred,
or WCAG conformance was established. Open recommendations do not block this
planning tracker's completion.

### Deliverable, screens, and role-based experience

The tracked deliverable is [docs/ui-ux-plan.md](docs/ui-ux-plan.md), titled
**3A TrackPro — UI/UX Planning Baseline**. Its reviewed and pushed UI/UX
planning/documentation checkpoint is
`7c87d4ae72f63c2a77837ce21755f23de63623be` —
`Document UI and UX planning baseline`.

The baseline inventories **22 route-backed user-facing pages**. Shared surfaces,
which are not counted as independent pages, include the desktop sidebar, mobile
top bar and off-canvas navigation, account/logout area, global alerts, brand
component, dynamic Stock In rows, dynamic POS cart, and receipt print state.

Admin and Staff are application-role usage profiles, not demographic personas
or undocumented job titles. Admin has **10** navigation destinations and Staff
has **7**. Staff may enter purchase cost while creating a new Stock In receipt,
but does not receive existing or historical purchase-cost visibility in catalog
browsing, Stock In history/detail after submission, POS, Dashboard, Reports, or
Sales History.

### Navigation, responsiveness, and mapped flows

At Tailwind `lg` (64rem / 1024px) and above, the authenticated shell uses a
fixed `w-60` sidebar, offset main content, grouped role-filtered navigation, and
an active-destination state. Below `lg`, it uses a sticky top bar, hamburger,
`w-72 max-w-[85vw]` drawer, backdrop, Escape/backdrop/X/navigation closure,
body-scroll lock, inert background, and focus entry/return. This evidence does
not establish complete modal-dialog semantics or WCAG conformance. Wide tables
commonly retain desktop columns and use horizontal scrolling on narrow screens.

The planning baseline maps Login to Dashboard; Category to Product to
ProductVariant; Opening Inventory; Stock In; Stock Correction; POS; Sales
History to receipt and reprint; Dashboard operational follow-up; Reports
filtering; and archive/reactivate lifecycle flows. These maps are planning
evidence, not completion evidence for separate testing trackers.

Sales History remains **status-neutral**: active Admin and Staff may browse all
historical Sales regardless of recording user. Completed-only semantics apply
to Dashboard and Reports analytics. The existing Sales History eyebrow
`Completed transactions` conflicts with the page semantics and is recorded as
a future content-correction candidate; Tracker #5 did not change it.

### Accessibility and print evidence

Accessibility observations use three evidence levels:

- **Implemented:** relevant semantic elements; navigation ARIA,
  `aria-current`, `aria-controls`, `aria-expanded`, and `aria-hidden`; `inert`;
  Escape handling; focus entry/return; `role=alert`/`status`; visible text with
  state colors; native disabled/read-only behavior; and existing
  `aria-describedby` help.
- **Partial:** table semantic completeness, field-error association, dynamic
  announcements, drawer modal semantics/focus trapping, and consistent focus
  styling.
- **Not established:** WCAG conformance, formal contrast audit, screen-reader
  testing, reduced-motion testing, comprehensive keyboard testing, and formal
  usability studies.

The Sale receipt supports browser printing and reprinting. Its print state
suppresses application chrome and non-receipt controls while retaining receipt
evidence. Historical Firefox receipt verification remains historical evidence.
Reports have no dedicated print control, print route, report-specific print
layout, PDF export, or CSV export.

### Source-derived risks and future boundary

Source inspection identified eight genuine usability risks, not proven user
failures:

1. POS and Stock In item selection omit Category context.
2. Archive acts without an explicit confirmation naming the affected record.
3. Below `xl`, the mobile POS cart follows the full catalog.
4. Repeated-row error association could be stronger.
5. Sales History eyebrow wording conflicts with status-neutral semantics.
6. Opening Inventory uses a generic unavailable reason.
7. Dynamic POS and Stock In updates lack assistive announcements.
8. Invalid Reports may retain zero-valued summary cards.

Future, not-yet-implemented recommendations cover Category context in POS/Stock
In, archive confirmation, a mobile cart shortcut/sticky summary, stronger
programmatic field-error association, status-neutral Sales History wording,
specific Opening Inventory ineligibility explanations, Staff-specific dependency
guidance, invalid-report summary handling, visual/action/focus consistency, and
assistive announcements for dynamic updates. None is described as a current
feature or implemented by this closeout.

### Evidence limits, tracker boundaries, and checkpoints

No repository evidence establishes pre-development wireframes, client-selected
colors/layouts, client UI sign-off, usability interviews, observed usability
sessions, quantified usability findings, formal demographic personas, specific
client device/printer models, or proof that every UI decision preceded
implementation. This closeout makes none of those claims.

Tracker #5 does not complete or start #7 Test Case Preparation, #15 Functional
Testing, #17 Edge Case & Permission Testing, or #18 User Guide & Screenshots.
No screenshot collection, new usability test, or user guide was produced.
Responsive Navigation remains a completed UI mini-checkpoint outside the formal
23-item tracker count.

The historical ordinary software baseline remains **191 tests / 2,023
assertions**, 27.576 seconds, isolated SQLite `:memory:`; the application route
baseline remains **40**. These were not rerun for Tracker #5. No test, build,
browser, or database gate was required or performed.

The UI/UX planning checkpoint is documentation-only and is not added to the
completed application checkpoint chain below. The latest completed application
checkpoint remains `31b5b95f8d4de3136c15924bc541b52a6d2fa846` —
`Add dashboard and sales reports`. Use `git rev-parse HEAD` for the repository's
actual current HEAD; this closeout does not predict its own commit hash.

## Completed Application Checkpoint Chain

Documentation-only guidance commits may occur between application checkpoints
and are intentionally not included in this application checkpoint chain.

1. `5ad66c0ed2d8533a1cd9613139d4605227f1a62d`
   - Initialize 3A TrackPro application

2. `daf2ff1e12faeffc0f69d7041d6abdb143c20dc0`
   - Add inventory and transaction data foundation

3. `131b0fc3626f369b8518b99b845c781188df790c`
   - Add authentication and role authorization

4. `e535af4ccd21abdfbae7f78ffb308a62384ab953`
   - Add product catalog management

5. `7bf4413f36e15239fa57f1cc2b301fbe8ed6be45`
   - Add opening inventory workflow

6. `b7374556dac1f8deb436278ab275943c21cc1137`
   - Add stock in workflow

7. `ac154a2dda2e955ea849669120e86c9cbcf425ac`
   - Add TrackPro branding

8. `e3cdb0858af110afce70619c421d85a1d8a4c73a`
   - Add admin stock correction workflow

9. `f55406bd6cc5931a611dac3a518664ac719a9b1a`
   - Add point of sale checkout workflow

10. `2fc45125bb1876b49b8a501b8896801fa709b727`
    - Add receipt and sales history

11. `deb3cbdfe4ad46ee62efa8c19f18b54ff1551466`
    - Add responsive application navigation

12. `31b5b95f8d4de3136c15924bc541b52a6d2fa846`
    - Add dashboard and sales reports

## Current Application Scope

Implemented:

- secure username/password authentication
- Admin / Staff roles
- active/disabled account enforcement
- Category management
- Product management
- Product Variant management
- archive/reactivation rules
- Staff read-only catalog access
- Opening Inventory
- INITIAL_STOCK movements
- Stock In / Restock
- multi-item Restocks
- historical purchase cost
- latest cost reference
- RESTOCK movements
- durable Stock In idempotency
- Stock In history/detail
- TrackPro branding
- Admin Stock Correction
- immutable CORRECTION movements
- read-only correction history
- POS / Sales for active Admin and Staff
- cash-only sales checkout and durable checkout-token idempotency
- immutable Sale / SaleItem / SALE stock movement history
- one-time completed/replayed post-checkout confirmation with View receipt link
- Tracker #13 — Receipt & Sales History for active Admin and Staff
- persistent receipt/detail page and Sales History index
- browser receipt reprint and historical sale browsing/filtering with pagination
- responsive desktop sidebar and mobile hamburger/drawer navigation — implemented and verified
- operational Dashboard at GET / for active Admin and Staff
- Admin-only Sales Summary at GET /reports

Not yet implemented:

- Admin full-sale void
- SALE_VOID
- returns/refunds
- discounts
- credit / utang
- formal profit / COGS
- CSV/PDF report exports
- sales-by-cashier ranking
- top-selling variant report
- general stock-movement report
- User Management UI
- final integration
- remaining documentation/testing/presentation work

## Database Foundation

Production domain tables:

1. users
2. categories
3. products
4. product_variants
5. sales
6. sale_items
7. restocks
8. restock_items
9. stock_movements
10. audit_logs

Plus Laravel:

- migrations

Current production schema is intentionally restrictive and historical records are
preserved.

No supplier table exists in Phase 1.

## Known Local Development Data

IMPORTANT:

`trackpro_local` is NOT empty.

It intentionally contains legitimate development/manual-smoke data.

These are recorded prior manual-smoke observations, not a fresh database query.

Known examples include:

- a real active local Admin account
- Category: Test Tools
- Product: Test Hammer
- Variant identity: 16oz · Claw · piece
- quantity mode: whole
- selling price: 150.00
- latest cost price: 110.00
- current stock: 2.000 (reconfirmed after Tracker #13 receipt/history browser smoke)
- low-stock threshold: 5.000
- status: active
- INITIAL_STOCK movement recorded at zero
- Stock In receipt: RST-000001
- Stock In reference: DR-STAGE3C-001
- Restock quantity: 5.000
- historical Restock unit cost: 110.00
- Sale TRX-000001: quantity 1, total/cash 150.00, change 0.00
- Sale TRX-000002: quantity 1, total 150.00, cash 200.00, change 50.00

The two legitimate immutable local Sales reduced stock from 4.000 to 2.000.
An intervening underpayment attempt did not deduct stock. Cost remained 110.00
and selling price remained 150.00; POS did not expose purchase cost. These are
historical manual observations, not database access during this update.

Tracker #13 browsing, receipt filters, receipt GETs/reloads, and Print Preview
did not change inventory: the normal Product Variants page still showed stock
2.000, cost 110.00, and selling price 150.00. No local data is queried or modified
for this documentation checkpoint.

Do not delete or reset this data.

It may be reused for later manual smoke testing.

Never ask for the local Admin password.

## Stage 3B Evidence

Historical recorded evidence from the completed stage; manual smoke and MySQL
proof results were not rerun during this documentation review.

Opening Inventory code/tests are complete.

Manual smoke proved:

Before:

- current stock = 0.000
- opening status = Not initialized

Recorded:

- opening quantity = 0.000

After:

- current stock = 0.000
- opening status = Initialized
- action = Completed

Real MySQL concurrency proof passed:

- 1 test
- 21 assertions

It proved zero-opening exactly-once behavior under MySQL REPEATABLE READ.

## Stage 3C Evidence

The manual-smoke and live MySQL results below are historical recorded evidence,
not tests run during this documentation review. Fixture cleanup and the empty
`trackpro_test` state refer only to that prior proof run.

Normal Stock In is operationally closed.

Ordinary Stock In tests:

- Authorization: 5 tests / 54 assertions
- Management: 15 tests / 167 assertions

Historical complete ordinary suite at Stage 3C completion:

- 112 tests
- 952 assertions

Historical Stage 3C application route count (explicit routes in `routes/web.php`, excluding
framework routes such as `/up`):

- 32 routes

Real MySQL concurrency Test A:

- 1 test / 19 assertions
- starting stock 10.000
- concurrent +5.000 and +7.000
- final stock 22.000
- no lost update
- continuous RESTOCK ledger

Real MySQL idempotency Test B:

- 1 test / 21 assertions
- REPEATABLE-READ stale snapshot established
- same-token competitor blocked
- current locking read recovered winning Restock
- same Restock ID returned
- final stock 15.000
- inventory incremented exactly once

Both test fixtures cleaned successfully.

`trackpro_test` ended with all domain tables empty.

## Stage 3D Evidence

Stage 3D — Admin Stock Correction / CORRECTION is COMPLETED.
The following results are historical completed evidence; no tests, database
queries, or browser smoke were performed during this documentation update.

Ordinary Stock Correction tests:

- Authorization: 5 tests / 52 assertions
- Management: 17 tests / 147 assertions
- Full ordinary suite: 134 tests / 1,152 assertions
- Application routes: 35
- Pint, build, and diff checks passed

Real MySQL Test A — concurrent different correction targets:

- 1 test / 16 assertions
- loser blocked behind winner
- current/locking reread after commit rejected the stale movement version
- final stock 7.000; exactly one CORRECTION

Real MySQL Test B — concurrent same target:

- 1 test / 13 assertions
- loser blocked, then reread authoritative stock 7.000
- no-op rejected before stale-version check
- exactly one CORRECTION

Real MySQL Test C — real Restock versus stale Correction:

- 1 test / 17 assertions
- Correction waited behind real Restock
- Restock changed 10.000 -> 15.000
- stale movement version rejected; received stock was not overwritten
- zero CORRECTION movements

Historical final test-database cleanup:

- all 10 domain tables = 0 rows
- table count = 11
- migration records = 10
- schema unchanged

Completed manual browser smoke used Test Tools / Test Hammer / 16oz · Claw,
unit piece, quantity mode whole. The existing Stage 3C receipt RST-000001,
reference DR-STAGE3C-001, retained quantity 5.000 and unit cost 110.00.

- Initial stock before correction: 5.000
- No-op target 5 rejected with: `No stock change is required.`
- No stock mutation resulted from that no-op.
- Successful correction by Admin: before 5.000, change -1.000, after 4.000
- Exact persisted reason: `Stage 3D no-op browser smoke`

The reason was reused for the successful correction. Its wording is semantically
imperfect but is legitimate immutable historical data; do not rewrite it.

Browser UI evidence:

- Admin navigation exposed Stock Correction.
- The correction form showed Test Hammer and current stock.
- No-op rejection and successful correction both worked.
- Read-only correction history showed before/change/after/actor/reason.
- No Edit/Delete history controls were present.
- Variant catalog reflected current stock 4.000.
- Cost remained 110.00 and selling price remained 150.00, unchanged by correction.
- Final low-stock threshold was 5.000 and Variant status was active.

## Tracker #12 POS / Sales Evidence

Tracker #12 — Sales / POS Module is COMPLETED. All results in this section are
historical completed evidence. No database, test, build, or browser execution
was performed for this documentation checkpoint.

Ordinary verification:

- PosAuthorizationTest: 5 tests / 45 assertions
- PosCheckoutTest: 15 tests / 305 assertions
- Full ordinary suite: 154 tests / 1,508 assertions
- Existing requested regression groups: 103 tests / 951 assertions
- Application routes: 37 from `php artisan route:list --except-vendor`
- Pint, Vite production build, and `git diff --check`: passed
- POS routes: `GET /pos` (`pos.index`) and
  `POST /pos/checkout` (`pos.checkout`)

### Historical real MySQL proofs

Dedicated connection `mysql_testing`, database `trackpro_test`, MySQL
`8.0.46-0ubuntu0.24.04.4`, isolation `REPEATABLE-READ`.

Test A — `test_concurrent_distinct_sales_serialize_and_prevent_overselling`:

- 1 test / 20 assertions; passed
- Initial/stale stock 5.000; competing Sale blocked while winner sold 4.000.
- After winner commit, loser reread authoritative availability 1.000 and was
  rejected; its temporary Sale rolled back.
- Final stock 1.000, one winning Sale/SaleItem/SALE movement, no oversell or
  partial loser; fixture cleanup returned all domain counts to baseline.

Test B — `test_identical_same_token_race_recovers_winner_after_stale_snapshot`:

- 1 test / 22 assertions; passed
- Loser's REPEATABLE READ snapshot saw token count 0 and stock 10.000; loser
  blocked until winner committed.
- Unique checkout-token collision occurred; losing transaction scope rolled
  back and fresh/current locking recovery saw the winner despite the stale
  ordinary snapshot.
- Both callers resolved to the same Sale ID. Captured stale evidence remained
  token count 0 / stock 10.000; final business stock was 6.000 with exactly one
  Sale/SaleItem/SALE movement and one deduction.
- Fixture cleanup returned all domain counts to baseline.

Test C — `test_reversed_multi_variant_browser_order_uses_global_hierarchy_order_without_deadlock`:

- 1 test / 19 assertions; passed
- Opposing browser/cart order used deterministic hierarchy locking; waiter
  blocked, no deadlock occurred, and both Sales succeeded.
- Final stocks 7.000 and 3.000; two Sales, four SaleItems, and two SALE movements
  per Variant. Each ledger preserved first quantity_after == second quantity_before.
- Fixture cleanup returned all domain counts to baseline.

Test D — `test_same_token_disjoint_inventory_blocks_on_unique_arbitration_and_rejects_semantic_reuse`:

- 1 test / 18 assertions; passed
- Transactions used disjoint Category/Product/Variant rows; child still blocked
  at shared checkout-token arbitration until winner committed.
- Fresh recovery read the winner; the different Variant set was rejected as
  semantic token reuse.
- One winning Sale/SaleItem/SALE movement; winner stock 9.000, losing Variant
  stock 10.000, no losing SaleItem/SALE movement or partial Sale, and no deadlock.
- Fixture cleanup returned all domain counts to baseline.

Pre-test, after every proof, and final counts were zero for all ten domain
tables: users, categories, products, product_variants, sales, sale_items,
restocks, restock_items, stock_movements, and audit_logs. Table count remained
11 and migration records remained 10; schema was unchanged. No external cleanup
was used to manufacture this state. No migration/reset/seed/DDL ran, and
`trackpro_local` was not accessed during MySQL proof execution.

### Historical actual manual POS browser smoke

Manual browser smoke used legitimate `trackpro_local` data: Test Tools / Test
Hammer / 16oz · Claw, unit piece, quantity mode whole. Before POS smoke, stock
was 4.000, cost 110.00, selling price 150.00, low-stock threshold 5.000, and status
active. Admin POS navigation was visible; Test Hammer, stock 4.000, and selling
price 150.00 appeared. Purchase cost was not exposed in POS.

Two successful Sales occurred, with an underpayment validation between them:

| Event | Quantity | Total | Cash | Change | Stock |
| --- | --- | --- | --- | --- | --- |
| TRX-000001 | 1 | 150.00 | 150.00 | 0.00 | 4.000 → 3.000 |
| Underpayment attempt (not a Sale) | 1 | 150.00 | Below total | — | Remained 3.000 |
| TRX-000002 | 1 | 150.00 | 200.00 | 50.00 | 3.000 → 2.000 |

Both successful Sales were for Test Hammer / 16oz · Claw and displayed the
one-time confirmation `Sale completed.` The underpayment attempt displayed
`The tendered cash is less than the sale total.` No successful Sale confirmation
or stock deduction occurred; the cart remained available for review/retry.

The normal Variants page visually confirmed Test Hammer / 16oz · Claw · piece,
Whole, final stock 2.000, cost 110.00, selling price 150.00, low-stock threshold
5.000, and active status. The two Sales reduced stock from 4.000 to 2.000; cost
and selling price remained unchanged, and underpayment did not deduct stock.
These two local Sales are legitimate immutable historical data; do not delete
or alter them.

At the Tracker #12 checkpoint, TRX-000001 and TRX-000002 were human-readable Sale
receipt-number representations used in checkout confirmation only. Persistent
receipt/detail, Sales History, reprint, and filtering were subsequently completed
in Tracker #13, as recorded below.

## Tracker #13 Receipt & Sales History Evidence

Tracker #13 — Receipt & Sales History is COMPLETED. All test, route, build, and
browser results below are historical approved evidence, not executions during
this documentation checkpoint. The application checkpoint and normal push are
complete: `2fc45125bb1876b49b8a501b8896801fa709b727`,
`Add receipt and sales history`.

### Completed behavior and route surface

Active Admin and Staff may browse all Sales and view/reprint all receipts.
Guests and disabled authenticated users are denied. History is read-only and
uses immutable Sale and SaleItem evidence; current Product/ProductVariant values
are not historical receipt sources. Purchase costs, checkout tokens, and
StockMovement internals are not exposed.

The index provides exact canonical receipt-number filtering, lowercase TRX
prefix normalization, fail-closed malformed receipt filters, cashier filtering,
and strict Asia/Manila calendar-date filtering (inclusive start, exclusive next
day end). It uses 20-row server-side pagination and deterministic newest-first
ordering by created_at DESC, then id DESC.

- `GET /sales` — `sales.index`
- `GET /sales/{sale}` — `sales.show`, with a numeric parameter constraint

Both routes are under `auth` and `active`; neither is Admin-only. There is no
separate receipt/reprint/edit/update/delete/void route or Sales mutation route.
The one `sales.show` page serves detail, receipt, and reprint, with SaleItems in
ID ascending order. Browser printing uses `window.print()` and hides application
chrome. Viewing, reloading, and printing create no AuditLog or other write.
There is no PDF generation or SALE_VOID workflow.

Completed and replayed POS confirmations provide View receipt links to the
correct `sales.show` page. RecordSale remained unchanged; checkout transaction,
pricing, stock, and idempotency semantics were not changed by Tracker #13.

### Historical ordinary verification

All ordinary tests used isolated in-memory SQLite only.

| Suite | Tests | Assertions |
| --- | --- | --- |
| SalesHistoryAuthorizationTest | 6 | 49 |
| SalesHistoryTest | 7 | 141 |
| Combined new Tracker #13 suites | 13 | 190 |
| Updated PosAuthorizationTest | 5 | 43 |
| Updated PosCheckoutTest | 15 | 311 |
| ModelFoundationTest | 9 | 66 |
| Full ordinary suite | 167 | 1,702 |

PHP syntax, Blade compilation, Pint, Vite production build, and
`git diff --check` passed. Application route count was 39 from the historical
`php artisan route:list --except-vendor` verification.

No dedicated MySQL concurrency test was required, created, or run for #13:
it adds read-only queries/rendering, with no mutation transaction, locks, DDL,
or MySQL-specific concurrency invariant. Neither test nor local databases are
accessed during this documentation checkpoint.

Historical ordinary privacy/accuracy tests verified that SaleItem snapshots
survive current catalog changes, later stock activity does not change old
receipt output, and current Product/ProductVariant values are not substituted.
Unique current purchase cost, unique historical restock cost, and the checkout
token were absent. Two consecutive receipt GETs caused no database-state change
and created no AuditLog.

### Historical manual Sales History and filter smoke

Manual UI smoke used the existing legitimate local Admin account and Sales
TRX-000001 and TRX-000002. Sales History navigation was visible/active, and the
initial page showed TRX-000002 before TRX-000001. Both rows showed cashier Admin,
status Completed, and 1 distinct item.

| Receipt | Total | Cash received | Change |
| --- | --- | --- | --- |
| TRX-000001 | 150.00 | 150.00 | 0.00 |
| TRX-000002 | 150.00 | 200.00 | 50.00 |

- Exact filter `TRX-000001` showed only TRX-000001.
- Lowercase filter `trx-000002` normalized to `TRX-000002` and showed only
  TRX-000002.
- Malformed filter `TRX-2` showed the controlled message
  `Enter a receipt number such as TRX-000002.` and `No Sales found.`
  There was no crash and no broad all-Sales fallback.

### Historical receipt and Print Preview smoke

Both receipt pages displayed Test Hammer, Variant 16oz · Claw, unit piece,
quantity 1.000, unit price 150.00, and line total 150.00. Both displayed status
Completed and cashier Admin, with their respective receipt numbers and payments:

| Receipt | Total | Cash received | Change |
| --- | --- | --- | --- |
| TRX-000001 | 150.00 | 150.00 | 0.00 |
| TRX-000002 | 150.00 | 200.00 | 50.00 |

TRX-000001 exposed no purchase cost, checkout token, StockMovement internals,
Edit, Delete, or Void control. The Print receipt control was visible.

Firefox Print Preview was opened for TRX-000002. TrackPro branding, receipt
number, status, cashier, historical item, quantity, prices, and totals remained
visible/readable. Application navigation, Back to Sales History, and the Print
receipt button were hidden. The receipt fit on one printed sheet. Firefox's
generated header/footer metadata is browser UI, not application receipt content;
no physical print was required.

### Historical read-only inventory and Staff boundary

After Sales History browsing, receipt filters, receipt GETs/reloads, and Print
Preview, the normal Product Variants page still showed Test Hammer,
16oz · Claw · piece, Whole, stock 2.000, cost 110.00, selling price 150.00,
low-stock threshold 5.000, and Active status. Receipt/history use did not change
inventory: stock remained 2.000, cost remained 110.00, and selling price remained
150.00. This is recorded browser evidence, not a fresh database query.

A new local Staff user was deliberately not created for browser smoke. Manual
Staff browser smoke was not performed; Staff all-Sales access is covered by
SalesHistoryAuthorizationTest (6 tests / 49 assertions).

## Responsive Navigation UI Mini-checkpoint Evidence

Status: IMPLEMENTED / VERIFIED — application commit and normal push complete:
`deb3cbdfe4ad46ee62efa8c19f18b54ff1551466`,
`Add responsive application navigation`.

All automated and browser results below are historical approved evidence. No
tests, builds, browser smoke, or database access occur in this documentation
checkpoint. The formal completed tracker count at that historical checkpoint was
**9 / 23**; Tracker #3 later raised it to 11 / 23, and the historical count after
Tracker #4 closeout was **12 / 23**.

### Completed navigation and security

Desktop uses a fixed left sidebar at Tailwind `lg` (64rem / 1024px) and above,
with `w-60` (approximately 240px), TrackPro branding, grouped navigation,
active-page highlighting, an independently scrollable navigation area, and
account/secure logout at the bottom. The `min-w-0` content shell uses `lg:pl-60`
and resets its print offset with `print:pl-0`.

Below `lg`, a compact sticky top bar and hamburger open a left off-canvas drawer
with `w-72 max-w-[85vw]`, a translucent backdrop, and account/logout within the
drawer. The permanent desktop sidebar is hidden. All application navigation
chrome is `print:hidden`.

One local grouped Blade structure is reused by the desktop and mobile navigation
component; there is no empty ADMIN section or future route.

| Group | Destinations |
| --- | --- |
| Main | Home |
| Sales | POS, Sales History |
| Catalog | Categories, Products, Variants |
| Inventory | Stock In, Opening Inventory, Stock Correction |

At that checkpoint Admin saw nine destinations; Staff saw seven. Tracker #16
subsequently changed Home to Dashboard and added Admin-only Reports (10 / 7).
Opening Inventory and Stock
Correction remain Admin-only. Backend authorization remains authoritative.
Both navigation renderings preserve POST logout with CSRF protection. No GET
logout route/link was introduced; AuthenticatedSessionController and logout
route semantics remain unchanged.

The hamburger uses `aria-controls` and `aria-expanded`; the drawer uses
`aria-hidden` and native `inert`; active links use `aria-current="page"`.
The explicit close button has an accessible label. Close button, backdrop,
Escape, and navigation-link selection close the drawer. Body scrolling locks
while open; background content and the mobile top bar become inert. Focus moves
to the close button on open and returns to the hamburger when appropriate,
after the top bar is restored to non-inert. Crossing into `lg` resets mobile
drawer state and restores background interaction. Only vanilla JavaScript is
used; no frontend framework or dependency was added.

### Limited page adjustments and feature boundary

- POS catalog/cart split and sticky cart moved from `lg` to `xl`; its three-card
  catalog grid moved from `xl` to `2xl`. Checkout/business behavior is unchanged.
- Sales History's five-column filter grid moved from `lg` to `xl`; filtering,
  queries, pagination, and receipt behavior are unchanged.
- Home received only direction-neutral navigation wording at that checkpoint;
  Tracker #16 subsequently replaced it with Dashboard.

Application routes at that checkpoint were 39; no route, migration, Composer dependency, npm
dependency, or controller/service/model change was introduced. The navigation
feature requires no database access. No MySQL gate or concurrency proof was
required: it adds no DB mutation, transaction, lock, schema, or concurrency
behavior.

### Historical automated verification

Ordinary tests used isolated SQLite `:memory:` only.

| Suite | Tests | Assertions |
| --- | --- | --- |
| ResponsiveNavigationTest | 3 | 156 |
| AuthenticationTest | 18 | 119 |
| PosAuthorizationTest | 5 | 43 |
| SalesHistoryAuthorizationTest | 6 | 49 |
| RestockAuthorizationTest | 5 | 54 |
| OpeningInventoryAuthorizationTest | 5 | 37 |
| StockCorrectionAuthorizationTest | 5 | 52 |
| Full ordinary suite | 170 | 1,858 |

Final full-suite duration: 25.886 seconds. PHP syntax, Blade compilation, Pint,
Vite production build, and `git diff --check` passed. Historical route audit
confirmed 39 application routes. The mobile-top-bar inert correction also
passed the Vite build and diff check before approved browser smoke.

### Historical manual browser smoke

- Mobile, approximately 414 × 846: compact top bar, hamburger, and branding
  visible; desktop sidebar hidden. Drawer opening and translucent backdrop
  worked. X, backdrop, Escape, and navigation-link closure worked. Body scroll
  lock, keyboard/focus behavior, and focus return behaved correctly. Account
  and Sign out were reachable; active Home state was visible; no obvious
  application horizontal overflow was observed.
- Narrow/tablet, 900 × 1024, Sales History: mobile top bar/hamburger shown and
  desktop sidebar hidden; content unobstructed. Filters stacked cleanly, Apply
  filters/Clear remained usable, historical rows remained readable, and no
  obvious application horizontal overflow was observed.
- Desktop, approximately 1366 × 768: fixed sidebar visible and mobile top
  bar/hamburger hidden. Content was correctly offset; grouped navigation and
  active state were correct. Navigation scrolled independently at constrained
  height; account/Sign out remained reachable, with no sidebar/content overlap.
  POS catalog/cart split remained usable, catalog was not visibly compressed,
  cart did not overlap, and existing POS data/UI was preserved.
- Exact boundary, 1023 × 768: mobile shell/hamburger shown, desktop sidebar
  hidden. At 1024 × 768: desktop sidebar shown, mobile top bar/hamburger hidden,
  and content offset beside the sidebar. This matches Tailwind `lg` =
  64rem / 1024px.

### Historical receipt print regression

Firefox Print Preview opened TRX-000002 from desktop sidebar mode. The printed
sheet contained no desktop sidebar, mobile top bar, hamburger, drawer/backdrop,
or account/navigation chrome, and no residual 240px left margin. Receipt content
started normally; TrackPro receipt branding, TRX-000002, Completed status, Admin
cashier, Test Hammer historical item, and quantity/prices/totals were visible.
The receipt fit one sheet. Firefox-generated headers/URL metadata are browser
print UI, not TrackPro application chrome.

### Historical Home observation, resolved by Tracker #16

The prior manual UI review identified the former simple Home landing page as
visually weaker than the application shell. Tracker #16 has now replaced it
with the operational Dashboard after approved design and verification. The
route name remains `home`; the page and navigation label are Dashboard.

## Tracker #16 Dashboard & Reports Evidence

Tracker #16 — Dashboard & Reports is COMPLETED. Application checkpoint:
`31b5b95f8d4de3136c15924bc541b52a6d2fa846`,
`Add dashboard and sales reports`. Implementation, automated verification,
manual browser smoke, commit, and normal push are complete. All results below
are recorded prior evidence; no tests, builds, browser requests, or database
access occur during this documentation-only update.

### Dashboard behavior and access

The existing `GET|HEAD /` route, named `home`, uses `DashboardController@index`.
Active Admin and Staff may access it; guests remain denied through auth and
disabled authenticated users through active-user middleware. Shared content is
Today's Sales, Transactions Today, Low Stock, Out of Stock, five Recent
Completed Sales, and five Low Stock Items. Staff does not receive the Admin-only
Seven-day Completed Sales Trend. No cost information appears.

All Dashboard sales analytics explicitly require `status = completed`. Today
uses Asia/Manila and `created_at >= start of Manila day` with
`created_at < start of next Manila day`, without `whereDate` or end-of-day
23:59:59 logic. Completed-only analytics accommodate eventual SALE_VOID;
SALE_VOID itself remains unimplemented.

Operational stock counts/lists require active Category, Product, and
ProductVariant rows. Low Stock remains `current_stock <= low_stock_threshold`;
Out of Stock is `current_stock = 0.000`. Zero stock is also low stock. Archived
hierarchy rows are excluded. Recent Completed Sales are limited to five, ordered
`created_at DESC, id DESC`, with receipt link, cashier, distinct item count,
and total. Sale history stays immutable; no checkout_token, cost, or
StockMovement internals are exposed.

The Admin trend covers today and the previous six Manila calendar days, includes
completed Sales only, fills zero-sale dates, and displays oldest to newest.
Blade/Tailwind bars introduce no chart dependency. BCMath handles exact decimal
normalization and presentation-only bar width; exact monetary text remains
authoritative.

### Reports v1 and historical semantics

Sales Summary is active Admin-only; Staff direct access receives 403. Staff
Sales History access is unchanged. Exactly one report route was introduced:
`GET|HEAD /reports`, `reports.index`, `ReportsController@index`, effective
middleware `web`, `auth`, `active`, `can:access-admin`. There is no
`reports.store`, `reports.export`, or `reports.print`.

Filters are `date_from`, `date_to`, and `cashier`. The default covers today plus
the previous six Manila calendar days. Explicit dates require both values in
strict `YYYY-MM-DD` format. Missing submitted pairs, malformed/impossible dates,
arrays/non-scalars, reversed ranges, and ranges over 366 inclusive calendar days
fail closed. Blank cashier means all cashiers; otherwise a positive integer is
required. Disabled historical cashiers with Sales remain selectable by name;
usernames are not exposed.

Output includes Completed Sales Total and Completed Transactions. Daily Sales
shows every selected Manila date, completed transaction count and total,
including zero-sale dates. Quantity Sold by Unit groups immutable
`sale_items.unit_snapshot`, keeps unlike units separate, and normalizes quantities
to three decimals. Reports use historical Sale/SaleItem evidence; current
ProductVariant unit or catalog state never rewrites that evidence.

User dates remain bound values in half-open predicates `>= start` and
`< next-day start`. Static daily grouping uses `DATE(sales.created_at)`; database
SUM/COUNT aggregates include `CAST(COALESCE(SUM(...), 0) AS CHAR)` for decimal-string
normalization. This syntax was verified in ordinary SQLite tests and is valid
for the intended MySQL 8 runtime. No user input is interpolated into raw SQL.
Authoritative money/quantity calculations use no PHP/JavaScript floating point.

### Accounting, navigation, and read-only boundary

Tracker #16 does not calculate/display cost_price, restock unit cost, purchase
cost, COGS, profit, gross profit, net profit, or margin. Current/latest cost
references and historical restock costs exist, but no formal FIFO,
weighted-average, or COGS engine exists; profit must not be inferred.
Checkout tokens, StockMovement internals, passwords, and authentication fields
are not exposed. No writes, AuditLog creation, transactions, locks, SALE_VOID,
CSV/PDF export, or duplicate Sales History workflow were introduced.

Admin's Main group contains Dashboard and Reports. Admin has 10 destinations;
Staff has seven. Reports, Opening Inventory, and Stock Correction remain
Admin-only. The responsive desktop/mobile shell and POST + CSRF logout are
preserved. `resources/views/welcome.blade.php` was replaced by
`resources/views/dashboard/index.blade.php`; no obsolete production welcome-view
reference remains. Route name `home` is retained while the visible page and
navigation label are Dashboard.

Application routes total 40. No production migration, model, service, Composer
dependency, npm dependency, JavaScript, or CSS change was introduced. Automated
verification required only isolated SQLite, with no MySQL or persistent database
access. No dedicated MySQL concurrency gate is required for read-only
Dashboard/Reports. Prior manual browser smoke read existing local data through
the application and performed no Sale/stock mutation; it is not fresh database
access during this documentation turn.

### SQLite fixture compatibility

Three existing custom SQLite scaffolds needed behavior-only support because
authenticated GET / now renders a data-backed Dashboard:

- `tests/Feature/Auth/AuthTestCase.php`: beyond existing users, added categories,
  products, product_variants, sales, and sale_items; no restock/movement/audit
  tables added. AuthenticationTest.php and AuthorizationTest.php are unchanged.
- `tests/Feature/Inventory/RestockTestCase.php`: added sales and sale_items.sale_id,
  reusing its existing sale_items table.
- `tests/Feature/Catalog/CatalogTestCase.php`: added sales and nullable
  sale_items.sale_id, reusing the existing table. Nullability deliberately
  preserves established headerless history-marker test rows.

These are test-harness compatibility changes only. No production controller,
route, or migration was weakened or changed as a fixture workaround. No assertion
was removed or skipped. No table was duplicated and no fourth fixture path was
needed; the final Home/custom-fixture audit found no unresolved dependency.

### Approved automated verification

All tests used isolated SQLite `:memory:`. Results are historical and not rerun
for this documentation checkpoint.

| Suite | Tests | Assertions |
| --- | --- | --- |
| DashboardAuthorizationTest | 4 | 17 |
| DashboardTest | 6 | 39 |
| ReportsAuthorizationTest | 5 | 23 |
| ReportsTest | 6 | 77 |
| Combined new Dashboard/Reports | 21 | 156 |
| ResponsiveNavigationTest | 3 | 165 |
| AuthorizationTest | 7 | 22 |
| AuthenticationTest | 18 | 119 |
| SalesHistoryAuthorizationTest | 6 | 49 |
| SalesHistoryTest | 7 | 141 |
| PosAuthorizationTest | 5 | 43 |
| RestockAuthorizationTest | 5 | 54 |
| CatalogAuthorizationTest | 4 | 43 |
| OpeningInventoryAuthorizationTest | 5 | 37 |
| StockCorrectionAuthorizationTest | 5 | 52 |
| ProductManagementTest | 7 | 38 |
| ProductVariantManagementTest | 12 | 95 |
| OpeningInventoryManagementTest | 13 | 124 |
| Full ordinary suite | 191 | 2,023 |

Final full-suite duration: 27.576 seconds. PHP syntax, Blade compilation, Pint,
Vite production build (2.47s), git diff --check, route/security/scope audits all
passed. Application route count: 40. No MySQL concurrency proof was required.

### Approved desktop Dashboard browser evidence

Displayed date: Tuesday, September 8, 2026. Today's Sales was ₱0.00,
Transactions Today 0, Low Stock 1, and Out of Stock 0. The Admin trend showed
Sep 7 at ₱300.00 / 2 sales, with every other displayed date at ₱0.00 / 0 sales.

Recent Completed Sales appeared in this order:

| Receipt | Date/time | Cashier | Distinct items | Total |
| --- | --- | --- | --- | --- |
| TRX-000002 | Sep 7, 2026 10:47 PM | Admin | 1 | ₱150.00 |
| TRX-000001 | Sep 7, 2026 10:46 PM | Admin | 1 | ₱150.00 |

Low Stock Items showed Test Hammer, Test Tools, 16oz · Claw, piece,
2.000 / 5.000. Dashboard navigation was active; Reports appeared under Main.
Desktop sidebar/content layout was clean, with no cost/token/profit visible.

### Approved Reports and receipt integration browser evidence

Default Reports used 2026-09-02 through 2026-09-08, All cashiers, and showed
Completed Sales Total ₱300.00 and Completed Transactions 2. Sep 7, 2026 showed
2 transactions / ₱300.00; other selected dates showed 0 / ₱0.00. Quantity Sold
by Unit showed piece / 2.000.

Explicit filtering used date_from 2026-09-07, date_to 2026-09-07, cashier Admin.
GET parameters were visible in the browser URL. The result was ₱300.00 / 2,
one Sep 7 daily row at 2 / ₱300.00, and quantity piece / 2.000.

Dashboard's TRX-000002 View receipt link navigated to /sales/2. The immutable
receipt showed TRX-000002, Completed, Admin, Test Hammer, 16oz · Claw, piece,
quantity 1.000, unit price ₱150.00, total ₱150.00, cash received ₱200.00,
and change ₱50.00. No cost/token/internal data was visible and no local Sale
or stock mutation was performed for smoke testing.

### Approved mobile browser evidence (approximately 414 × 846)

The compact mobile top bar appeared, desktop sidebar was hidden, and the drawer
worked. Dashboard and Reports were visible for Admin; account and Sign out were
reachable. Dashboard's four cards stacked; its seven-day trend, Sep 7 value/bar,
and zero-sale days were readable. Recent Sales showed TRX-000002 and TRX-000001
with usable receipt links. Low Stock Items showed Test Hammer 2.000 / 5.000.
There was no obvious application horizontal overflow.

Reports heading was readable; date/cashier inputs and summary cards stacked.
Apply filters and Reset were usable. Summary showed ₱300.00 / 2; Daily Sales
showed Sep 7 at 2 / ₱300.00 with readable zero days. Quantity Sold by Unit showed
piece / 2.000. Tables were usable with no obvious application horizontal
overflow. No Staff browser account was manufactured; automated authorization
provides Staff coverage.

## Branding Status

Branding checkpoint is complete.

Current branding includes:

- TrackPro SVG brand mark
- sidebar/mobile top-bar logo and wordmark
- SVG favicon
- split branded Login page
- orange Sign In CTA
- branded application shell and operational Dashboard

Authentication behavior was not changed.

Login remains:

- Username
- Password
- Sign in

No:

- Remember me
- Forgot password
- Registration
- Password reset

## Current Test Baseline

Verified final Tracker #16 ordinary suite (historical; not rerun for this update):

`191 tests / 2,023 assertions`

Historical final full-suite duration: 27.576 seconds, isolated SQLite `:memory:`.

Current application route count from the completed historical
`php artisan route:list --except-vendor` verification:

`40`

Before accepting a later stage, compare new results against the current code and
explain legitimate changes in counts.

Do not blindly require counts to remain identical when new tests/routes are
intentionally added.

## Current Inventory Rules

Opening Inventory:

- Admin-only
- first stock event
- zero is valid
- exactly once
- INITIAL_STOCK movement required

Normal Stock In:

- Admin and Staff
- requires opening initialization
- positive received quantity
- multi-item Restock supported
- immutable Restock / RestockItem history
- historical unit cost retained
- ProductVariant.cost_price updated to latest received cost
- one RESTOCK movement per item
- durable submission-token idempotency
- exact BCMath arithmetic

Stock Correction:

- Admin-only physical-target stock correction
- requires historical INITIAL_STOCK and active Category/Product/Variant hierarchy
- required normalized reason
- exact decimal-string / BCMath arithmetic
- quantity-only mutation; cost and selling price unchanged
- one immutable CORRECTION StockMovement; no AuditLog duplication
- latest StockMovement ID used as the stale-form version
- no-op rejected before stale-version check
- read-only correction history

Sales / POS:

- Active Admin and Staff; cash-only; initialized active catalog hierarchy only;
  no purchase-cost exposure.
- One to 100 submitted cart components; duplicate Variants consolidate
  server-side while original whole/fractional quantity rules remain authoritative.
- Exact BCMath arithmetic; zero-rounded lines and line/header overflow rejected;
  cash sufficiency enforced.
- Locked ProductVariant selling price is authoritative; `expected_unit_price`
  is only a stale-price precondition, and changed prices require cashier review.
- Deterministic Category → Product → ProductVariant lock order and current/locking
  historical INITIAL_STOCK checks.
- Unique Sale checkout token provides durable idempotency; no missing-token
  locking lookup before insertion. Unique Sale insertion precedes stock
  sufficiency, and transactional rollback protects temporary Sale headers.
- Completed equivalent replay resolves from immutable historical Sale evidence,
  including historical SaleItem prices; collision recovery uses current locks.
- Stock cannot become negative; one completed Sale, one SaleItem per distinct
  Variant, and one SALE StockMovement per SaleItem; no normal-checkout AuditLog.
- Completed Sale/SaleItem/StockMovement records are currently immutable, with no
  Sale edit/delete route and no SALE_VOID workflow.

Implemented application movement workflows: INITIAL_STOCK, RESTOCK, CORRECTION,
and SALE. SALE_VOID is schema-supported but not implemented.

## Current Next Step

Tracker #4 — Product Data Planning is COMPLETED on the approved source audit,
normalization plan, and reconciled local staging dataset. Its planning artifacts
are reviewed, committed, and pushed. Tracker #5 — UI/UX Planning is COMPLETED
on the reviewed retrospective/project-derived baseline in
`docs/ui-ux-plan.md`; its planning checkpoint is committed and pushed. Tracker
#3 remains COMPLETED on the approved requirements baseline. Tracker #16 remains
the latest completed application stage, automatically verified, manually
browser-smoked, committed, and pushed; Trackers #13 and #14 also remain
COMPLETED. The separate Responsive Navigation UI mini-checkpoint remains
IMPLEMENTED / VERIFIED outside the formal tracker count. The completed count is
**13 / 23**. Stop for review; Tracker #7 may be the next formal planning focus,
but no next tracker is started or authorized here.

#7 Test Case Preparation remains pending/in progress and is not started by this
closeout. Opening Inventory remains a separate physical count workflow; no
product data was loaded.

#15 Functional Testing retains its current pending/in-progress tracker status;
ongoing module tests do not formally complete it. #17 Edge Case & Permission
Testing also remains pending/in progress. Other remaining tracker work
is unchanged. SALE_VOID / Admin full-sale void, returns/refunds, discounts,
credit / utang, formal profit / COGS, CSV/PDF report exports, sales-by-cashier
ranking, top-selling variant report, general stock-movement report, User
Management UI, and remaining documentation/testing/presentation work remain
future scope.

## Documentation Maintenance Rule

Update this file after a meaningful project checkpoint, not after every tiny edit.

At minimum update:

- latest completed application/code checkpoint
- current stage/status
- tracker status
- completed application checkpoint chain
- implemented/not implemented scope
- test baseline
- known local-data state when it materially changes
- next step

Do not store credentials or secrets in this document.
