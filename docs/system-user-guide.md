# 3A TrackPro — System User Guide

This guide describes the currently implemented user-facing workflows in
3A TrackPro. Screens and available actions depend on the signed-in user's role.
It is intended for classroom demonstrations and for Admin and Staff users.

## 1. About TrackPro

3A TrackPro is a Hardware Store Sales and Inventory Management System for a
single store location. It organizes the product catalog, records inventory
receipts and corrections, records cash sales, preserves receipts and sales
history, and provides sales and procurement reports.

The catalog follows this hierarchy:

```text
Category
└── Product
    └── Product Variant
```

A Product Variant is the sellable and stock-tracked item. Its identity may
include size, type or series, thickness, and unit.

## 2. Starting the System Locally

Use an existing configured development installation. From a terminal, run:

```bash
cd ~/Projects/3a-trackpro-system
php artisan serve --host=127.0.0.1 --port=8015
```

Then open:

```text
http://127.0.0.1:8015
```

If the page loads without the expected styling or JavaScript behavior, build
the frontend assets once:

```bash
npm run build
```

For live frontend development, run this in a separate terminal instead:

```bash
npm run dev -- --host 127.0.0.1
```

Do not overwrite an existing `.env`, regenerate its application key, migrate,
seed, or reset a database merely to start an already configured system.

## 3. Signing In and Navigating

1. Open `/login` or the local address above.
2. Enter the assigned **Username** and **Password**.
3. Select **Sign in**.
4. Use the left sidebar on a desktop-sized screen. On a smaller screen, use the
   menu button to open the navigation drawer.
5. At the end of a session, select **Sign out** in the account area.

Only active accounts may use the application. A disabled account cannot
continue using protected pages; contact the project administrator if access is
denied. TrackPro has no public registration, forgot-password, password-reset,
or browser-based account setup page. Ask the project administrator if an
assigned account cannot sign in.

Useful application paths are:

| Screen | Path | Access |
|---|---|---|
| Dashboard | `/` | Admin and Staff |
| Reports | `/reports` | Admin |
| POS | `/pos` | Admin and Staff |
| Sales History | `/sales` | Admin and Staff |
| Categories | `/categories` | Admin and Staff |
| Products | `/products` | Admin and Staff |
| Variants | `/product-variants` | Admin and Staff |
| Stock In | `/stock-in` | Admin and Staff |
| Opening Inventory | `/opening-inventory` | Admin |
| Stock Correction | `/stock-corrections` | Admin |
| Purchase Orders | `/purchase-orders` | Admin and Staff |
| User Management | `/users` | Admin |
| Audit Logs | `/audit-logs` | Admin |

## 4.1 Admin User Management

User Management is available to Admins only. Open **User Management** from the
Admin navigation to search and manage accounts. Staff cannot open this area.

- Search by a person's name or username. Results include active and disabled
  accounts and keep the search when moving between pages.
- Select **Create user** to add a Staff or Admin account. Staff is selected by
  default. Every new account starts active. Enter a name, username, role, and
  password; passwords must be at least 12 characters and confirmed.
- Edit a user's name and username on that user's edit page. Role changes,
  archive/reactivation, and password resets are separate actions.
- Change a user's role between Admin and Staff when appropriate.
- Choose **Archive** to disable an account and prevent future access. Choose
  **Reactivate** to restore a disabled account to active status. Archive does
  not delete the account or its history; accounts cannot be deleted.
- Reset a password by entering and confirming a new password. There is no old
  password requirement or email reset link.
- An Admin may edit their own name and username or reset their own password,
  but cannot demote or disable their own account. The system also prevents a
  change that would leave no active Admin; another active Admin must remain.

## 4.2 Reviewing Audit Logs — Admin Only

Open **Audit Logs** from the Admin Administration area beside User Management.
The page is read-only and shows recorded events newest first, with 20 records
per page. Staff cannot open it, and guests or disabled accounts cannot access
the protected page.

Each row represents one recorded event. Review its Manila timestamp, actor,
action, affected-record context, description, and safe change summary. The
actor is the user who performed the action; the affected record is the account
or other record the action concerns. For account events, the stable User #ID
identifies the affected account, and the page may also show that account's
current context. The actor's displayed name and username are current account
details, not a saved historical identity snapshot. A disabled actor can still
appear in older history.

Use the filters as needed:

