# Agent Guidelines

## Code Discipline (Subtraction-First)

- **Edit and delete before adding.** Stop at the lowest rung that holds: question whether new code needs to exist (YAGNI) → reuse or extend existing deep services (`AttemptEngine`, `SatScoringPipeline`, `AssignmentService`, `ClassroomService`), Eloquent models, and Blade components → use PHP/JS standard library and native browser/SQL features → write the shortest working diff.
- **Justify new files and abstractions.** Create a new `app/Services/` class, DTO, trait, helper, or package only when an existing owner cannot absorb the responsibility cleanly. Avoid single-implementation interfaces and pass-through wrappers.
- **Clean up in the same diff.** When refactoring logic or migrating views to `.product-ui`, delete dead methods, unused metric aliases, compatibility shims, and replaced legacy CSS once verified unused by remaining legacy surfaces (`Blog`, `Forum`, `Profile`, `Notifications`, `Teacher Applications`).
- **Fix root causes.** Trace the full call flow and fix once where callers route through.

## Git & Worktree Safety

- **Leave work uncommitted** unless the user explicitly asks to commit, push, or checkpoint a cross-device handoff (see `docs/agents/session-memory.md`).
- **Preserve dirty state and worktrees.** Keep pre-existing uncommitted changes intact and leave sibling git worktrees untouched. Avoid destructive git commands (`reset --hard`, `checkout --`, `clean`).
- **Scope formatting to clean touches.** Run `vendor/bin/pint <file>` on new or cleanly formatted PHP files; on files carrying pre-existing style drift or shared dirty diffs, match PSR-12 manually on edited lines instead of reformatting the whole file.

## Verification & Database Gate

CI runs dependency audits only; verify locally before finishing any code change:

- **PHP changes**: Run affected tests (`php artisan test --filter=...`; `--testsuite=Unit` for fast DB-free checks; full suite on engine, scoring, or auth refactors), `vendor/bin/phpstan analyse <touched-paths>`, and scoped `pint`.
- **Frontend / Blade / CSS / JS changes**: Run `node --test tests/JavaScript/*.test.{js,mjs}` when touching JS, timers, or Home interactions, plus `npm run build`.
- **Database safety**: PHPUnit uses MySQL `sat_app_testing` (`phpunit.xml`), while `.env` targets local `digital_sat`. Run destructive schema commands (`migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`) exclusively with `--env=testing`.

## Platform, Language & Output

- **Shared-hosting target**: Production runs on cPanel without root access—build strictly on MySQL (queues, cache, sessions), local filesystem, and standard PHP/Vite (no Redis, Docker, Horizon, or Octane).
- **English-first**: Write all code, identifiers, tests, docs, and UI copy in English (except explicit `lang/vi/*` strings and teacher `.docx` import templates). Reply in the language of the user's prompt.
- **Concise output**: Lead with code, exact verification counts, and handoff facts; skip unrequested essays and feature tours.

## Context Pointers

- **Session & multi-device handoff** (`docs/agents/session.md`, protocol in `docs/agents/session-memory.md`): Read `docs/agents/session.md` if present before modifying files to respect active streams, `Env / DB Sync` deltas after `git pull`, and off-limits state; follow the user's current prompt and resume `Next` only when asked to continue. Overwrite `session.md` in place after state-changing work.
- **Completed work history** (`docs/agents/history.md`): Consult on demand when debugging regressions, modifying recently refactored areas, or checking retired services and deliberate deferrals (skip on routine tasks).
- **Domain glossary & repo traps** (`CONTEXT.md` and `docs/adr/`): Read before domain, model, timing, or test-fixture work. Use canonical glossary terms in code and tests; flag any ADR conflict explicitly.
- **Product UI & isolated Test Engine** (`DESIGN.md` and `docs/styling-system.md`): Read before Blade, CSS, or UI work. Use `.product-ui` semantic tokens (`design/*` mockups are superseded). Keep the Bluebook Test Engine (`resources/views/engine/**`, `components/layouts/test.blade.php`, engine CSS/JS, passage/KaTeX typography, `lang="en" translate="no"`) isolated from product-UI changes.
- **Scoring & runtime architecture** (`docs/scoring-pipeline-target.md` and `docs/system-architecture-and-roadmap.md`): Read when modifying IRT scoring, module timers/cascades, async submit polling, or queue/cron operations. Keep historical snapshotted attempts immutable.
