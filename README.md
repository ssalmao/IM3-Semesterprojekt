# Beispielprojekte

Fertig gebaute Projekte zum Anschauen und Abschauen. Sie zeigen, wo die
Code-Alongs hinführen: nicht zu einem Diagramm auf einer leeren Seite, sondern
zu einer Datengeschichte, die man jemandem zeigen kann.

| Projekt                     | Datenquelle                        | Was es zeigt                                                                |
| --------------------------- | ---------------------------------- | --------------------------------------------------------------------------- |
| [Hitzesommer](hitzesommer/) | Open-Meteo, historische JSON-Daten | die ganze Kette von der Datei bis zur Story, mit drei Grafiken und Fallback |

Ein Beispielprojekt ist keine Vorlage zum Kopieren. Eure Datenfrage, eure
Daten und eure Gestaltung sind eigene Entscheidungen. Übernehmen könnt ihr den
Aufbau: die Kette `extract → transform → load → unload → Frontend`, den
Datenvertrag als Abmachung zwischen den beiden Zweierteams und die Reihenfolge
Text – Grafik – Einordnung.

## Was ein fertiges Projekt ausmacht

- Eine Datenfrage, die die ganze Seite trägt.
- Ein Datenvertrag, auf den sich Backend und Frontend geeinigt haben.
- Grafiken, die eine Aussage machen, statt nur Zahlen zu zeigen.
- Ein Kasten, der sagt, was die Daten **nicht** hergeben.
- Ein Fallback für den Marktstand: gespeicherte Daten, falls die Datenbank
  oder das WLAN streikt.

## Hinweis für Dozierende

**Das Beispielprojekt läuft ohne Datenbank.** Fehlt die `config.php` oder läuft
PHP nicht, kann das Beispielprojekt trotzdem gezeigt werden. Dazu nur die html Datei lokal im Browser öffnen.

Wer die ganze Kette dennoch vorführen will, richtet die Datenbank nach
`beispielprojekt/hitzesommer/README.md` ein.
