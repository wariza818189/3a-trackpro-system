# 3A TrackPro — Client Problem and Requirements Baseline

## 1. Document status

This is the team-approved requirements baseline for Tracker #3, reconciling
approved project design decisions with implemented and verified system behavior.
It also records the approved Phase A requirements for teacher expansion #24–#31.
The #26 receiving, #27 follow-up, #28 Pending Purchase Orders, #29
Unfulfilled Items, and #30 damaged receiving requirements below reflect
completed implementation; the separate #31 Damaged Items Report remains
planned.
Validation basis: project/team baseline, reconciled against implemented and
verified system behavior and the approved expansion planning record.

No original client interview transcript or formal client sign-off is available
in the repository. Client-specific statements are therefore not attributed or
fabricated. Any undocumented present-store workflow is an assumption, not fact.
The problem statement, assumptions, adopted requirements, implemented baseline,
planned work, and exclusions are distinguished below.

Sources: [project status and verification evidence](../PROJECT_STATUS.md),
[project constraints](../AGENTS.md), [system overview](../README.md), and
[database/workflow design](database-design.md). Older stage descriptions are
historical; the application checkpoint in section 12 anchors current behavior.
This document does not change formal completion counts in PROJECT_STATUS.md;
that review/status checkpoint is separate.

## 2. Project context

| Item | Project context |
| --- | --- |
| System name | 3A TrackPro |
| Target organization | 3A Hardware Store |
| System type | Hardware Store Sales and Inventory Management System |
| Business/deployment scope | Single-location hardware-store project scope |
| Primary application roles | Admin and Staff |

Roles describe application permissions, not named employees or job titles.

## 3. Project-derived problem statement

3A TrackPro addresses the need for one controlled system that coordinates
hardware catalog information, product variants, stock quantities, opening
inventory, stock receiving, stock corrections, cash sales, transaction
receipts/history, low-stock visibility, and management summaries.

The system is intended to reduce reliance on fragmented operational handling
and provide consistent, authorized, traceable records. This is a project-derived
purpose, not an observation of a particular previous store process. No specific
previous tool, lost records, theft, quantified discrepancies, lost revenue,
customer complaints, or transaction delays are asserted.

## 4. Assumptions

| ID | Assumption / basis |
| --- | --- |
| A-01 | 3A Hardware Store is treated as a single operational location for this project. |
| A-02 | Sales and inventory activities benefit from centralized recording and stock visibility. |
| A-03 | Two application permission levels, Admin and Staff, are sufficient for current v1. |
| A-04 | Cash is the only supported payment mode in v1. |
| A-05 | V1 does not depend on customer accounts, Supplier CRUD/master data, credit, returns/refunds, or accounting integration; Purchase Orders use required historical supplier-name text instead. |
| A-06 | No claim is made about the client's exact previous manual tools or quantified operational losses. |
| A-07 | Requirements are team-approved/project-derived unless separately identified as direct documentary client evidence; none is identified here. |
| A-08 | The current scope has one global physical/logical cash register, not multiple registers or employee shifts. |
| A-09 | Supplier identity on a Purchase Order is required historical text, not a relationship to Supplier master data. |
| A-10 | Low-stock prioritization does not establish or invent an automatic reorder quantity. |
| A-11 | Damaged delivered quantity remains unfulfilled until the corresponding demand is fulfilled by an accepted replacement/subsequent delivery or transferred to a follow-up PO. |
| A-12 | A follow-up transfer moves the complete current outstanding quantity for each selected source PO line; arbitrary partial splitting of a selected line is not supported. |

## 5. Users and responsibilities

Admin may perform normal Staff workflows plus catalog mutation, Opening
Inventory, Stock Correction, Reports, the Admin-only Dashboard trend, and
Purchase Order creation/editing, receiving, and follow-up creation. Admin may
open the register and close any active register session. Other future sensitive
procurement workflows require implementation before they become available.
Admin does not imply store owner.

Staff may use POS, browse permitted catalog/inventory, perform legacy Stock In
and PO-based receiving, access Sales History/receipts, and use the operational
Dashboard. Staff may open the register, close the active session they opened,
and view cost-redacted operational PO information. Staff does not imply a
particular employee or job title. All application access requires an active
authenticated account; public visitors have no operational access.

