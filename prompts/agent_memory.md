# Agent Memory Log

> **RULE:** ALWAYS add new entries to the **TOP** of this log (immediately below this header block), so the newest items are seen first.

## [2026-05-24 11:50] - Perf: FCP Optimize & Lazy Load
*   **Topic**: Perf drop FCP, lazy load heavy CDNs.
*   **Summary**: Split CSS, drop FontAwesome, lazy load JS/CSS.
*   `app.blade.php`, `home.blade.php`, `practice.blade.php`:
    *   Extract `home.css`, `practice.css` from global Vite bundle. `@push` to specific routes.
*   `test-dashboard.blade.php`:
    *   Drop FontAwesome CDN (save 128KB).
    *   Remove synchronous CDNs (EasyMDE, KaTeX, Tabulator, TomSelect, marked).
*   `helpers.js`:
    *   Remap EasyMDE defaults from `fa fa-*` to `bi bi-*` (Bootstrap Icons).
    *   Add `loadHeavyDependencies()` with Promise caching for async script/style injection.
*   `index.js`:
    *   Make `DOMContentLoaded`, `renderActiveTab` async.
    *   `await loadHeavyDependencies()` before rendering tabs.
*   **Build**: Vite `npm run build` success.
*   **Changes**: `app.blade.php`, `test-dashboard.blade.php`, `helpers.js`, `index.js` [PERF] Lazy load heavy CDNs, drop FontAwesome, split CSS.

## [2026-05-24 02:20] - Perf: Chunk Rendering & Tabulator Virtualization
*   **Topic**: Huge dataset rendering bottlenecks.
*   **Summary**: Added pagination to tests/sections Tabulator. Rewrote modules/questions rendering into progressive chunks.
*   	ests.js, sections.js: Add pagination: true and paginationSize: 25 to Tabulator options.
*   modules.js, questions.js: Extract innerHTML loops into equestAnimationFrame + setTimeout chunks appending via insertAdjacentHTML.
*   **Build**: Vite 
pm run build success.
*   **Changes**: 	ests.js, sections.js, modules.js, questions.js [PERF] Progressive rendering for massive tables.

## [2026-05-24 02:10] - Perf: Fix INP & Redundant Tabulator Renders
*   **Topic**: High INP on tab switch, main thread blocking.
*   **Summary**: Wrap renderActiveTab in rAF + setTimeout. Cache current data reference to skip redundant table renders.
*   index.js: 
equestAnimationFrame + setTimeout for tab switch listener.
*   	ests.js, sections.js, modules.js, questions.js: Add currentData strict equality guard before 
eplaceData / innerHTML.
*   **Build**: Vite 
pm run build success.
*   **Changes**: index.js, components [PERF] INP fix, drop redundant tabulator layouts.
*   index.js: equestAnimationFrame + setTimeout for tab switch listener.
*   	ests.js, sections.js, modules.js, questions.js: Add currentData strict equality guard before eplaceData / innerHTML.
*   **Build**: Vite 
pm run build success.
*   **Changes**: index.js, components [PERF] INP fix, drop redundant tabulator layouts.

## [2026-05-24 02:00] - Perf: Dashboard Lazy Load & Script Defer
*   **Topic**: JS execution time, layout thrashing fix.
*   **Summary**: Defer scripts, lazy load Tabulator tables on tab switch, chunk TomSelect init.
*   	est-dashboard.blade.php: Defer script tags (marked, katex, tom-select, easymde, tabulator).
*   index.js: Cache payload. Add 
enderActiveTab. Bind shown.bs.tab. Chunk initTomSelectBatch with setTimeout.
*   **Build**: Vite 
pm run build success.
*   **Changes**: index.js, 	est-dashboard.blade.php [PERF] Dashboard lazy loading, unblock main thread.

## [2026-05-23 11:50] - UI: Remove Pretest Spinner Icon
*   **Topic**: Remove the growing spinner icon from the Pretest status badge.
*   **Summary**: Removed spinner-grow HTML elements from both static Blade view and dynamic JS table row templates.
*   `pool-table.blade.php`: Removed spinner-grow span from the Pretest badge definition.
*   `questions.js`: Removed spinner-grow span from dynamic usage badge template inside `renderQuestionsTable`.
*   **Build**: Vite `npm run build` success.
*   **Changes**: `pool-table.blade.php`, `questions.js` [UI] Removed spinner icon from Pretest badge.

