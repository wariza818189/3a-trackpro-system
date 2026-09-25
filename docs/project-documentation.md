# 3A TrackPro Project Documentation

## 1. Project Overview

### 1.1 Project Title

**3A TrackPro: Hardware Store Sales and Inventory Management System**

### 1.2 Team

3A TrackPro is developed by **The Visionaries**.

Team members:

1. Wariza
2. Layupan
3. Casipong
4. Largo
5. Amores

### 1.3 Problem

A hardware store needs consistent records for products, product variations, stock quantities, inventory transactions, and sales. When these records are separated or handled without clear controls, it becomes difficult to determine the available stock, trace why a quantity changed, review past sales, and identify items that need replenishment.

3A TrackPro addresses this need through one controlled system for catalog records, inventory initialization, stock additions and corrections, cash sales, receipts, sales history, operational summaries, and purchasing support.

### 1.4 Target Users

The system has two verified user roles:

- **Admin** — manages the catalog and controlled inventory activities, views management reports, and performs administrative procurement work.
- **Staff** — performs permitted day-to-day operations such as Point of Sale and Stock In and can view the transaction history allowed to both roles.

These are system-access roles rather than confirmed employee job titles.

### 1.5 Purpose

The purpose of 3A TrackPro is to organize a hardware store's sales and inventory processes in a centralized, authorized, and traceable system. It is intended to improve record consistency, protect inventory integrity, preserve useful transaction history, and provide information that can support routine operational decisions. These statements describe the project's intended contribution; no measured business impact or formal client validation is claimed.

## 2. System Scope

### 2.1 Included and Implemented Scope

The current system includes the following implemented areas:

- username-and-password login, logout, active-account checks, and server-side Admin/Staff authorization;
- a shared Dashboard with operational summaries, recent completed sales, and low-stock information, plus an Admin-only seven-day sales trend;
- category, product, and product-variant management with archive and reactivation controls; searchable Products link to a dedicated read-only detail page with role-permitted Variants and per-Variant stock and status, without a Product-level stock total;
- Admin-only Opening Inventory, recorded as an explicit initial stock transaction;
- Stock In for Admin and Staff, including multi-item receipts and historical received costs;
- Admin-only Stock Correction with a required reason and an inventory movement record;
- a cash-only Point of Sale with server-calculated prices, totals, payment sufficiency, change, and stock deductions;
- one global cash-register session that can be opened and closed within the approved single-register scope;
- Sales History and a printable receipt/reprint page based on historical sale data;
- an Admin-only Sales Summary with date and cashier filters;
- an Admin-only, read-only Inventory Report with one row per ProductVariant across active and archived catalog records, explicit Category/Product/Variant statuses, current stock, threshold, quantity mode, and derived stock state;
- an Admin-only, read-only Low Stock Report with one row per active-hierarchy ProductVariant at or below its configured stock threshold;
- responsive navigation and layouts for the main authenticated screens;
- low-stock procurement recommendations that separate uncovered demand from items already covered by an open Purchase Order;
- Admin-only Purchase Order creation with supplier text, ordered quantities, expected unit costs, and duplicate-submission protection;
- Purchase Order listing, filtering, historical detail viewing, and Admin-only editing of pending orders before receiving activity begins. The edit workflow supports supplier and notes changes, line quantity and expected-cost changes, line removal, eligible new lines, retained historical lines, and protection against saving an outdated draft; and
- PO-based partial and full receiving for Admin and Staff, with accepted/outstanding quantities, actual receipt-cost evidence, linked receipt history, inventory and StockMovement posting, completed/partially_received status progression, Admin cost visibility, Staff cost redaction, and safe duplicate submission replay; and
- Admin follow-up Purchase Order creation from selected source lines, transferring each selected line's full current outstanding quantity to one traceable child PO with idempotent replay and no inventory mutation; and
- damaged-item recording during PO receiving for Admin and Staff, as immutable receipt evidence separate from accepted inventory; and
- the Admin-only #31 Damaged Items Report, a GET-only read-only Reports page presenting immutable receiving evidence.

### 2.2 In-Progress and Planned Scope

The following work is incomplete or planned and must not be treated as available functionality:

- dedicated Product Sales and Restocking report areas that go beyond the current operational screens;
- Sale Void and its stock-restoration workflow;
- User Management screens for creating, editing, disabling, viewing, and searching users;
- audit logging across workflows and an audit-trail viewer/filter interface;
- a unified inventory movement history screen;
- remaining edge-case, permission, integration, and final system testing; and
- final screenshots, documentation review, and presentation preparation.

### 2.3 Explicit Exclusions

The following items are outside the approved project scope. Their absence is a scope decision rather than a defect:

- supplier master-data, accounting, invoice, and payment management;
- credit sales, discounts, returns/refunds, and tax processing;
- automatic unit conversion, automatic reorder calculation, and advanced costing or inventory valuation;
- procurement approval workflows;
- multiwarehouse and multiregister operation;
- extended cash reconciliation, shift, shortage/overage, and expense management; and
- advanced procurement export and damaged-item media features.

### 2.4 Main Screens

The current user-facing screens are:

- Login;
- Dashboard;
- Point of Sale, including open/close register controls;
- Sales History;
- Receipt and reprint view;
- Categories list, create, and edit screens;
- Products list, create, and edit screens;
- Product Variants list, create, and edit screens;
- Opening Inventory list and entry form;
- Stock In history, new receipt, and receipt-detail screens;
- Stock Corrections list and correction form;
- Sales Summary Reports;
- Purchase Orders list;
- Purchase Order creation;
- Purchase Order detail;
- Purchase Order edit for pending orders; and
- Purchase Order receive form and linked receipt history; and
- follow-up Purchase Order creation and source/child lineage in PO browsing and detail.

