<div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
    {{ $paginator->links('pagination::bootstrap-5') }}

    @if ($paginator->lastPage() > 1)
        <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2 mb-3"
            data-page-jump-form>
            @foreach (request()->except([$pageName, 'page', 'duplicate_tab']) as $key => $value)
                @foreach ((array) $value as $item)
                    <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}"
                        value="{{ $item }}">
                @endforeach
            @endforeach
            @isset($tab)
                <input type="hidden" name="duplicate_tab" value="{{ $tab }}">
            @endisset

            <label for="{{ $pageName }}Jump" class="small text-muted text-nowrap mb-0">Page</label>
            <input type="number" class="form-control form-control-sm" id="{{ $pageName }}Jump"
                name="{{ $pageName }}" value="{{ $paginator->currentPage() }}" min="1"
                max="{{ $paginator->lastPage() }}" inputmode="numeric" required
                aria-label="Page number, from 1 to {{ $paginator->lastPage() }}" style="width: 2rem;">
            <span class="small text-muted text-nowrap">of {{ $paginator->lastPage() }}</span>
            <button type="submit" class="btn btn-sm btn-outline-primary">Go</button>
        </form>
    @endif
</div>
