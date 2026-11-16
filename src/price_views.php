<?php
declare(strict_types=1);
function price_page(array $rows, array $user): void
{
    page_heading('Fuel prices');
    $from = input($_GET, 'from');
    $to = input($_GET, 'to');
    ?><form class="panel filter-bar" method="get"><input type="hidden" name="page" value="prices"><?php
    field('from','From',$from,['type'=>'date']);
    field('to','To',$to,['type'=>'date']);
    ?><button class="button"><?= e(t('Apply filters')) ?></button><?= action_link('Clear filters','prices') ?></form><?php
    if (($from && !valid_date($from)) || ($to && !valid_date($to)) || ($from && $to && $from > $to)) {
        ?><p role="alert"><?= e(t('Enter a valid date range with the start on or before the end.')) ?></p><?php
        return;
    }
    $stats = fuel_telemetry($rows, $from ?: null, $to ?: null);
    $units = units_for($user);
    $paid = array_values(array_filter($stats['in_range'], static fn(array $r): bool => (float)$r['price_per_liter'] > 0));
    if (!$paid) { empty_state('No paid fuel purchases', 'Gifted fuel is excluded from price statistics.'); return; }
    $unit = $user['currency'].'/'.unit_label($units['price']);
    ?><section class="stats-grid"><?php foreach ([
        'Average fuel price'=>$stats['avg_price_per_liter'],
        'Lowest price'=>min(array_column($paid,'price_per_liter')),
        'Highest price'=>max(array_column($paid,'price_per_liter')),
        'Latest price'=>$paid[array_key_last($paid)]['price_per_liter']
    ] as $label=>$value): ?><article class="stat-card"><h2 class="stat-label"><?= e(t($label)) ?></h2><p class="stat-value"><?= e(display_value($units,'price',$value,3)) ?><small> <?= e($unit) ?></small></p></article><?php endforeach ?></section>
    <p class="field-help"><?= e(t('Gifted fuel is excluded from price statistics.')) ?></p>
    <?php foreach (['station_id'=>'Average prices by station','month'=>'Monthly price trend'] as $key=>$title):
        $groups=[];
        foreach ($paid as $row) {
            $id=$key==='month'?substr($row['filled_at'],0,7):$row['station_id'];
            $groups[$id]['name']=$key==='month'?$id:$row['station_name'];
            $groups[$id]['cost']=($groups[$id]['cost']??0)+(float)$row['total_paid'];
            $groups[$id]['volume']=($groups[$id]['volume']??0)+(float)$row['liters'];
            $groups[$id]['count']=($groups[$id]['count']??0)+1;
        }
        ksort($groups);
        ?><section class="panel"><h2><?= e(t($title)) ?></h2><div class="fillup-list"><?php foreach($groups as $group): ?><div class="fillup-card"><strong><?= e($group['name']) ?></strong><p><?= e(display_value($units,'price',$group['cost']/$group['volume'],3).' '.$unit) ?> · <?= e($group['count'].' '.t('Fill-ups')) ?> · <?= e(display_value($units,'volume',$group['volume']).' '.unit_label($units['volume'])) ?></p></div><?php endforeach ?></div></section><?php
    endforeach;
}
