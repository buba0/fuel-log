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
    var errorDescription: String? { if case .message(let text) = self { return text }; return nil }
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
            guard var parts = URLComponents(string: address) else { throw AppError.message("Invalid server address.") }
            var parameters = [URLQueryItem(name: "page", value: "api"), URLQueryItem(name: "format", value: "json")]
            if days > 0 {
                let formatter = DateFormatter(); formatter.dateFormat = "yyyy-MM-dd"; formatter.locale = Locale(identifier: "en_US_POSIX")
                parameters.append(URLQueryItem(name: "from", value: formatter.string(from: Calendar.current.date(byAdding: .day, value: -days, to: Date())!)))
            }
            if !vehicle.isEmpty { parameters.append(URLQueryItem(name: "car_id", value: vehicle)) }
            parts.queryItems = parameters
            guard let url = parts.url else { throw AppError.message("Invalid server address.") }
            var request = URLRequest(url: url); request.timeoutInterval = 20
            request.setValue("Bearer " + Vault.read(address), forHTTPHeaderField: "Authorization")
            let (data, response) = try await session.data(for: request)
            guard generation == requestID else { return }
            let status = (response as? HTTPURLResponse)?.statusCode ?? 0
            guard status == 200 else { throw AppError.message(status == 401 ? "Your API token was rejected. Update it in Settings." : "Server returned HTTP \(status). Check the server address.") }
            guard let object = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let rows = object["fillups"] as? [[String: Any]], let summary = object["statistics"] as? [String: Any] else { throw AppError.message("The server did not return Fuel Log API data.") }
            fills = rows.map { Fill(fields: $0) }.sorted { $0.date > $1.date }
            stats = summary; currency = object["currency"] as? String ?? ""; user = object["user"] as? String ?? ""
        } catch { if generation == requestID { self.error = error.localizedDescription } }
    }
    func stat(_ key: String) -> Double? { (stats[key] as? NSNumber)?.doubleValue }
}

