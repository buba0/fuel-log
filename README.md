# Fuel Log

Fuel Log is a multi-user web app for tracking fuel purchases and vehicle mileage. Each account has its own vehicles, stations, fill-ups, and preferences.

## Usage

1. Create an account, then add a vehicle and its tank capacity under **Vehicles**.
2. Add the stations you visit under **Stations**.
3. Choose **New fill-up**. Enter the amount added, price per unit, and odometer. If the tank still has fuel, enter the estimated percentage remaining before filling.
4. Use **Dashboard** for lifetime totals, a date range, or a single vehicle. Use **Fill-ups** to correct or delete an entry.
5. Use **Preferences** to switch among metric, US imperial, UK imperial, and custom units. Distance, volume, fuel economy, and price units can each be set independently.

Amounts use one currency per account, initially PLN. Changing the currency code relabels recorded amounts. The app does not convert currencies.

## How the dashboard calculates totals

- Fuel added and spending come from fill-ups dated within the filter. Each payment is rounded to two decimal places when saved, then the dashboard adds the saved amounts.
- The estimated fuel already in the tank is the starting percentage multiplied by the vehicle's tank capacity. This estimate is saved with the fill-up and stays independent of the amount purchased.
- Distance and time between fill-ups use consecutive entries for each vehicle. A full interval is assigned to its ending fill-up, including when the previous entry was before the filter start. A first fill-up alone has no distance or elapsed time, and entries on the same day contribute zero days.
- For each vehicle, estimated fuel used is the previous fill-up's purchased fuel plus its starting tank estimate, minus the current fill-up's starting tank estimate. The odometer difference gives the interval distance. The app shows that interval's economy on the later fill-up.
- Average economy uses total estimated fuel used divided by total distance over valid intervals. Best and worst are the most and least efficient valid intervals ending in the selected range. Intervals need positive fuel use and distance to contribute. Existing fill-ups are recalculated from their saved tank levels and odometers.
- Average fuel price is total paid divided by fuel purchased. Cost per distance divides total spending in the date range by the recorded distance in that view.

## Self-hosting

The app requires PHP 8.2 or newer with PDO MySQL, and a MySQL server. PHP and the browser run the project directly, with no build step or third-party web dependencies.

1. Create an empty MySQL database, then import the schema as a database administrator:

   ```sh
   mysql -h DB_HOST -u DB_ADMIN -p fuel_log < database/schema.sql
   ```

2. Create a dedicated MySQL account restricted to the web server. Grant it `SELECT`, `INSERT`, `UPDATE` and `DELETE` on that database.
3. Copy `config.example.php` to `/etc/fuel-log/config.php`, fill in the database settings, and make the file readable by the PHP service user. Alternatively set `FUEL_LOG_CONFIG` to the absolute path of the protected config and pass that variable through to PHP.
4. Point the web server's document root at the project's `public/` directory and enable PHP for `index.php`. Keep `src/`, `database/`, and the runtime config outside the document root.
5. Open the site and create your first account. Set `allow_signup` to `false` in the config when the accounts you need have been created.

For local development against a MySQL database:

```sh
FUEL_LOG_CONFIG=/absolute/path/to/config.php php -S 127.0.0.1:8080 -t public
```

The homelab instance runs in an unprivileged LXC on PVE2 with the application at `/var/www/fuel-log`. Nginx serves only `public/` and passes `index.php` to PHP-FPM. The MySQL schema and runtime credentials are managed independently of source updates. The PVE2 instance is on the private LAN at `http://fuel-log.home/`.

## Checks

```sh
find public src tests -name '*.php' -exec php -l {} \;
php tests/domain.php
```
