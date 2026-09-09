# 3A TrackPro — Test Case Preparation Baseline

## 1. Status, purpose, and evidence limits

This is the Tracker #7 **RETROSPECTIVE / PROJECT-DERIVED TEST CASE
BASELINE**. Substantial automated testing and recorded browser verification
existed before this formal artifact. This document organizes that evidence into
an executable, requirement-traceable blueprint; it does not claim the cases were
prepared before implementation or that any test was rerun for Tracker #7.

Tracker #7 remains **IN PROGRESS** at this documentation checkpoint. The formal
completed count remains **13 / 23**. Formal completion requires this artifact to
be fully reviewed, committed and pushed, followed by a separately approved
`PROJECT_STATUS.md` closeout. The latest completed application checkpoint
remains `31b5b95f8d4de3136c15924bc541b52a6d2fa846` — `Add dashboard and
sales reports`.

This baseline prepares test cases, data, preconditions, steps, expected results,
requirements/rule traceability, existing evidence, and later execution targets.
It records no new runtime result, manual session, screenshot, defect, client
approval, security certification, or accessibility certification. SALE_VOID,
User Management, and Tracker #18 User Guide & Screenshots remain future work.

## 2. Tracker boundaries

- **#7 Test Case Preparation** defines what and how to verify.
- **#15 Functional Testing** will later execute normal functional/manual
  acceptance cases and record actual results.
- **#17 Edge Case & Permission Testing** will later execute negative, boundary,
  lifecycle, authorization, permission, and integrity-focused cases.

Preparation does not complete #15 or #17. A case can cite strong existing
automation and still remain **Prepared / Not Yet Executed** when later manual
acceptance has a distinct purpose. Existing automation should be reused rather
than duplicated manually when no visual or acceptance dimension remains.

## 3. Historical software and testing baseline

| Evidence | Recorded baseline | Meaning |
| --- | --- | --- |
| Ordinary suite | **191 tests / 2,023 assertions**, 27.576 seconds | Historical run using isolated SQLite `:memory:`; not rerun for #7 |
| Application routes | **40** | Historical application-route baseline; not rerun for #7 |
| Ordinary test methods | **191 declared methods** | Static source observation during the approved #7 audit; not a runtime result |
| Dedicated MySQL methods | **23 declared methods** | Static source observation; not a new MySQL result |

Runtime counts can differ from simple method counts when datasets/providers are
used. The historical run is the only runtime result claimed here.

## 4. Evidence classes, targets, and statuses

### 4.1 Evidence classes

| Class | Meaning |
| --- | --- |
| A — Automated Direct | An existing Feature, Unit, or dedicated MySQL test directly asserts the behavior. |
| B — Automated Partial | Related automation exists, but a UI, integration, or boundary dimension remains. |
| C — Historical Manual | Recorded browser, print, or manual evidence exists but was not rerun for #7. |
| D — Prepared / Not Yet Executed | A formal case has been prepared for later #15 or #17 execution without direct current evidence. |
| E — No Clear Current Evidence | No direct automated or historical evidence was identified. |

Class B, C, D, or E must not be silently upgraded to A. Guarded MySQL evidence
is always identified separately from ordinary SQLite automation.

### 4.2 Controlled execution targets and statuses

Targets are `#15 Functional Testing`, `#17 Edge Case & Permission Testing`,
`Guarded MySQL Verification`, `Review Only`, and `Existing Automation
Reference`. Statuses are:

- **Existing Automated Evidence** — historical automated evidence is referenced;
- **Historical Manual Evidence** — previously recorded manual evidence only;
- **Prepared / Not Yet Executed** — future #15/#17 execution has not occurred;
- **Review Evidence / Not Runtime-Testable** — verified through source/document review; and
- **Deferred Pending Implementation** — a future expectation cannot yet be tested.

No newly prepared case is marked Passed.

### 4.3 Formal case counts

| Execution target | Cases |
| --- | ---: |
| #15 Functional Testing | 30 |
| #17 Edge Case & Permission Testing | 37 |
| Guarded MySQL Verification | 8 |
| Review Only | 4 |
| **Total** | **79** |

Evidence-class distribution across the 79 formal cases is summarized after the
catalog and must be updated only when evidence genuinely changes.

## 5. Safe test data and shared preconditions

### 5.1 Database and historical-data boundaries

- Ordinary automation uses isolated SQLite `:memory:`.
- Guarded MySQL work may use only the scoped `mysql_testing` infrastructure and
  `trackpro_test`, and only when explicitly authorized later.
- `trackpro_local` contains legitimate local data and must never be routinely
  reset, reseeded, or treated as disposable.
- Test Hammer has legitimate historical Sale and StockMovement evidence. It
  must not be deleted, rewritten, renamed, or repurposed.
- Product-price staging data is planning data and is not automatically loaded
  for #7 or later test execution.
- No private credential, secret, or database connection value belongs in a case.
- Cleanup must not edit or delete immutable Sales, SaleItems, Restocks,
  RestockItems, or StockMovements.

No database was accessed while preparing this artifact.

### 5.2 Reproducible synthetic data vocabulary

Later executors must create isolated synthetic data through the approved test
harness or a specifically authorized disposable test environment:

| Key | Definition |
| --- | --- |
| `U-ADMIN` | Active user with application role Admin |
| `U-STAFF` | Active user with application role Staff |
| `U-DISABLED` | Persisted user with disabled status |
| `C-ACTIVE` / `C-ARCHIVED` | Synthetic active/archived Category |
| `P-ACTIVE` / `P-ARCHIVED` | Synthetic Product under the stated Category |
| `V-WHOLE` | Active initialized `piece` Variant using whole mode, known price/threshold/stock |
| `V-FRAC` | Active initialized `kg` or `m` Variant using fractional mode, known price/threshold/stock |
| `V-NEW` | Active zero-stock Variant with no StockMovement history |
| `R-ONE` | Synthetic Stock In receipt with explicit token, reference, actor, items, quantities, and costs |
| `S-ONE` | Synthetic completed cash Sale with explicit token and immutable snapshots |
| `TZ-MANILA` | Controlled dates around Asia/Manila start-of-day boundaries |

Names, receipt numbers, and tokens must be unique per case. Money and inventory
inputs are decimal strings, not binary floating-point values. Where cleanup is
necessary, use transaction rollback or disposable fixtures rather than rewriting
historical records.

## 6. Case design and ID convention

Stable IDs use `TC-<MODULE>-<NNN>` with modules `AUTH`, `CAT`, `PROD`, `VAR`,
`OI`, `STKIN`, `CORR`, `POS`, `SALES`, `DASH`, `REP`, `NAV`, `UI`, `A11Y`,
`PRINT`, `RES`, `INT`, and `REVIEW`. IDs remain stable if evidence or status
changes.

Every row below supplies:

- ID, module, scenario/title, requirements/rules, type, and role;
- preconditions/test data, concrete steps, and expected result;
- existing evidence key and evidence class; and
- execution target, status, and cleanup/notes.

Evidence keys resolve to exact source methods in section 14. Shared data keys
resolve through section 5; a row adds any case-specific state.

## 7. Functional case catalog — later #15

All cases in this section have execution target **#15 Functional Testing** and
status **Prepared / Not Yet Executed**.

