# DigiSAT warm product UI — detailed implementation plan

Status: student-journey delivery implemented; approved teacher/Content Suite extension is implemented and ready for review. The product scope, shared shell, Home/library/classroom/assignment/score styles, and guidance updates are in place. Verification results and remaining checks are recorded in `docs/agents/session.md`. No deployment or commit is included.

## 1. Objective and approved direction

Make the complete student study journey feel like one product, using the approved warmer Home as its visual authority. Preserve product data, navigation destinations, access rules and study behavior. Adapt composition and density to the task instead of copying Home's layout into every feature.

The first delivery covers Home → Test Library → Classes/Classroom → Assignments → Score Report, their empty entry pages and the shared controls required to complete that journey. Student pages operate in Impeccable's Operate mode; passages and explanations retain Read treatment within reports.

Approved decisions:

- Plus Jakarta Sans for product UI; navy `#10234A`, canvas `#F6F8FC`, blue `#2F5BEA`, gold `#F6B93B`, secondary text `#566175`.
- Gold emphasizes the main action in the active page or dialog. Blue identifies links, focus and selected controls. Supporting actions remain quieter; destructive actions retain distinct danger treatment.
- Retire classroom paper, binder, corkboard and handwriting decoration on migrated student pages while preserving classroom information and tools.
- Default body/control text 16px, supporting text 14px, compact table text 14px. Adapt spacing before reducing type size.
- Guided pages use capped content; working pages use more available width; reading content uses a 65–75ch measure.
- Feedback is immediate and calm: visible selection, loading, saved and error states, with brief purposeful transitions.
- Existing navigation, classroom functions, assignment eligibility, score calculations, Home guidance and exam behavior remain authoritative.

## 2. Scope and protected boundaries

### First delivery

| Surface | Route / current implementation | Required coverage |
| --- | --- | --- |
| Home | `/home`; `student/analytics/index.blade.php` and its Home partials | Fresh, assigned, active, one-score, established; goals, guide, activity, discovery and feeds |
| Test Library | `/student/practice`; `student/practice/index.blade.php` | Available tests, current attempts, test options/readiness, unavailable/empty states |
| Class entry and workspace | `/student/classes`, `?all=1`, `/student/classes/{classroom}`; `student/classes/index.blade.php`, `show.blade.php` | Empty classes, join, overview, active workspace, assignments, materials, classmates and settings |
| Assignment entry and detail | `/student/assignments`, `/student/assignments/{assignment}`; **both render `student/assignments/show.blade.php`** | Empty list, classroom context, eligible/expired/closed work, attempts, readiness and completed work |
| Score entry and report | `/student/scores`, `/student/scores/{userTest:ulid}`; score empty/show views and report partials | Redirect to latest report, empty history, full/section/raw results, answer explanations, filters and review controls |
| Shared chrome | Student layout, icon rail, optional list/sidebar, dialogs, notifications/account menus, alerts and loading presentation | Same visual identity on every migrated route; keyboard, focus, scroll and responsive behavior |

Paths in this table are under `resources/views/` unless otherwise indicated.

### Later deliveries

1. Remaining student surfaces: Progress, Blog, Forum, Profile and notification pages, including their empty/error states.
2. Teacher classroom/report/assignment tools, followed by admin and builder workspaces with compact density.
3. Authentication and marketing are separate presentation registers and require their own scoped follow-up; do not silently include them in the first delivery.

### Protected

- `resources/views/engine/**`, `components/layouts/test.blade.php`, engine CSS/JS, timers, submission, mobile-size gate and question-reading typography.
- Shared question renderer and KaTeX behavior, including snapshots, answer rendering and content sanitization.
- Scoring, report payload contracts, historical score disclosures, permissions, assignment limits and readiness POST routes.
- Existing Home milestone storage, goal endpoint and guide preference endpoint. This visual overhaul needs no migration or new endpoint.
- PDF export layout and content. It must remain functional and keep score meaning even though its appearance is outside the first delivery.
- Existing uncommitted work. Record the starting workspace and preserve unrelated changes.

## 3. Source evidence and migration risks

This is planning reconnaissance, not a new rendered accessibility audit. File observations below are source-backed; future rendering and interaction checks remain acceptance work.