## [2026-05-23 11:30] - UI: Question Bank Columns Center Alignment
*   **Topic**: Center alignment of key columns in Question Bank table.
*   **Summary**: Center align ID, Q. Number, Section, Usage, and Difficulty.
*   `pool-table.blade.php`:
    *   Add `text-center` class to `<th>` and `<td>` for columns: Id, Q. Number, Section, Usage, Difficulty.
    *   Add `justify-content-center` to flex layout in Q. Number table cell.
*   `questions.js`:
    *   Add `text-center` class to cell templates in `renderQuestionsTable`.
    *   Add `justify-content-center` to Q. Number flex wrap template.
*   **Build**: Vite `npm run build` success.
*   **Test**: Automated PHPUnit suite `php artisan test` passed (47/47).
*   **Changes**: `pool-table.blade.php`, `questions.js` [UI] Centered key columns in Question Bank table.

## [2026-05-23 11:00] - UI: Test Dashboard Performance Optimization
*   **Topic**: UI rendering performance optimizations.
*   **Summary**: Disable and strip transitions, transforms, continuous animations (pulse/ping), and heavy backdrop blurs globally under the Test Dashboard page, while keeping spinner operational.
*   `test-dashboard-admin.css`:
    *   Prepend global reset selector: `.dark-theme-dashboard *, .modal-content *, .offcanvas *` with `transition: none !important; transform: none !important; animation: none !important; backdrop-filter: none !important; -webkit-backdrop-filter: none !important;`.
    *   Explicitly re-enable spinner animation: `.animate-spin { animation: spin 1s linear infinite !important; }`.
    *   Strip explicit transition lines inside individual selectors for scrollbars, inputs, TomSelect, builder-block, Tabulator rows, sidebar links, checkboxes, radios, buttons, and EasyMDE.
*   **Build**: Vite `npm run build` success.
*   **Test**: Automated PHPUnit suite `php artisan test` passed (47/47).
*   **Changes**: `test-dashboard-admin.css` [PERF] Universal transition, transform, and backdrop-filter blocker, stripped explicit animations/transitions. HTML/Blade utility files cleared of transition classes and heavy backdrop-blur utility classes.

## [2026-05-23 10:45] - Arch: Clean Architecture & SOLID Refactoring
*   **Topic**: SOLID structural refactoring.
*   **Summary**: Clean up magic values, MVC controller bloat, procedural DB updates, tightly bound Request payloads.
*   `Question.php`, `Section.php`, `Module.php`, `SatScoringService.php`: static model and calculation bounds constants.
*   `TestManagementService.php` [NEW]: domain service. structure generator, clones, cascade deletions.
*   `StoreTestRequest.php`, `UpdateTestRequest.php`, `StoreSectionRequest.php`, `StoreModuleRequest.php`, `LinkModuleRequest.php`, `UpdateQuestionRequest.php` (SPR arrays clean), `AttachQuestionRequest.php`, `SubmitModuleRequest.php` [NEW]: FormRequests isolated validations.
*   `BulkQuestionCsvImportService.php`, `BulkQuestionImportService.php`: decouple import methods from HTTP `Request` objects, generic inputs.
*   `TestDashboardController.php`: inject service, use FormRequests, remove procedural structures code.
*   `TestTakingController.php`: DB transactions in submitModule writes, use FormRequest.
*   `TestManagementServiceTest.php` [NEW]: automation feature test. All 47 tests passed.
*   **Changes**: Controller decoupled SOLID architecture, isolated FormRequest validations, generic import services, database transactions safety.

## [2026-05-23 08:53] - Easy Builder: Live Preview Placeholder & Wizard Step Navigation
*   **Topic**: Authoring tools.
*   **Summary**: Fix dynamic math preview placeholder toggle selector. Step-back navigation cards wizard.
*   `builder.js`, `quick-author-wizard.blade.php`, `wizard.js`: back buttons selectors, dynamic builder block placeholder draws.
*   **Changes**: Builder math placeholder toggler, wizard step-back.

