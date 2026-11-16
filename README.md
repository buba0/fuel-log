# Fuel Log

Fuel Log tracks vehicle fuel purchases, odometer readings, and fuel economy. Each account has its own vehicles, stations, fill-ups, units, language, theme, and API access.

- [User guide](docs/USER-GUIDE.md) covers fill-ups, estimates, anomaly review, filters, and exports.
- [Deployment guide](docs/DEPLOYMENT.md) covers a fresh Ubuntu install, MySQL migrations, and the deployment layout.
- [API and Grafana](docs/API.md) covers bearer tokens, JSON, Prometheus metrics, and dashboard setup.

Open Fuel Log on the LAN at [fuel.example.com](http://fuel.example.com/). If the name is unavailable, use the container's [current DHCP address](http://192.0.2.10/). Use **Vehicles** and **Stations** to set up your first fill-up, then use **Dashboard** to see your totals. English and Polish are available under **Preferences**.

## Development

The app runs on PHP 8.2+ with PDO MySQL. The PHP ZIP extension enables XLSX and DOCX downloads. Initialize a MySQL database using the deployment guide, then start the development server:

```sh
FUEL_LOG_CONFIG=/absolute/path/to/config.php php -S 127.0.0.1:8080 -t public
```

```sh
find public src tests -name '*.php' -exec php -l {} \;
php tests/domain.php
```

## Documentation

- [User guide](docs/USER-GUIDE.md)
- [Technical reference](docs/ARCHITECTURE.md)
- [API and integrations](docs/API.md)
- [Deployment and database creation](docs/DEPLOYMENT.md)

## One-command Ubuntu setup

On a fresh Ubuntu 24.04 host with curl available, run

    curl -fsSLo /tmp/fuel-log-install.sh https://raw.githubusercontent.com/buba0/fuel-log/main/scripts/install-ubuntu.sh && sudo bash /tmp/fuel-log-install.sh

This creates a local MySQL database and serves Fuel Log on port 8080. The script has passed syntax checks; clean-machine installation remains unverified. See the deployment guide for upgrades and manual configuration.