| ID / module / scenario | References; type; role | Preconditions and test data | Steps | Expected result | Existing evidence; class | Notes / cleanup |
| --- | --- | --- | --- | --- | --- | --- |
| TC-AUTH-001 — Authentication — Admin login | FR-AUTH-01; functional; Admin | `U-ADMIN`; assigned non-secret username/password | Open Login; enter username/password; submit. | Session ID changes and Dashboard opens as Admin. | EV-AUTH-LOGIN; A | Log out through POST. |
| TC-AUTH-002 — Authentication — Staff login | FR-AUTH-01; functional; Staff | `U-STAFF` | Repeat TC-AUTH-001 as Staff. | Dashboard opens with Staff-appropriate content. | EV-AUTH-LOGIN; A | Log out through POST. |
| TC-DASH-001 — Dashboard — operational overview and follow-up | FR-DASH-01–04, BR-09; functional; Admin/Staff | Synthetic completed/non-completed Sales and low/out-of-stock Variants | Open Dashboard; compare four cards and both lists to fixture; follow one receipt and one low-stock link. | Counts/populations are correct; recent list is completed-only and at most five; links open intended pages. | EV-DASH; A | Read-only. |
| TC-DASH-002 — Dashboard — role-specific trend | FR-DASH-05–06, BR-12–13; functional; Admin/Staff | `TZ-MANILA` Sales across seven days including an empty day | Open as Admin, then Staff. | Admin sees seven oldest-to-newest zero-filled completed-sales days; Staff does not see the trend. | EV-DASH; A | Read-only. |
| TC-CAT-001 — Categories — create and edit | FR-CAT-01; functional; Admin | `U-ADMIN`; unique padded/mixed-case name | Create Category; verify normalized name; edit to another unique name. | Active Category is created and updated; success feedback and list values are correct. | EV-CAT; A | Archive only in TC-CAT-002. |
| TC-PROD-001 — Products — create and edit | FR-CAT-02; functional; Admin | `C-ACTIVE`; unique Product name | Start Create from Category row; submit; edit name and move to another safe active Category. | Route parent is authoritative; normalized Product and safe move persist. | EV-PROD; A | No historical activity on Product. |
| TC-VAR-001 — Variants — whole-mode creation | FR-CAT-03, FR-INV-02–03; functional; Admin | `P-ACTIVE`; unique identity; `piece`, whole mode, valid prices/threshold | Create Variant. | Variant is active with zero stock, exact prices/threshold, and no movement. | EV-VAR; A | Catalog form cannot set stock. |
| TC-VAR-002 — Variants — fractional creation | FR-CAT-03, FR-INV-02–03, NFR-DEC-01; functional; Admin | `P-ACTIVE`; unique identity; `kg` or `m`, fractional mode; three-decimal threshold | Create Variant and reopen edit form. | Unit/mode and exact decimals are retained. | EV-VAR; A | Catalog form cannot set stock. |
| TC-VAR-003 — Catalog — safe Staff browse | FR-CAT-05, NFR-SEC-01–02; functional; Staff | Active and archived hierarchy; costs present | Browse Categories, Products, Variants as `U-STAFF`. | Only active hierarchy is shown; cost/status mutation controls are absent. | EV-CAT-AUTH; A | Direct permission tested separately. |
| TC-OI-001 — Opening Inventory — normal completion | FR-OPEN-01–03, BR-05; functional; Admin | `V-NEW`; quantity `12`; reason `Initial physical count` | Open eligible Variant; submit quantity/reason; return to index. | Stock becomes `12.000`; one trusted INITIAL_STOCK movement exists; success and initialized state appear. | EV-OI; A | Do not delete movement. |
| TC-STKIN-001 — Stock In — Admin multi-item receipt | FR-STOCKIN-01–05, BR-06; functional; Admin | `V-WHOLE`, `V-FRAC`; unique receipt token; two quantities/costs | Open history; create receipt; add two rows; submit; open detail. | One immutable receipt commits; balances/cost reference increase exactly; one RESTOCK movement per item; Admin sees historical costs. | EV-STKIN; A | Preserve immutable receipt. |
| TC-STKIN-002 — Stock In — Staff current-cost entry | FR-STOCKIN-01–05, FR-CAT-05; functional; Staff | Initialized Variant; unique token; current purchase cost | As `U-STAFF`, enter and submit a new receipt, then view history/detail. | Entry accepts current purchase cost; receipt commits; existing/historical purchase costs are hidden after submission and elsewhere in Staff UI. | EV-STKIN-COST; A | Do not summarize as “Staff cannot enter cost.” |
| TC-CORR-001 — Stock Correction — valid physical adjustment | FR-CORR-01–04, BR-07; functional; Admin | Initialized active Variant; current movement ID; changed nonnegative target; reason | Open correction form; submit target/reason/version; view history. | Stock equals physical target; cost is unchanged; one exact CORRECTION movement and success feedback appear. | EV-CORR; A | Preserve history. |
| TC-POS-001 — POS — successful cash sale | FR-POS-01–07, BR-01–03, BR-10; functional; Admin/Staff | `V-WHOLE`, `V-FRAC`; stock sufficient; known prices; unique token; sufficient cash | Search/add both; set valid quantities; enter cash; submit; open receipt. | Server prices/totals/change govern; stock deducts exactly; one Sale, item per distinct Variant, and SALE movement per item exist; receipt transition succeeds. | EV-POS; A | Do not expose cost/token. |
| TC-SALES-001 — Sales History — status-neutral browse | FR-SALES-01–02; functional; Admin/Staff | Historical Sales of different statuses/users | Open Sales History; browse pages; filter by valid receipt/cashier/date. | Both roles can browse all historical Sales regardless of recorder; filters narrow results and preserve pagination state. | EV-SALES; A | “Completed transactions” eyebrow is a known wording risk, not semantics. |
| TC-SALES-002 — Receipt — immutable detail | FR-SALES-03, FR-SALES-05, BR-04; functional; Admin/Staff | `S-ONE`; later rename/reprice catalog fixture | Open Sale detail after catalog changes. | Receipt retains Product/Variant/unit/quantity/price snapshots and payment evidence; no cost, token, or movement internals appear. | EV-SALES-RECEIPT; A | Read-only. |
| TC-PRINT-001 — Receipt — browser reprint | FR-SALES-04, NFR-PRINT-01; print; Admin/Staff | Existing historical Sale | Open receipt; invoke Print; cancel or complete print; return and reopen. | Same receipt is printable/reprintable without a new Sale, stock change, movement, or audit write. | EV-SALES-RECEIPT plus HM-PRINT; C | Do not require physical paper. |
| TC-REP-001 — Reports — valid range/cashier filter | FR-REP-01–06, BR-12–14; functional; Admin | `TZ-MANILA`; completed/non-completed Sales; enabled/disabled cashiers; immutable units | Open Reports; use default; apply valid range/cashier; Reset. | Default is seven Manila days; filtered completed-only totals, zero days, and unit groups are exact; private/cost/profit/export data is absent. | EV-REP; A | Read-only. |
| TC-CAT-002 — Catalog — valid archive/reactivate lifecycle | FR-CAT-01–04; lifecycle; Admin | Empty active Category or zero-stock historical Variant with active ancestors | Archive from list; verify state; reactivate. | Lifecycle succeeds without hard delete or history rewrite; success feedback is shown. | EV-CAT-LIFE; A | Current action has no named confirmation. |
| TC-NAV-001 — Navigation — Admin desktop/mobile destinations | FR-NAV-01–03, NFR-RESP-01; responsive/manual; Admin | `U-ADMIN`; viewport above and below 1024px | Compare desktop sidebar and mobile drawer destinations; open each group. | Exactly 10 destinations appear in each navigation and route to authorized pages. | EV-NAV plus HM-NAV; B | No screenshots required. |
| TC-NAV-002 — Navigation — Staff desktop/mobile destinations | FR-NAV-01–03, NFR-RESP-01; responsive/manual; Staff | `U-STAFF`; viewport above and below 1024px | Repeat TC-NAV-001. | Exactly seven destinations appear; Reports, Opening Inventory, and Stock Correction are absent. | EV-NAV plus HM-NAV; B | Direct denial is in TC-AUTH-005. |
| TC-NAV-003 — Navigation — drawer interaction and breakpoint | FR-NAV-02, FR-NAV-04, NFR-RESP-01, NFR-A11Y-01; responsive/accessibility; Admin/Staff | Viewports 1023px and 1024px | At 1023px open with hamburger; close via X, backdrop, Escape, and link; inspect body/inert/focus; resize to 1024px. | Mobile controls work below `lg`; body locks and background is inert while open; focus enters/returns; desktop sidebar/content offset replace drawer at 1024px. | HM-NAV; C | No claim of complete dialog semantics/focus trap. |
| TC-POS-008 — POS UI — responsive catalog/cart | FR-POS-01, NFR-RESP-01; responsive/manual; Admin/Staff | Populated POS; viewports below and at/above `xl` | Search; add/increment/edit/remove items; inspect totals and layout at both widths. | Dynamic cart/totals follow actions; below `xl` cart follows catalog; at `xl` split layout has sticky cart. | HM-POS; C | Category is currently absent; no corrected behavior expected. |
| TC-STKIN-009 — Stock In UI — dynamic rows | FR-STOCKIN-01, NFR-RESP-01; UI/manual; Admin/Staff | At least two eligible initialized Variants | Add/remove rows; select Variant; enter quantity/cost; submit one intentionally invalid repeated row. | Rows adapt at `lg`, selection reveals implemented context, old input returns; global/available row feedback is visible. | EV-STKIN plus HM-STKIN; B | Category and strong row-error association are currently absent. |
| TC-UI-001 — Shared UI — tables, feedback, and empty states | NFR-RESP-01; UI/manual; Admin/Staff | Fixtures for empty, success, warning, validation, and wide table states | Visit representative catalog/inventory/sales pages at narrow width and trigger non-destructive states. | Wide tables horizontally scroll; messages are visible with text; contextual empty states render. | UI baseline review; B | Observe current conventions, not future standardization. |
| TC-DASH-003 — Dashboard UI — responsive grids | FR-DASH-01–06, NFR-RESP-01; responsive/manual; Admin/Staff | Dashboard fixtures; mobile/desktop widths | Inspect cards, lower panels, tables, and Admin trend at both widths. | Cards/panels reflow as implemented without navigation/content obstruction; wide tables remain scrollable. | HM-DASH; C | No screenshot collection. |
| TC-REP-004 — Reports UI — responsive presentation | FR-REP-02–05, NFR-RESP-01; responsive/manual; Admin | Valid report fixtures; mobile/desktop widths | Apply filters and inspect cards/daily/unit tables. | Filters/cards reflow; tables remain usable by horizontal scrolling; exact values stay visible. | HM-REP; C | No dedicated report print/export expected. |
| TC-A11Y-001 — Accessibility — implemented semantics | FR-NAV-04, NFR-A11Y-01; accessibility/manual; Admin/Staff | Representative forms/nav/status pages | Inspect labels/headings/buttons/links; operate navigation by keyboard; inspect ARIA/inert/current state and quantity help. | Implemented semantic elements, ARIA state, Escape/focus behavior, visible state text, native disabled/read-only, and existing `aria-describedby` help are observable. | EV-NAV plus UI baseline review; B | No WCAG or assistive-technology certification. |
| TC-A11Y-002 — Accessibility — partial error/dynamic behavior | NFR-A11Y-01; accessibility observation; Admin/Staff | Invalid form plus POS/Stock In dynamic activity | Trigger validation and dynamic updates; inspect field association, focus, and announcements. | Visible feedback exists; inconsistent field-error association and unestablished live announcements are recorded accurately. | UI baseline review; E | Corrected associations/announcements are deferred. |
| TC-PRINT-002 — Receipt print — visual state | FR-SALES-04, NFR-PRINT-01; print/manual; Admin/Staff | Representative receipt; browser Print Preview | Open preview; inspect chrome and retained receipt content. | Sidebar/top bar/drawer/backdrop/alerts/controls are hidden; receipt metadata, items, status, and payments remain readable. | HM-PRINT; C | Historical Firefox evidence exists; later run need not use a named client printer. |

