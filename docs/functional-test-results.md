# 3A TrackPro — Tracker #15 Functional Test Results

Run: `FT15-20260909-A`

Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`

Environment: `mysql_testing / trackpro_test`

Timezone: `Asia/Manila`

Scope: All **30 / 30** unique Tracker #15 cases have been executed and finalized: **30 Passed, 0 Failed, 0 Blocked, 0 Remaining**. `TC-SALES-002` was the final executed case. Its intentional current-catalog postcondition is retained. Tracker #17 remains separate and unexecuted.

## TC-AUTH-001 — Authentication — Admin login

- Timestamp: `2026-09-09T17:54:51+08:00`
- Executor: Codex in-app Browser Use, with user secure credential handoff
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use accessibility observations and rendered-page interaction
- Viewport: Login handoff followed by exact desktop verification at `1366 × 768`
- Result: **Pass**
- Expected: Open Login, enter the assigned username/password, and submit; the session ID changes and Dashboard opens as Admin.
- Actual observed: The authenticated request visibly opened the TrackPro Dashboard at `/`. At exact `1366 × 768`, the application visibly identified the user as `FT15 Admin` with role `Admin`. The Admin-only seven-day completed-sales trend was present. No password or session-cookie value was observed or recorded.
- Evidence reference: `BUE-FT15-AUTH-001` — formal Browser Use observation of successful authenticated Dashboard rendering and Admin identity.
- Supporting automated evidence: `EV-AUTH-LOGIN`; the focused `AuthenticationTest` class passed during this run, including active Admin login and `test_successful_login_explicitly_changes_the_session_id`.
- Issue/blocker: Browser Use exposes no supported cookie/session metadata interface that permits comparing the session identifier without risking disclosure of its value. The session-ID-change portion therefore uses the permitted supporting automated evidence and is not claimed as a browser observation.
- Notes: Admin remained authenticated for `TC-NAV-001`, then logged out through the application Sign out control. Credentials were entered by the user directly in the browser field.

## TC-NAV-001 — Navigation — Admin desktop/mobile destinations

- Timestamp: `2026-09-09T17:54:51+08:00`
- Executor: Codex in-app Browser Use
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use viewport control, accessibility observations, and rendered-page link interaction
- Viewports: Exact desktop `1366 × 768`; exact mobile `414 × 846`
- Result: **Pass**
- Expected: Exactly 10 destinations appear in both the desktop sidebar and mobile drawer, and each routes to its authorized page.
- Actual observed: Both navigation surfaces showed exactly: Dashboard, Reports, POS, Sales History, Categories, Products, Variants, Stock In, Opening Inventory, and Stock Correction. Every displayed destination was opened at both viewports and visibly produced its authorized route and page heading: Dashboard (`/`, `Dashboard`), Reports (`/reports`, `Sales Summary`), POS (`/pos`, `Point of Sale`), Sales History (`/sales`, `Sales History`), Categories (`/categories`, `Categories`), Products (`/products`, `Products`), Variants (`/product-variants`, `Product variants`), Stock In (`/stock-in`, `Stock In`), Opening Inventory (`/opening-inventory`, `Opening Inventory`), and Stock Correction (`/stock-corrections`, `Stock Correction`).
- Evidence reference: `BUE-FT15-NAV-001` — formal Browser Use desktop/mobile navigation counts, drawer rendering, route transitions, and page headings.
- Supporting automated evidence: `EV-NAV`; the focused `ResponsiveNavigationTest` class passed during this run, including the exact Admin ten-destination assertion for desktop and mobile markup.
- Issue/blocker: None.
- Notes: No forbidden URL was tested. The mobile drawer was opened through the rendered navigation control. Admin logout completed normally after the observations.

## TC-AUTH-002 — Authentication — Staff login

- Timestamp: `2026-09-09T17:54:51+08:00`
- Executor: Codex in-app Browser Use, with user secure credential handoff
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Staff (`FT15 Staff` synthetic account)
- Browser mechanism: Codex in-app Browser Use accessibility observations and rendered-page interaction
- Viewport: Login handoff followed by exact desktop verification at `1366 × 768`
- Result: **Pass**
- Expected: Repeat the successful login flow as Staff; Dashboard opens with Staff-appropriate content.
- Actual observed: The authenticated request visibly opened the TrackPro Dashboard at `/`. At exact `1366 × 768`, the application visibly identified the user as `FT15 Staff` with role `Staff`. Operational summary, Recent Completed Sales, and Low Stock Items content appeared, while the Admin-only seven-day completed-sales trend was absent. No password or session-cookie value was observed or recorded.
- Evidence reference: `BUE-FT15-AUTH-002` — formal Browser Use observation of successful authenticated Dashboard rendering and Staff identity/content.
- Supporting automated evidence: `EV-AUTH-LOGIN`; the focused `AuthenticationTest` class passed during this run, including active Staff login and `test_successful_login_explicitly_changes_the_session_id`.
- Issue/blocker: Browser Use exposes no supported cookie/session metadata interface that permits comparing the session identifier without risking disclosure of its value. The session-ID-change support is automated evidence only and is not claimed as a browser observation.
- Notes: Browser contexts were sequential rather than simultaneous: Admin logged out normally before Staff login. Staff remained authenticated for `TC-NAV-002`, then logged out through the application Sign out control. Credentials were entered by the user directly in the browser field.

## TC-NAV-002 — Navigation — Staff desktop/mobile destinations

- Timestamp: `2026-09-09T17:54:51+08:00`
- Executor: Codex in-app Browser Use
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Staff (`FT15 Staff` synthetic account)
- Browser mechanism: Codex in-app Browser Use viewport control, accessibility observations, and rendered-page link interaction
- Viewports: Exact desktop `1366 × 768`; exact mobile `414 × 846`
- Result: **Pass**
- Expected: Exactly seven destinations appear in both desktop and mobile navigation; Reports, Opening Inventory, and Stock Correction are absent.
- Actual observed: Both navigation surfaces showed exactly: Dashboard, POS, Sales History, Categories, Products, Variants, and Stock In. Reports, Opening Inventory, and Stock Correction were absent at both viewports. Every displayed destination was opened at both viewports and visibly produced its authorized route and page heading: Dashboard (`/`, `Dashboard`), POS (`/pos`, `Point of Sale`), Sales History (`/sales`, `Sales History`), Categories (`/categories`, `Categories`), Products (`/products`, `Products`), Variants (`/product-variants`, `Product variants`), and Stock In (`/stock-in`, `Stock In`).
- Evidence reference: `BUE-FT15-NAV-002` — formal Browser Use desktop/mobile navigation counts, Admin-only-item absence, drawer rendering, route transitions, and page headings.
- Supporting automated evidence: `EV-NAV`; the focused `ResponsiveNavigationTest` class passed during this run, including the exact Staff seven-destination and Admin-only-item absence assertions for desktop and mobile markup.
- Issue/blocker: None.
- Notes: No Admin-only route was visited directly. The mobile drawer was opened through the rendered navigation control. Staff logout completed normally after the observations.

## Supporting checks and data safety

- Focused automated support command: `php artisan test tests/Feature/Auth/AuthenticationTest.php tests/Feature/Navigation/ResponsiveNavigationTest.php`
- Result: **21 tests passed, 284 assertions**.
- Post-batch guarded read-only database check: configuration and server-side identity were required to resolve to `mysql_testing / trackpro_test` before five aggregate counts were read. The observed counts matched the controlled fixture baseline: `sales = 1`, `sale_items = 0`, `restocks = 0`, `restock_items = 0`, and `stock_movements = 3`.
- Domain-mutation conclusion: No new Sale, SaleItem, Restock, RestockItem, or StockMovement was created by these four non-domain-mutating cases.
- No migrate, reset, seed, rollback, cleanup, `--apply`, or other destructive database operation was performed.
- `trackpro_local` and Test Hammer were not accessed or changed.
- No password, password hash, cookie/session value, database credential, DSN, or token is included in this artifact.

## TC-CAT-001 — Categories — create and edit

- Timestamp: `2026-09-09T18:12:20+08:00`
- Executor: Codex in-app Browser Use
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 768`
- Result: **Pass**
- Expected: Create a Category from unique padded/mixed-case input, verify its normalized Active list value, then edit it to another unique normalized name; creation/update feedback and list values are correct.
- Actual observed: Padded input `FT15   bAtCh C   Catalog Category` created category ID 4 as normalized `FT15 bAtCh C Catalog Category`. The rendered index showed `Category created.`, zero Products, and Active status. Editing with padded input produced `FT15 Batch C Managed Category`; the index showed `Category updated.`, the normalized name, zero Products, and Active status.
- Evidence reference: `BUE-FT15-CAT-001` — formal Browser Use observation of the create form, route transition, success feedback, normalized list row, edit form, second success feedback, and updated Active row.
- Safe created/affected identifier: Category ID 4, final name `FT15 Batch C Managed Category`.
- Issue/blocker: None.
- Notes: Only the Category create/update required by this case was performed. Archive/reactivate was deferred to `TC-CAT-002`.

## TC-PROD-001 — Products — create and edit

- Timestamp: `2026-09-09T18:13:08+08:00`
- Executor: Codex in-app Browser Use
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 768`
- Result: **Pass**
- Expected: Start Create from an active Category row, submit a unique Product name, then edit its name and move it to another safe active Category; the route parent is authoritative, normalization and safe move persist, and success/list values are correct.
- Actual observed: Create was opened from Category ID 4's `Add product` control; the form visibly fixed the Category as `FT15 Batch C Managed Category` and the URL used `/categories/4/products/create`. Padded input created Product ID 3 as normalized `FT15 bAtCh C Test Product`; the index showed `Product created.`, the route-parent Category, zero Variants, and Active status. Edit normalized the final name to `FT15 Batch C Catalog Product` and moved it to active `FT15 Fixtures` (Category ID 1); the index showed `Product updated.` and the final Active row under `FT15 Fixtures`.
- Evidence reference: `BUE-FT15-PROD-001` — formal Browser Use observation of route-parent create context, normalized create result, edit selections, success feedback, and final moved list row.
- Safe created/affected identifier: Product ID 3, final name `FT15 Batch C Catalog Product`, final Category ID 1 (`FT15 Fixtures`).
- Issue/blocker: None.
- Notes: No historical activity or inventory mutation was created for the Product.

## TC-VAR-001 — Variants — whole-mode creation

- Timestamp: `2026-09-09T18:13:46+08:00`
- Executor: Codex in-app Browser Use
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 768`
- Result: **Pass**
- Expected: Create a unique Variant under an active Product using `piece`, whole quantity mode, and valid prices/threshold; it is Active with zero stock, exact prices/threshold, and no StockMovement, and the catalog form cannot set stock.
- Actual observed: Under Product ID 3, padded identity input created Variant ID 5 as normalized `FT15 Batch C Whole Variant · BC-W1 · 1.0 mm · piece`. The rendered index showed `Product variant created.`, Whole mode, no optional cost, selling price `150.25`, stock/threshold `0.000 / 4.000`, and Active status. The create form exposed no current-stock input.
- Evidence reference: `BUE-FT15-VAR-001` — formal Browser Use observation of the create form, submitted values, success feedback, and exact rendered Variant row; post-batch read-only reconciliation confirmed zero movements.
- Safe created/affected identifier: ProductVariant ID 5 under Product ID 3.
- Issue/blocker: None.
- Notes: No Opening Inventory or other inventory workflow was used; post-batch `movement_count = 0` for this Variant.

## TC-VAR-002 — Variants — fractional creation

- Timestamp: `2026-09-09T18:14:53+08:00`
- Executor: Codex in-app Browser Use
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 768`
- Result: **Pass**
- Expected: Create a unique fractional Variant under an active Product using `kg` or `m` and a three-decimal threshold, then reopen its edit form; unit/mode and exact decimals are retained, stock remains zero, and no movement is created.
- Actual observed: Under Product ID 3, padded identity input created Variant ID 6 as normalized `FT15 Batch C Fractional Variant · BC-F1 · 2.5 mm · kg`. The rendered index showed `Product variant created.`, Fractional mode, no optional cost, selling price `88.40`, stock/threshold `0.000 / 1.375`, and Active status. Reopening `/product-variants/6/edit` visibly retained the normalized identity, selected `kg`, selected Fractional mode, exact input value `88.40`, and exact three-decimal threshold `1.375`.
- Evidence reference: `BUE-FT15-VAR-002` — formal Browser Use observation of the create form, success/list row, reopened edit form, selected unit/mode, and exact retained input values; post-batch read-only reconciliation confirmed zero movements.
- Safe created/affected identifier: ProductVariant ID 6 under Product ID 3.
- Issue/blocker: None.
- Notes: No Opening Inventory or other inventory workflow was used; post-batch `movement_count = 0` for this Variant. `TC-VAR-003` was not executed.

## TC-CAT-002 — Catalog — valid archive/reactivate lifecycle

- Timestamp: `2026-09-09T18:15:58+08:00`
- Executor: Codex in-app Browser Use
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 768`
- Result: **Pass**
- Expected: Archive an eligible empty active Category or zero-stock historical Variant from its list, verify archived state, then reactivate it; lifecycle succeeds without hard delete or history rewrite and success feedback is shown.
- Actual observed: The intended lifecycle-only Category ID 2, `FT15 Lifecycle Category`, visibly showed zero Products and Active status before action. Archive produced `Category archived.`; the Archived filter showed the same ID/name with zero Products, Archived status, and a Reactivate control. Reactivate produced `Category reactivated.`; the Archived filter became empty, and the Active filter showed the same Category again with zero Products and Active status.
- Evidence reference: `BUE-FT15-CAT-002` — formal Browser Use observation of the eligible Active row, archive feedback, Archived-filter row, reactivate feedback, and restored Active row.
- Safe created/affected identifier: Existing lifecycle Category ID 2, `FT15 Lifecycle Category`; final status Active.
- Issue/blocker: None.
- Notes: `FT15 Fixtures` and all operational fixtures were left active. No record was deleted and no historical inventory transaction was rewritten.

## Batch C pre/post reconciliation

- Pre-batch guarded read-only snapshot: Categories `3`, Products `2`, ProductVariants `4`; Sales `1`, SaleItems `0`, Restocks `0`, RestockItems `0`, StockMovements `3`.
- Post-batch guarded read-only snapshot: Categories `4`, Products `3`, ProductVariants `6`; Sales `1`, SaleItems `0`, Restocks `0`, RestockItems `0`, StockMovements `3`.
- Exact intended net catalog changes: one Category (ID 4), one Product (ID 3), and two ProductVariants (IDs 5 and 6) were created. Existing lifecycle Category ID 2 was archived and reactivated, ending Active with the same identity. No other catalog record changed.
- Operational fixture preservation: Categories IDs 1 and 3, Products IDs 1 and 2, and ProductVariants IDs 1–4 retained their pre-batch names, relationships, statuses, prices, stock, and thresholds. The intended lifecycle Category ID 2 retained its name and ended Active.
- Inventory safety: ProductVariants IDs 5 and 6 both ended at `current_stock = 0.000` with zero StockMovements. The three pre-existing StockMovements remained the only movements.
- Transaction safety: no new Sale, SaleItem, Restock, RestockItem, or StockMovement was created.
- Execution progress after Batch C: **9 / 30** Tracker #15 formal cases executed — **9 Passed, 0 Failed, 0 Blocked, 21 Remaining**.
- Batch C used formal Browser Use evidence and guarded read-only reconciliation; no new automated suite was run for these five cases. The earlier `21 tests / 284 assertions` result remains focused supporting evidence only, not the full ordinary baseline. The recorded full ordinary baseline remains `195 tests / 2,056 assertions` and was not rerun here.
- No destructive database/provisioning operation, migration, reset, seed, rollback, cleanup, or second fixture apply was performed.
- `trackpro_local` and Test Hammer were not accessed or changed.

## TC-VAR-003 — Catalog — safe Staff browse

