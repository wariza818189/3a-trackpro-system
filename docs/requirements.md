# 3A TrackPro — Client Problem and Requirements Baseline

## 1. Document status

This is the team-approved requirements baseline for Tracker #3, reconciling
approved project design decisions with implemented and verified system behavior.
Validation basis: project/team baseline, reconciled against implemented and
verified system behavior.

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
| A-05 | V1 does not depend on customer accounts, supplier management, credit, returns/refunds, or accounting integration. |
| A-06 | No claim is made about the client's exact previous manual tools or quantified operational losses. |
| A-07 | Requirements are team-approved/project-derived unless separately identified as direct documentary client evidence; none is identified here. |

## 5. Users and responsibilities

Admin may perform normal Staff workflows plus catalog mutation, Opening
Inventory, Stock Correction, Reports, and the Admin-only Dashboard trend.
Future sensitive Admin workflows require separate implementation before they
become available. Admin does not imply store owner.

Staff may use POS, browse permitted catalog/inventory, perform Stock In, access
Sales History/receipts, and use the operational Dashboard. Staff does not imply
a particular employee or job title. All application access requires an active
authenticated account; public visitors have no operational access.

Staff may enter the purchase cost for a new Stock In receipt, but existing
catalog and historical Stock In costs are not disclosed to Staff. POS,
Dashboard, Reports, and Sales receipts/history do not expose purchase costs.

## 6. Functional requirements

All rows below are adopted requirements implemented at the referenced
application checkpoint. Acceptance outcomes are observable software behavior;
section 10 maps the tracker references to implementation and verification.
Admin/Staff entries mean active authenticated users unless otherwise stated.

