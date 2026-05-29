# Project Feature & Logic Memory

Summary of implemented functions/logic.

## 1. Authentication & User Management

- **Identity:** Laravel Sanctum + Fortify.
- **Flow:** Sign-up, Sign-in (Custom UI), Email Verification (req for dashboard), Password Reset, and Remembered User Screen.
  - **Smart Redirects**: Standard session-cookie authenticated users auto-redirect on `/` directly to `/home`. Checkbox "Remember me" users display the static remembered screen right at the index `/`, leaving them to manually choose to continue or sign out.
  - **Role Mismatch Check [2026-05-29]**: Login form in `signin.blade.php` passes expected target login role via hidden field. `LoginWebController.php` validates that the authenticated user's role matches expected role, rejecting logins on mismatch with English warning and clickable redirection link. Front-end `auth.js` renders via `innerHTML` and `auth.css` styles links within `#errorMessage`.
- **Security:** Soft deletes, rate limiting, secure session.

## 2. Student Portal

- **Dashboard:** Progress overview, recent tests, recommended practice.
- **Test Library:** Full-length tests, modular practice.
- **My Practice:** Review answers, correctness, explanations.
- **Score Details:** Precise analytics, domain levels (Low/Med/High), Question Review Modal.

## 3. Test Engine (Bluebook Clone)

- **Interface:** College Board Bluebook replica.
- **Rendering:** Markdown for passages/questions, LaTeX for math formulas.
- **Core Tools**:
  - **Timer**: Countdown, hide/show, 5-min warning.
  - **Calculator**: Desmos (Graphing/Scientific) in tabbed modal.
  - **Strike-through**: Visual MCQ choice exclusion.
  - **Highlight**: Persistent text highlighting.
  - **Review Page**: Grid view, "Mark for Review" status.
  - **Fullscreen**: Browser fullscreen on first interaction. Preserved seamlessly across module transitions using dynamic SPA-style page swapping when active.
  - **Loading Screen**: Glassmorphic dark overlay, mask unstyled content, adaptive grading delay.
  - **Typography**: 'Noto Serif' for test content.
  - **Custom Popups**: Promise-based `showCustomAlert()`/`showCustomConfirm()` glassmorphic modals. Universal UI compat.
  - **Alpine.js Dropdowns**: Directions/More buttons refactored to Alpine (`x-data`, `x-show`, `x-cloak`). Vite bundle.
- **Logic**: Auto-save, sequential navigation, section/module flow.

## 4. Scoring & Adaptive Logic (Advanced IRT)

- **Method**: 3-Parameter Logistic (3PL) IRT.
- **Estimation**: MLE via Newton-Raphson (30 iters).
- **Adaptive Routing**: M1 results (Theta) determine M2 path (Easy vs. Hard).
- **Scoring**: Sigmoid mapping Theta [-4, 4] to Scaled Score [200, 800].
- **Pretest**: `is_pretest = true` excluded from scoring.
- **Enforcement**: March 2026 3PL params (a, b, c) at model-boot level.

## 5. Admin & Content Management (Test Dashboard)

- **Split-Pane Builder**: Notion/Figma-style editor. Tree navigator (left) + Contextual canvas (right).
- **Quick Authoring Wizard**: Rapid Test/Module stubbing. 1-click DSAT structure gen, custom branching, quick-resume, step-back navigation.
- **Template Clone**: 1-click cloning of Test/Module hierarchies via `replicate()`. Omit questions.
- **Interactive Breadcrumbs**: Sticky breadcrumbs, sibling dropdowns in Builder.
- **1-Click DSAT Generator**: Stub official 6-module Bluebook structure via canvas action.
- **Drag-and-Drop**: `SortableJS` for reordering sections, modules, questions. Real-time DB persistence.
- **Reusable Modules**: Many-to-many `section_modules` mapping. Decouple modules from tests. Standalone pool by key.
- **Management**: CRUD for tests, sections, modules, questions. Auto-gen sections if missing.
- **Sync & State**: Client-side refresh. Background AJAX for dashboard updates. Preserve dropdown/filter states.
- **Bulk Import & Bank UI**:
  - **Formats**: JSON (structure), CSV (batch updates), ZIP (bundle + media).
  - **Validation Grid**: Tabulator integration. row-level blockers (red), metadata warnings (yellow). Inline editing, visual re-validation, approved row import.
  - **Operational [2026-05-26]**: Restore dropzones, sample loaders, BE connections, Tabulator validations.
  - **Question Bank**: Custom badges, modular rows, input filters, search icon. Blade/JS rendering parity. Centered columns.
