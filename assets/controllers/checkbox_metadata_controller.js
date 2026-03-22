import { Controller } from '@hotwired/stimulus';

/**
 * Checkbox group controller for small-vocabulary metadata types on the work form.
 *
 * When a checkbox is checked, hidden form fields are emitted into the form using
 * the same work_form[metadata][N][name/metadataType/link] structure as the autocomplete
 * controller. The shared data-metadata-index counter on the <form> element coordinates
 * index assignment across all metadata controllers.
 *
 * On connect(), pre-checked checkboxes (from AO3 import or form re-render) emit fields
 * immediately so their values are included in the submission without user interaction.
 *
 * Usage: place data-controller="checkbox-metadata" on the container div.
 * Each <input type="checkbox"> inside must have:
 *   value="{metadataName}"
 *   data-type-id="{metadataTypeId}"
 */
export default class extends Controller {
    /** @type {HTMLFormElement|null} */
    #form = null;
    /** @type {Map<string, HTMLElement>} key => hidden fields wrapper */
    #emittedGroups = new Map();
    /** @type {EventListener|null} */
    #handleChange = null;

    connect() {
        this.#form = this.element.closest('form');
        this.#handleChange = this.#onChange.bind(this);
        this.element.addEventListener('change', this.#handleChange);

        // Emit hidden fields for any checkboxes that are already checked on page load
        // (from AO3 import pre-population or a POST re-render after validation failure).
        this.element.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
            if (cb.checked) {
                this.#emit(cb);
            }
        });
    }

    disconnect() {
        if (this.#handleChange) {
            this.element.removeEventListener('change', this.#handleChange);
        }
        // Clean up any emitted fields if the element is removed from the DOM.
        this.#emittedGroups.forEach((wrapper) => wrapper.remove());
        this.#emittedGroups.clear();
    }

    #onChange(event) {
        const cb = event.target;
        if (!(cb instanceof HTMLInputElement) || cb.type !== 'checkbox') return;

        if (cb.checked) {
            this.#emit(cb);
        } else {
            this.#unemit(cb);
        }
    }

    #emit(cb) {
        const name = cb.value;
        const typeId = cb.dataset.typeId ?? '';
        const key = `${typeId}:${name}`;

        if (this.#emittedGroups.has(key)) return; // Already emitted (idempotent).

        const index = this.#getNextIndex();
        const prefix = 'work_form[metadata]';

        const wrapper = document.createElement('div');
        wrapper.style.display = 'none';
        wrapper.appendChild(this.#hidden(`${prefix}[${index}][name]`, name));
        wrapper.appendChild(this.#hidden(`${prefix}[${index}][metadataType]`, typeId));
        wrapper.appendChild(this.#hidden(`${prefix}[${index}][link]`, ''));

        this.#form?.appendChild(wrapper);
        this.#emittedGroups.set(key, wrapper);
    }

    #unemit(cb) {
        const key = `${cb.dataset.typeId ?? ''}:${cb.value}`;
        const wrapper = this.#emittedGroups.get(key);
        if (wrapper) {
            wrapper.remove();
            this.#emittedGroups.delete(key);
        }
    }

    #getNextIndex() {
        if (!this.#form) return 0;
        const current = parseInt(this.#form.dataset.metadataIndex || '0', 10);
        this.#form.dataset.metadataIndex = String(current + 1);
        return current;
    }

    #hidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }
}
