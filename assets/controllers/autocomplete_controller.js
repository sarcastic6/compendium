import { Controller } from '@hotwired/stimulus';

/**
 * Autocomplete controller for the work form.
 *
 * Supports two modes:
 *   - Single-select (multiValue=false): series field — shows a text input with a dropdown;
 *     selecting a result sets a separate hidden field with the entity ID.
 *   - Multi-value (multiValue=true): authors, fandoms, characters, etc. — selected items
 *     become chips; each chip carries embedded hidden form fields.
 *
 * Configuration (Stimulus values on the controller element):
 *   data-autocomplete-url-value          API endpoint URL (fixed params included, e.g. ?typeId=3)
 *   data-autocomplete-min-chars-value    Minimum characters before searching (default: 2)
 *   data-autocomplete-allow-new-value    Show "Create: {term}" option (default: false)
 *   data-autocomplete-multi-value-value  Multi-value chip mode (default: false)
 *   data-autocomplete-field-prefix-value Hidden field name prefix, e.g. "work_form[metadata]"
 *   data-autocomplete-index-attribute-value  camelCase key on form.dataset for the shared index counter
 *   data-autocomplete-type-id-value      MetadataType entity ID (emitted into [metadataType] field)
 *
 * Targets:
 *   input         The visible text input the user types into
 *   results       The dropdown list element
 *   hiddenField   (single-select only) The hidden input that stores the selected entity ID
 *   chipContainer (multi-value only) The container for chip elements
 */
export default class extends Controller {
    static values = {
        url:            String,
        minChars:       { type: Number, default: 2 },
        allowNew:       { type: Boolean, default: false },
        multiValue:     { type: Boolean, default: false },
        fieldPrefix:    { type: String, default: '' },
        indexAttribute: { type: String, default: '' },
        typeId:         { type: Number, default: 0 },
    };

    static targets = ['input', 'results', 'hiddenField', 'chipContainer'];

    /** @type {HTMLFormElement|null} */
    #form = null;
    /** @type {ReturnType<typeof setTimeout>|null} */
    #debounceTimer = null;
    /** @type {Set<string>} Names already selected (for duplicate prevention in multi-value mode) */
    #selected = new Set();
    /** @type {number} Index of the highlighted dropdown item (-1 = none) */
    #highlighted = -1;
    /** @type {Array<{id?: number, name: string, isNew?: boolean}>} Current dropdown items */
    #items = [];

    connect() {
        this.#form = this.element.closest('form');

        // Build the initial exclusion set from pre-rendered chips (AO3 import / edit form).
        if (this.multiValueValue && this.hasChipContainerTarget) {
            this.chipContainerTarget
                .querySelectorAll('[data-chip-name]')
                .forEach((chip) => {
                    const name = chip.dataset.chipName;
                    if (name) this.#selected.add(name.toLowerCase());
                });
        }

        this.inputTarget.addEventListener('input', this.#onInput.bind(this));
        this.inputTarget.addEventListener('keydown', this.#onKeydown.bind(this));
        document.addEventListener('click', this.#onClickOutside.bind(this));

        // Single-select: clear the hidden ID when the user edits the text after a selection.
        if (!this.multiValueValue && this.hasHiddenFieldTarget) {
            this.inputTarget.addEventListener('input', this.#clearStaleId.bind(this));
        }
    }

    disconnect() {
        this.#closeDropdown();
        document.removeEventListener('click', this.#onClickOutside.bind(this));
    }

    // -------------------------------------------------------------------------
    // Input handling
    // -------------------------------------------------------------------------

    #onInput() {
        clearTimeout(this.#debounceTimer);
        const term = this.inputTarget.value.trim();

        if (term.length < this.minCharsValue) {
            this.#closeDropdown();
            return;
        }

        this.#debounceTimer = setTimeout(() => this.#fetch(term), 300);
    }

    #clearStaleId() {
        if (this.hasHiddenFieldTarget && this.hiddenFieldTarget.value !== '') {
            this.hiddenFieldTarget.value = '';
        }
    }

    // -------------------------------------------------------------------------
    // Keyboard navigation
    // -------------------------------------------------------------------------

    #onKeydown(event) {
        if (this.#items.length === 0) return;

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.#setHighlight(Math.min(this.#highlighted + 1, this.#items.length - 1));
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.#setHighlight(Math.max(this.#highlighted - 1, 0));
                break;
            case 'Enter':
                event.preventDefault();
                if (this.#highlighted >= 0) {
                    this.#selectItem(this.#items[this.#highlighted]);
                }
                break;
            case 'Escape':
                this.#closeDropdown();
                break;
        }
    }

    #setHighlight(index) {
        const listItems = this.resultsTarget.querySelectorAll('[data-ac-index]');
        listItems.forEach((el) => el.classList.remove('active'));
        this.#highlighted = index;
        if (index >= 0 && index < listItems.length) {
            listItems[index].classList.add('active');
            listItems[index].scrollIntoView({ block: 'nearest' });
        }
    }

    // -------------------------------------------------------------------------
    // API fetch
    // -------------------------------------------------------------------------

    async #fetch(term) {
        const separator = this.urlValue.includes('?') ? '&' : '?';
        const url = `${this.urlValue}${separator}q=${encodeURIComponent(term)}`;

        let data;
        try {
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resp.ok) return;
            data = await resp.json();
        } catch {
            return;
        }

        this.#renderDropdown(data, term);
    }

