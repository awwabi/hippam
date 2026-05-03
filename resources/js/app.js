import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

import Chart from 'chart.js/auto';

// Make Chart.js available globally for Blade views
window.Chart = Chart;
