<?php
/**
 * Unload – liest die Jahreswerte aus MySQL und liefert den Datenvertrag für
 * Chart.js als JSON.
 *
 * GET unload.php                   alle Jahre
 * GET unload.php?from=2000&to=2025 nur ein Zeitraum (beide optional)
 *
 *   MySQL -> PDO/SELECT -> Aufbereitung in PHP -> JSON -> script.js
 *
 * Dieser Endpunkt verändert keine Daten. Aufbau identisch mit der
 * Fallback-Datei data/story-summary.json.
 */

declare(strict_types=1);

// Antwort als JSON mit UTF-8 (Umlaute)
header('Content-Type: application/json; charset=utf-8');

// config.php liegt neben unload.php
$configPath = __DIR__ . '/config.php';

// Ohne Config: 503, Frontend lädt die Fallback-Datei
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode([
        'error' => 'config.php fehlt. Die Story verwendet die Fallback-Datei.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require $configPath;

/**
 * Formt die Datenbankzeilen in den JSON-Datenvertrag der Story um.
 *
 * @param array $rows  Jahreszeilen aus yearly_stats, nach Jahr sortiert
 * @param array $audit Kontrollwerte aus etl_audit
 * @return array       vollständiger Datenvertrag
 */
function buildStoryContract(array $rows, array $audit): array
{
    // MySQL liefert Zahlen als Text: Spalte als int- oder float-Liste holen
    $ints = fn(string $key): array => array_map(fn($v): int => (int) $v, array_column($rows, $key));
    $floats = fn(string $key): array => array_map(fn($v): float => (float) $v, array_column($rows, $key));

    // Diagrammreihen
    $years = $ints('year');
    $marriages = $ints('marriages_total');
    $divorces = $ints('divorces_total');
    $ageMen = $floats('avg_age_m');
    $ageWomen = $floats('avg_age_f');
    $duration = $floats('avg_duration');

    // Altersunterschied Mann/Frau pro Jahr
    $ageGap = array_map(
        fn(float $m, float $f): float => round($m - $f, 1),
        $ageMen,
        $ageWomen,
    );

    // Kennzahlen für Texte und KPI-Kacheln (nur wenn Daten vorhanden)
    $summary = [];

    if ($rows !== []) {
        $last = count($years) - 1;

        // Jahr mit dem Höchstwert finden
        $peak = fn(array $values): array => [
            'year' => $years[array_search(max($values), $values, true)],
            'value' => max($values),
        ];

        $summary = [
            'peak_marriages' => $peak($marriages),
            'peak_divorces' => $peak($divorces),
            'age_change_men' => round($ageMen[$last] - $ageMen[0], 1),
            'age_change_women' => round($ageWomen[$last] - $ageWomen[0], 1),
            'duration_change' => round($duration[$last] - $duration[0], 1),
        ];
    }

    return [
        'meta' => [
            'question' => 'Heiraten wir später und halten Ehen länger?',
            'source' => 'BFS',
            'years' => [
                'from' => $years[0] ?? null,
                'to' => $years[array_key_last($years)] ?? null,
            ],
            // Legendentexte für Chart.js
            'series_labels' => [
                'men' => 'Männer',
                'women' => 'Frauen',
                'total' => 'Total',
                'per_1000' => 'pro 1000 Einwohner',
            ],
        ],
        'audit' => $audit,
        'summary' => $summary,
        'years' => $years,
        'marriages' => [
            'total' => $marriages,
            'per_1000' => $floats('marriages_per_1000'),
        ],
        'divorces' => [
            'total' => $divorces,
            'per_1000' => $floats('divorces_per_1000'),
        ],
        'age' => [
            'men' => $ageMen,
            'women' => $ageWomen,
            'gap' => $ageGap,
        ],
        'duration' => $duration,
    ];
}

// Filter aus der URL lesen; fehlt er, gilt der ganze Zeitraum
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

// Nur vierstellige Jahreszahlen erlauben, sonst 400
foreach ([$from, $to] as $value) {
    if ($value !== '' && !preg_match('/^\d{4}$/', $value)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültiges Jahr.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Von muss vor Bis liegen
if ($from !== '' && $to !== '' && (int) $from > (int) $to) {
    http_response_code(400);
    echo json_encode(['error' => 'from muss kleiner oder gleich to sein.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);

    // SQL mit optionalen Filtern zusammensetzen
    $sql = 'SELECT year, marriages_total, marriages_per_1000, avg_age_m, avg_age_f,
                   divorces_total, divorces_per_1000, avg_duration
            FROM yearly_stats';
    $conditions = [];
    $params = [];

    if ($from !== '') {
        $conditions[] = 'year >= :from';
        $params['from'] = (int) $from;
    }

    if ($to !== '') {
        $conditions[] = 'year <= :to';
        $params['to'] = (int) $to;
    }

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY year';

    // Abfrage mit Platzhaltern (Schutz vor SQL-Injection)
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    // Audit-Werte als assoziatives Array
    $audit = [];

    foreach ($pdo->query('SELECT metric, value FROM etl_audit')->fetchAll() as $row) {
        $audit[$row['metric']] = (int) $row['value'];
    }

    // Datenvertrag als JSON ausgeben
    echo json_encode(
        buildStoryContract($rows, $audit),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    );
} catch (Throwable $error) {
    // Details nur ins Server-Log, Browser bekommt eine neutrale Meldung
    http_response_code(500);
    error_log('unload.php: ' . $error->getMessage());

    echo json_encode([
        'error' => 'Daten konnten nicht aus der Datenbank geladen werden.',
    ], JSON_UNESCAPED_UNICODE);
}