Staff may enter a new actual receipt cost, but cannot see catalog cost, expected
PO cost, or prior actual receipt costs. POS, Dashboard, Reports, and Sales
receipts/history do not expose purchase costs.

## 6. Functional requirements

Rows below distinguish the implemented application checkpoint from the planned
teacher expansion. Acceptance outcomes describe required observable behavior;
the status column is authoritative about whether that behavior exists.
Admin/Staff entries mean active authenticated users unless otherwise stated.

| ID | Requirement | Role | Acceptance outcome | Implementation status / traceability |
| --- | --- | --- | --- | --- |
| FR-AUTH-01 | Authenticate using username/password. | Admin, Staff | Valid active credentials grant access; invalid credentials do not. | Implemented; #10 |
| FR-AUTH-02 | Deny continued use by disabled accounts. | Admin, Staff | A disabled account is denied protected requests, including after login. | Implemented; #10 |
| FR-AUTH-03 | Provide no public self-registration. | Public | No public registration route/form is available. | Implemented boundary; #10 |
| FR-AUTH-04 | Protect logout with POST and CSRF. | Admin, Staff | POST signs out; GET cannot log out; browser mutation retains CSRF protection. | Implemented; #10 |
| FR-CAT-01 | Allow Admin to manage Categories. | Admin | Valid create/edit/lifecycle actions succeed; invalid changes are rejected. | Implemented; #11 |
| FR-CAT-02 | Allow Admin to manage Products under Categories. | Admin | Products retain valid parent relationships and lifecycle restrictions. | Implemented; #11 |
| FR-CAT-03 | Allow Admin to manage Product Variants. | Admin | Valid variant attributes/prices can be maintained subject to stock, transaction, and procurement-history restrictions. | Implemented; open-PO identity/lifecycle protections from #26A are implemented; #11/#25/#26 |
| FR-CAT-04 | Preserve catalog history through archive/reactivate lifecycle. | Admin | No catalog hard-delete route exists; positive-stock variants cannot be archived, and open-PO history preserves valid receiving requirements. | Implemented; #26A lifecycle/history extension implemented; #11/#25/#26 |
| FR-CAT-05 | Provide safe catalog browsing to Staff. | Staff | Only permitted active hierarchy is shown; costs and mutation access are denied. | Implemented; #11 |
| FR-INV-01 | Keep an authoritative stock pool per Variant. | Admin, Staff | Each operation affects its specified variant balance; ordinary catalog forms cannot set stock. | Implemented; #11/#14/#12 |
| FR-INV-02 | Support piece, sheet, roll, m, and kg units. | Admin | Supported units are accepted and retained; unsupported units are rejected. | Implemented; #11 |
| FR-INV-03 | Support whole and fractional quantity modes. | Admin, Staff | Whole-mode fractions and excess precision are rejected; eligible fractional quantities are accepted up to three decimals. | Implemented; #11/#14/#12 |
| FR-INV-04 | Prevent negative current stock. | Admin, Staff | Invalid operations leave no negative balance. | Implemented; #14/#12 |
| FR-OPEN-01 | Record opening physical stock once per eligible Variant. | Admin | A first eligible opening succeeds; a later opening is rejected based on history. | Implemented; #14 |
| FR-OPEN-02 | Accept zero opening quantity. | Admin | Zero records initialization even though the balance remains zero. | Implemented; #14 |
| FR-OPEN-03 | Record opening movement evidence. | Admin | A successful opening creates INITIAL_STOCK with trusted actor and quantities. | Implemented; #14 |
| FR-STOCKIN-01 | Allow stock receiving by Admin and Staff. | Admin, Staff | Historical manual Restocks remain valid; Admin and Staff may receive accepted quantities against open PO lines in the active initialized hierarchy. | Implemented for legacy Stock In and PO-based receiving; #14/#26 |
| FR-STOCKIN-02 | Update stock atomically during receiving. | Admin, Staff | Accepted PO lines, balances, actual costs, and movements commit together or none do; equivalent retries do not duplicate effects. Damage is separate immutable evidence and changes no stock or cost. | Implemented for accepted and damaged PO receiving; #14/#26/#30 |
| FR-STOCKIN-03 | Preserve historical actual received cost. | Admin, Staff | Actual cost is immutable on each RestockItem; later receipts and actual cost do not rewrite expected PO cost. | Implemented for legacy and PO receiving; #14/#26 |
| FR-STOCKIN-04 | Maintain the latest accepted received-cost reference. | Admin, Staff | Successful accepted receiving updates the Variant reference to actual received cost; damage-only receipt does not change cost. | Implemented for accepted and damage-only PO receiving; #14/#26/#30 |
| FR-STOCKIN-05 | Record receiving movement evidence. | Admin, Staff | Each accepted RestockItem has one RESTOCK movement; damage-only lines create no StockMovement. | Implemented for accepted and damaged PO receiving; #14/#26/#30 |
| FR-PO-01 | **Teacher-requested:** Create Purchase Orders. | Admin | Admin can create and edit a pending PO with required historical supplier text, Variant lines, quantities, and expected costs before activity; accepted receiving, damaged receiving, or outgoing transfer activity freezes protected fields. | Planned; #25 |
| FR-PO-02 | **Teacher-requested:** Prioritize/recommend low-stock items for PO creation. | Admin | Active initialized low-stock Variants without open coverage appear first; already-covered low-stock Variants remain visible/searchable with their open quantity; no reorder quantity is invented. | Planned; #25 |
| FR-RECV-01 | **Teacher-requested:** Receive inventory from a PO and support partial delivery. | Admin, Staff | A new receipt references a PO and may accept less than a line's open outstanding quantity while preserving remaining demand; Admin can view expected/prior actual costs and Staff can enter a new actual cost without viewing protected costs. | Implemented; #26A–#26C |
| FR-RECV-02 | **Derived:** Increase sellable stock only for accepted quantity. | Admin, Staff | Accepted quantity alone updates current stock, latest received cost, and RESTOCK movement evidence. | Implemented atomically with linked receipt evidence; #26B |
| FR-RECV-03 | **Derived:** Preserve auditable damaged receiving evidence without a stock increase. | Admin, Staff | Damage, including damage-only receiving, records positive DECIMAL(14,3) quantity, required normalized note, and historical snapshots; actor/receipt/time context comes through Restock, PO context through PurchaseOrderItem. Damage creates no stock or cost change or StockMovement and remains outstanding. | Implemented; immutable `RestockDamageItem` under the existing Restock token; #30A–#30D |
| FR-RECV-04 | **Derived:** Prevent over-receiving, duplicate receipt effects, and unsafe concurrent receipt effects. | Admin, Staff | Authoritative locked PO evidence caps accepted quantity at current outstanding; damage quantity is uncapped by outstanding and never reduces it. Equivalent receipt retries compare accepted and damage semantics without duplicate effects. | Implemented; accepted receiving and damage races guarded MySQL concurrency-verified; #26D/#30D |
| FR-FOLLOWUP-01 | **Teacher-requested:** Continue remaining quantities through a follow-up Purchase Order. | Admin | Admin may select one or more open source PO lines and create a traceable child PO; each selected line transfers its full current outstanding quantity. Supplier is required and prefilled from source; source snapshots are preserved and expected planning cost may be changed. | Implemented; #27A–#27C |
| FR-FOLLOWUP-02 | **Derived:** Prevent duplicated remainder transfer and double-counted demand. | Admin | Each selected line transfers its complete current remainder once; unselected lines remain open; transferred demand is open on only the target PO; transfer is immutable procurement evidence and creates no inventory or StockMovement effect. UUID replay returns the same child. | Implemented and guarded MySQL concurrency-verified; #27A–#27D |
| FR-CORR-01 | Restrict stock correction to Admin. | Admin | Staff direct requests are forbidden. | Implemented; #14 |
| FR-CORR-02 | Require a physical target and reason for correction. | Admin | An eligible nonnegative target and nonblank reason are required. | Implemented; #14 |
| FR-CORR-03 | Reject stale and no-op corrections. | Admin | Intervening movement invalidates the form; unchanged targets cause no write. | Implemented; #14 |
| FR-CORR-04 | Correct quantity with movement evidence only. | Admin | Stock changes with one CORRECTION movement; cost stays unchanged. | Implemented; #14 |
| FR-REG-01 | **Teacher-requested:** Record a starting cash box/opening register amount. | Admin, Staff | Either role can open the single register with a nonnegative amount, including zero. | Planned; #24 |
| FR-REG-02 | **Derived:** Enforce at most one active register session and provide minimal closure. | Admin, Staff | Concurrent opens cannot create two active sessions; Staff may close their own session and Admin may close any session without reconciliation. | Planned; #24 |
| FR-REG-03 | **Derived:** Require and retain the authoritative active register relationship for new Sales. | Admin, Staff | New checkout is blocked without an active session; the server selects and locks it; legacy Sales may have no session link. | Planned; #24 |
| FR-POS-01 | Provide cash-only POS access through the active register. | Admin, Staff | Both roles can perform eligible cash checkout only while the global register is active; other payment workflows are absent. | Cash-only baseline implemented; register precondition planned; #12/#24 |
| FR-POS-02 | Determine checkout price and stock on the server. | Admin, Staff | Submitted values cannot override stock or price; stale expected prices are rejected. | Implemented; #12 |
| FR-POS-03 | Complete checkout atomically. | Admin, Staff | The server-selected register relationship, Sale, items, deductions, and movements commit together or roll back. | Implemented baseline; register relationship planned; #12/#24 |
| FR-POS-04 | Reject checkout exceeding available stock. | Admin, Staff | Insufficient stock produces no Sale or deduction, including concurrent checkout. | Implemented; #12 |
| FR-POS-05 | Prevent duplicate effects on equivalent retry. | Admin, Staff | Equivalent checkout-token replay returns the existing Sale without another deduction. | Implemented; #12 |
| FR-POS-06 | Preserve successful checkout evidence. | Admin, Staff | One immutable Sale and one SaleItem plus SALE movement per distinct Variant are recorded; each new Sale retains its register session while legacy Sales remain valid without one. | Implemented baseline; register relationship planned; #12/#24 |
| FR-POS-07 | Require sufficient cash and exact change. | Admin, Staff | Underpayment is rejected; change equals cash less authoritative total. | Implemented; #12 |
| FR-SALES-01 | Allow browsing of Sale history. | Admin, Staff | Both roles can browse Sales regardless of the recording user, with historical status preserved. | Implemented; #13 |
| FR-SALES-02 | Filter Sales History by receipt, user recording the sale, and Manila date. | Admin, Staff | Receipt, cashier, and date filters narrow results; invalid filters fail closed. | Implemented; #13 |
| FR-SALES-03 | Display immutable historical receipt evidence. | Admin, Staff | Later catalog changes do not alter stored receipt identity, units, quantities, or prices. | Implemented; #13 |
| FR-SALES-04 | Support browser receipt reprinting. | Admin, Staff | The same receipt can be printed again without a new Sale or stock change. | Implemented; #13 |
| FR-SALES-05 | Keep purchase costs and checkout tokens private. | Admin, Staff | Neither appears in Sales History or receipt responses. | Implemented; #13 |
| FR-NAV-01 | Provide desktop sidebar navigation. | Admin, Staff | At lg and above, destinations are reachable beside unobstructed content. | Implemented; UI mini-checkpoint |
| FR-NAV-02 | Provide mobile navigation below lg. | Admin, Staff | Below 64rem / 1024px, a top bar and off-canvas drawer replace the sidebar. | Implemented; UI mini-checkpoint |
| FR-NAV-03 | Match navigation visibility to role permissions. | Admin, Staff | Destinations reflect authorized Sales, Procurement, Inventory, and Reports workflows; restricted destinations are absent for Staff. | Implemented baseline counts; expansion revision planned; UI mini-checkpoint/#16/#24–#30 |
| FR-NAV-04 | Support keyboard and focus interaction in the drawer. | Admin, Staff | Focus enters the drawer; Escape closes it; focus returns appropriately and background interaction is controlled. | Implemented; UI mini-checkpoint |
| FR-DASH-01 | Provide an operational Dashboard. | Admin, Staff | Both roles can access the existing home route. | Implemented; #16 |
| FR-DASH-02 | Display operational summary cards. | Admin, Staff | Today's Sales, Transactions Today, Low Stock, and Out of Stock reflect the defined populations. | Implemented; #16 |
| FR-DASH-03 | Display up to five Recent Completed Sales. | Admin, Staff | Completed Sales appear newest first by creation time then ID, with receipt links. | Implemented; #16 |
| FR-DASH-04 | Display up to five Low Stock Items. | Admin, Staff | Only active-hierarchy variants at or below threshold appear. | Implemented; #16 |
| FR-DASH-05 | Provide a seven-day Completed Sales Trend to Admin. | Admin | Today plus six previous Manila dates appear oldest first, including zero days. | Implemented; #16 |
| FR-DASH-06 | Withhold the management trend from Staff. | Staff | Staff Dashboard output contains no seven-day trend. | Implemented; #16 |
| FR-REP-01 | Restrict Reports v1 to Admin. | Admin | Admin can open Sales Summary; Staff direct access returns 403. | Implemented; #16 |
| FR-REP-02 | Filter Sales Summary by dates and optional cashier. | Admin | date_from/date_to/cashier apply; default is seven Manila days; invalid inputs fail closed. | Implemented; #16 |
| FR-REP-03 | Summarize completed Sales only. | Admin | Completed Sales Total and Completed Transactions exclude non-completed Sales. | Implemented; #16 |
| FR-REP-04 | Include zero-sale dates in Daily Sales. | Admin | Every selected Manila date has a count and total, including zero. | Implemented; #16 |
| FR-REP-05 | Keep quantities separated by historical unit. | Admin | unit_snapshot groups remain separate and quantities display three decimals after current catalog changes. | Implemented; #16 |
| FR-REP-06 | Exclude cost/profit and sensitive internal data from Reports. | Admin | No purchase cost, profit, checkout token, or authentication fields appear. | Implemented; #16 |
| FR-PROC-REP-01 | **Teacher-requested:** Report Pending Purchase Orders. | Admin | A read-only report shows only `pending` or `partially_received` POs with at least one positive-outstanding line, using ordered quantity minus accepted receiving and outgoing transfer evidence, floored at zero. Child POs are evaluated independently. | Implemented; Admin-only GET report with supplier substring and open-status filters, lineage, and line-level quantities; #28 / expansion item #6 |
| FR-PROC-REP-02 | **Teacher-requested:** Report Unfulfilled Items. | Admin | A read-only report shows each PO line's ordered, accepted, transferred, and open outstanding quantities. One row is included per line only when `MAX(ordered - accepted - transferred, 0.000) > 0.000`; accepted and transferred use authoritative linked receiving and outgoing-transfer evidence. | Implemented; line-centered, Admin-only GET report with snapshot identity, supplier/status/PO filters and lineage; #29 / expansion item #7 |
| FR-PROC-REP-03 | **Teacher-requested:** Report Damaged Items. | Admin | A read-only report derives damaged-item rows from receiving evidence with PO, receipt, Variant snapshot, quantity, note, actor, and time. | Planned; #31 / expansion item #9 |

