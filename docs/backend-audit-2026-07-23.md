# Backend Audit — Digital SAT Platform

**Date:** 2026-07-23
**Scope:** `app/` backend — hot-path test engine, scoring pipeline, services, controllers, auth, routes.
**Method:** Manual read of scoring/engine hot path + security greps (`getMessage`, `whereRaw`, `env()`, `authorize`, ZIP handling). Not exhaustive across all 138 PHP files; admin CRUD and reporting only sampled.

Priority key:
- **P0 Critical** — data loss, security breach, or student cannot get a score. Fix now.
- **P1 High** — latency/lock risk under load, info leak, or fragile invariant on the hot path.
- **P2 Medium** — real bug with narrow blast radius, or notable tech debt.
- **P3 Low** — hardening, doc drift, dead code, cosmetic.

---

## P1 — High

### 1. Scoring runs synchronously inside an open DB transaction holding a row lock
**File:** `app/Http/Controllers/Engine/SubmissionController.php:38-122` (esp. `:94`)

`submit()` opens `DB::transaction`, takes `lockForUpdate()` on the `UserTest` row (`:39-42`), then calls `ScoreModuleJob::dispatchSync(...)` (`:94`). `dispatchSync` runs the **entire** `TestProgressionService::submit` — which eager-loads `Test::with('sections.modules.questions')` (`TestProgressionService.php:23`) and runs IRT EAP estimation — **inside the request thread and inside the still-open transaction**.

Consequences:
- The `UserTest` row lock is held for the full duration of test-load + IRT scoring on every module submit. Concurrent submits by the same student serialize (intended), but the lock + transaction also pin a DB connection for the whole compute. Under load this drains the connection pool and lengthens tail latency.
- This directly contradicts the documented architecture (`CLAUDE.md`: "save-then-dispatch… returns `status: scoring` immediately (avoids 504s)… frontend polls a `/submit-status` endpoint"). Current code returns the full result synchronously.
- The async polling infrastructure is now **dead/unreachable**: `ScoreModuleJob` writes `scoring_result_{id}` to cache, then `SubmissionController:100` immediately `Cache::forget`s it, so `checkStatus()` (`:147`) and `navigation.js:423-432` polling can never observe a `scoring` state. `QUEUE_CONNECTION=database` + `php artisan queue:work` are effectively unused for scoring.

**Fix:** Decide one model and make it consistent. Either (a) restore true async: save answers in the txn, `dispatch()` (not Sync) the job outside the transaction, return `status: scoring`, keep the cache result until the poller reads it (drop the `forget`); or (b) commit to synchronous: move the `Test`-load + scoring **outside** the transaction (compute first, then open a short txn only to persist), and delete the dead `checkStatus` route + polling branch. Do not leave the lock wrapping the compute.

### 2. Raw exception messages returned to clients (info leak), plus `APP_DEBUG=true`
**Files:**
- `app/Http/Controllers/Auth/RegisterController.php:56,66,73` — generic `catch (\Exception)` returns `$e->getMessage()` in JSON and flashes it to the page. A DB/driver error (e.g. constraint, connection) leaks internals to an unauthenticated caller.
- `app/Http/Controllers/Auth/VerifyEmailController.php:52` — `'error' => $e->getMessage()`.
- `app/Http/Controllers/Auth/LoginController.php:98` — `$message = $e->getMessage()` returned.
- `app/Http/Controllers/Admin/ModuleController.php:126` — generic 500 returns `$e->getMessage()`.
- `.env`: `APP_DEBUG=true` (local — verify prod sets `false`).

This violates the repo's own documented security invariant (`CLAUDE.md`: "Never return `$exception->getMessage()` in an HTTP response; log it server-side and return a generic message"). `TestController::destroy:97-100` already does it right (logs, returns generic) — mirror that.

**Fix:** In every generic `catch (\Throwable)`, log server-side with safe scalars and return a fixed generic message. Reserve message pass-through for `ValidationException`/`AuthorizationException`/`ConflictHttpException` (app-authored strings), which is already the safe pattern in `AnswerController:65-75` and `SubmissionController:126-135`. Confirm `APP_DEBUG=false` in production.

---

## P2 — Medium

### 3. Section auto-merge drops per-question timing (and timestamps)
**File:** `app/Services/TestProgressionService.php:168-185`

When merging two section-only attempts, copied `UserTestAnswer` rows set only `selected_answer`, `is_correct`, `question_snapshot` — **`time_spent` is omitted** (defaults to 0/null), and `created_at/updated_at` are not carried. Any per-question time analytics on a merged `full` attempt understate time for the second section.

