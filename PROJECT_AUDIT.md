# Digital SAT SaaS Platform Audit

**Reviewed source snapshot:** `repomix-output.xml`  
**Project type:** Laravel / Blade / TailwindCSS / Alpine.js / MySQL e-learning platform  
**Audit scope:** full technical audit, SaaS product workflow review, production readiness, deployment readiness, and scaling assessment  
**Primary conclusion:** this codebase is a strong Digital SAT simulator and internal content-authoring prototype, but it is not yet a production-ready SaaS educational platform.

---

## 1. Executive Summary

The project has real product substance: user authentication, email verification, role-based routing, a Bluebook-style test-taking interface, adaptive module routing, SAT scoring, a teacher/admin content dashboard, question imports, reusable modules, and result review. That is more than a toy prototype.

The current system is still not production-ready SaaS because the strongest part is the test simulator, while the weakest parts are the actual SaaS operating model: schools, classes, assignments, teacher-student relationships, tenant isolation, admin operations, analytics, billing, monitoring, and recovery.

The biggest mismatch is product shape. The application behaves like:

- a solo practice-test platform for students;
- a CMS for creating Digital SAT content;
- a prototype adaptive test engine.

It does not yet behave like:

- a classroom product teachers can adopt end-to-end;
- a school SaaS platform with rosters and assignments;
- an admin-operated production service;
- a platform ready for cohorts taking timed tests at scale.

### Scorecard

| Area                 |    Score | Assessment                                                                                                                                            |
| -------------------- | -------: | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Architecture         | 5.5 / 10 | Reasonable Laravel monolith, but domain boundaries are incomplete and several workflows are coupled to controllers/views.                             |
| Code Quality         | 5.5 / 10 | Some services and policies exist, but there is mixed inline view logic, duplicated UI helpers, and fragile frontend state.                            |
| Database             |   5 / 10 | SAT content schema is thoughtful, but SaaS tenancy, attempts, assignments, and indexes are incomplete.                                                |
| Frontend             |   6 / 10 | Test engine UX is ambitious; admin dashboard is heavy and complex; accessibility and mobile workflows are weak.                                       |
| Performance          | 4.5 / 10 | Good enough for small use; not ready for large cohorts due to database-backed cache/session/queue and broad eager loading.                            |
| Security             | 4.5 / 10 | Basic Laravel protections exist, but role self-selection, weak tenant model, incomplete authorization checks, and CDN/runtime dependencies are risks. |
| Maintainability      |   5 / 10 | File organization is acceptable, but feature complexity is ahead of product maturity.                                                                 |
| Deployment Readiness | 4.5 / 10 | Can likely deploy manually, but lacks production runbook, CI/CD, supervisor config, backups, monitoring, and env hardening.                           |
| Production Readiness | 38 / 100 | Usable as beta/internal pilot with controlled users; not ready for paid SaaS production.                                                              |

### Highest-Risk Issues

| Severity | Issue                                                                    | Why It Matters                                                                                            | Evidence                                                                                                               |
| -------- | ------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Critical | No SaaS tenant/class/assignment model                                    | Teachers cannot manage real cohorts; admins cannot operate a SaaS business.                               | `routes/web.php`, migrations under `database/migrations/*`, no organization/class/enrollment/assignment tables.        |
| Critical | Student answer state is not saved per question                           | A refresh, browser crash, or module navigation failure can lose work despite UI copy claiming autosave.   | `resources/js/test/navigation.js`, `app/Http/Controllers/TestTakingController.php`, `resources/views/index.blade.php`. |
| Critical | Time enforcement is client-side                                          | Timed testing fairness and recovery are not enforceable server-side.                                      | `resources/js/test/timer.js`, `app/Http/Controllers/TestTakingController.php`.                                         |
| High     | Queue/cache/session default to database                                  | MySQL becomes web session store, cache store, queue broker, app database, and scoring coordination layer. | `config/session.php`, `config/cache.php`, `config/queue.php`.                                                          |
| High     | Test attempt model is too thin                                           | Retakes, resume, concurrent attempts, per-module status, and audit trail are not modeled cleanly.         | `database/migrations/2026_05_11_132718_create_user_tests_table.php`, `app/Models/UserTest.php`.                        |
| High     | Teacher content workflow exists, but teacher education workflow does not | The product lets teachers build content, but not assign, monitor, group, intervene, or report.            | `test-dashboard` routes and views.                                                                                     |
| High     | Public/private sharing is not tenant-safe                                | Visibility flags are too coarse for schools, departments, teams, and paid accounts.                       | `created_by` / `is_public` fields in `2026_05_29_112208_add_ownership_and_visibility_fields.php`.                      |

---

## 2. Project Understanding and Maturity

### Business Purpose

The project is a Digital SAT preparation and testing platform. It attempts to reproduce the student testing experience while giving teachers/admins a way to build SAT tests, manage question banks, import content, and review student results.

### Inferred Roles

| Role    | Current Capability                                                                                    | Missing SaaS Capability                                                                                  |
| ------- | ----------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| Student | Register, sign in, choose practice test, take modules, receive score details.                         | Assignment inbox, resume, retakes, study plan, progress trend, accommodations, class membership.         |
| Teacher | Access test dashboard, create tests/sections/modules/questions, import questions, clone/link modules. | Roster, classes, assign tests, due dates, monitor live/in-progress attempts, student analytics, exports. |
| Admin   | Same dashboard with broad access.                                                                     | User/org management, teacher approval, billing, support, audit logs, content moderation, system health.  |

