@php
    // Sortable table header (server-side, single `sort` query param).
    // Params: $label, $asc, $desc, $current, $style (optional), $center (optional bool), $column (optional data-column key for Manage Columns)
    $current = $current ?? request('sort', 'client_asc');
    $isAsc = $current === $asc;
    $isDesc = $current === $desc;
    $next = $isAsc ? $desc : $asc;
    $url = request()->fullUrlWithQuery(['sort' => $next, 'page' => null]);
    $icon = $isAsc ? 'ri-arrow-up-line' : ($isDesc ? 'ri-arrow-down-line' : 'ri-arrow-up-down-line');
@endphp
<th @if(!empty($column)) data-column="{{ $column }}" @endif @if(!empty($style)) style="{{ $style }}" @endif @if(!empty($center)) class="text-center" @endif>
    <a href="{{ $url }}" class="text-reset text-decoration-none" title="Sort {{ strtolower($label) }} {{ $isAsc ? 'descending' : 'ascending' }}">
        {{ $label }} <i class="{{ $icon }} ms-1"></i>
    </a>
</th>