- Timestamp: `2026-09-09T22:56:57+08:00`
- Executor: Codex in-app Browser Use, with user secure credential handoff
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Staff (`FT15 Staff` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 768`
- Result: **Blocked**
- Expected: With active and archived hierarchy records and costs present, browse Categories, Products, and Variants as Staff; only active hierarchy is shown, and cost/status mutation controls are absent.
- Actual observed: The Staff session visibly opened Categories, Products, and Variants. Categories rendered four active rows with Name and Products columns only; Products rendered three active rows with Product, Category, and Variants columns only; Variants rendered six active rows with Product, Identity, Mode, Selling price, and Stock/threshold columns only. Cost price, Status, Actions, and catalog mutation controls were absent throughout. However, the required contrast fixtures did not exist: the guarded pre-case snapshot found zero archived Categories, zero archived Products, zero archived Variants, and zero cost-bearing Variants. The observation therefore could not prove that archived hierarchy rows and stored costs were actually suppressed.
- Evidence reference: `BUE-FT15-VAR-003` — formal Browser Use observation of all three Staff catalog pages and their rendered columns/controls; `DB-PRE-FT15-D` — guarded pre-case read-only fixture-precondition counts.
- Safe created/affected identifier: None.
- Issue/blocker: Required formal preconditions were absent, so the exact expected result could not be fully exercised without manufacturing unapproved test data or weakening the case.
- Notes: No direct permission probe was performed. No catalog or inventory mutation was made. Staff logged out normally after the observations.

### TC-VAR-003 retest and resolution

- Retest timestamp: `2026-09-09T23:40:59+08:00`
- Executor: Codex in-app Browser Use, with user secure credential handoff
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Staff (`FT15 Staff` synthetic account); Admin was used only for authorized test-support setup and cleanup
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 768`
- Test-support fixture setup: Existing Category ID 2, `FT15 Lifecycle Category`, was first verified Active with zero Products. Admin created Product ID 4, `FT15 Staff Contrast Archived Product`, then ProductVariant ID 7, `FT15 Staff Contrast Archived Variant`, with `piece`, Whole mode, blank cost, selling price `10.00`, threshold `0.000`, and stock `0.000`. Admin archived Variant ID 7, Product ID 4, and Category ID 2 bottom-up through supported lifecycle controls; each transition displayed its corresponding archived success message.
- Active cost contrast setup: Under active Product ID 3, `FT15 Batch C Catalog Product`, Admin created ProductVariant ID 8, `FT15 Staff Contrast Cost Variant`, with `piece`, Whole mode, `cost_price = 9182.43`, selling price `10000.00`, threshold `0.000`, and stock `0.000`. The Admin list showed the Variant Active with formatted cost `9,182.43` and stock/threshold `0.000 / 0.000`; reopening `/product-variants/8/edit` retained exact cost detail `9182.43`.
- Admin pre-retest contrast verification: Archived filters visibly showed Category ID 2 as Archived with one Product, Product ID 4 as Archived with one Variant, and Variant ID 7 as Archived with zero stock. The active Variant list visibly showed Variant ID 8 under active `FT15 Batch C Catalog Product` / `FT15 Fixtures`, with exact stored cost and zero stock.
- Staff Categories observed: The rendered table contained the three normal active Categories and only Name and Products columns. `FT15 Lifecycle Category` was absent. Create category, Status, Actions, Edit, Add product, Archive, and Reactivate controls were absent.
- Staff Products observed: The rendered table contained the three normal active Products and only Product, Category, and Variants columns. `FT15 Staff Contrast Archived Product` was absent. Status, Actions, Edit, Add variant, Archive, and Reactivate controls were absent.
- Staff Variants observed: The rendered table contained the six protected active Variants plus active ProductVariant ID 8. `FT15 Staff Contrast Cost Variant` was visibly present under `FT15 Batch C Catalog Product` / `FT15 Fixtures` with selling price `10,000.00` and stock/threshold `0.000 / 0.000`; archived ProductVariant ID 7 was absent. The page exposed only Product, Identity, Mode, Selling price, and Stock/threshold columns. Neither `9182.43` nor `9,182.43` appeared, and no Cost price, Status, Actions, Edit, Archive, or Reactivate heading/control was rendered.
- Evidence reference: `BUE-FT15-VAR-003-RETEST` — formal Browser Use evidence covering Admin contrast setup, archived-filter proof, Staff Categories/Products/Variants observations, active-cost presence with cost suppression, and Admin cleanup.
- Retest classification: **Pass**.
- Resolution: Initial execution: **Blocked**. Retest: **Pass**. Final `TC-VAR-003` classification: **Pass**.
- Post-retest cleanup: At `2026-09-09T23:42:52+08:00`, Admin archived only temporary ProductVariant ID 8. The application displayed `Product variant archived.`, and the Archived filter visibly showed ID 8 as Archived with retained cost `9,182.43` and zero stock. Product ID 3 remained Active under `FT15 Fixtures`, otherwise unchanged, with three child Variants. The archived Category ID 2 / Product ID 4 / Variant ID 7 branch remained archived and was not reactivated.
- Protected-state observation: The post-cleanup active Admin Variant list still showed operational/ProductVariant IDs 1–6 with their prior identities, ancestry, modes, prices, stock, thresholds, and Active status. ProductVariant ID 1 remained at `12.000 / 5.000`.
- Transaction safety: No Opening Inventory, Restock, Correction, Sale, SaleItem, or StockMovement workflow/control was used. Setup and cleanup were catalog-only create/archive actions; no record was deleted.
- Notes: The retest resolves the missing-contrast blocker without erasing the original Blocked history and does not count as a twelfth unique formal case. Both Staff and Admin logged out normally.

## TC-OI-001 — Opening Inventory — normal completion

- Timestamp: `2026-09-09T22:59:58+08:00`
- Executor: Codex in-app Browser Use, with user secure credential handoff
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 768`
- Result: **Pass**
- Expected: Open eligible `V-NEW`, submit quantity `12` with reason `Initial physical count`, and return to the index; stock becomes `12.000`, exactly one trusted `INITIAL_STOCK` movement exists, and success and initialized state appear.
- Actual observed: Before submission, the Opening Inventory index visibly identified ProductVariant ID 1 as `FT15 Whole Variant` under `FT15 Sample Material` / `FT15 Fixtures`, with unit `piece`, Whole mode, current stock `0.000`, and Not initialized state. The form repeated that exact identity and accepted `12.000` plus `Initial physical count`. Submit was activated exactly once. The browser returned to `/opening-inventory`, displayed `Opening inventory recorded.`, and showed the same Variant with current stock `12.000`, Initialized status, and Completed action state. During the original browser turn, the required post-action database reconciliation could not be completed: the explicitly authorized one-time SELECT-only run connected but ended with a query-shape `QueryException` before emitting any counts or movement rows. No Pass was claimed at that time. The later read-only reconciliation recorded below supplied the missing persisted-state evidence and finalized this result as Pass without another browser action or submission.
- Evidence reference: `BUE-FT15-OI-001` — original formal Browser Use observation of the reserved fixture, submitted values, single submit action, success feedback, resulting stock, and initialized/completed state. `DB-FT15-OI-001-RECON` — subsequent guarded SELECT-only reconciliation that finalized the Pass.
- Safe created/affected identifier: Existing ProductVariant ID 1, `FT15 Whole Variant`.
- Opening quantity: `12.000`.
- Browser-observed resulting stock: `12.000`.
- INITIAL_STOCK evidence reference: `DB-FT15-OI-001-RECON` confirmed exactly one movement for ProductVariant ID 1: movement ID 4, type `INITIAL_STOCK`, `quantity_before = 0.000`, `quantity_change = 12.000`, `quantity_after = 12.000`, null SaleItem/RestockItem references, reason `Initial physical count`, and `created_at = 2026-09-09 22:59:51` in the application's `Asia/Manila` timezone.
- Later read-only reconciliation timestamp: `2026-09-09T23:20:12+08:00`.
- Later read-only reconciliation evidence: Laravel resolved to environment `testing`, default connection `mysql_testing`, configured database `trackpro_test`, and live `SELECT DATABASE()` value `trackpro_test`. ProductVariant ID 1 resolved to size/identity `FT15 Whole Variant` under `FT15 Sample Material` / `FT15 Fixtures`, with `current_stock = 12.000`. Global counts were Categories `4`, Products `3`, ProductVariants `6`, Sales `1`, SaleItems `0`, Restocks `0`, RestockItems `0`, and StockMovements `4`. All four movements were `INITIAL_STOCK`; no `RESTOCK`, `SALE`, `CORRECTION`, or `SALE_VOID` movement existed. The documented guarded pre-Batch-D baseline had three total movements and zero movements for Variant ID 1, so movement ID 4 is the single Batch D addition and was not part of the provisioned baseline. Its timestamp is contemporaneous with the browser submission and precedes the preserved browser evidence timestamp by seven seconds because that timestamp records the completed observation.
- Issue/blocker: Resolved by the later guarded read-only reconciliation. The original same-turn query failure remains recorded above; no state repair, cleanup, browser retry, or second Opening Inventory submission occurred.
- Notes: Admin logged out normally after the original observation. The browser submit control was used once only; after redirect the row showed Completed and no Record opening inventory control for ProductVariant ID 1. The Pass was finalized only after the later read-only reconciliation.

## Batch D execution and reconciliation status

- Pre-batch guarded read-only baseline: Categories `4`, Products `3`, ProductVariants `6`; Sales `1`, SaleItems `0`, Restocks `0`, RestockItems `0`, StockMovements `3`.
- Reserved fixture before action: ProductVariant ID 1, `FT15 Whole Variant`, was active under active `FT15 Sample Material` / `FT15 Fixtures`, unit `piece`, Whole mode, stock `0.000`, threshold `5.000`, selling price `125.00`, with zero StockMovements and no `INITIAL_STOCK` movement.
- Browser-observed intended data effect: ProductVariant ID 1 changed from `0.000` / Not initialized to `12.000` / Initialized after one successful Opening Inventory submission.
- Post-batch database reconciliation: The original one-time query failure was later resolved by `DB-FT15-OI-001-RECON`, as recorded in `TC-OI-001`. That later guarded SELECT-only reconciliation confirmed ProductVariant ID 1 at `12.000`, exactly one trusted `INITIAL_STOCK` movement for it, and post-Batch-D counts of Categories `4`, Products `3`, ProductVariants `6`, Sales `1`, SaleItems `0`, Restocks `0`, RestockItems `0`, and StockMovements `4`.
- Duplicate-submission safety: The Opening Inventory form was submitted exactly once. No retry was made after the successful redirect, and no second Opening Inventory action was performed.
- Execution progress after the original Batch D reconciliation and before the TC-VAR-003 retest: **11 / 30** Tracker #15 formal cases executed — **10 Passed, 0 Failed, 1 Blocked, 19 Remaining**.
- No later Tracker #15 case and no Tracker #17 case was executed. No guarded MySQL formal test or automated suite was run for Batch D.
- No destructive database/provisioning operation, migration, reset, seed, rollback, cleanup, second fixture apply, or application-code change was performed.
- `trackpro_local` and Test Hammer were not accessed or changed.

## Retest final status

- Unique formal execution count remains **11 / 30**; the retest is resolution evidence for the existing case, not a new case.
- Final Tracker #15 status: **11 Passed, 0 Failed, 0 Blocked, 19 Remaining**.
- The original TC-VAR-003 Blocked execution remains recorded, followed by the successful contrast-backed retest.
- No Batch E case, other Tracker #15 case, Tracker #17 case, guarded MySQL formal test, or inventory transaction case was executed during this retest turn.

## TC-STKIN-009 — Stock In UI — dynamic rows

- Timestamp: `2026-09-10T00:04:59+08:00`
- Executor: Codex in-app Browser Use, with user secure credential handoff
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use viewport control, rendered-page interaction, accessibility observations, and visual screenshots
- Viewports: Exact responsive observations at `1023 × 846` and `1024 × 846`; dynamic-row and invalid-submit exercise at `1366 × 846`
- Result: **Pass**. The formal Browser component passed first with separate CLI no-domain-mutation reconciliation pending; the later guarded read-only reconciliation recorded below proved zero domain mutation and finalized the case as Pass.
- Expected: With at least two eligible initialized Variants, add and remove rows, select Variants, enter quantity/cost, and submit one intentionally invalid repeated-Variant row. Rows adapt at the Tailwind `lg` boundary, selection reveals implemented Variant context, old input returns after rejection, and global/available row feedback is visible. Category context and strong row-error association are not required by the committed case.
- Eligible-Variant baseline observed: The normal Stock In creation form offered both `FT15 Sample Material — FT15 Whole Variant — piece` (ProductVariant ID 1) and `FT15 Sample Material — FT15 Fractional Variant — kg` (ProductVariant ID 2), along with the initialized controlled support Variants.
- `1023 × 846` observation: The desktop sidebar was replaced by the hamburger navigation control. Within the received-item card, Variant, Received quantity, and Unit purchase cost rendered as stacked full-width fields, with the Remove item control below the context area.
- `1024 × 846` observation: The desktop sidebar and content offset appeared. Within the received-item card, Variant, Received quantity, and Unit purchase cost changed to a single horizontal row, with the Remove item control aligned at the row edge. This visually established the implemented layout change at `lg`.
- Add-row and Variant-context observation: Row 1 selected ProductVariant ID 1 with quantity `1` and current-entry unit cost `1.00`; the page rendered `FT15 Sample Material · FT15 Whole Variant · Unit: piece · whole · Current stock: 12.000`. Adding Row 2, selecting ProductVariant ID 2, and entering quantity `0.250` with current-entry unit cost `2.00` rendered `FT15 Sample Material · FT15 Fractional Variant · Unit: kg · fractional · Current stock: 6.500`.
- Remove/reindex observation: Removing the temporary second row through its Remove item control left one row and preserved Row 1's Whole Variant selection, quantity `1`, cost `1.00`, and context. The remaining row's Remove item control returned to disabled state. Adding another row then produced a new second row while preserving the first.
- Intentional duplicate submitted: Row 1 used ProductVariant ID 1, quantity `1`, unit purchase cost `1.00`; Row 2 used ProductVariant ID 1, quantity `2`, unit purchase cost `2.00`. Both rendered the Whole Variant context with current stock `12.000`, in top-to-bottom row order.
- Submission count: The invalid form was submitted exactly once through Record Stock In. It returned to `/stock-in/create`; no second click or corrected submission was performed.
- Exact validation feedback: The page displayed `Please correct the following:` followed by `The items.0.product_variant_id field has a duplicate value.` and `The items.1.product_variant_id field has a duplicate value.`
- Retained-old-input evidence: After rejection, both rows remained visible in their original order. Both retained the Whole Variant selection and context; Row 1 retained quantity `1` and cost `1.00`, while Row 2 retained quantity `2` and cost `2.00`. The duplicate situation remained understandable from the global two-row feedback and visible retained rows.
- Evidence reference: `BUE-FT15-STKIN-009` — formal Browser Use evidence for eligible selections, exact `1023/1024` layout transition, row addition/removal, both Variant contexts, preserved state, the single invalid submission, exact validation text, and returned old input.
- Domain-mutation status: Desktop Codex did not access any database or load `.env.mysql`. No successful Stock In was intentionally submitted. Separate guarded CLI SELECT-only verification of zero Restock, RestockItem, and StockMovement mutation remains pending.
- Later guarded read-only reconciliation timestamp: `2026-09-10T00:11:52+08:00`.
- Later guarded read-only reconciliation evidence: Laravel resolved to environment `testing`, default connection `mysql_testing`, configured database `trackpro_test`, and live `SELECT DATABASE()` value `trackpro_test`. Catalog counts remained Categories `4`, Products `4`, and ProductVariants `8`; transaction/history counts remained Sales `1`, SaleItems `0`, Restocks `0`, RestockItems `0`, and StockMovements `4`. Direct existence reads returned no Restock or RestockItem rows. All four movements remained `INITIAL_STOCK`, with no `RESTOCK`, `SALE`, `CORRECTION`, or `SALE_VOID` movement.
- Protected-state reconciliation: ProductVariant ID 1 remained active `FT15 Whole Variant`, `piece`, Whole mode, stock `12.000`, null cost, and exactly one movement (`INITIAL_STOCK`, `0.000 + 12.000 = 12.000`), with zero `RESTOCK` movements. ProductVariant ID 2 remained active `FT15 Fractional Variant`, `kg`, Fractional mode, stock `6.500`, null cost, and exactly one movement (`INITIAL_STOCK`, `0.000 + 6.500 = 6.500`), with zero `RESTOCK` movements. ProductVariants IDs 3–6 retained their prior identity, ancestry, status, price, stock, threshold, cost, and movement-count state. The archived Category ID 2 / Product ID 4 / ProductVariant IDs 7–8 support fixtures also remained unchanged, with both archived Variants at stock `0.000` and zero movements.
- Final classification: **Pass**. TC-STKIN-009 caused zero domain mutation. Tracker #15 is now **12 / 30 executed — 12 Passed, 0 Failed, 0 Blocked, 18 Remaining**.
- Issue/blocker: None in the Browser component. The committed limitation concerning strong row-error association was observed as global field-path feedback and is not classified as a new defect.
- Notes: The Admin session remains authenticated on the rejected form for possible later authorized work. `TC-STKIN-001`, `TC-STKIN-002`, Batch E2/E3, every other Tracker #15 case, and Tracker #17 were not executed.

## Batch E1 Browser status

- Unique Tracker #15 cases with Browser execution: **12 / 30**.
- Browser classifications: **12 Passed, 0 Failed, 0 Blocked, 18 Remaining**.
- TC-STKIN-009 was finalized Pass by the later guarded CLI zero-domain-mutation reconciliation recorded in its case entry.

## TC-STKIN-001 — Stock In — Admin multi-item receipt

- Timestamp: `2026-09-10T00:18:46+08:00`
- Executor: Codex in-app Browser Use, with user secure credential handoff inherited from the authenticated E1 session
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction, accessibility observations, and visual pre-submission capture
- Viewport: Exact desktop `1366 × 846`
- Result: **Pass**. The formal Browser component passed first with mandatory separate CLI persisted-state reconciliation pending; the later guarded read-only reconciliation recorded below proved the exact persisted receipt, items, latest Variant state, and movements and finalized the case as Pass.
- Expected: From Stock In history, open a fresh form with a unique server-generated submission token; create one immutable two-item receipt for the Whole and Fractional Variants. Balances and latest cost references increase exactly, one `RESTOCK` movement exists per item, and Admin can see historical costs. The receipt must be preserved.
- Fresh-form confirmation: The rejected `TC-STKIN-009` form was left through its Back to Stock In history control. History visibly showed no Stock In entries. Record Stock In then opened a blank single-row form with no validation feedback or retained values. No submission token was observed or recorded.
- Pre-submit Variant 1 context: Selecting ProductVariant ID 1 rendered `FT15 Sample Material · FT15 Whole Variant · Unit: piece · whole · Current stock: 12.000`.
- Pre-submit Variant 2 context: Selecting ProductVariant ID 2 rendered `FT15 Sample Material · FT15 Fractional Variant · Unit: kg · fractional · Current stock: 6.500`.
- Submitted rows: Row 1 was ProductVariant ID 1, `FT15 Whole Variant`, quantity `5.000`, unit purchase cost `70.00`. Row 2 was ProductVariant ID 2, `FT15 Fractional Variant`, quantity `2.250`, unit purchase cost `45.50`. No optional reference or notes value and no third item were entered.
- Pre-submit totals: The form did not render calculated line or receipt totals before submission. The approved expected calculations were therefore not claimed as pre-submit browser observations.
- Submission count: Record Stock In was clicked exactly once. The browser completed the request without ambiguity and redirected to `/stock-in/1`; no retry or second submission occurred.
- Success feedback and identifier: The detail page displayed `Stock In recorded.` and receipt number `RST-000001`; safe Restock identifier from the URL is ID 1.
- Receipt summary: The immutable detail displayed total `₱452.38`, recorded time `Sep 10, 2026 12:18 AM`, recorder `FT15 Admin`, Reference `—`, and Notes `—`.
- Variant 1 detail: `FT15 Sample Material` / `FT15 Whole Variant`, unit `piece`, quantity `5.000`, unit cost `₱70.00`, line total `₱350.00`, Before `12.000`, Change `5.000`, After `17.000`.
- Variant 2 detail: `FT15 Sample Material` / `FT15 Fractional Variant`, unit `kg`, quantity `2.250`, unit cost `₱45.50`, line total `₱102.38`, Before `6.500`, Change `2.250`, After `8.750`.
- History observation: Returning to Stock In history showed exactly one entry, `RST-000001`, at `Sep 10, 2026 12:18 AM`, recorder `FT15 Admin`, blank reference rendered as `—`, item count `2`, and total cost `₱452.38`. Opening its View link returned to `/stock-in/1` and reproduced the same immutable detail.
- Admin latest-cost observation: The active Product Variants page visibly showed ProductVariant ID 1 at cost `70.00` and stock/threshold `17.000 / 5.000`, and ProductVariant ID 2 at cost `45.50` and stock/threshold `8.750 / 2.500`.
- Evidence reference: `BUE-FT15-STKIN-001` — formal Browser Use evidence for the fresh form, exact pre-state contexts, two submitted rows, single successful submission, success redirect, receipt/history identity and totals, immutable item detail, before/change/after values, and Admin latest-cost references.
- Persisted-state status: Desktop Codex did not access a database or load `.env.mysql`. Separate guarded CLI reconciliation remains required to prove Restocks `0 → 1`, RestockItems `0 → 2`, StockMovements `4 → 6`, exact two `RESTOCK` movements, persisted stocks/costs, and absence of unintended movement types.
- Later guarded read-only reconciliation timestamp: `2026-09-10T00:25:15+08:00`.
- Later guarded read-only identity and receipt evidence: Laravel resolved to environment `testing`, default connection `mysql_testing`, configured database `trackpro_test`, and live `SELECT DATABASE()` value `trackpro_test`. Exactly one Restock existed: ID 1, derivable display identifier `RST-000001`, recorded by active Admin user ID 1 / `FT15 Admin`, with null reference and notes, persisted total `452.38`, two items, and `created_at = 2026-09-10 00:18:08`, contemporaneous with the Browser execution.
- Later guarded read-only item evidence: RestockItem ID 1 belonged only to Restock ID 1 and ProductVariant ID 1 and permanently stored snapshots `FT15 Sample Material` / `FT15 Whole Variant` / `piece`, quantity `5.000`, unit cost `70.00`, and line total `350.00`. RestockItem ID 2 belonged only to Restock ID 1 and ProductVariant ID 2 and permanently stored snapshots `FT15 Sample Material` / `FT15 Fractional Variant` / `kg`, quantity `2.250`, unit cost `45.50`, and line total `102.38`. Each item had exactly one linked `RESTOCK` movement.
- Later guarded read-only Variant and movement evidence: ProductVariant ID 1 remained active with latest stock `17.000` and latest cost reference `70.00`; its linked movement ID 5 was `RESTOCK`, `12.000 + 5.000 = 17.000`, performed by user ID 1 / `FT15 Admin`, linked to RestockItem ID 1, with null SaleItem and reason. ProductVariant ID 2 remained active with latest stock `8.750` and latest cost reference `45.50`; its linked movement ID 6 was `RESTOCK`, `6.500 + 2.250 = 8.750`, performed by the same Admin, linked to RestockItem ID 2, with null SaleItem and reason. The latest Variant cost references did not replace or rewrite either historical RestockItem cost or snapshot.
- Later guarded read-only global/protected-state evidence: Counts were Categories `4`, Products `4`, ProductVariants `8`, Sales `1`, SaleItems `0`, Restocks `1`, RestockItems `2`, and StockMovements `6`. Movement types were exactly `INITIAL_STOCK = 4` and `RESTOCK = 2`, with no `SALE`, `CORRECTION`, or `SALE_VOID`. ProductVariants IDs 3–6 and the archived Category ID 2 / Product ID 4 / ProductVariant IDs 7–8 support fixtures retained their previously reconciled state; Variants IDs 5–8 had no movements.
- Final classification: **Pass**. Exactly one immutable two-item receipt and exactly two one-to-one linked `RESTOCK` movements were persisted, with no duplicate receipt, item, or movement. Tracker #15 is now **13 / 30 executed — 13 Passed, 0 Failed, 0 Blocked, 17 Remaining**.
- Issue/blocker: None in the Browser component.
- Notes: The receipt was not edited, deleted, undone, or cleaned up. The Admin session remains authenticated on Product Variants for possible later authorized work. `TC-STKIN-002`, Batch E3, every other Tracker #15 case, and Tracker #17 were not executed.

## Batch E2 Browser status

- Unique Tracker #15 cases with Browser execution: **13 / 30**.
- Browser classifications: **13 Passed, 0 Failed, 0 Blocked, 17 Remaining**.
- TC-STKIN-001 was finalized Pass by the later guarded CLI persisted-state reconciliation recorded in its case entry.

## TC-STKIN-002 — Stock In — Staff current-cost entry

- Timestamp: `2026-09-10T00:32:36+08:00`
- Executor: Codex in-app Browser Use with secure browser-side Staff authentication; Codex did not enter, observe, transcribe, or record the password
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Staff (`FT15 Staff` synthetic account)
- Browser mechanism: Codex in-app Browser Use rendered-page interaction and accessibility observations
- Viewport: Exact desktop `1366 × 846`
- Result: **Pass** for the formal Browser component; mandatory separate CLI persisted-state reconciliation is pending before Batch E3 review is complete.
- Expected: Staff may enter the current purchase cost while recording Stock In. The one-item receipt commits and remains accessible, but existing and historical purchase costs are hidden after submission and elsewhere in Staff UI. Staff entry of the new current cost is required and must not be mischaracterized as prohibited.
- Authentication/session evidence: The authenticated Admin session was logged out normally. Browser-side Staff authentication completed without exposing a password to Codex, and the rendered application identity was confirmed as `FT15 Staff` with role `Staff` before testing.
- Existing-history privacy before submission: Staff Stock In history showed accessible `RST-000001` with date/time, recorder `FT15 Admin`, blank reference `—`, item count `2`, and View action. The table omitted the Total cost column and displayed no historical purchase-cost values.
- Existing Admin receipt privacy before submission: Staff opened `/stock-in/1`. The detail showed receipt identity, recorded time, recorder, reference/notes, Product/Variant snapshots, units, quantities, and Before/Change/After values. It omitted Unit cost, Line total, and Total cost fields, and did not expose the historical purchase costs `70.00` or `45.50` as purchase-cost content.
- Product Variants privacy before submission: The Staff Product Variants table showed active ProductVariant ID 1 at selling price `125.00` and stock/threshold `17.000 / 5.000`, but exposed no Cost price column/control, latest cost `70.00`, Status/Actions columns, Edit, or Archive controls.
- Fresh Staff form: Staff navigated normally from Stock In history to a new blank Record Stock In form with one row and no retained values or validation feedback. No submission token was observed or recorded.
- Pre-submit context and cost privacy: Selecting ProductVariant ID 1 rendered `FT15 Sample Material · FT15 Whole Variant · Unit: piece · whole · Current stock: 17.000`. Unit purchase cost remained blank after selection; existing latest cost `70.00` was neither prefilled nor otherwise disclosed.
- Submitted row: The single row used ProductVariant ID 1, `FT15 Whole Variant`, quantity `2.000`, and Staff-entered current unit purchase cost `72.00`. No second row and no optional reference/notes value were added. The pre-submit appearance of `72.00` was the authorized current-entry value and is not classified as a privacy disclosure.
- Submission count: Record Stock In was clicked exactly once. The browser completed the request without ambiguity and redirected to `/stock-in/2`; no retry or second receipt submission occurred.
- Success feedback and identifier: The page displayed `Stock In recorded.` and receipt number `RST-000002`; safe Restock identifier from the URL is ID 2. The rendered recorder was `FT15 Staff`, with one item.
- New receipt detail privacy: Staff saw receipt identity, time `Sep 10, 2026 12:31 AM`, recorder `FT15 Staff`, Reference `—`, Notes `—`, `FT15 Sample Material` / `FT15 Whole Variant`, unit `piece`, quantity `2.000`, Before `17.000`, Change `2.000`, and After `19.000`. Unit cost, Line total, Total cost, and historical purchase-cost value `72.00` were absent after submission.
- Staff history after submission: Stock In history showed both legitimate receipts exactly once: `RST-000002` with recorder `FT15 Staff` and item count `1`, followed by `RST-000001` with recorder `FT15 Admin` and item count `2`. The table continued to omit Total cost and all purchase-cost values.
- Previous Admin receipt privacy after submission: Reopening `/stock-in/1` again showed its permitted identities, quantities, and stock history while continuing to omit Unit cost, Line total, Total cost, `70.00`, and `45.50` as purchase-cost content.
- Product Variants privacy after submission: The Staff Product Variants table showed ProductVariant ID 1 active with selling price `125.00` and updated stock/threshold `19.000 / 5.000`. It still exposed no Cost price column/control, latest current cost `72.00`, prior cost `70.00`, Status/Actions columns, Edit, or Archive controls.
- Evidence reference: `BUE-FT15-STKIN-002` — formal Browser Use evidence for pre-existing history/detail privacy, pre-submit Variant privacy, fresh form and blank cost field, Staff current-cost entry, single successful submission, new and prior receipt privacy, permitted before/change/after values, and post-submit Variant cost suppression.
- Persisted-state status: Desktop Codex did not access a database or load `.env.mysql`. Separate guarded CLI reconciliation remains required to prove Restocks `1 → 2`, RestockItems `2 → 3`, StockMovements `6 → 7`, exact persisted latest cost `72.00`, preserved historical costs, one new `RESTOCK` movement, unchanged Variant ID 2, and absence of unintended movement types.
- Later guarded read-only reconciliation timestamp: `2026-09-10T13:34:49+08:00` (Asia/Manila).
- Later guarded read-only identity evidence: Laravel resolved to environment `testing`, default connection `mysql_testing`, configured database `trackpro_test`, and live `SELECT DATABASE()` value `trackpro_test`; the live database was not `trackpro_local`.
- Later guarded read-only Staff receipt evidence: Restock ID 2, derived display identifier `RST-000002`, persisted exactly once with `created_at = 2026-09-10 00:31:54`, contemporaneous with Browser E3. It was recorded by active Staff user ID 2 / `FT15 Staff`, retained null reference and notes because neither optional field was entered, persisted total cost `144.00`, and had exactly one RestockItem.
- Later guarded read-only Staff item/snapshot evidence: RestockItem ID 3 belonged only to Restock ID 2 and ProductVariant ID 1. It retained quantity `2.000`, unit cost `72.00`, line total `144.00`, and immutable snapshots `FT15 Sample Material` / `FT15 Whole Variant` / `piece`, with empty type/series and thickness snapshots as expected.
- Later guarded read-only Variant 1 and Staff movement evidence: ProductVariant ID 1 remained active `FT15 Whole Variant`, unit `piece`, Whole mode, with final stock `19.000` and latest cost reference `72.00`. Its complete three-movement chain was movement ID 4 `INITIAL_STOCK` (`0.000 + 12.000 = 12.000`), movement ID 5 Admin `RESTOCK` (`12.000 + 5.000 = 17.000`, linked to RestockItem ID 1), and movement ID 7 Staff `RESTOCK` (`17.000 + 2.000 = 19.000`, linked to RestockItem ID 3). Movement ID 7 was performed by user ID 2 / `FT15 Staff`, had null SaleItem and reason, and was the sole movement linked to the sole Staff RestockItem, proving one-to-one linkage with no duplicate.
- Later guarded read-only historical-cost integrity evidence: Restock ID 1 / `RST-000001` remained unchanged at `created_at = 2026-09-10 00:18:08`, active Admin recorder ID 1 / `FT15 Admin`, null reference/notes, total `452.38`, and exactly two items. RestockItem ID 1 still retained ProductVariant ID 1 snapshots `FT15 Sample Material` / `FT15 Whole Variant` / `piece`, quantity `5.000`, historical unit cost `70.00`, and line total `350.00`; its original movement ID 5 remained `12.000 + 5.000 = 17.000`. RestockItem ID 2 still retained ProductVariant ID 2 snapshots `FT15 Sample Material` / `FT15 Fractional Variant` / `kg`, quantity `2.250`, historical unit cost `45.50`, and line total `102.38`. Thus Variant ID 1's latest cost update from `70.00` to `72.00` did not rewrite the earlier Admin item, its snapshots, or its movement.
- Later guarded read-only Variant 2/protected-state evidence: ProductVariant ID 2 remained active `FT15 Fractional Variant`, unit `kg`, Fractional mode, stock `8.750`, and latest cost `45.50`, with exactly its two prior movements: `INITIAL_STOCK` (`0.000 + 6.500 = 6.500`) and Admin `RESTOCK` (`6.500 + 2.250 = 8.750`, linked to RestockItem ID 2). ProductVariants IDs 3–6 retained their prior identity, ancestry, status, price, stock, threshold, cost, and movement-count state. Category ID 2 / `FT15 Lifecycle Category`, Product ID 4 / `FT15 Staff Contrast Archived Product`, Variant ID 7 / `FT15 Staff Contrast Archived Variant`, and Variant ID 8 / `FT15 Staff Contrast Cost Variant` remained archived; Variants 7–8 remained at stock `0.000` with zero movements. No new movement existed for Variants IDs 5–8.
- Later guarded read-only global/duplicate evidence: Counts were Categories `4`, Products `4`, ProductVariants `8`, Sales `1`, SaleItems `0`, Restocks `2`, RestockItems `3`, and StockMovements `7`. Movement types were exactly `INITIAL_STOCK = 4` and `RESTOCK = 3`, with zero `SALE`, `CORRECTION`, and `SALE_VOID`. E3 added exactly one Restock, one RestockItem, and one one-to-one-linked `RESTOCK` movement; no duplicate receipt, item, movement, Sale/SaleItem mutation, Correction, SALE_VOID, or other unexpected write was present. All three historical item costs remained `70.00`, `45.50`, and `72.00` respectively.
- Final classification: **Pass**. TC-STKIN-002's persisted state matches every required value. Tracker #15 is now **14 / 30 executed — 14 Passed, 0 Failed, 0 Blocked, 16 Remaining**.
- Batch E final persisted state reconciled successfully. `TC-STKIN-009`, `TC-STKIN-001`, and `TC-STKIN-002` are all finalized **Pass**; Batch E is fully finalized.
- Issue/blocker: None in the Browser component.
- Notes: Neither receipt was edited, deleted, undone, or cleaned up. The Staff session remains authenticated on Product Variants. No other Tracker #15 case, Tracker #17 case, later Batch, or additional Stock In was executed.

## Batch E3 Browser status

- Unique Tracker #15 cases with Browser execution: **14 / 30**.
- Browser classifications: **14 Passed, 0 Failed, 0 Blocked, 16 Remaining**.
- TC-STKIN-002 was finalized Pass by the later guarded CLI persisted-state reconciliation recorded in its case entry; Batch E3 is fully reviewed.

## TC-CORR-001 — Stock Correction — valid physical adjustment

- Timestamp: `2026-09-11T01:08:09+08:00` (persisted movement timestamp in `Asia/Manila`; an exact browser-observation timestamp was not supplied)
- Executor: Codex Desktop built-in browser
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex Desktop built-in browser rendered-page interaction and textual observation
- Viewport: Unavailable
- Result: **Pass**
- Expected: Correct the initialized active `FT15 Whole Variant` from physical stock `19.000` to target `18.000` through the normal Stock Correction workflow, retaining cost and recording one exact immutable `CORRECTION` movement by the Admin with the supplied reason.
- Browser-observed result: The form submitted target `18.000` exactly once with reason `FT15 TC-CORR-001 physical adjustment`. The application displayed `Stock Correction recorded.`, showed ending stock `18.000`, and rendered correction history with Before `19.000`, Change `-1.000`, After `18.000`, actor `FT15 Admin`, and the expected reason. No ambiguity was reported.
- Screenshot status: No screenshot was captured; evidence is the supplied Codex Desktop browser observation plus independent persisted-state reconciliation.
- Evidence reference: `CDB-FT15-CORR-001` — Codex Desktop built-in browser observation of the single normal workflow submission, visible success feedback, resulting stock, and rendered history. `DB-FT15-CORR-001-RECON` — guarded SELECT-only persisted-state reconciliation.
- Safe created/affected identifier: Existing ProductVariant ID 1; new StockMovement ID 8.
- Persisted-state reconciliation: The guarded verifier and live `SELECT DATABASE()` proved environment `testing`, connection `mysql_testing`, and actual database `trackpro_test`. ProductVariant ID 1 remained active, unit `piece`, Whole mode, latest cost `72.00`, selling price `125.00`, threshold `5.000`, and current stock `18.000`. ProductVariant ID 2 remained unchanged at `8.750`.
- Immutable movement evidence: StockMovement ID 8 is the sole `CORRECTION` movement and records ProductVariant ID 1, `19.000 + (-1.000) = 18.000`, performed by active Admin user ID 1 / `FT15 Admin`, with null SaleItem and RestockItem references and exact reason `FT15 TC-CORR-001 physical adjustment`.
- Duplicate/integrity evidence: The exact correction predicate matched one row only. Total StockMovements changed from the documented baseline `7` to `8`, comprising `INITIAL_STOCK = 4`, `RESTOCK = 3`, and `CORRECTION = 1`, with zero `SALE` and `SALE_VOID`. All other FT15 Variant stocks and costs remained at their documented pre-case values.
- Sales integrity: The fixture-only Sale ID 1 remained the sole Sale, retained non-completed `voided` status and zero SaleItems, and remained explicitly marked as controlled fixture evidence rather than an executed void workflow. Completed Sales, SaleItems, `SALE` movements, and `SALE_VOID` movements all remained zero.
- Final classification: **Pass**. The successful browser workflow and independent persisted-state reconciliation agree. Tracker #15 is now **15 / 30 executed — 15 Passed, 0 Failed, 0 Blocked, 15 Remaining**.
- Issue/blocker: None.
- Notes: No screenshot was captured and the browser viewport was unavailable. No retry, second correction, direct database mutation, POS case, later Tracker #15 case, or Tracker #17 case was executed.

## TC-POS-008 — POS UI — responsive catalog/cart

- Timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop built-in browser
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account)
- Browser mechanism: Codex Desktop built-in browser rendered-page interaction, responsive viewport control, scrolling, and genuine in-app screenshot capture
- Viewports: Exact wide-screen `1280 × 720`; exact below-`xl` `1023 × 720`
- Result: **Pass**
- Expected: Search the populated POS, add/increment/edit/remove an item, inspect the resulting dynamic quantities and estimated totals, and compare responsive layout below and at/above `xl`. Dynamic cart/totals follow each action; below `xl` the Cart follows the catalog in a stacked layout, while at/above `xl` the catalog and Cart use a split layout with the Cart remaining visible while scrolling.
- Preconditions observed: The populated POS showed `FT15 Whole Variant` with stock `18.000` and `FT15 Fractional Variant` with stock `8.750`.
- Browser-observed actions: Searched for `FT15 Whole Variant`; added it at quantity `1`; added it again to increment quantity to `2`; edited quantity to `3`; removed it from the Cart; inspected both responsive viewports; and inspected wide-screen Cart behavior while scrolling.
- Dynamic result evidence: Estimated totals were `₱125.00` at quantity `1`, `₱250.00` at quantity `2`, and `₱375.00` at quantity `3`. After removal, the Cart was empty, the estimated total was `₱0.00`, and Checkout was disabled. No validation message was expected or displayed.
- Responsive result evidence: At `1023 × 720`, the Cart followed the catalog in the stacked layout. At `1280 × 720`, the catalog and Cart were split side-by-side and the Cart remained visible while scrolling.
- Known UI-risk observation: Category context remained absent from the POS UI, consistent with the committed test-case and UI-risk documentation; it is not classified as a `TC-POS-008` failure.
- Transaction result: No Sale completed, and no receipt or success transition occurred.
- Screenshot status: Genuine in-app browser screenshots were captured for the below-`xl` stacked layout and wide sticky-Cart layout. No external screenshot identifier was generated or recorded.
- Evidence reference: `CDB-FT15-POS-008` — the supplied Codex Desktop browser observations and in-app screenshot capture described above; this document-local label is not an external screenshot identifier. `DB-FT15-POS-008-RECON` — the independently completed guarded SELECT-only zero-mutation reconciliation.
- Independent zero-mutation reconciliation: Before this documentation update, the guarded verifier and live `SELECT DATABASE()` proved actual database `trackpro_test`. ProductVariant ID 1 remained at `18.000`, ProductVariant ID 2 remained at `8.750`, and all other FT15 Variant balances and relevant timestamps remained unchanged. The sole Sale remained the fixture-only non-completed control; completed Sales and SaleItems remained zero. Total StockMovements remained `8`, comprising `INITIAL_STOCK = 4`, `RESTOCK = 3`, and `CORRECTION = 1`, with zero `SALE` and `SALE_VOID` movements.
- Final classification: **Pass**. The supplied browser evidence satisfies the formal interactive/responsive expectations, and the independent persisted-state reconciliation proves the case caused no transaction or inventory mutation. Tracker #15 is now **16 / 30 executed — 16 Passed, 0 Failed, 0 Blocked, 14 Remaining**.
- Issue/blocker: None.
- Notes: The browser engine/version and exact execution timestamp were not supplied and are not inferred. At the close of `TC-POS-008`, `TC-POS-001` and every later Tracker #15 case remained unexecuted; `TC-POS-001` was executed and reconciled afterward. Every Tracker #17 case remains unexecuted.

## TC-POS-001 — POS — valid multi-Variant cash checkout

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Persisted transaction timestamp: `2026-09-11T01:54:17+08:00` (`Asia/Manila`).
- Executor: Codex Desktop built-in browser observation, with Checkout activation occurring during user handoff; independent guarded CLI reconciliation by Codex.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Staff (`FT15 Staff` synthetic account).
- Browser mechanism: Codex Desktop built-in browser rendered-page observation; Checkout was activated exactly once during user handoff, not by Codex.
- Viewport: Unavailable; no viewport dimensions were supplied.
- Screenshot status: A genuine in-app browser screenshot was captured showing the completed receipt. No external screenshot identifier was generated.
- Result: **Pass**
- Expected: Complete one cash checkout containing `2.000` of `FT15 Whole Variant` at `125.00` (`250.00`) and `1.250` of `FT15 Fractional Variant` at `80.00` (`100.00`), for total `350.00`, cash `400.00`, change `50.00`, and ending stocks `16.000` and `7.500` respectively.
- Supplied browser observation: Localhost was reachable and the POS was used as `FT15 Staff`. The starting POS stocks displayed were Whole `18.000` and Fractional `8.750`. The Cart contained Whole quantity `2.000` at `₱125.00` for `₱250.00` and Fractional quantity `1.250` at `₱80.00` for `₱100.00`, with total `₱350.00`, cash `₱400.00`, and change `₱50.00`. Checkout was activated exactly once during the user handoff. Codex observed the completed state, exact visible success feedback `Sale completed.`, and the successful transition to `/sales/2`; Codex did not retry. The receipt visibly showed reference `TRX-000002`, cashier `FT15 Staff`, and exactly two lines with the expected products, units, quantities, prices, and totals. Returning to the POS showed Whole stock `16.000` and Fractional stock `7.500`. Concern/ambiguity: None.
- Receipt/transaction reference: The browser visibly transitioned to `/sales/2` and displayed `TRX-000002`; the independent persisted-state reconciliation identifies the same completed receipt as Sale ID 2 / `TRX-000002`. No checkout token was selected or recorded.
- Independent database identity: The guarded verifier and live `SELECT DATABASE()` proved MySQL 8/InnoDB, environment `testing`, connection `mysql_testing`, and actual database `trackpro_test` before reconciliation.
- Completed Sale evidence: Sale ID 2 / `TRX-000002` is the sole completed Sale and the sole Sale matching this checkout. It was recorded by active Staff user ID 2 / `FT15 Staff`, has status `completed`, total `350.00`, cash received `400.00`, change `50.00`, and no void metadata. The fixture-only Sale ID 1 remains the sole non-completed `voided` control, retains its safe control values, has zero SaleItems and zero linked movements, and was not changed by this checkout.
- Immutable SaleItem evidence: SaleItem ID 1 belongs to Sale ID 2 and ProductVariant ID 1, with snapshots `FT15 Sample Material` / `FT15 Whole Variant` / `piece`, empty type/series and thickness snapshots, quantity `2.000`, unit price `125.00`, and line total `250.00`. SaleItem ID 2 belongs to the same Sale and ProductVariant ID 2, with snapshots `FT15 Sample Material` / `FT15 Fractional Variant` / `kg`, empty type/series and thickness snapshots, quantity `1.250`, unit price `80.00`, and line total `100.00`. The two line totals equal the persisted Sale total `350.00`.
- Immutable SALE movement evidence: StockMovement ID 9 links only to SaleItem ID 1 and ProductVariant ID 1, recording `18.000 + (-2.000) = 16.000`. StockMovement ID 10 links only to SaleItem ID 2 and ProductVariant ID 2, recording `8.750 + (-1.250) = 7.500`. Both were performed by Staff user ID 2 / `FT15 Staff`, belong through their SaleItems to completed Sale ID 2, and have null RestockItem and reason fields as required for `SALE` movements.
- Final stock evidence: ProductVariant ID 1 ended at `16.000`; ProductVariant ID 2 ended at `7.500`. Unrelated ProductVariants IDs 3–8 retained their expected stocks `1.000`, `0.000`, `0.000`, `0.000`, `0.000`, and `0.000` respectively.
- Duplicate/atomicity evidence: Relative to the verified pre-case baseline, exactly one completed Sale, exactly two SaleItems, and exactly two `SALE` movements were added. Both items belong to Sale ID 2; each item has exactly one distinct linked `SALE` movement; both movements belong through their items to that same completed Sale; no item lacks a movement; and no `SALE` movement lacks a completed Sale. The only movement IDs above the pre-case maximum ID 8 are IDs 9 and 10, both expected `SALE` movements. Total StockMovements are exactly `10`: `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. There is no duplicate completed checkout, partial transaction state, or unrelated inventory movement.
- Safe immutable identifiers: Sale ID 2 / `TRX-000002`; SaleItem IDs 1 and 2; StockMovement IDs 9 and 10.
- Evidence references: `BROWSER-FT15-POS-001` — the supplied genuine normal-browser execution report. `DB-FT15-POS-001-RECON` — guarded SELECT-only persisted-state reconciliation without selecting checkout tokens or private data.
- Final classification: **Pass**. The supplied exactly-once normal UI execution and the independent persisted transaction evidence agree. Tracker #15 is now **17 / 30 executed — 17 Passed, 0 Failed, 0 Blocked, 13 Remaining**.
- Issue/blocker: None.
- Notes: At the time `TC-POS-001` was finalized, no later formal case had been executed. No direct database mutation, repair, reprovisioning, migration, seed, reset, `--apply`, or cleanup occurred. `trackpro_local` and Test Hammer were not accessed.

## TC-SALES-001 — Sales History — status-neutral browse

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop built-in browser observation.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Roles: Staff (`FT15 Staff`) and Admin (`FT15 Admin`) synthetic accounts.
- Viewports: Admin `639 × 606`; Staff unavailable because it was not separately measured.
- Screenshot status: A genuine in-app browser screenshot was captured during the Admin date-filter check. No external screenshot identifier was generated.
- Result: **Pass**
- Formal expectation: With historical Sales of different statuses and recorders, both active roles can browse all Sales regardless of recorder; valid receipt, cashier, and date filters narrow the results; ordering is newest-first; and active filter/query state is preserved across pagination.
- Staff browser evidence: Sales History was accessible and displayed both existing Sales regardless of recorder. Filtering receipt `TRX-000002` left only `TRX-000002`. Selecting cashier `FT15 Controlled Disabled Cashier` left only `TRX-000001`.
- Admin browser evidence: Sales History was accessible and displayed both existing Sales regardless of recorder. Filtering receipt `TRX-000001` left only `TRX-000001`. Selecting cashier `FT15 Staff` left only `TRX-000002`.
- Mixed-status and ordering evidence: The unfiltered history rendered newest-first as `TRX-000002` — Completed — `FT15 Staff` — `Sep 11, 2026 1:54 AM`, followed by `TRX-000001` — Voided — `FT15 Controlled Disabled Cashier` — `Sep 8, 2026 12:00 PM`. This directly demonstrates status-neutral history and cross-recorder visibility for both roles.
- Date-filter evidence: Filtering `2026-09-11` left only `TRX-000002`; filtering `2026-09-08` left only `TRX-000001`. The rendered results and dates agreed with the `Asia/Manila` calendar dates.
- Known wording risk: The page displayed the eyebrow `COMPLETED TRANSACTIONS`, while its actual history remained status-neutral and visibly included the Voided fixture control. This is the already-documented UI wording risk and is not a history-semantics failure.
- Browser pagination limitation: Browser pagination was not directly exercised because the current FT15 dataset contained only two Sales and rendered no second page or pagination controls. No browser pagination behavior is claimed.
- Implementation evidence: `SalesHistoryController::index` builds a `Sale::query()` without a status predicate, so the committed query is status-neutral. It applies an exact canonical receipt-ID filter, a `recorded_by` cashier filter, and date boundaries created in `config('app.timezone')`, which is `Asia/Manila`; the end date uses an exclusive next-day boundary. Results are ordered by descending `created_at` and then descending `id`. The controller uses `paginate(20)->withQueryString()`, providing a 20-row server-side page size and preserving active query parameters in generated pagination links. Historical cashier options derive from distinct Sale recorders without excluding disabled users. These are source findings, not browser pagination observations.
- Supplemental automated evidence: Six narrowly relevant existing SQLite tests passed with **6 tests and 90 assertions**. `tests/Feature/Sales/SalesHistoryAuthorizationTest.php::test_active_admin_and_staff_can_view_all_sales` directly covers both-role access to another recorder's Sale. `tests/Feature/Sales/SalesHistoryTest.php::test_index_displays_authoritative_summary_and_deterministic_newest_first_order` covers deterministic newest-first ordering; `::test_receipt_search_is_exact_canonical_case_insensitive_and_fails_closed` covers receipt filtering; `::test_cashier_filter_is_narrow_and_includes_disabled_historical_cashiers` covers cashier narrowing and disabled historical cashiers; `::test_date_filters_use_inclusive_manila_calendar_days_and_exclusive_next_day` covers Manila calendar boundaries; and `::test_index_paginates_twenty_and_preserves_filters` creates 21 Sales, verifies page-two behavior, and directly asserts preservation of active cashier and `date_from` query parameters. No inspected automated test directly creates mixed completed/voided rows for the index; status-neutral runtime behavior is genuine browser evidence and is independently supported by the absence of a status predicate in the committed query.
- Guarded zero-mutation reconciliation: The guarded verifier and live `SELECT DATABASE()` proved MySQL 8/InnoDB, connection `mysql_testing`, and actual database `trackpro_test`. Sales remained exactly two: Sale ID 1 / `TRX-000001` remained `voided`, and Sale ID 2 / `TRX-000002` remained `completed`; no Sale was added. SaleItems remained exactly IDs 1 and 2, both belonging to completed Sale ID 2. ProductVariant stocks remained ID 1 `16.000`, ID 2 `7.500`, ID 3 `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`: `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No checkout token or secret field was selected.
- Mutation conclusion: The case was read-only. No Sale, SaleItem, stock balance, or StockMovement changed.
- Evidence references: `CDB-FT15-SALES-001` — supplied Codex Desktop browser observations for both roles, mixed-status ordering, filters, dates, wording, viewport, and screenshot. `AUTO-FT15-SALES-001` — the six focused existing SQLite tests listed above. `SRC-FT15-SALES-001` — committed controller/view inspection. `DB-FT15-SALES-001-RECON` — guarded SELECT-only zero-mutation reconciliation.
- Final classification: **Pass**. Browser-observable behavior matches the formal case, persisted state proves zero mutation, and the unrendered pagination requirement has direct existing automated coverage plus committed source support. Tracker #15 is now **18 / 30 executed — 18 Passed, 0 Failed, 0 Blocked, 12 Remaining**.
- Issue/blocker: None. The only manual-coverage limitation is that pagination was not directly exercised in the browser with the two-Sale FT15 dataset.
- Notes: At the time `TC-SALES-001` was finalized, `TC-SALES-002` and every later formal case remained unexecuted. No MySQL fixture was created to expose pagination, and no whole-suite test run was performed.

## TC-PRINT-001 — Receipt — browser reprint

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Initial Codex Desktop built-in browser observation followed by a genuine normal Mozilla Firefox manual continuation outside Codex Desktop.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account).
- Receipt: Sale ID 2 / `TRX-000002`.
- Viewport: Unavailable; no Firefox viewport dimensions were supplied.
- Result: **Pass**
- Formal expectation: Open an existing historical receipt, invoke browser Print, cancel or complete the preview, return and reopen the same receipt, and prove that printing/reprinting creates no new Sale, SaleItem, stock change, StockMovement, or AuditLog. Physical paper output is not required.
- Initial Codex Desktop limitation: `TRX-000002` opened successfully and `Print receipt` was clicked exactly once in Codex Desktop, but its native Print Preview surface was not observable. Codex did not retry. This unresolved surface observation did not establish native preview behavior and is not represented as doing so.
- Firefox receipt evidence before printing: In a genuine normal Mozilla Firefox session outside Codex Desktop, `TRX-000002` rendered with status Completed and cashier `FT15 Staff`. Its two lines showed Whole `2.000 × ₱125.00 = ₱250.00` and Fractional `1.250 × ₱80.00 = ₱100.00`; total was `₱350.00`, cash received `₱400.00`, and change `₱50.00`.
- Genuine native Print Preview evidence: `Print receipt` was invoked in Firefox and the native Firefox Print Preview appeared. The preview rendered the receipt as one sheet/page, displayed Firefox's native print controls, and showed destination `Save to PDF`. Physical printing was not required. This Firefox continuation belongs to the same formal `TC-PRINT-001` execution; no additional formal Print invocation or separate print case is claimed.
- Preview exit and return evidence: The exact Print Preview exit mechanism was not recorded. After leaving Print Preview, TrackPro returned normally to `/sales/2`, where the same `TRX-000002` receipt remained visible.
- Final manual reopen evidence: The user returned through Sales History, opened `TRX-000002` again, and the same historical receipt rendered normally. This is the supplied completed final manual reopen, not an inferred step.
- Screenshot status: Two genuine screenshots were supplied: one of native Firefox Print Preview and one of the returned TrackPro receipt page. No external screenshot identifier was generated for either screenshot. The Print Preview screenshot may be useful supporting evidence for future `TC-PRINT-002`, but it does not execute or finalize that separate case.
- Source evidence: The committed receipt view renders a `Print receipt` button wired directly to `onclick="window.print()"`; the receipt route is GET-only, and the receipt controller performs read-only selection of the Sale and its immutable SaleItems. This source evidence establishes the print wiring and read-only request surface, not the native Firefox preview observation.
- Supplemental automated evidence: `tests/Feature/Sales/SalesHistoryTest.php::test_receipt_uses_snapshots_preserves_privacy_prints_and_never_writes` passed in the ordinary isolated SQLite environment with **1 test and 51 assertions**. It directly checks repeated receipt rendering, immutable historical values, privacy exclusions, the `window.print()` control, print-hiding markup, and unchanged Sale, SaleItem, StockMovement, AuditLog, and stock values. Automation supplements read-only receipt behavior but does not substitute for the genuine Firefox preview.
- Pre-print persisted baseline: Sales `2` (`completed = 1`, `voided/non-completed = 1`); SaleItems `2`; StockMovements `10` (`INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, `SALE_VOID = 0`); Whole stock `16.000`; Fractional stock `7.500`; AuditLogs `0`. Sale ID 2 / `TRX-000002` was completed, recorded by `FT15 Staff`, retained total `350.00`, cash `400.00`, change `50.00`, and exactly two SaleItems. The baseline capture recorded `writes_performed=false`.
- Guarded post-print reconciliation: The guarded verifier and live `SELECT DATABASE()` proved MySQL 8/InnoDB, connection `mysql_testing`, and actual database `trackpro_test`. Post-print values exactly matched the baseline: Sales remained `2` (`completed = 1`, `voided = 1`); Sale ID 1 / `TRX-000001` remained the unchanged voided fixture control; Sale ID 2 / `TRX-000002` remained completed with cashier `FT15 Staff`, total `350.00`, cash `400.00`, and change `50.00`; SaleItems remained IDs 1 and 2, both attached to Sale ID 2; no Sale or SaleItem was added.
- Inventory/movement reconciliation: ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`; no stock or StockMovement changed.
- Audit reconciliation: AuditLogs remained exactly `0` before and after. Receipt viewing, Firefox Print Preview invocation, preview exit, normal return, and final reopen created no AuditLog.
- Privacy/safety: No checkout token or secret/private field was selected or recorded. All FT15 database checks were SELECT-only and reported `writes_performed=false`; `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-PRINT-001-LIMIT` — initial Codex Desktop print-surface limitation. `FF-FT15-PRINT-001` — supplied genuine Firefox receipt, native Print Preview, return, final reopen, and screenshots. `SRC-FT15-PRINT-001` — committed route/controller/view inspection. `AUTO-FT15-PRINT-001` — focused existing SQLite test. `DB-FT15-PRINT-001-RECON` — guarded pre/post zero-mutation comparison.
- Final classification: **Pass**. Genuine Firefox native Print Preview rendered the correct one-page receipt, TrackPro returned normally, the same receipt reopened through Sales History, and every persisted value matched the captured pre-print baseline. Tracker #15 is now **19 / 30 executed — 19 Passed, 0 Failed, 0 Blocked, 11 Remaining**.
- Issue/blocker: None.
- Notes: At the time `TC-PRINT-001` was finalized, `TC-PRINT-002` remained formally unexecuted. No later formal case was executed during that case, and no physical printout was required.

## TC-PRINT-002 — Receipt print — visual state

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: User-completed fresh manual execution in normal Mozilla Firefox.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account).
- Receipt: Sale ID 2 / `TRX-000002`.
- Viewport: Unavailable; no viewport dimensions were supplied.
- Result: **Pass**
- Formal expectation: In browser Print Preview, application sidebar, top bar, drawer, backdrop, alerts, and interactive controls are hidden from the printed sheet, while receipt metadata, items, status, and payment evidence remain readable.
- Fresh manual Firefox visual evidence: A genuine native Firefox Print Preview opened successfully for `TRX-000002`. The observation explicitly inspected the printed receipt sheet rather than treating Firefox's own native controls as TrackPro content. TrackPro's application sidebar/navigation and top/application chrome outside the receipt were absent. `Back to Sales History` and `Print receipt` were absent. The mobile drawer/backdrop did not contaminate the sheet, and alerts or other interactive application controls did not appear.
- Retained receipt evidence: The printed sheet retained readable 3A TrackPro receipt identity, reference `TRX-000002`, Completed status, sale date/time, and cashier `FT15 Staff`. It retained both item rows: Whole `2.000 × ₱125.00 = ₱250.00` and Fractional `1.250 × ₱80.00 = ₱100.00`, including their units. Payment evidence remained readable as total `₱350.00`, cash received `₱400.00`, and change `₱50.00`.
- Readability/layout result: The receipt content was readable without clipping, overlap, or material loss. Exact printed page count was not separately supplied and is recorded as unavailable rather than inferred from prior evidence.
- Screenshot/exit details: Fresh `TC-PRINT-002` screenshot status is unavailable because no new screenshot was explicitly supplied for this execution. The earlier `TC-PRINT-001` screenshot is not represented as a newly captured `TC-PRINT-002` screenshot. No screenshot identifier is claimed. The exact Print Preview exit action was not supplied and is recorded as not recorded.
- Source evidence: The committed shared application layout applies `print:hidden` to the desktop sidebar, mobile top bar, navigation backdrop, mobile drawer, success alert, and validation alert, and resets authenticated content padding with `print:pl-0`. The receipt view applies `print:hidden` to the Back/Print control container, wires `Print receipt` to `window.print()`, and uses print-specific receipt layout classes. It renders the receipt identity, status, date/time, cashier, immutable item snapshots, units, quantities, unit prices, line totals, and payment totals. These are committed-source findings, not manual Print Preview observations.
- Supplemental automated evidence: `tests/Feature/Sales/SalesHistoryTest.php::test_receipt_uses_snapshots_preserves_privacy_prints_and_never_writes` was inspected directly. It checks repeated receipt rendering, immutable receipt content, privacy exclusions, `window.print()` wiring, `print:hidden` markup, and unchanged Sale, SaleItem, StockMovement, AuditLog, and stock values. It was not rerun during this `TC-PRINT-002` finalization; its already-recorded focused run under the same commit during the immediately preceding print-case work passed with **1 test and 51 assertions**. This supplements content/non-write behavior and is not represented as native Firefox visual evidence.
- Guarded database identity: The FT15 verifier and live `SELECT DATABASE()` proved MySQL 8/InnoDB, connection `mysql_testing`, and actual database `trackpro_test` before the zero-mutation comparison.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control, and Sale ID 2 / `TRX-000002` remained `completed`, recorded by `FT15 Staff`, with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both belonging to Sale ID 2. No Sale or SaleItem was created.
- Stock/movement reconciliation: ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No stock mutation or StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`, proving no AuditLog was created by this receipt Print Preview observation.
- Privacy/safety: The reconciliation selected no checkout token or secret/private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `FF-FT15-PRINT-002` — supplied fresh normal-Firefox manual Print Preview observations. `SRC-FT15-PRINT-002` — committed shared-layout and receipt-view inspection. `AUTO-FT15-PRINT-002-SUPPORT` — inspected existing receipt/print test and its already-recorded focused result. `DB-FT15-PRINT-002-RECON` — guarded SELECT-only zero-mutation reconciliation.
- Final classification: **Pass**. The fresh Firefox Print Preview observation satisfied every formal visual-state expectation, retained readable receipt content without clipping or overlap, and the guarded persisted state remained unchanged. Tracker #15 is now **20 / 30 executed — 20 Passed, 0 Failed, 0 Blocked, 10 Remaining**.
- Issue/blocker: None.
- Notes: At the time `TC-PRINT-002` was finalized, `TC-SALES-002` and every other unexecuted formal case remained unexecuted. No other formal case was executed during that finalization.

