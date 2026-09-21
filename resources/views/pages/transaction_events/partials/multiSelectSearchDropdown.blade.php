@php
    // Reusable searchable multi-select dropdown (Bootstrap 5, no extra dependency).
    // Params: $dropdownId (unique), $fieldName, $options, $allLabel, $searchPlaceholder
    $dropdownId = $dropdownId ?? 'multiSelect';
    $fieldName = $fieldName ?? 'filter';
    $options = collect($options ?? [])->filter(fn ($v) => trim((string) $v) !== '')->values();
    $allLabel = $allLabel ?? 'All';
    $searchPlaceholder = $searchPlaceholder ?? 'Search...';
    $selectedValues = collect((array) request($fieldName, []))
        ->flatMap(fn ($v) => explode(',', (string) $v))
        ->map(fn ($v) => trim($v))
        ->filter()
        ->unique()
        ->values();
@endphp
<div class="dropdown w-100" id="{{ $dropdownId }}Dropdown" data-multi-select="{{ $dropdownId }}" data-field="{{ $fieldName }}">
    <button class="btn btn-light border form-select text-start d-flex align-items-center justify-content-between"
        type="button" id="{{ $dropdownId }}Btn" data-bs-toggle="dropdown"
        data-bs-auto-close="outside" aria-expanded="false" style="padding: .5rem 0.75rem;">
        <span id="{{ $dropdownId }}Label" class="text-truncate">{{ $allLabel }}</span>
    </button>
    <div class="dropdown-menu w-100 p-2" aria-labelledby="{{ $dropdownId }}Btn" style="max-height: 300px; overflow-y: auto;">
        <input type="search" class="form-control form-control-sm mb-2" id="{{ $dropdownId }}Search"
            placeholder="{{ $searchPlaceholder }}" autocomplete="off" onkeydown="event.stopPropagation();" onkeyup="event.stopPropagation();">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="{{ $dropdownId }}All" value="">
            <label class="form-check-label fw-semibold" for="{{ $dropdownId }}All">{{ $allLabel }}</label>
        </div>
        <hr class="my-2">
        <div id="{{ $dropdownId }}Options">
            @forelse ($options as $option)
                <div class="form-check" data-option-row data-option-label="{{ strtolower($option) }}">
                    <input class="form-check-input {{ $dropdownId }}-checkbox" type="checkbox"
                        id="{{ $dropdownId }}_{{ $loop->index }}" value="{{ $option }}"
                        {{ $selectedValues->contains($option) ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ $dropdownId }}_{{ $loop->index }}">{{ $option }}</label>
                </div>
            @empty
                <div class="text-muted small px-1">No options available.</div>
            @endforelse
        </div>
        <div class="text-muted small px-1 py-2 d-none" id="{{ $dropdownId }}NoResults">No matches found.</div>
    </div>
</div>
<script>
(function () {
    function initMultiSelectSearchDropdown() {
        var root = document.getElementById(@json($dropdownId . 'Dropdown'));
        if (!root || root.dataset.initialized === '1') return;
        root.dataset.initialized = '1';

        var dropdownId = @json($dropdownId);
        var fieldName = @json($fieldName);
        var allLabel = @json($allLabel);
        var allCheckbox = document.getElementById(dropdownId + 'All');
        var label = document.getElementById(dropdownId + 'Label');
        var searchInput = document.getElementById(dropdownId + 'Search');
        var optionsWrap = document.getElementById(dropdownId + 'Options');
        var noResults = document.getElementById(dropdownId + 'NoResults');
        var boxes = Array.from(root.querySelectorAll('.' + dropdownId + '-checkbox'));

        function syncLabel() {
            var checked = boxes.filter(function (cb) { return cb.checked; });
            if (!label) return;
            if (checked.length === 0 || checked.length === boxes.length) {
                label.textContent = allLabel;
            } else if (checked.length === 1) {
                label.textContent = checked[0].value;
            } else {
                label.textContent = checked.length + ' selected';
            }
            if (allCheckbox) {
                allCheckbox.checked = boxes.length > 0 && checked.length === boxes.length;
                allCheckbox.indeterminate = checked.length > 0 && checked.length < boxes.length;
            }
            root.dispatchEvent(new CustomEvent('multiSelectChange', { bubbles: true }));
        }

        if (allCheckbox) {
            allCheckbox.addEventListener('change', function () {
                var shouldCheck = this.checked;
                // Only toggle visible (search-filtered) rows so search + select works intuitively.
                var visible = boxes.filter(function (cb) {
                    var row = cb.closest('[data-option-row]');
                    return !row || row.style.display !== 'none';
                });
                (visible.length ? visible : boxes).forEach(function (cb) { cb.checked = shouldCheck; });
                syncLabel();
            });
        }
        boxes.forEach(function (cb) { cb.addEventListener('change', syncLabel); });

        if (searchInput) {
            // Keep dropdown open while typing.
            ['click', 'keydown', 'keyup'].forEach(function (evt) {
                searchInput.addEventListener(evt, function (e) { e.stopPropagation(); });
            });
            searchInput.addEventListener('input', function () {
                var q = this.value.trim().toLowerCase();
                var visibleCount = 0;
                boxes.forEach(function (cb) {
                    var row = cb.closest('[data-option-row]');
                    var hay = ((row && row.dataset.optionLabel) || cb.value.toLowerCase());
                    var show = !q || hay.indexOf(q) !== -1;
                    if (row) row.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });
                if (noResults) noResults.classList.toggle('d-none', visibleCount !== 0);
            });
        }

        var form = root.closest('form');
        if (form && !form.dataset['msdBound' + dropdownId]) {
            form.dataset['msdBound' + dropdownId] = '1';
            form.addEventListener('submit', function () {
                var scopeBoxes = Array.from(root.querySelectorAll('.' + dropdownId + '-checkbox'));
                form.querySelectorAll('input[type="hidden"][name="' + fieldName + '[]"]').forEach(function (el) { el.remove(); });
                var checked = scopeBoxes.filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; }).filter(function (v) { return v !== ''; });
                if (checked.length > 0 && checked.length < scopeBoxes.length) {
                    checked.forEach(function (val) {
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = fieldName + '[]';
                        hidden.value = val;
                        form.appendChild(hidden);
                    });
                }
            });
        }

        syncLabel();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMultiSelectSearchDropdown);
    } else {
        initMultiSelectSearchDropdown();
    }
})();
</script>
