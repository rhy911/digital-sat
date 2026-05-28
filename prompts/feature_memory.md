# Project Feature & Logic Memory

Summary of implemented functions/logic.

## 1. Authentication & User Management

- **Identity:** Laravel Sanctum + Fortify.
- **Flow:** Sign-up, Sign-in (Custom UI), Email Verification (req for dashboard), Password Reset, and Remembered User Screen.
  - **Smart Redirects**: Standard session-cookie authenticated users auto-redirect on `/` directly to `/home`. Checkbox "Remember me" users display the static remembered screen right at the index `/`, leaving them to manually choose to continue or sign out.
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
- **LaTeX/HTML**: Real-time Markdown, HTML, LaTeX auto-compile (`window.smartRenderMath()`). Strictly double `$$` LaTeX syntax.
- **Media**: "Upload and Insert Image" in EasyMDE toolbars. Insert `![](...)` at cursor.
- **State Sync**: Form submit invokes `.value()` on EasyMDE to sync with textareas.
- **Markdown/Styling**: Regex pre-processors for loose formatting. Hard line breaks. Override Tailwind resets for native `<ol>`/`<ul>` lists.
- **Format Toggle**: MCQ/SPR radio controls toggle forms in builder.
- **SOLID Arch**: Standardized constants. `TestManagementService` for domain logic. 8 FormRequest classes. Generic import services. DB transactions in `submitModule`.
- **Transitions [2026-05-26]**: 60fps animations. No blurs, use `bg-slate-950/80`. Snappy 200ms duration. Alpine `x-transition` + GPU promotion (`transform-gpu`, `will-change`).
- **Wizard [2026-05-26]**: `3xl` width modal. Slate grid layout. Larger icons/headings.
- **Dialogs [2026-05-26]**: Custom `showCustomPrompt()` replace browser `prompt()`. Slate dark theme alerts.
- **Tables [2026-05-26]**: Limit 30 items. Lazy load BE/FE. Glassmorphic spinner overlays. Debounced search (400ms). Min 400ms display for loaders.

## 6. Technical Stack

- **Backend**: Laravel 11, Service Layer.
- **Frontend**: Blade, Vanilla JS, Hybrid Styling (Tailwind v4 layout + Raw CSS complex). Alpine.js.
- **Database**: MySQL, Schema v3.0.
- **Perf [2026-05-24]**: Lazy-load Tabulator. Defer heavy scripts (KaTeX, EasyMDE, etc). Batch TomSelect init.
- **INP Fix [2026-05-24]**: `rAF` + `setTimeout` for tab switch. Data reference checks to skip redundant renders.
- **Chunk Rendering [2026-05-24]**: Progressive 20-item chunks via `rAF`. Tabulator virtualization.
