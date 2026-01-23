<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateAdminToken extends Command
{
    protected $signature = 'admin:generate-token {--show : Только показать токен, не записывать в .env}';

    protected $description = 'Генерация токена для админ-панели';

    public function handle(): int
    {
        $token = Str::random(64);

        if ($this->option('show')) {
            $this->info("Токен: {$token}");

            return self::SUCCESS;
        }

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('Файл .env не найден');

            return self::FAILURE;
        }

        $envContent = file_get_contents($envPath);

        if (str_contains($envContent, 'ADMIN_TOKEN=')) {
            // Заменяем существующий токен
            $envContent = preg_replace('/ADMIN_TOKEN=.*/', "ADMIN_TOKEN={$token}", $envContent);
        } else {
            // Добавляем новый токен
            $envContent .= "\nADMIN_TOKEN={$token}\n";
        }

        file_put_contents($envPath, $envContent);

        $this->info('Токен успешно сгенерирован!');
        $this->newLine();
        $this->line("ADMIN_TOKEN={$token}");
        $this->newLine();
        $this->info('URL админки: '.config('app.url')."/admin?token={$token}");

        return self::SUCCESS;
    }
}
