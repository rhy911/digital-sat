# Improvement Plan — Digital SAT Platform

**Date:** 2026-07-27
**Scope:** Platform-wide backend + frontend improvements: infrastructure, scoring trust, engine resilience, accessibility, localization, operations, asset delivery, CI, and product depth.
**Method:** Config/dependency inspection (`.env.example`, `composer.json`, `package.json`, `vite.config.js`, `config/app.php`, `.github/workflows/ci.yml`), route audit (`routes/web.php`), engine autosave read (`resources/js/test/navigation.js`), migration/index survey, accessibility grep across `resources/views/engine/`, i18n coverage check (`lang/`), media and ZIP-import read (`BulkQuestionImportService`, `MediaController`), plus directory/size survey. Not a line-by-line code audit — for that see `docs/backend-audit-2026-07-23.md`, which this plan builds on and does not duplicate.

**Relationship to prior docs:**
- `docs/backend-audit-2026-07-23.md` — P0–P3 defect list. Fix those first where they overlap; several items here depend on its P1 #1 (sync-vs-async submit) being resolved.
- `docs/current-problems.md` — product/UX backlog owned by the developer. Not restated here.

Priority key:
- **P0 Critical** — student loses work, or a regression ships undetected. Do now.
- **P1 High** — scaling wall, or trust/support burden that grows with usage.
- **P2 Medium** — real improvement, contained blast radius.
- **P3 Low** — hardening, DX, polish.

---

## Scale assumptions and the triage rule

**Target load: ~1000 simultaneous users in the near future.** Not internet scale. Every item in this document is sized against that number, and anything that only pays off past it has been cut rather than deferred — see "Explicitly not doing" near the end.

**Triage rule — KISS:** an improvable function is fine as long as a user never feels the shortfall. Before building anything here, ask *does a student, teacher, or admin experience this?* If not, it is not on the list.

That splits the work cleanly:

- **Build it** — anything protecting student data, score correctness, or the test-taking experience. These are felt directly and some are unrecoverable when they fail.
- **Skip it** — architectural elegance that buys headroom nobody will use. Containers, websockets replacing working polling, object storage on a single server, extra tooling layers. Every abstraction added is maintenance carried by one developer.

