<?php

namespace Tests\Feature;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class DatabaseGuardTest extends TestCase
{
    public function test_blocks_destructive_commands_when_environment_is_not_testing(): void
    {
        $this->app['env'] = 'local';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Destructive database command "migrate:fresh" is blocked!');

        Event::dispatch(new CommandStarting(
            'migrate:fresh',
            new ArrayInput([]),
            new NullOutput
        ));
    }

    public function test_blocks_destructive_commands_when_target_database_is_not_sat_app_testing(): void
    {
        config(['database.connections.mysql.database' => 'digital_sat']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Destructive database command "db:wipe" is blocked!');

        Event::dispatch(new CommandStarting(
            'db:wipe',
            new ArrayInput([]),
            new NullOutput
        ));
    }

    public function test_blocks_destructive_commands_when_force_flag_is_present_on_unsafe_database(): void
    {
        config(['database.connections.mysql.database' => 'digital_sat']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Destructive database command "migrate:reset" is blocked!');

        Event::dispatch(new CommandStarting(
            'migrate:reset',
            new ArrayInput(['--force' => true]),
            new NullOutput
        ));
    }

    public function test_blocks_destructive_commands_with_unsafe_database_option(): void
    {
        config(['database.connections.custom_unsafe' => [
            'driver' => 'mysql',
            'database' => 'digital_sat',
        ]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Destructive database command "migrate:refresh" is blocked!');

        $input = new ArrayInput(['--database' => 'custom_unsafe']);
        $input->bind(new InputDefinition([
            new InputOption('database', null, InputOption::VALUE_OPTIONAL),
        ]));

        Event::dispatch(new CommandStarting(
            'migrate:refresh',
            $input,
            new NullOutput
        ));
    }

    public function test_blocks_destructive_commands_with_db_url_override(): void
    {
        config(['database.connections.mysql.url' => 'mysql://root:@127.0.0.1:3306/production_db']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Destructive database command "db:seed" is blocked!');

        Event::dispatch(new CommandStarting(
            'db:seed',
            new ArrayInput([]),
            new NullOutput
        ));
    }

    public function test_allows_destructive_commands_in_testing_environment_with_sat_app_testing(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'sat_app_testing',
            'database.connections.mysql.url' => null,
        ]);

        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed'] as $command) {
            Event::dispatch(new CommandStarting(
                $command,
                new ArrayInput([]),
                new NullOutput
            ));
        }

        $this->assertTrue(true);
    }

    public function test_allows_additive_migrate_command(): void
    {
        config(['database.connections.mysql.database' => 'digital_sat']);
        $this->app['env'] = 'local';

        Event::dispatch(new CommandStarting(
            'migrate',
            new ArrayInput([]),
            new NullOutput
        ));

        $this->assertTrue(true);
    }
}
