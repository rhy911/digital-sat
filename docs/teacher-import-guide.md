# HƯỚNG DẪN NHẬP LIỆU & IMPORT CÂU HỎI DIGITAL SAT
*(Dành cho Giáo viên & Biên soạn nội dung)*

Tài liệu này hướng dẫn chi tiết các bước nhập liệu câu hỏi lên hệ thống Digital SAT Test Builder qua tính năng **Bulk Import** (JSON, ZIP, CSV).

---

## 1. QUY TRÌNH IMPORT CÂU HỎI (3 BƯỚC)

### Bước 1: Chọn điểm đích (Destination)
* **Module**: Chọn đúng bài thi/phần thi (Reading & Writing hoặc Math), số Module và độ khó tương ứng (ví dụ: *Test 1 | R&W - Mod 1 (Medium)*).
* **Vị trí bắt đầu (Start at question)**: Nhập số thứ tự câu muốn chèn vào (mặc định là `1`). Các câu hỏi hiện tại từ vị trí này trở đi sẽ tự động lùi xuống.

### Bước 2: Chọn phương thức Import
* **Paste or upload (JSON)**: Copy nội dung JSON hoặc tải file `.json` trực tiếp. Rất phù hợp nếu dùng AI để chuyển đổi dữ liệu thô.
* **CSV**: Tải file `.csv` chuẩn bị từ Excel hoặc Google Sheets. Phù hợp cho việc nhập liệu thủ công hàng loạt không có hình ảnh.
* **ZIP + images (Khuyên dùng khi có ảnh)**: Tải lên file `.zip` chứa file dữ liệu câu hỏi (JSON hoặc CSV) kèm theo các thư mục hình ảnh liên quan.

### Bước 3: Xem trước & Xác nhận (Preview & Import)
* Nhấn **Preview** để hệ thống kiểm tra định dạng và tính hợp lệ của từng câu hỏi.
* Nếu xuất hiện lỗi đỏ (ví dụ: thiếu đáp án đúng, sai tên domain, thiếu passage của phần R&W), hãy sửa trực tiếp trong editor hoặc trong file nguồn rồi upload lại.
* Nhấn **Import** để lưu câu hỏi vào cơ sở dữ liệu.

---

## 2. QUY TẮC ĐỊNH DẠNG NỘI DUNG (FORMATTING RULES)

Để câu hỏi hiển thị đẹp mắt và chuẩn xác như phần mềm thi thật (Bluebook), giáo viên cần tuân thủ các quy chuẩn định dạng sau:

### 2.1. Công thức Toán & Ký hiệu (LaTeX)

Chỉ có **ba quy tắc**. Không cần đếm dấu gạch chéo ngược.

#### Quy tắc 1 — Bọc mọi công thức trong `$$...$$`

Kể cả một biến đơn lẻ nằm giữa câu văn.

* *Đúng*: `Tìm giá trị của $$x$$ trong phương trình $$x^2 + 5x = 6$$.`
* *Sai*: `Tìm giá trị của x trong phương trình x^2 + 5x = 6.`

#### Quy tắc 2 — Bên trong `$$...$$`, viết LaTeX bình thường

Một dấu gạch chéo ngược, đúng như khi gõ trong bất kỳ tài liệu LaTeX nào. Hệ thống không cho Markdown động vào vùng công thức, nên không có ký hiệu nào cần "escape thêm".

| Muốn hiện | Gõ |
| :--- | :--- |
| $ (đô-la) | `$$\$45$$` |
| % (phần trăm) | `$$50\%$$` |
| Phân số | `$$\frac{a}{b}$$` |
| Căn thức | `$$\sqrt{x}$$` |
| Số mũ | `$$x^2$$` |
| Độ | `$$18^\circ$$` |
| Chữ Hy Lạp | `$$\pi$$`, `$$\theta$$`, `$$\omega$$` |
| So sánh | `$$\le$$`, `$$\ge$$`, `$$\neq$$` |
| Tập hợp | `$$\{1, 2, 3\}$$` |
| Đơn vị kèm số | `$$5\,\text{cm}$$` |
| Phép nhân | `$$3 \times 4$$` hoặc `$$3 \cdot 4$$` |

