# Native Fuel Log for iOS

A native SwiftUI iOS 17+ client. All screens use iOS controls and Swift Charts; URLSession retrieves JSON from the Fuel Log API. No WebKit, embedded HTML or web navigation is used.

## Personal Xcode installation

1. Open `ios/FuelLog.xcodeproj` in full Xcode.
2. In Signing & Capabilities, choose your Personal Team and a unique bundle identifier.
3. Connect your iPhone, enable Developer Mode when prompted, select it and press Run.
4. In the web app Preferences, create a personal API token. Enter the server URL and token in the native app's connection screen.

The phone needs network access to the server. HTTPS is supported; HTTP is allowed only for local hostnames/private IPv4 addresses. Server redirects are rejected to avoid forwarding the token elsewhere. Certificate checks are not bypassed. Tokens are stored in device-only Keychain entries, not preferences. Disconnect removes the active token and in-memory data.

## Native features

- Dashboard totals, weighted fuel economy, best/worst economy and average interval distance/time.
- Lifetime, 30-day, 90-day and 365-day filters.
- Swift Charts economy plot; select a point to inspect its fill-up.
- Searchable fill-up list, reversible date sorting and native detail sheets.
- Vehicle filtering from a fill-up's details, with a clear-filter action.
- Metric and US imperial display units, automatic light/dark appearance and Dynamic Type.
- Pull to refresh, request progress, error messages and retry.

Use the + button on Dashboard or Fill-ups to create records with native controls. The form carries over defaults, converts display units, calculates cost, and accepts the starting tank level and optional vehicle-reported economy. Vehicles and stations must already exist. This version does not yet edit/delete records, manage vehicles or stations, review alerts, export files, offer custom units or provide Polish native labels. The existing web app retains those features. Data stays in memory; offline editing and persistent offline storage are not provided.

The API token is scoped to the user's existing account. The server must include the native fill-up entry API; deploy the matching release before adding records.

## Personal HTTP server exceptions

The default transport policy allows local networking without disabling ATS globally. For a local hostname that iOS still blocks, copy `FuelLog/Info.plist` to the gitignored `FuelLog/Info.local.plist`. Add only that hostname under `NSAppTransportSecurity → NSExceptionDomains` with `NSExceptionAllowsInsecureHTTPLoads = YES`. Create the gitignored `Local.xcconfig` containing `FUEL_LOG_INFO_PLIST = FuelLog/Info.local.plist`. Rebuild and run from Xcode. Keep private hostnames in these ignored files. HTTPS still requires a TLS-enabled server and valid certificate.
