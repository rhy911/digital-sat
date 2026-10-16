# Assignments audit — 2026-10-17

Scope: student and teacher Assignments workspaces, including Results, Analysis, Settings, inspection, readiness, and navigation. Mode: Operate. Compared with the [2026-10-16 re-audit](D:/Projects/php/digital-sat/docs/audits/2026-10-16-assignments-reaudit.md). Audit only; no application fixes applied.

## Implementation integrity verdict

**Fail: the new inspection flow needs correction before release.** All three previously outstanding findings are resolved, but the deferred inspection implementation introduces a broken request URL and an insufficiently scoped content endpoint. Its loading and error states also fail independently of the URL issue.

The classroom visual system remains coherent. The mechanical detector returned five warnings: three `gray-on-color` matches at teacher view lines 756, 763, and 897 combine mutually exclusive Alpine classes; `.pinned-note-card` at workspace CSS line 956 is outside these rendered views; the font warning at line 96 conflicts with the approved classroom font choice. None is an additional verified defect. Runtime contrast scans now pass in the tested states.

## Executive summary and health score

**14/20 — Good by the numeric bands, with a blocking inspection defect.** The aggregate score does not override the P0/P1 findings. Current issues: **1 P0, 1 P1, 2 P2, 0 P3**.

| Dimension | Previous | Current | Key evidence |
|---|---:|---:|---|
| Accessibility | 3/4 | 3/4 | Tested WCAG axe scans pass; inspection still evaluates missing data during loading. |
| Performance | 3/4 | 4/4 | No analysis request or full inline data on initial Results; summary/details load on demand. |
| Responsive design | 3/4 | 3/4 | Contained tablet tables and phone touch panning still work; compact targets remain below the stronger 44×44 comfort goal. |
| Theming | 3/4 | 3/4 | Previously failing colors now pass; the established mix of local tokens and palette utilities remains. |
| Implementation integrity | 3/4 | 1/4 | Inspection requests fail, unrelated private questions are readable, and failed loads look successful. |
| **Total** | **15/20** | **14/20** | **Resolve the inspection integration before release.** |

Recommended order: repair the inspection URL and endpoint scope, then its pending/error states. Preserve the verified contrast, deferred loading, and Escape fixes.

## Previous outstanding findings

| Finding | Result | Evidence |
|---|---|---|
| Contrast | **Resolved in tested states** | All eight initial role/tab/viewport axe scans report zero WCAG A/AA violations. Scrolled Settings and the populated inspection's selected Correct state also pass. |
| Eager analysis data | **Resolved** | Initial Results has no analysis request and no `assignment-analysis-data` script. Serialized DOM markup is 209,247 characters, down from 749,025. The 115-question summary is fetched on first Analysis visit; full question details use a separate endpoint. |
| Editable date-picker Escape | **Resolved** | Escape closes the calendar and retains `settings_available_at_alt` focus, both fresh and after two Settings reinitializations. Clear then empties both submitted and visible values. |

The original fixes also hold in the checks repeated here: teacher arrow-key tab switching, status sorting, student-search recovery, named readiness dialog focus/inertness, closed/open mobile navigation state, and tablet table containment.

## Detailed findings

### [P0] 1. Every normal Inspect request uses the wrong assignment route key

**Location:** [teacher view:288](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:288); [Assignment route key:41](D:/Projects/php/digital-sat/app/Models/Assignment.php:41). **Category:** Implementation Integrity.

`openInspection()` builds its URL with `'assignment' => $assignment->id`. The bound Assignment model resolves by `ulid`, so the browser requests a numeric-key route that cannot resolve.

**Live reproduction:** open the audited assignment's Analysis tab and choose Inspect question 1. The browser requests `/teacher/assignments/36/questions/3888/inspection` and receives **404**. The modal has no answer options or student detail, despite the summary showing seven responses. Calling the same endpoint with assignment ULID `01M404A3H0B8W1H6F6AVTC8X1B` returns **200**, four choices, and seven student responses.

**Impact:** teachers cannot complete question inspection through the shipped UI. This blocks that workflow, not the entire Assignments workspace.

