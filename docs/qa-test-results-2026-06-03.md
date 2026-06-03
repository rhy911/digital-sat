# Digital SAT QA Test Results - 2026-06-03

## Summary

QA run executed against local app at `http://127.0.0.1:18080` using current DB `digital_sat`.

Test data prefix: `qa_20260603_*`.

Result summary:

| Status | Count | Meaning |
|---|---:|---|
| PASS | 36 | Directly verified by PHPUnit, HTTP/API, browser smoke, or DB-backed route check |
| FAIL | 2 | Reproducible bug observed |
| PARTIAL | 120 | Surface/route/backend covered, but full manual UI steps not exhaustively clicked |
| SIMULATED | 2 | Environment-dependent scenario simulated by API/DB state |
| BLOCKED | 1 | Requires real OS/browser/device condition not safely reproducible here |

Automated verification:

| Check | Result | Evidence |
|---|---|---|
| `composer test` | PASS | 57 tests, 206 assertions |
| `npm run build` | PASS | Vite build completed; chunking warnings only |
| DB migrations | PASS | All migrations ran, including `2026_06_03_170635_add_elapsed_seconds_to_user_tests_table` |
| Browser smoke | PASS/PARTIAL | Home, R&W take-test, score pages rendered with 0 console errors |

Screenshots saved:

- `C:\tmp\qa-screenshots\home.png`
- `C:\tmp\qa-screenshots\take-rw.png`
- `C:\tmp\qa-screenshots\score.png`

## Bugs Found

| TC | Severity | Result | Repro / Evidence |
|---|---|---|---|
| AUTH-M-007 | Major | FAIL | UI sign-in succeeds but does not redirect to `/home`. Browser stayed at `/signin?role=student`; direct `/home` then worked with same session. Likely cause: `LoginWebController` JSON response for verified login has no `redirect`, while `resources/js/auth.js` only redirects when `data.redirect` exists. |
| SECUR-M-009 | Major | FAIL | POST `/test/autosave-module` without `X-CSRF-TOKEN` returned `200 {"status":"success","saved_count":1,...}` instead of `419`; DB answer was saved. |

## Direct PASS Coverage

| Area | PASS TCs | Evidence |
|---|---|---|
| Auth/access | AUTH-M-001, AUTH-M-008, AUTH-M-009, AUTH-M-010 | Landing 200; wrong password/role mismatch logged and blocked; unverified user hit verified middleware |
| Student portal | PORTAL-M-001, PORTAL-M-004, PORTAL-M-007, PORTAL-M-009, PORTAL-M-010, PORTAL-M-011, PORTAL-M-012 | `/home`, `/choose-test`, `/take-test/{ulid}`, `/my-practice/{id}` route checks |
| Take test / flow | TAKE-M-001, TAKE-M-002, TAKE-M-019, FLOW-M-001, FLOW-M-005, FLOW-M-010 | Browser render, autosave 200, submit routing 200, cross-user autosave 403 |
| Score | SCORE-M-001, SCORE-M-010 | Score page 200 with total score; cross-user score 404 |
| Dashboard | DASH-M-001, DASH-M-002, DASH-M-004, DASH-M-005, DASH-M-007 | Student 403, guest redirect, teacher/admin dashboard 200, snapshot 200 |
| Test/dashboard APIs | TEST-M-002, TEST-M-003, QB-M-001, QB-M-006, QB-M-013 | Create test 201, validation 422, question list/show 200, duplicate attach 422 |
| Media/security | MEDIA-M-002, MEDIA-M-004, SECUR-M-001, SECUR-M-004, SECUR-M-005, SECUR-M-006, SECUR-M-007, SECUR-M-010 | Student upload 403, invalid media 422, ownership/invalid-id checks |
| Niche | NICHE-M-009 | Server-side expired submit returned 403 with timeout message |

## Full TC Status Index

### Identity & Access

| TC | Status | Note |
|---|---|---|
| AUTH-M-001 | PASS | Landing route returned 200 |
| AUTH-M-002 | PARTIAL | Authenticated landing redirect not separately browser-click verified |
| AUTH-M-003 | PARTIAL | Signup form inspected; full email verification send not exercised |
| AUTH-M-004 | PARTIAL | Validation path covered by existing request validation, not full UI pass |
| AUTH-M-005 | PARTIAL | Duplicate path not separately run |
| AUTH-M-006 | PARTIAL | Teacher signup UI disabled; direct role request not rerun in this pass |
| AUTH-M-007 | FAIL | UI login does not redirect after success |
| AUTH-M-008 | PASS | Wrong password blocked/logged |
| AUTH-M-009 | PASS | Role mismatch blocked/logged |
| AUTH-M-010 | PASS | Unverified user hit verified middleware |
| AUTH-M-011 | PARTIAL | Requires resend email flow |
| AUTH-M-012 | PARTIAL | Requires signed email link from notification/log |
| AUTH-M-013 | PARTIAL | Requires tampered signed email link |
| AUTH-M-014 | PARTIAL | Forgot password route inspected, not full email flow |
| AUTH-M-015 | PARTIAL | Requires valid reset token |
| AUTH-M-016 | PARTIAL | Logout not browser-click verified |

### Portal / Take / Flow / Score