    // -------------------------------------------------------------------------
    // Dropdown rendering
    // -------------------------------------------------------------------------

    #renderDropdown(results, term) {
        // Filter out already-selected items in multi-value mode.
        const filtered = this.multiValueValue
            ? results.filter((r) => !this.#selected.has(r.name.toLowerCase()))
            : results;

        const showCreate = this.allowNewValue
            && term.length >= this.minCharsValue
            && !this.#selected.has(term.toLowerCase())
            // Single-select: only show "Create" when there are no suggestions.
            // Multi-value: always show "Create" so the user can add exact text.
            && (this.multiValueValue || filtered.length === 0);

        this.#items = [
            ...filtered,
            ...(showCreate ? [{ name: term, isNew: true }] : []),
        ];

        if (this.#items.length === 0) {
            this.#closeDropdown();
            return;
        }

        this.resultsTarget.innerHTML = '';
        this.#highlighted = -1;

        this.#items.forEach((item, i) => {
            const li = document.createElement('li');
            li.setAttribute('data-ac-index', String(i));
            li.className = 'dropdown-item';
            li.style.cursor = 'pointer';
            li.textContent = item.isNew ? `Create: "${item.name}"` : item.name;

            li.addEventListener('mousedown', (e) => {
                e.preventDefault(); // Prevent input blur before click registers.
                this.#selectItem(item);
            });
            li.addEventListener('mouseover', () => this.#setHighlight(i));

            this.resultsTarget.appendChild(li);
        });

        this.#openDropdown();
    }

    #openDropdown() {
        this.resultsTarget.classList.add('show');
        this.resultsTarget.style.display = 'block';
    }

    #closeDropdown() {
        this.#items = [];
        this.#highlighted = -1;
        this.resultsTarget.classList.remove('show');
        this.resultsTarget.style.display = 'none';
        this.resultsTarget.innerHTML = '';
    }

    #onClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.#closeDropdown();
        }
    }

    // -------------------------------------------------------------------------
    // Selection
    // -------------------------------------------------------------------------

    #selectItem(item) {
        if (this.multiValueValue) {
            this.#addChip(item);
        } else {
            this.#setSingleValue(item);
        }
        this.#closeDropdown();
    }

    // --- Single-select ---

    #setSingleValue(item) {
        this.inputTarget.value = item.name;
        if (this.hasHiddenFieldTarget) {
            this.hiddenFieldTarget.value = item.isNew || item.id === undefined ? '' : String(item.id);
        }
    }

    // --- Multi-value ---

    #addChip(item) {
        if (this.#selected.has(item.name.toLowerCase())) return;

        const index = this.#getNextIndex();
        const prefix = this.fieldPrefixValue;
        const typeId = this.typeIdValue;
        const isMetadata = (typeId > 0);

        const chip = document.createElement('span');
        chip.className = 'badge bg-secondary fs-6 d-inline-flex align-items-center gap-1';
        chip.dataset.chipName = item.name;

        const label = document.createTextNode(item.name + ' ');
        chip.appendChild(label);

        // Remove button
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn-close btn-close-white';
        removeBtn.setAttribute('aria-label', 'Remove');
        removeBtn.addEventListener('click', () => this.#removeChip(chip, item.name));
        chip.appendChild(removeBtn);

        // Hidden form fields embedded in the chip so removing the chip removes the fields.
        chip.appendChild(this.#hidden(`${prefix}[${index}][name]`, item.name));
        if (isMetadata) {
            chip.appendChild(this.#hidden(`${prefix}[${index}][metadataType]`, String(typeId)));
        }
        chip.appendChild(this.#hidden(`${prefix}[${index}][link]`, ''));

        this.chipContainerTarget.appendChild(chip);
        this.#selected.add(item.name.toLowerCase());
        this.inputTarget.value = '';
    }

    #removeChip(chip, name) {
        chip.remove();
        this.#selected.delete(name.toLowerCase());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    #getNextIndex() {
        const key = this.indexAttributeValue; // e.g. 'metadataIndex' or 'authorIndex'
        if (!this.#form || !key) return 0;
        const current = parseInt(this.#form.dataset[key] || '0', 10);
        this.#form.dataset[key] = String(current + 1);
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
