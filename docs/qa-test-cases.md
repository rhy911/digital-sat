# Digital SAT - Manual QA Test Cases

Nguồn deep dive: `repomix-output.xml`, routes web/API, controllers, requests, policies, services, jobs, models, migrations, views Blade, JS test-taking và dashboard.

Mục tiêu tài liệu: thiết kế manual test case theo hướng người dùng thử nghiệm trên trình duyệt. Tester nên ưu tiên quan sát hành vi UI thật: trang nào hiển thị, nút nào bấm được, dữ liệu có được lưu, thông báo lỗi có dễ hiểu, và user có bị chặn đúng quyền hay không. Thunder Client chỉ dùng bổ sung khi cần xác minh security/validation sau UI.

## Hướng Dẫn Thực Hiện Test Cho Người Mới Bắt Đầu

Chào mừng bạn! Tài liệu này liệt kê các kịch bản kiểm thử (Test Cases) để đảm bảo hệ thống hoạt động đúng. Nếu bạn chưa từng làm QA hoặc chưa biết cách "gửi request", hãy đọc kỹ phần hướng dẫn dưới đây.

### 1. Test trên giao diện (UI Testing) - Cơ bản và quan trọng nhất

Hầu hết các kịch bản test đều được thiết kế để thực hiện trực tiếp trên giao diện trình duyệt (Chrome, Edge, Firefox, Safari...). Bạn đóng vai trò là một người dùng thử nghiệm:

1. **Mở trình duyệt** và truy cập trang web (ví dụ: `http://127.0.0.1:8000` hoặc địa chỉ chạy thử nghiệm).
2. **Thực hiện các thao tác** theo đúng thứ tự ghi ở cột **"Buớc thực hiện"** (ví dụ: bấm nút đăng ký, nhập form, chọn đáp án).
3. **Đối chiếu kết quả** thực tế trên màn hình với cột **"Kết quả mong đợi"**. Nếu khớp -> Test **PASS** (Đạt). Nếu màn hình báo lỗi, bị trắng trang, hoặc không lưu dữ liệu -> Test **FAIL** (Lỗi).

---

### 2. Cách kiểm tra dữ liệu ngầm (Inspect Network & Console)

Một số tiến trình như "Autosave" (tự động lưu bài làm) chạy ngầm dưới nền mà không báo thành công lên màn hình chính. Để kiểm tra:

1. Nhấn phím **F12** (hoặc chuột phải chọn **Inspect / Kiểm tra**) để mở Developer Tools của trình duyệt.
2. **Tab Console (Bảng điều khiển):** Nơi hiện thông tin/lỗi lập trình. Nếu thấy các dòng chữ màu đỏ báo lỗi (như `Uncaught TypeError` hay `500 Internal Server Error`), ứng dụng có thể đang bị crash.
3. **Tab Network (Mạng):** Nơi ghi nhận toàn bộ dữ liệu gửi đi và nhận về từ server.
   - Khi bạn chọn một đáp án trắc nghiệm, hãy quan sát tab Network: Một request mới tên là `autosave-module` sẽ xuất hiện.
   - Click vào request đó, xem cột **Status (Trạng thái)**:
     - `200` hoặc `201` (màu xanh/đen): Thành công (dữ liệu đã được lưu thành công vào database).
     - `403` (Bị cấm), `419` (Hết hạn CSRF), hoặc `500` (Lỗi server hệ thống): Thất bại.

---

### 3. Cách tự gửi Request để kiểm tra bảo mật (API Security Testing)

Các bài test bảo mật (ví dụ như `SECUR-M-004`: Học sinh A không được sửa/lưu bài của học sinh B) yêu cầu chúng ta phải giả lập việc gửi dữ liệu trực tiếp lên API mà không qua giao diện thông thường.

#### Sử dụng công cụ Thunder Client trong VS Code (hoặc Postman)

1. **Cài đặt:** Trên thanh công cụ bên trái VS Code, bấm vào biểu tượng Extensions -> Tìm kiếm và cài đặt **Thunder Client**.
2. **Lấy thông tin đăng nhập (Cookie & Session):**
   - Đăng nhập tài khoản kiểm thử (ví dụ: Student A) trên trình duyệt web.
   - Nhấn **F12** -> chọn tab **Network**.
   - Bấm vào một yêu cầu bất kỳ trong danh sách mạng -> Xem phần **Headers** -> tìm đến dòng **`Cookie`** dưới mục **Request Headers**.
   - Copy toàn bộ chuỗi ký tự dài nằm sau chữ `Cookie:`. Đây chính là thông tin xác thực danh tính của bạn.
3. **Gửi Request từ Thunder Client:**
   - Mở Thunder Client từ thanh menu bên trái VS Code -> Bấm **New Request**.
   - Chọn phương thức phù hợp ở ô kế bên thanh URL: `GET`, `POST`, `PUT` hoặc `DELETE`.
   - Nhập URL cần test (ví dụ: `http://127.0.0.1:8000/api/test/autosave-module`).
   - Chọn tab **Headers** của Thunder Client, thêm dòng:
     - **Key:** `Cookie`
     - **Value:** [Dán chuỗi Cookie bạn vừa copy ở trình duyệt vào].
   - **Xử lý CSRF Token (Bảo mật Laravel):** Đối với các request chỉnh sửa dữ liệu (`POST`/`PUT`/`DELETE`), bạn cần thêm header chống giả mạo:
     - Mở F12 trên trình duyệt -> chọn tab **Application** (Chrome) hoặc **Storage** (Firefox) -> Tìm danh sách **Cookies** -> Click vào domain web -> Tìm cookie có tên `XSRF-TOKEN` và copy giá trị của nó.
     - Trong tab **Headers** của Thunder Client, thêm:
       - **Key:** `X-XSRF-TOKEN` hoặc `X-CSRF-TOKEN`
       - **Value:** [Dán giá trị `XSRF-TOKEN` đã copy].
   - **Nhập dữ liệu gửi đi (Request Body):** Chuyển sang tab **Body** trong Thunder Client -> Chọn kiểu **JSON** -> Nhập dữ liệu muốn kiểm tra (ví dụ: `{"question_id": 999, "selected_choice": "A"}`).
   - Bấm **Send** và quan sát mã phản hồi (Response Status Code):
     - `200 OK` hoặc `201 Created`: Thành công.
     - `403 Forbidden` / `401 Unauthorized`: Bị chặn (đây là kết quả mong đợi của các case test bảo mật).

#### Mẹo sao chép nhanh (Copy as Fetch/cURL)

Thay vì tự tay điền từng Header và Cookie phức tạp, bạn có thể sao chép trực tiếp từ trình duyệt:

1. Mở trang web cần test, mở **F12** -> tab **Network**.
2. Click chọn 1 đáp án để trình duyệt tự gửi một request lưu bài đi.
3. Nhấp chuột phải vào request đó trong danh sách Network -> Chọn **Copy** -> **Copy as fetch** (hoặc **Copy as cURL**).
4. Mở Thunder Client/Postman -> Chọn **Import** -> Dán toàn bộ nội dung vừa copy vào. Mọi thông tin Header, Cookie và Body sẽ được tự động điền đầy đủ.
5. Lúc này, bạn chỉ cần chỉnh sửa ID của đối tượng trong Body hoặc URL để thử nghiệm xem hệ thống có bảo mật tốt không (ví dụ đổi ID bài thi của mình thành ID bài thi người khác rồi bấm Send xem có bị chặn `403` hay không).

---

### 4. Hướng dẫn chạy thử mẫu: Test Case `AUTH-M-007` (Login Student thành công)

1. **Chuẩn bị dữ liệu:** Bạn đã đăng ký một tài khoản Student và đã kích hoạt email (Verified).
2. **Thao tác:**
   - Mở trình duyệt web của bạn, truy cập URL: `http://127.0.0.1:8000/signin` (hoặc domain kiểm thử tương ứng).
   - Điền đúng Email và Mật khẩu của tài khoản student đã chuẩn bị.
   - Đảm bảo chọn đúng vai trò (Role) là `Student` (nếu có lựa chọn này trên giao diện).
   - Nhấp vào nút **Submit / Đăng nhập**.
3. **Đối chiếu kết quả:**
   - Xem trình duyệt có tự động chuyển hướng bạn đến trang `/home` hay không.
   - Nhìn lên góc trên thanh công cụ/header để xem Tên tài khoản hiển thị có đúng tên của bạn không.
   - Nếu cả hai điều trên đều đúng, test case này được đánh giá là **PASS** (Đạt).

---

## 1. Phạm vi chính

| Vùng test         | Vai trò người dùng                                         | Màn hình/luồng                                                                     |
| ----------------- | ---------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Identity & access | Guest, student, teacher, admin                             | Landing, signup, signin, email verify, forgot/reset password, logout               |
| Student portal    | Student verified                                           | Home, Test Preview, Choose Test, My Practice, Score Details                        |
| Test taking       | Student verified                                           | Reading & Writing, Math, autosave, review screen, submit, adaptive routing         |
| Content dashboard | Teacher, admin                                             | Practice Tests, Sections, Modules, Question Bank, Easy Builder, New Content wizard |
| Import/media      | Teacher, admin                                             | JSON/CSV/ZIP import, preview, media upload                                         |
| Authorization     | Guest, unverified, student, teacher owner/non-owner, admin | Route gating, ownership, public/shared visibility                                  |

## 2. Test data cần chuẩn bị

| Dữ liệu                 | Yêu cầu                                                                                         |
| ----------------------- | ----------------------------------------------------------------------------------------------- |
| Student verified        | Login được, email đã verify                                                                     |
| Student unverified      | Login đúng mật khẩu nhưng chưa verify email                                                     |
| Teacher 1 verified      | Có một số test/section/module/question do mình tạo                                              |
| Teacher 2 verified      | Có dữ liệu riêng để test truy cập chéo                                                          |
| Admin verified          | Có quyền xem/sửa/xóa toàn bộ dashboard                                                          |
| Active full-length test | Status `active`, có R&W M1, R&W M2 easy/hard, Math M1, Math M2 easy/hard, mỗi module có câu hỏi |
| Draft/archived test     | Không được student thấy trong Choose Test                                                       |
| Test Preview            | Active, có module preview, duration có thể bằng 0                                               |
| R&W question            | MCQ có passage, 4 choices A-D, explanation                                                      |
| Math MCQ                | Không cần passage                                                                               |
| Math SPR                | Input tự luận ngắn, có đáp án đúng                                                              |
| In-progress attempt     | UserTest `in_progress` để test resume/autosave                                                  |
| Completed attempt       | UserTest `completed`, có correct/incorrect/omitted để test score                                |
| Import files            | CSV hợp lệ, CSV lỗi header, JSON hợp lệ, JSON lỗi, ZIP có media, ZIP lỗi                        |
| Media files             | PNG/JPG/WebP hợp lệ <=2MB, PDF/SVG/BMP/ảnh >2MB                                                 |

## 3. Quy tắc ghi kết quả

| Trường        | Nội dung                                          |
| ------------- | ------------------------------------------------- |
| Mã TC         | Ví dụ `AUTH-M-001`, `TAKE-M-010`                  |
| Role          | Guest/student/teacher/admin                       |
| Điều kiện đầu | Data, login state, browser, viewport              |
| Bước lặp lại  | Các thao tác UI theo thứ tự                       |
| Expected      | Kết quả quan sát được trên UI và dữ liệu được lưu |
| Actual        | Kết quả thật                                      |
| Evidence      | Screenshot, URL, console/network nếu cần          |
| Severity      | Blocker/Critical/Major/Minor                      |

Bug nghiêm trọng:

- User xem/sửa/xóa dữ liệu của user khác.
- Student vào được `/test-dashboard`.
- Unverified user vào được `/home` hoặc `/take-test`.
- UI thao tác bình thường gây 500, màn hình trắng, mất bài làm, mất điểm.
- Submit bài xong không có kết quả, kết quả sai, hoặc attempt không chuyển completed.

## 4. Identity & Access

| Mã TC      | Kịch bản                     | Điều kiện đầu                         | Bước thực hiện                                                       | Kết quả mong đợi                                                                            | Priority |
| ---------- | ---------------------------- | ------------------------------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- | -------- |
| AUTH-M-001 | Guest xem landing page       | Chưa login                            | Mở `/`                                                               | Landing page render bình thường, có đường vào signin/signup, không lỗi console nghiêm trọng | P1       |
| AUTH-M-002 | User đã login vào landing    | Student verified đã login             | Mở `/`                                                               | Được redirect về `/home`, không thấy landing guest                                          | P1       |
| AUTH-M-003 | Đăng ký student thành công   | Guest, email/username mới             | Mở `/signup`, nhập username/email/password >=8, role student, submit | User được tạo, đăng nhập, được đưa đến trang verify email hoặc thông báo cần verify         | P1       |
| AUTH-M-004 | Đăng ký thiếu/sai field      | Guest                                 | Submit signup với email sai, password ngắn, confirmation không khớp  | Form hiện lỗi validation gần field, không tạo account                                       | P1       |
| AUTH-M-005 | Đăng ký email/username trùng | Guest, có user tồn tại                | Đăng ký lại cùng email hoặc username                                 | Hiện lỗi duplicate, không login thành user mới                                              | P1       |
| AUTH-M-006 | Đăng ký teacher bị khóa      | Guest                                 | Chọn/submit role teacher nếu UI cho phép hoặc request role teacher   | Hệ thống từ chối teacher signup, không tạo teacher                                          | P2       |
| AUTH-M-007 | Login student thành công     | Student verified                      | Mở `/signin`, nhập email/password, role student, submit              | Login thành công, vào `/home`, header hiện username                                         | P1       |
| AUTH-M-008 | Login sai mật khẩu           | Guest                                 | Nhập email đúng, password sai                                        | Ở lại signin, hiện lỗi, không có session login                                              | P1       |
| AUTH-M-009 | Login sai role               | Có teacher account                    | Đăng nhập teacher nhưng chọn role student                            | Hiện lỗi role mismatch, không vào home/dashboard                                            | P1       |
| AUTH-M-010 | Login unverified             | Student chưa verify                   | Đăng nhập đúng credential                                            | Bị đưa đến `/email-verify`, không vào `/home`                                               | P1       |
| AUTH-M-011 | Resend verification          | User unverified đã login              | Tại `/email-verify`, bấm gửi lại email                               | Hiện message đã gửi lại hoặc email đã verify, không lỗi 500                                 | P2       |
| AUTH-M-012 | Verify email link hợp lệ     | User chưa verify, link signed còn hạn | Mở link verify từ email/log                                          | Trang verified hiển thị, user được mark verified và có thể vào `/home`                      | P1       |
| AUTH-M-013 | Verify email link bị sửa     | Link verify bị sửa id/hash/signature  | Mở link đã sửa                                                       | Bị từ chối/redirect signin với lỗi, user không được verify                                  | P1       |
| AUTH-M-014 | Forgot password              | Guest, email tồn tại                  | Mở `/forgot`, nhập email, submit                                     | Hiện thông báo gửi reset link, không leak email không tồn tại qua UI quá rõ                 | P2       |
| AUTH-M-015 | Reset password thành công    | Có reset token hợp lệ                 | Mở `/reset-password/{token}?email=...`, nhập password mới, submit    | Password đổi, redirect signin, login bằng password mới thành công                           | P1       |
| AUTH-M-016 | Logout từ home               | User đã login                         | Bấm icon/logout trên header                                          | Session kết thúc, về landing/signin, back browser không mở lại protected page               | P1       |