| Source | Current implementation | Consequence for implementation |
| --- | --- | --- |
| `resources/css/student/study-home.css:2` | Home-only theme, type and sidebar overrides | Extract the accepted identity into a shared opt-in product scope; keep Home's composition |
| `resources/views/components/ui/button.blade.php:13` | Shared size classes use 14px text; default primary uses blue `bg-brand` | Separate action roles from brand/link color and migrate call sites deliberately |
| `resources/views/components/ui/card.blade.php:28` | Shared card geometry and overflow are owned separately from Home panels | Reuse one panel implementation; prevent clipped focus rings, menus and wrapped headers |
| `resources/css/app.css:1`, `:236` | Tailwind important utilities; global body font uses `!important` | Remove conflicting component-owned utilities when introducing role classes; avoid another specificity/`!important` layer |
| `resources/css/classroom-workspace.css:3`, `:85`, `:93` | Feature stylesheet sets root palette, document overflow, body font and 13.5px text | Isolate legacy styling before retiring it; preserve functional shell scrolling and shared teacher consumers |
| `resources/views/components/layouts/test.blade.php:40` | Exam also loads `app.css`, `app.js` and shared question renderer | A global font/token/component change can reach the protected engine despite not editing engine files |
| `resources/css/student/scores.css:16` | Patterned, multicolor score hero with independent shadows | Replace report chrome with shared surfaces while retaining report sections and meaning |
| `resources/views/student/scores/show.blade.php:2` | Same report view supports student and staff viewers | Determine theme from the authenticated viewer, not the student whose report is shown |
| `DESIGN.md`, `docs/styling-system.md`, `design/global_design_direction.md` | Conflicting historical Roboto, paper/binder and Home-exception guidance | Update authoritative guidance and mark obsolete visual directions superseded; retain useful functional specifications |

Existing conventions inspected: `AGENTS.md`, `CONTEXT.md`, `docs/agents/session.md`, `DESIGN.md`, `docs/styling-system.md` and the global/classroom/progress design documents. Existing stack: Laravel Blade, Alpine, Tailwind 4, Vite, shared Blade UI/shell components, local Fontsource fonts and the established Lucide-based icon component.

## 4. Shared foundation and isolation strategy

### 4.1 Opt in by surface, then consolidate

Add one explicit presentation option to `components/layouts/student.blade.php`, for example `ui="product"`, with the existing presentation as the default during migration. It emits a body scope such as `.product-ui`. Preserve caller-supplied body classes, header modes and user/title data.

Enable that option on all first-delivery student routes. On shared report or Home templates, opt in only for the authenticated student viewer. Staff views keep their current presentation until the staff phase.

Define the new semantic mappings centrally in `design-tokens.css` under that scope. Add a small `resources/css/product-ui.css` for shared component/layout rules, imported once by `app.css`. All new presentation selectors must require the product scope. Keep the existing Vite entry points until a consumer search shows they are no longer needed.

This is a temporary migration boundary, not a theme switcher, user preference, feature-flag service or second permanent design system. Record migrated consumers. Once the later product routes are migrated, remove redundant aliases and collapse the presentation boundary where appropriate; keep engine/marketing boundaries explicit.

### 4.2 Token roles

Extend the existing `--ds-*` vocabulary rather than adding another parallel `--home-*`/`--new-*` system. Preserve old root defaults during the pilot. Within `.product-ui` map:

| Role | Starting value / rule |
| --- | --- |
| Canvas | `#F6F8FC` |
| Surface | `#FFFFFF` |
| Primary text, navy surface, rail | `#10234A` |
| Secondary text | `#566175` |
| Links / selected controls | `#2F5BEA`; derive only consumed hover/active/soft states |
| Emphasized action background / foreground | `#F6B93B` / `#10234A` |
| Panel/separator border | `#DCE3EF`; decorative structure only |
| Input/meaningful control boundary | A stronger role, starting at `#7C8AA3` on white |
| Danger / success / warning | Preserve semantic roles, with text/icon labels and measured pairs |
| Focus | Blue on light surfaces; a verified light/two-tone treatment on navy surfaces |

Keep `brand`/link semantics separate from action emphasis. Repointing `--ds-brand` to gold would incorrectly turn links, selections and charts gold. Gold action styling also needs its own role rather than reusing warning tokens. Warning states require an explicit label/icon and a distinct surface treatment.

Declared opaque-pair calculations made during planning:

| Pair | Contrast |
| --- | --- |
| Navy on gold | 8.76:1 |
| White on gold | 1.76:1 — do not use for action labels |
| Blue on white | 5.52:1 |
| Secondary text on canvas | 5.87:1 |
| Strong input border on white | 3.49:1 |
| Blue against navy | 2.80:1 — insufficient as the sole focus boundary |
| Pale panel border on white | 1.29:1 — insufficient as a sole meaningful control boundary |

These are computed token-pair values, not proof of rendered contrast. Recheck actual backgrounds, alpha, state and adjacent colors in the browser.

### 4.3 Typography, geometry and motion

- Reuse installed Jakarta weights 400/500/600/700/800. UI text below 18px stays at weight 400 or above. Use rem sizes and unitless line heights.
- Page title 30–32px, main guided heading 26px, section heading 18–20px, body/control 16px, supporting/table 14px. Dense layouts reduce padding/gaps first. Inputs remain at least 16px on phones.
- Use tabular numerals for changing/numeric data; keep ordinary prose proportional. Reserve monospace for actual codes or technical values, not all scores.
- Preserve shared question-content typography inside reports. New shell type must not cascade into passages, answer choices, math or the exam.
- Start with the approved Home geometry: 16px panels, 18px navy next-action panel, 10px controls; verify nested surfaces optically. Group with space, use structural borders, and reserve elevation for overlays.
- Reuse the existing spacing scale: 8px inside close groups, 16px between related elements, 24–32px between sections as starting roles.
- Keep frequent hover/selection feedback instant or at most 150ms. Use existing 150/200/300ms motion roles only where needed. Name animated properties, guard hover by pointer capability and honor reduced motion. Avoid animating rail width/padding or page-wide geometry during resize.
- Keep the existing overlay ordering; resolve drawer/backdrop/toggle/menu collisions as a shared shell concern rather than adding arbitrary z-index values.

## 5. Shared component implementation

### Buttons and links

Extend `x-ui.button` while preserving its current public props (`variant`, `size`, `loading`, `href`, `type`) and slots. Use scoped semantic role classes/variables for appearance; remove conflicting appearance utilities owned by the component. Existing unopted consumers retain their current result.

- `primary`: gold/navy in the product scope, used for one main action in the active view/dialog.
- `secondary`: neutral outlined supporting action.
- `danger`: explicit destructive treatment; preserve existing confirmation.
- `ghost-on-dark`: legible supporting action on navy.
- Add a quiet variant only if a real migrated toolbar/row requires it; do not build a speculative component catalog.
- Existing repeated primary row buttons must be changed explicitly to secondary/quiet before their route opts in.
- Keep native anchors for navigation and native buttons/forms for actions. Loading/disabled states must retain an accessible name and actually block repeat activation. Focusable `aria-disabled` controls require behavior guards; disabled anchors must not remain activatable.
- Target default controls around 44–46px, compact controls around 36–40px where density permits. Meet the 24×24px WCAG minimum or its documented exceptions without overlapping expanded hit areas.

### Panels, forms and status

Adapt `x-ui.card` to the product role tokens while preserving header/body/footer/stretch behavior. Do not stretch Home's next-action hero or pin its CTA to an unrelated card's height. Separate decorative clipping from any container holding focus rings or menus.

Reuse existing select, alert, flash, status-badge, dropdown, modal and readiness components. Give the first-delivery forms a common field presentation through shared classes and existing form components; create a field component only when repeated markup needs a real reusable seam.

Required states: default, hover, focus-visible, pressed, selected, disabled, loading, validation error, request failure and success. Selection/status always leaves a static text/icon/border cue. Keep field errors beside controls, connect descriptions, and use stable live regions for asynchronous feedback.

Migrate Home's `.study-button` and `.study-panel` consumers to shared primitives, then delete their redundant appearance rules. Retain Home-specific composition and domain behavior. Remove `.student-home-page` palette/rail duplication only once the shared scope renders the approved result.

## 6. Shell and adaptive layout

Keep `NavRail` destinations and role boundaries. Share navy background, Jakarta labels, selected/hover/focus states, account/notification treatments and group-heading contrast across migrated pages.

Retain existing rail geometry initially: 224px labeled at viewport widths ≥1280px, 74px compact at 641–1279px, drawer at ≤640px. These are shell breakpoints; page composition uses the width remaining after navigation and any contextual list.

