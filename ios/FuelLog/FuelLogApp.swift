import SwiftUI
import Charts
import Combine
import Security

@main
struct FuelLogApp: App {
    var body: some Scene { WindowGroup { NativeApp() } }
}

enum Vault {
    static let service = "FuelLog.API"
    static func read(_ account: String) -> String {
        let query: [String: Any] = [kSecClass as String: kSecClassGenericPassword, kSecAttrService as String: service,
                                  kSecAttrAccount as String: account, kSecReturnData as String: true]
        var result: CFTypeRef?
        guard SecItemCopyMatching(query as CFDictionary, &result) == errSecSuccess, let data = result as? Data else { return "" }
        return String(decoding: data, as: UTF8.self)
    }
    static func save(_ token: String, account: String) throws {
        let query: [String: Any] = [kSecClass as String: kSecClassGenericPassword, kSecAttrService as String: service, kSecAttrAccount as String: account]
        let attributes: [String: Any] = [kSecValueData as String: Data(token.utf8), kSecAttrAccessible as String: kSecAttrAccessibleWhenUnlockedThisDeviceOnly]
        var status = SecItemUpdate(query as CFDictionary, attributes as CFDictionary)
        if status == errSecItemNotFound { status = SecItemAdd(query.merging(attributes) { _, new in new } as CFDictionary, nil) }
        if status != errSecSuccess { throw AppError.message("Could not save credentials securely (\(status)).") }
    }
    static func remove(_ account: String) {
        SecItemDelete([kSecClass as String: kSecClassGenericPassword, kSecAttrService as String: service, kSecAttrAccount as String: account] as CFDictionary)
    }
}
enum AppError: LocalizedError {
    case message(String)
    var errorDescription: String? { if case .message(let text) = self { return L(text) }; return nil }
}
struct Fill: Identifiable {
    let fields: [String: Any]
    var id: String { text("id") }
    func text(_ key: String) -> String { fields[key].map { String(describing: $0) } ?? "" }
    func number(_ key: String) -> Double? {
        if let value = fields[key] as? NSNumber { return value.doubleValue }
        return Double(fields[key] as? String ?? "")
    }
    var date: Date { ISO8601DateFormatter().date(from: text("filled_at") + "T12:00:00Z") ?? .distantPast }
}
final class NoRedirects: NSObject, URLSessionTaskDelegate {
    func urlSession(_ session: URLSession, task: URLSessionTask, willPerformHTTPRedirection response: HTTPURLResponse,
                    newRequest request: URLRequest, completionHandler: @escaping (URLRequest?) -> Void) { completionHandler(nil) }
}
@MainActor
final class Store: ObservableObject {
    @Published var fills: [Fill] = []
    @Published var stats: [String: Any] = [:]
    @Published var currency = ""
    @Published var user = ""
    @Published var error: String?
    @Published var loading = false
    @Published var days = 0
    @Published var vehicle = ""
    private var generation = UUID()
    private let session = URLSession(configuration: .ephemeral, delegate: NoRedirects(), delegateQueue: nil)
    func clear() { generation = UUID(); fills = []; stats = [:]; user = ""; error = nil; loading = false }
    func refresh(_ address: String) async {
        guard !address.isEmpty else { return }
        let requestID = UUID(); generation = requestID; loading = true; error = nil
        defer { if generation == requestID { loading = false } }
        do {
            guard var parts = URLComponents(string: address) else { throw AppError.message(L("Invalid server address.")) }
            var parameters = [URLQueryItem(name: "page", value: "api"), URLQueryItem(name: "format", value: "json")]
            if days > 0 {
                let formatter = DateFormatter(); formatter.dateFormat = "yyyy-MM-dd"; formatter.locale = Locale(identifier: "en_US_POSIX")
                parameters.append(URLQueryItem(name: "from", value: formatter.string(from: Calendar.current.date(byAdding: .day, value: -days, to: Date())!)))
            }
            if !vehicle.isEmpty { parameters.append(URLQueryItem(name: "car_id", value: vehicle)) }
            parts.queryItems = parameters
            guard let url = parts.url else { throw AppError.message(L("Invalid server address.")) }
            var request = URLRequest(url: url); request.timeoutInterval = 20
            request.setValue("Bearer " + Vault.read(address), forHTTPHeaderField: "Authorization")
            let (data, response) = try await session.data(for: request)
            guard generation == requestID else { return }
            let status = (response as? HTTPURLResponse)?.statusCode ?? 0
            guard status == 200 else { throw AppError.message(status == 401 ? L("Your API token was rejected. Update it in Settings.") : "Server returned HTTP \(status). Check the server address.") }
            guard let object = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let rows = object["fillups"] as? [[String: Any]], let summary = object["statistics"] as? [String: Any] else { throw AppError.message(L("The server did not return Fuel Log API data.")) }
            fills = rows.map { Fill(fields: $0) }.sorted { $0.date > $1.date }
            stats = summary; currency = object["currency"] as? String ?? ""; user = object["user"] as? String ?? ""
        } catch { if generation == requestID { self.error = error.localizedDescription } }
    }
    func stat(_ key: String) -> Double? { (stats[key] as? NSNumber)?.doubleValue }
}