**Fix:** Include `time_spent` (and `created_at`/`updated_at` if timing history matters) in the `UserTestAnswer::create([...])` payload.

### 4. Adaptive finalize can throw uncaught → student sees error at final submit
**File:** `app/Services/TestProgressionService.php:271-307, 360-374, 403-411`

`finalizeNormal` wraps conversion in `try/catch (\RuntimeException)` (`:323-351`) so a malformed form still completes with accuracy data. `finalizeAdaptive` has **no such guard**: `scoreAdaptiveSection` (`:360`) and `completeResponsesForModule` (`:403`) throw `RuntimeException` if any presented question lacks a response row or a routed Module 2 is missing. That exception propagates out of `finalize` → `submit` → the job's `catch` (`ScoreModuleJob.php:49`) caches a generic `error`. Net effect: on an adaptive test with any missing response, the student's answers are saved but they get "Server error during submission" at the final step with no score and no recovery path.

**Fix:** Give `finalizeAdaptive` the same defensive posture as `finalizeNormal` (complete the attempt with theta/accuracy but null scaled scores rather than hard-failing), or guarantee upstream that every presented adaptive question always has a materialized answer row before finalize.

### 5. Stale `UserTest` instance in the advance step
**Files:** `app/Http/Controllers/Engine/SubmissionController.php:104` + `app/Jobs/ScoreModuleJob.php:45-47`

The job re-fetches the `UserTest` via `findOrFail` (`ScoreModuleJob:45`) — a **different instance** from the controller's `lockForUpdate` model (`SubmissionController:39`). Inside the job, `TestProgressionService::submit` mutates and saves that job-local instance (e.g. `routeAdaptive` sets `rw_m2_path`, `finalize` sets `status`). The controller then calls `$this->progression->advance($userTest, $result)` on its **own stale instance** (`:104`), which `forceFill`s `current_module_id` and saves.

