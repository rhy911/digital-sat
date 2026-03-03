<x-layouts.auth>
    @push('styles')
        <style>
        /* Override default card gap for this page */
        .signin-container { gap: 30px; }

        .primary-btn {
            background-color: #fedb00;
            border: 1px solid #000;
            padding: 14px 24px;
            border-radius: 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .primary-btn:hover { box-shadow: 0 0 0 1px #000; }

        .text-divider {
            overflow: visible;
            padding: 0;
            border: none;
            border-top: medium double #d3d3d3;
            color: #333;
            text-align: center;
            height: 1em;
            margin: 1em 0;
            opacity: 1;
        }
        .text-divider::after {
            content: attr(data-content);
            display: inline-block;
            position: relative;
            top: -14px;
            font-size: 1rem;
            color: #333;
            padding: 0 0.25em;
            background: #fff;
        }

        .secondary-btn {
            background-color: white;
            color: #1a1a1a;
            border: 1px solid #000;
            padding: 12px 24px;
            border-radius: 2rem;
            font-weight: 600;
            width: 100%;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .secondary-btn:hover {
            background-color: #f0f0f0;
            box-shadow: 0 0 0 1px #000;
        }

        .signin-footer-links { text-align: center; }
        .signin-footer-links a {
            display: block;
            color: #324dc7;
            text-decoration: none;
            font-weight: 600;
            margin: 8px 0;
            transition: all 0.3s ease;
        }
        .signin-footer-links a:hover { text-decoration: underline; }
        </style>

    @endpush

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
            <a href="#help">Need help signing in?</a>
        </div>
    </div>
</x-layouts.auth>