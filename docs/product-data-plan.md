# 3A TrackPro — Product Data Plan

## Purpose and Tracker #4 status

Tracker #4 is product-data **planning**, based on the approved source audit and
team normalization decisions. This plan and the reconciled local staging dataset
are ready for formal closeout review; they do not authorize catalog entry or
change PROJECT_STATUS.md. HOLD records do not prevent planning completion when
all source evidence is accounted for and unresolved decisions remain visible.
Formal completion remains a separate one-file status closeout after review.

## Source provenance and accounting

Local-only source: `.local-source/3A-DURIAN-PRICING.pdf`, **22 pages**.
SHA-256: `656355b98027a4c366ee48ecca2dc04d313d9ccb30a9d6b103d48650206de064`.
The source is ignored and untracked. The full derived real-price dataset also
remains ignored and untracked; neither belongs in Git. This document records
structure and decisions, not the complete item-by-item price list.

| Source-audit measure | Count |
| --- | ---: |
| Main group headings | 59 |
| Printed item rows | 321 |
| Printed price positions, after matrix/alternate-basis expansion | 400 |
| Numeric price positions | 312 |
| Blank price positions | 88 |
| Printed item rows with no numeric price anywhere | 31 |

These are **source-audit counts**, not final catalog counts: 321 source rows do
not mean 321 Products, and 400 positions do not mean 400 Variants. Blank cells
belonging to printed items are retained; wholly empty table spacer rows are not
items. A wrapped item description remains one printed item row. Colors or
possible stock units are not expanded into invented source price positions.

## Current catalog mapping

The existing hierarchy is **Category → Product → ProductVariant**. Categories
are planning groupings; Products represent actual item families; Variants carry
`size`, `type_series`, `thickness`, `unit`, `quantity_mode`, selling/reference
cost prices, threshold, and the backend-authoritative stock pool.

The exact Variant identity is **(product_id, size, type_series, thickness, unit)**.
Quantity mode and price are not identity fields. The migration has this unique
key; the catalog request also checks case-insensitive identity and normalizes
input. Empty identity components must eventually be explicitly accepted as not
applicable, rather than concealing unknown data. Planning `product_key` is a
readable family key, not a database ID or an authorization to insert.

Source inspection references:
[Variant migration](../database/migrations/2026_09_05_000003_create_product_variants_table.php),
[Variant request](../app/Http/Requests/StoreProductVariantRequest.php), and
[requirements baseline](requirements.md). No application or database execution
is required for this planning work.

## Planning categories and families

These are team/project categories, not category labels asserted by the PDF.
Every local record preserves its original `source_group` separately. Counts
below are staged price positions, including blanks and alternate bases.

| Proposed category | Representative planned families | Positions |
| --- | --- | ---: |
| Steel Pipes & Tubes | G.I./B.I. Pipe, B.I. Tubular, G.I. S-Tube | 56 |
| Steel Bars & Sections | Square, Channel, Deformed, Plain Round, Angle and Flat Bar | 51 |
| Roofing & Sheet Metal | Steel Deck, Plain Sheet, Metal Cladding, G.I./B.I. C-Purlin | 23 |
| Wire, Screens & Netting | Cyclone Wire, Steel Matting, screens, Orchid's Net, Protective Net | 47 |
| Ceiling & Framing | Wall Angle, S-/D-Furring, C-Channel, Stud, Batten, W-Clip | 8 |
| Insulation & Coverings | Insulation Foam, Tent Black | 6 |
| Rope | Nylon Rope | 8 |
| Drainage & Sanitary | Culvert, Sink Stallion | 11 |
| Welding Supplies | Welding Rod | 6 |
| Plumbing Pipes, Fittings & Valves | Distinct G.I., PPR, PVC and P.E. pipes/fittings; valve families | 148 |
| Nails & Fasteners | Common, Finishing and Umbrella Nail | 24 |
| Boards & Panels | Plywood Ordinary, Plywood Marine, Plyboard, GRC Board | 12 |

Group actual families before mapping dimensions/types into Variant fields.
G.I./B.I. pipe schedules belong in `type_series`; labeled fitting series and
elbow angles are retained there. Different pipe materials remain distinguishable.
A section heading does not automatically become a Product: STOP COCK and CHECK
VALVE under BALL VALVE remain separate families; BALL COCK and UNIDEX CHINA GATE
VALVE under PPR are not PPR Pipe variants. Their inherited section size requires
review. Metal Furring's actual item types and Plyboard are also separated.

## Units and quantity modes

