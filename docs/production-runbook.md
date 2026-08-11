# Production Runbook

Everything that has to be true on the production server, and every command worth
running there. Written for the current host: **iNET cPanel shared hosting**, no
root, no supervisor, `shell_exec` disabled.

Companion docs: `docs/infrastructure-analysis.md` (why the stack is shaped this
way, and the upgrade path), `config/scoring.php` (the timing invariants that
govern module submission).

---

## 0. Host facts you must not forget

| Fact | Consequence |
|---|---|
| The `php` on `PATH` is **older than 8.2** | Every cron line and every manual artisan call must use the absolute binary. A bare `php artisan` silently does nothing and still exits 0. |
| PHP 8.2 binary: `/opt/cpanel/ea-php82/root/usr/bin/php` | Referred to as `$PHP_BIN` below. `deploy.sh` resolves this itself and hard-fails under 8.2. |
| No supervisor, no root | The queue worker is **cron-driven**, not a daemon. `deploy.sh`'s `supervisorctl` line is commented out on purpose. |
| `shell_exec` disabled | Nothing in the app can detect the worker by shelling out to `pgrep`. `sat:queue-health` measures worker liveness by job age instead. |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` are all **`database`** | Every `/submit-status` poll costs ≥3 MySQL ops on the same database that is writing answers. This is the real scale ceiling (~1.5k–2.5k concurrent), not PHP. |
| Data lives in Vietnam (Viettel IDC / VNPT) | Decree 53/2022 localization is already satisfied. Do not move student PII to AWS/GCP Singapore, Laravel Cloud, DigitalOcean or Hetzner without legal sign-off. |

Throughout this doc:

```bash
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
APP_DIR=/home/USER/PROJECT        # the directory containing artisan
```

Substitute your real path once and the rest copy-pastes.

---

## 1. One-time setup

### 1.1 Cron jobs

Three lines must exist. Add via **cPanel → Advanced → Cron Jobs**, or `crontab -e`.

```cron
# 1. Queue worker — scoring, Module 2 routing, notifications.
* * * * * /usr/bin/flock -n /tmp/sat-queue.lock /opt/cpanel/ea-php82/root/usr/bin/php /home/USER/PROJECT/artisan queue:work --max-time=55 --sleep=1 >> /dev/null 2>&1

# 2. Laravel scheduler — drives EVERY scheduled command (see §1.2).
* * * * * /usr/bin/flock -n /tmp/sat-schedule.lock /opt/cpanel/ea-php82/root/usr/bin/php /home/USER/PROJECT/artisan schedule:run >> /dev/null 2>&1
```

For a mock exam, run a **second** worker line with its own lock file
(`/tmp/sat-queue-2.lock`). The I2 invariant in `config/scoring.php` is derived
assuming 2 workers at 70 students.

**Why `flock`:** without it, a slow run stacks a new PHP process every minute
until the account hits its process limit. `-n` means "skip this tick if the
previous one is still running", which is exactly the desired behaviour.

**Why `--max-time=55` and not `--stop-when-empty`:** `--stop-when-empty` exits
the instant the queue drains, so a burst landing two seconds later waits until
the next minute tick — that is the up-to-60s scoring delay observed during the
2026-08-09 mock exam. `--max-time=55` keeps the worker alive polling for the
whole minute and exits cleanly before the next tick.

### 1.2 What the scheduler runs

Adding the `schedule:run` line activates all four of these. Verify with
`$PHP_BIN artisan schedule:list`.

| Command | Frequency | Purpose |
|---|---|---|
| `assignments:finalize-expired` | every minute | Auto-submits and advances assignment attempts whose module time ran out while the student's browser was closed. **Without this, a closed tab leaves an attempt `in_progress` indefinitely.** |
| `sat:queue-health` | every minute | Logs queue depth, oldest job age, failures, live submit locks to `storage/logs/queue.log`. Your exam-day telemetry. |
| `logs:clear` | daily | Truncates `storage/logs/laravel.log`. |
| `queue:prune-failed --hours=168` | daily | `failed_jobs` grows unbounded otherwise. |

### 1.3 Verify setup

```bash
crontab -l                                      # expect the two lines from §1.1
$PHP_BIN -v                                     # expect 8.2+
cd $APP_DIR && $PHP_BIN artisan schedule:list   # expect four entries
cd $APP_DIR && $PHP_BIN artisan about           # confirm env=production, debug=false
```

### 1.4 `.env` expectations

```dotenv
APP_ENV=production
APP_DEBUG=false          # never true in production — leaks stack traces
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