struct NativeApp: View {
    @AppStorage("nativeServer") private var address = ""
    @AppStorage("imperial") private var imperial = false
    @StateObject private var store = Store()
    @State private var settings = false
    @State private var selected: Fill?
    @State private var chartDate: Date?
    @State private var search = ""
    @State private var newest = true
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
                } description: { Text("Connect your account to view your fuel history and statistics.") }
                actions: { Button("Connect account") { settings = true }.buttonStyle(.borderedProminent) }
            } else {
                TabView {
                    NavigationStack {
                        List {
                            status
                            Section {
                                Picker("Period", selection: $store.days) { Text("Lifetime").tag(0); Text("30 days").tag(30); Text("90 days").tag(90); Text("365 days").tag(365) }
                                if !store.vehicle.isEmpty { Button("Clear vehicle filter") { store.vehicle = "" } }
                            }
                            Section("Overview") {
                                metric("Fuel added", value(volume(store.stat("total_liters")), unit: volumeUnit))
                                metric("Total spent", value(store.stat("total_paid"), unit: store.currency))
                                metric("Distance", value(distance(store.stat("distance_km")), unit: distanceUnit))
                                metric("Average economy", value(economy(store.stat("avg_consumption")), unit: economyUnit))
                                metric("Fill-ups", value(store.stat("fill_count")))
                            }
                            Section("Fuel economy · " + economyUnit) {
                                if store.fills.contains(where: { $0.number("calculated_consumption_l_100km") != nil }) {
                                    Chart(store.fills) { fill in
                                        if let amount = economy(fill.number("calculated_consumption_l_100km")) {
                                            PointMark(x: .value("Date", fill.date), y: .value("Economy", amount)).foregroundStyle(by: .value("Vehicle", fill.text("car_name")))
                                        }
                                    }.frame(height: 220).chartXSelection(value: $chartDate)
                                    Text("Touch the chart to inspect a fill-up.").font(.caption).foregroundStyle(.secondary)
                                } else { Text("Two usable fill-ups are needed to calculate fuel economy.").foregroundStyle(.secondary) }
                            }
                            Section("Between fill-ups") {
                                metric("Average distance", value(distance(store.stat("avg_distance_km")), unit: distanceUnit))
                                metric("Average days", value(store.stat("avg_days")))
                                metric("Best economy", value(economy(store.stat("best_consumption")), unit: economyUnit))
                                metric("Worst economy", value(economy(store.stat("worst_consumption")), unit: economyUnit))
                            }
                        }.navigationTitle("Dashboard").refreshable { await store.refresh(address) }.toolbar { refreshButton }
                    }.tabItem { Label("Dashboard", systemImage: "chart.bar.fill") }
                    NavigationStack {
                        List {
                            status
                            if store.fills.isEmpty && !store.loading { ContentUnavailableView("No fill-ups", systemImage: "fuelpump", description: Text("Try a wider timeframe or add records on your server.")) }
                            ForEach(filteredFills) { fill in Button { selected = fill } label: {
                                HStack {
                                    Image(systemName: "fuelpump.fill").foregroundStyle(.orange)
                                    VStack(alignment: .leading) { Text(fill.text("car_name")).font(.headline); Text(fill.text("station_name")).font(.subheadline).foregroundStyle(.secondary); Text(fill.date, style: .date).font(.caption).foregroundStyle(.secondary) }
                                    Spacer()
                                    VStack(alignment: .trailing) { Text(value(volume(fill.number("liters")), unit: volumeUnit)); Text(value(fill.number("total_paid"), unit: store.currency)).font(.caption).foregroundStyle(.secondary) }
                                }.foregroundStyle(.primary)
                            } }
                        }.navigationTitle("Fill-ups").searchable(text: $search, prompt: "Vehicle or station").refreshable { await store.refresh(address) }
                        .toolbar { Button { newest.toggle() } label: { Image(systemName: "arrow.up.arrow.down") }.accessibilityLabel("Reverse date order"); refreshButton }
                    }.tabItem { Label("Fill-ups", systemImage: "list.bullet") }
                    NavigationStack {
                        List {
                            Section("Account") { LabeledContent("Username", value: store.user); Text(address).font(.footnote).textSelection(.enabled); Button("Connection settings") { settings = true } }
                            Section("Display") { Toggle("US imperial units", isOn: $imperial) }
                            Section { Text("This native version reads server data. Adding or editing records is not yet available in the iOS app.").foregroundStyle(.secondary) }
                            Button("Disconnect", role: .destructive) { Vault.remove(address); address = ""; store.clear() }
                        }.navigationTitle("Settings")
                    }.tabItem { Label("Settings", systemImage: "gearshape") }
                }
            }
        }.tint(.orange)
        .sheet(isPresented: $settings, onDismiss: { store.clear(); Task { await store.refresh(address) } }) { ConnectionSettings(address: $address) }
        .sheet(item: $selected) { fill in
            NavigationStack {
                List {
                    Section { Text(fill.text("car_name")).font(.title2.bold()); Text(fill.text("station_name")); Text(fill.date, style: .date) }
                    Section("Measurements") {
                        metric("Fuel added", value(volume(fill.number("liters")), unit: volumeUnit))
                        metric("Amount paid", value(fill.number("total_paid"), unit: store.currency))
                        metric("Odometer", value(distance(fill.number("odometer_km")), unit: distanceUnit))
                        metric("Distance", value(distance(fill.number("calculated_distance_km")), unit: distanceUnit))
                        metric("Economy", value(economy(fill.number("calculated_consumption_l_100km")), unit: economyUnit))
                        metric("Days since previous", value(fill.number("calculated_interval_days")))
                        metric("Fuel before filling", value(volume(fill.number("tank_before_liters")), unit: volumeUnit))
                    }
                    Button("Filter dashboard to this vehicle") { store.vehicle = fill.text("car_id"); selected = nil }
                }.navigationTitle("Fill-up details").navigationBarTitleDisplayMode(.inline)
                .toolbar { Button("Done") { selected = nil } }
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
        if store.loading { ProgressView("Updating…") }
        if let error = store.error { Section { Text(error).foregroundStyle(.red); Text("Previously loaded values may be outdated.").font(.caption); Button("Retry") { Task { await store.refresh(address) } } } }
    }
    var refreshButton: some View { Button { Task { await store.refresh(address) } } label: { Image(systemName: "arrow.clockwise") }.disabled(store.loading).accessibilityLabel("Refresh") }
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
                Section("Server") { TextField("https://fuel.example.com/", text: $server).keyboardType(.URL).textInputAutocapitalization(.never).autocorrectionDisabled() }
                Section("API token") {
                    SecureField("Paste API token", text: $token).textInputAutocapitalization(.never).autocorrectionDisabled()
                    Text("Create a personal API token in Fuel Log’s web Preferences. The app reads your account data through the API and stores the token in Keychain.").font(.footnote)
                }
                Section { Text("HTTPS is required except for local hostnames and private IPv4 addresses. Your phone must be able to reach the server. The app does not bypass certificate validation.").font(.footnote) }
                if let error { Text(error).foregroundStyle(.red) }
            }.navigationTitle("Connect account")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) { Button("Save") { save() } }
            }.onAppear { server = address; token = Vault.read(address) }
        }
    }
    func save() {
        let raw = server.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let parts = URLComponents(string: raw), let host = parts.host,
              parts.user == nil, parts.password == nil, parts.query == nil, parts.fragment == nil else { error = "Enter a server URL without credentials or query parameters."; return }
        let octets = host.split(separator: ".").compactMap { Int($0) }
        let privateIP = octets.count == 4 && octets.allSatisfy { (0...255).contains($0) } && (octets[0] == 10 || (octets[0] == 192 && octets[1] == 168) || (octets[0] == 172 && (16...31).contains(octets[1])))
        let local = privateIP || host.hasSuffix(".local") || host.hasSuffix(".home") || !host.contains(".")
        guard parts.scheme == "https" || (parts.scheme == "http" && local) else { error = "Use HTTPS for non-local servers."; return }
        let credential = token.trimmingCharacters(in: .whitespacesAndNewlines)
        guard credential.range(of: "^[a-f0-9]{64}$", options: .regularExpression) != nil else { error = "Enter the 64-character personal API token."; return }
        do { try Vault.save(credential, account: raw); address = raw; dismiss() } catch { self.error = error.localizedDescription }
    }
}
