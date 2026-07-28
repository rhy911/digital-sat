# Scoring Pipeline — Thiết kế Mục tiêu (IRT-Default, Cap-Aware, Tự động)

> Tài liệu này mô tả **pipeline chấm điểm sẽ được implement** sau refactor.
> Mục tiêu tối cao: **chính xác nhất có thể so với kì thi thật**, đồng thời **tối thiểu thao tác
> thủ công** của giáo viên/người dùng.
>
> Bản này thay thế phiên bản routed-table-primary trước đó (đã sai hướng). Tài liệu
> `prompts/digital-sat-scoring-pipeline.md` mô tả pipeline hiện tại (IRT + scale tuyến tính) —
> giữ để đối chiếu, sẽ nghỉ hưu khi refactor xong.

---

## 0. Nền tảng — phương pháp chấm digital SAT thật (đã research)

| Thứ | Public? | Hệ quả |
| --- | --- | --- |
| Mô hình 3PL IRT, multistage adaptive, routing, cap theo nhánh | **Có** | Ta mô phỏng đúng **phương pháp** |
| Item params `a/b/c` từng câu | **Không** (bí mật, khác mỗi form) | Ta dùng default + auto-calibrate |
| Hàm equating θ→scale từng form | **Không** (bí mật, re-equate mỗi form) | Ta calibrate một đường cong generic |

**Hệ quả cốt lõi:** không third-party nào khớp **con số tuyệt đối** của kì thi thật (params +
equating bí mật). Nhưng ta khớp được **phương pháp** (IRT thật, difficulty-aware, cap thật) — đây
là khác biệt so với các nền tảng chỉ đếm số câu đúng.

**Sự thật đã xác nhận từ nguồn:**
- Easy Module 2 chặn trần section **~590–670** dù đúng 100%; Hard Module 2 mới mở tới **800**.
- Routing cut ~15 câu đúng Module 1 (không chính thức, khác theo form).
- IRT tính cả độ khó câu + xác suất đoán mò, không chỉ số câu đúng.

---

## 1. Triết lý

**IRT là engine chính, tự động 100%.** Không bảng tra đếm-câu làm mặc định (đó là commodity, bỏ
qua độ khó). θ vẫn là đại lượng năng lực; điểm cuối = θ ánh xạ qua **đường cong phi tuyến,
cap-aware, calibrate một lần**. Bảng/params chuẩn chỉ là **override tùy chọn**.

Bốn tầng, từ tự động nhất tới thủ công nhất:

| Tầng | Vai trò | Thủ công? |
| --- | --- | --- |
| **1. IRT engine** | Chấm mọi đề (custom + replica), difficulty-aware | Không |
| **2. θ→scale curve** | Phi tuyến, riêng RW/Math, tái tạo cap easy/hard | Calibrate **1 lần** |
| **3. Auto-calibrate** | Tự tinh chỉnh `a/b/c` khi có response thật | Không (background job) |
| **0. Override** | Dùng params/bảng chuẩn nếu người dùng có | Tùy chọn |

---

## 2. Hai loại đề

| | Normal (4 module) | Adaptive Full (6 module) |
| --- | --- | --- |
| Cấu trúc | M1 + M2 cố định mỗi section | M1 + M2-easy + M2-hard (thi 2, dựng 3) |
| Routing | Không | Có (M1 quyết định nhánh) |
| Khớp luồng Bluebook | Không (static) | **Có** |
| Chấm điểm | IRT trên toàn bộ câu (không có cap nhánh) | IRT + cap-aware curve theo nhánh đã đi |
| Vai trò | Practice nhanh | **Sản phẩm mô phỏng chính** |

Cả hai đều chạy IRT (difficulty-aware). Khác biệt: đề adaptive có routing + cap theo nhánh; đề
normal không có nhánh nên không áp cap.

---

## 3. Sơ đồ pipeline

```
                     [ HỌC SINH NỘP MODULE ]
                                │
          save answers (đồng bộ) → dispatch ScoreModuleJob (queue)
                                │  frontend poll /submit-status mỗi 1.5s
                                ▼
                    ┌───────────────────────┐
                    │  M1 của đề adaptive?   │
                    └───────────┬───────────┘
                    CÓ ▼                 ▼ KHÔNG
        ┌────────────────────────┐   ┌──────────────────────────┐
        │  ROUTING (Module 1)    │   │  Còn module → issue kế    │
        │  θ/raw M1 ≥ cutoff ?    │   │  Hết module → FINALIZE    │
        │     hard : easy        │   └────────────┬─────────────┘
        │  lưu rw/math_m2_path    │                │
        └────────────────────────┘                ▼
                                        ┌────────────────────┐
                                        │   FINALIZE ATTEMPT  │
                                        └─────────┬──────────┘
                                                  ▼
                        ┌─────────────────────────────────────────┐
                        │  Có approved ScoreConversionSet          │
                        │  && form_checksum khớp? (OVERRIDE)       │
                        └───────────┬──────────────────┬──────────┘
                              CÓ ▼                      ▼ KHÔNG (mặc định)
                    ┌──────────────────┐    ┌──────────────────────────────┐
                    │ Dùng params/bảng │    │  IRT engine (EAP grid, 3PL)  │
                    │ chuẩn của set    │    │  θ per section (đã trừ pretest)│
                    └────────┬─────────┘    └───────────────┬──────────────┘
                             │                              ▼
                             │              ┌──────────────────────────────┐
                             │              │  θ→scale CURVE (phi tuyến)    │
                             │              │  riêng RW/Math                │
                             │              │  nhánh easy → cap ~670        │
                             │              │  nhánh hard → tới 800         │
                             │              └───────────────┬──────────────┘
                             └──────────────┬───────────────┘
                                            ▼
                                [ SCALED SCORE 200–800 ]
                          + confidence band (từ θ SE qua cùng curve)
                          + total = RW + Math (clamp 400–1600)
```

