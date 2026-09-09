<form id="clientDistributionFilters" action="{{ route('dashboard') }}#clientDistributionFilters" method="GET"
    class="d-flex flex-wrap align-items-end gap-2 mt-3">
    @foreach ([
        ['key' => 'distribution_category', 'label' => 'Transaction categories', 'all' => 'All categories', 'options' => $txCategoryOptions, 'selected' => $distributionCategories],
        ['key' => 'distribution_type', 'label' => 'Transaction types', 'all' => 'All types', 'options' => $txTypeOptions, 'selected' => $distributionTypes],
    ] as $filter)
        <div>
            <label for="{{ $filter['key'] }}Button" class="form-label small mb-1">{{ $filter['label'] }}</label>
            <div class="dropdown" data-distribution-filter data-all-label="{{ $filter['all'] }}">
                <button id="{{ $filter['key'] }}Button" class="btn btn-light border dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span data-selection-label>{{ count($filter['selected']) ? count($filter['selected']).' selected' : $filter['all'] }}</span>
                </button>
                <div class="dropdown-menu p-2" aria-labelledby="{{ $filter['key'] }}Button"
                    style="min-width: 260px; max-height: 300px; overflow-y: auto;">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="{{ $filter['key'] }}All" data-all>
                        <label class="form-check-label fw-semibold" for="{{ $filter['key'] }}All">{{ $filter['all'] }}</label>
                    </div>
                    <hr class="my-2">
                    @forelse ($filter['options'] as $option)
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="{{ $filter['key'] }}_{{ $loop->index }}"
                                name="{{ $filter['key'] }}[]" value="{{ $option }}" data-option
                                @checked(in_array($option, $filter['selected'], true))>
                            <label class="form-check-label" for="{{ $filter['key'] }}_{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @empty
                        <span class="text-muted small">No options available.</span>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach
    <button class="btn btn-primary" type="submit">Apply Filters</button>
</form>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('clientDistributionFilters');
    form.querySelectorAll('[data-distribution-filter]').forEach(dropdown => {
        const options = Array.from(dropdown.querySelectorAll('[data-option]'));
        const all = dropdown.querySelector('[data-all]');
        const label = dropdown.querySelector('[data-selection-label]');
        function update() {
            const selected = options.filter(option => option.checked);
            label.textContent = selected.length === 0 ? dropdown.dataset.allLabel :
                selected.length === 1 ? selected[0].value : `${selected.length} selected`;
            all.checked = selected.length === 0 || selected.length === options.length;
            all.indeterminate = selected.length > 0 && selected.length < options.length;
        }
        all.addEventListener('change', () => {
            options.forEach(option => option.checked = false);
            update();
        });
        options.forEach(option => option.addEventListener('change', update));
        update();
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        const url = new URL(window.location.href);
        for (const key of Array.from(url.searchParams.keys())) {
            if (/^distribution_(category|type)(\[.*\])?$/.test(key)) url.searchParams.delete(key);
        }
        new FormData(form).forEach((value, key) => url.searchParams.append(key, value));
        url.hash = 'clientDistributionFilters';
        window.location.assign(url.toString());
    });
});
</script>
@endpush
