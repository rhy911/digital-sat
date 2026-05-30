# Code Review — Digital SAT Platform
**Reviewer:** Principal Engineer / Tech Lead (10 năm Production)
**Scope:** Security · Performance · Architecture · Scalability
**Standard:** Production-grade, không châm chước cho personal project nếu có plan deploy thật

---

## 🔴 CRITICAL — Có thể gây lộ data hoặc sập app ngay

---

### 1. **IDOR hoàn toàn trong `submitModule`** — `TestTakingController.php:57`

```php
$userTest = UserTest::findOrFail($validated['user_test_id']);
```

**Vấn đề:** `SubmitModuleRequest` chỉ validate `exists:user_tests,id` — không kiểm tra `user_test.user_id === auth()->id()`. Bất kỳ student đã login nào cũng có thể submit answers vào bài thi của người khác bằng cách đổi `user_test_id` trong request body.

**Hậu quả:** Ghi đè điểm, routing quyết định cho user khác. Kẻ tấn công có thể manipulate kết quả thi của toàn bộ student.

**Fix:**
```php
$userTest = UserTest::where('id', $validated['user_test_id'])
    ->where('user_id', auth()->id())
    ->firstOrFail();
```

---

### 2. **IDOR trong `startTest`** — `TestTakingController.php:34`

```php
$userTest = UserTest::firstOrCreate([
    'user_id' => $user->id,
    'test_id' => $test->id,
    'status' => 'in_progress',
]);
```

**Vấn đề:** `firstOrCreate` với 3 điều kiện: nếu user đã có bản ghi `completed`, sẽ tạo bản ghi `in_progress` thứ hai cho cùng test. Không check `Test::status === 'active'` trước. Student có thể start bất kỳ test nào kể cả `draft`.

**Fix:**
```php
$test = Test::where('id', $testId)->where('status', 'active')->firstOrFail();
// Dùng updateOrCreate với chỉ 2 điều kiện (user_id + test_id)
```

---

### 3. **API endpoint `/students` hoàn toàn public** — `routes/api.php:30-31`

```php
Route::get('/students', [UserController::class, 'get_data']);
Route::post('/students', [UserController::class, 'insert_data']);
```

**Vấn đề:** Không có middleware `auth:sanctum`. GET `/students` trả về danh sách toàn bộ user. POST `/students` tạo user mới không cần login. **Đây là data leak ngay lập tức.** Bất kỳ ai cũng gọi được.

> [!CAUTION]
> Endpoint này hiện tại expose toàn bộ user list ra internet không cần authentication. Fix ngay lập tức trước khi deploy bất cứ thứ gì.

**Fix:** Wrap vào `Route::middleware('auth:sanctum')->group()` hoặc xoá nếu không dùng.

---

### 4. **Email verification dùng `sha1` — không có timing-safe và dễ brute-force** — `VerifyEmailWebController.php:20`

```php
sha1($user->getEmailForVerification())
```

**Vấn đề:** Bluebook/production verification phải dùng HMAC-signed URL có expiry. `sha1(email)` là static — link không bao giờ hết hạn. Kẻ tấn công biết email → tính được hash → verify bất kỳ account nào mà không cần nhận email.

**Fix:** Dùng Laravel built-in `URL::temporarySignedRoute()` thay vì tự implement.

---

### 5. **`@ini_set('memory_limit', '512M')` trong production code** — `BulkQuestionImportService.php:32`

```php
@ini_set('memory_limit', '512M');
```

**Vấn đề:** Đây là red flag production. `@` suppress error. Nếu server không cho phép ini_set (shared hosting, restricted PHP), line này silent fail và user không biết. 512MB per request → 10 concurrent imports = 5GB RAM. Trên một shared/small VPS, đây là OOM killer.

**Fix:** Xử lý file lớn bằng streaming/chunking. Nếu cần, đẩy vào Queue job với dedicated worker có memory limit riêng.

---

## 🟠 HIGH — Lỗi logic nghiêm trọng, rủi ro cao

---

### 6. **N+1 query trong `checkAnswer` loop** — `TestTakingController.php:63-73`

```php
foreach ($submittedAnswers as $questionId => $answer) {
    $question = Question::find($questionId); // ← N queries
    ...
    $correctChoice = $question->answerChoices()->where('is_correct', true)->first(); // ← N queries
    $correctAnswers = $question->sprCorrectAnswers->pluck('answer')... // ← N queries
}
```

**Vấn đề:** Module có 27 câu hỏi → 27×3 = 81 queries chỉ để grade một module. Với concurrent users, DB sẽ bị quá tải.

**Fix:**
```php
$questionIds = array_keys($submittedAnswers);
$questions = Question::with(['answerChoices', 'sprCorrectAnswers'])
    ->whereIn('id', $questionIds)
    ->get()
    ->keyBy('id');
```

---

### 7. **Log raw user data lên production log** — `TestTakingController.php:52` & `RegisterWebController.php:34`

