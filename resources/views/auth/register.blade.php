<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký | Premium Auth</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>

<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Tạo tài khoản</h1>
            <p>Bắt đầu hành trình của bạn ngay hôm nay</p>
        </div>

        <form id="registerForm">
            @csrf
            <div class="form-group">
                <label for="name">Họ và tên</label>
                <input type="text" id="name" name="name" placeholder="John Doe" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="name@example.com" required>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Xác nhận mật khẩu</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" required>
            </div>

            <div id="errorMessage" class="alert alert-danger" style="display: none;"></div>

            <button type="submit" class="btn" id="submitBtn">Đăng ký</button>
        </form>

        <div class="auth-footer">
            Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a>
        </div>
    </div>

    <script>
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            const errorMsg = document.getElementById('errorMessage');

            submitBtn.textContent = 'Đang xử lý...';
            submitBtn.disabled = true;
            errorMsg.style.display = 'none';

            try {
                const formData = new FormData(e.target);
                const response = await fetch('/register', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();

                if (response.ok) {
                    // Register successful - redirect to email verification page
                    if (data.token) {
                        localStorage.setItem('api_token', data.token);
                    }
                    window.location.href = data.redirect || '/email/verify';
                } else {
                    // Error response
                    errorMsg.textContent = data.message || 'Đăng ký thất bại. Vui lòng kiểm tra lại thông tin.';
                    errorMsg.style.display = 'block';
                }
            } catch (error) {
                console.error('Register error:', error);
                errorMsg.textContent = 'Đã xảy ra lỗi kết nối. Vui lòng kiểm tra kết nối internet.';
                errorMsg.style.display = 'block';
            } finally {
                submitBtn.textContent = 'Đăng ký';
                submitBtn.disabled = false;
            }
        });
    </script>
</body>

</html>