| Evidence or team proposal | Candidate unit | Candidate quantity_mode | unit_status |
| --- | --- | --- | --- |
| Generic discrete/fixed pipes, bars, tubulars, purlins, culverts, sinks, fittings, valves, clips | piece | whole | proposed |
| Named Plain Sheet, Steel Matting, Plywood, Plyboard, GRC Board | sheet | whole | proposed |
| Explicit PRICE PER KILO | kg | fractional | candidate_from_source |
| Explicit PER METER / METER price basis | m | fractional | candidate_from_source |
| Explicit PER ROLL / ROLL price basis | roll | whole | candidate_from_source |

Quantity-mode mappings are team proposals (`quantity_mode_status = proposed`),
even where a selling basis is printed. Generic inferred units are never marked
source-confirmed. Cyclone Wire's unspecified selling basis remains HOLD with
blank unit/mode. The 12 blank nail PRICE PER BOX positions remain HOLD with
blank unit/mode and `review_required` statuses: box is not a supported unit,
package contents are unknown, and no box-to-kg conversion is assumed.

## Price, cost, currency, stock and threshold boundaries

Numeric source PRICE is a **candidate selling_price**, by team decision, never
purchase cost. `source_price` preserves the extracted numeric source text,
including punctuation/internal spacing; `selling_price` uses exact decimal text.
Formatting may remove thousands separators or internal price spacing and pad
an integer to two decimal places. It must not change value or silently round.
Source-price spacing changes are noted. Clear printed bases use
`candidate_from_source`; unresolved or merely proposed bases use
`selling_price_status = review_required`.

Application currency assumption = **PHP** (Philippine peso), from business/team
context. The PDF has no explicit currency label: `source_currency` is blank in
all 400 records. Do not claim it printed PHP or ₱.

`cost_price` is blank and `cost_price_status = not_provided_by_source` throughout.
Unknown cost may become application NULL; never derive it from selling price.
`low_stock_threshold` is blank and its status is `not_provided_by_source`
throughout. A later explicit threshold decision is required; the schema's zero
default is not source evidence or permission to invent a threshold.

Neither `current_stock` nor an opening-stock quantity is derived or included in
the CSV. Later physical counting and separately authorized Admin Opening
Inventory establish opening stock, exactly once through INITIAL_STOCK, including
zero where legitimate. Catalog planning does not initialize stock or bypass
movement history.

For every blank source-price position, both price fields stay blank,
`selling_price_status = missing_source_price`, and `review_status = hold`.
Never substitute zero, an average, another size's price, or a nearby cell.

## Alternate bases and source ambiguities

There is no unit conversion. One physical stock pool must not be duplicated
merely because the PDF offers two price bases. For THICK SCREEN, THIN SCREEN,
INSULATION FOAM and TENT BLACK, the meter record is the preferred operational
candidate; the roll alternative stays HOLD unless separately stocked full rolls
are confirmed. This assumption grants no entry approval.

MOSQUITO SCREEN, ORCHID'S NET, PLASTIC SCREEN, NYLON ROPE, P.E. pipe/options and
METAL CLADING retain HOLD where stock-pool identity is uncertain. Orchid's Net's
BLACK AND GREEN header does not establish separate color stock pools. Nail box
alternatives also need package and stock-pool clarification. `stock_pool_note`
and `duplicate_group` connect the relevant evidence; no conversion ratio is
calculated from prices or printed roll lengths.

The P.E. table places fittings beneath PRICE PER ROLL / PER METER. Both column
positions and their headers/bases remain source evidence. For coupling, elbow,
tee, male/female adaptors, male/female elbows, male/female tees and plug, the
team proposes **piece / whole**, with `unit_status = proposed` and the note
`source table header is ambiguous for fitting rows`. All remain HOLD; the PDF
does not explicitly say per piece. P.E. Black 90m/150m roll rows and generic
Black/Blue meter options likewise remain stock-pool review items.

B.I. Tubular and G.I./B.I. CEPURLANES columns 1.2 and 1.5 are proposed as
`thickness`, with `identity_status = review_required`. Steel Deck's 0.8 and 1.0
have the same unresolved dimension-unit status. Do not append mm without
explicit source evidence. Composite dimensions remain intact where splitting
would require assumptions. Unlabeled board dimensions, color-only Square Bar
sizes, Plyboard's missing size, and unlabeled PVC series remain HOLD. Blank
series must not be silently inferred as Series 900.

Always retain original wording in `source_group` and `source_text`. Normalized
fields may propose METAL CLADING → Metal Cladding, CEPURLANES → C-Purlin,
UNOIN → Union, and Greeen → Green, with `normalization_notes`. Layout whitespace
may be collapsed and wrapped descriptions joined; source words and numeric
price text are not replaced by corrected spellings.

## Duplicate and collision strategy

Never deduplicate source evidence to make the staging count resemble catalog
counts. Compare tentative `(product_key, size, type_series, thickness, unit)`
identities after trimming and case folding; repeat using actual catalog
normalization/collation before any future entry. Resolve synonyms, spelling,
fraction notation and unlabeled series manually before choosing canonical
Products/Variants. No existing database identities were queried.

