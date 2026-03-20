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
 *           data-chart-urls-value='["/list?filter=1","/list?filter=2","/list?filter=3"]'
 *   ></canvas>
 *
 * - data-chart-type-value:     Chart.js chart type (bar, doughnut, pie, line, …)
 * - data-chart-labels-value:   JSON array of axis labels
 * - data-chart-datasets-value: JSON array of dataset objects (Chart.js format)
 * - data-chart-options-value:  (optional) JSON object merged into Chart.js options
 * - data-chart-urls-value:     (optional) JSON array of URLs, one per label. When provided,
 *                              clicking a bar/slice navigates to the corresponding URL.
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
        urls: { type: Array, default: [] },
    };

    /** @type {import('chart.js').Chart|null} */
    #chart = null;

    // Stored so they can be removed on disconnect()
    #clickHandler = null;
    #mousemoveHandler = null;
    #mouseleaveHandler = null;

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

        const isSegmented = ['doughnut', 'pie'].includes(this.typeValue);

        // Inject default background colors when datasets omit them.
        // For segmented charts (doughnut/pie), each slice needs its own colour,
        // so backgroundColor must be an array indexed by data point.
        // For other chart types, one colour per dataset is correct.
        const datasets = this.datasetsValue.map((ds, i) => ({
            backgroundColor: isSegmented
                ? (ds.data ?? []).map((_, j) => defaultColors[j % defaultColors.length])
                : defaultColors[i % defaultColors.length],
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

        // Attach click/hover handling via DOM listeners after the chart is created.
        // Using getElementsAtEventForMode is the Chart.js-documented approach for
        // programmatic click handling and is more reliable than options.onClick.
        if (this.urlsValue.length > 0) {
            this.#attachClickHandlers();
        }
    }

    disconnect() {
        if (this.#clickHandler) {
            this.element.removeEventListener('click', this.#clickHandler);
            this.element.removeEventListener('mousemove', this.#mousemoveHandler);
            this.element.removeEventListener('mouseleave', this.#mouseleaveHandler);
            this.#clickHandler = null;
            this.#mousemoveHandler = null;
            this.#mouseleaveHandler = null;
        }
        this.#chart?.destroy();
        this.#chart = null;
    }

    #attachClickHandlers() {
        const chart = this.#chart;
        const el = this.element;
        const urls = this.urlsValue;

        this.#clickHandler = (event) => {
            const points = chart.getElementsAtEventForMode(
                event, 'nearest', { intersect: true }, false,
            );
            if (points.length > 0) {
                const url = urls[points[0].index];
                if (url) window.location.href = url;
            }
        };

        this.#mousemoveHandler = (event) => {
            const points = chart.getElementsAtEventForMode(
                event, 'nearest', { intersect: true }, false,
            );
            el.style.cursor = points.length > 0 ? 'pointer' : 'default';
        };

        this.#mouseleaveHandler = () => {
            el.style.cursor = 'default';
        };

        el.addEventListener('click', this.#clickHandler);
        el.addEventListener('mousemove', this.#mousemoveHandler);
        el.addEventListener('mouseleave', this.#mouseleaveHandler);
    }
}
