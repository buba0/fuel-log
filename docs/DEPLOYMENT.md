# Deploy Fuel Log

## Install on Ubuntu 24.04

On the application server, install Nginx and PHP with the extensions for MySQL and Office exports.

```sh
sudo apt-get update
sudo apt-get install nginx php8.3-fpm php8.3-cli php8.3-mysql php8.3-zip mysql-client
```

### Create the database and application account

As a MySQL 8 administrator, create the database and a dedicated application account. Replace `10.0.0.42` with the application server address or hostname that MySQL sees. MySQL returns the randomly generated password, which goes in the protected app config.

```sql
CREATE DATABASE fuel_log CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fuel_log'@'10.0.0.42' IDENTIFIED BY RANDOM PASSWORD;
GRANT SELECT, INSERT, UPDATE, DELETE ON fuel_log.* TO 'fuel_log'@'10.0.0.42';
```

From the checkout, import the schema with an administrator account. Replace `DB_HOST` and `DB_ADMIN` in the database commands.

```sh
mysql -h DB_HOST -u DB_ADMIN -p fuel_log < database/schema.sql
```

### Install the app files and settings

Run these commands from the checkout at the revision you intend to serve.

```sh
release=$(git rev-parse --short HEAD)
sudo install -d -m 0755 "/var/www/fuel-log-releases/$release"
git archive HEAD | sudo tar --no-same-owner -x -C "/var/www/fuel-log-releases/$release"
sudo ln -s "/var/www/fuel-log-releases/$release" /var/www/fuel-log
sudo install -d -m 0750 -o root -g www-data /etc/fuel-log
sudo install -m 0640 -o root -g www-data config.example.php /etc/fuel-log/config.php
sudoedit /etc/fuel-log/config.php
```

Set the database DSN, application account username, generated password, and local timezone in `/etc/fuel-log/config.php`. Use `FUEL_LOG_CONFIG` in the PHP-FPM environment if your installation stores the config at a different absolute path.

### Configure Nginx

Save the server block as `/etc/nginx/sites-available/fuel-log` and replace the example hostname. The document root is `public/`, and only the front controller is sent to PHP-FPM.

```nginx
server {
    listen 80;
    server_name fuel-log.home;

    root /var/www/fuel-log/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. { return 404; }
    location ~ \.php$ { return 404; }
}
```

```sh
sudo ln -s /etc/nginx/sites-available/fuel-log /etc/nginx/sites-enabled/fuel-log
sudo nginx -t
sudo systemctl enable --now php8.3-fpm nginx
sudo systemctl reload nginx
```

Open the site and create the required accounts. Set `allow_signup` to `false` in the runtime config when account creation is complete. The example serves HTTP on a trusted LAN. Use HTTPS when the app or API is accessed outside that network.

## Upgrade an existing installation

Back up the current database to a private directory, stage the new source in a separate release directory, and run the numbered migrations as the database administrator before switching releases. A fresh install from the current schema already includes the migrations.

```sh
umask 077
mysqldump --single-transaction -h DB_HOST -u DB_ADMIN -p fuel_log > fuel_log-before-upgrade.sql
mysql -h DB_HOST -u DB_ADMIN -p fuel_log < database/migrations/001-user-language.sql
mysql -h DB_HOST -u DB_ADMIN -p fuel_log < database/migrations/002-vehicle-details-api-theme.sql
```

Lint and run the domain tests in the staged checkout, then switch the symlink to the new release. This example assumes the current app path is a symlink.

```sh
sudo ln -s "/var/www/fuel-log-releases/$release" /var/www/.fuel-log-next
sudo mv -T /var/www/.fuel-log-next /var/www/fuel-log
sudo systemctl reload php8.3-fpm
```

Verify sign-in, dashboard, downloads, and any configured metrics scrape after the upgrade. Keep the previous release available so you can switch the symlink back if needed.

## Homelab layout

| Service | Location |
| --- | --- |
| Fuel Log and Nginx | PVE2 CT 111 at [fuel.home](http://fuel.home/) |
| Application files | `/var/www/fuel-log` points to a release directory under `/var/www/fuel-log-releases` |
| App runtime config | `/etc/fuel-log/config.php` owned by `root:www-data`, mode `0640` |
| MySQL | CT 107 at `10.10.70.9`, database `fuel_log` |
| Prometheus and Grafana | PVE1 CT 109, scrape job `fuel-log-buba` |

If `fuel.home` does not resolve, CT 111 currently answers at [10.10.70.36](http://10.10.70.36/). Check the active DHCP lease if this address changes.

Apply database/migrations/003-fillup-tools.sql before deploying the release with defaults, vehicle metering and estimate markers.

## Automated installation

The [one-command installer](../scripts/install-ubuntu.sh) targets fresh Ubuntu 24.04. Install curl first if it is absent. Run the README command as a sudo-capable administrator. It installs Nginx, PHP-FPM, ZIP and MySQL, creates a random database credential, imports the complete schema, protects runtime configuration and serves port 8080. It refuses an existing app directory or database. It does not alter the default port-80 site. Allow TCP 8080 through any host firewall when needed. Registration, login, incorrect-password rejection and chart navigation were verified in a fresh container.

For subsequent upgrades follow the manual upgrade procedure above; rerunning setup is not an updater. Logs are in the normal package/systemd, Nginx and PHP-FPM logs. The optional [RADIUS integration](RADIUS.md) is configured separately.