## [2026-05-23 08:42] - CSS Cascade Layer Overrides & Contrast Fix
*   **Topic**: CSS layer priority override.
*   **Summary**: Wrap custom overrides inside `@layer utilities` to take priority over Tailwind v4 compilation utility layers.
*   `test-dashboard-admin.css`: wrap `.d-none`, text slates, label overrides inside cascade layer block.
*   **Changes**: Tailwind v4 cascade layers override fixes.

## [2026-05-23 02:30] - UI/UX: Fix TomSelect Double Input Bug & Hide Selects
*   **Topic**: UI/UX TomSelect search input original select conflict.
*   **Summary**: Hide original selects. Reset search input styles.
*   `test-dashboard-admin.css`:
    *   Force hide `select.tomselected` + `select.ts-hidden-accessible`. Prevent double inputs.
    *   Reset `.ts-control input` border/shadow/radius/padding. Remove second visible box.
*   **Build**: Vite `npm run build` success.
*   **Changes**: `test-dashboard-admin.css` [BUGFIX] Hide selects, reset input styles.

## [2026-05-23 02:25] - UI/UX: High Performance Rendering & Low-End Desktop Lag Fix
*   **Topic**: UI/UX strip GPU bottlenecks.
*   **Summary**: Strip backdrop filters, glowing shadows. Replace with solid backgrounds.
*   `test-dashboard-admin.css`:
    *   Remove `backdrop-filter: blur(...)` globally.
    *   Use solid `#111827`, `#0f172a`, `#1e293b` backdrops.
    *   Replace box-shadow blooms with simple borders + outline outlines.
*   **Build**: Vite `npm run build` success.
*   **Changes**: `test-dashboard-admin.css` [PERF] Solid performant backdrops, simplify outlines.

## [2026-05-23 02:20] - UI/UX: Snappy UX & Color Harmony Refinement
*   **Topic**: UI/UX transition speeds, custom controls.
*   **Summary**: Fast transitions, strip transforms, style select tags, checks/radios, text contrast.
*   `test-dashboard-admin.css`:
    *   0.08s input transition. Strip hover transforms.
    *   TomSelect tags: HSL slate-indigo.
    *   Checkbox/Radio: Custom deep slate fills, violet checks.
    *   Contrast: Brighten slate text, clear headers.
*   **Changes**: `test-dashboard-admin.css` [REFACTOR] Snappy anims, custom check/radio styles.

## [2026-05-23 02:15] - UI/UX: Premium Dark Glass Builder Block & Asset Compile
*   **Topic**: Easy Builder Block style upgrade.
*   **Summary**: Glassmorphic container with amber inputs. Vite asset compile.
*   `builder-block-template.blade.php`: Upgraded white card to `glass-panel`. Amber switches, gold outline.
*   **Changes**: `builder-block-template.blade.php` [REFACTOR] Glassmorphic block theme.

## [2026-05-23 01:15] - Fix: Test Dashboard Specificity & Utility Clash
*   **Topic**: Tailwind CSS utility overrides.
*   **Summary**: Force Tailwind utility priority over Bootstrap classes.
*   `app.css`: Add `important` option to `@import "tailwindcss"`.
*   **Changes**: `app.css` [FIX] Tailwind utilities made `!important`.

## [2026-05-23] - Dashboard Dark Theme Complete
*   **Topic**: UI aesthetics overhaul.
*   **Summary**: Migrate admin dashboard to ultra-premium dark theme. Spec CSS utilities.
*   `test-dashboard-admin.css`, `test-dashboard.blade.php`: custom tomselect dropdown, card backdrops, modals dark schemes.
*   **Changes**: Ultra premium admin dark mode.

## [2026-05-22 15:00] - UI/UX: Modals & Wizard Tailwind v4 Conversion
*   **Topic**: Bootstrap to Tailwind v4 modal migration.
*   **Summary**: Rewrite dialogs/wizard with premium Tailwind CSS grid/flex structures.
*   `modals.blade.php`, `quick-author-wizard.blade.php`: Convert structures to custom Tailwind. Keep IDs/logic.
*   **Changes**: Modals and wizard custom CSS upgraded to v4.

