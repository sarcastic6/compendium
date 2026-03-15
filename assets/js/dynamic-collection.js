/**
 * Dynamic add/remove for Symfony CollectionType fields.
 *
 * Usage: add data-collection-widget to the container element.
 * The container must have data-prototype set by Symfony's form theme.
 *
 * Buttons:
 *   data-collection-add   — triggers adding a new entry
 *   data-collection-remove — removes the closest .collection-item ancestor
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-collection-widget]').forEach((container) => {
        const prototype = container.dataset.prototype;
        if (!prototype) {
            return;
        }

        let index = container.querySelectorAll('.collection-item').length;

        const addButton = container.querySelector('[data-collection-add]');
        if (addButton) {
            addButton.addEventListener('click', (e) => {
                e.preventDefault();
                const newEntry = prototype.replace(/__name__/g, String(index));
                const wrapper = document.createElement('div');
                wrapper.className = 'collection-item d-flex gap-2 align-items-start mb-2';
                wrapper.innerHTML = newEntry;
                addRemoveButton(wrapper);
                container.querySelector('[data-collection-entries]').appendChild(wrapper);
                index++;
            });
        }

        // Wire up remove buttons on pre-existing items
        container.querySelectorAll('.collection-item').forEach((item) => {
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
    btn.innerHTML = '<i class="fa fa-times" aria-hidden="true"></i>';

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        item.remove();
    });

    item.appendChild(btn);
}