`DB_QUEUE_RETRY_AFTER` is deliberately **not** in `.env`: the 180s value that
satisfies invariant I1 is the default in `config/queue.php`, so production picks
it up without anyone having to remember to add a key. Only set it in `.env` if
you are intentionally overriding, and then re-check I1 against
`SCORING_JOB_TIMEOUT`.

`APP_DEBUG=true` in production is the single highest-impact misconfiguration
available here — it exposes database credentials and file paths in error pages
to anyone who can trigger a 500.

---

## 2. Deploying

**The production deploy is a zip upload through cPanel File Manager, not git.**
Dependencies and assets are built locally and shipped as artifacts; artisan is
run afterwards from **cPanel Terminal** (no SSH).

> `deploy.sh` in the repo root assumes git + composer + npm **on the server** and
> starts with `git pull --ff-only origin main`. It does not describe this process
> and should not be run as-is. Keep it only as a reference for what the post-upload
> artisan sequence should be.

### 2.0 Four things that must never be in the zip

This is the whole risk of upload-based deploys. Extraction overwrites
same-named files silently, so anything local that shadows a production file
lands in production.

| Never upload | What happens if you do |
|---|---|
| `.env` | Production DB credentials and `APP_KEY` replaced by local ones. A changed `APP_KEY` logs out every session and makes existing encrypted values undecryptable. Worst case on this list. |
| `bootstrap/cache/*.php` | Production boots with **your local cached config** — `APP_ENV=local`, `APP_DEBUG=true`, local DB host. Either an outage or a credential leak on the next 500. |
| `storage/` | Uploaded question media lives in `storage/app/public`. Local logs and framework caches overwrite server ones; a stale local copy can shadow real files. |
| `public/storage` | It is a **symlink**. Zip tools frequently store it as a plain directory, which then blocks `storage:link` and breaks every question image. |

Build the zip from an explicit include list, not by zipping the project folder
and deleting things afterwards.

