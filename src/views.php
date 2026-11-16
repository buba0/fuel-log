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

function html_start(string $title, string $theme = 'system'): void
{
    ?>
<!doctype html>
<html lang="<?= e(language()) ?>" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t($title)) ?> · Fuel Log</title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="stylesheet" href="/assets/app.css?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../public/assets/app.css'), 0, 12)) ?>">
    <script defer src="/assets/app.js?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../public/assets/app.js'), 0, 12)) ?>"></script>
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
        . ($glyph ? icon($glyph) : '') . e(t($label)) . '</a>';
}

function layout_start(array $user, string $page, string $title, string $notice, string $error): void
{
    $navigation = [
        ['dashboard', 'Dashboard', 'dashboard', ['dashboard']],
        ['history', 'Fill-ups', 'history', ['history', 'fill', 'delete-fill']],
        ['cars', 'Vehicles', 'car', ['cars', 'car']],
        ['stations', 'Stations', 'pin', ['stations', 'station']],
        ['prices', 'Fuel prices', 'money', ['prices']],
        ['settings', 'Preferences', 'settings', ['settings']],
    ];
    html_start($title, $user['theme'] ?? 'system');
    ?>
<div class="app-shell">
    <aside class="sidebar" data-menu>
        <div class="sidebar-header">
            <?= brand() ?>
            <button type="button" class="menu-toggle" data-menu-toggle aria-label="<?= e(t('Toggle navigation')) ?>" aria-controls="navigation" aria-expanded="false" hidden><?= icon('menu') ?></button>
        </div>
        <nav id="navigation" class="nav-list" aria-label="<?= e(t('Main navigation')) ?>">
            <?php foreach ($navigation as [$target, $label, $glyph, $pages]): ?>
                <a class="nav-link" href="<?= e(url($target)) ?>"<?= in_array($page, $pages, true) ? ' aria-current="page"' : '' ?>><?= icon($glyph) ?><span><?= e(t($label)) ?></span></a>
            <?php endforeach ?>
        </nav>
        <div class="sidebar-footer">
            <span class="user-avatar" aria-hidden="true"><?= e(strtoupper($user['username'][0])) ?></span>
            <strong class="user-name" title="<?= e($user['username']) ?>"><?= e($user['username']) ?></strong>
            <form method="post" action="<?= e(url('logout')) ?>"><?= csrf() ?><button class="signout" type="submit" aria-label="<?= e(t('Sign out')) ?>" title="<?= e(t('Sign out')) ?>"><?= icon('logout') ?></button></form>
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
    ?><header class="page-heading"><div><h1><?= e(t($title)) ?></h1><?php if ($subtitle !== ''): ?><p><?= e(t($subtitle)) ?></p><?php endif ?></div><?php if ($actions !== ''): ?><div class="actions"><?= $actions ?></div><?php endif ?></header><?php
}

function auth_page(string $page, string $error, bool $allowSignup): void
{
    $register = $page === 'register';
    $title = $register ? 'Create an account' : 'Sign in';
    $otherLanguage = language() === 'pl' ? 'en' : 'pl';
    html_start($title);
    ?>
<main class="auth-page">
    <div class="auth-card panel">
        <?= brand() ?>
        <h1><?= e(t($title)) ?></h1>
        <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif ?>
        <?php if (!$register || $allowSignup): ?>
        <form method="post" action="<?= e(url($page)) ?>">
            <?= csrf() ?>
            <div class="form-grid form-grid-single">
                <?php field('username', 'Username', posted('username'), ['autocomplete' => 'username', 'maxlength' => '48', 'required' => true]); ?>
                <?php field('password', 'Password', '', ['type' => 'password', 'autocomplete' => $register ? 'new-password' : 'current-password', 'required' => true], $register ? 'Use at least 10 characters.' : ''); ?>
            </div>
            <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t($title)) ?></button></div>
        </form>
        <?php else: ?><p><?= e(t('Account registration is closed.')) ?></p><?php endif ?>
        <?php if ($register || $allowSignup): ?><p class="auth-switch"><a href="<?= e(url($register ? 'login' : 'register')) ?>"><?= e(t($register ? 'Back to sign in' : 'Create an account')) ?></a></p><?php endif ?>
        <p class="auth-switch"><a href="<?= e(url($page, ['lang' => $otherLanguage])) ?>" lang="<?= e($otherLanguage) ?>" hreflang="<?= e($otherLanguage) ?>"><?= $otherLanguage === 'pl' ? 'Polski' : 'English' ?></a></p>
    </div>
</main>
</body></html>
<?php
}

