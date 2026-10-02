<?php
/**
 * Unload – liest die Zeitblöcke aus MySQL und liefert den Datenvertrag für
 * Chart.js als JSON.
 *
 * GET unload.php                 alle vier Semester
 * GET unload.php?semester=FS27   nur ein Semester
 *
 * Unload ist die Schnittstelle zwischen Datenbank und Frontend:
 *
 *   MySQL -> PDO/SELECT -> Aggregation in PHP -> JSON -> script.js
 *
 * Anders als Load verändert dieser Endpunkt keine Daten. Er liest die feinen
 * Zeitblöcke und fasst sie bereits zu den Reihen zusammen, die Chart.js
 * benötigt. So bleibt der Datenvertrag für Datenbank und Fallback identisch.
 */

// Funktionsargumente und Rückgabewerte sollen strikt zu ihren Typangaben passen.
declare(strict_types=1);

// fetch() im Browser prüft diesen Header, bevor es response.json() aufruft.
// UTF-8 ist für Umlaute in Semesterbezeichnungen und Modulen notwendig.
header('Content-Type: application/json; charset=utf-8');

// config.php liegt neben unload.php. __DIR__ macht den Pfad unabhängig davon,
// von welchem Arbeitsverzeichnis der Webserver die Datei ausführt.
$configPath = __DIR__ . '/config.php';

// Nützliche Debug-Ausgabe während der Entwicklung. Sie bleibt auskommentiert,
// weil jeder zusätzliche Text vor dem JSON die Antwort ungültig machen würde.
//echo $configPath;

