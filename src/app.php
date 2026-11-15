<?php

declare(strict_types=1);

require_once __DIR__ . '/domain.php';

final class FormError extends RuntimeException {}

function db(array $config): PDO
{
    return new PDO($config['dsn'], $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function query(PDO $pdo, string $sql, array $args = []): PDOStatement
{
    $statement = $pdo->prepare($sql);
    $statement->execute($args);
    return $statement;
}

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page, array $args = []): string
{
    return '?' . http_build_query(['page' => $page] + $args);
}

function go(string $page, array $args = [], ?string $notice = null): never
{
    if ($notice !== null) {
        $_SESSION['notice'] = $notice;
    }
    header('Location: ' . url($page, $args), true, 303);
    exit;
}

function input(array $source, string $key, string $default = ''): string
{
    return is_string($source[$key] ?? null) ? $source[$key] : $default;
}

function id_value(array $source, string $key): int
{
    return filter_var(input($source, $key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
}

function csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function required_text(string $field, string $label, int $maximum, bool $optional = false): string
{
    $value = trim(input($_POST, $field));
    $length = preg_match_all('/./us', $value);
    if ($length === false || $length > $maximum || (!$optional && $value === '')) {
        throw new FormError($optional
            ? "$label can have up to $maximum characters."
            : "Enter $label using up to $maximum characters.");
    }
    return $value;
}

function number_input(string $field, string $label, float $min, float $max, ?float $default = null): float
{
    $raw = str_replace(',', '.', trim(input($_POST, $field)));
    if ($raw === '' && $default !== null) {
        return $default;
    }
    if (!is_numeric($raw) || !is_finite((float) $raw) || (float) $raw < $min || (float) $raw > $max) {
        throw new FormError("Check the value entered for $label.");
    }
    return (float) $raw;
}

function valid_date(string $date): bool
{
    if (!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $date)) {
        return false;
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

function units_for(array $user): array
{
    return [
        'distance' => $user['unit_distance'],
        'volume' => $user['unit_volume'],
        'economy' => $user['unit_economy'],
        'price' => $user['unit_price'],
    ];
}

function unit_symbol(string $code): string
{
    return match ($code) {
        'km' => 'km',
        'mi' => 'mi',
        'l' => 'L',
        'us_gal' => 'US gal',
        'uk_gal' => 'UK gal',
        'l_100km' => 'L/100 km',
        'mpg_us' => 'mpg (US)',
        'mpg_uk' => 'mpg (UK)',
        'km_l' => 'km/L',
    };
}

function metric_field(array $units, string $dimension, string $field, string $label, float $min, float $max, int $scale): float
{
    $value = round(fuel_to_metric(number_input($field, $label, $min === 0.0 ? 0.0 : 0.000001, 1000000000), $dimension, $units[$dimension]), $scale);
    if (!is_finite($value) || $value < $min || $value > $max) {
        throw new FormError("Check the value entered for $label.");
    }
    return $value;
}

function my_record(PDO $pdo, string $type, int $userId, int $id): array|false
{
    $table = match ($type) {
        'car' => 'cars',
        'station' => 'stations',
        'fill' => 'fillups',
    };
    return query($pdo, "SELECT * FROM $table WHERE id = ? AND user_id = ?", [$id, $userId])->fetch();
}

function authenticate(PDO $pdo, string $page): never
{
    $username = trim(input($_POST, 'username'));
    $password = input($_POST, 'password');
    if ($page === 'register') {
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,48}$/D', $username)) {
            throw new FormError('Use 3–48 letters, numbers, dots, underscores or hyphens for your username.');
        }
        if (strlen($password) < 10 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new FormError('Use a password between 10 and 72 bytes.');
        }
        try {
            query($pdo, 'INSERT INTO users (username, password_hash) VALUES (?, ?)',
                [$username, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new FormError('That username is already in use.');
            }
            throw $exception;
        }
        $userId = (int) $pdo->lastInsertId();
    } else {
        $account = query($pdo, 'SELECT id, password_hash FROM users WHERE username = ?', [$username])->fetch();
        $hash = $account['password_hash'] ?? '$2y$10$Ojn/jbkxTufGKBo/eN9oCerF4kQUGkKsXPRPz6WZIJN9PJjKAvsWO';
        if (strlen($password) > 72 || !password_verify($password, $hash) || !$account) {
            throw new FormError('The username or password is incorrect.');
        }
        $userId = (int) $account['id'];
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            query($pdo, 'UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
        }
    }
    session_regenerate_id(true);
    $_SESSION = ['user_id' => $userId, 'csrf' => bin2hex(random_bytes(32))];
    go('dashboard');
}

function save_car(PDO $pdo, int $userId, array $units, array|false $record): never
{
    $name = required_text('name', 'a vehicle name', 100);
    $capacity = metric_field($units, 'volume', 'capacity', 'tank capacity', 0.01, 99999, 5);
    if ($record) {
        query($pdo, 'UPDATE cars SET name = ?, tank_capacity_liters = ? WHERE id = ? AND user_id = ?',
            [$name, $capacity, $record['id'], $userId]);
    } else {
        query($pdo, 'INSERT INTO cars (name, tank_capacity_liters, user_id) VALUES (?, ?, ?)', [$name, $capacity, $userId]);
    }
    go('cars', notice: 'Vehicle saved.');
}

function save_station(PDO $pdo, int $userId, array|false $record): never
{
    $name = required_text('name', 'a station name', 100);
    $location = required_text('location', 'Location', 150, true);
    if ($record) {
        query($pdo, 'UPDATE stations SET name = ?, location = ? WHERE id = ? AND user_id = ?',
            [$name, $location, $record['id'], $userId]);
    } else {
        query($pdo, 'INSERT INTO stations (name, location, user_id) VALUES (?, ?, ?)', [$name, $location, $userId]);
    }
    go('stations', notice: 'Station saved.');
}

function save_fill(PDO $pdo, int $userId, array $units, array|false $record): never
{
    $car = my_record($pdo, 'car', $userId, id_value($_POST, 'car_id'));
    $station = my_record($pdo, 'station', $userId, id_value($_POST, 'station_id'));
    $date = input($_POST, 'filled_at');
    if (!$car || !$station || !valid_date($date)) {
        throw new FormError('Choose a vehicle, station and valid fill-up date.');
    }
    $liters = metric_field($units, 'volume', 'volume', 'fuel added', 0.00001, 99999, 5);
    $price = metric_field($units, 'price', 'price', 'price per unit', 0.0, 999999, 7);
    $odometer = metric_field($units, 'distance', 'odometer', 'odometer', 0.0, 999999999, 5);
    $economy = metric_field($units, 'economy', 'economy', 'average fuel use', 0.000001, 99999, 6);
    $percent = round(number_input('tank_level', 'tank level before filling', 0, 100, 0), 2);
    $capacity = (float) ($record && $record['car_id'] == $car['id']
        ? $record['tank_capacity_liters'] : $car['tank_capacity_liters']);
    $paid = round($liters * $price, 2);
    $before = round($capacity * $percent / 100, 5);
    $data = [$car['id'], $station['id'], $date, $liters, $price, $paid, $odometer, $economy, $percent, $capacity, $before];
    $pdo->beginTransaction();
    try {
        query($pdo, 'SELECT id FROM cars WHERE id = ? AND user_id = ? FOR UPDATE', [$car['id'], $userId]);
        $exclude = (int) ($record['id'] ?? 0);
        $outOfOrder = query($pdo, 'SELECT id FROM fillups WHERE user_id = ? AND car_id = ? AND id <> ?
            AND ((filled_at < ? AND odometer_km > ?) OR (filled_at > ? AND odometer_km < ?)) LIMIT 1',
            [$userId, $car['id'], $exclude, $date, $odometer, $date, $odometer])->fetchColumn();
        if ($outOfOrder) {
            throw new FormError('The odometer reading conflicts with another fill-up for this vehicle. Check the date and reading.');
        }
        if ($record) {
            query($pdo, 'UPDATE fillups SET car_id = ?, station_id = ?, filled_at = ?, liters = ?,
                price_per_liter = ?, total_paid = ?, odometer_km = ?, consumption_l_100km = ?,
                tank_before_percent = ?, tank_capacity_liters = ?, tank_before_liters = ? WHERE id = ? AND user_id = ?',
                [...$data, $record['id'], $userId]);
        } else {
            query($pdo, 'INSERT INTO fillups (car_id, station_id, filled_at, liters, price_per_liter, total_paid,
                odometer_km, consumption_l_100km, tank_before_percent, tank_capacity_liters, tank_before_liters, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$data, $userId]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
    go('history', notice: 'Fill-up saved.');
}

function save_settings(PDO $pdo, int $userId): never
{
    $presets = fuel_unit_presets();
    $preset = input($_POST, 'preset');
    $currency = strtoupper(trim(input($_POST, 'currency')));
    if (!preg_match('/^[A-Z]{3}$/D', $currency) || ($preset !== 'custom' && !isset($presets[$preset]))) {
        throw new FormError('Choose a unit preset and enter a three-letter currency code.');
    }
    $units = $presets[$preset] ?? [];
    if ($preset === 'custom') {
        foreach (fuel_unit_options() as $dimension => $options) {
            $unit = input($_POST, $dimension);
            if (!isset($options[$unit])) {
                throw new FormError('Choose a valid unit for each measurement.');
            }
            $units[$dimension] = $unit;
        }
    }
    query($pdo, 'UPDATE users SET currency = ?, unit_preset = ?, unit_distance = ?, unit_volume = ?,
        unit_economy = ?, unit_price = ? WHERE id = ?', [$currency, $preset, ...array_values($units), $userId]);
    go('settings', notice: 'Preferences saved.');
}

function fill_rows(PDO $pdo, int $userId, int $carId = 0): array
{
    return query($pdo, 'SELECT f.*, c.name AS car_name, s.name AS station_name
        FROM fillups f JOIN cars c ON c.id = f.car_id JOIN stations s ON s.id = f.station_id
        WHERE f.user_id = ?' . ($carId ? ' AND f.car_id = ?' : '') . ' ORDER BY f.filled_at, f.odometer_km, f.id',
        $carId ? [$userId, $carId] : [$userId])->fetchAll();
}
