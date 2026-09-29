<?php
/**
 * Load – die fertige Fassung aus Code-Along 12.
 *
 * Schreibt das Ergebnis des Transforms in die Datenbank. Einmal aufrufen,
 * danach liefert unload.php die Daten an die Story-Seite.
 *
 * Datenfluss dieser Datei:
 *
 *   258 Zeilen aus dem Transform        (aus transform.php)
 *     -> 3 Zeilen in cities             (suchen, sonst anlegen)
 *       -> 258 Zeilen in heat_summers   (prepare einmal, execute oft)
 *
 * Anders als bei Extract und Transform gibt diese Datei nichts zurück. Ihr
 * Ergebnis steht nach dem Aufruf in der Datenbank und bleibt dort.
 */

// ---------------------------------------------------------------------------
// 1. Ausgabe als reiner Text
// ---------------------------------------------------------------------------
//
// load.php ist ein Werkzeug und keine Webseite. Niemand ruft sie im Betrieb
// auf; sie läuft, wenn neue Daten in die Datenbank sollen.

header('Content-Type: text/plain; charset=utf-8');

// ---------------------------------------------------------------------------
// 2. Zugangsdaten laden
// ---------------------------------------------------------------------------
//
// Die config.php liegt im Hauptordner des Kurses und gilt für alle Beispiele.
// Von hier aus sind es drei Ordner nach oben.

require __DIR__ . '/../../../config.php';

// ---------------------------------------------------------------------------
// 3. Das Ergebnis des Transforms holen
// ---------------------------------------------------------------------------
//
// transform.php liegt daneben und wird hier unverändert benutzt.
// Es gibt ein Array mit question, rules, data und audit zurück. In die
// Datenbank schreiben wir nur data – die übrigen Schlüssel sind Begleitpapiere
// für Menschen, keine Messwerte.

$result = include __DIR__ . '/transform.php';
$rows = $result['data'];

echo 'Der Transform liefert ' . count($rows) . " Zeilen.\n\n";

// ---------------------------------------------------------------------------
// 4. Verbindung aufbauen
// ---------------------------------------------------------------------------
//
// Wortgleich wie in Code-Along 11 und in unload.php nebenan.

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Verbindung steht.\n\n";
} catch (PDOException $e) {
    exit('Verbindung fehlgeschlagen: ' . $e->getMessage() . "\n");
}

// ---------------------------------------------------------------------------
// 5. Städte suchen, sonst anlegen
// ---------------------------------------------------------------------------
//
// Im Transform steht «Bern», in heat_summers braucht es die Nummer von Bern.
// Das Muster heisst «suchen, sonst anlegen» und begegnet euch in jedem Projekt
// mit zwei Tabellen wieder.
//
// Das Fragezeichen ist ein Platzhalter wie :name, nur ohne Namen. Bei einem
// einzigen Wert ist das kürzer; der Wert kommt als Liste in execute().

$findCity = $pdo->prepare('SELECT id FROM cities WHERE name = ?');
$insertCity = $pdo->prepare('INSERT INTO cities (name) VALUES (?)');

// $cityIds ist ein Merkzettel: Stadtname => id. Ohne ihn liefe die Abfrage
// 258-mal statt dreimal, denn jede Zeile nennt ihre Stadt erneut.
$cityIds = [];

foreach ($rows as $row) {
    $city = $row['city'];

    // Schon nachgeschlagen? Dann sofort weiter zur nächsten Zeile.
    if (isset($cityIds[$city])) {
        continue;
    }

    // fetchColumn() holt den ersten Wert der ersten gefundenen Zeile.
    // Gibt es keine Zeile, liefert es false.
    $findCity->execute([$city]);
    $id = $findCity->fetchColumn();

    // Der Vergleich mit === false ist genau und nicht bloss vorsichtig:
    // Mit == sähe die Zahl 0 wie «nicht gefunden» aus.
    if ($id === false) {
        $insertCity->execute([$city]);

        // lastInsertId() gibt die Nummer zurück, die die Datenbank gerade
        // vergeben hat. Sie kommt als Text, deshalb (int).
        $id = $pdo->lastInsertId();
    }

    $cityIds[$city] = (int) $id;
}

