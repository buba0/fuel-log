<?php

declare(strict_types=1);

require_once __DIR__ . '/domain.php';

function fuel_anomalies(array $rows): array
{
    $notices = [];
    $previousByCar = [];
    $historyByCar = [];

    foreach (fuel_telemetry($rows)['in_range'] as $row) {
        $carId = $row['car_id'];
        $previous = $previousByCar[$carId] ?? null;
        $previousByCar[$carId] = $row;
        if ($previous === null) {
            continue;
        }

        $history = $historyByCar[$carId] ?? [
            'intervals' => 0, 'days' => 0.0, 'economy_intervals' => 0, 'kilometers' => 0.0, 'fuel_used' => 0.0,
        ];
        $economy = $row['calculated_consumption_l_100km'];
        $distance = $row['calculated_distance_km'];
        $days = $row['calculated_interval_days'];
        if ($economy !== null) {
            if ($history['economy_intervals'] >= 3) {
                $avgEconomy = 100 * $history['fuel_used'] / $history['kilometers'];
                if ($economy > 1.5 * $avgEconomy) {
                    $avgDays = $history['days'] / $history['intervals'];
                    $suggestion = fuel_inferred_tank_level($previous, $row, $avgEconomy * $distance / 100);
                    $notices[$row['id']] = [
                        'fill_id' => $row['id'],
                        'car_id' => $carId,
                        'filled_at' => $row['filled_at'],
                        'previous_fill_id' => $previous['id'],
                        'avg_economy' => $avgEconomy,
                        'current_economy' => $economy,
                        'avg_days' => $avgDays,
                        'interval_days' => $days,
                        'suspected_missing' => $avgDays > 0 && $days > 1.7 * $avgDays,
                        'suggested_percent' => $suggestion['percent'] ?? null,
                        'suggested_liters' => $suggestion['liters'] ?? null,
                    ];
                }
            }

            $history['economy_intervals']++;
            $history['kilometers'] += $distance;
            $history['fuel_used'] += $economy * $distance / 100;
        }

        $history['intervals']++;
        $history['days'] += $days;
        $historyByCar[$carId] = $history;
    }

    return $notices;
}

function fuel_inferred_tank_level(array $previous, array $current, float $expectedFuelUsed): ?array
{
    $capacity = (float) ($current['tank_capacity_liters'] ?? 0);
    if ((float) $current['tank_before_liters'] !== 0.0 || $capacity <= 0) {
        return null;
    }

    $inferred = (float) $previous['tank_before_liters'] + (float) $previous['liters'] - $expectedFuelUsed;
    $maxBefore = $capacity - (float) $current['liters'];
    if ($inferred <= 0 || $inferred > $maxBefore) {
        return null;
    }

    // Use the same precision as the editable percentage and saved tank volume.
    $percent = round(100 * $inferred / $capacity, 2);
    $liters = round($capacity * $percent / 100, 5);
    return $liters > 0 && $liters <= $maxBefore ? ['percent' => $percent, 'liters' => $liters] : null;
}