## 8. Edge and permission case catalog — later #17

All cases in this section target **#17 Edge Case & Permission Testing** and have
status **Prepared / Not Yet Executed**.

| ID / module / scenario | References; type; role | Preconditions and test data | Steps | Expected result | Existing evidence; class | Notes / cleanup |
| --- | --- | --- | --- | --- | --- | --- |
| TC-AUTH-003 — Authentication — disabled login | FR-AUTH-01–02, NFR-SEC-01; negative; disabled user | `U-DISABLED` credentials | Attempt login. | Generic credential failure; no authenticated session. | EV-AUTH-LOGIN; A | Do not reveal disabled status. |
| TC-AUTH-004 — Authentication — disabled after login | FR-AUTH-02; state/security; Admin/Staff | Log in active synthetic user, then disable through fixture control | Request protected HTML and JSON endpoints. | Session use is denied/invalidated; no protected response remains available. | EV-AUTHZ; A | Test environment only. |
| TC-AUTH-005 — Authorization — guest and Staff route matrix | FR-CAT-05, FR-CORR-01, FR-REP-01, NFR-SEC-01–02; permission; Guest/Staff | Route matrix from `routes/web.php` | As guest request protected pages; as Staff request every Admin-only page/mutation. | Guest is redirected to Login; Staff direct Admin requests return 403; no mutation occurs. | EV-AUTHZ, EV-CAT-AUTH, EV-OI-AUTH, EV-CORR-AUTH, EV-REP-AUTH; A | UI hiding is not the security control. |
| TC-AUTH-006 — Security — logout/mutation CSRF and route verbs | FR-AUTH-04, NFR-SEC-01; security; Admin/Staff | Authenticated synthetic user | Confirm POST logout token; attempt GET logout; submit representative mutation without/with invalid CSRF in browser harness. | GET cannot log out; valid POST works; invalid browser mutation is rejected by CSRF middleware. | EV-AUTH-CSRF, EV-ROUTE-SEC; B | Exact framework status may depend on harness; assert rejection/no mutation. |
| TC-CAT-003 — Categories — normalized duplicate | FR-CAT-01; validation; Admin | Existing `Fasteners` | Submit case/space-equivalent Category. | Validation rejects duplicate; original remains unchanged. | EV-CAT; A | No cleanup write to original. |
| TC-PROD-002 — Products — scoped normalized duplicate | FR-CAT-02; validation; Admin | Same normalized name in one Category and another Category | Duplicate in same Category, then same name in different Category. | Same-parent duplicate fails; distinct-parent identity succeeds. | EV-PROD; A | Remove disposable no-history fixture if harness rolls back. |
| TC-VAR-004 — Variants — duplicate composite identity | FR-CAT-03, FR-INV-02–03; validation; Admin | Existing Product/identity/unit/mode combination | Submit equivalent identity, then vary a valid composite component. | Exact composite duplicate fails; distinct supported identity succeeds. | EV-VAR; A | No stock mutation. |
| TC-CAT-004 — Catalog — archived/inactive hierarchy | FR-CAT-01–05, BR-06–07; lifecycle; Admin/Staff | Archived Category/Product/Variant combinations | Browse and directly request create/edit/operational actions. | Staff sees only active hierarchy; invalid direct operations are denied without mutation. | EV-CAT-AUTH, EV-PROD, EV-VAR; A | Preserve historical rows. |
| TC-VAR-005 — Variants — history-locked identity/unit/mode | FR-CAT-03–04, NFR-DATA-01; business rule; Admin | Variants with Sale, Restock, movement, or nonzero stock history | Attempt identity, unit, quantity-mode, or parent change. | History-sensitive fields remain locked; permitted price/threshold changes remain separate. | EV-VAR; A | Never rewrite history. |
| TC-VAR-006 — Variants — Stock In-owned cost lock | FR-CAT-03, FR-STOCKIN-03–04; state; Admin | Variant with first Stock In | Attempt catalog cost edit, then price/threshold edit. | Cost edit is rejected/disabled; eligible price/threshold changes succeed. | EV-VAR; A | Historical received cost unchanged. |
| TC-CAT-005 — Catalog — archive with active children | FR-CAT-04; business rule; Admin | Category with active Product; Product with active Variant | Attempt each parent archive. | Lifecycle action is rejected with no child/history change. | EV-CAT-LIFE, EV-PROD; A | Current UI submits without named confirmation. |
| TC-VAR-007 — Variants — archive with positive stock | FR-CAT-04, FR-INV-04, BR-01; business rule; Admin | Active Variant with positive stock | Attempt archive. | Archive is rejected; stock and movements remain unchanged. | EV-VAR; A | Zero-stock historical archive is covered by TC-CAT-002. |
| TC-OI-002 — Opening Inventory — repeat/preexisting/history | FR-OPEN-01, BR-05; negative/state; Admin | Separate Variants with prior INITIAL_STOCK, nonzero stock, Restock/Sale/Correction history | Attempt Opening Inventory on each. | Each ineligible attempt is rejected based on authoritative history/state; no new movement. | EV-OI; A | Unavailable UI reason is currently generic. |
| TC-OI-003 — Opening Inventory — zero and quantity-mode boundaries | FR-OPEN-02, FR-INV-03, NFR-DEC-01; boundary; Admin | `V-NEW` whole and fractional fixtures | Submit whole zero, fractional zero, whole fraction, >3 fractional decimals, negative, lexical invalid, overflow. | Zero succeeds exactly once; invalid forms fail without stock/movement change. | EV-OI; A | Use separate rollback fixtures for successful zero cases. |
| TC-OI-004 — Opening Inventory — inactive hierarchy/direct access | FR-OPEN-01, BR-05, NFR-SEC-01; lifecycle; Admin/Staff | Archived ancestor/Variant; Staff actor | Attempt index/action/direct submission. | Staff is forbidden; inactive hierarchy is ineligible; no movement. | EV-OI-AUTH, EV-OI; A | Framework-controlled ineligible response may be 409 where established. |
| TC-STKIN-003 — Stock In — duplicate rows and row cap | FR-STOCKIN-01–02; validation; Admin/Staff | Eligible initialized Variants | Submit same Variant twice; submit 101 rows. | Duplicate and excessive-row requests fail with no receipt/movement/stock change. | EV-STKIN; A | UI supports 1–100 rows. |
| TC-STKIN-004 — Stock In — quantity/cost precision and mode | FR-INV-03, FR-STOCKIN-01, NFR-DEC-01; boundary; Admin/Staff | Whole/fractional Variants | Submit zero/negative/lexical/overflow quantities, whole fractions, >3 decimals, invalid/overflow costs, then valid zero cost. | Invalid inputs fail atomically; valid modes and zero purchase cost behave as implemented; totals round exactly. | EV-STKIN; A | Current Stock In cost is not COGS. |
| TC-STKIN-005 — Stock In — initialization and active-hierarchy recheck | FR-STOCKIN-01–02, BR-06; state; Admin/Staff | Missing opening history or hierarchy changed after form load | Submit receipt. | Locked current state rejects ineligible items and commits nothing. | EV-STKIN; A | Do not rely only on UI option filtering. |
| TC-STKIN-006 — Stock In — equivalent replay | FR-STOCKIN-02, NFR-CON-01; idempotency; Admin/Staff | Previously committed `R-ONE`; identical semantics with order/format normalization | Resubmit equivalent request with same token. | Existing immutable receipt is returned/reported; no duplicate balance, item, or movement effect. | EV-STKIN-REPLAY; A | Preserve original receipt. |
| TC-STKIN-007 — Stock In — semantic token misuse | FR-STOCKIN-02, NFR-SEC-03; negative/idempotency; Admin/Staff | Used token; change actor/header/items/quantity/cost | Resubmit each changed semantic form. | Reuse is rejected safely; fresh review/token behavior appears where implemented; original receipt unchanged. | EV-STKIN-REPLAY; A | Token value must not be disclosed beyond controlled form use. |
| TC-STKIN-008 — Stock In — transactional rollback | FR-STOCKIN-02, FR-STOCKIN-05, NFR-INT-02; integrity; automated harness | Two valid items; inject second movement-write failure through existing test seam | Submit receipt. | Receipt/items/stock/cost/movements all roll back. | EV-STKIN-ROLLBACK; A | Existing automation reference; do not corrupt a manual DB. |
| TC-CORR-002 — Stock Correction — stale and ABA version | FR-CORR-03, BR-07; concurrency/state; Admin | Open form, then create intervening movement; separately return stock to original value with newer movement ID | Submit old version. | Both stale forms fail even if quantity matches an earlier value; winner state remains. | EV-CORR; A | Use controlled fixture transaction. |
| TC-CORR-003 — Stock Correction — no-op | FR-CORR-03; negative; Admin | Fresh movement ID; target equals current stock | Submit with valid reason. | Validation rejects no-op before stale comparison; no movement/write. | EV-CORR; A | Exact current quantity remains. |
| TC-CORR-004 — Stock Correction — unauthorized/inactive actor or hierarchy | FR-CORR-01–02, NFR-SEC-01–02; permission/state; Staff/disabled/Admin | Staff/disabled actor; archived hierarchy fixture | Attempt direct create/store and service-level invalid actor where harness supports it. | Forbidden or validation response occurs; no correction/movement/cost change. | EV-CORR-AUTH, EV-CORR; A | No role spoofing. |
| TC-CORR-005 — Stock Correction — target/reason boundaries | FR-CORR-02, FR-INV-03, NFR-DEC-01; boundary; Admin | Whole/fractional initialized Variants | Submit invalid lexical/array/object/negative/overflow/fractional whole targets and blank/oversized reason. | Each fails safely; valid fractional precision up to three decimals succeeds only in isolated fixture. | EV-CORR; A | Preserve original case stock on failures. |
| TC-POS-002 — POS — insufficient stock | FR-POS-02–04, FR-INV-04, BR-01–02; business rule; Admin/Staff | Requested quantity exceeds authoritative stock | Submit checkout. | Exact availability feedback; no Sale/item/movement/deduction; stock never negative. | EV-POS; A | Client estimate is non-authoritative. |
| TC-POS-003 — POS — underpayment and exact change | FR-POS-07, BR-02; validation; Admin/Staff | Known server total; cash below, equal, and above total | Submit each in isolated case. | Below total is rejected with no mutation; equal gives zero change; above gives exact server change. | EV-POS; A | Pre-submit UI may estimate ₱0.00 for invalid tender. |
| TC-POS-004 — POS — price refresh and unavailable old item | FR-POS-02, BR-02; stale state; Admin/Staff | Build cart; change price or make Variant unavailable through fixture control | Submit old cart. | Changed price is refreshed for review with token retained as implemented; unavailable item is removed with notice; no unsafe checkout. | EV-POS; A | Category remains absent from item context. |
| TC-POS-005 — POS — quantity/tampered fields | FR-POS-02–03, FR-INV-03, NFR-DEC-01; negative; Admin/Staff | Whole/fractional items | Submit invalid types/scales/overflow and spoof price, totals, stock, actor, movement fields. | Invalid/unexpected fields fail; server-authoritative values cannot be overridden; no mutation. | EV-POS; A | Do not use binary floats in expected values. |
| TC-POS-006 — POS — equivalent replay and semantic mismatch | FR-POS-05–06, NFR-CON-01; idempotency; Admin/Staff | Committed `S-ONE`; same token | Replay identical request; then alter actor/tender/items/expected price. | Equivalent replay returns existing Sale without another deduction; changed semantics are rejected and review/fresh-token behavior occurs. | EV-POS-REPLAY; A | Immutable original governs replay. |
| TC-POS-007 — POS — transactional rollback | FR-POS-03, FR-POS-06, NFR-INT-01–02; integrity; automated harness | Multi-item checkout; inject second SALE-movement failure | Submit. | Sale/items/deductions/movements all roll back; stocks remain unchanged. | EV-POS-ROLLBACK; A | Existing automation reference; not a manual DB corruption step. |
| TC-SALES-003 — Sales History — invalid filters | FR-SALES-02, NFR-TIME-01; negative; Admin/Staff | Malformed receipt/date, reversed dates, nonexistent cashier | Apply each filter independently. | Filters fail closed and never fall back to all Sales; no write. | EV-SALES; A | Do not infer completed-only semantics. |
| TC-SALES-004 — Sales History — disabled cashier and status neutrality | FR-SALES-01–02, BR-12; state/semantic; Admin/Staff | Disabled user with historical Sales; mixed Sale statuses | Filter by disabled cashier and browse mixed statuses. | Historical cashier remains selectable/narrow; all statuses remain available. Dashboard/Reports alone are completed-only. | EV-SALES; A | Current eyebrow mismatch is observed, not corrected. |
| TC-REP-002 — Reports — invalid date matrix | FR-REP-02, NFR-TIME-01; negative; Admin | Missing one date, invalid date, reverse range, >366 days | Submit each. | Report fails closed with explicit not-run feedback; current zero-valued summaries may remain. | EV-REP; A | Do not expect future hidden-summary recommendation. |
| TC-REP-003 — Reports — completed-only/privacy/read-only | FR-REP-01, FR-REP-03, FR-REP-06, BR-12, BR-14; permission/integrity; Admin/Staff | Mixed statuses; costs/tokens present | Compare Admin report; attempt Staff direct access; inspect response and route verbs. | Only completed Sales aggregate; Staff gets 403; no cost/profit/token/auth data or mutation/export endpoint appears. | EV-REP, EV-REP-AUTH; A | No report print feature. |
| TC-RES-001 — Resources — controlled forbidden/missing/ineligible access | NFR-SEC-01–02; negative/security; Guest/Admin/Staff | Known forbidden route, malformed/missing ID, directly ineligible resource | Request each exact source-supported path. | Established cases return controlled 403, 404, or 409 without raw errors or mutation. | EV-SALES-AUTH, EV-OI-AUTH, EV-CORR-AUTH; B | Assert only the code established for that route. |
| TC-RES-002 — Routes — immutable/future mutation surfaces absent | FR-CAT-04, FR-SALES-03–04, NFR-DATA-01, BR-04, BR-11; security/review; Admin | Current route table | Attempt only safe route recognition for catalog delete, receipt edit/delete/void, Restock/correction history mutation, public registration/setup. | Routes are absent or method-restricted; no destructive GET mutation exists; SALE_VOID remains unimplemented. | EV-ROUTE-SEC, EV-SALES-AUTH, EV-POS-AUTH; A | Do not probe production or mutate data. |

