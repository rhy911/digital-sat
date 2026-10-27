# Public trial

The public landing links to three account-free experiences: a static 12-question interface demo, one complete Reading and Writing or Math module, and one adaptive full practice test. Module and full-test attempts use the normal engine, scoring, snapshots, and paused-practice timing. The trial marker limits guest analysis while retaining ordinary attempt ownership and engine access.

## Content setup

Set these ULIDs per environment in `.env` before running the content command:

```dotenv
PUBLIC_TRIAL_SOURCE_TEST_ULID=01KW8MCQXY5V528GATFHM44YK8
PUBLIC_TRIAL_RW_TEST_ULID=01M5WJD3DSPQJ3XTA2NY4Z035S
PUBLIC_TRIAL_MATH_TEST_ULID=01M5WJD3DSPQJ3XTA2NY4Z035T
PUBLIC_TRIAL_FULL_TEST_ULID=01KW8MCQXY5V528GATFHM44YK8
```

The source must contain the configured RW and Math Module 1 ULIDs in `PreparePublicTrialContent`. Run `php artisan public-trial:prepare-content` after the source test and question bank are present. The command derives each `module_only` test through `TestManagementService`, shares the source questions without editing them, and validates its structure while leaving newly created tests as drafts. It is safe to run again after successful setup.

Before enabling the options on an environment, review both module tests in the Test Builder: question order, answer keys and explanations, passage/table/chart display, formula rendering, timing, and pretest configuration. Publish each reviewed module as public and active, then run the test command once per database. A missing ULID, inactive source, or invalid test leaves that option unavailable; it never substitutes a different test. Keep the ULIDs stable within an environment so existing attempts can resume if configuration changes.

The full-test ULID points to an active adaptive test with two routed Module 2 branches. The student receives 98 questions across the four issued modules, not all 147 items in the source bank.

## Access and results

The result page exposes correct/total counts, available SAT estimate and range, and one snapshot-backed sample review. Full question analysis, PDF export, and answer-review mutation require an account. After signup or signin, the guest attempt is merged into the selected account; verification links carry a signed attempt ULID so the user returns to that report after verifying.

The interface demo is static and untimed. It creates no guest user or attempt. Existing teacher exam-code guests are not marked as public trials and keep their existing result access.

## Guest retention and creation limits

An unsaved guest trial expires after 30 minutes without qualifying activity and after 24 hours at the latest. Opening or working on an attempt refreshes only that attempt. Signup/signin handoff protects the selected trial while it remains within the 24-hour limit. A successful merge clears its deadlines so the saved result follows normal account retention. The public result page shows its current inactivity deadline. Browser closure does not guarantee immediate server deletion.

New public guest attempts share an IP budget of 10 per 10 minutes and 50 per hour. Reusing an existing attempt does not consume the budget. The request-level route throttle remains a separate burst guard. IP keys contain an HMAC, not the address. `Request::ip()` is used, so verify the trusted-proxy configuration against the actual hosting proxy before enabling public trial creation behind a proxy; do not trust arbitrary forwarded headers.

The additive retention migration adds the two guest-trial deadlines and cleanup index. After deploying the code and migration, initialize existing eligible guest trials once:

```sh
php artisan public-trial:purge --initialize --dry-run
php artisan public-trial:purge --initialize
php artisan public-trial:purge --dry-run
```

Initialization grants one 30-minute activation window to existing attempts, including attempts older than 24 hours. The first dry run is read-only. Review the second dry run to confirm that teacher exams, assignments, and account-owned attempts are excluded. Physical deletion stays disabled until `PUBLIC_TRIAL_CLEANUP_ENABLED=true` is set after those checks and the every-five-minute scheduler is confirmed. The purge command scans bounded batches, removes expired attempts and cascaded answer snapshots, and deletes empty guest identities only after checking all database foreign-key references. Disabling cleanup stops deletion while expiry checks remain active. Deleted rows require restoration from backup.
