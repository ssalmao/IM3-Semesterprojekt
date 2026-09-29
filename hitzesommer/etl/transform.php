<?php
/**
 * Transform – fertig aus Block C.
 *
 * Das ist die Lösung aus Code-Along 09, unverändert. Sie liefert eine Zeile pro
 * Stadt und vollständigem Sommer:
 *
 *   ['city' => 'Bern', 'year' => 1940, 'measurement_days' => 92,
 *    'hot_days' => 0, 'max_temperature_c' => 26.5]
 *
 * Genau diese Zeilen schreibt load.php heute in die Datenbank. An dieser Datei
 * ändert sich nichts – wer sie liest, sieht nur noch einmal, woher die Felder
 * kommen, die gleich zu Spalten werden.
 */

$rawLocations = include __DIR__ . '/extract.php';

$summerMonths = [6, 7, 8];
$hotDayThresholdC = 30.0;
$expectedDaysPerSummer = 92;

$audit = [
    'input_days' => 0,
    'outside_summer' => 0,
    'invalid_measurements' => 0,
    'incomplete_summers' => 0,
    'output_rows' => 0,
];

$byCityAndYear = [];

foreach ($rawLocations as $location) {
    $city = $location['city'];

    $dates = $location['source']['daily']['time'] ?? [];
    $temperatures = $location['source']['daily']['temperature_2m_max'] ?? [];

    if (count($dates) !== count($temperatures)) {
        throw new RuntimeException("Datum und Temperatur passen bei {$city} nicht zusammen.");
    }

    for ($i = 0; $i < count($dates); $i++) {
        $audit['input_days']++;

        $date = $dates[$i];
        $temperature = $temperatures[$i];

        $month = (int) substr($date, 5, 2);

        if (!in_array($month, $summerMonths, true)) {
            $audit['outside_summer']++;
            continue;
        }

        if (!is_numeric($temperature)) {
            $audit['invalid_measurements']++;
            continue;
        }

        $year = (int) substr($date, 0, 4);
        $key = $city . '-' . $year;

        if (!isset($byCityAndYear[$key])) {
            $byCityAndYear[$key] = [
                'city' => $city,
                'year' => $year,
                'measurement_days' => 0,
                'hot_days' => 0,
                'max_temperature_c' => null,
            ];
        }

        $temperatureC = (float) $temperature;
        $byCityAndYear[$key]['measurement_days']++;

        if ($temperatureC >= $hotDayThresholdC) {
            $byCityAndYear[$key]['hot_days']++;
        }

        $oldMaximum = $byCityAndYear[$key]['max_temperature_c'];
        if ($oldMaximum === null || $temperatureC > $oldMaximum) {
            $byCityAndYear[$key]['max_temperature_c'] = $temperatureC;
        }
    }
}

$transformedRows = [];

foreach ($byCityAndYear as $summer) {
    if ($summer['measurement_days'] !== $expectedDaysPerSummer) {
        $audit['incomplete_summers']++;
        continue;
    }

    $summer['max_temperature_c'] = round($summer['max_temperature_c'], 1);
    $transformedRows[] = $summer;
}

usort($transformedRows, function (array $a, array $b): int {
    return [$a['year'], $a['city']] <=> [$b['year'], $b['city']];
});

$audit['output_rows'] = count($transformedRows);

return [
    'question' => 'Wie hat sich die Anzahl Hitzetage pro Sommer verändert?',
    'rules' => [
        'months' => $summerMonths,
        'hot_day_threshold_c' => $hotDayThresholdC,
        'required_measurement_days' => $expectedDaysPerSummer,
    ],
    'data' => $transformedRows,
    'audit' => $audit,
];
