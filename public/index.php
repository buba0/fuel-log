<?php

declare(strict_types=1);

require __DIR__ . '/../src/app.php';
require __DIR__ . '/../src/views.php';
require __DIR__ . '/../src/api.php';
require __DIR__ . '/../src/exports.php';
require __DIR__ . '/../src/anomaly_views.php';
require __DIR__ . '/../src/price_views.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
session_name('fuel_log');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
set_language($_SESSION['language'] ?? 'en');

try {
    $path = getenv('FUEL_LOG_CONFIG') ?: '/etc/fuel-log/config.php';
    if (!is_readable($path)) {
        throw new RuntimeException('Fuel Log configuration is missing.');
    }
    $config = require $path;
    date_default_timezone_set($config['timezone'] ?? 'Europe/Warsaw');
    $pdo = db($config);
    if (input($_GET, 'page') === 'api') {
        api_response($pdo);
    }
    $user = isset($_SESSION['user_id'])
        ? query($pdo, 'SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch()
        : false;
    if ($user) {
        set_language($user['language'] ?? 'en');
    } else {
        $requestedLanguage = input($_GET, 'lang');
        if (in_array($requestedLanguage, ['en', 'pl'], true)) {
            set_language($requestedLanguage);
        }
    }
    $_SESSION['language'] = language();
    $page = input($_GET, 'page', $user ? 'dashboard' : 'login');
    $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
    $notice = t($_SESSION['notice'] ?? '');
    unset($_SESSION['notice']);
    $error = '';

    if (!$user && !in_array($page, ['login', 'register'], true)) {
        go('login');
    }

    if ($isPost && !hash_equals($_SESSION['csrf'], input($_POST, 'csrf'))) {
        http_response_code(403);
        message_page('Session expired', 'Refresh the page and submit the form again.');
        exit;
    }

    if (!$user) {
        if ($isPost) {
            try {
                if (empty($config['allow_signup']) && array_key_exists('allow_signup', $config) && $page === 'register') {
                    throw new FormError(t('Account registration is closed.'));
                }
                authenticate($pdo, $page);
            } catch (FormError $exception) {
                http_response_code(422);
                $error = $exception->getMessage();
            }
        }
        auth_page($page, $error, $config['allow_signup'] ?? true);
        exit;
    }

    if (in_array($page, ['login', 'register'], true)) {
        go('dashboard');
    }
    $userId = (int) $user['id'];
    $units = units_for($user);
    $cars = query($pdo, 'SELECT * FROM cars WHERE user_id = ? ORDER BY name, id', [$userId])->fetchAll();
    $stations = query($pdo, 'SELECT * FROM stations WHERE user_id = ? ORDER BY name, location, id', [$userId])->fetchAll();
    if ($page === 'export') {
        $from = input($_GET, 'from');
        $to = input($_GET, 'to');
        if (($from && !valid_date($from)) || ($to && !valid_date($to)) || ($from && $to && $from > $to)) {
            http_response_code(422);
            message_page('Record unavailable', 'Enter a valid date range with the start on or before the end.');
            exit;
        }
        if (!in_array(input($_GET, 'format', 'csv'), ['csv','txt','json','xlsx','docx'], true)) {
            http_response_code(400);
            message_page('Record unavailable', 'Choose a valid action.');
            exit;
        }
        $stats = fuel_telemetry(fill_rows($pdo, $userId, id_value($_GET, 'car_id')), $from ?: null, $to ?: null);
        $stats['in_range'] = sort_fillup_history($stats['in_range'], input($_GET, 'sort', 'date_desc'));
        export_fillups($stats, $user, input($_GET, 'format', 'csv'));
    }
    $allRows = fill_rows($pdo, $userId);
    foreach ($cars as &$vehicle) {
        $vehicle['prediction_rows'] = array_values(array_filter($allRows, static fn(array $r): bool => $r['car_id'] == $vehicle['id']));
    }
    unset($vehicle);
    $id = id_value($_GET, 'id');
    $record = false;
    if (in_array($page, ['car', 'station', 'fill', 'delete-fill'], true) && ($id || $page === 'delete-fill')) {
        $record = my_record($pdo, $page === 'delete-fill' ? 'fill' : $page, $userId, $id);
        if (!$record) {
            http_response_code(404);
            message_page('Record unavailable', 'This record could not be found.');
            exit;
        }
    }

    if ($isPost) {
        try {
            switch ($page) {
                case 'recalculate':
                    query($pdo, 'UPDATE fillups SET anomaly_dismissed = 0 WHERE user_id = ?', [$userId]);
                    go('history', notice: 'Alerts recalculated.');
                case 'anomaly':
                    handle_anomaly($pdo, $userId, $id);
                case 'api-token':
                    $action = input($_POST, 'action');
                    if (!in_array($action, ['create', 'revoke'], true)) {
                        throw new FormError(t('Choose a valid action.'));
                    }
                    $token = $action === 'create' ? bin2hex(random_bytes(32)) : null;
                    query($pdo, 'UPDATE users SET api_token_hash = ? WHERE id = ?', [$token ? hash('sha256', $token) : null, $userId]);
                    unset($_SESSION['new_api_token']);
                    if ($token) {
                        $_SESSION['new_api_token'] = $token;
                    }
                    go('settings', notice: 'API access updated.');
                case 'logout':
                    $_SESSION = ['language' => language()];
                    session_regenerate_id(true);
                    go('login');
                case 'car':
                    save_car($pdo, $userId, $units, $record);
                case 'station':
                    save_station($pdo, $userId, $record);
                case 'fill':
                    save_fill($pdo, $userId, $units, $record);
                case 'settings':
                    save_settings($pdo, $userId);
                case 'delete-fill':
                    query($pdo, 'DELETE FROM fillups WHERE id = ? AND user_id = ?', [$id, $userId]);
                    go('history', notice: 'Fill-up deleted.');
                default:
                    http_response_code(405);
                    message_page('Method unavailable', 'Open the dashboard to continue.');
                    exit;
            }
        } catch (FormError $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
            if ($page === 'anomaly') {
                $page = 'history';
            }
            if ($page === 'api-token') {
                $page = 'settings';
            }
        }
    }

    $title = match ($page) {
        'dashboard' => 'Dashboard',
        'history' => 'Fill-up history',
        'fill' => $record ? 'Edit fill-up' : 'New fill-up',
        'delete-fill' => 'Delete fill-up',
        'cars' => 'Vehicles',
        'car' => $record ? 'Edit vehicle' : 'Add vehicle',
        'stations' => 'Stations',
        'station' => $record ? 'Edit station' : 'Add station',
        'settings' => 'Preferences',
        'prices' => 'Fuel prices',
        default => '',
    };

    if ($title === '') {
        http_response_code(404);
        message_page('Page unavailable', 'Open the dashboard to continue.');
        exit;
    }
    layout_start($user, $page, $title, $notice, $error);
    match ($page) {
        'dashboard', 'history' => report_page($pdo, $user, $cars, $page),
        'fill' => fill_form($record, $user, $cars, $stations,
            query($pdo, 'SELECT car_id, station_id, price_per_liter FROM fillups WHERE user_id = ? ORDER BY filled_at DESC, id DESC LIMIT 1', [$userId])->fetch() ?: []),
        'delete-fill' => delete_form($record),
        'cars', 'stations' => directory_page($page, $page === 'cars' ? $cars : $stations, $units),
        'car', 'station' => directory_form($page, $record, $units),
        'settings' => settings_page($user, $cars, $stations),
        'prices' => price_page($allRows, $user),
    };
    layout_end();
} catch (Throwable $exception) {
    error_log(get_class($exception) . ' at ' . basename($exception->getFile()) . ':' . $exception->getLine()
        . ($exception instanceof PDOException ? ' (SQLSTATE ' . $exception->getCode() . ')' : ''));
    http_response_code(503);
    message_page('Fuel Log is unavailable', 'Try again in a moment.');
}
