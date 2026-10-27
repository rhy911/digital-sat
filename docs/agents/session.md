# Live Session Board (minhdev — updated 2026-10-28 by Codex)

## Active Streams

### 1. production-remediation-and-cleanup [ready-for-review]
- **Touched scope**: Engine timing/progression, scoring, uploads, assignments, product UI, PHPStan, CI, and `docs/production-remediation-handoff.md`.
- **Local-only**: Committed previously on `minhdev`; push and deployment pending.
- **State**: Break timing synchronizes with server state, retries failures, follows advancement/completion, and rechecks on browser wake. Select errors are visible; redundant model logic was removed.
- **Verification**: The full current tree passed 665 PHP tests (3,015 assertions), 28 JavaScript tests, and Vite (71 modules). Scoped PHPStan/Pint and diff checks passed. Browser/cPanel checks remain pending.
- **Next**: Await push/deployment instructions; use the remediation handoff checklist.

### 2. public-index-and-public-trial [ready-for-review]
- **Touched scope**: Public landing/trial UI, static demo, guest starts/results, auth handoff, shared trial validation, database command guards, and regression tests.
- **Local-only**: Included in the current local commit on `minhdev`; not pushed or deployed. The SQL backup remains untracked and excluded.
- **State**: Public trials provide RW/Math modules and adaptive full practice with strict structure validation, session/IP start locks, and forced continuation of active trials. Demo content contains 6 RW and 6 Math questions; trial titles are masked for guests.
- **State**: Snapshot-backed partial reports gate full analysis/export. Signup/signin merge valid results; signed verification links return to the report and recover expired results/mail failures. Guests cannot enter account dashboards.
- **UI**: Responsive public navigation, shared CTA tokens, realistic question/report examples, 900x500 engine requirement, entry confirmations, custom exit confirmations, disabled-until-selected module starts, keyboard access, and notification badge refinements.
- **Env / DB Sync**: Configure `PUBLIC_TRIAL_*` content ULIDs per environment. `public-trial:prepare-content` derives/validates draft modules; review and publish them separately. Local derived modules and all 49 questions were already reviewed and published; preserve them.
- **Verification**: Covered by the full-tree results above. Latest CSS-only refinement passed Vite and the UI detector. Production browser verification remains pending.
- **Next**: Await review/push instructions; content setup and rollout details are in `docs/public-trial.md`.

### 3. public-trial-retention [ready-for-review]
- **Touched scope**: UTC retention deadlines/index, attempt-specific renewal, guest cookies, IP creation budgets, bounded cleanup command, five-minute scheduler, auth expiry notices, and regression tests.
- **Local-only**: Included in the current local commit on `minhdev`; no application migration or purge performed.
- **State**: Guest trials expire after 30 minutes idle or 24 hours absolute. Browser-session cookies and session-storage backups limit persistence. New attempts share HMAC-keyed IP budgets of 10/10 minutes and 50/hour; resuming existing work uses no creation budget.
- **State**: Atomic renewal cannot shorten newer deadlines. Cleanup rechecks eligibility/locks, preserves shared content and protected identities, advances a bounded guest cursor, and removes expired caches and owned artifacts safely. Valid account merges clear deadlines.
- **Env / DB Sync**: Deploy the additive migration, review `public-trial:purge --initialize --dry-run`, initialize legacy deadlines, then review `public-trial:purge --dry-run` on the intended database. Verify trusted proxies and the five-minute scheduler before enabling `PUBLIC_TRIAL_CLEANUP_ENABLED=true` (default false).
- **Verification**: Included in the full-tree PHP/JS/build results above. Local `digital_sat` trial records were not purged.
- **Next**: Follow `docs/public-trial.md` rollout after deployment authorization.

### 4. test-timer-hardening [ready-for-review]
- **Touched scope**: Server timing/checkpoints, answer deadlines, controller leases/takeover, practice pauses, continuous assignment clocks, browser recovery, legacy conversion, and regression tests.
- **Local-only**: Included in the current local commit on `minhdev`; no migration/conversion applied to application data.
- **State**: Assigned/live exams advance continuously through modules and breaks while absent; only pre-deadline server answers count. Practice pauses and covers questions on hide/disconnect/exit and requires explicit Resume/Continue, including timeout and zero-length breaks.
- **State**: Single-controller tokens protect mutations. Takeover preserves engine initialization; stale heartbeat replies cannot erase rotated tokens. Storage failures cannot block autosave. Dead grace APIs, preview arguments, pause arithmetic, speculative indexes, and duplicate pause handling were removed.
- **Env / DB Sync**: Deploy the additive control migration during maintenance. Review `assignments:convert-paused-clocks --dry-run --max-assignments=100`, convert bounded batches, then review/run `assignments:finalize-expired` before reopening traffic. Resumed module clocks take precedence over historical break receipts; overdue schedules cascade without a reset to now.
- **Verification**: Full PHP 665/3,015 assertions; JavaScript 28; Vite 71 modules; scoped PHPStan 0 errors; scoped Pint and diff checks passed. PHPUnit used only `sat_app_testing`.
- **Next**: Follow `docs/test-timer-hardening-plan.md`; browser/cPanel validation and deployment remain pending.

## Off-Limits State
- Sibling worktree on `codex/score-timer-production-fix` (at `417a439`) remains untouched.
- Local `digital_sat` has not received the retention/control migrations; no application-data conversion or cleanup was run.
- `storage/backup_digital_sat_20261027.sql` is a local database backup; leave untracked and do not delete.
- Earlier local commits, including `29d2f9e`, have not been pushed or deployed in this session.