- **Actor** selects the user who performed the action.
- **Action** selects a recorded activity type.
- **Date From** and **Date To** set optional calendar-date bounds in Manila
  time. Both blank means all time; either date may be used by itself. Selected
  dates include the whole calendar day.
- Filters work together, so a record must match every selected filter.
- Choose a page number or **Next**/**Previous** to browse results. Selected
  filters remain in place as you move between pages.

For account changes, summaries show only the safe fields **name**,
**username**, **role**, and **status**. Password values are never shown;
password-reset events do not display before/after password values. Descriptions
are shown as text. The page does not create, edit, delete, clear, prune, or
acknowledge Audit Logs, and viewing a record does not create another event.
Current account activity includes user creation, profile updates, role changes,
disabling, reactivation, and password resets. A successful Admin Sale Void is
also recorded as `SALE_VOIDED`; its audit description reports the status change
without copying the private void reason.

## 4. User Roles and Access

| Area | Admin | Staff |
|---|---:|---:|
| Dashboard | Yes | Yes |
| Seven-day sales trend | Yes | No |
| Reports | Yes | No |
| POS and checkout | Yes | Yes |
| Sales History and receipts | Yes | Yes |
| Void a completed Sale | Yes | No |
| Browse Categories, Products, and Variants | Yes | Yes |
| Create, edit, archive, and reactivate catalog records | Yes | No |
| View catalog cost price | Yes | No |
| Opening Inventory | Yes | No |
| Stock In | Yes | Yes |
| View Stock In purchase costs and totals | Yes | Restricted for Staff |
| Stock Correction | Yes | No |
| Browse Purchase Orders and receive a PO | Yes | Yes |
| Create/edit Purchase Orders and create follow-up POs | Yes | No |

Staff users see only the active catalog hierarchy. Admin users can filter
catalog records by active, archived, or all statuses and see the available
management actions. Both roles can view operational PO and receipt history,
but only Admin users can access the Reports module.

## 5. Understanding Quantities and Stock

TrackPro supports two quantity modes:

- **Whole** — for items counted in whole units, such as pieces or sheets.
- **Fractional** — for items that can be measured in partial units, such as
  kilograms or metres.

Current stock is stored with three decimal places for exact calculations. The
interface presents it according to the Variant's quantity mode:

| Stored current stock | Quantity mode | Visible current stock |
|---:|---|---:|
| `8.000` | Whole | `8` |
| `0.000` | Whole | `0` |
| `7.500` | Fractional | `7.500` |
| `6.000` | Fractional | `6.000` |

TrackPro does not convert between units. Always use the Variant's configured
unit and quantity mode.

A Variant is considered low stock when its current stock is less than or equal
to its low-stock threshold. An out-of-stock Variant has zero current stock and
is also included in the low-stock count when zero is at or below its threshold.

## 6. Managing the Catalog

### 6.1 Categories

Open **Catalog → Categories**.

- Use **Search** to find a Category by name.
- Admins can use the **Status** filter.
- Select **Create category** to add a Category.
- For an active Category, use **Edit**, **Add product**, or **Archive**.
- For an archived Category, use **Reactivate**.

A Category cannot be archived while it still contains active Products. Archive
its active Products first. Archived records are retained; there is no catalog
delete workflow.

### 6.2 Products

Open **Catalog → Products**.

- Search by Product name or filter by Category.
- Admins can also filter by Product status; the list is paginated. Select a
  Product name to open its read-only detail page.
- The detail page shows the Product name, Category, and active or archived
  status. Each permitted Variant has its own row showing size, type/series,
  thickness, unit, quantity mode, selling price, current stock, low-stock
  threshold, stock state, and active or archived status.
- Stock state is **Out of stock** at zero, **Low stock** above zero through the
  threshold, and **In stock** above the threshold. Whole and fractional stock
  quantities use the Variant's configured display format. Stock is shown per
  Variant; there is no Product-level stock sum or sum across different units.
- Admins can inspect active and archived Products and Variants. Staff can open
  only Products in an active Product and Category hierarchy and see only active
  Variants. Direct access to a hidden Product is denied by the server with 404.
  The detail page shows no cost to either role.
- Admins can create a Product from an active Category.
- Use **Edit** to change its name or Category where permitted.
- Use **Add variant** to create a sellable Variant.
- Archive all active Variants before archiving their Product.
- Reactivate the parent Category before reactivating an archived Product.

A Product with inventory or transaction history cannot be moved to another
Category.

### 6.3 Product Variants

Open **Catalog → Variants**.

Use the available search and Category, Product, Unit, Status, and **Low stock
only** filters. The list shows Product identity, quantity mode, prices, current
stock, threshold, and status according to the viewer's role.

When creating a Variant, review:

- Size, Type / series, and Thickness — optional identity fields when genuinely
  not applicable.
- Unit — one of `piece`, `sheet`, `roll`, `m`, or `kg`.
- Quantity mode — `whole` or `fractional`.
- Cost price — optional until supplied through normal Stock In.
- Selling price — required and greater than zero.
- Low-stock threshold — nonnegative and compatible with the quantity mode.

New Variants start with zero stock. Do not use the catalog form to initialize or
adjust stock.

After inventory or transaction activity, identity, unit, and quantity mode are
locked. After the first Stock In, cost price is managed by Stock In. A Variant
with positive stock cannot be archived. To archive a hierarchy safely, work
upward: Variants first, then Product, then Category.

## 7. Recording Opening Inventory — Admin Only

Opening Inventory records a Variant's one-time physical starting count.

1. Open **Inventory → Opening Inventory**.
2. Find the Variant with Search or the Category, Product, and Opening status
   filters.
3. Verify the Category, Product, Variant identity, unit, quantity mode, and
   current stock.
4. Select **Record opening inventory**.
5. Enter the physical **Opening quantity**.
6. Enter a clear **Reason**, such as `Initial physical count`.
7. Review the information and select **Record opening inventory** once.

Important rules:

- Opening Inventory is allowed exactly once per Variant.
- Zero is valid and still records the Variant as initialized.
- Whole-mode Variants require a whole quantity.
- Fractional-mode Variants accept up to three decimal places.
- The Variant and its Product and Category must be active.
- A Variant with previous stock or transaction history is not eligible.
- A successful operation creates one `INITIAL_STOCK` movement.

Use Stock In or Stock Correction for later changes. Do not attempt to repeat
Opening Inventory.

## 8. Recording Stock In — Admin and Staff

Stock In records received inventory and its purchase cost.

1. Open **Inventory → Stock In**.
2. Select **Record Stock In**.
3. Optionally enter a delivery receipt or other **Reference** and **Notes**.
4. Under **Received items**, select an active, initialized Variant.
5. Verify the displayed Product, identity, unit, quantity mode, and current
   stock.
6. Enter the positive **Received quantity**.
7. Enter the **Unit purchase cost**. Zero is accepted for genuinely free stock.
8. Use **Add item** when the same receipt contains more Variants. Each Variant
   may appear only once, with a maximum of 100 distinct items.
9. Review every line, then select **Record Stock In** once.
10. Verify the resulting `RST-` receipt and its item quantities.

A successful Stock In creates one receipt, one item and one `RESTOCK` movement
per selected Variant, increases current stock, and updates the Variant's latest
cost reference. The system calculates line and receipt totals.

Staff can enter the costs for the receipt they are recording, but existing
catalog costs and historical purchase-cost values are not shown to Staff.

Do not reload or resubmit a completed receipt intentionally. TrackPro has a
server-generated submission token to protect against accidental duplicate
submission.

Stock In is the general inventory-receipt workflow. Purchase Order receiving
is a separate workflow for deliveries against saved PO lines; use it when a
delivery belongs to a Purchase Order.

## 9. Managing Purchase Orders and Receiving — Admin and Staff

Open **Procurement → Purchase Orders** to browse saved orders. The list can be
filtered by supplier and status. Select **View** to inspect the order, its
supplier and item snapshots, receiving history, and any visible parent or
follow-up child context. Staff may browse and receive orders. Creating,
editing, and creating follow-up orders are Admin actions.

### 9.1 Creating and editing an order — Admin only

1. Select **Create Purchase Order**.
2. Enter the supplier and choose the suggested low-stock items or other
   eligible initialized Variants.
3. Review the recommendation/coverage information. It helps prioritize
   initialized active low-stock Variants and identify demand already covered
   by open orders; it is planning guidance, not an automatic order.
4. Enter the ordered quantities and expected unit costs, review the pending
   order, and select **Create Pending Purchase Order**.
5. To revise an eligible pending order with no receiving or transfer activity,
   select **Edit** from the list or order view and save the revised lines.

Once receiving or follow-up transfer activity makes an order ineligible for
editing, it is read-only. Use the order's displayed status and quantities to
understand its current state.

### 9.2 Receiving a Purchase Order — Admin and Staff

1. Open a Purchase Order and select **Receive Purchase Order** when it has
   outstanding lines.
2. For each delivered line, enter the accepted quantity and, if applicable,
   damaged quantity with a damage note. Leave lines not included in this
   delivery blank.
3. Review the unit and quantities, then record the receipt once.
4. Return to the order to review its receipt history, accepted quantities, and
   remaining outstanding demand.

Partial receipts leave the unaccepted quantity outstanding; a fully accepted
order is shown as completed. An Admin may transfer selected full outstanding
quantities to a follow-up child order, after which the source may be closed
with a remainder. Accepted quantity increases sellable stock and creates
normal Stock In evidence. Actual receiving cost is recorded for the accepted
quantity; it is distinct from the expected PO cost. Staff can perform the
receiving workflow but cannot access the Admin-only Reports module.

Damaged receiving can be **accepted only**, **damaged only**, or **accepted +
damaged** on a line. A damage entry requires a positive damaged quantity and a
note. Damaged quantity does not increase or decrease sellable stock, create a
Stock Movement, change product cost, or satisfy/reduce outstanding PO demand.
The damage remains outstanding until accepted or transferred to a follow-up
order. Damage evidence is retained in the order's operational history; there
are no damage edit, delete, or reversal actions.

### 9.3 Creating a follow-up order — Admin only

When a source order has eligible outstanding quantities, an Admin can use
**Create follow-up Purchase Order** to transfer selected lines' full current
outstanding quantities to a child PO. The child PO retains source/parent
context where shown. The transfer changes procurement allocation; it does not
receive goods or change stock. Receiving or damage recorded on a child order is
shown against that child PO.

## 10. Recording a Stock Correction — Admin Only

Use Stock Correction after a verified physical count shows that system stock is
wrong. Do not use it as a shortcut for receiving stock or recording a sale.

1. Open **Inventory → Stock Correction**.
2. Find an initialized active Variant.
3. Select **Correct stock**.
4. Confirm the displayed current stock, identity, unit, and quantity mode.
5. Enter the complete **Corrected physical stock** target—not a signed increase
   or decrease.
6. Enter a specific **Reason**, such as `Physical count discrepancy`.
7. Select **Record Stock Correction** once.

TrackPro calculates the signed difference and creates one immutable
`CORRECTION` movement. The corrected target must be nonnegative, must follow the
Variant's quantity mode, and must differ from current stock. Cost and selling
price are not changed. If the Variant changed while the form was open, reload
and verify the latest state before trying again. The correction screen keeps
its own movement history with quantity before/after, the responsible user, and
reason. This does not mean a separate AuditLog activity record is created.

## 11. Making a Cash Sale in POS — Admin and Staff

1. Open **Sales → POS**.
2. If the Cash Register is **Closed**, enter the **Starting cash amount** and
   select **Open Register**. The register is shared; its open status and opener
   are shown on the POS screen. Only the user who opened it or an Admin may
   close it.
3. Use **Search products** to find a Product or Variant identity.
4. Check the unit, quantity mode, available stock, and selling price.
5. Select **Add to cart**. Out-of-stock items cannot be added.
6. Enter the required quantity for each cart line.
7. Remove any unwanted line with **Remove**.
8. Verify the line estimates and **Estimated total**.
9. Enter **Cash tendered** and check the estimated change.
10. Select **Checkout** once. A successful sale shows its receipt number,
    total, cash, and change, with a **View receipt** link.

Whole-mode items require whole quantities. Fractional-mode items accept up to
three decimal places. Cash must cover the authoritative sale total, and stock
cannot become negative.

The backend rechecks current stock and selling prices during checkout. If a
price changed while the cart was open, TrackPro refreshes the price and asks the
user to review the cart. A successful checkout creates one completed Sale, one
Sale Item per distinct Variant, and one linked `SALE` stock movement per item.

TrackPro currently supports cash sales only. It does not support discounts,
credit or utang, returns, refunds, or a cash-out workflow.

## 12. Voiding a Completed Sale — Admin Only

Use Sale Void only when the entire completed transaction must be reversed.
Staff can continue to view Sales History and receipts, but cannot void a Sale.

1. Open **Sales → Sales History** and open the completed Sale or its receipt.
2. Confirm that the transaction is the one that must be voided. The completed
   Sale page shows the **Void Sale** form to Admins only.
3. Enter a required reason of up to 1,000 characters. The reason is normalized
   before it is saved.
4. Select **Void Sale** and confirm the resulting voided status and receipt.

Voiding applies to the full Sale and restores the exact quantities sold. The
original Sale remains in Sales History, and its items, totals, payment, and
change remain visible. The voided receipt shows the Voided status, reason,
responsible Admin, and time. A Sale that is already voided cannot be voided
again. Partial voids, reopening/unvoiding a Sale, refunds, and cash-out are not
available.

## 13. Viewing Receipts and Sales History

After checkout, select **View receipt**, or open **Sales → Sales History**.

Sales History can be filtered by:

- Exact receipt number, such as `TRX-000002`.
- Cashier.
- Inclusive **Date from** and **Date to** using Manila calendar dates.

Select **View receipt** to reopen a transaction. The receipt shows its status,
date and time, cashier, immutable Product and Variant snapshots, units,
quantities, unit prices, line totals, sale total, cash received, and change.

Select **Print receipt** to open the browser's normal print dialog. Viewing,
reloading, or printing a receipt does not change stock or create history.
Purchase costs and internal checkout tokens are never shown on the receipt.

Receipts use the historical values captured at checkout. Later catalog renames
or price changes do not rewrite old receipts.

## 14. Using the Dashboard

Open **Main → Dashboard**. Admin and Staff see:

- Today's completed sales total.
- Number of completed transactions today.
- Low-stock count.
- Out-of-stock count.
- The five most recent completed Sales.
- Up to five low-stock items with stock and threshold.

Admins also see the **Seven-day Completed Sales Trend** covering today and the
previous six Manila calendar days. Select **Sales History** or **View receipt**
for transaction details, and **View Variants** for the low-stock catalog filter.

The Dashboard is informational; viewing it does not alter inventory or sales.
It does not currently provide a Recent Stock Activity panel or a unified
inventory Movement History.

## 15. Using Reports — Admin Only

Open **Main → Reports**. This module is Admin-only; Staff cannot access it.
Current reports are:

- **Sales Summary** — completed sales within a selected date range, optionally
  filtered by cashier.
- **Product Sales Report** — Admin-only, read-only grouped quantity and sales
  amount for completed Sales in a selected Manila calendar period. The default
  period is today and the previous six Manila dates (seven dates total). Use
  **Date from** and **Date to** for another inclusive range of up to 366 days.
  Invalid, partial, reversed, or overlong date ranges fail closed and show that
  the report was not run. This report has no cashier or other filters, ranking,
  pagination, global quantity total, or mutation controls.
  Each row represents a historical Product/Variant identity group. Product
  name, size, type/series, thickness, and unit come from the sale-time snapshots;
  separate Variant IDs, historical Product names, and unlike units remain
  separate. Renaming or archiving catalog records does not rewrite report
  history. Quantity is the exact grouped stored quantity shown to three decimal
  places alongside its historical unit. Sales amount is the sum of stored line
  totals. The report does not show purchase/current cost, profit, or margin.
- **Inventory Report** — the complete current inventory listing, with one row
  per ProductVariant. It includes active and archived Categories, Products, and
  Variants, and shows each catalog status separately. Rows show Category and
  Product names and statuses, plus Variant size, type/series, thickness, unit,
  quantity mode, and status. They also show current stock, low-stock threshold,
  and stock state. It does not require opening-inventory evidence. Whole
  quantities omit decimal places; fractional quantities show three decimal
  places. The report
  is read-only, has no filters or pagination, and shows no summary or quantity
  total across Variants or units. It exposes no cost, selling price, procurement
  data, or movement/receipt history. Stock state is independent of catalog
  status: zero is **Out of stock**, positive stock at or below threshold is
  **Low stock**, and stock above threshold is **In stock**. Archived records
  remain listed with their stored stock state.
- **Low Stock Report** — active-hierarchy Product Variants with current stock
  at or below their configured low-stock threshold. The report shows one row
  per Variant with Category, Product, size, type/series, thickness, unit,
  current stock, threshold, and stock state. Zero stock is included and labeled
  **Out of stock**; other qualifying rows are labeled **Low stock**. The
  report uses the active Category → Product → Variant hierarchy and does not
  require inventory initialization. It has no filters or pagination and shows
  no cost, procurement coverage, or Product-level or cross-unit total.
  This attention-focused report is narrower than Inventory Report: it includes
  only active-hierarchy Variants at or below threshold, while Inventory Report
  includes all catalog statuses and all stock states.
- **Restocking Report** — Admin-only, read-only history with exactly one row
  per accepted RestockItem. It includes ordinary Stock In and accepted PO
  receiving, distinguished by the receipt's PO link; PO rows also show PO ID
  and supplier, while manual Stock In has no fabricated PO/supplier context.
  Rows use receiving-time Product, size, type/series, thickness, and unit
  snapshots, plus accepted quantity/unit, actual unit cost, stored line total,
  responsible user, `RST-` receipt identifier, and receipt time (shown as
  `M j, Y g:i A` in the configured application timezone). The saved snapshots
  keep item identity stable after catalog rename or archive; rows preserve
  separate receipts and units without totals. Damage-only receipts are
  excluded because they add no accepted stock; damage details remain in the
  Damaged Items Report. Opening Inventory and Stock Corrections are not
  Restocking Report history. Expected PO cost is not shown. The report has no
  filters, pagination, or mutation controls.
- **Pending Purchase Orders Report** — open orders that currently have
  outstanding demand.
- **Unfulfilled Items Report** — individual PO lines that remain unfulfilled,
  with their quantities and PO context.
- **Damaged Items Report** — historical damaged receiving evidence. Each row is
  one damage record and displays its PO, supplier, `RST-` receipt, historical
  item identity, damaged quantity and unit, note, receiving actor, and time.
  The item identity comes from the saved damage snapshots, so later catalog
  renames or archiving do not rewrite report history. The report is read-only
  and orders newest receipt evidence first. Use its **Supplier**, **Item**, or
  exact **PO #** filter; the Item search uses historical snapshot text.

The Damaged Items Report does not combine rows by Variant, does not show a
global quantity total across different units, and provides no date, actor, or
receipt filter. Damaged receiving remains visible operationally in PO history
for Admin and Staff; the dedicated report is available only to Admin.

Reports are Admin-only. Staff can still see permitted stock information in
operational Dashboard and catalog screens, but cannot access this report.

For the **Sales Summary**:

1. Choose **Date from** and **Date to**.
2. Optionally select a Cashier.
3. Select **Apply filters**.
4. Use **Reset** to return to the default range.

The default report covers today and the previous six Manila calendar days. A
custom range is inclusive and may cover at most 366 calendar days.

The Sales Summary contains:

- Completed Sales Total.
- Completed Transactions.
- Daily Sales, including selected days with zero completed Sales.
- Quantity Sold by Unit, keeping units such as piece, sheet, metre, and kilogram
  separate.

Only completed Sales contribute. Reports do not calculate profit, cost of goods
sold, or inventory valuation.

Product Sales is separate from Sales Summary: it groups completed SaleItems by
historical Product/Variant identity and reports each group's quantity and stored
line-total sales amount. It uses the same seven-day default, inclusive Manila
date inputs, and 366-day limit. Database filtering uses the selected dates from
the start of `date_from` up to (but not including) the following day after
`date_to`. Invalid dates prevent the report data query from running. The
Product Sales UI shows no global quantity total across units, summary card,
cashier or other filters, ranking, pagination, cost/profit/margin, or mutation
controls. Voided-status Sales are excluded; this report does not implement Sale
Void or stock restoration.

## 16. Windows and Classroom Demo Data

The repository application seeder does not provide the classroom's current
demo users, catalog, stock, or transaction history. A Windows classroom setup
may depend on separately provided private demo data. Follow
`docs/windows-11-demo-setup.md` for that machine's setup and data prerequisite;
obtain any private demo data through the project team's approved channel. Do
not place private database files or credentials in this repository. This
application guide does not provide or imply a public demo-data download.

Application setup instructions are separate from optional/private demo-data
preparation. For an already configured local installation, use Section 2 and
do not overwrite configuration or reset its database merely to start it.

## 17. Suggested Teacher-Demo Flow

Before presenting, use only approved project/demo records and quantities. A
short rehearsal can follow this order:

1. Sign in as Admin and show the role-labelled navigation.
2. Open Categories, Products, and Variants; demonstrate search and filters.
3. Point out whole stock without `.000` and fractional stock with three decimal
   places.
4. Show an initialized out-of-stock Variant and an existing low-stock Variant.
5. Show the PO list and a representative order/receipt, if approved demo data
   includes them. Explain that PO receiving is separate from general Stock In.
6. If an approved receiving demonstration is planned, show accepted and damaged
   quantities with a damage note and explain the stock/outstanding rules.
7. Make one approved cash sale with a whole item and a fractional item; first
   open the Cash Register if it is closed.
8. Verify the receipt, payment, and change, then reopen it from Sales History.
9. Show the Dashboard's current cards, recent completed Sales, and low-stock
   items.
10. As Admin, open Reports; show Sales Summary and any available procurement
    reports using relevant approved records.
11. Sign out.

Do not rehearse against a preserved test database. Do not delete or rewrite
legitimate history after a rehearsal; successful Stock In and Sale records are
valid append-only project/demo history.

## 18. Messages and Common Problems

### A form reports validation errors

Read the red **Please correct the following** message, correct the named fields,
and review the full form before resubmitting. Avoid repeated clicks while a
request is processing.

### An item is missing from Stock In or POS

Check that its Category, Product, and Variant are active. The Variant must also
have an Opening Inventory record. POS additionally requires positive available
stock to complete a sale.

### Opening Inventory is unavailable

The Variant may already be initialized, may have other history, may have an
inconsistent nonzero starting stock, or may belong to an archived hierarchy.
Use the appropriate later inventory workflow rather than trying to replace its
history.

### A catalog record cannot be archived

Archive active child records first. A Variant with stock on hand cannot be
archived.

### A Product or Variant field is locked

Inventory or transaction history protects identity fields. The first Stock In
also makes cost price restock-managed. Do not attempt to work around these
controls.

### Checkout says the price changed

Review the refreshed cart and current price, confirm the quantity and cash, then
submit again only after the updated information is acceptable.

### The page has no styling or interactive behavior

Confirm frontend assets were built with `npm run build`, or run the Vite
development command from Section 2 in a separate terminal.

## 19. Data-Safety Reminders

- Use the normal application workflows for every stock change.
- Never edit `current_stock` directly.
- Do not delete or rewrite Stock Movements, Stock In receipts, Sales, or Sale
  Items.
- Keep assigned passwords and environment files private.
- Do not include passwords, tokens, purchase costs, or private configuration in
  screenshots.
- Verify the visible Category, Product, Variant identity, unit, quantity, price,
  and cash before submitting a transaction.
- Use an Admin account only for Admin tasks; authorization is enforced by the
  server.

## 20. Current System Boundaries

The current interface does not provide:

- Public registration or password recovery.
- Supplier management.
- Discounts or promotions.
- Credit or utang sales.
- Returns or refunds.
- Voiding a Sale does not issue a refund or cash-out.
- A unified inventory Movement History or Recent Stock Activity dashboard.
- Unit conversion.
- Multiple store locations.
- Cost-of-goods-sold, profit, or inventory-valuation reports.
- PDF receipt generation; receipt output uses browser printing.

There is no general UI for editing or deleting immutable sales, stock receipts,
or damage evidence. Screenshots have not been captured as part of this guide
update. Do not present the unavailable features above as implemented.

## 21. Future Screenshot Checklist

Screenshots remain pending and were not captured for this guide update. Capture
them only after checking that no credential, token, cost, or private client
information is visible.

Suggested evidence:

1. Login page without entered credentials.
2. Admin Dashboard and Staff Dashboard showing role-specific content.
3. Categories, Products, and Variants lists, plus Product detail with per-Variant
   whole/fractional stock and status.
4. Admin Opening Inventory and Stock Correction screens.
5. Stock In form and receipt detail.
6. POS with a closed register, the open-register state, and a reviewed cart.
7. Completed sale receipt and Sales History.
8. Voided Sale receipt/history showing void status, reason, actor, and time;
   optionally capture the Admin Void Sale action before submission.
9. Purchase Orders list, create/edit screen, and order detail.
10. PO receiving screen showing accepted quantity and damaged quantity/note.
11. Follow-up PO screen and its source/child context.
12. Reports index, Sales Summary, Product Sales, Inventory Report, Low Stock
    Report, Pending Purchase Orders, Unfulfilled Items, Restocking, and Damaged
    Items reports.
13. Admin User Management list/search, create form, profile edit, role change,
    archive/reactivate actions, and password reset form.
14. Admin Audit Logs page showing the filters and representative account
    lifecycle events.
15. Responsive mobile navigation.

Use consistent browser dimensions, readable demo records, and short captions
that state what the screenshot proves. Crop or retake any image that exposes a
password, submission token, checkout token, environment value, or unrelated
personal information.
