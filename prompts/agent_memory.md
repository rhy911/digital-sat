# Agent Memory Log

> **RULE:** ALWAYS add new entries to TOP of log. Newest items first.

## [2026-05-28 11:25] - Feat: Email Verification Success Screen & Auto-redirect
- **Topic**: Implement successful email verification screen with dynamic countdown and auto-routing.
- **Summary**: Created a high-fidelity visual success screen (`email-verified.blade.php`) in English featuring a green checkmark animation, a dynamic 3-second redirect countdown, and a manual "Continue Now" fallback button. Refactored the styling to a hybrid layout using Tailwind CSS for simple classes and isolated Raw CSS strictly for animations, borders, and shadows. Updated `VerifyEmailWebController.php` to return the new success view upon verification success (or if already verified) instead of immediately redirecting to home.

## [2026-05-28 10:35] - Feat: Add Remember me checkbox to Sign-In page
- **Topic**: Align remember-me option in user credentials panel.
- **Summary**: Added flex-aligned "Remember me" checkbox to the right of "Forgot password?" in `signin.blade.php`. Updated `checkFormValidity()` inside `auth.js` to only require checkboxes to be checked if they carry the `required` attribute. This guarantees the optional "Remember me" checkbox does not lock out form submissions. Rebuilt production assets successfully.

## [2026-05-27 16:40] - Bug Fix: Resolve test completion stuck loading screen
- **Topic**: Prevent z-index lockout during test preview and real exam completion.
- **Summary**: Identified that the `.loading-screen` z-index (`100000`) was blocking the custom confirmation/alert dialogs (`z-index: 10000`), hiding them behind the dark loading overlay. Changed `.custom-alert-modal` z-index to `200000` in both `ui.js` and `test-main.css`. Surgical update to `navigation.js` to dismiss the loading screen *before* presenting the alert modals, then showing a transitional status text during actual redirects. Clean production compilation confirmed.

## [2026-05-27 16:30] - UI: Fix container and title naming mismatches
- **Topic**: Solve container conflicts and page header layout mismatches.
- **Summary**: Rename `.container` to `.auth-container` in `layouts/auth.blade.php` and `auth.css` to bypass default Tailwind v4 `.container` layout constraints. Cleaned up `forgot.blade.php`, `reset-password.blade.php` and `email-verify.blade.php` to leverage unified dynamic flex headers and remove stray `.signin-title` styles. Optimized email verification page's logout button styling.

## [2026-05-27 11:30] - Auth: Warm & Personalized 3-Role Authentication Flow Redesign

- **Topic**: Overhaul Sign-in, Sign-up, and Entry screens to be warm, supportive, and customizable by role.
- **Summary**: Replace intense `#324dc7` with soft academic indigo `#4361EE` and custom role picker cards in `auth.css`. Enable dynamic `role` query headers in `signin.blade.php`. Implement high-fidelity visual cards for Student/Teacher selector in `signup.blade.php`. Save selected roles securely via `RegisterWebController.php`.
- `resources/css/auth.css`: Shift brand colors to soft academic indigo, style dynamic segmented role radio cards. Reverted buttons and links to exact legacy border and hover outline styles. Added `.signup-step` classes with `!important` to bypass Tailwind specificity.
- `resources/views/index.blade.php`: Personalized brand copy ("PrepSat™"), add direct "Continue as Student" and "Continue as Teacher" pathways.
- `resources/views/auth/signin.blade.php`: Dynamically style subtitles based on query param roles. Added conditional check to omit "Don't have an account?" link for the admin role.
- `resources/views/auth/signup.blade.php`: Visually pleasing icon-based account type selector cards. Refactored into a snappy, compact 2-step multi-step form to avoid scroll fatigue (Step 1: Role, Step 2: Credentials), using `.signup-step` toggle states. Repurposed the original top back link (`#backBtn`) to navigate back to Step 1 when in Step 2, and removed the duplicate bottom back button.
- `app/Http/Controllers/Auth/RegisterWebController.php`: Validate and store the user chosen role on signup. Defaulted `name` attribute to `username` to satisfy database integrity constraints during creation.
- `resources/js/auth.js`: Overhauled `checkFormValidity()` to support radio buttons by verifying if at least one item in the radio group is checked, resolving registration button activation lockouts.
- **Build**: Vite dynamic compiler and PHP syntax checks OK.
- **Changes**: Softer educational branding, streamlined role-based flows, encouraging progress tracking prompts. Exact legacy button visual specs preserved. Button lockout resolved.

## [2026-05-27 08:11] - UI: Bulletproof Responsive Fixed Questions Pool Table Fix

- **Topic**: Eliminate layout clipping and unbroken word overflow in Questions Pool Table.
- **Summary**: Force `table-layout: fixed` and `min-width: 1000px` to enforce explicit column sizes. Humanize and `block truncate` snake_case Domain identifiers (`problem_solving_and_data_analysis`) in both Blade and JS renderers to enable clean wrapping/tooltips.
- `resources/views/components/test-dashboard/questions/pool-table.blade.php`: Added inline `table-layout: fixed; min-width: 1000px;` and humanized/truncated Domain cells.
- `resources/js/test/dashboard/components/questions.js`: Dynamic JS cell update to call `humanizeUnderscores()` and apply `block truncate` styles.
- **Build**: Vite compile success.
- **Changes**: Bulletproof table rendering. No overflow/horizontal page scroll. Seamless inner card scrolling on small viewports.