For receiving and follow-up, a child starts `pending`; a source with positive
outstanding quantity remains `partially_received`; a source whose outstanding
quantity reaches zero through a transfer becomes `closed_with_remainder`.
`completed` remains for fully accepted demand without transfer. Equivalent
follow-up UUID replay remains valid after source status and outstanding quantity
change. Damage evidence does not affect PO status; status remains determined by
accepted and transferred evidence.

Sales History grants both roles access to all Sales; FR-SALES-01 does not add a
completed-only restriction to that existing history surface. Completed-only
filtering is explicitly required for Dashboard/Reports analytics. The UI term
"cashier" identifies the user who recorded a Sale, not a claim about job titles.

For FR-REP-02, explicit filtering requires both dates in strict YYYY-MM-DD format.
Missing pairs, malformed/impossible dates, arrays/non-scalars, reversed ranges,
and ranges over 366 inclusive calendar days fail closed. Blank cashier means
all; otherwise it must be a positive integer. Disabled historical users with
Sales remain selectable by name. Only GET /reports (reports.index) exists;
there is no report store/export/print route.

## 7. Non-functional / quality requirements

| ID | Requirement / observable constraint |
| --- | --- |
| NFR-INT-01 | No valid operation leaves negative stock. |
| NFR-INT-02 | Every legitimate stock mutation has StockMovement evidence. |
| NFR-CON-01 | Concurrent register, inventory, PO receiving, and remainder-transfer workflows preserve one-active-register, authoritative balances/outstanding demand, and exactly-once effects where designed, including equivalent restock/checkout retries. |
| NFR-SEC-01 | Backend authorization governs access to protected operations. |
| NFR-SEC-02 | Role-hidden UI is not a substitute for backend checks; unauthorized direct requests are denied. |
| NFR-SEC-03 | Authentication secrets and internal submission/checkout tokens are not disclosed through reporting or historical presentation. |
| NFR-DATA-01 | Historical transactional evidence remains immutable under supported application workflows. |
| NFR-DEC-01 | Authoritative money/quantity arithmetic avoids binary floating point and preserves declared precision. |
| NFR-TIME-01 | Business/reporting date semantics use Asia/Manila. |
| NFR-RESP-01 | Authenticated navigation and operational pages remain usable at desktop/mobile breakpoints. |
| NFR-A11Y-01 | Navigation supplies appropriate ARIA, inert, keyboard, and focus behavior. |
| NFR-PRINT-01 | Receipt printing hides application navigation and control chrome. |
| NFR-MAINT-01 | Retain Laravel 13, PHP, Blade, Tailwind CSS 4, minimal vanilla JavaScript, and MySQL 8; use focused services without unnecessary architectural complexity. |

