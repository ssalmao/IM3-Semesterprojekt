<?php
/**
 * Transform – macht aus den CSV-Zeilen eindeutige Zeitblöcke.
 *
 * Eine Ergebniszeile entspricht einem belegten Zeitfenster der Klasse
 * mmp25c2. Alle Werte sind danach bereit für die Datenbank; load.php muss
 * nichts mehr berechnen oder bereinigen.
 *
 * Der Datenfluss in dieser Datei:
 *
 *   1. extrahierte CSV-Zeilen aus extract.php übernehmen
 *   2. nur die gewünschte Klasse auswählen
 *   3. Datum, Dauer, Unterrichtsmodus und Kursname bereinigen
 *   4. doppelte Zeitfenster entfernen
 *   5. die Zeitfenster chronologisch sortieren
 *   6. Daten, Regeln und Kontrollzahlen an load.php zurückgeben
 */

// PHP soll bei Funktionsaufrufen nicht stillschweigend zwischen unpassenden
// skalaren Typen umwandeln. Aus "3" wird dadurch beispielsweise nicht
// automatisch die Ganzzahl 3, wenn eine Funktion ausdrücklich int erwartet.
declare(strict_types=1);

// Diese zwei Konstanten sind Regeln des Transforms. Sie stehen bewusst ganz
// oben: Ändert sich die untersuchte Klasse oder die angenommene Blockdauer,
// muss nicht in der Verarbeitungsschleife danach gesucht werden.
//
// TARGET_CLASS begrenzt die Analyse auf genau eine Klasse. In den CSV-Dateien
// stehen daneben zahlreiche Termine anderer MMP-Klassen.
const TARGET_CLASS = 'mmp25c2';

// Ein üblicher Unterrichtsblock dauert von 09:15 bis 12:30 Uhr oder von 13:30
// bis 16:45 Uhr. Das sind 3 Stunden und 15 Minuten beziehungsweise 3.25
// Dezimalstunden. Der Wert dient weiter unten zur Reparatur eines Datenfehlers.
const STANDARD_BLOCK_HOURS = 3.25;

// Die Kürzel in den Quelldaten eignen sich gut als stabile Schlüssel. Für die
// Anzeige bekommt jedes Kürzel zusätzlich einen verständlichen Namen.
// sort_order hält die fachliche Semesterreihenfolge fest. Eine alphabetische
// Sortierung wäre falsch: FS26 würde zum Beispiel vor HS25 stehen.
$semesterNames = [
    'HS25' => ['name' => '1. Semester', 'sort_order' => 1],
    'FS26' => ['name' => '2. Semester', 'sort_order' => 2],
    'HS26' => ['name' => '3. Semester', 'sort_order' => 3],
    'FS27' => ['name' => '4. Semester', 'sort_order' => 4],
];

// include führt extract.php aus und übernimmt dessen Rückgabewert. Extract
// kennt nur das Dateiformat: Es liest die CSV-Dateien, verändert ihre Inhalte
// aber noch nicht. Die eigentlichen Rohzeilen liegen unter dem Schlüssel data.
$extracted = include __DIR__ . '/extract.php';
$rawRows = $extracted['data'];

// Das Audit-Protokoll macht die Transformation überprüfbar. Damit lässt sich
// am Ende beantworten, wie viele Zeilen eingelesen, ausgewählt, korrigiert
// oder verworfen wurden. Diese Zahlen sind keine eigentlichen Stundenplandaten
// und werden deshalb getrennt von data zurückgegeben.
$audit = [
    // Alle Zeilen aus allen vier CSV-Dateien, noch vor dem Klassenfilter.
    'source_rows' => count($rawRows),

    // Anzahl Zeilen, die tatsächlich zur Zielklasse gehören.
    'selected_rows' => 0,

    // Mehrfach vorhandene Zeitfenster, die nur einmal übernommen werden.
    'duplicate_rows_removed' => 0,

    // Offensichtlich falsche Zeitdauern, die auf 3.25 Stunden gesetzt werden.
    'duration_rows_corrected' => 0,

    // Anzahl der fertigen, eindeutigen Zeitfenster nach dem Transform.
    'output_slots' => 0,
];

// Dieses assoziative Array dient gleichzeitig als Ergebnisspeicher und als
// Dublettenprüfung. Der zusammengesetzte Schlüssel wird weiter unten gebaut.
// Existiert ein Schlüssel bereits, ist dieses Zeitfenster schon gespeichert.
$slotsByKey = [];