Today this is mostly safe (same DB connection holds the lock; Eloquent `save()` writes only dirty attributes, so different columns don't clobber). But it is a latent footgun: any future code that reads a scalar off the controller's `$userTest` after scoring will read a pre-scoring value.

**Fix:** After `dispatchSync`, `$userTest->refresh()` before `advance`, or thread the single locked instance through the job instead of re-fetching by id.

### 6. Full test graph eager-loaded on every module submit
**File:** `app/Services/TestProgressionService.php:23`

`Test::with('sections.modules.questions')->findOrFail(...)` loads the entire test (all sections, all modules, all questions) on **every** module submission, inside the locked transaction (see #1). For a full-length SAT that is ~4 modules × ~27 questions plus the non-routed Module 2 branches. Wasteful on the hot path.

**Fix:** Load only what routing/finalize needs (the submitted section's modules/questions), or move the load outside the lock.

---

## P3 — Low / Hardening

### 7. Admin/Teacher FormRequests `authorize()` return `true`
**Files:** `StoreSectionRequest.php:11`, `StoreModuleRequest.php:11`, `UpdateModuleRequest.php:11`, `UpdateSectionRequest.php:11`, `UpdateTestRequest.php:11`

Not a live IDOR — the controllers call `$this->authorize('update', $model)` against policies (`ModuleController:90`, `TestController:46`). But it violates the documented invariant ("`FormRequest::authorize()` must do real authorization") and is fragile: a new controller action that forgets the manual `authorize()` call ships an unguarded write. Consider moving the policy check into `authorize()` (resolve the route model) so it can't be forgotten.

### 8. Practice timer is client-authoritative and resets on reload
**Files:** `app/Http/Controllers/Engine/SessionController.php:94-108`, `app/Http/Controllers/Engine/AnswerController.php:40-42`

For non-assignment (practice) attempts, `current_module_started_at` is reset to `now()` on every `show()` (`:101`), and elapsed time is taken from client-supplied `elapsed_seconds` (`AnswerController:41`). A student can trivially extend/bypass the practice timer. Low stakes (practice only; assignment path is server-authoritative via `AssignmentModuleTimingService`), but worth a note.

### 9. Assignment timing has zero grace vs practice's 5-minute grace
**Files:** `app/Services/AssignmentModuleTimingService.php:36-43` vs `app/Http/Controllers/Engine/SubmissionController.php:75`

Practice submit allows `duration + 5` minutes (`SubmissionController:75`); assignment `expired` triggers exactly at `remaining == 0`. Data isn't lost (the timed-out branch preserves autosaved answers and marks the rest omitted, `SubmissionController:90`), but a genuine network-delayed final submit at the boundary is rejected. Confirm this strictness is intended.

### 10. `checkAnswer` uses `hash_equals` for free-text SPR comparison
**File:** `app/Http/Controllers/Engine/Concerns/HandlesAnswers.php:44`

`hash_equals($acceptedText, $submitted)` is a constant-time comparator for secrets, misused here for answer text. It works, but the semantics are wrong and non-numeric SPR answers are compared case- and whitespace-exact only. Use a plain normalized string comparison (and decide casing/whitespace policy explicitly).

### 11. `isApprovedTeacher` treats `null` status as approved
**File:** `app/Models/User.php:99`

`in_array($this->teacher_approval_status, [null, 'approved'], true)` auto-approves any teacher whose status is `null`. New teachers get `pending` at registration (`RegisterController:32`), so this only affects legacy/seed/manually-created rows — but it's a silent privilege grant. Prefer explicit `'approved'` only, and backfill legacy rows.

### 12. `ScoreModuleJob` lacks retry/timeout config and blanket-swallows exceptions
**File:** `app/Jobs/ScoreModuleJob.php:49-57`

No `$tries`, `$backoff`, `$timeout`, or `failed()` hook, and `catch (\Throwable)` writes a generic error to a 300s cache entry. Harmless while `dispatchSync` is used, but if #1 is fixed toward true async this job has no retry/dead-letter behavior and would silently strand a student on the first transient failure. Log `user_test_id`/`module_id` scalars (currently logs the whole exception object at `:50`).

### 13. Documentation drift: scoring is EAP, not MLE/Newton-Raphson
**Files:** `app/Services/SatScoringService.php:57-111` vs `CLAUDE.md` / `PRODUCT.md`

Code implements a standard-normal-prior **EAP over a fixed theta grid** (`method => 'eap_3pl_v1'`). The docs describe "MLE via Newton-Raphson." Reconcile the docs to prevent future contributors from "fixing" the estimator to match stale prose.

### 14. Dead code
- `app/Services/FormScoringAuditService.php:130-136` — `validParameters()` is defined but never called.
- `app/Http/Controllers/Engine/SubmissionController.php:147-161` (`checkStatus`) + route `engine.submit-status` + `navigation.js:423-432` polling — unreachable given synchronous submit (see #1). Remove or re-enable as a set.

### 15. Local-only passwordless dev login
**File:** `routes/web.php:229-232`

`/dev/login-student3` logs in a hardcoded user with no password. Correctly gated behind `app()->environment('local')`. Just ensure prod `APP_ENV` is never `local` and this block never ships enabled.

---

## Things checked and found OK
- **SQL injection:** all `whereRaw`/`orderByRaw`/`selectRaw` use parameter bindings or constants (`SessionController:172`, `ResolvesRouting:19`, `QuestionController:66`, `AssignmentReportService:51`, `AnalyticsController:54`). Clean.
- **ZIP import (zip-slip / zip-bomb):** `BulkQuestionImportService::assertZipIsSafeToExtract:112-123` rejects `..`, leading `/`/`\`, caps file count (1000) and uncompressed size (200MB) before `extractTo`. Reasonable.
- **File serving:** `ClassroomDocumentAccessController` authorizes via policy, reads path from DB (not user input), sanitizes the `Content-Disposition` filename against header injection. `MediaController::show` regex-restricts the filename. No traversal.
- **IDOR on ownership + state:** engine queries scope by `user_id`/`status` in the query itself with `lockForUpdate` (`SubmissionController:39-42`, `HandlesAnswers:92-99`, `AttemptController`) per the documented pattern.
- **Mass assignment:** `User::$fillable` excludes `role`; role is set explicitly server-side (`RegisterController:34`) and constrained to `student|teacher` (admin not self-assignable).
- **Answer leakage:** `is_correct` is `makeHidden` on answer choices before rendering the engine view (`SessionController:69,236`).
- **Duplicate/replay submit:** the `UserTestModuleSubmission` receipt check (`SubmissionController:48-63`) makes re-submits idempotent for valid transitions and 409s stale ones.

---

## Suggested fix order
1. **#1** (sync-in-transaction + dead async path) — biggest hot-path risk; decide sync vs async and make it coherent.
2. **#2** (exception-message leaks + confirm `APP_DEBUG=false`) — quick, security-relevant, batchable.
3. **#4** (adaptive finalize hard-fail) — can strand a student's final score.
4. **#3** (merge drops `time_spent`) — silent data quality bug.
5. **#5, #6** — hot-path robustness/perf, ride along with #1.
6. Remainder (#7–#15) — hardening/cleanup, opportunistic.
