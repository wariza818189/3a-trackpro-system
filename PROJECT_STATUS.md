# 3A TrackPro — Project Status

Last updated: 2026-09-07

## Latest Completed Application Checkpoint

Branch:

`main`

Latest completed application/code checkpoint:

`ac154a2dda2e955ea849669120e86c9cbcf425ac`

Commit:

`Add TrackPro branding`

This is the application baseline immediately before the repository-guidance
documentation checkpoint.

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

Current next engineering stage:

Stage 3D — Admin Stock Correction / CORRECTION

Status:

Planning / not yet implemented

## Current Team Tracker Position

Tracker item:

#14 — Stock-In & Stock Movements

Status:

IN PROGRESS

Completed within tracker #14:

- Opening Inventory
- Normal Stock In / Restock
- RESTOCK StockMovement
- historical purchase-cost recording
- latest Variant cost reference

Remaining within tracker #14:

- Admin-controlled Stock Correction
- CORRECTION StockMovement
- final Stage 3D tests
- Stage 3D MySQL integrity proof
- Stage 3D Git checkpoint
- Stage 3D manual browser smoke

Tracker #14 may become COMPLETED only after the remaining Stock Correction work
is implemented and verified.

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

Not yet implemented:

- Admin Stock Correction
- CORRECTION movements
- POS
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
- current stock: 5.000
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

Current complete ordinary suite baseline:

- 112 tests
- 952 assertions

Current application route count (explicit routes in `routes/web.php`, excluding
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

Recent ordinary suite:

`112 tests / 952 assertions`

Recent application route count (`routes/web.php`, excluding framework routes):

`32`

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

- NOT YET IMPLEMENTED
- intended to be Admin-only
- intended movement type: CORRECTION
- final design pending Stage 3D inspection

## Current Next Step

Stage 3D read-only architecture/design inspection:

Admin Stock Correction / CORRECTION

Important design questions to resolve before implementation:

1. corrected target stock versus delta input
2. no-op correction policy
3. required reason
4. stale-form protection
5. concurrency between two corrections
6. concurrency between Correction and Restock
7. AuditLog duplication decision
8. exact CORRECTION database constraints
9. MySQL concurrency proof strategy

Do not implement Stage 3D until its inspection/design is explicitly approved.

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