## TC-DASH-001 — Dashboard — operational overview and follow-up

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop built-in browser observation.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Staff (`FT15 Staff` synthetic account).
- Dashboard date: `Friday, September 11, 2026` (`Asia/Manila`).
- Viewport: Exact `528 × 528`.
- Result: **Pass**
- Formal expectation: Open Dashboard with controlled completed/non-completed Sales and low/out-of-stock Variants; compare all four operational cards and both lists to the fixture; confirm Recent Sales is completed-only and limited to five; then follow one receipt link and the low-stock follow-up link. The case is read-only.
- Four-card browser evidence: The Dashboard displayed Today's Sales `₱350.00`, Transactions Today `1`, Low Stock `4`, and Out of Stock `3`, matching the current controlled FT15 state.
- Recent Completed Sales evidence: Exactly one row appeared: `TRX-000002`, `Sep 11, 2026 1:54 AM`, Completed, cashier `FT15 Staff`, `2` items, total `₱350.00`. The voided fixture control `TRX-000001` was absent as expected, directly confirming the list's completed-only behavior. With one qualifying Sale, the visible population was within the five-row maximum.
- Receipt follow-up: Selecting `View receipt` for `TRX-000002` opened `/sales/2`, where the expected `TRX-000002` receipt rendered.
- Low Stock list evidence: Exactly four rows appeared in this order: (1) `FT15 Controlled Out of Stock`, unit `piece`, `0.000 / 0.000`; (2) `FT15 Batch C Fractional Variant`, identity detail `BC-F1 · 2.5 mm`, unit `kg`, `0.000 / 1.375`; (3) `FT15 Batch C Whole Variant`, identity detail `BC-W1 · 1.0 mm`, unit `piece`, `0.000 / 4.000`; and (4) `FT15 Controlled Low Stock`, unit `piece`, `1.000 / 5.000`.
- Low-stock follow-up: Selecting `View Variants →` opened `/product-variants?low_stock=1`; the Product Variants page loaded with `Low stock only` checked.
- Screenshot status: A genuine in-app screenshot was captured of the low-stock Product Variants follow-up. No external screenshot identifier was generated.
- Browser mutation evidence: No form was submitted, no print or checkout occurred, and no catalog, inventory, or Sale mutation was performed. Concern/ambiguity: None.
- Source evidence: `DashboardController::index` calculates Today using the half-open `Asia/Manila` interval from start-of-day through, but excluding, the next start-of-day and counts only completed Sales. Low Stock means `current_stock <= low_stock_threshold`; Out of Stock means `current_stock = 0`; both require an active Category, Product, and ProductVariant. Recent Sales is completed-only, ordered by descending `created_at` then ID, and limited to five. Low Stock rows are limited to five and ordered with zero-stock rows first, followed by Category, Product, Variant identity, unit, and ID. The receipt action targets `/sales/{id}`; the low-stock follow-up targets `/product-variants?low_stock=1`. Staff and Admin receive the same TC-DASH-001 cards/lists; the separate Admin-only trend was not evaluated.
- Supplemental automated evidence: During the preflight, five narrowly relevant existing Dashboard tests passed with **5 tests and 35 assertions**: `test_today_uses_half_open_manila_boundaries_and_completed_sales_only`, `test_low_stock_and_out_of_stock_use_only_the_full_active_hierarchy`, `test_recent_completed_sales_are_limited_and_deterministically_ordered`, `test_dashboard_gets_are_read_only`, and `test_active_admin_and_staff_can_view_dashboard_with_role_appropriate_content`. They were not rerun during this finalization. Automated evidence supplements the supplied browser observation and is not represented as browser evidence.
- Guarded database identity: The FT15 verifier and live `SELECT DATABASE()` proved MySQL 8/InnoDB, connection `mysql_testing`, and actual database `trackpro_test` before the zero-mutation comparison.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control, and Sale ID 2 / `TRX-000002` remained `completed`, recorded by `FT15 Staff`, with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both belonging to Sale ID 2. No Sale or SaleItem was created.
- Stock/movement reconciliation: ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No stock mutation or StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`, proving Dashboard viewing and both read-only follow-up navigations created no AuditLog.
- Privacy/safety: The reconciliation selected no checkout token, password/hash, secret, or unrelated private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-DASH-001` — supplied browser cards, lists, follow-up navigation, viewport, and screenshot observation. `SRC-FT15-DASH-001` — committed Dashboard controller/view and destination inspection. `AUTO-FT15-DASH-001` — five focused preflight Dashboard tests. `DB-FT15-DASH-001-RECON` — guarded SELECT-only zero-mutation comparison.
- Final classification: **Pass**. The supplied browser behavior matched every TC-DASH-001 expectation, both follow-up links reached their intended pages, and persisted state remained unchanged. Tracker #15 is now **21 / 30 executed — 21 Passed, 0 Failed, 0 Blocked, 9 Remaining**.
- Issue/blocker: None.
- Notes: At the time `TC-DASH-001` was finalized, `TC-DASH-002`, `TC-DASH-003`, and every other unexecuted formal case remained unexecuted. No Admin trend or responsive-layout requirement was evaluated as part of that case, and no other formal case was executed during its finalization.

