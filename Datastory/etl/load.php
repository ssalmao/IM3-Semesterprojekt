<?php
/**
 * Load – schreibt die bereinigten Daten mit PDO in MySQL.
 *
 * Voraussetzung: etl/ehen_scheidungen.sql wurde in phpMyAdmin ausgeführt.
 *
 *   transform.php -> PHP-Arrays -> INSERTs -> MySQL
 *
 * Alles läuft in einer Transaktion: entweder alles gespeichert oder nichts.
 */

declare(strict_types=1);

// Ausgabe als Klartext (Admin-Werkzeug, keine Webseite)
header('Content-Type: text/plain; charset=utf-8');

// config.php liegt eine Ebene höher im Datastory-Ordner
$configPath = __DIR__ . '/../config.php';

if (!is_file($configPath)) {
    http_response_code(503);
    exit("config.php fehlt im Datastory-Ordner.\n");
}

// Stellt $dsn, $username, $password und $options bereit
require $configPath;

// Bereinigte Daten und Audit aus transform.php holen
$result = include __DIR__ . '/transform.php';
$rows = $result['data'];
$audit = $result['audit'];

echo 'Der Transform liefert ' . count($rows) . " Jahre.\n\n";

try {
    // Verbindung aufbauen
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Verbindung steht.\n\n";

    // Transaktion starten
    $pdo->beginTransaction();

    // Alten Datenstand löschen (CSV ist immer der vollständige Stand)
    $deletedRows = $pdo->exec('DELETE FROM yearly_stats');
    $pdo->exec('DELETE FROM etl_audit');
    echo $deletedRows . " alte Zeilen gelöscht.\n\n";

    // INSERT einmal vorbereiten, in der Schleife nur Werte austauschen
    $insertYear = $pdo->prepare(
        'INSERT INTO yearly_stats
            (year, marriages_total, marriages_per_1000, avg_age_m, avg_age_f,
             divorces_total, divorces_per_1000, avg_duration)
         VALUES
            (:year, :marriages_total, :marriages_per_1000, :avg_age_m, :avg_age_f,
             :divorces_total, :divorces_per_1000, :avg_duration)'
    );

    // Eine Zeile pro Jahr schreiben (Schlüssel entsprechen den Platzhaltern)
    foreach ($rows as $row) {
        $insertYear->execute($row);
    }

    // Audit-Werte schreiben, eine Zeile pro Kennzahl
    $insertAudit = $pdo->prepare(
        'INSERT INTO etl_audit (metric, value) VALUES (:metric, :value)'
    );

    foreach ($audit as $metric => $value) {
        $insertAudit->execute([
            'metric' => $metric,
            'value' => $value,
        ]);
    }

    // Alle Änderungen endgültig speichern
    $pdo->commit();

    echo count($rows) . " Jahre geschrieben.\n";
    echo count($audit) . " Prüfwerte geschrieben.\n\n";

    // Kontrolle direkt aus der Datenbank
    $check = $pdo->query(
        'SELECT COUNT(*) AS total, MIN(year) AS first_year, MAX(year) AS last_year
         FROM yearly_stats'
    )->fetch();

    echo "In yearly_stats stehen jetzt {$check['total']} Zeilen "
        . "({$check['first_year']} bis {$check['last_year']}).\n";
} catch (Throwable $error) {
    // Bei Fehler alles rückgängig machen
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    exit('Load fehlgeschlagen: ' . $error->getMessage() . "\n");
}