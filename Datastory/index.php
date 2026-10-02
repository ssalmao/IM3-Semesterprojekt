<!DOCTYPE html>
<html lang="de">

<head>
  <!--
    index.php enthält die semantische Struktur und die journalistischen Texte
    der Datengeschichte. PHP-Code ist hier nicht nötig: Die dynamischen Werte
    werden nach dem Laden von script.js in die vorbereiteten Elemente eingesetzt.

    Der Dateiname .php sorgt dafür, dass die Seite im selben PHP-Projekt wie
    unload.php ausgeliefert werden kann. Dadurch liegen Frontend und JSON-
    Endpunkt auf derselben Domain und fetch() benötigt keine CORS-Freigabe.
  -->

  <!-- UTF-8 bewahrt Umlaute; viewport sorgt für eine mobile Darstellung. -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Titel und Beschreibung werden in Browser-Tab und Suchresultaten genutzt. -->
  <title>Studium in Wellen · Stundenplan mmp25c2</title>
  <meta name="description"
    content="Vier Semester Stundenplandaten zeigen, wie ungleich Unterrichtszeit über Wochen und Wochentage verteilt ist.">

  <!--
    Das kleine Balken-Favicon ist als SVG direkt in der URL codiert. Es braucht
    deshalb keine zusätzliche Bilddatei und bleibt beim Kopieren des Projekts erhalten.
  -->
  <link rel="icon" type="image/svg+xml"
    href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='12' fill='%230c1b33'/%3E%3Cpath d='M10 43h8V27h-8zm12 0h8V14h-8zm12 0h8V21h-8zm12 0h8V9h-8z' fill='%23b8f34a'/%3E%3C/svg%3E">

  <!-- Das lokale Stylesheet bestimmt Layout, Farben und responsive Ansichten. -->
  <link rel="stylesheet" href="style.css">

  <!--
    Chart.js muss vor script.js geladen werden, weil script.js sofort auf das
    globale Objekt Chart zugreift. type="module" führt das eigene Script erst
    nach dem Parsen des HTML aus; alle Canvas-Elemente existieren dann bereits.
  -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
  <script src="script.js" type="module"></script>
</head>