### 2.1 Build locally

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan test        # nothing gates this automatically — see §7
```

`composer.json` pins `config.platform.php` to `8.2.31`, so packages resolve
against the server's PHP version rather than whatever you run locally. Do not
remove that pin.

### 2.2 What to include

Normal code release:

```text
app/  bootstrap/app.php  config/  database/  resources/  routes/
public/  (including public/build, EXCLUDING public/storage)
composer.json  composer.lock  artisan
```

Add `vendor/` **only when `composer.lock` changed** — it is large and unchanged
between most releases.

`public/build` and the PHP that references it must ship **together**. Uploading
PHP without a rebuilt `public/build`, or vice versa, produces a Vite manifest
mismatch and a hard 500 on every page.

### 2.3 Upload and extract

1. **cPanel Terminal:** `cd $APP_DIR && $PHP_BIN artisan down --retry=60`
2. File Manager → upload the zip into `$APP_DIR`.
3. Extract, overwriting existing files.
4. Delete the zip from the server.
5. **cPanel Terminal**, in order:

```bash
cd $APP_DIR
$PHP_BIN artisan optimize:clear     # MUST come first — drops stale cached config/routes/views
$PHP_BIN artisan migrate --force    # skip only if the release has no new migration
$PHP_BIN artisan storage:link       # only if public/storage is missing or broken
$PHP_BIN artisan optimize           # rebuild config + route + view caches
$PHP_BIN artisan queue:restart      # workers exit after their current job; cron relaunches them
$PHP_BIN artisan up
```

`optimize:clear` before `optimize` is not optional. Cached config from before the
upload survives extraction, so new `.env` values and new `config/` entries are
invisible until the cache is dropped and rebuilt.

### 2.4 Verify

```bash
$PHP_BIN artisan about              # env=production, debug=false
$PHP_BIN artisan migrate:status | tail -n 15
$PHP_BIN artisan schedule:list      # four entries
ps -eo cmd | grep '[a]rtisan queue:work'
```

**Zero queue workers is a production incident**, not a warning. With no worker,
terminal module submissions never score and students poll `/submit-status`
forever. Fix before anything else (§5.1).

Then load the site and confirm assets render — a blank or unstyled page is the
signature of a `public/build` mismatch.

### 2.5 Deleted files do not disappear

Extraction only adds and overwrites. A file you deleted locally stays on the
server forever unless you delete it by hand in File Manager. Class autoloading
follows the locally-generated `vendor/composer/autoload_classmap.php`, so an
orphaned PHP class is usually inert — but **orphaned Blade views and stale
`public/build` assets are still reachable**. When a release deletes a view or a
route, remove the file on the server in the same session.

### 2.6 Checklist for a release touching scoring or the engine

- [ ] `php artisan test` green locally (301 tests at time of writing).
- [ ] Zip built from the include list — no `.env`, no `bootstrap/cache`, no `storage/`, no `public/storage`.
- [ ] `public/build` rebuilt and included if any JS/CSS changed.
- [ ] Deploy outside exam hours.
- [ ] `sat:queue-health` clean before and after.
- [ ] One end-to-end attempt on production: start → submit module → see score.

---

## 3. Exam-day operations

### 3.1 Before (day of)

```bash
cd $APP_DIR
$PHP_BIN artisan sat:queue-health            # pending/reserved should be ~0
crontab -l                                   # workers + scheduler still present
$PHP_BIN artisan queue:failed                # should be empty
$PHP_BIN artisan assignments:finalize-expired --dry-run
```

Add the second worker cron line if you expect 50+ concurrent students.

**Consider staggering start times.** The load shape here is a synchronized
burst — everyone starts, times out, and submits together — so the hot spot is
the ~60 seconds around module submit. Staggering by 5 minutes per group removes
most of the peak for free, and is cheaper than any technical fix.

### 3.2 During

```bash
tail -f storage/logs/queue.log
```

Watch for:

| Log key | Meaning |
|---|---|
| `queue_health` | Per-minute snapshot. `oldest_pending_age_s` climbing = the worker is not keeping up (or is dead). |
| `score_module_job` | Per-job `duration_ms` and `queue_wait_ms`. `duration_ms` p50 ≥ 3000 means `lock_ttl` needs raising — see I2 in `config/scoring.php`. |
| `conflict.lock_held` | Benign. A submission is in flight; the client polls. |
| `conflict.stale_receipt` / `conflict.module_mismatch` | A client submitted a module the attempt already left. Investigate if frequent. |
| `timeout_sweep.module_submitted` | The auto-submit sweeper closed out an absent student's module. Expected on any exam where someone closes their tab. |
| `timeout_sweep.stuck_receipt` | **Investigate.** A module scored but the attempt never advanced. The sweeper deliberately refuses to repair this. |

`sat:queue-health` exits non-zero when the oldest pending job is older than
`lock_ttl`, so it works as an alarm condition if you ever wire one up.

### 3.3 After

```bash
$PHP_BIN artisan assignments:finalize-expired --dry-run   # who is still open
$PHP_BIN artisan assignments:finalize-expired             # close them out
$PHP_BIN artisan queue:failed
```

The scheduler already does this every minute; running it by hand is for
confirming a clean finish, and for the case where the scheduler cron was missing.

---

## 4. Command reference

All commands run from `$APP_DIR` with `$PHP_BIN`.

### Assignment attempt timeouts

```bash
artisan assignments:finalize-expired --dry-run   # list attempts past deadline, change nothing
artisan assignments:finalize-expired             # submit + advance them to completion
```

Only touches attempts with an `assignment_id` and `status = in_progress` whose
clock has started. Practice attempts are never swept — they are allowed to pause
while the student is away. It takes the same submit lock as a live student, so it
backs off rather than racing anyone mid-submission.

### Queue

```bash
artisan sat:queue-health          # table
artisan sat:queue-health --json   # single JSON line, for piping
artisan queue:failed              # list failures
artisan queue:retry all           # re-run all failed jobs
artisan queue:retry <uuid>        # re-run one
artisan queue:restart             # signal workers to exit after current job
artisan queue:work --max-time=55  # run a worker in the foreground (debugging)
```

### Cache and config

```bash
artisan optimize        # cache config + routes + views (deploy.sh does this)
artisan optimize:clear  # drop all caches — do this after ANY .env edit
artisan config:clear
artisan cache:clear
```

`.env` changes have **no effect** until the config cache is rebuilt. This is the
most common "I changed it and nothing happened" cause on this host.

### Database

```bash
artisan migrate --force
artisan migrate:status
artisan db:show
```

### Diagnostics

```bash
artisan about              # environment, drivers, cache state
artisan schedule:list      # what the scheduler will run and when
artisan schedule:run       # run due tasks once, by hand
artisan tinker
artisan logs:clear
```

---

## 5. Failure playbook

### 5.1 Students stuck on "Scoring in progress…"

**Cause:** no queue worker. Terminal submissions dispatch `ScoreModuleJob`; with
nothing consuming the queue, the client polls until its 300s budget expires.

```bash
ps -eo cmd | grep '[a]rtisan queue:work'      # expect ≥1
$PHP_BIN artisan sat:queue-health             # pending climbing, oldest age growing
crontab -l                                    # is the worker line there? correct PHP path?
```

Immediate mitigation — run a worker by hand in an SSH session:

```bash
cd $APP_DIR && $PHP_BIN artisan queue:work --max-time=600
```

Then fix the cron. If jobs already failed: `artisan queue:failed`, then
`artisan queue:retry all`.

Emergency alternative: set `SCORING_INLINE_NON_FINAL=true` (already the default)
keeps non-terminal modules off the queue entirely — only the final submission
needs a worker.

### 5.2 Assignment attempts stuck "In progress"

**Cause:** the `schedule:run` cron is missing, so the sweeper never fires.

```bash
crontab -l | grep schedule:run
$PHP_BIN artisan assignments:finalize-expired --dry-run
$PHP_BIN artisan assignments:finalize-expired
```

The app also finalizes lazily when a student re-enters the assignment or a
teacher opens the assignment report, so this degrades to "stale until someone
looks" rather than lost data. But statuses shown to teachers will be wrong until
the cron exists.

### 5.3 Scheduled commands silently stopped

Both per-minute scheduled commands use `withoutOverlapping()` with a **bounded**
expiry (2 minutes for `sat:queue-health`, 5 for `assignments:finalize-expired`).
If a process is killed by a host resource limit mid-run, the lock expires on its
own and the next tick proceeds.

If they still appear stuck, clear the overlap locks:

```bash
$PHP_BIN artisan cache:clear
```

### 5.4 `module_progression_conflict` popups during an exam

Read the `conflict.*` keys in `storage/logs/queue.log` to classify it, then check
the invariants in `config/scoring.php` against the observed `duration_ms`. If job
p50 is ≥3s, either raise `SCORING_LOCK_TTL` (and `SCORING_CLIENT_BUDGET` with it,
to preserve I3) or add a worker. Do not tune one number in isolation.

### 5.5 Uploaded new code but production behaves like the old code

In order of likelihood:

1. **Stale cache.** `optimize:clear` was skipped, so cached config/routes/views
   from before the upload are still being served. Run `optimize:clear` then
   `optimize`.
2. **Wrong PHP binary.** A bare `php artisan` on this host runs a PHP older than
   8.2, fails to boot Laravel 12, and can exit without an obvious error — the
   command appears to succeed and does nothing. Always use `$PHP_BIN`.
3. **Files did not actually land.** Check the modified timestamp in File Manager
   on a file you know changed. A zip extracted into the wrong directory (one level
   up, or into a nested folder of the same name) is easy to miss.
4. **Queue workers still running old code.** They boot the framework once and
   keep it in memory. `queue:restart` is what makes them pick up new job classes.

### 5.6 Question images stopped loading after a deploy

`public/storage` was overwritten by a real directory instead of staying a
symlink. `storage:link` will refuse to overwrite it, so remove it first in File
Manager, then:

```bash
cd $APP_DIR && $PHP_BIN artisan storage:link
```

If `storage/app/public` itself was overwritten by a local copy, the files are
gone from the server and must be restored from a backup — this is why `storage/`
is on the never-upload list (§2.0).

---

## 6. Tuning knobs

`config/scoring.php`, all overridable from `.env` without a code deploy — but
`artisan optimize:clear` is required after editing:

| Variable | Default | Notes |
|---|---|---|
| `SCORING_JOB_TIMEOUT` | 120 | Must stay **below** `DB_QUEUE_RETRY_AFTER` (I1). |
| `SCORING_LOCK_TTL` | 240 | Must be ≥ max queue wait + job timeout (I2). |
| `SCORING_CLIENT_BUDGET` | 300 | Must be **strictly** greater than lock TTL (I3). |
| `SCORING_RESULT_TTL` | 900 | Must exceed client budget (I4). |
| `SCORING_INLINE_NON_FINAL` | true | Kill switch — flip off to force all scoring onto the queue mid-exam. |

Read the invariant block at the top of `config/scoring.php` before changing any
of them. They are a single interlocking set; incoherent values are what produced
the 2026-08-09 incident.

---

## 7. Known limitations

Things that are true today and will bite if assumed otherwise.

- **An assignment attempt created but never opened has no clock** and stays
  `in_progress` forever. `due_at` blocks new starts only; it does not close a
  running or unopened attempt.
- **Single server assumed.** `FILESYSTEM_DISK=local` writes question media to
  local disk, `ClassroomDocumentAccessController` uses `Storage::path()` +
  `response()->file()` (local driver only), and `deploy.sh` builds assets on the
  server. A second app server would see none of the uploads and would produce
  different asset hashes.
- **No CI gate on tests.** `.github/workflows/ci.yml` runs `composer audit` only,
  so the ~7.8k lines of tests run automatically for nobody. Run
  `php artisan test` yourself before deploying.
- **PHPStan baseline is ~727 errors at level 6**, so static analysis currently
  gates nothing. New code is expected to add zero.
- **Scale ceiling ~1.5k–2.5k concurrent** on the current driver config. Moving
  cache + session + queue to Redis raises it to roughly 10k for near-zero code
  change, but needs a root-capable host (iNET Cloud VPS C, ~499k VNĐ/month, same
  provider and datacenters — no legal change).

---

## Appendix A — Shipping the assignment auto-submit change (2026-08-11)

The change that makes assignment attempts finish on time even when the student
closes the browser. Listed explicitly because it is the first release that
**requires a cron change**, not just an upload.

### Files to include in the zip

```text
app/Console/Commands/FinalizeExpiredAssignmentAttempts.php   (new)
app/Services/AssignmentAttemptTimeoutService.php             (new)
app/Services/AssignmentModuleTimingService.php
app/Services/AttemptProgressionService.php
app/Services/ModuleScoringService.php
app/Jobs/ScoreModuleJob.php
app/Http/Controllers/Engine/SessionController.php
app/Http/Controllers/Engine/SubmissionController.php
app/Http/Controllers/Student/AssignmentController.php
app/Http/Controllers/Teacher/AssignmentController.php
bootstrap/app.php
```

**No migrations. No JS or CSS changes** — `public/build` does not need rebuilding
for this release, and `vendor/` is unchanged.

### Steps

1. Upload and extract as in §2.3, then `optimize:clear` and `optimize`.
2. **Add the `schedule:run` cron line** (§1.1) if it is not already there. Without
   it the sweeper never runs and the fix only takes effect when a student or
   teacher opens a page.
3. Verify:

```bash
cd $APP_DIR
$PHP_BIN artisan schedule:list                            # expect 4 entries
$PHP_BIN artisan assignments:finalize-expired --dry-run   # lists the existing backlog
```

### Expect a backlog on the first run

Every attempt abandoned before this deploy has been sitting `in_progress` with an
expired clock. The first real run submits and scores all of them at once, which
means **real score records appear for students who never finished**. That is the
intended correction, but it is visible to teachers, so run it deliberately rather
than letting the cron surprise you:

```bash
$PHP_BIN artisan assignments:finalize-expired --dry-run   # read the list first
$PHP_BIN artisan assignments:finalize-expired             # then close them out
```

Modules submitted this way keep whatever was autosaved and mark everything else
omitted — the same result the student would have got by sitting there and letting
the timer run out.

### Not covered by this change

- `due_at` still blocks new starts only; it does not close a running attempt.
- An attempt created but never opened has no clock and stays `in_progress`.
