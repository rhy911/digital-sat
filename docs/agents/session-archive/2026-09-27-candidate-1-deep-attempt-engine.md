# Session: Consolidate Attempt Lifecycle & Timing into a Deep Attempt Engine (Candidate 1)

## Current Goal & Status
- **Goal**: Consolidate attempt lifecycle, timing, submission coordination, distributed locking, and cascade sweeps into AttemptEngine while eliminating controller trait leaks and shallow services.
- **Status**: completed

## Working State & Completed Steps
- Collapsed `HandlesAnswers` trait, `AttemptProgressionService`, `AssignmentModuleTimingService`, `ModuleScoringService`, and `AssignmentAttemptTimeoutService` into `AttemptEngine`.
- Decoupled `SubmissionController`, `SessionController`, `AnswerController`, and `ExamSessionService` from raw answers, progression, and timing internals.
- Resolved code-review & ponytail-review findings:
  - Eliminated message chain and unpersisted SQL query in `AttemptEngine::isPastGraceDeadline` and `durationSeconds`.
  - Encapsulated internal SPR math and question grading mechanics (`checkAnswer` and `parseNumericAnswer` are `protected`).
  - Privatized `isTerminalSubmission` helper in `AttemptEngine`.
  - Removed unused class property on `FinalizeExpiredAssignmentAttempts`.
  - Restored lean `phpunit.xml` config without unneeded `memory_limit`.
- Unit tests `tests/Unit/AttemptEngineTest.php` and `tests/Unit/SprAnswerEquivalenceTest.php` pass cleanly (30 tests, 92 assertions, 0 failures, 0 errors).

## Next Immediate Steps
1. Task complete. Ready for merge/PR. Workspace idle.

## Gotchas & Uncommitted Discoveries
- Eloquent dynamic property accesses like `$module->section?->test?->isPreview()` trigger SQL queries against the database if the relationship is not eager-loaded. Model predicates and engine timing methods must guard with `$module->relationLoaded('section')` to guarantee test isolation without depending on database state.