## [2026-05-27 00:59] - UI: Convert Media Management List to Premium Tailwind Grid

- **Topic**: Convert legacy Bootstrap classes in dynamic media list generator to Tailwind.
- **Summary**: Replace Bootstrap classes like `col-md-3`, `col-6`, `position-relative`, `d-flex`, `align-items-center`, `justify-content-center`, `btn-danger` with modern premium Tailwind utilities inside `editors.js`. Implement custom dark-theme card wrappers and floating corner remove buttons.
- `resources/js/test/dashboard/ui/editors.js`: Refactor dynamic list template nodes in `refreshEditMediaList()`.
- **Build**: Vite compile success.
- **Changes**: Media management cards align correctly in grid slots with correctly positioned floating action buttons.

## [2026-05-27 00:54] - Bug Fix: Safe Modal Unblocking & Pointer Events

- **Topic**: Prevent interaction lockout inside edit question modal.
- **Summary**: Wrap editor initialization in a `try...catch...finally` block. Apply `pointer-events: none` and `display: none` inside the `finally` block to guarantee the overlay always successfully unblocks click/scroll events even if EasyMDE or preview rendering throws an error. Enable `pointer-events: auto` only during active loads.
- `resources/js/test/dashboard/index.js`: Safe overlay removal with strict `pointer-events` restoration.
- **Build**: Vite compile success.
- **Changes**: Perfect interaction and scrollability restored instantly after loader fades.

## [2026-05-27 00:50] - Perf: 60fps Modal Entry & Edit Loading Overlay

- **Topic**: Optimize edit question modal opening transition frame rate.
- **Summary**: Defer heavy editor instantiation and markdown compilation by 250ms (waiting for Alpine modal transition to finish). Create a custom absolute loader overlay inside the modal to indicate editor initialization, preventing layout thrashing and animation lag.
- `resources/views/components/test-dashboard/modals/edit-question.blade.php`: Add relative wrapper with premium loading spinner.
- `resources/js/test/dashboard/index.js`: Defer editor initialization by 250ms and smoothly fade out loader overlay.
- **Build**: Vite compile success.
- **Changes**: Silky smooth 60fps modal slide-in with loading indicator.

## [2026-05-27 00:47] - UI: Increase Question Edit Modal Width to 80%

- **Topic**: Resize the edit question dialog modal width.
- **Summary**: Add an `80%` max-width option mapped to Tailwind `sm:max-w-[80vw]` in `modal.blade.php`. Set `max-width="80%"` on `edit-question.blade.php` modal declaration.
- `resources/views/components/ui/modal.blade.php`: Add `80%` max-width mapping class.
- `resources/views/components/test-dashboard/modals/edit-question.blade.php`: Change `max-width` attribute to `80%`.
- **Build**: Vite compile success.
- **Changes**: Question edit modal takes up 80% of screen width.

## [2026-05-27 00:43] - Feat: Module Search by Test/Section Name & Loader Offset

- **Topic**: Modules search criteria and loading overlay position layout.
- **Summary**: Update `modules.js` to search by `test_title`, `test_name`, `sec_name`, `sec_type` in addition to keys/IDs/difficulty. Update `showTableLoader` in `helpers.js` to measure and offset the loader overlay below the header and above the footer so search inputs remain visible and clickable.
- `resources/js/test/dashboard/components/modules.js`: Update `initModulesSearch()` filter logic.
- `resources/js/test/dashboard/utils/helpers.js`: Calculate header and footer offsets dynamically inside `showTableLoader()`.
- **Build**: Vite compile success.
- **Changes**: Search modules by associated test or section; loading overlay no longer blocks table headers and footers.

## [2026-05-27 00:39] - Feat: Section Search by Test Name

- **Topic**: Section table search criteria.
- **Summary**: Replace simple 'name' field filter with a custom function filter in `sections.js` that checks both `name` (Section Name) and `test_title` (Test Title) case-insensitively.
- `resources/js/test/dashboard/components/sections.js`: Update `sectionsTableSearch` listener.
- **Build**: Vite compile.
- **Changes**: Sections table can search by both section name and test title.

## [2026-05-27 00:35] - UI: Vertically Center Tabulator Cells

- **Topic**: Align cell contents vertically in tests and sections tables.
- **Summary**: Add standard `display: inline-flex !important` + `align-items: center !important` to `.dark-theme-dashboard .tabulator-cell` in CSS. Vertically center cell contents.
- `resources/css/test-dashboard-admin.css`: Modify `.tabulator-cell` properties.
- **Build**: Vite compile.
- **Changes**: Tabulator cells vertically aligned.

## [2026-05-26 23:05] - Feat: Limit Tables 30 Items & Lazy Load + Loaders

