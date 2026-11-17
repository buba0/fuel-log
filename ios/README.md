# Fuel Log for iOS

An iOS 17+ SwiftUI companion for an existing Fuel Log installation. The app embeds the responsive web interface in WKWebView, with native server setup, navigation, progress/error states and export sharing. It uses the same login, records, language, units and theme as the website. It is not a separate native implementation of the forms or an offline database.

## Personal installation with Xcode

1. Install full Xcode from Apple and open `ios/FuelLog.xcodeproj`.
2. Select the FuelLog target, then Signing & Capabilities. Select your Personal Team and replace `com.example.fuellog` with a unique bundle identifier.
3. Connect your iPhone, trust the Mac and enable Developer Mode if prompted. Select your iPhone as the run destination.
4. Press Run. Follow any device prompts to trust your development signing identity. Personal-team provisioning may require periodic rebuilding.
5. Open the app, enter your server's full address, and sign in. For a local server, connect to the same network or a suitable VPN and grant local-network access when prompted.

The app stores only the server address in preferences. WebKit maintains the login session in its app sandbox. Sign out within Fuel Log to end the session. External links open outside the embedded browser. HTTP is permitted for self-hosted local installations; HTTPS is recommended. Certificate validation is not bypassed.

CSV, TXT, JSON, XLSX and DOCX downloads open the iOS share sheet for saving or sending. Temporary exports are removed after the share operation completes. Language settings affect the web interface; the small native connection/navigation interface currently uses English. No push notifications or offline editing are included.

## Validation status

Project/plist structure and Swift syntax were checked. A full iOS SDK build and device test have not been run because the development machine has Command Line Tools but no full Xcode installation. Before relying on this build, test login/logout, record creation/editing, chart dialogs, all exports, rotation, local-network permission, loss of connectivity and changing server addresses on a device.

No Apple signing credentials or private server addresses are included. This target is for personal Xcode installation, not an App Store submission.