## 5. Student Portal

| Mã TC        | Kịch bản                         | Điều kiện đầu                                 | Bước thực hiện                                         | Kết quả mong đợi                                                                   | Priority |
| ------------ | -------------------------------- | --------------------------------------------- | ------------------------------------------------------ | ---------------------------------------------------------------------------------- | -------- |
| PORTAL-M-001 | Home dashboard mặc định          | Student verified                              | Login và vào `/home`                                   | Hiện lời chào username, khu vực Your Tests, Practice, BigFuture, không lỗi layout  | P1       |
| PORTAL-M-002 | Home không có bài đã làm         | Student không có attempt                      | Vào `/home`, chọn tab/panel Past trong Practice nếu có | Empty state "Ready to Practice?" hoặc "You Haven't Taken..." hiện đúng             | P2       |
| PORTAL-M-003 | Home có in-progress và completed | Student có 1 attempt in_progress, 1 completed | Vào `/home`, mở Practice Past                          | Card in-progress và completed hiện đúng title/status/score link                    | P1       |
| PORTAL-M-004 | Guest vào home                   | Chưa login                                    | Mở `/home`                                             | Redirect signin/unauthenticated, không render data                                 | P1       |
| PORTAL-M-005 | Unverified vào home              | Student unverified login                      | Mở `/home`                                             | Redirect `/email-verify`, có warning                                               | P1       |
| PORTAL-M-006 | Test Preview card                | Student verified                              | Vào `/home`, bấm Test Preview                          | Mở preview/take-test flow, không cần chọn test                                     | P1       |
| PORTAL-M-007 | Choose full-length practice      | Có active test                                | Vào `/home`, bấm Full-Length Practice                  | Mở `/choose-test`, hiện select "Choose a test" với active tests                    | P1       |
| PORTAL-M-008 | Choose test Next khi chưa chọn   | Student verified                              | Vào `/choose-test`, bấm Next mà chưa chọn              | Không vào test không hợp lệ; tester ghi bug nếu Next đi đến `#` gây confused/block | P2       |
| PORTAL-M-009 | Choose active test               | Có active full-length test                    | Chọn test trong custom select, bấm Next                | URL sang `/take-test/{module_ulid}`, bắt đầu module đầu tiên                       | P1       |
| PORTAL-M-010 | Draft/archived không hiện        | Có draft/archived/active                      | Mở `/choose-test`                                      | Chỉ thấy active tests, không thấy draft/archived                                   | P1       |
| PORTAL-M-011 | My Practice của mình             | Student có completed attempt                  | Bấm completed card hoặc mở `/my-practice/{id}`         | Trang practice hiện đúng attempt của user, không lỗi                               | P2       |
| PORTAL-M-012 | Cross-user practice              | Student B, có attempt của Student A           | B mở URL `/my-practice/{id của A}`                     | 404/403, không thấy dữ liệu của A                                                  | P1       |

## 6. Test Taking - Core Experience

