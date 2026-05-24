# Project Feature & Logic Memory

Simple summary of implemented functions and logic for AI context.

## 1. Authentication & User Management

- **Identity:** Laravel Sanctum + Fortify.
- **Flow:** Sign-up, Sign-in (Custom UI), Email Verification (required for dashboard), Password Reset (Email).
- **Security:** Soft deletes for users, rate limiting, secure session management.

## 2. Student Portal (Student Experience)

- **Dashboard:** Overview of progress, recent tests, recommended practice.
- **Test Library:** Access to full-length tests and modular practice.
- **My Practice:** Review system to see past answers, correctness, and explanations.
- **Score Details:** Dynamic page showing precise analytics, domain performance levels (Low/Medium/High), and an interactive Question Review Modal interface.

## 3. Test Engine (Bluebook Clone)

- **Interface:** Accurate replica of College Board's Bluebook app.
- **Rendering:** Integrated **Markdown** for passages/questions and **LaTeX** support for complex math formulas.
- **Core Tools:**
  - **Timer:** Countdown with hide/show and 5-min warning logic.
  - **Calculator:** Dual-mode Desmos (Graphing & Scientific) via tabbed modal.
  - **Strike-through:** Visual exclusion of MCQ choices.
  - **Highlight:** Persistent text highlighting in passages/questions.
  - **Review Page:** Grid view of all questions with "Mark for Review" status.
  - **Fullscreen Mode:** Enters browser fullscreen mode on first click/interaction to simulate a secure testing environment.
  - **Loading Screen:** Glassmorphic dark overlay that masks unstyled content on start and visually manages adaptive grading delay during module transitions.
  - **Typography:** Uses premium academic 'Noto Serif' exclusively for test content (passages, stems, responses) to mirror official exam formatting.
  - **Custom Popups:** Promise-based `showCustomAlert()` and `showCustomConfirm()` modal systems replacing all standard browser blocking alerts/confirms with beautiful glassmorphism style, custom icons, and fluid animations. Supports dynamic self-generation on any page for 100% universal UI compatibility.
- **Logic:** Auto-save answers, sequential navigation, section/module flow.

## 4. Scoring & Adaptive Logic (Advanced IRT)

- **Method:** 3-Parameter Logistic (3PL) Item Response Theory.
- **Estimation:** Maximum Likelihood Estimation (MLE) using Newton-Raphson (30 iterations).
- **Adaptive Routing:** Module 1 results (Theta) determine Module 2 path (Easy vs. Hard).
- **Scoring:** Sigmoid-based mapping from Theta [-4, 4] to Scaled Score [200, 800].
- **Pretest:** Questions marked `is_pretest = true` are excluded from scoring.
- **Enforcement:** Enforces standardized March 2026 3PL parameters (a, b, c) at the model-boot level (Question::creating listener), automatically populating parameters for all uploaded/imported questions.

## 5. Admin & Content Management (Test Dashboard)