These constraints adopt existing project design and recorded verification.
They do not assert an uptime, throughput, response-time target, or formal
accessibility-standard certification. Model history guards are application
guardrails, not proof that unrestricted direct SQL cannot alter records.

## 8. Business rules

| ID | Rule |
| --- | --- |
| BR-01 | Stock never becomes negative. |
| BR-02 | The server determines checkout stock, prices, totals, payment sufficiency, and change. |
| BR-03 | Every legitimate stock mutation records movement evidence with trusted actor and quantities. |
| BR-04 | Completed Sale/SaleItem history is immutable; current catalog changes do not rewrite snapshots. |
| BR-05 | Opening Inventory is Admin-only and once per eligible active Variant; zero is valid and initialization is determined from history. |
| BR-06 | Historical manual Stock In remains valid. New normal receiving requires a PO and initialized inventory in the active Category → Product → Variant hierarchy; accepted actual cost is immutable and accepted receiving updates the latest reference. |
| BR-07 | Stock Correction is Admin-only, requires an initialized active variant, physical target and reason, rejects stale/no-op forms, and changes quantity without changing cost. |
| BR-08 | No unit conversion exists; each Variant is an independent stock pool. The same physical inventory must not be counted in two pools. |
| BR-09 | Low Stock is current_stock <= low_stock_threshold within the active hierarchy; zero stock is also low stock. Out of Stock is current_stock = 0.000. |
| BR-10 | V1 supports cash-only sales. |
| BR-11 | Current v1 has no discounts, credit/utang, returns/refunds, or partial void. |
| BR-12 | Dashboard/Reports sales analytics include status = completed only. |
| BR-13 | Manila calendar boundaries govern reporting: include start of day and exclude start of the next day after the selected end date. |
| BR-14 | No FIFO, weighted-average, formal COGS or profit calculation exists. Latest/reference and historical restock costs do not establish cost of goods sold. |
| BR-15 | Opening cash is not Sales revenue and is excluded from Dashboard and Sales Summary totals. |
| BR-16 | At most one cash-register session may be active globally. Opening cash may be zero; minimal closure performs no reconciliation. |
| BR-17 | PO-based receiving requires a Purchase Order; legacy/manual Stock In remains valid with unlinked Restocks. |
| BR-18 | Accepted quantity alone creates RESTOCK movement evidence, increases sellable stock, and updates latest accepted cost. Damaged quantity recorded during PO receiving creates no StockMovement and neither increases nor decreases sellable stock or cost. Damage-only receiving is supported. |
| BR-19 | Current outstanding equals MAX(ordered quantity minus accepted quantity minus outgoing transferred quantity, 0.000), using exact DECIMAL(14,3) quantities. Damaged quantity is intentionally absent: it does not count as accepted/transferred, and there is no accepted-plus-damaged or cumulative-damage cap against demand. |
| BR-20 | A selected source PO line transfers its complete current remainder at most once, and transferred demand may be open on only one PO at a time. |
| BR-21 | Admin may edit expected cost while a PO is pending and has no accepted receiving, damaged receiving, or outgoing transfer activity. Actual RestockItem cost is separate immutable evidence and never rewrites expected cost; follow-up expected planning cost is separate and may be changed during child creation. |