## 9. Guarded MySQL and integrity cases

These cases are not ordinary SQLite cases. Their target is **Guarded MySQL
Verification**. Existing methods are historical automated evidence and were not
run for #7. Any future execution requires explicit authorization and the
existing database-identity guards.

| ID / module / scenario | References; type; role | Preconditions and data | Steps | Expected result | Existing evidence; class | Status / notes |
| --- | --- | --- | --- | --- | --- | --- |
| TC-INT-001 — Database identity guard | NFR-CON-01, NFR-SEC-01; MySQL guard; test process | Testing environment, connection `mysql_testing`, database `trackpro_test` | Run only the guarded identity checks when authorized. | Execution fails closed outside exact isolated identity; grants remain scoped. | EV-MYSQL-ID; A | Existing Automated Evidence; never substitute `trackpro_local`. |
| TC-INT-002 — Schema CHECK/FK/engine integrity | NFR-INT-01–02, NFR-DEC-01, NFR-DATA-01; MySQL schema | Migrated isolated MySQL test schema | Execute schema constraint suite. | InnoDB, collation, CHECK constraints, RESTRICT FKs, decimal rules, and historical restrictions hold. | EV-MYSQL-SCHEMA; A | Existing Automated Evidence. Schema support for SALE_VOID is not a workflow. |
| TC-INT-003 — Opening Inventory concurrency | FR-OPEN-01–03, NFR-CON-01, BR-05; concurrency; Admin actors | One eligible Variant; two connections under MySQL REPEATABLE READ | Race zero Opening Inventory submissions. | Locking/current read serializes them; exactly one INITIAL_STOCK effect survives. | EV-MYSQL-OI; A | Existing Automated Evidence. |
| TC-INT-004 — Stock In distinct-receipt concurrency | FR-STOCKIN-02, NFR-CON-01, BR-03; concurrency; Admin/Staff | Same Variant, two distinct receipt tokens/connections | Hold first transaction and submit second. | Both serialize without lost update; ledger before/after values remain continuous. | EV-MYSQL-STKIN; A | Existing Automated Evidence. |
| TC-INT-005 — Stock In same-token arbitration | FR-STOCKIN-02, NFR-CON-01; idempotency/concurrency | Equivalent same-token requests on two connections | Race submissions with stale ordinary snapshot available. | Current/locking recovery returns one winner; receipt and inventory effect occur once. | EV-MYSQL-STKIN; A | Existing Automated Evidence. |
| TC-INT-006 — Stock Correction concurrency | FR-CORR-03–04, NFR-CON-01, BR-07; concurrency; Admin | Same Variant; different/same targets and a competing Restock | Execute the three controlled races. | Winner commits; stale or no-op loser cannot overwrite; received stock is preserved. | EV-MYSQL-CORR; A | Existing Automated Evidence. |
| TC-INT-007 — Sale overselling and replay concurrency | FR-POS-03–06, NFR-CON-01, BR-01–03; concurrency; Admin/Staff | Limited stock; distinct and same-token connections | Race overselling, equivalent-token, and semantic-reuse requests. | Stock never goes negative; exactly one token effect; replay recovers winner; mismatch is rejected. | EV-MYSQL-SALE; A | Existing Automated Evidence. |
| TC-INT-008 — Stable multi-Variant lock order | FR-POS-03–04, NFR-CON-01; deadlock/integrity; Admin/Staff | Two Variants selected in reverse browser order | Run concurrent checkouts when authorized. | Global Category → Product → Variant ID ordering avoids application-induced deadlock and preserves balances. | EV-MYSQL-SALE; A | Existing Automated Evidence; SQLite does not prove this. |

