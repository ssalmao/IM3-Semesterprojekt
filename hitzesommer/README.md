# Beispielprojekt: Hitzesommer

> **Ziel:** So kann ein fertiges IM3-Projekt aussehen. Dieselbe Kette wie in
> den Code-Alongs, nur zu Ende gebaut: mit Story, Gestaltung und Fallback.

Die Datengeschichte beantwortet eine einzige Frage: **Wie hat sich die Anzahl
Hitzetage pro Sommer in Bern, Chur und Zürich seit 1940 verändert?**

Das Frontend ist der Stand aus Code-Along 17, weitergebaut zu dem, was am
Marktstand stehen könnte – Text und Diagramme wechseln sich ab, jede Grafik hat
eine Aussage, und die Grenzen der Daten stehen auf der Seite statt in einer
Fussnote.

## Starten

Den Ordner auf den Webserver hochladen und über die eigene Domain öffnen:
`https://eure-domain.ch/beispielprojekt/hitzesommer/`

Zum schnellen Zeigen geht es auch lokal, dann aber nur mit den Musterdaten:

```bash
cd beispielprojekt/hitzesommer
php -S localhost:8000
```

Die Seite läuft **auch ohne Datenbank**: Antwortet `unload.php` nicht, lädt sie
`data/heat-summers.json` und schreibt es in den Statustext. Wer die ganze Kette
sehen will, richtet die Datenbank ein:

1. `config.php` im Hauptordner des Kurses anlegen (siehe `config.template.php`).
2. `etl/schema.sql` in phpMyAdmin ausführen – das legt die zwei Tabellen an.
3. `etl/load.php` einmal im Browser aufrufen. Danach stehen 258 Zeilen in der Datenbank.

> Der Doppelklick auf `index.html` funktioniert **nicht**: Dann läuft kein PHP,
> und `unload.php` liefert keine Daten.

## Was wo liegt

| Datei | Rolle |
| --- | --- |
| `etl/extract.php` | liest die drei JSON-Dateien aus `etl/data/` |
| `etl/transform.php` | filtert auf Juni–August, zählt Hitzetage, prüft auf 92 Messtage |
| `etl/load.php` | schreibt das Ergebnis in `cities` und `heat_summers` |
| `etl/schema.sql` | das Datenmodell – einmal von Hand ausführen |
| `unload.php` | JSON-Endpunkt, `SELECT` mit `JOIN`, optionaler `?city=`-Filter |
| `index.html` | die Geschichte: Text, Kennzahlen, drei Grafiken, Methode |
| `script.js` | holt die Daten, formt sie um, zeichnet mit Chart.js |
| `style.css` | die Gestaltung |
| `data/heat-summers.json` | Musterdaten und Fallback, exakt in der Form des Endpunkts |

Die ETL-Dateien sind unverändert aus den Code-Alongs 09, 12 und 14 übernommen.
Neu ist hier nur, was danach kommt.

## Der Datenvertrag

Ein Datensatz ist eine Stadt in einem vollständigen Sommer:

```json
{
  "city": "Bern",
  "year": 2023,
  "measurement_days": 92,
  "hot_days": 12,
  "max_temperature_c": 36.3
}
```

Auf diese fünf Felder haben sich Backend und Frontend geeinigt. `unload.php`
setzt die Typen durch, `script.js` verlässt sich darauf – und die Musterdatei
hält sich ebenfalls daran. Deshalb lässt sich die Datenquelle austauschen, ohne
dass eine Zeile im Frontend zu ändern wäre.

## Was ihr euch abschauen könnt

**Für die Story:**

- Eine Frage, nicht fünf. Alles auf der Seite dient dieser einen Frage.
- Jede Grafik hat einen Titel, der eine Aussage macht, und einen Untertitel,
  der sagt, was man sieht.
- Der Kasten «Was diese Daten nicht sagen» gehört auf die Seite. Wer die
  Grenzen selbst nennt, wirkt nicht schwächer, sondern glaubwürdiger.
- Die Methode steht am Schluss: Datenfrage, Definitionen, Datenvertrag, Kette.

**Für die Technik:**

- Der Fallback auf Musterdaten – und der ehrliche Hinweis, dass gerade er läuft.
- Drei Zustandsvariablen, eine `render()`-Funktion. Jedes Bedienelement ändert
  eine Variable und zeichnet neu.
- Der Stadtfilter geht über den Server (`?city=` wird zu `WHERE`), Zeitraum und
  Messwert bleiben im Browser. Der Server filtert, was viel ist; der Browser
  filtert, was schon da ist.
- Jede Stadt hat in allen Grafiken dieselbe Farbe. In der Rangliste ersetzt die
  Farbe die Legende.

## Zahlen zum Nachrechnen

Alle Aussagen im Text stammen aus `data/heat-summers.json` und lassen sich
nachprüfen:

| Aussage | Wert |
| --- | --- |
| Hitzetage pro Sommer, 1940–1969 → 1996–2025 | Bern 1.3 → 2.9, Chur 1.4 → 3.5, Zürich 2.2 → 5.6 |
| Sommer ohne Hitzetag, 1970er | 24 von 30 |
| Sommer ohne Hitzetag, seit 2020 | 2 von 18 |
| Sommer ohne Hitzetag, 1940–2025 | Bern 58, Chur 45, Zürich 30 von je 86 |
| letzter Sommer ohne Hitzetag | Chur 2014, Bern und Zürich 2021 |
| stärkster Sommer | Zürich 2003, 22 Hitzetage |
| höchste Temperatur | Bern 2023, 36.3 °C |
| Sommer mit mindestens 35 °C | Chur 2019, Bern 2023, Chur 2023 |
| Hitzetage total, 1940–2025 | Zürich 270, Chur 164, Bern 134 |
| Hitzetage pro Sommer, 2020er | Chur 10.2, Zürich 8.0, Bern 6.8 |

## Verwandte Materialien

- Cheatsheets: [E1 Unload](../../cheatsheets/E1_unload.md),
  [F1 Chart.js](../../cheatsheets/F1_chartjs.md)
- Code-Alongs: [16 Liniendiagramm](../../code-alongs/F_visualisierung/16_hitzesommer_liniendiagramm/),
  [17 Rangliste](../../code-alongs/F_visualisierung/17_hitzesommer_ranking/)
- Theorie: [F Datengrafiken](../../theorie/F_visualisierung/)