function message_page(string $title, string $message): void
{
    html_start($title);
    ?>
<main class="auth-page"><div class="auth-card panel"><?= brand() ?><h1><?= e(t($title)) ?></h1><p><?= e(t($message)) ?></p><?= action_link('Open Fuel Log', 'dashboard') ?></div></main></body></html>
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
    <label for="<?= e($name) ?>"><?= e(t($label)) ?></label>
    <input id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"<?= attrs($attributes + ($help !== '' ? ['aria-describedby' => $helpId] : [])) ?>>
    <?php if ($help !== ''): ?><p class="field-help" id="<?= e($helpId) ?>"><?= e(t($help)) ?></p><?php endif ?>
</div>
<?php
}

function select_field(string $name, string $label, string $value, array $options, array $attributes = [], string $help = ''): void
{
    $helpId = $name . '-help';
    ?>
<div class="form-field">
    <label for="<?= e($name) ?>"><?= e(t($label)) ?></label>
    <select id="<?= e($name) ?>" name="<?= e($name) ?>"<?= attrs($attributes + ($help !== '' ? ['aria-describedby' => $helpId] : [])) ?>>
        <?php foreach ($options as $key => $option):
            $label = is_array($option) ? $option[0] : $option;
            $optionAttributes = is_array($option) ? $option[1] : [];
            ?><option value="<?= e($key) ?>"<?= (string) $key === $value ? ' selected' : '' ?><?= attrs($optionAttributes) ?>><?= e($label) ?></option>
        <?php endforeach ?>
    </select>
    <?php if ($help !== ''): ?><p class="field-help" id="<?= e($helpId) ?>"><?= e(t($help)) ?></p><?php endif ?>
</div>
<?php
}

function unit_label(string $unit): string
{
    return t(unit_symbol($unit));
}

function display_value(array $units, string $dimension, mixed $value, int $places = 2): string
{
    if ($value === null) {
        return '—';
    }
    return localized_decimal(fuel_from_metric((float) $value, $dimension, $units[$dimension]), $places);
}

function form_number(array $units, string $dimension, array|false $record, string $key): string
{
    if (!isset($record[$key])) {
        return '';
    }
    $value = fuel_from_metric((float) $record[$key], $dimension, $units[$dimension]);
    return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
}

function empty_state(string $title, string $text, string $action = '', string $glyph = 'fuel'): void
{
    ?><div class="empty-state"><span class="empty-icon"><?= icon($glyph) ?></span><h3><?= e(t($title)) ?></h3><p><?= e(t($text)) ?></p><?= $action ?></div><?php
}

function directory_page(string $page, array $rows, array $units): void
{
    $isCar = $page === 'cars';
    $target = $isCar ? 'car' : 'station';
    $glyph = $isCar ? 'car' : 'pin';
    $add = action_link($isCar ? 'Add vehicle' : 'Add station', $target, style: 'primary', glyph: 'plus');
    page_heading($isCar ? 'Vehicles' : 'Stations', $isCar ? 'Tank capacities and vehicles in your garage.' : 'Save the stations where you refuel.', $add);
    ?>
<section class="panel">
    <?php if (!$rows): ?>
        <?php empty_state($isCar ? 'Your garage is empty' : 'No stations saved', $isCar ? 'Add your first vehicle and its tank capacity.' : 'Add a station to select it when you log a fill-up.', $add, $glyph); ?>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th scope="col"><?= e(t('Name')) ?></th><th scope="col"><?= e(t($isCar ? 'Tank capacity' : 'Location')) ?></th><th scope="col"><?= e(t('Actions')) ?></th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
        <tr><th scope="row"><?= e($row['name']) ?><?php if ($isCar): ?><?= vehicle_summary($row) ?><?php endif ?></th><td><?= $isCar ? e(display_value($units, 'volume', $row['tank_capacity_liters']) . ' ' . unit_label($units['volume'])) : e($row['location'] ?: '—') ?></td><td><?= action_link('Edit', $target, ['id' => $row['id']], 'quiet') ?></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>
<?php
}

function vehicle_summary(array $row): string
{
    $model = trim(implode(' ', [$row['make'] ?? '', $row['model'] ?? '', $row['model_year'] ?? '']));
    $details = array_filter([$model, $row['engine'] ?? '', $row['registration'] ?? '']);
    return $details ? '<small class="vehicle-specs">' . e(implode(' · ', $details)) . '</small>' : '';
}