- **Topic**: Limit table rows, pagination, custom loaders.
- **Summary**: GPU glassmorphic loaders in `app.css`. `relative` + ID in Blade (`tests-tab`, `sections-tab`, `modules-tab`, `pool-table`). Paginate 30 items BE/FE. `showTableLoader`/`hideTableLoader` in `helpers.js`. Tabulator size 30, trigger on `pageChanged`, debounced search. Client-side pagination + footer in `modules.js`. Question fetch 400ms min spinner in `index.js`. Vite compile.
- `resources/css/app.css`: CSS `.table-loading-overlay` + spin animation.
- `resources/views/test-dashboard.blade.php`: `QUESTIONS_PER_PAGE` = 30.
- `resources/views/components/test-dashboard/tests-tab.blade.php`, `sections-tab.blade.php`, `modules-tab.blade.php`: `relative` wrapper, pagination.
- `resources/views/components/test-dashboard/questions/pool-table.blade.php`: `relative`, ID, fixed width `w-[280px]` on filter.
- `app/Http/Controllers/TestDashboardController.php`: `QUESTIONS_TABLE_PER_PAGE` = 30.
- `resources/js/test/dashboard/core/config.js`: `QUESTIONS_PER_PAGE` = 30.
- `resources/js/test/dashboard/utils/helpers.js`: `showTableLoader`/`hideTableLoader` overlays.
- `resources/js/test/dashboard/components/tests.js`, `sections.js`: Tabulator size 30, page triggers, debounced search.
- `resources/js/test/dashboard/components/modules.js`: Client pagination (30), footer UI, debounced loaders.
- `resources/js/test/dashboard/components/questions.js`: Page size query = 30.
- `resources/js/test/dashboard/index.js`: Overlays in `refreshQuestionsTableOnly` (min 400ms load).
- **Build**: `npm run build`.
- **Changes**: Tables limit 30 items. Custom loader overlays on search/page change.

## [2026-05-26 18:20] - Bug Fix: Restore Bulk Question Importing Functionality

- **Topic**: Restore bulk import listeners, dropzones, validation grid, submit.
- **Summary**: Restore missing bulk-import listeners. `initBulkImport()` in `bulk-import.js` bind dropzones, json templates, Tabulator grid, status alerts, multi-format submit (JSON, CSV, ZIP). Swap close button in validation grid to Tailwind `bi-x-lg`. Call `initBulkImport()` in `index.js` on DOM load.
- `resources/views/components/test-dashboard/questions/validation-grid.blade.php`: Replace close button with Tailwind `bi-x-lg`.
- `resources/js/test/dashboard/components/bulk-import.js`: `initBulkImport()`. Restore dropzones, sample loads, previews, Tabulator grid, re-validations, approved rows import, ZIP uploads.
- `resources/js/test/dashboard/index.js`: Call `BulkImport.initBulkImport()` in DOMContentLoaded.
- **Changes**: Bulk import wizard operational (JSON, CSV, ZIP).

## [2026-05-26 18:12] - UI: Enforce Premium Dark Overrides on Tabulator Grids

- **Topic**: Align Tabulator tables to premium slate dark theme.
- **Summary**: Standardize Tabulator skin classes in `test-dashboard-admin.css` with `.dark-theme-dashboard` + `!important`. Prevent CDN `tabulator_bootstrap5.min.css` from overriding dark theme.
- `resources/css/test-dashboard-admin.css`: Prepend `.dark-theme-dashboard` + `!important` to Tabulator skin overrides.
- **Build**: Vite `npm.cmd run build`.
- **Changes**: Tabulator tables blend into premium dark theme.

## [2026-05-26 18:10] - Bug Fix: Restore Tabulator Dynamic Table Renderings on Sidebar Click

- **Topic**: Fix practice tests/sections tables rendering on tab switch.
- **Summary**: Replace Bootstrap `shown.bs.tab` with click listeners on `.sidebar-link`. Add `data-bs-target` to Blade. Fallback queries in `renderActiveTab()` for robustness.
- `resources/views/test-dashboard.blade.php`: Add `data-bs-target` to Alpine buttons. Init `activeTab` from `sessionStorage`.
- `resources/js/test/dashboard/index.js`: Replace `shown.bs.tab` with native click listeners. Update `renderActiveTab()` to use `sessionStorage`.
- **Build**: `npm.cmd run build`.
- **Changes**: Tabulator tables render correctly on load/click.

## [2026-05-26 18:03] - Bug Fix: Migrate JS Element Toggle to Tailwind 'hidden'

- **Topic**: Fix practice tests, sections, builder panels visibility.
- **Summary**: Replace `d-none` with Tailwind `hidden` in JS. Blade use `hidden`, removing `d-none` left elements hidden.
- `resources/js/test/dashboard/components/tests.js`, `sections.js`, `questions.js`, `builder.js`: Replace `d-none` with `hidden`.
- `resources/js/test/dashboard/ui/editors.js`: `showPassage` check `.contains('hidden')`.
- **Build**: `npm.cmd run build`.
- **Changes**: Dynamic tables, sections, author panels show/hide correctly.