struct NativeApp: View {
    @AppStorage("nativeServer") private var address = ""
    @AppStorage("imperial") private var imperial = false
    @AppStorage("appLanguage") private var appLanguage = "system"
    @StateObject private var store = Store()
    @State private var settings = false
    @State private var selected: Fill?
    @State private var chartDate: Date?
    @State private var search = ""
    @State private var newest = true
    @State private var adding = false
    func value(_ number: Double?, unit: String = "") -> String {
        guard let number else { return "—" }
        return number.formatted(.number.precision(.fractionLength(0...2))) + (unit.isEmpty ? "" : " " + unit)
    }
    var distanceUnit: String { imperial ? "mi" : "km" }
    var volumeUnit: String { imperial ? "US gal" : "L" }
    var economyUnit: String { imperial ? "mpg (US)" : "L/100 km" }
    func distance(_ n: Double?) -> Double? { n.map { imperial ? $0 / 1.609344 : $0 } }
    func volume(_ n: Double?) -> Double? { n.map { imperial ? $0 / 3.785411784 : $0 } }
    func economy(_ n: Double?) -> Double? { n.flatMap { $0 > 0 ? (imperial ? 235.214583 / $0 : $0) : nil } }
    var body: some View {
        Group {
            if address.isEmpty {
                ContentUnavailableView {
                    Label("Fuel Log", systemImage: "fuelpump.fill")
                } description: { Text(L("Connect your account to view your fuel history and statistics.")) }
                actions: { Button(L("Connect account")) { settings = true }.buttonStyle(.borderedProminent) }
            } else {
                TabView {
                    NavigationStack {
                        List {
                            status
                            Section {
                                Picker(L("Period"), selection: $store.days) { Text(L("Lifetime")).tag(0); Text(L("30 days")).tag(30); Text(L("90 days")).tag(90); Text(L("365 days")).tag(365) }
                                if !store.vehicle.isEmpty { Button(L("Clear vehicle filter")) { store.vehicle = "" } }
                            }
                            Section(L("Overview")) {
                                metric(L("Fuel added"), value(volume(store.stat("total_liters")), unit: volumeUnit))
                                metric(L("Total spent"), value(store.stat("total_paid"), unit: store.currency))
                                metric(L("Distance"), value(distance(store.stat("distance_km")), unit: distanceUnit))
                                metric(L("Average economy"), value(economy(store.stat("avg_consumption")), unit: economyUnit))
                                metric(L("Fill-ups"), value(store.stat("fill_count")))
                            }
                            Section(L("Fuel economy · ") + economyUnit) {
                                if store.fills.contains(where: { $0.number("calculated_consumption_l_100km") != nil }) {
                                    Chart(store.fills) { fill in
                                        if let amount = economy(fill.number("calculated_consumption_l_100km")) {
                                            PointMark(x: .value(L("Date"), fill.date), y: .value(L("Economy"), amount)).foregroundStyle(by: .value(L("Vehicle"), fill.text("car_name")))
                                        }
                                    }.frame(height: 220).chartXSelection(value: $chartDate)
                                    Text(L("Touch the chart to inspect a fill-up.")).font(.caption).foregroundStyle(.secondary)
                                } else { Text(L("Two usable fill-ups are needed to calculate fuel economy.")).foregroundStyle(.secondary) }
                            }
                            Section(L("Between fill-ups")) {
                                metric(L("Average distance"), value(distance(store.stat("avg_distance_km")), unit: distanceUnit))
                                metric(L("Average days"), value(store.stat("avg_days")))
                                metric(L("Best economy"), value(economy(store.stat("best_consumption")), unit: economyUnit))
                                metric(L("Worst economy"), value(economy(store.stat("worst_consumption")), unit: economyUnit))
                            }
                        }.navigationTitle(L("Dashboard")).refreshable { await store.refresh(address) }.toolbar { addButton; refreshButton }
                    }.tabItem { Label(L("Dashboard"), systemImage: "chart.bar.fill") }
                    NavigationStack {
                        List {
                            status
                            if store.fills.isEmpty && !store.loading { ContentUnavailableView(L("No fill-ups"), systemImage: "fuelpump", description: Text(L("Try a wider timeframe or add records on your server."))) }
                            ForEach(filteredFills) { fill in Button { selected = fill } label: {
                                HStack {
                                    Image(systemName: "fuelpump.fill").foregroundStyle(.orange)
                                    VStack(alignment: .leading) { Text(fill.text("car_name")).font(.headline); Text(fill.text("station_name")).font(.subheadline).foregroundStyle(.secondary); Text(fill.date, style: .date).font(.caption).foregroundStyle(.secondary) }
                                    Spacer()
                                    VStack(alignment: .trailing) { Text(value(volume(fill.number("liters")), unit: volumeUnit)); Text(value(fill.number("total_paid"), unit: store.currency)).font(.caption).foregroundStyle(.secondary) }
                                }.foregroundStyle(.primary)
                            } }
                        }.navigationTitle(L("Fill-ups")).searchable(text: $search, prompt: L("Vehicle or station")).refreshable { await store.refresh(address) }
                        .toolbar { addButton; Button { newest.toggle() } label: { Image(systemName: "arrow.up.arrow.down") }.accessibilityLabel(L("Reverse date order")); refreshButton }
                    }.tabItem { Label(L("Fill-ups"), systemImage: "list.bullet") }
                    NavigationStack {
                        List {
                            Section(L("Account")) { LabeledContent(L("Username"), value: store.user); Text(address).font(.footnote).textSelection(.enabled); Button(L("Connection settings")) { settings = true } }
                            Section(L("Display")) { Picker(L("Language"), selection: $appLanguage) { Text(L("System")).tag("system"); Text("English").tag("en"); Text("Polski").tag("pl") }; Toggle(L("US imperial units"), isOn: $imperial) }
                            Section { Text(L("Add fill-ups with the + button. Editing existing records is available on the web.")).foregroundStyle(.secondary) }
                            Button(L("Disconnect"), role: .destructive) { Vault.remove(address); address = ""; store.clear() }
                        }.navigationTitle(L("Settings"))
                    }.tabItem { Label(L("Settings"), systemImage: "gearshape") }
                }
            }
        }.tint(.orange).environment(\.locale, Locale(identifier: appLanguage == "system" ? Locale.current.identifier : appLanguage))
        .sheet(isPresented: $settings, onDismiss: { store.clear(); Task { await store.refresh(address) } }) { ConnectionSettings(address: $address) }
        .sheet(isPresented: $adding) { AddFill(address: address, imperial: imperial) { Task { await store.refresh(address) } } }
        .sheet(item: $selected) { fill in
            NavigationStack {
                List {
                    Section { Text(fill.text("car_name")).font(.title2.bold()); Text(fill.text("station_name")); Text(fill.date, style: .date) }
                    Section(L("Measurements")) {
                        metric(L("Fuel added"), value(volume(fill.number("liters")), unit: volumeUnit))
                        metric(L("Amount paid"), value(fill.number("total_paid"), unit: store.currency))
                        metric(L("Odometer"), value(distance(fill.number("odometer_km")), unit: distanceUnit))
                        metric(L("Distance"), value(distance(fill.number("calculated_distance_km")), unit: distanceUnit))
                        metric(L("Economy"), value(economy(fill.number("calculated_consumption_l_100km")), unit: economyUnit))
                        metric(L("Days since previous"), value(fill.number("calculated_interval_days")))
                        metric(L("Fuel before filling"), value(volume(fill.number("tank_before_liters")), unit: volumeUnit))
                    }
                    Button(L("Filter dashboard to this vehicle")) { store.vehicle = fill.text("car_id"); selected = nil }
                }.navigationTitle(L("Fill-up details")).navigationBarTitleDisplayMode(.inline)
                .toolbar { Button(L("Done")) { selected = nil } }
            }
        }
        .task(id: address + "|\(store.days)|" + store.vehicle) { await store.refresh(address) }
        .onChange(of: chartDate) { _, date in
            if let date { selected = store.fills.filter { $0.number("calculated_consumption_l_100km") != nil }.min(by: { abs($0.date.timeIntervalSince(date)) < abs($1.date.timeIntervalSince(date)) }); chartDate = nil }
        }
    }
    var filteredFills: [Fill] {
        let rows = store.fills.filter { search.isEmpty || ($0.text("car_name") + " " + $0.text("station_name")).localizedCaseInsensitiveContains(search) }
        return newest ? rows : rows.reversed()
    }
    @ViewBuilder var status: some View {
        if store.loading { ProgressView(L("Updating…")) }
        if let error = store.error { Section { Text(L(error)).foregroundStyle(.red); Text(L("Previously loaded values may be outdated.")).font(.caption); Button(L("Retry")) { Task { await store.refresh(address) } } } }
    }
    var addButton: some View { Button { adding = true } label: { Image(systemName: "plus") }.accessibilityLabel(L("Add fill-up")) }
    var refreshButton: some View { Button { Task { await store.refresh(address) } } label: { Image(systemName: "arrow.clockwise") }.disabled(store.loading).accessibilityLabel(L("Refresh")) }
    func metric(_ label: String, _ text: String) -> some View { LabeledContent(label) { Text(text).monospacedDigit().foregroundStyle(.primary) } }
}

