# 3A TrackPro Project Tracker

This file is the canonical project-status tracker for the repository.

- Project: 3A TrackPro — Hardware Store Sales and Inventory Management System
- Team: The Visionaries
- Final presentation: October 22, 2026
- Authoritative repository checkpoint:
  `740fa3c4474ba8255a7c4bbf6536a0d83560a341`

Project status is maintained in this repository tracker. Previous Notion
tracking is retained only as historical reference and is no longer the active
source of truth. `PROJECT_STATUS.md` remains a detailed historical checkpoint
and verification archive; this tracker supersedes its status summaries.

## Current Focus

The next development focus is **#25 — Purchase Order & Low-Stock
Prioritization**. It remains **Not Started** until an authorized implementation
checkpoint begins. Tracker #17 remains paused pending revised post-expansion
formal testing.

## Status Definitions

- **Completed** — implementation or documentation scope is finished and its
  required evidence/checkpoint is recorded.
- **In Progress** — authorized work has begun but the tracker item is not yet
  complete. A paused item retains this status.
- **Not Started** — no authorized implementation checkpoint has begun.
- **Closeout Pending** — implementation is complete, but a required formal
  closeout or checkpoint is still outstanding.

## Primary Tracker

| # | Tracker Item | Area | Status | Owner | Checkpoint / Evidence | Notes |
|---:|---|---|---|---|---|---|
| 1 | Create Tracking Document | Project management | Completed | — | Historical tracking baseline recorded in `PROJECT_STATUS.md` | Active status tracking has moved to this file. |
| 2 | Project Scope Planning | Planning | Completed | — | Approved project scope and expansion boundaries | Scope changes require an explicit checkpoint. |
| 3 | Client Problem & Requirements | Requirements | Completed | — | `docs/requirements.md` | Requirements baseline reviewed and reconciled. |
| 4 | Product Data Planning | Planning | Completed | — | `docs/product-data-plan.md` | Product-data planning artifact completed. |
| 5 | UI/UX Planning | Planning | Completed | — | `docs/ui-ux-plan.md` | Retrospective/project-derived UI/UX plan completed. |
| 6 | Workflow & Business Rules | Requirements | Completed | — | `docs/requirements.md`, `docs/database-design.md` | Authoritative workflow and business-rule baseline recorded. |
| 7 | Test Case Preparation | Testing | Completed | — | `docs/test-cases.md` — 79 consolidated cases; 87/87 requirements and assumptions represented | Distribution: #15 30, #17 37, guarded MySQL 8, review-only 4. |
| 8 | Documentation Outline | Documentation | In Progress | — | Existing repository documentation set | Broader project documentation is not complete. |
| 9 | Database & System Design | Design | Completed | — | `docs/database-design.md` | Implemented baseline plus approved additive expansion design. |
| 10 | Authentication & User Roles | Application | Completed | — | Authentication/authorization implementation and regression evidence | Active/disabled Admin and Staff rules remain server-authoritative. |
| 11 | Product & Inventory Module | Application | Completed | — | Catalog and inventory implementation checkpoints | Includes hierarchy lifecycle and authoritative Variant stock. |
| 12 | Sales/POS | Application | Completed | — | Cash-only POS implementation and regression evidence | Immutable Sale/item/movement behavior retained. |
| 13 | Receipt & Sales History | Application | Completed | — | Receipt/history implementation and verification checkpoint | Read-only history and reprint behavior completed. |
| 14 | Stock-In & Stock Movements | Application | Completed | — | Opening Inventory, Stock In, and Stock Correction checkpoints | Immutable movement history and exact stock semantics verified. |
| 15 | Functional Testing | Testing | Completed | — | `docs/functional-test-results.md`; run `FT15-20260909-A` | 30/30 passed; 0 failed, 0 blocked, 0 remaining. Dedicated `trackpro_test` is frozen/protected. |
| 16 | Dashboard & Reports | Application | Completed | — | Dashboard/Reports application checkpoint and regression evidence | Completed-sales reporting and role boundaries verified. |
| 17 | Edge Case & Permission Testing | Testing | In Progress | — | Historical run `FT17-20260912-A` | **PAUSED** pending revised post-expansion formal testing; preserved state: 8 pass, 1 fail, 0 blocked, 28 remaining. |
| 18 | User Guide & Screenshots | Documentation | Not Started | — | — | Separate future documentation work. |
| 19 | Final Integration & Bug Fixing | Integration | Not Started | — | — | Begins only after preceding implementation scope is ready. |
| 20 | Project Documentation Finalization | Documentation | Not Started | — | — | Separate from item-specific technical completion. |
| 21 | Presentation & Demo Preparation | Delivery | Not Started | — | — | Final-presentation preparation. |
| 22 | Final System Testing & Rehearsal | Testing | Not Started | — | — | Final integrated testing and rehearsal. |
| 23 | Final Presentation | Delivery | Not Started | — | — | Scheduled for October 22, 2026. |
| 24 | POS Opening Cash Register | Application / MySQL concurrency | Completed | — | `740fa3c` — #24A–#24E implemented and verified; isolated MySQL concurrency closeout complete | One global register; protected databases untouched; no further #24 production/test/harness work remains. |
| 25 | Purchase Order & Low-Stock Prioritization | Procurement | Not Started | — | Approved Phase A requirements/design | **Current development focus.** Planning alone does not start implementation. |
| 26 | PO-Based Receiving & Partial Delivery | Procurement | Not Started | — | Approved Phase A requirements/design | Depends on Purchase Order foundations. |
| 27 | Follow-up PO for Unfulfilled Quantities | Procurement | Not Started | — | Approved Phase A requirements/design | Follow-up traceability remains future scope. |
| 28 | Pending Purchase Orders Report | Reports | Not Started | — | Approved Phase A requirements/design | Requires authoritative PO/receiving evidence. |
| 29 | Unfulfilled Items Report | Reports | Not Started | — | Approved Phase A requirements/design | Requires authoritative outstanding quantities. |
| 30 | Damaged Items Recording & Report | Procurement / Reports | Not Started | — | Approved Phase A requirements/design | Recording must precede report implementation. |