function directory_form(string $page, array|false $row, array $units): void
{
    $isCar = $page === 'car';
    $args = $row ? ['id' => $row['id']] : [];
    $vehicleTypes = ['car' => 'Car', 'moped' => 'Moped', 'motorcycle' => 'Motorcycle', 'truck' => 'Truck', 'van' => 'Van', 'other' => 'Other'];
    $fuelTypes = ['petrol' => 'Petrol', 'diesel' => 'Diesel', 'lpg' => 'LPG', 'hybrid' => 'Hybrid', 'electric' => 'Electric', 'other' => 'Other'];
    page_heading($isCar ? ($row ? 'Edit vehicle' : 'Add vehicle') : ($row ? 'Edit station' : 'Add station'));
    ?>
<form class="panel form-panel" method="post" action="<?= e(url($page, $args)) ?>">
    <?= csrf() ?>
    <div class="form-grid<?= $isCar ? '' : ' form-grid-single' ?>">
        <?php field('name', $isCar ? 'Vehicle name' : 'Station name', posted('name', $row['name'] ?? ''), ['maxlength' => '100', 'required' => true], $isCar ? 'For example, Toyota Corolla.' : 'For example, Shell.'); ?>
        <?php if ($isCar): ?>
            <label><input type="checkbox" name="fuel_metering" value="1"<?= isset($_POST['fuel_metering']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($row['fuel_metering'])) ? ' checked' : '' ?>> <?= e(t('Supports fuel economy metering')) ?></label>
            <?php field('capacity', t('Tank capacity ({unit})', ['unit' => unit_label($units['volume'])]), posted('capacity', form_number($units, 'volume', $row, 'tank_capacity_liters')), ['type' => 'number', 'min' => '0.000001', 'step' => 'any', 'required' => true]); ?>
            <?php select_field('vehicle_type', 'Vehicle type', posted('vehicle_type', $row['vehicle_type'] ?? 'car'), array_map('t', $vehicleTypes)); ?>
            <?php select_field('fuel_type', 'Fuel type', posted('fuel_type', $row['fuel_type'] ?? 'other'), array_map('t', $fuelTypes)); ?>
            <?php field('make', 'Make (optional)', posted('make', $row['make'] ?? ''), ['maxlength' => '60']); ?>
            <?php field('model', 'Model (optional)', posted('model', $row['model'] ?? ''), ['maxlength' => '60']); ?>
            <?php field('model_year', 'Model year (optional)', posted('model_year', (string) ($row['model_year'] ?? '')), ['type' => 'number', 'step' => '1', 'min' => '1886', 'max' => (string) ((int) date('Y') + 2)]); ?>
            <?php field('engine', 'Engine (optional)', posted('engine', $row['engine'] ?? ''), ['maxlength' => '100']); ?>
            <?php field('registration', 'Registration (optional)', posted('registration', $row['registration'] ?? ''), ['maxlength' => '30']); ?>
        <?php else: ?>
            <?php field('location', 'Location (optional)', posted('location', $row['location'] ?? ''), ['maxlength' => '150'], 'Add a street or town to distinguish stations with the same name.'); ?>
        <?php endif ?>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t($isCar ? 'Save vehicle' : 'Save station')) ?></button><?= action_link('Cancel', $isCar ? 'cars' : 'stations') ?></div>
</form>
<?php
}

