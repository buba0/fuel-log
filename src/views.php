<?php

declare(strict_types=1);

function icon(string $name): string
{
    $path = match ($name) {
        'fuel' => '<path d="M3 22h12M5 22V4a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v18M5 10h10m0 4h2a2 2 0 0 1 2 2v2a2 2 0 0 0 4 0v-7l-4-4m2 2v2a2 2 0 0 0 2 2"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'history' => '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'car' => '<path d="M5 18H3v-5l3-2 2-5h8l2 5 3 2v5h-2M7 18h10M6 11h12"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'settings' => '<path d="M5 4v16m7-16v16m7-16v16M3 8h4m3 8h4m3-7h4"/>',
        'logout' => '<path d="M10 17l5-5-5-5m5 5H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'distance' => '<path d="m4 21 4-18m12 18L16 3m-4 1v3m0 3v3m0 3v4"/>',
        'money' => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'trend' => '<path d="m3 17 5-5 4 4 8-8m-5 0h5v5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        default => '<circle cx="12" cy="12" r="8"/>',
    };
    return '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

function html_start(string $title): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($title) ?> · Fuel Log</title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="stylesheet" href="/assets/app.css?v=1">
    <script defer src="/assets/app.js?v=1"></script>
</head>
<body>
<?php
}

function brand(): string
{
    return '<a class="brand" href="' . e(url('dashboard')) . '"><span class="brand-icon">'
        . icon('fuel') . '</span><span>Fuel Log</span></a>';
}

function action_link(string $label, string $page, array $args = [], string $style = 'secondary', ?string $glyph = null): string
{
    return '<a class="button button-' . e($style) . '" href="' . e(url($page, $args)) . '">'
        . ($glyph ? icon($glyph) : '') . e($label) . '</a>';
}

function layout_start(array $user, string $page, string $title, string $notice, string $error): void
{
    $navigation = [
        ['dashboard', 'Dashboard', 'dashboard', ['dashboard']],
        ['history', 'Fill-ups', 'history', ['history', 'fill', 'delete-fill']],
        ['cars', 'Vehicles', 'car', ['cars', 'car']],
        ['stations', 'Stations', 'pin', ['stations', 'station']],
        ['settings', 'Preferences', 'settings', ['settings']],
    ];
    html_start($title);
    ?>
<div class="app-shell">
    <aside class="sidebar" data-menu>
        <div class="sidebar-header">
            <?= brand() ?>
            <button type="button" class="menu-toggle" data-menu-toggle aria-label="Toggle navigation" aria-controls="navigation" aria-expanded="false" hidden><?= icon('menu') ?></button>
        </div>
        <nav id="navigation" class="nav-list" aria-label="Main navigation">
            <?php foreach ($navigation as [$target, $label, $glyph, $pages]): ?>
                <a class="nav-link" href="<?= e(url($target)) ?>"<?= in_array($page, $pages, true) ? ' aria-current="page"' : '' ?>><?= icon($glyph) ?><span><?= e($label) ?></span></a>
            <?php endforeach ?>
        </nav>
        <div class="sidebar-footer">
            <span class="user-avatar" aria-hidden="true"><?= e(strtoupper($user['username'][0])) ?></span>
            <strong class="user-name" title="<?= e($user['username']) ?>"><?= e($user['username']) ?></strong>
            <form method="post" action="<?= e(url('logout')) ?>"><?= csrf() ?><button class="signout" type="submit" aria-label="Sign out" title="Sign out"><?= icon('logout') ?></button></form>
        </div>
    </aside>
    <div class="workspace">
        <main class="main" id="main">
            <?php if ($notice !== ''): ?><div class="notice notice-success" role="status"><?= e($notice) ?></div><?php endif ?>
            <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif ?>
<?php
}

function layout_end(): void
{
    ?></main></div></div></body></html><?php
}

function page_heading(string $title, string $subtitle = '', string $actions = ''): void
{
    ?><header class="page-heading"><div><h1><?= e($title) ?></h1><?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif ?></div><?php if ($actions !== ''): ?><div class="actions"><?= $actions ?></div><?php endif ?></header><?php
}

