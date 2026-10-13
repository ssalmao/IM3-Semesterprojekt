<!DOCTYPE html>
<html lang="de">

<head>
    <!--
      index.php enthält die Struktur und die Texte der Datastory.
      Die Zahlen setzt script.js nach dem fetch() auf unload.php in die
      vorbereiteten Elemente mit id ein. Die Gedankenstriche (–) sind
      Platzhalter, solange die Daten laden.
    -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ehe und Scheidung in der Schweiz · Datastory</title>
    <meta name="description"
          content="Heiraten wir später und halten Ehen länger? Eine Datastory mit BFS-Daten von 1975 bis 2025.">

    <!-- Einbindung vom CSS Style Sheet einbinden -->
    <link rel="stylesheet" href="style.css">

    <!-- Chart.js zuerst, weil script.js direkt auf das Objekt Chart zugreift -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="script.js" type="module"></script>
</head>

<body>

<!-- 1 · Hero (dunkelrot): Kernaussage und Illustration -->
<header class="hero">
    <div class="shell hero-inner">
            <h1>
                Wir heiraten heute durchschnittlich
                <span id="hero-years">–</span> Jahre später als <span id="hero-from">–</span>.
            </h1>
        <img class="hero-img" src="img/brautpaar.svg" alt="Illustration eines Brautpaars" width="240" height="440">
    </div>
</header>

<!-- 2 · Titel (hellrosa) -->
<section class="intro">
    <div class="shell intro-grid">
        <img class="character" src="img/characters/svg/enamoured_stephan.svg" alt="Illustration von Mann - Stephan">
        <div class="intro-text">
            <h2 class="story-title">Ehe und Scheidung</h2>
            <span>in der Schweiz</span>
        </div>
        <img class="character" src="img/characters/svg/enamoured_lisa.svg" alt="Illustration von Frau - Lias">
    </div>
</section>

