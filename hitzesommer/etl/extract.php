<?php
/**
 * Extract – fertig aus Block B.
 *
 * Liest die drei JSON-Dateien und gibt die Rohdaten als PHP-Array weiter.
 * An dieser Datei ändert sich heute nichts.
 */

$sources = [
    'Bern' => __DIR__ . '/data/bern.json',
    'Chur' => __DIR__ . '/data/chur.json',
    'Zürich' => __DIR__ . '/data/zuerich.json',
];

$rawLocations = [];

foreach ($sources as $city => $file) {
    $json = file_get_contents($file);
    $rawLocations[] = [
        'city' => $city,
        'source' => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
    ];
}

return $rawLocations;
