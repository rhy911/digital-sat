# Audit Report — Digital SAT Platform

Date: 2026-07-27
Branch: `minhdev`
Scope: cleanup audit (dead code, duplication, inconsistency, unused assets, debug leftovers, structural drift).
**Phase 2 deliverable — no code was modified.**

## Relationship to existing audits

Three prior audit docs already exist and were read first so this report does not restate them:

- `docs/code-audit-2026-07-22.md` — security/deps (client-side XSS in preview, `ScoreModuleJob`, CORS, SRI, Desmos key, dead `CorsMiddleware`, unused `GEMINI_API_KEY`, dashboard JS duplication).
- `docs/backend-audit-2026-07-23.md` — backend correctness (transaction/lock, exception leakage, timer authority, `authorize(): true`, dead code, dev login).
- `docs/improvement-plan-2026-07-27.md` — forward-looking roadmap (CI, observability, a11y, i18n, ops).

Findings below marked **(overlaps X)** are already recorded elsewhere; they are listed only where this pass adds new evidence. Everything unmarked is new.

The unique contribution of this pass is **section C (CSS) and section D (JS/Alpine)** — the Tailwind/raw-CSS hybrid and the front-end dead-asset surface, which no prior audit covered systematically.

---

## Summary table

| Category | CRITICAL | HIGH | MEDIUM | LOW | Effort |
|---|---|---|---|---|---|
| A. Laravel / PHP backend | 0 | 1 | 3 | 5 | S–M |
| B. Blade views | 0 | 0 | 3 | 3 | S–M |
| C. CSS (Tailwind + raw hybrid) | 0 | 1 | 3 | 3 | M–L |
| D. Vanilla JS + Alpine | 0 | 1 | 0 | 3 | S–M |
| E. Assets & build | 0 | 0 | 0 | 2 | S |
| **Total** | **0** | **3** | **9** | **16** | — |

**Verified clean (no findings):** zero `dd()`/`dump()`/`var_dump()`/`ray()` in the codebase; zero `console.log`/`debugger` (only two legitimate `console.warn` in error handlers); zero `var` declarations in `resources/js`; every model defines `$fillable` (none uses `$guarded = []`); `User::$fillable` excludes `role`; no `env()` calls outside `config/`; `QuestionContentRenderer` is a correct allowlist sanitizer; no Alpine↔vanilla-JS state desync found in the test engine (checked `#popover-content`, `#moreMenu`, `#takeBreakBtn`, `highlight-mode-active` — all guarded or child-only mutation); eager loading is applied correctly in the Blade `@foreach` paths checked.

**Recommended greenlight order if you want the cheapest wins first:** A3, A4, A5, B1, B2, C5, E1 (pure deletions, near-zero risk) → D1, A1 (real behavior bugs) → C1, C2 (the CSS architecture work, needs its own conversation).

---

## A. Laravel / PHP backend

### [HIGH] `TestBuilderController::index()` silently swallows every exception and swaps the paginator for a collection
- **File(s):** `app/Http/Controllers/Admin/TestBuilderController.php:20-62`
- **Category:** A — error handling / dead-fallback
- **Evidence:** Four `try { … } catch (\Exception $e) { … }` blocks. `$e` is never logged, never rethrown, never inspected. On failure `$tests`, `$passages`, `$allModules` are replaced with `collect()` — but the success path returns `->paginate(30)` (a `LengthAwarePaginator`). Any view code calling `->links()` / `->currentPage()` / `->total()` on that variable throws a second, unrelated error. A DB outage or a bad `visibleTo()` scope therefore renders an empty test-builder dashboard with no trace anywhere.
  ```php
  } catch (\Exception $e) {
      $tests = collect();
  }
  ```
- **Risk to fix:** low. Removing the catch (or logging + rethrowing) restores normal Laravel error reporting; the only behavior change is that a real failure becomes visible instead of silent.
- **Suggested action:** delete the four `try/catch` wrappers, or narrow them to the specific exception you actually expect and log it:
  ```php
  } catch (\Throwable $e) {
      Log::error('Test builder dashboard query failed', ['exception' => $e]);
      throw $e;
  }
  ```
  Note `snapshot()` (`:72-93`) runs the same queries with **no** try/catch, which confirms the catches are not load-bearing.

### [MEDIUM] `index()` and `snapshot()` duplicate three identical queries verbatim
- **File(s):** `app/Http/Controllers/Admin/TestBuilderController.php:21-26` vs `:74-79`; `:54-59` vs `:81-86`; `:32` vs `:80`
- **Category:** A — duplication
- **Evidence:** The `Test::visibleTo(...)->with([...])->withCount([...])->latest()->paginate(30)` chain and the `Module::visibleTo(...)` chain are byte-identical between the two methods. Any change to eager-loading has to be made twice or the JSON refresh path silently diverges from the initial render.
- **Risk to fix:** low.
- **Suggested action:** extract two private methods on the controller (`testsQuery()`, `modulesQuery()`), or move them to `TestManagementService` — a service already exists for this domain. Behavior unchanged.

