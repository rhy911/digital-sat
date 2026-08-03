# Phân tích hạ tầng — nền tảng luyện thi đa kỳ thi

> Báo cáo quyết định kiến trúc. Trả lời: với mục tiêu đa kỳ thi (SAT + TSA + THPT QG) và quy mô vượt xa 1000 concurrent, **có nên đổi framework backend/frontend không?**
>
> Ngày: 2026-07-31 · Trạng thái: bản thảo để bàn luận, chưa chốt

---

## 1. Chốt view — mục tiêu

**Sản phẩm:** công cụ cho **giáo viên và trung tâm** tự tạo đề → giao bài → phân tích lớp, trên nhiều kỳ thi.
Không phải nơi bán khoá học. Thị trường VN đã đông ở mảng B2C bán đề thi thử (tuyensinh247, HOCMAI/PAT, phongluyenthi, hethonglab). Khoảng trống là **công cụ cho người dạy**.

**Mục tiêu kỹ thuật, đo được:**

> Thêm kỳ thi thứ 4 chỉ tốn **config + 1 class driver**. Tốn hơn thế = trừu tượng sai.

**4 trục biến thiên phải cắm được:**

| Trục | Ví dụ khác biệt |
|---|---|
| Cấu trúc đề | SAT 2 phần adaptive · TSA 3 phần 60/30/60 phút · THPT 1 bài/môn |
| Thuật toán chấm | SAT IRT 3PL EAP thang 400–1600 · TSA cộng điểm /100 · THPT điểm từng phần /10 |
| Dạng câu hỏi | MCQ 1 đáp án · MCQ nhiều đáp án · Đúng/Sai nhiều ý (điểm từng phần) · trả lời ngắn · kéo-thả |
| Ngôn ngữ + prose | Tiếng Anh cho SAT · tiếng Việt cho TSA/THPT |

