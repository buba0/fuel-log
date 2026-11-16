<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/anomalies.php';

function expectSame(mixed $actual, mixed $expected, string $description): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($description . ': got ' . var_export($actual, true));
    }
}

function expectNear(float $actual, float $expected, string $description): void
{
    if (abs($actual - $expected) > 0.000001) {
        throw new RuntimeException("$description: expected $expected, got $actual");
    }
}

foreach (fuel_unit_options() as $dimension => $options) {
    foreach (array_keys($options) as $unit) {
        expectNear(
            fuel_from_metric(fuel_to_metric(12.5, $dimension, $unit), $dimension, $unit),
            12.5,
            "$dimension $unit round trip"
        );
    }
}

expectNear(fuel_to_metric(1, 'distance', 'mi'), 1.609344, 'Mile to kilometers');
expectNear(fuel_to_metric(1, 'volume', 'uk_gal'), 4.54609, 'UK gallon to liters');
expectNear(fuel_to_metric(30, 'economy', 'mpg_us'), 7.840486, 'US mpg to L/100 km');
expectNear(fuel_from_metric(2, 'price', 'us_gal'), 7.570823568, 'Price per US gallon');

function fillUp(
    int $id,
    int $car,
    string $date,
    float $odometer,
    float $tankBefore,
    float $volume,
    float $price,
    ?float $paid = null
): array {
    return [
        'id' => $id,
        'car_id' => $car,
        'filled_at' => $date,
        'odometer_km' => $odometer,
        'consumption_l_100km' => 99.0,
        'tank_before_liters' => $tankBefore,
        'liters' => $volume,
        'price_per_liter' => $price,
        'total_paid' => $paid,
    ];
}

$fills = [
    fillUp(6, 1, '2026-02-01', 850, 7, 25, 5),
    fillUp(5, 1, '2026-01-22', 700, 18, 25, 7),
    fillUp(3, 1, '2026-01-11', 300, 10, 40, 3.75),
    fillUp(4, 2, '2026-01-22', 1100, 5, 35, 6, 200),
    fillUp(2, 2, '2026-01-02', 1000, 12, 20, 6),
    fillUp(1, 1, '2026-01-01', 100, 20, 10, 6),
];

$filtered = fuel_telemetry($fills, '2026-01-11', '2026-01-22');
expectSame(array_column($filtered['in_range'], 'id'), [3, 5, 4], 'Inclusive dates and odometer order');
expectSame($filtered['fill_count'], 3, 'Fill-up count');
expectSame($filtered['interval_count'], 3, 'Intervals use each car’s previous fill-up');
expectNear($filtered['total_liters'], 100, 'Total purchased volume');
expectNear($filtered['total_paid'], 525, 'Total cost honors transactions’ recorded paid amounts');
expectNear($filtered['avg_price_per_liter'], 5.25, 'Volume-weighted average price');
expectNear($filtered['avg_days'], 41 / 3, 'Average gap includes previous fill before filter start');
expectNear($filtered['distance_km'], 700, 'Distance includes previous readings for each car');
expectNear($filtered['avg_distance_km'], 700 / 3, 'Average distance includes the previous readings before filter start');
expectSame(array_column($filtered['in_range'], 'calculated_distance_km'), [200.0, 400.0, 100.0],
    'Each distance belongs to the later fill-up for the same car');
expectNear($filtered['avg_consumption'], 100 * (20 + 32 + 27) / 700, 'Fuel used per distance across valid intervals');
expectNear($filtered['best_consumption'], 8, 'Best economy');
expectNear($filtered['worst_consumption'], 27, 'Worst economy');
expectSame(array_column($filtered['in_range'], 'calculated_consumption_l_100km'), [10.0, 8.0, 27.0],
    'Each interval uses previous fuel after filling and current fuel before filling');

$noSpan = fuel_telemetry($fills, '2026-01-01', '2026-01-02');
expectSame($noSpan['distance_km'], null, 'No distance until there are comparable readings');
expectSame($noSpan['avg_distance_km'], null, 'No average distance before the second fill for a vehicle');
expectSame(array_column($noSpan['in_range'], 'calculated_distance_km'), [null, null], 'First fill-ups have no distance');
expectSame($noSpan['avg_days'], null, 'No interval between different cars');
expectSame($noSpan['avg_consumption'], null, 'Legacy economy readings are ignored');
expectSame(array_column($noSpan['in_range'], 'calculated_consumption_l_100km'), [null, null], 'First fill-ups have no economy');

$sameDay = fuel_telemetry([
    fillUp(8, 1, '2026-02-02', 990, 0, 10, 5),
    fillUp(9, 1, '2026-02-02', 920, 0, 10, 5),
]);
expectNear($sameDay['distance_km'], 70, 'Same-day distance follows the odometer');
expectNear($sameDay['avg_distance_km'], 70, 'Same-day fill-ups count toward average distance');
expectNear($sameDay['avg_days'], 0, 'Same-day interval');
expectNear($sameDay['avg_consumption'], 100 * 10 / 70, 'Empty tank estimates use only the previous purchased fuel');

