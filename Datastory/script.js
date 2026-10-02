/**
 * Frontend der Stundenplan-Datengeschichte.
 *
 * Aufgaben dieser Datei:
 *
 *   1. JSON bevorzugt von unload.php laden
 *   2. bei einem Serverproblem auf lokale Musterdaten zurückfallen
 *   3. die Auswahl eines Semesters verwalten
 *   4. Kennzahlen aus dem Datenvertrag berechnen
 *   5. drei Chart.js-Diagramme aktualisieren
 *
 * index.php stellt dafür die HTML-Elemente und Canvas-Flächen bereit.
 * unload.php und schedule-summary.json liefern bewusst denselben Vertrag.
 */

// Primäre Datenquelle auf derselben Domain und stabile Ersatzquelle für eine
// lokale Demonstration oder einen vorübergehenden Datenbankausfall.
const ENDPOINT = 'unload.php';
const FALLBACK_DATA = 'data/schedule-summary.json';

// Ein Semester behält in allen Diagrammen dieselbe Farbe. Die fachlichen
// Schlüssel entsprechen exakt den semester-Werten aus dem JSON.
const COLORS = {
  HS25: '#4c68ff',
  FS26: '#ff7058',
  HS26: '#45c8d8',
  FS27: '#b8f34a',
};

// Gitternetzfarben für dunkle und helle Diagrammkarten. Die Transparenz hält
// Hilfslinien sichtbar, ohne sie wichtiger als die Daten erscheinen zu lassen.
const DARK_GRID = 'rgba(255, 255, 255, 0.12)';
const LIGHT_GRID = 'rgba(12, 27, 51, 0.10)';

// ---------------------------------------------------------------------------
// Zustand
// ---------------------------------------------------------------------------

// storyData enthält nach dem Laden den vollständigen JSON-Vertrag. Bis dahin
// bleibt die Variable undefined und die Platzhalter aus index.php sind sichtbar.
let storyData;

// "all" ist ein Frontendwert. Ein echtes Semester wird als HS25, FS26 usw.
// gespeichert und bei Bedarf als Query-Parameter an unload.php geschickt.
let selectedSemester = 'all';

// Merkt sich, welche Quelle beim letzten Laden erfolgreich war. Dadurch kann
// der Status ehrlich zwischen Datenbank und lokaler Datei unterscheiden.
let source = 'endpoint';

// ---------------------------------------------------------------------------
// Verbindungen zum HTML
// ---------------------------------------------------------------------------

// querySelector() liefert Referenzen auf die vorbereiteten Elemente aus
// index.php. Sie werden einmal gesucht und danach bei jedem Rendern wiederverwendet.
const semesterSelect = document.querySelector('#semester');
const statusText = document.querySelector('#status');
const blocksMetric = document.querySelector('#metric-blocks');
const fridayMetric = document.querySelector('#metric-friday');
const onlineMetric = document.querySelector('#metric-online');
const weeklyNote = document.querySelector('#weekly-note');
const modeNote = document.querySelector('#mode-note');

// Globale Chart.js-Vorgaben: Alle drei Diagramme übernehmen Schrift und
// Standardfarbe. Einzelne Achsen im dunklen Kapitel überschreiben die Farbe.
Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
Chart.defaults.color = '#667085';

// ---------------------------------------------------------------------------
// Chart 1: Unterrichtsstunden pro Semesterwoche
// ---------------------------------------------------------------------------

// Das Chart-Objekt wird nur einmal erzeugt. Beim Filtern ersetzt JavaScript
// später labels und datasets und ruft update() auf. Mehrfaches new Chart() auf
// demselben Canvas würde zu "Canvas is already in use" führen.
const weeklyChart = new Chart(document.querySelector('#weekly-chart'), {
  type: 'line',

  // Vor dem ersten Datenabruf ist das Diagramm absichtlich leer.
  data: { labels: [], datasets: [] },
  options: {
    // Chart.js passt die Breite an den Container an. Die Höhe stammt wegen
    // maintainAspectRatio:false aus .chart-box-line in style.css.
    responsive: true,
    maintainAspectRatio: false,

    // Beim Überfahren zeigt der Tooltip alle Semester derselben Woche, auch
    // wenn der Mauszeiger nicht exakt einen Datenpunkt trifft.
    interaction: { mode: 'index', intersect: false },
    scales: {
      x: {
        // Weniger Linien halten die lange Wochenachse ruhig.
        grid: { display: false },
        title: { display: true, text: 'Woche seit Semesterstart' },
      },
      y: {
        // Stunden können nicht negativ sein; die Skala beginnt immer bei null.
        beginAtZero: true,
        grid: { color: LIGHT_GRID },
        title: { display: true, text: 'Stunden' },
      },
    },
    plugins: {
      // usePointStyle zeichnet kleine Farbpunkte statt breiter Rechtecke.
      legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
      tooltip: {
        callbacks: {
          // Tooltips nutzen dieselbe lokalisierte Stundenformatierung wie KPIs.
          label: (item) => `${item.dataset.label}: ${formatHours(item.raw)}`,
        },
      },
    },
  },
});

