# Инструкция по развертыванию Laravel приложения на Ubuntu 24.04

## Системные требования
- Ubuntu 24.04
- PHP 8.3+
- MySQL 8.0+
- Nginx
- Composer
- Redis
- Supervisor

## Шаг 1: Подключение к серверу

```bash
ssh root@155.212.223.159
```

## Шаг 2: Установка зависимостей

```bash
# Запустите скрипт установки
cd ~
wget https://raw.githubusercontent.com/zaasglg/healapp_api/main/deploy/setup-server.sh
chmod +x setup-server.sh
sudo bash setup-server.sh
```

Или установите вручную все зависимости из скрипта.

## Шаг 3: Настройка MySQL

```bash
sudo mysql
```

В консоли MySQL выполните:

```sql
CREATE DATABASE healapp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'healapp_user'@'localhost' IDENTIFIED BY 'ваш_надежный_пароль';
GRANT ALL PRIVILEGES ON healapp_db.* TO 'healapp_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## Шаг 4: Клонирование проекта

```bash
# Создайте директорию для проекта
sudo mkdir -p /var/www/healapp-api

# Клонируйте репозиторий
cd /var/www
sudo git clone https://github.com/zaasglg/healapp_api.git healapp-api

# Или скопируйте файлы с локальной машины через scp
# scp -r ./healapp-api root@155.212.223.159:/var/www/
```

## Шаг 5: Настройка прав доступа

```bash
# Установите владельца
sudo chown -R www-data:www-data /var/www/healapp-api

# Установите правильные права
sudo find /var/www/healapp-api -type f -exec chmod 644 {} \;
sudo find /var/www/healapp-api -type d -exec chmod 755 {} \;

# Права на storage и cache
sudo chmod -R 775 /var/www/healapp-api/storage
sudo chmod -R 775 /var/www/healapp-api/bootstrap/cache
```

## Шаг 6: Установка зависимостей Composer

```bash
cd /var/www/healapp-api
sudo -u www-data composer install --no-dev --optimize-autoloader
```

## Шаг 7: Настройка окружения

```bash
# Скопируйте .env файл
sudo cp .env.example .env

# Отредактируйте .env файл
sudo nano .env
```

Обновите следующие параметры в `.env`:

```env
APP_NAME="HealApp API"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://155.212.223.159

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=healapp_db
DB_USERNAME=healapp_user
DB_PASSWORD=ваш_надежный_пароль

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Шаг 8: Генерация ключа приложения

```bash
cd /var/www/healapp-api
sudo -u www-data php artisan key:generate
```

## Шаг 9: Выполнение миграций

```bash
sudo -u www-data php artisan migrate --force
```

## Шаг 10: Оптимизация Laravel

```bash
# Кэширование конфигурации
sudo -u www-data php artisan config:cache

# Кэширование маршрутов
sudo -u www-data php artisan route:cache

# Кэширование представлений
sudo -u www-data php artisan view:cache

# Генерация документации Swagger (если используется)
sudo -u www-data php artisan l5-swagger:generate
```

## Шаг 11: Настройка Nginx

```bash
# Скопируйте конфигурацию nginx
sudo cp /var/www/healapp-api/deploy/nginx/healapp-api.conf /etc/nginx/sites-available/healapp-api

# Создайте символическую ссылку
sudo ln -s /etc/nginx/sites-available/healapp-api /etc/nginx/sites-enabled/

# Удалите дефолтный сайт
sudo rm /etc/nginx/sites-enabled/default

# Проверьте конфигурацию
sudo nginx -t

# Перезапустите nginx
sudo systemctl restart nginx
```

## Шаг 12: Настройка Supervisor для очередей

```bash
# Скопируйте конфигурацию supervisor
sudo cp /var/www/healapp-api/deploy/supervisor/healapp-queue-worker.conf /etc/supervisor/conf.d/

# Обновите supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Запустите воркеры
sudo supervisorctl start healapp-queue-worker:*

# Проверьте статус
sudo supervisorctl status
```

