<?php

declare(strict_types=1);

function export_fillups(array $stats, array $user, string $format): never
{
    $units = units_for($user);
    $symbols = array_map('unit_symbol', $units);
    $currency = $user['currency'];
    $distance = t($symbols['distance']);
    $volume = t($symbols['volume']);
    $price = $currency . '/' . t($symbols['price']);
    $economy = t($symbols['economy']);
    $columns = [
        'date' => t('Date'),
        'vehicle' => t('Vehicle'),
        'station' => t('Station'),
        'volume' => t('Fuel ({unit})', ['unit' => $volume]),
        'price_per_unit' => t('Price ({unit})', ['unit' => $price]),
        'paid' => t('Paid ({currency})', ['currency' => $currency]),
        'odometer' => t('Odometer ({unit})', ['unit' => $distance]),
        'distance_since_last_fill' => t('Distance since last fill-up ({unit})', ['unit' => $distance]),
        'estimated_economy' => t('Estimated economy ({unit})', ['unit' => $economy]),
        'tank_before' => t('Tank before ({unit})', ['unit' => $volume]),
        'tank_before_percent' => t('Tank level before filling (%)'),
    ];

    $rows = [];
    foreach ($stats['in_range'] as $row) {
        $rows[] = [
            'date' => $row['filled_at'],
            'vehicle' => $row['car_name'],
            'station' => $row['station_name'],
            'volume' => fuel_from_metric((float) $row['liters'], 'volume', $units['volume']),
            'price_per_unit' => fuel_from_metric((float) $row['price_per_liter'], 'price', $units['price']),
            'paid' => (float) $row['total_paid'],
            'odometer' => fuel_from_metric((float) $row['odometer_km'], 'distance', $units['distance']),
            'distance_since_last_fill' => $row['calculated_distance_km'] === null ? null
                : fuel_from_metric((float) $row['calculated_distance_km'], 'distance', $units['distance']),
            'estimated_economy' => $row['calculated_consumption_l_100km'] === null ? null
                : fuel_from_metric((float) $row['calculated_consumption_l_100km'], 'economy', $units['economy']),
            'tank_before' => fuel_from_metric((float) $row['tank_before_liters'], 'volume', $units['volume']),
            'tank_before_percent' => (float) $row['tank_before_percent'],
        ];
    }

    $headings = array_values($columns);
    [$type, $body] = match ($format) {
        'csv' => ['text/csv; charset=UTF-8', "\xEF\xBB\xBF" . fillup_export_delimited($headings, $rows, ',')],
        'txt' => ['text/plain; charset=UTF-8', fillup_export_delimited($headings, $rows, "\t")],
        'json' => ['application/json; charset=UTF-8', json_encode([
            'units' => [
                'distance' => $symbols['distance'],
                'volume' => $symbols['volume'],
                'economy' => $symbols['economy'],
                'price_per_unit' => $currency . '/' . $symbols['price'],
                'tank_before_percent' => '%',
            ],
            'currency' => $currency,
            'fillups' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', fillup_export_xlsx($headings, $rows)],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', fillup_export_docx($headings, $rows)],
        default => throw new InvalidArgumentException('Unsupported export format.'),
    };

    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="fuel-log-fillups-' . date('Y-m-d') . '.' . $format . '"');
    echo $body;
    exit;
}

function fillup_export_delimited(array $headings, array $rows, string $separator): string
{
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) {
        throw new RuntimeException('Cannot start fill-up export.');
    }

    try {
        fputcsv($stream, $headings, $separator, '"', '', "\r\n");
        foreach ($rows as $row) {
            $cells = array_map(static function (mixed $value): mixed {
                // Protect user-entered text from spreadsheet formula interpretation.
                if (is_string($value) && preg_match('/^(?:[\t\r\n]|[\x00-\x20\p{Z}\x{FEFF}]*[=+\-@＝＋－＠])/u', $value)) {
                    return "'" . $value;
                }
                return $value;
            }, $row);
            fputcsv($stream, $cells, $separator, '"', '', "\r\n");
        }
        rewind($stream);
        $data = stream_get_contents($stream);
        if ($data === false) {
            throw new RuntimeException('Cannot read fill-up export.');
        }
        return $data;
    } finally {
        fclose($stream);
    }
}

function fillup_export_xlsx(array $headings, array $rows): string
{
    $spreadsheetNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $officeRelationships = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $packageRelationships = 'http://schemas.openxmlformats.org/package/2006/relationships';
    $workbook = '<workbook xmlns="' . $spreadsheetNamespace . '" xmlns:r="' . $officeRelationships . '">'
        . '<bookViews><workbookView/></bookViews>'
        . '<sheets><sheet name="' . fillup_export_xml(t('Fill-ups')) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $workbookRelations = '<Relationships xmlns="' . $packageRelationships . '">'
        . '<Relationship Id="rId1" Type="' . $officeRelationships . '/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $end = chr(ord('A') + count($headings) - 1) . (count($rows) + 1);
    $worksheet = '<worksheet xmlns="' . $spreadsheetNamespace . '">'
        . '<dimension ref="A1:' . $end . '"/><sheetViews><sheetView workbookViewId="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews>'
        . '<cols><col min="1" max="' . count($headings) . '" width="25" customWidth="1"/></cols>'
        . '<sheetData>';

    foreach ([$headings, ...$rows] as $index => $row) {
        $worksheet .= '<row r="' . ($index + 1) . '">';
        foreach (array_values($row) as $column => $value) {
            if ($value === null) {
                continue;
            }
            $ref = chr(ord('A') + $column) . ($index + 1);
            if (is_string($value)) {
                $worksheet .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                    . fillup_export_xml($value) . '</t></is></c>';
            } else {
                $worksheet .= '<c r="' . $ref . '"><v>' . json_encode($value, JSON_THROW_ON_ERROR) . '</v></c>';
            }
        }
        $worksheet .= '</row>';
    }
    $worksheet .= '</sheetData><autoFilter ref="A1:' . $end . '"/></worksheet>';

    return fillup_export_package([
        'xl/workbook.xml' => $workbook,
        'xl/_rels/workbook.xml.rels' => $workbookRelations,
        'xl/worksheets/sheet1.xml' => $worksheet,
    ], 'xl/workbook.xml', [
        'xl/workbook.xml' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
        'xl/worksheets/sheet1.xml' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml',
    ]);
}

function fillup_export_docx(array $headings, array $rows): string
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $widths = [1100, 1700, 1700, 1200, 1350, 1300, 1300, 1600, 1600, 1300, 1200];
    $title = fillup_export_xml(t('Fill-up history'));
    $document = '<w:document xmlns:w="' . $namespace . '"><w:body>'
        . '<w:p><w:r><w:rPr><w:b/><w:sz w:val="36"/></w:rPr><w:t>' . $title . '</w:t></w:r></w:p>'
        . '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($widths) . '" w:type="dxa"/>'
        . '<w:tblBorders>';
    foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
        $document .= '<w:' . $side . ' w:val="single" w:sz="4" w:color="DDD5DC"/>';
    }
    $document .= '</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>';
    foreach ($widths as $width) {
        $document .= '<w:gridCol w:w="' . $width . '"/>';
    }
    $document .= '</w:tblGrid>';

    foreach ([$headings, ...$rows] as $index => $row) {
        $heading = $index === 0;
        $document .= '<w:tr>' . ($heading ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
        foreach (array_values($row) as $column => $value) {
            $size = match ($column) {
                4 => 3,
                6, 7, 8 => 1,
                default => 2,
            };
            $display = match (true) {
                $value === null => '—',
                !$heading && $column === 0 => localized_date($value),
                is_float($value) => localized_decimal($value, $size),
                default => $value,
            };
            $document .= '<w:tc><w:tcPr><w:tcW w:w="' . $widths[$column] . '" w:type="dxa"/>'
                . ($heading ? '<w:shd w:fill="F1ECF1"/>' : '') . '</w:tcPr><w:p><w:r><w:rPr>'
                . ($heading ? '<w:b/>' : '') . '<w:sz w:val="17"/></w:rPr><w:t xml:space="preserve">'
                . fillup_export_xml($display) . '</w:t></w:r></w:p></w:tc>';
        }
        $document .= '</w:tr>';
    }

    $document .= '</w:tbl><w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
        . '<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="0" w:footer="0" w:gutter="0"/>'
        . '</w:sectPr></w:body></w:document>';
    return fillup_export_package(['word/document.xml' => $document], 'word/document.xml', [
        'word/document.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
    ]);
}

function fillup_export_xml(string $text): string
{
    $valid = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
    return htmlspecialchars($valid ?? '', ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function fillup_export_package(array $parts, string $entryPoint, array $types): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('The PHP ZIP extension is required for Office downloads.');
    }
    $manifest = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>';
    foreach ($types as $name => $type) {
        $manifest .= '<Override PartName="/' . $name . '" ContentType="' . $type . '"/>';
    }
    $parts['[Content_Types].xml'] = $manifest . '</Types>';
    $parts['_rels/.rels'] = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
        . ' Target="' . $entryPoint . '"/></Relationships>';

    $path = tempnam(sys_get_temp_dir(), 'fuellog-');
    if ($path === false) {
        throw new RuntimeException('Cannot start Office download.');
    }
    try {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open Office download.');
        }
        foreach ($parts as $name => $xml) {
            if (!$zip->addFromString($name, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . $xml)) {
                throw new RuntimeException('Cannot write Office download.');
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('Cannot finish Office download.');
        }

        $body = file_get_contents($path);
        if ($body === false) {
            throw new RuntimeException('Cannot read Office download.');
        }
        return $body;
    } finally {
        unlink($path);
    }
}