Use layout classes on existing page wrappers rather than introducing another layout framework:

| Pattern | Implementation starting point |
| --- | --- |
| Guided | 1120px maximum, leading alignment; main/support split only above 960px available width |
| Workspace | Full available pane, generous but bounded gutters; collapse local side panels when the main task no longer fits |
| Reading | 65–75ch text measure inside the workspace; charts and contextual tools may use wider siblings |

Use definite-width containers, `minmax(0, 1fr)`, `min-width: 0`, logical properties and wrapping for long names/titles. Mobile padding starts near 16px. No CSS `order` rearrangement; DOM and visual order match.

Preserve the existing list/detail and mobile Navigation/List behavior where present. Main navigation, page actions and core filters must remain discoverable without horizontal scrolling. For genuinely two-dimensional tables, use an explicitly labeled local overflow region with visible cues, keyboard access and retained identifiers; use stacked records where that preserves the task better. Never hide columns or actions without an accessible route to the same information.

Keep a single clear main scroll owner per workspace and contained scrolling in overlays. Preserve sticky-anchor/focus visibility with scroll padding. Add or verify the existing layout's skip-to-content path.

## 7. Page-by-page migration

### Home

Treat the current approved Home as the reference, not a fresh redesign. Replace duplicated primitives and theme overrides without removing populated discovery, guide, assignments, activity or resource feeds. Preserve work-first priority, promoted-assignment deduplication, readiness, guide persistence, optional goals, limited-evidence wording and all chart/data alternatives.

### Test Library

- Standardize header, introductory copy and toolbar. Preview and score-history links remain supporting actions.
- Put resumable work ahead of the catalogue when it exists. Keep existing visibility and eligibility rules.
- Use available-width card/list arrangements with titles that wrap, one short purpose and real test-format/section metadata. Preserve existing filters and test-option behavior; do not introduce unsupported filtering or duration promises.
- Give repeated test entries quieter View/options actions. The selected test's existing options/readiness dialog owns its prominent Start/Resume action.
- Preserve the direct `?test_id=` entry and assignment-linked resume behavior. Keep Preview's information page functional and style its outer explanatory page consistently; the Preview engine retains its protected appearance and visit marker.
- Empty/unavailable states explain the situation and provide an actual destination or filter reset where applicable.

### Class overview and workspace

- Replace decorative paper, pins, tape, handwritten accents, binder tabs and corkboard backgrounds with shared surfaces and ordinary navigation/sections.
- Preserve all announcement, teacher-note, material, classmate, assignment and settings content previously carried by decorative regions. Map every removed region to a destination before deleting it.
- Keep class switching, `all=1` overview links, active-class redirects and direct links intact.
- Assignment summaries use consistent state labels, real due dates and quieter repeated actions. The main task area gets the primary emphasis.
- Keep join, nickname and leave forms, validation and destructive confirmation intact. Materials retain Open/Download behavior and external-link semantics.

### Assignments

- Update `student/assignments/show.blade.php` for both its selected-assignment and empty-index modes; do not create an unused separate index view.
- Retain classroom filters/context, list selection and responsive list/detail navigation.
- Place title, class, instructions, due date, status and attempt allowance in a readable hierarchy. Use start/resume only when the existing resolver permits it.
- Keep readiness checks before starting/resuming. Expired/closed/completed states explain available actions without inventing eligibility or urgency.
- Preserve attempt history, answer-review/report links and the recipient-specific contract.

### Score entry and report

- Harmonize the empty score page and actual report, including history list, local navigation, filters, tooltips, disclosures and review modal.
- Replace patterned gradient/heavy hero treatments with the shared report surface; use navy sparingly for hierarchy rather than repeating Home's action hero.
- Preserve existing `scorePresentation` and report-service output. Scaled full results retain /1600, scaled sections /800, and raw/unscaled/legacy/provisional results retain their actual basis, units, ranges and explanatory disclosures. Never restyle raw accuracy as a scaled SAT score or add a mastery claim.
- Retain score zones, domain diagnostics, pacing, question table/detail, explanations and PDF action. Preserve table headers/captions and complete accessible chart values.
- Limit long explanations to a reading measure; keep analytical content wide enough to compare. Ensure question renderer/KaTeX styles survive the UI font change.
- Determine the new theme from the authenticated viewer. Teacher viewing a student's report does not become a migrated student surface.

## 8. Delivery sequence and completion gates