Detailed schema and concurrency mechanisms remain in the existing #6/#9 design
evidence. Bound date predicates and static SQL aggregation preserve reporting
boundaries; requirement acceptance concerns their resulting values and access.

## 9. Scope status

### Current implemented baseline

- Authentication/roles, Categories, Products, and Variants.
- Opening Inventory, Stock In, and Stock Correction.
- Cash POS, receipts/reprinting, and Sales History.
- PO-based partial/full receiving for Admin and Staff, with accepted/outstanding
  quantities, actual-cost evidence, linked receipt history, inventory movement
  posting, and idempotent replay.
- Damage recording during PO receiving for Admin and Staff, including
  damage-only and mixed receipts, immutable snapshot/note evidence, and
  idempotent replay. Damage does not change stock/cost or reduce outstanding.
- Admin follow-up POs for selected lines at full current outstanding quantity,
  with source/child lineage, immutable transfer evidence, idempotent replay, and
  no inventory effect.
- Responsive navigation, shared Dashboard, and Admin Sales Summary Reports.

### Planned current-project future work

- #31 Damaged Items Report remains planned; #27 follow-up POs, the #28 Pending
  Purchase Orders report, the #29 Unfulfilled Items report, and #30 damaged
  receiving are implemented.
- SALE_VOID / Admin full-sale void, requiring separate implementation and approval.
- User Management UI.
- Final integration and remaining testing, documentation, and presentation work.

