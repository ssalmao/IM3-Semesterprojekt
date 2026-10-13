import { initSlider } from './slider.js';
import { initReveal } from './reveal.js';

initReveal();

/* Primäre Datenquelle auf derselben Domain und stabile Ersatzquelle für eine
lokale Demonstration oder einen vorübergehenden Datenbankausfall. */
const ENDPOINT = 'unload.php';
const FALLBACK_DATA = 'data/story-summary.json';

async function loadJson(url) {
  const response = await fetch (url);

  if (!response.ok) {
    throw new Error(`${url} antwortet mit Status ${response.status}`);
  }

  const data = await response.json();

  if (data.error){
    throw new Error(data.error);
  }

  return data;
}

async function loadData() {
  try {
    const data = await loadJson(ENDPOINT);
    return { data, source: 'Datenbank' };
  } catch (error) {
    console.warn('Endpunkt nicht erreichbar, nutze Fallback:', error.message);
    const data = await loadJson(FALLBACK_DATA);
    return { data, source: 'Fallback-Datei' };
  }
}

const statusText = document.querySelector('#status');

const { data, source } = await loadData();

console.log(data);
statusText.textContent = `Daten aus der ${source}.`;

//* Wandelt zahlen in Schweizer Schreibweise um
const number = new Intl.NumberFormat('de-CH', { maximumFractionDigits: 1 });

function setText(id, value) {
  document.getElementById(id).textContent = value;
}

//* Behält aus einer Jahresreihe nur die Werte von 1975, 1985, …, 2025.
function everyTenYears(values) {
  return values.filter((_, index) => data.years[index] % 10 === 5);
}

function renderNumbers() {
  const { summary, audit } = data;
  const last = data.years.length - 1;

  // Hero: Mittelwert der Altersverschiebung von Männern und Frauen
  const ageChange = (summary.age_change_men + summary.age_change_women) / 2;
  setText('hero-years', Math.round(ageChange));
  setText('hero-from', data.years[0]);


  // Kapitel Alter Erstheirat
  setText('age-change-men', number.format(summary.age_change_men));
  setText('age-change-women', number.format(summary.age_change_women));

  // Kapitel Dauer der Ehe
  setText('duration-change', number.format(summary.duration_change));

  // Grosse Aussage: jeweils der letzte Wert der Reihe
  setText('statement-year', data.years[last]);
  setText('statement-duration', number.format(data.duration[last]));

  // Kapitel Scheidungen
  setText('peak-divorces-year', summary.peak_divorces.year);
  setText('peak-divorces-value', number.format(summary.peak_divorces.value));

  // Methode
  setText('audit-source', audit.source_rows);
  setText('audit-invalid', audit.invalid_rows);
  setText('audit-duplicates', audit.duplicate_rows_removed);
}

//*Farben aus CSS holen und in Grafik integrieren
const styles = getComputedStyle(document.documentElement);

const COLORS = {
  red: styles.getPropertyValue('--red-dark').trim(),
  coral: styles.getPropertyValue('--coral').trim(),
  brown: styles.getPropertyValue('--brown').trim(),
  rose: styles.getPropertyValue('--rose').trim(),
};

Chart.defaults.font.family = styles.getPropertyValue('--font-text').trim();
Chart.defaults.color = COLORS.red;

// Erklärungen zu einzelnen Jahren: Jahr → Textzeilen der Infobox
const divorceNotes = {
  1999: ['Letztes Jahr nach altem Recht:', 'Viele Paare liessen sich noch', 'vor der Gesetzesänderung scheiden.'],
  2000: ['Neues Scheidungsrecht ab 1. Januar:', 'Die neuen Verfahren dauerten', 'zuerst länger.'],
};

const marriageNotes = {
  2020: ['Corona-Pandemie: Viele Hochzeiten', 'wurden verschoben.'],
  2022: ['Nachholeffekt: Verschobene', 'Hochzeiten wurden gefeiert.'],
};

/**
 * Erstellt ein Liniendiagramm über alle Jahre.
 * canvasId: ID des Canvas, values: Datenreihe,
 * unit: Text für Tooltip und y-Achse, color: Linienfarbe,
 * notes: optionale Erklärungen { Jahr: ['Zeile 1', 'Zeile 2'] }
 */