## Status Summary

| Status | Count |
|---|---:|
| Completed | 16 |
| In Progress | 2 |
| Not Started | 12 |
| Closeout Pending | 0 |
| **Total** | **30** |

## Important Testing State

### Tracker #15

Formal run `FT15-20260909-A` completed with 30/30 passed, 0 failed,
0 blocked, and 0 remaining. The dedicated `trackpro_test` database is
frozen/protected. Detailed evidence is in
`docs/functional-test-results.md`.

### Tracker #17

FT17 is paused after the teacher scope expansion. Its old evidence is
historical and immutable: 8 pass, 1 fail, 0 blocked, and 28 remaining.
`AUTH006` retains its initial formal FAIL and provisional disposition
`TEST_SPEC_PROCEDURE_DEFECT`; the controlled cross-origin retest is deferred to
the FT17 security closeout. This tracker does not reinterpret that evidence.

### Tracker #24

Tracker #24 is technically complete at `740fa3c4474ba8255a7c4bbf6536a0d83560a341`.
Its implementation checkpoints are:

- `71cef2a` — Add cash register session schema
- `45bcbc7` — Implement cash register lifecycle
- `4806173` — Require active register for checkout
- `02b69a3` — Add cash register controls to POS
- `3eb52cf` — Add isolated MySQL verification harness
- `3fdbd0a` — Add MySQL cash register schema verification
- `4b99922` — Add dedicated MySQL concurrency test harness
- `a4d5717` — Retry and verify cash register concurrency
- `e0aed99` — Add stale-snapshot cash register replay test
- `740fa3c` — Add dedicated MySQL sale concurrency tests

Accepted verification evidence:

- ordinary full regression: 280 tests / 2543 assertions passed;
- dedicated schema verification: empty-state 1/109 and complete-state 1/110
  passed;
- cash-register concurrency A–E: 125 assertions;
- Sale concurrency 1–4: 170 assertions; and
- combined dedicated concurrency: 295 assertions across nine individually
  executed scenario tests, not one combined nine-test PHPUnit execution.

The `OpenCashRegister` retry fixed the real MySQL concurrent-double-open
deadlock. Protected databases remained untouched. Future dedicated live MySQL
runs may require a host-capable context that can access the Unix socket.
Procurement trackers #25–#30 remain separate.

## Tracker Conventions

- The primary table is authoritative for current status and focus.
- Detailed evidence remains in repository documents, tests, and Git history;
  the tracker records concise checkpoints rather than duplicating reports.
- A planning discussion does not move a tracker item to **In Progress**.
- Historical test verdicts are immutable. Retests add evidence rather than
  rewriting prior formal results.
- Status changes must be committed so repository history records who changed
  the tracker and when.
