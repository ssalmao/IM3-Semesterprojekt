<?php

declare(strict_types=1);

// Quelldatei und erwartete Spalten
$source = __DIR__ . '/../data/raw/ehen-scheidungen.csv';

$expectedColumns = [
    'year',
    'marriages_total',
    'marriages_per_1000',
    'avg_age_m',
    'avg_age_f',
    'divorces_total',
    'divorces_per_1000',
    'avg_duration',
];

$rawRows = [];
$skippedRows = 0;

// Datei öffnen, bei Fehler abbrechen
$handle = fopen($source, 'rb');

if ($handle === false) {
    throw new RuntimeException("Datei nicht gefunden: {$source}");
}

// Kopfzeile lesen
$headers = fgetcsv($handle, 0, ',', '"', '');

if ($headers === false) {
    fclose($handle);
    throw new RuntimeException("Keine Kopfzeile in {$source}");
}

// Kopfzeile bereinigen (BOM von Excel, Leerzeichen)
$headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
$headers = array_map('trim', $headers);

// Prüfen, ob alle Spalten vorhanden sind
$missingColumns = array_diff($expectedColumns, $headers);

if ($missingColumns !== []) {
    fclose($handle);
    throw new RuntimeException('Fehlende Spalten: ' . implode(', ', $missingColumns));
}

// Datenzeilen lesen
while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
    // Leerzeile ignorieren
    if ($values === [null]) {
        continue;
    }

    // Zeile mit falscher Feldanzahl überspringen und zählen
    if (count($values) !== count($headers)) {
        $skippedRows++;
        continue;
    }

    // Spaltennamen als Schlüssel, nur erwartete Spalten übernehmen
    $row = array_combine($headers, array_map('trim', $values));
    $rawRows[] = array_intersect_key($row, array_flip($expectedColumns));
}

fclose($handle);

// Ergebnis für transform.php
return [
    'source' => basename($source),
    'skipped_rows' => $skippedRows,
    'data' => $rawRows,
];