Schema support for void status/fields/movement types is preparation only; no
void transition or stock-restoration workflow is implemented. Existing roles
and disabled-account enforcement do not constitute a User Management UI.

### Currently outside the recorded teacher-requested expansion unless later requested

- Supplier CRUD/master data and customer accounts.
- Credit/utang, returns/refunds, discounts, and partial void.
- Unit conversion and accounting integration.
- FIFO / weighted-average COGS and profit reporting.
- Supplier payments, accounts payable, invoices, purchase returns, purchase
  discounts, tax/VAT accounting, approval workflows, multi-warehouse,
  multi-register, closing-cash reconciliation, shortage/overage, employee
  shifts, cash expenses, and damaged-item photo uploads.
- CSV/PDF procurement/report exports and advanced additional reports, including cashier
  ranking, top-selling variants, and a general stock-movement report.

These are scope exclusions, not promises that every item will be delivered in
Phase 2. Existing receipt browser printing is included; report export is not.
Public registration, password reset, remember-me and social login also remain
outside the approved authentication scope; Admin bootstrap is CLI-only.

## 10. Acceptance / traceability

The functional table provides acceptance outcomes; this table links those areas
to existing evidence. All implementation statuses refer to section 12's
application checkpoint. Detailed procedures and test-case preparation remain #7.

