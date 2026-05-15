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
    }
}