## TC-DASH-002 — Dashboard — role-specific trend

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop built-in browser observation.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Roles: Admin (`FT15 Admin`) and Staff (`FT15 Staff`) synthetic accounts.
- Dashboard date: `Friday, September 11, 2026` (`Asia/Manila`).
- Viewports: Admin exact `1280 × 720`; Staff exact `1280 × 720`.
- Result: **Pass**
- Formal expectation: Open Dashboard as Admin and then Staff using seven Manila calendar days that include empty days and completed/non-completed Sales. Admin sees seven oldest-to-newest, zero-filled completed-sales days; Staff does not see the trend. The case is read-only.
- Admin trend presence: The Admin Dashboard rendered the heading `Seven-day Completed Sales Trend`.
- Admin trend rows: Exactly seven rows rendered oldest-to-newest: (1) `Sep 5` — `₱0.00 · 0 sales`; (2) `Sep 6` — `₱0.00 · 0 sales`; (3) `Sep 7` — `₱0.00 · 0 sales`; (4) `Sep 8` — `₱0.00 · 0 sales`; (5) `Sep 9` — `₱0.00 · 0 sales`; (6) `Sep 10` — `₱0.00 · 0 sales`; and (7) `Sep 11` — `₱350.00 · 1 sale`.
- Admin trend semantics: All empty dates were explicitly zero-filled, and zero-total days displayed no visibly filled amount bar. `Sep 8` remained zero despite historical `TRX-000001` because that Sale is voided. `Sep 11` was the non-zero maximum bar. Singular/plural wording rendered correctly as `0 sales` and `1 sale`.
- Staff trend absence: The Staff Dashboard did not render `Seven-day Completed Sales Trend`; the trend was absent as required.
- Screenshot status: Two genuine browser screenshots were captured: the Admin trend and the Staff trend absence. No external screenshot identifier was generated for either screenshot.
- Browser mutation evidence: Only normal login/logout occurred. No Sale creation/change, checkout, catalog or inventory mutation, Stock In, Stock Correction, Opening Inventory, print, report execution, or other formal case occurred. Concern/ambiguity: None.
- Source evidence: `DashboardController::index` sets `sevenDayTrend` only when the authenticated user is Admin; Staff receives `null`, and the view renders the trend only when the value is non-null. The trend uses the current `Asia/Manila` day and previous six days, filters to completed Sales in a half-open seven-day interval, groups by date, constructs all seven dates oldest-to-newest, fills missing dates with `0.00` and zero transactions, and calculates bars relative to the maximum completed-sales total. The view uses singular/plural sale wording. These source findings are separate from the genuine rendered browser evidence.
- Supplemental automated evidence: The established TC-DASH-001 preflight ran five focused Dashboard tests with **5 tests and 35 assertions**, including `tests/Feature/Dashboard/DashboardTest.php::test_admin_trend_contains_all_seven_days_and_excludes_non_completed_sales` and `tests/Feature/Dashboard/DashboardAuthorizationTest.php::test_active_admin_and_staff_can_view_dashboard_with_role_appropriate_content`. Those methods directly supplement seven-day ordering/zero-fill/completed-only behavior and Admin-present/Staff-absent rendering. They were inspected but not rerun during this finalization; automation is not represented as browser evidence.
- Guarded database identity: The FT15 verifier and live `SELECT DATABASE()` proved MySQL 8/InnoDB, connection `mysql_testing`, and actual database `trackpro_test` before the zero-mutation comparison.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control, and Sale ID 2 / `TRX-000002` remained `completed`, recorded by `FT15 Staff`, with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both belonging to Sale ID 2. No Sale or SaleItem was created or changed.
- Stock/movement reconciliation: ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No stock mutation or StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`, proving Dashboard viewing and normal login/logout created no AuditLog or other recorded application-domain mutation.
- Privacy/safety: The reconciliation selected no checkout token, password/hash, secret, or unrelated private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-DASH-002` — supplied Admin/Staff browser trend observations, viewports, and screenshots. `SRC-FT15-DASH-002` — committed Dashboard trend implementation/view inspection. `AUTO-FT15-DASH-002-SUPPORT` — relevant focused preflight tests and inspected methods. `DB-FT15-DASH-002-RECON` — guarded SELECT-only zero-mutation comparison.
- Final classification: **Pass**. The Admin rendered exactly seven oldest-to-newest zero-filled completed-sales days, the Staff trend was absent, and persisted application-domain state remained unchanged. Tracker #15 is now **22 / 30 executed — 22 Passed, 0 Failed, 0 Blocked, 8 Remaining**.
- Issue/blocker: None.
- Notes: `TC-DASH-003` and every other unexecuted formal case remain unexecuted. No responsive-layout requirement was evaluated as part of this case, and no other formal case was executed.