## [2026-05-22 14:15] - UI/UX: Dashboard Polish + Tabulator
*   **Topic**: Dashboard Grid Data upgrade.
*   **Summary**: Integrate Tabulator spreadsheets. Keyboard bindings. ZIP progress logic.
*   `test-dashboard.js`, `tests-tab.blade.php`, `sections-tab.blade.php`: Tabulator grids, auto-saves, progress bars, empty states.
*   **Changes**: Upgrade grids to Tabulator, secure auto-save UI.

## [2026-05-22 02:37] - Fix: Markdown Parse + Image + List Reset
*   **Topic**: EasyMDE markdown sync, image centering, lists resets.
*   **Summary**: Sync EasyMDE on submit. Fix space regex in marked compiler. Override list overrides.
*   `test-dashboard.js`, `test-dashboard.blade.php`, `test-main.css`: marked.js line breaks, markdown bold spacing, restore native `list-style-type` for `<ol>`/`<ul>`.
*   **Changes**: Fix markdown compiled spacing, restore native lists.

## [2026-05-22 02:16] - Fix: Question Edit Submission & EasyMDE Rendering
*   **Topic**: Question Modal form submission PUT bugs.
*   **Summary**: Secure choice booleans. Double-refresh EasyMDE on modal shown hook to fix white-outs.
*   `test-dashboard.js`, `modals.blade.php`: Bind form submission PUT, populate choice arrays, double-render EasyMDE.
*   **Changes**: Put edit submission fix, correct EasyMDE modal draw.

## [2026-05-22 01:50] - Fix: Edit Question Form & Live Preview Compiler
*   **Topic**: Live preview compilers and fonts.
*   **Summary**: Fix marked.js parser syntax to v12. Load Noto Serif + KaTeX styles.
*   `test-dashboard.js`, `test-dashboard.blade.php`: marked.parse(), load fonts, robust form data selector.
*   **Changes**: Compile script marked upgrade, load Noto + KaTeX assets.

## [2026-05-22 01:05] - Feat: Premium EasyMDE LaTeX and Live Previews
*   **Topic**: LaTeX formula integration inside EasyMDE editors.
*   **Summary**: Double `$$` LaTeX syntax. Widescreen split-screen edit question modal.
*   `modals.blade.php`, `test-dashboard.js`: Split-pane edit modal, real-time preview sync.
*   **Changes**: LaTeX mathematical formulas parsing, split-screen edit interface.

## [2026-05-22 00:53] - Feat: Adaptive Routing Fail-safe & UI Fix
*   **Topic**: Adaptive engine fallback.
*   **Summary**: Handle missing Module 2 paths gracefully. Fallback routing + warning countdown alerts.
*   `TestTakingController.php`, `navigation.js`, `ui.js`: Routing check, countdown notifications, total duration seeder recalculations.
*   **Changes**: Safe routing fallback checks, beautiful warning modals.

## [2026-05-21 16:55] - Feat: Authoring UX Workflow Upgrade (Wizard + Clones)
*   **Topic**: UX workflow improvement.
*   **Summary**: Quick Wizard modal. Template cloning. Sticky siblings breadcrumbs.
*   `TestDashboardController.php`, `quick-author-wizard.blade.php`, `test-dashboard.js`: Wizard structural generation, duplicate hierarchies, breadcrumb jump selectors.
*   **Changes**: Test structure builder wizard, template clones, siblings jump navigator.

## [2026-05-21 14:33] - Feat: Test Dashboard UI/UX & Import Upgrade
*   **Topic**: Spreadsheet csv validator, Split-screen easy builder.
*   **Summary**: Tolerant CSV validation. Tabulator spreadsheet validations. 3-col split-pane workspace. MCQ/SPR toggles.
*   `BulkQuestionCsvImportService.php`, `test-dashboard.js`, `builder-tab.blade.php`, `questions-tab.blade.php`: spreadsheet preview inline corrections, live math compiler, builder sidebar navigation.
*   **Changes**: Bulk import spreadsheet validator, 3-col split pane easy builder workspace.

## [2026-05-20 16:15] - Fix: Workspace Button Logic
*   **Topic**: Tree navigation canvas button hooks.
*   **Summary**: Re-bind listeners for delete actions, quick-add cards, SortableJS ordering sync.
*   `test-builder.js`: Bind quick actions, spinners, reordering.
*   **Changes**: Workspace button bindings fixed.

