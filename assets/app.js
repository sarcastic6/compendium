import { Tooltip } from 'bootstrap';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import './styles/app.scss';
import './stimulus_bootstrap.js';

// Initialise all Bootstrap tooltips globally.
// data-bs-trigger="hover focus click" makes them work on touch as well.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new Tooltip(el));
});

// When a Bootstrap tab becomes visible, trigger a window resize so that
// Chart.js (responsive: true) redraws any charts that were hidden at init time.
document.addEventListener('shown.bs.tab', () => {
    window.dispatchEvent(new Event('resize'));
});