// Jede Rohzeile wird einzeln geprüft und in die vereinbarte Zielform gebracht.
foreach ($rawRows as $row) {
    // Die Quelldateien enthalten mehrere Klassen. continue beendet nur den
    // aktuellen Schleifendurchlauf und fährt sofort mit der nächsten Zeile
    // fort. Fremde Klassen gelangen dadurch gar nicht in die Auswertung.
    if ($row['class_code'] !== TARGET_CLASS) {
        continue;
    }

    // Ab hier wissen wir: Die Zeile gehört zu mmp25c2. Gezählt wird vor der
    // weiteren Bereinigung, damit das Audit den Klassenfilter dokumentiert.
    $audit['selected_rows']++;

    // In der CSV sind Start und Ende Texte wie "08.09.2025 09:15:00".
    // DateTimeImmutable macht daraus Datumsobjekte, mit denen sich rechnen und
    // die sich später in ein einheitliches Datenbankformat bringen lassen.
    // "Immutable" bedeutet: Änderungen erzeugen ein neues Objekt und verändern
    // den ursprünglichen Zeitpunkt nicht unbemerkt.
    $start = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $row['start_local']);
    $end = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $row['end_local']);

    // createFromFormat() liefert false, wenn ein Datum nicht gelesen werden
    // kann. Ohne einen gültigen Start und ein gültiges Ende lässt sich kein
    // verlässliches Zeitfenster bilden; die Zeile wird deshalb übersprungen.
    if ($start === false || $end === false) {
        continue;
    }

    // getTimestamp() liefert Sekunden seit dem 1. Januar 1970. Die Differenz
    // von Ende und Start ist also die Dauer in Sekunden. Division durch 3600
    // wandelt sie in Dezimalstunden um, wie sie die Datenbank erwartet.
    $durationHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

    // Plausibilitätsprüfung: Eine Dauer kleiner oder gleich null bedeutet,
    // dass das Ende vor dem Start liegt. Mehr als zehn Stunden sind für einen
    // einzelnen Unterrichtsblock ebenfalls unplausibel.
    //
    // In den vorliegenden Daten springt ein Eintrag fälschlich auf den
    // Folgetag. Solche Werte werden auf die oben definierte, in diesen Daten
    // übliche Blockdauer gesetzt. Das Audit hält jede Korrektur fest.
    if ($durationHours <= 0 || $durationHours > 10) {
        $durationHours = STANDARD_BLOCK_HOURS;
        $audit['duration_rows_corrected']++;
    }

    // FS27 enthält dieselben Zeitfenster teilweise doppelt. Für die Analyse
    // zählt derselbe Startzeitpunkt derselben Klasse im selben Semester nur
    // einmal. Der senkrechte Strich trennt die drei Bestandteile und macht
    // daraus einen eindeutigen, gut lesbaren Array-Schlüssel, beispielsweise:
    //
    //   FS27|mmp25c2|2027-02-15 09:15:00
    //
    // Der Kursname gehört absichtlich nicht zum Schlüssel: Zwei Einträge am
    // gleichen Zeitpunkt würden sonst als zwei belegte Zeitfenster zählen.
    $slotKey = $row['semester'] . '|' . $row['class_code'] . '|' . $start->format('Y-m-d H:i:s');

    // isset() prüft schnell, ob unter diesem Schlüssel bereits ein Zeitfenster
    // gespeichert wurde. Die erste Zeile bleibt erhalten, spätere identische
    // Zeitfenster werden gezählt und übersprungen.
    if (isset($slotsByKey[$slotKey])) {
        $audit['duplicate_rows_removed']++;
        continue;
    }

    // trim() entfernt Leerzeichen am Anfang und Ende. Für Vergleiche wird eine
    // kleingeschriebene Kopie erzeugt. Der Originalwert in $room behält seine
    // Schreibweise und kann später unverändert angezeigt werden.
    $room = trim($row['room']);
    $roomLower = mb_strtolower($room);

    // Die Raumspalte enthält nicht nur Raumnummern, sondern teilweise auch die
    // Angabe "online" oder den Platzhalter "tba". Daraus wird die kleine,
    // konsistente Kategorie mode für Datenbank und Visualisierung:
    //
    //   online     Unterricht findet online statt
    //   unclear    leer oder noch nicht bekannt
    //   in_person  ein konkreter physischer Ort ist eingetragen
    //
    // str_contains() findet auch Angaben wie "nur online". mb_strtolower()
    // sorgt dafür, dass Gross- und Kleinschreibung keine Rolle spielen.
    if (str_contains($roomLower, 'online')) {
        $mode = 'online';
    } elseif ($roomLower === '' || $roomLower === 'tba') {
        $mode = 'unclear';
    } else {
        $mode = 'in_person';
    }

    // Der Klassenpräfix "25c2 " steht in jeder Kursbezeichnung, ist aber schon
    // im separaten Feld class_code vorhanden. preg_replace() entfernt vom
    // Anfang des Textes (^) das erste zusammenhängende Wort (\S+) und die
    // folgenden Leerzeichen (\s+). Aus "25c2 Interaktive Medien III" wird so
    // "Interaktive Medien III". trim() räumt zuvor äussere Leerzeichen auf.
    $courseName = preg_replace('/^\S+\s+/', '', trim($row['course_name']));

    // Jetzt ist die Zeile vollständig transformiert und entspricht dem
    // Datenvertrag für load.php. Nur benötigte Felder werden übernommen;
    // technische CSV-Felder wie die ursprüngliche Zeilennummer bleiben draussen.
    $slotsByKey[$slotKey] = [
        // Stabile Kennungen werden direkt aus der Quelle übernommen.
        'semester' => $row['semester'],
        'class_code' => $row['class_code'],

        // Bereinigter Name ohne wiederholtes Klassenkürzel.
        'course_name' => $courseName,

        // MySQL DATETIME verwendet das Format YYYY-MM-DD HH:MM:SS.
        'starts_at' => $start->format('Y-m-d H:i:s'),

        // Zwei Nachkommastellen genügen für Viertelstunden wie 3.25.
        'duration_hours' => round($durationHours, 2),

        // Das Formatzeichen N liefert ISO-Wochentage: Montag = 1 bis Sonntag = 7.
        'weekday' => (int) $start->format('N'),

        // Vereinheitlichte Kategorie und ursprüngliche Raumangabe.
        'mode' => $mode,

        // In der Datenbank bedeutet null "keine Angabe". Das ist eindeutiger
        // als ein leerer Text und lässt sich mit SQL gezielt abfragen.
        'room' => $room === '' ? null : $room,
    ];
}