Ba lưu ý nhỏ:

* **Không cần gõ `\displaystyle`.** Hệ thống tự thêm khi thấy `\frac`. Gõ thêm cũng không sao, không bị lặp.
* **Không dùng dấu `*` để nhân** — dùng `\times` hoặc `\cdot`. Dấu `*` bị hiểu là cú pháp in nghiêng.
* **Hệ phương trình**: nên tách thành hai khối `$$` đặt trên hai đoạn (cách nhau một dòng trống), vừa giống Bluebook vừa tránh rắc rối với `\\`:

  ```text
  $$3x + 4y = 26$$

  $$5x - 2y = 13$$
  ```

#### Quy tắc 3 — Nếu nộp bằng file JSON, nhân đôi mọi dấu `\`

Đây là **quy tắc của định dạng JSON**, không phải của hệ thống. File CSV và ô soạn thảo trên web thì gõ y hệt Quy tắc 2, không nhân đôi gì.

| Trong ô soạn thảo / file CSV | Trong file JSON |
| :--- | :--- |
| `$$50\%$$` | `"$$50\\%$$"` |
| `$$\$45$$` | `"$$\\$45$$"` |
| `$$\frac{a}{b}$$` | `"$$\\frac{a}{b}$$"` |
| `$$\pi$$` | `"$$\\pi$$"` |

> **Cảnh báo quan trọng.** Nếu quên nhân đôi trong file JSON, phần lớn công thức **hỏng âm thầm chứ không báo lỗi**: `\frac` bị JSON đọc thành ký tự điều khiển form-feed, `\text` thành dấu Tab, `\neq` thành ký tự xuống dòng. Hệ thống nay đã tự phát hiện và chặn ở bước Preview, nhưng cách chắc chắn nhất vẫn là bảo AI *"output valid JSON"* — khi đó AI tự escape đúng.

*Ghi chú tương thích*: nội dung cũ viết theo lối nhân đôi (`$$\\%$$`, `$$\\$45$$`) vẫn hiển thị đúng, không cần sửa lại.

### 2.2. Bảng dữ liệu (HTML Table)
Nếu câu hỏi hoặc bài đọc chứa bảng biểu có thể đọc được, hãy chuyển đổi nó thành định dạng HTML dạng bảng:
```html
<table class="min-w-full divide-y divide-slate-200">
  <thead>
    <tr>
      <th>Biến số</th>
      <th>Tần số</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>$$x = 1$$</td>
      <td>12</td>
    </tr>
  </tbody>
</table>
```

### 2.3. Hình ảnh / Đồ thị (Media Placeholder)
Nếu câu hỏi chứa biểu đồ, hình vẽ hình học hoặc đồ thị:
* Không chèn trực tiếp đường dẫn tuyệt đối hoặc để trống.
* Đặt một placeholder đại diện ở đúng vị trí hình ảnh hiển thị theo định dạng: `[Media:tên_file.ext]`
  * *Ví dụ*: `[Media:q05_scatterplot.png]`, `[Media:q12_triangle.jpg]`
* Tên file ảnh phải là duy nhất trong gói import (tránh đặt chung chung như `[Media:image.png]`).
* Các định dạng ảnh được hỗ trợ: `.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`, `.webp`.

---

## 3. CHI TIẾT CẤU TRÚC FILE IMPORT

### 3.1. Cấu trúc Gói ZIP (Khi import kèm hình ảnh)
Để import câu hỏi có chứa hình ảnh, giáo viên đóng gói file ZIP theo cấu trúc thư mục sau:

**Cách 1 (Các file cùng cấp):**
```text
my-import-package.zip
├── questions.json  (hoặc questions.csv)
├── q01_graph.png
└── q05_triangle.png
```

**Cách 2 (Hình ảnh nằm trong thư mục `images`):**
```text
my-import-package.zip
├── questions.json  (hoặc questions.csv)
└── images/
    ├── q01_graph.png
    └── q05_triangle.png
```
*Lưu ý: File JSON hoặc CSV phải nằm ở gốc (root) hoặc cùng thư mục với các file ảnh. Hệ thống sẽ tự động quét đệ quy để tìm file dữ liệu và ánh xạ đúng ảnh.*

---

