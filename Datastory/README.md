# Beispielprojekt: Studium in Wellen

Eine kleine Data Story für den IM3-Unterricht. Vier CSV-Dateien mit dem
Stundenplan von HS25 bis FS27 durchlaufen die vollständige Kurskette:

```text
CSV → Extract → Transform → Load → MySQL → Unload/JSON → Chart.js
```

Die Geschichte beantwortet eine Frage: **Wie gleichmässig ist ein Semester
wirklich?** Gezeigt wird die Klasse `mmp25c2`, weil sie in allen vier Dateien
vorkommt.

## Story

1. **Wochen:** Unterricht verteilt sich nicht gleichmässig, sondern kommt in
   Wellen.
2. **Tage:** Der Mittwoch trägt am meisten.
3. **Ort:** Online-Unterricht bleibt die Ausnahme.

Der Semesterfilter aktualisiert Kennzahlen und alle drei Diagramme. Bei einer
Auswahl fragt JavaScript `unload.php?semester=FS27` neu an. Der Filter wird im
Endpunkt zu einer `WHERE`-Bedingung.


### 1. Datenbankzugang

Im Hauptordner des Kurs-Repositories eine `config.php` anlegen. Als Vorlage
dient `config.template.php`.

### 2. Tabellen anlegen

Den Inhalt von `etl/stundenplan.sql` in phpMyAdmin im Reiter «SQL» ausführen.

### 3. Daten laden

Auf dem Webserver einmal aufrufen:

```text
https://subdomain.eure-domain.ch/etl/load.php
```

Die Ausgabe muss am Ende 325 Zeitblöcke melden.

### 4. Story öffnen

```text
https://subdomain.eure-domain.ch
```

`index.php` lädt über `script.js` den JSON-Endpunkt `unload.php`.

## Lokal zeigen

Zum schnellen Zeigen ohne Datenbank:

```bash
cd beispielprojekt/stundenplan
php -S localhost:8000
```

Danach `http://localhost:8000` öffnen. Wenn Datenbank oder `config.php` fehlen,
lädt JavaScript automatisch `data/schedule-summary.json`. Die Seite nennt ihre
aktuelle Quelle direkt unter dem Semesterfilter.

## Der Datenfluss

### Extract

`etl/extract.php` liest die vier CSV-Dateien mit `fgetcsv()`. Es verändert
nichts und gibt 2644 Rohzeilen als PHP-Array zurück.

### Transform

`etl/transform.php` übernimmt das Array aus Extract und:

- filtert auf `mmp25c2`,
- entfernt 43 doppelte Zeitfenster,
- korrigiert eine unplausible Dauer von 27,25 auf 3,25 Stunden,
- vereinheitlicht die Ortsangabe zu `in_person`, `online` oder `unclear`,
- formatiert Datum, Dauer und Wochentag für die Datenbank.

Ergebnis: 325 bereinigte Zeitblöcke.

### Load

`etl/load.php` ruft den Transform auf, verbindet sich mit PDO und schreibt:

- vier Zeilen nach `semesters`,
- 325 Zeilen nach `schedule_slots`,
- fünf Prüfwerte nach `etl_audit`.

Der Load läuft in einer Transaktion. Bei einem Fehler wird nichts teilweise
gespeichert.

### Unload

`unload.php` liest die Zeitblöcke mit `SELECT` und `JOIN`, aggregiert sie für
die drei Grafiken und liefert JSON. Optional filtert `?semester=FS27` bereits
in der Datenbank.

### Frontend

`script.js` fragt zuerst `unload.php` an. Wenn der Endpunkt nicht erreichbar
ist, lädt es die Fallback-Datei. Danach werden die Daten zu `labels` und
`datasets` für Chart.js umgeformt.

## Datenvertrag

Endpunkt und Fallback liefern dieselbe Form:

```json
{
  "audit": {
    "duplicate_rows_removed": 43,
    "duration_rows_corrected": 1
  },
  "semesters": [],
  "weekly": [],
  "weekdays": [],
  "modes": []
}
```

## Datenmodell

| Tabelle | Eine Zeile bedeutet |
| --- | --- |
| `semesters` | ein Semester mit Code und Reihenfolge |
| `schedule_slots` | ein eindeutiges Zeitfenster der Klasse mmp25c2 |
| `etl_audit` | ein Prüfwert aus dem Transform |

## Was die Daten nicht sagen

Der Stundenplan zeigt belegte Zeitfenster. Er misst weder Anwesenheit noch
Vorbereitung, Selbststudium oder Projektarbeit. Auch ein eingetragener Raum
beweist nicht, dass der Termin tatsächlich dort stattgefunden hat.


## Dateien

| Datei                        | Rolle |
|------------------------------| --- |
| `data/raw/*.csv`             | unveränderte Quelldateien |
| `etl/extract.php`            | CSVs lesen und Roharray zurückgeben |
| `etl/transform.php`          | filtern, bereinigen und normalisieren |
| `etl/stundenplan.sql`        | drei MySQL-Tabellen anlegen |
| `etl/load.php`               | Transform aufrufen und mit PDO laden |
| `unload.php`                 | Datenbank auslesen und JSON liefern |
| `data/schedule-summary.json` | gleicher Datenvertrag als Fallback |
| `index.php`                  | kurze Story und drei Canvas-Elemente |
| `script.js`                  | Endpunkt laden und Charts aktualisieren |
| `style.css`                  | responsives Erscheinungsbild |

Die Seite verwendet Chart.js 4.5.1 über jsDelivr.