function auth_page(string $page, string $error, bool $allowSignup): void
{
    $register = $page === 'register';
    $title = $register ? 'Create an account' : 'Sign in';
    html_start($title);
    ?>
<main class="auth-page">
    <div class="auth-card panel">
        <?= brand() ?>
        <h1><?= e($title) ?></h1>
        <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif ?>
        <?php if (!$register || $allowSignup): ?>
        <form method="post" action="<?= e(url($page)) ?>">
            <?= csrf() ?>
            <div class="form-grid form-grid-single">
                <?php field('username', 'Username', posted('username'), ['autocomplete' => 'username', 'maxlength' => '48', 'required' => true]); ?>
                <?php field('password', 'Password', '', ['type' => 'password', 'autocomplete' => $register ? 'new-password' : 'current-password', 'required' => true], $register ? 'Use at least 10 characters.' : ''); ?>
            </div>
            <div class="form-actions"><button class="button button-primary" type="submit"><?= e($title) ?></button></div>
        </form>
        <?php else: ?><p>Account registration is closed.</p><?php endif ?>
        <?php if ($register || $allowSignup): ?><p class="auth-switch"><a href="<?= e(url($register ? 'login' : 'register')) ?>"><?= $register ? 'Back to sign in' : 'Create an account' ?></a></p><?php endif ?>
    </div>
</main>
</body></html>
<?php
}

function message_page(string $title, string $message): void
{
    html_start($title);
    ?>
<main class="auth-page"><div class="auth-card panel"><?= brand() ?><h1><?= e($title) ?></h1><p><?= e($message) ?></p><?= action_link('Open Fuel Log', 'dashboard') ?></div></main></body></html>
<?php
}

function attrs(array $attributes): string
{
    $html = '';
    foreach ($attributes as $key => $value) {
        $html .= $value === true ? ' ' . $key : ' ' . $key . '="' . e($value) . '"';
    }
    return $html;
}

function posted(string $key, string $default = ''): string
{
    return input($_POST, $key, $default);
}

function field(string $name, string $label, string $value, array $attributes = [], string $help = ''): void
{
    $helpId = $name . '-help';
    ?>
<div class="form-field">
    <label for="<?= e($name) ?>"><?= e($label) ?></label>
    <input id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"<?= attrs($attributes + ($help !== '' ? ['aria-describedby' => $helpId] : [])) ?>>
    <?php if ($help !== ''): ?><p class="field-help" id="<?= e($helpId) ?>"><?= e($help) ?></p><?php endif ?>
</div>
<?php
}

function select_field(string $name, string $label, string $value, array $options, array $attributes = [], string $help = ''): void
{
    $helpId = $name . '-help';
    ?>
<div class="form-field">
    <label for="<?= e($name) ?>"><?= e($label) ?></label>
    <select id="<?= e($name) ?>" name="<?= e($name) ?>"<?= attrs($attributes + ($help !== '' ? ['aria-describedby' => $helpId] : [])) ?>>
        <?php foreach ($options as $key => $option):
            $label = is_array($option) ? $option[0] : $option;
            $optionAttributes = is_array($option) ? $option[1] : [];
            ?><option value="<?= e($key) ?>"<?= (string) $key === $value ? ' selected' : '' ?><?= attrs($optionAttributes) ?>><?= e($label) ?></option>
        <?php endforeach ?>
    </select>
    <?php if ($help !== ''): ?><p class="field-help" id="<?= e($helpId) ?>"><?= e($help) ?></p><?php endif ?>
</div>
<?php
}

function decimal(float $value, int $places = 2): string
{
    return number_format($value, $places, '.', ',');
}

function display_value(array $units, string $dimension, mixed $value, int $places = 2): string
{
    if ($value === null) {
        return '—';
    }
    return decimal(fuel_from_metric((float) $value, $dimension, $units[$dimension]), $places);
}

function form_number(array $units, string $dimension, array|false $record, string $key): string
{
    if (!$record) {
        return '';
    }
    $value = fuel_from_metric((float) $record[$key], $dimension, $units[$dimension]);
    return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
}

function empty_state(string $title, string $text, string $action = '', string $glyph = 'fuel'): void
{
    ?><div class="empty-state"><span class="empty-icon"><?= icon($glyph) ?></span><h3><?= e($title) ?></h3><p><?= e($text) ?></p><?= $action ?></div><?php
}