Implement these as small reviewable work units; no commit is implied.

| Order | Work unit | Completion gate |
| --- | --- | --- |
| 0 | Record workspace baseline, affected imports/callers and current behavior tests; reconcile the approved direction with historical docs | Exact consumer map and protected boundaries recorded; no reset of existing work |
| 1 | Central scoped tokens, optional product layout presentation and shared stylesheet | Opted and legacy consumers coexist; protected engine retains its computed styles |
| 2 | Shared controls, panels, form/status states and navigation skin | Home can consume shared primitives without losing its approved appearance; component API remains compatible |
| 3 | Migrate Home and Library, including Preview information/readiness chrome | Both destinations and their entry/empty/active states share the foundation; study actions unchanged |
| 4 | Migrate classes and assignments | Removed decoration has no lost content; overview/context/eligibility/material flows remain correct |
| 5 | Migrate student score entry/report | Score truth, review behavior, accessibility alternatives, exports and staff-view isolation preserved |
| 6 | Complete one journey-wide browser review, one fix batch and at most one confirmation pass | Acceptance matrix satisfied, unresolved issues explicitly reported, guidance updated |
| Later | Remaining student surfaces, then staff/admin | Extend the validated system; remove obsolete aliases only when no consumers remain |

Do not change global default fonts, recolor `bg-brand` indiscriminately, delete feature CSS wholesale, duplicate the shared shell, or rewrite report/attempt logic to achieve a visual migration.

Documentation targets: `DESIGN.md` is the canonical visual specification; `docs/styling-system.md` documents boundaries and implementation roles. Mark superseded visual sections in `design/global_design_direction.md`, `design/student_classroom_design.md` and `design/student_progress_design.md` clearly while preserving functional notes. Synchronize Impeccable metadata through its supported documenter if present/required during implementation; do not invent a sidecar format. Context loading already performed in this conversation need not be repeated. The approved Home/mockup direction is pinned; no new visual-direction tournament or asset generation is required.

## 9. Verification plan

### Six-domain acceptance

| Domain owner | Implementation check |
| --- | --- |
| better-accessibility | Native semantics/names; visible light/dark focus; keyboard completion; dialog/drawer trap and restoration; labels/errors/live regions; redundant state cues; zoom and forced-colors behavior |
| better-layout | Task/source order, content-width breakpoints, long-string expansion, phone controls, local table overflow, scroll ownership, sticky focus visibility and logical properties |
| better-writing | Preserve factual content and terminology; action-specific labels; calm recoverable errors; useful empty states; no unsupported personalization/scoring claims; English and Vietnamese strings remain intact |
| better-typography | Jakarta faces actually loaded; 16/14px roles in rem; unitless line height; phone inputs; tabular data; wrapping/full-value access; reading measure; protected question fonts |
| better-colors | Semantic role separation; rendered default/hover/selected/error/focus contrast; gold/navy foreground; stronger input boundaries; status labels/icons |
| better-ui | Shared corners/surfaces/icons; no unsupported icon names; restrained interaction states; hover capability; reduced motion; contained overlay scroll and existing overlay stack |

### Automated checks

Use the existing PHP 8.2 executable and PHPUnit runner. Run database suites sequentially against `sat_app_testing`; do not use the runtime database or `migrate:fresh` to create visual fixtures.

Core Home/library suites:

```powershell
& C:/Users/minhc/.config/herd/bin/php82/php.exe vendor/phpunit/phpunit/phpunit tests/Feature/StudentHomeDiscoveryHubTest.php tests/Feature/StudentHomeGuideTest.php tests/Feature/HomeFocusLineTest.php tests/Feature/StudentProgressGoalTest.php tests/Feature/ResumeConfirmationTest.php tests/Feature/StudentPracticeVisibilityTest.php
```

Classroom/report isolation suites:

```powershell
& C:/Users/minhc/.config/herd/bin/php82/php.exe vendor/phpunit/phpunit/phpunit tests/Feature/StudentClassManagementTest.php tests/Feature/StudentAssignmentNavigationTest.php tests/Feature/ClassroomLmsOverhaulTest.php tests/Feature/ScoreReportOverhaulTest.php tests/Feature/UserTestAnswerReviewTest.php tests/Feature/TeacherHomeNavigationTest.php tests/Feature/TeacherScoreAccessTest.php tests/Feature/OwnershipAccessControlTest.php tests/Feature/EngineMobileBlockTest.php tests/Feature/TestEngineTranslationProtectionTest.php
node --test tests/JavaScript/student-home.test.mjs
npm run build
git diff --check
```