## TC-DASH-003 — Dashboard UI — responsive grids

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop in-app browser observation.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account) at both widths. Staff role-specific behavior was not repeated because `TC-DASH-002` separately covered Staff trend visibility.
- Viewports: Exact mobile `528 × 720`; exact desktop `1280 × 720`.
- Result: **Pass**
- Formal expectation: Inspect Dashboard cards, lower panels, tables, and the Admin trend at mobile and desktop widths. Cards and panels reflow as implemented without navigation/content obstruction, and wide tables retain responsive horizontal-overflow handling where their content requires it.
- Mobile navigation/content evidence: The mobile header remained separate from the Dashboard content, with no obstruction or page-level horizontal overflow. The heading and content remained readable.
- Mobile card evidence: All four operational cards stacked vertically in one column. Every label and value remained visible without clipping or overlap.
- Mobile Admin trend evidence: The seven-day trend remained present. All seven rows were readable, and their labels, bars, totals, and sale counts remained unobstructed without overlap or clipping.
- Mobile lower-panel evidence: Recent Completed Sales and Low Stock Items stacked vertically. Their headings and actions remained visible, and neither panel competed with or obstructed the other.
- Mobile table evidence: Both tables remained contained and readable inside their panels. Each rendered at approximately `494px` content width within an approximately `494px` container, so actual horizontal scroll travel was **not required and was not manually performed**. Both table wrappers retained responsive horizontal-overflow handling for content that would require it.
- Desktop navigation/content evidence: The fixed `240px` sidebar did not overlap the Dashboard. Main content began at `x=240`, and the page remained fully usable.
- Desktop card evidence: All four summary cards rendered unobstructed in one row, providing the implemented four-column desktop presentation.
- Desktop Admin trend evidence: The seven-day trend remained present and readable. Labels, bars, totals, and sale counts did not overlap.
- Desktop lower-panel and table evidence: Recent Completed Sales and Low Stock Items rendered side-by-side. Both tables remained contained and usable, and no inaccessible content was found.
- Overall responsive evidence: No material clipping, overlap, content obstruction, or page-level horizontal overflow was observed at either viewport. No receipt or Variant follow-up link was opened.
- Screenshot status: Two genuine in-app screenshots were captured, one mobile and one desktop, with full-page views included. No external screenshot identifier was generated.
- Browser mutation evidence: No link was followed, no form was submitted, no checkout or printing occurred, and no Reports, catalog, inventory, Sale, Stock In, Stock Correction, or Opening Inventory action was performed. Concern/ambiguity: None.
- Source evidence: The committed Dashboard view uses `grid sm:grid-cols-2 xl:grid-cols-4` for summary cards and `grid xl:grid-cols-2` for the lower panels. Both table wrappers use `overflow-x-auto`. Trend rows use a base stacked grid and `sm:grid-cols-[5rem_minmax(0,1fr)_11rem] sm:items-center`. The committed application layout uses a fixed `w-60` desktop sidebar with `lg:pl-60` on the main content, while the mobile header is rendered separately below the desktop breakpoint. This source evidence confirms the intended responsive and overflow classes; it is separate from the manual rendering evidence and is not represented as manual table scrolling.
- Supplemental automated evidence: No new test and no whole-suite run were required or performed. The previously established focused Dashboard automation remains supplemental only; the actual `528 × 720` and `1280 × 720` rendering evidence came from the supplied genuine browser execution.
- Guarded database identity: `scripts/provision-ft15-fixtures --verify --run-id=FT15-20260909-A` confirmed connection `mysql_testing`, live database `trackpro_test`, MySQL/InnoDB identity, and `writes_performed=false`. A separate guarded query also required live `SELECT DATABASE()` to equal `trackpro_test` before the narrow baseline SELECTs ran.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control with total/cash/change `10.00 / 10.00 / 0.00`, and Sale ID 2 / `TRX-000002` remained the unchanged `completed` Sale recorded by `FT15 Staff`, with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both attached to Sale ID 2, with their established quantities, prices, and totals. No Sale or SaleItem was created or changed.
- Stock/movement reconciliation: ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No stock mutation or StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`; responsive Dashboard observation created no AuditLog.
- Privacy/safety: The reconciliation selected no checkout token, password/hash, secret, or unrelated private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Reconciliation execution note: The first narrow SELECT-only comparison stopped on an invalid `sale_items.updated_at` projection and produced no complete baseline result or writes. The projection was corrected from the committed migration, and the complete intended SELECT-only comparison was rerun successfully.
- Evidence references: `CDB-FT15-DASH-003` — supplied genuine mobile/desktop browser rendering and screenshots. `SRC-FT15-DASH-003` — committed Dashboard and shared-layout responsive-class inspection. `DB-FT15-DASH-003-RECON` — guarded identity verification and corrected complete SELECT-only zero-mutation comparison.
- Final classification: **Pass**. The supplied browser execution satisfies every responsive presentation expectation, committed responsive classes are consistent with the observation, and persisted application-domain state exactly matches the established FT15 baseline. Tracker #15 is now **23 / 30 executed — 23 Passed, 0 Failed, 0 Blocked, 7 Remaining**.
- Issue/blocker: None.
- Notes: `TC-DASH-001` remains the separate functional cards/lists/follow-up case, and `TC-DASH-002` remains the separate Admin/Staff trend-visibility case. `TC-DASH-003` covers responsive presentation only. `TC-REP-001` and every later formal case remain unexecuted; no other formal case was executed during this finalization.

## TC-REP-001 — Reports — valid range/cashier filter

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop in-app browser observation.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account).
- Viewport: Exact `593 × 528`. This viewport was incidental to the functional execution; no deliberate responsive resizing or `TC-REP-004` responsive assessment occurred.
- Result: **Pass**
- Formal expectation: Open Reports, inspect the default seven-Manila-day report, apply valid date-range and cashier filters, and Reset. Completed-only totals, zero-filled days, and immutable-unit quantity groups must be exact; browser-visible private, cost, profit, and export data must be absent. The workflow is read-only.
- Default filter evidence: Date from was `2026-09-05`, date to was `2026-09-11`, and Cashier was `All cashiers`.
- Default summary evidence: Completed Sales Total was exactly `₱350.00`, and Completed Transactions was exactly `1`.
- Default daily evidence: Exactly seven rows rendered newest-first: (1) `Sep 11, 2026` — `1` — `₱350.00`; (2) `Sep 10, 2026` — `0` — `₱0.00`; (3) `Sep 9, 2026` — `0` — `₱0.00`; (4) `Sep 8, 2026` — `0` — `₱0.00`; (5) `Sep 7, 2026` — `0` — `₱0.00`; (6) `Sep 6, 2026` — `0` — `₱0.00`; and (7) `Sep 5, 2026` — `0` — `₱0.00`. Sep 8 remained zero despite the historical voided Sale.
- Default quantity-by-unit evidence: Exactly two rows rendered in order: `piece` — `2.000`, then `kg` — `1.250`. These are the immutable SaleItem unit snapshots.
- Cashier-option evidence: Options rendered in this exact order: `All cashiers`, `FT15 Controlled Disabled Cashier`, and `FT15 Staff`. `FT15 Admin` was absent because no historical Sale references that user. The disabled historical cashier remained available as required.
- Date-range filter evidence: With `2026-09-08` through `2026-09-11` and `All cashiers`, the summary remained exactly `₱350.00` and `1`. Four newest-first rows rendered: Sep 11 — `1` — `₱350.00`; Sep 10 — `0` — `₱0.00`; Sep 9 — `0` — `₱0.00`; and Sep 8 — `0` — `₱0.00`. Unit rows remained `piece` — `2.000` and `kg` — `1.250`. The in-range voided Sale did not contribute.
- Active-cashier filter evidence: With the same date range and `FT15 Staff`, the summary was `₱350.00` and `1`; daily and unit rows matched the date-range result. The filter did not visibly change totals because FT15 Staff recorded the sole completed Sale.
- Disabled-cashier filter evidence: With the same date range and `FT15 Controlled Disabled Cashier`, the summary was `₱0.00` and `0`. All four daily rows were zero, and Quantity Sold by Unit displayed the exact empty state `No completed quantities in this report.` This supplied the clearest browser-visible cashier narrowing evidence without treating the historical voided Sale as completed.
- Reset evidence: Reset returned to `http://127.0.0.1:8015/reports` with no query parameters. The default `2026-09-05` through `2026-09-11`, All cashiers, `₱350.00`, one completed transaction, seven default daily rows, and the `piece 2.000` / `kg 1.250` unit rows all returned.
- Browser-visible privacy/scope evidence: No report content exposed checkout tokens, login credentials, password/hash data, remember-token data, database secrets, purchase/catalog costs, COGS, profit, margin, StockMovement internals, or export, print, or download controls. This is browser-visible acceptance evidence only and is not represented as a broader security certification.
- Screenshot status: Two genuine full-page in-app screenshots were captured: the default report and the disabled-cashier zero-result report. No external screenshot identifier was generated.
- Browser mutation evidence: Only Reports navigation, read-only GET filter applications, and Reset occurred. No checkout, Sale, catalog, inventory, Stock In, Stock Correction, Opening Inventory, print, export, download, or other formal-case action occurred. Concern/ambiguity: None.
- Source evidence: The committed Admin-protected GET Reports route computes the default from the current `Asia/Manila` day through the preceding six days. Explicit inclusive dates use half-open query boundaries from the selected start-of-day through, but excluding, the day after the selected end date. Aggregates filter to completed Sales only; daily rows are zero-filled newest-first; cashier filtering uses the Sale recorder; selector options include users referenced by any historical Sale regardless of current user or Sale status; quantities group by immutable `sale_items.unit_snapshot`; and Reset links to the unfiltered Reports route. The view exposes only the rendered aggregate/report fields and has no report store, print, or export route.
- Supplemental automated evidence: During the TC-REP-001 preflight, seven directly relevant existing Reports tests passed with **7 tests and 57 assertions**. Coverage included the default seven Manila days, half-open boundaries, completed-only aggregation, valid cashier filtering, disabled historical cashier availability, immutable-unit grouping, read-only/privacy behavior, Admin authorization, and GET-only constraints. They were not rerun during finalization. Automated evidence remains separate from the supplied browser evidence.
- Guarded database identity: `scripts/provision-ft15-fixtures --verify --run-id=FT15-20260909-A` confirmed connection `mysql_testing`, live database `trackpro_test`, MySQL/InnoDB identity, and `writes_performed=false`. A separate guarded query required live `SELECT DATABASE()` to equal `trackpro_test` before the narrow baseline SELECTs ran.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control with total/cash/change `10.00 / 10.00 / 0.00`, and Sale ID 2 / `TRX-000002` remained the unchanged `completed` Sale recorded by FT15 Staff with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both attached to Sale ID 2, with immutable units `piece` and `kg` and their established quantities, prices, and totals. No Sale or SaleItem was created or changed.
- Stock/movement reconciliation: ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No stock change or StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`, proving the Reports GET/filter/Reset actions created no AuditLog.
- Privacy/safety: The reconciliation selected no checkout token, password/hash, remember token, credential, secret, or unrelated private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-REP-001` — supplied genuine functional Reports browser observations and screenshots. `SRC-FT15-REP-001` — committed Reports controller, route, and view inspection. `AUTO-FT15-REP-001` — seven focused preflight Reports tests. `DB-FT15-REP-001-RECON` — guarded identity and SELECT-only zero-mutation comparison.
- Final classification: **Pass**. Default, date-range, active-cashier, disabled-historical-cashier, immutable-unit, Reset, privacy/scope, and read-only behavior matched the formal case, and persisted application-domain state exactly matched the established baseline. Tracker #15 is now **24 / 30 executed — 24 Passed, 0 Failed, 0 Blocked, 6 Remaining**.
- Issue/blocker: None.
- Notes: The `593 × 528` viewport was incidental and supplies no `TC-REP-004` responsive evidence. `TC-REP-004` and every later formal case remain unexecuted; no other formal case was executed during this finalization.