## [2026-05-26 17:58] - UI: Match Pool Table Styles on Filter Applied

- **Topic**: Fix question pool table style on filter.
- **Summary**: Replace Bootstrap HTML generator in `questions.js` with Tailwind/dark-theme classes matching `pool-table.blade.php`.
- `resources/js/test/dashboard/components/questions.js`: `renderQuestionsTable` template use `hover:bg-indigo-500/5`, border-slate, custom badges. Match empty state. Restyle pagination to Tailwind dark.
- **Build**: Vite `npm run build`.
- **Changes**: Question table retains dark theme styling on filter.

## [2026-05-26 17:05] - Feat: Premium Slate Dark Dialog Prompt in Quick Wizard

- **Topic**: Native prompt to premium slate dark modal.
- **Summary**: Replace browser `prompt` with `showCustomPrompt`. Redesign alert modal in `helpers.js` to premium slate dark. Style `.custom-alert-input` with deep slate, white text, focus ring. Vite compile.
- `resources/js/test/dashboard/utils/helpers.js`: `getOrCreateAlertModal()` use dark slate background, translucent overlay. Style `.custom-alert-input`. Upgrade buttons.
- `resources/js/test/dashboard/components/wizard.js`: Import `showCustomPrompt`. Replace `prompt()` with `showCustomPrompt()` async call.
- **Build**: Vite `cmd /c npm run build`.
- **Changes**: Browser dialogs eradicated. Custom slate dark input dialog implemented.

## [2026-05-26 16:50] - Perf: 100% Robust 60fps Transitions & Quick Wizard Size Upgrade

- **Topic**: Fix lag in offcanvas/modal, upgrade Quick Wizard size.
- **Summary**: Restore Alpine `x-transition` for offcanvas/modal. Use HSL slate backdrops, GPU Promotion layers, 200ms duration. Scale Quick Wizard to `3xl` width with larger cards.
- `resources/views/components/ui/offcanvas.blade.php`, `modal.blade.php`: Restore Alpine `x-show` + `x-transition`. Snappy `duration-200`, GPU promotion (`will-change: transform`, `transform-gpu`), `bg-slate-950/80`.
- `resources/views/components/test-dashboard/quick-author-wizard.blade.php`: Upgrade width to `3xl`. Expand padding, typography, icon sizes. Larger headings/descriptions.
- **Build**: Vite `cmd /c npm run build`.
- **Changes**: Smooth 200ms transitions. Quick Wizard resized to premium 3xl grid.

## [2026-05-26 15:45] - UI: Stem Snippet Length and Width Increase

- **Topic**: Increase question stem snippet length.
- **Summary**: Increase stem snippet limit 50 -> 120, cell max-width 280px -> 450px in pool table.
- `resources/css/test-dashboard-admin.css`: Add `.max-w-450` utility.
- `resources/views/components/test-dashboard/questions/pool-table.blade.php`: Stem column `max-w-450`, `Str::limit` 120.
- `resources/js/test/dashboard/components/questions.js`: `stem.slice` 120, `max-width: 450px`.
- **Build**: `npm run build`.
- **Changes**: Stem snippet shows more text (450px width).

## [2026-05-26 14:41] - Feat: Removed Attach Existing Question Feature

- **Topic**: Clean redundant Question Bank features.
- **Summary**: Remove "Attach Existing Question from Bank" feature.
- `resources/views/components/test-dashboard/questions-tab.blade.php`: Delete `attach-question` component.
- Deleted `resources/views/components/test-dashboard/questions/attach-question.blade.php`.
- `resources/js/test/dashboard/index.js`: Remove `QUESTIONS_ATTACH_URL`, `setupForm` listener.
- **Changes**: Feature deleted.

## [2026-05-26 14:36] - UI: Clean Stylesheet & Purging of !important Tags

- **Topic**: `test-dashboard-admin.css` cleanup.
- **Summary**: Purge `!important` from `test-dashboard-admin.css`. Bootstrap removal resolved specificity clashes.
- `resources/css/test-dashboard-admin.css`: Remove all `!important`.
- **Build**: CSS bundle size reduced ~56% (28.69 kB -> 12.39 kB).
- **Changes**: `!important` purged.

## [2026-05-26 14:28] - UI: Snappy Tab Changes & Transition Removal

- **Topic**: Remove fade transitions from dashboard tabs.
- **Summary**: Delete `x-transition` from main tabs for instant change.
- `resources/views/components/test-dashboard/*.blade.php`: Remove `x-transition` from tests, sections, modules, questions, builder tabs.
- **Changes**: Tab transitions removed.

## [2026-05-26 14:25] - UI: Dashboard Stylesheet Purge & Root-Level Animation Optimization

- **Topic**: `test-dashboard-admin.css` refactor + optimization.
- **Summary**: Purge ~950 lines dead Bootstrap CSS. Remove global `*` transition blocker. Root-level animation blocking by avoiding dynamic transitions.
- `resources/css/test-dashboard-admin.css`: Delete unoptimized `*` animation blocker. Purge legacy Bootstrap overrides. Streamline skins (Scrollbars, inputs, TomSelect, Tabulator, EasyMDE, Sidebar, dropzones, builder cards).
- **Build**: CSS size reduced ~46% (28.69 kB -> 15.31 kB). Vite asset transformation 4.3x faster.
- **Changes**: Obsolete overrides purged, unoptimized blocker removed.

