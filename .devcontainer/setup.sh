#!/usr/bin/env bash
# Chạy 1 lần khi tạo Codespace: cài PHP 8.3 + Composer + MariaDB, cài Laravel, tạo CSDL và dữ liệu mẫu
set -e
cd "$(dirname "$0")/.."
echo "==> Cài PHP 8.3, Composer và MariaDB..."
sudo apt-get update -q
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -q \
  php-cli php-mysql php-gd php-intl php-curl php-mbstring php-xml php-zip unzip composer mariadb-server mariadb-client

echo "==> Khởi động MariaDB & tạo CSDL moons..."
sudo service mariadb start
sudo mysql -e "CREATE DATABASE IF NOT EXISTS moons CHARACTER SET utf8mb4;
  CREATE USER IF NOT EXISTS 'moons'@'localhost' IDENTIFIED BY 'moons123';
  CREATE USER IF NOT EXISTS 'moons'@'127.0.0.1' IDENTIFIED BY 'moons123';
  GRANT ALL PRIVILEGES ON *.* TO 'moons'@'localhost';
  GRANT ALL PRIVILEGES ON *.* TO 'moons'@'127.0.0.1';
  FLUSH PRIVILEGES;"

echo "==> composer install..."
composer install --no-interaction --prefer-dist --no-progress

if [ ! -f .env ]; then
  cp .env.example .env
  sed -i 's/^DB_USERNAME=.*/DB_USERNAME=moons/; s/^DB_PASSWORD=.*/DB_PASSWORD=moons123/' .env
  php artisan key:generate
fi

echo "==> Tạo bảng & sinh dữ liệu mô phỏng (~1 phút)..."
php artisan migrate:fresh --seed --force
echo "==> Xong! Web sẽ chạy ở cổng 8080."
