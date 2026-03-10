<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Premium Auth</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>

<body>
    <div class="dashboard-container">
        <div class="auth-header" style="text-align: left; margin-bottom: 3rem; display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1>Xin chào, {{ Auth::user()->name }}!</h1>
                <p>Bạn đã đăng nhập thành công vào hệ thống.</p>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn" style="width: auto; padding: 0.5rem 1.5rem; margin-top: 0; background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.2);">Đăng xuất</button>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div class="auth-container" style="max-width: none; margin: 0; padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem; font-size: 1.1rem; color: #818cf8;">Thông tin tài khoản</h3>
                <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Email: {{ Auth::user()->email }}</p>
                <p style="font-size: 0.9rem; color: var(--text-secondary);">Thành viên từ: {{ Auth::user()->created_at->format('d/m/Y') }}</p>
            </div>

            <div class="auth-container" style="max-width: none; margin: 0; padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem; font-size: 1.1rem; color: #c084fc;">Trạng thái hệ thống</h3>
                <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Máy chủ: Hoạt động</p>
                <p style="font-size: 0.9rem; color: var(--text-secondary);">Phiên bản: 1.0.0-stable</p>
            </div>
        </div>

        <div style="margin-top: 2rem;">
            <h3 style="margin-bottom: 0.5rem; font-size: 1.1rem; color: #fb7185;">API Token của bạn</h3>
            <p style="font-size: 0.875rem; color: var(--text-secondary);">Sử dụng token này để truy cập các tài nguyên API một cách bảo mật.</p>
            <div id="tokenBox" class="token-box">Đang tải token...</div>
        </div>
    </div>

    <script>
        const token = localStorage.getItem('api_token');
        const tokenBox = document.getElementById('tokenBox');

        if (token) {
            tokenBox.textContent = token;
        } else {
            tokenBox.textContent = 'Không tìm thấy token. Vui lòng đăng nhập lại.';
        }

        tokenBox.addEventListener('click', () => {
            navigator.clipboard.writeText(tokenBox.textContent);
            const originalText = tokenBox.textContent;
            tokenBox.textContent = 'Đã sao chép!';
            setTimeout(() => {
                tokenBox.textContent = originalText;
            }, 2000);
        });
    </script>
</body>

</html>