### [MEDIUM] Debug-grade `Log::info` left in the bulk-import path and in registration
- **File(s):** `app/Services/BulkQuestionImportService.php:162, 188, 194, 280, 398, 400`; `app/Http/Controllers/Auth/RegisterController.php:17`
- **Category:** A — debug leftovers
- **Evidence:** Calls are written with an inline fully-qualified `\Illuminate\Support\Facades\Log::info(...)` (rather than the imported facade used elsewhere in the same file), and the messages read as trace output, not operational logging:
  ```php
  \Illuminate\Support\Facades\Log::info('Decoded JSON keys: ' . implode(', ', array_keys($decoded)));
  \Illuminate\Support\Facades\Log::info('First item keys:', array_keys($payload['items'][0]));
  ```
  `RegisterController.php:17` is `Log::info('RegisterWeb called');` — a bare trace marker.
- **Risk to fix:** none. `Log::error('ZIP Import Error: …')` at `:89` is real error logging and must stay.
- **Suggested action:** delete the six `Log::info` calls in `BulkQuestionImportService` and `RegisterController.php:17`. Keep `RegisterController.php:37` (`'User created via Web', ['id' => $user->id]`) — that is a legitimate audit line with safe scalar metadata.

### [MEDIUM] Dead duplicate route `/landing-new`
- **File(s):** `routes/web.php:21-23`
- **Category:** A — dead route
- **Evidence:** Unnamed closure route returning `view('public.landing')`. `LandingController` (`app/Http/Controllers/Public/LandingController.php:19`) returns the *same* view for `/`. Grep across `resources/`, `app/`, `routes/` finds zero references to `/landing-new`; it has no route name so it cannot be `route()`d to.
- **Risk to fix:** low — the only risk is an unbookmarked external link. Confirm nobody is using the URL as a preview link before deleting.
- **Suggested action:** delete lines 21-23.

### [MEDIUM] Commented-out route block in `routes/api.php`
- **File(s):** `routes/api.php:8-13`
- **Category:** A — commented-out code
- **Evidence:** Six lines of commented `Route::post('/register', …)` / `Route::post('/login', …)` / `Route::get('/verify-email/…')`. The live web equivalents exist in `routes/web.php`.
- **Risk to fix:** none.
- **Suggested action:** delete lines 8-13.