function directory_page(string $page, array $rows, array $units): void
{
    $isCar = $page === 'cars';
    $target = $isCar ? 'car' : 'station';
    $label = $isCar ? 'vehicle' : 'station';
    $glyph = $isCar ? 'car' : 'pin';
    $add = action_link('Add ' . $label, $target, style: 'primary', glyph: 'plus');
    page_heading($isCar ? 'Vehicles' : 'Stations', $isCar ? 'Tank capacities and vehicles in your garage.' : 'Save the stations where you refuel.', $add);
    ?>
<section class="panel">
    <?php if (!$rows): ?>
        <?php empty_state($isCar ? 'Your garage is empty' : 'No stations saved', $isCar ? 'Add your first vehicle and its tank capacity.' : 'Add a station to select it when you log a fill-up.', $add, $glyph); ?>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th scope="col">Name</th><th scope="col"><?= $isCar ? 'Tank capacity' : 'Location' ?></th><th scope="col">Actions</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
        <tr><th scope="row"><?= e($row['name']) ?></th><td><?= $isCar ? e(display_value($units, 'volume', $row['tank_capacity_liters']) . ' ' . unit_symbol($units['volume'])) : e($row['location'] ?: '—') ?></td><td><?= action_link('Edit', $target, ['id' => $row['id']], 'quiet') ?></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>
<?php
}

function directory_form(string $page, array|false $row, array $units): void
{
    $isCar = $page === 'car';
    $name = $isCar ? 'vehicle' : 'station';
    $args = $row ? ['id' => $row['id']] : [];
    page_heading(($row ? 'Edit ' : 'Add ') . $name);
    ?>
<form class="panel form-panel" method="post" action="<?= e(url($page, $args)) ?>">
    <?= csrf() ?>
    <div class="form-grid form-grid-single">
        <?php field('name', ucfirst($name) . ' name', posted('name', $row['name'] ?? ''), ['maxlength' => '100', 'required' => true], $isCar ? 'For example, Toyota Corolla.' : 'For example, Shell.'); ?>
        <?php if ($isCar): ?>
            <?php field('capacity', 'Tank capacity (' . unit_symbol($units['volume']) . ')', posted('capacity', form_number($units, 'volume', $row, 'tank_capacity_liters')), ['type' => 'number', 'min' => '0.000001', 'step' => 'any', 'required' => true]); ?>
        <?php else: ?>
            <?php field('location', 'Location (optional)', posted('location', $row['location'] ?? ''), ['maxlength' => '150'], 'Add a street or town to distinguish stations with the same name.'); ?>
        <?php endif ?>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit">Save <?= e($name) ?></button><?= action_link('Cancel', $isCar ? 'cars' : 'stations') ?></div>
</form>
<?php
}

