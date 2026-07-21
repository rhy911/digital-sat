# Code Audit — Digital SAT App
Date: 2026-07-22
Scope: full codebase (backend PHP, frontend JS/Blade, config/DB/deps)

## Executive Summary

Codebase overall in solid shape. Laravel 12 / PHP 8.2, dependencies current, no dependency rot. Server-side security discipline is good: policies exist for most models, transactions wrap multi-step writes with `lockForUpdate()` where needed, SQL is parameterized throughout, no hardcoded secrets, `.env` correctly gitignored, migrations consistently indexed with `down()` methods, feature-test coverage on scoring/auth/access-control is real.

Main gap: **client-side rendering duplicates server-side sanitization and doesn't sanitize** — the JS live-preview path (bulk import + question builder) renders teacher/admin-uploaded content via `marked.parse()` straight into `innerHTML` with no allowlist, while the server (`QuestionContentRenderer`) does this correctly. That's the one finding worth treating as urgent. Everything else below is maintainability/performance debt or defense-in-depth gaps, not active exploits.

---

## Prioritized Findings

### 1. Stored XSS via client-side question preview (bulk import + builder)
- **Severity:** High
- **Category:** Security
- **Location:** `resources/js/test/dashboard/utils/text.js:1-23` (`compileMarkdownToHtml`), `components/builder.js:181-241`, `ui/editors.js:82-137`, `components/bulk-import.js:419-531`
- **Impact:** `marked.parse()` called with no `sanitize`/allowlist, result assigned straight to `.innerHTML`. Server sanitizer (`app/Support/QuestionContentRenderer.php`) exists and is correct, but preview endpoints don't run through it — only validate required fields. A crafted stem/passage/explanation in an uploaded CSV/ZIP/JSON question bank (`<img src=x onerror=...>`, `<script>`) executes in the teacher/admin's authenticated session the moment they click "Preview" — cookie/session theft, CSRF-token exfil, acting as that user.
- **Remediation:** Either (a) run preview content through server-side `QuestionContentRenderer` before returning it to the client (preferred — one sanitizer, one place), or (b) sanitize client-side with DOMPurify before any `innerHTML` assignment in `builder.js`, `editors.js`, `bulk-import.js`. Do not ship a preview path that trusts `marked.parse()` output directly.
```js
// utils/text.js — before
export function compileMarkdownToHtml(text) {
  return window.marked.parse(text, { breaks: true });
}

// after
export function compileMarkdownToHtml(text) {
  const raw = window.marked.parse(text, { breaks: true });
  return window.DOMPurify.sanitize(raw, { FORBID_TAGS: ['script','iframe'], FORBID_ATTR: ['onerror','onload'] });
}
```

### 2. `ScoreModuleJob` swallows all failures — never lands in `failed_jobs`
- **Severity:** High
- **Category:** Reliability / Architecture
- **Location:** `app/Jobs/ScoreModuleJob.php:39-58`
- **Impact:** `handle()` wraps everything in try/catch, logs on `\Throwable`, writes an error payload to a cache key, but never rethrows. Queue always sees the job as successful. No `$tries`/`backoff`/`failed()` defined. A scoring failure is invisible to any failed-job monitoring/alerting — a student's module silently never gets scored and nobody is paged.
- **Remediation:** Rethrow after logging (or catch only expected/recoverable exceptions), add `public $tries = 3;`, `public function backoff() { return [10, 30, 60]; }`, and a `failed(\Throwable $e)` method that surfaces the failure (log critical + notify).
```php
public function handle(): void
{
    try {
        // ... existing scoring logic ...
    } catch (\Throwable $e) {
        Log::error('EXCEPTION in ScoreModuleJob', ['exception' => $e]);
        throw $e; // let the queue retry/mark failed instead of swallowing
    }
}

public function failed(\Throwable $e): void
{
    Log::critical('ScoreModuleJob permanently failed', ['exception' => $e]);
}
```

