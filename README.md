<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## Project set up

- Install Herd
- Clone project into Herd's project file
- Run `composer install` inside the project folder (installs PHP dependencies)
- Install Xampp, turn on Xampp's MySQL, press Admin
- Create new database named digital_sat, pick utf8mb4_unicode_ci
- Copy `.env.example` to `.env` and run `php artisan key:generate`
- Run `php artisan migrate`
- Run `npm install`

How to run: go to digital-sat.test (Herd must be on)
To edit frontend: Must run `npm run dev` (Start Vite)

## Git rules

Branch structure

- `dev` — main development branch, **merge requests required**
- `feat/your-feature` — shared feature branch, team can merge freely
- `your_local_branch_name` — your personal local branch

After first set up

- git checkout dev
- git pull origin dev
- git checkout feat/your-feature (or create: git checkout -b feat/your-feature)
- git checkout -b your_local_branch_name
- Start coding ...

Finish coding (merge into feature branch, no review needed)

- git add .
- git commit -m 'commit message'
- git checkout feat/your-feature
- git pull origin feat/your-feature (resolve conflicts if any)
- git merge your_local_branch_name
- git push origin feat/your-feature

Merge feature branch into dev (requires merge request)

- git checkout dev
- git pull origin dev
- git checkout feat/your-feature
- git merge dev (sync with latest dev, resolve conflicts if any)
- git push origin feat/your-feature
- Open a merge request from feat/your-feature → dev and request a review

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.