## TC-REP-004 — Reports UI — responsive presentation

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop in-app browser observation.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Role: Admin (`FT15 Admin` synthetic account).
- Viewports: Exact mobile `528 × 720`; exact desktop `1280 × 720`.
- Representative report state: Date range `2026-09-08` through `2026-09-11`; `All cashiers`; Completed Sales Total `₱350.00`; Completed Transactions `1`.
- Result: **Pass**
- Formal expectation: Apply valid filters and inspect responsive filters, summary cards, Daily Sales, and Quantity Sold by Unit at mobile and desktop widths. Filters and cards reflow, tables remain usable with horizontal scrolling where needed, and exact values remain visible.
- Mobile navigation/content evidence: The mobile header did not cover Reports content. No navigation/content obstruction or page-level horizontal overflow was observed.
- Mobile filter evidence: Date from, Date to, and Cashier stacked naturally. Apply filters and Reset remained visible, unobstructed, and usable, with no control overlap.
- Mobile summary evidence: The two summary cards stacked vertically. `₱350.00` and `1` remained clearly readable.
- Mobile Daily Sales evidence: All three columns and all four rows remained visible and usable: Sep 11 — `1` — `₱350.00`; Sep 10 — `0` — `₱0.00`; Sep 9 — `0` — `₱0.00`; and Sep 8 — `0` — `₱0.00`. No content was inaccessible.
- Mobile quantity evidence: Quantity Sold by Unit remained usable, with `piece` — `2.000` and `kg` — `1.250` readable and accessible.
- Desktop navigation/content evidence: The fixed `240px` sidebar did not overlap Reports content. Main content began at `x=240` and remained usable.
- Desktop filter evidence: Date from, Date to, Cashier, Apply filters, and Reset rendered unobstructed in one multi-column row.
- Desktop summary evidence: Both summary cards rendered side-by-side, with `₱350.00` and `1` clearly visible.
- Desktop panel evidence: Daily Sales and Quantity Sold by Unit rendered side-by-side without obstructing one another. All Daily Sales rows and columns remained readable, and `piece 2.000` and `kg 1.250` remained accessible.
- Table-usability evidence: At both tested widths, the rendered Daily Sales and Quantity Sold by Unit content fitted the available containers. Actual horizontal scroll travel was **not needed and was not performed**. No inaccessible table content was found, and the absence of necessary scroll travel is not treated as a defect.
- Overall responsive evidence: No material clipping, overlap, navigation/content obstruction, inaccessible content, or unintended page-level horizontal overflow was observed at either width. Every representative filtered value remained visible.
- Screenshot status: Two genuine full-page in-app screenshots were captured at exact `528 × 720` and `1280 × 720`. No external screenshot identifier was generated. The temporary viewport override was reset afterward.
- Browser mutation evidence: Only authorized GET Reports navigation/filter behavior and responsive viewport changes occurred. No Sale, checkout, catalog, inventory, Stock In, Stock Correction, Opening Inventory, print, export, download, or later formal-case action occurred. Concern/ambiguity: None.
- Source evidence: The committed Reports filter form uses `grid lg:grid-cols-4`; the summary uses `grid sm:grid-cols-2`; and the main panels use `grid xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]`. The Daily Sales table alone has an `overflow-x-auto` wrapper. The Quantity Sold by Unit table has `min-w-full` inside an `overflow-hidden` panel but no `overflow-x-auto` wrapper; none is invented here. The shared layout pairs the fixed `w-60` desktop sidebar with `lg:pl-60` content offset and uses a separate mobile header. These committed classes supplement, but do not replace, the genuine rendered browser observations and do not prove manual scrolling.
- Supplemental automated evidence: No focused test or whole-suite rerun was required or performed for this manual responsive case. `TC-REP-001` retains its separate functional/source/automated evidence; those calculation and filter assertions are not reclassified as `TC-REP-004` responsive evidence.
- Guarded database identity: `scripts/provision-ft15-fixtures --verify --run-id=FT15-20260909-A` confirmed connection `mysql_testing`, live database `trackpro_test`, MySQL/InnoDB identity, and `writes_performed=false`. A separate guarded query required live `SELECT DATABASE()` to equal `trackpro_test` before the narrow baseline SELECTs ran.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control with total/cash/change `10.00 / 10.00 / 0.00`, and Sale ID 2 / `TRX-000002` remained the unchanged `completed` Sale recorded by FT15 Staff with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both attached to Sale ID 2, with their established immutable units, quantities, prices, and totals. No Sale or SaleItem was created or changed.
- Stock/movement reconciliation: ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 controlled low-stock `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No stock mutation or StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`, proving the Reports GET/filter/viewport activity created no AuditLog.
- Privacy/safety: The reconciliation selected no checkout token, password/hash, remember token, credential, secret, or unrelated private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-REP-004` — supplied genuine responsive Reports browser observations and screenshots. `SRC-FT15-REP-004` — committed Reports and shared-layout responsive-class inspection. `DB-FT15-REP-004-RECON` — guarded identity and SELECT-only zero-mutation comparison.
- Final classification: **Pass**. Filters, cards, panels, and both report tables remained usable at the exact mobile and desktop widths, every representative value stayed visible, and persisted application-domain state exactly matched the established FT15 baseline. Tracker #15 is now **25 / 30 executed — 25 Passed, 0 Failed, 0 Blocked, 5 Remaining**.
- Issue/blocker: None.
- Notes: `TC-REP-001` remains the separate functional calculations/filter-semantics case. `TC-REP-004` covers responsive presentation only. `TC-NAV-003` and every later formal case remain unexecuted; no navigation interaction or other formal case was executed during this finalization.

## TC-NAV-003 — Navigation — drawer interaction and breakpoint

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop in-app browser observation with secure user credential handoff.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Roles: Admin (`FT15 Admin`) and Staff (`FT15 Staff`) synthetic accounts.
- Viewports: Exact mobile-side breakpoint `1023 × 720`; exact desktop-side breakpoint `1024 × 720`.
- Result: **Pass**
- Formal expectation: At 1023px, open the mobile drawer and close it by X, backdrop, Escape, and navigation link while inspecting body, inert, ARIA, and focus state; resize an open drawer to 1024px and verify that the desktop sidebar/content offset replace the mobile surfaces without stale state. No complete dialog-semantics or focus-trap claim is required.
- Admin 1023px initial state: The mobile header and hamburger were visible; the desktop sidebar was hidden. The hamburger exposed `aria-expanded=false` and `aria-label="Open navigation"`; the drawer exposed `aria-hidden=true` and was inert; the backdrop was inactive; the body was not overflow-locked; and Dashboard/application content remained usable and non-inert.
- Admin open state: Activating the hamburger changed it to `aria-expanded=true` and `aria-label="Close navigation"`. The drawer became visible, `aria-hidden=false`, and non-inert; the backdrop became active; body overflow locked; the mobile header and application-content wrapper became inert; and focus landed on the drawer's Close navigation button.
- Admin X close: The drawer and backdrop closed, ARIA/inert state reset, body overflow unlocked, content became usable, and focus returned to the hamburger.
- Admin backdrop close: The same complete reset occurred, including focus return to the hamburger.
- Admin Escape close: Escape produced the same complete reset, including focus return to the hamburger.
- Admin navigation-link close: Selecting Sales History reached `/sales`; the destination rendered with the mobile drawer normally closed and inert, no stale backdrop, and no stale body lock. Focus return to the prior page was not required because navigation occurred.
- Admin 1023px-to-1024px cleanup: With the drawer open at 1023px, resizing to 1024px fully cleared the open mobile state. The mobile header, hamburger, drawer, and backdrop were no longer presented; the desktop sidebar appeared; body overflow lock cleared; application content became non-inert; and no stale obstruction or sidebar/content overlap remained. The sidebar began at `x=0` with approximately `240px` width, and main content began at `x=240`.
- Staff 1023px initial state: The mobile header and hamburger were visible; the desktop sidebar was hidden; the drawer was closed and inert; the body was unlocked; and Dashboard/application content was usable.
- Staff open state: The hamburger changed to expanded with the Close navigation label; the drawer became visible and non-inert; the backdrop activated; body overflow locked; the mobile header and application content became inert; and focus moved to the Close navigation button.
- Staff close mechanisms: X, backdrop, and Escape each completely reset drawer/backdrop/ARIA/inert/body state and returned focus to the hamburger. Selecting Sales History reached `/sales` with no stale open drawer, backdrop, or body lock; no old-page focus return was required after navigation.
- Staff 1023px-to-1024px cleanup: Resizing an open drawer from 1023px to 1024px removed the mobile header/drawer/backdrop, displayed the desktop sidebar, cleared body lock, restored non-inert application content, and left no stale obstruction or sidebar/content overlap. The sidebar was approximately `240px` wide, and main content began at approximately `x=240`.
- Focus/inert/body-lock evidence: Both roles demonstrated observable focus entry into the drawer; focus return for X, backdrop, and Escape; background/mobile-header inert activation and reset; body scroll-lock activation and reset; Escape closure; navigation-link closure/navigation; and open-state cleanup on the exact breakpoint transition.
- Accessibility-scope limitation: This case proves only the observable ARIA-label/expanded/hidden changes, inert changes, body-lock behavior, focus entry/ordinary-close return, Escape behavior, navigation-link handling, breakpoint cleanup, and mobile/desktop replacement described above. It does **not** claim complete focus trapping, dialog semantics, `aria-modal` semantics, full WCAG compliance, or assistive-technology certification.
- Screenshot status: Two genuine in-app screenshots were captured: the Admin 1023px drawer-open state and the Admin 1024px desktop-sidebar state. No external screenshot identifier was generated. The temporary viewport override was reset afterward.
- Browser mutation evidence: Navigation, normal authentication/session behavior, and viewport changes created no application-domain mutation. No checkout, Sale, catalog, inventory, Stock In, Stock Correction, Opening Inventory, print, report action, or other formal case occurred. Concern/ambiguity: None.
- Source evidence: The committed layout uses `lg:hidden` for mobile header/drawer/backdrop, `hidden ... lg:flex` for the fixed `w-60` desktop sidebar, and `lg:pl-60` for the authenticated content offset. The navigation script defines desktop as `(min-width: 64rem)`, opens by updating translate/ARIA/inert/backdrop/body state and focusing Close navigation, and centralizes cleanup through `closeDrawer`. X and backdrop call normal close; Escape closes only while expanded; mobile links close without old-page focus return; and the media-query change closes without focus return when entering desktop. This source evidence supports but does not replace the genuine browser interaction evidence.
- Supplemental automated evidence: Existing `ResponsiveNavigationTest` coverage confirms initial drawer/hamburger markup, ARIA attributes, inert markup, print-hidden surfaces, the `lg:pl-60` offset, secure logout forms, and role-specific desktop/mobile navigation structure. It was inspected but not rerun. Destination counts remain the separate `TC-NAV-001` and `TC-NAV-002` concern and are not re-executed or claimed here.
- Guarded database identity: `scripts/provision-ft15-fixtures --verify --run-id=FT15-20260909-A` confirmed connection `mysql_testing`, live database `trackpro_test`, MySQL/InnoDB identity, and `writes_performed=false`. A separate guarded query required live `SELECT DATABASE()` to equal `trackpro_test` before the narrow baseline SELECTs ran.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control with total/cash/change `10.00 / 10.00 / 0.00`, and Sale ID 2 / `TRX-000002` remained the unchanged `completed` Sale recorded by FT15 Staff with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both attached to Sale ID 2, with their established immutable units, quantities, prices, and totals. No Sale or SaleItem was created or changed.
- Stock/movement reconciliation: ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 controlled low-stock `1.000`, and IDs 4–8 `0.000`. StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No stock mutation or StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`, proving navigation/login/logout/viewport activity created no AuditLog. Normal session/authentication storage is not application-domain mutation.
- Privacy/safety: The reconciliation selected no password/hash, remember token, checkout token, credential, secret, or unrelated private field and recorded `writes_performed=false`. No rejected credential value was recorded. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-NAV-003` — supplied genuine Admin/Staff drawer, focus, inert/body, close-mechanism, breakpoint, layout, and screenshot observations. `SRC-FT15-NAV-003` — committed shared layout, navigation component, and JavaScript state-machine inspection. `AUTO-FT15-NAV-003-SUPPORT` — inspected existing navigation markup/role tests. `DB-FT15-NAV-003-RECON` — guarded identity and SELECT-only zero-mutation comparison.
- Final classification: **Pass**. Both roles demonstrated every specified below-`lg` drawer interaction and exact 1023px-to-1024px cleanup, the desktop sidebar/content offset replaced mobile state without obstruction, and persisted application-domain state exactly matched the established FT15 baseline. Tracker #15 is now **26 / 30 executed — 26 Passed, 0 Failed, 0 Blocked, 4 Remaining**.
- Issue/blocker: None.
- Notes: The incidental rejected Staff login attempt before secure handoff was non-domain, non-mutating, and neither a formal defect nor a blocker; no credential value is recorded. `TC-UI-001` and every later formal case remain unexecuted, and no full focus-trap, dialog, WCAG, assistive-technology, destination-count, or other formal-case claim is made.

## TC-UI-001 — Shared UI — tables, feedback, and empty states

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop in-app browser observation with secure user credential handoff.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Roles: Admin (`FT15 Admin`) and Staff (`FT15 Staff`) synthetic accounts.
- Viewport: Exact narrow viewport `528 × 720`.
- Result: **Pass**
- Formal expectation: Visit representative catalog, inventory, and Sales pages at narrow width and exercise non-destructive empty, success, warning, validation, and genuinely wide-table states. Wide tables remain accessible through horizontal scrolling, messages remain visible with text, and contextual empty states render according to current conventions rather than a future standardized design.
- Admin catalog warning evidence: At `/product-variants/1/edit`, the amber attention message `Identity, unit, and quantity mode are locked because this variant has inventory or activity.` was fully visible and readable. The form remained usable without page-level horizontal overflow. Size, Type / series, and Thickness were read-only; Unit and Quantity mode were disabled. No form was submitted.
- Opening Inventory table evidence: At `/opening-inventory`, Admin observed usable narrow Search/filter controls and the eight-column table contained by its responsive overflow wrapper. The container measured `clientWidth = 463px` and `scrollWidth = 692px`, proving genuine horizontal overflow. Real horizontal travel reached `scrollLeft = 217px` against a `229px` maximum; this is not claimed as reaching the mathematical maximum. The movement exposed right-side stock, opening-status, and action content while the table remained usable.
- Staff Sales History table evidence: At `/sales`, the heading remained usable, filters stacked, and the populated nine-column table retained both existing Sales. Its container measured `clientWidth = 479px` and `scrollWidth = 718px`. Real horizontal travel reached the exact `239px` maximum. At maximum right scroll, `TRX-000002` exposed Cash received `₱400.00`, Change `₱50.00`, and Action `View receipt`. The receipt link was not activated.
- Empty-state evidence: As Staff, the valid canonical but nonexistent GET receipt filter `TRX-999999` retained the Sales table header/shell and rendered the exact contextual empty text `No Sales found.` The text remained readable at 528px and no domain mutation occurred.
- Validation evidence: The GET receipt filter `INVALID` rendered the exact inline red message `Enter a receipt number such as TRX-000002.` The filter failed closed, retained the table shell, and rendered `No Sales found.` No shared global validation summary is implemented or required on this Sales History page. Programmatic field-error association was not assessed.
- Success-evidence provenance: A fresh success flash was deliberately **not produced and was not freshly observed during TC-UI-001**, because doing so would require an application-domain mutation. This case reuses the previously finalized genuine TC-CAT-001 manual browser observations `Category created.` and `Category updated.` as retained success-feedback evidence. The committed shared layout's emerald success-alert markup is separate source support only; source is not represented as a fresh browser observation.
- Current-convention evidence: Wide tables remained tables rather than becoming mobile cards. Inline validation appeared without a shared global summary. No future-standardization requirement was imposed. No programmatic error-association or standardized-focus-system assessment was performed. The Sales History eyebrow `Completed transactions` remains a known wording risk separate from this responsive/UI result.
- Screenshot status: Five genuine in-app screenshots were captured: the Admin amber Variant warning, horizontally travelled Opening Inventory table, Sales History rightmost content, Sales empty state, and Sales validation state. No external screenshot identifier was generated. The temporary viewport override was reset afterward.
- Browser mutation evidence: Only authorized login/session activity, GET navigation, GET filters, viewport control, focus, and horizontal scrolling occurred. No Product Variant submission, catalog mutation, Opening Inventory submission, Stock In, Stock Correction, checkout, Sale mutation, print, export/download, or later formal case occurred. Concern/ambiguity: None.
- Source evidence: The shared layout renders successful session flashes as an emerald bordered/textual alert. The Product Variant form renders the exact amber locked-identity notice and read-only/disabled controls from the activity-based lock state. Opening Inventory and Sales History place their wide tables inside `overflow-x-auto` wrappers and retain contextual empty rows within the table shell. Sales History normalizes receipt filters, returns the exact inline message for invalid input, fails invalid filters closed, and renders `No Sales found.` when no rows qualify. This committed source supports, but does not replace, the current browser observations or retained prior success evidence.
- Supplemental automated evidence: Existing `SalesHistoryTest::test_receipt_search_is_exact_canonical_case_insensitive_and_fails_closed` covers the valid nonexistent receipt and invalid receipt paths. Existing Opening Inventory and Product Variant tests support list state and activity-lock rules. They were inspected but not rerun, and no whole-suite run occurred. Automation is not represented as the manual narrow-width or scrolling evidence.
- Guarded database identity: `scripts/provision-ft15-fixtures --verify --run-id=FT15-20260909-A` confirmed connection `mysql_testing`, live database `trackpro_test`, MySQL/InnoDB identity, and `writes_performed=false`. A separate guarded query required live `SELECT DATABASE()` to equal `trackpro_test` before the narrow baseline SELECTs ran.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control with total/cash/change `10.00 / 10.00 / 0.00`, and Sale ID 2 / `TRX-000002` remained the unchanged `completed` Sale recorded by FT15 Staff with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both attached to Sale ID 2 with their established immutable units, quantities, prices, and totals. No Sale or SaleItem was created or changed.
- Catalog/stock reconciliation: ProductVariant ID 1 remained active under Product ID 1 as `FT15 Whole Variant`, with empty Type / series and Thickness, unit `piece`, Whole mode, cost `72.00`, selling price `125.00`, stock `16.000`, threshold `5.000`, and unchanged established timestamp. ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 controlled low-stock `1.000`, and IDs 4–8 `0.000`. No catalog field or stock changed.
- StockMovement reconciliation: StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`, proving TC-UI-001 navigation/filter/scroll activity created no AuditLog. Normal authentication/session storage is not application-domain mutation.
- Privacy/safety: The reconciliation selected no password/hash, remember token, checkout token, credential, secret, or unrelated private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-UI-001` — supplied genuine current warning, wide-table scrolling, empty-state, validation, viewport, and screenshot observations. `BUE-FT15-CAT-001` — retained prior genuine success-feedback browser observations, explicitly reused rather than freshly observed. `SRC-FT15-UI-001` — committed shared layout, Product Variant, Opening Inventory, Sales History, and controller inspection. `AUTO-FT15-UI-001-SUPPORT` — inspected existing focused tests. `DB-FT15-UI-001-RECON` — guarded identity and SELECT-only zero-mutation comparison.
- Final classification: **Pass**. The current run supplied readable warning and validation text, contextual empty content, and genuine horizontal table travel across representative catalog, inventory, and Sales surfaces; retained prior genuine manual evidence supplies success feedback without a new mutation; and persisted application-domain state exactly matched the baseline. Tracker #15 is now **27 / 30 executed — 27 Passed, 0 Failed, 0 Blocked, 3 Remaining**.
- Issue/blocker: None.
- Notes: The incidental UI query timeout during automatic login-page transition was omitted because subsequent DOM evidence established the correct Admin role before formal testing and the timeout had no application, data, or evidence impact. `TC-A11Y-001`, `TC-A11Y-002`, `TC-SALES-002`, and every later formal case remain unexecuted; no accessibility or immutable-receipt claim is made here.

## TC-A11Y-001 — Accessibility — implemented semantics

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop in-app browser observation with secure user credential handoff.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Roles: Admin (`FT15 Admin`) and Staff (`FT15 Staff`) synthetic accounts.
- Keyboard/mobile viewport: Exact `1023 × 720` for both roles.
- Result: **Pass**
- Formal expectation: On representative Admin/Staff forms, navigation, and status pages, inspect labels, headings, buttons, links, semantic elements, native states, and existing quantity help; operate mobile navigation by keyboard and observe implemented ARIA, inert, Escape, and focus behavior. This class B case evaluates positive implemented semantics only and does not certify WCAG conformance or assistive-technology behavior.
- Fresh Admin keyboard evidence: Genuine Tab traversal from the document body reached the hamburger without mouse activation. The focused button exposed accessible name `Open navigation`, `aria-controls="mobile-navigation"`, and `aria-expanded="false"`; Enter opened it. Its name changed to `Close navigation` and `aria-expanded` to `true`. The drawer became visible, exposed `aria-hidden="false"`, and became non-inert; the application content and mobile-header background became inert as implemented, body overflow locked, and focus moved automatically to the drawer's `Close navigation` button. A navigation landmark labeled `Mobile primary navigation` was present, and the active `Dashboard` link exposed `aria-current="page"`.
- Fresh Admin Escape/focus evidence: Keyboard Escape closed the drawer; `aria-expanded` returned to `false`, the hamburger name returned to `Open navigation`, the drawer returned to `aria-hidden="true"` and inert, application/background usability and body scrolling were restored, and focus returned to the hamburger. This is not represented as a complete focus-trap observation.
- Fresh Stock Correction semantic/help evidence: Admin opened `/product-variants/1/stock-correction` by safe GET and made no submission. The page exposed a semantic main, semantic form, H1 `Record Stock Correction`, visible `Corrected physical stock` and `Reason` labels, a `Record Stock Correction` button, and Cancel/back links. The required `corrected_stock` input exposed `aria-describedby="corrected-stock-help"`, which exactly referenced the visible element `id="corrected-stock-help"` containing `Use a nonnegative whole number.` This proves existing quantity-help association, not error-message association.
- Fresh Product Variant native-state evidence: Admin opened `/product-variants/1/edit` by safe GET and made no submission. Size, Type / series, Thickness, and Cost price (optional) were natively read-only. Unit and Quantity mode were natively disabled. The exact visible explanations were `Identity, unit, and quantity mode are locked because this variant has inventory or activity.` and `Managed by restocking after the first restock.` Representative labels, heading, button, and link remained visible. Read-only and disabled states are recorded distinctly.
- Fresh Staff keyboard evidence: On `/sales`, genuine Tab traversal reached the hamburger without mouse activation. The initial button exposed accessible name `Open navigation`, `aria-controls="mobile-navigation"`, and `aria-expanded="false"`; Enter opened it and changed the name to `Close navigation` and expanded state to `true`. The drawer exposed `aria-hidden="false"` and became non-inert, the application/background became inert, body overflow locked, focus moved to `Close navigation`, and the labeled mobile navigation landmark was present. The active `Sales History` link exposed `aria-current="page"`. Escape closed the drawer, reset ARIA/inert/body-lock state, and returned focus to the hamburger.
- Fresh Sales semantic/status evidence: The Staff Sales History page exposed a semantic main, H1 `Sales History`, semantic GET form, visible Receipt number, Cashier, Date from, and Date to labels, an `Apply filters` button, a `Clear` link, and a semantic populated table with nine header cells, data cells, and two `View receipt` links. No intentionally invalid filter was submitted. The actual visible status words `Completed` and `Voided` were present; emerald/red styling supplemented those words, so meaning was not conveyed by color alone. No color-contrast measurement was performed.
- Evidence provenance: The observations above are fresh TC-A11Y-001 browser evidence. Prior finalized `TC-NAV-003` observations of navigation ARIA/inert/focus behavior and `TC-UI-001` observations of Variant locked states remain supplemental prior manual evidence only and are not mislabeled as fresh execution evidence. Screenshots are supplemental rather than sole proof of keyboard traversal or focus.
- Explicit accessibility limitations: TC-A11Y-001 does **not** establish full WCAG conformance, a complete WCAG success-criterion audit, color-contrast certification, assistive-technology certification, screen-reader certification, complete focus trapping, dialog semantics, `aria-modal` semantics, exhaustive tab-order correctness, universal focus-style conformance, error-message programmatic association, invalid-form focus correctness, dynamic/live-region announcements, POS live announcements, Stock In dynamic announcements, or reduced-motion conformance. `TC-A11Y-002` remains separate and unexecuted.
- Screenshot status: Five genuine in-app screenshots were captured: Admin keyboard-open drawer, Staff keyboard-open drawer, Stock Correction quantity help, Variant locked states, and populated Sales History. No external screenshot identifier was generated. The viewport override was reset afterward. Keyboard traversal, activation, and focus were directly observed during execution and are not inferred from screenshots alone.
- Browser mutation evidence: Only authorized authentication/session activity, GET navigation, viewport changes, Tab, Enter, Escape, and read-only DOM/semantic inspection occurred. No Stock Correction or Variant form was submitted; no Opening Inventory, Stock In, checkout, catalog mutation, Sale mutation, intentionally invalid validation case, print, export/download, `TC-A11Y-002`, `TC-SALES-002`, or other formal case occurred.
- Source evidence: The committed shared layout and navigation component provide semantic labeled navigation, icon-button accessible names, `aria-controls`, initial `aria-expanded`/`aria-hidden`/inert state, and `aria-current="page"` for the active link. The committed navigation script changes accessible name, expanded/hidden/inert/body state, focuses Close on open, handles Escape, restores focus on ordinary close, and uses the `64rem` breakpoint. Stock Correction source provides the semantic form, required `corrected_stock` control, exact `aria-describedby` relationship, and visible help text. Product Variant source provides the distinct native read-only and disabled controls and exact lock/cost explanations. Sales source provides the semantic GET form/table, visible labels/actions, and textual completed/voided status with supplemental styling. Source supports but does not replace the fresh browser evidence.
- Supplemental automated evidence: Existing `ResponsiveNavigationTest` checks initial accessible names, `aria-controls`, expanded/hidden/inert markup, active `aria-current`, labeled role-specific navigation structure, and semantic secure logout forms. Existing Stock Correction and inventory tests support authorized GET availability and form/quantity-mode behavior. These tests were inspected but not rerun; no whole-suite run occurred. Automation is implementation/markup support only and is not represented as keyboard or focus browser evidence.
- Guarded database identity: `scripts/provision-ft15-fixtures --verify --run-id=FT15-20260909-A` confirmed connection `mysql_testing`, live database `trackpro_test`, MySQL 8/InnoDB identity, and `writes_performed=false`. A separate guarded query required live `SELECT DATABASE()` to equal `trackpro_test` before the narrow SELECT-only comparison ran.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control with total/cash/change `10.00 / 10.00 / 0.00`, and Sale ID 2 / `TRX-000002` remained the unchanged `completed` Sale recorded by FT15 Staff with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly IDs 1 and 2, both attached to Sale ID 2 with immutable units `piece` and `kg`, quantities `2.000` and `1.250`, prices `125.00` and `80.00`, and totals `250.00` and `100.00`. No Sale or SaleItem was created or changed.
- Catalog/stock reconciliation: ProductVariant ID 1 remained active under Product ID 1 as `FT15 Whole Variant`, with empty Type / series and Thickness, unit `piece`, Whole mode, cost `72.00`, selling price `125.00`, stock `16.000`, threshold `5.000`, and unchanged established timestamp. ProductVariant stocks remained ID 1 Whole `16.000`, ID 2 Fractional `7.500`, ID 3 controlled low-stock `1.000`, and IDs 4–8 `0.000`. No catalog field or stock changed.
- StockMovement reconciliation: StockMovements remained exactly `10`, with maximum ID 10 and type counts `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. No StockMovement occurred.
- Audit reconciliation: AuditLogs remained exactly `0`, proving TC-A11Y-001 authentication/navigation/inspection activity created no AuditLog. Normal authentication/session storage is not application-domain mutation.
- Privacy/safety: The reconciliation selected no password/hash, remember token, checkout token, credential, secret, or unrelated private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-A11Y-001` — supplied fresh Admin/Staff keyboard, ARIA/inert/focus/current, form/help, native-state, semantic/status, viewport, and screenshot observations. `PRIOR-FT15-NAV-003-UI-001` — retained supplemental genuine prior browser evidence, explicitly not fresh A11Y-001 evidence. `SRC-FT15-A11Y-001` — committed layout, navigation, JavaScript, Stock Correction, Product Variant, and Sales source inspection. `AUTO-FT15-A11Y-001-SUPPORT` — inspected existing navigation/inventory tests. `DB-FT15-A11Y-001-RECON` — guarded identity and SELECT-only zero-mutation comparison.
- Final classification: **Pass**. Fresh Admin/Staff keyboard operation, navigation state/focus behavior, semantic form/table samples, exact quantity-help association, distinct native read-only/disabled states, and visible textual statuses satisfy the positive implemented-semantics case; source support is consistent and persisted application-domain state exactly matches the baseline. Tracker #15 is now **28 / 30 executed — 28 Passed, 0 Failed, 0 Blocked, 2 Remaining**.
- Issue/blocker: None.
- Notes: The first supplemental baseline query used an incorrect Sale field name and stopped read-only after confirming database identity; it performed no write. The corrected SELECT-only reconciliation completed successfully. `TC-A11Y-002` and `TC-SALES-002` remain unexecuted. No error-association, invalid-form focus, dynamic-announcement, complete focus-trap, dialog, WCAG, assistive-technology, or other later-case claim is made.

## TC-A11Y-002 — Accessibility — partial error/dynamic behavior

- Browser timestamp: Unavailable; an exact browser execution timestamp was not supplied.
- Executor: Codex Desktop in-app browser observation with secure user credential handoff.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Roles: Admin (`FT15 Admin`) for invalid Sales filtering; Staff (`FT15 Staff`) for POS and Stock In dynamic activity.
- Result: **Pass**
- Formal expectation: Trigger an invalid form and representative POS/Stock In dynamic updates, then inspect visible feedback, field-error association, focus, and announcement semantics. Visible feedback must exist, while inconsistent field-error association and unestablished live announcements are recorded accurately as known deferred limitations. This class E case does not require those deferred semantics to have been corrected.
- Admin validation evidence: The Admin submitted the Sales History receipt filter `INVALID` by GET. The exact inline message `Enter a receipt number such as TRX-000002.` appeared, the table shell/header remained present, and the fail-closed result showed `No Sales found.` No shared global validation summary appeared, and no application-domain mutation occurred.
- Field/error association evidence: The Receipt control was a native `INPUT` with `name="receipt"`; `id`, `aria-describedby`, `aria-errormessage`, and `aria-invalid` were absent. Its visible inline error was a native `SPAN` with the exact message above; `id` and `role` were absent. For this specific field/error example, visible validation feedback exists but the inline error is not programmatically associated with the Receipt input. This finding is not extrapolated to every application form.
- Post-validation focus evidence: After the GET response completed, `document.activeElement` was `BODY`. No application-defined transfer to the Receipt input, inline error, or a global summary was observed. Automatic invalid-field focus is not represented as a formal requirement of this case.
- Staff POS dynamic evidence: Without activating Checkout, Staff added ProductVariant ID 1 (`FT15 Sample Material — FT15 Whole Variant`), changed quantity from `1` to `2`, entered tender `400`, inspected the updates, and removed the row. Quantity 1 produced line estimate/total `₱125.00 / ₱125.00`; quantity 2 produced `₱250.00 / ₱250.00`; tender `400` produced change `₱150.00`. After removal, `The cart is empty.`, total `₱0.00`, change `₱400.00`, and disabled Checkout were visible.
- POS ordinary-region semantics: The cart and cart row were ordinary `DIV` elements, empty-cart text was a `P`, and the line value was a `SPAN`. No explicit `role`, `aria-live`, or `aria-atomic` was present on those regions. Therefore, no established live-announcement implementation is claimed for ordinary cart, row, empty-state, or line updates. Checkout's disabled state changed natively/programmatically, but Checkout itself was not a live region.
- POS output semantics: Total and change were native `OUTPUT` elements. Neither had an explicit `role`, `aria-live`, or `aria-atomic`, but browser accessibility inspection exposed the computed/implicit role `status` for both. Native output semantics therefore differ from the ordinary cart/row/span regions and support implicit status/live semantics for the two outputs; actual screen-reader announcement wording or timing was not tested.
- POS focus evidence: After Add, focus remained on `Add to cart`; after the quantity edit, the quantity input was active; after the tender edit, `amount_tendered` was active. Removing the only row destroyed the focused row/control and browser fallback became `BODY`; no application-defined recovery target was observed.
- Staff Stock In dynamic evidence: At `/stock-in/create`, the initial one row had disabled Remove. Add item created a second row, enabled both Remove controls, and left Add item enabled. Selecting Variant ID 1 in the second row changed `Select a variant to see its unit, quantity mode, and current stock.` to `FT15 Sample Material · FT15 Whole Variant · Unit: piece · whole · Current stock: 16.000`. Removing the second row left one row, disabled the remaining Remove control, and left Add item enabled. `Record Stock In` was not submitted.
- Stock In semantics: Metadata was a native `P` with no `id`, `role`, `aria-live`, or `aria-atomic`. The Variant select had no `aria-describedby` or `aria-controls` relationship to that metadata. The item container and rows were ordinary `DIV` elements without live-region attributes. Row and metadata updates were visibly usable, but the implementation establishes no explicit programmatic announcement relationship or row-count live semantics. Actual screen-reader silence is not claimed.
- Stock In focus evidence: After Add item, activeElement remained Add item. During the automated selection used in this execution, activeElement remained Add item; committed client code contains no explicit `focus()` transfer for the metadata update. This is not a general claim about whether native user interaction with the Variant select focuses that select. Activating the second-row Remove control destroyed the focused control and browser fallback became `BODY`; no application-defined recovery target was observed.
- Announcement conclusion: **Partial / mixed.** POS total and change have native `OUTPUT` semantics and were exposed by browser accessibility inspection as `status`; ordinary POS cart/row/empty/line updates lack explicit live-region markup; Checkout is not a live region; and Stock In row/metadata changes are visible but lack an established explicit announcement relationship/live region. Neither blanket absence nor universal announcement is claimed.
- Accessibility limitations: No screen reader was run. This evidence establishes no WCAG conformance, assistive-technology certification, or screen-reader compatibility certification. Actual announcement wording and timing were not tested. Findings are limited to DOM/native semantics, browser accessibility exposure, visible behavior, and observed focus state.
- Screenshot status: Four genuine in-app screenshots were captured: Sales validation, populated POS calculations, empty POS state, and two-row Stock In metadata state. No external screenshot identifier was generated. Screenshots are supplemental only.
- Source evidence: Sales History source renders the Receipt input and inline error with the observed missing association attributes, and its controller supplies the exact fail-closed error behavior. POS source uses ordinary cart/row/empty/line elements, native `OUTPUT` elements for total/change, exact decimal-scaled client calculations, row creation/removal, and disabled Checkout updates. Stock In source creates/removes/reindexes rows and updates plain-text metadata. The committed client code contains no explicit focus recovery for removed POS/Stock In controls and no explicit focus transfer for Stock In metadata updates. Source supports but does not replace the fresh browser and accessibility-tree observations.
- Supplemental automated evidence: Existing `SalesHistoryTest::test_receipt_search_is_exact_canonical_case_insensitive_and_fails_closed` directly supports the server-side invalid-filter and fail-closed result. Existing `PosCheckoutTest` and `RestockManagementTest` cover server validation, integrity, and atomicity, while `RestockAuthorizationTest::test_admin_and_staff_can_view_and_post_stock_in` supports role access. These tests were inspected but not rerun; they do not prove the client-side live, accessibility-tree, or focus observations, and no whole-suite run occurred.
- Guarded database identity: `scripts/provision-ft15-fixtures --verify --run-id=FT15-20260909-A` confirmed connection `mysql_testing`, live database `trackpro_test`, MySQL 8/InnoDB identity, and `writes_performed=false`. A separate guarded query required live `SELECT DATABASE()` to equal `trackpro_test` before the narrow SELECT-only comparison ran.
- Sales/SaleItem reconciliation: Sales remained exactly `2`: Sale ID 1 / `TRX-000001` remained the unchanged `voided` fixture control with total/cash/change `10.00 / 10.00 / 0.00`, and Sale ID 2 / `TRX-000002` remained the unchanged `completed` Sale recorded by FT15 Staff with total `350.00`, cash `400.00`, change `50.00`, and its original timestamp. SaleItems remained exactly `2`, both attached to Sale ID 2. No Sale or SaleItem was created or changed.
- Catalog/stock reconciliation: The catalog remained exactly 4 Categories, 4 Products, and 8 ProductVariants with the established hierarchy/status population. ProductVariant stocks remained ID 1 `16.000`, ID 2 `7.500`, ID 3 `1.000`, and IDs 4–8 `0.000`. No catalog row or stock changed.
- StockMovement/Restock reconciliation: StockMovements remained exactly `10`, maximum ID `10`, with `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. Restocks remained exactly `2` with maximum ID `2`; RestockItems remained exactly `3` with maximum ID `3`. No StockMovement, Restock, or RestockItem was created or changed.
- Audit reconciliation: AuditLogs remained exactly `0`, proving the A11Y-002 GET and unsaved client-side POS/Stock In activity created no AuditLog. Normal authentication/session state and unsaved browser form/cart state are not application-domain mutations.
- Privacy/safety: The reconciliation selected no password/hash, remember token, checkout token, submission token, credential, secret, or unrelated private field and recorded `writes_performed=false`. `trackpro_local` and Test Hammer were not accessed.
- Evidence references: `CDB-FT15-A11Y-002` — supplied fresh Admin validation/association/focus and Staff POS/Stock In dynamic/semantic/focus observations plus screenshots. `SRC-FT15-A11Y-002` — committed Sales, POS, Stock In, controller, and JavaScript inspection. `AUTO-FT15-A11Y-002-SUPPORT` — inspected focused server-behavior tests. `DB-FT15-A11Y-002-RECON` — guarded identity and SELECT-only zero-mutation comparison.
- Final classification: **Pass**. Visible validation and dynamic behavior were usable; the specific missing error association, focus fallbacks, ordinary-region announcement gaps, and native `OUTPUT` status semantics were recorded accurately as partial/mixed behavior; and persisted application-domain state exactly matched the established baseline. Tracker #15 is now **29 / 30 executed — 29 Passed, 0 Failed, 0 Blocked, 1 Remaining**.
- Issue/blocker: None.
- Notes: The incidental login-page locator timeout caused by automatic transition into the Admin session was non-domain, non-mutating, and omitted from the formal evidence because the correct role was confirmed before observation continued. `TC-SALES-002 — Receipt — immutable detail` remains unexecuted and must remain the final Tracker #15 case. No Checkout, Stock In submission, screen-reader run, accessibility certification, or later formal case occurred.