### System Architecture

Current architecture is a Laravel monolith:

- Web routes in `routes/web.php`.
- API auth routes in `routes/api.php`.
- Controllers under `app/Http/Controllers`.
- Form requests under `app/Http/Requests`.
- Policies under `app/Policies`.
- Core services under `app/Services`.
- Scoring job in `app/Jobs/ScoreModuleJob.php`.
- Blade views under `resources/views`.
- Frontend modules under `resources/js`.
- MySQL-compatible migrations under `database/migrations`.
- Vite asset build in `vite.config.js`.

This is a reasonable starting architecture for an MVP. A monolith is still appropriate at 1,000-10,000 registered users if the domain model and infrastructure are improved. The problem is not "Laravel monolith"; the problem is incomplete SaaS domain modeling and weak production infrastructure.

### Core Modules

- Authentication and email verification: `app/Http/Controllers/Auth/*`, `resources/views/auth/*`.
- Student home and practice history: `HomeController`, `PracticeController`, `resources/views/home.blade.php`.
- Test taking: `TestTakingController`, `resources/views/tests/take/*`, `resources/js/test/*`.
- Scoring/adaptive routing: `SatScoringService`, `ScoreModuleJob`.
- Test/content management: `TestDashboardController`, `TestController`, `SectionController`, `ModuleController`, `QuestionController`, `TestStructureController`.
- Bulk import: `BulkQuestionImportService`, `BulkQuestionCsvImportService`.
- Authorization: `EnsureHasRole`, policies for test/section/module/question.

### Implementation Status

**Maturity estimate:** MVP / early beta for a controlled internal pilot.

It is not just a prototype because many core flows exist and tests are present. It is not production candidate because:

- student progress persistence is insufficient;
- production operations are missing;
- SaaS domain model is missing;
- teacher/admin product workflows are incomplete;
- scaling defaults are not production-grade;
- legal/branding risk exists if Bluebook/College Board-like naming is public-facing.

---

## 3. SaaS Workflow Review

## 3.1 Student Workflow

### What Works

- Student auth flow exists.
- Student dashboard exists at `/home`.
- Practice test selection exists at `choose-test`.
- Test-taking UI includes timer, module navigation, answer choices, review, mark-for-review, strike-through, highlighting, and calculator.
- Score details and domain analysis exist through `PracticeController::scoreDetails()` and `resources/views/tests/score-details.blade.php`.

### UX Friction

#### [Critical] The product promises autosave but does not provide real autosave

**Evidence**

- Marketing/auth copy says "Your progress is automatically saved" in `resources/views/index.blade.php`, `resources/views/auth/signin.blade.php`, and `resources/views/auth/signup.blade.php`.
- Answers are collected in browser memory and sent only when a module is submitted in `resources/js/test/navigation.js`.
- Server writes answers in `TestTakingController::submitModule()`.

**Impact**

A student can lose an entire module if the browser crashes, Wi-Fi drops, laptop sleeps, or they accidentally navigate away before module submit. This is unacceptable for timed assessment SaaS.

**Fix**

Add per-question or periodic autosave.

Example minimal endpoint shape:

```php
Route::post('/attempts/{attempt}/answers', [AttemptAnswerController::class, 'store'])
    ->middleware(['auth', 'verified']);
```

```php
public function store(StoreAttemptAnswerRequest $request, Attempt $attempt)
{
    $this->authorize('update', $attempt);

    AttemptAnswer::updateOrCreate(
        [
            'attempt_id' => $attempt->id,
            'question_id' => $request->integer('question_id'),
        ],
        [
            'selected_answer' => $request->input('selected_answer'),
            'marked_for_review' => $request->boolean('marked_for_review'),
            'client_saved_at' => $request->date('client_saved_at'),
        ]
    );

    return response()->json(['status' => 'saved']);
}
```

#### [Critical] Timer is client-side only

**Evidence**

- `resources/js/test/timer.js` initializes countdown from `window.durationMinutes`.
- `handleTimeUp()` only moves the user to the review section.
- No server-side deadline is stored or enforced in `user_tests`.

**Impact**

Students can refresh, modify browser state, pause JS, or submit late. The platform cannot support fair timed tests, live cohorts, or any teacher-trusted timed assignment.

**Fix**

Store module-level deadlines server-side:

```php
attempt_modules:
- id
- attempt_id
- module_id
- started_at
- due_at
- submitted_at
- status
```

Server should reject or auto-submit answers after `due_at`.

#### [High] Retakes are not a first-class workflow

**Evidence**

- `TestTakingController::startTest()` uses `UserTest::updateOrCreate()` by `user_id` and `test_id`.
- `user_tests` has no attempt number, no started_at, no per-module state, and no explicit unique/index strategy.

**Impact**

A student cannot cleanly retake a test, compare attempts, resume partial attempts, or maintain a reliable history.

**Fix**

Rename/replace `user_tests` with `test_attempts`, and let each student have many attempts per test.

```php
test_attempts:
- id
- user_id
- test_id
- assigned_test_id nullable
- attempt_number
- status
- started_at
- completed_at
- score_reading_writing
- score_math
- total_score
```

#### [High] Completion redirects home instead of results

**Evidence**

- `ScoreModuleJob` sets `redirect_url` to `route('home')`.
- Student then has to find the completed attempt manually.

**Impact**

The most important feedback moment is interrupted.

**Fix**

Redirect directly to score details:

```php
'redirect_url' => route('my-practice.score', $userTest->id),
```

#### [Medium] Student dashboard is framed like official test-day Bluebook, not a practice SaaS

**Evidence**

- `resources/views/home.blade.php` includes "You Have No Upcoming Tests", paper ticket copy, and practice toggle.

**Impact**

This confuses the product promise. A practice SaaS should prioritize assigned work, recommended next practice, weak areas, upcoming deadlines, and recent scores.

**Fix**

Restructure student home:

- Continue active attempt
- Assigned tests
- Recommended weak-area practice
- Recent attempts
- Score trend
- Teacher feedback

### Missing Student Features

- Resume active attempt.
- Per-question autosave.
- Retake attempts.
- Assignment inbox.
- Student progress timeline.
- Weak-area recommendations.
- Practice generated from missed domains.
- Accommodations: extended time, pause rules, text sizing, keyboard navigation.
- Parent/student downloadable reports.
- Notification/reminder system.

### Student Features To Remove Or Defer

- Forced fullscreen and copy blocking until there is a real proctoring model.
- "Paper ticket from your school" copy unless ticket-based auth is implemented.
- Bluebook/College Board-like branding if this is a public SaaS.

---

## 3.2 Teacher Workflow

### What Works

Teacher/admin users can access `/test-dashboard` and manage:

- tests;
- sections;
- modules;
- reusable modules;
- questions;
- bulk imports;
- ZIP/media import;
- quick author wizard;
- builder workspace.

This is a capable content CMS.

### UX Friction

#### [Critical] Teacher workflow stops at content creation

**Evidence**

- Routes under `test-dashboard` manage tests, sections, modules, questions, and media.
- There are no routes, migrations, or controllers for classes, rosters, assignments, teacher-student relationships, or student progress dashboards.

**Impact**

Teachers can build a test but cannot run a class. That blocks product-market fit for educational SaaS.

**Fix**

Add the missing teacher workflow:

1. Create class.
2. Invite/enroll students.
3. Assign test with due date and timing/accommodations.
4. Monitor in-progress attempts.
5. Review class results.
6. Export/report/send recommendations.

Minimum schema:

```php
organizations
classes
class_memberships
test_assignments
assignment_attempts
teacher_student_notes
```

#### [High] Content hierarchy is exposed too directly

**Evidence**

- Teacher UI exposes separate tabs for Practice Tests, Sections, Modules, Question Bank, and Easy Builder.
- `resources/views/components/test-dashboard/*` mirrors the internal SAT hierarchy.

**Impact**

Most teachers think in terms of "create assignment" or "make practice set", not `Test > Section > Module > Question` internals.

**Fix**

Keep the current structure for power users/admins, but add a teacher-first flow:

- "Create Practice Test"
- "Use Template"
- "Add Questions"
- "Preview as Student"
- "Publish"
- "Assign to Class"

#### [High] Too many authoring entry points

**Evidence**

- Create Test offcanvas.
- Create Section offcanvas.
- Create Module offcanvas.
- Link Module offcanvas.
- Import Questions Wizard.
- Easy Question Builder.
- Quick Author Wizard.

**Impact**

This gives power but increases cognitive load. It also increases QA scope and support burden.

**Fix**

Promote one default wizard. Hide low-level module/section controls under "Advanced".

#### [High] Teacher accounts are self-selected

**Evidence**

- `RegisterWebController` accepts `role` as `student` or `teacher`.
- Signup UI lets users register as student or teacher.

**Impact**

Anyone can become a teacher and access teacher-only authoring. In a SaaS with student data, this is not acceptable.

**Fix**

Change teacher onboarding:

- Students self-register.
- Teachers request access or are invited by an organization admin.
- Teacher role starts as `pending_teacher`.
- Admin approves or organization owner invites.

#### [Medium] Teacher visibility uses public/private flags, not team ownership

**Evidence**

- `created_by` and `is_public` were added to tests, sections, modules, and questions.
- Visibility scopes use owner or public checks.

**Impact**

This does not support departments, school-owned content, paid org libraries, shared drafts, or collaboration.

**Fix**

Use organization-scoped ownership:

```php
content_assets:
- organization_id
- owner_id
- visibility: private | org | public_library
- status: draft | review | published | archived
```

### Missing Teacher Features

- Class roster.
- Invite links.
- CSV student import.
- Assign test to class/student.
- Due dates and availability windows.
- Attempt monitoring.
- Per-student reports.
- Class domain mastery heatmap.
- Export to CSV/PDF.
- Comment/feedback on attempts.
- Question-level item analysis.
- Reassign missed-domain practice.
- Shared school question bank.

### Teacher Features To Remove Or Defer

- Public visibility toggles until tenant model exists.
- Standalone reusable module management for normal teachers.
- ZIP import for all teachers; keep it admin/power-user only.
- Advanced IRT settings in teacher UI unless users understand them.

---

## 3.3 Admin Workflow

### Current State

Admin appears to be a privileged role in the same content dashboard. This is not enough for SaaS administration.

### Missing Admin Features

#### [Critical] No organization/tenant administration

**Evidence**

- Users have a single `role` enum.
- No organization, school, tenant, plan, subscription, or membership tables exist.

**Impact**

The app cannot safely sell to schools, isolate customer data, or give organization admins control.

**Fix**

Add:

- `organizations`
- `organization_memberships`
- `roles` or scoped role assignments
- `invitations`
- `billing_accounts`
- `subscriptions`

