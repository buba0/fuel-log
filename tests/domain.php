<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/domain.php';

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
    float $consumption,
    float $volume,
    float $price,
    ?float $paid = null
): array {
    return [
        'id' => $id,
        'car_id' => $car,
        'filled_at' => $date,
        'odometer_km' => $odometer,
        'consumption_l_100km' => $consumption,
        'liters' => $volume,
        'price_per_liter' => $price,
        'total_paid' => $paid,
    ];
}

$fills = [
    fillUp(6, 1, '2026-02-01', 850, 7, 25, 5),
    fillUp(5, 1, '2026-01-22', 700, 8, 25, 7),
    fillUp(3, 1, '2026-01-11', 300, 5, 40, 3.75),
    fillUp(4, 2, '2026-01-22', 1100, 9.5, 35, 6, 200),
    fillUp(2, 2, '2026-01-02', 1000, 9, 20, 6),
    fillUp(1, 1, '2026-01-01', 100, 6, 10, 6),
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
expectNear($filtered['avg_consumption'], (5 * 200 + 9.5 * 100 + 8 * 400) / 700, 'Distance-weighted fuel economy');
expectNear($filtered['best_consumption'], 5, 'Best economy');
expectNear($filtered['worst_consumption'], 9.5, 'Worst economy');

$noSpan = fuel_telemetry($fills, '2026-01-01', '2026-01-02');
expectSame($noSpan['distance_km'], null, 'No distance until there are comparable readings');
expectSame($noSpan['avg_days'], null, 'No interval between different cars');
expectNear($noSpan['avg_consumption'], 7.5, 'Arithmetic average with no odometer intervals');

$sameDay = fuel_telemetry([
    fillUp(8, 1, '2026-02-02', 990, 6, 10, 5),
    fillUp(9, 1, '2026-02-02', 920, 7, 10, 5),
]);
expectNear($sameDay['distance_km'], 70, 'Same-day distance follows the odometer');
expectNear($sameDay['avg_days'], 0, 'Same-day interval');
expectNear($sameDay['avg_consumption'], 6, 'Same-day later reading determines interval economy');

$empty = fuel_telemetry($fills, '2026-03-01');
expectSame($empty['fill_count'], 0, 'Empty date range');
expectSame($empty['avg_consumption'], null, 'No economy in empty range');
expectSame($empty['avg_price_per_liter'], null, 'No average price in empty range');

echo "Domain checks passed.\n";