---

## 4. Chi tiết từng tầng

### 4.1 Nộp bài (giữ nguyên — đã tốt)
- `submitModule` lưu answers đồng bộ → dispatch `ScoreModuleJob` → trả `status: scoring`.
- Frontend poll `/submit-status` (cache 300s) tới khi có điểm.
- **Bắt buộc** `php artisan queue:work` chạy.

### 4.2 Ước lượng năng lực θ (giữ EAP grid — KHÔNG quay lại Newton-Raphson)
- **EAP grid**, prior chuẩn N(0,1), lưới −4..4 bước 0.05, 3PL.
- Lý do không dùng Newton-Raphson MLE: MLE **phân kỳ** khi học sinh đúng/sai 100% (θ→±∞). EAP
  grid luôn cho θ hữu hạn, ổn định biên. Đây là lý do codebase đã chuyển khỏi Newton-Raphson.
- Trừ pretest trước khi tính.
- SE = độ lệch chuẩn posterior → dùng cho confidence band.

### 4.3 Routing Module 1 (chỉ đề adaptive) — CHỐT: theta-based

**Route bằng θ (IRT information-based), KHÔNG bằng số câu đúng (number-correct).** Căn cứ:
- Bluebook thật route bằng IRT/θ, không phải raw cut score.
- Number-correct chỉ là **thống kê đủ (sufficient statistic) cho θ dưới Rasch/1PL**. Model ở đây
  là **3PL với `a`, `c` biến thiên** → raw-count mất chính xác vì câu không tương đương thống kê
  (câu khó và dễ đều đếm "1" nhưng mang thông tin khác nhau). θ tính cả `a`, `c`.
- θ đã được tính sẵn từ EAP trên Module 1 → route theo θ **miễn phí, zero config per-form**.
- Không đuổi theo raw-cutoff per-form: số đó không chính thức, khác theo form, và cần nhập tay —
  nghịch mục tiêu tự động. Cap-aware curve (4.4) đã lo hậu quả điểm.

**Ngưỡng:**
- `θ ≥ cutoff` → Hard M2, ngược lại Easy M2.
- `cutoff` = **config global per-section** (mặc định `0.0`, tune được), KHÔNG per-form, KHÔNG
  hardcode raw. Một config, không việc mỗi đề.
- Trừ pretest trước khi tính θ. Lưu θ + raw M1 vào audit log (chỉ để kiểm soát, không dùng route).
- Ghi nhánh vào `rw_m2_path` / `math_m2_path`.

### 4.4 θ→scale — ĐIỂM SỬA CỐT LÕI

