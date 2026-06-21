<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
                'remember' => 'boolean',
                'role' => 'nullable|string|in:student,teacher,admin'
            ]);

            $credentials = $request->only('email', 'password');
            $remember = $request->boolean('remember');

            if (!Auth::attempt($credentials, $remember)) {
                throw ValidationException::withMessages([
                    'email' => ['Thông tin đăng nhập không chính xác.'],
                ]);
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();

            $expectedRole = $request->input('role');
            if ($expectedRole && in_array($expectedRole, ['student', 'teacher', 'admin'], true)) {
                if ($user->role !== $expectedRole) {
                    Auth::logout();
                    
                    $roleLabels = [
                        'student' => 'Student',
                        'teacher' => 'Teacher',
                        'admin' => 'Administrator',
                    ];
                    
                    $actualLabel = $roleLabels[$user->role] ?? ucfirst($user->role);
                    $article = ($user->role === 'admin') ? 'an' : 'a';
                    $targetUrl = route('signin.form', ['role' => $user->role]);

                    $message = "This account is registered as {$article} " . strtolower($actualLabel) . ". " .
                               "<a href=\"{$targetUrl}\">Sign in as {$article} {$actualLabel} instead</a>.";

                    throw ValidationException::withMessages([
                        'email' => [$message],
                    ]);
                }
            }

            $request->session()->regenerate();

            // Check if email is verified
            if (!$user->hasVerifiedEmail()) {
                // Return JSON for AJAX requests or redirect for form submissions
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Email của bạn chưa được xác minh.',
                        'redirect' => route('verify.email.notice')
                    ], 403);
                }

                return redirect()->route('verify.email.notice')
                    ->with('warning', 'Email của bạn chưa được xác minh.');
            }

            $defaultTarget = match ($user->role) {
                'teacher' => $user->isApprovedTeacher() ? route('home') : route('teacher.application.status'),
                'admin' => route('admin.teacher-applications.index'),
                default => route('home'),
            };

            // Return JSON response for AJAX or redirect for web
            if ($request->wantsJson()) {
                $target = $request->session()->pull('url.intended', $defaultTarget);
                return response()->json([
                    'message' => 'Đăng nhập thành công.',
                    'user' => $user,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'redirect' => $target,
                ], 200);
            }

            return redirect()->intended($defaultTarget);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($e instanceof ValidationException) {
                $message = $e->validator->errors()->first();
            }

            Log::error('Login error (Web):', [
                'error' => $message,
                'email' => $request->email ?? null,
            ]);

            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message
                ], 422);
            }

            return redirect()->back()
                ->withInput($request->except('password'))
                ->withErrors(['error' => $message]);
        }
    }
}
