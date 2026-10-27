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

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\CommandStarting::class,
            function (\Illuminate\Console\Events\CommandStarting $event) {
                $guarded = [
                    'migrate:fresh',
                    'migrate:refresh',
                    'migrate:reset',
                    'migrate:rollback',
                    'db:wipe',
                    'db:seed',
                ];

                if (! in_array($event->command, $guarded, true)) {
                    return;
                }

                $input = $event->input;
                $connectionName = ($input->hasOption('database') && $input->getOption('database'))
                    ? (string) $input->getOption('database')
                    : (string) config('database.default');

                $config = config("database.connections.{$connectionName}");
                if (is_array($config) && ! empty($config['url'])) {
                    $config = (new \Illuminate\Database\ConfigurationUrlParser)->parseConfiguration($config);
                }

                $driver = is_array($config) ? ($config['driver'] ?? null) : null;
                $database = is_array($config) ? ($config['database'] ?? null) : null;
                $env = app()->environment();

                if ($env !== 'testing' || $driver !== 'mysql' || $database !== 'sat_app_testing') {
                    throw new \RuntimeException(sprintf(
                        'Destructive database command "%s" is blocked! It is only permitted in the "testing" environment targeting the MySQL "sat_app_testing" database. (Resolved environment: "%s", database: "%s", driver: "%s")',
                        $event->command,
                        $env,
                        (string) $database,
                        (string) $driver
                    ));
                }
            }
        );
    }
}
