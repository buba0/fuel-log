<?php
declare(strict_types=1);

function anomaly_notices(array $rows, array $visible, array $user): void
{
    $allowed = array_column($visible, null, 'id');
    $units = units_for($user);
    foreach (fuel_anomalies($rows) as $id => $notice) {
        if (!isset($allowed[$id]) || !empty($allowed[$id]['anomaly_dismissed'])) {
            continue;
        }
        $row = $allowed[$id];
        ?>
        <dialog class="panel anomaly-panel" id="alert-<?= e($id) ?>">
            <button type="button" class="button" data-close-dialog><?= e(t('Close')) ?></button>
            <h2><?= e(t('Check this fuel economy estimate')) ?></h2>
            <p><?= e($row['car_name'] . ' · ' . localized_date($row['filled_at'])) ?></p>
            <p><?= e(t('This interval is {current}, compared with a previous average of {average}.', [
                'current' => display_value($units, 'economy', $notice['current_economy']) . ' ' . unit_label($units['economy']),
                'average' => display_value($units, 'economy', $notice['avg_economy']) . ' ' . unit_label($units['economy']),
            ])) ?></p>
            <p><?= e(t('The gap is {days} days; your previous average is {average} days.', [
                'days' => localized_decimal($notice['interval_days'], 1), 'average' => localized_decimal($notice['avg_days'], 1)])) ?></p>
            <?php if ($notice['suspected_missing']): ?>
                <p><?= e(t('The longer gap may indicate a missing fill-up. Check your receipts and add any missing entry before applying an estimate.')) ?></p>
            <?php endif ?>
            <?php if ($notice['suggested_percent'] !== null): ?>
                <p><?= e(t('Using your previous average suggests {percent}% fuel remained before this fill-up. Applying this estimate updates this entry and recalculates later intervals.', ['percent' => localized_decimal($notice['suggested_percent'])])) ?></p>
            <?php else: ?>
                <p><?= e(t('Review the odometer, fuel amount and tank levels for this and the preceding entry.')) ?></p>
            <?php endif ?>
            <p class="field-help"><?= e(t('This is an approximation. Driving conditions and fuel estimates can also change consumption.')) ?></p>
            <form class="form-actions" method="post" action="<?= e(url('anomaly', ['id' => $id])) ?>">
                <?= csrf() ?>
                <input type="hidden" name="fingerprint" value="<?= e(anomaly_fingerprint($rows, (int) $notice['car_id'])) ?>">
                <?php if ($notice['suggested_percent'] !== null): ?>
                    <button class="button button-primary" name="action" value="apply"><?= e(t('Apply estimate')) ?></button>
                <?php endif ?>
                <button class="button" name="action" value="dismiss"><?= e(t('Dismiss')) ?></button>
                <?= action_link('Edit fill-up', 'fill', ['id' => $id]) ?>
            </form>
        </dialog>
        <?php
    }
}

function anomaly_fingerprint(array $rows, int $carId): string
{
    return hash('sha256', json_encode(array_values(array_filter($rows,
        static fn(array $row): bool => (int) $row['car_id'] === $carId)), JSON_THROW_ON_ERROR));
}

function handle_anomaly(PDO $pdo, int $userId, int $id): never
{
    $record = my_record($pdo, 'fill', $userId, $id);
    if (!$record) {
        throw new FormError(t('This record could not be found.'));
    }
    $pdo->beginTransaction();
    try {
        query($pdo, 'SELECT id FROM cars WHERE id = ? AND user_id = ? FOR UPDATE', [$record['car_id'], $userId]);
        $rows = fill_rows($pdo, $userId);
        $notice = fuel_anomalies($rows)[$id] ?? null;
        if (!$notice || !hash_equals(anomaly_fingerprint($rows, (int) $record['car_id']), input($_POST, 'fingerprint'))) {
            throw new FormError(t('The fill-up history changed. Review the latest estimate before applying it.'));
        }
        $action = input($_POST, 'action');
        if ($action === 'dismiss') {
            query($pdo, 'UPDATE fillups SET anomaly_dismissed = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
        } elseif ($action === 'apply' && $notice['suggested_percent'] !== null) {
            query($pdo, 'UPDATE fillups SET tank_before_percent = ?, tank_before_liters = ?, anomaly_dismissed = 0, estimated_tank = 1 WHERE id = ? AND user_id = ?',
                [$notice['suggested_percent'], $notice['suggested_liters'], $id, $userId]);
        } else {
            throw new FormError(t('Review the odometer, fuel amount and tank levels for this and the preceding entry.'));
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
    go('history', notice: 'Review saved.');
}