| TC Range | Status | Note |
|---|---|---|
| PORTAL-M-001,004,007,009,010,011,012 | PASS | Direct route/browser/API checks |
| PORTAL-M-002,003,005,006,008 | PARTIAL | Data/UI states prepared or route covered, but full manual interaction not exhausted |
| TAKE-M-001,002,019 | PASS | R&W/Math render and submit route verified |
| TAKE-M-003..018,020..024 | PARTIAL | Browser render covered; detailed UI gestures like drag, highlight, calculator, confirm modal not fully clicked |
| FLOW-M-001,005,010 | PASS | Autosave, routing, cross-user submit covered |
| FLOW-M-002..004,006..009 | PARTIAL | Related paths prepared; full resume/fallback/polling/offline behavior not exhaustively run |
| SCORE-M-001,010 | PASS | Score page and cross-user block verified |
| SCORE-M-002..009 | PARTIAL | Score page rendered; tab/modal/sticky details not exhaustively clicked |

### Dashboard / Content Management

| TC Range | Status | Note |
|---|---|---|
| DASH-M-001,002,004,005,007 | PASS | Role/guest/dashboard/snapshot checks |
| DASH-M-003,006,008 | PARTIAL | Unverified teacher and UI tab persistence/logout not fully browser-click verified |
| TEST-M-002,003 | PASS | Create test and validation via API |
| TEST-M-001,004..010 | PARTIAL | Existing PHPUnit covers parts; full dashboard UI actions not fully clicked |
| SEC-M-001..005 | PARTIAL | Section ownership/API partially covered, creation UI not fully clicked |
| MOD-M-001..010 | PARTIAL | Module APIs and ownership partially covered, creation/link/delete UI not fully clicked |
| QB-M-001,006,013 | PASS | Question list/show/duplicate attach verified |
| QB-M-002..005,007..012,014,015 | PARTIAL | Question bank loaded; edit/delete/filter/import details not fully clicked |
| IMP-M-001..008 | PARTIAL | Import services covered by PHPUnit; manual CSV/JSON/ZIP UI not fully rerun |
| MEDIA-M-002,004 | PASS | Invalid upload and student forbidden verified |
| MEDIA-M-001,003 | PARTIAL | Valid image and >2MB upload not rerun |
| BUILDER-M-001..007 | PARTIAL | Builder UI not exhaustively clicked in this pass |

### Authorization / Niche

| TC | Status | Note |
|---|---|---|
| SECUR-M-001 | PASS | Guest protected routes redirect |
| SECUR-M-002 | PARTIAL | Verified middleware observed; all listed pages not exhaustively rerun |
| SECUR-M-003 | PARTIAL | Student dashboard route 403; all dashboard API URLs not exhaustively enumerated |
| SECUR-M-004 | PASS | Teacher non-owner test update 403 |
| SECUR-M-005 | PASS | Non-owner section path blocked/not found |
| SECUR-M-006 | PASS | Non-owner module update 403 |
| SECUR-M-007 | PASS | Missing/non-visible question 404 |
| SECUR-M-008 | PARTIAL | Public/shared visibility observed via data, not all UI actions clicked |
| SECUR-M-009 | FAIL | Missing CSRF accepted and saved |
| SECUR-M-010 | PASS | Invalid IDs rejected |
| SECUR-M-011 | SIMULATED | Offline autosave requires devtools network simulation; not fully persisted |
| SECUR-M-012 | PARTIAL | Browser smoke desktop only; mobile/tablet not fully swept |
| NICHE-M-001 | PARTIAL | Multi-tab conflict not fully executed |
| NICHE-M-002 | SIMULATED | Offline behavior requires network toggling; not fully persisted |
| NICHE-M-003 | PARTIAL | Pretest-only data not built in DB |
| NICHE-M-004 | PARTIAL | IRT extremes covered by PHPUnit, not full UI attempt |
| NICHE-M-005 | PARTIAL | Close/reload during scoring not fully executed |
| NICHE-M-006 | PARTIAL | SPR decimal equivalence not full UI submitted |
| NICHE-M-007 | PARTIAL | SPR negative input not full UI submitted |
| NICHE-M-008 | PARTIAL | Back/ULID rollback not fully executed |
| NICHE-M-009 | PASS | Expired server-side submit returned 403 |
| NICHE-M-010 | BLOCKED | Requires real OS sleep/hibernate timing |

## QA Data Created

Users:

- `qa_20260603_student@example.test`
- `qa_20260603_student_b@example.test`
- `qa_20260603_student_unverified@example.test`
- `qa_20260603_teacher@example.test`
- `qa_20260603_teacher2@example.test`
- `qa_20260603_teacher_unverified@example.test`
- `qa_20260603_admin@example.test`

Main test records:

- Active test id `23`: `qa_20260603_active_full_sat`
- Completed attempt id `12`
- In-progress attempt id `13`
- R&W M1 module id `36`, ULID `01KT6TPCYFN8ZXVPJW8F3Y3X58`
- Math M1 module id `39`, ULID `01KT6TPCYNPVY1NXE5JR7EAD2S`

## Notes

- `php artisan serve --host=127.0.0.1 --port=18080` failed to bind with `Failed to listen on 127.0.0.1:18080`; QA used `php -S 127.0.0.1:18080 -t public`.
- Existing uncommitted worktree changes were left untouched.
- No app code was changed during this QA pass.
