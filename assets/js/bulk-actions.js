/**
 * Bulk actions for the reading entry list.
 *
 * Progressive enhancement: the bulk UI is hidden by default and only shown when
 * this script runs. If JS is disabled, individual edit/delete actions still work.
 */

const bulkForm = document.getElementById('bulk-form');
if (bulkForm) {
    const selectAll = document.getElementById('select-all');
    const actionBar = document.getElementById('bulk-action-bar');
    const bulkCount = document.getElementById('bulk-count');
    const bulkStatusBtn = document.getElementById('bulk-status-btn');
    const bulkStatusSelect = document.getElementById('bulk-status-select');
    const bulkDeleteConfirmBtn = document.getElementById('bulk-delete-confirm-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    /** @returns {HTMLInputElement[]} */
    function getCheckboxes() {
        return Array.from(bulkForm.querySelectorAll('.entry-checkbox'));
    }

    /** @returns {HTMLInputElement[]} */
    function getChecked() {
        return getCheckboxes().filter(cb => cb.checked);
    }

    function updateActionBar() {
        const checked = getChecked();
        const count = checked.length;

        if (count > 0) {
            actionBar.classList.remove('d-none');
            bulkCount.textContent = count + ' selected';
        } else {
            actionBar.classList.add('d-none');
        }

        const all = getCheckboxes();
        if (selectAll && all.length > 0) {
            selectAll.checked = count === all.length;
            selectAll.indeterminate = count > 0 && count < all.length;
        }
    }

    // Select-all toggle
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            getCheckboxes().forEach(cb => { cb.checked = selectAll.checked; });
            updateActionBar();
        });
    }

    // Individual checkbox changes
    bulkForm.addEventListener('change', function (e) {
        if (e.target.classList.contains('entry-checkbox')) {
            updateActionBar();
        }
    });

    // Bulk status: set form action and submit
    if (bulkStatusBtn) {
        bulkStatusBtn.addEventListener('click', function () {
            const statusId = bulkStatusSelect ? bulkStatusSelect.value : '';
            if (!statusId) { return; }
            const checked = getChecked();
            if (checked.length === 0) { return; }

            bulkForm.action = bulkStatusBtn.getAttribute('data-action-url') || '';
            bulkForm.submit();
        });
    }

    // Bulk delete confirm: set form action, then submit after modal closes
    if (bulkDeleteConfirmBtn) {
        bulkDeleteConfirmBtn.addEventListener('click', function () {
            const url = bulkDeleteBtn ? bulkDeleteBtn.getAttribute('data-action-url') : '';
            bulkForm.action = url || '';
            // Short timeout lets Bootstrap close the modal before navigation
            setTimeout(function () { bulkForm.submit(); }, 50);
        });
    }

    // Initialize state on load
    updateActionBar();
}