### 2.5 Core Features

The core features are controlled access, catalog organization, exact inventory tracking, traceable stock changes, cash sales, preserved transaction history, operational summaries, and early procurement support. Important values such as stock, sale totals, and the responsible user are determined by the system rather than accepted directly from the browser.

### 2.6 Main Business Process

The current operational process can be summarized as:

**Catalog setup → Opening Inventory → Stock In and controlled corrections → Open cash register → Point of Sale → Receipt and Sales History → Dashboard and Sales Summary → Low-stock review → Purchase Order creation, editing, receiving, follow-up ordering, and lineage monitoring**

Categories, Products, and Product Variants establish the catalog. Opening Inventory records the first quantity for each Variant. Later stock additions use Stock In, while authorized physical-count corrections create separate evidence. After a register is opened, a successful cash sale records the sale, stock deductions, and inventory movements. Users can review sales and receipts, while Admin users can review summaries, low-stock information, and Purchase Orders.

Purchase Orders can now be received partially or in full. Accepted quantity updates inventory and creates linked receipt and movement evidence; outstanding quantity remains available for later deliveries. Admins can transfer selected lines' full current outstanding quantities into traceable child POs. A follow-up transfer records procurement demand without changing stock or creating a StockMovement. The #28 Pending Purchase Orders Report monitors open POs with demand; the #29 Unfulfilled Items Report presents the specific outstanding PO lines, quantities, and supplier/order provenance.

Admin and Staff can record goods found damaged during PO receiving. Damage creates immutable `RestockDamageItem` evidence under the existing Restock and PO-line context, with historical catalog snapshots, damaged quantity, required note, and actor/time context through the Restock. Damage does not increase or decrease sellable stock, change cost, create a StockMovement, count as accepted or transferred, or reduce outstanding demand. Damage-only receipts are supported and leave PO status governed by accepted and transferred evidence; mixed receipts change inventory and cost only for their accepted quantity. Equivalent submission-token replay returns the original Restock after comparing damage semantics.

The separate Admin-only #31 Damaged Items Report presents this evidence as one row per `RestockDamageItem`, including linked PO and supplier, existing `RST-` receipt identifier, historical item snapshots, damaged quantity and unit, note, receiving actor, and receipt time. Historical item identity comes from the damage row's product, size, type/series, thickness, and unit snapshot fields; current catalog names do not rewrite it. Supplier substring, historical item snapshot text, and exact PO ID are the only filters. Rows sort by receipt time descending, receipt ID descending, then damage row ID descending. The report is GET-only and read-only, has distinct no-evidence and no-filter-match states, and does not aggregate by Variant or show a global quantity total across unlike units. It shows a child PO as that PO without parent/child lineage. Operational damage history remains available in PO receiving to Admin and Staff; the dedicated Reports module remains Admin-only.

### 2.7 Main Data and CRUD Entities

| Entity | Purpose | Current User-Facing Support |
| --- | --- | --- |
| Users | Authentication, role, status, and transaction responsibility | **Some operations available** — login/logout and role/status enforcement exist; no User Management screen exists. |
| Categories | Top level of the product catalog | **Create, view/search, edit, archive, and reactivate**. |
| Products | Product identities within Categories | **Create, view/search, edit, archive, and reactivate**. |
| Product Variants | Sellable or stockable units with prices and the primary stock record | **Create, view/search, edit, archive, and reactivate**; stock changes use separate inventory workflows. |
| Cash Register Sessions | Opening and closing cash context for sales | **Some operations available** — open and close controls exist; there is no separate session-history screen. |
| Sales | Cash checkout and saved sale/payment details | **Create and view** — history and receipt/reprint are available; editing, deletion, and Sale Void are not. |
| Sale Items | Saved product, Variant, quantity, and price details for each sale | **Created automatically and view-only**. |
| Restocks | Stock In receipt headers | **Create, list, and view** for legacy manual Stock In and PO-based receiving; PO receipts link to their Purchase Order. Historical receipts cannot be edited or deleted. |
| Restock Items | Accepted quantities and historical unit costs | **Created automatically and view-only**; accepted PO receipt lines link to their Purchase Order Items. Damage-only receipt lines have no RestockItem. |
| Restock Damage Items | Immutable evidence of damaged goods recorded during PO receiving | **Created automatically and view-only**; stores the PO-line and Variant links, historical product/size/type/thickness/unit snapshots, damaged quantity, note, and creation time. Restock supplies actor, receipt, and PO context. No edit, delete, or reversal workflow exists. |
| Stock Movements | Evidence of initial stock, restocking, corrections, and sales deductions | **Created automatically with related transactions**; no unified movement-history screen exists. |
| Purchase Orders | Supplier details, status, creator, and procurement history | **Create, list/filter, view details, edit pending orders, receive partial or full deliveries including damaged quantities, and create follow-up POs**; source-to-child lineage is visible. Operational damage history appears in PO details; the dedicated #31 report is linked from Reports and remains Admin-only. |
| Purchase Order Items | Ordered quantities, expected costs, and saved product details | **Created and editable through pending Purchase Orders**; accepted/outstanding quantities, linked actual receipt costs, and transferred quantities are represented by history evidence. Saved historical details remain available when catalog records later become inactive. |
| Purchase Order Item Transfers | Immutable source-to-child procurement-demand evidence | **Created automatically and view-only**; records the full quantity transferred, source/target item relationship, actor, and creation time. Transfers do not affect inventory. |
| Audit Logs | Intended record of sensitive system activity | **Not yet implemented for normal workflows** — database/model preparation exists, but logging and a viewer are unavailable. |