struct ConnectionSettings: View {
    @Binding var address: String
    @Environment(\.dismiss) private var dismiss
    @State private var server = ""
    @State private var token = ""
    @State private var error: String?
    var body: some View {
        NavigationStack {
            Form {
                Section(L("Server")) { TextField("https://fuel.example.com/", text: $server).keyboardType(.URL).textInputAutocapitalization(.never).autocorrectionDisabled() }
                Section(L("API token")) {
                    SecureField(L("Paste API token"), text: $token).textInputAutocapitalization(.never).autocorrectionDisabled()
                    Text(L("Create a personal API token in Fuel Log’s web Preferences. The app reads your account data through the API and stores the token in Keychain.")).font(.footnote)
                }
                Section { Text(L("HTTPS is required except for local hostnames and private IPv4 addresses. Your phone must be able to reach the server. The app does not bypass certificate validation.")).font(.footnote) }
                if let error { Text(L(error)).foregroundStyle(.red) }
            }.navigationTitle(L("Connect account"))
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button(L("Cancel")) { dismiss() } }
                ToolbarItem(placement: .confirmationAction) { Button(L("Save")) { save() } }
            }.onAppear { server = address; token = Vault.read(address) }
        }
    }
    func save() {
        let raw = server.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let parts = URLComponents(string: raw), let host = parts.host,
              parts.user == nil, parts.password == nil, parts.query == nil, parts.fragment == nil else { error = L("Enter a server URL without credentials or query parameters."); return }
        let octets = host.split(separator: ".").compactMap { Int($0) }
        let privateIP = octets.count == 4 && octets.allSatisfy { (0...255).contains($0) } && (octets[0] == 10 || (octets[0] == 192 && octets[1] == 168) || (octets[0] == 172 && (16...31).contains(octets[1])))
        let local = privateIP || host.hasSuffix(".local") || host.hasSuffix(".home") || !host.contains(".")
        guard parts.scheme == "https" || (parts.scheme == "http" && local) else { error = L("Use HTTPS for non-local servers."); return }
        let credential = token.trimmingCharacters(in: .whitespacesAndNewlines)
        guard credential.range(of: "^[a-f0-9]{64}$", options: .regularExpression) != nil else { error = L("Enter the 64-character personal API token."); return }
        do { try Vault.save(credential, account: raw); address = raw; dismiss() } catch { self.error = error.localizedDescription }
    }
}

