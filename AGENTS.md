# 3A TrackPro — Agent Instructions

## Project

3A TrackPro is a Hardware Store Sales and Inventory Management System.

Primary stack:

- Laravel 13
- PHP (Composer requirement: `^8.3`; recorded development runtime: 8.4)
- Blade
- Tailwind CSS 4
- Vanilla JavaScript
- MySQL 8
- PHPUnit
- Laravel Pint

The application uses a traditional server-rendered Laravel architecture.

Do not introduce React, Vue, SPA architecture, Redis, queues, WebSockets,
microservices, Elasticsearch, or other major infrastructure unless the current
task explicitly requires and approves it.

## Repository

Authoritative repository:

`/home/joyboy/Projects/3a-trackpro-system`

Current remote:

`https://github.com/wariza818189/3a-trackpro-system`

Historical repository:

`/home/joyboy/3a-trackpro`

The historical repository is READ-ONLY reference material.

Never modify the historical repository.

Never copy it wholesale into the current project.

## Current Project State

Before substantial development work, read:

`PROJECT_STATUS.md`

It contains the current:

- Git checkpoint
- completed stages
- active stage
- tracker status
- test baseline
- known local-data state
- next planned work

Do not assume PROJECT_STATUS.md replaces inspection of production source code.

For implementation decisions, inspect the actual current source and migrations.

## Domain Model

Core hierarchy:

Category
→ Product
→ ProductVariant

ProductVariant is the authoritative stock pool.

Important Variant fields include:

- unit
- quantity_mode
- cost_price
- selling_price
- current_stock
- low_stock_threshold
- status

Supported quantity modes:

- whole
- fractional

Inventory quantities use exact decimal semantics.

Do not use binary floating-point arithmetic for authoritative inventory or money
calculations.

## Roles

Supported application roles:

- Admin
- Staff

Authorization must remain server-side authoritative.

Staff and Admin capabilities are not interchangeable.

Do not add a permission package unless explicitly approved.

## Authentication

Current authentication is intentionally limited.

Supported:

- username/password login
- POST logout
- active/disabled account enforcement
- Admin/Staff authorization

Do NOT add without explicit approval:

- public registration
- password reset
- forgot-password workflow
- remember-me behavior
- social login
- browser-based Admin bootstrap
- public setup routes

Admin bootstrap remains CLI-only.

## Inventory Integrity

`ProductVariant.current_stock` is backend-authoritative.

Never trust submitted:

- current_stock
- quantity_before
- quantity_change
- quantity_after
- movement_type
- performed_by
- calculated totals
- historical snapshots

Every legitimate stock mutation must create an appropriate StockMovement.

Implemented application workflows create these movement types:

- INITIAL_STOCK
- RESTOCK
- CORRECTION

The schema also supports these movement types, whose application workflows are
not yet implemented:

- SALE
- SALE_VOID

Do not implement future movement workflows unless they belong to the explicitly
approved current stage.

Stock must never become negative.

Use exact decimal-string arithmetic such as BCMath where required.

## Inventory Workflow Rules

Opening Inventory:

- Admin-only
- exactly once per Variant
- may record zero
- represented by INITIAL_STOCK
- initialization is determined from history, not positive stock

Normal Stock In:

- Admin and Staff
- requires prior INITIAL_STOCK
- quantity must be positive
- uses Restock + RestockItem + RESTOCK StockMovement
- historical received unit cost is immutable
- ProductVariant.cost_price becomes the latest received-cost reference
- durable submission idempotency is required

Stock Correction:

- Admin-only
- uses a corrected physical stock target
- requires prior INITIAL_STOCK and an active catalog hierarchy
- requires an Admin-supplied reason
- changes quantity only and never updates cost
- creates an immutable CORRECTION StockMovement
- uses the latest StockMovement ID as its stale-form version

Do not rewrite immutable historical inventory transactions.

## Historical Data

Historical transaction records are append-only unless a future approved workflow
explicitly defines a compensating action.

Do not add edit/delete routes for immutable transaction history.

Prefer:

- archive instead of delete for catalog entities
- disable instead of delete for users
- compensating movement instead of rewriting stock history

## Catalog Rules

Category, Product, and ProductVariant lifecycle rules must preserve historical
integrity.

Do not directly modify current_stock through ordinary catalog editing.

Positive-stock Variants must not be archived.

Identity/unit/quantity-mode fields may become frozen after stock or transactional
history according to the existing production rules.

Inspect existing lifecycle code before changing catalog behavior.

## Database Safety

Never assume a database is empty.

`trackpro_local` contains legitimate manual-development data.

Do NOT access or mutate `trackpro_local` unless the current task explicitly
authorizes it.