## 10. Review-only cases

| ID / module / scenario | References; type; role | Preconditions/data | Steps | Expected result | Existing evidence; class | Target/status/notes |
| --- | --- | --- | --- | --- | --- | --- |
| TC-REVIEW-001 — Scope assumptions | A-01–A-07; review; project | Approved requirements and current repository | Compare documented assumptions with scope and test-data design. | Single-location, two-role, cash-only, evidence-limit assumptions are stated without fabricated client facts. | Requirements/UI plans; B | Review Only; Review Evidence / Not Runtime-Testable. |
| TC-REVIEW-002 — Excluded domain behavior | BR-08, BR-11, BR-14; review; project | Requirements, routes, source | Confirm no unit conversion, discounts, credit, returns/refunds, partial void, FIFO/weighted average/formal COGS/profit workflow is claimed. | Exclusions remain explicit; independent Variants are not treated as converted shared pools. | Requirements/source and partial route evidence; B | Review Only; Review Evidence / Not Runtime-Testable. |
| TC-REVIEW-003 — Maintainable architecture | NFR-MAINT-01; review; project | Composer/package/source layout | Inspect declared stack and focused services without executing build. | Laravel 13, PHP, Blade, Tailwind 4, minimal vanilla JS, MySQL 8 and focused transactional services remain the baseline; no unsupported architecture is claimed. | Repository source review, not automated evidence; E | Review Only; Review Evidence / Not Runtime-Testable. |
| TC-REVIEW-004 — Future tracker/features boundary | A-05, A-07, BR-11; review; project | Tracker status and route/source review | Confirm SALE_VOID and User Management remain future and #18 remains separate. | No prepared case describes an absent workflow as implemented; no user guide/screenshots are claimed. | Project status/source and partial route evidence; B | Review Only; Review Evidence / Not Runtime-Testable. |

## 11. Evidence-class and status summary

Across the 79 cases, current evidence is classified as:

| Evidence class | Cases |
| --- | ---: |
| A — Automated Direct | 61 |
| B — Automated Partial | 10 |
| C — Historical Manual | 6 |
| D — Prepared / Not Yet Executed | 0 |
| E — No Clear Current Evidence | 2 |
| **Total** | **79** |

Class D is available for future cases with no stronger classification; current
cases instead identify partial, historical, absent, or direct evidence
explicitly. Their execution status is separate:

| Execution status | Cases |
| --- | ---: |
| Prepared / Not Yet Executed | 67 |
| Existing Automated Evidence | 8 |
| Review Evidence / Not Runtime-Testable | 4 |
| Historical Manual Evidence | 0 standalone cases; referenced by prepared cases |
| Deferred Pending Implementation | 0 formal current-baseline cases |
| **Total formal cases** | **79** |

## 12. Receipt, reporting, UI, and accessibility interpretation

Receipt cases verify immutable snapshots, role access, read-only reprinting,
print chrome suppression, and exclusion of purchase cost, checkout token, and
movement internals. Historical Firefox Print Preview evidence may support later
planning but is not a #7 run. Reports have no dedicated print control, print
route, report-specific print layout, PDF export, or CSV export.

Sales History is **status-neutral**: active Admin and Staff can browse all
historical Sales regardless of recording user. Completed-only semantics apply
to Dashboard and Reports analytics. Staff may enter current purchase cost when
creating a Stock In receipt, but Staff does not receive existing/historical
purchase-cost visibility in catalog browsing, Stock In history/detail after
submission, POS, Dashboard, Reports, or Sales History.

