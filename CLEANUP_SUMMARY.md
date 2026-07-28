# Cleanup Summary

Date: 2026-07-27
Branch: `minhdev`
Source plan: `AUDIT_REPORT.md`

**Not committed.** All changes are in the working tree. Batches below are grouped as they should be committed; suggested messages are included.

## Verification (run after all changes)

| Check | Result |
|---|---|
| `composer test` | **246 passed** (968 assertions), 102.04s |
| `npm run build` | **✓ built in 3.37s**, no warnings |
| `php artisan route:list` | loads cleanly |

Per-batch checks were also run as work progressed (`BulkQuestionImportTest` 5 passed, `AuthRoleTest` 6 passed after batch 1; `npm run build` after batches 3 and 4).

Bundle sizes dropped from the CSS deletions:

| Asset | Before | After |
|---|---|---|
| `analytics-*.css` | 22.04 kB | 16.11 kB |
| `test-builder-*.css` | 42.06 kB | 40.43 kB |
| `scores-*.css` | 39.24 kB | 38.30 kB |
| `progress-*.css` | 17.73 kB | 17.24 kB |
| `profile-*.css` | 7.61 kB | 7.39 kB |

---

## Batch 1 — debug leftovers and dead routes (A3, A4, A5, A7)

`cleanup: remove debug logging, dead routes, and stale env key`

- `app/Services/BulkQuestionImportService.php` — removed 6 `Log::info` trace calls (`Found data file in ZIP`, `Decoded JSON keys`, `Items extracted from`, `Media imported`, `Bulk Import payload items count`, `First item keys`). Kept both `Log::error` calls.
- `app/Http/Controllers/Auth/RegisterController.php:17` — removed `Log::info('RegisterWeb called')`. Kept the `'User created via Web'` audit line at `:37`.
- `routes/web.php` — removed the unnamed `/landing-new` route that duplicated `/`.
- `routes/api.php` — removed 6 lines of commented-out register/login/verify routes.
- `.env.example` — removed the unreferenced `GEMINI_API_KEY` and its comment header.

## Batch 2 — orphaned Blade views (B1, B3)

`cleanup: delete orphaned blade components and unreachable view fallbacks`

Deleted 9 files, all re-grepped immediately before removal (only `AUDIT_REPORT.md` and `docs/redesign-roadmap.md` referenced them):

- `components/student/cards/{completed-practice-card,in-progress-practice-card,empty-state-box}.blade.php`
- `components/student/dashboard/{bigfuture-section,practice-option-link,practice-toggle-header,tests-toggle-header}.blade.php`
- `components/scoring/estimate-label.blade.php`
- `student/scores/partials/domain.blade.php`

Removed the three directories left empty (`components/student/cards`, `components/student/dashboard`, `components/scoring`).

Also removed the unreachable `$testData ??= (object) [...]` fallback from `engine/module/reading.blade.php` and `engine/module/math.blade.php` — it sat *after* `$testData->section_directions ??=` had already dereferenced the variable, so it could never fire. `$questions ??=` / `$savedAnswers ??=` were kept.

## Batch 3 — orphaned CSS (C2)

`cleanup: remove orphaned css selectors across raw stylesheets`

| File | Removed |
|---|---|
| `student/analytics.css` | the whole dead student-dashboard block — `ds-test-card*`, `ds-test-grid`, `ds-resume-*`, `ds-booklet-seal*`, `ds-next-card`, `ds-card-actions`, `ds-card-note`, `ds-practice-library`, `ds-section-heading`, `ds-alert`, `ds-button`, `ds-meta-dot`, `ds-muted`, `ds-attempt-row__score`, `ds-dashboard-grid--secondary`, `ds-card-label--accent` |
| `admin/test-builder.css` | `dash-btn*`, `dash-field`, `glass-panel`, `dashboard-title-gradient`, `builder-block-preview`, `table-id-cell`, `table-numeric-cell`, `text-indigo-600/650/400`, `border-indigo-200` |
| `student/scores.css` | `sd-section-scores`, `sd-section-score-card/label/num/sub`, `sd-section-icon`, `sort-icon`, `sd-tab-panel` |
| `classroom-workspace.css` | `answer-correct`, `answer-wrong`, `response-saved`, `response-omitted`, `d-inline-block` |
| `student/progress.css` | `ds-bar-track__tick`, `ds-trend-subline` (+ `.is-math` / `.is-rw`), `ds-bar-fill.is-low`, `ds-bar-fill.is-medium` |
| `auth.css` | `test-device-btn`, `name-row`, `signin-link`, `motivational-prompt` |
| `app.css` | `bento-card` |
| `student/profile.css` | `ps-back` |

Where an orphan shared a comma-group with a live selector, only the orphan was dropped and the live rule kept intact — `.ds-card p` (was grouped with `.ds-next-card p`), `.ds-preview-list` (was grouped with `.ds-practice-library`), `.ds-attempt-row span` (was grouped with `.ds-muted`), `.ds-workspace-actions` / `.ds-workspace-head` (were grouped with `.ds-button` / `.ds-dashboard-grid--secondary`), `.border-indigo-100`, `#editQuestionPreviewContent`, `.transition-all` / `.transition-colors` / `.sidebar-link`.