function fill_form(array|false $row, array $user, array $cars, array $stations, array $defaults = []): void
{
    $units = units_for($user);
    $volumeUnit = unit_label($units['volume']);
    $priceUnit = $user['currency'] . '/' . unit_label($units['price']);
    $args = $row ? ['id' => $row['id']] : [];
    $defaults['car_id'] = $user['default_car_id'] ?: ($defaults['car_id'] ?? null);
    $defaults['station_id'] = $user['default_station_id'] ?: ($defaults['station_id'] ?? null);
    $prefill = $row ?: $defaults;
    page_heading($row ? 'Edit fill-up' : 'New fill-up');
    if (!$cars || !$stations) {
        $actions = (!$cars ? action_link('Add vehicle', 'car', style: 'primary', glyph: 'plus') : '')
            . (!$stations ? action_link('Add station', 'station', style: 'primary', glyph: 'plus') : '');
        ?><section class="panel"><?php empty_state('Set up your fill-up log', 'Add a vehicle and a station before logging fuel.', '<div class="actions">' . $actions . '</div>'); ?></section><?php
        return;
    }
    $carOptions = ['' => t('Select a vehicle')];
    foreach ($cars as $car) {
        $capacity = $row && $row['car_id'] == $car['id'] ? $row['tank_capacity_liters'] : $car['tank_capacity_liters'];
        $carOptions[$car['id']] = [$car['name'], ['data-capacity-liters' => $capacity, 'data-metering' => (int) ($car['fuel_metering'] ?? 0),
            'data-history' => json_encode(array_map(static fn(array $r): array => ['date' => $r['filled_at'], 'km' => (float) $r['odometer_km']], $car['prediction_rows'] ?? []))]];
    }
    $stationOptions = ['' => t('Select a station')];
    foreach ($stations as $station) {
        $stationOptions[$station['id']] = $station['name'] . ($station['location'] ? ' · ' . $station['location'] : '');
    }
    $numeric = ['type' => 'number', 'step' => 'any', 'required' => true];
    ?>
<form class="content-grid entry-grid" method="post" action="<?= e(url('fill', $args)) ?>" data-fill-form
    data-volume-factor="<?= e(fuel_to_metric(1, 'volume', $units['volume'])) ?>"
    data-price-factor="<?= e(fuel_to_metric(1, 'volume', $units['price'])) ?>"
    data-distance-factor="<?= e(fuel_to_metric(1, 'distance', $units['distance'])) ?>" data-volume-unit="<?= e($volumeUnit) ?>" data-currency="<?= e($user['currency']) ?>">
    <?= csrf() ?>
    <section class="panel form-panel">
        <div class="panel-header"><h2><?= e(t('Fill-up details')) ?></h2></div>
        <div class="form-grid">
            <?php select_field('car_id', 'Vehicle', posted('car_id', (string) ($row['car_id'] ?? $defaults['car_id'] ?? (count($cars) === 1 ? $cars[0]['id'] : ''))), $carOptions, ['data-fill-car' => true, 'required' => true]); ?>
            <?php select_field('station_id', 'Station', posted('station_id', (string) ($row['station_id'] ?? $defaults['station_id'] ?? (count($stations) === 1 ? $stations[0]['id'] : ''))), $stationOptions, ['required' => true]); ?>
            <?php field('filled_at', 'Date', posted('filled_at', $row['filled_at'] ?? date('Y-m-d')), ['type' => 'date', 'required' => true]); ?>
            <?php field('odometer', t('Odometer ({unit})', ['unit' => unit_label($units['distance'])]), posted('odometer', form_number($units, 'distance', $prefill, 'odometer_km')), $numeric + ['min' => '0']); ?>
            <div class="form-field"><button class="button" type="button" data-predict><?= e(t('Estimate odometer')) ?></button><span class="field-help" data-prediction-status><?= e(t('Uses previous distance per day. Check the estimate before saving.')) ?></span><input type="hidden" name="estimated_odometer" value="<?= e($row['estimated_odometer'] ?? 0) ?>"></div>
            <div data-metering-field><?php field('reported_consumption', t('Vehicle-reported economy ({unit})', ['unit' => unit_label($units['economy'])]), posted('reported_consumption', $row && $row['reported_consumption'] !== null ? form_number($units, 'economy', $row, 'reported_consumption') : ''), ['type'=>'number','min'=>'0.000001','step'=>'any'], 'Optional vehicle reading'); ?></div>
            <?php field('volume', t('Fuel added ({unit})', ['unit' => $volumeUnit]), posted('volume', form_number($units, 'volume', $row, 'liters')), $numeric + ['min' => '0.000001', 'data-fill-volume' => true]); ?>
            <?php field('price', t('Price per unit ({unit})', ['unit' => $priceUnit]), posted('price', form_number($units, 'price', $prefill, 'price_per_liter')), $numeric + ['min' => '0', 'data-fill-price' => true]); ?>
            <?php field('tank_level', 'Tank level before filling (%)', posted('tank_level', $row['tank_before_percent'] ?? '0'), ['type' => 'number', 'min' => '0', 'max' => '100', 'step' => '0.01', 'data-tank-level' => true], 'Leave at 0% if the tank was empty. This estimate is used to calculate fuel economy between fill-ups.'); ?>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t('Save fill-up')) ?></button><?= action_link('Cancel', 'history') ?><?php if ($row): ?><?= action_link('Delete', 'delete-fill', $args, 'danger') ?><?php endif ?></div>
    </section>
    <aside class="panel form-panel" aria-label="<?= e(t('Fill-up estimates')) ?>">
        <div class="panel-header"><h2><?= e(t('Fill-up estimates')) ?></h2></div>
        <div class="estimate-grid" aria-live="polite">
            <div><p class="stat-label"><?= e(t('Amount paid')) ?></p><output class="stat-value" data-paid-preview><?= $row ? e(localized_decimal((float) $row['total_paid']) . ' ' . $user['currency']) : '—' ?></output></div>
            <div><p class="stat-label"><?= e(t('Fuel before filling')) ?></p><output class="stat-value" data-tank-preview><?= $row ? e(display_value($units, 'volume', $row['tank_before_liters']) . ' ' . $volumeUnit) : '—' ?></output></div>
            <div><p class="stat-label"><?= e(t('Fuel after filling')) ?></p><output class="stat-value" data-after-preview>—</output></div>
        </div>
        <p class="field-help"><?= e(t('Fuel estimates use the selected vehicle’s tank capacity and the estimated starting level.')) ?></p>
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
    <p><?= e(t('Delete the fill-up recorded on {date}?', ['date' => localized_date($row['filled_at'])])) ?></p>
    <div class="form-actions"><button class="button button-danger" type="submit"><?= e(t('Delete fill-up')) ?></button><?= action_link('Cancel', 'fill', ['id' => $row['id']]) ?></div>