<body>
  <!--
    Story-Kopf: führt in die zentrale Aussage ein und enthält den einzigen
    globalen Filter. Die Klasse shell begrenzt die Inhaltsbreite auf grossen
    Bildschirmen. JavaScript liest den Wert des Selects über #semester.
  -->
  <header class="story-head shell">
    <div class="head-copy">
      <p class="eyebrow">mmp25c2 · Herbst 2025 bis Frühling 2027</p>
      <h1>Studium kommt<br><em>in Wellen.</em></h1>
      <p class="lead">Vier Semester Stundenplan zeigen keinen gleichmässigen Takt. Volle Wochen wechseln mit Lücken,
        und der Freitag bleibt fast immer frei.</p>
    </div>

    <!-- Steuerung der Ansicht und zugängliche Rückmeldung zum Datenstatus. -->
    <div class="head-control">
      <label for="semester">Ansicht</label>
      <select id="semester">
        <!--
          Die option-Werte entsprechen exakt den Semester-Codes in Datenbank,
          Unload-Vertrag und Fallback-Datei. "all" ist nur ein Frontendwert.
        -->
        <option value="all">Alle Semester</option>
        <option value="HS25">1. Semester · HS25</option>
        <option value="FS26">2. Semester · FS26</option>
        <option value="HS26">3. Semester · HS26</option>
        <option value="FS27">4. Semester · FS27</option>
      </select>

      <!-- role="status" lässt Screenreader Änderungen automatisch ankündigen. -->
      <p id="status" class="status" role="status">Daten werden geladen …</p>
    </div>
  </header>

  <main>
    <!--
      Kennzahlen: Die Gedankenstriche sind Platzhalter für den Ladezustand.
      renderMetrics() ersetzt ausschliesslich den Text in den drei strong-
      Elementen; Beschriftung und Layout bleiben im HTML.
    -->
    <section class="metrics shell" aria-label="Die wichtigsten Zahlen">
      <article class="metric">
        <strong id="metric-blocks">–</strong>
        <span>Zeitblöcke im Stundenplan</span>
      </article>
      <article class="metric">
        <strong id="metric-friday">–</strong>
        <span>der Stunden liegen am Freitag</span>
      </article>
      <article class="metric">
        <strong id="metric-online">–</strong>
        <span>der Stunden sind online</span>
      </article>
    </section>

    <!--
      Kapitel 1 – zeitlicher Verlauf. Das Canvas besitzt bewusst keine feste
      Pixelgrösse. Chart.js liest die Grösse aus .chart-box-line und reagiert
      dadurch auf unterschiedliche Bildschirmbreiten.
    -->
    <section class="chapter shell">
      <div class="chapter-copy">
        <p class="chapter-no">01 · Wochen</p>
        <h2>Kein Semester läuft im selben Takt.</h2>
        <p>Die Linien zeigen belegte Stunden pro Kalenderwoche. Leere Wochen bleiben sichtbar.</p>
      </div>
      <figure class="chart-card chart-card-wide">
        <div class="chart-head">
          <h3>Geplante Stunden pro Semesterwoche</h3>
          <!-- Wird beim Semesterwechsel von renderWeeklyChart() aktualisiert. -->
          <p id="weekly-note">Vier Semester im Vergleich</p>
        </div>
        <div class="chart-box chart-box-line">
          <!-- role und aria-label geben dem rein visuellen Canvas einen Namen. -->
          <canvas id="weekly-chart" role="img"
            aria-label="Liniendiagramm mit den geplanten Stunden pro Semesterwoche."></canvas>
        </div>
      </figure>
    </section>

    <!--
      Kapitel 2 – Verteilung auf Wochentage. Die äussere Section spannt den
      dunklen Hintergrund über die ganze Fensterbreite; .chapter-grid bildet
      darin das begrenzte zweispaltige Layout. style.css überschreibt deshalb
      bei .chapter-dark das allgemeine Grid von .chapter mit display:block.
    -->
    <section class="chapter chapter-dark">
      <div class="shell chapter-grid">
        <div class="chapter-copy">
          <p class="chapter-no">02 · Tage</p>
          <h2>Mittwoch trägt am meisten.</h2>
          <p>Die Wochenmitte bündelt den Unterricht. Immerhin zweieinhalb Prozent der geplanten Stunden liegen am Freitag.</p>
        </div>
        <figure class="chart-card chart-card-dark">
          <div class="chart-head">
            <h3>Geplante Stunden nach Wochentag</h3>
            <p>Aufsummiert je Semester</p>
          </div>
          <div class="chart-box chart-box-bar">
            <!-- renderWeekdayChart() zeichnet hier das gestapelte Balkendiagramm. -->
            <canvas id="weekday-chart" role="img"
              aria-label="Balkendiagramm der geplanten Stunden von Montag bis Freitag."></canvas>
          </div>
        </figure>
      </div>
    </section>

    <!--
      Kapitel 3 – Unterrichtsmodus. Das Ringdiagramm aggregiert die technischen
      Schlüssel in_person, online und unclear; sichtbare Bezeichnungen kommen
      aus meta.mode_labels des JSON-Vertrags.
    -->
    <section class="chapter shell chapter-split">
      <div class="chapter-copy">
        <p class="chapter-no">03 · Ort</p>
        <h2>Online bleibt die Ausnahme.</h2>
        <p>Der Stundenplan ist klar auf Präsenz ausgerichtet. «Raum noch offen» ist keine Ortsart, sondern eine sichtbare Datenlücke.</p>
      </div>
      <figure class="chart-card chart-card-donut">
        <div class="chart-head">
          <h3>Stunden nach Ortsangabe</h3>
          <!-- Wird beim Filtern auf den Namen des gewählten Semesters gesetzt. -->
          <p id="mode-note">Alle vier Semester</p>
        </div>
        <div class="chart-box chart-box-donut">
          <canvas id="mode-chart" role="img"
            aria-label="Ringdiagramm der geplanten Stunden in Präsenz, online und mit noch offenem Raum."></canvas>
        </div>
      </figure>
    </section>

    <!--
      Methodenkasten: erklärt nicht nur die Aussage, sondern auch Auswahl und
      Grenzen. Die beiden Audit-Zahlen werden aus der Datenbank beziehungsweise
      aus der identisch aufgebauten Fallback-Datei eingesetzt.
    -->
    <section class="method shell">
      <p class="chapter-no">Methode</p>
      <h2>Erst bereinigen, dann erzählen.</h2>
      <div class="method-grid">
        <p><strong>Filter</strong><br>Gezeigt wird die Klasse mmp25c2, weil sie in allen vier Dateien vorkommt.</p>
        <!-- IDs sind Einfügepunkte für reload() in script.js. -->
        <p><strong>Bereinigung</strong><br><span id="audit-duplicates">–</span> doppelte Zeilen entfernt, <span
            id="audit-duration">–</span> unplausible Zeitangabe korrigiert.</p>
        <p><strong>Grenze</strong><br>Der Plan misst belegte Zeitfenster, nicht Anwesenheit oder Selbststudium.</p>
      </div>
    </section>
  </main>

  <!-- Statische Quellen- und Projektangabe am Seitenende. -->
  <footer class="footer shell">
    <span>Beispielprojekt Interaktive Medien 3</span>
    <span>Daten: Semesterpläne HS25–FS27</span>
  </footer>
</body>

</html>
