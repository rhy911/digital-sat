<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Blade::directive('markdown', function ($expression) {
            return "<?php echo \Illuminate\Support\Str::markdown(str_replace(['\(', '\)'], ['$$', '$$'], $expression), ['html_input' => 'strip', 'allow_unsafe_links' => false]); ?>";
        });

        $locks = app(\App\Services\TestContentLockService::class);
        foreach (['updating', 'deleting'] as $event) {
            \App\Models\Section::{$event}(fn ($section) => $locks->ensureSectionUnlocked($section));
            \App\Models\Module::{$event}(fn ($module) => $locks->ensureModuleUnlocked($module));
            \App\Models\Question::{$event}(fn ($question) => $locks->ensureQuestionUnlocked($question));
            \App\Models\AnswerChoice::{$event}(fn ($choice) => $choice->question && $locks->ensureQuestionUnlocked($choice->question));
            \App\Models\QuestionExplanation::{$event}(fn ($explanation) => $explanation->question && $locks->ensureQuestionUnlocked($explanation->question));
            \App\Models\SprCorrectAnswer::{$event}(fn ($answer) => $answer->question && $locks->ensureQuestionUnlocked($answer->question));
            \App\Models\Passage::{$event}(function ($passage) {
                if ($passage->questions()->whereHas('modules.sections.test.assignments', fn ($query) => $query->where('status', 'published'))->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['passage' => 'This passage belongs to a test in an open assignment. Close or delete the assignment before editing it.']);
                }
            });
        }
        \App\Models\Test::updating(function ($test) use ($locks) {
            if ($test->isDirty(['test_type', 'total_duration_minutes', 'break_duration_minutes', 'status'])) {
                $locks->ensureUnlocked($test);
            }
        });
        \App\Models\Test::deleting(fn ($test) => $locks->ensureUnlocked($test));
    }
}