## [2026-05-26 14:07] - UI: Hybrid Tailwind/CSS Refactor for Admin Dashboard

- **Topic**: Admin dashboard hybrid styling refactor.
- **Summary**: Replace complex Tailwind gradients/shadows in `test-dashboard.blade.php` with raw CSS in `test-dashboard-admin.css`.
- `resources/css/test-dashboard-admin.css`: Implement `.sidebar-logo-box`, `.btn-new-content`, `.btn-refresh-data`, `.dashboard-title-gradient`.
- `resources/views/test-dashboard.blade.php`: Refactor using CSS helper classes. Bind sidebar buttons to `.sidebar-link` with Alpine active states.
- **Build**: `npm run build`.
- **Changes**: Style system migrated to hybrid Tailwind/CSS.

## [2026-05-25 17:25] - UI: Alpine Dropdown Integration & Tailwind Important Clash Fix

- **Topic**: Alpine.js Vite integration + Tailwind important clash.
- **Summary**: Replace Vanilla JS toggler with Alpine directives for Directions/More dropdown in `test.blade.php`. Resolve Tailwind `important` override conflict. Add glassmorphic backdrop dimmer. Purge legacy dropdown CSS.
- `test.blade.php`: Dropdown refactor (`x-data`, `x-show`, `x-cloak`). Add glassmorphic dimmer. Dynamic `:class` force visibility.
- `resources/css/test/test-header.css`: Purge legacy dropdown rules.
- `app.js`: Alpine boot inside fail-safe block.
- `vite.config.js`: Add `test.js` + `test-main.css` entrypoints.
- `app.css`: Add `[x-cloak]` styles.
- **Build**: Vite build success (bundle reduced 46KB).
- **Changes**: Alpine dropdowns resolved, Livewire conflict fixed.

## [2026-05-25 01:14] - UI: Hybrid Styling Refactor for Global App Header

- **Topic**: Hybrid styling implementation.
- **Summary**: Refactor `user-header.blade.php` and global `app.css` to hybrid model.
- `resources/views/components/app/user-header.blade.php`: Tailwind utility for sizing/alignment.
- `resources/css/app.css`: Strip layout rules. Define complex styles (transitions, shadows, transforms).
- **Build**: `npm.cmd run build`.
- **Changes**: Global header refactored to hybrid model.

## [2026-05-25 01:12] - Rules: Add Styling Hybrid Rule to GEMINI.md

- **Topic**: Frontend styling strategy.
- **Summary**: Enforce hybrid model: Raw CSS for complex (transitions, shadows), Tailwind for basic rules.
- `GEMINI.md`: Update Styling guidelines.
- **Changes**: Hybrid Tailwind/CSS rule logged.

## [2026-05-25 01:10] - UI: Fix Global App Header in MyPractice Page

- **Topic**: Broken header layout in practice page.
- **Summary**: Move custom header styles from `home.css` to global `app.css`.
- `resources/css/app.css`: Append header styles.
- **Build**: `npm.cmd run build`.
- **Changes**: Header styles moved to `app.css`.

## [2026-05-25 01:05] - UI: Center Portal Loading Screen

- **Topic**: Loading screen layout.
- **Summary**: Add `flex-direction: column` + `justify-content: center` to `.loading-screen`.
- `resources/css/app.css`: Flex column + center content.
- **Changes**: Loading screen centered.

## [2026-05-24 16:30] - UI: Purge Bootstrap & Migrate Test/Auth to Tailwind

- **Topic**: Tailwind CSS UI migration complete.
- **Summary**: Purge Bootstrap from Test Engine/Auth views. Swap `d-none` to `hidden`.
- `test.blade.php` etc: Refactor `d-flex`/`d-none` to Tailwind.
- `navigation.js`, `ui.js`: Update JS DOM toggles (`d-none` -> `hidden`).
- `home.blade.php`, `auth/*.blade.php`: Swap grid/flex utilities to Tailwind.
- **Changes**: Test Engine/Auth UI Tailwind v4 conversion.

## [2026-05-24 11:50] - Perf: FCP Optimize & Lazy Load

- **Topic**: FCP drop, lazy load heavy CDNs.
- **Summary**: Split CSS, drop FontAwesome, lazy load JS/CSS.
- `app.blade.php` etc: Extract `home.css`/`practice.css`. `@push` to routes.
- `test-dashboard.blade.php`: Drop FontAwesome (128KB). Remove sync CDNs (EasyMDE, KaTeX, etc).
- `helpers.js`: Remap EasyMDE to Bootstrap Icons. Add `loadHeavyDependencies()` with Promise caching.
- `index.js`: `DOMContentLoaded`, `renderActiveTab` async. `await loadHeavyDependencies()` before rendering.
- **Changes**: Lazy load heavy CDNs, drop FontAwesome, split CSS.