#### [High] No user management console

Admin needs:

- create/edit/deactivate users;
- reset MFA/password;
- approve teachers;
- inspect user attempts;
- impersonate with audit trail;
- view email delivery state.

#### [High] No system operations dashboard

Admin needs:

- queue depth;
- failed jobs;
- scoring error rate;
- storage usage;
- active tests;
- request/error metrics;
- CDN/media health;
- database backup status.

#### [High] No audit logging

Admin actions, teacher content changes, role changes, assignment changes, and student attempt overrides should be logged.

Minimum audit schema:

```php
audit_events:
- actor_id
- organization_id
- action
- subject_type
- subject_id
- metadata json
- ip_address
- user_agent
- created_at
```

---

## 4. Architecture Review

## 4.1 Strengths

- Laravel monolith is a pragmatic fit for MVP.
- Controllers are split by domain: auth, test taking, dashboard, test/section/module/question management.
- Service layer exists for scoring, bulk import, CSV import, and test management.
- Policies exist for ownership checks.
- Form requests exist for validation.
- Frontend test engine is modularized under `resources/js/test/*`.
- Dashboard JS has been split into modules under `resources/js/test/dashboard/*`.
- Queue job is used for scoring.

## 4.2 Architecture Issues

### [High] Domain model is content-first, not SaaS-first

**Evidence**

Schema covers SAT content well:

- tests;
- sections;
- modules;
- questions;
- passages;
- answer choices;
- explanations;
- score conversions.

But it lacks:

- organizations;
- classes;
- enrollments;
- assignments;
- attempt modules;
- billing;
- audit logs.

**Impact**

You can build tests, but you cannot operate the product as SaaS.

**Fix**

Introduce a domain layer around `Organization`, `Classroom`, `Assignment`, and `Attempt`. Do not bolt those onto `UserTest` as nullable fields indefinitely.

### [High] Test attempt lifecycle is not explicitly modeled

**Evidence**

- `user_tests` stores one row with score fields and status.
- `user_test_answers` stores final answers.
- Routing path fields were later added.

**Impact**

Attempt lifecycle becomes hard to reason about. State transitions such as started, module in progress, module submitted, scoring, completed, expired, abandoned, resumed, and invalidated need explicit representation.

**Fix**

Add:

- `test_attempts`
- `attempt_modules`
- `attempt_answers`
- `attempt_events`

### [High] Broad dashboard route group mixes UI and JSON APIs

**Evidence**

`routes/web.php` puts dashboard page routes, API-like JSON endpoints, mutation endpoints, import endpoints, media upload, and delete endpoints under the same web route group.

**Impact**

This works for MVP but becomes difficult to version, authorize, test, and consume by a future SPA/mobile app.

**Fix**

Split:

- web pages: `routes/web.php`;
- internal JSON endpoints: `routes/admin.php` or `routes/api.php`;
- public/student APIs: versioned API group.

### [Medium] Controllers still perform too much workflow coordination

**Evidence**

- `TestTakingController::showModule()` loads test structure, determines default test/module, hides answer correctness, builds view data, determines next module, creates user test.
- `TestTakingController::submitModule()` saves answers, checks correctness, dispatches scoring.

**Impact**

Testing and modifying test lifecycle logic becomes risky.

**Fix**

Create services:

- `AttemptStartService`
- `AttemptModuleViewService`
- `AnswerSubmissionService`
- `ModuleRoutingService`
- `AttemptCompletionService`

### [Medium] Service layer exists but ownership context is inconsistent

**Evidence**

Bulk question import creates questions but does not consistently inject `created_by`.

**Impact**

Teacher ownership and visibility become unreliable.

**Fix**

Pass actor context into write services:

```php
$bulkQuestionImport->import($payload, actor: auth()->user());
```

### [Medium] Frontend code relies on global state and inline Blade globals

**Evidence**

- Test views set `window.nextModuleId`, `window.userTestId`, `window.currentModuleId`, `window.durationMinutes`.
- JS modules rely on shared `state`.

**Impact**

This is acceptable for MVP, but fragile for complex resume/recovery flows and real-time autosave.

**Fix**

Centralize test runtime boot data as JSON:

```blade
<script type="application/json" id="test-runtime">
    @json($runtime)
</script>
```

Then parse once in JS.

---

## 5. Database Review

## 5.1 Strengths

- SAT content schema is normalized.
- Many-to-many module/question table uses unique indexes.
- `question_explanations` uses unique question relation.
- User test answers have unique `user_test_id + question_id`.
- Score conversions table has composite uniqueness.
- Foreign keys are used throughout migrations.

## 5.2 Database Issues

### [Critical] Missing SaaS tenancy tables

**Evidence**

No migrations define organizations, classes, enrollments, assignments, subscriptions, or tenant-level roles.

**Impact**

The app cannot safely support multiple schools or paid customers.

**Fix**

Add tenant model before onboarding real institutions:

```php
organizations
organization_memberships
classrooms
classroom_memberships
test_assignments
assignment_attempts
```

### [High] `user_tests` is underspecified

**Evidence**

`database/migrations/2026_05_11_132718_create_user_tests_table.php` contains:

- user_id;
- test_id;
- score fields;
- status;
- completed_at.

Later migration adds routing paths and theta fields.

**Impact**

It conflates attempt identity, attempt state, scoring output, and adaptive path state.

**Fix**

Replace with `test_attempts` and `attempt_modules`.

### [High] Missing indexes for common product queries

Likely needed indexes:

```php
Schema::table('tests', function (Blueprint $table) {
    $table->index(['status', 'is_public']);
    $table->index(['created_by', 'status']);
});

Schema::table('questions', function (Blueprint $table) {
    $table->index(['created_by', 'section_type', 'is_complete']);
    $table->index(['section_type', 'difficulty']);
    $table->index('external_id');
});

Schema::table('user_tests', function (Blueprint $table) {
    $table->index(['user_id', 'status', 'completed_at']);
    $table->index(['test_id', 'status']);
});
```

### [High] `is_public` is too coarse

**Evidence**

`2026_05_29_112208_add_ownership_and_visibility_fields.php` adds `created_by` and `is_public`.

**Impact**

Content sharing becomes binary. SaaS needs organization and role-scoped sharing.

**Fix**

Use visibility enum and organization ownership.

### [Medium] Scoring state relies on cache result keys

**Evidence**

`ScoreModuleJob` writes `scoring_result_{userTestId}` to cache; polling endpoint pulls it.

**Impact**

With database cache this creates extra DB writes. With short TTL, slow jobs or worker failures can leave students stuck polling.

**Fix**

Persist scoring status to `attempt_modules`:

```php
status: submitted | scoring | routed | completed | failed
scoring_error: nullable text
next_module_id: nullable
```

### [Medium] No audit/event table

**Impact**

You cannot reliably reconstruct who published content, changed a role, deleted a question, changed a score, or invalidated an attempt.

**Fix**

Add `audit_events`.

---

## 6. Frontend and UX Review

## 6.1 Strengths

- Student test-taking UI is ambitious and close to real SAT workflow.
- The test frontend is split into modules: `state`, `timer`, `navigation`, `features`, `ui`.
- Admin dashboard uses components for tests, sections, modules, questions, builder, modals.
- Content authoring includes Markdown/editor previews, media upload, CSV/JSON/ZIP imports, and live previews.

## 6.2 UX Problems

### [Critical] Student progress copy is inaccurate

The UI says progress is automatically saved, but the implementation saves on module submit. This is a trust-breaking issue.

### [High] Teacher dashboard is a CMS, not a teacher workflow

Teachers need:

- "Create class";
- "Assign practice";
- "See who finished";
- "Find weak areas";
- "Send practice".

They currently get:

- Tests;
- Sections;
- Modules;
- Question Bank;
- Builder.

### [High] Admin dashboard uses dark, dense, highly styled UI for operational work

The dashboard is visually strong but heavy. SaaS operations should be information-dense, predictable, and low-friction. The current design is closer to a showcase CMS than an everyday admin console.

### [High] External CDN dependencies are runtime-critical

**Evidence**

The test layout loads KaTeX and Desmos from CDNs. Dashboard helper loads Tom Select, EasyMDE, Tabulator, Marked, and KaTeX from CDNs at runtime.

**Impact**

If a CDN is slow/blocked, core authoring or math rendering breaks. This is especially risky in schools with restrictive networks.

**Fix**

Bundle where possible through Vite. Keep Desmos external if licensing requires it, but add failure states and network checks.

### [Medium] Forced fullscreen/copy blocking is weak security and causes UX friction

**Evidence**

`preventNormalCursorBehavior()` disables context menu, blocks copy/paste shortcuts outside inputs, and pushes history state.

**Impact**

This annoys legitimate users but does not stop cheating. It may also create accessibility issues.

**Fix**

Remove for practice mode. Only enable controlled-lockdown behavior for a separate proctored mode.

### [Medium] Accessibility is not clearly handled

Risks:

- custom buttons implemented as divs;
- heavy modals;
- keyboard traps;
- custom dropdowns;
- forced fullscreen;
- visual-only state indicators.

**Fix**

Add an accessibility pass:

- semantic buttons;
- ARIA labels;
- keyboard navigation;
- focus management;
- contrast checks;
- screen reader-friendly answer selection;
- accommodations settings.

---

## 7. Performance Review

## 7.1 Backend Performance

### [High] Database is overloaded with app duties

**Evidence**

- `SESSION_DRIVER` defaults to database.
- `CACHE_STORE` defaults to database.
- `QUEUE_CONNECTION` defaults to database.

**Impact**

At cohort scale, MySQL handles:

- application queries;
- sessions;
- cache keys;
- scoring job queue;
- scoring poll result storage.

This causes avoidable write load and lock contention.

**Fix**

