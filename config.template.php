<?php
/**
 * Vorlage für die Zugangsdaten zur Datenbank.
 *
 * Diese Datei liegt im Hauptordner des Kurses und gilt für alle Code-Alongs
 * und Übungen. Es gibt sie also genau einmal.
 *
 * So legst du deine eigene Fassung an – im Hauptordner:
 *
 *     cp config.template.php config.php
 *
 * config.php steht in .gitignore und landet nie auf GitHub. PhpStorm lädt sie
 * trotzdem per FTP auf den Server, denn dort wird sie gebraucht. Diese Vorlage
 * ohne Werte bleibt im Repository, damit alle wissen, welche Angaben nötig sind.
 */

// --- Zugangsdaten -----------------------------------------------------------
//
// Die Datenbank läuft auf dem Webserver bei Hostpoint. Datenbank und Benutzer
// legst du im Control Panel von Hostpoint an, dort stehen auch alle Werte.
//
// Hostpoint setzt den Namen deines Kontos vor Host, Datenbank und Benutzer.
// Heisst dein Konto «konto», sieht das so aus:
//
//   Host       konto.mysql.db.hostpoint.ch
//   Datenbank  konto_im3
//   Benutzer   konto_im3
//
// Fehlt dieser Vorsatz, meldet PDO «Access denied» oder «Unknown database».

$host     = '';
$dbname   = '';
$username = '';
$password = '';

// --- DSN: die Adresse der Datenbank -----------------------------------------
//
// DSN heisst Data Source Name. Er sagt PDO, welche Datenbank wo liegt.
// charset=utf8mb4 sorgt dafür, dass Umlaute richtig ankommen.
// Einen Port braucht es bei Hostpoint nicht.

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

// --- Optionen für PDO -------------------------------------------------------

$options = [
    // Fehler brechen laut ab, statt still zu scheitern. Wichtigste Zeile hier.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // Zeilen kommen als assoziative Arrays zurück: $row['location'].
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Platzhalter werden von der Datenbank selbst eingesetzt, nicht von PHP.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Ausweichweg ohne Server ------------------------------------------------
//
// Ist der Server einmal nicht erreichbar, beschreibt theorie/00_lokale_db/ eine
// Datenbank auf dem eigenen Rechner. Die Werte dafür stehen dort. Der übrige
// Code bleibt unverändert – genau dafür stehen die Zugangsdaten in einer
// eigenen Datei.