</form>
<?php
}

function settings_page(array $user, array $cars = [], array $stations = []): void
{
    $presets = fuel_unit_presets();
    $defaultCars = ['' => t('Use last fill-up')] + array_column($cars, 'name', 'id');
    $defaultStations = ['' => t('Use last fill-up')] + array_column($stations, 'name', 'id');
    $names = ['distance' => 'Distance and odometer', 'volume' => 'Fuel volume and tank capacity', 'economy' => 'Fuel economy', 'price' => 'Price per fuel unit'];
    page_heading('Preferences', 'Choose a preset or set each measurement individually.');
    ?>
<form class="panel form-panel preferences-form" method="post" action="<?= e(url('settings')) ?>">
    <?= csrf() ?>
    <div class="form-grid">
        <?php select_field('default_car_id', 'Default vehicle', posted('default_car_id', (string) ($user['default_car_id'] ?? '')), $defaultCars); ?>
        <?php select_field('default_station_id', 'Default station', posted('default_station_id', (string) ($user['default_station_id'] ?? '')), $defaultStations); ?>
        <?php select_field('language', 'Language', posted('language', $user['language'] ?? 'en'), ['en' => 'English', 'pl' => 'Polski']); ?>
        <?php select_field('theme', 'Theme', posted('theme', $user['theme'] ?? 'system'), ['system' => t('System'), 'light' => t('Light'), 'dark' => t('Dark')], ['data-theme-preference' => true]); ?>
        <?php field('currency', 'Currency code', posted('currency', $user['currency']), ['maxlength' => '3', 'minlength' => '3', 'required' => true], 'For example, PLN, EUR or USD. Changing the code relabels your recorded amounts.'); ?>
        <?php select_field('preset', 'Unit preset', posted('preset', $user['unit_preset']), ['metric' => t('Metric'), 'us' => t('US imperial'), 'uk' => t('UK imperial'), 'custom' => t('Custom')], ['data-unit-preset' => true]); ?>
        <?php foreach (fuel_unit_options() as $dimension => $options):
            $labels = array_map(static fn(string $unit): string => $dimension === 'price' ? t('Per {unit}', ['unit' => t($unit)]) : t($unit), $options);
            select_field($dimension, $names[$dimension], posted($dimension, $user['unit_' . $dimension]), $labels, [
                'data-unit-preference' => true,
                'data-metric' => $presets['metric'][$dimension],
                'data-us' => $presets['us'][$dimension],
                'data-uk' => $presets['uk'][$dimension],
            ]);
        endforeach ?>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t('Save preferences')) ?></button></div>
</form>
<?php api_preferences($user); ?>
<?php
}

function api_preferences(array $user): void
{
    $token = $_SESSION['new_api_token'] ?? null;
    unset($_SESSION['new_api_token']);
    $tokenHash = $user['api_token_hash'] ?? null;
    $hasToken = is_string($tokenHash) && $tokenHash !== '';
    ?>
<section class="panel form-panel">
    <div class="panel-header"><h2><?= e(t('API access')) ?></h2></div>
    <p><?= e(t('Send the token in the Authorization header as a Bearer token.')) ?></p>
    <dl class="api-endpoints">
        <dt><?= e(t('JSON API')) ?></dt><dd><code><?= e(url('api', ['format' => 'json'])) ?></code></dd>
        <dt><?= e(t('Metrics')) ?></dt><dd><code><?= e(url('api', ['format' => 'metrics'])) ?></code></dd>
    </dl>
    <?php if ($hasToken && is_string($token) && hash_equals($tokenHash, hash('sha256', $token))): ?>
        <div class="notice" role="status"><?= e(t('Copy this token now. It is shown once.')) ?></div>
        <div class="form-grid form-grid-single"><?php field('new_api_token', 'API token', $token, ['readonly' => true, 'spellcheck' => 'false', 'autocomplete' => 'off']); ?></div>
    <?php endif ?>
    <form class="form-actions" method="post" action="<?= e(url('api-token')) ?>">
        <?= csrf() ?>
        <button class="button button-secondary" type="submit" name="action" value="create"><?= e(t($hasToken ? 'Replace API token' : 'Create API token')) ?></button>
        <?php if ($hasToken): ?><button class="button button-danger" type="submit" name="action" value="revoke"><?= e(t('Revoke API token')) ?></button><?php endif ?>
    </form>
</section>
<?php
}

