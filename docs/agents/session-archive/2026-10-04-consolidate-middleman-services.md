# Session: Consolidate Middle-Man Services (Candidates 1 - 4)

## Current Goal & Status
- **Goal**: Eliminate redundant middle-man services, consolidate fractured domain boundaries, and apply the Ponytail principle across the core digital-sat services.
- **Status**: completed

## Working State & Completed Steps
1. **Candidate 1 (Attempt Progression)**:
   - Inlined `TestProgressionService` into `AttemptEngine`.
   - Injected `TestStructureService` and `SatScoringPipeline` directly into `AttemptEngine`.
   - Deleted `app/Services/TestProgressionService.php` (-359 lines).
2. **Candidate 2 (Question Ingestion)**:
   - Absorbed CSV parsing and row mapping from `BulkQuestionCsvImportService` into `BulkQuestionImportService`.
   - Decoupled HTTP `Request` validation from `BulkQuestionImportService` methods, taking native `UploadedFile` and primitives.
   - Deleted `app/Services/BulkQuestionCsvImportService.php` (-332 lines).
   - Added `tests/Unit/BulkQuestionImportServiceTest.php`.
3. **Candidate 3 (Assignment Lifecycle)**:
   - Inlined `startOrResume()` from `AssignmentAttemptService` into `AssignmentService`.
   - Added constructor injection of `AttemptEngine` into `AssignmentService`.
   - Updated `Student\AssignmentController` and `TeacherClassManagementTest`.
   - Deleted `app/Services/AssignmentAttemptService.php` (-68 lines).
   - Added `tests/Unit/AssignmentServiceTest.php`.
4. **Candidate 4 (Score Revisions)**:
   - Folded `applyScoreRevision()` into `SatScoringPipeline` and repointed `RescoreGenericV1` command directly to `SatScoringPipeline::rescoreLegacy()` and `applyScoreRevision()`.
   - Deleted `app/Services/ScoreRevisionService.php` (-39 lines).
   - Added `tests/Unit/SatScoringPipelineTest.php`.
5. **Test Verification**:
   - 97 unit tests passing (280 assertions).
   - Workspace is clean and idle.

## Next Immediate Steps
1. Review git diff (`git diff`) and verify all staged/unstaged changes.
2. Await explicit user request if ready to commit.

## Gotchas & Uncommitted Discoveries
- Feature tests require MySQL CLI dump commands not configured in standard local environments, but the Unit test suite (`php artisan test --testsuite=Unit`) runs completely in-memory/isolated in ~3 seconds.
