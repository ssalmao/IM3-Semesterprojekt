/**
 * Beispielprojekt Hitzesommer – das Frontend der Datengeschichte.
 *
 * Aufgebaut wie die Code-Alongs 16 und 17, nur fertig gebaut:
 *
 *   Holen      unload.php fragen, bei Ausfall auf Musterdaten zurückfallen
 *   Umformen   aus 258 Datensätzen werden labels und datasets
 *   Zeichnen   drei Diagramme, eine einzige render()-Funktion
 *   Reagieren  drei Bedienelemente ändern den Zustand und zeichnen neu
 *
 * Die Seite läuft auf dem Webserver: hochladen und über die eigene Domain
 * öffnen. Zum schnellen Zeigen geht auch php -S localhost:8000 – dann mit den
 * Musterdaten.
 */

// ---------------------------------------------------------------------------
// Einstellungen
// ---------------------------------------------------------------------------
//
// Alles, was man beim Anpassen als Erstes sucht, steht zuoberst.

const ENDPUNKT = 'unload.php';
const MUSTERDATEN = 'data/heat-summers.json';

// Jede Stadt behält in allen drei Diagrammen ihre Farbe. Das ist keine
// Dekoration: In der Rangliste ist die Farbe die Beschriftung.
const CITY_COLORS = {
  Bern: '#4b93a4',
  Chur: '#b5723a',
  Zürich: '#8b8a6e',
};

// Die zwei Messwerte. Die Schlüssel heissen genau wie die Felder im
// Datenvertrag – deshalb genügt weiter unten row[metric] ohne jedes if.
const METRICS = {
  hot_days: {
    label: 'Hitzetage',
    unit: ' Tage',
    axis: 'Tage ab 30 °C',
    decadeAxis: 'Hitzetage pro Sommer (Mittel)',
    decimals: 1,
  },
  max_temperature_c: {
    label: 'Höchste Temperatur',
    unit: ' °C',
    axis: 'Grad Celsius',
    decadeAxis: 'Höchste Temperatur (Mittel)',
    decimals: 1,
  },
};

// Schrift und Farbe der Diagramme an die Seite angleichen. Ohne diese zwei
// Zeilen zeichnet Chart.js in seiner eigenen Schrift, und die Grafiken sehen
// aus wie hineinkopiert.
Chart.defaults.font.family = '"Avenir Next", "Helvetica Neue", Helvetica, Arial, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = '#7a7a72';

// ---------------------------------------------------------------------------
// Zustand
// ---------------------------------------------------------------------------
//
// Drei Variablen beschreiben, was gerade zu sehen ist. Jede Bedienung ändert
// genau eine davon und ruft danach render() auf.

let summers = [];
let fromYear = 1940;
let metric = 'hot_days';
let quelle = 'endpunkt';   // 'endpunkt' oder 'musterdaten'

// ---------------------------------------------------------------------------
// Elemente
// ---------------------------------------------------------------------------

const citySelect = document.querySelector('#city');
const statusText = document.querySelector('#status');
const fromYearInput = document.querySelector('#from-year');
const fromYearValue = document.querySelector('#from-year-value');
const metricSwitch = document.querySelector('#metric');

// ---------------------------------------------------------------------------
// Baustein 1: Holen
// ---------------------------------------------------------------------------

/**
 * Fragt den eigenen Endpunkt. Der Stadtfilter wird an die URL gehängt und
 * dort zu einem WHERE im SQL.
 */
async function ladeVomEndpunkt(city) {
  const url = city === 'alle'
    ? ENDPUNKT
    : `${ENDPUNKT}?city=${encodeURIComponent(city)}`;

  const response = await fetch(url);

  // fetch() wirft keinen Fehler, wenn der Server mit 404 oder 500 antwortet –
  // es ist ja eine Antwort angekommen. Ohne diese Prüfung liefe sie weiter.
  if (!response.ok) {
    throw new Error(`Der Endpunkt antwortet mit Status ${response.status}.`);
  }

  // Fängt genau einen Fehler ab: Der Server hat das PHP nicht ausgeführt und
  // den Quelltext geschickt. Ohne die Prüfung meldet der Browser
  // «Unexpected token '<'», und man sucht im JavaScript statt im Server.
  const contentType = response.headers.get('content-type') ?? '';

  if (!contentType.includes('application/json')) {
    throw new Error('Die Antwort ist kein JSON.');
  }

  return await response.json();
}

