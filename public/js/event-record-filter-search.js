document.addEventListener('DOMContentLoaded', function () {
    const filters = [
        ['recordClientCategoryBtn', '.record-client-category-checkbox', 'client categories'],
        ['recordAddressFilterBtn', 'input[name="address[]"]', 'addresses'],
        ['recordCategoryFilter', '[data-category-option]', 'transaction categories'],
        ['recordTypeFilterBtn', '.record-type-checkbox', 'transaction types'],
        ['eventClientCategoryBtn', '.event-client-category-checkbox', 'client categories'],
        ['eventAddressFilterBtn', 'input[name="address[]"]', 'addresses'],
        ['eventTransactionCategory', '[data-category-option]', 'transaction categories'],
        ['eventTypeFilterBtn', '.event-type-checkbox', 'transaction types'],
    ];

    filters.forEach(([buttonId, optionSelector, name]) => {
        const button = document.getElementById(buttonId);
        if (!button) return;
        const dropdown = button.closest('.dropdown');
        const menu = dropdown.querySelector('.dropdown-menu');
        const options = Array.from(menu.querySelectorAll(optionSelector));
        menu.style.maxHeight = '300px';
        menu.style.overflowY = 'auto';

        let search = menu.querySelector('input[type="search"]');
        if (!search) {
            const searchContainer = document.createElement('div');
            searchContainer.className = 'p-2 bg-body sticky-top';
            search = document.createElement('input');
            search.type = 'search';
            search.className = 'form-control form-control-sm';
            search.placeholder = `Search ${name}...`;
            search.autocomplete = 'off';
            searchContainer.appendChild(search);
            menu.prepend(searchContainer);
        }
        search.setAttribute('aria-label', `Search ${name}`);

        let empty = menu.querySelector('[data-dropdown-empty]');
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'small text-muted p-2 d-none';
            empty.textContent = 'No matching options';
            empty.setAttribute('role', 'status');
            menu.appendChild(empty);
        }

        function filterOptions() {
            const term = search.value.trim().toLocaleLowerCase();
            let matches = 0;
            options.forEach(option => {
                const row = option.closest('.form-check');
                const matchesSearch = option.value.toLocaleLowerCase().includes(term);
                // Search visibility is separate from the transaction-type restrictions.
                row.classList.toggle('d-none', !matchesSearch);
                if (matchesSearch && !row.hidden && row.style.display !== 'none' && !option.disabled) {
                    matches++;
                }
            });
            empty.classList.toggle('d-none', matches > 0);
        }

        search.addEventListener('input', filterOptions);
        search.addEventListener('keydown', event => {
            // Enter while searching must not submit the filters form.
            if (event.key === 'Enter') event.preventDefault();
        });
        dropdown.addEventListener('shown.bs.dropdown', () => {
            filterOptions();
            search.focus();
        });
        dropdown.addEventListener('hidden.bs.dropdown', () => {
            search.value = '';
            filterOptions();
        });
        dropdown.closest('form').addEventListener('change', filterOptions);
        filterOptions();
    });
});
