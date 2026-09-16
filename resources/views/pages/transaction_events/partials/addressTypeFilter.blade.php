// Keep multi-select addresses in sync with the transaction types on this page.
(() => {
    const dropdown = document.getElementById(@json($addressId));
    const addressCheckboxes = Array.from(dropdown.querySelectorAll('input[name="address[]"]'));
    const allAddresses = dropdown.querySelector('[data-address-all]');
    const label = dropdown.querySelector('[data-address-label]');
    const typeCheckboxes = Array.from(document.querySelectorAll(@json($typeSelector)));
    const addressTypes = @json($addressTypes);

    function updateLabel() {
        const available = addressCheckboxes.filter(checkbox => !checkbox.disabled);
        const selected = available.filter(checkbox => checkbox.checked);
        label.textContent = selected.length === 0 ? 'All addresses'
            : selected.length === 1 ? selected[0].value : `${selected.length} selected`;
        allAddresses.checked = available.length > 0 && selected.length === available.length;
        allAddresses.indeterminate = selected.length > 0 && selected.length < available.length;
        allAddresses.disabled = available.length === 0;
    }

    function syncAddresses() {
        const selectedTypes = typeCheckboxes.filter(checkbox => checkbox.checked).map(checkbox => checkbox.value);
        const unrestricted = selectedTypes.length === 0 || selectedTypes.length === typeCheckboxes.length;
        const matchingAddresses = new Set(addressTypes
            .filter(option => selectedTypes.includes(option.type)).map(option => option.address));
        addressCheckboxes.forEach(checkbox => {
            const visible = unrestricted || matchingAddresses.has(checkbox.value);
            checkbox.closest('[data-address-option]').hidden = !visible;
            checkbox.disabled = !visible;
            if (!visible) checkbox.checked = false;
        });
        updateLabel();
    }

    allAddresses.addEventListener('change', () => {
        addressCheckboxes.forEach(checkbox => {
            checkbox.checked = !checkbox.disabled && allAddresses.checked;
        });
        updateLabel();
    });
    addressCheckboxes.forEach(checkbox => checkbox.addEventListener('change', updateLabel));
    dropdown.closest('form').addEventListener('change', event => {
        if (typeCheckboxes.includes(event.target) || event.target.id === @json($allTypesId)) syncAddresses();
    });
    syncAddresses();
})();
