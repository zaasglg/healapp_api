<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clear 
                            {--force : Force the operation without confirmation}
                            {--except-users : Keep users table}
                            {--except-organizations : Keep organizations table}
                            {--except-roles : Keep roles and permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all data from the database (keeps structure)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! $this->option('force')) {
            if (! $this->confirm('⚠️  This will delete ALL data from the database. Are you sure?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $this->info('Starting database cleanup...');

        // Отключаем проверку внешних ключей
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $exceptUsers = $this->option('except-users');
        $exceptOrganizations = $this->option('except-organizations');
        $exceptRoles = $this->option('except-roles');

        // Список таблиц для очистки в правильном порядке (от зависимых к основным)
        $tables = [
            'password_reset_tokens',
            'personal_access_tokens',
            'failed_jobs',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',

            // Связанные таблицы
            'diary_access',
            'patient_user',
            'invitations',

            // Основные данные приложения
            'alarms',
            'diary_entries',
            'tasks',
            'task_templates',
            'diaries',
            'patients',
            'pension_schedules',
        ];

        // Добавляем пользователей и организации если не исключены
        if (! $exceptOrganizations) {
            $tables[] = 'organizations';
        }

        if (! $exceptUsers) {
            $tables[] = 'users';
            $tables[] = 'model_has_permissions';
            $tables[] = 'model_has_roles';
        }

        // Добавляем роли и права если не исключены
        if (! $exceptRoles) {
            $tables[] = 'role_has_permissions';
            $tables[] = 'permissions';
            $tables[] = 'roles';
        }

        $cleared = 0;
        $skipped = 0;

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                try {
                    DB::table($table)->truncate();
                    $this->info("✓ Cleared table: {$table}");
                    $cleared++;
                } catch (\Exception $e) {
                    $this->warn("✗ Could not clear table: {$table} - {$e->getMessage()}");
                    $skipped++;
                }
            } else {
                $this->warn("⊘ Table does not exist: {$table}");
                $skipped++;
            }
        }

        // Включаем обратно проверку внешних ключей
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine();
        $this->info('✅ Database cleanup completed!');
        $this->info("   Cleared: {$cleared} tables");

        if ($skipped > 0) {
            $this->warn("   Skipped: {$skipped} tables");
        }

        $this->newLine();

        // Если были исключения, показываем что сохранилось
        $kept = [];
        if ($exceptUsers) {
            $kept[] = 'users';
        }
        if ($exceptOrganizations) {
            $kept[] = 'organizations';
        }
        if ($exceptRoles) {
            $kept[] = 'roles & permissions';
        }

        if (! empty($kept)) {
            $this->info('🔒 Kept data: '.implode(', ', $kept));
        }

        $this->newLine();
        $this->comment('💡 Tip: Run "php artisan migrate:fresh --seed" to recreate the database with seed data');

        return 0;
    }
}