function sort_fillup_history(array $rows, string $sort): array
{
    if ($sort === 'date_asc') {
        return $rows;
    }
    $rows = array_reverse($rows);
    $column = match ($sort) {
        'distance_desc' => 'calculated_distance_km',
        'economy_asc', 'economy_desc' => 'calculated_consumption_l_100km',
        'paid_desc' => 'total_paid',
        default => null,
    };
    if ($column !== null) {
        usort($rows, static function (array $left, array $right) use ($column, $sort): int {
            $a = $left[$column];
            $b = $right[$column];
            if ($a === null || $b === null) {
                return ($a === null) <=> ($b === null);
            }
            return $sort === 'economy_asc' ? (float) $a <=> (float) $b : (float) $b <=> (float) $a;
        });
    }
    return $rows;
}

function fillup_export_menu(array $filters): void
{
    ?>
<details class="export-menu">
    <summary class="button button-secondary"><?= e(t('Download fill-ups')) ?></summary>
    <div class="actions export-links">
        <?php foreach (['csv', 'xlsx', 'txt', 'docx', 'json'] as $format): ?>
            <?= action_link(strtoupper($format), 'export', ['format' => $format] + $filters, 'quiet') ?>
        <?php endforeach ?>
    </div>
</details>
<?php
}

function report_page(PDO $pdo, array $user, array $cars, string $page): void
{
    $dashboard = $page === 'dashboard';
    $carOptions = ['' => t('All vehicles')];
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
    $sortOptions = [
        'date_desc' => 'Newest first', 'date_asc' => 'Oldest first', 'distance_desc' => 'Longest distance',
        'economy_asc' => 'Most efficient first', 'economy_desc' => 'Least efficient first', 'paid_desc' => 'Highest amount paid',
    ];
    $sort = input($_GET, 'sort', 'date_desc');
    if (!isset($sortOptions[$sort])) {
        $sort = 'date_desc';
    }
    $rangeFilters = ($carId ? ['car_id' => $carId] : []) + (!$dashboard && $sort !== 'date_desc' ? ['sort' => $sort] : []);
    $add = action_link('New fill-up', 'fill', style: 'primary', glyph: 'plus');
    page_heading($dashboard ? 'Dashboard' : 'Fill-up history',
        $dashboard ? 'Fuel economy, spending and mileage for your vehicles.' : '', $add);
    ?>
<section class="panel filters-panel" aria-label="<?= e(t('Filters')) ?>">
    <div class="range-links" aria-label="<?= e(t('Timeframe')) ?>">
        <?php foreach ($periods as $key => [$label]): ?>
            <a class="range-link" href="<?= e(url($page, ['period' => $key] + $rangeFilters)) ?>"<?= $key === $period ? ' aria-current="true"' : '' ?>><?= e(t($label)) ?></a>
        <?php endforeach ?>
    </div>
    <form class="filter-bar" method="get" action="/">
        <input type="hidden" name="page" value="<?= e($page) ?>">
        <?php select_field('car_id', 'Vehicle', $carId ? (string) $carId : '', $carOptions); ?>
        <?php field('from', 'From', $from, ['type' => 'date']); ?>
        <?php field('to', 'To', $to, ['type' => 'date']); ?>
        <?php if (!$dashboard): ?><?php select_field('sort', 'Sort by', $sort, array_map('t', $sortOptions)); ?><?php endif ?>
        <button class="button button-secondary" type="submit"><?= e(t('Apply filters')) ?></button><?= action_link('Clear filters', $page) ?>
    </form>
</section>
<?php if ($invalidDates): ?>
    <div class="notice notice-error" role="alert"><?= e(t('Enter a valid date range with the start on or before the end.')) ?></div>
<?php return; endif; ?>
<?php anomaly_notices($rows, $stats['in_range'], $user); ?>
<?php if ($dashboard): ?>
    <?php dashboard_metrics($stats, $user); ?>
    <div class="content-grid report-grid">
        <section class="panel">
            <div class="panel-header"><h2><?= e(t('Fuel economy')) ?></h2></div>
            <?php economy_chart($stats['in_range'], units_for($user)); ?>
        </section>
        <section class="panel">
            <div class="panel-header"><h2><?= e(t('Recent fill-ups')) ?></h2><?= action_link('View all', 'history', array_filter(['car_id' => $carId, 'from' => $from, 'to' => $to]), 'quiet', 'arrow') ?></div>
            <?php recent_fills(array_slice(array_reverse($stats['in_range']), 0, 5), $user, $rows ? '' : $add); ?>
        </section>
    </div>
<?php else: ?>
    <section class="panel">
<div class="history-toolbar">
        <?php if ($stats['in_range']): ?><?php fillup_export_menu(array_filter(['car_id' => $carId, 'from' => $from, 'to' => $to, 'sort' => $sort])); ?><?php endif ?>
        <form method="post" action="<?= e(url('recalculate')) ?>" class="form-actions"><?= csrf() ?><button class="button"><?= e(t('Recalculate alerts')) ?></button></form>
        </div>
        <?php history_table(sort_fillup_history($stats['in_range'], $sort), $user, $rows ? '' : $add, fuel_anomalies($rows)); ?>
    </section>
<?php endif;
}