## Шаг 13: Настройка планировщика Laravel (опционально)

Если используете Laravel Scheduler:

```bash
# Скопируйте systemd сервисы
sudo cp /var/www/healapp-api/deploy/systemd/laravel-scheduler.service /etc/systemd/system/
sudo cp /var/www/healapp-api/deploy/systemd/laravel-scheduler.timer /etc/systemd/system/

# Активируйте таймер
sudo systemctl daemon-reload
sudo systemctl enable laravel-scheduler.timer
sudo systemctl start laravel-scheduler.timer

# Проверьте статус
sudo systemctl status laravel-scheduler.timer
```

## Шаг 14: Настройка Firewall (UFW)

```bash
# Включите firewall
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
sudo ufw status
```

## Шаг 15: Проверка работы

Откройте браузер и перейдите по адресу:
- API: http://155.212.223.159/api
- Swagger документация: http://155.212.223.159/api/documentation

## Полезные команды для управления

### Перезапуск служб
```bash
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
sudo systemctl restart redis-server
sudo supervisorctl restart healapp-queue-worker:*
```

### Просмотр логов
```bash
# Логи Nginx
sudo tail -f /var/log/nginx/healapp-api-error.log
sudo tail -f /var/log/nginx/healapp-api-access.log

# Логи Laravel
sudo tail -f /var/www/healapp-api/storage/logs/laravel.log

# Логи очередей
sudo tail -f /var/www/healapp-api/storage/logs/worker.log

# Логи PHP-FPM
sudo tail -f /var/log/php8.3-fpm.log
```

### Очистка кэша
```bash
cd /var/www/healapp-api
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear
```

### Обновление приложения
```bash
cd /var/www/healapp-api

# Получить изменения из git
sudo -u www-data git pull origin main

# Установить зависимости
sudo -u www-data composer install --no-dev --optimize-autoloader

# Выполнить миграции
sudo -u www-data php artisan migrate --force

# Очистить и пересоздать кэш
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Перезапустить очереди
sudo supervisorctl restart healapp-queue-worker:*
```

## Установка SSL сертификата (для production)

Когда будете использовать домен:

```bash
# Установите Certbot
sudo apt install -y certbot python3-certbot-nginx

# Получите SSL сертификат
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Автообновление сертификата
sudo systemctl enable certbot.timer
```

## Мониторинг производительности

### Установка htop
```bash
sudo apt install -y htop
htop
```

### Мониторинг Redis
```bash
redis-cli
> INFO
> MONITOR
```

### Мониторинг MySQL
```bash
sudo mysql
> SHOW PROCESSLIST;
> SHOW STATUS;
```

## Резервное копирование

### Бэкап базы данных
```bash
# Создать бэкап
sudo mysqldump -u healapp_user -p healapp_db > ~/backup_$(date +%Y%m%d_%H%M%S).sql

# Восстановить бэкап
sudo mysql -u healapp_user -p healapp_db < ~/backup_20250115_120000.sql
```

## Проблемы и решения

### Ошибки 500
- Проверьте права доступа к `storage/` и `bootstrap/cache/`
- Проверьте логи: `/var/www/healapp-api/storage/logs/laravel.log`
- Проверьте `.env` файл

### Ошибки 502 Bad Gateway
- Проверьте PHP-FPM: `sudo systemctl status php8.3-fpm`
- Проверьте путь к сокету в конфигурации nginx

### Очереди не работают
- Проверьте Supervisor: `sudo supervisorctl status`
- Проверьте Redis: `redis-cli ping`
- Перезапустите воркеры: `sudo supervisorctl restart healapp-queue-worker:*`

## Контакты для поддержки

Если возникнут проблемы, проверьте:
1. Логи nginx: `/var/log/nginx/`
2. Логи Laravel: `/var/www/healapp-api/storage/logs/`
3. Статус служб: `sudo systemctl status nginx php8.3-fpm redis-server`