| ID | Requirement | Role | Acceptance outcome | Implementation status / traceability |
| --- | --- | --- | --- | --- |
| FR-AUTH-01 | Authenticate using username/password. | Admin, Staff | Valid active credentials grant access; invalid credentials do not. | Implemented; #10 |
| FR-AUTH-02 | Deny continued use by disabled accounts. | Admin, Staff | A disabled account is denied protected requests, including after login. | Implemented; #10 |
| FR-AUTH-03 | Provide no public self-registration. | Public | No public registration route/form is available. | Implemented boundary; #10 |
| FR-AUTH-04 | Protect logout with POST and CSRF. | Admin, Staff | POST signs out; GET cannot log out; browser mutation retains CSRF protection. | Implemented; #10 |
| FR-CAT-01 | Allow Admin to manage Categories. | Admin | Valid create/edit/lifecycle actions succeed; invalid changes are rejected. | Implemented; #11 |
| FR-CAT-02 | Allow Admin to manage Products under Categories. | Admin | Products retain valid parent relationships and lifecycle restrictions. | Implemented; #11 |
| FR-CAT-03 | Allow Admin to manage Product Variants. | Admin | Valid variant attributes/prices can be maintained subject to history restrictions. | Implemented; #11 |
| FR-CAT-04 | Preserve catalog history through archive/reactivate lifecycle. | Admin | No catalog hard-delete route exists; positive-stock variants cannot be archived. | Implemented; #11 |
| FR-CAT-05 | Provide safe catalog browsing to Staff. | Staff | Only permitted active hierarchy is shown; costs and mutation access are denied. | Implemented; #11 |
| FR-INV-01 | Keep an authoritative stock pool per Variant. | Admin, Staff | Each operation affects its specified variant balance; ordinary catalog forms cannot set stock. | Implemented; #11/#14/#12 |
| FR-INV-02 | Support piece, sheet, roll, m, and kg units. | Admin | Supported units are accepted and retained; unsupported units are rejected. | Implemented; #11 |
| FR-INV-03 | Support whole and fractional quantity modes. | Admin, Staff | Whole-mode fractions and excess precision are rejected; eligible fractional quantities are accepted up to three decimals. | Implemented; #11/#14/#12 |
| FR-INV-04 | Prevent negative current stock. | Admin, Staff | Invalid operations leave no negative balance. | Implemented; #14/#12 |
| FR-OPEN-01 | Record opening physical stock once per eligible Variant. | Admin | A first eligible opening succeeds; a later opening is rejected based on history. | Implemented; #14 |
| FR-OPEN-02 | Accept zero opening quantity. | Admin | Zero records initialization even though the balance remains zero. | Implemented; #14 |
| FR-OPEN-03 | Record opening movement evidence. | Admin | A successful opening creates INITIAL_STOCK with trusted actor and quantities. | Implemented; #14 |
| FR-STOCKIN-01 | Allow stock receiving by Admin and Staff. | Admin, Staff | Both roles can submit positive quantities for active initialized variants. | Implemented; #14 |
| FR-STOCKIN-02 | Update stock atomically during receiving. | Admin, Staff | All receipt items and balances commit together or none do; equivalent retries do not duplicate receiving. | Implemented; #14 |
| FR-STOCKIN-03 | Preserve historical received cost. | Admin, Staff | Later receipts do not change the earlier receipt's stored unit cost. | Implemented; #14 |
| FR-STOCKIN-04 | Maintain the latest received-cost reference. | Admin, Staff | Successful receiving updates the variant reference to the received cost. | Implemented; #14 |
| FR-STOCKIN-05 | Record receiving movement evidence. | Admin, Staff | Each RestockItem has one corresponding RESTOCK movement. | Implemented; #14 |
| FR-CORR-01 | Restrict stock correction to Admin. | Admin | Staff direct requests are forbidden. | Implemented; #14 |
| FR-CORR-02 | Require a physical target and reason for correction. | Admin | An eligible nonnegative target and nonblank reason are required. | Implemented; #14 |
| FR-CORR-03 | Reject stale and no-op corrections. | Admin | Intervening movement invalidates the form; unchanged targets cause no write. | Implemented; #14 |
| FR-CORR-04 | Correct quantity with movement evidence only. | Admin | Stock changes with one CORRECTION movement; cost stays unchanged. | Implemented; #14 |
| FR-POS-01 | Provide cash-only POS access. | Admin, Staff | Both roles can perform eligible cash checkout; other payment workflows are absent. | Implemented; #12 |
| FR-POS-02 | Determine checkout price and stock on the server. | Admin, Staff | Submitted values cannot override stock or price; stale expected prices are rejected. | Implemented; #12 |
| FR-POS-03 | Complete checkout atomically. | Admin, Staff | Sale, items, deductions, and movements commit together or roll back. | Implemented; #12 |
| FR-POS-04 | Reject checkout exceeding available stock. | Admin, Staff | Insufficient stock produces no Sale or deduction, including concurrent checkout. | Implemented; #12 |
| FR-POS-05 | Prevent duplicate effects on equivalent retry. | Admin, Staff | Equivalent checkout-token replay returns the existing Sale without another deduction. | Implemented; #12 |
| FR-POS-06 | Preserve successful checkout evidence. | Admin, Staff | One immutable Sale and one SaleItem plus SALE movement per distinct variant are recorded. | Implemented; #12 |
| FR-POS-07 | Require sufficient cash and exact change. | Admin, Staff | Underpayment is rejected; change equals cash less authoritative total. | Implemented; #12 |
| FR-SALES-01 | Allow browsing of completed Sale history. | Admin, Staff | Both roles can find completed Sales regardless of recording user. | Implemented; #13 |
| FR-SALES-02 | Filter Sales History by receipt, user recording the sale, and Manila date. | Admin, Staff | Receipt, cashier, and date filters narrow results; invalid filters fail closed. | Implemented; #13 |
| FR-SALES-03 | Display immutable historical receipt evidence. | Admin, Staff | Later catalog changes do not alter stored receipt identity, units, quantities, or prices. | Implemented; #13 |
| FR-SALES-04 | Support browser receipt reprinting. | Admin, Staff | The same receipt can be printed again without a new Sale or stock change. | Implemented; #13 |
| FR-SALES-05 | Keep purchase costs and checkout tokens private. | Admin, Staff | Neither appears in Sales History or receipt responses. | Implemented; #13 |
| FR-NAV-01 | Provide desktop sidebar navigation. | Admin, Staff | At lg and above, destinations are reachable beside unobstructed content. | Implemented; UI mini-checkpoint |
| FR-NAV-02 | Provide mobile navigation below lg. | Admin, Staff | Below 64rem / 1024px, a top bar and off-canvas drawer replace the sidebar. | Implemented; UI mini-checkpoint |
| FR-NAV-03 | Match navigation visibility to role permissions. | Admin, Staff | Admin has 10 destinations and Staff seven; restricted destinations are absent for Staff. | Implemented; UI mini-checkpoint/#16 |
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
| NFR-CON-01 | Concurrent stock-changing workflows preserve authoritative balances and exactly-once effects where designed, including opening and equivalent restock/checkout retries. |
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
| BR-06 | Stock In requires initialized inventory in the active Category → Product → Variant hierarchy and positive quantities; historical received cost is immutable and received cost updates the latest reference. |
| BR-07 | Stock Correction is Admin-only, requires an initialized active variant, physical target and reason, rejects stale/no-op forms, and changes quantity without changing cost. |
| BR-08 | No unit conversion exists; each Variant is an independent stock pool. The same physical inventory must not be counted in two pools. |
| BR-09 | Low Stock is current_stock <= low_stock_threshold within the active hierarchy; zero stock is also low stock. Out of Stock is current_stock = 0.000. |
| BR-10 | V1 supports cash-only sales. |
| BR-11 | Current v1 has no discounts, credit/utang, returns/refunds, or partial void. |
| BR-12 | Dashboard/Reports sales analytics include status = completed only. |
| BR-13 | Manila calendar boundaries govern reporting: include start of day and exclude start of the next day after the selected end date. |
| BR-14 | No FIFO, weighted-average, formal COGS or profit calculation exists. Latest/reference and historical restock costs do not establish cost of goods sold. |

