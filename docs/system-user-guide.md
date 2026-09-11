# 3A TrackPro — System User Guide

This guide describes the features implemented at application checkpoint
`d78f15cdf1f09a745afc9c69108789d40428e1ff` (`Improve whole-quantity stock
display`). It is intended for classroom demonstrations, Admin users, Staff
users, and future user-guide screenshot preparation.

## 1. About TrackPro

3A TrackPro is a Hardware Store Sales and Inventory Management System for a
single store location. It organizes the product catalog, tracks stock changes,
records cash sales, preserves receipts and sales history, and provides current
inventory and sales summaries.

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

Only active accounts may use the application. TrackPro has no public
registration, forgot-password, password-reset, or browser-based account setup
page. Ask the project administrator if an assigned account cannot sign in.

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

## 4. User Roles and Access

| Area | Admin | Staff |
|---|---:|---:|
| Dashboard | Yes | Yes |
| Seven-day sales trend | Yes | No |
| Reports | Yes | No |
| POS and checkout | Yes | Yes |
| Sales History and receipts | Yes | Yes |
| Browse Categories, Products, and Variants | Yes | Yes |
| Create, edit, archive, and reactivate catalog records | Yes | No |
| View catalog cost price | Yes | No |
| Opening Inventory | Yes | No |
| Stock In | Yes | Yes |
| View Stock In purchase costs and totals | Yes | Restricted for Staff |
| Stock Correction | Yes | No |

Staff users see only the active catalog hierarchy. Admin users can filter
catalog records by active, archived, or all statuses and see the available
management actions.

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

## 9. Recording a Stock Correction — Admin Only

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
and verify the latest state before trying again.

## 10. Making a Cash Sale in POS — Admin and Staff

1. Open **Sales → POS**.
2. Use **Search catalog** to find a Product, Variant identity, or unit.
3. Check the unit, quantity mode, available stock, and selling price.
4. Select **Add to cart**. Out-of-stock items cannot be added.
5. Enter the required quantity for each cart line.
6. Remove any unwanted line with **Remove**.
7. Verify the two calculated areas: line **Estimate** and **Estimated total**.
8. Enter **Cash tendered**.
9. Confirm that the estimated change is correct.
10. Select **Checkout** once.

Whole-mode items require whole quantities. Fractional-mode items accept up to
three decimal places. Cash must cover the authoritative sale total, and stock
cannot become negative.

The backend rechecks current stock and selling prices during checkout. If a
price changed while the cart was open, TrackPro refreshes the price and asks the
user to review the cart. A successful checkout creates one completed Sale, one
Sale Item per distinct Variant, and one linked `SALE` stock movement per item.

TrackPro currently supports cash sales only. It does not support discounts,
credit or utang, returns, refunds, or sale voiding.

## 11. Viewing Receipts and Sales History

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

## 12. Using the Dashboard

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

## 13. Using Reports — Admin Only

Open **Main → Reports** to view the **Sales Summary**.

1. Choose **Date from** and **Date to**.
2. Optionally select a Cashier.
3. Select **Apply filters**.
4. Use **Reset** to return to the default range.

The default report covers today and the previous six Manila calendar days. A
custom range is inclusive and may cover at most 366 calendar days.

The report contains:

- Completed Sales Total.
- Completed Transactions.
- Daily Sales, including selected days with zero completed Sales.
- Quantity Sold by Unit, keeping units such as piece, sheet, metre, and kilogram
  separate.

Only completed Sales contribute. Reports do not calculate profit, cost of goods
sold, or inventory valuation.

## 14. Suggested Teacher-Demo Flow

Before presenting, use only approved project/demo records and quantities. A
short rehearsal can follow this order:

1. Sign in as Admin and show the role-labelled navigation.
2. Open Categories, Products, and Variants; demonstrate search and filters.
3. Point out whole stock without `.000` and fractional stock with three decimal
   places.
4. Show an initialized out-of-stock Variant and an existing low-stock Variant.
5. Record one approved Stock In receipt through the normal UI.
6. Recheck the Variant's increased stock.
7. Make one approved cash sale with a whole item and a fractional item.
8. Verify the receipt, payment, and change.
9. Reopen the transaction from Sales History.
10. Show the changed Dashboard inventory indicators and recent Sale.
11. Open Reports and use a date range containing the Sale.
12. Sign out.

Do not rehearse against a preserved test database. Do not delete or rewrite
legitimate history after a rehearsal; successful Stock In and Sale records are
valid append-only project/demo history.

## 15. Messages and Common Problems

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

## 16. Data-Safety Reminders

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

## 17. Current System Boundaries

The current interface does not provide:

- User Management screens.
- Public registration or password recovery.
- Supplier management.
- Purchase orders.
- Discounts or promotions.
- Credit or utang sales.
- Returns or refunds.
- Sale voiding or `SALE_VOID` stock restoration.
- Unit conversion.
- Multiple store locations.
- Cost-of-goods-sold, profit, or inventory-valuation reports.
- PDF receipt generation; receipt output uses browser printing.

Do not present these as implemented features.

## 18. Future Screenshot Checklist

For future Tracker #18 work, capture screenshots only after checking that no
credential, token, or private client information is visible.

Suggested evidence:

1. Login page without entered credentials.
2. Admin Dashboard.
3. Staff Dashboard showing the reduced navigation.
4. Categories list.
5. Products list.
6. Variants list showing whole and fractional current-stock presentation.
7. Opening Inventory index or form using approved demo data.
8. Stock In form and a completed Stock In detail page.
9. Stock Correction form or history using approved demo data.
10. POS catalog and reviewed cart before checkout.
11. Completed sales receipt without purchase cost.
12. Sales History filters and result.
13. Admin Sales Summary report.
14. Responsive mobile navigation.

Use consistent browser dimensions, readable demo records, and short captions
that state what the screenshot proves. Crop or retake any image that exposes a
password, submission token, checkout token, environment value, or unrelated
personal information.