echo 'Städte in der Datenbank: ' . implode(', ', array_keys($cityIds)) . ".\n\n";

// ---------------------------------------------------------------------------
// 6. Alten Stand löschen
// ---------------------------------------------------------------------------
//
// Das ist die Antwort auf die Beobachtung aus Code-Along 11: Beim zweiten
// Aufruf standen dort doppelt so viele Zeilen in der Tabelle. INSERT fragt
// nicht, ob es die Zeile schon gibt.
//
// Wir dürfen hier löschen, weil Open-Meteo die Jahre 1940 bis heute jederzeit
// neu liefert. Bei einer Live-Sammlung wäre diese Zeile fatal: Die
// Vergangenheit steht dann nirgends sonst. Solche Projekte brauchen stattdessen
// eine UNIQUE-Regel und INSERT IGNORE.
//
// cities bleibt stehen. Die drei Städte ändern sich nicht, und heat_summers
// verweist mit dem Fremdschlüssel darauf.

$deleted = $pdo->exec('DELETE FROM heat_summers');
echo $deleted . " alte Zeilen gelöscht.\n\n";

// ---------------------------------------------------------------------------
// 7. Alle Zeilen schreiben
// ---------------------------------------------------------------------------
//
// prepare() steht vor der Schleife und läuft genau einmal: Die Datenbank
// bekommt den Bauplan. execute() steht in der Schleife und schickt nur noch
// die Werte.
//
// Beachtenswert ist, wie kurz diese Schleife ist. Hier wird nichts mehr
// umgerechnet, umbenannt oder geprüft – das hat der Transform erledigt. Wer im
// eigenen Projekt an dieser Stelle noch rechnen muss, hat es im Transform
// vergessen.

$insertSummer = $pdo->prepare(
    'INSERT INTO heat_summers (city_id, year, measurement_days, hot_days, max_temperature_c)
     VALUES (:city_id, :year, :measurement_days, :hot_days, :max_temperature_c)'
);

foreach ($rows as $row) {
    $insertSummer->execute([
        'city_id' => $cityIds[$row['city']],
        'year' => $row['year'],
        'measurement_days' => $row['measurement_days'],
        'hot_days' => $row['hot_days'],
        'max_temperature_c' => $row['max_temperature_c'],
    ]);
}

echo count($rows) . " Zeilen geschrieben.\n\n";

// ---------------------------------------------------------------------------
// 8. Kontrolle
// ---------------------------------------------------------------------------
//
// Zählen allein genügt nicht: Die Zahl wäre auch dann richtig, wenn in jeder
// Zeile Unsinn stünde. Deshalb lesen wir zusätzlich ein paar Werte zurück.
//
// query() reicht für den ersten Befehl, weil darin kein eigener Wert steckt.
// Sobald einer hineinkommt – wie die city_id unten –, gilt wieder prepare()
// und execute().

$total = $pdo->query('SELECT COUNT(*) FROM heat_summers')->fetchColumn();
echo "In heat_summers stehen jetzt {$total} Zeilen.\n\n";

$lastSummers = $pdo->prepare(
    'SELECT year, hot_days, max_temperature_c
     FROM heat_summers
     WHERE city_id = ?
     ORDER BY year DESC
     LIMIT 3'
);

foreach ($cityIds as $city => $cityId) {
    echo $city . ":\n";

    $lastSummers->execute([$cityId]);

    foreach ($lastSummers->fetchAll() as $summer) {
        echo '  ' . $summer['year'] . "\t"
            . $summer['hot_days'] . " Hitzetage\tmax "
            . $summer['max_temperature_c'] . " °C\n";
    }
}