### 3. Sensitive `User` fields mass-assignable
- **Severity:** Medium (latent — no active exploit path found)
- **Category:** Security
- **Location:** `app/Models/User.php:24-39` — `$fillable` includes `teacher_approval_status`, `teacher_reviewed_by`, `teacher_reviewed_at`, `is_2FA_enabled`, `two_factor_code`, `is_active`
- **Impact:** No current call site does `User::create($request->all())` or `$user->update($request->all())` — every call site hand-picks fields (`ProfileService`, `RegisterController`, `TeacherApplicationController`). But the model itself has no guard rail: one future endpoint that mass-assigns from request input would let a student self-approve as teacher or flip `is_active`.
- **Remediation:** Move privileged fields out of `$fillable` into `$guarded`-equivalent explicit assignment, or split into a separate admin-only update path.
```php
protected $fillable = [
    'name', 'email', 'password', // ...normal profile fields only
];
// privileged transitions go through dedicated methods, e.g.:
public function approveAsTeacher(User $reviewer): void
{
    $this->forceFill([
        'teacher_approval_status' => 'approved',
        'teacher_reviewed_by' => $reviewer->id,
        'teacher_reviewed_at' => now(),
    ])->save();
}
```

### 4. `QuestionController::attach` — visibility vs ownership authorization split, undocumented
- **Severity:** Medium
- **Category:** Security / Architecture
- **Location:** `app/Http/Controllers/Admin/QuestionController.php:212-248`
- **Impact:** Attaching a question to a module only checks the question is *visible* (`visibleTo()`), not that the caller owns it, while `update`/`delete` are ownership-gated. A teacher can attach another teacher's public question into their own module. May be intentional (shared question bank) but nothing states this rule, and `TestStructureController::cloneModule` (line 133-144) applies the same visibility-only rule for cloning — same unstated convention repeated in two places, one accidental drift away from breaking auth.
- **Remediation:** Document the rule explicitly (e.g. a comment or a shared policy method `QuestionPolicy::attach()`) so it's not "whatever the controller currently happens to do."

### 5. `QuestionController::update` authorization lives only in the FormRequest
- **Severity:** Medium
- **Category:** Maintainability / Security
- **Location:** `app/Http/Controllers/Admin/QuestionController.php:137-139`, `app/Http/Requests/Admin/UpdateQuestionRequest.php:9-13`
- **Impact:** Every sibling controller (`SectionController`, `ModuleController`, `TestController`) calls `$this->authorize()` inline in the method body. `QuestionController::update` relies entirely on `UpdateQuestionRequest::authorize()` — correct today, but invisible when reading the controller, and `destroy()` in the same class takes a plain `$id` with manual scoping instead of the FormRequest. A future refactor swapping the type-hint silently removes authorization.
- **Remediation:** Add an explicit `$this->authorize('update', $question);` in the controller body even though the FormRequest already enforces it — redundant-but-visible beats invisible-but-correct.

### 6. No `config/cors.php` — framework wildcard-origin default in effect
- **Severity:** Medium
- **Category:** Security / Config
- **Location:** (absent) `config/cors.php`; dead code at `app/Http/Middleware/CorsMiddleware.php` (no-op, not registered in `bootstrap/app.php`)
- **Impact:** `api/*` and `sanctum/csrf-cookie` run under Laravel's built-in default: `allowed_origins => ['*']`, `supports_credentials => false`. The `false` on credentials limits practical damage (browsers won't send cookies cross-origin to it), but it means CORS is not actually configured — it's whatever the framework ships with, and anyone reading `CorsMiddleware.php` would wrongly assume CORS is handled there.
- **Remediation:** Either publish and configure `config/cors.php` explicitly with your real allowed origins, or delete the dead `CorsMiddleware` to stop it misleading future readers.

### 7. `SESSION_SECURE_COOKIE` unset
- **Severity:** Medium
- **Category:** Security / Config
- **Location:** `config/session.php:172`, `.env.example` (key absent)
- **Impact:** Defaults to `null` → Laravel does not force the `Secure` flag on the session cookie. If production `.env` doesn't set this explicitly, the session cookie can be sent over plain HTTP if ever reached that way (downgrade, misconfigured proxy).
- **Remediation:** Set `SESSION_SECURE_COOKIE=true` in production `.env` and add the key (commented, defaulting to blank) to `.env.example` so it's not forgotten on new environment setup.