/**
 * Der Fallback für den Marktstand.
 *
 * Die Musterdatei ist aus unload.php exportiert und hat exakt dieselbe Form.
 * Weil hier kein Server filtert, übernimmt das der Browser.
 */
async function ladeMusterdaten(city) {
  const response = await fetch(MUSTERDATEN);

  if (!response.ok) {
    throw new Error(`Auch die Musterdaten fehlen (Status ${response.status}).`);
  }

  const alle = await response.json();

  return city === 'alle' ? alle : alle.filter((row) => row.city === city);
}

/**
 * Erst der Endpunkt, sonst die Musterdaten. Welche Quelle es geworden ist,
 * merkt sich `quelle` – der Statustext sagt es der Leserin.
 */
async function loadSummers(city) {
  try {
    const rows = await ladeVomEndpunkt(city);
    quelle = 'endpunkt';
    return rows;
  } catch (error) {
    // Die genaue Meldung gehört in die Konsole, nicht auf die Seite.
    console.warn('Endpunkt nicht erreichbar, nutze Musterdaten:', error.message);
    quelle = 'musterdaten';
    return await ladeMusterdaten(city);
  }
}

// ---------------------------------------------------------------------------
// Baustein 2: Umformen
// ---------------------------------------------------------------------------
//
// Chart.js will keine Datensätze, sondern zwei Listen: labels für die Achse
// und data für die Werte. Beide müssen gleich lang sein und in derselben
// Reihenfolge stehen.

/** Der sichtbare Ausschnitt. Der Schieber filtert im Browser, ohne den Server
 *  zu fragen: Die Daten sind schon da. */
function visibleSummers() {
  return summers.filter((summer) => summer.year >= fromYear);
}

/** Jedes Jahr kommt dreimal vor, einmal pro Stadt. Set wirft die
 *  Wiederholungen weg. Ohne Vergleichsfunktion sortiert sort() als Text. */
function yearsOf(rows) {
  return [...new Set(rows.map((row) => row.year))].sort((a, b) => a - b);
}

/** Welche Städte stecken in den Daten? Mit Filter eine, sonst drei. */
function cityNamesOf(rows) {
  return [...new Set(rows.map((row) => row.city))].sort();
}

/** Eine Stadt wird eine Linie. */
function datasetFor(rows, city) {
  const cityRows = rows.filter((row) => row.city === city);

  return {
    label: city,
    data: cityRows.map((row) => row[metric]),
    borderColor: CITY_COLORS[city] ?? '#575757',
    backgroundColor: CITY_COLORS[city] ?? '#575757',
    borderWidth: 2,
    tension: 0.3,
    pointRadius: 0,
    pointHoverRadius: 5,
  };
}

/** Die Rangliste: sortieren, dann abschneiden.
 *  [...rows] macht eine Kopie – sort() verändert sonst die Liste selbst. */
function topSummers(rows, count = 10) {
  return [...rows]
    .sort((a, b) => b[metric] - a[metric])
    .slice(0, count);
}

/**
 * Die Jahrzehnte.
 *
 * Math.floor(1947 / 10) * 10 ergibt 1940 – das ist die ganze Rechnung. Danach
 * wird pro Jahrzehnt und Stadt der Mittelwert gebildet.
 *
 * Gerundet wird erst hier, in der Darstellung. Wer schon beim Rechnen rundet,
 * verschiebt das Ergebnis.
 */
function decadesOf(rows) {
  return [...new Set(rows.map((row) => Math.floor(row.year / 10) * 10))]
    .sort((a, b) => a - b);
}

function decadeMean(rows, decade, city) {
  const passend = rows.filter(
    (row) => row.city === city && Math.floor(row.year / 10) * 10 === decade,
  );

  if (passend.length === 0) {
    return null;   // keine Daten – und nicht etwa null Hitzetage
  }

  const summe = passend.reduce((total, row) => total + row[metric], 0);

  return Number((summe / passend.length).toFixed(METRICS[metric].decimals));
}

