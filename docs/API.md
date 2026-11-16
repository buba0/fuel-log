# Fuel Log API and Grafana

## Create or revoke access

In **Preferences → API access**, choose **Create API token** and copy the token when it is shown. Each token grants read access to the account's own data. Fuel Log stores its SHA-256 hash. Use **Replace API token** to rotate it or **Revoke API token** to disable it.

Send the token in the `Authorization: Bearer` header. Keep it in a protected secret store or file. A deployment administrator can configure a separate Grafana service token so revoking or replacing a personal token leaves the monitoring connection intact.

## Read JSON or metrics

| Endpoint | Contents |
| --- | --- |
| `GET /?page=api&format=json` | Version, account and currency, unit labels, aggregate statistics, and individual fill-ups |
| `GET /?page=api&format=metrics` | Prometheus text format with aggregate and per-vehicle statistics, individual fill-ups, the last fill-up timestamp, and an anomaly count |

Either endpoint accepts optional `from` and `to` in inclusive `YYYY-MM-DD` format, and `car_id` for a specific vehicle owned by the account. Read the vehicle ID from `car_id` on a fill-up in the JSON response. With no filters, the response contains lifetime data. Interval measurements are attributed to the ending fill-up.

API units are kilometers, liters, liters per 100 kilometers, and days. Monetary amounts are in the account's currency, and unit prices are per liter. Display preferences affect the website and downloads. API values stay in these canonical units. JSON uses `null` for unavailable calculated measurements, and Prometheus omits those samples.

Example using Bash. The token is read without echoing it and provided to curl over stdin. Use the app's current LAN address from the [deployment guide](DEPLOYMENT.md) if local DNS is unavailable.

```bash
read -r -s -p 'Fuel Log API token: ' FUEL_LOG_TOKEN
printf '\n'
curl --fail --silent --show-error --config - \
  'http://fuel.example.com/?page=api&format=json&from=2026-01-01&to=2026-01-31' <<EOF_HEADER
header = "Authorization: Bearer ${FUEL_LOG_TOKEN}"
EOF_HEADER
unset FUEL_LOG_TOKEN
```

An invalid or missing token returns `401`. An invalid date range or unsupported format returns `400`.

## Scrape from Prometheus

Create a separate token on the Prometheus host and store it in a protected file readable by the Prometheus service user. This example assumes the service group is `prometheus`.

```sh
sudo install -m 0640 -o root -g prometheus /dev/null /etc/prometheus/fuel-log.token
openssl rand -hex 32 | tr -d '\n' | sudo tee /etc/prometheus/fuel-log.token >/dev/null
sudo sha256sum /etc/prometheus/fuel-log.token
```

As a database administrator, save the hex hash for the account you want to monitor. To revoke the service token, set `grafana_token_hash` to `NULL` for that account.

```sql
UPDATE users
SET grafana_token_hash = 'HEX_HASH_FROM_SHA256SUM'
WHERE username = 'MONITORED_USERNAME';
```

Configure a job to scrape the metrics endpoint every 30 seconds.

```yaml
scrape_configs:
  - job_name: fuel-log
    scrape_interval: 30s
    metrics_path: /
    params:
      page: [api]
      format: [metrics]
    authorization:
      type: Bearer
      credentials_file: /etc/prometheus/fuel-log.token
    static_configs:
      - targets: [fuel.example.com:80]
```

Use the Prometheus data source in Grafana. Aggregate samples have `car_id="all"`. Use a numeric `car_id` label for one vehicle. The following queries can populate dashboard stat panels.

```promql
fuel_log_avg_distance_km{job="fuel-log", car_id="all"}
fuel_log_avg_days{job="fuel-log", car_id="all"}
fuel_log_avg_consumption{job="fuel-log", car_id="all"}
fuel_log_total_paid{job="fuel-log", car_id="all"}
```

The per-fill series use the `fuel_log_fill_` prefix and identify each fill-up by `fill_id`, vehicle, station, and date. `fuel_log_fill_timestamp_seconds` gives the actual fill-up date as a Unix timestamp. Scrape times reflect when Prometheus received the values.

The example dashboard is [Fuel Log in Grafana](http://grafana.example.com:3000/d/fuel-log/fuel-log).

## Metric catalog

Aggregate names below have the `fuel_log_` prefix. They are current snapshots (gauges), not monotonically increasing counters; do not apply `rate()` to spending or lifetime totals. Labels identify the user, currency and vehicle (`car_id="all"` for combined figures).

| Suffix | Meaning |
| --- | --- |
| fill_count | Number of selected fill-ups |
| total_liters | Fuel added, including gifts |
| total_paid | Sum of receipt totals |
| distance_km | Distance between recorded fill-ups |
| avg_distance_km | Average interval distance |
| avg_days | Average days between visits |
| avg_consumption | Distance-weighted litres/100 km |
| best_consumption / worst_consumption | Lowest/highest valid litres/100 km |
| avg_price_per_liter | Spending divided by paid litres; excludes zero-price litres |
| cost_per_km | Cost-per-distance estimate |

Per-fill metrics have prefix `fuel_log_fill_`, with suffixes `liters`, `total_paid`, `price_per_liter`, `odometer_km`, `tank_before_liters`, `tank_before_percent`, `calculated_distance_km`, `calculated_consumption_l_100km` and `timestamp_seconds`. Labels include `fill_id`, `date`, `car_id`, `car`, `station`, `user` and `currency`.

Monthly metrics have prefix `fuel_log_month_`: `total_liters`, `total_paid`, `distance_km`, `avg_consumption`, `fill_count`, with a `month` label. Station metrics are `fuel_log_station_total_paid` and `fuel_log_station_total_liters`. `fuel_log_last_fill_timestamp_seconds` reports the most recent selected fill-up. `fuel_log_anomaly_count` counts active undismissed notices.

The monthly series cover complete months touched by the requested range, while aggregate and per-fill series follow the exact date range. Anomaly count uses full available vehicle history. Stations with identical names share the station label, so use distinct station names for unambiguous monitoring. Per-fill series grow with history size; size Prometheus retention accordingly.

The JSON object contains `version`, `user`, `currency`, `units`, `statistics`, and `fillups`. Each fill-up includes its identifiers, date, recorded canonical measurements and calculated interval fields. The API is read-only; it does not create or edit records. There is no pagination. Avoid placing bearer tokens in URLs. Responses are not cached by the app.

Import [grafana-dashboard.json](grafana-dashboard.json). Select your Prometheus data source and adjust the job/account selectors. Monetary panel labels must match the monitored account's currency. A different currency setting does not convert historical spending.
