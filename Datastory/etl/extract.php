<?php
/**
 * Extract – holt die Daten live von der BFS-Schnittstelle (STAT-TAB, PxWeb API).
 *
 *   BFS-API (2 Tabellen) -> nach Jahr zusammenführen -> PHP-Array
 *
 * Ist die API nicht erreichbar, wird die lokale CSV verwendet (Rückfallebene).
 * Nach jedem erfolgreichen Abruf wird diese CSV aktualisiert.
 * Verändert keine Werte, das macht transform.php.
 */

declare(strict_types=1);

// Daten ab 1975
const START_YEAR = 1975;

// Lokale Kopie
const LOCAL_CSV = __DIR__ . '/../data/raw/ehen-scheidungen.csv';

// Eigene Spaltennamen
const SOURCES = [
    'px-x-0102020202_110' => [            // Heiraten und Heiratshäufigkeit
        '0'  => 'marriages_total',
        '31' => 'marriages_per_1000',
        '32' => 'avg_age_m',
        '33' => 'avg_age_f',
    ],
    'px-x-0102020203_110' => [            // Scheidungen und Scheidungshäufigkeit
        '0'  => 'divorces_total',
        '21' => 'avg_duration',
        '22' => 'divorces_per_1000',
    ],
];

// Reihenfolge der Spalten
const COLUMNS = [
    'year',
    'marriages_total',
    'marriages_per_1000',
    'avg_age_m',
    'avg_age_f',
    'divorces_total',
    'divorces_per_1000',
    'avg_duration',
];

function fetchBfsTable(string $table, array $codes): array
{
    $url = "https://www.pxweb.bfs.admin.ch/api/v1/de/{$table}/{$table}.px";

    // Abfrage: benötigten Indikatoren, alle Jahre, Antwort als JSON
    $query = json_encode([
        'query' => [[
            'code' => 'Demografisches Merkmal und Indikator',
            'selection' => ['filter' => 'item', 'values' => array_map('strval', array_keys($codes))],
        ]],
        'response' => ['format' => 'json'],
    ], JSON_THROW_ON_ERROR);

    // POST-Anfrage mit cURL
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $query,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $status !== 200) {
        throw new RuntimeException("BFS-API nicht erreichbar ({$table}, HTTP {$status})");
    }

    $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    // Antwort umformen
    $byYear = [];

    foreach ($response['data'] ?? [] as $entry) {
        [$year, $code] = $entry['key'];
        $byYear[$year][$codes[$code]] = $entry['values'][0];
    }

    return $byYear;
}

    // Liest die lokale CSV

function readLocalCsv(string $path): array
{
    $handle = fopen($path, 'rb');

    if ($handle === false) {
        throw new RuntimeException("Datei nicht gefunden: {$path}");
    }

    // Kopfzeile bereinigen
    $headers = fgetcsv($handle, 0, ',', '"', '');
    $headers[0] = preg_replace('/^\x{FEFF}/u', '', (string) $headers[0]);
    $headers = array_map('trim', $headers);

    $missing = array_diff(COLUMNS, $headers);

    if ($missing !== []) {
        fclose($handle);
        throw new RuntimeException('Fehlende Spalten: ' . implode(', ', $missing));
    }

    $rows = [];

    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        // Leerzeilen und fehlerhafte Zeilen überspringen
        if ($values === [null] || count($values) !== count($headers)) {
            continue;
        }

        $row = array_combine($headers, array_map('trim', $values));
        $rows[] = array_intersect_key($row, array_flip(COLUMNS));
    }

    fclose($handle);

    return $rows;
}

/**
 * Speichert die Zeilen als CSV, damit die Rückfallebene aktuell bleibt.
 */
function writeLocalCsv(string $path, array $rows): void
{
    $handle = @fopen($path, 'wb');

    // Kein Abbruch, falls der Ordner schreibgeschützt ist
    if ($handle === false) {
        error_log("extract.php: CSV konnte nicht gespeichert werden ({$path})");
        return;
    }

    fputcsv($handle, COLUMNS, ',', '"', '');

    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '');
    }

    fclose($handle);
}

// --- Ablauf -----------------------------------------------------------------

$rawRows = [];
$skippedRows = 0;

try {
    // 1. Beide Tabellen von der BFS-API holen
    $tables = [];

    foreach (SOURCES as $table => $codes) {
        $tables[] = fetchBfsTable($table, $codes);
    }

    // 2. Nach Jahr zusammenführen (nur Jahre, die in beiden Tabellen vorkommen)
    $years = array_intersect(...array_map('array_keys', $tables));
    sort($years);

    foreach ($years as $year) {
        if ((int) $year < START_YEAR) {
            continue;
        }

        $merged = ['year' => (string) $year] + array_merge(...array_map(fn(array $t): array => $t[$year], $tables));

        // Spalten in fester Reihenfolge
        $rawRows[] = array_merge(array_flip(COLUMNS), $merged);
    }

    // Jahre ab START_YEAR, die nur in einer Tabelle vorkommen
    $allYears = array_unique(array_merge(...array_map('array_keys', $tables)));
    $skippedRows = count(array_filter($allYears, fn($y): bool => (int) $y >= START_YEAR)) - count($rawRows);

    // 3. Lokale Kopie aktualisieren
    writeLocalCsv(LOCAL_CSV, $rawRows);

    $source = 'BFS STAT-TAB: ' . implode(', ', array_keys(SOURCES));
    $fromApi = true;
} catch (Throwable $error) {
    // API nicht erreichbar: lokale CSV verwenden
    error_log('extract.php: ' . $error->getMessage() . ' – verwende lokale CSV.');

    $rawRows = readLocalCsv(LOCAL_CSV);
    $source = basename(LOCAL_CSV);
    $fromApi = false;
}

// Ergebnis für transform.php
return [
    'source' => $source,
    'from_api' => $fromApi,
    'skipped_rows' => $skippedRows,
    'data' => $rawRows,
];