Confirmed left in place as documented false positives: `brand-wordmark--*`, `cal-dot--*`, `cal-item--*`, `attempt-monitor__status--*` (all composed dynamically), and the `tabulator-*` / `ts-*` / `tomselected` / `CodeMirror-*` third-party skinning.

Re-ran the orphan scanner after the edits: only those false positives remain.

## Batch 4 — dead stylesheets and unused dependencies (C5, E1, E2)

`cleanup: drop dead stylesheets and unused build dependencies`

- Deleted `public/css/auth.css` (already confirmed orphaned in `DESIGN.md:37`) and the now-empty `public/css/`.
- Deleted `resources/sass/app.scss` (its own comment said it was no longer used) and the now-empty `resources/sass/`.
- `npm uninstall sass-embedded esbuild` — no `.scss` remains in the repo, and Vite manages its own esbuild. Build verified after removal.

## Batch 5 — inline `<style>` consolidation (C7)

`cleanup: move auth page styles out of inline blade style blocks`

- `auth/email-verify.blade.php` — removed the `@push('styles')` block entirely. Its only rule was `.signin-container { gap: 20px }`, and `.signin-container` exists in **no** stylesheet and **no** markup anywhere in the repo, so this was dead rather than something to migrate.
- `auth/email-verified.blade.php` — moved `.success-checkmark`, `.success-checkmark svg`, `.redirect-timer` and the `popIn` / `drawCheck` / `fadeIn` keyframes into `resources/css/auth.css` (loaded by `layouts/auth`) verbatim. Rendering is unchanged.
- The two DOMPDF views (`teacher/assignments/export-print.blade.php`, `student/scores/export-pdf.blade.php`) were left untouched — their inline styles are required for PDF rendering.

## Batch 7 — error handling and authorization clarity (A1, A2, A6)

`fix: stop test builder dashboard from swallowing query failures`

- `app/Http/Controllers/Admin/TestBuilderController.php` — removed the four `try/catch (\Exception $e)` blocks that discarded the exception and replaced paginators with `collect()`. Extracted `testsQuery()`, `passagesQuery()`, `modulesQuery()` private methods (typed `LengthAwarePaginator`) so `index()` and `snapshot()` no longer duplicate the same three queries. `snapshot()` already ran these queries without any try/catch, which is what confirmed the catches were not load-bearing.
- `app/Http/Requests/Admin/StoreSectionRequest.php`, `StoreModuleRequest.php` — added comments recording where authorization actually happens, matching the existing style in `Teacher/NoteUpdateRequest.php`. No logic change.

## Approved extraction — B5 (announcement card)

`refactor: extract shared classroom announcement card component`

New `resources/views/components/classroom/announcement-card.blade.php` with a `canManage` prop, now used by both `teacher/classes/show.blade.php` and `student/classes/show.blade.php`. Behavior preserved exactly:

- Pin/unpin form and announcement delete button render only when `canManage`.
- Comment delete renders when `canManage` **or** the comment is the viewer's own — matching the teacher page (always) and the student page (own comments only) as they were.
- The confirm-dialog copy and `title` attribute still differ per role ("this comment" / "Delete comment" vs "your comment" / "Delete your comment").

This also fills the previously empty `components/classroom/` directory (finding B2), so no directory removal was needed.

## Approved extraction — B4 (partially done, see below)

`refactor: extract shared progress digest corkboard component`

New `resources/views/components/classroom/progress-digest.blade.php`, used by both `student/progress/index.blade.php` and `teacher/students/progress.blade.php`. The two corkboard blocks were byte-identical, so this is a pure de-duplication.

**The seven-partial performance stack was NOT merged.** On closer reading during execution the two pages are not the same layout: the student page splits the cards across three `x-shell.tab-bar` panels (`overview` / `skills` / `pacing`), while the teacher page stacks all of them flat with `margin-top: 20px`. The copy strings also differ per audience ("Accuracy across every completed test" vs "…every visible completed test"; "Complete a practice test to see…" vs "No completed tests are visible for this student yet"), and the teacher page passes an extra `showBadges` to `score-trend`. Merging them would force one page's layout onto the other — a redesign, not a refactor. The `@include` calls already share all seven partials, which is where the real duplication risk lives; what remains is per-page composition. Flagging rather than forcing it, per the "stop and report" rule.

---

## Deferred — needs your decision

### D1 / D2 — the three `showCustomConfirm` implementations

**Not executed.** Executing this revealed the finding is worse and less cosmetically neutral than `AUDIT_REPORT.md` described, so I stopped rather than guessing.

What I found reading the implementations side by side: the two modals are not just divergent in text handling, they are **visually different themes**.