Dedicated MySQL tests use the isolated testing connection/database.

Do not access MySQL merely for convenience.

Do NOT run unless explicitly authorized by the current task:

- migrations
- rollback
- fresh/reset operations
- seeders
- destructive schema runners
- manual fixture deletion
- database/volume deletion

Never casually delete Docker volumes or MySQL databases.

When MySQL tests are explicitly approved:

- use the existing guarded mysql_testing infrastructure
- verify database identity
- never substitute trackpro_local
- preserve fixture cleanup rules
- never print credentials

## Secrets

Never display or commit the contents of:

- `.env`
- `.env.mysql`

These files must remain ignored and untracked.

Never commit:

- passwords
- API keys
- private keys
- database credentials
- secret tokens
- SQL dumps containing sensitive information

Metadata checks such as ignored/tracked status and file permissions are allowed
when relevant.

## Transactions and Concurrency

Inventory mutations must be transactional.

Follow the established hierarchy lock ordering:

Category
→ Product
→ ProductVariant

For multi-entity inventory operations, preserve a stable global ID ordering where
the existing workflow requires it.

After waiting on locks under MySQL REPEATABLE READ, authoritative state checks
must use current/locking reads where required.

Do not rely on stale ordinary snapshot reads for inventory integrity.

## Controllers and Services

Keep controllers thin.

Complex inventory mutations belong in focused transactional services.

Prefer Laravel-native architecture.

Do not introduce:

- repository pattern
- event bus
- generic inventory framework
- queues

unless explicitly justified and approved.

FormRequest validation is preliminary.

Sensitive domain services should defensively validate critical invariants again.

## UI

Use:

- Blade
- Tailwind
- minimal vanilla JavaScript

Do not create an SPA.

Backend validation and authorization remain authoritative even when client-side
validation exists.

Brand identity:

- Primary navy: `#0F172A`
- Accent orange: `#F59E0B`
- Slate: `#64748B`
- Background: `#F8FAFC`

Do not broadly redesign the visual system outside the approved task.

## Testing

Ordinary feature/unit tests use isolated SQLite `:memory:` where configured.

Ordinary tests must fail closed if a specialized test harness expects SQLite
memory but receives another database.

SQLite behavior tests do NOT prove MySQL concurrency or MySQL-specific DDL.

Use dedicated guarded MySQL tests only when explicitly approved.

After implementation changes, run the smallest relevant tests first, followed by
the broader regression suite when appropriate.

Typical safe checks include:

- PHP syntax checks
- focused PHPUnit tests
- `php artisan test`
- `php artisan route:list --except-vendor`
- `vendor/bin/pint --test`
- `npm run build`
- `git diff --check`

Do not run destructive database verification merely because code changed.

## Git Workflow

Before editing:

1. verify expected HEAD
2. verify branch
3. verify origin/main when relevant
4. inspect working-tree/index state

Respect the explicitly approved file scope.

Do not silently expand the file set.

Never use destructive Git operations unless explicitly approved.

Do not use force push.

Do not stage, commit, or push unless the current task explicitly authorizes it.

When exact staging is required, prefer literal path staging.

Do not use:

- `git add .`
- `git add -A`
- `git add --all`

for tightly scoped checkpoints.

After a checkpoint, verify:

- commit parent
- commit message
- committed path set
- HEAD/origin parity
- clean working tree

## Development Workflow

For substantial tasks:

1. inspect first
2. understand existing source and schema
3. report design before editing when requested
4. preserve stage boundaries
5. implement only approved scope
6. test
7. perform security/integrity review
8. stop for approval before sensitive operations

Never hide a failed command.

A scanner/test that crashes or only partially runs is NOT a passing audit.

Correct it and rerun the complete intended check.

## Error Handling and Security

Do not expose raw:

- SQL
- database errors
- stack traces
- index names
- credentials

to application users.

Do not create:

- public migrate routes
- seed routes
- reset routes
- setup routes
- debug credential routes
- user-listing debug routes

No mutating operation should be implemented as GET.

CSRF protection must remain enabled for browser mutations.

## Scope Discipline

Do not implement future modules early.

Current/future project areas include:

- Catalog
- Opening Inventory
- Stock In
- Stock Correction
- POS / Sales
- Sales History / Receipt
- Sale Void
- Dashboard / Reports
- User Management
- Final Integration

Only implement the stage explicitly approved by the current task.

## Reporting

After significant work, report:

- files created
- files modified
- commands/tests run
- exact test results
- security/integrity findings
- deviations
- unresolved issues
- Git status
- whether database access occurred

Never claim a check was performed if it was not actually executed.
