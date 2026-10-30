# Live Session Board (main — updated 2026-10-30 by Codex)

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

### 5. dfet-sat-branding [ready-for-review]
- **Touched scope**: Existing brand SVG/PNG assets and manifest, visible Blade branding, public metadata, verification email copy, trial-content labels, and related test expectations.
- **Uncommitted / Local-only**: Branding changes remain uncommitted on `main`; no push or deployment.
- **State**: Brand is DFET SAT; logo tagline is Digital SAT Practice. Original DFET icon pixels are unchanged; PNGs are transparent 900x216. Web/PDF variants, accessible labels, navigation, and landing title/description are synchronized; obsolete split-wordmark CSS was removed.
- **Env / DB Sync**: None. Locked development dependencies were restored locally with `composer install`; dependency manifests are unchanged. No application data was migrated or renamed.
- **Verification**: PHP 34/34 (205 assertions, testing DB only); JavaScript 28/28; Vite 71 modules; scoped PHPStan 0 errors. Pint passed on two clean files; two string-only PHP edits retain identical baseline style findings. Six browser checks passed across landing/signin at 1440/390/320px; UI detector findings were pre-existing.
- **Next**: Await user review/commit.

### 6. unified-auth-experience [ready-for-review]
- **Touched scope**: Unified signin/signup and role routing, verification and password recovery, shared auth tokens/components, guest exam entry and result handoff, teacher application status, route/controller regressions, and auth JavaScript behavior tests.
- **Uncommitted / Local-only**: Auth work remains uncommitted on `main`; existing branding and unrelated dirty changes are preserved. The untracked SQL backup remains excluded.
- **State**: `/signin` is the canonical role-agnostic login and `/signin/form` redirects. Signup is one form with student default and teacher approval notice. Authenticated entry routes through `/dashboard`; verification has explicit Continue and no countdown redirect. AJAX and native forms retain field errors, safe response text, loading recovery, and server-owned resend cooldowns.
- **State**: Password recovery/reset and verification notices use persistent flash messages. Verification instructions remain accurate when mail delivery fails. Guest exam/trial merges and eligible result returns remain covered; pending teachers continue through the dashboard approval gate.
- **Verification**: Full PHP suite passed 672 tests / 3,051 assertions on the implementation state; the final signup delivery-failure copy regression passed separately (1 test / 8 assertions). JavaScript suite passed 34/34; Vite built 71 modules; scoped PHPStan reported no errors; scoped Pint and `git diff --check` passed. Signin, signup, and recovery were inspected at 320, 390, 768, and 1440px with no horizontal overflow; keyboard validation/focus, password toggle, reduced-motion preference, and a 200% CSS zoom simulation were checked. Full browser inspection of authenticated teacher-status and verification-result screens remains unverified. The one Impeccable detector warning is the requested Plus Jakarta Sans font being common.
- **Env / DB Sync**: PHPUnit used `sat_app_testing`; no migrations or application data changes were made. No engine files were intentionally changed.
- **Next**: Await review; do not commit or deploy without instruction.

### 7. guest-reports-and-host-attempt-deletion [ready-for-review]
- **Touched scope**: `GuestExamController`, `ScoreController`, `EnsureEmailIsVerified`, `ExamSessionController`, `ExamSessionService`, `routes/web.php`, `resources/views/guest/result.blade.php` (removed), `resources/views/student/scores/show.blade.php`, `resources/views/teacher/exam-sessions/partials/candidate-rows.blade.php`, `resources/css/shell.css`, and `tests/Feature/ExamSessionTest.php`.
- **Uncommitted / Local-only**: Changes remain uncommitted on `main`; sibling worktree and untracked SQL backup are preserved.
- **State**: Guest completion/result routes redirect directly to the shared detailed report with ownership checks and active-attempt resume preserved. Guests immediately see full question review, scores, analysis, correct answers, explanations, PDF export, and mistake reflection without signup. Navigation rail, score sidebar, and View Progress are hidden for guests, and optional account-link links (sign up/sign in) are surfaced. Attempt deletion replaces reset with `DELETE /teacher/exam-sessions/{examSession}/attempts/{userTest}`; host/admin authorization and session membership are validated; deletion adheres to the cleanup locking pattern with owned locks released; dependent records cascade while users and tests are preserved; and candidates may rejoin while open.
- **Verification**: `ExamSessionTest` passed 49/49 tests (218 assertions); `ScoreReportOverhaulTest` passed 31/31 (200 assertions); `PublicTrialTest` passed 22/22 (166 assertions); `UserTestAnswerReviewTest` passed 2/2 (16 assertions); Unit testsuite passed 129/129 (412 assertions); Node JS suite passed 34/34; Vite built 71 modules; scoped PHPStan reported 0 errors; `git diff --check` passed. Database operations used only `sat_app_testing`.
- **Next**: Await review; keep uncommitted.

