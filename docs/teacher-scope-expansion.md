# Teacher Scope Expansion

## Context

After teacher consultation, additional requirements were added to 3A TrackPro.
This document records the teacher-requested scope, current planning direction,
and unresolved architecture decisions. It does not claim that the added scope
is implemented, that schema changes exist, or that the formal requirements and
test catalog have already been revised.

## Added Tracker Tasks

### #24 POS Opening Cash Register

Requirement:

POS must support a starting cash box / opening cash register amount.

### #25 Purchase Order & Low-Stock Prioritization

Requirement:

Create Purchase Orders, with low-inventory items prioritized/recommended when
preparing a PO.

### #26 PO-Based Receiving & Partial Delivery

Requirement:

Delivery/received inventory must be based on a Purchase Order and partial
receiving must be supported.

### #27 Follow-up PO for Remaining Items

Requirement:

Remaining items or quantities not fulfilled from the original Purchase Order
must be able to continue through another/follow-up Purchase Order.

### #28 Pending Purchase Orders Report

Requirement:

Provide visibility/reporting for POs that are still pending or not completely
fulfilled.

### #29 Unfulfilled Items Report

Requirement:

Report PO line quantities that remain unfulfilled.

### #30 Damaged Items Recording & Report

Requirement:

Provide a report for damaged items.

Derived system requirement: damaged quantities must be captured during the
receiving workflow so the report is based on auditable transaction data rather
than manually entered report data.

## Confirmed Planning Direction

These are current planning decisions, not final implementation claims.

### POS opening cash

- Introduce a minimal cash-register/session concept.
- Opening cash is not sales revenue.
- Do not automatically expand scope into full cash-drawer accounting,
  shortage/overage, expenses, or reconciliation.

### Purchase Orders

- Regular future replenishment should become PO-based.
- Low-stock items should be prioritized/recommended.
- Do not invent automatic purchase quantities unless a rule is later approved.

### Receiving

- Reuse the current Restock/Stock-In engine where safe.
- New regular receiving should reference a PO.
- Existing historical Restocks remain valid.
- Partial receiving must be supported.

### Damaged items

- Damaged quantity must not increase sellable inventory.
- Damaged receiving must remain auditable.

### Follow-up PO

- Remaining quantity transferred to a follow-up PO must not remain
  simultaneously counted as open demand in both POs.
- Original PO history must remain traceable.

### Reports

New reporting scope:

- Pending Purchase Orders
- Unfulfilled Items
- Damaged Items

## Existing Stock-In Clarification

Existing manual Stock-In functionality is part of the current implemented
baseline.

The intended expanded workflow is:

`Purchase Order -> Receiving -> existing Restock/StockMovement inventory engine`

Therefore:

- do not delete historical Stock-In records;
- do not rewrite existing history;
- old Restocks may remain without a PO relationship;
- future new regular replenishment is intended to require a PO at the
  application/workflow level; and
- final schema decisions remain pending repository impact analysis.

## Current FT17 State

Formal run:

`FT17-20260912-A`

Paused tally:

**Pass 8 / Fail 1 / Blocked 0 / Remaining 28**

Completed evidence remains preserved. FT17 is paused because the final
application scope has changed. The old results are pre-expansion evidence, and
the remaining old cases should not be blindly executed against a changing
architecture. Historical verdicts must not be rewritten; `TC-AUTH-006` remains
the preserved formal **FAIL**.

## Pending Architecture Decisions

The following decisions remain unresolved:

- exact cash-register session schema and close behavior;
- PO schema and lifecycle/status representation;
- supplier name snapshot versus Supplier entity;
- exact PO-to-Restock relationships;
- damaged-item source of truth;
- follow-up PO remainder-transfer representation;
- whether open/pending PO quantities affect low-stock prioritization;
- legacy nullable foreign-key strategy; and
- exact permission policy for PO management and receiving.

## Scope Control

The following are currently outside the recorded teacher-requested expansion
unless later requested:

- Supplier CRUD/master data
- supplier payments
- accounts payable
- invoices
- purchase returns
- purchase discounts
- tax/VAT accounting
- approval workflows
- multi-warehouse
- multi-register
- closing-cash reconciliation
- shortage/overage
- employee shift management
- cash expenses
- damaged-item photo uploads
- PDF/CSV procurement export

These items are not permanently forbidden; they are outside the recorded
teacher-requested expansion.

## Next Step

Conduct a read-only repository impact audit before:

- changing formal requirements;
- changing database design;
- creating migrations;
- implementing application code;
- revising the test catalog;
- resuming formal FT17 execution.
