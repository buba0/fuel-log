<?php

declare(strict_types=1);

require __DIR__ . '/../src/app.php';
require __DIR__ . '/../src/views.php';

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

try {
    $path = getenv('FUEL_LOG_CONFIG') ?: '/etc/fuel-log/config.php';
    if (!is_readable($path)) {
        throw new RuntimeException('Fuel Log configuration is missing.');
    }
    $config = require $path;
    date_default_timezone_set($config['timezone'] ?? 'Europe/Warsaw');
    $pdo = db($config);
    $user = isset($_SESSION['user_id'])
        ? query($pdo, 'SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch()
        : false;
    $page = input($_GET, 'page', $user ? 'dashboard' : 'login');
    $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
    $notice = $_SESSION['notice'] ?? '';
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
                    throw new FormError('Account registration is closed.');
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
                case 'logout':
                    $_SESSION = [];
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
        'fill' => fill_form($record, $user, $cars, $stations),
        'delete-fill' => delete_form($record),
        'cars', 'stations' => directory_page($page, $page === 'cars' ? $cars : $stations, $units),
        'car', 'station' => directory_form($page, $record, $units),
        'settings' => settings_page($user),
    };
    layout_end();
} catch (Throwable $exception) {
    error_log(get_class($exception) . ' at ' . basename($exception->getFile()) . ':' . $exception->getLine()
        . ($exception instanceof PDOException ? ' (SQLSTATE ' . $exception->getCode() . ')' : ''));
    http_response_code(503);
    message_page('Fuel Log is unavailable', 'Try again in a moment.');
}