| Requirement area | Formal tracker | Implementation evidence | Verification evidence |
| --- | --- | --- | --- |
| Authentication (FR-AUTH) | #10 | Session authentication, active middleware, Admin gate | Auth/authorization suites and completed checkpoint |
| Catalog/variant data (FR-CAT, FR-INV) | #11 | Catalog controllers, requests, models and lifecycle rules | Catalog/variant behavior suites and schema evidence |
| Stock workflows (FR-OPEN, FR-STOCKIN, FR-CORR; FR-INV integrity) | #11/#14 | Focused transactional inventory services and movement records | Inventory suites, recorded guarded MySQL concurrency proofs, browser smoke |
| Checkout (FR-POS) | #12 | POS and RecordSale workflow | Checkout/authorization suites, recorded MySQL race/retry proofs, browser smoke |
| Receipts/history (FR-SALES) | #13 | SalesHistoryController and immutable receipt presentation | History/receipt suites, filters and Print Preview smoke |
| Navigation (FR-NAV) | Separate UI mini-checkpoint; #16 menu additions | Shared Blade navigation and drawer behavior | Navigation suite, keyboard/breakpoint/mobile/print evidence |
| Dashboard/Reports (FR-DASH, FR-REP) | #16 | DashboardController, ReportsController and Blade views | Dashboard/Reports suites, desktop/mobile, filter and receipt-link smoke |
| Register sessions (FR-REG; expanded FR-POS) | #24 | Planned CashRegisterSession and RecordSale extension | Not implemented; revised test catalog and execution pending |
| Purchase Orders/low stock (FR-PO) | #25 | Planned PO header/item workflow and initialized low-stock recommendation | Not implemented; revised test catalog and execution pending |
| PO receiving (FR-RECV; expanded FR-STOCKIN) | #26 | Transactional PO-linked receiving, partial/full quantities, linked evidence, and inventory posting | Implemented; 6 guarded MySQL concurrency tests / 220 assertions passed |
| Follow-up POs (FR-FOLLOWUP) | #27A–#27D | Transactional full-current-remainder transfer evidence, child lineage, idempotent creation, and Admin workflow | Implemented; 6 guarded MySQL concurrency tests / 299 assertions passed |
| Damage receiving (FR-RECV-03) | #30 / expansion item #8 | Immutable `RestockDamageItem` evidence linked to Restock, PO line, and Variant; accepted/damage receipt semantics in the existing receiving flow | Implemented; manual guarded MySQL verification recorded below |
| Pending Purchase Orders report (FR-PROC-REP-01) | #28 / expansion item #6 | Admin-only read-only report query/view over PO and receiving/transfer evidence | Implemented; current engineering verification recorded in project documentation |
| Unfulfilled Items report (FR-PROC-REP-02) | #29 / expansion item #7 | Admin-only line-centered read-only report over PO and receiving/transfer evidence | Implemented; current engineering verification recorded in project documentation |
| Damaged Items report (FR-PROC-REP-03) | #31 / expansion item #9 | Future Admin-only read-only report consuming #30 damage evidence | Not Started; not implemented |

