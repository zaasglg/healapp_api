# HealApp API Deployment Files

Эта папка содержит все необходимые файлы для развертывания приложения на сервере Ubuntu 24.04.

## Структура папки

```
deploy/
├── nginx/
│   └── healapp-api.conf          # Конфигурация Nginx
├── supervisor/
│   └── healapp-queue-worker.conf # Конфигурация Supervisor для очередей
├── systemd/
│   ├── laravel-scheduler.service # Systemd сервис для планировщика
│   └── laravel-scheduler.timer   # Systemd таймер для планировщика
├── .env.production               # Пример production .env файла
├── setup-server.sh               # Скрипт установки зависимостей
├── deploy.sh                     # Скрипт развертывания приложения
├── DEPLOYMENT.md                 # Подробная инструкция по развертыванию
└── README.md                     # Этот файл
```

## Быстрый старт

### 1. Подготовка сервера

```bash
# Подключитесь к серверу
ssh root@155.212.223.159

# Скачайте и запустите скрипт установки
wget https://raw.githubusercontent.com/YOUR_REPO/healapp-api/main/deploy/setup-server.sh
chmod +x setup-server.sh
sudo bash setup-server.sh
```

### 2. Загрузка проекта

```bash
# Склонируйте репозиторий
cd /var/www
sudo git clone https://github.com/YOUR_REPO/healapp-api.git

# Или скопируйте файлы с локальной машины
# scp -r ./healapp-api root@155.212.223.159:/var/www/
```

### 3. Настройка окружения

```bash
cd /var/www/healapp-api

# Скопируйте и отредактируйте .env файл
sudo cp deploy/.env.production .env
sudo nano .env

# Обновите следующие параметры:
# - DB_PASSWORD (пароль базы данных)
# - MAIL_* (настройки почты)
# - SMS_* (настройки SMS, если используется)
```

### 4. Развертывание

```bash
cd /var/www/healapp-api
sudo bash deploy/deploy.sh
```

## Подробная инструкция

Полная пошаговая инструкция находится в файле [DEPLOYMENT.md](DEPLOYMENT.md).

## Основные компоненты

### Nginx
- Слушает порт 80
- Обрабатывает статические файлы
- Проксирует PHP запросы к PHP-FPM
- Лимиты: 20MB для загрузки файлов

### PHP-FPM
- Версия: PHP 8.3
- Сокет: `/var/run/php/php8.3-fpm.sock`
- Таймауты: 300 секунд

### MySQL
- База данных: `healapp_db`
- Пользователь: `healapp_user`
- Кодировка: utf8mb4

### Redis
- Используется для:
  - Кэширование
  - Сессии
  - Очереди

### Supervisor
- Управляет воркерами очередей Laravel
- 2 процесса по умолчанию
- Автоматический перезапуск при сбоях

### Systemd Timer (опционально)
- Запускает Laravel Scheduler каждую минуту
- Альтернатива cron

## Управление приложением

### Просмотр логов
```bash
# Laravel
tail -f /var/www/healapp-api/storage/logs/laravel.log

# Nginx
tail -f /var/log/nginx/healapp-api-error.log

# Очереди
tail -f /var/www/healapp-api/storage/logs/worker.log
```

### Перезапуск служб
```bash
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
sudo supervisorctl restart healapp-queue-worker:*
```

### Очистка кэша
```bash
cd /var/www/healapp-api
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
```

### Обновление приложения
```bash
cd /var/www/healapp-api
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo supervisorctl restart healapp-queue-worker:*
```

## Безопасность

### Рекомендации
1. Смените пароли по умолчанию
2. Настройте firewall (UFW)
3. Установите SSL сертификат
4. Регулярно обновляйте систему
5. Настройте автоматическое резервное копирование

### Firewall
```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
```

### SSL (для production с доменом)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

## Резервное копирование

### База данных
```bash
# Создать бэкап
mysqldump -u healapp_user -p healapp_db > backup_$(date +%Y%m%d).sql

# Восстановить
mysql -u healapp_user -p healapp_db < backup_20250115.sql
```

### Файлы
```bash
# Создать архив
tar -czf healapp-backup-$(date +%Y%m%d).tar.gz /var/www/healapp-api

# Исключить vendor и node_modules
tar -czf healapp-backup-$(date +%Y%m%d).tar.gz \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage/logs/*' \
    /var/www/healapp-api
```

## Мониторинг

### Проверка статуса служб
```bash
# Все службы
sudo systemctl status nginx php8.3-fpm redis-server

# Очереди
sudo supervisorctl status

# Планировщик (если используется)
sudo systemctl status laravel-scheduler.timer
```

### Производительность
```bash
# Использование ресурсов
htop

# Статистика Redis
redis-cli INFO

# Процессы MySQL
mysql -e "SHOW PROCESSLIST;"
```

## Troubleshooting

### Ошибка 500
1. Проверьте права: `storage/` и `bootstrap/cache/`
2. Проверьте `.env` файл
3. Просмотрите логи Laravel

### Ошибка 502
1. Проверьте PHP-FPM: `systemctl status php8.3-fpm`
2. Проверьте сокет в конфигурации Nginx
3. Увеличьте таймауты

### Очереди не работают
1. Проверьте Supervisor: `supervisorctl status`
2. Проверьте Redis: `redis-cli ping`
3. Проверьте логи воркеров

## Контакты

Для получения дополнительной информации см. [DEPLOYMENT.md](DEPLOYMENT.md).

## Полезные ссылки

- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [Supervisor Documentation](http://supervisord.org/)
- [Ubuntu Server Guide](https://ubuntu.com/server/docs)