// ---------------------------------------------------------------------------
// Chart 2: Unterrichtsstunden nach Wochentag
// ---------------------------------------------------------------------------

const weekdayChart = new Chart(document.querySelector('#weekday-chart'), {
  type: 'bar',
  data: { labels: [], datasets: [] },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        // Alle Semesterwerte eines Wochentags werden übereinander gestapelt.
        stacked: true,
        grid: { display: false },

        // Helle Beschriftung auf dem dunkelblauen Kapitelhintergrund.
        ticks: { color: '#dce5f3' },
      },
      y: {
        stacked: true,
        beginAtZero: true,
        grid: { color: DARK_GRID },
        ticks: { color: '#dce5f3' },
        title: { display: true, text: 'Stunden', color: '#dce5f3' },
      },
    },
    plugins: {
      legend: {
        position: 'bottom',
        labels: { color: '#dce5f3', usePointStyle: true, boxWidth: 8 },
      },
      tooltip: {
        callbacks: {
          label: (item) => `${item.dataset.label}: ${formatHours(item.raw)}`,
        },
      },
    },
  },
});

// ---------------------------------------------------------------------------
// Chart 3: Stunden nach Ortsangabe beziehungsweise Unterrichtsmodus
// ---------------------------------------------------------------------------

const modeChart = new Chart(document.querySelector('#mode-chart'), {
  type: 'doughnut',
  data: {
    labels: [],

    // Das Ringdiagramm besitzt nur eine Datenreihe. Die drei Farben stehen in
    // derselben Reihenfolge wie modeKeys in renderModeChart().
    datasets: [{ data: [], backgroundColor: ['#4c68ff', '#45c8d8', '#ff7058'] }],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,

    // 68 Prozent Ausschnitt erzeugen den sichtbaren Ring statt eines Vollkreises.
    cutout: '68%',
    plugins: {
      legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
      tooltip: {
        callbacks: {
          label: (item) => `${item.label}: ${formatHours(item.raw)}`,
        },
      },
    },
  },
});

// ---------------------------------------------------------------------------
// Kleine Hilfsfunktionen
// ---------------------------------------------------------------------------

/**
 * Formatiert eine Stundenzahl nach Schweizer Schreibweise.
 * Aus 3.25 wird beispielsweise "3.25 h"; unnötige Nachkommastellen entfallen.
 */
function formatHours(value) {
  return `${new Intl.NumberFormat('de-CH', { maximumFractionDigits: 2 }).format(value)} h`;
}

/**
 * Formatiert einen Anteil zwischen 0 und 1 als Prozentwert.
 * Beispiel: 0.025 wird als "2.5%" ausgegeben.
 */
function formatPercent(value) {
  return new Intl.NumberFormat('de-CH', {
    style: 'percent',
    maximumFractionDigits: 1,
  }).format(value);
}

/**
 * Liefert entweder alle übergebenen Semesterreihen oder nur die zur aktuellen
 * Auswahl passenden. Da alle vier Datenbereiche dieselbe semester-Eigenschaft
 * verwenden, kann eine Funktion für summaries, weekly, weekdays und modes
 * wiederverwendet werden.
 */
function selectedRows(rows) {
  return selectedSemester === 'all'
    ? rows
    : rows.filter((row) => row.semester === selectedSemester);
}

/**
 * Addiert eine Liste numerischer Werte. Der Startwert 0 ist wichtig: So liefert
 * auch eine leere Liste eine gültige Summe statt eines Fehlers.
 */
function sum(values) {
  return values.reduce((total, value) => total + value, 0);
}

// ---------------------------------------------------------------------------
// Daten laden
// ---------------------------------------------------------------------------