Use Redis:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
```

### [High] Broad eager loading in test rendering

**Evidence**

`TestTakingController::showModule()` loads nested relationships such as:

- `section.test.sections.modules.questions.passage`;
- `section.test.sections.modules.questions.answerChoices`.

**Impact**

Rendering one module can load more of the test than necessary. At scale, this becomes expensive.

**Fix**

Load only:

- current module questions;
- current module passages/choices;
- next module metadata only.

### [Medium] Polling scoring status every 1.5 seconds

**Evidence**

`resources/js/test/navigation.js` polls `/submit-status/{userTestId}` every 1500ms.

**Impact**

For 1,000 simultaneous students, polling can create thousands of requests per minute during transitions.

**Fix**

Persist status and use:

- longer exponential polling;
- server-sent events;
- WebSocket channel;
- or synchronous scoring if scoring remains cheap.

### [Medium] Dashboard refresh fetches broad snapshots

**Evidence**

`TestDashboardController::snapshot()` returns tests, passages, and modules.

**Impact**

As content grows, every dashboard refresh becomes heavier.

**Fix**

Use paginated, per-tab endpoints with filters and search.

## 7.2 Frontend Performance

### [High] Admin dashboard loads many heavy libraries dynamically

**Evidence**

`loadHeavyDependencies()` loads Tom Select, EasyMDE, Tabulator, Marked, KaTeX, and CSS from CDN.

**Impact**

Initial admin load can feel slow. Network issues break authoring.

**Fix**

Bundle vendor assets through Vite and split chunks per dashboard tab.

### [Medium] Test page loads Desmos and KaTeX for every math/test layout

**Impact**

This is acceptable for math modules, but unnecessary for non-math views. R&W can defer calculator-specific resources.

**Fix**

Conditionally load Desmos only for math modules.

### Quick Wins

- Move sessions/cache/queue to Redis.
- Add indexes for status/owner/common filters.
- Redirect completed tests directly to result page.
- Remove inaccurate autosave copy until autosave exists.
- Bundle admin dependencies.
- Add module-only data loading.

### Medium Effort Improvements

- Attempt lifecycle refactor.
- Per-question autosave.
- Per-tab dashboard APIs.
- Teacher assignment/class model.
- Server-side time enforcement.

### High Impact Improvements

- Tenant model.
- Full teacher analytics.
- Redis + Horizon.
- Object storage + CDN.
- Monitoring and audit logs.

---

## 8. Security Audit

## 8.1 What Is Good

- Laravel CSRF protection is used in Blade forms and fetch requests.
- Email verification exists.
- Passwords use Laravel hashing.
- Policies exist for key content entities.
- File upload validates image mime/size in `MediaController`.
- ZIP import checks for path traversal.
- Markdown rendering strips unsafe HTML in the Blade directive.

## 8.2 Security Issues

### [Critical] Teacher role can be self-selected

**Evidence**

Signup accepts `role` as `student` or `teacher`.

**Impact**

Any user can gain teacher dashboard access. In a SaaS platform, that is a role escalation design flaw even if not a code exploit.

**Fix**

Only allow public signup as student. Teacher role must be invitation or approval based.

### [High] Tenant isolation is absent

**Evidence**

Authorization is owner/public based; no organization boundary exists.

**Impact**

Once schools/customers exist, data isolation cannot be guaranteed.

**Fix**

Every tenant-owned table should have `organization_id`, and policies must include tenant checks.

### [High] Incomplete authorization risk around module/question write flows

**Evidence**

Some flows call `Module::findOrFail()` directly for target module and then attach/import questions. Visibility checks are not consistently paired with update/ownership checks.

**Impact**

A teacher may be able to mutate relationships involving resources they should only view.

**Fix**

Every mutation should call policy checks on the target resource:

```php
$module = Module::findOrFail($validated['module_id']);
$this->authorize('update', $module);
```

### [High] Timed test integrity is not enforceable

Client-side timers are not security. For assigned timed tests, server deadlines are required.

### [Medium] Secrets and environment management are not production-hardened

**Evidence**

`.env.example` exists and config files are standard, but no documented production secret process appears in the codebase.

**Fix**

Use:

- platform secret manager;
- no committed `.env`;
- deployment-time env injection;
- key rotation runbook.

### [Medium] Runtime CDN dependencies increase supply-chain and availability risk

**Fix**

Pin, bundle, and use SRI where external scripts are unavoidable.

### [Medium] "Disable copy/context menu" is not security

This should not be marketed as secure testing. It can remain a UX guard in preview mode, but real proctoring needs a separate approach.

---

## 9. Production Readiness

## Production Readiness Score: 38 / 100

### Why Not Ready

- No real SaaS tenant model.
- No teacher assignment/class workflow.
- No autosave/resume guarantee.
- Client-side timer.
- Database-backed queue/cache/session defaults.
- No monitoring/alerting.
- No backup/recovery runbook.
- No CI/CD pipeline evidence.
- No worker/scheduler supervisor config.
- No admin ops dashboard.
- No audit logging.
- Local media storage by default.

### What Is Ready Enough For Pilot

- Basic auth and roles.
- Test taking works for controlled usage.
- Content dashboard can create/manage SAT content.
- Scoring job exists.
- Unit/feature tests exist for scoring, auth role behavior, schema, imports, ownership, media upload, cloning.

### Required Before Paid Production

1. Redis for cache/session/queue.
2. Object storage for media.
3. Attempt/autosave/server timer.
4. Teacher classes and assignments.
5. Admin user/org management.
6. Audit logs.
7. Monitoring/log aggregation.
8. Backups and restore test.
9. Deployment runbook.
10. Security hardening and role approval.

---

## 10. Deployment Audit: Ubuntu / Nginx / PHP-FPM / MySQL

The project can likely be deployed manually because it is a standard Laravel app, but the repository does not include enough production deployment automation or operational config to call it deployment-ready.

### Required Server Components

- Ubuntu LTS.
- Nginx.
- PHP 8.2+ with extensions:
  - `pdo_mysql`
  - `mbstring`
  - `openssl`
  - `tokenizer`
  - `xml`
  - `ctype`
  - `json`
  - `bcmath`
  - `fileinfo`
  - `zip`
  - `gd` or `imagick`
  - `redis` if Redis is used.
- Composer.
- Node.js/npm for Vite build.
- MySQL 8+.
- Redis.
- Supervisor or systemd workers.

### Missing / Needed Config

| Area           | Status                                | Required Action                                                       |
| -------------- | ------------------------------------- | --------------------------------------------------------------------- |
| Nginx config   | Not present                           | Add production virtual host.                                          |
| PHP-FPM config | Not present                           | Tune workers and upload limits.                                       |
| Queue worker   | README says run queue worker manually | Add Supervisor/systemd config.                                        |
| Scheduler      | No production cron documented         | Add `php artisan schedule:run` cron if scheduled jobs are introduced. |
| Storage link   | Required for public media             | Run `php artisan storage:link`.                                       |
| Cache config   | Defaults to database                  | Use Redis in production.                                              |
| Sessions       | Defaults to database                  | Use Redis in production.                                              |
| Backups        | Not present                           | Add DB and media backups.                                             |
| Monitoring     | Not present                           | Add logs, metrics, uptime checks.                                     |
| CI/CD          | Not present                           | Add test/build/deploy pipeline.                                       |

### Minimal Deployment Checklist

1. Provision Ubuntu server.
2. Install Nginx, PHP-FPM, MySQL, Redis, Composer, Node.js.
3. Create database and database user.
4. Clone repository.
5. Install dependencies:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

1. Create `.env` with production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
DB_CONNECTION=mysql
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
FILESYSTEM_DISK=public
```