| Mã TC      | Kịch bản                          | Điều kiện đầu                                     | Bước thực hiện                                                                          | Kết quả mong đợi                                                                           | Priority |
| ---------- | --------------------------------- | ------------------------------------------------- | --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ | -------- |
| TAKE-M-001 | Mở R&W module                     | Student verified, module R&W có passage/questions | Vào `/take-test/{rw_module_ulid}`                                                       | Header section/module đúng, passage trái, question phải, choices A-D, timer hiện           | P1       |
| TAKE-M-002 | Mở Math MCQ module                | Module Math MCQ không SPR                         | Vào module Math                                                                         | Layout 1 cột cho MCQ, không hiện panel passage thừa, calculator button nếu có trong header | P1       |
| TAKE-M-003 | Mở Math SPR                       | Module Math có SPR                                | Chuyển đến câu SPR                                                                      | Hiện directions SPR bên trái, input answer, Answer Preview cập nhật                        | P1       |
| TAKE-M-004 | Next/Back navigation              | Module có nhiều câu                               | Bấm Next, Back, chọn câu trong popover                                                  | Câu/passage/question number cập nhật đúng, không mất đáp án đã chọn                        | P1       |
| TAKE-M-005 | Chọn answer MCQ                   | Câu MCQ                                           | Chọn B, đi câu khác rồi quay lại                                                        | B vẫn checked, question nav đánh dấu answered                                              | P1       |
| TAKE-M-006 | Đổi answer MCQ                    | Câu MCQ đã chọn B                                 | Chọn D                                                                                  | Chỉ D checked, trạng thái answered vẫn đúng                                                | P1       |
| TAKE-M-007 | Mark for Review                   | Câu bất kỳ                                        | Bấm Mark for Review, mở review screen                                                   | Câu được đánh dấu for review bằng màu/icon, toggle lại thì bỏ mark                         | P2       |
| TAKE-M-008 | Cross out choices                 | Câu MCQ                                           | Bấm icon cross-out trên choice, chọn choice đã bị cross                                 | Choice bị gạch, nếu chọn choice bị gạch thì gạch được bỏ và radio checked                  | P2       |
| TAKE-M-009 | Bật/tắt cross-out mode            | Câu MCQ                                           | Bấm nút ABC cross-out toggle                                                            | Strike buttons ẩn/hiện theo trạng thái, không ảnh hưởng đáp án đã chọn                     | P3       |
| TAKE-M-010 | SPR input hợp lệ                  | Math SPR                                          | Nhập `3.5`, `7/2`, `-1.25`                                                              | Input chấp nhận ký tự hợp lệ, preview hiện đúng                                            | P1       |
| TAKE-M-011 | SPR input ký tự cấm               | Math SPR                                          | Thử nhập/paste `abc,$% 12`                                                              | Ký tự không hợp lệ bị chặn/loại bỏ, không submit ký tự cấm                                 | P1       |
| TAKE-M-012 | SPR maxlength                     | Math SPR                                          | Nhập số dương >5 ký tự và số âm >6 ký tự                                                | Input bị giới hạn 5/6 ký tự theo rule UI                                                   | P2       |
| TAKE-M-013 | Highlight text                    | Module có highlight button                        | Bấm highlight mode, select text trong passage/question                                  | Text được highlight, double click vào highlight để bỏ                                      | P2       |
| TAKE-M-014 | Resize panels                     | R&W hoặc SPR có 2 panel                           | Kéo divider, double click divider                                                       | Panel resize trong giới hạn, double click reset 50/49, không vỡ layout                     | P2       |
| TAKE-M-015 | Calculator Math                   | Math module                                       | Bấm calculator, đổi tab graphing/scientific, kéo/resize modal, đóng                     | Calculator mở/đóng, tab đổi đúng, không che mất vĩnh viễn nút điều hướng                   | P2       |
| TAKE-M-016 | Review screen                     | Đến câu cuối, bấm Next                            | Hiện "Check your Work", legend Unanswered/For Review/Answered, list câu đúng trạng thái | P1                                                                                         |
| TAKE-M-017 | Quay từ review về câu cuối        | Đang ở review screen                              | Bấm Back                                                                                | Quay về câu cuối, answers/marks còn                                                        | P1       |
| TAKE-M-018 | Submit có confirm                 | Ở review screen                                   | Bấm Next, cancel confirm                                                                | Không submit, vẫn ở review/test                                                            | P1       |
| TAKE-M-019 | Submit confirm                    | Ở review screen                                   | Bấm Next, confirm                                                                       | Hiện loading saving/scoring, sau đó chuyển module tiếp hoặc result                         | P1       |
| TAKE-M-020 | Auto-submit hết giờ               | Module duration ngắn                              | Chờ timer về 00:00                                                                      | Hiện alert Time Up, tự submit module không cần confirm                                     | P1       |
| TAKE-M-021 | Untimed preview/module duration 0 | Test Preview duration 0                           | Mở module preview                                                                       | Timer hiện 00:00 nhưng không auto-submit liên tục                                          | P2       |
| TAKE-M-022 | Cảnh báo refresh/close            | Đang làm bài                                      | Bấm refresh/close tab                                                                   | Browser warning hiện; nếu user tiếp tục refresh, autosave gần nhất có thể khôi phục        | P2       |
| TAKE-M-023 | Back browser bị chặn              | Đang làm bài                                      | Bấm browser Back                                                                        | Không rời khỏi test, hiện alert hoặc vẫn ở trang test                                      | P2       |
| TAKE-M-024 | Dev shortcut/right-click          | Đang làm bài                                      | Bấm F12/Ctrl+U/right-click                                                              | UI chặn theo JS; không ảnh hưởng làm bài                                                   | P3       |

## 7. Test Taking - Autosave, Resume, Routing

| Mã TC      | Kịch bản                          | Điều kiện đầu                                                  | Bước thực hiện                                            | Kết quả mong đợi                                                                 | Priority |
| ---------- | --------------------------------- | -------------------------------------------------------------- | --------------------------------------------------------- | -------------------------------------------------------------------------------- | -------- |
| FLOW-M-001 | Autosave MCQ                      | Attempt real, không preview                                    | Chọn answer, đổi câu, đổi lại trang sau 1 giây            | Network autosave thành công; reload module vẫn thấy answer đã lưu                | P1       |
| FLOW-M-002 | Autosave SPR                      | Attempt real, Math SPR                                         | Nhập SPR, đổi câu/reload                                  | Giá trị SPR được lưu và hiện lại                                                 | P1       |
| FLOW-M-003 | Autosave không chạy trong preview | Test Preview                                                   | Chọn answers, quan sát Network/local behavior             | Không gọi `/test/autosave-module` nếu `isPreview=true`; preview vẫn đi tiếp được | P2       |
| FLOW-M-004 | Resume in-progress                | Student có attempt in_progress                                 | Rời test sau autosave, vào home/practice và mở lại module | Answers đã lưu hiện lại; không tạo duplicate attempt                             | P1       |
| FLOW-M-005 | Submit R&W M1 routing hard/easy   | R&W M1 có đáp án tạo theta cao/thấp, M2 easy/hard có questions | Làm M1 đúng nhiều/ít, submit                              | Được route sang R&W M2 hard/easy tương ứng, URL/module name đúng                 | P1       |
| FLOW-M-006 | Fallback module unavailable       | M2 được route bị thiếu question, path còn lại có question      | Submit M1                                                 | Hiện warning fallback và countdown, chuyển sang module thay thế                  | P2       |
| FLOW-M-007 | Next section sau M2               | Hoàn thành R&W M2, Math M1 tồn tại                             | Submit                                                    | Chuyển sang Math Module 1                                                        | P1       |
| FLOW-M-008 | Final submit completed            | Hoàn thành Math M2 cuối                                        | Submit                                                    | Test status completed, redirect score page, total score hiện                     | P1       |
| FLOW-M-009 | Scoring timeout/polling error     | Mô phỏng queue chậm/lỗi                                        | Submit module                                             | UI hiện lỗi scoring timeout/polling dễ hiểu, không trắng trang                   | P2       |
| FLOW-M-010 | Cross-user submit bị chặn         | Student B dùng URL/request của attempt A                       | Thử submit/autosave attempt A                             | 403/404, không sửa answer của A                                                  | P1       |

## 8. Score Details & Review

