# Live Session Board (minhdev — updated 2026-10-26 by Codex)

## Active Streams

### 1. production-remediation-and-cleanup: Review fixes resolved [ready-for-review]
- **Touched scope**: Existing remediation changes across engine timing/progression, scoring, uploads, assignments, product UI, PHPStan typing, CI, tests, and `docs/production-remediation-handoff.md`. Follow-up review fixes touch `resources/js/test/break.js`, `resources/js/test/navigation.js`, `resources/views/components/ui/select.blade.php`, `resources/views/engine/break.blade.php`, `app/Models/Module.php`, `app/Models/UserTestAnswer.php`, `app/Http/Controllers/Engine/SessionController.php`, and focused tests.
- **Uncommitted / Local-only**: Remediation committed locally on `minhdev`; push and deployment remain pending.
- **Env / DB Sync**: `npm run build` completed. Shared testing database `sat_app_testing` preserved.
- **State & verified**:
  - Follow-up fixes use the server timing endpoint for break countdown, show `SYNC` and retry after timing request failures, follow server navigation when another tab advances/completes, and recheck on focus/page restore/visibility/expiry. Required-select errors are visible and styled. Removed redundant model fallback branches and closure alias; removed unused break deadline view data.
  - CI includes MySQL 8 PHPUnit, JavaScript tests, Vite build, PHPStan, and dependency audits. Deployment/rollback checklist is in `docs/production-remediation-handoff.md`.
  - Focused PHPUnit: 48 passed (170 assertions). JavaScript: 20 passed, including server timing failure, stale-tab advancement, and completion redirects. Scoped PHPStan: 3 files, 0 errors. Vite build: 71 modules, passed. `git diff --check` passed with the existing `resources/css/shell.css` CRLF notice.
  - Earlier full suite: 627 passed (2,773 assertions); full-suite verification predates the latest review fixes. Production browser and cPanel checks remain pending.
- **Next**: Await push/deployment instructions; production browser and cPanel checks remain pending.

## Off-Limits State
- Sibling worktree on `codex/score-timer-production-fix` (at `417a439`) — untouched.
- Commit `29d2f9e` — local only; no push or deployment made.