1. Generate app key if first deploy:

```bash
php artisan key:generate
```

1. Run migrations:

```bash
php artisan migrate --force
```

1. Link storage:

```bash
php artisan storage:link
```

1. Optimize Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

1. Configure Nginx root to `public/`.
2. Configure Supervisor/systemd for queue workers.
3. Configure log rotation.
4. Configure backups.
5. Smoke-test auth, test-taking, scoring, media upload, queue worker.

### Example Nginx Site

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/digital-sat/public;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Example Supervisor Worker

```ini
[program:digital-sat-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/digital-sat/artisan queue:work redis --sleep=1 --tries=3 --timeout=120
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/digital-sat-worker.log
stopwaitsecs=3600
```

---

## 11. DevOps Review

### CI/CD Readiness

Current repo has tests and package scripts, but no visible CI workflow.

Recommended CI pipeline:

```yaml
steps:
  - composer install
  - npm ci
  - npm run build
  - php artisan test
  - php artisan config:clear
  - php artisan route:clear
```

### Docker Readiness

Laravel Sail is in dev dependencies, but there is no production Docker setup.

Minimal professional setup:

- app container with PHP-FPM;
- Nginx container;
- MySQL managed service or container for local only;
- Redis;
- queue worker container;
- scheduler container.

### Environment Separation

Needed environments:

- local;
- staging;
- production.

Each should have separate:

- database;
- Redis;
- mail provider;
- storage bucket;
- app key;
- domain;
- queue workers.

### Rollback Strategy

Current rollback strategy is not documented.

Required:

- release directory or container versioning;
- DB backup before migrations;
- backward-compatible migrations;
- asset versioning;
- queue drain/restart process;
- rollback runbook.

---

## 12. Scalability Assessment

## 12.1 1,000 Users

**Verdict:** feasible with a modest production setup, not with casual local-style operations.

Required:

- 1 app server or small PaaS instance;
- MySQL with backups;
- Redis for session/cache/queue;
- queue worker;
- object storage or reliable persistent disk;
- monitoring.

Main risk at 1,000:

- students losing progress;
- queue worker not running;
- local storage/media issues;
- teacher workflow gaps.

## 12.2 10,000 Users

**Verdict:** not suitable as-is.

Required refactors:

- Redis-backed queues/cache/session;
- horizontal app workers;
- object storage + CDN;
- attempt lifecycle tables;
- per-question autosave;
- server-side timing;
- indexes for dashboard/search/attempt queries;
- class/assignment domain;
- observability.

Main bottlenecks:

- MySQL write load from sessions/cache/queues;
- dashboard snapshot payloads;
- scoring status polling;
- broad eager loading;
- lack of attempt state persistence.

## 12.3 100,000 Users

**Verdict:** requires major platform refactor.

Laravel can still be part of the architecture, but the app needs a real SaaS platform design:

- load-balanced app servers;
- Redis cluster or managed Redis;
- managed MySQL with replicas;
- object storage/CDN;
- async analytics pipeline;
- queue autoscaling;
- tenant-aware data model;
- robust admin ops;
- audit logs;
- business metrics and billing.

At this scale, likely refactors include:

- separate read models for dashboards;
- event table for attempts/answers;
- background aggregation for analytics;
- per-tenant quotas/rate limits;
- content search indexing;
- report generation jobs.

---

## 13. Over-Engineering and Features To Remove

## 13.1 Over-Engineered For Current Product Stage

| Feature                                          | Why It Is Over-Engineered Now                                                | Keep / Change                                  |
| ------------------------------------------------ | ---------------------------------------------------------------------------- | ---------------------------------------------- |
| Reusable modules with many-to-many section links | Powerful, but exposes internal SAT mechanics before teacher workflows exist. | Keep internally; hide from normal teachers.    |
| ZIP/JSON/CSV imports                             | Useful for admins, too much for standard teacher UX.                         | Admin/power-user only.                         |
| IRT-like scoring                                 | Interesting, but trust requires calibration and explainability.              | Keep, but label as estimated practice scoring. |
| Forced fullscreen/copy blocking                  | Not real security and hurts UX.                                              | Remove from practice mode.                     |
| Public/private sharing flags                     | Too simple for SaaS collaboration.                                           | Replace with tenant visibility.                |