- **Split-Pane Document-Based Builder:** Migrated from basic schema-centric admin CRUD to a premium task-centric Notion/Figma-style editor. Left pane: collapsible tree navigator visualizing the Test -> Section -> Module hierarchy. Right pane: contextual canvas rendering action tools and fields based on selected node.
- **Quick Authoring Wizard:** Modal interface for rapidly stubbing new Test/Module content. Features a 1-click DSAT structure generator, custom module branching, tracks recent authoring context locally to provide quick-resume buttons, and includes step-back navigation to smoothly return to the option cards screen in custom flows.
- **Template Clone System:** Provides 1-click cloning of Test and Module hierarchies. Uses Eloquent's `replicate()` to clone structures while omitting underlying questions, keeping templates clean.
- **Interactive Breadcrumbs:** Added sticky, dynamic breadcrumbs in the Builder interface. Automatically populates sibling sections and modules in quick-jump dropdowns without requiring navigation back to lists.
- **1-Click DSAT Generator:** Instantly stubs the standard official 6-module Bluebook structure with correct defaults (Math/R&W, Standard/Easy/Hard modules) under any empty test via a single canvas action.
- **Drag-and-Drop Structure Reordering:** Integrated `SortableJS` on tree and canvas, allowing admins to reorder sections, modules, and questions seamlessly, automatically persisting positions to the database in real-time.
- **Reusable Modules & Composition:** Decoupled modules from tests by introducing many-to-many relationship mapping (`section_modules` table). Modules exist as standalone, reusable question pools that can be called/linked by a unique `key` (e.g. `RW_M1_STANDARD_01`) from any section or test.
- **Management:** CRUD for Tests, Sections, Modules, and Questions. Admins can create standalone modules with unique keys and link them dynamically to test sections. Target Sections are automatically generated on the fly if they don't exist yet on the target Test, eliminating manual section creation.
- **Automatic Dynamic Sync & State Preservation:** Integrated universal client-side refresh mechanism. Creating, updating, linking, or deleting tests, sections, modules, or questions automatically triggers a background AJAX refresh of all dashboard views, dynamically rebuilding and repopulating all related dropdown selections and filter lists without full page reloads, while fully preserving the user's active dropdown selections and filter states.
- **Bulk Import & Bank UI:**
  - **JSON:** Complete structure import.
  - **CSV:** Specialized for batch question updates.
  - **ZIP:** Bundle including images/media and JSON/CSV definitions.
  - **Tolerant Validation Grid (Tabulator Integration):** Parses raw uploads into a local spreadsheet grid showing row-level blocker errors (red-highlighted) and metadata warnings (yellow-highlighted) with hover-based tooltips. Supports direct inline double-click editing, visual re-validation updates, and importing approved/non-blocker rows dynamically.
  - **Premium Question Bank & Pool:** Features a highly optimized, state-of-the-art interactive data layout. Includes custom status badges (R&W blue badges, Math green badges, Pretest active pulser, Easy/Medium/Hard color indicators), modular table rows, advanced input filter bars with live search icon, and smooth button-circle action icons. Renders identically via both server-side Blade and dynamic client-side JS, with ID, Q. Number, Section, Usage, and Difficulty columns perfectly centered/middle aligned.