Accessibility cases observe implemented evidence and limitations. They do not
claim WCAG conformance, formal contrast results, screen-reader certification,
reduced-motion compliance, comprehensive keyboard testing, complete drawer
modal semantics, or comprehensive focus trapping.

## 13. Requirements traceability and coverage matrix

Every approved requirement/rule is mapped below. `Auto` means existing
automation reference, `#15`/`#17` means later prepared execution, `MySQL` means
explicit guarded verification, and `Review` means the item is not meaningfully
verified as a standalone runtime behavior.

### 13.1 Functional requirements

| Requirement | Formal cases | Verification |
| --- | --- | --- |
| FR-AUTH-01 | TC-AUTH-001–003 | Auto; #15/#17 |
| FR-AUTH-02 | TC-AUTH-003–004 | Auto; #17 |
| FR-AUTH-03 | TC-RES-002 | Auto/source-route review; #17 |
| FR-AUTH-04 | TC-AUTH-006 | Auto partial; #17 browser rejection |
| FR-CAT-01 | TC-CAT-001–003, TC-CAT-005 | Auto; #15/#17 |
| FR-CAT-02 | TC-PROD-001–002, TC-CAT-004–005 | Auto; #15/#17 |
| FR-CAT-03 | TC-VAR-001–006 | Auto; #15/#17 |
| FR-CAT-04 | TC-CAT-002, TC-CAT-005, TC-VAR-005, TC-VAR-007, TC-RES-002 | Auto; #15/#17 |
| FR-CAT-05 | TC-VAR-003, TC-AUTH-005, TC-CAT-004, TC-STKIN-002 | Auto; #15/#17 |
| FR-INV-01 | TC-VAR-001–002, TC-OI-001, TC-STKIN-001–002, TC-CORR-001, TC-POS-001 | Auto; #15 |
| FR-INV-02 | TC-VAR-001–002, TC-VAR-004 | Auto; #15/#17 |
| FR-INV-03 | TC-VAR-001–002, TC-OI-003, TC-STKIN-004, TC-CORR-005, TC-POS-005 | Auto; #15/#17 |
| FR-INV-04 | TC-VAR-007, TC-POS-002, TC-POS-007, TC-INT-002, TC-INT-007 | Auto; #17; MySQL |
| FR-OPEN-01 | TC-OI-001–004, TC-INT-003 | Auto; #15/#17; MySQL |
| FR-OPEN-02 | TC-OI-003, TC-INT-003 | Auto; #17; MySQL |
| FR-OPEN-03 | TC-OI-001, TC-INT-003 | Auto; #15; MySQL |
| FR-STOCKIN-01 | TC-STKIN-001–005, TC-STKIN-009 | Auto/UI; #15/#17 |
| FR-STOCKIN-02 | TC-STKIN-001, TC-STKIN-003, TC-STKIN-005–008, TC-INT-004–005 | Auto; #15/#17; MySQL |
| FR-STOCKIN-03 | TC-STKIN-001–002, TC-VAR-006 | Auto; #15/#17 |
| FR-STOCKIN-04 | TC-STKIN-001–002, TC-VAR-006 | Auto; #15/#17 |
| FR-STOCKIN-05 | TC-STKIN-001, TC-STKIN-008, TC-INT-004 | Auto; #15/#17; MySQL |
| FR-CORR-01 | TC-CORR-001, TC-AUTH-005, TC-CORR-004 | Auto; #15/#17 |
| FR-CORR-02 | TC-CORR-001, TC-CORR-004–005 | Auto; #15/#17 |
| FR-CORR-03 | TC-CORR-002–003, TC-INT-006 | Auto; #17; MySQL |
| FR-CORR-04 | TC-CORR-001, TC-INT-006 | Auto; #15; MySQL |
| FR-POS-01 | TC-POS-001, TC-POS-008 | Auto/UI; #15 |
| FR-POS-02 | TC-POS-001–002, TC-POS-004–005 | Auto; #15/#17 |
| FR-POS-03 | TC-POS-001, TC-POS-005, TC-POS-007, TC-INT-007–008 | Auto; #15/#17; MySQL |
| FR-POS-04 | TC-POS-002, TC-INT-007–008 | Auto; #17; MySQL |
| FR-POS-05 | TC-POS-006, TC-INT-007 | Auto; #17; MySQL |
| FR-POS-06 | TC-POS-001, TC-POS-006–007, TC-INT-007 | Auto; #15/#17; MySQL |
| FR-POS-07 | TC-POS-001, TC-POS-003 | Auto; #15/#17 |
| FR-SALES-01 | TC-SALES-001, TC-SALES-004 | Auto; #15/#17 |
| FR-SALES-02 | TC-SALES-001, TC-SALES-003–004 | Auto; #15/#17 |
| FR-SALES-03 | TC-SALES-002, TC-RES-002 | Auto; #15/#17 |
| FR-SALES-04 | TC-PRINT-001–002, TC-RES-002 | Auto plus historical/manual; #15/#17 |
| FR-SALES-05 | TC-SALES-002 | Auto; #15 |
| FR-NAV-01 | TC-NAV-001–002 | Auto markup plus manual; #15 |
| FR-NAV-02 | TC-NAV-001–003 | Auto partial/historical manual; #15 |
| FR-NAV-03 | TC-NAV-001–002 | Auto plus manual; #15 |
| FR-NAV-04 | TC-NAV-003, TC-A11Y-001 | Historical/manual plus partial auto; #15 |
| FR-DASH-01 | TC-DASH-001, TC-DASH-003 | Auto/manual; #15 |
| FR-DASH-02 | TC-DASH-001, TC-DASH-003 | Auto/manual; #15 |
| FR-DASH-03 | TC-DASH-001 | Auto; #15 |
| FR-DASH-04 | TC-DASH-001 | Auto; #15 |
| FR-DASH-05 | TC-DASH-002–003 | Auto/manual; #15 |
| FR-DASH-06 | TC-DASH-002–003 | Auto/manual; #15 |
| FR-REP-01 | TC-REP-001, TC-AUTH-005, TC-REP-003 | Auto; #15/#17 |
| FR-REP-02 | TC-REP-001–002, TC-REP-004 | Auto/manual; #15/#17 |
| FR-REP-03 | TC-REP-001, TC-REP-003 | Auto; #15/#17 |
| FR-REP-04 | TC-REP-001, TC-REP-004 | Auto/manual; #15 |
| FR-REP-05 | TC-REP-001, TC-REP-004 | Auto/manual; #15 |
| FR-REP-06 | TC-REP-001, TC-REP-003 | Auto; #15/#17 |

### 13.2 Non-functional requirements

| Requirement | Formal cases | Verification |
| --- | --- | --- |
| NFR-INT-01 | TC-POS-002, TC-POS-007, TC-INT-002, TC-INT-007 | Auto; #17; MySQL |
| NFR-INT-02 | TC-OI-001, TC-STKIN-001/008, TC-CORR-001, TC-POS-001/007, TC-INT-002 | Auto; #15/#17; MySQL |
| NFR-CON-01 | TC-STKIN-006, TC-POS-006, TC-INT-001, TC-INT-003–008 | Auto; #17; guarded MySQL |
| NFR-SEC-01 | TC-VAR-003, TC-AUTH-003/005/006, TC-RES-001, TC-INT-001 | Auto; #15/#17; MySQL guard |
| NFR-SEC-02 | TC-VAR-003, TC-AUTH-005, TC-CORR-004, TC-RES-001 | Auto; #15/#17 |
| NFR-SEC-03 | TC-STKIN-007, TC-SALES-002, TC-REP-003 | Auto; #15/#17 |
| NFR-DATA-01 | TC-VAR-005, TC-SALES-002, TC-RES-002, TC-INT-002 | Auto; #15/#17; MySQL |
| NFR-DEC-01 | TC-VAR-002, TC-OI-003, TC-STKIN-004, TC-CORR-005, TC-POS-005, TC-INT-002 | Auto; #15/#17; MySQL |
| NFR-TIME-01 | TC-DASH-002, TC-SALES-003, TC-REP-001–002 | Auto; #15/#17 |
| NFR-RESP-01 | TC-NAV-001–003, TC-POS-008, TC-STKIN-009, TC-UI-001, TC-DASH-003, TC-REP-004 | Partial/historical manual; #15 |
| NFR-A11Y-01 | TC-NAV-003, TC-A11Y-001–002 | Partial/historical/manual; #15 |
| NFR-PRINT-01 | TC-PRINT-001–002 | Auto plus historical/manual; #15 |
| NFR-MAINT-01 | TC-REVIEW-003 | Source review; not standalone runtime behavior |

