<?php

declare(strict_types=1);

const FUEL_KM_PER_MILE = 1.609344;
const FUEL_LITERS_PER_US_GALLON = 3.785411784;
const FUEL_LITERS_PER_UK_GALLON = 4.54609;

function fuel_unit_definitions(): array
{
    return [
        'distance' => [
            'km' => ['km', 1.0],
            'mi' => ['mi', FUEL_KM_PER_MILE],
        ],
        'volume' => [
            'l' => ['L', 1.0],
            'us_gal' => ['US gal', FUEL_LITERS_PER_US_GALLON],
            'uk_gal' => ['UK gal', FUEL_LITERS_PER_UK_GALLON],
        ],
        'economy' => [
            'l_100km' => ['L/100 km', 1.0],
            'mpg_us' => ['mpg (US)', 100 * FUEL_LITERS_PER_US_GALLON / FUEL_KM_PER_MILE],
            'mpg_uk' => ['mpg (UK)', 100 * FUEL_LITERS_PER_UK_GALLON / FUEL_KM_PER_MILE],
            'km_l' => ['km/L', 100.0],
        ],
        'price' => [
            'l' => ['L', 1.0],
            'us_gal' => ['US gal', 1.0 / FUEL_LITERS_PER_US_GALLON],
            'uk_gal' => ['UK gal', 1.0 / FUEL_LITERS_PER_UK_GALLON],
        ],
    ];
}

function fuel_unit_options(): array
{
    $options = [];
    foreach (fuel_unit_definitions() as $dimension => $definitions) {
        foreach ($definitions as $unit => [$label]) {
            $options[$dimension][$unit] = $label;
        }
    }

    return $options;
}

function fuel_unit_presets(): array
{
    return [
        'metric' => ['distance' => 'km', 'volume' => 'l', 'economy' => 'l_100km', 'price' => 'l'],
        'us' => ['distance' => 'mi', 'volume' => 'us_gal', 'economy' => 'mpg_us', 'price' => 'us_gal'],
        'uk' => ['distance' => 'mi', 'volume' => 'uk_gal', 'economy' => 'mpg_uk', 'price' => 'uk_gal'],
    ];
}

function fuel_to_metric(float $value, string $dimension, string $unit): float
{
    return fuel_convert($value, $dimension, $unit, true);
}

function fuel_from_metric(float $value, string $dimension, string $unit): float
{
    return fuel_convert($value, $dimension, $unit, false);
}

function fuel_convert(float $value, string $dimension, string $unit, bool $toMetric): float
{
    $definition = fuel_unit_definitions()[$dimension][$unit] ?? null;
    if ($definition === null) {
        throw new InvalidArgumentException('Choose a supported unit.');
    }

    [, $factor] = $definition;
    if ($dimension === 'economy' && $unit !== 'l_100km') {
        if ($value <= 0) {
            throw new InvalidArgumentException('Fuel economy must be greater than zero.');
        }

        return $factor / $value;
    }

    return $toMetric ? $value * $factor : $value / $factor;
}

/**
 * Return statistics in canonical metric units. Dates are inclusive ISO calendar dates.
 * An interval's distance, duration and reported economy belong to its later fill-up.
 */
function fuel_telemetry(array $rows, ?string $start = null, ?string $end = null): array
{
    usort($rows, static function (array $left, array $right): int {
        return strcmp($left['filled_at'], $right['filled_at'])
            ?: (float) ($left['odometer_km'] ?? 0) <=> (float) ($right['odometer_km'] ?? 0)
            ?: (int) $left['id'] <=> (int) $right['id'];
    });

    $inRange = [];
    $previousByCar = [];
    $totalLiters = 0.0;
    $totalPaid = 0.0;
    $distanceKm = null;
    $intervalCount = 0;
    $totalDays = 0.0;
    $recordedEconomies = [];
    $weightedEconomySum = 0.0;
    $economyWeightKm = 0.0;

    foreach ($rows as $row) {
        $date = substr($row['filled_at'], 0, 10);
        if ($end !== null && $date > $end) {
            break;
        }

        $carId = $row['car_id'];
        $previous = $previousByCar[$carId] ?? null;
        $previousByCar[$carId] = $row;
        if ($start !== null && $date < $start) {
            continue;
        }

        $inRange[] = $row;
        $liters = (float) $row['liters'];
        $totalLiters += $liters;
        $totalPaid += (float) ($row['total_paid'] ?? round($liters * (float) $row['price_per_liter'], 2));
        $economy = (float) ($row['consumption_l_100km'] ?? 0);
        if ($economy > 0) {
            $recordedEconomies[] = $economy;
        }

        if ($previous === null) {
            continue;
        }

        $seconds = strtotime($row['filled_at'] . ' UTC') - strtotime($previous['filled_at'] . ' UTC');
        $totalDays += $seconds / 86400;
        $intervalCount++;
        if (!isset($row['odometer_km'], $previous['odometer_km'])) {
            continue;
        }

        $span = (float) $row['odometer_km'] - (float) $previous['odometer_km'];
        if ($span < 0) {
            continue;
        }

        $distanceKm = ($distanceKm ?? 0.0) + $span;
        if ($economy > 0) {
            $weightedEconomySum += $economy * $span;
            $economyWeightKm += $span;
        }
    }

    $averageEconomy = null;
    if ($economyWeightKm > 0) {
        $averageEconomy = $weightedEconomySum / $economyWeightKm;
    } elseif ($recordedEconomies !== []) {
        $averageEconomy = array_sum($recordedEconomies) / count($recordedEconomies);
    }

    return [
        'fill_count' => count($inRange),
        'total_liters' => $totalLiters,
        'total_paid' => round($totalPaid, 2),
        'distance_km' => $distanceKm,
        'avg_days' => $intervalCount > 0 ? $totalDays / $intervalCount : null,
        'avg_consumption' => $averageEconomy,
        'best_consumption' => $recordedEconomies !== [] ? min($recordedEconomies) : null,
        'worst_consumption' => $recordedEconomies !== [] ? max($recordedEconomies) : null,
        'avg_price_per_liter' => $totalLiters > 0 ? $totalPaid / $totalLiters : null,
        'cost_per_km' => $distanceKm > 0 ? $totalPaid / $distanceKm : null,
        'in_range' => $inRange,
        'interval_count' => $intervalCount,
    ];
}