**Ràng buộc cứng:** production đang có học sinh làm bài · fidelity Bluebook cho SAT (`PRODUCT.md` nguyên tắc #1) · nội dung lai (bank trung tâm do admin curate + giáo viên tự nhập).

---

## 2. Quy mô mục tiêu — đặt lại con số

Giả định cũ là ~1000 concurrent. Nếu tính đường dài cho thị trường VN, con số thật khác hẳn:

| Kỳ thi | Quy mô thị trường/năm |
|---|---|
| THPT Quốc gia | **~1 triệu thí sinh** |
| HSA (ĐHQGHN) | ~117.000 thí sinh |
| TSA (ĐHBK HN) | ~60.000 lượt thi |

Chiếm 1% thị trường THPT = **10.000 học sinh hoạt động**.

**Mẫu tải nguy hiểm không phải "nhiều người dùng" — mà là "thi thử toàn quốc".** Cả nghìn/chục nghìn người bấm bắt đầu **cùng lúc**, làm bài **cùng thời lượng**, và **nộp cùng lúc** vì module có hẹn giờ. Đây là **burst đồng bộ**, không phải tải đều.

Con số thiết kế nên dùng: **10.000–50.000 concurrent trong cửa sổ 60 giây**, không phải 1.000 đều.

---

## 3. Profile tải thật của app khi thi

| Giai đoạn | Tần suất | Chi phí |
|---|---|---|
| Bắt đầu module | 1 req/học sinh | Nặng vừa — render N câu + passage + media |
| Trong lúc làm (32–60 phút) | ~1 req / 30–60 giây/học sinh | **Thấp.** Autosave theo sự kiện trả lời |
| Nộp module | 1 req/học sinh, **dồn trong ~60 giây** | Bulk upsert answers + dispatch job |
| **Poll `/submit-status`** | **mỗi 1.5 giây/học sinh** cho tới khi có điểm | **Đây là điểm vỡ** |
| Job chấm điểm | 1 job/học sinh/module | EAP 3PL trên lưới θ 161 điểm |
| Sau sự kiện | Tất cả xem điểm + giáo viên mở báo cáo | Burst đọc |

Nút thắt nằm ở **cửa sổ 60 giây quanh lúc nộp**, không phải ở app nói chung.

---

## 4. Phát hiện quyết định: toàn bộ hạ tầng phụ trợ đang chạy qua MySQL

Từ `.env.example`:

```
DB_CONNECTION=mysql
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
BROADCAST_CONNECTION=log
```

`composer.json`: **không có** `octane`, `reverb`, `horizon`, `predis`, `phpredis`, `pusher`. PHP 8.2.31.

Hệ quả — **mỗi request poll `/submit-status` tốn tối thiểu 3 thao tác MySQL:**

1. `SELECT` session
2. `UPDATE` session (Laravel ghi session mỗi request)
3. `SELECT` cache (`scoring_result_{id}`)

Cộng thêm queue worker đang **poll bảng `jobs`** trên cùng MySQL đó, và answer writes cũng vào cùng MySQL đó.

### Tính ra con số

Poll mỗi 1.5 giây, N học sinh đang chờ điểm:

| Concurrent | Req/giây | Thao tác MySQL/giây (chỉ từ polling) |
|---|---|---|
| 1.000 | 667 | **~2.000** |
| 2.500 | 1.667 | ~5.000 |
| 5.000 | 3.333 | ~10.000 |
| 10.000 | 6.667 | ~20.000 |
| 50.000 | 33.333 | ~100.000 |

Một MySQL primary tuned tốt xử lý cỡ **5.000–20.000 thao tác đơn giản/giây** — và ở đây nó **còn phải làm việc chính**: ghi answers, chạy queue, đọc content.

> **Trần thật của stack hiện tại: ~1.500–2.500 concurrent.**
> Và **không phải lỗi của PHP hay Laravel.** Là lỗi **cấu hình driver**.

Điểm này quan trọng vì nó lật ngược trực giác: người ta hay kết luận "PHP không scale được, đổi Go/Node đi". Ở đây, đổi framework mà giữ `CACHE_STORE=database` thì **vẫn vỡ ở đúng chỗ đó**.

### Điều đã làm đúng sẵn

- `saveModuleAnswers` **đã bulk upsert** (gom `$upsertData` rồi ghi 1 lần), không phải N câu lệnh riêng. Tốt.
- Nộp bài đã là **save-then-dispatch async** — `submitModule` lưu đồng bộ, đẩy `ScoreModuleJob`, trả `status: scoring` ngay. Tránh 504 khi IRT chậm. Kiến trúc đúng.
- Idempotency bằng cache lock + receipt `user_test_module_submissions`. Chịu được retry và double-submit.

Nghĩa là: **hình dạng kiến trúc đã đúng, chỉ là mọi thứ đang cắm vào sai backing store.**

---

## 5. Ngưỡng vỡ theo quy mô, và cách sửa

| Quy mô | Cái gì vỡ | Sửa | Đổi framework? |
|---|---|---|---|
| **< 2.000** | Chưa vỡ | Vài queue worker | Không |
| **2.000–10.000** | MySQL nghẹt vì cache/session/queue | **Redis** cho cả 3. Poll có jitter + backoff. Read replica cho báo cáo. Queue worker ngang. | **Không** |
| **10.000–50.000** | Polling vẫn quá tốn dù có Redis; bootstrap PHP-FPM tốn | **Push thay poll** (Reverb/SSE). **Octane** (Swoole/RoadRunner). CDN cho media. | **Không** |
| **> 50.000** | Ghi DB, giữ kết nối, phân vùng | Shard theo `organization_id` / theo sự kiện. Có thể tách service push riêng. | **Cân nhắc — nhưng lúc đó có doanh thu để thuê người và có số liệu thật** |

### Chi phí từng bước sửa, xếp theo đòn bẩy

| Việc | Chi phí | Được gì |
|---|---|---|
| **Redis cho cache + session + queue** | **Đổi `.env` + thêm `predis`. Gần như zero code.** | Trần **~2k → ~10k**. Đòn bẩy cao nhất trong toàn bộ tài liệu này. |
| Poll có jitter + exponential backoff | ~20 dòng JS | Giảm tải poll 3–5 lần. Rẻ, làm ngay. |
| Laravel Horizon | Nửa ngày | Queue worker tự scale + quan sát được. Bắt buộc khi có sự kiện đồng bộ. |
| Read replica cho báo cáo | Config + `$connection` | Burst đọc sau sự kiện không đụng đường ghi |
| **Push thay poll (Reverb / SSE)** | 1–2 tuần | Polling 6.667 req/s → **~0**. Mở đường tới 50k. |
| Laravel Octane | 1 tuần + kỷ luật code (state rò rỉ giữa request) | Chi phí/request giảm ~5–10 lần |
| CDN cho ảnh câu hỏi | Vài giờ | Bắt buộc khi 10k người tải đề cùng lúc |
| Stagger giờ bắt đầu thi thử | **Giải pháp sản phẩm, gần như free** | Xoá luôn burst đồng bộ. **Nên cân nhắc trước mọi giải pháp kỹ thuật.** |

> Đọc bảng này theo thứ tự. **Redis đứng trước mọi thảo luận framework.**

---

## 6. Backend — so sánh

### Mục tiêu ép gì lên backend?

| Đòi hỏi | Phân biệt được framework không? |
|---|---|
| Driver cắm được 4 trục | **Không.** Mọi BE hiện đại có DI + interface đều làm được. Laravel service container thừa sức. |
| 10k–50k concurrent | **Không** — nút thắt là backing store + giữ kết nối, không phải ngôn ngữ |
| Giữ 10k+ kết nối long-lived (push) | **Có.** PHP-FPM không làm được (process-per-request) |
| Calibrate IRT, phân tích item | **Có.** PHP yếu thật ở mảng toán/khoa học dữ liệu |

Chỉ **2 dòng** phân biệt được framework. Cả hai đều giải được **không cần viết lại BE**.

### Bảng so sánh

| | Lợi thế với mục tiêu này | Giá phải trả | Verdict |
|---|---|---|---|
| **Laravel 12 (giữ)** | Service container thừa sức làm driver registry. 61 migration, 12.8k dòng `app/`, 7.8k dòng test đã có. Ecosystem PDF/queue/mail/Excel mạnh nhất. **Reverb** (WebSocket first-party) + **Octane** giải đúng 2 điểm yếu ở trên mà không đổi ngôn ngữ. Đội đã biết. | Type safety yếu hơn TS/Go. Toán nặng chậm. Octane đòi kỷ luật state. | ✅ **Giữ** |
| **NestJS + TS** | Chia sẻ type với FE React. Node giữ nhiều kết nối tốt hơn FPM. | Viết lại **toàn bộ** — gồm cả logic psychometrics đang chấm điểm học sinh thật. Ecosystem PDF/report/queue kém Laravel rõ. Chỉ đáng nếu FE **chắc chắn** React và muốn một ngôn ngữ. | ❌ |
| **Go** | Perf và concurrency **bạn có thể mua rẻ hơn bằng Redis + Reverb**. | Mất tốc độ dev nhiều. ORM/migration/queue/mail đều thua xa. | ❌ |
| **Elixir/Phoenix** | **Steelman mạnh nhất:** BEAM giữ hàng triệu kết nối, Phoenix Channels tốt nhất thị trường, burst đồng bộ đúng là bài toán OTP sinh ra để giải. LiveView có thể xoá bớt FE. | Viết lại hết. **Pool tuyển ở VN rất nhỏ.** Thư viện IRT gần như không có. Giải bài toán bạn **chưa có**, bằng cách vứt bỏ thứ **đang chạy và đã audit bảo mật**. | ❌ |
| **Python (Django/FastAPI)** | **Lý do thật duy nhất:** psychometrics. `girth`, `py-irt`, numpy/scipy — calibrate IRT, phân tích item, dựng phổ điểm. PHP làm mấy thứ này bằng tay. | Viết lại toàn bộ BE để lấy đúng một module. | ❌ **làm BE chính** · ✅ **làm sidecar** |

### Kết luận BE

**Giữ Laravel.** Không đòi hỏi nào trong mục tiêu ép đổi — kể cả ở 50k concurrent. Đổi thì mất tất cả, được zero đo được, và **vẫn vỡ ở cùng chỗ** nếu không sửa backing store.

Ba điều chỉnh thật sự đáng làm:

1. **Redis + Horizon.** Trước mọi thứ khác. Xem §5.
2. **PHPStan / Larastan level 6+.** Được ~80% lợi ích type safety với ~1% chi phí đổi ngôn ngữ. Hiện tại Pint nằm trong `composer.json` require-dev nhưng **không có trong `vendor/bin`** và không wire vào script nào — kỷ luật static analysis đang bằng 0.
3. **Python sidecar cho calibration — sau, không phải bây giờ.** Khi bắt đầu auto-calibrate IRT từ dữ liệu thật, tách 1 FastAPI service nhỏ chỉ làm calibration + item analysis, Laravel gọi qua queue. `SatScoringService::estimateAbility` (chấm bài lúc thi) **cứ để nguyên PHP** — nó đã chạy trong job async, và viết lại nó là rủi ro trên điểm số học sinh thật.

---

## 7. Frontend — đây mới là câu hỏi thật

### Framing lại

Repo **đã là app JS rồi, chỉ đang giả vờ không phải**:

- 11.169 dòng JS
- Engine tự cài SPA thủ công — `navigation.js:620-712` swap module client-side
- `builder.js` 1.453 dòng · `bulk-import.js` 939 dòng · `tests.js` 916 dòng
- Tabulator load từ CDN, phải hack `.dark-theme-dashboard !important` vì stylesheet CDN load sau
- **Không test runner, không linter, không types**

Câu hỏi không phải "Blade hay React". Là: **tiếp tục tự cuốn tay, hay nhận một framework?**

Mục tiêu đa kỳ thi đẩy từ 2 dạng câu → **5 dạng × ~4 bề mặt** (render khi thi · collect · review · soạn đề) ≈ **20 bề mặt UI**. Bug kiểu `collectAnswers()` **bỏ im lặng** dạng câu lạ (`navigation.js:243-259`) chính xác là loại lỗi mà types + component model chặn được.

### Lăng kính quy mô đổi gì cho FE

Ở 10k+ concurrent, chiến lược đúng là **client làm nhiều hơn để server làm ít hơn**:

- Buffer + batch answer thay vì autosave chatty
- Chịu offline — học sinh VN trên mạng di động giữa giờ thi
- Reconcile khi kết nối lại, không mất bài
- Retry có backoff, hàng đợi phía client

Đây là **client có state, có tính bền** — không còn là "Blade + vài đoạn script". Viết bằng vanilla thì làm được, nhưng đau và không có lưới an toàn nào (không test runner, không types).

Ngược lại, bundle size không phải vấn đề: React ~45KB gzip, không đáng kể so với rủi ro.

### Bảng so sánh

| | Lợi thế | Giá | Rủi ro ngày thi | Có chặn đường scale không? |
|---|---|---|---|---|
| **Blade + vanilla + TypeScript** | Rẻ nhất — TS qua Vite chỉ là config. Type contract cho collector. Không đụng gì latency-sensitive. | Không có component model → 20 bề mặt viết DOM tay. Kéo-thả vanilla đau. Client offline/reconcile rất khó viết tay. | **Zero** | Không chặn, nhưng làm client bền lên tốn hơn nhiều |
| **Blade + Alpine (mở rộng)** | Alpine đã có sẵn. Hợp form soạn đề. | Không hợp engine (cần kiểm soát chặt). Kéo-thả tệ. Không types. | Thấp | Không chặn |
| **Blade + Livewire (mở rộng)** | Đã có 1 component. Laravel-native. | **Mỗi tương tác = roundtrip server.** Đúng anti-pattern của burst đồng bộ: 10k học sinh × mỗi lần bấm = thêm request. Học sinh mạng chập chờn giữa giờ thi. | Cao | **Chặn thẳng.** Loại cho engine |
| **Inertia + React + TS** | Giữ nguyên controller/route/auth/FormRequest Laravel — **không cần viết API layer**. Mọi invariant bảo mật trong `CLAUDE.md` (ownership check trong query, `FormRequest::authorize`) **ở nguyên chỗ cũ**. Dạng câu hỏi thành component có interface typed. `dnd-kit` giải quyết kéo-thả. `TanStack Table` xoá hack Tabulator CDN. | Học Inertia. Viết lại view theo từng phần. | Tuỳ phạm vi | Không chặn — client bền dễ viết |
| **Laravel API + React SPA rời** | Tách bạch nhất, scale-friendly nhất về lý thuyết. | Phải build **toàn bộ** JSON API, Sanctum SPA mode, CSRF flow mới, validate 2 lần, routing/auth guard client. **Đây là chỗ dễ tái sinh lỗ hổng IDOR nhất** — đúng loại lỗi đợt audit 2026-05-30 vừa vá. | Cao | Không chặn |
| **Vue thay React** | Inertia gốc pair với Vue. Học dễ hơn. SFC hợp designer. | Không có tương đương ngang tầm `dnd-kit` / `TanStack Table`. Pool tuyển VN nhỏ hơn React. | — | Không chặn |

### Kết luận FE

**Nếu chọn React thì phải là Inertia, không phải API+SPA.** Inertia giữ nguyên toàn bộ tầng bảo mật đã audit. API+SPA bắt viết lại nó — và tầng đó vừa mới được vá một loạt lỗ hổng.

**Nhưng quyết định phải theo bề mặt, không theo toàn app.** Ba bề mặt, nhu cầu khác hẳn:

| Bề mặt | Áp lực | Rủi ro nếu đổi | Nên |
|---|---|---|---|
| **Engine thi** | Cao nhất — 5 dạng câu, latency, fidelity Bluebook, offline-tolerance khi scale | **Cao nhất** — học sinh đang thi thật; `PRODUCT.md` nguyên tắc #1 là fidelity 100% | Đổi **sau**, khi có dữ liệu |
| **Builder / soạn đề** | Cao — nơi độ phức tạp đa kỳ thi *thật sự bùng nổ* (soạn 5 dạng × 3 kỳ thi, gán điểm từng phần, kéo-thả) | **Gần zero** — không học sinh nào đang thi trong builder | Đổi **trước** |
| **LMS / lớp học** | Thấp — chủ yếu tĩnh, thiết kế skeuomorphic | Đổi = phá design system trong `.agents/rules/design-system.md` | **Không đụng** |

### Khuyến nghị ngược trực giác: React ở builder trước, engine sau

Lý do:

- Builder là nơi đa kỳ thi làm nổ độ phức tạp thật — soạn Đúng/Sai nhiều ý, cấu hình đường cong điểm từng phần, dựng bài kéo-thả. Đây là UI form phức tạp, đúng sở trường React.
- **Zero rủi ro ngày thi.**
- Chứng minh stack trước khi đặt cược engine vào nó.
- Xoá được hack Tabulator CDN `!important` (thay bằng TanStack Table).
- Nếu hỏng, mất builder — **không mất bài thi của học sinh**.

Rồi engine đổi sau, quyết định bằng dữ liệu vận hành thật thay vì đoán.

**Điều đúng bất kể chọn gì: bật TypeScript ngay.** Không phụ thuộc framework, giá gần bằng 0, chặn đúng loại bug đang tồn tại.

---

## 8. Quản lý state — React có thật sự giỏi khoản này?

Câu hỏi thường gặp khi cân nhắc React. Trả lời chính xác thì **đúng một nửa**.

### Chỉnh lại thông tin

**React không có "quản lý state rất tốt" built-in.** React chỉ có `useState`, `useReducer`, `useContext`. Context còn *dở* với state thay đổi liên tục — mọi consumer re-render theo.

Danh tiếng đó đến từ **hệ sinh thái**, không phải React: Redux, Zustand, Jotai, TanStack Query, XState. Đó là **thư viện**, không phải React.

Thẳng thắn: **Vue và Svelte có reactivity built-in tốt hơn React.** React đòi *nhiều* kỷ luật hơn — immutability, dependency array, `useMemo`/`useCallback` để tránh bão re-render.

Thứ React thật sự cho là `UI = f(state)` — đổi state, UI tự cập nhật, không tự tay đồng bộ DOM. Đó mới là giá trị thật, và **mọi framework hiện đại đều có**, không riêng React.

### App này đang ở đâu

`resources/js/test/state.js` (38 dòng) tên là "Centralized State" nhưng chứa **tham chiếu DOM element + bookkeeping timer**, không phải state ứng dụng:

```js
backButton: null, nextButton: null, questionElements: [],
currentQuestionIndex: 0, timeLeft: 0, timerInterval: null, ...
```

Không có `answers`. Không có `flagged`. Không có `crossedOut`. Không có `highlights`.

Chúng ở **DOM** — `resources/js/test/ui.js:73-80`:

```js
const selectedAnswer = questionElement.querySelector('input[type="radio"]:checked');
const textInput = questionElement.querySelector('input.answer-input, input.spr-input');
return !!questionElement.querySelector(".bookmark.marked");
```

Và `navigation.js` chỉ có **3 biến module-level**: `autosaveInitialized`, `autosaveTimer`, `lastAutosavePayload`.

> **Chẩn đoán: engine không phải "quản lý state kém" — nó không có tầng state nào cả. DOM chính là state store.**

Mọi thứ đọc trạng thái phải `querySelector` ngược ra DOM: nav popover, review page, nút Next, autosave. Đúng pattern vanilla kinh điển, và đúng chỗ vỡ khi state phức tạp lên.

### React thắng ở đâu, cản ở đâu

| React **thắng** | Vì sao |
|---|---|
| **Builder soạn đề** | Form lồng động: Đúng/Sai N ý, kéo-thả N slot × M target, cấu hình đường cong điểm từng phần. Vanilla form code nổ ở đúng đây. Mạnh nhất. |
| **5 dạng câu hỏi** | Mỗi dạng tự sở hữu shape state riêng. Component = ranh giới tự nhiên. |
| **Kéo-thả** | `dnd-kit` quản state drag hộ. Tự viết drag state thật sự đau. |
| **Offline/reconcile khi scale** | TanStack Query giải đúng lớp bài toán này. Không có tương đương vanilla nào không phải tự cuốn tay. |

| React **cản** | Vì sao |
|---|---|
| **Timer** | Re-render mỗi giây là lãng phí. Cuối cùng vẫn phải dùng ref + ghi DOM trực tiếp. |
| **Highlight/annotation trên passage** | Range-based DOM annotation bản chất imperative. React chống lại — phải escape hatch bằng ref. |
| **Desmos** | Widget bên thứ 3 imperative. Ref + effect. Lợi ích bằng 0. |
| **Gõ SPR** | Controlled input thêm 1 chu kỳ render mỗi phím. Footgun trên đường latency-critical. |

Đây chính là lý do khuyến nghị **builder trước, engine sau** (§7) — builder React thắng sạch, engine thì tranh chấp.

### Chỉ 1/3 vấn đề thật là bài toán state

| Vấn đề | Loại | Sửa bằng |
|---|---|---|
| `collectAnswers()` bỏ im lặng dạng câu lạ | **types** | TypeScript |
| DOM là nguồn sự thật | **state** | một store — React *tuỳ chọn* |
| Không test runner | **tooling** | Vitest |

### Khuyến nghị: tách quyết định state khỏi quyết định framework

Hoãn quyết định FE là hợp lý. Nhưng **không cần hoãn việc sửa state.**

Thêm store nhỏ **framework-agnostic** ngay:

- `nanostores` (~1KB) hoặc tự viết ~150 dòng observable store
- Giữ `answers`, `flagged`, `crossedOut`, `highlights`, `submitStatus`
- Blade/vanilla subscribe được **hôm nay**; React subscribe được **y hệt** nếu sau đổi

Lợi ích: khoá được "DOM là state" ngay, và **store không bị vứt đi dù chọn framework nào**. Nếu sau đổi React, thứ phải viết lại là *renderer*, không phải *state + logic* — cùng nguyên tắc DOM-contract đã đặt cho collector dạng câu hỏi (§9).

Việc này thuộc nhóm "làm ngay, không phụ thuộc quyết định nào khác" ở §10.

---

## 9. Ràng buộc thời điểm cho quyết định FE

Hoãn quyết định FE là hợp lý — nhưng **hoãn có deadline**.

Phase định nghĩa registry dạng câu hỏi sẽ chốt **contract collector JS**. Nếu React sẽ đến, phải biết **trước** khi viết 5 collector vanilla — nếu không, viết 5 dạng câu **hai lần**.

**Mốc an toàn: chốt FE trước khi viết dạng câu hỏi thứ 2.** Trước đó hoãn thoải mái.

**Giảm đau kiến trúc:** thiết kế contract collector thành **DOM contract**, không phải component API:

```js
{ read(rootEl), restore(rootEl, value), isAnswered(rootEl), watch(rootEl, onChange) }
```

Hợp đồng này giống hệt nhau dù subtree do Blade hay React render. Nên nếu sau này đổi, chi phí viết lại là **renderer**, không phải **logic chấm/thu đáp án**. Giữ cửa mở với giá gần bằng 0.

---

## 10. Tổng hợp khuyến nghị

### Không đổi

- **Backend: Laravel 12.** Không có đòi hỏi nào ép đổi, kể cả ở 50k concurrent.
- **LMS/lớp học: Blade.** Design system skeuomorphic phụ thuộc vào nó.
- **Psychometrics chấm bài: PHP, nguyên chỗ.** `SatScoringService::estimateAbility` đã chạy async, viết lại là rủi ro trên điểm số thật.

### Làm ngay, không phụ thuộc quyết định nào khác

| # | Việc | Vì sao trước |
|---|---|---|
| 1 | **Redis** cho `CACHE_STORE` + `SESSION_DRIVER` + `QUEUE_CONNECTION` | Đòn bẩy scale cao nhất toàn tài liệu. Trần ~2k → ~10k. Gần như zero code. |
| 2 | **CI thật sự chạy `php artisan test`** | `ci.yml` hiện chỉ có `composer audit`. 7.853 dòng test đang **không ai chạy tự động**. Không có lưới an toàn thì không được đụng scoring. |
| 3 | **TypeScript** cho `resources/js/` | Chặn đúng loại bug đang có (`collectAnswers()` bỏ im lặng dạng lạ). Độc lập với mọi quyết định framework. |
| 4 | **PHPStan/Larastan** level 6+ | 80% lợi ích type safety, 1% chi phí đổi ngôn ngữ |
| 5 | Poll có jitter + backoff | ~20 dòng JS, giảm tải poll 3–5 lần |

### Làm khi chạm ngưỡng, theo thứ tự

Horizon → read replica → **push thay poll (Reverb/SSE)** → Octane → CDN → shard theo `organization_id`.

Ghi chú: thiết kế tenancy nhẹ (`organization_id` nullable trên `tests`/`questions`/`classrooms`) **tình cờ dựng sẵn ranh giới shard tự nhiên** cho mốc >50k. Không phải lý do để làm tenancy sớm, nhưng là lý do để làm **đúng** khi làm.

### Quyết định sau, có deadline

**Frontend engine.** Chốt trước khi viết dạng câu hỏi thứ 2. Khuyến nghị: **Inertia + React + TS, bắt đầu ở builder**, engine để sau.

---

## 11. Biến số duy nhất lật ngược khuyến nghị FE

Toàn bộ phân tích FE giả định đội **nhỏ (1–3 người)**. Nếu sai, kết luận đổi:

| Đội | Khuyến nghị đổi thành |
|---|---|
| **Solo** | React ở đâu cũng là gánh nặng. Vanilla + TS + Alpine, giữ mọi thứ Laravel-native. Tối ưu cho **một người bảo trì được**. |
| **3–5 người, có tuyển** | Inertia + React đáng — pool React ở VN lớn hơn pool "Blade + 11k dòng vanilla JS" rất nhiều. Onboarding người mới vào codebase hiện tại **rất đắt**. |
| **Có FE chuyên trách** | React builder-first ngay |

Backend verdict (giữ Laravel) **không đổi theo biến số này**.

---

## 12. Một câu tóm tắt

> Framework không phải nút thắt. `CACHE_STORE=database` mới là nút thắt.
> Sửa backing store trước, rồi mới bàn framework — và khi bàn, chỉ frontend đáng bàn.

---

## 13. Phụ lục A — Greenfield stack (nếu đập đi xây lại)

> Câu hỏi giả định: **nếu bắt đầu từ số 0**, stack nào hợp nhất với mục tiêu ở §1?
>
> Cảnh báo trước, một lần: **không khuyến nghị đập đi xây lại.** Codebase hiện tại có tài sản thật (IRT/EAP, pipeline nộp-bài-async, LMS lớp học, 7.853 dòng test) và **có học sinh đang dùng**. Chương này trả lời câu hỏi giả định, không phải đề xuất hành động.

### Stack chốt: TypeScript monorepo

| Tầng | Chọn | Vì sao cho **sản phẩm này** |
|---|---|---|
| **Ngôn ngữ** | TypeScript end-to-end | Quyết định gốc — xem A.1 |
| **Backend** | **NestJS** | DI container + module system đúng hình dạng driver registry. Gần Laravel nhất trong TS. Không dùng Express trần. |
| **Contract BE↔FE** | **tRPC + Zod schema dùng chung** | Giết sạch lớp bug hiện có. Xem A.1 |
| **DB** | **PostgreSQL** | JSONB có GIN index + operator thật. `NUMERIC` cho điểm. Partial index. `SKIP LOCKED`. Xem A.3 |
| **ORM** | **Drizzle** | SQL-first, migration là SQL thuần **review được từng dòng** — bắt buộc cho expand-migrate-contract trên điểm số thật |
| **Cache / session / queue** | **Redis** + **BullMQ** | Không lặp lại `CACHE_STORE=database` (§4) |
| **Push** | **SSE** cho submit-status; WebSocket (Nest gateway) khi cần giám sát lớp live | Poll chết ở burst đồng bộ. SSE một chiều đủ và đơn giản hơn WS |
| **Frontend** | **React + Vite + TS** | Sau auth hết, không cần SSR/SEO → **không Next.js** |
| **Server state** | **TanStack Query** | Retry, offline, reconcile — đúng lớp bài toán 10k học sinh trên mạng di động |
| **Client state** | **Zustand** | Store nhỏ, không boilerplate |
| **Kéo-thả** | **dnd-kit** | Lý do kỹ thuật thật để chọn React thay Vue |
| **Bảng admin** | **TanStack Table** | Xoá vĩnh viễn hack Tabulator CDN `!important` |
| **CSS** | **Tailwind v4** | Giữ — đã dùng, đã hợp |
| **Công thức** | **KaTeX** | Giữ |
| **Psychometrics** | **Python FastAPI sidecar** (`girth`, `py-irt`, numpy/scipy) | Gọi qua queue. Calibrate IRT + item analysis + phổ điểm |
| **Test** | **Vitest + Playwright** | Playwright **bắt buộc** — engine thi phải có E2E, không thương lượng |

### A.1 Vì sao TypeScript end-to-end — đây là toàn bộ lý do

Bề mặt rủi ro cao nhất của sản phẩm này là **contract dạng câu hỏi băng qua BE↔FE**: shape answer key, shape response, cấu hình điểm từng phần.

Codebase hiện tại đã chứng minh — **hai bug chấm sai im lặng nằm đúng trên đường ghép đó**:

- `collectAnswers()` (`navigation.js:243-259`) bỏ im lặng dạng câu lạ → nộp thành chưa trả lời
- `checkAnswer()` (`HandlesAnswers.php:17-50`) coi mọi thứ không phải MCQ là SPR → chấm sai chứ không báo lỗi

Với 5 dạng × 3 kỳ thi × 4 bề mặt, bề mặt đó **nhân lên ~20 lần**.

Một `Zod` schema dùng chung — grader (BE) và collector (FE) cùng import — làm drift **bất khả thi về mặt cấu trúc**. Không phải "cẩn thận hơn". Là compile error.

Với app chấm điểm học sinh thật, **chấm sai im lặng là failure mode tệ nhất có thể có**. Đây là lý do quyết định.

Chốt thêm: repo đã **11.169 dòng JS / 12.810 dòng PHP** — gần 50/50, và 5 dạng câu hỏi mới đẩy tiếp về phía client. Đây không còn là "app PHP có vài đoạn script". Hai ngôn ngữ cho một app nửa-nửa là chi phí không cần trả.

### A.2 Vì sao NestJS, không phải Next.js hay Express

- **Next.js loại:** app sau auth hết, SEO vô nghĩa. Next hợp kém với job chạy dài, queue worker, kết nối long-lived — đúng 3 thứ engine thi cần. Ranh giới server/client của Next là chi phí thuần ở đây.
- **Express trần loại:** phải tự lắp mọi thứ. Đội nhỏ không nên trả giá đó.
- **NestJS:** module + DI + provider token là **đúng hình dạng của exam driver registry**. Đăng ký `ExamDriver` theo slug, inject `QuestionTypeRegistry` — framework làm hộ thay vì tự cuốn tay.

### A.3 Vì sao PostgreSQL, không MySQL

App này lưu JSON ở mọi chỗ quan trọng: `question_snapshot`, `answer_key`, `key_payload`, `grading_options`, `metrics`, `response`. MySQL JSON không index nội dung tử tế; **JSONB của Postgres có GIN index + operator thật**.

Cộng thêm: `NUMERIC` chính xác cho điểm (tránh float drift trên điểm từng phần `0.1/0.25/0.5`), partial index (`WHERE status='in_progress'`), `SKIP LOCKED` cho queue.

### A.4 Cố ý KHÔNG chọn

| Không chọn | Lý do |
|---|---|
| **Elixir/Phoenix** | Kỹ thuật là lựa chọn *đúng nhất* cho burst đồng bộ — BEAM sinh ra để làm việc đó. **Loại vì pool tuyển ở VN gần bằng 0.** Stack không tuyển được là stack chết. |
| **Go** | Perf mua rẻ hơn bằng Redis + SSE. Mất tốc độ dev không đáng. |
| **Python làm BE chính** | Đúng cho psychometrics, sai cho CRUD/auth/report. Nên là **sidecar**, không phải xương sống. |
| **Next.js** | A.2 |
| **Prisma** | Giấu SQL sau query engine riêng. Migration điểm số phải review được từng dòng. |
| **Redux** | Boilerplate không cần cho quy mô state này. |
| **Vue** | Tốt, nhưng không có tương đương ngang tầm `dnd-kit`/`TanStack Table`, pool VN nhỏ hơn React. |
| **GraphQL** | Một client, một server. tRPC cho type safety tốt hơn với 1/10 chi phí. |

### A.5 Điều kiện lật ngược — một cái duy nhất

**Nếu solo và không bao giờ tuyển:** chọn **Laravel + Inertia + React + TS + Postgres** thay vì NestJS.

Laravel batteries (migration, auth, policy, queue, mail, scheduler, PDF, validation) giúp ship nhanh hơn rõ rệt ở ~70% code là CRUD/LMS/report. Với một người, **tốc độ ship là sinh tồn** — nó thắng type safety. Vẫn giữ React + TS ở FE, vẫn chia sẻ type qua codegen (`spatie/laravel-typescript-transformer`), chỉ là qua một seam thay vì không có seam.

Đội **từ 2 người trở lên hoặc có ý định tuyển** → NestJS monorepo như bảng trên.

### A.6 Đường trung dung — thứ nên làm thật

Greenfield stack trên tốt hơn stack hiện tại. Nhưng **"tốt hơn" không thắng "đang chạy và đã audit"**: viết lại nghĩa là viết lại tầng bảo mật vừa vá lỗ hổng IDOR, viết lại psychometrics đang chấm điểm học sinh thật, và migrate dữ liệu sống — trong khi đối thủ VN đang chạy.

Nếu stack trên hấp dẫn, đường đúng là:

> **Giữ Laravel. Chuyển FE sang React + TS qua Inertia. Bắt đầu ở builder.**

Được ~80% lợi ích của A.1 (shared types qua codegen, component model, `dnd-kit`, `TanStack Table`) mà **không đụng vào phần đang chấm điểm học sinh**.

---

## Phụ lục B — nguồn

**Format kỳ thi:**
[HUST — dạng câu hỏi TSA](https://hust.edu.vn/vi/tuyen-sinh/dai-hoc/bai-thi-danh-gia-tu-duy-2023-muc-do-danh-gia-tu-duy-dang-cau-hoi-va-cac-vi-du-mau-651869.html) ·
[Kế hoạch TSA 2026](https://baochinhphu.vn/ke-hoach-ky-thi-danh-gia-tu-duy-tsa-nam-2026-cua-dai-hoc-bach-khoa-ha-noi-102250915091516463.htm) ·
[Cấu trúc đề THPT 2025 — Bộ GD&ĐT](https://vqa.moet.gov.vn/vi/news/savefile/thong-bao/cau-truc-dinh-dang-de-thi-tot-nghiep-thpt-tu-nam-2025-74.html) ·
[Cách tính điểm THPT 2025](https://thuvienphapluat.vn/chinh-sach-phap-luat-moi/vn/ho-tro-phap-luat/chinh-sach-moi/85588/cau-truc-de-thi-tot-nghiep-thpt-nam-2025-va-cach-thuc-tinh-diem) ·
[HSA — IDT VNU](https://idt.vnu.edu.vn/do-luong-va-danh-gia/danh-gia-nang-luc)

**Codebase (đã verify tại thời điểm viết):**
`.env.example` (driver config) · `composer.json` (không có octane/reverb/horizon/redis) ·
`app/Http/Controllers/Engine/Concerns/HandlesAnswers.php` (bulk upsert — đúng) ·
`resources/js/test/navigation.js:243-259` (collectAnswers bỏ im lặng dạng lạ) ·
`.github/workflows/ci.yml` (không chạy test)