Extend existing tests only for material behavior affected by extraction: themed/legacy component compatibility, loading names/activation guards, viewer-based theme isolation, route entry/empty behavior, preserved readiness and score units, and shared focus/selection behavior. Retain Home goal/guide tests for normalization, failures, stable focus, clear/null and chart/table synchronization. Avoid tests that only assert CSS class spelling or mirror markup.

### Bounded browser review

Build the complete first-delivery journey before visual QA. Use one batched review across the journey, fix the consolidated findings in one batch, then at most one confirmation pass. Do not run separate open-ended polish loops per page or domain.

- Viewports: 320, 390, 768, 1024 and 1440px; also a 1912px desktop capture to assess use of wide workspace space against the supplied reference.
- Actual 200% browser zoom, using a disposable browser profile. Device scale or page-scale emulation is not a substitute.
- Home states: fresh, actionable assignments, active attempt with long title/name, one comparable full test, established history.
- Library: ordinary catalogue, resume, test options, assignment-linked entry and empty/unavailable states.
- Classes/assignments: no classes, overview, active class, long titles/materials, filtered/empty index, new/active/completed/closed/expired work, join/nickname validation and leave confirmation.
- Reports: scaled full, scaled section, raw/unscaled/legacy/provisional basis, incomplete/unavailable, no scores, large history, long explanations/math and authorized staff viewer.
- States: keyboard-only completion, visible focus, selected/disabled/loading states, request failures/retry, tooltip Escape, dialog/drawer focus restoration, reduced motion, English/Vietnamese strings and long content.
- Compare navigation and controls directly across routes, not just separate page screenshots. Match Home against its approved capture, and verify working pages use space without shrinking their text.
- Scan rendered contrast and accessibility at representative desktop/phone states; inspect meaningful states the scanner cannot open. Revisit the existing shared avatar-name warning manually rather than assuming a pass.
- For resize measurements, wait until layout/fonts/transitions have settled before recording widths. For dialogs, wait for initial focus and completed dismissal before asserting restoration.
- Use read-only render fixtures or isolated test data; never fabricate Home milestones or practice activity in real accounts to obtain screenshots.
- Sample query count and answer-review selects on Home and the report; presentation extraction should not add database queries or per-answer lookups.
- Check the protected exam at a supported size plus its existing mobile guard; compare computed exam chrome/passage fonts, colors and question rendering. Smoke-check an unopted teacher classroom, staff report and admin page.

Record viewport/state, screenshot, console/request errors, contrast pairs and interaction results. Report physical-device and screen-reader audio checks as unverified if they cannot be performed. Accessibility scan output alone is not a release verdict.

## 10. Definition of done

The first delivery is complete when a student can move through Home, Library, Classroom/Assignment and Score Report with consistent navigation, typography, controls and action hierarchy; every supported state remains functional; pages adapt to task and available space; and the protected engine and unopted staff surfaces retain their behavior and visual boundaries.

Delivery includes updated canonical guidance, the migrated-consumer list, relevant test/build results, the bounded browser evidence and an explicit list of remaining issues/unverified checks. No new database fields, UI framework, chart library, telemetry, generalized preference service, deployment or commit is part of this overhaul.

Planning verification: source and existing Home reference evidence inspected; declared color pairs calculated. No new browser audit, release approval or test/build run is claimed for this planning-only deliverable.

## 24 October: approved teacher/Content Suite extension

The user approved extending the shared warm identity to teacher Home/practice, Classes, Assignments/analysis/attempt inspection, individual student progress, shared score reports, live exam management, and Content Suite for teacher/admin authors. Keep compact 14px table/supporting text and 16px standard controls; regroup toolbars and sections where useful while preserving all features, routes, exports and interactions. Retain authoring-specific navigation. Remove obsolete teacher styles and duplicate progress themes, preserving active legacy consumers and the protected exam/question renderer.

Verification includes teacher/student navigation and access suites, classroom/assignment interactions, exam management, authoring/recycle flows, PDF exports and the asset build. Review desktop/mobile renderings in one batch, correct defects together, and use at most one confirmation pass. No commit or deployment is included.