// Bisher waren die zusammengesetzten Dublettenschlüssel die Schlüssel des
// Arrays. Die Datenbank braucht aber eine normale, fortlaufend nummerierte
// Liste. array_values() entfernt deshalb nur diese Hilfsschlüssel; die
// Zeitfenster selbst bleiben unverändert.
$slots = array_values($slotsByKey);

// Temporäre Sichtkontrolle für die Entwicklung: <pre> erhält im Browser die
// Formatierung von var_dump(). var_dump() zeigt neben den Werten auch deren
// Datentypen und ist deshalb beim Prüfen des Datenvertrags hilfreich.
//
// Wichtig für den späteren Betrieb: Diese direkte Ausgabe geschieht noch vor
// dem return und wird daher auch ausgegeben, wenn load.php diese Datei mit
// include aufruft. Für einen reinen ETL-Lauf kann die Kontrolle nach Abschluss
// der Entwicklung entfernt oder auskommentiert werden.
echo '<pre>';
var_dump($slots);
echo '<pre>';

// usort() sortiert die Liste selbst. Die Vergleichsfunktion baut auf beiden
// Seiten je ein kleines Vergleichsarray:
//
//   [Semester-Reihenfolge, Startzeit]
//
// Der Spaceship-Operator <=> vergleicht zuerst das Semester. Nur wenn beide
// Werte gleich sind, entscheidet starts_at. Das ISO-Datumsformat lässt sich
// als Text chronologisch korrekt vergleichen.
usort(
    $slots,
    fn(array $a, array $b): int => [$semesterNames[$a['semester']]['sort_order'], $a['starts_at']]
        <=> [$semesterNames[$b['semester']]['sort_order'], $b['starts_at']],
);

// Erst nach Filterung, Korrektur, Deduplizierung und Sortierung steht fest, wie
// viele Zeitfenster der Transform tatsächlich an load.php weitergibt.
$audit['output_slots'] = count($slots);

// Eine mit include geladene PHP-Datei kann einen Wert zurückgeben. load.php
// erhält dadurch nicht nur die fertigen Daten, sondern auch ihre Bedeutung,
// die angewandten Regeln, die Semesterbeschriftungen und das Audit-Protokoll.
return [
    // Die Datenfrage hält fest, wozu diese Transformation gebaut wurde.
    'question' => 'Wie gleichmässig ist ein Semester wirklich?',

    // Die wichtigsten Transformationsregeln werden maschinenlesbar
    // dokumentiert. So bleiben Annahmen wie die Blockdauer nachvollziehbar.
    'rules' => [
        'target_class' => TARGET_CLASS,
        'standard_block_hours' => STANDARD_BLOCK_HOURS,
        'duplicate_key' => 'semester + class_code + starts_at',
    ],

    // Namen und Sortierreihenfolge für die spätere Ausgabe im Frontend.
    'semesters' => $semesterNames,

    // Die eigentlichen, bereinigten Zeitfenster für die Datenbank.
    'data' => $slots,

    // Kontrollzahlen über den gesamten Transformationsprozess.
    'audit' => $audit,
];