### 13.3 Business rules and assumptions

| Requirement/rule | Formal cases | Verification |
| --- | --- | --- |
| BR-01 | TC-VAR-007, TC-POS-001–002, TC-INT-002/007 | Auto; #15/#17; MySQL |
| BR-02 | TC-POS-001–005 | Auto; #15/#17 |
| BR-03 | TC-OI-001, TC-STKIN-001/008, TC-CORR-001, TC-POS-001/007, TC-INT-004/007 | Auto; #15/#17; MySQL |
| BR-04 | TC-SALES-002, TC-RES-002, TC-INT-002 | Auto; #15/#17; MySQL |
| BR-05 | TC-OI-001–004, TC-INT-003 | Auto; #15/#17; MySQL |
| BR-06 | TC-STKIN-001–007, TC-CAT-004 | Auto; #15/#17 |
| BR-07 | TC-CORR-001–005, TC-INT-006 | Auto; #15/#17; MySQL |
| BR-08 | TC-REVIEW-002 | Review: absence of unit conversion is a scope/design rule |
| BR-09 | TC-DASH-001 | Auto; #15 |
| BR-10 | TC-POS-001 | Auto; #15 |
| BR-11 | TC-RES-002, TC-REVIEW-002/004 | Route/source review; excluded future behavior |
| BR-12 | TC-DASH-002, TC-SALES-004, TC-REP-001/003 | Auto; #15/#17 |
| BR-13 | TC-DASH-002, TC-REP-001 | Auto; #15 |
| BR-14 | TC-REP-001/003, TC-REVIEW-002 | Auto plus review; no artificial COGS runtime case |
| A-01 | TC-REVIEW-001 | Review: single-location project assumption |
| A-02 | TC-REVIEW-001 | Review: scope rationale, not a measurable runtime result |
| A-03 | TC-REVIEW-001, TC-AUTH-005 | Review plus role authorization cases |
| A-04 | TC-REVIEW-001, TC-POS-001 | Review plus cash checkout |
| A-05 | TC-REVIEW-001/004 | Review: excluded features, not fabricated runtime cases |
| A-06 | TC-REVIEW-001 | Review: protects evidence limits; no client-loss metric test |
| A-07 | TC-REVIEW-001/004 | Review: project-derived evidence status |

## 14. Existing automated evidence map

These are representative exact current methods, not an exhaustive one-for-one
mapping of 191 tests. Related methods in the same class may provide additional
boundary evidence.

| Key | Exact representative source evidence |
| --- | --- |
| EV-AUTH-LOGIN | `tests/Feature/Auth/AuthenticationTest.php` — `AuthenticationTest::test_active_admin_can_log_in`, `::test_active_staff_can_log_in`, `::test_all_credential_failures_use_the_same_generic_message` |
| EV-AUTH-CSRF | `tests/Feature/Auth/AuthenticationTest.php` — `AuthenticationTest::test_logout_removes_authentication_invalidates_session_and_rotates_csrf_token`, `::test_login_and_logout_use_csrf_protected_web_middleware_without_exclusions` |
| EV-AUTHZ | `tests/Feature/Auth/AuthorizationTest.php` — `AuthorizationTest::test_guest_cannot_access_the_protected_application_page`, `::test_staff_direct_request_to_admin_route_is_forbidden`, `::test_user_disabled_after_login_is_rejected_on_the_next_real_request` |
| EV-ROUTE-SEC | `tests/Feature/Catalog/CatalogRouteSecurityTest.php` — `CatalogRouteSecurityTest::test_state_changes_are_not_get_or_delete_routes`, `::test_admin_forms_include_csrf_and_csrf_has_no_exclusions` |
| EV-CAT-AUTH | `tests/Feature/Catalog/CatalogAuthorizationTest.php` — `CatalogAuthorizationTest::test_staff_sees_only_active_hierarchy_without_cost_or_admin_controls`, `::test_staff_direct_mutations_are_forbidden` |
| EV-CAT | `tests/Feature/Catalog/CategoryManagementTest.php` — `CategoryManagementTest::test_admin_creates_normalized_active_category_and_blank_is_rejected`, `::test_case_and_whitespace_normalized_duplicate_is_rejected` |
| EV-CAT-LIFE | `tests/Feature/Catalog/CategoryManagementTest.php` — `CategoryManagementTest::test_active_category_can_be_updated_archived_and_reactivated`, `::test_category_archive_is_denied_while_it_has_an_active_product` |
| EV-PROD | `tests/Feature/Catalog/ProductManagementTest.php` — `ProductManagementTest::test_create_uses_active_route_parent_normalizes_name_and_cannot_be_overridden`, `::test_product_name_update_and_safe_category_move_are_allowed`, `::test_archive_requires_no_active_variants_and_reactivation_requires_active_category` |
| EV-VAR | `tests/Feature/Catalog/ProductVariantManagementTest.php` — `ProductVariantManagementTest::test_supported_units_quantity_modes_and_composite_uniqueness_are_enforced`, `::test_identity_edit_is_denied_for_each_activity_type_and_nonzero_stock`, `::test_cost_is_immutable_after_first_restock_but_price_and_threshold_can_change` |
| EV-OI-AUTH | `tests/Feature/Inventory/OpeningInventoryAuthorizationTest.php` — `OpeningInventoryAuthorizationTest::test_staff_is_forbidden_and_has_no_opening_inventory_navigation`, `::test_routes_have_admin_middleware_and_only_post_mutates` |
| EV-OI | `tests/Feature/Inventory/OpeningInventoryManagementTest.php` — `OpeningInventoryManagementTest::test_positive_whole_opening_is_canonical_atomic_and_actor_bound`, `::test_zero_whole_opening_is_recorded_exactly_once`, `::test_invalid_quantity_lexical_forms_scale_overflow_and_whole_fraction_are_rejected` |
| EV-STKIN | `tests/Feature/Inventory/RestockManagementTest.php` — `RestockManagementTest::test_initialized_zero_stock_records_exact_multi_item_history_and_latest_cost`, `::test_quantity_validation_rejects_invalid_forms_and_accepts_valid_modes`, `::test_historical_cost_and_snapshots_survive_later_catalog_changes_and_replay` |
| EV-STKIN-COST | `tests/Feature/Inventory/RestockManagementTest.php` — `RestockManagementTest::test_staff_never_receives_existing_or_historical_cost_values_but_admin_does` |
| EV-STKIN-REPLAY | `tests/Feature/Inventory/RestockManagementTest.php` — `RestockManagementTest::test_equivalent_replay_is_canonical_and_order_independent`, `::test_token_reuse_with_any_changed_semantics_or_actor_is_rejected_safely` |
| EV-STKIN-ROLLBACK | `tests/Feature/Inventory/RestockManagementTest.php` — `RestockManagementTest::test_second_movement_failure_rolls_back_entire_multi_item_receipt` |
| EV-CORR-AUTH | `tests/Feature/Inventory/StockCorrectionAuthorizationTest.php` — `StockCorrectionAuthorizationTest::test_staff_is_forbidden_and_has_no_stock_correction_navigation`, `::test_routes_have_admin_middleware_csrf_and_no_historical_mutations` |
| EV-CORR | `tests/Feature/Inventory/StockCorrectionManagementTest.php` — `StockCorrectionManagementTest::test_negative_physical_target_creates_exact_movement_and_changes_quantity_only`, `::test_stale_movement_version_rejects_without_overwrite`, `::test_movement_version_rejects_aba_stock_cycle` |
| EV-POS-AUTH | `tests/Feature/Sales/PosAuthorizationTest.php` — `PosAuthorizationTest::test_active_admin_and_staff_can_view_and_checkout`, `::test_pos_routes_have_auth_and_active_without_admin_gate_and_no_future_routes` |
| EV-POS | `tests/Feature/Sales/PosCheckoutTest.php` — `PosCheckoutTest::test_checkout_persists_authoritative_distinct_sale_evidence`, `::test_pos_finder_and_checkout_enforce_initialization_active_hierarchy_and_stock`, `::test_money_payment_rounding_and_overflow_are_exact_and_controlled` |
| EV-POS-REPLAY | `tests/Feature/Sales/PosCheckoutTest.php` — `PosCheckoutTest::test_idempotent_replays_compare_canonical_history_not_current_catalog`, `::test_retry_token_ux_retains_ordinary_tokens_but_replaces_semantically_reused_tokens` |
| EV-POS-ROLLBACK | `tests/Feature/Sales/PosCheckoutTest.php` — `PosCheckoutTest::test_second_sale_movement_failure_rolls_back_all_checkout_work` |
| EV-SALES-AUTH | `tests/Feature/Sales/SalesHistoryAuthorizationTest.php` — `SalesHistoryAuthorizationTest::test_active_admin_and_staff_can_view_all_sales`, `::test_malformed_and_missing_sale_ids_return_controlled_not_found_responses` |
| EV-SALES | `tests/Feature/Sales/SalesHistoryTest.php` — `SalesHistoryTest::test_cashier_filter_is_narrow_and_includes_disabled_historical_cashiers`, `::test_date_filters_use_inclusive_manila_calendar_days_and_exclusive_next_day`, `::test_malformed_and_reversed_date_filters_fail_closed` |
| EV-SALES-RECEIPT | `tests/Feature/Sales/SalesHistoryTest.php` — `SalesHistoryTest::test_receipt_uses_snapshots_preserves_privacy_prints_and_never_writes` |
| EV-DASH | `tests/Feature/Dashboard/DashboardTest.php` — `DashboardTest::test_today_uses_half_open_manila_boundaries_and_completed_sales_only`, `::test_low_stock_and_out_of_stock_use_only_the_full_active_hierarchy`, `::test_admin_trend_contains_all_seven_days_and_excludes_non_completed_sales` |
| EV-REP-AUTH | `tests/Feature/Reports/ReportsAuthorizationTest.php` — `ReportsAuthorizationTest::test_staff_is_forbidden_and_admin_can_view_reports`, `::test_reports_route_is_get_only_and_admin_protected` |
| EV-REP | `tests/Feature/Reports/ReportsTest.php` — `ReportsTest::test_default_report_uses_the_latest_seven_manila_calendar_days`, `::test_invalid_date_filters_fail_closed`, `::test_quantities_remain_separate_and_use_historical_unit_snapshots` |
| EV-NAV | `tests/Feature/Navigation/ResponsiveNavigationTest.php` — `ResponsiveNavigationTest::test_admin_navigation_uses_all_ten_destinations_in_desktop_and_mobile_menus`, `::test_staff_navigation_uses_exactly_the_seven_permitted_destinations_in_both_menus`, `::test_navigation_markup_is_accessible_print_hidden_active_and_uses_secure_logout_forms` |
| EV-MYSQL-ID | `tests/MySql/DatabaseIdentityTest.php` — `DatabaseIdentityTest::test_live_connection_identity_and_expected_schema_state`, `::test_account_grants_are_limited_to_the_isolated_database` |
| EV-MYSQL-SCHEMA | `tests/MySql/SchemaIntegrityTest.php` — `SchemaIntegrityTest::test_schema_inventory_engine_collation_checks_and_foreign_keys`, `::test_stock_movements_reject_invalid_quantities_reasons_directions_and_references` |
| EV-MYSQL-OI | `tests/MySql/OpeningInventoryConcurrencyTest.php` — `OpeningInventoryConcurrencyTest::test_zero_opening_inventory_is_serialized_and_recorded_exactly_once` |
| EV-MYSQL-STKIN | `tests/MySql/RestockConcurrencyTest.php` — `RestockConcurrencyTest::test_concurrent_distinct_restocks_serialize_without_lost_update`, `::test_same_token_race_recovers_winner_with_current_read_despite_stale_snapshot` |
| EV-MYSQL-CORR | `tests/MySql/StockCorrectionConcurrencyTest.php` — `StockCorrectionConcurrencyTest::test_different_targets_serialize_and_stale_loser_cannot_overwrite_winner`, `::test_restock_winner_makes_waiting_correction_stale_without_received_stock_overwrite` |
| EV-MYSQL-SALE | `tests/MySql/SaleConcurrencyTest.php` — `SaleConcurrencyTest::test_concurrent_distinct_sales_serialize_and_prevent_overselling`, `::test_reversed_multi_variant_browser_order_uses_global_hierarchy_order_without_deadlock`, `::test_same_token_disjoint_inventory_blocks_on_unique_arbitration_and_rejects_semantic_reuse` |

