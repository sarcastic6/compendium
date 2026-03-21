import { Tooltip } from 'bootstrap';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import './styles/app.css';
import './js/dynamic-collection.js';
import './js/metadata-sections.js';
import './stimulus_bootstrap.js';

// Initialise all Bootstrap tooltips globally.
// data-bs-trigger="hover focus click" makes them work on touch as well.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new Tooltip(el));
});
