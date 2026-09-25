# 3A TrackPro Project Tracker

- Team: The Visionaries
- Project: 3A TrackPro: Hardware Store Sales and Inventory Management System
- Final Presentation: October 22, 2026

This file is the canonical current project-progress tracker for the repository.
It mirrors the teacher task-tracker structure. The Excel workbook is manually
synchronized from this file for teacher checking.

`PROJECT_STATUS.md` is historical evidence/archive. Previous Notion tracking is
historical/reference only. Neither is an authoritative source of current
project status.

## Current Focus

Current development focus: **Final integration, remaining testing, documentation, and presentation preparation**

Status: **In Progress**

The Purchase Order schema/model foundation, authoritative low-stock eligibility
and open-coverage queries, Admin-only pending Purchase Order creation,
list/detail browsing, and pending/no-activity editing are implemented. #26
PO-based partial/full receiving is complete, including Admin/Staff access,
accepted/outstanding tracking, actual-cost evidence, inventory and movement
posting, idempotent replay, and guarded MySQL concurrency verification.
Follow-up POs, the Pending Purchase Orders Report, the Unfulfilled Items Report,
#30 damaged receiving, and the separate #31 Damaged Items Report are complete.

## Status Definitions

- **Completed** — the row's stated task and deliverable are materially complete
  and supported by implementation or documentation evidence.
- **In Progress** — meaningful work exists, but one or more material parts of
  the row remain incomplete. A paused task retains this status, with the pause
  recorded in Notes.
- **Not Started** — no material implementation of the row's stated task exists.

## Primary Tracker