// ---------------------------------------------------------------------------
// Baustein 3: Zeichnen
// ---------------------------------------------------------------------------
//
// Die Einstellungen stehen neben den Diagrammen und nicht darin. Sie sind
// Fleissarbeit; nachschlagen kann man sie in der Chart.js-Dokumentation.

const gitterFarbe = 'rgba(51, 51, 47, 0.08)';

const verlaufOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  scales: {
    x: {
      title: { display: true, text: 'Sommer' },
      ticks: { maxTicksLimit: 12 },
      grid: { display: false },
    },
    y: {
      beginAtZero: true,
      title: { display: true, text: '' },
      grid: { color: gitterFarbe },
      border: { display: false },
    },
  },
  plugins: {
    legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
    tooltip: {
      callbacks: {
        label: (item) =>
          `${item.dataset.label}: ${item.formattedValue}${METRICS[metric].unit}`,
      },
    },
  },
};

const dekadenOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: { grid: { display: false } },
    y: {
      beginAtZero: true,
      title: { display: true, text: '' },
      grid: { color: gitterFarbe },
      border: { display: false },
    },
  },
  plugins: {
    legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
    tooltip: {
      callbacks: {
        label: (item) =>
          `${item.dataset.label}: ${item.formattedValue}${METRICS[metric].unit}`,
      },
    },
  },
};

const ranglisteOptions = {
  responsive: true,
  maintainAspectRatio: false,

  // Der eine Unterschied zum stehenden Balkendiagramm. Beschriftungen wie
  // «2003 · Zürich» brauchen Platz, und den gibt es links.
  indexAxis: 'y',

  scales: {
    x: {
      beginAtZero: true,
      title: { display: true, text: '' },
      grid: { color: gitterFarbe },
      border: { display: false },
    },
    y: { grid: { display: false } },
  },
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (item) => `${item.formattedValue}${METRICS[metric].unit}`,
      },
    },
  },
};

// Jedes Diagramm wird genau einmal erzeugt und danach nur noch aktualisiert.
// Ein zweites new Chart() auf demselben Canvas gibt «Canvas is already in use».

const verlaufChart = new Chart(document.querySelector('#verlauf'), {
  type: 'line',
  data: { labels: [], datasets: [] },
  options: verlaufOptions,
});

const dekadenChart = new Chart(document.querySelector('#dekaden'), {
  type: 'bar',
  data: { labels: [], datasets: [] },
  options: dekadenOptions,
});

const ranglisteChart = new Chart(document.querySelector('#rangliste'), {
  type: 'bar',
  data: { labels: [], datasets: [] },
  options: ranglisteOptions,
});

/**
 * Die einzige Stelle, die Diagramme anfasst.
 *
 * Egal was sich geändert hat – Stadt, Zeitraum oder Messwert: Hier wird aus
 * dem aktuellen Zustand das aktuelle Bild.
 */
function render() {
  const rows = visibleSummers();
  const cities = cityNamesOf(rows);

  // --- Verlauf ---
  verlaufChart.data.labels = yearsOf(rows);
  verlaufChart.data.datasets = cities.map((city) => datasetFor(rows, city));
  verlaufChart.options.scales.y.title.text = METRICS[metric].axis;

  // Eine Anzahl beginnt bei null, sonst wirkt jede Schwankung wie ein Sprung.
  // Bei Temperaturen wäre die Null sinnlos: Zwischen 0 und 26 Grad passiert in
  // diesen Daten nichts, und die Unterschiede verschwänden.
  verlaufChart.options.scales.y.beginAtZero = metric === 'hot_days';
  verlaufChart.update();

  // --- Jahrzehnte ---
  const decades = decadesOf(rows);

  dekadenChart.data.labels = decades.map((decade) => `${decade}er`);
  dekadenChart.data.datasets = cities.map((city) => ({
    label: city,
    data: decades.map((decade) => decadeMean(rows, decade, city)),
    backgroundColor: CITY_COLORS[city] ?? '#575757',
    borderWidth: 0,
  }));
  dekadenChart.options.scales.y.title.text = METRICS[metric].decadeAxis;
  dekadenChart.options.scales.y.beginAtZero = metric === 'hot_days';
  dekadenChart.update();

  // --- Rangliste ---
  const top = topSummers(rows);

  ranglisteChart.data.labels = top.map((row) => `${row.year} · ${row.city}`);
  ranglisteChart.data.datasets = [
    {
      label: METRICS[metric].label,
      data: top.map((row) => row[metric]),

      // Ein Balken pro Sommer, gefärbt nach Stadt – deshalb ist
      // backgroundColor hier eine Liste und keine einzelne Farbe.
      backgroundColor: top.map((row) => CITY_COLORS[row.city] ?? '#575757'),
      borderWidth: 0,
    },
  ];
  ranglisteChart.options.scales.x.title.text = METRICS[metric].axis;
  ranglisteChart.update();

  zeigeStatus(rows);
}

