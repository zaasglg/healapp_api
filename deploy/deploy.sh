#!/bin/bash

# Скрипт быстрого развертывания приложения
# Используется после установки зависимостей (setup-server.sh)

set -e

PROJECT_DIR="/var/www/healapp-api"
USER="www-data"

echo "================================"
echo "Развертывание HealApp API"
echo "================================"

# Установка прав доступа
echo "Установка прав доступа..."
chown -R $USER:$USER $PROJECT_DIR
find $PROJECT_DIR -type f -exec chmod 644 {} \;
find $PROJECT_DIR -type d -exec chmod 755 {} \;
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache

# Проверка наличия .env
if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo "Копирование .env файла..."
    cp $PROJECT_DIR/.env.example $PROJECT_DIR/.env
    echo "ВНИМАНИЕ! Отредактируйте .env файл перед продолжением"
    exit 1
fi

# Установка зависимостей Composer
echo "Установка зависимостей Composer..."
cd $PROJECT_DIR
sudo -u $USER composer install --no-dev --optimize-autoloader

# Генерация ключа приложения
echo "Генерация ключа приложения..."
sudo -u $USER php artisan key:generate

# Выполнение миграций
echo "Выполнение миграций базы данных..."
read -p "Выполнить миграции? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    sudo -u $USER php artisan migrate --force
fi

# Оптимизация Laravel
echo "Оптимизация приложения..."
sudo -u $USER php artisan config:cache
sudo -u $USER php artisan route:cache
sudo -u $USER php artisan view:cache

# Генерация документации API (если используется Swagger)
if [ -f "$PROJECT_DIR/artisan" ]; then
    echo "Генерация документации API..."
    sudo -u $USER php artisan l5-swagger:generate || echo "Swagger не установлен или настроен"
fi

# Настройка Nginx
echo "Настройка Nginx..."
if [ ! -f "/etc/nginx/sites-available/healapp-api" ]; then
    cp $PROJECT_DIR/deploy/nginx/healapp-api.conf /etc/nginx/sites-available/healapp-api
    ln -s /etc/nginx/sites-available/healapp-api /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
fi
nginx -t
systemctl restart nginx

# Настройка Supervisor
echo "Настройка Supervisor..."
if [ ! -f "/etc/supervisor/conf.d/healapp-queue-worker.conf" ]; then
    cp $PROJECT_DIR/deploy/supervisor/healapp-queue-worker.conf /etc/supervisor/conf.d/
    supervisorctl reread
    supervisorctl update
fi
supervisorctl start healapp-queue-worker:* || supervisorctl restart healapp-queue-worker:*

# Настройка планировщика (опционально)
read -p "Настроить Laravel Scheduler? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    cp $PROJECT_DIR/deploy/systemd/laravel-scheduler.service /etc/systemd/system/
    cp $PROJECT_DIR/deploy/systemd/laravel-scheduler.timer /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable laravel-scheduler.timer
    systemctl start laravel-scheduler.timer
fi

# Проверка статусов служб
echo ""
echo "================================"
echo "Статусы служб:"
echo "================================"
systemctl status nginx --no-pager
systemctl status php8.3-fpm --no-pager
systemctl status redis-server --no-pager
supervisorctl status

echo ""
echo "================================"
echo "Развертывание завершено!"
echo "================================"
echo "Приложение доступно по адресу: http://155.212.223.159"
echo ""
echo "Полезные команды:"
echo "  - Логи Laravel: tail -f $PROJECT_DIR/storage/logs/laravel.log"
echo "  - Логи Nginx: tail -f /var/log/nginx/healapp-api-error.log"
echo "  - Логи очередей: tail -f $PROJECT_DIR/storage/logs/worker.log"
echo "  - Очистить кэш: cd $PROJECT_DIR && php artisan cache:clear"
echo ""
