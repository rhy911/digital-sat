# Digital SAT - Project Documentation

> **Purpose**: AI-readable project overview for faster context understanding and onboarding.

---

## 📋 Project Overview

| Attribute | Value |
|-----------|-------|
| **Name** | Digital SAT |
| **Framework** | Laravel 12 |
| **PHP Version** | ^8.2 |
| **Frontend** | Vite + Tailwind CSS 4 + Bootstrap 5 |
| **Authentication** | Laravel Sanctum |
| **Database** | mysql (default) |

---

## 🏗️ Architecture

### Backend Stack
- **Framework**: Laravel 12 (latest)
- **Authentication**: Laravel Sanctum (API tokens)
- **Testing**: PHPUnit ^11.5
- **Dev Tools**: Laravel Pail (logging), Laravel Sail (Docker), Laravel Pint (code style)

### Frontend Stack
- **Build Tool**: Vite 7
- **CSS Framework**: Tailwind CSS 4 + Bootstrap 5
- **Fonts**: Roboto (via @fontsource)
- **Styling**: Sass/SCSS support
- **HTTP Client**: Axios

### Directory Structure
```
digital-sat/
├── app/
│   ├── Http/          # Controllers, Middleware, Requests
│   ├── Models/        # Eloquent models
│   ├── Notifications/ # Notification classes
│   └── Providers/     # Service providers
├── database/
│   ├── factories/     # Model factories for testing
│   ├── migrations/    # Database migrations
│   └── seeders/       # Database seeders
├── resources/
│   ├── css/           # Compiled CSS output
│   ├── js/            # JavaScript modules
│   ├── sass/          # SCSS/Sass stylesheets
│   └── views/         # Blade templates
├── routes/            # Application routes
├── tests/             # PHPUnit tests
└── config/            # Configuration files
```

---

## 🚀 Quick Start

### Installation
```bash
composer setup
# Or manually:
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Development Server
```bash
composer dev
# Runs: server, queue, logs, and Vite dev server concurrently
```

### Testing
```bash
composer test
# Or: php artisan test
```

### Build for Production
```bash
npm run build
```

---

## 📝 Current Tasks

> **Instructions**: Update this section with your current/next tasks. AI assistants will prioritize these when helping.

### Active Task
<!-- Write your current focus here -->
- [x] Complete the auth function perfectly with no bugs
  - Sign in: Working with web and API endpoints, proper validation, error handling
  - Sign up: Working with email verification, password confirmation
  - Forgot/Reset password: Working with email notifications
  - Email verification: Working with signed URLs
  - Logout: Working with session and token revocation

### Upcoming Tasks
<!-- List pending tasks here -->
- [x] Integrate backend/db into the home page
  - Pull user name from database
  - Display in `.user` tag dynamically
  - Protect home page with auth middleware

### Notes
<!-- Any context, blockers, or important info -->
- Auth system is fully functional as of 2026-03-26
- Home page now requires authentication and displays logged-in user's name
- All routes properly protected with middleware

---

## 🧠 AI Context Guidelines

### When Assisting
1. **Check this file first** for current tasks and project context
2. **Follow Laravel 12 conventions** - use latest features and patterns
3. **Respect existing code style** - review before making changes
4. **Run verification** - execute tests/linting after code changes

### Code Style Preferences
- **PHP**: PSR-12 (enforced by Laravel Pint)
- **CSS**: Tailwind utility-first approach
- **JavaScript**: ES6+ modules

### Testing Requirements
- Add tests for new features/bug fixes
- Run `composer test` before committing
- Ensure no regressions in existing tests

---

## 📚 Key Commands Reference

| Command | Description |
|---------|-------------|
| `composer dev` | Start all development servers |
| `composer test` | Run PHPUnit tests |
| `php artisan serve` | Start Laravel development server |
| `php artisan migrate` | Run database migrations |
| `php artisan make:*` | Generate new files (controllers, models, etc.) |
| `npm run dev` | Start Vite development server |
| `npm run build` | Build assets for production |

---

## 🔐 Environment Setup

Required environment variables (see `.env.example`):
- `APP_KEY` - Application encryption key
- `DB_CONNECTION` - Database driver
- `DB_DATABASE` - Database file path

---

*Last updated: 2026-03-26*