| Mã TC       | Kịch bản                    | Điều kiện đầu                      | Bước thực hiện                                              | Kết quả mong đợi                                                          | Priority |
| ----------- | --------------------------- | ---------------------------------- | ----------------------------------------------------------- | ------------------------------------------------------------------------- | -------- |
| SCORE-M-001 | Mở score completed          | Student có completed attempt       | Bấm score/details từ card hoặc mở `/my-practice/{id}/score` | Hero hiện total score /1600, title test, date completed                   | P1       |
| SCORE-M-002 | Stats all                   | Completed có correct/wrong/omitted | Xem khu Question Review tab All                             | Total/Correct/Incorrect-Omitted khớp dữ liệu                              | P1       |
| SCORE-M-003 | Tab R&W                     | Completed có câu R&W               | Bấm Reading & Writing tab                                   | Domain và table chỉ hiện R&W, stats cập nhật                              | P2       |
| SCORE-M-004 | Tab Math                    | Completed có câu Math              | Bấm Math tab                                                | Domain và table chỉ hiện Math, stats cập nhật                             | P2       |
| SCORE-M-005 | Omitted answer              | Có câu bỏ trống                    | Xem table/modal                                             | Your Answer hiện Omitted, tính vào Incorrect/Omitted                      | P1       |
| SCORE-M-006 | Pretest question không tính | Attempt có pretest answers         | Xem score                                                   | Pretest không xuất hiện/tính vào stats                                    | P2       |
| SCORE-M-007 | Review modal MCQ            | Câu MCQ có choices/explanation     | Bấm row/question review                                     | Modal hiện stem, choices, your answer, correct answer, explanation        | P1       |
| SCORE-M-008 | Review modal SPR            | Câu SPR                            | Bấm row/question review                                     | Modal không hiện choices MCQ, hiện your answer/correct answer/explanation | P1       |
| SCORE-M-009 | Sticky tabs                 | Scroll score page                  | Cuộn qua hero                                               | Tabs bar sticky, không che mất nội dung bất thường                        | P3       |
| SCORE-M-010 | Cross-user score            | Student B mở score của A           | Mở `/my-practice/{id của A}/score`                          | 404/403, không leak score của A                                           | P1       |

## 9. Dashboard Access & Navigation

| Mã TC      | Kịch bản                         | Điều kiện đầu       | Bước thực hiện                                | Kết quả mong đợi                                           | Priority |
| ---------- | -------------------------------- | ------------------- | --------------------------------------------- | ---------------------------------------------------------- | -------- |
| DASH-M-001 | Student vào dashboard            | Student verified    | Mở `/test-dashboard`                          | 403/redirect, không hiện Content Suite                     | P1       |
| DASH-M-002 | Guest vào dashboard              | Chưa login          | Mở `/test-dashboard`                          | Redirect signin/unauthenticated                            | P1       |
| DASH-M-003 | Unverified teacher vào dashboard | Teacher chưa verify | Mở `/test-dashboard`                          | Redirect email verify                                      | P1       |
| DASH-M-004 | Teacher vào dashboard            | Teacher verified    | Mở `/test-dashboard`                          | Hiện Content Suite, sidebar tabs, role Teacher             | P1       |
| DASH-M-005 | Admin vào dashboard              | Admin verified      | Mở `/test-dashboard`                          | Hiện Content Suite, role Administrator                     | P1       |
| DASH-M-006 | Tab persistence                  | Teacher/admin       | Chọn tab Modules/Questions, reload page       | Tab active được nhớ bằng sessionStorage                    | P2       |
| DASH-M-007 | Refresh Data                     | Dashboard có data   | Bấm Refresh Data                              | Snapshot/list cập nhật, không mất active tab, không lỗi JS | P2       |
| DASH-M-008 | Home/logout header               | Dashboard           | Bấm home icon, quay lại dashboard; bấm logout | Home icon về `/home`; logout kết thúc session              | P2       |

## 10. Dashboard - Practice Tests

| Mã TC      | Kịch bản                 | Điều kiện đầu                                 | Bước thực hiện                                                           | Kết quả mong đợi                                                                               | Priority |
| ---------- | ------------------------ | --------------------------------------------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------- | -------- |
| TEST-M-001 | Empty state tests        | Account không có tests                        | Mở tab Practice Tests                                                    | Empty state "No tests created yet", Create Your First Test mở offcanvas                        | P2       |
| TEST-M-002 | Create active test       | Teacher/admin                                 | Bấm Create Test, nhập title, type, break duration, status active, submit | Offcanvas đóng/alert success, test mới xuất hiện trong table                                   | P1       |
| TEST-M-003 | Create test validation   | Teacher/admin                                 | Bỏ trống title hoặc break duration âm, submit                            | UI/response hiện validation, không tạo test                                                    | P1       |
| TEST-M-004 | Search tests             | Có nhiều tests                                | Nhập keyword vào Search tests                                            | Table lọc theo title/status/creator, clear search hiện lại                                     | P2       |
| TEST-M-005 | Toggle public visibility | Owner teacher/admin có test                   | Đổi checkbox Public                                                      | Trạng thái public cập nhật và persist sau refresh                                              | P2       |
| TEST-M-006 | Teacher show shared      | Teacher 1, có public test của Teacher 2/admin | Bật Show Shared                                                          | Shared test hiện thêm, có creator, hành động sửa/xóa bị giới hạn nếu không owner               | P1       |
| TEST-M-007 | Clone test               | Owner/admin                                   | Bấm clone action trên test                                               | Test copy được tạo với sections/modules/questions liên quan theo expected, title/key phân biệt | P2       |
| TEST-M-008 | Delete test cancel       | Owner/admin                                   | Bấm delete, cancel confirm                                               | Test vẫn còn                                                                                   | P1       |
| TEST-M-009 | Delete test confirm      | Owner/admin                                   | Bấm delete, confirm                                                      | Test biến mất, child sections/modules xử lý theo cấu hình, table refresh                       | P1       |
| TEST-M-010 | Teacher non-owner delete | Teacher 1 với shared test của Teacher 2       | Thử delete qua UI nếu nút hiện hoặc request trực tiếp                    | Bị chặn 403/không có nút; dữ liệu không đổi                                                    | P1       |

## 11. Dashboard - Sections & Modules

| Mã TC     | Kịch bản                        | Điều kiện đầu                          | Bước thực hiện                                                                          | Kết quả mong đợi                                                          | Priority |
| --------- | ------------------------------- | -------------------------------------- | --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- | -------- |
| SEC-M-001 | Create R&W section              | Có test owner                          | Tab Sections, Create Section, chọn parent test, type R&W, submit                        | Section tạo, name/order tự động đúng, table cập nhật                      | P1       |
| SEC-M-002 | Create Math section             | Có test owner                          | Tạo type Math                                                                           | Section Math order 2, table cập nhật                                      | P1       |
| SEC-M-003 | Duplicate section type          | Test đã có R&W                         | Tạo thêm R&W cho cùng test                                                              | Validation/error dễ hiểu, không duplicate                                 | P1       |
| SEC-M-004 | Section validation              | Bỏ trống parent/type                   | Submit                                                                                  | Validation hiện, không tạo                                                | P1       |
| SEC-M-005 | Teacher parent ownership        | Teacher 1, test của Teacher 2          | Thử tạo section vào test của Teacher 2                                                  | 403/không thể chọn nếu UI ẩn; dữ liệu không đổi                           | P1       |
| MOD-M-001 | Create standalone module        | Teacher/admin                          | Tab Modules, Create Module, không chọn target test, nhập field required, submit         | Module standalone tạo, key auto nếu bỏ trống, table cập nhật              | P1       |
| MOD-M-002 | Create module under test        | Có test owner                          | Create Module, chọn target test, section type, module #, difficulty, duration/questions | Module tạo và link vào section phù hợp, total duration cập nhật           | P1       |
| MOD-M-003 | Module defaults by section      | Trong create module                    | Đổi Section Type R&W/Math                                                               | Duration/questions default cập nhật hợp lý nếu UI có logic                | P2       |
| MOD-M-004 | Module validation               | Duration/questions = 0, key duplicate  | Submit                                                                                  | Validation hoặc lỗi user-friendly, không tạo duplicate                    | P1       |
| MOD-M-005 | Search modules                  | Có nhiều modules                       | Nhập keyword key/creator/type                                                           | Table lọc đúng                                                            | P2       |
| MOD-M-006 | Link module to section          | Có standalone module và section owner  | Bấm Link Module, chọn Existing Section + module, submit                                 | Module được associate, target hiện trong table                            | P1       |
| MOD-M-007 | Link module auto-create section | Có test owner                          | Link target Test & Auto-Create, chọn test/type/module                                   | Section được tạo nếu cần, module linked                                   | P2       |
| MOD-M-008 | Clone module                    | Owner/admin                            | Bấm clone module                                                                        | Module copy tạo với key/ulid mới, không public mặc định nếu code quy định | P2       |
| MOD-M-009 | Delete module cancel/confirm    | Owner/admin                            | Bấm delete cancel rồi confirm                                                           | Cancel giữ module; confirm xóa module/link, table refresh                 | P1       |
| MOD-M-010 | Teacher shared visibility       | Teacher 1, public module của Teacher 2 | Bật Show Shared                                                                         | Shared modules hiện, không sửa/xóa được non-owner                         | P1       |

