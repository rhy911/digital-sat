# Completed Work History

Reverse-chronological ledger of closed milestones across harnesses. Read on demand when debugging regressions, modifying recently refactored areas, or checking retired abstractions and deliberate deferrals. Keep each new entry to 3–4 lines (newest at top).

### 2026-10-25 (`minhdev`) — `agent-guidelines-overhaul`
- **Delivered**: Rewrote `AGENTS.md` around subtraction-first Ponytail discipline, branch-aware verification gates (`sat_app_testing` safety + `node --test`), shared-cPanel limits, and front-loaded context pointers; added un-ignored `CLAUDE.md` (`@AGENTS.md`), 3-tier cross-device/multi-harness memory (`session-memory.md`, `session.md`, `history.md`), and salvaged repo traps in `CONTEXT.md`.
- **Retired**: Deleted unused `mattpocock` issue/triage/domain templates (`docs/agents/{issue-tracker,triage-labels,domain}.md`), `docs/agents/session-archive/**` (distilled here), and empty `docs/current-problems.md`.

### 2026-10-24 (`1aa8b5d`) — `warm-product-ui`
- **Delivered**: Unified student (Home, Library/Preview, Classes, Assignments, Score Report, Progress) and teacher (Home student-view tab, Classes, Assignments/inspection, diagnostics, Content Suite) under opt-in `.product-ui` (`Plus Jakarta Sans`, navy/blue/gold tokens, `.icon-rail--product`).
- **Retired / Deferred**: Retired paper/binder/chalkboard classroom decoration on migrated surfaces. Intentionally left `Blog`, `Forum`, `Profile`, `Notifications`, `Teacher Applications`, `Auth/Landing`, Print/PDF exports, and the isolated Bluebook Test Engine (`resources/views/engine/**`) outside `.product-ui`.

### 2026-10-18..22 (`4f509ec`..`74e3d15`) — `score-reports-assignments-hardening`
- **Delivered**: Overhauled student score reports, KaTeX math rendering, and answer review flows (`74e3d15`); deferred assignment report analysis, hardened inspection/ownership checks, and fixed shell/modal/tab WCAG AA focus & contrast (`3564ccd`–`ca92d95`).
- **Retired**: Deleted the legacy classroom calendar feature and associated dead code (`4f509ec`)—do not reintroduce.

### 2026-10-12 (`6c6a95e`) — `side-rail-and-shell-layer`
- **Delivered**: Extracted universal `.app-shell` and `.icon-rail` out of `classroom-workspace.css` into `resources/css/shell.css` + semantic `--ds-rail-*` tokens in `design-tokens.css`; upgraded rail popovers to accessible dialogs with single-word labels.
- **Retired**: Purged ~600 lines of duplicated shell rules from `classroom-workspace.css` while preserving backward-compatible `@import "./shell.css"`.

### 2026-10-11 (`bfa1dce`, `e630893`) — `classroom-lms-and-auth-overhaul`
- **Delivered**: Added LMS curriculum units, exclusive tests, `ClassroomCohortAnalytics`, Livewire `ClassroomAssignments`/`ClassroomRoster`, and bilingual `lang/{en,vi}/classroom.php` (`e630893`); modernized auth and landing templates with WCAG 2.2 landmarks and `:-webkit-autofill` resets (`bfa1dce`).

### 2026-10-04 (`a91a709`..`64add8d`) — `consolidate-middleman-services`
- **Delivered**: Folded `TestProgressionService` into `AttemptEngine`, `BulkQuestionCsvImportService` into `BulkQuestionImportService`, `AssignmentAttemptService` into `AssignmentService`, and `ScoreRevisionService` into `SatScoringPipeline`; consolidated analytics into `ProgressDossierService` and authoring into `TestManagementService`.
- **Retired**: Deleted those pass-through middleman and zombie lock services—route new work directly through the deep owners above.

### 2026-09-27 (`bea152f`..`7431707`) — `deep-attempt-engine`
- **Delivered**: Consolidated attempt lifecycle, module timing, answer grading, distributed submission locking, and cascade sweeps into `AttemptEngine`, decoupling `Engine/*` controllers from raw progression and answer internals.
- **Retired**: Deleted `HandlesAnswers` trait, `AttemptProgressionService`, `AssignmentModuleTimingService`, `ModuleScoringService`, and `AssignmentAttemptTimeoutService`.