### 3.2. Ví dụ và Định dạng JSON (JSON Schema)
File JSON chứa một mảng các đối tượng câu hỏi dưới khóa `items`. Dưới đây là **Ví dụ file JSON hoàn chỉnh và đầy đủ thuộc tính** bao gồm cả câu hỏi Reading & Writing (Multiple Choice), Math (Multiple Choice), và Math (SPR) có sử dụng LaTeX, bảng HTML và giải thích nâng cao:

```json
{
  "items": [
    {
      "question_number": 1,
      "question_type": "multiple_choice",
      "passage": "Scientists recently analyzed the atmospheric composition of exoplanet Kepler-186f. They discovered trace amounts of water vapor in the upper atmosphere, suggesting that liquid water may exist on the surface under specific conditions. However, critics point out that the planet's atmospheric pressure is significantly lower than Earth's, which might prevent stable ocean formation.",
      "stem": "Which choice best states the main idea of the text?",
      "difficulty": "medium",
      "skill_domain": "craft_and_structure",
      "skill_subdomain": "text_structure_and_purpose",
      "choices": {
        "A": "Kepler-186f has oceans of stable liquid water.",
        "B": "Recent atmospheric analyses of Kepler-186f suggest water vapor is present, though ocean stability remains debated.",
        "C": "Low atmospheric pressure completely rules out water on Kepler-186f.",
        "D": "Critics argue that Earth and Kepler-186f have identical atmospheric profiles."
      },
      "correct_choice": "B",
      "explanation": "The text states that water vapor was discovered (suggesting liquid water), but critics raise atmospheric pressure concerns that could prevent oceans. This dual structure is best captured by choice B.",
      "rationale_a": "Choice A is incorrect because ocean stability is disputed, not confirmed.",
      "rationale_b": "Choice B is correct because it covers both the vapor discovery and the pressure counter-argument.",
      "rationale_c": "Choice C is too extreme; the text says low pressure 'might prevent' ocean formation, not that it 'rules out' water.",
      "rationale_d": "Choice D is unsupported; the text implies the atmospheres are different, not identical.",
      "strategy_tip": "Look for answers that balance both the initial finding and the counter-claim.",
      "common_mistakes": "Picking choice A which is too broad and ignores the critics' reservations.",
      "is_pretest": false,
      "calculator_allowed": true,
      "expected_time": 75,
      "external_id": "RW-01-T1"
    },
    {
      "question_number": 2,
      "question_type": "multiple_choice",
      "stem": "The table below displays the values of variables $$x$$ and $$y$$. If the relationship is linear, what is the slope of the line?\n\n<table class=\"min-w-full divide-y divide-slate-200\"><thead><tr><th>$$x$$</th><th>$$y$$</th></tr></thead><tbody><tr><td>$$1$$</td><td>$$5$$</td></tr><tr><td>$$3$$</td><td>$$11$$</td></tr></tbody></table>",
      "difficulty": "easy",
      "skill_domain": "algebra",
      "skill_subdomain": "linear_functions",
      "choices": {
        "A": "$$2$$",
        "B": "$$3$$",
        "C": "$$5$$",
        "D": "$$6$$"
      },
      "correct_choice": "B",
      "explanation": "Using the slope formula $$m = \\frac{y_2 - y_1}{x_2 - x_1}$$, we get $$m = \\frac{11 - 5}{3 - 1} = \\frac{6}{2} = 3$$.",
      "strategy_tip": "Select two coordinates from the HTML table to compute the slope.",
      "is_pretest": false,
      "calculator_allowed": true,
      "external_id": "M-02-T1"
    },
    {
      "question_number": 3,
      "question_type": "student_produced_response",
      "stem": "In the triangle shown, what is the value of $$\\tan(\\theta)$$?\n\n[Media:q03_triangle.png]",
      "difficulty": "hard",
      "skill_domain": "geometry_trigonometry",
      "skill_subdomain": "right_triangles_and_trigonometry",
      "spr_correct_answers": ["4/3", "1.33", "1.333"],
      "spr_hint": "Enter your answer as a fraction or decimal.",
      "explanation": "In a right triangle, $$\\tan(\\theta) = \\frac{\\text{Opposite}}{\\text{Adjacent}} = \\frac{4}{3} \\approx 1.333$$.",
      "strategy_tip": "Identify opposite and adjacent sides relative to angle $$\\theta$$ from the graph.",
      "is_pretest": false,
      "calculator_allowed": true,
      "external_id": "M-03-T1"
    }
  ]
}
```

