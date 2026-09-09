<div class="d-flex flex-wrap gap-2">
    <div class="dropdown" id="distCategoryDropdown">
        <button
            class="btn btn-light border form-select form-select-sm text-start d-flex align-items-center justify-content-between"
            style="width: 230px; min-width: 200px;" type="button" id="distCategoryBtn" data-bs-toggle="dropdown"
            data-bs-auto-close="outside" aria-expanded="false"
            aria-label="Filter client category chart by transaction category"
            title="Filter by transaction category">
            <span
                id="distCategoryLabel">{{ count($distributionCategories ?? []) === 0 || count($distributionCategories ?? []) === count($txCategoryOptions ?? []) ? 'All categories' : (count($distributionCategories) === 1 ? $distributionCategories[0] : count($distributionCategories) . ' selected') }}</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-2"
            style="min-width: 230px; max-height: 260px; overflow-y: auto;">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="distCategoryAll"
                    value="">
                <label class="form-check-label fw-semibold" for="distCategoryAll">
                    All categories
                </label>
            </div>
            <hr class="my-2">
            @foreach ($txCategoryOptions ?? [] as $option)
                <div class="form-check">
                    <input class="form-check-input dist-category-check" type="checkbox"
                        id="distCategoryCheck_{{ $loop->index }}"
                        name="distribution_category[]" value="{{ $option }}"
                        {{ in_array($option, $distributionCategories ?? []) ? 'checked' : '' }}>
                    <label class="form-check-label"
                        for="distCategoryCheck_{{ $loop->index }}">
                        {{ $option }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
    <div class="dropdown" id="distTypeDropdown">
        <button
            class="btn btn-light border form-select form-select-sm text-start d-flex align-items-center justify-content-between"
            style="width: 230px" type="button" id="distTypeBtn" data-bs-toggle="dropdown"
            data-bs-auto-close="outside" aria-expanded="false"
            aria-label="Filter client category chart by transaction type"
            title="Filter by transaction type">
            <span
                id="distTypeLabel">{{ count($distributionTypes ?? []) === 0 || count($distributionTypes ?? []) === count($txTypeOptions ?? []) ? 'All types' : (count($distributionTypes) === 1 ? $distributionTypes[0] : count($distributionTypes) . ' selected') }}</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-2"
            style="min-width: 230px; max-height: 260px; overflow-y: auto;">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="distTypeAll"
                    value="">
                <label class="form-check-label fw-semibold" for="distTypeAll">
                    All types
                </label>
            </div>
            <hr class="my-2">
            @foreach ($txTypeOptions ?? [] as $option)
                <div class="form-check"
                    @if (!in_array($option, $distVisibleTypes ?? ($txTypeOptions ?? []))) style="display: none;" @endif>
                    <input class="form-check-input dist-type-check" type="checkbox"
                        id="distTypeCheck_{{ $loop->index }}"
                        name="distribution_type[]" value="{{ $option }}"
                        {{ in_array($option, $distributionTypes ?? []) ? 'checked' : '' }}>
                    <label class="form-check-label"
                        for="distTypeCheck_{{ $loop->index }}">
                        {{ $option }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
</div>
