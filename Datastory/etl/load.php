<?php
/**
 * Load – schreibt die bereinigten Zeitblöcke mit PDO in MySQL.
 *
 * Vorher etl/stundenplan.sql in phpMyAdmin ausführen. Danach diese Datei einmal
 * über die eigene Domain aufrufen.
 *
 * Diese Datei ist der letzte Schritt des ETL-Prozesses:
 *
 *   transform.php -> PHP-Arrays -> vorbereitete INSERTs -> MySQL
 *
 * Alle Schreiboperationen laufen in einer Transaktion. Entweder wird der
 * komplette neue Datenstand gespeichert oder bei einem Fehler gar nichts.
 */

// Aktiviert strikte Typprüfung für Funktionsaufrufe in dieser Datei.
declare(strict_types=1);

// load.php ist ein administratives Werkzeug und keine gestaltete Webseite.
// Die Fortschrittsmeldungen werden deshalb als gut lesbarer Klartext gesendet.
header('Content-Type: text/plain; charset=utf-8');

// Die Zugangsdaten liegen ausserhalb des ETL-Unterordners im Projektstamm.
// __DIR__ zeigt auf etl; /.. wechselt genau eine Ebene nach oben.
$configPath = __DIR__ . '/../config.php';

// Diese Ausgabe dient als einfache Kontrolle, welche Konfigurationsdatei auf
// dem Server tatsächlich verwendet wird.
echo $configPath;

// Ohne Konfiguration ist keine Datenbankverbindung möglich. HTTP 503 bedeutet
// "Service Unavailable" und signalisiert einen Konfigurationsfehler, nicht
// einen Fehler in den Stundenplandaten.
if (!is_file($configPath)) {
    http_response_code(503);
    exit("config.php fehlt im Hauptordner des Kurs-Repositories.\n");
}

// require bricht im Gegensatz zu include ab, falls die benötigte Datei trotz
// der vorangehenden Prüfung nicht geladen werden kann. Danach stehen $dsn,
// $username, $password und $options zur Verfügung.
require $configPath;

// transform.php führt intern zuerst extract.php aus und gibt danach den
// vollständigen Datenvertrag zurück. Load benötigt daraus drei Teile:
// die Zeitfenster, die Semester-Stammdaten und das Audit-Protokoll.
$result = include __DIR__ . '/transform.php';
$rows = $result['data'];
$semesters = $result['semesters'];
$audit = $result['audit'];

// Frühe Kontrollausgabe: Schon vor der Verbindung ist sichtbar, wie viele
// bereinigte Zeitfenster überhaupt geschrieben werden sollen.
echo 'Der Transform liefert ' . count($rows) . " Zeitblöcke.\n\n";