/**
 * Fragt den PHP-Endpunkt an.
 *
 * Bei "all" wird kein Query-Parameter benötigt. Bei einem einzelnen Semester
 * entsteht zum Beispiel unload.php?semester=FS27. encodeURIComponent()
 * schützt die URL auch dann, wenn ein Filterwert einmal Sonderzeichen enthält.
 */
async function loadFromEndpoint(semester) {
  const url = semester === 'all'
    ? ENDPOINT
    : `${ENDPOINT}?semester=${encodeURIComponent(semester)}`;

  // await pausiert nur diese async-Funktion, nicht die gesamte Browseroberfläche.
  const response = await fetch(url);

  // fetch() betrachtet auch HTTP 400 oder 500 als technisch empfangene
  // Antwort und wirft deshalb nicht automatisch. Die Statusprüfung ist nötig,
  // damit loadStoryData() zum Fallback wechseln kann.
  if (!response.ok) {
    throw new Error(`Der Endpunkt antwortet mit Status ${response.status}.`);
  }

  // Ein PHP-Warning oder falsch konfigurierter Server kann HTML statt JSON
  // zurückgeben. Diese gezielte Meldung ist verständlicher als der spätere
  // JSON-Parserfehler "Unexpected token <".
  const contentType = response.headers.get('content-type') ?? '';

  if (!contentType.includes('application/json')) {
    throw new Error('Die Antwort des Endpunkts ist kein JSON.');
  }

  // response.json() liest den Antworttext und wandelt ihn in Arrays/Objekte um.
  const data = await response.json();

  // unload.php kann trotz JSON-Format eine kontrollierte Fehlermeldung liefern.
  // Auch sie soll den normalen Fehler- und Fallbackweg auslösen.
  if (data.error) {
    throw new Error(data.error);
  }

  return data;
}

/**
 * Bildet den Serverfilter auf der lokalen Gesamtdatei nach.
 *
 * Der Endpunkt kann bereits in SQL auf ein Semester begrenzen. Die statische
 * Fallback-Datei enthält dagegen immer alle Semester. Mit Objekt- und Array-
 * Spread entsteht eine gefilterte Kopie, ohne das Originalobjekt zu verändern.
 */
function filterFallback(data, semester) {
  // Ohne Filter kann dasselbe Objekt unverändert zurückgegeben werden.
  if (semester === 'all') {
    return data;
  }

  return {
    // Zuerst alle unveränderten obersten Felder übernehmen.
    ...data,
    meta: {
      ...data.meta,

      // In den Metadaten soll nur das tatsächlich gelieferte Semester stehen.
      semester_order: [semester],
    },

    // Jede fachliche Reihe wird nach demselben Semestercode gefiltert.
    semesters: data.semesters.filter((row) => row.semester === semester),
    weekly: data.weekly.filter((row) => row.semester === semester),
    weekdays: data.weekdays.filter((row) => row.semester === semester),
    modes: data.modes.filter((row) => row.semester === semester),
  };
}

/** Lädt die statische JSON-Datei und wendet bei Bedarf den Browserfilter an. */
async function loadFallback(semester) {
  const response = await fetch(FALLBACK_DATA);

  // Fehlt auch der Fallback, kann die Story nicht gerendert werden. Der Fehler
  // wandert bis zum abschliessenden catch von init().
  if (!response.ok) {
    throw new Error(`Auch die Musterdaten fehlen (Status ${response.status}).`);
  }

  return filterFallback(await response.json(), semester);
}

/**
 * Gemeinsamer Einstieg für beide Datenquellen.
 *
 * Die Datenbank hat Vorrang. Jeder Fehler beim Endpoint wird protokolliert,
 * aber nicht zur Nutzerin durchgereicht, solange die lokale Datei funktioniert.
 * Die Variable source ermöglicht später einen ehrlichen Statustext.
 */
async function loadStoryData(semester) {
  try {
    const data = await loadFromEndpoint(semester);
    source = 'endpoint';
    return data;
  } catch (error) {
    // Die technische Ursache bleibt für die Entwicklung in der Konsole sichtbar.
    console.warn('Endpunkt nicht erreichbar, nutze Musterdaten:', error.message);
    source = 'fallback';
    return await loadFallback(semester);
  }
}

// ---------------------------------------------------------------------------
// Kennzahlen und Diagramme rendern
// ---------------------------------------------------------------------------

