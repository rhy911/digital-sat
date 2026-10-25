# Session Memory & Cross-Device Handoff

Shared 3-tier memory protocol across harnesses (Antigravity, Codex, Claude Code, Cursor) and across Windows machines synced via Git:

- **Live board (`docs/agents/session.md`)**: Active unmerged streams only (`<=60` lines, overwritten in place). Tracked in Git on working branches so `git push` / `git pull` carries handoff context across devices; deleted when no active streams remain.
- **Completed ledger (`docs/agents/history.md`)**: Reverse-chronological 3–4 line summaries of closed milestones (what shipped, what was retired on purpose, and what was deliberately deferred). Read on demand only.
- **Durable domain & traps (`CONTEXT.md` and `docs/adr/`)**: Timeless glossary terms, permanent repo invariants/traps, and architectural decisions.

## Lifecycle

1. **Start / Device Pickup (Read without hijacking)**
   - Before modifying files, read `docs/agents/session.md` if present.
   - When picking up a branch after `git pull` on another machine, run or flag any commands listed under `Env / DB Sync` (`php artisan migrate && php artisan migrate --env=testing`, `composer install`, `npm install`) before running tests or editing.
   - Respect every active stream's `Touched scope`, `Uncommitted / Local-only`, and `Off-Limits State`. Follow the user's current prompt; auto-resume `Next` only when the user asks to continue or pick up that handoff.

2. **Finish Turn (Overwrite in place, <=60 lines)**
   - Whenever a turn modifies repo files, changes verification status, alters migrations/lockfiles/`.env.example`, or hits a blocker, overwrite `docs/agents/session.md` in place—replace superseded bullets instead of appending chronological logs.
   - Keep entries strictly machine- and harness-portable: use repo-relative paths and git branch names only (no `D:/...`, `C:/Users/...`, `.codex/`, `.gemini/`, or `.claude/` paths).
   - Leave changes uncommitted on disk so any manual `git commit && git push` by the user automatically includes the updated `session.md`. Skip editing `session.md` on read-only Q&A or reviews unless requested.

3. **Cross-Device Checkpoint (Explicit request only)**
   - When the user explicitly asks to *checkpoint* or *push handoff*, update `docs/agents/session.md` (`Uncommitted / Local-only: none (checkpointed)` + current `Env / DB Sync`), stage the stream's changes alongside `docs/agents/session.md`, create a `wip(<stream-slug>): ...` commit, and push the current branch.

4. **Close Stream / Merge to `main` (Promote, log & clear)**
   - Keep finished unmerged work marked `ready-for-review` (WIP commits keep `session.md` intact; never delete `session.md` on a WIP commit).
   - Only when closing a completed stream or preparing a merge to `main`:
     1. Move any `Promote on Close` items into `CONTEXT.md` or `docs/adr/`.
     2. Prepend a 3–4 line summary (`### YYYY-MM-DD (<sha-or-branch>) — <stream-slug>`, delivered scope, retired code, deliberate deferrals) to the top of `docs/agents/history.md`.
     3. Remove the closed stream from `session.md`—deleting `docs/agents/session.md` once no active streams remain.

## Board Schema

```markdown
# Live Session Board (<branch> — updated YYYY-MM-DD by <harness>)

## Active Streams
### 1. <stream-slug>: <one-line goal> [in-progress | blocked | ready-for-review]
- **Touched scope**: <repo-relative paths or globs owned by this stream across the branch>
- **Uncommitted / Local-only**: <dirty files on current machine, or "none (checkpointed)">
- **Env / DB Sync**: <"none", or exact commands needed after git pull: migrate, composer/npm install, .env keys>
- **State & verified**: <concise bullets: what changed, exact test/build counts, repo-relative paths only>
- **Next**: <1–3 concrete actions, or "Await user review / commit">

## Off-Limits State
- <Untouched dirty files, sibling branches/worktrees by branch name, or local DB constraints incoming agents must leave alone>

## Promote on Close
- <New permanent glossary terms or repo traps to move into CONTEXT.md before closing this stream>
```
