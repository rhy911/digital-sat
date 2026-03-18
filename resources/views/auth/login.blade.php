<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | Premium Auth</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Chào mừng trở lại</h1>
            <p>Vui lòng đăng nhập vào tài khoản của bạn</p>
        </div>

        <form id="loginForm">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="name@example.com" required>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <div id="errorMessage" class="alert alert-danger" style="display: none;"></div>

            <button type="submit" class="btn" id="submitBtn">Đăng nhập</button>
        </form>

        <div class="auth-footer">
            Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            const errorMsg = document.getElementById('errorMessage');

            submitBtn.textContent = 'Đang xử lý...';
            submitBtn.disabled = true;
            errorMsg.style.display = 'none';

            try {
                const formData = new FormData(e.target);
                const response = await fetch('/login', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();

                if (response.ok) {
                    // Login successful
                    if (data.token) {
                        localStorage.setItem('api_token', data.token);
                    }
                    window.location.href = '/dashboard';
                } else if (response.status === 403 && data.redirect) {
                    // Email not verified - redirect to verification page
                    window.location.href = data.redirect;
                } else {
                    // Error response
                    errorMsg.textContent = data.message || 'Đăng nhập thất bại. Vui lòng thử lại.';
                    errorMsg.style.display = 'block';
                }
            } catch (error) {
                console.error('Login error:', error);
                errorMsg.textContent = 'Đã xảy ra lỗi kết nối. Vui lòng kiểm tra kết nối internet.';
                errorMsg.style.display = 'block';
            } finally {
                submitBtn.textContent = 'Đăng nhập';
                submitBtn.disabled = false;
            }
        });
    </script>
</body>

</html>