import { Controller } from '@hotwired/stimulus';

/**
 * Generic Chart.js controller.
 *
 * Usage:
 *   <canvas data-controller="chart"
 *           data-chart-type-value="bar"
 *           data-chart-labels-value='["Jan","Feb","Mar"]'
 *           data-chart-datasets-value='[{"label":"Entries","data":[3,7,2]}]'
 *           data-chart-options-value='{"indexAxis":"y"}'
 *   ></canvas>
 *
 * - data-chart-type-value:     Chart.js chart type (bar, doughnut, pie, line, …)
 * - data-chart-labels-value:   JSON array of axis labels
 * - data-chart-datasets-value: JSON array of dataset objects (Chart.js format)
 * - data-chart-options-value:  (optional) JSON object merged into Chart.js options
 *
 * Chart.js is loaded lazily via dynamic import so it is only fetched on pages
 * that include at least one chart element.
 */
export default class extends Controller {
    static values = {
        type: String,
        labels: Array,
        datasets: Array,
        options: { type: Object, default: {} },
    };

    /** @type {import('chart.js').Chart|null} */
    #chart = null;

    async connect() {
        const { Chart } = await import('chart.js/auto');

        const defaultColors = [
            'rgba(13,110,253,0.75)',
            'rgba(25,135,84,0.75)',
            'rgba(255,193,7,0.85)',
            'rgba(220,53,69,0.75)',
            'rgba(13,202,240,0.75)',
            'rgba(111,66,193,0.75)',
            'rgba(253,126,20,0.75)',
            'rgba(32,201,151,0.75)',
        ];

        // Inject default background colors when datasets omit them
        const datasets = this.datasetsValue.map((ds, i) => ({
            backgroundColor: defaultColors[i % defaultColors.length],
            ...ds,
        }));

        this.#chart = new Chart(this.element, {
            type: this.typeValue,
            data: {
                labels: this.labelsValue,
                datasets,
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: ['doughnut', 'pie'].includes(this.typeValue),
                    },
                },
                ...this.optionsValue,
            },
        });
    }

    disconnect() {
        this.#chart?.destroy();
        this.#chart = null;
    }
}