Detailed schema and concurrency mechanisms remain in the existing #6/#9 design
evidence. Bound date predicates and static SQL aggregation preserve reporting
boundaries; requirement acceptance concerns their resulting values and access.

## 9. Scope status

### Current implemented baseline

- Authentication/roles, Categories, Products, and Variants.
- Opening Inventory, Stock In, and Stock Correction.
- Cash POS, receipts/reprinting, and Sales History.
- Responsive navigation, shared Dashboard, and Admin Sales Summary Reports.

### Planned current-project future work

- SALE_VOID / Admin full-sale void, requiring separate implementation and approval.
- User Management UI.
- Final integration and remaining testing, documentation, and presentation work.

Schema support for void status/fields/movement types is preparation only; no
void transition or stock-restoration workflow is implemented. Existing roles
and disabled-account enforcement do not constitute a User Management UI.

### Deferred / excluded from current v1

- Supplier management and purchase orders; customer accounts.
- Credit/utang, returns/refunds, discounts, and partial void.
- Unit conversion and accounting integration.
- FIFO / weighted-average COGS and profit reporting.
- CSV/PDF report exports and advanced additional reports, including cashier
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

Refer to [PROJECT_STATUS.md](../PROJECT_STATUS.md) for completed checkpoints and
recorded results, and [database-design.md](database-design.md) for workflow rules.
Current verified ordinary baseline: **191 tests / 2,023 assertions**, 27.576s,
isolated SQLite :memory:. Application route baseline: **40**. These are existing
results, not tests rerun while preparing this document. SQLite behavior tests
do not prove MySQL concurrency; the referenced earlier guarded proofs cover
their specific workflows. Read-only Dashboard/Reports require no new concurrency
gate. Tests prove software behavior, not original client interviews.

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
| Client validation/sign-off | Not recorded / not required for this project-derived baseline |

This record identifies the approved baseline type and preparation context; it
does not represent a client interview, signature, or approval date.