Refer to [PROJECT_STATUS.md](../PROJECT_STATUS.md) for completed checkpoints and
recorded results, and [database-design.md](database-design.md) for workflow rules.
Current #26 closeout verification: **6 guarded MySQL concurrency tests / 220
assertions** and ordinary regression **370 tests / 3,702 assertions**. These are
recorded current engineering results, not tests rerun while preparing this
document. User-run current #27 engineering verification separately passed **3
guarded MySQL identity tests / 18 assertions**, **6 guarded MySQL concurrency tests /
299 assertions**, and the ordinary SQLite regression at **399 tests / 3,975
assertions**. These current implementation checks are not historical teacher
test results. The historical ordinary baseline remains recorded in the project
documentation.

**Current engineering verification for #30 (user-run):** guarded MySQL identity
passed **3 tests / 18 assertions**; readiness passed **1 / 7**; the accepted
receipt-versus-damage race passed **1 / 58**; equivalent damage-only replay
passed **1 / 45**; damage-versus-follow-up passed **1 / 42**; the complete
guarded #30D suite passed **4 / 152**; and ordinary SQLite regression passed
**439 tests / 4,509 assertions**. The replay calls share an actor/User lock and
may serialize before unique-token-index contention; this verifies safe
equivalent replay and does not claim unique-index collision recovery. These are
current engineering results, separate from FT15 and paused FT17 historical
records. The #30 guarded migration precheck observed an exact 18-entry ledger;
after the authorized damage migration, the exact 19-entry ledger and damage
schema postcheck passed.
Read-only Dashboard/Reports require no new concurrency gate. Tests prove software
behavior, not original client interviews.

Tracker #3 defines why, what, for whom, business rules, quality constraints,
scope, and acceptance outcomes. It does not complete #4 actual product-data
preparation, #5 UI/UX planning evidence, or #7 detailed test-case preparation.

## 11. Open assumptions / validation status

The exact pre-system/manual tools, quantified frequency of operational errors,
employee/job-title mapping to Admin/Staff, transaction volumes, and formal
client sign-off remain unverified client-specific facts.

These facts are intentionally not required for this team-approved Tracker #3
baseline and must not be invented in future documentation. Actual client
evidence may supplement this baseline later without changing historical project
facts. No assumption here establishes a measured improvement or client approval.

## 12. Review record

| Field | Record |
| --- | --- |
| Baseline type | Team-approved / project-derived |
| Tracker | #3 Client Problem & Requirements |
| Repository implementation reference | Latest application checkpoint: 31b5b95f8d4de3136c15924bc541b52a6d2fa846 — Add dashboard and sales reports |
| Documentation preparation date | 2026-09-08 |
| Phase A expansion amendment date | 2026-09-17 |
| Phase A expansion status | Requirements/schema design approved on 2026-09-17; implementation was not yet complete at that amendment date |
| Client validation/sign-off | Not recorded / not required for this project-derived baseline |

This record identifies the approved baseline type and preparation context; it
does not represent a client interview, signature, or approval date.