struct AddFill: View {
    let address: String
    let imperial: Bool
    let onSaved: () -> Void
    @Environment(\.dismiss) private var dismiss
    @State private var cars: [Fill] = []
    @State private var stations: [Fill] = []
    @State private var car = ""
    @State private var station = ""
    @State private var date = Date()
    @State private var amount = ""
    @State private var price = ""
    @State private var odometer = ""
    @State private var tank = 0.0
    @State private var reported = ""
    @State private var currency = ""
    @State private var loading = true
    @State private var saving = false
    @State private var error: String?
    private var selectedCar: Fill? { cars.first { $0.id == car } }
    private var volumeFactor: Double { imperial ? 3.785411784 : 1 }
    private var volumeUnit: String { imperial ? "US gal" : "L" }
    private func numeric(_ text: String) -> Double? { Double(text.trimmingCharacters(in: .whitespaces).replacingOccurrences(of: ",", with: ".")) }
    private var valid: Bool {
        guard !car.isEmpty, !station.isEmpty, let a = numeric(amount), let p = numeric(price), let o = numeric(odometer) else { return false }
        return a.isFinite && p.isFinite && o.isFinite && a > 0 && p >= 0 && o >= 0
    }
    var body: some View {
        NavigationStack {
            Form {
                if loading { ProgressView(L("Loading vehicles and stations…")) }
                if let error { Section { Text(L(error)).foregroundStyle(.red) } }
                if !loading && (cars.isEmpty || stations.isEmpty) {
                    Section { Text(L("Add a vehicle and a fuel station in the web app first, then reopen this form.")) }
                }
                Section(L("Visit")) {
                    Picker(L("Vehicle"), selection: $car) { Text(L("Choose vehicle")).tag(""); ForEach(cars) { Text($0.text("name")).tag($0.id) } }
                    Picker(L("Station"), selection: $station) { Text(L("Choose station")).tag(""); ForEach(stations) { Text($0.text("name") + " " + $0.text("location")).tag($0.id) } }
                    DatePicker(L("Date"), selection: $date, displayedComponents: .date)
                }
                Section(L("Fuel and cost")) {
                    TextField("\(L("Fuel added")) (\(volumeUnit))", text: $amount).keyboardType(.decimalPad)
                    TextField("\(L("Price per unit")) (\(volumeUnit), \(currency))", text: $price).keyboardType(.decimalPad)
                    if let a = numeric(amount), let p = numeric(price), (a * p).isFinite {
                        LabeledContent(L("Total paid"), value: (a * p).formatted(.number.precision(.fractionLength(2))) + " " + currency)
                    }
                    Text(L("Enter zero price for gifted fuel. Fuel economy is calculated from consecutive fill-ups.")).font(.footnote).foregroundStyle(.secondary)
                }
                Section(L("Odometer and tank")) {
                    TextField("\(L("Odometer")) (\(imperial ? "mi" : "km"))", text: $odometer).keyboardType(.decimalPad)
                    LabeledContent(L("Fuel before filling"), value: "\(Int(tank))%")
                    Slider(value: $tank, in: 0...100, step: 1).accessibilityLabel(L("Tank level before filling"))
                    if let capacity = selectedCar?.number("tank_capacity_liters") {
                        Text(((capacity * tank / 100) / volumeFactor).formatted(.number.precision(.fractionLength(2))) + " " + volumeUnit + L(" remaining before filling")).font(.footnote)
                    }
                    if selectedCar?.number("fuel_metering") == 1 {
                        TextField("\(L("Vehicle-reported economy (optional)")) (\(imperial ? "mpg US" : "L/100 km"))", text: $reported).keyboardType(.decimalPad)
                    }
                }
            }.disabled(saving).navigationTitle(L("New fill-up")).navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button(L("Cancel")) { dismiss() }.disabled(saving) }
                ToolbarItem(placement: .confirmationAction) { Button(saving ? L("Saving…") : L("Save")) { Task { await save() } }.disabled(!valid || saving || loading) }
            }.interactiveDismissDisabled(saving)
            .task { await load() }
            .onChange(of: car) { _, _ in reported = "" }
        }
    }
    private func request(_ resource: String, body: [String: String]? = nil) async throws -> [String: Any] {
        guard var parts = URLComponents(string: address) else { throw AppError.message(L("Invalid server address.")) }
        parts.queryItems = [URLQueryItem(name: "page", value: "api"), URLQueryItem(name: "resource", value: resource)]
        guard let url = parts.url else { throw AppError.message(L("Invalid server address.")) }
        var request = URLRequest(url: url); request.timeoutInterval = 20
        request.setValue("Bearer " + Vault.read(address), forHTTPHeaderField: "Authorization")
        if let body { request.httpMethod = "POST"; request.httpBody = try JSONSerialization.data(withJSONObject: body); request.setValue("application/json", forHTTPHeaderField: "Content-Type") }
        let session = URLSession(configuration: .ephemeral, delegate: NoRedirects(), delegateQueue: nil)
        defer { session.finishTasksAndInvalidate() }
        let (data, response) = try await session.data(for: request)
        let object = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any] ?? [:]
        let status = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard (200...299).contains(status) else { throw AppError.message(object["error"] as? String ?? "Server returned HTTP \(status).") }
        return object
    }
    private func load() async {
        defer { loading = false }
        do {
            let data = try await request("fill-form")
            guard let vehicles = data["cars"] as? [[String: Any]], let locations = data["stations"] as? [[String: Any]] else { throw AppError.message(L("Update the server to support native fill-up entry.")) }
            cars = vehicles.map { Fill(fields: $0) }; stations = locations.map { Fill(fields: $0) }; currency = data["currency"] as? String ?? ""
            let defaults = Fill(fields: data["defaults"] as? [String: Any] ?? [:])
            car = cars.contains { $0.id == defaults.text("car_id") } ? defaults.text("car_id") : cars.first?.id ?? ""
            station = stations.contains { $0.id == defaults.text("station_id") } ? defaults.text("station_id") : stations.first?.id ?? ""
            if let p = defaults.number("price") { price = String(p * volumeFactor) }
        } catch { self.error = error.localizedDescription }
    }
    private func save() async {
        guard valid, let a = numeric(amount), let p = numeric(price), let o = numeric(odometer) else { return }
        saving = true; error = nil
        defer { saving = false }
        let formatter = DateFormatter(); formatter.locale = Locale(identifier: "en_US_POSIX"); formatter.dateFormat = "yyyy-MM-dd"
        var data = ["car_id": car, "station_id": station, "filled_at": formatter.string(from: date), "volume": String(a * volumeFactor), "price": String(p / volumeFactor), "odometer": String(o * (imperial ? 1.609344 : 1)), "tank_level": String(tank)]
        if selectedCar?.number("fuel_metering") == 1 && !reported.isEmpty {
            guard let n = numeric(reported), n.isFinite, n > 0 else { error = L("Enter a positive vehicle-reported economy or leave it empty."); return }
            data["reported_consumption"] = String(imperial ? 235.214583 / n : n)
        }
        do {
            let result = try await request("fillups", body: data)
            guard result["id"] != nil else { throw AppError.message(L("No save confirmation received. Check history before retrying.")) }
            onSaved(); dismiss()
        } catch { self.error = error.localizedDescription + L(" If the connection failed during saving, check history before retrying to avoid a duplicate.") }
    }
}