function fill_form(array|false $row, array $user, array $cars, array $stations): void
{
    $units = units_for($user);
    $volumeUnit = unit_symbol($units['volume']);
    $priceUnit = $user['currency'] . '/' . unit_symbol($units['price']);
    $args = $row ? ['id' => $row['id']] : [];
    page_heading($row ? 'Edit fill-up' : 'New fill-up');
    if (!$cars || !$stations) {
        $actions = (!$cars ? action_link('Add vehicle', 'car', style: 'primary', glyph: 'plus') : '')
            . (!$stations ? action_link('Add station', 'station', style: 'primary', glyph: 'plus') : '');
        ?><section class="panel"><?php empty_state('Set up your fill-up log', 'Add a vehicle and a station before logging fuel.', '<div class="actions">' . $actions . '</div>'); ?></section><?php
        return;
    }
    $carOptions = ['' => 'Select a vehicle'];
    foreach ($cars as $car) {
        $capacity = $row && $row['car_id'] == $car['id'] ? $row['tank_capacity_liters'] : $car['tank_capacity_liters'];
        $carOptions[$car['id']] = [$car['name'], ['data-capacity-liters' => $capacity]];
    }
    $stationOptions = ['' => 'Select a station'];
    foreach ($stations as $station) {
        $stationOptions[$station['id']] = $station['name'] . ($station['location'] ? ' · ' . $station['location'] : '');
    }
    $numeric = ['type' => 'number', 'step' => 'any', 'required' => true];
    ?>
<form class="content-grid entry-grid" method="post" action="<?= e(url('fill', $args)) ?>" data-fill-form
    data-volume-factor="<?= e(fuel_to_metric(1, 'volume', $units['volume'])) ?>"
    data-price-factor="<?= e(fuel_to_metric(1, 'volume', $units['price'])) ?>"
    data-volume-unit="<?= e($volumeUnit) ?>" data-currency="<?= e($user['currency']) ?>">
    <?= csrf() ?>
    <section class="panel form-panel">
        <div class="panel-header"><h2>Fill-up details</h2></div>
        <div class="form-grid">
            <?php select_field('car_id', 'Vehicle', posted('car_id', (string) ($row['car_id'] ?? (count($cars) === 1 ? $cars[0]['id'] : ''))), $carOptions, ['data-fill-car' => true, 'required' => true]); ?>
            <?php select_field('station_id', 'Station', posted('station_id', (string) ($row['station_id'] ?? (count($stations) === 1 ? $stations[0]['id'] : ''))), $stationOptions, ['required' => true]); ?>
            <?php field('filled_at', 'Date', posted('filled_at', $row['filled_at'] ?? date('Y-m-d')), ['type' => 'date', 'required' => true]); ?>
            <?php field('odometer', 'Odometer (' . unit_symbol($units['distance']) . ')', posted('odometer', form_number($units, 'distance', $row, 'odometer_km')), $numeric + ['min' => '0']); ?>
            <?php field('volume', 'Fuel added (' . $volumeUnit . ')', posted('volume', form_number($units, 'volume', $row, 'liters')), $numeric + ['min' => '0.000001', 'data-fill-volume' => true]); ?>
            <?php field('price', 'Price per unit (' . $priceUnit . ')', posted('price', form_number($units, 'price', $row, 'price_per_liter')), $numeric + ['min' => '0', 'data-fill-price' => true]); ?>
            <?php field('tank_level', 'Tank level before filling (%)', posted('tank_level', $row['tank_before_percent'] ?? '0'), ['type' => 'number', 'min' => '0', 'max' => '100', 'step' => '0.01', 'data-tank-level' => true], 'Leave at 0% if the tank was empty. This estimate is used to calculate fuel economy between fill-ups.'); ?>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit">Save fill-up</button><?= action_link('Cancel', 'history') ?><?php if ($row): ?><?= action_link('Delete', 'delete-fill', $args, 'danger') ?><?php endif ?></div>
    </section>
    <aside class="panel form-panel" aria-label="Fill-up estimates">
        <div class="panel-header"><h2>Fill-up estimates</h2></div>
        <div class="estimate-grid" aria-live="polite">
            <div><p class="stat-label">Amount paid</p><output class="stat-value" data-paid-preview><?= $row ? e(decimal((float) $row['total_paid']) . ' ' . $user['currency']) : '—' ?></output></div>
            <div><p class="stat-label">Fuel before filling</p><output class="stat-value" data-tank-preview><?= $row ? e(display_value($units, 'volume', $row['tank_before_liters']) . ' ' . $volumeUnit) : '—' ?></output></div>
            <div><p class="stat-label">Fuel after filling</p><output class="stat-value" data-after-preview>—</output></div>
        </div>
        <p class="field-help">Fuel estimates use the selected vehicle’s tank capacity and the estimated starting level.</p>
    </aside>
</form>
<?php
}

function delete_form(array $row): void
{
    page_heading('Delete fill-up');
    ?>
<form class="panel form-panel" method="post" action="<?= e(url('delete-fill', ['id' => $row['id']])) ?>">
    <?= csrf() ?>
    <p>Delete the fill-up recorded on <strong><?= e($row['filled_at']) ?></strong>?</p>
    <div class="form-actions"><button class="button button-danger" type="submit">Delete fill-up</button><?= action_link('Cancel', 'fill', ['id' => $row['id']]) ?></div>
</form>
<?php
}