- **Split-Screen Builder & Widescreen Edit Modal & Visual Refinements:** Refactored the Easy Builder workspace into a robust 3-column layout (navigator sidebar, form card, and live preview drawer). Individual question builder block templates are upgraded to match this premium dark glassmorphic container grid style with gold/amber highlights, styled toggles, and translucent custom inputs. Upgraded and completely converted all dashboard modals (Edit Question Modal, Import Preview, Quick Authoring Wizard) to gorgeous Tailwind CSS v4 custom views. **Refined global CSS for a simple, robust, and snappy UX**: completely eliminated all layout-shifting float/sliding hover transforms across sidebar links, cards, builder blocks, and buttons. Restored complete dark color harmony by overriding default browser checkboxes, radios, TomSelect selected tags, and dynamic selection list backdrops with custom premium glassmorphic/indigo styles. Boosted readability of description texts and metadata by raising color contrast on all low-visibility slate classes. Left side of the Edit Question Modal features a scrollable edit form with integrated EasyMDE editors, while the right side displays a sticky, real-time visual preview panel. **Full Premium Dark Theme Verification**: Verified absolute dark-mode harmony across all tabs (Practice Tests, Sections, Modules, Question Bank, Easy Builder) and wizards, ensuring flawless color schemes, zero styling clashes, high specificity overrides to defeat Tailwind compilation rules, and zero layout shifting. **Low-End Desktop Rendering Performance Optimizations**: Completely stripped all CPU/GPU-taxing backdrop-filter blurs (`backdrop-filter: blur(...)`) and massive multi-layered glowing box-shadow blooms from `.glass-panel`, inputs, select dropdowns, textareas, TomSelect controls, Tabulator footers, card overlays, modals, and offcanvas drawers. Replaced translucency with solid, highly performant dark theme colors (`#111827`, `#0f172a`, `#1e293b`) and simplified flat borders / standard lightweight focus rings (`box-shadow: 0 0 0 2px rgba(...)`) to eliminate scrolling lag and render latency for low-end desktops. **Tailwind v4 Cascade Layers Compliance**: Resolved color/visibility override conflicts by wrapping standard text slate colors, form-text, labels, and `.d-none { display: none !important; }` inside `@layer utilities` to ensure custom high-specificity overrides successfully take precedence over Tailwind CSS v4's compiled layered `!important` utility styles. **Rendering Performance Optimizations (Zero-Transition Lag)**: Banned and completely stripped all CPU/GPU-taxing transitions, scaling/sliding transforms, and continuous animations (pulse, ping) globally under the Test Dashboard. Added a universal CSS blocker reset (`.dark-theme-dashboard *, .modal-content *, .offcanvas * { transition: none !important; transition-property: none !important; transition-duration: 0s !important; transition-delay: 0s !important; transform: none !important; animation: none !important; }`) that instantly overrides all Tailwind and Bootstrap transition/animation classes on both custom and third-party vendor components (TomSelect dropdowns, Tabulator rows, Bootstrap modals). Explicitly preserved the `.animate-spin` class to keep progress spinners fully functional. Cleaned up explicit transitions in scrollbars, inputs, buttons, checkboxes, radios, TomSelect, and EasyMDE to optimize stylesheet parsed efficiency.
- **LaTeX & HTML Formula Parity:** Real-time Markdown, raw HTML tags (e.g. `<u>`), and LaTeX auto-compile with a debounced delay using `window.smartRenderMath()`. Unified all Passage, Stem, and Explanation editors to strictly use double `$$` LaTeX syntax (e.g., `$$ x^2 $$`) to guarantee 100% rendering parity with the student's exam page.
- **In-Editor Image Uploads & Media Management:** Embedded a custom "Upload and Insert Image" action directly inside all EasyMDE toolbars (builder & edit modal). Admins can dynamically upload images to the backend and insert the compiled markdown link (`![](...)`) at the exact cursor position inside passages, stems, and explanations.
- **Automatic Form State Synchronization:** Form submit lifecycles automatically invoke explicit `.value()` assignment on all active EasyMDE editor instances, synchronizing their compiled content with underlying hidden textareas to guarantee that Laravel's backend receives up-to-date data on form submission via FormData.
- **Enhanced Markdown Compilation & Styling Parity:** Custom regex pre-processors ensure strict Markdown engines handle "loose" formatting (e.g. spaces inside bold tags) matching the editor's syntax highlights. Enforces hard line breaks (`breaks: true`) and overrides Tailwind CSS's global preflight resets directly inside `.passage-container` and preview boxes to correctly restore native `<ol>` and `<ul>` list numbering/bullets.
- **Dynamic Question Formats Toggle:** BEM-style MCQ/SPR radio controls in the builder cards instantly toggle forms between multiple choices and student produced responses (SPR) with direct validation highlights on input fields.
- **Clean Architecture & SOLID Refactoring:** Standardized model and service constants across the application (Question type, Section type, Module difficulty, and SatScoring bounds). Extracted a robust, transactional `TestManagementService` to handle domain structure logic (structure generation, hierarchy cloning, cascading deletes), completely eliminating procedurally coupled controller transactions. Implemented 8 dedicated FormRequest validation classes under `app/Http/Requests` (StoreTest, UpdateTest, StoreSection, StoreModule, LinkModule, UpdateQuestion with SPR sanitization, AttachQuestion, SubmitModule). Decoupled `BulkQuestionImportService` and `BulkQuestionCsvImportService` from HTTP `Request` dependencies to make them purely generic, and secured `TestTakingController::submitModule` operations within standard DB transactions.

## 6. Technical Stack

- **Backend:** Laravel 11, Service Layer architecture (`app/Services`).
- **Frontend:** Blade, Vanilla JS (performance-critical engine), Tailwind CSS v4.
- **Database:** MySQL, optimized Schema v3.0 for multi-level test content.
- **[2026-05-24] Dashboard Performance Optimizations**: Implemented lazy-loading for Tabulator tables on the test dashboard to eliminate layout thrashing. Deferred heavy third-party scripts (KaTeX, EasyMDE, Tabulator, TomSelect) to unblock the main thread during initial page load, and batched TomSelect initialization using setTimeout chunking.
- **[2026-05-24] Dashboard INP & Redundant Render Fix**: Drastically reduced Interaction to Next Paint (INP) times when switching tabs by yielding to the main thread via equestAnimationFrame and setTimeout. Added strict data reference checks to prevent Tabulator from unnecessarily destroying and re-rendering tables when navigating back to previously visited tabs.
- **[2026-05-24] Progressive Chunk Rendering (INP Fix)**: Added progressive chunk rendering loops to the Modules and Question Bank tabs. This breaks large datasets into 20-item chunks that render sequentially via equestAnimationFrame, preventing the main thread from locking up. Added native pagination (pagination: true, size 25) to Tabulator tables in the Practice Tests and Sections tabs to enable virtualization.