### 8. CDN-loaded third-party scripts with no Subresource Integrity
- **Severity:** Medium
- **Category:** Security / Supply chain
- **Location:** `resources/js/test/dashboard/utils/script-loader.js:4-50` (TomSelect, Tabulator, marked, KaTeX, EasyMDE from `cdn.jsdelivr.net`)
- **Impact:** No `integrity`/`crossorigin` attributes. A compromised CDN or MITM can inject arbitrary script into the authenticated admin/teacher dashboard.
- **Remediation:** Add SRI hashes to each dynamically-created `<script>`/`<link>` tag in `script-loader.js`, or vendor these libraries through the Vite build instead of runtime CDN loads.

### 9. `RescoreGenericV1` bulk-mutates scores behind a bare `--apply` flag
- **Severity:** Medium
- **Category:** Reliability
- **Location:** `app/Console/Commands/RescoreGenericV1.php`
- **Impact:** With `--apply`, persists score revisions across all `UserTest` rows tagged `generic_ds_v1` in bulk — no confirmation prompt, no `--force` requirement, no dry-run diff shown before commit. One mistyped invocation on production rescoring live student scores.
- **Remediation:** Add a `confirm()` prompt (or require `--force` in production) before the mutating branch runs, and log a summary of rows affected before/after.

### 10. `BulkQuestionImportService::import()` — per-row inserts instead of batched
- **Severity:** Medium
- **Category:** Performance
- **Location:** `app/Services/BulkQuestionImportService.php:411-494`
- **Impact:** For every question: separate `Question::create()`, separate `AnswerChoice::create()` per choice, separate `DB::table(...)->insert()` per answer, separate `attach()` call. A 100-question import with 4 choices each generates several hundred individual INSERTs inside one transaction — correct but slow, and this is one of the actively-developed services per current git status.
- **Remediation:** Batch with `AnswerChoice::insert([...])` / `DB::table(...)->insert([...bulk array...])` per question instead of per-row `create()`.

### 11. `BulkQuestionImportService` is a god-class (663 lines, 5+ responsibilities)
- **Severity:** Low
- **Category:** Maintainability
- **Location:** `app/Services/BulkQuestionImportService.php`
- **Impact:** ZIP extraction/zip-bomb guarding, media rewriting, JSON/CSV parsing, and DB persistence all in one class. `assertZipIsSafeToExtract`, `extractZipToTempDir`, `findZipDataFiles`, `parseZipDataFile`, `processZipMedia` have no dependency on the persistence logic.
- **Remediation:** Extract the archive-handling methods into a dedicated `ZipQuestionExtractor`, leave `BulkQuestionImportService` doing validation + persistence only. (Same applies, smaller scale, to `TestManagementService` — blueprint generation vs. deletion/cascade logic are separable, `app/Services/TestManagementService.php:26-203` vs `226-371`.)

### 12. Duplicated JS logic across dashboard table components
- **Severity:** Low
- **Category:** Maintainability
- **Location:** chunked-render: `modules.js:104-132`, `sections.js:68-96`, `tests.js:118-146`; pagination bar: `modules.js:164-207`, `sections.js:125-168`, `tests.js:175-218`; EasyMDE init duplicated 3x in `builder.js` (lines 631-643, 669-682, 876-912, 1355-1389) — one copy (`restoreBuilderDraft`, 1418-1441) re-implements teardown inline instead of calling the shared `bindRemoveBlockButton`, so it will drift out of sync silently.
- **Remediation:** Extract one `renderChunked(container, items, rowTemplateFn)` and one `buildPaginationBar(...)` helper; make `restoreBuilderDraft` call `bindRemoveBlockButton` like the other two call sites do.

### 13. `fetch` error handling checks JSON body before `response.ok`
- **Severity:** Low
- **Category:** Maintainability / UX
- **Location:** `resources/js/test/dashboard/components/bulk-import.js` (submit handlers, e.g. lines 714-757, 759-791, 793-826, 849-938)
- **Impact:** Calls `await response.json()` before checking `response.ok`. A 419 (CSRF expired) or 500 HTML error page throws a generic `SyntaxError`, surfaced to the teacher as `"Unexpected token < in JSON"` instead of "session expired, refresh."
- **Remediation:**
```js
const response = await fetch(url, opts);
if (!response.ok) {
  const message = response.status === 419 ? 'Session expired — please refresh.' : `Request failed (${response.status})`;
  throw new Error(message);
}
const data = await response.json();
```

