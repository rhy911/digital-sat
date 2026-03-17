<x-layouts.auth>
    <h2 class="signin-title">Verify Your Email Address</h2>
    <p class="mb-4">A verification link has been sent to your email address. Please check your inbox and click the link to verify your account.</p>
    <form id="resendForm" novalidate>
        <button type="submit" id="resendBtn" class="btn btn-primary w-100 mt-4">Resend Verification Email</button>
    </form>
    <a href="/signin" class="btn btn-link mt-3">Back to Sign In</a>
    @push('scripts')
    <script>
        const resendBtn = document.getElementById("resendBtn");

        resendBtn.addEventListener("click", function(event) {
            event.preventDefault();
            // Add your resend verification email logic here
            alert("Verification email resent! Please check your inbox.");
        });
    </script>
    @endpush

</x-layouts.auth>