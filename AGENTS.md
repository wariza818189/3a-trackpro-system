# 3A TrackPro — Agent Instructions

## Safety

- Inspect relevant files and current conventions before editing.
- Never expose or commit secrets, credentials, `.env` files, API keys, or database passwords.
- Never use destructive Git or database commands unless explicitly authorized.
- Never access or mutate `trackpro_local`, `trackpro_test`, or `trackpro_ft17_test` unless the current task explicitly authorizes it.
- Stop before expanding beyond the task's authorized file scope.
- Preserve the historical repository at `/home/joyboy/3a-trackpro` as read-only.

## Git

- For substantial work, verify the branch, expected baseline, and clean index/worktree before editing.
- Do not stage, commit, or push unless explicitly authorized. Prefer literal path staging; avoid broad staging commands.
- Keep significant feature work in reviewed Git checkpoints.

## Implementation

- Make the smallest approved change and inspect existing conventions before adding abstractions or infrastructure.
- Keep the traditional Laravel/Blade architecture unless the task explicitly approves a change.
- Do not weaken tests to obtain a pass. If testing exposes a domain/design defect, preserve evidence and report it instead of silently changing architecture.
- Keep authorization server-side; Admin and Staff capabilities are distinct. Do not add unapproved authentication flows or a permission package.
- `ProductVariant.current_stock` is authoritative; protect exact decimal inventory/money arithmetic, transactional stock movements, nonnegative stock, and immutable transaction history. Do not change stock through ordinary catalog editing.
- Do not add future modules or edit/delete paths for immutable transaction history without explicit approval.

## Verification

- Run checks relevant to the changed scope. Do not repeat successful expensive checks merely for timing.
- Distinguish test/harness defects from application/domain defects; preserve historical test evidence as historical.
- Report actual results; a crashed or partial check is not a pass.

## Documentation roles

- `docs/project-tracker.md` is the authoritative current tracker.
- `docs/project-documentation.md` is the canonical teacher-facing narrative.
- `PROJECT_STATUS.md` is historical/archive; do not rewrite historical chronology based on later implementation.

## Task instructions

- Task-specific prompts define authorized scope. Read supporting documents only when relevant; do not automatically read every project document.