| Modules | Items | Tasks | Assigned to | Start date | Due date | Status | Deliverable | Notes |
|---|---|---|---|---|---|---|---|---|
| PROJECT PLANNING | Tracking Document | Create and share the project task tracking document | Jonel Layupan | 09/01/2026 | 09/03/2026 | Completed | Shared Task Tracker | Shared with all project members. The repository tracker is the canonical current-progress source. |
| PROJECT PLANNING | Project Scope | Define the purpose, core features, limitations, and boundaries of 3A TrackPro | ROBERT JAMES WARIZA | 09/04/2026 | 09/08/2026 | Completed | Approved project scope | Use as the boundary for system features and implementation decisions. Scope and exclusions are documented. |
| PROJECT PLANNING | Client Requirements | Document the hardware store's current manual sales and inventory process | Jonel Layupan | 09/09/2026 | 09/10/2026 | In Progress | Requirements document | Cover current sales, receipts, inventory, and restocking process. Requirements artifacts exist, but verified evidence of the store's exact current manual process remains to be documented. |
| PROJECT PLANNING | Product Data | Prepare sample hardware categories, products, units, sizes, and variants | Jayson Amores | 09/10/2026 | 09/12/2026 | Completed | Product data list | Use as reference for database records, UI forms, and testing. Product-data planning and staging structure are documented. |
| PROJECT PLANNING | UI/UX | Prepare layout ideas for Dashboard, POS, Products, Inventory, and Reports | Jayson Amores | 09/10/2026 | 09/15/2026 | Completed | Wireframe / layout plan | Review layouts before major implementation. A retrospective, source-accurate UI/UX layout and workflow plan is complete. |
| PROJECT PLANNING | Business Rules | Define sales, stock-in, stock correction, permissions, and transaction rules | ROBERT JAMES WARIZA | 09/13/2026 | 09/17/2026 | Completed | Workflow and business rules | Include important edge cases and transaction-integrity rules. Requirements and database-design documents record the approved rules. |
| PROJECT PLANNING | Test Cases | Prepare test scenarios for login, sales, stock, restocking, and permissions | Rommel Jave Casipong | 09/15/2026 | 09/19/2026 | Completed | Testing checklist | Use throughout functional and edge-case testing. The prepared catalog contains 79 consolidated cases with 87/87 approved requirements and assumptions represented. |
| PROJECT PLANNING | Documentation | Prepare the structure of the project documentation and user guide | Willmer Largo | 09/17/2026 | 09/21/2026 | In Progress | Documentation outline | Update the document structure as development progresses. A substantial document set and user-guide structure exist, but final project-documentation structure and expansion updates remain incomplete. |
| PROJECT PLANNING | Database Design | Design database entities, relationships, constraints, roles, and authorization rules | ROBERT JAMES WARIZA | 09/18/2026 | 09/23/2026 | Completed | ERD / database design | Approve the database and system design before core coding. The implemented baseline and approved additive expansion design are documented. |
| DASHBOARD | Summary | Display sales and inventory summary | Jayson Amores | 10/09/2026 | 10/11/2026 | Completed | Working dashboard summary | Show key sales, transaction, product, and inventory information using current system data. Implemented and verified. |
| DASHBOARD | Low Stock | Display products reaching low-stock level | Jayson Amores | 10/10/2026 | 10/12/2026 | Completed | Working low-stock alert section | Show active products or variants whose stock is at or below the configured low-stock threshold. Implemented and verified. |
| DASHBOARD | Recent Sales | Display recent sales transactions | Jayson Amores | 10/11/2026 | 10/13/2026 | Completed | Working recent sales section | Show latest completed sales with transaction number, date/time, cashier, and total amount. Implemented and verified. |
| DASHBOARD | Recent Stock Activity | Display recent inventory movements | Jayson Amores | 10/12/2026 | 10/14/2026 | Not Started | Working recent stock activity section | Show recent stock-in, sales stock-out, corrections, and authorized stock restorations. The current Dashboard has no recent StockMovement activity section. |
| PRODUCT | New | Add a new product | Jayson Amores | 09/24/2026 | 09/27/2026 | Completed | Working product creation feature | Save category, product name, unit, cost price, selling price, low-stock threshold, and status. Implemented through normalized Product and ProductVariant workflows. |
| PRODUCT | Edit | Update an existing product | Jayson Amores | 09/27/2026 | 09/29/2026 | Completed | Working product update feature | Allow permitted product changes without modifying historical sales or stock records. Implemented through normalized Product and ProductVariant workflows. |
| PRODUCT | Archive | Archive an existing product | ROBERT JAMES WARIZA | 09/28/2026 | 09/29/2026 | Completed | Working product archive feature | Archived products remain in historical records but cannot be used for new sales. Implemented and verified. |
| PRODUCT | Search | View a specific product | Jayson Amores | 09/24/2026 | 09/26/2026 | Completed | Working product search and view feature | Search products and display relevant information, stock, status, and available variants. The searchable Product list links to a dedicated read-only detail page with Product status and permitted Variants, each showing its own stock and status. |
| PRODUCT | New Category | Add a new product category | Jayson Amores | 09/24/2026 | 09/25/2026 | Completed | Working category creation feature | Create reusable product categories and prevent invalid or duplicate category entries. Implemented and verified. |
| PRODUCT | Edit Category | Update an existing product category | Jayson Amores | 09/25/2026 | 09/26/2026 | Completed | Working category update feature | Update category information while preserving relationships with existing products. Implemented and verified. |
| PRODUCT | Archive Category | Archive an existing product category | ROBERT JAMES WARIZA | 09/27/2026 | 09/28/2026 | Completed | Working category archive feature | Preserve categories referenced by products and historical records instead of permanently deleting them. Implemented and verified. |
| PRODUCT | New Variant | Add a new product variant | Jayson Amores | 09/26/2026 | 09/28/2026 | Completed | Working product variant creation feature | Add variants when applicable, such as different size, type, thickness, unit, price, or stock configuration. Implemented and verified. |
| PRODUCT | Edit Variant | Update an existing product variant | Jayson Amores | 09/28/2026 | 09/30/2026 | Completed | Working product variant update feature | Allow permitted variant changes without altering historical transaction information. Implemented and verified. |
| PRODUCT | Archive Variant | Archive an existing product variant | ROBERT JAMES WARIZA | 09/30/2026 | 10/01/2026 | Completed | Working product variant archive feature | Archived variants remain in historical records but cannot be selected for new sales. Implemented and verified. |
| SALES / POS | New Sale | Start a new sales transaction | Jayson Amores | 09/29/2026 | 09/30/2026 | Completed | Working POS sales workspace | Provide a transaction workspace where the cashier can begin processing a customer sale. Implemented and verified. |
| SALES / POS | Product Search | Search and select a product for sale | Jayson Amores | 09/29/2026 | 10/01/2026 | Completed | Working POS product search and selection | Show active and sellable products or variants with current selling price and available stock. Implemented and verified. |
| SALES / POS | Cart | Add and remove products from the sales cart | Jayson Amores | 09/30/2026 | 10/02/2026 | Completed | Working sales cart | Allow multiple products or variants to be added and removed before checkout. Implemented and verified. |
| SALES / POS | Quantity | Update item quantities in the sales cart | Jayson Amores | 10/01/2026 | 10/02/2026 | Completed | Working cart quantity controls | Accept only valid positive quantities and prevent quantities greater than available stock. Backend checkout remains authoritative and prevents excessive quantities. |
| SALES / POS | Payment | Accept payment and calculate change | ROBERT JAMES WARIZA | 10/02/2026 | 10/04/2026 | Completed | Working payment and change calculation | Calculate amount due and change automatically and prevent checkout when cash received is insufficient. Implemented and verified. |
| SALES / POS | Checkout | Complete a sale and deduct inventory stock | ROBERT JAMES WARIZA | 10/03/2026 | 10/05/2026 | Completed | Completed and validated checkout feature | Save sale and sale items, preserve historical prices, verify and deduct stock, create stock movements, prevent negative stock and duplicate checkout, and avoid partial transactions on failure. Implemented and verified. |
| SALES / POS | Opening Cash Register | Open and close the global POS cash register with a starting cash amount and require an active register for new checkout. | TBD | — | — | Completed | Working controlled cash-register lifecycle | Implemented and verified under former tracker #24; technical closeout baseline `740fa3c`. Opening cash is register state only and does not enter sales totals. |
| SALES HISTORY | Search | View a specific sales transaction | Willmer Largo | 10/06/2026 | 10/07/2026 | Completed | Working sales transaction search | Search historical sales using available transaction information and open the selected transaction. Implemented and verified. |
| SALES HISTORY | Details | View transaction item details | Willmer Largo | 10/06/2026 | 10/07/2026 | Completed | Working sale details view | Display purchased items, quantities, historical prices, subtotals, total, payment, and change. Implemented and verified. |
| SALES HISTORY | Receipt | View a sales receipt | Willmer Largo | 10/07/2026 | 10/08/2026 | Completed | Working receipt view | Generate the receipt from saved historical transaction data rather than current product prices. Implemented and verified. |
| SALES HISTORY | Reprint | Reprint an existing sales receipt | Willmer Largo | 10/08/2026 | 10/08/2026 | Completed | Working receipt reprint feature | Allow an existing receipt to be printed again without creating another sale. Implemented and verified. |
| SALES HISTORY | Void | Void an existing sale with authorization | ROBERT JAMES WARIZA | 10/07/2026 | 10/08/2026 | Not Started | Working controlled sale void feature | Restrict voiding, require a reason, preserve the original sale, restore applicable stock, log movements/audit records, and prevent double voiding. The SALE_VOID workflow remains unimplemented. |
| INVENTORY | View Stock | Display current product stock | Rommel Jave Casipong | 09/29/2026 | 10/01/2026 | Completed | Working inventory stock view | Display current stock per product or variant together with unit, threshold, and stock status. Implemented and verified. |
| INVENTORY | Stock-In | Add or restock product quantities | ROBERT JAMES WARIZA | 10/06/2026 | 10/09/2026 | Completed | Working stock-in feature | Record restocked quantities and historical purchase cost, increase inventory, and create stock movement records. Implemented and verified for the legacy Stock In workflow. |
| INVENTORY | Stock Correction | Correct an incorrect stock quantity | ROBERT JAMES WARIZA | 10/09/2026 | 10/11/2026 | Completed | Working controlled stock correction feature | Implemented. The immutable CORRECTION StockMovement records the before/change/after quantities, actor, timestamp, and required reason; this movement is the required correction audit evidence, with cost unchanged. The broader Audit Trail / Record Activity remains a separate In Progress item. |
| INVENTORY | Low Stock | View products reaching low-stock level | Rommel Jave Casipong | 09/30/2026 | 10/01/2026 | Completed | Working low-stock inventory list | List products or variants whose current stock is at or below their configured threshold. Implemented and verified. |
| INVENTORY | Movement History | View stock movement history | Rommel Jave Casipong | 10/09/2026 | 10/11/2026 | In Progress | Working stock movement history | Display stock changes from sales, stock-in, corrections, and sale void restorations with date, quantity, reference, and user. No unified history covers all relevant movement types, references, and users; SALE_VOID restoration remains future. |
| PROCUREMENT | Purchase Order | Create and manage purchase orders with supplier snapshot, ordered quantities, expected unit costs, and pending status. | TBD | — | — | Completed | Working purchase-order creation and management | Implemented: Admin creates and edits pending POs with supplier/item snapshots, quantities, and expected costs; Admin and Staff browse and receive orders; partial/full receiving, follow-up POs, and reports #28–#31 are implemented. |
| PROCUREMENT | Low-Stock Prioritization | Prioritize initialized active low/out-of-stock variants for purchase-order planning and distinguish uncovered from already covered demand. | TBD | — | — | Completed | Working prioritized PO-planning list | Implemented in PO creation: uncovered low-stock Variants appear first; covered low-stock Variants remain visible/searchable with open coverage; current stock is shown and Admin chooses order quantities without an invented reorder quantity. |
| PROCUREMENT | PO-Based Receiving & Partial Delivery | Receive delivered quantities against purchase-order lines and support partial delivery while preserving accepted quantities and actual receiving cost. | TBD | — | — | Completed | Working PO receiving workflow | Maps to former #26. Partial/full PO-linked receiving is available to Admin and Staff, tracks accepted and outstanding quantities, records immutable actual receipt costs while preserving expected PO costs, and posts inventory with one RESTOCK movement per accepted line. Linked receipt history and idempotent replay are implemented; guarded MySQL concurrency verification passed (6 tests / 220 assertions). Legacy manual Stock In remains supported. |
| PROCUREMENT | Follow-up PO for Unfulfilled Quantities | Create a follow-up purchase order for selected remaining outstanding quantities while preserving traceability to the source PO. | TBD | — | — | Completed | Working follow-up purchase-order workflow | Maps to former #27 / expansion item #5. Admin transfers each selected source line's full current outstanding quantity to a traceable child PO; immutable transfer evidence, idempotent replay, lineage, edit freeze, and no-inventory-mutation behavior are implemented and guarded MySQL concurrency-verified. |
| PROCUREMENT | Damaged Item Recording | Record damaged quantities during PO receiving without adding damaged quantity to sellable stock. | TBD | — | — | Completed | Working damaged-receiving evidence workflow | Maps to #30 / expansion item #8. Admin and Staff record immutable damage evidence during PO receiving. Damage does not change stock, cost, accepted/transferred quantities, outstanding demand, or StockMovements. Verified with guarded MySQL concurrency tests. |
| REPORTS | Sales Report | Display sales within a selected date range | Rommel Jave Casipong | 10/10/2026 | 10/14/2026 | Completed | Working sales report | Show valid sales and totals for a selected period while handling voided transactions correctly. Completed-only report filtering is implemented and verified. |
| REPORTS | Product Sales | Display sales grouped by product | Rommel Jave Casipong | 10/10/2026 | 10/14/2026 | In Progress | Working product sales report | Summarize quantities sold and sales amounts by product or variant for the selected reporting period. Existing reporting does not yet provide the required Product/Variant-grouped quantity and sales-amount report. |
| REPORTS | Inventory Report | Display current inventory status | Rommel Jave Casipong | 10/10/2026 | 10/14/2026 | In Progress | Working inventory report | Show current stock quantities, units, categories, and stock status using current inventory data. Operational inventory data exists, but the dedicated report surface remains incomplete. |
| REPORTS | Low Stock Report | Display products at or below stock threshold | Rommel Jave Casipong | 10/11/2026 | 10/14/2026 | In Progress | Working low-stock report | Report active products or variants requiring attention based on their configured low-stock threshold. Operational low-stock data exists, but the dedicated report surface remains incomplete. |
| REPORTS | Restocking Report | Display historical stock-in records | Rommel Jave Casipong | 10/11/2026 | 10/14/2026 | In Progress | Working restocking report | Show stock-in history including product or variant, quantity, historical purchase cost, date, and responsible user. Operational Stock In history exists, but the dedicated report surface remains incomplete. |
| REPORTS | Pending Purchase Orders | Display pending/open purchase orders and their remaining outstanding demand. | TBD | — | — | Completed | Working pending-purchase-orders report | Maps to former #28 / expansion item #6. Admin-only, GET-only monitoring report includes pending or partially_received POs only when authoritative accepted and transfer evidence yields positive outstanding demand. |
| REPORTS | Unfulfilled Items | Display purchase-order items with remaining unfulfilled quantities. | TBD | — | — | Completed | Working unfulfilled-items report | Maps to former #29 / expansion item #7. Admin-only, GET-only line report shows each positive-outstanding PO item independently with historical snapshot identity and accepted/transfer-aware quantities. |
| REPORTS | Damaged Items Report | Display damaged-item history from PO receiving evidence. | TBD | — | — | Completed | Working damaged-items report | Maps to #31 / expansion item #9. Admin-only GET/read-only report presents one row per immutable RestockDamageItem, with PO, receipt, supplier, historical snapshots, quantity, note, actor, and time. Supplier substring, historical item text, and exact PO ID filters; newest receipt first. Implemented and current engineering-verified. |
| USER | Login | Log in to the system | ROBERT JAMES WARIZA | 09/24/2026 | 09/25/2026 | Completed | Working secure login feature | Validate credentials, create an authenticated session, and allow access according to account status and role. Implemented and verified. |
| USER | Logout | Log out of the system | ROBERT JAMES WARIZA | 09/24/2026 | 09/25/2026 | Completed | Working logout feature | End the authenticated session and prevent continued access to protected pages after logout. Implemented and verified. |
| USER | New | Add a new user | Jonel Layupan | 09/25/2026 | 09/26/2026 | Not Started | Working user creation feature | Allow authorized account creation with required information, secure password storage, role, and status. No User Management creation module exists; CLI-only Admin bootstrap is separate. |
| USER | Edit | Update an existing user | Jonel Layupan | 09/26/2026 | 09/27/2026 | Not Started | Working user update feature | Allow authorized changes to user information, role, or account status while preserving accountability. No User Management edit module exists. |
| USER | Archive | Archive an existing user | ROBERT JAMES WARIZA | 09/26/2026 | 09/27/2026 | Not Started | Working user archive feature | Disable future access while preserving references to previous transactions and system activities. No supported User Management archive/disable workflow exists. |
| USER | Search | View a specific user | Jonel Layupan | 09/25/2026 | 09/26/2026 | Not Started | Working user search and view feature | Search user accounts and display relevant account information, role, and status to authorized users. No User Management search/view module exists. |
| USER | Role & Access | Manage user roles and access permissions | ROBERT JAMES WARIZA | 09/24/2026 | 09/27/2026 | In Progress | Working role-based access control | Apply Admin/Owner and Staff/Cashier permissions and prevent unauthorized access to restricted functions. Server-side Admin/Staff enforcement exists; account role/status management remains missing. |
| AUDIT TRAIL | Record Activity | Record critical user and system activities | ROBERT JAMES WARIZA | 09/26/2026 | 10/11/2026 | In Progress | Working audit logging system | Record sensitive actions such as account changes, stock corrections, and sale voids with user, action, reference, timestamp, and reason when applicable. Schema/model foundation exists, but production workflows do not create ordinary AuditLog activity records. |
| AUDIT TRAIL | View Logs | Display audit trail records | Rommel Jave Casipong | 10/12/2026 | 10/14/2026 | Not Started | Working audit trail viewer | Allow authorized users to review recorded system activities and their relevant details. No audit viewer UI exists. |
| AUDIT TRAIL | Filter Logs | Search and filter audit trail records | Rommel Jave Casipong | 10/12/2026 | 10/14/2026 | Not Started | Working audit log filtering | Filter audit records using relevant criteria such as user, action, and date range. No audit viewer/filter UI exists. |
| TESTING | Functional Testing | Test all completed modules and document errors or unexpected results | Rommel Jave Casipong | 10/12/2026 | 10/13/2026 | Completed | Functional test results | Report discovered bugs to the project lead and retest after fixes. Formal run `FT15-20260909-A`: 30/30 Passed, 0 Failed, 0 Blocked, 0 Remaining. |
| TESTING | Edge Cases & Permissions | Test invalid inputs, insufficient stock, duplicate actions, and unauthorized access | Rommel Jave Casipong | 10/14/2026 | 10/15/2026 | In Progress | Edge-case / security test report | Include permission checks and transaction failure scenarios. PAUSED after teacher scope expansion. Historical FT17: 8 Pass, 1 Fail, 0 Blocked, 28 Remaining. AUTH006 retains its initial formal FAIL and provisional `TEST_SPEC_PROCEDURE_DEFECT`; controlled cross-origin retest is deferred. |
| DOCUMENTATION | User Guide & Screenshots | Prepare user instructions and organize final system screenshots | Willmer Largo | 10/14/2026 | 10/16/2026 | In Progress | User guide draft | Cover login, sales, products, inventory, reports, and other final user-facing features. A substantial user-guide draft exists; final screenshots and teacher-expansion updates remain. |
| FINALIZATION | Integration & Bug Fixing | Review all modules, fix identified issues, and prepare a stable final build | ROBERT JAMES WARIZA | 10/15/2026 | 10/18/2026 | Not Started | Release candidate | No major new features after this phase; prioritize stability and correctness. This phase has not begun. |
| DOCUMENTATION | Project Documentation | Finalize project description, objectives, features, workflows, and screenshots | Willmer Largo | 10/16/2026 | 10/19/2026 | Not Started | Final documentation | Final document should be reviewed by the group before submission/presentation. Finalization has not begun. |
| PRESENTATION | Demo Preparation | Prepare presentation slides, system demo flow, and speaking assignments | ALL MEMBERS | 10/18/2026 | 10/20/2026 | Not Started | Presentation materials | Every member should understand the system and assigned speaking part. Presentation preparation has not begun. |
| TESTING | Final Testing & Rehearsal | Perform final end-to-end testing and practice the system presentation | ALL MEMBERS | 10/20/2026 | 10/21/2026 | Not Started | Final checklist / rehearsal | Freeze the system before the final presentation except for critical fixes. Final testing and rehearsal have not begun. |
| PRESENTATION | Final Presentation | Present and demonstrate 3A TrackPro on finals day | ALL MEMBERS | 10/22/2026 | 10/22/2026 | Not Started | Final presentation | Finals day. |

## Status Summary

- Completed: 47
- In Progress: 11
- Not Started: 13
- Total: 71

## Excel Sync

The primary Markdown table mirrors the teacher workbook columns. Existing
teacher rows preserve their original owners and dates. When updating the
teacher workbook, copy the current Status and relevant Notes from this tracker.
New expansion rows use `TBD` and `—` until the team or teacher assigns owners
and dates. Update this GitHub tracker first, then synchronize Excel.

## Tracker Conventions

- The primary table is the only authoritative task-status table.
- Only `Completed`, `In Progress`, and `Not Started` are valid Status values.
- Pause state and technical evidence belong in Notes, not in the Status column.
- Former tracker numbers may appear in Notes only when useful for traceability.
- Historical test verdicts are immutable; later retests add evidence rather
  than rewriting earlier results.