## 3. Technology & Architecture

### 3.1 Frontend Technologies

| Technology | Version Used | Use in the project |
| --- | --- | --- |
| Blade | Included with Laravel 13.30.1 | Server-rendered authenticated pages and reusable view components. |
| Tailwind CSS | 4.3.3 | Responsive layouts, forms, cards, tables, status indicators, and navigation. |
| Vanilla JavaScript | No separate framework version | Focused interaction for forms, the POS cart, and other progressive interface behavior. |
| Vite | Version 8 | Builds and bundles frontend CSS and JavaScript assets. |

### 3.2 Backend Technologies

| Technology | Version Used | Use in the project |
| --- | --- | --- |
| PHP | Composer requirement `^8.3`; recorded development runtime 8.4.25 | Main server-side programming language, including exact decimal calculations for stock and money. |
| Laravel | 13.30.1 locked; project requirement `^13.17` | Routing, session authentication, middleware, validation, authorization, controllers, database access, and server-rendered application structure. |

### 3.3 Database Technologies

| Technology | Version Used | Use in the project |
| --- | --- | --- |
| MySQL / InnoDB | MySQL 8.0.46 | Persistent relational application storage, foreign keys, uniqueness rules, transactions, locking, and database constraints. |
| SQLite | In-memory test database; exact engine version not recorded | Isolated execution of the normal automated application test suite. |

### 3.4 System Architecture

3A TrackPro uses a **traditional server-rendered Laravel MVC architecture with focused service and query layers**. It is not a single-page application.

A typical request follows this simplified flow:

**Browser request → Laravel route and access checks → validation and controller → business service or model → database → Blade page response**

Controllers coordinate requests and responses. Laravel validation classes check important input, while focused services handle complex inventory, sales, register, and procurement changes. Eloquent models represent stored data and relationships, and query objects organize specialized reports or procurement information where useful. Blade produces the page, with small JavaScript enhancements for interaction.

Not every page needs every layer. Simple pages can read through a controller and model, while sensitive changes use additional business-service protection.

### 3.5 Development and Testing Tools

| Tool | Version or Type | Role |
| --- | --- | --- |
| Composer | Project dependency tool | PHP dependency and script management. |
| Node.js and npm | Frontend development tooling | Frontend dependency and build-script management. |
| PHPUnit | 12.5.34 | Automated feature and unit testing through Laravel's test runner. |
| Laravel Pint | Code-formatting tool | Consistent PHP code formatting. |
| Git and GitHub | Versioned repository | Change history, collaboration, and shared project storage. |

### 3.6 Why These Technologies Are Used

- **Laravel** supplies a structured way to implement routes, authentication integration, validation, authorization, database models, and transaction-aware business workflows.
- **Blade** supports secure, server-rendered pages that fit the authenticated hardware-store workflow.
- **Tailwind CSS** supports consistent responsive styling for navigation, forms, cards, and data tables.
- **Vanilla JavaScript** provides focused interactive behavior without requiring a separate frontend application architecture.
- **MySQL with InnoDB** provides persistent relational storage, transactions, row locking, and integrity constraints for operational data.
- **SQLite in memory** provides fast, isolated automated application tests.
- **PHPUnit and Laravel's test runner** verify application behavior and regression safety.
- **Vite** bundles the frontend assets used by Laravel pages.
- **Composer and npm** keep backend and frontend dependencies reproducible.
- **Git and GitHub** preserve project history and support team repository management.

## 4. Development Progress

### 4.1 Timeline and Week-Mapping Note

Development of the system began during the **first week of September 2026**. The week labels below summarize the sequence of project development; official class-week date boundaries were not separately recorded. This milestone chronology records work through September 21, 2026; the later #26 receiving closeout is captured in the current-state sections below.

### 4.2 Week 1–7 Milestones

