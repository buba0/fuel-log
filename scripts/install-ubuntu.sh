#!/usr/bin/env bash
set -euo pipefail
# Fresh installation on Ubuntu 24.04. Serves HTTP on port 8080.
[[ $EUID == 0 ]] || { echo "Run with sudo."; exit 1; }
source /etc/os-release
[[ $ID == ubuntu && $VERSION_ID == 24.04 ]] || { echo "Ubuntu 24.04 is required."; exit 1; }
[[ ! -e /etc/fuel-log/config.php && ! -e /var/www/fuel-log ]] || { echo "Existing installation detected. Follow the upgrade guide."; exit 1; }
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y git nginx mysql-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-zip openssl
systemctl enable --now mysql php8.3-fpm nginx
if mysql -Nse "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='fuel_log'" | read -r existing; then
    echo "Database fuel_log already exists. Use the manual deployment guide."
    exit 1
fi
git clone --depth 1 https://github.com/buba0/fuel-log.git /var/www/fuel-log
db_password=$(openssl rand -hex 32)
mysql <<SQL
CREATE DATABASE fuel_log CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fuel_log'@'127.0.0.1' IDENTIFIED BY '$db_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON fuel_log.* TO 'fuel_log'@'127.0.0.1';
SQL
mysql fuel_log < /var/www/fuel-log/database/schema.sql
install -d -m 0750 -o root -g www-data /etc/fuel-log
umask 027
cat > /etc/fuel-log/config.php <<PHP
<?php
return [
    'dsn' => 'mysql:host=127.0.0.1;dbname=fuel_log;charset=utf8mb4',
    'username' => 'fuel_log',
    'password' => '$db_password',
    'timezone' => 'UTC',
    'allow_signup' => true,
];
PHP
chown root:www-data /etc/fuel-log/config.php
chmod 0640 /etc/fuel-log/config.php
unset db_password
cat > /etc/nginx/sites-available/fuel-log <<'NGINX'
server {
    listen 8080;
    server_name _;
    root /var/www/fuel-log/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ \.php$ { return 404; }
    location ~ /\. { return 404; }
}
NGINX
ln -s /etc/nginx/sites-available/fuel-log /etc/nginx/sites-enabled/fuel-log
nginx -t
systemctl reload nginx
echo "Fuel Log installed. Open http://SERVER_IP:8080 and create an account."
