<!-- Modal Hướng dẫn Nhập liệu & AI Prompt -->
<x-ui.modal id="importGuideModal" maxWidth="5xl" title="Teacher Import Guide & AI Prompt">
    <div x-data="{
        activeTab: 'overview',
        copied: false,
        copyPrompt() {
            const el = document.getElementById('aiPromptTextarea');
            if (el) {
                navigator.clipboard.writeText(el.value || el.textContent).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                });
            }
        }
    }" class="flex flex-col md:flex-row gap-5 min-h-[400px] md:h-[620px]">

        <!-- Tab Navigation (Sidebar-style in Modal) -->
        <div
            class="w-full md:w-56 shrink-0 flex flex-col gap-1 border-b md:border-b-0 md:border-r border-slate-200 pb-4 md:pb-0 md:pr-4">
            <button type="button" @click="activeTab = 'overview'"
                :class="activeTab === 'overview' ? 'bg-[var(--color-brand-soft)] text-brand font-bold' :
                    'text-slate-655 hover:bg-slate-50'"
                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 cursor-pointer border-0">
                <x-ui.icon name="info-circle" class="w-4 h-4" />
                <span>Quy trình Import</span>
            </button>
            <button type="button" @click="activeTab = 'formatting'"
                :class="activeTab === 'formatting' ? 'bg-[var(--color-brand-soft)] text-brand font-bold' :
                    'text-slate-655 hover:bg-slate-50'"
                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 cursor-pointer border-0">
                <x-ui.icon name="type-italic" class="w-4 h-4" />
                <span>Quy tắc Định dạng</span>
            </button>
            <button type="button" @click="activeTab = 'specs'"
                :class="activeTab === 'specs' ? 'bg-[var(--color-brand-soft)] text-brand font-bold' :
                    'text-slate-655 hover:bg-slate-50'"
                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 cursor-pointer border-0">
                <x-ui.icon name="file-earmark-code" class="w-4 h-4" />
                <span>Cấu trúc File (JSON/ZIP/CSV)</span>
            </button>
            <button type="button" @click="activeTab = 'prompt'"
                :class="activeTab === 'prompt' ? 'bg-[var(--color-brand-soft)] text-brand font-bold' :
                    'text-slate-655 hover:bg-slate-50'"
                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 cursor-pointer border-0">
                <x-ui.icon name="robot" class="w-4 h-4" />
                <span>AI Conversion Prompt</span>
            </button>
        </div>

        <!-- Tab Content -->
        <div class="flex-1 overflow-y-auto md:h-full pr-2 text-slate-700 text-sm leading-relaxed space-y-4">

            <!-- OVERVIEW TAB -->
            <div x-show="activeTab === 'overview'" class="space-y-4">
                <h4
                    class="text-base font-extrabold text-slate-800 border-b pb-1.5 flex items-center gap-2 m-0 font-sans">
                    <x-ui.icon name="list-ol" class="w-4 h-4 text-brand" /> Quy trình Import câu hỏi (3 bước)
                </h4>

                <div class="space-y-4 mt-2">
                    <div class="flex gap-3">
                        <span
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-[var(--color-brand-soft)] text-brand font-bold shrink-0 text-xs">1</span>
                        <div>
                            <strong class="text-slate-800 font-bold block">Chọn vị trí đích</strong>
                            <p class="text-xs text-slate-600 mt-1">Chọn đúng Module bài thi cần import (ví dụ: R&W
                                Module 1, Math Module 2). Nhập vị trí bắt đầu chèn câu hỏi. Hệ thống sẽ tự động dịch
                                chuyển các câu hỏi đứng sau vị trí này xuống dưới.</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <span
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-[var(--color-brand-soft)] text-brand font-bold shrink-0 text-xs">2</span>
                        <div>
                            <strong class="text-slate-800 font-bold block">Chọn phương thức tải dữ liệu</strong>
                            <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1.5 mt-1">
                                <li><strong>JSON (Paste hoặc File)</strong>: Phù hợp nhất khi có dữ liệu thô đã chuyển
                                    đổi bằng AI. Dán trực tiếp hoặc tải file <code>.json</code>.</li>
                                <li><strong>CSV</strong>: Phù hợp khi soạn bằng Excel. Tải file mẫu <code>.csv</code> để
                                    nhập đúng tên cột.</li>
                                <li><strong>ZIP (Bao gồm ảnh)</strong>: Bắt buộc dùng khi câu hỏi có đồ thị hoặc hình
                                    vẽ. Đóng gói file JSON/CSV kèm thư mục hình ảnh.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <span
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-[var(--color-brand-soft)] text-brand font-bold shrink-0 text-xs">3</span>
                        <div>
                            <strong class="text-slate-800 font-bold block">Xem trước (Preview) &amp; Lưu kết
                                quả</strong>
                            <p class="text-xs text-slate-600 mt-1">Nhấp nút <strong>Preview</strong> để chạy trình kiểm
                                định (Validator). Các lỗi như sai tên domain, thiếu câu trả lời đúng, thiếu passage (đối
                                với R&W)... sẽ hiển thị dạng danh sách lỗi màu đỏ để giáo viên kịp thời chỉnh sửa trước
                                khi chính thức lưu vào hệ thống.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORMATTING TAB -->
            <div x-show="activeTab === 'formatting'" class="space-y-4" style="display: none;">
                <h4
                    class="text-base font-extrabold text-slate-800 border-b pb-1.5 flex items-center gap-2 m-0 font-sans">
                    <x-ui.icon name="regex" class="w-4 h-4 text-brand" /> Quy tắc định dạng câu hỏi
                </h4>

                <div class="bg-[var(--color-brand-soft)]/40 rounded-xl p-4 border border-brand/20 space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <strong
                            class="text-brand flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider">
                            <x-ui.icon name="calculator" class="w-4 h-4" /> 1. Công thức Toán &amp; Biến số (LaTeX)
                        </strong>
                        <p class="text-xs text-slate-700 leading-normal">
                            Mọi biểu thức toán, phương trình, biến số (ví dụ: $$x$$, $$y$$), phép nhân chia, góc độ,
                            phân số đều phải được biểu diễn bằng LaTeX và bọc trong <strong>hai dấu đô-la</strong>
                            <code>$$...$$</code> (bao gồm cả dạng viết nội dòng).
                        </p>
                        <p class="text-xs text-slate-700 leading-normal font-bold">
                            Ràng buộc LaTeX quan trọng:
                        </p>
                        <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1 mt-1">
                            <li><strong>Phân số (\frac)</strong>: Bắt buộc phải thêm <code>\displaystyle</code> ở đầu công thức. Ví dụ: <code class="text-rose-600">$$\displaystyle \frac{1}{2}$$</code> thay vì <code class="text-rose-600">$$\frac{1}{2}$$</code>.</li>
                            <li><strong>Ký hiệu Hy Lạp &amp; Đặc biệt</strong>: Mọi ký hiệu như đô-la ($), phần trăm (%), pi (\pi), omega (\omega), theta (\theta), lớn hơn hoặc bằng (\ge), nhỏ hơn hoặc bằng (\le) đều bắt buộc phải dùng LaTeX, không dùng text trần.</li>
                            <li><strong>Escape dấu gạch chéo ngược (\) trong JSON</strong>: Khi viết trong JSON, bắt buộc phải viết đúp thành hai dấu gạch chéo ngược <code>\\</code> để tránh lỗi parser. Ví dụ: <code>"$$ \\\\$5 $$"</code>, <code>"$$ 50\\\\% $$"</code>, <code>"$$ \\\\pi $$"</code>, <code>"$$ \\\\displaystyle \\\\frac{1}{2} $$"</code>.</li>
                        </ul>
                        <div
                            class="bg-white/95 rounded-lg p-2.5 font-mono text-xs border border-brand/20 mt-1.5 text-slate-800">
                            Ví dụ thực tế:<br>
                            - Phân số hiển thị rộng: <code class="text-rose-600">$$\displaystyle \frac{1}{2}$$</code><br>
                            - Số mũ: <code class="text-rose-600">$$x^2 + 5x = 6$$</code><br>
                            - Độ: <code class="text-rose-600">$$180^\circ$$</code>
                        </div>
                    </div>

                    <div class="h-px bg-[var(--color-brand-soft)]/60"></div>

                    <div class="space-y-1.5">
                        <strong
                            class="text-brand flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider">
                            <x-ui.icon name="grid-3x3" class="w-4 h-4" /> 2. Bảng biểu dữ liệu (HTML Table)
                        </strong>
                        <p class="text-xs text-slate-700 leading-normal">
                            Khi cần tạo bảng dữ liệu có thể đọc và quét được (thay vì dùng ảnh), hãy mã hóa dưới dạng
                            bảng HTML <code>&lt;table&gt;</code> với class <code>min-w-full divide-y
                                divide-slate-200</code>.
                        </p>
                    </div>

                    <div class="h-px bg-[var(--color-brand-soft)]/60"></div>

                    <div class="space-y-1.5">
                        <strong
                            class="text-brand flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider">
                            <x-ui.icon name="image" class="w-4 h-4" /> 3. Ký hiệu Hình ảnh / Đồ thị (Media Placeholder)
                        </strong>
                        <p class="text-xs text-slate-700 leading-normal">
                            Nếu đề bài có hình vẽ đồ thị, biểu đồ hình học hoặc ảnh bảng biểu không thể chuyển sang
                            text:
                            <br>Hãy sử dụng placeholder có cấu trúc: <code
                                class="text-rose-600 font-bold">[Media:q##_mota.ext]</code> đặt tại đúng vị trí ảnh
                            xuất hiện.
                            <br><em>Ví dụ:</em> <code>[Media:q05_scatterplot.png]</code> hoặc
                            <code>[Media:q12_triangle.jpg]</code>.
                            <br>Tải lên bằng phương thức **ZIP** chứa các file ảnh tương ứng này.
                        </p>
                    </div>
                </div>
            </div>

            <!-- SPECS TAB -->
            <div x-show="activeTab === 'specs'" class="space-y-4" style="display: none;">
                <h4
                    class="text-base font-extrabold text-slate-800 border-b pb-1.5 flex items-center gap-2 m-0 font-sans">
                    <x-ui.icon name="file-earmark-code" class="w-4 h-4 text-brand" /> Cấu trúc File &amp; Schema
                </h4>

                <div class="space-y-3 mt-2">
                    <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/50">
                        <strong class="text-slate-800 block text-xs font-bold uppercase tracking-wider mb-1">
                                <x-ui.icon name="file-earmark-zip" class="w-4 h-4 text-brand mr-1" /> Gói ZIP chứa hình ảnh</strong>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Nếu import câu hỏi có hình ảnh, file ZIP phải chứa:
                            <br>1. Một file dữ liệu (ví dụ: <code>questions.json</code> hoặc <code>questions.csv</code>)
                            ở thư mục gốc.
                            <br>2. Các file ảnh đồ thị, nằm cùng thư mục hoặc trong thư mục con <code>images/</code>.
                        </p>
                    </div>

                    <!-- JSON SECTION -->
                    <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/50 space-y-2">
                        <strong class="text-slate-800 block text-xs font-bold uppercase tracking-wider mb-1">
                                <x-ui.icon name="filetype-json" class="w-4 h-4 text-brand mr-1" /> Ví dụ JSON hoàn chỉnh (Đầy đủ
                            thuộc tính)</strong>
                        <p class="text-[11px] text-slate-500 leading-normal mt-0">Mẫu JSON dưới đây biểu diễn 1 câu R&W
                            (Multiple Choice với passage, LaTeX, giải thích chi tiết), 1 câu Toán MCQ có bảng HTML, và 1
                            câu Toán SPR chứa đồ thị hình ảnh:</p>
                        <div
                            class="bg-white rounded-lg p-2.5 font-mono text-[11px] leading-normal border border-slate-200 max-h-56 overflow-y-auto">
                            <pre class="m-0 text-slate-800">{
  "items": [
    {
      "question_number": 1,
      "question_type": "multiple_choice",
      "passage": "Scientists recently analyzed the atmospheric composition of exoplanet Kepler-186f. They discovered trace water vapor...",
      "stem": "Which choice best states the main idea of the text?",
      "difficulty": "medium",
      "skill_domain": "craft_and_structure",
      "skill_subdomain": "text_structure_and_purpose",
      "choices": {
        "A": "Kepler-186f has oceans of stable liquid water.",
        "B": "Recent analyses suggest water vapor is present, though ocean stability remains debated.",
        "C": "Low pressure completely rules out water on the exoplanet.",
        "D": "Earth and Kepler-186f have identical atmospheric profiles."
      },
      "correct_choice": "B",
      "explanation": "The text discusses water vapor and pressure counter-arguments.",
      "rationale_a": "Incorrect because ocean stability is disputed.",
      "rationale_b": "Correct because it captures the main idea.",
      "rationale_c": "Incorrect because 'completely rules out' is too extreme.",
      "rationale_d": "Incorrect because profiles are different.",
      "strategy_tip": "Identify both findings and limitations.",
      "common_mistakes": "Ignoring critics' reservations in choice A.",
      "is_pretest": false,
      "calculator_allowed": true,
      "external_id": "RW-01"
    },
    {
      "question_number": 2,
      "question_type": "multiple_choice",
      "stem": "Determine the slope of the linear relationship below:\n\n<table class=\"min-w-full divide-y divide-slate-200\"><thead><tr><th>$$x$$</th><th>$$y$$</th></tr></thead><tbody><tr><td>$$1$$</td><td>$$5$$</td></tr><tr><td>$$3$$</td><td>$$11$$</td></tr></tbody></table>",
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
      "explanation": "Slope formula gives $$(11 - 5)/(3 - 1) = 3$$.",
      "is_pretest": false,
      "calculator_allowed": true
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
      "explanation": "$$\\tan(\\theta) = \\text{Opposite}/\\text{Adjacent} = 4/3$$.",
      "is_pretest": false,
      "calculator_allowed": true
    }
  ]
}</pre>
                        </div>
                    </div>

                    <!-- CSV SECTION -->
                    <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/50 space-y-2">
                        <strong class="text-slate-800 block text-xs font-bold uppercase tracking-wider mb-1">
                                <x-ui.icon name="file-earmark-spreadsheet" class="w-4 h-4 text-brand mr-1" /> Định dạng CSV mẫu (Raw
                            CSV Text)</strong>
                        <p class="text-[11px] text-slate-550 leading-normal mt-0">Bạn có thể sao chép đoạn văn bản thô
                            dưới đây, lưu vào file dạng <code>.csv</code> (mã hóa UTF-8) để mở trực tiếp trong Excel
                            hoặc Google Sheets:</p>
                        <div
                            class="bg-white rounded-lg p-2.5 font-mono text-[10px] leading-normal border border-slate-200 max-h-48 overflow-y-auto">
                            <pre class="m-0 text-slate-800">question_type,difficulty,skill_domain,skill_subdomain,stem,passage_content,passage_genre,choice_a_content,choice_b_content,choice_c_content,choice_d_content,correct_choice,spr_correct_answers,spr_hint,explanation,strategy_tip,common_mistakes,is_pretest,calculator_allowed,external_id
multiple_choice,medium,craft_and_structure,words_in_context,"As used in the text, what does ""vital"" mean?","Notes were vital to the team's success.",natural_science,useless,essential,optional,secondary,B,,,Notes were essential.,Context clues.,Secondary meaning trap,0,1,RW-CSV-01
student_produced_response,hard,algebra,linear_equations_in_one_variable,"If $$3x - 5 = 10$$, what is $$x$$?",,,,,,,,5,Enter integer.,$$3x = 15 \Rightarrow x = 5$$.,Isolate variable.,Arithmetic error.,0,1,M-CSV-02</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PROMPT TAB -->
            <div x-show="activeTab === 'prompt'" style="display: none;">
                <div class="space-y-3 flex flex-col h-full">
                    <div class="flex justify-between items-center border-b pb-1.5">
                        <h4 class="text-base font-extrabold text-slate-800 flex items-center gap-2 m-0 font-sans">
                            <x-ui.icon name="robot" class="w-4 h-4 text-brand" /> AI Conversion Prompt
                        </h4>
                        <button type="button" @click="copyPrompt()"
                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand hover:bg-brand-hover text-white rounded-lg text-xs font-bold transition-colors cursor-pointer border-0 shadow-xs">
                            <x-ui.icon name="check-lg" class="w-4 h-4" x-show="copied" />
                            <x-ui.icon name="copy" class="w-4 h-4" x-show="!copied" />
                            <span x-text="copied ? 'Đã sao chép!' : 'Sao chép Prompt'"></span>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed mt-0">
                        Hãy copy prompt bên dưới và dán vào ChatGPT hoặc Claude cùng với file OCR/text đề thi của bạn. AI sẽ
                        chuyển đổi dữ liệu thô thành file JSON cực kỳ chính xác để import.
                    </p>
                    <div class="flex-1 relative">
                        <textarea id="aiPromptTextarea" readonly
                            class="w-full h-80 p-3 bg-slate-50 border border-slate-200 rounded-xl font-mono text-[11px] leading-relaxed text-slate-700 focus:outline-hidden"
                            style="resize: none;">@include('components.admin.test-builder.questions.ai-conversion-prompt')</textarea>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <x-slot:footer>
        <button type="button" x-on:click="$dispatch('close-modal', 'importGuideModal')"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-semibold transition-colors cursor-pointer border border-slate-250">
            Đóng
        </button>
    </x-slot:footer>
</x-ui.modal>