```php
Log::info("Raw submitModule input: " . json_encode($request->all()));
// Và:
Log::info('User created via Web', $user->toArray());
```

**Vấn đề:** `$request->all()` chứa toàn bộ answer data của student. `$user->toArray()` chứa `email`, `username`, metadata nhạy cảm. Production logs thường được collect bởi nhiều team, alert system, third-party log aggregators. Không nên log PII/test content.

**Fix:** Log có chọn lọc: `Log::info("submitModule", ['user_test_id' => ..., 'module_id' => ...])`.

---

### 8. **Lộ exception message ra client** — `TestTakingController.php:189`, `TestDashboardController.php:700`

```php
return response()->json([
    'error' => 'Server error during submission.',
    'message' => $e->getMessage()  // ← Lộ stack trace/DB error
], 500);
```

**Vấn đề:** `$e->getMessage()` có thể chứa SQL query, file path, class name — thông tin attackers dùng để fingerprint stack. Các lỗi như `SQLSTATE[42S02]: Base table not found` chỉ thẳng vào cấu trúc DB.

**Fix:**
```php
Log::error('submitModule failed', ['exception' => $e]);
return response()->json(['error' => 'Server error. Please try again.'], 500);
```

---

### 9. **`choose-test` route không có auth middleware** — `routes/web.php:80-85`

```php
Route::get('choose-test', function () {
    $tests = Test::where('status', 'active')...->get();
    return view('tests.choose', compact('tests'));
})->name('choose-test');
```

**Vấn đề:** Route này nằm ngoài `auth` middleware group. Bất kỳ khách nào (không login) cũng thấy danh sách test active. Còn route `/take-test/{module_id?}` cũng không có `auth` — line 87.

---

### 10. **`take-test` route chứa business logic khổng lồ trong closure** — `routes/web.php:87-187`

100 dòng logic trong route closure, bao gồm: Eloquent eager loading, data transformation, `firstOrCreate` UserTest, view selection. Đây là **anti-pattern nghiêm trọng**:
- Không thể test (không có controller method để unit test)
- Không thể reuse
- Routes file trở thành "fat routes" không maintain được

**Fix:** Extract sang `TestTakingController::showModule()`.

---

### 11. **`SubmitModuleRequest::authorize()` luôn return `true`** — `SubmitModuleRequest.php:10`

```php
public function authorize(): bool {
    return true;
}
```

Authorization phải ở đây, không phải hardcode `true`. Kết hợp với bug IDOR #1, đây là lỗ hổng kép.

---

## 🟡 MEDIUM — Code quality, maintainability, và scalability

---

### 12. **`TestDashboardController` quá fat — 884 dòng, 26 methods** — `TestDashboardController.php`

Một controller làm tất cả: CRUD tests, sections, modules, questions, bulk import, ZIP import, generate SAT structure, clone, delete. Vi phạm Single Responsibility.

**Nên split thành:**
- `TestController` — test CRUD
- `SectionController` — section CRUD
- `ModuleController` — module CRUD  
- `QuestionController` — question CRUD + bulk import
- `TestStructureController` — generate, clone

---

### 13. **`UserTest` model thiếu `rw_m2_path`, `math_m2_path` trong `$fillable`** — `UserTest.php`

```php
protected $fillable = [
    'user_id', 'test_id', 'score_reading_writing', 'score_math',
    'total_score', 'status', 'completed_at',
    // MISSING: 'rw_m2_path', 'math_m2_path', 'rw_theta', 'math_theta'
];
```

`TestTakingController.php:92-95` set `$userTest->rw_m2_path = $path` trực tiếp vào property. Hoạt động được vì Laravel mass-assignment chỉ chặn `create()`/`fill()`, nhưng `$userTest->update([...])` ở `finalizeTest:219` sẽ **silent ignore** `rw_theta`, `math_theta` nếu không có trong `$fillable`. Dữ liệu theta không được lưu vào DB.

**Fix:** Thêm `rw_m2_path`, `math_m2_path`, `rw_theta`, `math_theta` vào `$fillable`.

---

### 14. **Scoring recalculate theta từ đầu thay vì dùng routing decision đã lưu** — `SatScoringService.php:31-32`

```php
$thetaM1 = $this->estimateTheta($m1);
$m2Path = $this->routeModule2($thetaM1); // ← Recompute M2 path
```

`scoreSection()` tính lại M2 routing thay vì đọc `rw_m2_path`/`math_m2_path` đã lưu từ lúc submit Module 1. Nếu có floating-point drift hoặc pretest filtering khác, path tính lại có thể khác path student thực sự đã đi → **sai điểm**.

Chữ ký của hàm nên nhận `$m2Path` đã lưu:
```php
public function scoreSection(Collection $m1, Collection $m2, string $m2Path): array
```

---

### 15. **`MediaController` không validate MIME bằng server-side magic bytes** — `MediaController.php:14`

```php
'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
```

