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
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        \Illuminate\Pagination\Paginator::defaultView('pagination::tailwind');

        \Illuminate\Support\Facades\Blade::directive('markdown', function ($expression) {
            return "<?php echo \App\Support\QuestionContentRenderer::markdown($expression); ?>";
        });

        \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables[] = 'SystemRoot';
        \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables[] = 'SystemDrive';
    }
}
