import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
// Dynamically load Chart.js after Alpine initialization
import("chart.js/auto").then((ChartModule) => {
  const Chart = ChartModule?.default ?? ChartModule;
  window.Chart = Chart;
}).catch((err) => {
  console.error("Chart.js failed to load", err);
});
