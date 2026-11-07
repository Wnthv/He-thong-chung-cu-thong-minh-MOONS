#!/usr/bin/env bash
# Chạy mỗi lần mở Codespace: bật MariaDB và máy chủ Laravel ở cổng 8080
cd "$(dirname "$0")/.."
sudo service mariadb start
pkill -f "artisan serve" 2>/dev/null || true
nohup setsid php artisan serve --host=0.0.0.0 --port=8080 > /tmp/laravel-serve.log 2>&1 &
echo "Web Moons: cổng 8080 (xem log: /tmp/laravel-serve.log)"