// Ohne Zugangsdaten kann der Endpunkt nicht auf MySQL zugreifen. Der Status
// 503 teilt script.js mit, dass der Dienst gerade nicht verfügbar ist. Das
// Frontend reagiert darauf und lädt seine lokale Fallback-Datei.
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode([
        'error' => 'config.php fehlt. Die Story verwendet die Fallback-Datei.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Stellt $dsn, $username, $password und die gemeinsamen PDO-Optionen bereit.
require $configPath;

/**
 * Formt Datenbankzeilen in den JSON-Datenvertrag der Story um.
 *
 * Die Datenbank enthält eine Zeile pro Zeitfenster. Das Frontend benötigt
 * dagegen Zusammenfassungen pro Semester sowie drei Diagrammreihen:
 * Wochen, Wochentage und Unterrichtsmodus.
 *
 * @param array $rows  Zeitfenster aus schedule_slots mit Semestercode
 * @param array $audit Kontrollwerte aus etl_audit
 * @return array       vollständiger, JSON-fähiger Datenvertrag
 */
function buildStoryContract(array $rows, array $audit): array
{
    // Stabile Semesterreihenfolge und lesbare Bezeichnungen. Der SQL-Endpunkt
    // kann auch nur ein Semester liefern; die Reihenfolge bleibt trotzdem
    // dieselbe wie im gesamten Studienverlauf.
    $semesterDefinitions = [
        'HS25' => ['name' => '1. Semester', 'sort_order' => 1],
        'FS26' => ['name' => '2. Semester', 'sort_order' => 2],
        'HS26' => ['name' => '3. Semester', 'sort_order' => 3],
        'FS27' => ['name' => '4. Semester', 'sort_order' => 4],
    ];

    // Chart.js verwendet die Beschriftungen in genau dieser Reihenfolge. Da
    // der untersuchte Stundenplan keine Wochenendtermine enthält, genügen fünf.
    $weekdayLabels = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'];

    // Zuerst werden die flachen SQL-Zeilen nach Semester gruppiert. Das macht
    // die anschliessenden Berechnungen übersichtlicher und verhindert, dass in
    // jeder Kennzahl erneut über alle Semester gefiltert werden muss.
    $rowsBySemester = [];

    foreach ($rows as $row) {
        // [] hängt die aktuelle Zeile an die Liste ihres Semestercodes an.
        // Beim ersten Auftreten erzeugt PHP dieses Unterarray automatisch.
        $rowsBySemester[$row['semester']][] = $row;
    }

    // Jede Liste entspricht später einem obersten Schlüssel der JSON-Antwort.
    $semesterSummaries = [];
    $weeklySeries = [];
    $weekdaySeries = [];
    $modeSeries = [];

    // Die Definitionsliste bestimmt bewusst die Reihenfolge. Eine Schleife
    // direkt über SQL-Zeilen wäre von der gelieferten Datenmenge abhängig.
    foreach ($semesterDefinitions as $semester => $definition) {
        // Der Null-Coalescing-Operator ?? liefert eine leere Liste, wenn der
        // Endpoint durch ?semester=... nur ein anderes Semester erhalten hat.
        $semesterRows = $rowsBySemester[$semester] ?? [];

        // Für nicht angefragte oder leere Semester wird keine Null-Reihe gebaut.
        if ($semesterRows === []) {
            continue;
        }

        // Die erste und letzte Zeile bestimmen später die Semesterlänge. Daher
        // wird unabhängig von der SQL-Sortierung nochmals chronologisch sortiert.
        usort(
            $semesterRows,
            fn(array $a, array $b): int => $a['starts_at'] <=> $b['starts_at'],
        );

        // Der erste und letzte Termin begrenzen die sichtbare Zeitachse. Mit
        // "monday this week" werden beide auf den Montag ihrer Kalenderwoche
        // gesetzt. setTime(0, 0) entfernt Uhrzeiten aus der Differenz.
        $firstStart = new DateTimeImmutable($semesterRows[0]['starts_at']);
        $lastStart = new DateTimeImmutable($semesterRows[array_key_last($semesterRows)]['starts_at']);
        $firstMonday = $firstStart->modify('monday this week')->setTime(0, 0);
        $lastMonday = $lastStart->modify('monday this week')->setTime(0, 0);

        // 604800 Sekunden entsprechen sieben Tagen. +1 zählt auch die erste
        // Woche mit; liegen beide Termine in derselben Woche, ist das Ergebnis 1.
        $calendarWeeks = (int) (($lastMonday->getTimestamp() - $firstMonday->getTimestamp()) / 604800) + 1;

        // Leere Wochen müssen als Nullen vorhanden sein, damit das Liniendiagramm
        // Pausen im Semester zeigt und nicht zwei Unterrichtswochen verbindet.
        // Die Indizes beginnen absichtlich bei 1 und entsprechen der sichtbaren
        // relativen Wochennummer.
        $weeks = array_fill(1, $calendarWeeks, 0.0);

        // Ebenso werden Montag (1) bis Freitag (5) mit null Stunden vorbereitet.
        $weekdays = array_fill(1, 5, 0.0);

        // Diese Schlüssel sind derselbe kontrollierte Wortschatz, den Transform
        // in schedule_slots.mode speichert.
        $modes = ['in_person' => 0.0, 'online' => 0.0, 'unclear' => 0.0];

        // Assoziative Schlüssel funktionieren hier wie ein Set: Jedes Datum
        // kommt unabhängig von der Anzahl Blöcke höchstens einmal vor.
        $days = [];
        $hours = 0.0;

        // Ein einziger Durchlauf füllt alle Aggregationen des Semesters.
        foreach ($semesterRows as $row) {
            $start = new DateTimeImmutable($row['starts_at']);
            $weekMonday = $start->modify('monday this week')->setTime(0, 0);

            // Differenz zur ersten Semesterwoche, erneut in Siebentagesblöcken.
            $relativeWeek = (int) (($weekMonday->getTimestamp() - $firstMonday->getTimestamp()) / 604800) + 1;

            // DECIMAL-Werte kommen aus MySQL häufig als Text. Der Cast macht
            // daraus eine Zahl, damit PHP addiert statt Zeichenketten zu nutzen.
            $duration = (float) $row['duration_hours'];
            $weekday = (int) $row['weekday'];

            // Derselbe Block fliesst in vier verschiedene Sichtweisen ein.
            $weeks[$relativeWeek] += $duration;

            // Sicherheitshalber werden Samstag und Sonntag nicht in das
            // Fünf-Tage-Diagramm geschrieben.
            if ($weekday <= 5) {
                $weekdays[$weekday] += $duration;
            }

            $modes[$row['mode']] += $duration;
            $days[$start->format('Y-m-d')] = true;
            $hours += $duration;
        }

        // array_filter() behält nur Wochen mit mehr als null Stunden. max()
        // findet die höchste Belastung, array_search() ihre relative Nummer.
        $activeWeeks = count(array_filter($weeks, fn(float $value): bool => $value > 0));
        $peakHours = max($weeks);
        $peakWeek = array_search($peakHours, $weeks, true);

        // Kompakte Kennzahlen pro Semester für Text und KPI-Anzeigen.
        $semesterSummaries[] = [
            'semester' => $semester,
            'name' => $definition['name'],
            'blocks' => count($semesterRows),
            'hours' => round($hours, 2),
            'days' => count($days),
            'active_weeks' => $activeWeeks,
            'calendar_weeks' => $calendarWeeks,
            'peak_week' => $peakWeek,
            'peak_hours' => round($peakHours, 2),
        ];

        // Das Liniendiagramm erwartet pro Semester eine geordnete Werteliste.
        // array_map() rundet Darstellungswerte, array_values() entfernt die bei
        // 1 beginnenden PHP-Schlüssel für ein sauberes JSON-Array.
        $weeklySeries[] = [
            'semester' => $semester,
            'name' => $definition['name'],
            'values' => array_values(array_map(fn(float $value): float => round($value, 2), $weeks)),
        ];

        // Die fünf Werte entsprechen exakt weekday_labels oben.
        $weekdaySeries[] = [
            'semester' => $semester,
            'name' => $definition['name'],
            'values' => array_values(array_map(fn(float $value): float => round($value, 2), $weekdays)),
        ];

        // Beim Modus bleiben die Schlüssel erhalten. script.js kann dadurch
        // row.values.online lesen, ohne sich auf eine Position zu verlassen.
        $modeSeries[] = [
            'semester' => $semester,
            'name' => $definition['name'],
            'values' => array_map(fn(float $value): float => round($value, 2), $modes),
        ];
    }

    // Der Datenvertrag trennt Metadaten, Audit und die vier fachlichen Reihen.
    // Sämtliche Werte bestehen aus Arrays, Texten, Zahlen und Booleans und sind
    // deshalb direkt mit json_encode() serialisierbar.
    return [
        'meta' => [
            'class' => 'mmp25c2',
            'question' => 'Wie gleichmässig ist ein Semester wirklich?',

            // Enthält bei einem Filter nur das tatsächlich gelieferte Semester.
            'semester_order' => array_column($semesterSummaries, 'semester'),
            'weekday_labels' => $weekdayLabels,

            // Technische Datenbankschlüssel werden hier für die Legende übersetzt.
            'mode_labels' => [
                'in_person' => 'Präsenz',
                'online' => 'Online',
                'unclear' => 'Raum noch offen',
            ],
        ],
        'audit' => $audit,
        'semesters' => $semesterSummaries,
        'weekly' => $weeklySeries,
        'weekdays' => $weekdaySeries,
        'modes' => $modeSeries,
    ];
}

// $_GET enthält Query-Parameter aus der URL. Fehlt semester, wird ein leerer
// Text verwendet und damit der vollständige Datenstand angefragt. trim()
// entfernt versehentliche Leerzeichen; strtoupper() erlaubt auch "fs27".
$semester = strtoupper(trim($_GET['semester'] ?? ''));

// Die Whitelist verhindert sowohl Tippfehler als auch beliebige Filterwerte.
// Der leere Text ist die ausdrücklich erlaubte Variante "alle Semester".
$allowedSemesters = ['', 'HS25', 'FS26', 'HS26', 'FS27'];

// Strikter Vergleich stellt sicher, dass nicht nur ähnlich aussehende Werte
// akzeptiert werden. Bei einem Fehler endet der Endpoint mit HTTP 400.
if (!in_array($semester, $allowedSemesters, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unbekanntes Semester.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Datenbank-, Datums- und JSON-Fehler werden gemeinsam abgefangen. Der Browser
// erhält eine kontrollierte Antwort; technische Details landen im Server-Log.
try {
    // Verbindungsdaten und Optionen stammen aus config.php.
    $pdo = new PDO($dsn, $username, $password, $options);

    // Der JOIN übersetzt semester_id in fachliche Semesterinformationen. Für
    // die Diagramme werden nur die tatsächlich benötigten Spalten gelesen.
    $sql = 'SELECT s.code AS semester,
                   s.name AS semester_name,
                   ss.starts_at,
                   ss.duration_hours,
                   ss.weekday,
                   ss.mode
            FROM schedule_slots AS ss
            JOIN semesters AS s ON s.id = ss.semester_id';

    // Parameter werden getrennt vom SQL gesammelt und später an execute()
    // übergeben. Beim Abruf aller Semester bleibt das Array leer.
    $params = [];

    if ($semester !== '') {
        // Der benannte Platzhalter schützt vor SQL-Injection und übergibt den
        // bereits gegen die Whitelist geprüften Semesterwert.
        $sql .= ' WHERE s.code = :semester';
        $params['semester'] = $semester;
    }

    // Stabile Reihenfolge für Aggregation, Tests und lesbare Rohantworten.
    $sql .= ' ORDER BY s.sort_order, ss.starts_at';

    // Debug-Ausgabe bleibt deaktiviert: SQL-Text vor dem JSON würde den
    // Datenvertrag beschädigen und response.json() im Browser scheitern lassen.
    //echo $sql;

    // prepare() verarbeitet den SQL-Bauplan; execute() setzt Filterwerte ein.
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    // Durch PDO::FETCH_ASSOC aus config.php sind die Spaltennamen Array-Schlüssel.
    $rows = $statement->fetchAll();

    // Die Audit-Tabelle ist klein und benötigt keinen vom Nutzer kommenden
    // Filter. query() genügt deshalb; Platzhalter sind hier nicht erforderlich.
    $auditRows = $pdo->query('SELECT metric, value FROM etl_audit')->fetchAll();
    $audit = [];

    // Aus Datenbankzeilen wird wieder dasselbe assoziative Audit-Array, das
    // transform.php ursprünglich geliefert hat.
    foreach ($auditRows as $row) {
        $audit[$row['metric']] = (int) $row['value'];
    }

    // Zuerst wird der Story-Vertrag aufgebaut und anschliessend als JSON
    // ausgegeben. JSON_THROW_ON_ERROR verhindert stille leere Antworten;
    // JSON_UNESCAPED_UNICODE lässt Umlaute für Menschen lesbar.
    echo json_encode(
        buildStoryContract($rows, $audit),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    );
} catch (Throwable $error) {
    // Keine internen Server-, SQL- oder Zugangsdaten an den Browser senden.
    // Die konkrete Meldung ist nur im geschützten Server-Log sichtbar.
    http_response_code(500);
    error_log('unload.php: ' . $error->getMessage());

    // script.js erkennt sowohl den HTTP-Status als auch dieses error-Feld und
    // wechselt anschliessend zur lokalen Datei schedule-summary.json.
    echo json_encode([
        'error' => 'Daten konnten nicht aus der Datenbank geladen werden.',
    ], JSON_UNESCAPED_UNICODE);
}