/** Berechnet und schreibt die drei grossen Kennzahlen am Seitenanfang. */
function renderMetrics() {
  // Alle drei Datenbereiche folgen derselben aktuellen Semesterauswahl.
  const semesters = selectedRows(storyData.semesters);
  const weekdays = selectedRows(storyData.weekdays);
  const modes = selectedRows(storyData.modes);

  // blocks steht bereits in der Semesterzusammenfassung. Bei "alle" werden
  // die vier Werte addiert; bei einem Filter enthält die Liste nur einen Wert.
  const blocks = sum(semesters.map((row) => row.blocks));

  // Für jeden sichtbaren Wochentag werden die Stunden aller ausgewählten
  // Semester addiert. index ist 0 für Montag bis 4 für Freitag.
  const weekdayTotals = storyData.meta.weekday_labels.map((_, index) =>
    sum(weekdays.map((row) => row.values[index])),
  );

  // Der Freitaganteil braucht die Summe aller fünf Wochentage als Nenner.
  const totalWeekdayHours = sum(weekdayTotals);

  // Moduswerte besitzen benannte Schlüssel. flatMap() macht aus allen
  // Semesterobjekten eine einzige Liste von Präsenz-, Online- und Offen-Stunden.
  const onlineHours = sum(modes.map((row) => row.values.online));
  const totalModeHours = sum(modes.flatMap((row) => Object.values(row.values)));

  // textContent setzt ausschliesslich Text und interpretiert keine HTML-Tags.
  // Intl.NumberFormat sorgt für die zur gewählten Sprache passende Darstellung.
  blocksMetric.textContent = new Intl.NumberFormat('de-CH').format(blocks);

  // weekdayTotals[4] ist Freitag, weil die Datenreihe bei Montag mit Index 0 beginnt.
  fridayMetric.textContent = formatPercent(weekdayTotals[4] / totalWeekdayHours);
  onlineMetric.textContent = formatPercent(onlineHours / totalModeHours);
}

/** Aktualisiert das Liniendiagramm der relativen Semesterwochen. */
function renderWeeklyChart() {
  const rows = selectedRows(storyData.weekly);

  // Semester können unterschiedlich viele Kalenderwochen umfassen. Die
  // längste Reihe bestimmt die gemeinsame x-Achse; kürzere Reihen enden früher.
  const longestSeries = Math.max(...rows.map((row) => row.values.length));

  // Array.from erzeugt die sichtbaren Wochennummern 1, 2, 3, ...
  weeklyChart.data.labels = Array.from({ length: longestSeries }, (_, index) => index + 1);

  // Jede Semesterreihe aus dem Datenvertrag wird zu einem Chart.js-Dataset.
  weeklyChart.data.datasets = rows.map((row) => ({
    label: `${row.name} · ${row.semester}`,
    data: row.values,

    // Linien- und Punktfarbe bleiben über alle Diagramme konsistent.
    borderColor: COLORS[row.semester],
    backgroundColor: COLORS[row.semester],

    // Eine einzelne Linie darf kräftiger sein als vier Vergleichslinien.
    borderWidth: selectedSemester === 'all' ? 2.5 : 3.5,
    pointRadius: 2,
    pointHoverRadius: 5,

    // tension rundet die Linie leicht. spanGaps:false verbindet keine fehlenden Werte.
    tension: 0.28,
    spanGaps: false,
  }));

  // Der Begleittext oberhalb des Diagramms spiegelt die Auswahl wider.
  weeklyNote.textContent = selectedSemester === 'all'
    ? 'Vier Semester im Vergleich'
    : `${rows[0].name} · ${rows[0].semester}`;

  // update() zeichnet dieselbe Chart-Instanz mit den neuen Daten neu.
  weeklyChart.update();
}

/** Aktualisiert das gestapelte Balkendiagramm von Montag bis Freitag. */
function renderWeekdayChart() {
  const rows = selectedRows(storyData.weekdays);

  // Beschriftungen kommen aus dem Backendvertrag und bleiben dadurch sicher
  // in derselben Reihenfolge wie die fünf Werte jedes Semesters.
  weekdayChart.data.labels = storyData.meta.weekday_labels;

  // Ein Dataset entspricht einem Semester. Weil beide Achsen stacked:true
  // verwenden, werden mehrere Semester pro Wochentag übereinander gezeichnet.
  weekdayChart.data.datasets = rows.map((row) => ({
    label: row.semester,
    data: row.values,
    backgroundColor: COLORS[row.semester],

    // Abgerundete Balkenenden sind rein gestalterisch.
    borderRadius: 5,
    borderSkipped: false,
  }));

  weekdayChart.update();
}

