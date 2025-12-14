#!/bin/bash

# Скрипт установки зависимостей для Laravel на Ubuntu 24.04
# Запустите с правами sudo: sudo bash setup-server.sh

set -e

echo "================================"
echo "Установка зависимостей для Laravel"
echo "================================"

# Обновление системы
echo "Обновление системы..."
apt update && apt upgrade -y

# Установка базовых пакетов
echo "Установка базовых пакетов..."
apt install -y software-properties-common curl git unzip

# Добавление репозитория PHP 8.3
echo "Добавление репозитория PHP 8.3..."
add-apt-repository ppa:ondrej/php -y
apt update

# Установка PHP 8.3 и необходимых расширений
echo "Установка PHP 8.3 и расширений..."
apt install -y php8.3 \
    php8.3-fpm \
    php8.3-cli \
    php8.3-common \
    php8.3-mysql \
    php8.3-zip \
    php8.3-gd \
    php8.3-mbstring \
    php8.3-curl \
    php8.3-xml \
    php8.3-bcmath \
    php8.3-intl \
    php8.3-redis

# Установка Nginx
echo "Установка Nginx..."
apt install -y nginx

# Установка MySQL
echo "Установка MySQL Server..."
apt install -y mysql-server

# Установка Redis (для кэша и очередей)
echo "Установка Redis..."
apt install -y redis-server

# Установка Supervisor (для управления очередями Laravel)
echo "Установка Supervisor..."
apt install -y supervisor

# Установка Composer
echo "Установка Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Проверка версий
echo ""
echo "================================"
echo "Установленные версии:"
echo "================================"
php -v
composer --version
nginx -v
mysql --version
redis-server --version

# Настройка PHP-FPM
echo ""
echo "Настройка PHP-FPM..."
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/' /etc/php/8.3/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 20M/' /etc/php/8.3/fpm/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/8.3/fpm/php.ini
sed -i 's/;date.timezone =/date.timezone = Asia\/Almaty/' /etc/php/8.3/fpm/php.ini
sed -i 's/;date.timezone =/date.timezone = Asia\/Almaty/' /etc/php/8.3/cli/php.ini

# Перезапуск служб
echo ""
echo "Перезапуск служб..."
systemctl restart php8.3-fpm
systemctl enable php8.3-fpm
systemctl restart nginx
systemctl enable nginx
systemctl restart redis-server
systemctl enable redis-server
systemctl restart supervisor
systemctl enable supervisor

# Создание MySQL базы данных и пользователя
echo ""
echo "================================"
echo "Настройка MySQL"
echo "================================"
echo "ВАЖНО! Выполните следующие команды вручную:"
echo ""
echo "sudo mysql"
echo "CREATE DATABASE healapp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "CREATE USER 'healapp_user'@'localhost' IDENTIFIED BY 'your_secure_password';"
echo "GRANT ALL PRIVILEGES ON healapp_db.* TO 'healapp_user'@'localhost';"
echo "FLUSH PRIVILEGES;"
echo "EXIT;"
echo ""

echo "================================"
echo "Установка завершена успешно!"
echo "================================"