### 14. Desmos API key hardcoded inline instead of via config
- **Severity:** Low
- **Category:** Maintainability
- **Location:** `resources/views/components/layouts/test.blade.php:22-23`
- **Impact:** Not a real secret (Desmos embed keys are meant to be client-visible), but duplicated twice inline — rotating it means find/replace across a Blade view instead of one env var.
- **Remediation:** `config('services.desmos.key')` sourced from `.env`, referenced once.

### 15. Dead `CorsMiddleware` class
- **Severity:** Low
- **Category:** Maintainability
- **Location:** `app/Http/Middleware/CorsMiddleware.php`
- **Impact:** No-op, never registered in `bootstrap/app.php`. Misleads anyone who greps for CORS handling into thinking it's here.
- **Remediation:** Delete it, or wire it up and actually use it if custom CORS logic was intended.

### 16. Stale unused `GEMINI_API_KEY` env var
- **Severity:** Low
- **Category:** Maintainability
- **Location:** `.env` / `.env.example:69`
- **Impact:** Zero references under `app/`. Dead config surface, likely leftover from tooling rather than app.
- **Remediation:** Remove if confirmed unused, or document what it's for if kept intentionally.

### 17. Duplicate query in `AnalyticsController`
- **Severity:** Low
- **Category:** Performance
- **Location:** `app/Http/Controllers/Student/AnalyticsController.php:57-60`
- **Impact:** `exists()` then a separate `first()` against the same `classroomMemberships()` query — two round trips on the `/home` hot path where one would do.
- **Remediation:**
```php
$primaryClassroom = $user->classroomMemberships()->where('status', 'active')->with('classroom.owner')->first()?->classroom;
$hasClassroom = $primaryClassroom !== null;
```

---

## Quick Wins (low effort, real impact)

1. **Sanitize client-side preview HTML** (Finding #1) — drop in DOMPurify around every `compileMarkdownToHtml()` → `innerHTML` call site. Single library add, four call sites.
2. **Fix `ScoreModuleJob` to rethrow** (Finding #2) — one-line change (remove the swallow), add `failed()`. Turns silent scoring failures into visible, alertable ones.
3. **Delete dead `CorsMiddleware`** (Finding #15) — removes a misleading no-op file.
4. **Set `SESSION_SECURE_COOKIE=true`** in production `.env` and add the key to `.env.example` (Finding #7) — one config line.
5. **Add `$this->authorize()` explicitly in `QuestionController::update`** (Finding #5) — one line, makes an already-correct check visible.

---

## Not Findings (verified safe, worth noting)

- No SQL injection vectors — all `DB::raw`/`selectRaw` use parameter binding; `LIKE` patterns manually escape `%`/`_`/`\`.
- No hardcoded secrets anywhere in `app/`/`config/`; `.env` correctly gitignored and never committed; `.env.example` stays in sync with real `.env` keys.
- `QuestionContentRenderer` (server-side markdown sanitizer) is a well-built DOMDocument allowlist — the standard the client-side path (#1) should be held to.
- Forum/blog views (`forum/show.blade.php`, `blog/show.blade.php`) correctly `e()`-escape before `{!! nl2br(...) !!}`.
- All migrations have `down()` methods; foreign keys are consistently indexed.
- CSRF tokens present on every form checked; no missing `@csrf`.
- Transaction discipline is good — multi-step writes across `TestManagementService`, `AssignmentService`, `ClassroomService`, `AssignmentAttemptService` are wrapped in `DB::transaction`, several with `lockForUpdate()`.
- Dependencies are current (Laravel 12.62, Sanctum 4.3, Livewire 4.3, Vite 7, Tailwind 4) — no abandoned/deprecated packages.
- Test coverage is real, not decorative: dedicated security-flavored feature tests exist (`OwnershipAccessControlTest`, `ModuleProgressionSecurityTest`, `UserTestResultSecurityTest`), plus 5 unit test files concentrated on scoring logic.
