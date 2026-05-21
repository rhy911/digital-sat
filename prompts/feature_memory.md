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
  - **Premium Question Bank & Pool:** Features a highly optimized, state-of-the-art interactive data layout. Includes custom status badges (R&W blue badges, Math green badges, Pretest active pulser, Easy/Medium/Hard color indicators), modular table rows, advanced input filter bars with live search icon, and smooth button-circle action icons. Renders identically via both server-side Blade and dynamic client-side JS.
- **Split-Screen Builder & Live Math Preview:** Refactored Easy Builder workspace into a robust 3-column layout. Left: Sticky vertical sidebar navigator indexing questions by difficulty/format badges with smooth centering and active scroll highlighting. Middle: Active question form card. Right: Bluebook Live Preview Drawer showing stems, passages, and choices.
- **LaTeX Formula Syncing:** Real-time Markdown and LaTeX auto-compiles with a 250ms debounced delay utilizing `window.smartRenderMath()` to ensure absolute parity with the student's test environment.
- **Dynamic Question Formats Toggle:** BEM-style MCQ/SPR radio controls in the builder cards instantly toggle forms between multiple choices and student produced responses (SPR) with direct validation highlights on input fields.
- **Media:** Centralized media controller for image uploads and storage.

## 6. Technical Stack

- **Backend:** Laravel 11, Service Layer architecture (`app/Services`).
- **Frontend:** Blade, Vanilla JS (performance-critical engine), Tailwind CSS v4.
- **Database:** MySQL, optimized Schema v3.0 for multi-level test content.
