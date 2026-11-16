# Technical reference

## Runtime

Nginx serves assets and routes application requests to public/index.php through PHP-FPM. The controller opens PDO MySQL, starts the session, selects the account language and dispatches pages or actions. Source releases are deployed through a symlink. Credentials remain in /etc/fuel-log/config.php outside the document root.

## Components

| Component | Purpose |
| --- | --- |
| src/app.php | Authentication, prepared SQL, validation, account-scoped CRUD and preferences |
| src/domain.php | Unit conversions, interval distances and calculated consumption |
| src/views.php | Pages, compact history, sorting and interactive SVG charts |
| src/i18n.php | Polish/English text and date/number formatting |
| src/anomalies.php | Prior-history baselines and tank estimates |
| src/anomaly_views.php | Alert dialogs, stale-history checks and transactional corrections |
| src/price_views.php | Paid-fuel averages and station/month comparisons |
| src/exports.php | CSV, TXT, JSON, native XLSX and DOCX |
| src/api.php | Account-scoped JSON and Prometheus endpoints |
| public/assets/app.js | Live previews, settings, navigation, dialogs and odometer prediction |
| public/assets/app.css | Themes, responsive layout and compact tables |

## Data and calculations

Users own vehicles, stations and fill-ups. Composite foreign keys ensure each fill-up references the same owner's vehicle and station. Stored measurements use kilometres, litres and litres per 100 kilometres. Input and display conversions follow account preferences.

Each fill-up saves the tank capacity used at entry time and its estimated initial volume. Historical snapshots survive later vehicle-capacity changes. The original consumption_l_100km column is retained for compatibility; calculated_consumption_l_100km is derived from consecutive readings.

For each vehicle, entries sort by date, odometer and ID. Fuel used equals previous initial fuel plus previous purchased fuel minus current initial fuel. Economy divides fuel used by distance, multiplied by 100. Positive distance and fuel are required. Average economy divides total valid fuel by total valid distance.

Filters assign an interval to its ending entry and retain an earlier baseline. Gifted fuel contributes to volume and usage. Purchase-price averages divide spending by paid litres only.

## Authentication and API

PHP password_hash/password_verify handle local credentials. Session IDs rotate at login. Cookies are HTTP-only and SameSite=Lax, with Secure enabled under HTTPS. State-changing forms require CSRF tokens. Queries use prepared parameters and authenticated ownership checks.

API tokens are random 256-bit values stored as SHA-256 hashes. Personal and Grafana service tokens are independent. See API.md for endpoints, query parameters and metric definitions.

## Integrations

Prometheus scrapes the authenticated metrics endpoint. The supplied Grafana dashboard covers totals, monthly statistics, vehicles, stations, recorded fill-ups, reviews and API health. Its monetary panels assume PLN. Prometheus timestamps reflect collection time; actual fill-up dates are labels. Grafana's time picker selects monitoring samples, while app filters select fill-up dates.

XLSX and DOCX use PHP ZipArchive and Office Open XML. CSV protects formula-like user text. DOCX can be imported into Google Docs; direct cloud upload is not implemented.

RADIUS setup is still in progress. This source release uses local authentication. The Ubuntu installer does not configure RADIUS.

## Operations

Run PHP lint and tests/domain.php after code changes. Apply numbered migrations before the corresponding release. Back up MySQL and the protected configuration. The Ubuntu installer targets a fresh Ubuntu 24.04 host, installs local MySQL and serves HTTP on port 8080. It refuses existing installations. Syntax checks have passed; a clean-machine installation remains unverified.