/** Aktualisiert das Ringdiagramm der drei Unterrichtsmodi. */
function renderModeChart() {
  const rows = selectedRows(storyData.modes);

  // Diese Reihenfolge stimmt mit den drei Dataset-Farben bei der Initialisierung
  // überein und bestimmt zugleich Reihenfolge von Legende und Segmenten.
  const modeKeys = ['in_person', 'online', 'unclear'];

  // Technische Schlüssel werden über meta.mode_labels lesbar beschriftet.
  modeChart.data.labels = modeKeys.map((key) => storyData.meta.mode_labels[key]);

  // Bei der Gesamtansicht werden die Stunden eines Modus über alle Semester
  // addiert. Bei einem Filter enthält rows nur das ausgewählte Semester.
  modeChart.data.datasets[0].data = modeKeys.map((key) =>
    sum(rows.map((row) => row.values[key])),
  );

  modeNote.textContent = selectedSemester === 'all'
    ? 'Alle vier Semester'
    : `${rows[0].name} · ${rows[0].semester}`;
  modeChart.update();
}

/**
 * Zentraler Render-Einstieg. Jede Änderung der Daten oder Auswahl aktualisiert
 * stets alle abhängigen Ansichten und kann dadurch keine veraltete Grafik
 * zurücklassen.
 */
function render() {
  renderMetrics();
  renderWeeklyChart();
  renderWeekdayChart();
  renderModeChart();
}

// ---------------------------------------------------------------------------
// Laden, Interaktion und Fehlerzustände
// ---------------------------------------------------------------------------

/** Lädt die aktuelle Auswahl neu und zeichnet anschliessend die ganze Story. */
async function reload() {
  // Sofortige Rückmeldung während der asynchronen Netzwerkabfrage.
  statusText.textContent = 'Daten werden geladen …';
  storyData = await loadStoryData(selectedSemester);

  // Die Audit-Werte kommen bei beiden Datenquellen im selben Format an.
  document.querySelector('#audit-duplicates').textContent = storyData.audit.duplicate_rows_removed;
  document.querySelector('#audit-duration').textContent = storyData.audit.duration_rows_corrected;

  // Ein gültiger, aber leerer Filter ist kein technischer Fehler. Die Seite
  // meldet den Zustand und zeichnet keine Diagramme mit fehlenden Reihen.
  if (storyData.semesters.length === 0) {
    statusText.textContent = 'Für diese Auswahl gibt es keine Daten.';
    return;
  }

  // Die sichtbare Meldung legt offen, ob echte Datenbankdaten oder der lokale
  // Ersatz verwendet werden. Das ist für eine Datengeschichte methodisch wichtig.
  statusText.textContent = source === 'endpoint'
    ? 'Daten aus der Datenbank.'
    : 'Daten aus der lokalen Fallback-Datei.';

  render();
}

/** Registriert die Bedienung und startet den ersten Datenabruf. */
async function init() {
  // Bei jeder Auswahl wird zuerst der zentrale Zustand geändert und danach der
  // Server mit demselben Filter neu angefragt. So zeigt das Frontend immer den
  // Datenvertrag, den auch ein direkter Endpoint-Aufruf liefern würde.
  semesterSelect.addEventListener('change', async () => {
    selectedSemester = semesterSelect.value;

    try {
      await reload();
    } catch (error) {
      // Dieser Zweig wird erreicht, wenn sowohl Endpoint als auch Fallback
      // scheitern. Details bleiben für die Entwicklung in der Konsole.
      console.error(error);
      statusText.textContent = 'Die Daten konnten nicht geladen werden.';
    }
  });

  // Erstbefüllung mit selectedSemester="all".
  await reload();
}

// init() ist asynchron und gibt ein Promise zurück. Der abschliessende catch
// fängt auch Fehler des allerersten Ladevorgangs ab, die innerhalb des späteren
// change-Handlers noch nicht behandelt werden konnten.
init().catch((error) => {
  console.error(error);

  // Wenn gar keine Datenquelle funktioniert, ersetzt eine verständliche
  // Anleitung den unvollständigen Hauptinhalt. Der Header bleibt sichtbar.
  document.querySelector('main').innerHTML = `
    <section class="method shell">
      <h2>Die Daten konnten nicht geladen werden.</h2>
      <p>Starte die Seite über einen lokalen Webserver, zum Beispiel mit <code>php -S localhost:8000</code>.</p>
    </section>
  `;
});
