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

  // Kapitel heiraten
  setText('peak-marriages-year', summary.peak_marriages.year);
  setText('peak-marriages-value', number.format(summary.peak_marriages.value));

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
};

Chart.defaults.font.family = styles.getPropertyValue('--font-text').trim();
Chart.defaults.color = COLORS.brown;

/**
 * Erstellt ein Liniendiagramm über alle Jahre.
 * canvasId: ID des Canvas, values: Datenreihe,
 * unit: Text für Tooltip und y-Achse, color: Linienfarbe
 */
function createLineChart(canvasId, values, unit, color) {
  return new Chart(document.getElementById(canvasId), {
    type: 'line',
    data: {
      labels: data.years,
      datasets: [{
        label: unit,
        data: values,
        borderColor: color,
        borderWidth: 3,
        pointRadius: 0,
        pointHoverRadius: 5,
        tension: 0.3,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (item) => `${number.format(item.raw)} ${unit}`,
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
          title: { display: true, text: unit },
          ticks: { callback: (value) => number.format(value) },
        },
      },
    },
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

/**
 * Chart.js-Plugin: zeichnet eine gestrichelte senkrechte Linie mit
 * Beschriftung bei einem bestimmten Jahr.
 * Einstellungen kommen aus options.plugins.yearMarker: { year, label, color }
 */
const yearMarker = {
  id: 'yearMarker',

  afterDatasetsDraw(chart, args, options) {
    const index = data.years.indexOf(options.year);

    // Jahr nicht in den Daten (z. B. anderer Zeitraum): nichts zeichnen
    if (index === -1) {
      return;
    }

    const { ctx, chartArea, scales } = chart;
    const x = scales.x.getPixelForValue(index);

    ctx.save();

    // Gestrichelte Linie von oben nach unten
    ctx.strokeStyle = options.color;
    ctx.lineWidth = 1.5;
    ctx.setLineDash([4, 4]);
    ctx.beginPath();
    ctx.moveTo(x, chartArea.top);
    ctx.lineTo(x, chartArea.bottom);
    ctx.stroke();

    // Beschriftung links neben der Linie
    ctx.setLineDash([]);
    ctx.fillStyle = options.color;
    ctx.font = `600 12px ${Chart.defaults.font.family}`;
    ctx.textAlign = 'right';
    ctx.fillText(options.label, x - 6, chartArea.top + 14);

    ctx.restore();
  },
};

const lawChange = {
  year: 2000,
  label: 'Neues Scheidungsrecht',
  color: COLORS.coral,
};

renderNumbers();
await document.fonts.ready;
createLineChart('marriages-rate-chart', data.marriages.per_1000, 'Heiraten pro 1000 Einwohner', COLORS.red);
createLineChart('marriages-chart', data.marriages.total, 'Heiraten', COLORS.red);
createAgeChart();
createDurationChart();
createLineChart('divorces-chart', data.divorces.total, 'Scheidungen', COLORS.red, lawChange);
createLineChart('divorces-rate-chart', data.divorces.per_1000, 'Scheidungen pro 1000 Einwohner', COLORS.red, lawChange);

document.querySelectorAll('.slider').forEach(initSlider);