**Standard:** route/model-binding contract and feature correctness. **Recommendation:** generate the route with the Assignment model or its route key, consistently with the summary endpoint. Verify a real Inspect click against the returned full details.

**Suggested command:** `$impeccable harden`.

### [P1] 2. The inspection endpoint returns unrelated private question content

**Location:** [controller:306](D:/Projects/php/digital-sat/app/Http/Controllers/Teacher/AssignmentController.php:306); [inspection service:507](D:/Projects/php/digital-sat/app/Services/AssignmentReportService.php:507); [existing question visibility scope:75](D:/Projects/php/digital-sat/app/Models/Question.php:75). **Category:** Implementation Integrity / access control.

The controller authorizes viewing the assignment, then accepts any independently bound Question. The service immediately loads that question's choices, passage, and explanation without checking that it belongs to the assignment's test or assigned section.

**Verified case:** for the existing teacher account, question **4035** is outside this assignment and excluded by `Question::visibleTo($teacher)`. A read-only request to the teacher's own assignment ULID plus `/questions/4035/inspection` nevertheless returns **200**, a stem, four choices, and an explanation object. No private content text is reproduced in this report.

**Impact:** an authorized teacher can change a question ID and retrieve content they cannot access through the normal question visibility scope. The broken UI URL in finding 1 does not protect the correctly addressed endpoint.

**Standard:** object-level authorization and parent/child resource scoping. **Recommendation:** resolve the question through the authorized assignment's permitted test/section question set and reject unrelated IDs with 403/404. Preserve any intentional right to inspect questions actually assigned to that class; do not rely on assignment authorization alone for arbitrary question IDs.

**Suggested command:** `$impeccable harden`.

### [P2] 3. Failed asynchronous loads are cached and presented as successful empty data

**Location:** [analysis loader:226](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:226); [inspection loader:285](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:285). **Category:** Implementation Integrity / error feedback.

Both loaders parse JSON without checking `res.ok` or the response shape. Analysis sets `analysisLoaded = true` even for an error payload. Inspection merges an error payload into the question and sets `details_loaded = true`. Catch handlers only log to the console, and there is no visible retry action.

**Live controlled failure:** returning a JSON **503** for the browser's Analysis request produces zero questions, zero modules, 0% error, and No questions match the selected filters. `analysisLoaded` becomes true and no retry button appears. The real 404 inspection response is likewise marked `details_loaded: true` despite missing details.

**Impact:** teachers can mistake a failed request for an empty or valid analysis. Revisiting the tab or question reuses the failed state until a full reload.

**Standard:** distinguish failed, empty, pending, and successful states. **Recommendation:** check HTTP success and validate the expected payload before updating data or success flags. Show a visible failure message and retry control, preserve retryable state, and avoid merging error envelopes into question objects.

**Suggested command:** `$impeccable harden`.

### [P2] 4. Inspection renders detail-only bindings before details exist

**Location:** [modal body:699](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:699), [response count:826](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:826), [All count:878](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:878); summary data originates at [service:403](D:/Projects/php/digital-sat/app/Services/AssignmentReportService.php:403). **Category:** Implementation Integrity / rendering.

Summary question objects intentionally omit `students`, but the modal immediately evaluates `activeQuestion.students.length` twice. Its body uses `x-show`, which hides markup without deferring its Alpine expressions.

**Independent confirmation:** in the test browser only, the faulty request URL was rewritten to the correct ULID endpoint and its successful response delayed by 350 ms. The page still threw **two `Cannot read properties of undefined (reading 'length')` errors** before the valid seven-student response populated. Thus fixing finding 1 alone does not close this issue.

**Impact:** each newly opened question can produce uncaught rendering errors and temporarily blank or incomplete counts, even with a healthy server response. This is a separate pending-state defect from failed-response handling.

**Standard:** the rendered data contract must hold throughout asynchronous loading. **Recommendation:** initialize detail fields with safe defaults or mount the detail subtree only after successful loading; make all pending bindings safe. Retain the visible loading state and existing modal focus behavior.

**Suggested command:** `$impeccable harden`.

