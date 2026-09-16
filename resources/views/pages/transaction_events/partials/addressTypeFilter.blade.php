// Keep address choices in sync with the transaction types on this page.
(() => {
    const addressSelect = document.getElementById(@json($addressId));
    const typeCheckboxes = Array.from(document.querySelectorAll(@json($typeSelector)));
    const addressTypes = @json($addressTypes);
    const originalOptions = Array.from(addressSelect.options, option => ({
        value: option.value,
        text: option.textContent,
    }));

    function syncAddresses() {
        const selectedTypes = typeCheckboxes.filter(checkbox => checkbox.checked).map(checkbox => checkbox.value);
        // The existing type filter treats none or all selected as unrestricted.
        const unrestricted = selectedTypes.length === 0 || selectedTypes.length === typeCheckboxes.length;
        const matchingAddresses = new Set(addressTypes
            .filter(option => selectedTypes.includes(option.type))
            .map(option => option.address));
        const previousAddress = addressSelect.value;
        addressSelect.replaceChildren();
        originalOptions.forEach(option => {
            if (option.value === '' || unrestricted || matchingAddresses.has(option.value)) {
                addressSelect.add(new Option(option.text, option.value));
            }
        });
        addressSelect.value = Array.from(addressSelect.options).some(option => option.value === previousAddress)
            ? previousAddress : '';
    }

    addressSelect.form.addEventListener('change', event => {
        if (typeCheckboxes.includes(event.target) || event.target.id === @json($allTypesId)) {
            syncAddresses();
        }
    });
    syncAddresses();
})();