function settings_page(array $user): void
{
    $presets = fuel_unit_presets();
    $names = ['distance' => 'Distance and odometer', 'volume' => 'Fuel volume and tank capacity', 'economy' => 'Fuel economy', 'price' => 'Price per fuel unit'];
    page_heading('Preferences', 'Choose a preset or set each measurement individually.');
    ?>
<form class="panel form-panel preferences-form" method="post" action="<?= e(url('settings')) ?>">
    <?= csrf() ?>
    <div class="form-grid">
        <?php select_field('preset', 'Unit preset', posted('preset', $user['unit_preset']), ['metric' => 'Metric', 'us' => 'US imperial', 'uk' => 'UK imperial', 'custom' => 'Custom'], ['data-unit-preset' => true]); ?>
        <?php field('currency', 'Currency code', posted('currency', $user['currency']), ['maxlength' => '3', 'minlength' => '3', 'required' => true], 'For example, PLN, EUR or USD. Changing the code relabels your recorded amounts.'); ?>
        <?php foreach (fuel_unit_options() as $dimension => $options):
            $labels = $dimension === 'price' ? array_map(static fn(string $unit): string => 'Per ' . $unit, $options) : $options;
            select_field($dimension, $names[$dimension], posted($dimension, $user['unit_' . $dimension]), $labels, [
                'data-unit-preference' => true,
                'data-metric' => $presets['metric'][$dimension],
                'data-us' => $presets['us'][$dimension],
                'data-uk' => $presets['uk'][$dimension],
            ]);
        endforeach ?>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit">Save preferences</button></div>
</form>
<?php
}