Laravel `mimes:` validation dựa trên file extension + MIME type từ header — có thể spoofed. SVG đặc biệt nguy hiểm: SVG chứa JavaScript, có thể gây XSS khi served từ same domain.

**Fix:** Loại `svg` khỏi danh sách, và dùng `finfo_file()` để verify magic bytes thực sự.

---

### 16. **`snapshot()` dump toàn bộ data không paginate** — `TestDashboardController.php:86-97`

```php
$tests = Test::visibleTo(auth()->user())->with([...])->latest()->get();
$passages = Passage::latest()->get();
$allModules = Module::visibleTo(auth()->user())->with([...])->latest()->get();
```

`->get()` không có limit. Nếu admin có 1000 tests, 500 modules, response JSON lên đến hàng MB mỗi lần refresh dashboard. Không cache. **Sẽ gây timeout ở quy mô lớn.**

---

### 17. **Role check bằng string comparison, không dùng Gate/Policy** — khắp nơi

```php
if (auth()->user()->role === 'teacher' && ...) { abort(403); }
```

Pattern này xuất hiện ít nhất 15 lần trong `TestDashboardController`. Nếu sau này đổi tên role hoặc thêm role mới, phải tìm và sửa ở 15+ chỗ. Không có central authorization logic.

**Fix:** Implement `Policy` classes và dùng `$this->authorize('update', $test)`.

---

### 18. **`LoginController` (API) tạo Sanctum token ngay cả khi dùng session auth** — `LoginWebController.php:79`

```php
'token' => $user->createToken('api-token')->plainTextToken,
```

Web app dùng session-based auth (cookie). Tạo thêm Sanctum token không cần thiết, token không được revoke sau đó → token leak. Mỗi login qua AJAX tạo thêm một token. Sau 100 logins, user có 100 tokens active trong DB.

---

### 19. **`RegisterWebController` log toàn bộ request data** — `RegisterWebController.php:17`

```php
Log::info('RegisterWeb called', $request->all());
```

`$request->all()` tại thời điểm register chứa: `password`, `password_confirmation` dạng plaintext. **Không bao giờ log password dù hash hay không.**

---

### 20. **`BulkQuestionImportService`: ZIP path traversal potential** — `BulkQuestionImportService.php:61-68`

```php
$tempDir = 'temp/import_' . Str::random(10);
Storage::makeDirectory($tempDir);
$tempPath = storage_path('app/' . $tempDir);
if (!$zip->extractTo($tempPath)) { ... }
```

ZIP files có thể chứa entries với paths như `../../config/.env`. `ZipArchive::extractTo()` trên nhiều PHP versions không strip `../` sequences tự động. Kẻ tấn công upload malicious ZIP → overwrite `.env`.

**Fix:**
```php
foreach (range(0, $zip->numFiles - 1) as $i) {
    $name = $zip->getNameIndex($i);
    if (str_contains($name, '..') || str_starts_with($name, '/')) {
        throw new \Exception('Malicious ZIP entry detected.');
    }
}
```

---

## 🔵 LOW — Code style, minor issues

---

### 21. **Naming convention vi phạm PSR-12** — `UserController.php`

```php
public function get_data() {}   // snake_case — vi phạm PSR-12
public function insert_data() {}
```

Tất cả methods phải là `camelCase` theo PSR-12 và project convention.

---

### 22. **`choose-test` route hardcode title** — `routes/web.php:82`

```php
->where('title', '!=', 'Test Preview')
```

Business logic hardcode string vào route. Nên dùng enum/constant hoặc `is_preview` boolean column.

---

### 23. **`SatScoringService`: `estimateTheta` không convergence-safe với extreme responses**

Nếu toàn bộ responses là correct nhưng một câu có IRT params bất thường (e.g., `irt_a = 10.0`), Newton-Raphson vẫn chạy 30 iterations và có thể diverge trước khi clamp tại `[-4, 4]`. Nên log warning khi iterations max out.

---

## 📋 Tổng kết

| Mức độ | Số lỗi | Hành động |
|--------|--------|-----------|
| 🔴 Critical | 5 | Fix trước khi deploy |
| 🟠 High | 6 | Fix trong sprint tới |
| 🟡 Medium | 9 | Refactor có kế hoạch |
| 🔵 Low | 3 | Nice to have |

### Top 3 ưu tiên ngay lập tức:
1. **API `/students` public** → Data leak hiện tại
2. **IDOR trong `submitModule`** → Manipulation thi cử
3. **Email verification dùng sha1 static** → Account takeover

### Kiến trúc tốt (giữ lại):
- Service layer tách biệt (`SatScoringService`, `TestManagementService`)
- FormRequest validation đúng chỗ
- Scope `visibleTo()` trên Models — đúng hướng
- DB transaction trong scoring
- Soft delete cascade

Codebase có nền tảng tốt. Lỗi chủ yếu là authorization bypass và thiếu kinh nghiệm về production security patterns — những thứ chỉ học được khi có người chỉ ra.