Initial exact tentative comparison finds **10 collision groups / 20 records**:
the two source price positions for each of the ten P.E. fitting rows map to the
same proposed per-piece identity. Each member is HOLD and carries an
`IDENTITY-...` duplicate group, alongside stock-pool notes. No other exact
case-folded tentative collision was found; this is not proof that semantic
near-duplicates or existing catalog conflicts are absent. `POOL-...` groups
identify alternate-basis risks, not confirmed duplicates. Future decisions may
mark a retained evidence record `excluded_from_entry`; they must not erase it,
merge prices blindly, or create duplicate physical balances.

## Staging schema and reconciliation

Local dataset: `.local-source/3A-DURIAN-PRICING-staging.csv` (ignored/untracked).
Public schema: [product-data-staging-template.csv](product-data-staging-template.csv)
(header only, no real item rows/prices). Both use the same 33 columns in the
exact order below:

```text
staging_id,source_document,source_page,source_row_id,source_group,source_text,source_column,source_price_label,source_price,source_price_basis,source_currency,proposed_category,category_status,product_key,product_name,size,type_series,thickness,unit,quantity_mode,selling_price,selling_price_status,cost_price,cost_price_status,low_stock_threshold,low_stock_threshold_status,identity_status,unit_status,quantity_mode_status,stock_pool_note,duplicate_group,normalization_notes,review_status
```

One record represents one printed price position. Stable `P01-R001-C01` IDs
identify page, top-to-bottom printed item row within that page, and left-to-right
price position; `P01-R001` links positions from one item. IDs do not change when
normalization proposals change. `source_text` is the printed item description;
`source_column` retains the active table/subheader followed by ` | ` and the
selected column label. `source_price_label` isolates that label (including
matrix dimension labels); `source_price_basis` is blank unless explicitly
printed in the column/header or item wording. Blank proposed fields are not
implicitly confirmed as not applicable.

Programmatic reconciliation of the local file: **400 data records + one header,
321 unique source_row_id values, 400 unique staging_id values, 312 numeric source
prices, 88 blank source prices, and 31 wholly unpriced source rows**. All 22 pages
and 59 main groups are represented. Numeric source evidence is also compared
page-by-page against extracted PDF price text. Currency, costs and thresholds
are blank in every record; no current-stock or opening-quantity column exists.

## Review vocabulary and entry readiness

Overall vocabulary: `normalized_pending_review`, `hold`, `approved_for_entry`,
`excluded_from_entry`. Initial counts: **205 normalized_pending_review,
195 hold, 0 approved_for_entry, 0 excluded_from_entry**. HOLD covers missing prices
and unresolved source identity, basis, package or stock-pool ambiguities.
`normalized_pending_review` means a documented candidate awaiting team decisions,
not authorization to enter it. Every record still lacks a threshold decision.

Field-status vocabulary: `candidate_from_source`, `proposed`, `review_required`,
`confirmed`, `not_applicable_confirmed`, `missing_source_price`,
`not_provided_by_source`. No additional field-status value is introduced.

Entry readiness requires reviewed category and actual family; resolved identity
components (or explicit not-applicable decisions); confirmed supported unit and
quantity mode; one distinct physical stock pool; a confirmed positive selling
price and application currency decision; accepted nullable unknown cost or
verified cost; an explicit valid threshold; and resolved duplicate/collision
checks. Whole-mode thresholds must be whole numbers. Separate authorization is
still required to enter data. Stock initialization is a subsequent workflow,
not a value copied from this source. No initial record is approved_for_entry.

## Test Hammer, scope and remaining clarification

Test Tools / Test Hammer / 16oz · Claw is historical local development evidence,
not the preferred representative product-data example going forward. Do not
delete, rename, reuse it as a real item, or rewrite its Sales/StockMovement
history. No database access is involved. Future documentation/demo examples
should prefer approved real-source families, such as G.I. Pipe or Plain Sheet,
after relevant entry decisions are reviewed.

Tracker #4 accounts for the real source, plans categories/families, documents
normalization and stock-pool risks, exposes missing/ambiguous data as HOLD, and
provides the private staging file and public schema without inventing cost,
threshold or opening stock. HOLD rows do not block planning closeout. Remaining
clarification concerns selling bases, separate full-roll stock, P.E. fittings,
box packaging, dimensions/series/identity, missing prices, and all thresholds.

This work does not start #5 UI/UX Planning or #7 Test Case Preparation, perform
catalog loading or Opening Inventory, implement SALE_VOID or User Management,
or modify application source. No tests, builds or databases are accessed.
PROJECT_STATUS.md and README.md remain unchanged. Formal #4 completion awaits
the separate approved status closeout after review.