## [2026-05-20 15:30] - Feat: Unified Workspace Dashboard
*   **Topic**: Admin dashboard refactoring.
*   **Summary**: Merge split CRUD tabs into Notion-style Unified Workspace. 2-column sidebar tree navigator + Canvas.
*   `tests-tab.blade.php`, `test-builder.js`: Tree-canvas re-rendering, SortableJS, quick module attachments.
*   **Changes**: Notion-style admin dashboard with sidebar Tree Navigator.

## [2026-05-20 14:46] - Feat: Split-Pane Doc Test Builder
*   **Topic**: Figma-style canvas layout.
*   **Summary**: Split flexbox pane layout, 1-click Bluebook structures, SortableJS drag-and-drop.
*   `vite.config.js`, `tests-tab.blade.php`, `test-builder.js`, `TestDashboardController.php`: structure ordering AJAX persistence.
*   **Changes**: Widescreen canvas split-pane, SortableJS structure ordering.

## [2026-05-20 13:35] - Sync: Dynamic Dropdown & State
*   **Topic**: TomSelect filters rebuild.
*   **Summary**: Background AJAX TomSelect refreshes while preserving search inputs and selected item states.
*   `test-dashboard.js`: tomselect rebuilders, capture dropdown states.
*   **Changes**: tomselect dynamic reload and preservation.

## [2026-05-20 12:55] - UX: Restyle Bulk Import
*   **Topic**: Import cards styling.
*   **Summary**: Success gradient header, clean labels, badge indicators.
*   `questions-tab.blade.php`: Header styles.
*   **Changes**: Bulk import UI aligned.

## [2026-05-20 11:17] - Bug Fix: Seeder, Pivot, Media, Link
*   **Topic**: Seed errors, duplicates, media urls.
*   **Summary**: Seeder name, syncWithoutDetaching modules, asset() helper media URLs, linkModuleSection auto-creation.
*   `UserSeeder.php`, `TestDashboardController.php`, `MediaController.php`, `BulkQuestionImportService.php`: pivot dedups, null checks.
*   **Changes**: Database seed errors fixed, duplicate links blocked, dynamic section creator.

## [2026-05-19 18:49] - Score Details: Normalization, Comparatives, LaTeX
*   **Topic**: Practice history page.
*   **Summary**: Single layout, academic domain keys, compare choice answers, KaTeX modals.
*   `score-details.blade.php`, `app.js`: Section data filters, domain labels, comparison tables.
*   **Changes**: Score details page normalized with KaTeX reviews.

## [2026-05-19 16:30] - Documentation Sync
*   **Topic**: project status tracking.
*   **Summary**: researchers sync `GEMINI.md` + `feature_memory.md`.
*   **Changes**: Knowledge Base sync complete.

## [2026-05-19 16:10] - Score Details Refactor
*   **Topic**: User test stats page.
*   **Summary**: decouple analytics from dashboard grids, custom choice cards KaTeX modals.
*   `UserTest.php`, `PracticeController.php`, `score-details.blade.php`: score analytics models & views.
*   **Changes**: Detailed practice test analysis page.

## [2026-05-19 14:35] - Seeded Completed Practice Data
*   **Topic**: Database seeding.
*   **Summary**: Mock score entries and complete test taker data.
*   `DatabaseSeeder.php`: seed completed user tests.
*   **Changes**: Practice completion history seeds.

## [2026-05-19 13:40] - Fix Dropdown Clipping
*   **Topic**: CSS overflows.
*   **Summary**: Card overflow visible to prevent selector clipping.
*   `questions-tab.blade.php`: Card overflow visibility rules.
*   **Changes**: Card clipping resolved.

## [2026-05-19 13:35] - High Z-Index & Overflow Fix
*   **Topic**: tomselect stack orders.
*   **Summary**: tomselect dropdown z-index 9999, wrapper container overflows.
*   `test-dashboard.blade.php`, `questions-tab.blade.php`: z-index outline overrides.
*   **Changes**: Select layer visibility secured.

## [2026-05-19 13:30] - Reposition Attach Q Card
*   **Topic**: UI balance.
*   **Summary**: Move "Attach Existing Question" card below Bulk Import.
*   `questions-tab.blade.php`: cards ordering.
*   **Changes**: Question Bank layouts repositioned.

