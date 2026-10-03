<?php
/**
 * Transform – prüft die Rohzeilen und wandelt sie in Zahlen um.
 * Danach sind die Daten bereit für load.php.
 *
 *   1. Rohzeilen aus extract.php übernehmen
 *   2. Werte prüfen und in int/float umwandeln
 *   3. doppelte Jahre entfernen
 *   4. nach Jahr sortieren
 *   5. Daten und Audit an load.php zurückgeben
 */

declare(strict_types=1);

// Plausible Wertebereiche: [min, max]
const RULES = [
    'year'               => [1900, 2100],
    'marriages_total'    => [0, 200000],
    'marriages_per_1000' => [0, 20],
    'avg_age_m'          => [15, 70],
    'avg_age_f'          => [15, 70],
    'divorces_total'     => [0, 100000],
    'divorces_per_1000'  => [0, 20],
    'avg_duration'       => [0, 60],
];

// Spalten mit Ganzzahlen, alle anderen sind Dezimalzahlen
const INT_COLUMNS = ['year', 'marriages_total', 'divorces_total'];

// Rohdaten aus extract.php holen
$extracted = include __DIR__ . '/extract.php';
$rawRows = $extracted['data'];

// Kontrollzahlen für etl_audit
$audit = [
    'source_rows'            => count($rawRows) + $extracted['skipped_rows'],
    'skipped_rows'           => $extracted['skipped_rows'],
    'invalid_rows'           => 0,
    'duplicate_rows_removed' => 0,
    'output_rows'            => 0,
];

// Ergebnis, Jahr als Schlüssel (dient auch zur Dublettenprüfung)
$rowsByYear = [];

foreach ($rawRows as $row) {
    $clean = [];
    $valid = true;

    // Jeden Wert prüfen und umwandeln
    foreach (RULES as $column => [$min, $max]) {
        $value = $row[$column];

        // Kein Zahlwert (z. B. leer, "...", "X")
        if (!is_numeric($value)) {
            $valid = false;
            break;
        }

        // In int oder float umwandeln
        $number = in_array($column, INT_COLUMNS, true) ? (int) $value : (float) $value;

        // Ausserhalb des plausiblen Bereichs
        if ($number < $min || $number > $max) {
            $valid = false;
            break;
        }

        $clean[$column] = $number;
    }

    // Ungültige Zeile verwerfen und zählen
    if (!$valid) {
        $audit['invalid_rows']++;
        continue;
    }

    // Jahr schon vorhanden: erste Zeile behalten, Dublette zählen
    if (isset($rowsByYear[$clean['year']])) {
        $audit['duplicate_rows_removed']++;
        continue;
    }

    $rowsByYear[$clean['year']] = $clean;
}

// Nach Jahr sortieren, Schlüssel entfernen
ksort($rowsByYear);
$rows = array_values($rowsByYear);

$audit['output_rows'] = count($rows);

// Ergebnis für load.php
return [
    'question' => 'Heiraten wir später und halten Ehen länger?',
    'rules' => [
        'ranges' => RULES,
        'duplicate_key' => 'year',
    ],
    'data' => $rows,
    'audit' => $audit,
];