function createLineChart({ canvasId, values, unit, color, notes = {} }) {
  const hasNote = (index) => data.years[index] in notes;

  return new Chart(document.getElementById(canvasId), {
    type: 'line',
    data: {
      labels: data.years,
      datasets: [{
        label: unit,
        data: values,
        borderColor: color,
        borderWidth: 3,
        tension: 0.3,
        /* Nur Jahre mit Erklärung bekommen einen sichtbaren Punkt */
        pointRadius: (context) => (hasNote(context.dataIndex) ? 6 : 0),
        pointHoverRadius: (context) => (hasNote(context.dataIndex) ? 9 : 5),
        pointBackgroundColor: (context) => (hasNote(context.dataIndex) ? COLORS.coral : color),
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
      }],
    },

    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        // Tooltip als kleine Infobox
        tooltip: {
          backgroundColor: '#ffffff',
          titleColor: COLORS.red,
          bodyColor: COLORS.brown,
          footerColor: COLORS.brown,
          footerFont: { weight: 'normal' },
          borderColor: COLORS.coral,
          borderWidth: 1,
          cornerRadius: 8,
          padding: 12,
          displayColors: false,
          callbacks: {
            label: (item) => `${number.format(item.raw)} ${unit}`,
            footer: (items) => notes[data.years[items[0].dataIndex]] ?? [],
          },
        },
      },

      scales: {
        x: {
          grid: { display: false },
          ticks: {
            autoSkip: false,
            maxRotation: 0,
            callback: (value, index) => (data.years[index] % 10 === 5 ? data.years[index] : ''),
          },
        },
        y: {
          beginAtZero: true,
          ticks: { callback: (value) => number.format(value) },
        },
      },
    },
  });
}

/** Liniendiagramm: Heiraten pro 1000 Einwohner (Kapitel «Heiraten», Slide 1) */
function createMarriagesRateChart() {
  return createLineChart({
    canvasId: 'marriages-rate-chart',
    values: data.marriages.per_1000,
    unit: 'Heiraten pro 1000 Einwohner',
    color: COLORS.red,
    notes: marriageNotes,
  });
}

/** Liniendiagramm: Anzahl Heiraten pro Jahr (Kapitel «Heiraten», Slide 1) */
function createMarriagesChart() {
  return createLineChart({
    canvasId: 'marriages-chart',
    values: data.marriages.total,
    unit: 'Heiraten',
    color: COLORS.red,
    notes: marriageNotes,
  });
}

/** Liniendiagramm: Scheidungen pro 1000 Einwohner (Kapitel «Scheidungen», Slide 2) */
function createDivorcesRateChart() {
  return createLineChart({
    canvasId: 'divorces-rate-chart',
    values: data.divorces.per_1000,
    unit: 'Scheidungen pro 1000 Einwohner',
    color: COLORS.red,
    notes: divorceNotes,
  });
}

/** Liniendiagramm: Anzahl Scheidungen pro Jahr (Kapitel «Scheidungen», Slide 2) */
function createDivorcesChart() {
  return createLineChart({
    canvasId: 'divorces-chart',
    values: data.divorces.total,
    unit: 'Scheidungen',
    color: COLORS.red,
    notes: divorceNotes,
  });
}


//* Gruppiertes Balkendiagramm: Alter bei der Erstheirat, Männer und Frauen alle zehn Jahre.
function createAgeChart() {
  return new Chart(document.getElementById('age-chart'), {
    type: 'bar',
    data: {
      labels: everyTenYears(data.years),
      datasets: [
        {
          label: data.meta.series_labels.men,
          data: everyTenYears(data.age.men),
          backgroundColor: COLORS.brown,
          borderRadius: 4,
        },
        {
          label: data.meta.series_labels.women,
          data: everyTenYears(data.age.women),
          backgroundColor: COLORS.coral,
          hoverBackgroundColor: COLORS.rose,
          borderRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { usePointStyle: true, boxWidth: 8 },
        },
        tooltip: {
          callbacks: {
            label: (item) => `${item.dataset.label}: ${number.format(item.raw)} Jahre`,
          },
        },
      },
      scales: {
        x: { grid: { display: false } },
        y: {
          beginAtZero: true,
          title: { display: true, text: 'Alter in Jahren' },
        },
      },
    },
  });
}

/**
 * Horizontales Balkendiagramm: durchschnittliche Ehedauer bis zur Scheidung,
 * alle zehn Jahre. Das letzte Jahr ist farblich hervorgehoben.
 */
function createDurationChart() {
  const values = everyTenYears(data.duration);
  const lastIndex = values.length - 1;

  return new Chart(document.getElementById('duration-chart'), {
    type: 'bar',
    data: {
      labels: everyTenYears(data.years),
      datasets: [{
        label: 'Ehedauer bis zur Scheidung',
        data: values,
        backgroundColor: values.map((_, index) => (index === lastIndex ? COLORS.coral : COLORS.brown)),
        borderRadius: 4,
        barPercentage: 0.5,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (item) => `${number.format(item.raw)} Jahre`,
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          title: { display: true, text: 'Jahre bis zur Scheidung' },
        },
        y: { grid: { display: false } },
      },
    },
  });
}

renderNumbers();
await document.fonts.ready;

/* Grafiken / Diagramme werden erstellt */
createMarriagesChart();
createMarriagesRateChart();
createAgeChart();
createDurationChart();
createDivorcesChart();
createDivorcesRateChart();

document.querySelectorAll('.slider').forEach(initSlider);