## [2026-05-19 13:25] - Drag-and-Drop Zones
*   **Topic**: Upload UI.
*   **Summary**: Zip/CSV/JSON drag-and-drop zones.
*   `questions-tab.blade.php`, `test-dashboard.js`: interactive file drops.
*   **Changes**: Premium upload zones.

## [2026-05-19 13:20] - Question Dashboard UI
*   **Topic**: Q Bank visual polish.
*   **Summary**: Slate-to-indigo headers, domain badges, pretest active pulsers, difficulty indicators.
*   `questions-tab.blade.php`: badges & badges CSS.
*   **Changes**: Question Bank list layout upgraded.

## [2026-05-19 13:15] - Home Page Dropdown Fix
*   **Topic**: JS scopes.
*   **Summary**: Bind profile dropdown init script globally to window.
*   `app.js`: window profile dropdown triggers.
*   **Changes**: Header profile toggler bugfix.

## [2026-05-19 13:05] - routes/web.php:181 Null Pointer Fix
*   **Topic**: Routing crashes.
*   **Summary**: Null-safe check module section relation orders. Seed module paths.
*   `web.php`: null safe `?->` operators.
*   **Changes**: Section ordering routes null pointer fixed.

## [2026-05-19 12:50] - Collection Property Exception Fix
*   **Topic**: Eloquent relationships.
*   **Summary**: getSectionAttribute accessor to resolve section relation on module model.
*   `Module.php`: fallback accessor.
*   **Changes**: Module section property crash fixed.

## [2026-05-19 12:45] - Custom Alert & Confirm Modal
*   **Topic**: Modal dialogs.
*   **Summary**: Replace blocking browser alert/confirm with async glassmorphic modals.
*   `test-main.css`, `ui.js`: custom alert promise hooks.
*   **Changes**: Universal beautiful alerts & confirms.

## [2026-05-19 12:35] - Auto Section Gen
*   **Topic**: Creation shortcuts.
*   **Summary**: Auto-create Section if Section ID not specified in Module creation.
*   `TestDashboardController.php`: firstOrCreate section hooks.
*   **Changes**: Dynamic Section generation.

## [2026-05-19 12:20] - Reusable Module Arch
*   **Topic**: junction tables structure.
*   **Summary**: section_modules junction.Decouple Module from Section. stand-alone pools.
*   `2026_05_19_121500_create_section_modules_table.php`, `Module.php`, `Section.php`, `TestDashboardController.php`: junction relationship structures.
*   **Changes**: Reusable Modules architecture implemented.

## [2026-05-19 11:27] - March 2026 DSAT Alignment
*   **Topic**: Score compliance.
*   **Summary**: Force standardized 3PL parameter parameters (a, b, c) at model boot level.
*   `Question.php`: static booted model creator listener.
*   **Changes**: March 2026 IRT Parameters secured.

## [2026-05-19 11:15] - Noto Serif Typography
*   **Topic**: exam layout parity.
*   **Summary**: Noto Serif for exam passages and stems. Inter for application chrome.
*   `test.blade.php`, `test-main.css`: fonts.
*   **Changes**: Typography alignment complete.

## [2026-05-19 11:12] - Loading Performance
*   **Topic**: GPU drawing.
*   **Summary**: will-change: transform + translateZ(0) to enable GPU layers. Strip backdrop-blur.
*   `app.css`: hardware accelerations.
*   **Changes**: 60fps glassmorphic drawings.

## [2026-05-19 11:04] - Seamless Portal Transition
*   **Topic**: Page loading UX.
*   **Summary**: Global glassmorphic transition loading overlay. Mask unstyled content.
*   `test-main.css`, `app.css`, `portal.blade.php`: loader hooks.
*   **Changes**: Seamless portal-to-test transition.

## [2026-05-19 11:00] - Premium Loading Screen
*   **Topic**: transition Steppers.
*   **Summary**: FOUC blocker, adaptive delay loader overlays.
*   `test.blade.php`, `ui.js`: loader steppers.
*   **Changes**: Glassmorphic loader screen.

## [2026-05-19 10:52] - Fullscreen & GEMINI.md Fix
*   **Topic**: exam security mock.
*   **Summary**: Enter fullscreen on user first interaction.
*   `features.js`, `test.js`: fullscreen.
*   **Changes**: Auto fullscreen engine.