## [2026-05-24 02:20] - Perf: Chunk Rendering & Tabulator Virtualization

- **Topic**: Large dataset rendering.
- **Summary**: Add pagination to tests/sections Tabulator. Progressive chunks for modules/questions.
- `tests.js`, `sections.js`: `pagination: true`, `paginationSize: 25`.
- `modules.js`, `questions.js`: `rAF` + `setTimeout` chunks via `insertAdjacentHTML`.
- **Changes**: Progressive rendering for massive tables.

## [2026-05-24 02:10] - Perf: Fix INP & Redundant Tabulator Renders

- **Topic**: High INP on tab switch.
- **Summary**: Wrap `renderActiveTab` in `rAF` + `setTimeout`. Cache data reference to skip redundant renders.
- `index.js`: `rAF` + `setTimeout` for tab switch.
- `tests.js` etc: `currentData` equality guard before `replaceData`/`innerHTML`.
- **Changes**: INP fix, drop redundant layouts.

## [2026-05-24 02:00] - Perf: Dashboard Lazy Load & Script Defer

- **Topic**: JS execution time, layout thrashing.
- **Summary**: Defer scripts, lazy load Tabulator on tab switch, chunk TomSelect init.
- `test-dashboard.blade.php`: Defer script tags.
- `index.js`: Cache payload. `renderActiveTab` on switch. Chunk `initTomSelectBatch`.
- **Changes**: Dashboard lazy loading, unblock main thread.

## [2026-05-23 11:50] - UI: Remove Pretest Spinner Icon

- **Topic**: Remove growing spinner from Pretest badge.
- **Summary**: Remove `spinner-grow` from Blade + JS templates.
- **Changes**: Spinner removed from Pretest badge.

## [2026-05-23 11:30] - UI: Question Bank Columns Center Alignment

- **Topic**: Center key columns in Question Bank table.
- **Summary**: Center align ID, Q. Number, Section, Usage, Difficulty.
- `pool-table.blade.php`, `questions.js`: Add `text-center`, `justify-content-center`.
- **Changes**: Key columns centered.

## [2026-05-23 11:00] - UI: Test Dashboard Performance Optimization

- **Topic**: UI rendering performance.
- **Summary**: Disable transitions, transforms, continuous anims (pulse/ping), heavy blurs globally in Test Dashboard. Keep spinner.
- `test-dashboard-admin.css`: Global reset `transition: none !important`, etc. Re-enable `.animate-spin`. Strip explicit transitions.
- **Changes**: Universal transition/blur blocker.

## [2026-05-23 10:45] - Arch: Clean Architecture & SOLID Refactoring

- **Topic**: SOLID refactoring.
- **Summary**: Clean magic values, MVC bloat, procedural DB updates.
- `Question.php` etc: Static model/calculation constants.
- `TestManagementService.php` [NEW]: Domain service. Structure generator, clones, cascade deletes.
- `FormRequest` classes [NEW]: Isolated validations.
- `BulkQuestionImportService` etc: Decouple from `Request` objects.
- `TestDashboardController.php`: Inject service, use FormRequests.
- **Changes**: SOLID architecture, FormRequest validations, transactions safety.

## [2026-05-23 08:53] - Easy Builder: Live Preview Placeholder & Wizard Step Navigation

- **Topic**: Authoring tools.
- **Summary**: Fix math preview placeholder selector. Step-back navigation in wizard.
- **Changes**: Builder math placeholder fix, wizard step-back.

## [2026-05-23 08:42] - CSS Cascade Layer Overrides & Contrast Fix

- **Topic**: CSS layer priority.
- **Summary**: Wrap overrides in `@layer utilities` to override Tailwind v4.
- **Changes**: Tailwind v4 cascade layer fixes.

## [2026-05-23 02:30] - UI/UX: Fix TomSelect Double Input Bug & Hide Selects

- **Topic**: TomSelect search conflict.
- **Summary**: Hide original selects. Reset search input styles.
- `test-dashboard-admin.css`: Force hide `select.tomselected`. Reset `.ts-control input`.
- **Changes**: Hide selects, reset input styles.

## [2026-05-23 02:25] - UI/UX: High Performance Rendering & Low-End Desktop Lag Fix

- **Topic**: Strip GPU bottlenecks.
- **Summary**: Strip backdrop filters, glowing shadows. Solid backgrounds.
- `test-dashboard-admin.css`: Remove `backdrop-filter: blur(...)`. Use solid backdrops. Replace shadows with borders.
- **Changes**: Solid performant backdrops, simple outlines.

## [2026-05-23 02:20] - UI/UX: Snappy UX & Color Harmony Refinement

- **Topic**: Transition speeds, custom controls.
- **Summary**: Fast transitions, strip transforms, style selects/checks/radios.
- `test-dashboard-admin.css`: 0.08s input transition. Strip hover transforms. Custom check/radio styles. Contrast fix.
- **Changes**: Snappy anims, custom styles.

## [2026-05-23 02:15] - UI/UX: Premium Dark Glass Builder Block & Asset Compile

