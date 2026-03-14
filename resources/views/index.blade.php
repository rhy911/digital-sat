<x-layouts.auth>
    <div class="signin-container">
        <h2 class="signin-title">Sign In</h2>
        
        <button class="primary-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-link"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
            <span>Use a sign-in ticket from your school</span>
        </button>

        <hr class="text-divider" data-content="OR">
        <a href="/signin" class="secondary-btn text-decoration-none text-center d-block">
            Sign in with a College Board student account
        </a>

        <div class="signin-footer-links">
            <a href="#educator">I'm an educator</a>
            <a href="/signup">Don't have an account?</a>
        </div>
    </div>
</x-layouts.auth>