<?php
declare(strict_types=1);

function api_response(PDO $pdo): never
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $hash = preg_match('/^Bearer ([a-f0-9]{64})$/D', $auth, $match) ? hash('sha256', $match[1]) : '';
    $personal = $hash ? query($pdo, 'SELECT user_id FROM api_tokens WHERE token_hash = ?', [$hash])->fetchColumn() : false;
    $user = $hash ? query($pdo, 'SELECT * FROM users WHERE api_token_hash = ? OR grafana_token_hash = ?', [$hash, $hash])->fetch() : false;
    if ($personal) { $user = query($pdo, 'SELECT * FROM users WHERE id = ?', [$personal])->fetch(); }
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        header('WWW-Authenticate: Bearer');
        echo json_encode(['error' => 'A valid API token is required.']);
        exit;
    }
    $method = $_SERVER['REQUEST_METHOD'];
    $resource = input($_GET, 'resource');
    if (in_array($method, ['POST', 'DELETE'], true)) {
        if (!$personal && (!$user['api_token_hash'] || !hash_equals($user['api_token_hash'], $hash))) {
            api_json(['error' => 'A personal API token is required to modify fill-ups.'], 403);
        }
        if ($resource !== 'fillups') { api_json(['error' => 'Unknown write resource.'], 404); }
        if ($method === 'DELETE') {
            $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) { api_json(['error' => 'A valid fill-up ID is required.'], 400); }
            $deleted = query($pdo, 'DELETE FROM fillups WHERE id = ? AND user_id = ?', [$id, $user['id']]);
            if (!$deleted->rowCount()) { api_json(['error' => 'Fill-up not found.'], 404); }
            api_json(['deleted' => $id]);
        }
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            api_json(['error' => 'Use application/json.'], 415);
        }
        try {
            $body = json_decode(file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($body) || array_is_list($body)) { api_json(['error' => 'Expected a JSON object.'], 400); }
            $_POST = [];
            foreach ($body as $key => $value) { if (is_string($value) || is_int($value) || is_float($value)) { $_POST[$key] = (string) $value; } }
            $id = persist_fill($pdo, (int) $user['id'], ['distance'=>'km','volume'=>'l','price'=>'l','economy'=>'l_100km'], false);
            api_json(['id' => $id], 201);
        } catch (JsonException $exception) { api_json(['error' => 'Invalid JSON.'], 400); }
        catch (FormError $exception) { api_json(['error' => $exception->getMessage()], 422); }
    }
    if ($method !== 'GET') { header('Allow: GET, POST, DELETE'); api_json(['error' => 'Method not allowed.'], 405); }
    if ($resource === 'fill-form') {
        $last = query($pdo, 'SELECT car_id, station_id, price_per_liter FROM fillups WHERE user_id = ? ORDER BY filled_at DESC, odometer_km DESC, id DESC LIMIT 1', [$user['id']])->fetch() ?: [];
        api_json([
            'cars' => query($pdo, 'SELECT id, name, tank_capacity_liters, fuel_metering FROM cars WHERE user_id = ? ORDER BY name', [$user['id']])->fetchAll(),
            'stations' => query($pdo, 'SELECT id, name, location FROM stations WHERE user_id = ? ORDER BY name', [$user['id']])->fetchAll(),
            'defaults' => ['car_id' => $user['default_car_id'] ?: ($last['car_id'] ?? null), 'station_id' => $user['default_station_id'] ?: ($last['station_id'] ?? null), 'price' => $last['price_per_liter'] ?? null],
            'currency' => $user['currency'],
        ]);
    }
    $from = input($_GET, 'from');
    $to = input($_GET, 'to');
    if (($from && !valid_date($from)) || ($to && !valid_date($to)) || ($from && $to && $from > $to)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid ISO date range.']);
        exit;
    }
    $rows = fill_rows($pdo, (int) $user['id'], id_value($_GET, 'car_id'));
    $stats = fuel_telemetry($rows, $from ?: null, $to ?: null);
    $format = input($_GET, 'format', 'json');
    if ($format === 'json') {
        foreach ($stats['in_range'] as &$row) {
            unset($row['consumption_l_100km'], $row['user_id']);
        }
        unset($row);
        header('Content-Type: application/json');
        echo json_encode(['version' => 1, 'user' => $user['username'], 'currency' => $user['currency'],
            'units' => ['distance' => 'km', 'volume' => 'L', 'economy' => 'L/100 km'],
            'statistics' => array_diff_key($stats, ['in_range' => true]), 'fillups' => $stats['in_range']],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
    if ($format !== 'metrics') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo '{"error":"Choose json or metrics."}';
        exit;
    }
    header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
    $base = ['user' => $user['username'], 'currency' => $user['currency']];
    emit_metrics($stats, $base + ['car_id' => 'all', 'car' => 'All vehicles']);
    $byCar = [];
    foreach ($rows as $row) {
        $byCar[$row['car_id']][] = $row;
    }
    foreach ($byCar as $carId => $carRows) {
        emit_metrics(fuel_telemetry($carRows, $from ?: null, $to ?: null), $base + ['car_id' => (string) $carId, 'car' => $carRows[0]['car_name']]);
    }
    $metricFields = ['liters', 'total_paid', 'price_per_liter', 'odometer_km', 'tank_before_liters', 'tank_before_percent',
        'calculated_distance_km', 'calculated_consumption_l_100km'];
    foreach ($stats['in_range'] as $row) {
        $labels = $base + ['car_id' => (string) $row['car_id'], 'car' => $row['car_name'], 'station' => $row['station_name'],
            'date' => $row['filled_at'], 'fill_id' => (string) $row['id']];
        foreach ($metricFields as $field) {
            emit_metric('fuel_log_fill_' . $field, $labels, $row[$field]);
        }
        emit_metric('fuel_log_fill_timestamp_seconds', $labels, strtotime($row['filled_at'] . ' UTC'));
    }
    $months = [];
    foreach ($stats['in_range'] as $row) {
        $months[substr($row['filled_at'], 0, 7)] = true;
    }
    foreach (array_keys($months) as $month) {
        $monthStats = fuel_telemetry($rows, $month . '-01', date('Y-m-t', strtotime($month . '-01')));
        foreach (['total_liters', 'total_paid', 'distance_km', 'avg_consumption', 'fill_count'] as $key) {
            emit_metric('fuel_log_month_' . $key, $base + ['month' => $month], $monthStats[$key]);
        }
    }
    $stations = [];
    foreach ($stats['in_range'] as $row) {
        $stations[$row['station_id']][] = $row;
    }
    foreach ($stations as $stationRows) {
        emit_metric('fuel_log_station_total_paid', $base + ['station' => $stationRows[0]['station_name']],
            array_sum(array_column($stationRows, 'total_paid')));
        emit_metric('fuel_log_station_total_liters', $base + ['station' => $stationRows[0]['station_name']],
            array_sum(array_column($stationRows, 'liters')));
    }
    $last = end($stats['in_range']);
    emit_metric('fuel_log_last_fill_timestamp_seconds', $base, $last ? strtotime($last['filled_at'] . ' UTC') : null);
    $rowsById = array_column($rows, null, 'id');
    emit_metric('fuel_log_anomaly_count', $base, count(array_filter(fuel_anomalies($rows),
        static fn(array $notice): bool => empty($rowsById[$notice['fill_id']]['anomaly_dismissed']))));
    exit;
}

function emit_metrics(array $stats, array $labels): void
{
    foreach (['fill_count','total_liters','total_paid','distance_km','avg_distance_km','avg_days',
        'avg_consumption','best_consumption','worst_consumption','avg_price_per_liter','cost_per_km'] as $key) {
        emit_metric('fuel_log_' . $key, $labels, $stats[$key]);
    }
}

function emit_metric(string $name, array $labels, mixed $value): void
{
    if ($value === null) {
        return;
    }
    $parts = [];
    foreach ($labels as $key => $label) {
        $escaped = str_replace(["\\", "\n", '"'], ["\\\\", "\\n", '\\"'], (string) $label);
        $parts[] = $key . '="' . $escaped . '"';
    }
    echo $name . '{' . implode(',', $parts) . '} ' . sprintf('%.10g', (float) $value) . "\n";
}

function api_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}
