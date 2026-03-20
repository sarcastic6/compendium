/**
 * Dynamic per-type metadata sections on the work form.
 *
 * Each section corresponds to one MetadataType. When the user clicks "Add"
 * in a section, a new entry is cloned from the shared prototype, the hidden
 * type select is set to that section's MetadataType ID, and the entry is
 * appended to the section.
 *
 * Pre-populated entries (e.g. from the AO3 scraper) are rendered server-side
 * inside their matching section and get remove buttons wired up on load.
 */

document.addEventListener('DOMContentLoaded', () => {
    const prototypeSource = document.getElementById('metadata-prototype-source');
    if (!prototypeSource) {
        return;
    }

    const prototype = prototypeSource.dataset.prototype;
    let index = parseInt(prototypeSource.dataset.index || '0', 10);

    document.querySelectorAll('[data-metadata-section]').forEach((section) => {
        const typeId = section.dataset.metadataTypeId;
        const addBtn = section.querySelector('[data-metadata-section-add]');
        const entriesContainer = section.querySelector('[data-metadata-section-entries]');

        if (!addBtn || !entriesContainer) {
            return;
        }

        addBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const newHtml = prototype.replace(/__name__/g, String(index));
            const wrapper = document.createElement('div');
            wrapper.className = 'collection-item d-flex gap-2 align-items-start mb-2';
            wrapper.innerHTML = newHtml;

            const typeSelect = wrapper.querySelector('[data-metadata-type-select]');
            if (typeSelect) {
                typeSelect.value = typeId;
            }

            addRemoveButton(wrapper);
            entriesContainer.appendChild(wrapper);
            index++;
        });

        // Wire up remove buttons on entries pre-rendered by the server
        section.querySelectorAll('.collection-item').forEach((item) => {
            addRemoveButton(item);
        });
    });
});

function addRemoveButton(item) {
    if (item.querySelector('[data-collection-remove]')) {
        return;
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-danger btn-sm';
    btn.dataset.collectionRemove = '';
    btn.setAttribute('aria-label', 'Remove');
    btn.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        item.remove();
    });

    item.appendChild(btn);
}