### 8. exam-join-start-button-ux [ready-for-review]
- **Touched scope**: `resources/views/guest/join.blade.php`, `resources/css/auth.css`, and `tests/Feature/ExamSessionTest.php`.
- **Uncommitted / Local-only**: Changes remain uncommitted on `main`; sibling worktree and untracked SQL backup are preserved.
- **State**: In the exam join screen (`/exam-join/{code}`), `#examJoinSubmit` is dimmed (`opacity: 0.5`, `cursor: not-allowed`) with `aria-disabled="true"` when guest name input is required and empty. Dynamically toggles brightness and `aria-disabled` as the user types; submission attempts on an empty field focus the input and report validity without submitting. Active state renders at full brightness without `aria-disabled` when the candidate is signed in or initial input exists.
- **Verification**: `ExamSessionTest` passed 49/49 tests (218 assertions); Unit testsuite passed 129/129; Node JS suite passed 35/35; Vite built 71 modules cleanly; `git diff --check` passed. Tested with `sat_app_testing`.
- **Next**: Await review; keep uncommitted.

### 9. break-clock-sync-flash-fix [ready-for-review]
- **Touched scope**: `app/Services/AttemptEngine.php`, `resources/js/test/navigation.js`, and `tests/JavaScript/break.test.js`.
- **Uncommitted / Local-only**: Changes remain uncommitted on `main`; sibling worktree preserved.
- **State**: `scoreAndAdvance` computes and returns `remaining_break_seconds` alongside `break_ends_at`. `navigation.js` passes `remainingSeconds` directly to `showInterSectionBreak()`, eliminating the brief `SYNC` text flash on `#breakTimerDisplay` before the countdown begins.
- **Verification**: Node JS suite passed 35/35 (added immediate rendering assertion); Unit testsuite passed 129/129; scoped PHPStan 0 errors; Vite built 71 modules; `git diff --check` passed.
- **Next**: Await review; keep uncommitted.

### 10. guest-report-redirect-and-button-cleanup [ready-for-review]
- **Touched scope**: `app/Services/AttemptEngine.php`, `resources/js/test/navigation.js`, `resources/views/student/scores/show.blade.php`, and `resources/css/student/scores.css`.
- **Uncommitted / Local-only**: Changes remain uncommitted on `main`; sibling worktree preserved.
- **State**: Root-relative URLs in `completionDestination` (`route(..., false)`) and client-side URL normalization in `navigation.js` prevent host drift to `.env` `APP_URL` (`digital-sat.test`) when testing on localhost/127.0.0.1. Deduplicated report buttons to a single top-bar pair (`Sign Up` and `Sign In`) with inline links in the notice banner; styled `.sd-guest-btn-primary` in brand gold (`#f6b93b` on `#10234a`) and `.sd-guest-btn-secondary` in bordered white, eliminating invisible white-on-white text.
- **Verification**: `ExamSessionTest` passed 49/49 tests (218 assertions); Unit testsuite passed 129/129; Node JS suite passed 35/35; scoped PHPStan reported 0 errors; Vite built 71 modules cleanly; `git diff --check` passed.
- **Next**: Await review; keep uncommitted.

## Off-Limits State
- Pre-existing edits to engine JS/layout, shared CSS/loading screen, and practice index are preserved; branding only adjusts the shared wordmark CSS and engine exit label.
- Sibling worktree on `codex/score-timer-production-fix` (at `417a439`) remains untouched.
- Local `digital_sat` has received the additive control migration (`2026_10_28_000001_add_attempt_control_state_to_user_tests`); no application-data conversion or cleanup was run.
- `storage/backup_digital_sat_20261027.sql` is a local database backup; leave untracked and do not delete.
- Earlier local commits, including `29d2f9e`, have not been pushed or deployed in this session.