// PDO- und SQL-Fehler werden durch die Optionen aus config.php als Exceptions
// ausgelöst und gemeinsam im catch-Block behandelt.
try {
    // PDO baut die Verbindung zum in $dsn beschriebenen MySQL-Server auf.
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Verbindung steht.\n\n";

    // Ab hier bilden alle Änderungen eine Einheit. commit() speichert sie
    // endgültig; rollBack() nimmt sie bei einem Fehler vollständig zurück.
    $pdo->beginTransaction();

    // Die Semester sind Stammdaten und stehen in einer eigenen Tabelle. Für
    // jedes Semester gilt das Muster "suchen, sonst anlegen, sonst ändern".
    // Der Code HS25/FS26/... ist durch das SQL-Schema eindeutig.
    //
    // prepare() schickt den SQL-Bauplan einmal an die Datenbank. execute()
    // liefert später nur noch die Werte für ? oder die benannten Platzhalter.
    $findSemester = $pdo->prepare('SELECT id FROM semesters WHERE code = ?');
    $insertSemester = $pdo->prepare(
        'INSERT INTO semesters (code, name, sort_order) VALUES (:code, :name, :sort_order)'
    );
    $updateSemester = $pdo->prepare(
        'UPDATE semesters SET name = :name, sort_order = :sort_order WHERE id = :id'
    );

    // schedule_slots speichert nicht das Kürzel HS25, sondern die numerische
    // Fremdschlüssel-ID. Dieses Array merkt sich deshalb code -> id, damit die
    // ID beim späteren INSERT nicht für jede Zeile neu gesucht werden muss.
    $semesterIds = [];

    // Die vier Semesterdefinitionen stammen direkt aus transform.php.
    foreach ($semesters as $code => $semester) {
        // Der einzige positionale Platzhalter ? erhält den Semester-Code.
        $findSemester->execute([$code]);

        // fetchColumn() liest die erste Spalte der ersten Trefferzeile. Gibt es
        // kein passendes Semester, liefert die Methode exakt false.
        $id = $findSemester->fetchColumn();

        if ($id === false) {
            // Das Semester fehlt und wird neu angelegt. Benannte Platzhalter
            // machen sichtbar, welcher PHP-Wert in welche SQL-Spalte gehört.
            $insertSemester->execute([
                'code' => $code,
                'name' => $semester['name'],
                'sort_order' => $semester['sort_order'],
            ]);

            // Die automatisch vergebene ID wird für die Zeitfenster benötigt.
            $id = $pdo->lastInsertId();
        } else {
            // Ein vorhandenes Semester bleibt bestehen, erhält aber bei Bedarf
            // den aktuellen Anzeigenamen und die aktuelle Sortierreihenfolge.
            $updateSemester->execute([
                'name' => $semester['name'],
                'sort_order' => $semester['sort_order'],
                'id' => $id,
            ]);
        }

        // PDO liefert IDs häufig als Text. Der explizite Cast stellt sicher,
        // dass im Merkarray tatsächlich eine Ganzzahl gespeichert wird.
        $semesterIds[$code] = (int) $id;
    }

    // Die CSVs sind der vollständige Datenstand. Deshalb wird die Faktentabelle
    // bei jedem Lauf ersetzt und nicht Zeile für Zeile ergänzt.
    // DELETE bleibt innerhalb der Transaktion rückgängig zu machen. Das Audit
    // wird ebenfalls ersetzt, damit seine Zahlen genau zu den Slots passen.
    $deletedSlots = $pdo->exec('DELETE FROM schedule_slots');
    $pdo->exec('DELETE FROM etl_audit');

    echo $deletedSlots . " alte Zeitblöcke gelöscht.\n\n";

    // Das INSERT für die Faktentabelle wird genau einmal vorbereitet. In der
    // folgenden Schleife werden nur die Werte ausgetauscht. Das ist schneller
    // und sicherer als zusammengesetzte SQL-Strings.
    $insertSlot = $pdo->prepare(
        'INSERT INTO schedule_slots
            (semester_id, class_code, course_name, starts_at, duration_hours, weekday, mode, room)
         VALUES
            (:semester_id, :class_code, :course_name, :starts_at, :duration_hours, :weekday, :mode, :room)'
    );

    // Jede transformierte Zeile wird zu genau einer Datenbankzeile.
    foreach ($rows as $row) {
        $insertSlot->execute([
            // Umwandlung vom fachlichen Code zur zuvor ermittelten ID.
            'semester_id' => $semesterIds[$row['semester']],

            // Die übrigen Felder entsprechen bereits dem Datenbankschema und
            // werden deshalb ohne weitere Berechnung übernommen.
            'class_code' => $row['class_code'],
            'course_name' => $row['course_name'],
            'starts_at' => $row['starts_at'],
            'duration_hours' => $row['duration_hours'],
            'weekday' => $row['weekday'],
            'mode' => $row['mode'],
            'room' => $row['room'],
        ]);
    }

    // Auch die Kontrollzahlen des Transforms werden gespeichert. Unload kann
    // sie dadurch zusammen mit den Diagrammdaten ans Frontend ausliefern.
    $insertAudit = $pdo->prepare(
        'INSERT INTO etl_audit (metric, value) VALUES (:metric, :value)'
    );

    // Aus dem assoziativen Array wird eine Zeile pro Kennzahl, zum Beispiel
    // metric="duplicate_rows_removed" und value=43.
    foreach ($audit as $metric => $value) {
        $insertAudit->execute([
            'metric' => $metric,
            'value' => $value,
        ]);
    }

    // Erst jetzt werden alle seit beginTransaction() ausgeführten Änderungen
    // gemeinsam dauerhaft sichtbar.
    $pdo->commit();

    // Zusammenfassung für die Person, die load.php im Browser aufgerufen hat.
    echo count($rows) . " Zeitblöcke geschrieben.\n";
    echo count($audit) . " Prüfwerte geschrieben.\n\n";

    // Erste Plausibilitätskontrolle direkt aus der Datenbank: Die Anzahl muss
    // mit count($rows) übereinstimmen.
    $total = $pdo->query('SELECT COUNT(*) FROM schedule_slots')->fetchColumn();
    echo "In schedule_slots stehen jetzt {$total} Zeilen.\n\n";

    // Eine zweite Kontrolle gruppiert den gespeicherten Datenstand pro
    // Semester. JOIN übersetzt die semester_id wieder in das lesbare Kürzel.
    $check = $pdo->query(
        'SELECT s.code, COUNT(*) AS blocks, SUM(ss.duration_hours) AS hours
         FROM schedule_slots AS ss
         JOIN semesters AS s ON s.id = ss.semester_id
         GROUP BY s.id, s.code, s.sort_order
         ORDER BY s.sort_order'
    );

    // fetchAll() liefert dank PDO::FETCH_ASSOC verständliche Spaltennamen.
    foreach ($check->fetchAll() as $semester) {
        echo $semester['code'] . ': '
            . $semester['blocks'] . ' Blöcke, '
            . $semester['hours'] . " Stunden\n";
    }
} catch (Throwable $error) {
    // Schlägt nach beginTransaction() irgendein Schritt fehl, darf kein halber
    // Datenstand bleiben. inTransaction() verhindert einen ungültigen Rollback,
    // falls bereits der Verbindungsaufbau gescheitert ist.
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Der HTTP-Status macht den Fehlschlag auch für Werkzeuge sichtbar. Die
    // konkrete Exception wird hier ausgegeben, weil load.php ein bewusst
    // aufgerufenes Administrationswerkzeug und kein öffentlicher Endpunkt ist.
    http_response_code(500);
    exit('Load fehlgeschlagen: ' . $error->getMessage() . "\n");
}
