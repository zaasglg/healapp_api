<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset 
                            {--seed : Run seeders after migration}
                            {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all tables and re-run migrations (with optional seeding)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! $this->option('force')) {
            if (! $this->confirm('⚠️  This will DROP and RECREATE all tables. Are you sure?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $this->newLine();
        $this->info('🔄 Starting database reset...');
        $this->newLine();

        // Шаг 1: Drop all tables and run migrations
        $this->comment('Step 1/2: Dropping all tables and running migrations...');
        $this->call('migrate:fresh', [
            '--force' => true,
        ]);

        // Шаг 2: Run seeders if option is set
        if ($this->option('seed')) {
            $this->newLine();
            $this->comment('Step 2/2: Running seeders...');
            $this->call('db:seed', [
                '--force' => true,
            ]);
        }

        $this->newLine();
        $this->info('✅ Database reset completed successfully!');

        if ($this->option('seed')) {
            $this->info('   • Tables recreated');
            $this->info('   • Seed data inserted');
        } else {
            $this->info('   • Tables recreated');
            $this->comment('   💡 Run with --seed to insert seed data');
        }

        $this->newLine();

        return 0;
    }
}