- **Refinements**: 3-column builder layout. Glassmorphic blocks, gold/amber highlights. Tailwind v4 modals. No layout-shifting transforms. High contrast slate. verified dark-mode harmony. No blurs on low-end desktops (solid colors `#111827`, `#0f172a`, `#1e293b`).
- **Unified Styling Controls [2026-05-30]**: Standardized all form controls (inputs, selects, and textareas) across `tests-tab.blade.php`, `sections-tab.blade.php`, `modules-tab.blade.php`, and `builder-tab.blade.php` to use a consistent aesthetic configuration (`bg-slate-900/60 border border-slate-800/80 rounded-xl hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden text-white placeholder-slate-500 text-sm transition-all duration-200`). This completely eliminates redundant class duplications and clashing legacy overrides in `test-dashboard-admin.css`.
- **LaTeX/HTML**: Real-time Markdown, HTML, LaTeX auto-compile (`window.smartRenderMath()`). Strictly double `$$` LaTeX syntax.
- **Media**: "Upload and Insert Image" in EasyMDE toolbars. Insert `![](...)` at cursor.
- **State Sync**: Form submit invokes `.value()` on EasyMDE to sync with textareas.
- **Markdown/Styling**: Regex pre-processors for loose formatting. Hard line breaks. Override Tailwind resets for native `<ol>`/`<ul>` lists.
- **Format Toggle**: MCQ/SPR radio controls toggle forms in builder.
- **Easy Question Builder Stored Support [2026-05-28]**: Enables editing existing module questions directly inside Easy Question Builder workspace. Workspace Index (left sidebar) lists stored questions under the active module. Selecting a stored question fetches full detailed data asynchronously and pre-populates all inputs (stem, passage, domain, difficulty, MCQ choices, SPR answers, explanation) in an editable block. Clicking "Save All Questions" performs transactional dual saves: updates existing questions via `PUT` and stores newly drafted questions in bulk via `POST`. Added confirmation-backed clear workspace helper.
- **Easy Builder Auto-Save [2026-05-28]**: Implements automatic, debounced drafts preservation. Workspace state (select targets, start positions, existing question IDs, passages, stems, MCQ options/correct-selection, SPR answers, and explanations) are compiled and cached in `localStorage` under `sat_builder_draft` on every keystroke and form input. Workspace draft is automatically reconstructed sequentially upon page load/refresh. Saved drafts are automatically cleared upon successful submission or manual workspace clears.
- **SOLID Arch**: Standardized constants. `TestManagementService` for domain logic. 8 FormRequest classes. Generic import services. DB transactions in `submitModule`.
- **Transitions [2026-05-26]**: 60fps animations. No blurs, use `bg-slate-950/80`. Snappy 200ms duration. Alpine `x-transition` + GPU promotion (`transform-gpu`, `will-change`).
- **Wizard [2026-05-26]**: `3xl` width modal. Slate grid layout. Larger icons/headings.
- **Dialogs [2026-05-26]**: Custom `showCustomPrompt()` replace browser `prompt()`. Slate dark theme alerts.
- **Tables [2026-05-26]**: Limit 30 items. Lazy load BE/FE. Glassmorphic spinner overlays. Debounced search (400ms). Min 400ms display for loaders.
  - **Tabulator Layout Shifts Fix [2026-05-30]**: Prevented column collapsing and visual data jumping when switching between dashboard tabs. Implemented a premium fade-in transition (`transition-opacity duration-200 opacity-0` to `opacity-100`) on `testsTabulatorTable` and `sectionsTabulatorTable` containers. Configured `tests.js` and `sections.js` to redraw layouts and fade tables back in with appropriate timeouts on initial build, data replacement, and tab reactivation. Wired `index.js` tab click listener to instantly set `opacity-0` when clicked, hiding any visual layout recalculations from the user.