---

### 3.3. Ví dụ và Định dạng CSV (Bảng Excel)
File CSV cần có dòng tiêu đề đầu tiên khớp chính xác các tiêu đề cột. 

#### Bảng định nghĩa cột CSV:

| Tên Cột (Header) | Yêu cầu | Mô tả / Giá trị cho phép |
| :--- | :---: | :--- |
| `question_type` | **Bắt buộc** | `multiple_choice` hoặc `student_produced_response` |
| `difficulty` | **Bắt buộc** | `easy`, `medium`, `hard` |
| `skill_domain` | **Bắt buộc** | Xem danh mục Domain ở phần 4 |
| `skill_subdomain`| Không | Xem danh mục Subdomain ở phần 4 |
| `stem` | **Bắt buộc** | Câu hỏi chính / Yêu cầu đề bài |
| `passage_content` | R&W | Đoạn văn bài đọc (chỉ dành cho R&W, để trống đối với Toán) |
| `passage_genre` | Không | Thể loại bài đọc (ví dụ: `humanities`, `natural_science`, `literature`) |
| `choice_a_content`| MC | Nội dung lựa chọn A |
| `choice_b_content`| MC | Nội dung lựa chọn B |
| `choice_c_content`| MC | Nội dung lựa chọn C |
| `choice_d_content`| MC | Nội dung lựa chọn D |
| `correct_choice` | MC | Đáp án đúng của trắc nghiệm: `A`, `B`, `C`, hoặc `D` |
| `spr_correct_answers`| SPR | Các đáp án chấp nhận cho câu điền số. Nếu có nhiều đáp án đúng, ngăn cách bằng dấu gạch đứng `\|` hoặc `;` (Ví dụ: `5\|5.0` hoặc `1/2\|0.5`) |
| `spr_hint` | Không | Gợi ý khi học sinh điền đáp án số |
| `explanation` | Không | Lời giải chi tiết |
| `rationale_a` đến `rationale_d` | Không | Giải thích tại sao từng phương án A, B, C, D đúng/sai |
| `strategy_tip` | Không | Mẹo làm bài nhanh |
| `common_mistakes` | Không | Lỗi sai học sinh thường mắc phải |
| `is_pretest` | Không | `1` nếu là câu thử nghiệm không tính điểm, `0` nếu tính điểm (mặc định) |
| `calculator_allowed`| Không | `1` nếu cho phép dùng máy tính (mặc định cho Toán), `0` nếu không |
| `expected_time` | Không | Thời gian làm bài dự kiến (tính theo giây, ví dụ: `75` cho 75 giây) |
| `external_id` | Không | Mã định danh duy nhất của câu hỏi để ánh xạ hệ thống |

#### Ví dụ file CSV hoàn chỉnh (Raw CSV Text):
Dưới đây là nội dung thô của một file `.csv` chuẩn. Bạn có thể lưu đoạn văn bản này thành file có đuôi `.csv` và mở bằng Excel hoặc Google Sheets để chỉnh sửa:

```csv
question_type,difficulty,skill_domain,skill_subdomain,stem,passage_content,passage_genre,choice_a_content,choice_b_content,choice_c_content,choice_d_content,correct_choice,spr_correct_answers,spr_hint,explanation,strategy_tip,common_mistakes,is_pretest,calculator_allowed,external_id
multiple_choice,medium,craft_and_structure,words_in_context,"As used in the text, what does the word ""vital"" most nearly mean?","The researcher's notes were vital to the team's success.",natural_science,useless,essential,optional,secondary,B,,,The passage states that the notes were vital, which means essential.,Read context clues.,Do not pick secondary definitions if they don't fit.,0,1,RW-CSV-01
student_produced_response,hard,algebra,linear_equations_in_one_variable,"If $$3x - 5 = 10$$, what is the value of $$x$$?",,,,,,,,5,Enter an integer.,Add 5 to both sides: $$3x = 15$$. Divide by 3: $$x = 5$$. ,Isolate the variable.,Check arithmetic carefully.,0,1,M-CSV-02
```
*Lưu ý khi mở và lưu CSV bằng Excel*:

- **LaTeX trong CSV viết y hệt như trong ô soạn thảo trên web**: một dấu gạch chéo ngược. `$$50\%$$`, `$$\$45$$`, `$$\frac{a}{b}$$`. **Không** nhân đôi — quy tắc nhân đôi chỉ áp dụng cho file JSON (xem mục 2.1, Quy tắc 3).
- Đảm bảo lưu dưới định dạng **CSV UTF-8 (Comma delimited) (\*.csv)** để không bị lỗi hiển thị tiếng Việt hoặc các ký hiệu LaTeX.
- Toàn bộ nội dung chứa dấu phẩy `,` hoặc dấu xuống dòng bắt buộc phải bọc trong cặp dấu nháy kép `""...""`. Dấu nháy kép nằm bên trong nội dung phải được nhân đôi thành `""""`.

---

## 4. DANH MỤC DOMAIN & SUBDOMAIN HỢP LỆ (BẮT BUỘC KHỚP 100%)

> Danh mục này là bản sao của `config/sat_taxonomy.php`. Hệ thống **từ chối** mọi giá trị ngoài danh sách ngay ở bước Preview, kèm gợi ý các giá trị hợp lệ. Lý do: hai trường này là khoá gom nhóm của báo cáo điểm và phân tích điểm yếu — một giá trị tự chế không báo lỗi mà biến thành một "kỹ năng" chỉ có đúng một câu, làm hỏng báo cáo.

### 4.1. Reading & Writing
* **`information_and_ideas`**
  * `central_ideas_and_details`
  * `command_of_evidence`
  * `inferences`
* **`craft_and_structure`**
  * `words_in_context`
  * `text_structure_and_purpose`
  * `cross_text_connections`
* **`expression_of_ideas`**
  * `rhetorical_synthesis`
  * `transitions`
* **`standard_english_conventions`**
  * `boundaries`
  * `form_structure_and_sense`

### 4.2. Math
* **`algebra`**
  * `linear_equations_in_one_variable`
  * `linear_functions`
  * `linear_equations_in_two_variables`
  * `systems_of_two_linear_equations_in_two_variables`
  * `linear_inequalities_in_one_or_two_variables`
* **`advanced_math`**
  * `nonlinear_functions`
  * `nonlinear_equations_in_one_variable`
  * `systems_of_equations_in_two_variables`
  * `equivalent_expressions`
* **`problem_solving_data_analysis`**
  * `ratios_rates_proportional_relationships_and_units`
  * `percentages`
  * `one_variable_data_distributions_and_measures_of_center_and_spread`
  * `two_variable_data_models_and_scatterplots`
  * `probability_and_conditional_probability`
  * `inference_from_sample_statistics_and_margin_of_error`
  * `evaluating_statistical_claims_observational_studies_and_experiments`
* **`geometry_trigonometry`**
  * `area_and_volume`
  * `lines_angles_and_triangles`
  * `right_triangles_and_trigonometry`
  * `circles`

---

## 5. PROMPT CHUYỂN ĐỔI DỮ LIỆU BẰNG AI (AI CONVERSION PROMPT)

Giáo viên sao chép toàn bộ prompt dưới đây, dán vào ChatGPT hoặc Claude để chuyển đổi file tài liệu Word/PDF đề thi thô thành file JSON sẵn sàng import.

> Bản prompt này được sinh ra từ `resources/views/components/admin/test-builder/questions/ai-conversion-prompt.blade.php`, và danh mục kỹ năng trong đó lấy thẳng từ `config/sat_taxonomy.php`. Bản trong ứng dụng (nút **Copy AI Prompt** ở màn hình Bulk Import) luôn là bản mới nhất.

