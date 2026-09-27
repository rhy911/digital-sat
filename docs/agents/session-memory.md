# Session Memory & Task Handoff

How engineering agents maintain continuity, track in-progress work, and hand off active tasks across sessions.

## Quick Reference

- **Active task file**: `docs/agents/session.md` (only exists when work is in progress).
- **Archive directory**: `docs/agents/session-archive/YYYY-MM-DD-<task-slug>.md`.

---

## Agent Lifecycle Rules

### 1. Starting a Session (Read)
Before starting any implementation work, check if `docs/agents/session.md` exists:
- **If present**: Read it immediately. Resume the task from `Next Immediate Steps` and respect any notes in `Gotchas & Uncommitted Discoveries`.
- **If absent**: The workspace is idle. Proceed with the user's requested instructions.

### 2. During Work & Pausing (Write)
Update `docs/agents/session.md` whenever:
- You conclude a session with uncommitted or incomplete work.
- You encounter a non-obvious domain quirk, blocker, or unexpected behavior that the next agent must know.
- You hand off control back to the user or another agent.

### 3. Completing a Task (Archive)
When the task is complete, tests pass, and work is ready for PR or merge:
1. Ensure the final summary in `docs/agents/session.md` reflects the completed state.
2. Archive the session file:
   ```bash
   git mv docs/agents/session.md docs/agents/session-archive/YYYY-MM-DD-<task-slug>.md
   ```
3. Commit the archived file alongside the task changes. The absence of `docs/agents/session.md` returns the workspace to idle.

---

## Session File Template

When creating or updating `docs/agents/session.md`, adhere strictly to this schema:

```markdown
# Session: <Task Name or Issue Title>

## Current Goal & Status
- **Goal**: <One-line summary of what this task accomplishes>
- **Status**: <in-progress | blocked | ready-for-review>

## Working State & Completed Steps
- <Files modified and key architectural components touched>
- <Current test status: e.g. tests passing, failing, or pending>
- <Any open questions or pending decisions>

## Next Immediate Steps
1. <First concrete action the incoming agent should take>
2. <Subsequent action>
3. <Verification or test run>

## Gotchas & Uncommitted Discoveries
- <Non-obvious repository quirks, cascading timing constraints, or unexpected bugs uncovered>
```