### [LOW] Two admin FormRequests return `true` from `authorize()` with no explanation
- **File(s):** `app/Http/Requests/Admin/StoreSectionRequest.php:9-12`, `app/Http/Requests/Admin/StoreModuleRequest.php:9-12`
- **Category:** A — authorization clarity. **(overlaps `backend-audit-2026-07-23.md` #7)**
- **Evidence (new):** Verified the controllers *do* authorize. `SectionController::store` (`:29-31`) does `Test::findOrFail(...)` then `$this->authorize('update', $test)` then `ensureUnlocked($test)`. `ModuleController::store` (`:31-35`, `:49-51`) does an explicit ownership check plus `ensureUnlocked`. So this is a documentation gap, not a hole. Contrast `Teacher/NoteUpdateRequest.php:11`, which carries exactly the right comment.
- **Risk to fix:** none (comment only).
- **Suggested action:** add the same style of comment as `NoteUpdateRequest`:
  ```php
  return true; // SectionController::store() calls $this->authorize('update', $test) after resolving test_id
  ```

### [LOW] Role middleware applied inconsistently across the `/student` prefix
- **File(s):** `routes/web.php:95-115`
- **Category:** A — middleware inconsistency
- **Evidence:** Inside the same `['auth','verified']->prefix('student')` group, `/classes*` and `/assignments*` are wrapped in an inner `Route::middleware('role:student')` group (`:105-114`), while `/progress-analytics` (`:96`), `/practice*` (`:98-101`) and `/scores*` (`:102-104`) are not. A teacher or admin can therefore reach the student practice/scores pages but not student classes.
- **Risk to fix:** medium — this may well be intentional (teachers previewing practice content / their own score history). Adding `role:student` to those three would be a **behavior change**, not a cleanup.
- **Suggested action:** do not change without a decision. Either add `role:student` to the remaining routes or add a one-line comment in `web.php` recording that the asymmetry is deliberate.

### [LOW] Route name prefix `home-dashboard.` on `/admin/*` test-builder routes
- **File(s):** `routes/web.php:190`
- **Category:** A — naming drift
- **Evidence:** `->prefix('admin')->name('home-dashboard.')` produces names like `home-dashboard.questions.list` for URIs under `/admin/…`. The sibling admin group at `:166` uses `->name('admin.')`. The prefix name appears to be a leftover from an earlier page layout.
- **Risk to fix:** medium — renaming breaks every `route('home-dashboard.*')` call in Blade and JS. Not worth it for cosmetics alone.
- **Suggested action:** leave as-is; note in `CLAUDE.md` so it is not mistaken for a bug. Only rename if you are already touching the test-builder route table.

### [LOW] `/api/user` returns the full `User` model
- **File(s):** `routes/api.php:16-18`
- **Category:** A — dead/overbroad endpoint
- **Evidence:** `return $request->user();` serializes the whole model minus `$hidden`. No caller found in `resources/js` or `resources/views`. It is the Laravel skeleton default.
- **Risk to fix:** low, but confirm no external Sanctum client depends on it before removing.
- **Suggested action:** delete, or replace with an explicit shape (`['id','name','email','role']`).

### [LOW] `GEMINI_API_KEY` declared in `.env.example` but referenced nowhere
- **File(s):** `.env.example:69`
- **Category:** A — config drift. **(overlaps `code-audit-2026-07-22.md` #16)**
- **Evidence (new):** re-confirmed — zero `env()` calls exist anywhere in `app/`, and the key appears in no file under `config/`.
- **Suggested action:** delete the line.

---

## B. Blade views

### [MEDIUM] Nine orphaned Blade files — the pre-redesign student dashboard
- **File(s):**
  - `resources/views/components/student/cards/completed-practice-card.blade.php`
  - `resources/views/components/student/cards/in-progress-practice-card.blade.php`
  - `resources/views/components/student/cards/empty-state-box.blade.php`
  - `resources/views/components/student/dashboard/bigfuture-section.blade.php`
  - `resources/views/components/student/dashboard/practice-option-link.blade.php`
  - `resources/views/components/student/dashboard/practice-toggle-header.blade.php`
  - `resources/views/components/student/dashboard/tests-toggle-header.blade.php`
  - `resources/views/components/scoring/estimate-label.blade.php`
  - `resources/views/student/scores/partials/domain.blade.php`
- **Category:** B — orphaned views
- **Evidence:** Built the full inbound-reference set for every view: all `view('…')`, `View::make`, `loadView`, `@include`, `@extends`, and every `<x-…>` tag across `resources/views`, `app/`, `routes/`. These nine appear in **zero** of them. Cross-checked with a repo-wide literal grep (excluding `node_modules`, `vendor`, `public/build`) — the only hits are historical mentions in `docs/redesign-roadmap.md:132`, which describes a past edit to three of them, not a current usage.
  Their content confirms the diagnosis: `bigfuture-section` renders a "Explore BigFuture" block, and the cards use the old `.option` / `.status-badge` / Tailwind-heavy markup that predates the current `classroom-workspace.css` design language.
  `student/scores/partials/domain.blade.php` is superseded by `domain-group.blade.php`, which is the one `report.blade.php:223,229` actually includes.
- **Risk to fix:** low. These are Blade *components* — they can only be reached by a literal `<x-…>` tag, so there is no dynamic-name escape hatch (unlike `view($name)`). `domain.blade.php` is the one to double-check, since `@include` *can* take a variable; grep found no variable includes in `report.blade.php`.
- **Suggested action:** delete all nine. This is the prerequisite for finding C2 (the matching orphan CSS).

### [MEDIUM] `student/progress/index.blade.php` and `teacher/students/progress.blade.php` are structurally duplicated
- **File(s):** `resources/views/student/progress/index.blade.php` (146 lines) vs `resources/views/teacher/students/progress.blade.php` (149 lines)
- **Category:** B — duplicate partials
- **Evidence:** Both pull the identical seven partials with identical argument shapes — `score-hero`, `score-trend`, `recommendations-card`, `accuracy-bar-card` (×2, same `rowLabelKey` split of domain vs difficulty), `pacing-bar-card`, `habits-card`, `efficiency-card` — in the same order, inside the same `progress-cards` / `progress-cards--secondary` wrappers with the same inline `style="margin-top: 20px;"`. The differences are the shell (`x-shell.icon-rail` items, `x-shell.sidebar-list` roster) and the `@php` data-prep block.
- **Risk to fix:** medium. The two pages have different data sources and different authorization context; a bad extraction couples them.
- **Suggested action:** **needs your decision before execution.** The mechanical option is to extract the seven-include body into `resources/views/partials/performance/_stack.blade.php` taking the already-shared variables, leaving each page to own only its shell and data prep. The alternative is that this duplication is deliberate isolation (student sees their own data, teacher sees a student's) and should stay. I would not guess here.

### [MEDIUM] Announcement card markup duplicated between the student and teacher classroom views
- **File(s):** `resources/views/student/classes/show.blade.php:95-140` vs `resources/views/teacher/classes/show.blade.php:155-200`
- **Category:** B — duplicate partials
- **Evidence:** Both render the same `announce-card__time` / `announce-pin-badge` / `announce-card__body` / `announce-comments` / `announce-comment__avatar` / `announce-comment__role-tag` structure, including the same `{!! nl2br(e($announcement->body)) !!}` and the same teacher-detection expression `in_array($comment->author_id, [$classroom->owner_id, ...$classroom->coTeachers->pluck('user_id')->all()], true)`. The teacher view adds pin/delete controls; the student view does not.
- **Risk to fix:** medium — the teacher variant has extra affordances, so extraction needs a `:canManage` prop.
- **Suggested action:** extract to `resources/views/components/classroom/announcement-card.blade.php` with a `canManage` boolean prop. Convenient side effect: it fills the currently empty `components/classroom/` directory (finding B2). **Ask before doing** — same judgment call as B4.

### [LOW] Dead defensive block in both engine module views, in the wrong order
- **File(s):** `resources/views/engine/module/reading.blade.php:11-15`, `resources/views/engine/module/math.blade.php:31-35`
- **Category:** B — dead code / ordering bug
- **Evidence:** Both files do this:
  ```php
  $testData->section_directions ??= "…";   // line 2  — dereferences $testData
  $testData ??= (object) [ … ];            // line 11 / 31 — "in case $testData is unset"
  ```
  The fallback at line 11/31 can never fire usefully: if `$testData` were unset, line 2 would already have raised `Undefined variable`. In practice `SessionController` always passes `$testData` (`:140`, `:267`, `:307`), so the block is unreachable. It is also incomplete — the fallback object lacks `section_directions`, `section_number`, `module_number`, `is_preview`, `duration_minutes`, all of which the view then reads.
- **Risk to fix:** low. Deleting a block that cannot execute.
- **Suggested action:** delete the `$testData ??= (object) [ … ];` block in both files. Keep `$questions ??= collect();` / `$savedAnswers ??= collect();` — those are real guards for the preview path.

### [LOW] Empty directory `resources/views/components/classroom/`
- **File(s):** `resources/views/components/classroom/`
- **Category:** B — structural drift
- **Evidence:** Directory contains zero entries (checked with `-Force`). `design/global_design_direction.md:3` references `components/classroom/*` as a source of the redesign, so the components were moved or inlined and the directory was left.
- **Risk to fix:** none.
- **Suggested action:** remove the directory, or fill it via B5.

### [LOW] Inline `onclick`/`onchange` mixed with the `addEventListener` convention
- **File(s):** 18 occurrences across 8 files. Highest concentration: `resources/views/teacher/classes/show.blade.php` (7), `resources/views/components/layouts/test.blade.php` (4, e.g. `:123` `onclick="toggleTimer()"`, `:188` `onchange="window.location.href = …"`, `:38` `onload="…"`), `resources/views/teacher/assignments/show.blade.php` (2)
- **Category:** B — pattern inconsistency
- **Evidence:** The same interactions elsewhere are bound via `addEventListener` in `resources/js` (225 call sites across 29 files). The inline handlers are what force the `window.toggleTimer` / `window.nextQuestion` / `window.showQuestion` global exports in `resources/js/test.js:32-38`.
- **Risk to fix:** medium — converting them means removing the matching `window.*` exports, and the engine hot path is latency- and correctness-sensitive.
- **Suggested action:** do not convert as part of cleanup. Record the convention (inline handlers are permitted in the engine layout only) so the inconsistency is intentional rather than accidental.

---

## C. CSS — Tailwind v4 / raw CSS hybrid

> Note on the build: this project is Tailwind **v4**, configured in CSS. There is no `tailwind.config.js` and no `postcss.config.js` — content scanning is declared with `@source` in `resources/css/app.css:18-21`. The `@source '../**/*.blade.php'` and `@source '../**/*.js'` globs are relative to `resources/css/`, so they cover all of `resources/`. **Purge coverage is correct** — no finding there.

### [HIGH] `@import "tailwindcss" important;` marks every Tailwind utility `!important` — this is the root of the specificity war
- **File(s):** `resources/css/app.css:1`
- **Category:** C — specificity
- **Evidence:**
  ```css
  @import "tailwindcss" important;
  ```
  The `important` flag on the v4 import makes **every generated utility** emit `!important`. Consequence: any hand-written rule that needs to win against a utility on the same element must itself use `!important`. That is exactly what the codebase shows — **304 `!important` declarations across 12 raw CSS files**:

  | File | `!important` count |
  |---|---|
  | `resources/css/admin/test-builder.css` | 179 |
  | `resources/css/classroom-workspace.css` | 29 |
  | `resources/css/engine/main.css` | 31 |
  | `resources/css/student/analytics.css` | 20 |
  | `resources/css/student/scores.css` | 12 |
  | `resources/css/student/profile.css` | 8 |
  | `resources/css/engine/test-footer.css` | 8 |
  | `resources/css/auth.css`, `student/progress.css`, `engine/test-header.css` | 4 each |
  | `resources/css/app.css` | 3 |
  | `resources/css/engine/test-review.css` | 2 |

  `CLAUDE.md` documents a *different* justification for the test-builder subset ("wire dark-theme overrides through `.dark-theme-dashboard !important` since the CDN Tabulator stylesheet loads after") — that reason is real and applies to some of the 179, but not to the other 125 across the rest of the tree.
- **Risk to fix:** **high.** Removing the `important` flag is a global cascade change; every place that currently relies on a utility beating a raw rule would flip. It cannot be done as a cleanup commit.
- **Suggested action:** **do not change under this audit.** Record it as a known architectural constraint. If you want to unwind it later, the safe sequence is: (1) fix C2 (delete orphan rules) to shrink the surface, (2) migrate raw component CSS to `@layer components` so it loses to utilities by layer rather than by `!important`, (3) drop the flag last, behind a visual-regression pass. That is a project, not a cleanup item — worth its own conversation.

### [MEDIUM] Confirmed orphan selectors in hand-written CSS
- **File(s):** see table
- **Category:** C — orphaned raw CSS
- **Evidence:** Extracted every class selector from all 15 files under `resources/css/`, stripped comments, and grepped each name against the full corpus of `resources/views/**/*.blade.php` + `resources/js/**/*.js` + `app/**/*.php`. Then manually re-verified each hit to strip false positives (see the note below the table).

  | File | Confirmed orphan selectors |
  |---|---|
  | `student/analytics.css` | `ds-alert`, `ds-attempt-row__score`, `ds-booklet-seal`, `ds-booklet-seal-inner`, `ds-button`, `ds-card-actions`, `ds-card-label--accent`, `ds-card-note`, `ds-dashboard-grid--secondary`, `ds-meta-dot`, `ds-muted`, `ds-next-card`, `ds-practice-library`, `ds-resume-card` (+ `__action-wrapper`, `__body`, `__icon-wrapper`, `__info`, `__meta`, `__status`, `__title`), `ds-resume-coil`, `ds-resume-grid`, `ds-resume-section`, `ds-resume-widget`, `ds-resume-widget-spine`, `ds-section-heading`, `ds-test-card` (+ `__action`, `__body`, `__meta`, `__meta-flat`, `__topline`, `--in-progress`), `ds-test-grid` — **35 total** |
  | `admin/test-builder.css` | `dash-btn`, `dash-btn-primary`, `dash-btn-secondary`, `dash-btn-danger`, `dash-field`, `glass-panel`, `dashboard-title-gradient`, `builder-block-preview`, `table-id-cell`, `table-numeric-cell`, `border-indigo-200`, `text-indigo-400`, `text-indigo-600`, `text-indigo-650` — **14** |
  | `classroom-workspace.css` | `answer-correct`, `answer-wrong`, `response-saved`, `response-omitted`, `d-inline-block` — **5** |
  | `student/scores.css` | `sd-section-icon`, `sd-section-scores`, `sd-section-score-card`, `sd-section-score-label`, `sd-section-score-num`, `sd-section-score-sub`, `sd-tab-panel`, `sort-icon` — **8** |
  | `student/progress.css` | `ds-bar-track__tick`, `ds-trend-subline` (+ `.is-math`, `.is-rw` compounds) — **4** |
  | `app.css` | `bento-card` — **1** |
  | `auth.css` | `motivational-prompt`, `name-row`, `signin-link`, `test-device-btn` — **4** |
  | `student/profile.css` | `ps-back` — **1** |

  The `analytics.css` block is the largest and correlates directly with finding B1 — `ds-test-card`, `ds-resume-card`, `ds-practice-library` are the CSS for the same removed student-dashboard design. Deleting B1 and C2 together is one coherent change.

  **False positives excluded after manual verification — do NOT delete these:**
  - `brand-wordmark--sm|md|lg|brand|dark|inverse` — composed dynamically at `resources/views/components/brand/wordmark.blade.php:10` (`"brand-wordmark--{$size} brand-wordmark--{$tone}"`).
  - `cal-dot--event|due|opens`, `cal-item--due|opens` — composed in `resources/js/classroom-calendar.js:45,101`.
  - `attempt-monitor__status--active|complete` — composed at `resources/views/teacher/assignments/partials/attempt-monitor.blade.php:121`.
  - `tabulator-*`, `ts-dropdown`, `tomselected`, `ts-hidden-accessible`, `CodeMirror-cursor` — third-party skinning for CDN-loaded Tabulator / TomSelect / CodeMirror. Intentional.
- **Risk to fix:** low for the confirmed list, but every deletion must be re-grepped at execution time, because a class name can be reintroduced dynamically at any point. The `border-indigo-200` / `text-indigo-*` entries are Tailwind-utility-shaped overrides — verify they are not being generated by `@apply` before removing.
- **Suggested action:** delete in file-sized batches (one commit per CSS file), re-running the grep for each name immediately before removal. Start with `student/analytics.css` paired with the B1 view deletions.

### [MEDIUM] Breakpoint drift between raw media queries and Tailwind's scale
- **File(s):** all files under `resources/css/`
- **Category:** C — breakpoint drift
- **Evidence:** Tailwind v4 defaults are `sm:640 md:768 lg:1024 xl:1280 2xl:1536` (this project defines no custom `--breakpoint-*` in the `@theme` block at `app.css:23-46`, so the defaults are in force). The raw CSS uses **23 distinct media queries**, of which these do not correspond to any Tailwind breakpoint:
  `max-width: 480px`, `600px`, `767px`, `860px`, `980px`, `1023px`, `1180px`, `1279px`; `min-width: 1537px`; plus `(min-width: 1024px) and (max-width: 1279px)` and `(min-width: 1024px) and (max-width: 1366px)`.
  Two specific hazards: `max-width: 640px` (10 occurrences, the most common) overlaps Tailwind's `sm:` at **exactly** 640px — both rulesets apply at that width. And `767px`/`1023px` sit one pixel below `md:`/`lg:`, so an element styled with `md:flex` plus a raw `max-width: 767px` rule behaves correctly, while a sibling using `max-width: 768px` does not — the inconsistency is invisible until it bites.
- **Risk to fix:** medium. Normalizing values changes rendering at those exact widths.
- **Suggested action:** do not bulk-rewrite. Two safer steps: (a) change the 10 `max-width: 640px` queries to `max-width: 639.98px` to remove the exact-boundary double-apply; (b) define the off-scale values as `--breakpoint-*` custom properties in the `@theme` block so the two systems share one source of truth going forward. Both are worth their own commits; (a) is the one that fixes an actual defect.

### [MEDIUM] KaTeX is delivered three different ways
- **File(s):** `package.json:24` (npm `katex@^0.16.47`); `resources/js/student/scores-katex.js:1-2` (bundled import); `resources/css/student/scores.css:5` (`@import "katex/dist/katex.min.css"`); `resources/views/components/layouts/test.blade.php:34,35,38` (CDN `katex@0.16.11`); `resources/js/test/dashboard/utils/script-loader.js:36,44,45` (CDN `katex@0.16.11`)
- **Category:** C/E — duplicate library delivery + version skew
- **Evidence:** The npm package is `0.16.47`; both CDN paths pin `0.16.11`. A student viewing a score report gets the bundled 0.16.47; the same student inside the test engine gets CDN 0.16.11. Three copies of the CSS can be in play. This also compounds `code-audit-2026-07-22.md` #8 (CDN scripts without SRI).
- **Risk to fix:** medium. Moving the engine off the CDN changes when KaTeX becomes available — `test.blade.php:38` relies on the script's `onload` to trigger `window.smartRenderMath`, which a bundled import would need to replace with an explicit call.
- **Suggested action:** pick one delivery path. Preferred: bundle everywhere via Vite (the dependency is already installed and already used on the scores page), delete the three CDN tags in `test.blade.php` and the three in `script-loader.js`, and call `smartRenderMath` from module code instead of an `onload` attribute. If the CDN must stay for the engine, at minimum align the pinned version to the npm one and add SRI hashes.

### [LOW] Two dead stylesheet files
- **File(s):** `public/css/auth.css` (3.6 KB), `resources/sass/app.scss` (84 B)
- **Category:** C/E — unused assets
- **Evidence:** `public/css/auth.css` is in no `vite.config.js` input and no Blade `<link>`; `DESIGN.md:37` already independently confirmed it is orphaned and deferred deletion to "a later cleanup pass" — this is that pass. `resources/sass/app.scss` is not in the Vite input list either, and its own first line says `// SASS file is no longer used for Bootstrap. Kept for Vite compatibility if needed.` A repo-wide grep finds zero `.scss` imports anywhere.
- **Risk to fix:** none.
- **Suggested action:** delete both files. See E1 for the follow-on dependency.

### [LOW] Geist font loaded from CDN on the landing layout only
- **File(s):** `resources/views/components/layouts/landing.blade.php:9`; `resources/css/app.css:26` (`--font-geist: "Geist Sans", sans-serif;`)
- **Category:** C — inconsistent asset delivery
- **Evidence:** `<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1.3.0/dist/fonts/geist.css">` is the only CDN font in the project — every other family (Roboto, Plus Jakarta Sans, IBM Plex Mono, Architects Daughter) is bundled via `@fontsource/*` imports in `app.css:3-14`. The `--font-geist` token is declared globally in `@theme` but the font only actually loads on the landing page, so any other page using that token silently falls back to `sans-serif`.
- **Risk to fix:** low.
- **Suggested action:** either add `@fontsource/geist-sans` and import it in `app.css` alongside the others (consistent, removes a third-party request), or scope `--font-geist` so it is clearly landing-only. Check first whether `--font-geist` is referenced outside the landing page.

### [LOW] Inline `<style>` blocks in Blade
- **File(s):** `resources/views/auth/email-verify.blade.php:3-8`, `resources/views/auth/email-verified.blade.php:3`, `resources/views/teacher/assignments/export-print.blade.php:6`, `resources/views/student/scores/export-pdf.blade.php:6`
- **Category:** B/C — inline style
- **Evidence:** Only four exist. Two are legitimate: `export-print` and `export-pdf` render through DOMPDF and **must** be self-contained — leave them alone. The other two are one-off overrides pushed into a `@push('styles')` stack, e.g.
  ```blade
  @push('styles')
      <style>
          .signin-container { gap: 20px; }
      </style>
  @endpush
  ```
  `.signin-container` is defined in `resources/css/auth.css`; this is a single-property override living outside the stylesheet.
- **Risk to fix:** low.
- **Suggested action:** move the two auth overrides into `auth.css` as a scoped modifier (e.g. `.signin-container--verify { gap: 20px; }`) and drop the `@push` blocks. Do not touch the two DOMPDF views.

---

## D. Vanilla JS + Alpine.js

### [HIGH] Three divergent `showCustomConfirm` implementations, two of which fight over `window.showCustomConfirm` on the same page
- **File(s):** `resources/js/app.js:334` (+ export at `:397`), `resources/js/test/ui.js:262` (+ export at `:326`), `resources/js/test/dashboard/utils/custom-alert.js:176` (+ re-export at `resources/js/test/dashboard/index.js:31`)
- **Category:** D — global namespace collision + behavioral divergence
- **Evidence:** All three define a function with the identical signature `showCustomConfirm(message, type = 'warning', title = 'Confirm Action')` and all three assign it to `window.showCustomConfirm`. They do not behave the same:
  ```js
  // app.js:345
  msgEl.textContent = message;
  // test/ui.js:273
  msgEl.innerHTML = message.replace(/\n/g, '<br>');
  ```
  The pages where two of them load simultaneously:
  - **Test engine** — `resources/views/components/layouts/test.blade.php:32` loads *both* `resources/js/app.js` and `resources/js/test.js` (which pulls in `test/ui.js`). Whichever module evaluates last owns `window.showCustomConfirm`.
  - **Admin test builder** — `layouts/admin.blade.php:10` loads `app.js`; `admin/test-builder/index.blade.php:225` loads `test-dashboard.js`, whose `index.js:31` assigns the third implementation.

  This is not theoretical: `test.blade.php:168` calls `window.showCustomConfirm(...)` from an inline Alpine handler for **"Exit the exam"**, and `resources/js/test/navigation.js:370` calls the imported (`test/ui.js`) version for the module-advance confirm. The two call sites on the same page can resolve to different implementations. The `\n\n` in navigation.js's message renders as line breaks under `ui.js` and as literal whitespace under `app.js` — and `app.js` is the safe one, so whichever wins also decides whether the message string is treated as HTML.
- **Risk to fix:** medium. The engine confirm dialog is on the exam-exit path; a regression here is user-visible during a live test.
- **Suggested action:** pick one implementation as canonical and have the other two import it rather than redefine it. `resources/js/test/dashboard/utils/custom-alert.js` is the most complete (16 KB, used by 3 modules via proper ES imports) — promote it to a shared module (e.g. `resources/js/ui/confirm.js`), import it from `app.js` and `test/ui.js`, and keep exactly **one** `window.showCustomConfirm = …` assignment. Decide deliberately whether the canonical version uses `textContent` (safe) or `innerHTML` (allows `<br>`); if callers need line breaks, use `textContent` plus `white-space: pre-line` in CSS rather than `innerHTML`.

### [LOW] `getOrCreateAlertModal` duplicated alongside the above
- **File(s):** `resources/js/app.js:~310`, `resources/js/test/ui.js:119`, `resources/js/test/dashboard/utils/custom-alert.js`
- **Category:** D — duplication
- **Evidence:** Each of the three modules builds a `#customAlertModal` element from scratch. They deduplicate at runtime by id lookup (`let modal = document.getElementById('customAlertModal'); if (modal) return modal;`), so whichever runs first defines the DOM structure the other two then query. It works only because the structures currently agree — a change to one file's markup silently breaks the others' `querySelector` calls.
- **Risk to fix:** low, and it disappears for free if D1 is fixed.
- **Suggested action:** fold into the D1 consolidation. No separate work.

### [LOW] Dashboard module state held on `window.__td*` globals
- **File(s):** `resources/js/test/dashboard/core/config.js:84-93`, `dashboard-data.js:20-22,101-104,144-155`, `index.js:193-243`, `components/{tests,sections,modules,questions,builder}.js` (~40 assignments total)
- **Category:** D — global namespace pollution
- **Evidence:** Pagination, filter, and cache state (`__tdQuestionsPage`, `__tdLatestTests`, `__tdLatestPayload`, `__tdSectionsPage`, `__builderExistingQuestions`, …) is stored on `window` and read across seven modules. The `__` prefix signals it was intended as private, but every module can write it, and there is no single owner. This is the same architectural smell as `code-audit-2026-07-22.md` #12 ("duplicated JS logic across dashboard table components"), from the state angle.
- **Risk to fix:** medium — refactoring shared mutable state across seven modules is a real change, not a cleanup.
- **Suggested action:** out of scope for this pass. If you tackle #12 from the earlier audit, do this at the same time: one exported state object in `core/config.js` that the components import, instead of `window`.

### [LOW] Repeated inline Alpine `x-data` dropdown literal
- **File(s):** 8 occurrences of `x-data="{ open: false }"` paired with `@click.outside="open = false"` — `components/ui/dropdown.blade.php:34`, `components/shell/icon-rail.blade.php:39,68`, `components/layouts/test.blade.php:80,150`, `components/admin/test-builder/builder-tab.blade.php:132,143`
- **Category:** D — duplicate Alpine logic
- **Evidence:** Reviewed every `x-data` in the project (32 total). They are all small inline literals — there is **no** copy-pasted multi-method Alpine component anywhere, which is good. The only repeated shape is this two-property dropdown.
- **Risk to fix:** low, but the payoff is small.
- **Suggested action:** low priority. If you want it, register `Alpine.data('dropdown', () => ({ open: false, close() { this.open = false } }))` once in `app.js` and use `x-data="dropdown"`. Note that `components/ui/dropdown.blade.php` already exists as the shared component — the other seven sites may simply want to use it instead.

---

## E. Assets & build

### [LOW] `sass-embedded` is an unused dependency
- **File(s):** `package.json:15`
- **Category:** E — unused dependency
- **Evidence:** The only `.scss` file in the repo is `resources/sass/app.scss`, which is not in the Vite input list and whose own comment says it is no longer used (see C5). A repo-wide grep finds no `.scss` import in any JS or CSS file. Vite loads a Sass compiler only when it encounters `.scss`.
- **Risk to fix:** none, provided C5 is executed first (delete `app.scss`, then the dependency).
- **Suggested action:** delete `resources/sass/app.scss`, then `npm uninstall sass-embedded`. Run `npm run build` afterwards to confirm.

### [LOW] `esbuild` declared explicitly as a devDependency
- **File(s):** `package.json:14`
- **Category:** E — redundant dependency
- **Evidence:** `esbuild@^0.28.1` is declared directly, but Vite bundles and manages its own esbuild. No `esbuild` import or CLI invocation exists in the repo (`package.json` scripts are only `vite` / `vite build`). A direct declaration at a different major than Vite's internal one can cause resolution surprises.
- **Risk to fix:** low, but non-zero — if something in the toolchain resolves the hoisted top-level copy, removing it changes which esbuild is used.
- **Suggested action:** remove it and run a clean `npm ci && npm run build` to verify. If the build breaks, put it back and add a comment explaining the pin. Lower priority than E1.

---

## Awaiting your decision

Three items need a call from you before anything can be executed, because guessing would change product behavior:

1. **B4 / B5** — is the student↔teacher view duplication deliberate isolation, or should it be extracted into shared partials?
2. **A10** — is the missing `role:student` on `/student/scores`, `/student/practice`, `/student/progress-analytics` intentional (teachers preview those pages) or an oversight?
3. **C1** — do you want to keep `@import "tailwindcss" important;` as a permanent constraint, or schedule the unwind as a separate project? This report assumes *keep*, and treats the 304 `!important` declarations as consequences rather than defects.

## Ready to execute on approval

Grouped as they would be committed. Nothing here changes rendered output or business logic.

| Batch | Findings | Files touched |
|---|---|---|
| 1. Debug + dead route removal | A3, A4, A5, A7 | `BulkQuestionImportService.php`, `RegisterController.php`, `routes/web.php`, `routes/api.php`, `.env.example` |
| 2. Orphaned Blade views | B1, B2, B3 | 9 deletions + 1 empty dir + 2 engine view edits |
| 3. Orphaned CSS (one commit per file) | C2 | 8 CSS files |
| 4. Dead stylesheets + deps | C5, E1 | `public/css/auth.css`, `resources/sass/app.scss`, `package.json` |
| 5. Inline style consolidation | C7 (partial) | 2 auth views + `auth.css` |
| 6. Confirm-dialog consolidation | D1, D2 | `app.js`, `test/ui.js`, `dashboard/utils/custom-alert.js`, new shared module |
| 7. Error-handling fix | A1, A2, A6 | `TestBuilderController.php`, 2 FormRequests |

Verification available per batch: `composer test` (42 test files, real coverage on scoring/auth/access-control), `npm run build`, plus manual route checks for batches 2/3/5/6, which have no automated coverage.