```text
Convert a Digital SAT exam (Word/Google Doc) into import JSON. You transcribe; you never author.

Overriding rules:
- Output only what the document contains. Never invent, rephrase, translate, reorder, or rebalance the answer key.
- Copy passages, stems and choices verbatim; fix only conversion typos.
- A field not in the document is omitted, never guessed.
- Output ONLY valid JSON: no code fences, no commentary.

Shape: {"items": [ ... ]}

multiple_choice:
{"question_number":1,"question_type":"multiple_choice","passage":"Reading and Writing only; omit for Math","stem":"...","difficulty":"easy|medium|hard","skill_domain":"...","skill_subdomain":"...","choices":{"A":"...","B":"...","C":"...","D":"..."},"correct_choice":"B"}

student_produced_response (Math grid-ins only):
{"question_number":1,"question_type":"student_produced_response","stem":"...","difficulty":"...","skill_domain":"...","skill_subdomain":"...","spr_correct_answers":["4/3","1.33","1.333"]}

Add ONLY when the document contains it: explanation, rationale_a/b/c/d, strategy_tip, common_mistakes, spr_hint (answer format only, never revealing the answer), is_pretest:true (unscored trial item), expected_time (estimated seconds to answer). No other keys — the system fills the rest.

Field notes:
- question_number: as printed, restarts at 1 per module; emit items in that order.
- correct_choice: from the answer key, exactly one of A/B/C/D.
- spr_correct_answers: every accepted form of the answer.
- difficulty: the document's label if it has one, otherwise judge it.
- skill_domain / skill_subdomain: copy exactly from the list below and keep the subdomain inside its own domain. The importer rejects anything else.

Allowed skills:
Reading and Writing
  information_and_ideas: central_ideas_and_details, command_of_evidence, inferences
  craft_and_structure: words_in_context, text_structure_and_purpose, cross_text_connections
  expression_of_ideas: rhetorical_synthesis, transitions
  standard_english_conventions: boundaries, form_structure_and_sense
Math
  algebra: linear_equations_in_one_variable, linear_functions, linear_equations_in_two_variables, systems_of_two_linear_equations_in_two_variables, linear_inequalities_in_one_or_two_variables
  advanced_math: nonlinear_functions, nonlinear_equations_in_one_variable, systems_of_equations_in_two_variables, equivalent_expressions
  problem_solving_data_analysis: ratios_rates_proportional_relationships_and_units, percentages, one_variable_data_distributions_and_measures_of_center_and_spread, two_variable_data_models_and_scatterplots, probability_and_conditional_probability, inference_from_sample_statistics_and_margin_of_error, evaluating_statistical_claims_observational_studies_and_experiments
  geometry_trigonometry: area_and_volume, lines_angles_and_triangles, right_triangles_and_trigonometry, circles

LaTeX and formatting:
- Wrap every formula, variable, unit and symbol in $$...$$, even a lone variable: $$x^2$$, $$18^\circ$$.
- Inside $$...$$ use plain LaTeX with ONE backslash: $$50\%$$, $$\$45$$, $$\frac{a}{b}$$, $$\pi$$, $$\{1,2\}$$, $$5\,\text{cm}$$.
- Never write \displaystyle (added automatically). Never use a bare * to multiply — use \times or \cdot.
- Systems of equations: two separate $$...$$ blocks split by a blank line, not \begin{cases}.
- JSON escaping: double every backslash — "$$50\\%$$", "$$\\frac{a}{b}$$". A lone backslash corrupts silently instead of erroring, because \f \t \n \b are valid JSON escapes, so \frac \text \neq \beta turn into control characters.
- \n\n starts a new paragraph; a single \n is only a space.
- **bold**, *italic*, <u>underline</u>. Never start a line with "- " or "| " unless you mean a list or table.
- Readable table: HTML <table class="min-w-full divide-y divide-slate-200">.
- Any graph, figure, chart or picture-only table: put [Media:q##_description.png] at its exact spot (q05_graph.png, q12_triangle.png — never a generic name) and export that image under that filename.

Check before output: every field traceable to the document; stem, choices and explanation describe the same question; correct_choice matches the key; domain and subdomain are on the list and belong together; the count of $$ is even in every field; no repeated stem; question_number gapless; optional fields present only if the document had them; valid JSON with every backslash doubled.

Do not convert yet and do not ask for the document yet. First reply, under 100 words and in your own words: what you may take from the source versus never invent, which fields you omit, how you write and escape LaTeX, what you check before output. Then wait.
```