function dashboard_metrics(array $stats, array $user): void
{
    $units = units_for($user);
    $volume = unit_label($units['volume']);
    $distance = unit_label($units['distance']);
    $economy = unit_label($units['economy']);
    $currency = $user['currency'];
    $costPerDistance = $stats['cost_per_km'] === null ? '—'
        : localized_decimal($stats['cost_per_km'] * fuel_to_metric(1, 'distance', $units['distance']));
    $cards = [
        ['Fuel added', display_value($units, 'volume', $stats['total_liters']), $volume, 'fuel'],
        ['Total spent', localized_decimal($stats['total_paid']), $currency, 'money'],
        ['Distance driven', display_value($units, 'distance', $stats['distance_km'], 1), $distance, 'distance'],
        ['Average distance between fill-ups', display_value($units, 'distance', $stats['avg_distance_km'], 1), $distance, 'distance'],
        ['Average time between fill-ups', $stats['avg_days'] === null ? '—' : localized_decimal($stats['avg_days'], 1), t('days'), 'clock'],
        ['Average fuel economy', display_value($units, 'economy', $stats['avg_consumption'], 1), $economy, 'trend'],
        ['Most efficient', display_value($units, 'economy', $stats['best_consumption'], 1), $economy, 'trend'],
        ['Least efficient', display_value($units, 'economy', $stats['worst_consumption'], 1), $economy, 'trend'],
        ['Fill-ups', (string) $stats['fill_count'], '', 'history'],
        ['Average fuel price', display_value($units, 'price', $stats['avg_price_per_liter'], 3), $currency . '/' . unit_label($units['price']), 'money'],
        ['Cost per distance', $costPerDistance, $currency . '/' . $distance, 'distance'],
    ];
    ?>
<section class="stats-grid" aria-label="<?= e(t('Fill-up statistics')) ?>">
    <?php foreach ($cards as [$label, $value, $unit, $glyph]): ?>
    <article class="stat-card">
        <div class="stat-top"><h2 class="stat-label"><?= e(t($label)) ?></h2><span class="stat-icon"><?= icon($glyph) ?></span></div>
        <div class="stat-value"><?= e($value) ?><?php if ($unit !== '' && $value !== '—'): ?> <span class="stat-unit"><?= e($unit) ?></span><?php endif ?></div>
    </article>
    <?php endforeach ?>
</section>
<?php
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
        <span class="recent-info"><strong><?= e($row['station_name']) ?></strong><small><?= e($row['car_name'] . ' · ' . localized_date($row['filled_at'])) ?></small></span>
        <span class="recent-amount"><strong><?= e(localized_decimal((float) $row['total_paid']) . ' ' . $user['currency']) ?></strong><small><?= e(display_value($units, 'volume', $row['liters']) . ' ' . unit_label($units['volume'])) ?></small></span>
    </a></li>
    <?php endforeach ?>
</ul>
<?php
}