## Positive findings and systemic pattern

- The prior contrast work passes in the tested default, scrolled Settings, and selected inspection states. The detector's conditional-class matches are not reasons to undo those colors.
- Deferring analysis data removed another roughly 540,000 serialized markup characters from initial teacher pages. Initial Results/Settings have 908 DOM elements; Analysis has 7,368 after loading its data. The actual summary is 57,032 JSON characters in this sample; these measurements are not compressed transfer sizes.
- The date-picker Escape fix works after replacement-input reinitialization and keeps the expected field focus.
- Readiness remains named and managed: focus enters the dialog, background content becomes inert, and Escape returns to Start attempt. Its WCAG axe scan passes.
- Closed mobile navigation remains inert; opening it makes main content inert. Teacher status sorting yields Completed, Overdue, Withdrawn, and Clear search restores the ten result rows.
- At 768 px, main content remains 439 px wide with 439 px scroll width; the 333 px table wrapper contains its 730 px contents. Actual synthesized phone touch events move that wrapper from `scrollLeft = 0` to 269 px.

The recurring new problem is the contract at the deferred-data boundary: correct resource identity, permitted resource scope, response success, and the shape available before/after loading all need explicit handling. The initial page scans do not exercise that boundary.

## Evidence and limits

- Used isolated Microsoft Edge/Chromium `155.0.4283.45` contexts and the same existing teacher/student accounts and assignments as earlier audits. No settings, assignment lifecycle, or attempt-start form was submitted. Clear modified browser form values only.
- Captured student and teacher views at 1440×1000 and 390×844; teacher Results/Analysis/Settings were all included. All eight initial loads returned HTTP 200, with zero WCAG axe violations and no uncaught JavaScript errors before opening inspection.
- Exercised keyboard tabs, inspection, pending-response behavior, a controlled JSON 503, corrected-path read-only requests, editable picker Escape/reinitialization, status sorting, empty-search recovery, navigation inertness, readiness focus, and tablet containment.
- The inspection screenshot with the test-only URL rewrite demonstrates the populated UI's contrast, not a fix to the application. The original request failure was captured separately. Uncaught inspection errors are recorded in both normal and corrected-path checks.
- The first interaction harness sampled Analysis before its fetch had completed and then stopped when reading the missing first item. The confirmation waits for a real Inspect button and records 115 loaded questions. That harness timing error is not counted as a product finding.
- Touch evidence uses CDP `touchStart`, twelve `touchMove` events, and `touchEnd` in Chromium mobile emulation. No physical device, Safari/Firefox, complete screen-reader session, production load test, or PHP regression-suite run. The sample still lacks an omitted-response case and a multi-class student dataset; prior source verification for those states is not upgraded to live proof.

Screenshots: [actual inspection failure](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-audit-2026-10-17/teacher-inspection-failure-1440.png), [controlled Analysis failure](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-audit-2026-10-17/teacher-analysis-failure-1440.png), [populated inspection using test-only URL rewrite](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-audit-2026-10-17/teacher-inspection-test-rewrite-1440.png), [tablet](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-audit-2026-10-17/teacher-results-768.png).

Raw evidence: [initial scans](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-audit-2026-10-17/initial-results.json), [interactions](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-audit-2026-10-17/interaction-results.json), [confirmation](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-audit-2026-10-17/confirmation-results.json), [question scope metadata](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-audit-2026-10-17/question-scope-check.json).

## Recommended actions

1. **[P0] `$impeccable harden`** — repair the generated inspection route and verify the actual Inspect action loads choices and responses.
2. **[P1] `$impeccable harden`** — scope inspection questions to the authorized assignment's permitted content and verify unrelated/private IDs are rejected.
3. **[P2] `$impeccable harden`** — implement validated, retryable fetch states and safe pending detail bindings; check normal, delayed, 404, and 503 responses.
4. **`$impeccable polish`** — verify the repaired flow together with the already passing keyboard, focus, contrast, and responsive behavior.

You can ask me to run these one at a time, all at once, or in any order you prefer. Re-run `$impeccable audit` after fixes to reassess the score.
