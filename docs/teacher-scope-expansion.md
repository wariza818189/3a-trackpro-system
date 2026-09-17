# Teacher Scope Expansion

## Context

After teacher consultation, additional requirements were added to 3A TrackPro.
This document records the teacher-requested scope and the approved Phase A
planning direction. It does not claim that the added scope is implemented or
that schema changes exist. The Phase A formal requirements and additive
database design are documented; application implementation has not begun. The
test catalog has not yet been revised.

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

- Introduce one global active cash-register session for the current
  single-register system.
- Opening cash may be zero. Admin and Staff may open the register.
- Staff may close the active session they opened; Admin may close any active
  session. Close Register only ends the current session.
- Opening cash is not sales revenue.
- Historical Sales may have no session relationship, while every new Sale must
  retain the server-selected active session. The browser does not select it.
- Register-session details do not need to appear on customer receipts.
- Do not automatically expand scope into full cash-drawer accounting,
  shortage/overage, expenses, reconciliation, or shift accounting.

### Purchase Orders

- Regular future replenishment should become PO-based.
- Admin creates/edits POs and creates follow-up POs. Admin and Staff may
  perform PO-based receiving.
- Store required `supplier_name` historical text on each PO; do not introduce
  Supplier CRUD/master data.
- Each PO item stores required expected unit cost as planning evidence. Admin
  may edit it while the PO is pending without procurement activity; accepted
  receiving, damaged receiving, or outgoing transfer activity makes it
  immutable. Actual receiving cost remains separate immutable receipt evidence
  and never rewrites expected cost.
- Low-stock recommendations use `current_stock <= low_stock_threshold` and
  require an active Category, Product, and Variant plus INITIAL_STOCK evidence.
- Show uncovered low-stock items first. Keep covered low-stock items visible in
  an "Already covered by open PO" group with their open quantity.
- Do not invent automatic purchase quantities or target-stock arithmetic.
- Use `pending`, `partially_received`, `completed`, and
  `closed_with_remainder`; the last two are terminal for receiving/editing.

### Receiving

- Reuse the current Restock/Stock-In engine where safe.
- New regular receiving must reference a PO at the application level.
- Existing historical Restocks and RestockItems remain valid through nullable
  legacy PO relationships and are not backfilled.
- Partial receiving must be supported.
- `RestockItem.quantity` remains accepted sellable quantity. Only accepted
  quantity increases stock and creates a RESTOCK movement.
- Prevent over-receiving, duplicate receipt effects, and concurrent receipt or
  transfer races with transactions, locking/current reads, and idempotency.

### Damaged items

- Use separate damage-detail evidence linked to the Restock, PO item, Variant,
  immutable identity/unit snapshots, required note, actor, and timestamp.
- Damaged quantity must not increase sellable inventory or create a RESTOCK
  StockMovement. It remains unfulfilled/outstanding.
- Damage-only receiving is valid and remains auditable even when accepted
  quantity and accepted-cost total are zero.

### Follow-up PO

- Use PO header parent linkage plus explicit item-transfer evidence.
- Admin may select one or more source lines with open outstanding quantity.
  Each selected line transfers its entire current remainder; arbitrary partial
  splitting within a selected line is not allowed.
- A source line may transfer only once. Unselected outstanding lines remain
  open on the source PO.
- Each target follow-up line receives the selected source line's complete
  remainder as its ordered quantity. The incoming transfer records origin but
  is not receiving activity, so the new follow-up PO starts `pending`.
- Outgoing transfer affects the source PO lifecycle. Transferred demand must
  not remain open on both POs, and the source becomes
  `closed_with_remainder` only after no open outstanding remains and at least
  some remainder was transferred out.
- Original history and chained follow-up traceability must be preserved.

### Reports

New reporting scope:

- Pending Purchase Orders
- Unfulfilled Items
- Damaged Items

The reports are Admin-only and remain under the existing Reports area.
Purchase Order and receiving operations belong under Procurement. No CSV/PDF
procurement export is included.

### Variant lifecycle

- PO activity becomes history-sensitive catalog evidence for Variant identity,
  unit, and quantity-mode changes.
- Lifecycle rules must preserve the ability to receive valid open POs without
  rewriting historical identity snapshots.

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
- the additive schema uses nullable legacy PO relationships.

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

## Approved Phase A Architecture Decisions

The read-only repository impact audit is complete. The Phase A design now
approves:

- a database-enforced single active register session and minimal close action;
- nullable legacy Sale-to-session and Restock/RestockItem-to-PO relationships;
- PO headers and items with supplier text, expected cost, approved lifecycle,
  and server-authoritative outstanding calculations;
- the primary/covered low-stock recommendation groups;
- reuse of the existing Restock and StockMovement engine for accepted goods;
- a separate auditable damage-detail source of truth with no damage movement;
- parent PO chain linkage plus explicit full-remainder selected-line transfer
  evidence; and
- the Admin/Staff ownership and Admin-only reporting policy recorded above.

These are approved documentation and implementation design decisions, not
claims that migrations, models, services, screens, or tests already exist.

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

Prepare the controlled implementation plan and implementation checkpoints for
the documented Phase A design. The test catalog will be revised in a later
separate controlled checkpoint before a new formal integration/edge run; the
preserved FT17 run will not be resumed.
