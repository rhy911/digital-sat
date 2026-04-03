<x-layouts.auth>
    @push('styles')
    <style>
        .signin-container {
            gap: 20px;
        }
    </style>
    @endpush
    <h2 class="signin-title">Verify Your Email Address</h2>
    <p class="mb-4">A verification link has been sent to your email address. Please check your inbox and click the link to verify your account.</p>
    
    @auth
    <p style="text-align: center; font-size: 16px;">
        <strong>{{ auth()->user()->email }}</strong>
    </p>
    @endauth
    
    <div id="successMessage" style="display: none; color: #155724; margin-bottom: 1rem; padding: 0.75rem; background-color: #d4edda; border-radius: 4px;"></div>
    <div id="errorMessage" style="display: none; color: #dc3545; margin-bottom: 1rem; padding: 0.75rem; background-color: #f8d7da; border-radius: 4px;"></div>
    
    <form id="resendForm" action="{{ route('verification.send') }}" method="POST" novalidate>
        @csrf
        <button type="submit" id="resendBtn" class="btn btn-primary w-100 mt-4" data-processing-text="Sending...">Resend Verification Email</button>
    </form>
    
    <form id="logoutForm" method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-link w-100">Logout</button>
    </form>
    
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new AuthForm('resendForm', {
                onSuccess: (data) => {
                    const successMsg = document.getElementById('successMessage');
                    if (successMsg) {
                        successMsg.textContent = data.message || 'Verification email sent! Check your inbox.';
                        successMsg.style.display = 'block';
                    }
                }
            });

            // Handle logout form
            const logoutForm = document.getElementById('logoutForm');
            if (logoutForm) {
                logoutForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    try {
                        const response = await fetch(logoutForm.action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                               logoutForm.querySelector('input[name="_token"]')?.value,
                                'Accept': 'application/json'
                            }
                        });
                        if (response.ok) {
                            window.location.href = '/';
                        }
                    } catch (error) {
                        console.error('Logout error:', error);
                    }
                });
            }
        });
    </script>
    @endpush

</x-layouts.auth>