function history_table(array $rows, array $user, string $action, array $alerts = []): void
{
    $units = units_for($user);
    if (!$rows) {
        empty_state('No fill-ups in this view', 'Log a fill-up or adjust your filters.', $action);
        return;
    }
    $headers = [
        ['Date', '', 'date'], ['Vehicle', '', 'vehicle'], ['Station', '', 'station'],
        ['Fuel', unit_label($units['volume']), 'volume'],
        ['Price', $user['currency'] . '/' . unit_label($units['price']), 'price'],
        ['Paid', $user['currency'], 'paid'],
        ['Odometer', unit_label($units['distance']), 'odometer'],
        ['Distance', unit_label($units['distance']), 'distance'],
        ['Fuel economy', unit_label($units['economy']), 'economy'],
        ['Tank before', unit_label($units['volume']), 'tank'],
        ['Meter', unit_label($units['economy']), 'reported'],
        ['Actions', '', 'actions'],
    ];
    ?>
<div class="history-table-wrap">
<table class="history-table">
    <colgroup><?php foreach ($headers as [, , $column]): ?><col class="history-<?= e($column) ?>"><?php endforeach ?></colgroup>
    <thead><tr><?php foreach ($headers as [$label, $unit, $column]): ?>
        <th scope="col" class="history-<?= e($column) ?>"><?= e(t($label)) ?><?php if ($unit): ?><small><?= e($unit) ?></small><?php endif ?></th>
    <?php endforeach ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $row):
        $estimate = [];
        if (!empty($row['estimated_tank'])) { $estimate[] = t('Tank level'); }
        if (!empty($row['estimated_odometer'])) { $estimate[] = t('Odometer'); }
        $values = [
            ['vehicle', $row['car_name']], ['station', $row['station_name']],
            ['volume', display_value($units, 'volume', $row['liters'])],
            ['price', display_value($units, 'price', $row['price_per_liter'], 3)],
            ['paid', localized_decimal((float) $row['total_paid'])],
            ['odometer', display_value($units, 'distance', $row['odometer_km'], 1)],
            ['distance', display_value($units, 'distance', $row['calculated_distance_km'], 1)],
            ['economy', display_value($units, 'economy', $row['calculated_consumption_l_100km'], 1)],
            ['tank', display_value($units, 'volume', $row['tank_before_liters'])],
            ['reported', display_value($units, 'economy', $row['reported_consumption'] ?? null, 1)],
        ];
        ?>
        <tr>
            <th scope="row" class="history-date"><time datetime="<?= e($row['filled_at']) ?>" title="<?= e(localized_date($row['filled_at'])) ?>"><?= e(localized_date($row['filled_at'], 'd.m.y')) ?></time></th>
            <?php foreach ($values as [$column, $value]):
                $title = $value;
                if ($column === 'price' && (float) $row['price_per_liter'] === 0.0) { $title = t('Gifted fuel'); }
                if ($column === 'economy') { $title .= ' · ' . t('Vehicle reading') . ' ' . display_value($units, 'economy', $row['reported_consumption'] ?? null, 1); }
                ?>
                <td class="history-<?= e($column) ?>" title="<?= e($title) ?>"><?= e($value) ?></td>
            <?php endforeach ?>
            <td class="history-actions"><div class="history-row-actions">
                <?php if ($estimate): ?><span class="history-estimate" title="<?= e(t('Includes an algorithm estimate') . ' · ' . implode(', ', $estimate)) ?>" aria-label="<?= e(t('Includes an algorithm estimate')) ?>">≈</span><?php endif ?>
                <?php if (isset($alerts[$row['id']]) && empty($row['anomaly_dismissed'])): ?><button type="button" class="button alert-button" data-open-dialog="alert-<?= e($row['id']) ?>" title="<?= e(t('Review alert')) ?>" aria-label="<?= e(t('Review alert')) ?>">!</button><?php endif ?>
                <?= action_link('Edit', 'fill', ['id' => $row['id']], 'quiet') ?>
            </div></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div>
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
    $unit = unit_label($units['economy']);
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
        $names[$row['car_id']] = $row['car_name'];
        $dots[] = [$x, $y, $row['car_name'] . ' · ' . localized_date($row['filled_at']) . ' · ' . localized_decimal($points[$index]) . ' ' . $unit];
    }
    ?>
<div class="chart-canvas"><div class="chart-legend"><?php foreach ($names as $carId => $name): ?><span class="series-<?= (int) $carId % 5 ?>"><?= e($name) ?></span><?php endforeach ?></div>
    <p class="chart-caption"><?= e($unit) ?> · <?= e(t('Estimated between fill-ups')) ?><?= $count > count($rows) ? ' · ' . e(t('Last 120 estimates')) : '' ?></p>
    <svg class="chart" viewBox="0 0 690 208" role="img" aria-label="<?= e(t('Fuel economy over time in {unit}, from {min} to {max}', ['unit' => $unit, 'min' => localized_decimal($min), 'max' => localized_decimal($max)])) ?>">
        <?php for ($i = 0; $i <= 4; $i++): $y = 22 + 35 * $i; ?>
            <line class="chart-gridline" x1="66" x2="658" y1="<?= $y ?>" y2="<?= $y ?>"/>
            <text class="chart-label" x="57" y="<?= $y + 4 ?>" text-anchor="end"><?= e(localized_decimal($high - ($high - $low) * $i / 4, $high < 3 ? 2 : 1)) ?></text>
        <?php endfor ?>
        <?php foreach ($series as $carId => $line): ?>
            <polyline class="chart-line series-<?= (int) $carId % 5 ?>" points="<?= e(implode(' ', $line)) ?>"/>
        <?php endforeach ?>
        <?php foreach ($dots as [$x, $y, $label]): ?>
            <circle tabindex="0" class="chart-point" cx="<?= e($x) ?>" cy="<?= e($y) ?>" r="3.7"><title><?= e($label) ?></title></circle>
        <?php endforeach ?>
        <text class="chart-label" x="<?= $start === $end ? 362 : 66 ?>" y="192"<?= $start === $end ? ' text-anchor="middle"' : '' ?>><?= e(localized_date($rows[0]['filled_at'])) ?></text>
        <?php if ($start !== $end): ?><text class="chart-label" x="658" y="192" text-anchor="end"><?= e(localized_date($rows[array_key_last($rows)]['filled_at'])) ?></text><?php endif ?>
    </svg>
</div>
<?php
}
