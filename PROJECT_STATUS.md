# 3A TrackPro — Project Status

Last updated: 2026-09-07

## Latest Completed Application Checkpoint

Branch:

`main`

Latest completed application/code checkpoint:

`e3cdb0858af110afce70619c421d85a1d8a4c73a`

Commit:

`Add admin stock correction workflow`

This is the completed Stage 3D application baseline. Documentation-only updates
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

Latest completed engineering stage:

Stage 3D — Admin Stock Correction / CORRECTION

Status:

COMPLETED — implementation review, ordinary tests, MySQL concurrency proofs,
manual browser smoke, and application commit/push approved.

## Current Team Tracker Position

Tracker item:

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

Not yet implemented:

- POS / Sales
- sales checkout
- SALE stock movements
- receipt workflow
- sales history
- Admin full-sale void
- SALE_VOID
- Dashboard
- Reports
- User Management UI
- final integration

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
- current stock: 4.000
- low-stock threshold: 5.000
- status: active
- INITIAL_STOCK movement recorded at zero
- Stock In receipt: RST-000001
- Stock In reference: DR-STAGE3C-001
- Restock quantity: 5.000
- historical Restock unit cost: 110.00

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

Verified final Stage 3D ordinary suite (historical; not rerun for this update):

`134 tests / 1,152 assertions`

Current application route count from `php artisan route:list --except-vendor`:

`35`

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

## Current Next Step

The next substantive application work is the POS / Sales module.

Status: NOT STARTED / not implemented.

POS / Sales requires separate explicit approval. No POS design or implementation
is part of this documentation checkpoint.

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