- **Dynamic Role Badge [2026-05-29]**: Dynamically render 'Teacher' or 'Administrator' in header based on user role attribute.
- **Ownership & Role-Based Access Control [2026-05-29]**: Implemented strict role-based access control and visibility configuration (`is_public` and `created_by` columns) for tests, sections, modules, and questions. Admins have absolute system rights, while Teachers can view/use their own created resources and public ones created by others. Shared resources created by other teachers are read-only. Added cascading recursive query scopes `visibleTo` for zero-leak data loading. Added strict server-side ownership authorization validation on all write endpoints (`store`, `update`, `delete`, `link`, `attach`, `clone`), returning `403 Forbidden` for unauthorized modifications. Created custom PUT update routes for sections/modules to support inline table updates. Rendered interactive/static checkbox toggles and dynamic "Mine"/"Shared" badges (User vs Globe icons) with distinct dashed `.row-shared` styling for non-owned rows. Added template cloning event handlers. Verified with a comprehensive PHPUnit integration test suite (`OwnershipAccessControlTest`).
  - **Dynamic Formats & Owner Display Sync**: Standardized `created_at` date display across all dashboard tables to a clean `DD/MM/YY` format (e.g. `29/05/26`) via `formatDateToShort` utility. Cleaned all system fallback labels from `'System/Admin'` to `'Admin'`. Resolved visual ownership sync bugs by computing `is_owner` and `created_by_name` dynamically on the client-side inside Tabulator maps for `tests.js` and `sections.js` when AJAX reloads retrieve raw Eloquent models lacking pre-processed properties, ensuring correct badge styling and read/write states persist immediately upon asset creation or update.
  - **Questions & Modules Refinements**: Swapped Edit button to View (eye icon) and disabled fields/editors dynamically for unowned questions initially on Blade template load in pool-table.blade.php. Restricted all target module select dropdowns (Easy Question Builder target module, Questions tab module filter, and Import Questions Wizard target module) to only display owned modules for teachers. Integrated questionsTableModuleFilter into the dynamic TomSelect preservation and rebuilding cycles to cleanly retain select value states across AJAX updates.
    - **Dashboard Layout Refactor [2026-05-29]**: Moved inline creation forms in both "Practice Tests" and "Sections" tabs to dedicated Offcanvas (side panel) components (`createTestOffcanvas`, `createSectionOffcanvas`). This transition from cramped grid-based inline cards to focused vertical side panels resolves UI clutter and establishes structural consistency. Integrated "Create Test" and "Create Section" trigger buttons in their respective tab headers, and wired empty state buttons to open these panels. Leveraged the generic JS `setupForm` utility to automatically close the offcanvas side panels upon successful AJAX submits.
- **Questions Tab Show-Shared Toggle**: Connected the questions pool Show Shared toggle to the client-side fetcher `questionsListFetchUrl()` by passing the `show_shared=1` parameter when active. Wired a change event listener to `#questionsShowSharedToggle` in `index.js` to reset pagination and refresh the questions table on state toggle, enabling teachers to filter and view shared public questions on demand.

## 6. Technical Stack

- **Backend**: Laravel 11, Service Layer.
- **Frontend**: Blade, Vanilla JS, Hybrid Styling (Tailwind v4 layout + Raw CSS complex). Alpine.js.
- **Database**: MySQL, Schema v3.0.
- **Perf [2026-05-24]**: Lazy-load Tabulator. Defer heavy scripts (KaTeX, EasyMDE, etc). Batch TomSelect init.
- **INP Fix [2026-05-24]**: `rAF` + `setTimeout` for tab switch. Data reference checks to skip redundant renders.
- **Chunk Rendering [2026-05-24]**: Progressive 20-item chunks via `rAF`. Tabulator virtualization.