## TC-SALES-002 — Receipt — immutable detail

- Browser timestamp: Exact browser execution timestamp unavailable; the approved database timestamps below are persisted application evidence, not a claimed browser timestamp.
- Executor: Codex Desktop in-app browser observation with secure Admin/Staff credential handoffs.
- Application commit: `6d70cf7076057dd97e306e2b8d0852bf5e0c0907`
- FT15 run ID: `FT15-20260909-A`
- Formal references: `FR-SALES-03`, `FR-SALES-05`, and `BR-04`; functional; Admin/Staff; evidence class A.
- Roles: Admin (`FT15 Admin`) performed the three controlled current-catalog edits and pre/post receipt checks. Staff (`FT15 Staff`) performed only the post-mutation receipt check.
- Result: **Pass**
- Formal expectation: After legitimate later Product rename and Variant repricing, historical Sale detail retains its immutable Product/Variant/unit/quantity/price/line-total snapshots and payment evidence, exposes no purchase cost, token/idempotency data, or StockMovement internals, and remains a read-only receipt surface.
- Pre-mutation Admin receipt: At `/sales/2`, the receipt visibly showed `TRX-000002`, `Completed`, `Sep 11, 2026 1:54 AM`, and cashier `FT15 Staff`. Back to Sales History and Print receipt were present; Print was not invoked. No Sale/SaleItem edit, delete, or void control appeared.
- Pre-mutation historical line 1: `FT15 Sample Material`; `FT15 Whole Variant`; unit `piece`; quantity `2.000`; unit price `₱125.00`; line total `₱250.00`.
- Pre-mutation historical line 2: `FT15 Sample Material`; `FT15 Fractional Variant`; unit `kg`; quantity `1.250`; unit price `₱80.00`; line total `₱100.00`.
- Pre-mutation payment evidence: Total `₱350.00`; Cash received `₱400.00`; Change `₱50.00`.
- Intentional Product edit: Admin used the normal `/products/1/edit` application form, retained Category ID 1 / `FT15 Fixtures`, and changed only Product ID 1 name from `FT15 Sample Material` to `FT15 Snapshot Changed Material`. The application redirected to `/products`, rendered the exact success message `Product updated.`, and visibly showed the renamed active Product.
- Intentional Whole Variant edit: Admin used the normal `/product-variants/1/edit` form and changed only selling price from `125.00` to `175.00`. Size `FT15 Whole Variant`, empty type/series and thickness, unit `piece`, Whole mode, cost `72.00`, stock `16.000`, threshold `5.000`, Product relationship, and active status were retained. The application redirected to `/product-variants`, rendered `Product variant updated.`, and visibly showed `175.00`.
- Intentional Fractional Variant edit: Admin used the normal `/product-variants/2/edit` form and changed only selling price from `80.00` to `160.00`. Size `FT15 Fractional Variant`, empty type/series and thickness, unit `kg`, Fractional mode, cost `45.50`, stock `7.500`, threshold `2.500`, Product relationship, and active status were retained. The application redirected to `/product-variants`, rendered `Product variant updated.`, and visibly showed `160.00`.
- Current-catalog divergence: The rendered Product Variants page showed `FT15 Snapshot Changed Material` with Whole selling price `175.00` and Fractional selling price `160.00`. Thus the current catalog visibly differed from the original SaleItem snapshots before the historical receipt was reopened.
- Post-mutation Admin receipt: Admin reopened `/sales/2`. It still showed both original `FT15 Sample Material` snapshot lines, original Whole/Fractional identities, `piece`/`kg`, quantities `2.000`/`1.250`, prices `₱125.00`/`₱80.00`, line totals `₱250.00`/`₱100.00`, and payments `₱350.00`/`₱400.00`/`₱50.00`. The renamed Product and new prices did not replace historical receipt values.
- Post-mutation Staff receipt: After secure role handoff, Staff opened `/sales/2` and observed the same original historical snapshots and payment evidence. Staff performed no catalog mutation. The new Product name and prices did not replace receipt values.
- Receipt privacy/internal exclusions: Neither role's receipt exposed purchase/current costs, checkout tokens, submission/idempotency tokens, StockMovement IDs/types, movement before/after quantities, movement actors, or other implementation internals. Database existence is not treated as presentation exposure.
- Receipt read-only evidence: The receipt exposed Back to Sales History and Print receipt only. It exposed no Edit Sale, Delete Sale, Edit SaleItem, or Void Sale control. Print is a read-only browser action and was present but not invoked during this case.
- Exact allowed persisted changes: Product ID 1 name changed to `FT15 Snapshot Changed Material`, with automatic `updated_at = 2026-09-11 18:12:13`. ProductVariant ID 1 selling price changed to `175.00`, with automatic `updated_at = 2026-09-11 18:13:15`. ProductVariant ID 2 selling price changed to `160.00`, with automatic `updated_at = 2026-09-11 18:13:53`. These three existing business-field changes and their automatic timestamps were intentional formal precondition writes, not drift.
- Product/Variant reconciliation: Product ID 1 retained ID, Category ID 1, active status, and `created_at = 2026-09-09 16:33:25`. Variants 1 and 2 retained Product ID 1, exact identities, units, modes, costs, stocks, thresholds, active statuses, and the same created timestamp. No Product or Variant row was created or removed.
- Immutable Sale reconciliation: Sale ID 2 remained completed, recorded by user ID 2 / `FT15 Staff`, created `2026-09-11 01:54:17`, total `350.00`, cash `400.00`, and change `50.00`. Sales remained exactly `2`: one completed and one voided.
- Immutable SaleItem 1 reconciliation: ID 1 remained attached to Sale ID 2 and ProductVariant ID 1 with snapshots `FT15 Sample Material` / `FT15 Whole Variant` / empty type-series / empty thickness / `piece`, quantity `2.000`, unit price `125.00`, line total `250.00`, and original created timestamp.
- Immutable SaleItem 2 reconciliation: ID 2 remained attached to Sale ID 2 and ProductVariant ID 2 with snapshots `FT15 Sample Material` / `FT15 Fractional Variant` / empty type-series / empty thickness / `kg`, quantity `1.250`, unit price `80.00`, line total `100.00`, and original created timestamp. SaleItems remained exactly `2`, both attached to Sale ID 2.
- Inventory/history reconciliation: ProductVariant stocks IDs 1–8 remained `16.000`, `7.500`, `1.000`, `0.000`, `0.000`, `0.000`, `0.000`, and `0.000`. StockMovements remained exactly `10`, maximum ID `10`, with `INITIAL_STOCK = 4`, `RESTOCK = 3`, `CORRECTION = 1`, `SALE = 2`, and `SALE_VOID = 0`. Restocks remained `2` with maximum ID 2; RestockItems remained `3` with maximum ID 3; AuditLogs remained `0`.
- Intended-versus-unintended write conclusion: Exactly the three approved existing current-catalog business fields changed, plus their automatic timestamps. Historical Sale/SaleItems, Categories, all stock balances, StockMovements, Restocks/RestockItems, and AuditLogs remained reconciled; no unintended application-domain write was found. This case was intentionally state-changing end-to-end, while the historical receipt itself remained read-only.
- Intentional final postcondition: The changed current `trackpro_test` catalog name and prices are deliberately retained as evidence of historical snapshot independence. No fixture `--apply`, rollback, or revert followed the case.
- Screenshot status: Five genuine in-app screenshots were captured: pre-mutation Admin receipt; initial post-edit catalog/success surface; post-mutation Admin receipt; post-mutation Staff receipt; and a full-page current-catalog view showing `FT15 Snapshot Changed Material`, Whole `175.00`, and Fractional `160.00`. No external screenshot identifier was generated, and no screenshot path is fabricated.
- Source/automated support: The committed Sale detail query selects only receipt-safe Sale/SaleItem snapshot fields and does not load current catalog, token, cost, or movement presentation data. The receipt view renders snapshot fields, and immutable model guards reject Sale/SaleItem update/delete. Existing `SalesHistoryTest::test_receipt_uses_snapshots_preserves_privacy_prints_and_never_writes` directly covers later catalog rename/reprice and original snapshot/privacy rendering. Relevant Product/Variant tests cover allowed rename/reprice rules. These tests were inspected but not rerun during this closeout; the retained committed ordinary baseline remains historical rather than a fresh run.
- Privacy/safety: Final SELECT-only reconciliation required live `SELECT DATABASE()` to equal `trackpro_test` and selected no password/hash, remember token, checkout token, credential, secret, or unrelated private field. `trackpro_local` and Test Hammer were not accessed or changed.
- Evidence references: `CDB-FT15-SALES-002-PRE` — genuine pre-mutation Admin receipt. `CDB-FT15-SALES-002-CATALOG` — normal Admin edits and rendered current-catalog divergence. `CDB-FT15-SALES-002-ADMIN-POST` and `CDB-FT15-SALES-002-STAFF-POST` — genuine post-mutation receipt observations. `DB-FT15-SALES-002-RECON` — guarded final SELECT-only reconciliation. `SRC-AUTO-FT15-SALES-002` — committed source and existing focused test support.
- Final classification: **Pass**. Genuine current-catalog divergence was established through normal Admin UI, both Admin and Staff receipts retained every original historical snapshot and payment value, prohibited internals remained absent, the receipt remained read-only, and persisted state contained only the three approved current-catalog changes plus automatic timestamps. Tracker #15 is now **30 / 30 executed — 30 Passed, 0 Failed, 0 Blocked, 0 Remaining**.
- Issue/blocker: None.
- Notes: Two unsupported browser-helper calls failed before any value change or form submission; the supported value-setting method was then used, and all later form states and writes were independently verified. These tooling-only events were non-domain, non-blocking, and not application defects. This was the final Tracker #15 case. No fixture re-apply, rollback, revert, Print invocation, Tracker #17 execution, or additional formal case occurred.

## Tracker #15 Functional Testing Closeout

- Run: `FT15-20260909-A`
- Result: **30 / 30 finalized — 30 Passed, 0 Failed, 0 Blocked**.
- Coverage: Authentication, Dashboard, Catalog and Variants, Opening Inventory, Stock In, Stock Correction, POS, Sales History, receipt/reprint/print presentation, Reports, navigation and responsive behavior, shared UI states, implemented accessibility semantics, partial accessibility/error/dynamic behavior, and immutable historical receipt evidence after legitimate current-catalog change.
- Final evidence: `TC-SALES-002` completed the run by proving that the deliberately retained current Product rename and Variant repricing did not rewrite or replace historical SaleItem/receipt snapshots.
- Boundary: This completes Tracker #15 only. Tracker #17 — Edge Case & Permission Testing remains a separate In Progress tracker and was not executed by this closeout.