## 12. Dashboard - Question Bank

| Mã TC    | Kịch bản                           | Điều kiện đầu                         | Bước thực hiện                                             | Kết quả mong đợi                                                                    | Priority                                               |
| -------- | ---------------------------------- | ------------------------------------- | ---------------------------------------------------------- | ----------------------------------------------------------------------------------- | ------------------------------------------------------ |
| QB-M-001 | Load Question Bank                 | Teacher/admin có questions            | Mở tab Question Bank                                       | Import wizard, validation grid/pool table hiện, pagination/search load              | P1                                                     |
| QB-M-002 | Empty question bank                | Không có questions visible            | Mở tab Questions                                           | Empty state/table không lỗi, có cách import/build question                          | P2                                                     |
| QB-M-003 | Search by text                     | Có question stem keyword              | Nhập keyword search                                        | Kết quả lọc đúng, total/pagination cập nhật                                         | P2                                                     |
| QB-M-004 | Search by numeric id               | Có question id                        | Nhập id vào search                                         | Trả đúng question id nếu visible                                                    | P2                                                     |
| QB-M-005 | Filter section/difficulty/complete | Có dữ liệu đã mix                     | Chọn các filter nếu UI hỗ trợ                              | Chỉ hiện questions phù hợp, clear filter hiện lại                                   | P2                                                     |
| QB-M-006 | Open question details/edit         | Owner/admin                           | Bấm edit/view question                                     | Modal edit hiện stem, type, difficulty, domain, choices/SPR/explanation             | P1                                                     |
| QB-M-007 | Update MCQ                         | Question MCQ owner                    | Sửa stem/choices/correct answer, save                      | Alert success, modal đóng/cập nhật, reload vẫn thấy data mới                        | P1                                                     |
| QB-M-008 | MCQ missing correct                | Question MCQ                          | Xóa correct answer hoặc choices không đủ                   | Save                                                                                | Validation hiện, không lưu câu incomplete ngoài ý muốn | P1 |
| QB-M-009 | Update SPR                         | Math question                         | Đổi type SPR, nhập accepted answers, save                  | SPR answers lưu, MCQ choices cũ không gây hiển thị sai                              | P1                                                     |
| QB-M-010 | SPR missing answers                | SPR                                   | Save không có answers                                      | Validation hiện, không lưu                                                          | P1                                                     |
| QB-M-011 | R&W passage edit                   | R&W question có passage               | Sửa passage content/stem, save                             | Passage và stem cập nhật, render markdown/math không vỡ layout                      | P2                                                     |
| QB-M-012 | Attach question to module          | Có module owner và question visible   | Chọn module/question, attach position cuối hoặc position 2 | Question xuất hiện trong module đúng thứ tự; existing positions shift nếu chèn giữa | P1                                                     |
| QB-M-013 | Attach duplicate                   | Question đã nằm trong module          | Attach lại                                                 | Hiện lỗi duplicate, không thêm thêm row                                             | P1                                                     |
| QB-M-014 | Delete question cancel/confirm     | Owner/admin                           | Bấm delete cancel rồi confirm                              | Cancel giữ; confirm xóa question khỏi table/module pivot                            | P1                                                     |
| QB-M-015 | Teacher non-owner question         | Teacher 1, private question Teacher 2 | Tìm/mở/sửa/xóa question non-visible                        | Không thấy hoặc 404/403, không leak content                                         | P1                                                     |

## 13. Import Wizard, Easy Builder, Media

| Mã TC         | Kịch bản                     | Điều kiện đầu                        | Bước thực hiện                                                | Kết quả mong đợi                                                              | Priority |
| ------------- | ---------------------------- | ------------------------------------ | ------------------------------------------------------------- | ----------------------------------------------------------------------------- | -------- |
| IMP-M-001     | CSV preview hợp lệ           | Module target có sẵn, CSV hợp lệ     | Question Bank import CSV preview                              | Preview hiện rows parsed, errors rỗng, chưa import DB                         | P1       |
| IMP-M-002     | CSV import hợp lệ            | Sau preview hợp lệ                   | Bấm import/confirm                                            | Questions tạo, attach vào module, positions đúng, table refresh               | P1       |
| IMP-M-003     | CSV thiếu header             | CSV lỗi header                       | Upload preview/import                                         | Hiện lỗi header/user-friendly, không 500                                      | P1       |
| IMP-M-004     | CSV MCQ thiếu choices        | CSV MCQ không choices                | Preview/import                                                | Row error chỉ rõ, không import row lỗi                                        | P1       |
| IMP-M-005     | JSON import hợp lệ           | JSON items hợp lệ                    | Upload/paste/import JSON nếu UI hỗ trợ                        | Questions tạo đúng type/passage/explanation/choices                           | P1       |
| IMP-M-006     | R&W missing passage          | R&W item không passage               | Preview/import                                                | Preview cần flag error hoặc import bị chặn 422; không 500                     | P1       |
| IMP-M-007     | ZIP import media             | ZIP có questions + image placeholder | Import ZIP                                                    | Media lưu và question render ảnh `/storage/media/...` trong take-test         | P1       |
| IMP-M-008     | ZIP lỗi/path traversal       | ZIP có file lỗi/`../evil.png`        | Import ZIP                                                    | Bị chặn với message an toàn; không tạo file ngoài media; không 500 nếu có thể | P1       |
| MEDIA-M-001   | Upload image hợp lệ          | Teacher/admin                        | Dùng editor/import upload PNG/JPG/WebP <=2MB                  | Trả URL/markdown, ảnh hiện trong preview/question                             | P1       |
| MEDIA-M-002   | Upload file sai              | Teacher/admin                        | Upload PDF/SVG/BMP                                            | Validation hiện, không upload                                                 | P1       |
| MEDIA-M-003   | Upload file quá lớn          | Ảnh >2MB                             | Upload                                                        | Validation max size, không 500                                                | P1       |
| MEDIA-M-004   | Student upload media         | Student verified                     | Thử gọi upload từ UI/request                                  | 403/redirect, không upload                                                    | P1       |
| BUILDER-M-001 | Quick Author wizard Full SAT | Teacher/admin                        | Bấm New Content > Full SAT                                    | Loading/generation chạy, tạo cấu trúc full SAT hoặc hiện lỗi rõ nếu fail      | P2       |
| BUILDER-M-002 | Wizard custom flow           | Teacher/admin, có test               | New Content > Custom, chọn test/domain/module, Launch Builder | Mở Easy Builder với target đúng                                               | P2       |
| BUILDER-M-003 | Easy Builder add block       | Có module owner                      | Tab Easy Builder, chọn module, Add Another Question           | Block mới hiện, sidebar index và live preview cập nhật                        | P1       |
| BUILDER-M-004 | Easy Builder live preview    | Đang edit block                      | Nhập stem/choices/formula markdown                            | Preview cập nhật real-time, không vỡ UI                                       | P2       |
| BUILDER-M-005 | Easy Builder save all        | Có block hợp lệ                      | Bấm Save All Questions                                        | Questions tạo/attach vào module, success alert, table refresh                 | P1       |
| BUILDER-M-006 | Easy Builder validation      | Block thiếu stem/correct answer      | Save All                                                      | Lỗi hiện gần block, không import partial ngoài ý muốn                         | P1       |
| BUILDER-M-007 | Clear All / Clear Unchanged  | Có nhiều blocks                      | Bấm Clear Unchanged, Clear All cancel/confirm                 | Chỉ xóa block phù hợp; cancel giữ dữ liệu                                     | P2       |