Thay `500 + 100·θ` tuyến tính ([config/sat_scoring.php:30-36](config/sat_scoring.php#L30-L36)) bằng
**đường cong phi tuyến, cap-aware, riêng từng section**.

**Yêu cầu của đường cong:**
- **Phi tuyến** — nén ở hai đầu, dốc ở giữa (giống equating thật), không phải đường thẳng.
- **Riêng RW và Math** — hai section equate khác nhau, KHÔNG dùng chung center/slope.
- **Tái tạo cap theo nhánh (đề adaptive):**
  - Nhánh **easy**: θ tối đa đạt được (đúng 100% easy) map ~**590–670**, không tới 800.
  - Nhánh **hard**: mở toàn dải tới **800**.
- **Calibrate MỘT LẦN:** dùng các anchor public đã research (cap easy ~670, hard→800, hình dạng
  equating) fit đường cong, nạp vào `config` làm **default**. Sau đó áp **tự động cho mọi đề**.
  Không việc per-form.

**Vì sao cap nằm ở tầng scale, không phải hardcode:** trên nhánh easy toàn câu `b` thấp → câu
easy không đo được năng lực cao (thông tin ≈ 0 ở θ lớn) → θ ước lượng bị chặn tự nhiên. Đường cong
calibrate để θ-max-easy map đúng ~670, phản ánh chính xác giới hạn đo lường này.

### 4.5 Confidence Band
- Band suy từ θ SE **qua cùng đường cong** (map `θ−SE` và `θ+SE`), nên tâm band luôn trùng điểm
  chính. Không lệch tâm.
- Total: `RW + Math`, clamp `[400, 1600]`; range gộp SE hai section theo quadrature.

### 4.6 Override tùy chọn (Tầng 0)
- `ScoreConversionService` thử `approvedScoreConversionSet()` trước; **giữ cổng checksum**
  `hash_equals($set->form_checksum, $audit->formChecksum($test))` — không query thẳng bảng theo
  `test_id` (tránh dính row draft/retired/bảng cũ sau khi form sửa).
- Có set hợp lệ (bảng/params chuẩn người dùng nạp) → ưu tiên dùng. Không có → fallback IRT +
  curve. Đúng yêu cầu "có tham số chuẩn thì dùng, không thì tự động".

### 4.7 Auto-calibrate (Tầng 3 — tương lai gần, tự động)
- Khi tích đủ response thật → background job ước lượng lại `a/b/c` từng câu (2PL/3PL calibration).
- Ghi đè default tag-based bằng params calibrate. **Không người can thiệp.**
- Engine không đổi cấu trúc — chỉ nguồn params đổi. Accuracy tự leo về gần thật theo thời gian.

---

## 5. Lưu trữ & phân biệt regime
Ghi trên `UserTest`: `score_reading_writing`, `score_math`, `total_score` (+ lower/upper),
`rw_theta`, `math_theta`, `rw_theta_se`, `math_theta_se`, `rw_m2_path`, `math_m2_path`,
`scoring_method`, `score_conversion_set_id`, `score_conversion_version`, `score_estimate_kind`.

`estimate_kind` phân biệt: `irt_default_curve` (mặc định) / `irt_calibrated` (sau auto-calibrate) /
`form_specific_override` (có set chuẩn). UI báo cáo hiển thị đúng mức tin cậy. Điểm cũ theo θ tuyến
tính giữ nguyên (going-forward-only, không backfill).

---

## 6. Trần chính xác — thành thật
- **Phương pháp:** khớp SAT thật (IRT 3PL + EAP + routing + cap). Đạt được.
- **Con số:** ước lượng, không tuyệt đối — vì params + equating của CB bí mật, khác mỗi form. Mọi
  third-party đều vướng trần này.
- **Hơn đối thủ:** difficulty-aware IRT thật + cap thật, không phải bảng đếm câu.
- **Tự cải thiện:** auto-calibrate kéo accuracy về gần thật khi có data.
- **Tuyệt đối trên 1 đề cụ thể:** chỉ khi người dùng nạp params/bảng chuẩn qua override.

---

## 7. Các quyết định đã chốt
1. **Routing cutoff:** ✅ **theta-based**, ngưỡng `θ ≥ cutoff` với `cutoff` là config global
   per-section (mặc định `0.0`). Không raw-count, không per-form. Lý do đầy đủ ở 4.3.
2. **Cap-aware curve:** ✅ **path-aware** — nhánh easy trần ~670, hard tới 800, đúng SAT thật.
3. **Anchor calibrate:** ✅ dùng dải public đã research (easy ~590–670, hard→800 + hình dạng
   equating phi tuyến) để fit đường cong.

---

## 8. Thứ tự implement

| # | Việc | Phụ thuộc | Ưu tiên |
| --- | --- | --- | --- |
| 1 | θ→scale phi tuyến, riêng RW/Math, thay `500+100θ`; band map qua cùng curve | Chốt mục 7.2, 7.3 | **P0** |
| 2 | Cap-aware theo nhánh easy/hard (nếu chọn path-aware) | #1 | **P0** |
| 3 | Guard nhánh `section_only/custom` finalize; bỏ EAP tính trùng ([SatScoringService.php:34-37](app/Services/SatScoringService.php#L34-L37)) | Không | **P0** |
| 4 | Doc drift CLAUDE.md: "MLE Newton-Raphson" → "EAP grid + cap-aware curve" | Không | **P0** |
| 5 | Routing theta-based: ngưỡng `θ ≥ cutoff` config global per-section (mặc định 0.0) | Không | P1 |
| 6 | Giữ override path: `ScoreConversionService` checksum-gated cho ai có set chuẩn | Không | P1 |
| 7 | Auto-calibrate background job (params từ response thật) | Cần đủ volume data | P2 (tương lai) |
| 8 | Unit test biên: 100% easy → dính cap ~670; 100% hard → tới 800; RW≠Math curve; band trùng tâm | Sau #1–2 | P1 |

**Nhóm PR:** #1–4 gộp một PR (core scale fix, đứng độc lập). #5–6 PR sau. #7 giai đoạn sau khi có
data.

---

## 9. File ảnh hưởng chính
- `config/sat_scoring.php` — đường cong phi tuyến riêng RW/Math, cap-aware.
- `app/Services/AdaptiveScoreConversionService.php` — `mapTheta` phi tuyến + cap theo nhánh.
- `app/Services/TestProgressionService.php` — `finalizeAdaptive` dùng curve; guard branch; band trùng tâm.
- `app/Services/SatScoringService.php` — bỏ EAP trùng; (tùy chọn) routing raw-cutoff.
- `app/Services/ScoreConversionService.php` — giữ override checksum-gated.
- `CLAUDE.md` — sửa mô tả engine.
- `database/jobs|console` — (tương lai) auto-calibrate job.
