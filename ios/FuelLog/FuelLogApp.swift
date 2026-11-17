import SwiftUI
import WebKit
import Combine
import UniformTypeIdentifiers

@main
struct FuelLogApp: App {
    var body: some Scene { WindowGroup { ContentView() } }
}

@MainActor
final class Browser: NSObject, ObservableObject, WKNavigationDelegate, WKDownloadDelegate {
    let web = WKWebView(frame: .zero, configuration: WKWebViewConfiguration())
    @Published var loading = false
    @Published var error: String?
    @Published var canGoBack = false
    @Published var share: SharedFile?
    private var server: URL?
    private var downloads: [ObjectIdentifier: URL] = [:]

    override init() {
        super.init()
        web.navigationDelegate = self
        web.allowsBackForwardNavigationGestures = true
    }
    func open(_ url: URL) {
        server = url
        error = nil
        web.load(URLRequest(url: url))
    }
    func reload() { error = nil; if web.url != nil { web.reload() } else if let server { open(server) } }
    func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
        loading = true; error = nil
    }
    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        loading = false; canGoBack = webView.canGoBack
    }
    func failed(_ failure: Error) {
        loading = false
        if (failure as NSError).code != NSURLErrorCancelled { error = failure.localizedDescription }
    }
    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError failure: Error) { failed(failure) }
    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError failure: Error) { failed(failure) }
    func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction,
                 decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = action.request.url, let server else { decisionHandler(.cancel); return }
        let sameOrigin = url.scheme == server.scheme && url.host == server.host && url.port == server.port
        if sameOrigin { decisionHandler(action.shouldPerformDownload ? .download : .allow) }
        else {
            decisionHandler(.cancel)
            if action.navigationType == .linkActivated && ["https", "http", "mailto"].contains(url.scheme ?? "") {
                UIApplication.shared.open(url)
            } else { loading = false; error = "The server redirected to a different address. Update the server address in Settings." }
        }
    }
    func webView(_ webView: WKWebView, decidePolicyFor response: WKNavigationResponse,
                 decisionHandler: @escaping (WKNavigationResponsePolicy) -> Void) {
        let attachment = (response.response as? HTTPURLResponse)?.value(forHTTPHeaderField: "Content-Disposition")?.lowercased().contains("attachment") == true
        decisionHandler(attachment || !response.canShowMIMEType ? .download : .allow)
    }
    func webView(_ webView: WKWebView, navigationAction: WKNavigationAction, didBecome download: WKDownload) { download.delegate = self }
    func webView(_ webView: WKWebView, navigationResponse: WKNavigationResponse, didBecome download: WKDownload) { download.delegate = self }
    func download(_ download: WKDownload, decideDestinationUsing response: URLResponse, suggestedFilename: String,
                  completionHandler: @escaping (URL?) -> Void) {
        let directory = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        do {
            try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
            let name = (suggestedFilename as NSString).lastPathComponent
            let destination = directory.appendingPathComponent(name.isEmpty ? "export" : name)
            downloads[ObjectIdentifier(download)] = destination
            completionHandler(destination)
        } catch { failed(error); completionHandler(nil) }
    }
    func downloadDidFinish(_ download: WKDownload) {
        loading = false
        if let file = downloads.removeValue(forKey: ObjectIdentifier(download)) { share = SharedFile(url: file) }
    }
    func download(_ download: WKDownload, didFailWithError error: Error, resumeData: Data?) {
        if let file = downloads.removeValue(forKey: ObjectIdentifier(download)) { try? FileManager.default.removeItem(at: file.deletingLastPathComponent()) }
        failed(error)
    }
}

struct SharedFile: Identifiable { let id = UUID(); let url: URL }
struct WebContent: UIViewRepresentable {
    let browser: Browser
    func makeUIView(context: Context) -> WKWebView { browser.web }
    func updateUIView(_ view: WKWebView, context: Context) {}
}
struct FileShare: UIViewControllerRepresentable {
    let file: URL
    func makeUIViewController(context: Context) -> UIActivityViewController {
        let controller = UIActivityViewController(activityItems: [file], applicationActivities: nil)
        controller.completionWithItemsHandler = { _, _, _, _ in try? FileManager.default.removeItem(at: file.deletingLastPathComponent()) }
        return controller
    }
    func updateUIViewController(_ controller: UIActivityViewController, context: Context) {}
}