// Native interface language is independent from the server account language.
func L(_ text: String) -> String {
    let choice = UserDefaults.standard.string(forKey: "appLanguage") ?? "system"
    let polish = choice == "pl" || (choice == "system" && Locale.preferredLanguages.first?.hasPrefix("pl") == true)
    guard polish else { return text }
    let translations: [String: String] = [
        "Dashboard": "Podsumowanie",
        "Fill-ups": "Tankowania",
        "Settings": "Ustawienia",
        "Connect account": "Połącz konto",
        "Connect your account to view your fuel history and statistics.": "Połącz konto, aby przeglądać historię tankowań i statystyki.",
        "Period": "Okres",
        "Lifetime": "Cały okres",
        "30 days": "30 dni",
        "90 days": "90 dni",
        "365 days": "365 dni",
        "Clear vehicle filter": "Wyczyść filtr pojazdu",
        "Overview": "Przegląd",
        "Fuel added": "Zatankowane paliwo",
        "Total spent": "Łączne wydatki",
        "Distance": "Dystans",
        "Average economy": "Średnie spalanie",
        "Fuel economy · ": "Spalanie · ",
        "Touch the chart to inspect a fill-up.": "Dotknij wykresu, aby zobaczyć szczegóły tankowania.",
        "Two usable fill-ups are needed to calculate fuel economy.": "Do obliczenia spalania potrzebne są dwa prawidłowe tankowania.",
        "Between fill-ups": "Między tankowaniami",
        "Average distance": "Średni dystans",
        "Average days": "Średnia liczba dni",
        "Best economy": "Najniższe spalanie",
        "Worst economy": "Najwyższe spalanie",
        "No fill-ups": "Brak tankowań",
        "Try a wider timeframe or add records on your server.": "Wybierz szerszy okres lub dodaj tankowanie.",
        "Vehicle or station": "Pojazd lub stacja",
        "Reverse date order": "Odwróć kolejność dat",
        "Account": "Konto",
        "Username": "Nazwa użytkownika",
        "Connection settings": "Ustawienia połączenia",
        "Display": "Wyświetlanie",
        "US imperial units": "Jednostki imperialne USA",
        "Add fill-ups with the + button. Editing existing records is available on the web.": "Dodawaj tankowania przyciskiem +. Edycja istniejących wpisów jest dostępna w wersji WWW.",
        "Disconnect": "Rozłącz",
        "Measurements": "Pomiary",
        "Amount paid": "Zapłacona kwota",
        "Odometer": "Stan licznika",
        "Economy": "Spalanie",
        "Days since previous": "Dni od poprzedniego tankowania",
        "Fuel before filling": "Paliwo przed tankowaniem",
        "Filter dashboard to this vehicle": "Filtruj podsumowanie według tego pojazdu",
        "Fill-up details": "Szczegóły tankowania",
        "Done": "Gotowe",
        "Updating…": "Aktualizowanie…",
        "Previously loaded values may be outdated.": "Wcześniej pobrane dane mogą być nieaktualne.",
        "Retry": "Ponów",
        "Refresh": "Odśwież",
        "Add fill-up": "Dodaj tankowanie",
        "Server": "Serwer",
        "API token": "Token API",
        "Paste API token": "Wklej token API",
        "Create a personal API token in Fuel Log’s web Preferences. The app reads your account data through the API and stores the token in Keychain.": "Utwórz osobisty token API w ustawieniach Fuel Log w przeglądarce. Aplikacja korzysta z API i przechowuje token w pęku kluczy.",
        "HTTPS is required except for local hostnames and private IPv4 addresses. Your phone must be able to reach the server. The app does not bypass certificate validation.": "HTTPS jest wymagane z wyjątkiem lokalnych nazw i prywatnych adresów IPv4. Telefon musi mieć dostęp do serwera. Aplikacja weryfikuje certyfikaty.",
        "Cancel": "Anuluj",
        "Save": "Zapisz",
        "Enter a server URL without credentials or query parameters.": "Podaj adres serwera bez danych logowania i parametrów zapytania.",
        "Use HTTPS for non-local servers.": "Dla serwerów publicznych użyj HTTPS.",
        "Enter the 64-character personal API token.": "Wprowadź osobisty token API o długości 64 znaków.",
        "Loading vehicles and stations…": "Ładowanie pojazdów i stacji…",
        "Add a vehicle and a fuel station in the web app first, then reopen this form.": "Najpierw dodaj pojazd i stację w wersji WWW, a następnie otwórz formularz ponownie.",
        "Visit": "Wizyta",
        "Vehicle": "Pojazd",
        "Choose vehicle": "Wybierz pojazd",
        "Station": "Stacja",
        "Choose station": "Wybierz stację",
        "Date": "Data",
        "Fuel and cost": "Paliwo i koszt",
        "Total paid": "Zapłacona kwota",
        "Enter zero price for gifted fuel. Fuel economy is calculated from consecutive fill-ups.": "Dla paliwa otrzymanego bezpłatnie wpisz cenę zero. Spalanie jest obliczane między kolejnymi tankowaniami.",
        "Odometer and tank": "Licznik i zbiornik",
        "Tank level before filling": "Poziom paliwa przed tankowaniem",
        " remaining before filling": " przed tankowaniem",
        "New fill-up": "Nowe tankowanie",
        "Saving…": "Zapisywanie…",
        "Invalid server address.": "Nieprawidłowy adres serwera.",
        "Your API token was rejected. Update it in Settings.": "Token API został odrzucony. Zaktualizuj go w ustawieniach.",
        "The server did not return Fuel Log API data.": "Serwer nie zwrócił danych API Fuel Log.",
        "Update the server to support native fill-up entry.": "Zaktualizuj serwer, aby obsługiwał dodawanie tankowań z aplikacji.",
        "Enter a positive vehicle-reported economy or leave it empty.": "Podaj dodatnią wartość spalania wskazanego przez pojazd lub pozostaw pole puste.",
        "No save confirmation received. Check history before retrying.": "Nie otrzymano potwierdzenia zapisu. Sprawdź historię przed ponowieniem.",
        " If the connection failed during saving, check history before retrying to avoid a duplicate.": " Jeśli połączenie zostało przerwane podczas zapisu, sprawdź historię przed ponowieniem, aby uniknąć duplikatu.",
        "Choose a vehicle, station and valid fill-up date.": "Wybierz pojazd, stację i prawidłową datę tankowania.",
        "The odometer reading conflicts with another fill-up for this vehicle. Check the date and reading.": "Stan licznika jest sprzeczny z innym tankowaniem tego pojazdu. Sprawdź datę i odczyt.",
        "A personal API token is required to create fill-ups.": "Do dodawania tankowań wymagany jest osobisty token API.",
        "Price per unit": "Cena jednostkowa",
        "Vehicle-reported economy (optional)": "Spalanie według pojazdu (opcjonalnie)",
        "Language": "Język",
        "System": "Systemowy"
    ]
    if let translated = translations[text] { return translated }
    return text.replacingOccurrences(of: "Server returned HTTP", with: "Serwer zwrócił HTTP")
        .replacingOccurrences(of: "Could not save credentials securely", with: "Nie można bezpiecznie zapisać danych logowania")
        .replacingOccurrences(of: "Check the value entered for", with: "Sprawdź wartość pola")
}