- **Topic**: Easy Builder Block upgrade.
- **Summary**: Glassmorphic container, amber inputs.
- `builder-block-template.blade.php`: Upgraded to `glass-panel`. Amber switches.
- **Changes**: Glassmorphic block theme.

## [2026-05-23 01:15] - Fix: Test Dashboard Specificity & Utility Clash

- **Topic**: Tailwind CSS utility overrides.
- **Summary**: Force Tailwind utility priority over Bootstrap.
- `app.css`: Add `important` to `@import "tailwindcss"`.
- **Changes**: Tailwind utilities made `!important`.

## [2026-05-23] - Dashboard Dark Theme Complete

- **Topic**: UI aesthetics overhaul.
- **Summary**: Migrate dashboard to premium dark theme.
- **Changes**: Ultra premium admin dark mode.

## [2026-05-22 15:00] - UI/UX: Modals & Wizard Tailwind v4 Conversion

- **Topic**: Bootstrap to Tailwind v4 modal migration.
- **Summary**: Rewrite dialogs/wizard with premium Tailwind grid/flex.
- **Changes**: Modals and wizard custom CSS upgraded.

## [2026-05-22 14:15] - UI/UX: Dashboard Polish + Tabulator

- **Topic**: Dashboard Grid Data upgrade.
- **Summary**: Integrate Tabulator. Keyboard bindings. ZIP progress logic.
- **Changes**: Upgrade grids to Tabulator, secure auto-save UI.

## [2026-05-22 02:37] - Fix: Markdown Parse + Image + List Reset

- **Topic**: EasyMDE sync, image centering, list reset.
- **Summary**: Sync EasyMDE on submit. Fix space regex in marked. Restore native `list-style-type`.
- **Changes**: Fix markdown spacing, restore native lists.

## [2026-05-22 02:16] - Fix: Question Edit Submission & EasyMDE Rendering

- **Topic**: Question Modal PUT bugs.
- **Summary**: Secure choice booleans. Double-refresh EasyMDE on modal shown.
- **Changes**: PUT edit submission fix, EasyMDE modal draw.

## [2026-05-22 01:50] - Fix: Edit Question Form & Live Preview Compiler

- **Topic**: Live preview compilers, fonts.
- **Summary**: Fix marked parser v12. Load Noto Serif + KaTeX.
- **Changes**: marked upgrade, load Noto + KaTeX assets.

## [2026-05-22 01:05] - Feat: Premium EasyMDE LaTeX and Live Previews

- **Topic**: LaTeX in EasyMDE.
- **Summary**: Double `$$` LaTeX syntax. Widescreen split-screen edit modal.
- **Changes**: LaTeX formula parsing, split-screen edit.

## [2026-05-22 00:53] - Feat: Adaptive Routing Fail-safe & UI Fix

- **Topic**: Adaptive engine fallback.
- **Summary**: Handle missing Module 2 paths. Fallback routing + warning alerts.
- **Changes**: Safe routing fallback, warning modals.

## [2026-05-21 16:55] - Feat: Authoring UX Workflow Upgrade (Wizard + Clones)

- **Topic**: UX workflow improvement.
- **Summary**: Quick Wizard modal. Template cloning. Sticky siblings breadcrumbs.
- **Changes**: Builder wizard, template clones, siblings navigator.

## [2026-05-21 14:33] - Feat: Test Dashboard UI/UX & Import Upgrade

- **Topic**: CSV validator, Split-screen easy builder.
- **Summary**: Tolerant CSV validation. Tabulator validations. 3-col split-pane workspace. MCQ/SPR toggles.
- **Changes**: Bulk import validator, 3-col split pane easy builder.

## [2026-05-20 16:15] - Fix: Workspace Button Logic

- **Topic**: Tree navigation hooks.
- **Summary**: Re-bind listeners for delete, quick-add, SortableJS sync.
- **Changes**: Workspace button bindings fixed.

## [2026-05-20 15:30] - Feat: Unified Workspace Dashboard

- **Topic**: Admin dashboard refactoring.
- **Summary**: Merge CRUD tabs into Unified Workspace. 2-col sidebar tree navigator + Canvas.
- **Changes**: Notion-style dashboard with sidebar Tree Navigator.

## [2026-05-20 14:46] - Feat: Split-Pane Doc Test Builder

- **Topic**: Figma-style canvas layout.
- **Summary**: Split flexbox pane, 1-click Bluebook structures, SortableJS drag-and-drop.
- **Changes**: Widescreen canvas split-pane, SortableJS ordering.

## [2026-05-20 13:35] - Sync: Dynamic Dropdown & State

- **Topic**: TomSelect filters rebuild.
- **Summary**: Background AJAX TomSelect refresh, preserve search/selected states.
- **Changes**: TomSelect dynamic reload + preservation.

## [2026-05-20 12:55] - UX: Restyle Bulk Import

- **Topic**: Import cards styling.
- **Summary**: Success gradient header, clean labels, badges.
- **Changes**: Bulk import UI aligned.

## [2026-05-20 11:17] - Bug Fix: Seeder, Pivot, Media, Link