Before execution, every cited method must still exist at the recorded path. An
evidence-reference change does not require renumbering its formal test case.

## 15. Historical manual evidence map

`PROJECT_STATUS.md` records historical, not newly executed, evidence:

| Key | Historical evidence |
| --- | --- |
| HM-OI | Opening Inventory manual/browser completion including zero initialization |
| HM-STKIN | Stock In entry, history/detail, costs, and receipt behavior |
| HM-CORR | Stock Correction form/history and stale/current behavior |
| HM-POS | Successful Sales, underpayment rejection, unchanged stock on failure, and cost privacy |
| HM-PRINT | Firefox Print Preview suppressed application chrome and retained a one-sheet receipt |
| HM-NAV | Mobile/tablet/desktop and 1023/1024 navigation, close paths, focus, keyboard, inert, and scroll behavior |
| HM-DASH | Admin Dashboard desktop/mobile data and responsive presentation |
| HM-REP | Admin Reports desktop/mobile data, filters, and receipt integration |

Historical evidence does not complete a future #15/#17 execution record. No
Staff browser session, screenshot campaign, or user guide is fabricated.

## 16. Tracker #5 usability-risk handling

| Current source-derived risk | Prepared treatment | Corrected expectation |
| --- | --- | --- |
| Category context is absent in POS/Stock In | Observe in TC-POS-008 and TC-STKIN-009; retain risk | Deferred until UI changes |
| Archive has no named-record confirmation | Observe during TC-CAT-002/005 | Confirmation must not be expected now |
| Below `xl` POS cart follows full catalog | Observe in TC-POS-008 | Shortcut/sticky mobile summary deferred |
| Repeated-row error association is limited | Observe visible feedback in TC-STKIN-009/A11Y-002 | Strong programmatic association deferred |
| Sales History says “Completed transactions” despite status neutrality | Record wording while TC-SALES-001/004 verify all statuses | Status-neutral wording deferred |
| Opening Inventory unavailable reason is generic | Observe during TC-OI-002/004 | Specific reason text deferred |
| Dynamic POS/Stock In announcements are not established | Record in TC-A11Y-002 | Live announcements deferred |
| Invalid Reports may retain zero summary cards | Expect current state in TC-REP-002 | Hide/replace behavior deferred |

These are implementation-review risks, not proven user failures. No case expects
an unimplemented recommendation.

## 17. Completion standard

Tracker #7 can close when:

1. scope, evidence classes, targets, and statuses are documented;
2. every implemented core module has executable functional cases;
3. negative, boundary, lifecycle, authorization, permission, and integrity cases are prepared;
4. responsive, accessibility-oriented, and receipt-print manual cases are prepared;
5. cases trace to applicable requirements and business rules;
6. preconditions, reproducible data, steps, and expected results are unambiguous;
7. exact representative automation is cited without claiming a rerun;
8. historical manual evidence remains distinct from new execution;
9. every case identifies its #15, #17, guarded MySQL, or review target;
10. future manual cases remain Prepared / Not Yet Executed;
11. database and historical-data safety boundaries are documented;
12. this single artifact is fully reviewed, committed, and pushed; and
13. a separate approved `PROJECT_STATUS.md` closeout records formal completion.

No #15 or #17 execution is required to complete this planning tracker. Open UI
recommendations, SALE_VOID, User Management, and #18 work do not block test-case
preparation and are not started by this artifact.