## 14. Authorization & Negative Manual Checks

| Mã TC       | Kịch bản                       | Điều kiện đầu                       | Bước thực hiện                                                       | Kết quả mong đợi                                                                              | Priority |
| ----------- | ------------------------------ | ----------------------------------- | -------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- | -------- |
| SECUR-M-001 | Protected pages require login  | Guest                               | Mở `/home`, `/take-test`, `/choose-test`, `/test-dashboard`          | Redirect signin/401, không render protected content                                           | P1       |
| SECUR-M-002 | Verified middleware            | User unverified                     | Mở `/home`, `/take-test`, `/test-dashboard`                          | Redirect verify notice                                                                        | P1       |
| SECUR-M-003 | Role middleware                | Student verified                    | Mở dashboard và dashboard API URLs                                   | 403/không có quyền                                                                            | P1       |
| SECUR-M-004 | Teacher ownership tests        | Teacher 1 và Teacher 2              | Teacher 1 thử sửa/xóa test Teacher 2 private                         | 403/404, không đổi dữ liệu                                                                    | P1       |
| SECUR-M-005 | Teacher ownership sections     | Teacher 1/2                         | Teacher 1 thử sửa/xóa/link section Teacher 2                         | 403/404, không đổi dữ liệu                                                                    | P1       |
| SECUR-M-006 | Teacher ownership modules      | Teacher 1/2                         | Teacher 1 thử update/delete/clone-to-target module Teacher 2 private | 403/404, không đổi dữ liệu                                                                    | P1       |
| SECUR-M-007 | Teacher ownership questions    | Teacher 1/2                         | Teacher 1 thử show/update/delete private question Teacher 2          | 403/404, không leak stem/answer                                                               | P1       |
| SECUR-M-008 | Public/shared read-only        | Teacher 1, public dữ liệu Teacher 2 | Bật Show Shared và thao tác                                          | Có thể xem/clone/link theo logic cho phép; không update/delete original                       | P1       |
| SECUR-M-009 | CSRF missing                   | User đã login, dùng Thunder Client  | POST/PUT/DELETE protected route không CSRF                           | 419/từ chối; không đổi DB                                                                     | P2       |
| SECUR-M-010 | Invalid IDs                    | User đã login                       | Đổi URL id/ulid sang id không tồn tại                                | 404/alert user-friendly, không 500                                                            | P2       |
| SECUR-M-011 | Network offline while autosave | Đang làm bài                        | Tắt network/chọn answer/bật lại/reload                               | UI không crash; có local backup nếu có; tester ghi bug nếu mất toàn bộ bài làm không cảnh báo | P2       |
| SECUR-M-012 | Browser mobile viewport        | Student và teacher                  | Test home, choose, take-test, score, dashboard trên mobile/tablet    | Không overlap text/nút; thao tác chính vẫn đúng                                               | P2       |

## 15. Smoke suite để chạy trước mỗi release

Chạy nhanh các case sau trên browser desktop:

| Thứ tự | Case                                    |
| ------ | --------------------------------------- |
| 1      | `AUTH-M-007` Login student verified     |
| 2      | `PORTAL-M-001` Home dashboard           |
| 3      | `PORTAL-M-009` Choose active test       |
| 4      | `TAKE-M-005` Chọn answer MCQ            |
| 5      | `TAKE-M-010` SPR input hợp lệ           |
| 6      | `TAKE-M-016` Review screen              |
| 7      | `FLOW-M-008` Final submit completed     |
| 8      | `SCORE-M-001` Mở score completed        |
| 9      | `DASH-M-004` Teacher vào dashboard      |
| 10     | `TEST-M-002` Create active test         |
| 11     | `MOD-M-001` Create standalone module    |
| 12     | `QB-M-007` Update MCQ                   |
| 13     | `IMP-M-001` CSV preview hợp lệ          |
| 14     | `SECUR-M-003` Student bị chặn dashboard |

## 16. Regression focus theo rủi ro code hiện tại

| Rủi ro                                                | Manual focus                                                                            |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------- |
| Route `/signup` POST không nằm trong middleware guest | Khi đang login, thử submit signup; hệ thống không nên vô tình đổi session sang user mới |
| Autosave im lặng khi fail                             | Test offline/CSRF/session expired để đảm bảo user không bị mất bài làm mà không biết    |
| Queue scoring/polling                                 | Test submit module với queue chậm/lỗi; UI phải có lỗi dễ hiểu                           |
| Preview vs real attempt                               | Preview không nên tạo/ghi đè attempt thật; real attempt phải autosave/submit            |
| Teacher shared visibility                             | Shared/public chỉ nên cho xem/clone/link theo policy, không sửa/xóa original            |
| Import validation                                     | File lỗi không nên gây 500; cần message rõ row/file nào lỗi                             |
| ZIP media security                                    | Path traversal và file lạ không được ghi ra ngoài thư mục media                         |
| Score stats                                           | Pretest và omitted phải được tính đúng, không làm sai total                             |

