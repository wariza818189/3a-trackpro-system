# TrackPro Codex Workflow

Reference guidance, not required reading for every Codex task. `AGENTS.md` contains the lightweight always-on rules. Last reviewed: September 30, 2026. Model names, pricing, and availability can change; periodically re-review the routing policy.

## 1. Purpose

This workflow preserves repository safety, keeps tasks small and reviewable, reduces unnecessary token and usage consumption, and starts with the cheapest capable model.

## 2. Cost-First Model Strategy

Choose a model by task type first, then escalate only when evidence shows the selected tier is insufficient. Use the least expensive and least powerful model that can reliably complete the task. Do not route every implementation through Luna, or choose a stronger model merely because it is available.

| Task | Preferred model | Effort |
| --- | --- | --- |
| Mechanical Git/status/hash checks, simple inspection, formatting, clerical work | GPT-6 Luna | Low/Medium |
| Docs-only closeouts, tracker/status updates, bounded audits, requirements sync, test-result recording, ordinary planning | GPT-6 Luna | Medium |
| Difficult bounded audits or ambiguous repository reasoning | GPT-6 Luna | High, only when Medium is insufficient |
| Production code, multi-file implementation, debugging, refactoring, browser/computer use requiring reasoning | GPT-6.1 Sol | Medium |
| Transaction, authorization/security, schema/migration, inventory/financial, or concurrency-sensitive implementation | GPT-6.1 Sol | Medium |
| Difficult implementation/debugging; unresolved security ambiguity or unusually difficult concurrency/locking | GPT-6.1 Sol | High |
| Genuinely difficult unresolved problem, major architecture reasoning, or repeated Medium/High failure | GPT-6.1 Sol | XHigh/Max, exceptionally |
| Exceptional task demonstrably beyond GPT-6.1 Sol | GPT-6 Astra | Appropriate effort, exceptionally |

Normal escalation path: Luna Medium → Luna High → GPT-6.1 Sol Medium → GPT-6.1 Sol High → GPT-6.1 Sol XHigh/Max → Astra. This is an evidence-based ladder, not a required sequence: choose GPT-6.1 Sol Medium directly for clearly implementation-grade work, and keep obvious docs or mechanical tasks on Luna. High, XHigh/Max, and Astra are not routine defaults. Use the lowest reasoning effort that can reliably finish the task; too little effort can cause retries and rework. Future equivalent model generations can fill the efficient/scoped, implementation/judgment, and exceptional frontier slots without changing this policy.

## 3. Prompt Design

Standard task prompts should normally contain only: task; baseline or completed checkpoint; scope; critical invariants; authorized files/actions; verification; checkpoint/commit expectation; and report format. Name the model when useful. For high-risk work, include the detail needed to preserve safety; do not compress authorization, destructive-operation, database, concurrency, inventory, financial, or test-history requirements.

Do not repeat repository rules already in `AGENTS.md`, paste full project history unless needed, or repeat the same prohibition many times. Reference canonical repository files such as `AGENTS.md`, `docs/codex-workflow.md`, `docs/project-tracker.md`, or a completed audit/checkpoint instead of pasting their contents. After a read-only audit or design checkpoint establishes policy, behavior, files, invariants, and test plan, the implementation prompt should reference that checkpoint and repeat only the critical safety invariants. Compression must retain authorization boundaries, transaction rules, destructive-operation prohibitions, database safety, concurrency invariants, and historical-test boundaries.

## 4. Standard Compact Prompt Template

```text
MODEL: <model + effort>

TASK
<one concise objective>

BASELINE
<HEAD/origin SHA and required Git state>

ALLOWED CHANGES
<exact files or bounded scope>

FROZEN / OUT OF SCOPE
<critical exclusions only>

INVARIANTS
<behavior that must remain true>

VERIFY
<targeted checks/tests>

FAILURE POLICY
<conditions requiring STOP rather than automatic repair>

REPORT
<baseline, files changed, verification, Git state, classification>

No commit unless explicitly authorized.
```

## 5. Implementation Workflow

Use this sequence:

clean baseline → inspect relevant files → implement the smallest slice → targeted verification → broader regression only when justified → complete diff review → stop before commit → external review → separate mechanical Git checkpoint → documentation sync when necessary → next feature.

Keep implementation and checkpointing separate so the change can be reviewed before it becomes a baseline. Checkpoint after review, then build subsequent work on a known state.

## 6. Failure Handling

### Test/harness implementation defect

A narrow correction may be appropriate when the failure clearly belongs to newly written test or harness code.

### Application/domain defect

Stop and preserve evidence. Do not rewrite business or domain logic merely to make tests green.

### Scope expansion

Stop before modifying an unauthorized path and request explicit scope expansion.

### Concurrency failure

Deadlock, timeout, lost update, duplicate identity, partial state, or invariant violation must not be hidden by repeated reruns.

## 7. Verification Efficiency

- Run targeted checks first; run full regression only when justified.
- Do not rerun a successful expensive suite merely for timing.
- Choose checks by asking what the change could realistically break; use the smallest sufficient set first, then broaden when shared infrastructure changed, failures appear, the affected surface is broad, or project closeout requires a final full-suite result. Test count is not a goal by itself.
- Docs-only changes do not require the full SQLite suite, npm build, or MySQL. For Blade/UI changes, use focused affected tests, npm build, and browser visual checks where needed; guarded MySQL is relevant only when server/data behavior changed. Read-only query/UI work does not require concurrency MySQL unless write/locking behavior changed. For concurrency-sensitive writes, run focused ordinary tests and guarded MySQL where required.
- Do not rerun already-passed guarded MySQL concurrency suites after unrelated docs/Blade changes, or manual FT cases unless the changed scope could affect them. Reuse verified evidence from the current checkpoint.
- Do not rerun failures caused by a genuine domain invariant hoping for a pass.
- Distinguish an application transaction retry from rerunning a test process.
- Report the checks actually run and their actual results.

## 8. Token / Usage Efficiency

- Re-evaluate model fit at every task boundary; do not keep an expensive model just because the previous task used it. For example, return to Luna for docs after GPT-6.1 Sol implementation, and use Medium for mechanical checkpoint work after a difficult audit.
- Prefer a fresh Codex thread after a major completed checkpoint when prior context is mostly historical, such as a feature commit and docs closeout, a long audit before a distinct implementation phase, or completed migration/concurrency work. Do not start a fresh thread during an unresolved task that still depends on its context.
- Keep successful final reports compact: classification, changed files, important behavior, verification results, commit/push state, and blockers or failures. Do not restate the prompt, repeat long project history, or narrate obvious steps; retain important failure and safety information.
- Avoid repeated repository audits when a reviewed checkpoint already proves the baseline.
- Avoid reading every repository document; use authoritative references and inspect only what the task needs.
- Usage includes input context, repository reading, reasoning, tool activity, and repeated verification. Shortening the final report alone is not the main optimization; avoid unnecessary repeated context and redundant work. Do not assume lower reasoning always saves total usage.

## 9. Reporting Standard

Successful reports normally contain the starting baseline, files changed, key implemented behavior, verification results, scope/frozen-file integrity, final Git state, classification, and recommended next action.

Failure reports should additionally contain the exact failing check, observed behavior, whether the failure is test/harness or domain, preserved evidence, and the decision required.

## 10. Documentation Roles

- `docs/project-tracker.md` — current authoritative tracker.
- `docs/project-documentation.md` — teacher-facing canonical narrative.
- `docs/test-cases.md` — detailed test catalog and evidence.
- `docs/functional-test-results.md` — historical functional-test execution.
- `PROJECT_STATUS.md` — historical archive.

Historical evidence must remain historical and must not be relabeled based on later work.

## 11. TrackPro Development Principle

Prefer: small feature slice → verify → review → checkpoint → documentation sync if needed → next feature.

Avoid accumulating multiple unrelated implementation slices before checkpointing.

## 12. Updating This Workflow

Change this file when model strategy or workflow evolves. Keep `AGENTS.md` stable and lean; do not add temporary feature-specific rules there. Git history records process changes.