| Week | Major Milestones | Status/Note |
| --- | --- | --- |
| Week 1 | Project foundation; database and model structure; authentication and role enforcement; Category, Product, and Product Variant management; Opening Inventory; Stock In; initial branding. | Implemented. |
| Week 2 | Stock Correction; cash Point of Sale; receipt and Sales History; responsive navigation; Dashboard and Sales Summary; requirements, product-data, UI/UX, and test-planning documents; early formal functional and edge-testing evidence. | Core application work and the 30-case functional run were completed; edge testing later remained incomplete. |
| Week 3 | Teacher-requested scope expansion; revised requirements and database design; cash-register schema, lifecycle, POS integration, and database-specific verification; project tracker; Purchase Order foundation, low-stock recommendations, creation service, and creation interface. | Register work was implemented and verified; procurement work began and remained in progress. |
| Week 4 | Purchase Order list, filters, detail view, backend update logic, and the pending-order edit interface. | Administrators can browse and edit pending orders before receiving activity begins; the later procurement lifecycle remains in development. |
| Week 5 | Follow-up Purchase Order (#27A–#27D): transfer evidence foundation, transactional service, HTTP/UI, and guarded MySQL concurrency closeout. | Completed; current engineering verification passed. |
| Week 6 | No completed milestone recorded yet at the current documentation date. | Development is continuing. |
| Week 7 | No completed milestone recorded yet at the current documentation date. | Development is continuing. |

### 4.3 Current Progress Summary

The current system has a working core covering access control, catalog and inventory processes, cash sales, register opening and closing, transaction history, and operational reporting. Procurement includes low-stock recommendations, Purchase Order creation/browsing/editing, Admin/Staff partial/full and damaged PO receiving, Admin follow-up POs with source/child lineage and full-current-remainder transfers, and the Admin-only #28, #29, and #31 reports.

The project remains in active development. PO-based receiving, #27 follow-up ordering, and #30 damaged receiving are implemented and concurrency-verified. The #28 Pending Purchase Orders, #29 Unfulfilled Items, and #31 Damaged Items reports are complete. Sale Void, User Management, Audit Trail, other remaining reports, expanded formal testing, and final materials are not yet complete.

## 5. Technical Decisions & Issues

### 5.1 Major Technical Decisions

| Decision | Teacher-Friendly Explanation |
| --- | --- |
| Product Variant is the primary stock record. | Stock belongs to the exact sellable form—such as a size, type, thickness, or unit—instead of being stored only at the general Product level. |
| Inventory and money use exact calculations. | Quantities and currency avoid binary floating-point rounding. Whole and fractional modes enforce the precision allowed for each Variant. |
| Opening Inventory and later stock changes remain distinguishable. | A Variant is initialized once through an `INITIAL_STOCK` record, including when its opening count is zero. Later restocking, corrections, and sales deductions create their own Stock Movement evidence. |
| Sensitive changes protect against conflicting updates. | Related records are changed as one database transaction, and the latest stored data is rechecked before saving where simultaneous activity is possible. |
| Duplicate submissions are handled safely. | Durable submission identifiers prevent repeated checkout, Stock In, and Purchase Order requests from creating duplicate business transactions. |
| Catalog records are archived instead of destructively deleted. | Historical transaction relationships are preserved while inactive records are removed from normal use. |
| Historical transaction details do not change with the catalog. | Receipts and transaction history retain the names, units, prices, quantities, and responsible users that applied when the transaction occurred. |
| POS calculations are performed by the backend. | Stock, prices, totals, cash sufficiency, change, and transaction ownership are verified by the system rather than trusted from the browser. |
| Low-stock recommendations do not invent order quantities. | The system identifies eligible low-stock Variants and existing open coverage, while the Admin remains responsible for the purchase quantity. |

### 5.2 Problems, Solutions, and Remaining Issues

| Problem or Issue | Decision or Solution | Current Status |
| --- | --- | --- |
| The teacher-requested expansion changed the expected final workflows. | Requirements, database design, development priorities, and the testing plan were revised before continuing formal edge testing. | At the planning checkpoint recorded here, #27 follow-up ordering and #28–#29 procurement reports were complete, while damage handling and the Damaged Items Report had not yet been implemented. #30 and #31 were completed later. |
| The edge and permission testing run no longer covered the expanded final scope. | Completed results were preserved, and the run was paused instead of executing an outdated case set against changing architecture. | Paused and awaiting a refreshed test baseline. |
| Simultaneous requests could conflict or rely on older inventory/register data. | The system rechecks the latest stored data and uses database transaction protection before saving. The single-register rule also has database-level protection and isolated MySQL-specific tests. | Applied to register and inventory services; Purchase Order updates still need final simultaneous-update verification. |
| Current catalog values can change after a sale. | Sale and Sale Item snapshots preserve the historical receipt values instead of substituting current catalog data. | Implemented for Sales History and receipt/reprint. |

### 5.3 Instructor and Team Interventions

During the System Check and teacher consultation, the team received the following additional requirements:

- POS should record a starting cash or opening-register amount;
- Purchase Order creation should prioritize low-stock items;
- delivered or received items should be processed from an existing Purchase Order;
- remaining unfulfilled quantities should continue through a follow-up Purchase Order;
- Reports should include Pending Purchase Orders;
- Reports should include Unfulfilled Items; and
- damaged items should be recorded and included in reporting.

The team responded by reviewing the requirements, revising the database design and workflow, changing implementation priorities, and adjusting the testing plan for the expanded register and procurement scope. The added work was divided into manageable stages so existing catalog, inventory, and sales functions could be preserved while the new requirements were introduced. No formal client validation or sign-off is recorded in the current project documentation.

## 6. Testing & System Evaluation

### 6.1 Testing Approach

This section records the testing approach for the **September 17–18, 2026 WST 1 System Testing Approach activity**. It describes what was ready and how the team intended to evaluate it at that historical point; it is not a statement that all later tests or features were already complete.

At that time, the stable core included authentication and roles, Dashboard, catalog management, Opening Inventory, Stock In, Stock Correction, POS checkout, Sales History, receipt/reprint, Sales Summary, and the responsive interface. Opening and closing the cash register were under active implementation and focused verification during the activity window. Procurement and Purchase Order functionality began later and was not ready for testing on September 17–18.

#### 6.1.1 Features Ready for Testing

- login, logout, active-account handling, and Admin/Staff authorization;
- Dashboard summaries and role-sensitive content;
- Categories, Products, and Product Variants;
- Opening Inventory;
- Stock In and historical Stock In details;
- Stock Correction;
- cash POS checkout;
- Sales History and receipt/reprint;
- Sales Summary filtering and calculations; and
- responsive navigation and the main desktop/mobile layouts.

The register-opening and closing workflow was treated as active implementation/focused verification, not as a fully closed feature at the beginning of the activity. Purchase Orders, procurement receiving, follow-up ordering, and damage handling were not part of the ready-for-testing list.

#### 6.1.2 Functional Testing

**Purpose/Objectives:** Confirm that implemented workflows perform their intended tasks for authorized users and produce the expected visible result.

**Areas Covered:** Authentication, Dashboard, catalog maintenance, Opening Inventory, Stock In, Stock Correction, POS, receipt/history, and Sales Summary.

**Types of Checks:** Successful login and logout; permitted catalog creation and maintenance; first inventory entry; later restocking; authorized stock correction; a valid cash sale; correct receipt and history access; and expected Dashboard, filter, and summary behavior. Detailed case steps remain in the separate test-case document.

#### 6.1.3 Input and Validation Testing

**Purpose/Objectives:** Confirm that invalid, incomplete, duplicate, unauthorized, or unsafe input is rejected without corrupting inventory or transaction history.

**Areas Covered:** Authentication forms, catalog forms, quantity and price fields, inventory transactions, POS cash and stock rules, search/filter fields, and role-restricted actions.

**Types of Checks:** Required fields; missing, invalid, or oversized text; duplicate names; zero, negative, excessive, or over-precision quantities; whole-versus-fractional rules; insufficient stock; insufficient cash; invalid filter values; stale form values; duplicate submissions; and guest, disabled-user, Staff, and Admin access boundaries.

#### 6.1.4 Interface and Responsive Testing

**Purpose/Objectives:** Confirm that important tasks remain understandable and usable across the recorded desktop and mobile viewport sizes.

**Areas Covered:** Responsive navigation, Dashboard cards, catalog and inventory forms, tables, POS item selection and cart, reports, validation/confirmation feedback, and receipt print view.

**Types of Checks:** Visible headings and labels; usable navigation; readable cards and tables; horizontal table containment where required; form and feedback visibility; stacked mobile layouts; desktop layout use; POS cart interaction; and receipt readability in Firefox Print Preview. Recorded viewport evidence includes **1366×768**, **414×846**, **1023×720**, and **1024×720**. These sizes do not represent every possible device.

#### 6.1.5 Data and Database Testing

**Purpose/Objectives:** Confirm that stored relationships, inventory balances, transaction history, and failure behavior remain consistent.

**Areas Covered:** Catalog relationships, Product Variant stock, Stock Movements, Stock In, corrections, sales and items, saved historical details, uniqueness rules, and transaction safety.

**Types of Checks:** Valid relationships between stored records; nonnegative stock; correct before/change/after movement values; exactly one appropriate movement per successful stock change; canceling the whole operation when one part fails; saved receipt/history details that do not change; uniqueness rules; safe duplicate-submission handling; and simultaneous-operation checks where applicable.

#### 6.1.6 Testers, Environment, and Input Categories

**Testers:** Testing was performed by all members of The Visionaries: Wariza, Layupan, Casipong, Largo, and Amores. Casipong was the tracker-assigned member for the major testing tasks, while the team participated in functional, validation, interface/responsive, and data/database checking as the system was developed.

**Test environments:**

- normal automated application testing with Laravel/PHPUnit and isolated SQLite `:memory:`;
- isolated MySQL 8.0.46/InnoDB testing for database-specific behavior;
- manual functional review of the local Laravel application in an authenticated browser using controlled synthetic data;
- responsive review using the recorded desktop and mobile viewport sizes; and
- receipt-print review using Firefox Print Preview where supported.

**Test input categories:** Valid normal inputs; missing, invalid, or oversized text; duplicate values; zero, negative, excessive, whole, and fractional quantities; stale and current values; sufficient and insufficient cash; available and insufficient stock; guest, Admin, Staff, and disabled-user access; valid and invalid filters; repeated submissions; and active and archived records.

### 6.2 Test Coverage and Detailed Test Cases

The separate [Detailed Test Cases](./test-cases.md) document contains a **79-case pre-expansion baseline**:

| Category | Cases |
| --- | ---: |
| Functional Testing | 30 |
| Edge Case & Permission Testing | 37 |
| Isolated MySQL Verification | 8 |
| Review Only | 4 |
| **Total** | **79** |

The detailed test-case catalog was first prepared on **September 9, 2026** as an early testing baseline. For the September 17–18 WST 1 activity, the team documented the four-part testing approach and identified the modules then ready for testing. Later register and procurement changes require the detailed catalog to be updated. The 79 cases are supporting planning evidence and were not all executed.

### 6.3 Test Execution and Results

| Date / Run | Scope | Recorded Result | Notes |
| --- | --- | --- | --- |
| September 8, 2026 | Historical automated application test suite using isolated SQLite in memory | 191 tests / 2,023 assertions passing | A historical regression baseline for the core application at that date; not evidence for later register or procurement features. |
| Completed September 11, 2026 — `FT15-20260909-A` | Formal functional testing | 30 Pass / 0 Fail / 0 Blocked | All 30 cases in that functional run were finalized as passing. |
| Began September 12, 2026 — `FT17-20260912-A` | Edge-case and permission testing | 8 Pass / 1 Fail / 0 Blocked / 28 Remaining | **Paused and incomplete.** The completed evidence is preserved, while the changed project scope requires a revised test baseline before remaining work continues. |

The single FT17 failure remains recorded and is provisionally identified as a test-procedure issue; its controlled retest was deferred. It must not be changed into a passing result without completing and documenting the retest.

Focused feature and isolated MySQL-specific tests cover the cash-register implementation, including simultaneous-operation behavior. The committed results document does not provide one consolidated register run count, so none is claimed here.

Current #26 verification includes six guarded MySQL concurrency tests (220 assertions) covering over-receiving, receipt/edit serialization, receipt versus legacy Stock In, overlapping Variant sets, and equivalent-token replay; the ordinary regression run passed 370 tests / 3,702 assertions. These are current engineering results. The September 17–18 approach and historical results above remain unchanged.

**User-run current #27 engineering verification (after #27A–#27D):** the guarded MySQL identity suite passed **3 tests / 18 assertions**; the combined guarded #27D concurrency suite passed **6 tests / 299 assertions**; and the ordinary SQLite regression passed **399 tests / 3,975 assertions**. The concurrency cases covered readiness/schema, follow-up versus receipt, two follow-ups on one line, overlapping selections, follow-up versus PO edit, and equivalent-token replay. No deadlock, lock wait timeout, duplicate transfer, over-transfer/over-receipt, partial selected-set transfer, follow-up inventory mutation, or cleanup failure was observed. These are current engineering verification results, not historical teacher test results and not a replacement for FT15/FT17 records.

**User-run current #30 engineering verification (after #30A–#30D):** guarded MySQL identity passed **3 tests / 18 assertions**; #30D readiness passed **1 test / 7 assertions**; the accepted-versus-damage race passed **1 test / 58 assertions**; equivalent damage-only replay passed **1 test / 45 assertions**; damage-versus-follow-up passed **1 test / 42 assertions**; the complete guarded #30D suite passed **4 tests / 152 assertions**; and the ordinary SQLite regression passed **439 tests / 4,509 assertions**. No deadlock, lock wait timeout, duplicate receipt/damage evidence, damage inventory/cost/movement effect, damage demand reduction, transfer corruption, partial loser write, or cleanup failure was observed. The equivalent replay workers use the same actor/User lock, which may serialize them before unique-token-index contention; this verifies safe equivalent replay but does not prove unique-index collision recovery. These are current engineering verification results, separate from historical teacher/manual testing and FT15/FT17; the historical rows above remain unchanged.

### 6.4 Current System Evaluation and Remaining Issues

**Implemented core with existing test evidence:** Authentication and authorization, catalog management, Opening Inventory, Stock In, Stock Correction, cash POS, register open/close behavior, Sales History, receipt/reprint, Dashboard, Sales Summary, and responsive navigation have implementation and test evidence. Saved transaction details, system-calculated values, and inventory movement recording are built into these workflows.

**Current Product Search state:** The existing searchable Product listing opens a dedicated read-only Product detail page. Admin can inspect active and archived Products and Variants; Staff is limited to an active Product and Category hierarchy and active Variants, with hidden Product URLs returning 404. The page shows Product identity/status and each permitted Variant's selling price, stock, threshold, and derived stock state separately, without cost or a Product-level stock sum. No schema change or new mutation route was needed. Current engineering verification passed Product detail (9 tests / 61 assertions), Catalog authorization (4 / 43), Catalog route security (5 / 135), Product management (7 / 41), Product Variant management (15 / 126), and the full ordinary SQLite suite (455 / 4,688). Rendering 12 Variants used no more than six SELECTs and no database writes. The frontend build, targeted Pint, and `git diff --check` passed. MySQL was not run for this detail-page implementation. These are current engineering checks, separate from historical teacher/manual testing, FT15, and paused FT17 records.

**Current Low Stock Report state:** The Admin-only, read-only Reports page returns one row per active Variant under an active Product and Category when `current_stock <= low_stock_threshold`. Zero-stock Variants remain eligible, and inventory initialization is not required. The report shows each Variant's Category, Product, identity, unit, current stock, threshold, and stock state, with no Product-level or cross-unit quantity total and no procurement/open-PO coverage. It requires no schema or migration change. Current engineering verification passed the focused Low Stock Report suite (5 tests / 64 assertions), Reports authorization (5 / 23), generic Reports (6 / 77), Dashboard (7 / 43), Product Variant management (15 / 126), and the full ordinary SQLite suite (460 / 4,752). The report rendered 12 Variants with at most six SELECTs and no database writes. `npm run build`, targeted Pint, and `git diff --check` passed; MySQL was not run. These are current engineering checks, separate from historical teacher/manual results, FT15, and paused FT17 records.

**Current Inventory Report state:** The Admin-only, read-only Reports page shows one row per ProductVariant across active and archived Categories, Products, and Variants, with each catalog status shown separately. It displays current stock, low-stock threshold, unit, quantity mode, and derived stock state: zero is Out of stock, positive stock at or below threshold is Low stock, and stock above threshold is In stock. Stock state is independent of catalog status. Initialization evidence is not required, and stock is not aggregated across Variants or units. The complete result set has no filters, pagination, summary metrics, or global quantity total; cost, selling price, procurement data, and stock/receipt history are not exposed. No schema or migration change was needed. Current engineering verification passed Inventory Report (5 tests / 69 assertions), Reports authorization (5 / 23), generic Reports (6 / 77), Low Stock Report (5 / 64), Product Variant management (15 / 126), and the full ordinary SQLite suite (465 / 4,821). The report rendered 12 Variants with at most six SELECTs and no database writes. `npm run build`, targeted Pint, and `git diff --check` passed; MySQL was not run. These are current engineering checks, separate from historical teacher/manual results, FT15, and paused FT17 records.

**Implemented but needing additional testing:** The register feature has focused application and MySQL-specific evidence but no consolidated result summary. The expanded formal case catalog and consolidated execution record still need completion. Purchase Order edit-versus-edit behavior also needs its own simultaneous-update verification.

**Current Purchase Order state:**

- Schema/models, low-stock recommendations, creation, list/filter, historical detail, and Admin-only pending-order editing are implemented. The edit workflow can update supplier and notes, change or remove existing lines, add currently eligible initialized Variants, retain historical lines whose catalog hierarchy is now inactive, and reject outdated drafts.
- Admin and Staff can receive partial or full deliveries. The workflow tracks accepted and outstanding quantities, stores actual received cost as immutable evidence without changing expected PO cost, links receipts and lines to their PO records, and posts one inventory increase and RESTOCK movement per accepted line. Status progresses from pending to partially_received or completed, and linked receipt history is available; Staff can see and enter a new actual cost but cannot see expected or prior actual costs.
- Equivalent submission-token replay returns the existing receipt without repeating inventory changes. Guarded MySQL tests verify serialization for concurrent receipts, edits, and legacy Stock In.
- Admins can create a follow-up child PO from selected source lines. Each selected line transfers its full current outstanding quantity; source snapshots are preserved, child supplier is prefilled from source and may be changed, and expected planning cost may be changed. Immutable transfer evidence preserves source-to-child lineage and UUID replay returns the same child after source status/outstanding changes. Existing transfer evidence freezes source/child edits. Transfer creation is procurement-demand evidence only and creates no Restock, RestockItem, stock change, or StockMovement. Inactive historical catalog hierarchy and missing INITIAL_STOCK do not block follow-up creation; normal active-hierarchy receiving rules continue to apply to physical deliveries. Staff can view operational lineage and quantities while protected costs and actions remain redacted.
- A source with positive outstanding demand remains `partially_received`; when outgoing transfer reduces outstanding to zero, it becomes `closed_with_remainder`. A child begins `pending`; `completed` remains for fully accepted demand without transfer.
- The Admin-only Pending Purchase Orders Report is implemented as a read-only monitoring view of current open procurement demand. It includes only `pending` or `partially_received` POs with at least one positive-outstanding line. Outstanding quantity is derived from ordered quantity less accepted receipt quantity and transferred quantity, floored at zero; completed, closed-with-remainder, and zero-outstanding POs are excluded. Follow-up child POs are evaluated independently. Supplier substring and open-status filters are available, and the report shows PO lineage and line-level ordered, accepted, transferred, and outstanding quantities. It does not replace operational PO browsing or expose costs or submission tokens.
- **Current engineering verification for #28:** the focused Pending Purchase Orders Report suite passed 6 tests / 81 assertions, existing Reports tests passed 17 tests / 178 assertions, and the ordinary SQLite suite passed 405 tests / 4,056 assertions. Targeted Pint and `git diff --check` passed. MySQL and `npm run build` were not run for this read-only report. These are current implementation checks, not historical teacher/manual testing, and do not alter FT15/FT17 records.
- The Admin-only Unfulfilled Items Report is implemented as a read-only, PO-line-centered backlog. Each row represents one `purchase_order_item` with positive current outstanding demand. Accepted quantity is the sum of linked RestockItem quantities, transferred quantity is outgoing transfer evidence, and outstanding is `MAX(ordered - accepted - transferred, 0.000)` using exact three-decimal BCMath arithmetic. Only `pending` and `partially_received` orders contribute; completed and closed-with-remainder orders do not. Transferred source demand is subtracted once, and child PO lines are evaluated independently. The report keeps line rows separate across POs and Variants and displays PO-line snapshot identity, unit, supplier/status/time, PO links and lineage, and ordered, accepted, transferred, and outstanding quantities. Filters cover supplier substring, snapshot item text, open status, and exact PO ID; results sort oldest PO time, PO ID, then line ID. Costs, submission tokens, and mutation controls are withheld.
- **Current engineering verification for #29:** the focused Unfulfilled Items suite passed 7 tests / 123 assertions; the #28 regression passed 6 tests / 81 assertions; existing Reports authorization/summary tests passed 11 tests / 100 assertions; and the full ordinary SQLite suite passed 412 tests / 4,179 assertions. Targeted Pint and `git diff --check` passed. MySQL and `npm run build` were not run. The shared `PurchaseOrderLineEvidence` helper provides exact BCMath scale-3 arithmetic for both reports; #28 inclusion rules, filters, ordering, and UI did not change. These are current engineering checks, separate from historical teacher/manual testing and FT15/FT17 records.
- Damaged-item recording is implemented within the existing PO receiving flow. A receipt line may be accepted-only, damage-only, accepted plus damaged, or part of a multi-line mixture. Positive damage requires a normalized nonblank note (maximum 1,000 characters); quantity uses exact DECIMAL(14,3) handling, rejects nonpositive/overflow values, and follows the Variant's whole/fractional quantity mode. Damage is not capped by ordered quantity, outstanding demand, or earlier damage, and accepted plus damaged is not capped together. Accepted quantity alone remains capped by current outstanding.
- Damage evidence is immutable and uses the receipt's Restock submission token. Equivalent replay compares actor, source PO, accepted line semantics, damaged lines, quantities, and normalized notes; changed damage semantics conflict under the existing token behavior. Damage-only Restocks have zero RestockItems and zero StockMovements, total cost 0.00, unchanged stock/cost/outstanding, and no damage-driven PO status change. In mixed receipts, accepted quantity alone changes stock/cost, contributes receipt cost, creates RESTOCK movement evidence, and affects PO status. Follow-up transfers continue using outstanding derived from ordered minus accepted minus transferred; damage does not shrink transfer quantity or cause source/child double counting.
- Operations show accepted and damaged evidence separately using historical receiving/PO-line snapshots, quantity/unit, note, and actor/time context through Restock. Damage history is view-only for Admin and Staff; protected cost redaction remains unchanged. No damage edit, delete, or reversal route/workflow exists.
- **Current engineering verification for #30:** user-run guarded MySQL identity passed 3 tests / 18 assertions; #30D readiness passed 1 / 7; Race 1 passed 1 / 58; Race 2 passed 1 / 45; Race 3 passed 1 / 42; and the complete guarded suite passed 4 / 152. The current ordinary SQLite regression passed 439 tests / 4,509 assertions. The same-actor replay test may serialize on its User lock and does not claim unique-index collision recovery. These are current engineering checks, separate from historical teacher/manual, FT15, and paused FT17 records.
- The Admin-only #31 Damaged Items Report is a dedicated GET-only, read-only Reports page over immutable #30 evidence. It starts from `RestockDamageItem` and returns exactly one row per damage record; it never aggregates by Variant, PO, receipt, supplier, or item identity. Item identity and item searching use only the damage row's historical product, size, type/series, thickness, and unit snapshots, so catalog rename/archive does not rewrite report history. Each row shows its PO link, supplier context from PurchaseOrder, existing `RST-` receipt identifier, snapshot identity, damaged quantity and unit, damage note, Restock recording actor, and receipt timestamp. Filters are limited to supplier substring, item snapshot text, and exact numeric PO ID; invalid filters fail closed. Results sort by Restock creation time descending, Restock ID descending, then damage row ID descending. The full result set is rendered without pagination. The report shows no global quantity total across unlike units, costs, tokens, stock/movement details, mutation controls, or procurement lineage. The child PO is presented as its own PO. No database schema, migration, or index change was needed. Distinct empty states indicate no damage evidence and no matching rows. Staff remains forbidden from Reports even though Admin and Staff can view operational damage history in PO details.
- **Current engineering verification for #31:** focused report passed 6 tests / 108 assertions; Reports authorization passed 5 / 23; #28 passed 6 / 81; #29 passed 7 / 123; generic Reports passed 6 / 77; #30 damage foundation passed 4 / 37; and the full ordinary in-memory SQLite suite passed 445 tests / 4,617 assertions. `npm run build`, targeted Pint, and `git diff --check` passed. Bounded-query verification rendered eight damage rows with no more than six SELECTs. MySQL was not run for this read-only report. These are current engineering checks, separate from historical teacher/manual testing, FT15, and paused FT17 records.

**Other incomplete areas and limitations:** Sale Void, User Management, Audit Log writing/viewing, unified inventory movement history, several dedicated reports, refreshed edge/permission testing, final integration, screenshots, and final documentation review remain outstanding. Some accessibility checks, including contrast measurement and stronger programmatic association of validation messages, also remain for later evaluation.

The project is functional in its implemented core, but it is not presented as complete, fully tested, or ready for production use.

## 7. Reflection & Conclusion

### 7.1 Team Reflection

We learned that proper code structure and organization are important when building a web system with Laravel. The MVC pattern helped us separate responsibilities and made the application easier to maintain and debug. We also learned that it is not enough to make individual pieces of code work; the team needs to understand the complete flow from user input to validation, database changes, and the page shown to the user.

We learned that database accuracy is especially important in an inventory system because a small mistake can affect stock counts, reports, and later transactions. Proper validation and protected inventory transactions help prevent duplicate, missing, or incorrect records. Functional and hands-on system testing also revealed validation and inventory-update cases that were not obvious during normal development, helping us improve both reliability and usability.

Git helped us track changes and preserve earlier work, while task assignments made responsibilities clearer. Regular communication reduced duplicated work and helped the team avoid conflicts. When the teacher expanded the register and procurement scope, we reviewed the requirements again, identified the database and workflow changes, and adjusted our implementation priorities and testing plan without discarding the existing catalog, inventory, and sales functions.

If we started the project again, we would complete more of the requirements and system design before coding, begin testing earlier, plan tasks more clearly, and finalize the database structure sooner. These changes would reduce large revisions later and give the team more time for integration and final testing.

### 7.2 Conclusion

3A TrackPro now provides a working foundation for catalog and inventory management, Opening Inventory, Stock In and corrections, cash POS, Sales History and receipts, operational reporting, the opening-cash/register workflow, Purchase Order creation, browsing, detail viewing, pending-order editing, PO-based partial/full and damaged receiving, and Admin follow-up ordering for unfulfilled demand. Follow-up child POs preserve source lineage and transfer the full current remainder of each selected line without changing inventory. Receiving preserves accepted quantities, outstanding demand, actual cost evidence, linked history, and accepted-quantity inventory movements for Admin and Staff. Damaged receiving preserves immutable damage evidence and does not change sellable stock, cost, movement history, accepted/transferred quantities, or outstanding demand. The Admin-only #31 Damaged Items Report presents that evidence separately as one row per immutable damage record, using historical snapshots and receipt/PO/actor/time provenance. Current guarded MySQL concurrency verification covers receipt-versus-damage, equivalent damage-only replay, and damage-versus-follow-up races; #31 itself is read-only and required no MySQL run.

The project is still being developed. Other management workflows and final testing remain incomplete. The team will continue testing, refining the documentation, and preparing the system before the final presentation without presenting the current version as fully complete.

## 8. Appendices / Links

### 8.1 Project Tracker

- [Project Tracker](./project-tracker.md) — primary current task and progress record.

### 8.2 Repository

- [3A TrackPro GitHub Repository](https://github.com/wariza818189/3a-trackpro-system)

### 8.3 Deployed System

The system has not yet been publicly deployed.

### 8.4 Selected Screenshots

Selected screenshots will be added before final submission. Recommended screens include the Dashboard, Product Variants, Inventory Report, Opening Inventory or Stock In, POS, Sales History, receipt/reprint view, Purchase Order creation, and Purchase Order list/detail.

### 8.5 Requirements and Database Design

- [Project Requirements](./requirements.md)
- [Database Design](./database-design.md)

### 8.6 Testing Evidence

- [Detailed Test Cases](./test-cases.md)
- [Functional Test Results](./functional-test-results.md)

### 8.7 User Guide

- [System User Guide](./system-user-guide.md)