## 13.2 Features To Remove Or Defer

- Bluebook/College Board-like branding if publicly launched.
- Ticket/device-test copy unless implemented.
- Teacher self-registration without approval.
- Low-level module linking for normal teachers.
- Public content sharing before moderation and tenant controls.
- "Automatically saved" copy until true autosave exists.

---

## 14. Missing Product Features

## 14.1 Must Have For SaaS MVP

- Organizations/schools.
- Teacher approval/invitation.
- Classes/rosters.
- Student invitations.
- Assign tests to classes.
- Due dates and availability windows.
- Attempt lifecycle.
- Autosave/resume.
- Server-side timer.
- Teacher class reports.
- Student progress trends.
- Admin user/org management.
- Basic billing or plan enforcement.

## 14.2 Should Have Soon

- CSV roster import.
- Student accommodations.
- Report export.
- Weak-area practice generation.
- Assignment reminders.
- Content review/publishing workflow.
- Audit logs.
- Queue/admin health dashboard.

## 14.3 Can Improve Later

- Advanced adaptive scoring calibration.
- Real-time live proctor dashboard.
- Parent accounts.
- LMS integrations.
- SSO.
- Question search indexing.
- AI-generated practice recommendations.

---

## 15. Prioritized Roadmap

## Priority 1: Must Fix Before Production

### P1.1 Add real attempt model and autosave

**Severity:** Critical  
**Affected files:** `TestTakingController`, `resources/js/test/navigation.js`, `resources/js/test/timer.js`, `user_tests` migrations.

**Why it matters**

No serious testing SaaS can lose a student's work.

**Concrete fix**

- Add `test_attempts`.
- Add `attempt_modules`.
- Add `attempt_answers`.
- Autosave answers per question.
- Store module deadlines server-side.

### P1.2 Add teacher/class/assignment model

**Severity:** Critical  
**Affected files:** new migrations, new controllers, routes, teacher dashboard views.

**Why it matters**

Without assignments, teachers cannot use the product with real students.

**Concrete fix**

Build:

- classes;
- enrollments;
- assignments;
- assignment attempts;
- teacher reports.

### P1.3 Replace teacher self-registration with approval/invitation

**Severity:** Critical  
**Affected files:** `RegisterWebController`, signup Blade views, user role migration/model.

**Why it matters**

Role escalation is currently a product design flaw.

**Concrete fix**

Allow self-registration only as student. Teacher access requires admin or organization invite.

### P1.4 Move cache/session/queue to Redis

**Severity:** High  
**Affected files:** `.env.example`, `config/session.php`, `config/cache.php`, `config/queue.php`, deployment docs.

**Why it matters**

MySQL should not carry session/cache/queue load in production.

**Concrete fix**

Production env:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

### P1.5 Add production operations baseline

**Severity:** High  
**Affected files:** deployment docs, infrastructure config.

**Why it matters**

Without backups, monitoring, queue supervision, and error tracking, production incidents will be blind.

**Concrete fix**

- Supervisor workers.
- Backups.
- Log aggregation.
- Error tracking.
- Uptime checks.
- Failed job alerts.

## Priority 2: Should Fix Soon

### P2.1 Add tenant-safe content ownership

**Severity:** High  
**Affected files:** visibility scopes in `Test`, `Section`, `Module`, `Question`; ownership migration.

**Fix**

Replace `is_public` with scoped visibility and `organization_id`.

### P2.2 Simplify teacher content creation UX

**Severity:** Medium  
**Affected files:** `resources/views/test-dashboard.blade.php`, dashboard components.

**Fix**

Default to one wizard. Hide sections/modules/linking as advanced.

### P2.3 Improve dashboard data loading

**Severity:** Medium  
**Affected files:** `TestDashboardController`, dashboard JS modules.

**Fix**

Use per-tab paginated APIs, not broad snapshots.

### P2.4 Bundle dashboard dependencies

**Severity:** Medium  
**Affected files:** `resources/js/test/dashboard/utils/helpers.js`, `package.json`, Vite config.

**Fix**

Install and import dependencies via npm/Vite instead of loading critical scripts from CDN.

### P2.5 Add audit logs

**Severity:** Medium  
**Affected files:** new migration/model/service.

**Fix**

Record role changes, content edits, deletes, assignment changes, attempt overrides, and admin impersonation.

## Priority 3: Can Improve Later

### P3.1 Calibrate scoring

**Severity:** Medium  
**Affected files:** `SatScoringService`, score conversion seeders/tables.

**Fix**

Use validated conversion tables and versioned scoring models. Explain score confidence to users.

### P3.2 Add advanced analytics

**Severity:** Medium  
**Fix**

Add class heatmaps, item analysis, time-per-question, longitudinal trends, and weak-area practice generation.

### P3.3 Add professional DevOps

**Severity:** Medium  
**Fix**

CI/CD, staging, blue-green deploys, migration safety checks, containerized workers, secrets management.

---

## 16. Final Verdict

The project is impressive as a Digital SAT simulation and content-authoring MVP. The student test engine and content CMS show significant work. However, the product is not yet a SaaS educational platform in the practical sense.

The next milestone should not be more import formats, more module abstractions, or more Bluebook mimicry. The next milestone should be:

1. reliable attempts;
2. autosave/resume;
3. server-side timing;
4. classes and assignments;
5. teacher analytics;
6. admin operations;
7. Redis/object storage/monitoring.

Once those exist, the project can credibly move from internal beta to production candidate.