**Where 1000 concurrent genuinely does bite — one honest exception.** The number is small for most purposes but not for the engine hot path. 1000 students polling `/submit-status` every 1.5s is roughly 660 requests/sec on its own, on top of session reads and answer autosaves, and today all three of session, cache, and queue run on MySQL (#3). That is the one place not to economize. Everything downstream of it — Horizon, broadcasting, S3, containerization — is not needed at this ceiling and has been trimmed accordingly.

---

## P0 — Critical

### 1. CI never runs the test suite or the build

**File:** `.github/workflows/ci.yml`

The only CI job is `audit`: `composer audit --locked` + `npm audit --audit-level=high`. It installs both dependency trees and then never runs `php artisan test` (40 test files exist) or `npm run build`. Nothing gates a merge to `main` on tests passing or assets compiling.

**Fix:** Add a `test` job to the same workflow:
- MySQL 8 service container, database `sat_app_testing` (matches `phpunit.xml`).
- `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate --force`.
- `php artisan test`.
- Separate step (or job): `npm ci && npm run build` — catches Vite/Tailwind breakage before deploy.

**Why P0:** cheapest change in this document by a wide margin, and it protects every other item on the list. Do it before anything else.

### 2. Engine autosave failures are silent and the local backup is never restored

**File:** `resources/js/test/navigation.js:311-360`

`autosaveAnswers()` writes a local backup to `localStorage` at `:328` under key `sat_state_{userTestId}_{moduleId}`, then POSTs to `/engine/test/autosave-module`. On any network failure the `catch` at `:357-359` does `console.warn("Autosave failed:", error)` and nothing else:

- No retry.
- No user-visible indicator — the student has no way to know their answers are not reaching the server.
- No backoff or queue; the next scheduled autosave (15s tick, `:278-281`) simply tries again with fresh state, and the `payload === lastAutosavePayload` short-circuit at `:330` is not reached because `lastAutosavePayload` is only updated on success (correct — but it means repeated full retries, not resumption).

The `sat_state_` localStorage key is **write-only**: a grep across `resources/js/` finds the `setItem` at `:328` and no corresponding `getItem`. The backup that exists for exactly this scenario is never read back, so a student who loses connectivity and reloads recovers nothing from it.

**Fix (three parts, in order):**
1. **Restore path** — on module load, read `sat_state_{userTestId}_{moduleId}`; if it holds answers newer than what the server returned, offer to restore (or restore silently and mark dirty so the next autosave pushes them). This makes the existing backup do its job.
2. **Save-status indicator** — a small chip in the engine header: `Saved` / `Saving…` / `Not saved — retrying`. Students must be able to see the difference between "safe" and "not safe".
3. **Retry with backoff** — on failure, retry the failed payload (2s, 5s, 15s), and flush on `online` / `visibilitychange` events. Keep `keepalive: true` on the final flush.

**Constraint:** this is the test-engine hot path. Do not touch timer or scoring logic (`timer.js`, submit flow) while making this change — restrict the diff to the autosave function, its state, and the new indicator element.

**Why P0:** this is the only failure mode on the list that destroys a student's actual work with no recovery path.

---

## P1 — High

### 3. Cache, queue, and session all run on MySQL; Redis is configured but unused

**File:** `.env.example`

```
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
```

Redis connection settings are present and the client is declared, but all three hot subsystems point at MySQL. Every page view is a `sessions` table read+write; every cache get/put is a `cache` table query; `queue:work` polls the `jobs` table on an interval. On the engine hot path these stack on top of the same connection pool that scoring and answer-saving need.

This matters most at the exact moment of peak load: a class of students finishing a module within the same minute, each triggering a module submit, scoring job, and (per the documented design) a `/submit-status` poll every 1.5s.

**Fix:** Move `CACHE_STORE`, `QUEUE_CONNECTION`, and `SESSION_DRIVER` to `redis`. Config-only change; verify `phpredis` (or `predis`) is installed in the target environment first. Update `.env.example` and deployment docs together so local and prod do not drift.

**Sequencing note:** `docs/backend-audit-2026-07-23.md` P1 #1 finds that scoring currently runs via `dispatchSync` inside an open transaction, so the queue is effectively unused for scoring today and the `/submit-status` polling branch is unreachable. Resolve that item first — the value of moving the queue to Redis is proportional to the queue actually being used.

### 4. No observability of any kind

**File:** `composer.json`

No Sentry, Telescope, Pulse, or Horizon. The scoring pipeline is asynchronous by design and fails quietly: a `queue:work` process that dies leaves students sitting on a "scoring" state forever, and nothing alerts anyone. There is no record of production exceptions.

**Fix — two things, both small:**

1. **Sentry** (or equivalent) for production exceptions. Wired correctly it also captures failed queue jobs, which is the specific blind spot that matters here.
2. **An alert on `failed_jobs` being non-empty** — the table already exists. A scheduled check that emails on a non-zero count is enough; this does not need a dashboard.

**Deliberately not doing:** Laravel Horizon. It is the standard answer and it is overkill at this ceiling — a dashboard, its own auth, and another supervised process, to observe a queue running essentially one job type. Sentry plus the `failed_jobs` alert answers the only question actually being asked: *did scoring break, and for whom?*

### 5. Scoring has no golden-fixture tests

**Files:** `app/Services/SatScoringService.php`, `AdaptiveScoreConversionService.php`, `config/sat_scoring.php`

23 services, 40 test files. Scoring is the output students will dispute and the code most likely to be silently wrong after a config tweak — the theta→scaled-score curves live in `config/sat_scoring.php` (`adaptive_conversion.curves`) and are described as v1-calibrated, i.e. expected to change.

**Fix:** Add fixture tests that pin exact outputs for fixed response patterns, one per meaningful path:
- Easy Module 2 path — asserts the section cap (~670 RW / ~660 Math) holds.
- Hard Module 2 path — asserts the full range to 800 is reachable.
- All-correct and all-wrong — asserts the EAP grid stays finite (the documented reason for choosing grid EAP over Newton-Raphson MLE).
- A pattern including `is_pretest` items — asserts they are excluded from theta.
- Section-only attempt, and a merged attempt via `TestProgressionService::autoMergeIfEligible()`.

Each test: fixed responses in, exact theta + scaled score out. Any curve edit that shifts scores then fails loudly instead of silently rescoring the platform.

**Keep it small:** start with the first three. They cover the paths that produce a wrong number a student would actually notice. The rest can follow if they earn their keep — this is a safety net, not a coverage target.

### 6. No rate limit on the engine's write endpoints

**File:** `routes/web.php:172-186`

The `engine` group is protected by `['auth','verified']` but carries no `throttle`. Compare the rest of the app, which throttles consistently: `throttle:5,1` on auth (`:32,35,38,41`), `throttle:10,1`/`15,1` on forum (`:73,74`), `throttle:20,1` on announcements (`:81,140`), `throttle:60,1` at `:184`.

The unthrottled routes include `POST /engine/test/submit-module` (`:186`), the autosave endpoint (`:185`), and `POST /engine/test/start/{test_id}` (`:182`). A client stuck in a retry loop — which becomes more likely once #2 adds retries — or a scripted request can hammer the most expensive path in the application.

**Fix:** Apply a generous throttle sized above real usage so legitimate students never see it: autosave `throttle:120,1`, submit `throttle:30,1`, start `throttle:20,1`. Tune after observing real traffic (needs #4).

### 7. Module submit may not be idempotent

**Files:** `app/Http/Controllers/Engine/SubmissionController.php`, `app/Models/UserTestModuleSubmission.php`

The documented flow is save-then-dispatch. If a student double-taps submit, or a client retries after a timeout, it must not be possible to create two submissions or dispatch `ScoreModuleJob` twice for the same module.

**Status:** not verified in this pass — the `lockForUpdate()` on `UserTest` noted in the 2026-07-23 audit may already serialize this sufficiently. Confirm before building anything.

**Fix if needed:** unique constraint on `(user_test_id, module_id)` in `user_test_module_submissions`, plus `firstOrCreate` and an early return when a submission already exists.

---

## P2 — Medium

### 8. Test engine ships as one large bundle

**Files:** `vite.config.js`, `resources/js/test/` (473 KB of source), `resources/js/test.js` (entry)

Everything under `resources/js/test/` loads through a single `test.js` entry: calculator, reference sheet, KaTeX rendering, break handling, screen guard. A student on a Reading & Writing module downloads and parses the graphing calculator and math rendering code they will never open. KaTeX (`katex@0.16`) is a large dependency on its own.

**Measure before building anything.** 473 KB is *source*, not shipped bytes — minified and gzipped it may already be fine. Run `npm run build` and read the actual gzipped size of the `test.js` chunk first. If it is under ~250 KB gzipped, close this item; a student loads it once per test and the browser caches it.

**Fix, only if the measurement justifies it:** dynamic `import()` for the calculator and reference sheet — the two features a Reading & Writing student never opens — triggered on first open. Everything else stays in the main bundle. Do not chase a general code-splitting scheme.

**Constraint:** do not lazy-load anything the timer or submit path depends on.

### 9. Score explainability endpoint

**Files:** `app/Models/UserTestScoreRevision.php`, `app/Services/ScoreReportService.php`

The data needed to explain a score already exists but is not surfaced. "Why did I get 640?" is a support ticket today and a trust problem at scale.

**Fix:** A read-only per-attempt breakdown showing: final theta and standard error, which items were excluded as pretest, which Module 2 path was routed (easy/hard) and the theta cutoff used, which conversion tier applied (per-test `ScoreConversionSet` vs. `DefaultScoreConversionService` fallback, and whether a checksum mismatch caused the fallback), and the resulting scaled score. All read-only over existing data.

### 10. Teacher item analysis

**Files:** `app/Models/UserTestAnswer.php`, `app/Services/AssignmentReportService.php`

Every response is stored, but there is no per-question aggregate view. A single view gives teachers p-value (proportion correct), distractor distribution, and point-biserial correlation per question.

Two payoffs: teachers immediately see which questions their class failed, and the same aggregates are the input needed to replace the Easy/Med/Hard IRT parameter defaults with real calibration — the stated roadmap direction in `PRODUCT.md` §4.

### 11. Printable / shareable score report

**Dependency:** `barryvdh/laravel-dompdf` is already required in `composer.json` and currently underused.

**Fix:** Generate a student-facing PDF score report with section scores and per-domain breakdown. Low effort given the dependency is present, and it produces the artifact students actually share.

---

## P3 — Low

### 12. Pint is installed but never runs

`laravel/pint` is in `composer.json` `require-dev`, but there is no `composer lint` script and no CI step. Formatting is unenforced across 138 PHP files.

**Fix:** Add `"lint": "pint --test"` and `"format": "pint"` to composer scripts; add `pint --test` to CI (after #1). Run `pint` once as a standalone formatting-only commit so the diff never mixes with feature work.

### 13. Back off the `/submit-status` poll interval

`navigation.js` polls `/submit-status` every 1.5s until the score lands. At 1000 concurrent submitters that is roughly 660 requests/sec of pure polling — the single largest source of avoidable load in the application.

**Fix (small):** back the interval off instead of holding it flat — 1.5s for the first few attempts, then 3s, then 5s. Scoring usually resolves in the first second or two, so the fast early polls preserve the perceived speed while the tail stops hammering. Roughly a five-line change in the existing polling loop.

**Deliberately not doing:** replacing polling with Laravel Reverb websockets. It is the architecturally correct answer and the wrong call here — a new dependency, a new supervised process, and a new failure mode, to save requests that Redis-backed polling with a backoff already absorbs comfortably at this ceiling.

**Sequencing:** depends on backend-audit P1 #1 (async submit restored) — the polling branch is currently unreachable, so there is nothing to tune until that lands.

---

## Accessibility & accommodations

### 14. The test engine is effectively inaccessible — **P1**

**Files:** `resources/views/engine/` (`module/`, `mobile-blocked.blade.php`)

A grep for `aria-` and `role=` across the entire `resources/views/engine/` tree returns **one** match. The engine is the product — a timed, keyboard-heavy, single-page interaction — and it exposes almost no semantics to assistive technology.

Concretely missing:

- No `role="radiogroup"` / `aria-checked` on answer choices, so a screen reader user cannot tell which option is selected or how many exist.
- No `aria-live` region on the timer, the save-status, or the question counter — state changes are silent.
- No `aria-label` on icon-only controls (flag, cross-out, calculator, reference sheet).
- Focus management on question navigation is unverified: after "Next", focus should land on the new question stem, not be lost to `<body>`.
- Modal dialogs (calculator, review page) need `role="dialog"`, `aria-modal`, focus trap, and Escape-to-close.

**Why this ranks high for this product specifically:** the real Digital SAT is administered with accommodations, and a meaningful share of test-takers use screen readers, magnification, or keyboard-only navigation. A practice platform that cannot be operated the way the real test will be operated is not practice for those students — it excludes them. There is also straightforward institutional risk: schools and districts procuring software commonly require a VPAT or WCAG 2.1 AA statement, and the platform cannot produce one today.

**Fix, staged:**

1. Semantics pass on the engine module view — radiogroup, labels, live regions, landmarks. CSS/markup only, no JS logic change.
2. Keyboard pass — full navigation without a mouse, visible focus rings, focus moved deliberately on question change, Escape closes overlays.
3. Contrast audit of the engine palette against WCAG AA (4.5:1 body text).
4. Manual pass with NVDA or VoiceOver on one full module.

**Constraint:** markup and CSS only. Do not modify timer, autosave, or submit logic while doing this.

### 15. No accommodations model — extended time does not exist — **P1**

**Evidence:** grep for `extended_time`, `accommodation`, `time_multiplier`, `extra_time` across `app/` and `database/migrations/` returns **zero** hits. `AssignmentModuleTimingService` exists, but timing appears to be per-assignment, not per-student.

The real Digital SAT grants time accommodations at 1.5× and 2.0×, plus extra or extended breaks. Students who test with those accommodations cannot rehearse under their real conditions here — and a student practising at 1.0× is training for the wrong test.

**Fix:** Add a per-student, per-assignment accommodation record — at minimum a time multiplier (`1.0` / `1.5` / `2.0`) and an extended-break flag. `AssignmentModuleTimingService` is the natural place for the multiplier to apply, so module duration becomes `base × multiplier` at attempt start. Store the multiplier on the attempt itself so a mid-course change to the student's profile never retroactively alters a completed attempt.

**Sequencing:** touches timing on the hot path. Do it after #2 (autosave resilience) lands, and add a fixture test asserting a 1.5× attempt gets exactly 1.5× the seconds.

### 16. Blocking every viewport under 768px may be the wrong product call — **P3 (decision, not a defect)**

**Files:** `resources/js/test/screen-guard.js`, `resources/views/engine/mobile-blocked.blade.php`, `resources/views/components/layouts/test.blade.php`

The guard is well built — the inline load-time check runs before paint, `screen-guard.js` handles mid-session resize with a debounced listener, and it is explicitly visual-only (`does not touch timer/attempt state`). No complaint about the implementation.

The question is the threshold. `SCREEN_SIZE_THRESHOLD ?? 768` applied to `Math.min(innerWidth, innerHeight)` blocks phones — correct — but also blocks any tablet in portrait whose narrow dimension falls under 768 CSS px. The real Bluebook app ships on iPad and is used on it heavily in schools. Blocking tablets outright may be cutting off a legitimate and growing slice of the audience.

**Options:** (a) keep as-is, accept the loss; (b) lower the threshold to ~700 and QA one portrait-tablet layout; (c) allow tablets above a size floor and block only phones. Product call, not an engineering one — flagged for a decision, not queued for work.

---

## Localization & reach

### 17. Internationalization is 5% done — **P2**

**Evidence:** `lang/en/` and `lang/vi/` each contain exactly one file, `classroom.php`. 111 Blade views exist; 72 `__()` / `@lang` calls total. Everything outside the classroom module is hardcoded English.

The project's own backlog (`docs/current-problems.md`) is written in Vietnamese, which strongly implies a Vietnamese-speaking user base. Those students are currently practising on an English-only interface — defensible for the *test content*, which must stay in English, but not for navigation, instructions, error messages, score reports, or teacher tooling.

**Fix:** Extract strings module by module in priority order — auth → student dashboard → score report → teacher tooling. Keep the test engine's *question content* untranslated by design; translate only its chrome (buttons, timer labels, modals, instructions). Add a locale switcher and persist the choice on `users`. `APP_LOCALE` already reads from env (`config/app.php:81`) and the `en`/`vi` structure is in place — this is extraction work, not architecture work.

### 18. Public content ships with no SEO metadata — **P2**

**Evidence:** `BlogPost`, `ForumThread`, and `ForumReply` models exist with public routes. A grep for `og:`, `meta name="description"`, and `canonical` across `resources/views/components/layouts/` returns nothing. `public/robots.txt` exists; there is no `sitemap.xml`.

The blog and forum are the platform's only organic acquisition surface, and search engines and social cards currently see an untitled, undescribed page.

**Fix:**

1. Per-page `<title>`, `meta description`, and `canonical` in the public layout, overridable per view via a Blade section.
2. Open Graph + Twitter card tags so shared links render a preview.
3. A generated `sitemap.xml` covering blog posts and forum threads.
4. `Article` / `QAPage` JSON-LD on blog and forum pages.

Low effort, compounding return, and entirely isolated from the engine.

---

## Data & operations

### 19. No backup strategy — **P1**

**Evidence:** no `spatie/laravel-backup` (or any backup package) in `composer.json`; no documented dump procedure in `docs/`.

The irreplaceable asset is hand-authored test content — `Test > Section > Module > Question`, answer choices, explanations, calibrated `ScoreConversionSet` tables, plus every student attempt and score. Content is deliberately immutable once locked (`TestContentLockService`), which protects it from *edits* but not from a dropped database, a bad migration, or disk loss.

**Fix:** Scheduled database dumps plus the `storage/app/public/media` tree to off-server storage, with a documented restore procedure and at least one rehearsed restore. A backup that has never been restored is not a backup.

### 20. Media lives on the local `public` disk — **P3, conditional**

**Files:** `app/Services/BulkQuestionImportService.php:267-278`, `app/Http/Controllers/Admin/MediaController.php:25-53`

Imported question images are written to `Storage::disk('public')` under `media/`, named `Str::random(20) . '.' . $ext`.

Two notes, one positive:

- **Not a leak.** The 20-character random filename is not enumerable, so publicly-served media does not expose the question bank to scraping. This is fine as-is.
- **Does not survive scaling.** Local disk means no CDN, no redundancy (see #19), and immediate breakage the moment the app runs on more than one server or on ephemeral container storage, since instance B cannot see instance A's uploads.

**Do nothing for now.** On a single server at this ceiling, local disk serving images is completely adequate — S3 would add a dependency, credentials, and a failure mode to solve a problem that does not exist yet.

**Trigger to revisit:** the moment a second app server is added, or the host becomes ephemeral (container/PaaS). Then move the `public` disk to S3-compatible storage; both call sites already go through the `Storage` facade, so it is a config change, not a rewrite.

**One thing worth checking now, independent of storage:** the extension is taken straight from the source filename via `pathinfo`. Confirm the upload validation constrains it to an image allowlist — that is a correctness question about what gets written to disk, and it applies to local storage too.

### 21. Production setup is undocumented — **P2**

**Evidence:** no `Dockerfile`, `docker-compose.yml`, or `Procfile`. Local development runs on Laravel Herd via `composer dev`.

Nothing in the repository describes how production runs — PHP version and extensions, the `queue:work` supervisor (which scoring depends on entirely), the scheduler, or cache/queue drivers. That knowledge lives in one person's head, and environment drift between Herd and production is exactly how "works locally" bugs reach students mid-test.

**Fix — a `docs/deployment.md`, one page, no containers.** Record: PHP version and required extensions, the supervisor config for `queue:work` (with `--tries` and `--timeout`), the scheduler cron entry, driver settings, and the deploy sequence (`migrate --force`, `config:cache`, `route:cache`, `npm run build`, queue restart). The queue supervisor is the critical entry — if it is not running, students never receive scores (see #4).

**Deliberately not doing:** containerizing the app. Docker solves reproducibility across a team and across many environments; there is one developer and one server here. A written page delivers the same protection against drift at a fraction of the cost, and does not add an image to build and maintain on every deploy.

### 22. Two-factor authentication appears half-built — **P2**

**Evidence:** `app/Models/User.php:30-31,49` declares `two_factor_code` and `two_factor_expired_at` in `$fillable` and `$hidden`. Whether a complete challenge/verify flow exists was not traced in this pass.

Teacher and admin accounts can read every student's PII and scores. Those accounts warrant a second factor even if students do not.

**Fix:** First determine whether the flow is complete, partially implemented, or dead columns. Then either finish it (email-code 2FA is sufficient given Resend is already wired) and require it for `admin` and `teacher` roles, or drop the columns in a migration so the schema stops implying a protection that does not exist. Do not leave it ambiguous — a half-present security feature is worse than a documented absent one.

### 23. No student data export or deletion path — **P3**

Student accounts accumulate PII, attempt history, and scores. There is no self-serve export and no account-deletion flow, and soft deletes on content do not extend to user data.

**Do nothing until asked.** At this scale a deletion request can be handled manually by an admin — it does not need a self-serve flow, and building one before anyone has requested it is speculative work.

**Trigger to revisit:** the first school-district contract or EU user, where an export/deletion path stops being a nicety and becomes contractual. When it comes: export as JSON or PDF of scores and attempts, and *anonymize* attempts rather than hard-deleting them so aggregate item statistics (#10) survive.

---

## Product depth

### 24. Item analysis feeds adaptive practice — **P2, extends #10**

Once per-question p-values and distractor distributions exist (#10), the same aggregates unlock the feature students actually want: **drill mode**. Weakest domains identified from a student's attempt history, then a generated practice set drawn from the question bank targeting those domains at appropriate difficulty.

This is the natural payoff of infrastructure the platform already has — every `UserTestAnswer` is stored, questions carry difficulty tags, and `DifficultyDistributionAdvisor` already reasons about difficulty spread. It reuses that rather than adding a new subsystem.

**Prerequisite:** question-level domain/skill tagging must be reliable across the bank. Verify tagging coverage before designing the feature.

### 25. Question bank search and reuse — **P2**

`TestContentCopyService` and `TestShare` exist, so reuse is a recognised need. What appears to be missing is discovery: filtering the accumulated question bank by domain, skill, difficulty, and usage count when assembling a new test.

**Fix:** A searchable/filterable question-bank view in the test builder, with "insert into module" from search results. Directly addresses the "content creation wizard is too basic / not customizable enough" complaint at the top of `docs/current-problems.md`.

### 26. Proctoring signals for assigned tests — **P3**

`screen-guard.js` handles viewport only and deliberately does not touch attempt state. For teacher-assigned (as opposed to self-practice) attempts, teachers commonly want visibility into tab-switching, window blur, paste events, and unusual timing patterns.

**Not scheduled — build only if a teacher actually asks.** This is a whole feature (event capture, storage, a teacher-facing review screen, a disclosure flow), and nothing in the current backlog says anyone wants it. Speculative anti-cheat is exactly the kind of work that grows into permanent maintenance for a hypothetical need.

**If it is ever requested:** behind an explicit per-assignment "proctored" flag, record blur/focus/paste events against the attempt and surface a summary in the teacher's attempt monitor. Record and report only — never auto-invalidate an attempt, and never act on a signal without a human reviewing it. Disclose it clearly to students in the UI before the attempt starts; silent monitoring is not acceptable here.

### 27. Score history and progress trends — **P3**

`UserTestScoreRevision` tracks rescoring, and student progress views exist. Worth confirming that a student can see a simple longitudinal view: score over time per section, per domain accuracy trend, and pacing (time per question vs. the module average).

This is the single most motivating screen in a test-prep product — it is the reason a student comes back next week — and most of the underlying data is already stored.

---

## Verified good — do not "fix"

Checked during this pass and found sound. Recorded so a future audit does not spend time re-deriving them or "hardening" something already hardened:

- **ZIP import security** (`BulkQuestionImportService.php:101-127`) — `assertZipIsSafeToExtract()` already guards path traversal, file count, and uncompressed-size (zip-bomb) limits before extraction, with a `mimes:zip|max:20480` validation rule at the boundary. No action needed.
- **Media filenames** — `Str::random(20)` is not enumerable; public serving does not expose the question bank.
- **Authorization primitives** — 8 policies present (`Assignment`, `Classroom`, `ClassroomDocument`, `Module`, `Question`, `Section`, `Test`, `UserTest`).
- **Throttling outside the engine** — consistently applied across auth, forum, classroom, and announcement routes. The gap is engine-specific (#6).
- **Database indexing on attempt tables** — composite index `idx_user_tests_composite` on `(user_id, test_id, assignment_id, status, updated_at)`, and unique constraints on `(user_test_id, module_id, question_id)` and `(user_test_id, module_id)`. The hot-path lookups are covered.
- **Screen-size guard** — correct construction: pre-paint inline check, debounced resize listener, visual-only with no attempt-state side effects. Only the threshold is open to debate (#16).

---

## Explicitly not doing

Considered and rejected at this scale. Listed so the decision does not get quietly re-litigated by a future audit — each of these is the *conventionally correct* answer, and each is the wrong call for ~1000 users and one developer.

| Not doing | The standard argument for it | Why not here |
| --------- | ---------------------------- | ------------ |
| **Laravel Horizon** | Queue dashboards, throughput, retries | A dashboard, its own auth, another supervised process — to watch one job type. Sentry plus a `failed_jobs` alert answers the real question. (#4) |
| **Reverb / websockets** | Removes polling, instant score delivery | New dependency, new process, new failure mode. Redis-backed polling with a backoff absorbs this load fine. (#13) |
| **Docker / containers** | Reproducible environments | Solves drift across a team; there is one developer and one server. A written `deployment.md` gets the same protection. (#21) |
| **S3 / CDN for media** | Scales, redundant, offloads the app server | Local disk is adequate on a single box. Revisit at the second server, not before. (#20) |
| **ESLint / TypeScript** | Static checking on the engine's vanilla JS | Real value, but a tooling layer to configure and maintain. The engine's actual risk is covered better by #1 (CI) and #5 (scoring fixtures). |
| **Self-serve data export/deletion** | Compliance | An admin can handle a request manually today. Build it when a contract requires it. (#23) |
| **Proctoring signals** | Teachers worry about cheating | Nobody has asked. A whole feature for a hypothetical need. (#26) |
| **General code-splitting scheme** | Smaller bundles | Measure the gzipped bundle first; it may already be fine. At most, lazy-load the calculator. (#8) |

The pattern: **keep everything a user can feel, skip everything that only an architect can feel.**

---

## Recommended order

Three tracks. The **Foundation** track is sequential and blocks the others in places; **Reach** and **Product** are independent and can run in parallel or be picked up by whoever is free.

### Track A — Foundation (sequential)

| Step | Item | Rationale |
| ---- | ---- | --------- |
| A1 | #1 CI runs tests + build | Protects everything after it. Hours, not days. |
| A2 | #2 Engine autosave resilience | Only item that destroys real student work. |
| A3 | #19 Backups | Second data-loss item. Cheap; nothing else recovers from a lost DB. |
| A4 | backend-audit P1 #1 (sync/async submit) | Unblocks #3, #4, #13. |
| A5 | #3 Redis drivers | Config-only; removes the scaling wall. |
| A6 | #4 Sentry + failed-job alerts | Stop flying blind before adding load. |
| A7 | #5 Scoring fixture tests | Freeze correct behaviour before any curve recalibration. |
| A8 | #6 Engine throttles | Small, and #2's retries make it more relevant. |
| A9 | #21 Deployment doc | Captures the `queue:work` supervisor that A4–A6 depend on. One page. |
| A10 | #13 Poll-interval backoff | Five-line change; removes the largest avoidable load source. After A4. |

### Track B — Reach and access (parallel; independent of Track A)

| Step | Item | Rationale |
| ---- | ---- | --------- |
| B1 | #14 Engine accessibility | Largest excluded-user gap. Markup/CSS only — cannot break scoring. |
| B2 | #18 SEO on public content | Isolated, compounding, low effort. |
| B3 | #15 Accommodations / extended time | Touches timing — land after A2, pair with a fixture test. |
| B4 | #17 Localization rollout | Extraction work, module by module. |

### Track C — Product depth (after A7; #10 gates the rest)

| Step | Item | Rationale |
| ---- | ---- | --------- |
| C1 | #10 Teacher item analysis | Gates #24 and real IRT calibration. |
| C2 | #9 Score explainability | Cuts support load; read-only over existing data. |
| C3 | #25 Question bank search | Directly answers the top complaint in `current-problems.md`. |
| C4 | #11 PDF score report | Dependency already installed. |
| C5 | #24 Adaptive drill mode | Needs C1 plus verified domain tagging. |
| C6 | #27 Progress trends | Retention screen; most data already stored. |

**Small and unscheduled — do them whenever there is a spare hour:** #12 (Pint script + CI step), #8 (measure the gzipped bundle; act only if it is genuinely large), #22 (2FA — at minimum resolve whether the columns are live or dead; shipping the flow can wait).

**Trigger-based, not scheduled** — each has a specific event that starts the clock, listed in its own section: #20 (S3, at the second server), #23 (data export, at the first district or EU user), #26 (proctoring, if a teacher asks), #16 (tablet threshold — a product decision, not queued work).

### Not verified in this pass

Confirm before implementing:

- #7 — submit idempotency may already be covered by the existing `lockForUpdate()`.
- #22 — whether the 2FA flow is complete, partial, or dead columns.
- #24 — question-level domain/skill tagging coverage across the bank.
- #20 — whether upload validation constrains the image extension allowlist.