- `resources/js/app.js:255-332` builds a **dark** modal — `background: #111827`, `color: #f8fafc`, brand-gradient primary button, plus an extra `#customAlertInput` field. Uses `msgEl.textContent = message`.
- `resources/js/test/ui.js:119-186` builds a **light** modal — `background: #ffffff`, `color: #0f172a`, slate `#1e293b` button, no input field. Uses `msgEl.innerHTML = message.replace(/\n/g, '<br>')`.
- `resources/js/test/dashboard/utils/custom-alert.js:176` is a third variant used by the dashboard.

Both `getOrCreateAlertModal` functions inject their own `style[data-custom-alerts]` and both create `#customAlertModal`, each guarded by the same selector/id. Because one function does both the markup and the style injection and returns early if the modal already exists, markup and styles always stay paired — so there is **no mixed-theme bug today**. What decides which theme you get is which module calls the function first, which in practice follows `@vite` script order: the page-specific module (`test.js`, `test-dashboard.js`) loads after `app.js` and therefore wins on the engine and test-builder pages, while `app.js` wins everywhere else.

The consequence: **any consolidation changes the modal's appearance on at least one page.** Making `app.js` canonical turns the engine's exam-exit and module-advance dialogs dark; making `test/ui.js` canonical turns every other page's dialogs light. That is a visual decision, not a cleanup, and it lands on the live exam-exit path.

**What I need from you:** which look is the intended one — the dark `app.js` modal or the light `test/ui.js` modal? Once that is settled, the fix is straightforward: extract the winner into `resources/js/ui/confirm.js`, import it from all three call sites, and keep exactly one `window.showCustomConfirm` assignment. I would also switch the canonical version to `textContent` plus `white-space: pre-line` rather than `innerHTML`, which preserves the `\n` line breaks that `navigation.js:370` relies on without treating messages as HTML.

### A10 — `role:student` middleware asymmetry

Untouched, as flagged. `/student/scores`, `/student/practice`, `/student/progress-analytics` still lack `role:student` while `/student/classes` and `/student/assignments` have it. Adding it would be a behavior change (teachers/admins would lose access to those pages), so it needs your call on whether the current asymmetry is deliberate.

### C1 — `@import "tailwindcss" important;`

Left in place, per the report's recommendation and the assumption you did not overturn. The 304 `!important` declarations in raw CSS remain a consequence of it. Batch 3 shrank the surface; unwinding the flag itself is still a separate project needing visual-regression coverage.

---

## Flagged in the report, not executed (no approval requested, or advised against)

| Finding | Status |
|---|---|
| C3 — breakpoint drift (23 distinct media queries; `max-width: 640px` double-applies with Tailwind `sm:`) | not done — the recommended `639.98px` change alters rendering at exact widths and deserves its own commit + visual check |
| C4 — KaTeX delivered three ways (npm `0.16.47` bundled vs CDN `0.16.11` in `test.blade.php` and `script-loader.js`) | not done — the engine relies on the CDN script's `onload` to trigger `smartRenderMath`; replacing it changes init timing on the exam page |
| C6 — Geist font from CDN on `layouts/landing` while `--font-geist` is a global token | not done — needs a decision on bundling `@fontsource/geist-sans` vs scoping the token |
| A8 — `/api/user` returns the full `User` model | not done — should confirm no external Sanctum client depends on it first |
| A9 — `home-dashboard.` route-name prefix on `/admin/*` | advised to leave; renaming breaks every `route('home-dashboard.*')` call in Blade and JS |
| B6 — 18 inline `onclick`/`onchange` handlers | advised to leave; converting means removing the matching `window.*` exports on the engine hot path |
| D3 — `window.__td*` dashboard globals (~40 assignments) | out of scope; tackle alongside `code-audit-2026-07-22.md` #12 |

## Found during execution, not approved — no action taken

- **Design-hook findings on pre-existing CSS.** The `impeccable` hook flagged `side-tab` accent borders in `student/progress.css` (L102, L294, L419, L461), `border-accent-on-rounded` in `app.css` (L222) and `classroom-workspace.css` (L1703), and `bounce-easing` on the `cubic-bezier(0.175, 0.885, 0.32, 1.275)` in `auth.css`. None were introduced by this pass — the `auth.css` one is the email-verified animation moved verbatim in batch 5, and the rest are untouched existing rules. These look like deliberate choices under the skeuomorphic classroom design language that `CLAUDE.md` mandates (binder tabs, manila panels, push-pins), so I left them alone rather than flattening the design to satisfy a linter. Worth a separate `/impeccable audit` pass if you want them reviewed properly.
- **`.ds-resume-widget::before`** — the 6px left accent bar the hook flagged in `analytics.css` was inside the dead resume-widget block, so batch 3 removed it as a side effect of the orphan cleanup, not as a design change.
- **Two pre-existing Intelephense false positives** in `routes/web.php` (L86 `auth()->user()`, L248 `auth()->login()`) — unrelated to these edits, present before and after.
- **Browser-support warnings** surfaced by the CSS language server (`min-height: auto` in Firefox, missing `-webkit-backdrop-filter` in `scores.css:104,605`, `-webkit-user-select` in `scores.css:427`, `oklch()` in Chrome < 111). All pre-existing; none touched.