- **Topic**: Seed errors, duplicates, media URLs.
- **Summary**: `syncWithoutDetaching` modules, `asset()` helper media URLs, `linkModuleSection` auto-creation.
- **Changes**: Database seeds fixed, duplicate links blocked, dynamic section creator.

## [2026-05-19 18:49] - Score Details: Normalization, Comparatives, LaTeX

- **Topic**: Practice history page.
- **Summary**: Single layout, academic domain keys, compare choice answers, KaTeX modals.
- **Changes**: Score details page normalized with KaTeX reviews.

## [2026-05-19 16:30] - Documentation Sync

- **Topic**: Project status tracking.
- **Summary**: Sync `GEMINI.md` + `feature_memory.md`.
- **Changes**: Knowledge Base sync complete.

## [2026-05-19 16:10] - Score Details Refactor

- **Topic**: User test stats page.
- **Summary**: Decouple analytics from grids, custom choice cards, KaTeX modals.
- **Changes**: Detailed practice test analysis page.

## [2026-05-19 14:35] - Seeded Completed Practice Data

- **Topic**: Database seeding.
- **Summary**: Mock score entries and complete test taker data.
- **Changes**: Practice completion history seeds.

## [2026-05-19 13:40] - Fix Dropdown Clipping

- **Topic**: CSS overflows.
- **Summary**: Card overflow visible to prevent selector clipping.
- **Changes**: Card clipping resolved.

## [2026-05-19 13:35] - High Z-Index & Overflow Fix

- **Topic**: TomSelect stack orders.
- **Summary**: TomSelect z-index 9999, wrapper container overflows.
- **Changes**: Select layer visibility secured.

## [2026-05-19 13:30] - Reposition Attach Q Card

- **Topic**: UI balance.
- **Summary**: Move "Attach Existing Question" card below Bulk Import.
- **Changes**: Question Bank layouts repositioned.

## [2026-05-19 13:25] - Drag-and-Drop Zones

- **Topic**: Upload UI.
- **Summary**: Zip/CSV/JSON drag-and-drop zones.
- **Changes**: Premium upload zones.

## [2026-05-19 13:20] - Question Dashboard UI

- **Topic**: Q Bank visual polish.
- **Summary**: Slate-to-indigo headers, domain badges, pretest active pulsers, difficulty indicators.
- **Changes**: Question Bank list layout upgraded.

## [2026-05-19 13:15] - Home Page Dropdown Fix

- **Topic**: JS scopes.
- **Summary**: Bind profile dropdown init globally.
- **Changes**: Header profile toggler fix.

## [2026-05-19 13:05] - routes/web.php:181 Null Pointer Fix

- **Topic**: Routing crashes.
- **Summary**: Null-safe check module section relation. Seed module paths.
- **Changes**: Section ordering routes null pointer fixed.

## [2026-05-19 12:50] - Collection Property Exception Fix

- **Topic**: Eloquent relationships.
- **Summary**: `getSectionAttribute` accessor for module model.
- **Changes**: Module section property crash fixed.

## [2026-05-19 12:45] - Custom Alert & Confirm Modal

- **Topic**: Modal dialogs.
- **Summary**: Replace browser alert/confirm with async glassmorphic modals.
- **Changes**: Universal beautiful alerts & confirms.

## [2026-05-19 12:35] - Auto Section Gen

- **Topic**: Creation shortcuts.
- **Summary**: Auto-create Section if Section ID not specified in Module creation.
- **Changes**: Dynamic Section generation.

## [2026-05-19 12:20] - Reusable Module Arch

- **Topic**: Junction tables structure.
- **Summary**: `section_modules` junction. Decouple Module from Section. Stand-alone pools.
- **Changes**: Reusable Modules architecture implemented.

## [2026-05-19 11:27] - March 2026 DSAT Alignment

- **Topic**: Score compliance.
- **Summary**: Force standardized 3PL parameters (a, b, c) at model boot.
- **Changes**: March 2026 IRT Parameters secured.

## [2026-05-19 11:15] - Noto Serif Typography

- **Topic**: Exam layout parity.
- **Summary**: Noto Serif for exam passages/stems. Inter for app chrome.
- **Changes**: Typography alignment complete.

## [2026-05-19 11:12] - Loading Performance

- **Topic**: GPU drawing.
- **Summary**: `will-change: transform` + `translateZ(0)` for GPU layers. Strip blurs.
- **Changes**: 60fps glassmorphic drawings.

## [2026-05-19 11:04] - Seamless Portal Transition

- **Topic**: Page loading UX.
- **Summary**: Global glassmorphic transition loading overlay. Mask unstyled content.
- **Changes**: Seamless portal-to-test transition.

## [2026-05-19 11:00] - Premium Loading Screen

- **Topic**: Transition steppers.
- **Summary**: FOUC blocker, adaptive delay loader overlays.
- **Changes**: Glassmorphic loader screen.

## [2026-05-19 10:52] - Fullscreen & GEMINI.md Fix

- **Topic**: Exam security mock.
- **Summary**: Fullscreen on first user interaction.
- **Changes**: Auto fullscreen engine.
