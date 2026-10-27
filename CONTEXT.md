# Domain Glossary & Repo Traps

## Domain Glossary

- **Test**: The exam specification and container for sections and modules (`adaptive_full`, `normal_full`, `section_only`, `module_only`).
- **Section**: Top-level subject partition (`reading_writing` or `math`).
- **Module**: Discrete 27-question (RW) or 22-question (Math) test module.
- **Attempt (`UserTest`)**: A student's sitting of a test.
  - **Chained Clock**: Continuous timer for assignments and live exam sessions. The next module starts when the previous deadline passes, even if the student leaves.
  - **Paused Clock**: Pausable timer for student self-study practice. Legacy assignments using this mode are converted to the continuous clock while preserving each active attempt's saved time.
- **Submission**: Finalizing a module, saving question snapshots, grading answers, and advancing the attempt.
- **Cascade**: Sweeper process that automatically advances overdue modules on chained-clock attempts when a student is away.
- **AttemptEngine**: Deep domain service encapsulating attempt timing, progression, answer verification, submission coordination, and cascade sweep timeouts.
- **SatScoringPipeline**: Deep domain service encapsulating ability estimation, score conversion, confidence bands, and attempt finalization across test progression and score revision.

## Repo Traps & Invariants

- **Guard unloaded relations in hot paths and unit tests**: Check `$module->relationLoaded('section')` (and nested `test` or `review` relations) before traversing dynamic Eloquent properties inside `AttemptEngine`, `ProgressDossierService`, and model predicates so DB-free unit tests and loops do not fire hidden SQL queries.
- **Historical attempt snapshots**: Historical rescoring (`sat:rescore-generic-v1`, `SatScoringPipeline::rescoreLegacy`) skips unsafe or incomplete attempts and honors locked revisions. Legacy scoring tests must construct complete snapshotted forms via `Tests\Support\ScoringFormBuilder`.
- **Assignment access vs. clock mode**: Classroom/recipient eligibility is orthogonal to timer mode—do not infer assignment privacy or access from `Chained Clock` vs. `Paused Clock`.
- **Windows test environment**: `php artisan test --testsuite=Unit` runs DB-free in ~3s. Feature tests run against MySQL `sat_app_testing`; avoid schema-dump workflows that shell out to `mysqldump` when absent from local Windows `PATH`.