<main>

    <!-- 3 · Heiraten (dunkelrot): Diagramm links, Text rechts -->
    <section class="chapter chapter-dark">
        <div class="shell chapter-grid">
            <figure class="chart-card slider">
                <div class="slider-head">
                    <p class="slider-title">Heiraten pro Jahr</p>
                    <span class="slider-count">1 / 2</span>
                </div>

                <div class="slider-body">
                    <button type="button" class="slider-btn slider-prev" aria-label="Vorherige Grafik">‹</button>

                    <div class="slider-window">
                        <div class="slider-track">
                            <div class="slide" data-title="Heiraten pro Jahr">
                                <div class="chart-box">
                                    <canvas id="marriages-chart" role="img"
                                            aria-label="Liniendiagramm der Anzahl Heiraten pro Jahr von 1975 bis 2025."></canvas>
                                </div>
                            </div>
                            <div class="slide" data-title="Heiraten pro 1000 Einwohner">
                                <div class="chart-box">
                                    <canvas id="marriages-rate-chart" role="img"
                                            aria-label="Liniendiagramm der Heiraten pro 1000 Einwohnerinnen und Einwohner von 1975 bis 2025."></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="slider-btn slider-next" aria-label="Nächste Grafik">›</button>
                </div>
            </figure>
            <div class="chapter-text">
                <h2>Heiraten</h2>
                <p>
                    Am meisten Hochzeiten gab es <span id="peak-marriages-year">–</span>:
                    <span id="peak-marriages-value">–</span> Paare sagten Ja.
                    Seither ist die Zahl gesunken, obwohl die Bevölkerung gewachsen ist.
                    Pro 1000 Einwohnerinnen und Einwohner wird heute deutlich seltener geheiratet.
                </p>
            </div>
        </div>
    </section>
    <!-- 3.5 · Heiraten - Zusatzinfos -->
    <section class="chapter chapter-dark">
        <div class="shell chapter-grid">
            <h1>Mehr Menschen aber nicht mehr Hochzeiten: pro 1000 Menschen wird heute fast 1 Drittel weniger geheiratet als 1975.</h1>
            <img class="hero-img" src="img/characters/svg/married_couple.svg" alt="Illustration eines verheiraten Paars" width="240" height="440">
        </div>
    </section>

    <!-- 4 · Alter bei der Erstheirat (weiss): gruppierte Balken Männer/Frauen -->
    <section class="chapter chapter-rose">
        <div class="shell chapter-stack">
            <figure class="chart-card">
                <div class="chart-box">
                    <canvas id="age-chart" role="img"
                            aria-label="Balkendiagramm des Durchschnittsalters von Männern und Frauen bei der Erstheirat, alle zehn Jahre von 1975 bis 2025."></canvas>
                </div>
            </figure>
            <div class="chapter-text">
                <h2>Alter Erstheirat</h2>
                <p>
                    Männer sind bei der ersten Hochzeit heute <span id="age-change-men">–</span> Jahre älter
                    als 1975, Frauen sogar <span id="age-change-women">–</span> Jahre.
                    Der Altersunterschied zwischen den Partnern wird dabei kleiner.
                </p>
            </div>
        </div>
    </section>

    <!-- 4.5 · Alter Erstheirat - Zusatzinfos -->
    <section class="chapter chapter-rose">
        <div class="shell chapter-grid">
            <h1>Früher musste ein Partner vor Gericht ein schweres Verschulden des anderen nachweisen (z. B. Ehebruch oder schwere Pflichtverletzung).
                <br>
                <br>
            Seit dem Jahr 2000 gilt das Zerrüttungsprinzip: Eine Ehe kann vor im gegenseitigen Einvernehmen oder nach einer zweijährigen Trennungsfrist geschieden werden, ohne dass eine Schuldfrage geklärt werden muss.</h1>
            <img class="hero-img" src="img/characters/svg/arguing_couple.svg" alt="Illustration eines verheiraten Paars" width="240" height="440">
        </div>
    </section>

    <!-- 5 · Ehedauer bis zur Scheidung (rosa): horizontale Balken -->
    <section class="chapter chapter-light">
        <div class="shell">
            <h2 class="chapter-title">Dauer der Ehe bis zur Scheidung</h2>
            <div class="chapter-grid">
                <figure class="chart-card">
                    <div class="chart-box">
                        <canvas id="duration-chart" role="img"
                                aria-label="Horizontales Balkendiagramm der durchschnittlichen Ehedauer bis zur Scheidung, alle zehn Jahre von 1975 bis 2025."></canvas>
                    </div>
                </figure>
                <div class="chapter-text">
                    <p>
                        Ehen, die geschieden werden, halten heute länger als früher.
                        Seit 1975 ist die durchschnittliche Dauer bis zur Scheidung um
                        <span id="duration-change">–</span> Jahre gestiegen.
                        Wer später heiratet, scheint sich mehr Zeit füreinander zu nehmen.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 5.5 · Grosse Aussage (weiss) -->
    <section class="statement">
        <div class="shell">
            <p>
                Wer sich <span id="statement-year">–</span> scheiden liess,
                war im Schnitt <span id="statement-duration">–</span> Jahre verheiratet.
            </p>
        </div>
    </section>

    <!-- 7 · Scheidungen (korallrot): Text links, Liniendiagramm rechts -->
    <section class="chapter chapter-coral">
        <div class="shell chapter-grid chapter-grid-reverse">
            <div class="chapter-text">
                <h2>Scheidungen</h2>
                <p>
                    Den Höchststand erreichten die Scheidungen <span id="peak-divorces-year">–</span>
                    mit <span id="peak-divorces-value">–</span> Fällen. Seither gehen sie leicht zurück.
                </p>
                <p class="note">
                    Der Knick um das Jahr 2000 hat einen rechtlichen Grund: Am 1. Januar 2000 trat das
                    neue Scheidungsrecht in Kraft. Viele Paare liessen sich 1999 noch nach altem Recht
                    scheiden, 2000 dauerten die neuen Verfahren länger.
                </p>
            </div>
            <figure class="chart-card slider">
                <div class="slider-head">
                    <p class="slider-title">Scheidungen pro Jahr</p>
                    <span class="slider-count">1 / 2</span>
                </div>

                <div class="slider-body">
                    <button type="button" class="slider-btn slider-prev" aria-label="Vorherige Grafik">‹</button>

                    <div class="slider-window">
                        <div class="slider-track">
                            <div class="slide" data-title="Scheidungen pro 1000 Einwohner">
                                <div class="chart-box">
                                    <canvas id="divorces-rate-chart" role="img"
                                            aria-label="Liniendiagramm der Scheidungen pro 1000 Einwohnerinnen und Einwohner von 1975 bis 2025."></canvas>
                                </div>
                            </div>
                            <div class="slide" data-title="Scheidungen pro Jahr">
                                <div class="chart-box">
                                    <canvas id="divorces-chart" role="img"
                                            aria-label="Liniendiagramm der Anzahl Scheidungen pro Jahr von 1975 bis 2025."></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="slider-btn slider-next" aria-label="Nächste Grafik">›</button>
                </div>
            </figure>
        </div>
    </section>

    <!-- 8 · Methode: Quelle, Bereinigung, Grenzen (Transparenz) -->
    <section class="method">
        <div class="shell">
            <h2>Methode</h2>
            <div class="method-grid">
                <p>
                    <strong>Quelle</strong><br>
                    Bundesamt für Statistik (BFS), BEVNAT. Schweiz gesamt, 1975 bis 2025.
                </p>
                <p>
                    <strong>Bereinigung</strong><br>
                    <span id="audit-source">–</span> Zeilen gelesen,
                    <span id="audit-invalid">–</span> ungültig,
                    <span id="audit-duplicates">–</span> Dubletten entfernt.
                </p>
                <p>
                    <strong>Grenzen</strong><br>
                    Die Ehedauer gilt nur für geschiedene Ehen, nicht für alle Ehen.
                    Scheidungen und Heiraten eines Jahres betreffen verschiedene Paare.
                </p>
            </div>
        </div>
    </section>

</main>

<footer class="footer">
    <div class="shell footer-inner">
        <span>Interaktive Medien 3 · FHGR</span>
        <!-- role="status" kündigt Änderungen für Screenreader an -->
        <p id="status" class="status" role="status">Daten werden geladen …</p>
        <span>Daten: BFS</span>

    </div>
</footer>

</body>

</html>