struct ContentView: View {
    @AppStorage("serverAddress") private var address = ""
    @StateObject private var browser = Browser()
    @State private var settings = false
    var body: some View {
        NavigationStack {
            Group {
                if address.isEmpty {
                    VStack(spacing: 18) {
                        Image(systemName: "fuelpump.fill").font(.system(size: 54)).foregroundStyle(.orange)
                        Text("Fuel Log").font(.largeTitle.bold())
                        Text("Connect to your Fuel Log server to access your vehicles, fill-ups and statistics.").multilineTextAlignment(.center)
                        Button("Connect to server") { settings = true }.buttonStyle(.borderedProminent)
                    }.padding(32)
                } else {
                    ZStack {
                        WebContent(browser: browser)
                        if let error = browser.error {
                            VStack(spacing: 14) {
                                Image(systemName: "wifi.exclamationmark").font(.largeTitle)
                                Text("Unable to load Fuel Log").font(.headline)
                                Text(error).font(.callout).multilineTextAlignment(.center)
                                Button("Try again") { browser.reload() }.buttonStyle(.borderedProminent)
                            }.padding(24).frame(maxWidth: .infinity, maxHeight: .infinity).background(.background)
                        }
                    }.overlay(alignment: .top) { if browser.loading { ProgressView().padding(8).background(.regularMaterial, in: Capsule()) } }
                }
            }
            .navigationTitle("Fuel Log").navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarLeading) { Button { browser.web.goBack() } label: { Image(systemName: "chevron.left") }.disabled(!browser.canGoBack).accessibilityLabel("Back") }
                ToolbarItemGroup(placement: .topBarTrailing) {
                    Button { browser.reload() } label: { Image(systemName: "arrow.clockwise") }.disabled(address.isEmpty).accessibilityLabel("Refresh")
                    Button { settings = true } label: { Image(systemName: "gearshape") }.accessibilityLabel("Server settings")
                }
            }
            .sheet(isPresented: $settings) { ServerSettings(address: $address) }
            .sheet(item: $browser.share) { FileShare(file: $0.url) }
            .onAppear { if let url = URL(string: address), browser.web.url == nil { browser.open(url) } }
            .onChange(of: address) { _, value in if let url = URL(string: value) { browser.open(url) } }
        }.tint(.orange)
    }
}

struct ServerSettings: View {
    @Binding var address: String
    @Environment(\.dismiss) private var dismiss
    @State private var draft = ""
    private var validURL: URL? {
        guard let url = URL(string: draft.trimmingCharacters(in: .whitespacesAndNewlines)),
              ["http", "https"].contains(url.scheme ?? ""), let host = url.host, !host.isEmpty,
              url.user == nil, url.password == nil, url.query == nil, url.fragment == nil else { return nil }
        return url
    }
    var body: some View {
        NavigationStack {
            Form {
                Section("Server address") {
                    TextField("https://fuel.example.com/", text: $draft).keyboardType(.URL).textInputAutocapitalization(.never).autocorrectionDisabled()
                    Text("Use the address of your Fuel Log installation. Your iPhone must be able to reach that server.").font(.footnote)
                }
                Section {
                    Text("Use HTTPS when possible. HTTP is intended only for trusted local networks. Sign in using your existing Fuel Log account. Passwords are handled by the server; this app does not store them in preferences.").font(.footnote)
                    Text("Language, units and theme are available in Fuel Log Preferences. An internet or local-network connection is required; offline editing is not supported.").font(.footnote)
                }
            }
            .navigationTitle("Connection")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) { Button("Save") { if let url = validURL { address = url.absoluteString; dismiss() } }.disabled(validURL == nil) }
            }
            .onAppear { draft = address }
        }
    }
}