/**
 * Der Statustext.
 *
 * Er sagt drei Dinge: wie viele Sommer gerade gezeigt werden, welcher
 * Zeitraum das ist und woher die Daten kommen. Der letzte Punkt ist der
 * wichtigste – eine Seite, die heimlich Musterdaten zeigt, täuscht.
 */
function zeigeStatus(rows) {
  statusText.classList.remove('is-fallback', 'is-error');

  if (rows.length === 0) {
    statusText.textContent = 'Für diese Auswahl gibt es keine Daten.';
    return;
  }

  const jahre = yearsOf(rows);
  const ausschnitt =
    `${rows.length} Sommer · ${jahre[0]}–${jahre[jahre.length - 1]} · `
    + `${cityNamesOf(rows).join(', ')}`;

  if (quelle === 'musterdaten') {
    statusText.classList.add('is-fallback');
    statusText.textContent =
      `${ausschnitt} · aus gespeicherten Musterdaten – der Endpunkt antwortet nicht.`;
    return;
  }

  statusText.textContent = `${ausschnitt} · live aus der Datenbank.`;
}

// ---------------------------------------------------------------------------
// Baustein 4: Reagieren
// ---------------------------------------------------------------------------

/** Der Weg über den Server: neue Daten holen, dann neu zeichnen. */
async function reload() {
  statusText.classList.remove('is-fallback', 'is-error');
  statusText.textContent = 'Daten werden geladen …';

  try {
    summers = await loadSummers(citySelect.value);
    render();
  } catch (error) {
    console.error(error);
    statusText.classList.add('is-error');
    statusText.textContent =
      `Die Daten konnten nicht geladen werden. ${error.message}`;
  }
}

// Die Stadt fragt den Endpunkt neu an: Auswahl -> ?city=Bern -> WHERE im SQL
// -> kleinere Antwort -> neue Diagramme.
citySelect.addEventListener('change', reload);

// Der Zeitraum bleibt im Browser. Kein fetch(), kein Warten: Der Zustand
// ändert sich, render() zeichnet neu. «input» feuert schon beim Ziehen.
fromYearInput.addEventListener('input', () => {
  fromYear = Number(fromYearInput.value);
  fromYearValue.textContent = fromYear;
  render();
});

// Der Messwert ebenfalls. Der geklickte Knopf bekommt is-active, die anderen
// verlieren es – so weiss die Leserin, was gerade gezeigt wird.
for (const button of metricSwitch.querySelectorAll('button')) {
  button.addEventListener('click', () => {
    metric = button.dataset.metric;

    for (const other of metricSwitch.querySelectorAll('button')) {
      other.classList.toggle('is-active', other === button);
      other.setAttribute('aria-pressed', String(other === button));
    }

    render();
  });
}

// ---------------------------------------------------------------------------
// Beiwerk: Abschnitte einblenden
// ---------------------------------------------------------------------------
//
// Reine Gestaltung, kein Datenteil. Ohne JavaScript bleibt alles sichtbar,
// weil die Klasse is-visible dann einfach nie dazukommt – deshalb setzt erst
// dieser Code die Startwerte, die etwas verstecken.

const beobachter = new IntersectionObserver((eintraege) => {
  for (const eintrag of eintraege) {
    if (eintrag.isIntersecting) {
      eintrag.target.classList.add('is-visible');
      beobachter.unobserve(eintrag.target);
    }
  }
}, { rootMargin: '0px 0px -8% 0px' });

for (const element of document.querySelectorAll('.reveal')) {
  beobachter.observe(element);
}

// ---------------------------------------------------------------------------
// Start
// ---------------------------------------------------------------------------

reload();