function report_page(PDO $pdo, array $user, array $cars, string $page): void
{
    $dashboard = $page === 'dashboard';
    $carOptions = ['' => 'All vehicles'];
    foreach ($cars as $car) {
        $carOptions[$car['id']] = $car['name'];
    }
    $carId = id_value($_GET, 'car_id');
    if (!isset($carOptions[$carId])) {
        $carId = 0;
    }
    $from = input($_GET, 'from');
    $to = input($_GET, 'to');
    $today = new DateTimeImmutable('today');
    $periods = [
        'lifetime' => ['Lifetime', '', ''],
        'month' => ['Last 30 days', $today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d')],
        'quarter' => ['Last 90 days', $today->modify('-89 days')->format('Y-m-d'), $today->format('Y-m-d')],
        'year' => ['This year', $today->format('Y-01-01'), $today->format('Y-m-d')],
    ];
    $period = input($_GET, 'period', $from || $to ? 'custom' : 'lifetime');
    if (isset($periods[$period])) {
        [, $from, $to] = $periods[$period];
    }
    $invalidDates = ($from !== '' && !valid_date($from)) || ($to !== '' && !valid_date($to))
        || ($from !== '' && $to !== '' && $from > $to);
    $rows = $invalidDates ? [] : fill_rows($pdo, (int) $user['id'], $carId);
    $stats = fuel_telemetry($rows, $from ?: null, $to ?: null);
    $add = action_link('New fill-up', 'fill', style: 'primary', glyph: 'plus');
    page_heading($dashboard ? 'Dashboard' : 'Fill-up history',
        $dashboard ? 'Fuel economy, spending and mileage for your vehicles.' : '', $add);
    ?>
<section class="panel filters-panel" aria-label="Filters">
    <div class="range-links" aria-label="Timeframe">
        <?php foreach ($periods as $key => [$label]): ?>
            <a class="range-link" href="<?= e(url($page, ['period' => $key] + ($carId ? ['car_id' => $carId] : []))) ?>"<?= $key === $period ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach ?>
    </div>
    <form class="filter-bar" method="get" action="/">
        <input type="hidden" name="page" value="<?= e($page) ?>">
        <?php select_field('car_id', 'Vehicle', $carId ? (string) $carId : '', $carOptions); ?>
        <?php field('from', 'From', $from, ['type' => 'date']); ?>
        <?php field('to', 'To', $to, ['type' => 'date']); ?>
        <button class="button button-secondary" type="submit">Apply filters</button>
    </form>
</section>
<?php if ($invalidDates): ?>
    <div class="notice notice-error" role="alert">Enter a valid date range with the start on or before the end.</div>
<?php elseif ($dashboard): ?>
    <?php dashboard_metrics($stats, $user); ?>
    <div class="content-grid report-grid">
        <section class="panel">
            <div class="panel-header"><h2>Fuel economy</h2></div>
            <?php economy_chart($stats['in_range'], units_for($user)); ?>
        </section>
        <section class="panel">
            <div class="panel-header"><h2>Recent fill-ups</h2><?= action_link('View all', 'history', array_filter(['car_id' => $carId, 'from' => $from, 'to' => $to]), 'quiet', 'arrow') ?></div>
            <?php recent_fills(array_slice(array_reverse($stats['in_range']), 0, 5), $user, $rows ? '' : $add); ?>
        </section>
    </div>
<?php else: ?>
    <section class="panel">
        <?php history_table(array_reverse($stats['in_range']), $user, $rows ? '' : $add); ?>
    </section>
<?php endif;
}

function dashboard_metrics(array $stats, array $user): void
{
    $units = units_for($user);
    $volume = unit_symbol($units['volume']);
    $distance = unit_symbol($units['distance']);
    $economy = unit_symbol($units['economy']);
    $currency = $user['currency'];
    $costPerDistance = $stats['cost_per_km'] === null ? '—'
        : decimal($stats['cost_per_km'] * fuel_to_metric(1, 'distance', $units['distance']));
    $cards = [
        ['Fuel added', display_value($units, 'volume', $stats['total_liters']), $volume, 'fuel'],
        ['Total spent', decimal($stats['total_paid']), $currency, 'money'],
        ['Distance driven', display_value($units, 'distance', $stats['distance_km'], 1), $distance, 'distance'],
        ['Average fuel economy', display_value($units, 'economy', $stats['avg_consumption'], 1), $economy, 'trend'],
        ['Most efficient', display_value($units, 'economy', $stats['best_consumption'], 1), $economy, 'trend'],
        ['Least efficient', display_value($units, 'economy', $stats['worst_consumption'], 1), $economy, 'trend'],
        ['Time between fill-ups', $stats['avg_days'] === null ? '—' : decimal($stats['avg_days'], 1), 'days avg.', 'clock'],
        ['Fill-ups', (string) $stats['fill_count'], '', 'history'],
        ['Average fuel price', display_value($units, 'price', $stats['avg_price_per_liter'], 3), $currency . '/' . unit_symbol($units['price']), 'money'],
        ['Cost per distance', $costPerDistance, $currency . '/' . $distance, 'distance'],
    ];
    ?>
<section class="stats-grid" aria-label="Fill-up statistics">
    <?php foreach ($cards as [$label, $value, $unit, $glyph]): ?>
    <article class="stat-card">
        <div class="stat-top"><h2 class="stat-label"><?= e($label) ?></h2><span class="stat-icon"><?= icon($glyph) ?></span></div>
        <div class="stat-value"><?= e($value) ?><?php if ($unit !== '' && $value !== '—'): ?> <span class="stat-unit"><?= e($unit) ?></span><?php endif ?></div>
    </article>
    <?php endforeach ?>
</section>
<?php
}

function date_label(string $date, string $format = 'j M Y'): string
{
    return (new DateTimeImmutable($date))->format($format);
}

function recent_fills(array $rows, array $user, string $action): void
{
    if (!$rows) {
        empty_state('No fill-ups in this view', 'Recorded visits will appear here.', $action, 'history');
        return;
    }
    $units = units_for($user);
    ?>
<ul class="recent-list">
    <?php foreach ($rows as $row): ?>
    <li><a class="recent-row" href="<?= e(url('fill', ['id' => $row['id']])) ?>">
        <span class="recent-icon"><?= icon('fuel') ?></span>
        <span class="recent-info"><strong><?= e($row['station_name']) ?></strong><small><?= e($row['car_name'] . ' · ' . date_label($row['filled_at'])) ?></small></span>
        <span class="recent-amount"><strong><?= e(decimal((float) $row['total_paid']) . ' ' . $user['currency']) ?></strong><small><?= e(display_value($units, 'volume', $row['liters']) . ' ' . unit_symbol($units['volume'])) ?></small></span>
    </a></li>
    <?php endforeach ?>
</ul>
<?php
}

function history_table(array $rows, array $user, string $action): void
{
    if (!$rows) {
        empty_state('No fill-ups in this view', 'Log a fill-up or adjust your filters.', $action, 'history');
        return;
    }
    $units = units_for($user);
    $volume = unit_symbol($units['volume']);
    $currency = $user['currency'];
    ?>
<div class="table-wrap"><table>
<thead><tr>
    <th scope="col">Date</th><th scope="col">Vehicle / station</th>
    <th scope="col">Fuel (<?= e($volume) ?>)</th><th scope="col">Price (<?= e($currency . '/' . unit_symbol($units['price'])) ?>)</th>
    <th scope="col">Paid (<?= e($currency) ?>)</th><th scope="col">Odometer (<?= e(unit_symbol($units['distance'])) ?>)</th>
    <th scope="col">Estimated economy (<?= e(unit_symbol($units['economy'])) ?>)</th><th scope="col">Tank before (<?= e($volume) ?>)</th><th scope="col">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
    <th scope="row"><time datetime="<?= e($row['filled_at']) ?>"><?= e(date_label($row['filled_at'])) ?></time></th>
    <td><strong><?= e($row['car_name']) ?></strong><small><?= e($row['station_name']) ?></small></td>
    <td><?= e(display_value($units, 'volume', $row['liters'])) ?></td>
    <td><?= e(display_value($units, 'price', $row['price_per_liter'], 3)) ?></td>
    <td><?= e(decimal((float) $row['total_paid'])) ?></td>
    <td><?= e(display_value($units, 'distance', $row['odometer_km'], 1)) ?></td>
    <td><?= e(display_value($units, 'economy', $row['calculated_consumption_l_100km'], 1)) ?></td>
    <td><?= e(display_value($units, 'volume', $row['tank_before_liters']) . ' (' . rtrim(rtrim($row['tank_before_percent'], '0'), '.') . '%)') ?></td>
    <td><?= action_link('Edit', 'fill', ['id' => $row['id']], 'quiet') ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table></div>
<?php
}

function economy_chart(array $rows, array $units): void
{
    $rows = array_values(array_filter($rows, static fn(array $row): bool => $row['calculated_consumption_l_100km'] !== null));
    if (!$rows) {
        empty_state('No fuel economy estimates in this view', 'An estimate needs two fill-ups for the same vehicle with a positive distance and fuel used between them.', glyph: 'trend');
        return;
    }
    $count = count($rows);
    $rows = array_slice($rows, -120);
    $points = array_map(static fn(array $row): float => fuel_from_metric($row['calculated_consumption_l_100km'], 'economy', $units['economy']), $rows);
    $unit = unit_symbol($units['economy']);
    $min = min($points);
    $max = max($points);
    $margin = max(($max - $min) * 0.12, $max * 0.025);
    $low = max(0, $min - $margin);
    $high = $max + $margin;
    $start = strtotime($rows[0]['filled_at'] . ' UTC');
    $end = strtotime($rows[array_key_last($rows)]['filled_at'] . ' UTC');
    $series = [];
    $dots = [];
    foreach ($rows as $index => $row) {
        $time = strtotime($row['filled_at'] . ' UTC');
        $x = $start === $end ? 362 : round(66 + 592 * ($time - $start) / ($end - $start), 1);
        $y = round(162 - 140 * ($points[$index] - $low) / ($high - $low), 1);
        $series[$row['car_id']][] = $x . ',' . $y;
        $dots[] = [$x, $y, $row['car_name'] . ' · ' . $row['filled_at'] . ' · ' . decimal($points[$index], 2) . ' ' . $unit];
    }
    ?>
<div class="chart-canvas">
    <p class="chart-caption"><?= e($unit) ?> · Estimated between fill-ups<?= $count > count($rows) ? ' · Last 120 estimates' : '' ?></p>
    <svg class="chart" viewBox="0 0 690 208" role="img" aria-label="<?= e('Fuel economy over time in ' . $unit . ', from ' . decimal($min, 2) . ' to ' . decimal($max, 2)) ?>">
        <?php for ($i = 0; $i <= 4; $i++): $y = 22 + 35 * $i; ?>
            <line class="chart-gridline" x1="66" x2="658" y1="<?= $y ?>" y2="<?= $y ?>"/>
            <text class="chart-label" x="57" y="<?= $y + 4 ?>" text-anchor="end"><?= e(decimal($high - ($high - $low) * $i / 4, $high < 3 ? 2 : 1)) ?></text>
        <?php endfor ?>
        <?php foreach ($series as $line): ?>
            <polyline class="chart-line" points="<?= e(implode(' ', $line)) ?>"/>
        <?php endforeach ?>
        <?php foreach ($dots as [$x, $y, $label]): ?>
            <circle class="chart-point" cx="<?= e($x) ?>" cy="<?= e($y) ?>" r="3.7"><title><?= e($label) ?></title></circle>
        <?php endforeach ?>
        <text class="chart-label" x="<?= $start === $end ? 362 : 66 ?>" y="192"<?= $start === $end ? ' text-anchor="middle"' : '' ?>><?= e(gmdate('j M Y', $start)) ?></text>
        <?php if ($start !== $end): ?><text class="chart-label" x="658" y="192" text-anchor="end"><?= e(gmdate('j M Y', $end)) ?></text><?php endif ?>
    </svg>
</div>
<?php
}
