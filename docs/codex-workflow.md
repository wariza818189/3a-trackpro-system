# TrackPro Codex Workflow

Reference guidance, not required reading for every Codex task. `AGENTS.md` contains the lightweight always-on rules. Last reviewed: September 23, 2026. Model names, pricing, and availability can change; periodically re-review the routing policy.

## 1. Purpose

This workflow preserves repository safety, keeps tasks small and reviewable, reduces unnecessary token and usage consumption, and starts with the cheapest capable model.

## 2. Cost-First Model Strategy

Escalation order:

1. GPT-6 Luna Medium
2. GPT-6 Luna High
3. GPT-6 Sol Medium
4. GPT-6 Sol High

Prefer the lowest-cost model and effort that can safely complete the task. High reasoning is an escalation, not the default. Do not use a stronger model merely because it is available. If model availability or pricing changes, update this section instead of hardcoding policy into every prompt.

| Task | Preferred model | Effort |
| --- | --- | --- |
| Git/status/hash/checkpoint | GPT-6 Luna | Medium |
| Simple documentation update | GPT-6 Luna | Medium |
| Read-only repository inspection | GPT-6 Luna | Medium |
| Difficult bounded review | GPT-6 Luna | High |
| Normal Laravel implementation | GPT-6 Sol | Medium |
| Database/transaction/concurrency implementation | GPT-6 Sol | Medium first |
| Concrete unresolved hard concurrency/architecture issue | GPT-6 Sol | High |

Escalate only after evidence that the cheaper tier is insufficient. A failed or ambiguous Medium run may justify High; mechanical work does not justify automatic escalation.

## 3. Prompt Design

Standard task prompts should normally contain only: model; task/objective; baseline; allowed changes; critical invariants or frozen scope; verification; failure/stop policy; and a short report format.

Do not repeat repository rules already in `AGENTS.md`, paste full project history unless needed, or repeat the same prohibition many times. Reference canonical repository files instead of pasting their contents. State task-specific safety requirements explicitly when the work is high risk.

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
- Do not rerun failures caused by a genuine domain invariant hoping for a pass.
- Distinguish an application transaction retry from rerunning a test process.
- Report the checks actually run and their actual results.

## 8. Token / Usage Efficiency

- Prefer fresh Codex threads after major clean checkpoints; avoid carrying huge prior chat context into unrelated tasks.
- Keep successful final reports compact and request detailed diagnostics mainly on failure.
- Avoid repeated repository audits when a reviewed checkpoint already proves the baseline.
- Do not ask Codex to read every document; use Luna for mechanical or bounded work and Sol when implementation or reasoning complexity justifies it.
- Avoid High reasoning unless necessary.

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