$invalid = fuel_telemetry([
    fillUp(10, 1, '2026-02-03', 100, 0.1, 0.2, 5),
    fillUp(11, 1, '2026-02-04', 200, 0.3, 5, 5),
    fillUp(12, 1, '2026-02-05', 300, 6, 10, 5),
    fillUp(13, 1, '2026-02-06', 300, 2, 12, 5),
    fillUp(14, 1, '2026-02-07', 400, 6, 15, 5),
]);
expectSame(array_column($invalid['in_range'], 'calculated_consumption_l_100km'), [null, null, null, null, 8.0],
    'Zero fuel, negative fuel or zero distance leaves economy unavailable');
expectNear($invalid['distance_km'], 300, 'Invalid fuel balance still counts recorded distance');
expectNear($invalid['avg_distance_km'], 75, 'Average distance includes zero-distance fill-ups and invalid fuel balances');
expectSame(array_column($invalid['in_range'], 'calculated_distance_km'), [null, 100.0, 100.0, 0.0, 100.0],
    'Zero distance remains available in the fill-up history');
expectNear($invalid['avg_consumption'], 8, 'Only valid intervals contribute to average economy');

$missingOdometer = fillUp(19, 1, '2026-03-25', 700, 0, 10, 5);
unset($missingOdometer['odometer_km']);
$incomplete = fuel_telemetry([
    fillUp(15, 1, '2026-03-01', 370, 0, 10, 5),
    fillUp(16, 1, '2026-03-03', 360, 0, 10, 5),
    fillUp(17, 1, '2026-03-07', 520, 0, 10, 5),
    fillUp(18, 1, '2026-03-15', 640, 0, 10, 5),
    $missingOdometer,
]);
expectSame(array_column($incomplete['in_range'], 'calculated_distance_km'), [null, null, 160.0, 120.0, null],
    'Negative and missing odometer deltas leave distance unavailable');
expectNear($incomplete['avg_distance_km'], 140, 'Incomplete distances do not count in the distance average');
expectNear($incomplete['avg_days'], 6, 'Incomplete distances still count in the time average');

$empty = fuel_telemetry($fills, '2026-03-01');
expectSame($empty['fill_count'], 0, 'Empty date range');
expectSame($empty['avg_distance_km'], null, 'No average distance in empty range');
expectSame($empty['avg_consumption'], null, 'No economy in empty range');
expectSame($empty['avg_price_per_liter'], null, 'No average price in empty range');

$baseline = [
    fillUp(21, 1, '2026-01-01', 100, 0, 10, 5),
    fillUp(22, 1, '2026-01-11', 300, 0, 8, 5),
    fillUp(23, 1, '2026-01-21', 400, 0, 18, 5),
    fillUp(24, 1, '2026-01-31', 700, 0, 30, 5),
    fillUp(27, 2, '2026-02-01', 100, 0, 49, 5),
    fillUp(28, 2, '2026-02-25', 200, 0, 30, 5),
];
expectSame(fuel_anomalies($baseline), [], 'Unusual consumption needs three preceding valid same-car intervals');

$outlier = fillUp(29, 1, '2026-02-25', 900, 0, 20, 5) + ['tank_capacity_liters' => 50.0];
$anomalies = fuel_anomalies([$outlier, ...$baseline]);
expectSame(array_keys($anomalies), [29], 'Only the high-consumption interval with a baseline is flagged');
$notice = $anomalies[29];
expectSame($notice['previous_fill_id'], 24, 'Anomaly uses the previous fill-up for the same car');
expectNear($notice['avg_economy'], 6, 'Anomaly baseline is distance-weighted and excludes its own interval');
expectNear($notice['current_economy'], 15, 'Anomaly records the high consumption');
expectNear($notice['avg_days'], 10, 'Usual time is based on prior same-car fill-ups');
expectNear($notice['interval_days'], 25, 'Anomaly records its elapsed time');
expectSame($notice['suspected_missing'], true, 'A long gap can suggest a missing fill-up');
expectNear($notice['suggested_percent'], 36, 'Tank estimate uses the inferred fuel and recorded capacity');
expectNear($notice['suggested_liters'], 18, 'Tank estimate uses expected rather than recorded fuel used');

$outlier['filled_at'] = '2026-02-10';
$shortGap = fuel_anomalies([...$baseline, $outlier])[29];
expectSame($shortGap['suspected_missing'], false, 'A normal gap does not suggest a missing fill-up');
expectNear($shortGap['suggested_percent'], 36, 'The tank correction can be inferred during a normal gap');

$outlier['liters'] = 35;
expectSame(fuel_anomalies([...$baseline, $outlier])[29]['suggested_percent'], null,
    'Do not suggest a tank level that would overflow after filling');
$outlier['liters'] = 20;
$outlier['tank_before_liters'] = 3;
expectSame(fuel_anomalies([...$baseline, $outlier])[29]['suggested_percent'], null,
    'Do not overwrite an already estimated tank level');
$outlier['tank_before_liters'] = 0;
$baseline[3]['liters'] = 18;
expectSame(fuel_anomalies([...$baseline, $outlier]), [], 'A value exactly at the high-consumption cutoff is not flagged');

echo "Domain checks passed.\n";

$gifted = fuel_telemetry([
    fillUp(101, 1, '2026-01-01', 100, 0, 20, 5),
    fillUp(102, 1, '2026-01-02', 200, 0, 10, 0),
]);
expectNear($gifted['total_liters'], 30, 'Gifted fuel counts in volume');
expectNear($gifted['avg_price_per_liter'], 5, 'Gifted fuel excluded from price denominator');
expectNear($gifted['distance_km'], 100, 'Gifted fuel preserves distance');