## 17. Các kịch bản kiểm thử nâng cao và cận biên (Niche & Edge Cases)

| Mã TC       | Kịch bản                                                                      | Điều kiện đầu                                                                                    | Bước thực hiện                                                                                                                                                                                              | Kết quả mong đợi                                                                                                                                                                                                                                                      | Priority |
| ----------- | ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| NICHE-M-001 | Chạy thi đồng thời trên hai tab trình duyệt                                   | Học sinh đã login, đang làm bài thi thực                                                         | 1. Mở bài thi ở tab A.<br>2. Sao chép URL và mở ở tab B.<br>3. Ở tab A, chọn đáp án `A` cho câu 1.<br>4. Ở tab B, chọn đáp án `B` cho câu 1.<br>5. Bấm F5 tải lại cả hai tab.                               | Hệ thống đồng bộ đáp án cuối cùng được lưu (`B`). Không bị xung đột trạng thái hay sinh ra nhiều bản ghi attempt.                                                                                                                                                     | P2       |
| NICHE-M-002 | Trực tuyến mất mạng giữa chừng khi tự động lưu                                | Học sinh đang làm bài thi                                                                        | 1. Tắt kết nối internet của máy tính (hoặc chỉnh offline trong F12 Network).<br>2. Chọn đáp án khác cho câu hiện tại.<br>3. Chờ tiến trình autosave chạy ngầm.<br>4. Bật lại internet.<br>5. Refresh trang. | UI hiển thị cảnh báo mất kết nối hoặc lưu thất bại. Khi có mạng lại, hệ thống tự động đồng bộ lại đáp án hoặc phục hồi từ bộ nhớ tạm local (localStorage/sessionStorage).                                                                                             | P2       |
| NICHE-M-003 | Làm bài thi có 100% câu hỏi là Pretest                                        | Admin tạo cấu trúc đề thi mà toàn bộ câu hỏi trong Module 1 được đánh dấu là `is_pretest = true` | 1. Học sinh bắt đầu làm bài thi này.<br>2. Làm đúng/sai một số câu.<br>3. Submit Module 1.                                                                                                                  | Hệ thống chấm điểm Module 1 (Theta) trả về giá trị mặc định (ví dụ: Theta = 0.0) và route sang Module 2 Easy/Hard theo cơ chế dự phòng an toàn thay vì bị crash phép toán chia cho 0 hoặc lỗi hội tụ Newton-Raphson.                                                  | P3       |
| NICHE-M-004 | Thuật toán IRT đạt điểm tuyệt đối hoặc điểm liệt                              | Học sinh làm bài thi thật                                                                        | **Kịch bản A (Tuyệt đối):** Làm đúng 100% các câu của Module 1.<br>**Kịch bản B (Điểm liệt):** Làm sai 100% các câu của Module 1.                                                                           | **Kịch bản A:** Hội tụ Newton-Raphson chặn cận trên của Theta ở mức `4.0`, không bị lặp vô hạn hay tràn số (infinite). Chuyển sang Module 2 Hard.<br>**Kịch bản B:** Chặn cận dưới ở mức `-4.0`, không bị lỗi toán học. Chuyển sang Module 2 Easy.                    | P2       |
| NICHE-M-005 | Đóng tab/F5 ngay khi bấm Submit Module (Đang Scoring)                         | Hệ thống đang chấm bài (Scoring status) và đang gọi API để poll kết quả chấm điểm                | 1. Bấm Submit Module ở màn hình Review.<br>2. Ngay lập tức nhấn F5 reload trang hoặc đóng tab khi vòng tròn quay (loading) đang hiển thị.                                                                   | Lệnh submit không bị hủy do backend chạy qua Queue (`ScoreModuleJob`). Khi học sinh quay lại `/home` và bấm Resume, hệ thống nhận diện trạng thái đã hoàn thành chấm điểm và đưa thẳng sang Module tiếp theo.                                                         | P2       |
| NICHE-M-006 | Nhập đáp án SPR phân số vô hạn tuần hoàn                                      | Đang làm phần Math SPR, câu hỏi có đáp án đúng là `2/3`                                          | 1. Nhập vào ô đáp án chuỗi `.666` hoặc `0.66` hoặc `.667`.<br>2. Submit bài thi.                                                                                                                            | Hệ thống chấm điểm chấp nhận cả dạng phân số `2/3` lẫn các cách viết số thập phân làm tròn tiêu chuẩn của College Board như `.666` và `.667`.                                                                                                                         | P2       |
| NICHE-M-007 | Nhập đáp án Math SPR giá trị âm                                               | Đang làm phần Math SPR                                                                           | 1. Cố tình gõ dấu trừ `-` vào ô nhập liệu SPR hoặc paste chuỗi `-5` vào ô.                                                                                                                                  | Hệ thống chặn ký tự `-` ngay từ giao diện nhập liệu hoặc báo lỗi validation trên UI, không cho phép gửi đáp án âm lên server (Luật thi SAT không có đáp án âm cho SPR).                                                                                               | P2       |
| NICHE-M-008 | Cố ý quay lại Module 1 bằng nút Back của trình duyệt sau khi đã sang Module 2 | Đã submit Module 1 thành công và đang làm Module 2                                               | 1. Bấm liên tục nút Back trên trình duyệt (Browser Back).<br>2. Hoặc sửa tay ULID trên thanh địa chỉ URL về ULID của Module 1.                                                                              | Trình duyệt bị chặn điều hướng bởi JS, hoặc backend kiểm tra thấy Module 1 đã submit sẽ tự động redirect trở lại Module 2 hoặc trang chủ `/home` kèm thông báo lỗi.                                                                                                   | P1       |
| NICHE-M-009 | Hết hạn thời gian nộp bài tuyệt đối (Absolute Submission Timeout Bypass)      | Học sinh đang thi                                                                                | 1. Cố ý sửa thời gian của đồng hồ hệ thống trên máy tính (lùi giờ hệ điều hành).<br>2. Đợi hết thời gian làm bài thật sự trên server.<br>3. Bấm Submit Module.                                              | Server kiểm tra thời gian bắt đầu làm bài lưu trong database (`current_module_started_at`) cộng với thời gian làm bài tối đa cho phép để từ chối nhận bài (`403 Forbidden - Module submission time has expired`), ngăn chặn việc gian lận bằng cách chỉnh giờ Client. | P1       |
| NICHE-M-010 | Đóng máy tính / Ngủ đông (Sleep Mode) giữa chừng khi làm bài                  | Học sinh đang làm bài, timer đang chạy                                                           | 1. Gập màn hình máy tính / cho máy tính vào trạng thái Sleep khoảng 10 phút.<br>2. Mở lại máy tính, tiếp tục làm bài.                                                                                       | Timer trên giao diện tự động đồng bộ lại thời gian thực còn lại dựa vào thời gian hiện tại so với mốc bắt đầu thi lưu ở server. Nếu quá giờ cho phép, màn hình sẽ hiển thị trạng thái hết giờ làm bài và tự động nộp bài.                                             | P2       |
