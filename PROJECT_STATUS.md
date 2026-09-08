# 3A TrackPro — Project Status

Last updated: 2026-09-08

## Latest Completed Application Checkpoint

Branch:

`main`

Latest completed application/code checkpoint:

`2fc45125bb1876b49b8a501b8896801fa709b727`

Commit:

`Add receipt and sales history`

This is the completed Tracker #13 Receipt & Sales History application baseline. Documentation-only updates
may follow without changing this latest completed application checkpoint.

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

Latest completed engineering stage:

Tracker #13 — Receipt & Sales History

Status:

COMPLETED — explicit design/authorization approval, implementation, focused
ordinary SQLite tests, full ordinary regression, security/privacy review, route
audit, Pint/build/diff checks, manual browser smoke, read-only inventory
confirmation, and the application Git checkpoint and normal push are complete
and approved. Tracker #13 required no dedicated MySQL concurrency proof.

## Current Team Tracker Position

Tracker item:

#13 — Receipt & Sales History

Status:

COMPLETED

All required completion gates listed above passed. Tracker #12 remains COMPLETED;
its one-time completed/replayed POS confirmation now links to the receipt.

Overall completed tracker count: **9 / 23**.

Completed tracker items:

- #1 Create Tracking Document
- #2 Project Scope Planning
- #6 Workflow & Business Rules
- #9 Database & System Design
- #10 Authentication & User Roles
- #11 Product & Inventory Module
- #12 Sales / POS Module
- #13 Receipt & Sales History
- #14 Stock-In & Stock Movements

All other tracker statuses remain unchanged. #15 Functional Testing retains its
existing pending/in-progress status; module tests do not formally complete it.
#17 Edge Case & Permission Testing is not marked complete by these feature tests.

Next major unimplemented application tracker, after the separate planned
responsive-navigation UI mini-checkpoint:

#16 — Dashboard & Reports — NOT STARTED

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

Not yet implemented:

- Admin full-sale void
- SALE_VOID
- returns/refunds
- discounts
- credit / utang
- Dashboard
- Reports
- User Management UI
- final integration
- remaining documentation/testing/presentation work
- responsive sidebar/hamburger navigation (separate planned UI mini-checkpoint)

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

## Branding Status

Branding checkpoint is complete.

Current branding includes:

- TrackPro SVG brand mark
- navbar logo/wordmark
- SVG favicon
- split branded Login page
- orange Sign In CTA
- branded Home hero

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

Verified final Tracker #13 ordinary suite (historical; not rerun for this update):

`167 tests / 1,702 assertions`

Current application route count from the completed historical
`php artisan route:list --except-vendor` verification:

`39`

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

Tracker #13 is COMPLETED. A separate responsive-navigation UI mini-checkpoint is
PLANNED after its closure: desktop fixed/sidebar navigation, a mobile
hamburger/off-canvas drawer, grouped navigation sections, responsive behavior,
preserved role visibility, preserved POST+CSRF logout, and print-hidden
application chrome. This redesign is not part of Tracker #13 and is not
implemented yet.

After that separate UI mini-checkpoint, the next major unimplemented application
tracker is #16 — Dashboard & Reports. Status: NOT STARTED. It is not started or
completed during this documentation turn.

#15 Functional Testing retains its current pending/in-progress tracker status;
ongoing module tests do not formally complete it. Other remaining tracker work
is unchanged. SALE_VOID / Admin full-sale void, returns/refunds, discounts,
credit / utang, Dashboard & Reports, User Management UI, and remaining
documentation/testing/presentation work remain future scope.

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
