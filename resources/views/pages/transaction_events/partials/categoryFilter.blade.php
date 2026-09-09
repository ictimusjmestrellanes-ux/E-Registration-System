@php
    $selectedCategories = collect((array) request('transaction_category', []))
        ->flatMap(fn ($value) => explode(',', $value))->map(fn ($value) => trim($value))->filter();
@endphp
<div class="dropdown w-100" id="{{ $filterId }}Dropdown">
    <button class="btn btn-light border form-select text-start" type="button" id="{{ $filterId }}"
        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <span data-category-label>All categories</span>
    </button>
    <div class="dropdown-menu w-100 p-2" aria-labelledby="{{ $filterId }}" style="max-height: 280px; overflow-y: auto;">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="{{ $filterId }}All" data-category-all>
            <label class="form-check-label fw-semibold" for="{{ $filterId }}All">All categories</label>
        </div>
        <hr class="my-2">
        @foreach ($filterCategories as $category)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="transaction_category[]"
                    id="{{ $filterId }}Option{{ $loop->index }}" value="{{ $category }}"
                    @checked($selectedCategories->contains($category)) data-category-option>
                <label class="form-check-label" for="{{ $filterId }}Option{{ $loop->index }}">{{ $category }}</label>
            </div>
        @endforeach
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dropdown = document.getElementById(@json($filterId . 'Dropdown'));
    const options = Array.from(dropdown.querySelectorAll('[data-category-option]'));
    const all = dropdown.querySelector('[data-category-all]');
    const label = dropdown.querySelector('[data-category-label]');
    function update() {
        const selected = options.filter(option => option.checked);
        label.textContent = selected.length === 0 ? 'All categories' :
            selected.length === 1 ? selected[0].value : `${selected.length} selected`;
        all.checked = selected.length === 0 || selected.length === options.length;
        all.indeterminate = selected.length > 0 && selected.length < options.length;
    }
    all.addEventListener('change', function () {
        options.forEach(option => option.checked = false);
        update();
    });
    options.forEach(option => option.addEventListener('change', update));
    update();
});
</script>
@endpush
