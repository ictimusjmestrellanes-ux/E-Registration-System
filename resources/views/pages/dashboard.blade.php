@extends('layouts.master')
@section('title', 'ERS | Dashboard')
@section('content')
    @php
        $categoryMeta = [
            'social_services' => ['fa-hand-holding-heart', 'primary'],
            'solicitation' => ['fa-file-invoice', 'success'],
            'youth_sports' => ['fa-futbol', 'info'],
            'appointments' => ['fa-calendar-check', 'warning'],
            'infrastructure' => ['fa-building', 'secondary'],
            'scholarships' => ['fa-graduation-cap', 'danger'],
            'permits' => ['fa-file-contract', 'primary'],
            'events' => ['fa-calendar-days', 'success'],
            'job_application' => ['fa-briefcase', 'info'],
            'hoa' => ['fa-house', 'warning'],
            'others' => ['fa-ellipsis', 'secondary'],
        ];
        $totalCategoryTransactions = $totalTransactions ?? array_sum($categoryCounts);

        $chartLabels = array_values($categories);
        $chartData = [];
        foreach ($categories as $key => $label) {
            $chartData[] = $categoryCounts[$key] ?? 0;
        }
        $chartColors = [
            '#6366F1', // Indigo
            '#22C55E', // Green
            '#06B6D4', // Cyan
            '#F59E0B', // Amber
            '#A855F7', // Purple
            '#EF4444', // Red
            '#EC4899', // Pink
            '#405189', // Teal
            '#3B82F6', // Blue
            '#F97316', // Orange
            '#84CC16', // Lime
        ];
        $timeGreeting =
            now()->hour >= 0 && now()->hour <= 11
                ? 'Good Morning'
                : (now()->hour >= 12 && now()->hour <= 17
                    ? 'Good Afternoon'
                    : 'Good Evening');
    @endphp

    <style>
        .distribution-card {
            min-height: 350px;
            height: 100%;
        }

        .category-card,
        .stat-card {
            transition: transform .15s ease-in-out, box-shadow .15s ease-in-out;
        }

        .category-card:hover,
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .12) !important;
            border-color: var(--vz-primary) !important;
        }
    </style>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Dashboard</h4>
                                <p class="text-muted mb-0">{{ $timeGreeting }}, {{ auth()->user()?->name ?? 'User' }}.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end welcome row-->

        <div class="row g-4">
            <!-- Left: Main Content (col-lg-8) -->
            <div class="col-lg-9">
                <!-- Stat Cards -->
                <div class="row g-3">
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <a href="{{ route('client.list') }}" class="text-decoration-none">
                            <div class="card material-shadow border-primary border-opacity-25 stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <div
                                                class="avatar-sm bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center">
                                                <i class="fa-solid fa-users text-primary fs-4"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-muted mb-1">Total Registered Clients</p>
                                            <h3 class="mb-0">{{ number_format($totalClients) }}</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <a href="{{ route('transactions.index') }}" class="text-decoration-none">
                            <div class="card material-shadow border-info border-opacity-25 stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <div
                                                class="avatar-sm bg-info bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center">
                                                <i class="fa-solid fa-receipt text-info fs-4"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-muted mb-1">Total Transactions</p>
                                            <h3 class="mb-0">{{ number_format($totalCategoryTransactions) }}</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <a href="#service-categories" class="text-decoration-none">
                            <div class="card material-shadow border-success border-opacity-25 stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <div
                                                class="avatar-sm bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center">
                                                <i class="fa-solid fa-layer-group text-success fs-4"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-muted mb-1">Total Categories</p>
                                            <h3 class="mb-0">{{ count($categories) }}</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                </div>
                <!--end stat cards-->

                <!-- Client Trend Chart -->
                <div class="card material-shadow mt-4">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0">Total Registered Clients</h5>
                            <p class="text-muted mb-0">Monthly client registrations through {{ now()->format('F Y') }}</p>
                        </div>
                        <div class="text-end">
                            <span class="text-muted">Total clients</span>
                            <h3 class="mb-0 text-primary">{{ number_format($totalClients) }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="w-100" style="height: 230px; position: relative;">
                            <canvas id="clientTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Transaction Trend Chart -->
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <div class="card material-shadow h-100">
                            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                <div>
                                    <h5 class="mb-0" id="txTrendTitle">Total
                                        Transactions{{ ($txTrendSuffix ?? '') !== '' ? ' — ' . $txTrendSuffix : '' }}</h5>
                                    <p class="text-muted mb-0">Monthly totals through {{ now()->format('F Y') }}. Hover or
                                        tap a bar for the breakdown.</p>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="dropdown" id="txCategoryDropdown">
                                        <button
                                            class="btn btn-light border form-select form-select-sm text-start d-flex align-items-center justify-content-between"
                                            style="width: 230px" type="button" id="txCategoryBtn" data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside" aria-expanded="false"
                                            aria-label="Filter transactions graph by category"
                                            title="Filter by transaction category" style="min-width: 200px;">
                                            <span
                                                id="txCategoryLabel">{{ count($txCategories ?? []) === 0 || count($txCategories ?? []) === count($txCategoryOptions ?? []) ? 'All categories' : (count($txCategories) === 1 ? $txCategories[0] : count($txCategories) . ' selected') }}</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end p-2"
                                            style="min-width: 230px; max-height: 260px; overflow-y: auto;">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="txCategoryAll"
                                                    value="">
                                                <label class="form-check-label fw-semibold" for="txCategoryAll">
                                                    All categories
                                                </label>
                                            </div>
                                            <hr class="my-2">
                                            @foreach ($txCategoryOptions ?? [] as $option)
                                                <div class="form-check">
                                                    <input class="form-check-input tx-category-check" type="checkbox"
                                                        id="txCategoryCheck_{{ $loop->index }}"
                                                        value="{{ $option }}"
                                                        {{ in_array($option, $txCategories ?? []) ? 'checked' : '' }}>
                                                    <label class="form-check-label"
                                                        for="txCategoryCheck_{{ $loop->index }}">
                                                        {{ $option }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="dropdown" id="txTypeDropdown">
                                        <button
                                            class="btn btn-light border form-select form-select-sm text-start d-flex align-items-center justify-content-between"
                                            style="width: 230px" type="button" id="txTypeBtn" data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside" aria-expanded="false"
                                            aria-label="Filter transactions graph by transaction type"
                                            title="Filter by transaction type">
                                            <span
                                                id="txTypeLabel">{{ count($txTypes ?? []) === 0 || count($txTypes ?? []) === count($txTypeOptions ?? []) ? 'All types' : (count($txTypes) === 1 ? $txTypes[0] : count($txTypes) . ' selected') }}</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end p-2"
                                            style="min-width: 230px; max-height: 260px; overflow-y: auto;">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="txTypeAll"
                                                    value="">
                                                <label class="form-check-label fw-semibold" for="txTypeAll">
                                                    All types
                                                </label>
                                            </div>
                                            <hr class="my-2">
                                            @foreach ($txTypeOptions ?? [] as $option)
                                                <div class="form-check"
                                                    @if (!in_array($option, $txVisibleTypes ?? ($txTypeOptions ?? []))) style="display: none;" @endif>
                                                    <input class="form-check-input tx-type-check" type="checkbox"
                                                        id="txTypeCheck_{{ $loop->index }}" value="{{ $option }}"
                                                        {{ in_array($option, $txTypes ?? []) ? 'checked' : '' }}>
                                                    <label class="form-check-label"
                                                        for="txTypeCheck_{{ $loop->index }}">
                                                        {{ $option }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="w-100" style="height: 230px; position: relative;">
                                    <canvas id="transactionTrendChart" role="img"
                                        aria-label="Monthly transaction totals for the selected categories and types"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly totals by transaction date -->
                <div class="card material-shadow mt-4" id="transaction-date-chart">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0">Transactions by Transaction Date</h5>
                            <p class="text-muted mb-0" id="transactionDateDescription">
                                @if ($transactionDates !== [])
                                    Monthly totals for {{ count($transactionDates) }} selected
                                    {{ count($transactionDates) === 1 ? 'date' : 'dates' }}
                                @elseif ($transactionDateFrom || $transactionDateTo)
                                    {{ $transactionDateFrom ? \Carbon\Carbon::parse($transactionDateFrom)->format('M d, Y') : 'All earlier dates' }}
                                    –
                                    {{ $transactionDateTo ? \Carbon\Carbon::parse($transactionDateTo)->format('M d, Y') : now()->format('M d, Y') }}
                                @else
                                    Monthly totals from Transaction History through {{ now()->format('F Y') }}
                                @endif
                            </p>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">

                            <div class="dropdown" id="transactionDateCategoryDropdown">
                                <button
                                    class="btn btn-light border form-select form-select-sm text-start d-flex align-items-center justify-content-between"
                                    style="width: 220px;" type="button" id="transactionDateCategoryBtn"
                                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                                    aria-controls="transactionDateCategoryMenu"
                                    aria-label="Filter monthly transactions by transaction category">
                                    <span id="transactionDateCategoryLabel"
                                        class="text-truncate pe-3">{{ count($transactionDateCategories) === 1 ? $transactionDateCategories[0] : (count($transactionDateCategories) > 1 ? count($transactionDateCategories) . ' categories selected' : 'All categories') }}</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-3" id="transactionDateCategoryMenu"
                                    aria-labelledby="transactionDateCategoryBtn"
                                    style="width: 220px; max-width: calc(100vw - 40px);">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="transactionDateCategoryAll"
                                            {{ $transactionDateCategories === [] ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="transactionDateCategoryAll">All
                                            categories</label>
                                    </div>
                                    <hr class="my-2">
                                    <div style="max-height: 240px; overflow-y: auto;">
                                        @forelse ($txCategoryOptions as $category)
                                            <div class="form-check transaction-date-category-option">
                                                <input class="form-check-input transaction-date-category-check"
                                                    type="checkbox" name="transaction_date_categories[]"
                                                    id="transactionDateCategoryOption_{{ $loop->index }}"
                                                    value="{{ $category }}"
                                                    {{ in_array($category, $transactionDateCategories, true) ? 'checked' : '' }}>
                                                <label class="form-check-label"
                                                    for="transactionDateCategoryOption_{{ $loop->index }}">{{ $category }}</label>
                                            </div>
                                        @empty
                                            <p class="text-muted small mb-0">No transaction categories available.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown" id="transactionDateTypeDropdown">
                                <button
                                    class="btn btn-light border form-select form-select-sm text-start d-flex align-items-center justify-content-between"
                                    style="width: 220px;" type="button" id="transactionDateTypeBtn"
                                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                                    aria-controls="transactionDateTypeMenu"
                                    aria-label="Filter monthly transactions by transaction type">
                                    <span id="transactionDateTypeLabel"
                                        class="text-truncate pe-3">{{ count($transactionDateTypes) === 1 ? $transactionDateTypes[0] : (count($transactionDateTypes) > 1 ? count($transactionDateTypes) . ' types selected' : 'All types') }}</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-3" id="transactionDateTypeMenu"
                                    aria-labelledby="transactionDateTypeBtn"
                                    style="width: 220px; max-width: calc(100vw - 40px);">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="transactionDateTypeAll"
                                            {{ $transactionDateTypes === [] ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="transactionDateTypeAll">All
                                            types</label>
                                    </div>
                                    <hr class="my-2">
                                    <div style="max-height: 240px; overflow-y: auto;">
                                        @forelse ($txTypeOptions as $type)
                                            <div
                                                class="form-check transaction-date-type-option {{ in_array($type, $transactionDateTypeOptions, true) ? '' : 'd-none' }}">
                                                <input class="form-check-input transaction-date-type-check"
                                                    type="checkbox" name="transaction_date_types[]"
                                                    id="transactionDateTypeOption_{{ $loop->index }}"
                                                    value="{{ $type }}"
                                                    {{ in_array($type, $transactionDateTypeOptions, true) ? '' : 'disabled' }}
                                                    {{ in_array($type, $transactionDateTypes, true) ? 'checked' : '' }}>
                                                <label class="form-check-label"
                                                    for="transactionDateTypeOption_{{ $loop->index }}">{{ $type }}</label>
                                            </div>
                                        @empty
                                            <p class="text-muted small mb-0">No transaction types available.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown" id="transactionDateDropdown">
                                <button
                                    class="btn btn-light border form-select form-select-sm text-start d-flex align-items-center justify-content-between"
                                    style="width: 180px;" type="button" id="transactionDateBtn"
                                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                                    aria-controls="transactionDateMenu"
                                    aria-label="Filter transactions by transaction date">
                                    <span
                                        id="transactionDateLabel">{{ count($transactionDates) === 1 ? \Carbon\Carbon::parse($transactionDates[0])->format('M d, Y') : (count($transactionDates) > 1 ? count($transactionDates) . ' dates selected' : 'All dates') }}</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-3" id="transactionDateMenu"
                                    aria-labelledby="transactionDateBtn"
                                    style="width: 180px; max-width: calc(100vw - 40px);">
                                    <div class="row g-2">
                                        @foreach ($txCategories as $category)
                                            <input type="hidden" name="tx_category[]" value="{{ $category }}">
                                        @endforeach
                                        @foreach ($txTypes as $type)
                                            <input type="hidden" name="tx_type[]" value="{{ $type }}">
                                        @endforeach
                                        <div class="col-12">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="transactionDateAll"
                                                    {{ $transactionDates === [] ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="transactionDateAll">All
                                                    dates</label>
                                            </div>
                                            <hr class="my-2">
                                            <div style="max-height: 240px; overflow-y: auto;">
                                                @forelse ($transactionDateOptions as $date)
                                                    <div
                                                        class="form-check transaction-date-option {{ in_array($date, $transactionDateVisibleDates, true) ? '' : 'd-none' }}">
                                                        <input class="form-check-input transaction-date-check"
                                                            type="checkbox" name="transaction_dates[]"
                                                            id="transactionDateOption_{{ $loop->index }}"
                                                            value="{{ $date }}"
                                                            {{ in_array($date, $transactionDateVisibleDates, true) ? '' : 'disabled' }}
                                                            {{ in_array($date, $transactionDates, true) ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="transactionDateOption_{{ $loop->index }}">
                                                            {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                                                        </label>
                                                    </div>
                                                @empty
                                                    <p class="text-muted small mb-0">No transaction dates available.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                        @if ($errors->has('transaction_dates') || $errors->has('transaction_dates.*'))
                                            <div class="col-12 text-danger small" role="alert">Select valid transaction
                                                dates from the list.</div>
                                        @endif
                                        {{-- <div class="col-12 text-muted small">Select dates to update the chart automatically.</div> --}}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="card-body">
                        <div id="transactionDateFeedback" class="small d-none mb-2" role="status" aria-live="polite">
                        </div>
                        <div class="overflow-auto" tabindex="0" role="region"
                            aria-label="Monthly transaction chart; scroll horizontally for more months">
                            <div id="transactionDatePlot"
                                style="height: 280px; position: relative; min-width: {{ max(320, count($transactionDateTrend['labels']) * 80) }}px;">
                                <canvas id="transactionDateChart" role="img"
                                    aria-label="Transaction counts by transaction date for each month. Exact values follow in a table."></canvas>
                            </div>
                        </div>
                        <p id="transactionDateCurrentMonth"
                            class="text-muted small mt-2 mb-0 {{ in_array(now()->format('M Y'), $transactionDateTrend['labels']) ? '' : 'd-none' }}">
                            {{ now()->format('F Y') }} is the current month and is still in progress.</p>
                        <p id="transactionDateEmpty"
                            class="text-muted mt-2 mb-0 {{ array_sum($transactionDateTrend['data']) === 0 ? '' : 'd-none' }}">
                            No transactions found for this period.</p>
                        <table class="visually-hidden">
                            <caption>Monthly transaction counts by transaction date</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Month</th>
                                    <th scope="col">Transactions</th>
                                </tr>
                            </thead>
                            <tbody id="transactionDateTableBody">
                                @foreach ($transactionDateTrend['labels'] as $index => $month)
                                    <tr>
                                        <th scope="row">{{ $month }}</th>
                                        <td>{{ $transactionDateTrend['data'][$index] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CARAVAN Trend Chart -->
                {{-- <div class="card material-shadow mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">CARAVAN Transactions</h5>
                        <p class="text-muted mb-0">Caravan registrations per month (January 2026 - present)</p>
                    </div>
                    <div class="card-body">
                        <div class="w-100" style="height: 280px; position: relative;">
                            <canvas id="caravanTrendChart"></canvas>
                        </div>
                    </div>
                </div> --}}

                <div class="col-12 mt-4">
                    <div class="row g-3">
                        <!-- Client Category Chart -->
                        <div class="col-12">
                            <div class="card material-shadow distribution-card">
                                <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                    <div>
                                        <h5 class="mb-0">Client Category Distribution</h5>
                                        <p class="text-muted mb-0">Share of transactions per client category</p>
                                    </div>
                                    @include('pages.partials.clientDistributionFilters')
                                </div>
                                <div class="card-body">
                                    <div class="overflow-auto">
                                        <div id="clientCategoryPlot"
                                            style="position: relative; min-width: 420px; height: {{ max(200, count($clientCategoryDistribution['data']) * 25 + 40) }}px;">
                                            <canvas id="clientCategoryChart" role="img"
                                                data-export-url="{{ route('dashboard.client-distribution') }}"
                                                aria-label="Transactions by client category, sorted from highest to lowest"
                                                class="{{ array_sum($clientCategoryDistribution['data']) > 0 ? '' : 'd-none' }}"></canvas>
                                        </div>
                                        <div id="clientCategoryEmpty"
                                            class="{{ array_sum($clientCategoryDistribution['data']) > 0 ? 'd-none' : '' }} d-flex align-items-center justify-content-center text-center text-muted"
                                            role="status" style="min-height: 200px;">
                                            No transactions with a client category are available.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Service Category Chart -->
                        <div class="col-12">
                            <div class="card material-shadow distribution-card" id="serviceCategoryCard">
                                <div class="card-header">
                                    <h5 class="mb-0">Service Categories Distribution</h5>
                                    <p class="text-muted mb-0">Share of transactions per service category</p>
                                </div>
                                <div class="card-body">
                                    <div class="overflow-auto">
                                        <div
                                            style="position: relative; min-width: 420px; height: {{ max(200, count($chartData) * 25 + 40) }}px;">
                                            @if (array_sum($chartData) > 0)
                                                <canvas id="serviceCategoryChart" role="img"
                                                    aria-label="Transactions by service category, sorted from highest to lowest"></canvas>
                                            @else
                                                <div class="d-flex align-items-center justify-content-center h-100 text-center text-muted"
                                                    role="status">
                                                    No transactions with a service category are available.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1" id="service-categories">
                    <div class="col-12">
                        <div class="card mb-0">
                            <div class="card-body">
                                <h5 class="mb-0">Service Categories</h5>
                                <p class="text-muted mb-0">Overview of client service requests</p>
                            </div>
                        </div>
                    </div>

                    @foreach ($categories as $key => $label)
                        @php
                            [$icon] = $categoryMeta[$key] ?? ['fa-circle'];
                            // Same palette position as the Service Categories bar
                            // chart so each card matches its bar color.
                            $hex = $chartColors[$loop->index % count($chartColors)];
                            $count = $categoryCounts[$key] ?? 0;
                        @endphp
                        <div class="col-lg-6 col-md-2 col-sm-6" style="width: 173.5px">
                            <a href="{{ route('transactions.category', $key) }}" class="text-decoration-none">
                                <div class="card material-shadow category-card" style="height: 170px">
                                    <div class="card-body text-center">
                                        <div class="avatar-md rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                                            style="background: {{ $hex }}1a;">
                                            <i class="fa-solid {{ $icon }} fs-3"
                                                style="color: {{ $hex }};"></i>
                                        </div>
                                        <h4 class="mb-1">{{ number_format($count) }}</h4>
                                        <p class="text-muted mb-0">{{ $label }}</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
            <!--end col-lg-8 main content-->

            <!-- Right Sidebar: Clock & Calendar (col-lg-4) -->
            <div class="col-lg-3">
                <!-- Analog Clock Card -->
                <div class="card material-shadow">
                    <div class="card-body p-4">
                        <h6 class="text-muted text-uppercase fw-semibold mb-3 text-center">
                            <i class="ri-time-line me-1"></i> Clock
                        </h6>
                        <div class="d-flex justify-content-center">
                            <canvas id="dashAnalogClock" width="300" height="300"
                                style="max-width: 100%;"></canvas>
                        </div>
                        <div class="text-center mt-3">
                            <span id="dashDigitalClock" class="fs-5 fw-semibold text-primary"></span>
                            <br>
                            <span id="dashDigitalDate" class="text-muted small"></span>
                        </div>
                    </div>
                </div>
                <!--end clock card-->

                <!-- Calendar Card -->
                <div class="card material-shadow mt-4">
                    <div class="card-body p-3">
                        <h6 class="text-muted text-uppercase fw-semibold mb-3 text-center">
                            <i class="ri-calendar-line me-1"></i> Calendar
                        </h6>
                        <div id="dashCalendar">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <button type="button" class="btn btn-sm btn-soft-primary" id="dashCalPrev">
                                    <i class="ri-arrow-left-s-line"></i>
                                </button>
                                <span id="dashCalMonthYear" class="fw-semibold"></span>
                                <button type="button" class="btn btn-sm btn-soft-primary" id="dashCalNext">
                                    <i class="ri-arrow-right-s-line"></i>
                                </button>
                            </div>
                            <table class="table table-sm table-borderless text-center mb-0">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>Su</th>
                                        <th>Mo</th>
                                        <th>Tu</th>
                                        <th>We</th>
                                        <th>Th</th>
                                        <th>Fr</th>
                                        <th>Sa</th>
                                    </tr>
                                </thead>
                                <tbody id="dashCalBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!--end calendar card-->

                @if (feature_allowed('Activity Logs'))
                    <!-- Recent Activity Card -->
                    <div class="card material-shadow mt-4">
                        <div class="card-body p-3">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3 text-center">
                                <i class="ri-history-line me-1"></i> Recent Activity
                            </h6>
                            @php
                                $dashActionMeta = function ($action) {
                                    $action = strtolower((string) $action);

                                    return match (true) {
                                        str_contains($action, 'create') => [
                                            'Create',
                                            'bg-primary-subtle text-primary',
                                            'ri-add-line',
                                        ],
                                        str_contains($action, 'update') => [
                                            'Update',
                                            'bg-info-subtle text-info',
                                            'ri-pencil-line',
                                        ],
                                        str_contains($action, 'delete') => [
                                            'Delete',
                                            'bg-danger-subtle text-danger',
                                            'ri-delete-bin-line',
                                        ],
                                        str_contains($action, 'archive') => [
                                            'Archive',
                                            'bg-warning-subtle text-warning',
                                            'ri-archive-line',
                                        ],
                                        str_contains($action, 'restore') => [
                                            'Restore',
                                            'bg-success-subtle text-success',
                                            'ri-restart-line',
                                        ],
                                        str_contains($action, 'login') => [
                                            'Login',
                                            'bg-success-subtle text-success',
                                            'ri-login-box-line',
                                        ],
                                        str_contains($action, 'logout') => [
                                            'Logout',
                                            'bg-secondary-subtle text-secondary',
                                            'ri-logout-box-r-line',
                                        ],
                                        str_contains($action, 'fingerprint') => [
                                            'Fingerprint',
                                            'bg-primary-subtle text-primary',
                                            'ri-fingerprint-line',
                                        ],
                                        default => ['Activity', 'bg-light text-dark', 'ri-history-line'],
                                    };
                                };
                            @endphp
                            @forelse ($recentActivities ?? [] as $activity)
                                @php [$dashLabel, $dashBadge, $dashIcon] = $dashActionMeta($activity->action); @endphp
                                <div class="d-flex gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                    <div class="flex-shrink-0">
                                        <span class="badge {{ $dashBadge }} p-2" title="{{ $dashLabel }}"><i
                                                class="{{ $dashIcon }}"></i></span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small fw-semibold"
                                            style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                            {{ $activity->description }}</div>
                                        <div class="text-muted" style="font-size:.75rem;">
                                            {{ $activity->user?->name ?? 'System' }} ·
                                            <span
                                                title="{{ $activity->created_at?->setTimezone('Asia/Manila')->format('M d, Y h:i A') ?? '-' }}">{{ $activity->created_at?->diffForHumans() ?? '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted small text-center mb-2">No recent activity.</p>
                            @endforelse
                            <div class="text-center mt-2">
                                <a href="{{ route('activity.logs') }}" class="btn btn-sm btn-soft-primary">View All
                                    Logs</a>
                            </div>
                        </div>
                    </div>
                    <!--end recent activity card-->
                @endif
            </div>
            <!--end col-lg-4 right sidebar-->
        </div>
        <!--end main 2-column row-->
    @endsection

    @push('scripts')
        <script src="{{ asset('assets/libs/chart.js/chart.umd.min.js') }}"></script>
        <script src="{{ asset('assets/js/dashboard-chart-export.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                @if ($errors->has('transaction_dates') || $errors->has('transaction_dates.*'))
                    const dateFilterButton = document.getElementById('transactionDateBtn');
                    if (dateFilterButton && window.bootstrap?.Dropdown) {
                        bootstrap.Dropdown.getOrCreateInstance(dateFilterButton).show();
                    }
                @endif
                const dateChecks = [...document.querySelectorAll('.transaction-date-check')];
                let availableTypes = new Set(@json($transactionDateTypeOptions));
                let availableDates = new Set(@json($transactionDateVisibleDates));
                let loadingOptions = false;
                const categoryChecks = [...document.querySelectorAll('.transaction-date-category-check')];
                const allCategories = document.getElementById('transactionDateCategoryAll');
                const categoryLabel = document.getElementById('transactionDateCategoryLabel');
                const updateCategorySelection = () => {
                    const checked = categoryChecks.filter(input => input.checked);
                    allCategories.checked = checked.length === 0;
                    categoryLabel.textContent = checked.length === 0 ? 'All categories' :
                        checked.length === 1 ? checked[0].value : checked.length + ' categories selected';
                };
                allCategories.addEventListener('change', () => {
                    categoryChecks.forEach(input => input.checked = false);
                    updateCategorySelection();
                    refreshDateChart();
                });
                categoryChecks.forEach(input => input.addEventListener('change', () => {
                    updateCategorySelection();
                    refreshDateChart();
                }));
                const typeChecks = [...document.querySelectorAll('.transaction-date-type-check')];
                const allTypes = document.getElementById('transactionDateTypeAll');
                const typeLabel = document.getElementById('transactionDateTypeLabel');
                const updateTypeSelection = () => {
                    const checked = typeChecks.filter(input => input.checked);
                    allTypes.checked = checked.length === 0;
                    typeLabel.textContent = checked.length === 0 ? 'All types' :
                        checked.length === 1 ? checked[0].value : checked.length + ' types selected';
                };
                allTypes.addEventListener('change', () => {
                    typeChecks.forEach(input => input.checked = false);
                    updateTypeSelection();
                    refreshDateChart();
                });
                typeChecks.forEach(input => input.addEventListener('change', () => {
                    updateTypeSelection();
                    refreshDateChart();
                }));
                const allDates = document.getElementById('transactionDateAll');
                const dateLabel = document.getElementById('transactionDateLabel');
                const updateDateSelection = () => {
                    const checked = dateChecks.filter(input => input.checked);
                    allDates.checked = checked.length === 0;
                    dateLabel.textContent = checked.length === 0 ? 'All dates' :
                        checked.length === 1 ? checked[0].labels[0].textContent.trim() :
                        checked.length + ' dates selected';
                };
                allDates.addEventListener('change', () => {
                    dateChecks.forEach(input => input.checked = false);
                    updateDateSelection();
                    refreshDateChart();
                });
                dateChecks.forEach(input => input.addEventListener('change', () => {
                    updateDateSelection();
                    refreshDateChart();
                }));

                function updateOptionVisibility() {
                    typeChecks.forEach(input => {
                        const allowed = availableTypes.has(input.value);
                        input.disabled = loadingOptions || !allowed;
                        input.closest('.transaction-date-type-option').classList.toggle('d-none', !allowed);
                    });
                    dateChecks.forEach(input => {
                        const row = input.closest('.transaction-date-option');
                        const allowed = availableDates.has(input.value);
                        input.disabled = loadingOptions || !allowed;
                        row.classList.toggle('d-none', !allowed);
                    });
                    allTypes.disabled = loadingOptions;
                    allDates.disabled = loadingOptions;
                    document.getElementById('transactionDateTypeBtn').disabled = loadingOptions;
                    document.getElementById('transactionDateBtn').disabled = loadingOptions;
                }
                updateOptionVisibility();
                const canvas = document.getElementById('transactionDateChart');
                if (!canvas) return;
                const labels = @json($transactionDateTrend['labels']);
                const counts = @json($transactionDateTrend['data']);
                const currentMonth = @json(now()->format('M Y'));

                const dateChart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Transactions',
                            data: counts,
                            backgroundColor: labels.map(month => month === currentMonth ? '#405189' :
                                '#405189'),
                            borderRadius: 4,
                            maxBarThickness: 48
                        }]
                    },
                    plugins: [{
                        id: 'monthlyTransactionCounts',
                        afterDatasetsDraw(chart) {
                            const {
                                ctx
                            } = chart;
                            ctx.save();
                            ctx.font = '600 12px sans-serif';
                            ctx.fillStyle = '#405189';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            chart.getDatasetMeta(0).data.forEach((bar, index) => {
                                ctx.fillText(Number(chart.data.datasets[0].data[index])
                                    .toLocaleString(), bar.x, bar.y - 6);
                            });
                            ctx.restore();
                        }
                    }],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 22
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: context => ' ' + context.parsed.y.toLocaleString() + ' transactions'
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Transaction month'
                                },
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    autoSkip: false,
                                    maxRotation: 0
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grace: '15%',
                                title: {
                                    display: true,
                                    text: 'Transactions'
                                },
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
                let pendingRequest = null;
                let requestVersion = 0;
                let appliedDates = dateChecks.filter(input => input.checked).map(input => input.value);
                let appliedCategories = categoryChecks.filter(input => input.checked).map(input => input.value);
                let appliedTypes = typeChecks.filter(input => input.checked).map(input => input.value);
                const status = document.getElementById('transactionDateFeedback');

                async function refreshDateChart() {
                    const version = ++requestVersion;
                    loadingOptions = true;
                    updateOptionVisibility();
                    pendingRequest?.abort();
                    pendingRequest = new AbortController();
                    const selected = dateChecks.filter(input => input.checked).map(input => input.value);
                    const url = new URL(@json(route('dashboard.transaction-date-trend')), window.location.origin);
                    typeChecks.filter(input => input.checked).forEach(input => url.searchParams.append(
                        'transaction_date_types[]', input.value));
                    selected.forEach(date => url.searchParams.append('transaction_dates[]', date));
                    categoryChecks.filter(input => input.checked).forEach(input => url.searchParams.append(
                        'transaction_date_categories[]', input.value));
                    status.textContent = 'Updating chart…';
                    status.classList.remove('d-none', 'text-danger');
                    canvas.setAttribute('aria-busy', 'true');
                    try {
                        const response = await fetch(url, {
                            headers: {
                                Accept: 'application/json'
                            },
                            signal: pendingRequest.signal
                        });
                        if (!response.ok) throw new Error('Unable to load chart');
                        const payload = await response.json();
                        if (version !== requestVersion) return;
                        dateChart.data.labels = payload.labels;
                        dateChart.data.datasets[0].data = payload.data;
                        dateChart.data.datasets[0].backgroundColor = payload.labels.map(month => month ===
                            currentMonth ? '#405189' : '#405189');
                        document.getElementById('transactionDatePlot').style.minWidth = Math.max(320, payload.labels
                            .length * 80) + 'px';
                        dateChart.resize();
                        dateChart.update();
                        document.getElementById('transactionDateDescription').textContent = payload.description;
                        document.getElementById('transactionDateCurrentMonth').classList.toggle('d-none', !payload
                            .labels.includes(currentMonth));
                        document.getElementById('transactionDateEmpty').classList.toggle('d-none', payload.total !==
                            0);
                        const tableBody = document.getElementById('transactionDateTableBody');
                        tableBody.replaceChildren();
                        payload.labels.forEach((month, index) => {
                            const row = document.createElement('tr');
                            const label = document.createElement('th');
                            label.scope = 'row';
                            label.textContent = month;
                            const count = document.createElement('td');
                            count.textContent = payload.data[index];
                            row.append(label, count);
                            tableBody.append(row);
                        });
                        appliedDates = payload.dates;
                        appliedCategories = payload.categories;
                        appliedTypes = payload.types;
                        availableTypes = new Set(payload.type_options);
                        availableDates = new Set(payload.date_options);
                        dateChecks.forEach(input => input.checked = appliedDates.includes(input.value));
                        typeChecks.forEach(input => input.checked = appliedTypes.includes(input.value));
                        updateDateSelection();
                        updateTypeSelection();
                        const pageUrl = new URL(window.location.href);
                        ['transaction_dates', 'transaction_dates[]', 'transaction_date_from', 'transaction_date_to']
                        .forEach(key => pageUrl.searchParams.delete(key));
                        // Also remove indexed array parameters from server-generated links.
                        [...pageUrl.searchParams.keys()].filter(key => key.startsWith('transaction_dates['))
                            .forEach(key => pageUrl.searchParams.delete(key));
                        appliedDates.forEach(date => pageUrl.searchParams.append('transaction_dates[]', date));
                        [...pageUrl.searchParams.keys()].filter(key => key === 'transaction_date_categories' || key
                            .startsWith('transaction_date_categories[')).forEach(key => pageUrl.searchParams
                            .delete(key));
                        appliedCategories.forEach(category => pageUrl.searchParams.append(
                            'transaction_date_categories[]', category));
                        [...pageUrl.searchParams.keys()].filter(key => key === 'transaction_date_types' || key
                            .startsWith('transaction_date_types[')).forEach(key => pageUrl.searchParams.delete(
                            key));
                        appliedTypes.forEach(type => pageUrl.searchParams.append('transaction_date_types[]', type));
                        window.history.replaceState({}, '', pageUrl);
                        status.textContent = 'Chart updated.';
                    } catch (error) {
                        if (error.name === 'AbortError' || version !== requestVersion) return;
                        dateChecks.forEach(input => input.checked = appliedDates.includes(input.value));
                        categoryChecks.forEach(input => input.checked = appliedCategories.includes(input.value));
                        typeChecks.forEach(input => input.checked = appliedTypes.includes(input.value));
                        updateDateSelection();
                        updateCategorySelection();
                        updateTypeSelection();
                        status.textContent =
                            'Could not update the chart. Please select the filters again to retry.';
                        status.classList.add('text-danger');
                    } finally {
                        if (version === requestVersion) {
                            canvas.setAttribute('aria-busy', 'false');
                            loadingOptions = false;
                            updateOptionVisibility();
                        }
                    }
                }
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const trendCanvas = document.getElementById('clientTrendChart');
                if (trendCanvas) {
                    const trendLabels = @json($clientTrend['labels']);
                    const trendData = @json($clientTrend['data']);

                    new Chart(trendCanvas, {
                        type: 'line',
                        plugins: [{
                            id: 'clientRegistrationCounts',
                            afterDatasetsDraw(chart) {
                                const {
                                    ctx,
                                    chartArea
                                } = chart;
                                const meta = chart.getDatasetMeta(0);
                                if (meta.hidden) return;

                                ctx.save();
                                ctx.font = '600 12px sans-serif';
                                ctx.fillStyle = '#405189';
                                ctx.textBaseline = 'bottom';
                                meta.data.forEach((point, index) => {
                                    const label = Number(trendData[index]).toLocaleString();
                                    const halfWidth = ctx.measureText(label).width / 2;
                                    const x = Math.max(chartArea.left + halfWidth,
                                        Math.min(point.x, chartArea.right - halfWidth));
                                    ctx.textAlign = 'center';
                                    ctx.fillText(label, x, point.y - 8);
                                });
                                ctx.restore();
                            }
                        }],
                        data: {
                            labels: trendLabels,
                            datasets: [{
                                label: 'Registered Clients',
                                data: trendData,
                                borderColor: '#405189',
                                backgroundColor: 'rgba(64, 81, 137, 0.12)',
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#405189',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        boxWidth: 12,
                                        boxHeight: 12,
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return ' ' + context.parsed.y + ' new registrations';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grace: '15%',
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const txCanvas = document.getElementById('transactionTrendChart');
                let txChart = null;
                let txBreakdown = [];
                // Each server series is a category/type pair. Sum them once
                // per month so bar height always represents the filtered total.
                const txDatasets = (sets, labels) => {
                    txBreakdown = sets || [];
                    return [{
                        label: 'Total transactions',
                        data: labels.map((_, index) => txBreakdown.reduce((total, series) =>
                            total + (Number(series.data[index]) || 0), 0)),
                        backgroundColor: '#405189',
                        hoverBackgroundColor: '#293966',
                        borderRadius: 5,
                        maxBarThickness: 48,
                        barPercentage: 0.8,
                        categoryPercentage: 0.8
                    }];
                };
                if (txCanvas) {
                    const txLabels = @json($transactionTrend['labels']);

                    txChart = new Chart(txCanvas, {
                        type: 'bar',
                        data: {
                            labels: txLabels,
                            datasets: txDatasets(@json($transactionTrend['datasets']), txLabels)
                        },
                        plugins: [{
                            id: 'totalTransactionCounts',
                            afterDatasetsDraw(chart) {
                                const {
                                    ctx
                                } = chart;
                                ctx.save();
                                ctx.font = '600 12px sans-serif';
                                ctx.fillStyle = '#405189';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'bottom';
                                chart.getDatasetMeta(0).data.forEach((bar, index) => {
                                    ctx.fillText(Number(chart.data.datasets[0].data[index])
                                        .toLocaleString(), bar.x, bar.y - 6);
                                });
                                ctx.restore();
                            }
                        }],
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: {
                                    top: 22
                                }
                            },
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    displayColors: false,
                                    callbacks: {
                                        label: function(context) {
                                            return 'Total: ' + context.parsed.y.toLocaleString() +
                                                ' transactions';
                                        },
                                        afterBody: function(items) {
                                            if (!items.length) return [];
                                            const index = items[0].dataIndex;
                                            const breakdown = txBreakdown.map(series => ({
                                                    label: series.label,
                                                    count: Number(series.data[index]) || 0
                                                })).filter(row => row.count > 0)
                                                .sort((a, b) => b.count - a.count);
                                            const lines = breakdown.slice(0, 5).map(row =>
                                                row.label + ': ' + row.count.toLocaleString());
                                            if (breakdown.length > 5) {
                                                const remaining = breakdown.slice(5).reduce((sum, row) =>
                                                    sum + row.count, 0);
                                                lines.push('Remaining categories/types: ' + remaining
                                                    .toLocaleString());
                                            }
                                            return lines;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        autoSkip: true,
                                        maxRotation: 0,
                                        maxTicksLimit: 8
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grace: '10%',
                                    ticks: {
                                        precision: 0,
                                        callback: value => Number(value).toLocaleString()
                                    }
                                }
                            }
                        }
                    });
                }

                // Multi-select dropdowns mirror the Filter Records style: an
                // "All" master checkbox plus one box per option. Unticking
                // everything and ticking everything both mean "no filter".
                let txUpdatingAll = false; // guard against circular updates
                const txMultiState = (boxCls) => {
                    const boxes = [...document.querySelectorAll('.' + boxCls)].filter((box) => {
                        const row = box.closest('.form-check');
                        return !row || row.style.display !== 'none';
                    });
                    const checked = boxes.filter((b) => b.checked).map((b) => b.value);
                    return {
                        boxes,
                        values: checked.length === boxes.length ? [] : checked
                    };
                };
                const txUpdateMultiLabel = (boxCls, allBoxId, labelId, allText) => {
                    // Hidden options (filtered out by the other dropdown) don't count.
                    const boxes = [...document.querySelectorAll('.' + boxCls)].filter((b) => {
                        const row = b.closest('.form-check');
                        return !row || row.style.display !== 'none';
                    });
                    const labelEl = document.getElementById(labelId);
                    const allBox = document.getElementById(allBoxId);
                    const checkedCount = boxes.filter((b) => b.checked).length;
                    if (labelEl) {
                        labelEl.textContent = (checkedCount === 0 || checkedCount === boxes
                                .length) ? allText :
                            (checkedCount === 1 ? boxes.find((b) => b.checked).value :
                                checkedCount + ' selected');
                    }
                    if (allBox && !txUpdatingAll) {
                        txUpdatingAll = true;
                        allBox.checked = checkedCount === 0 || checkedCount === boxes.length;
                        allBox.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
                        txUpdatingAll = false;
                    }
                };
                const txSyncMultiLabels = () => {
                    txUpdateMultiLabel('tx-category-check', 'txCategoryAll', 'txCategoryLabel',
                        'All categories');
                    txUpdateMultiLabel('tx-type-check', 'txTypeAll', 'txTypeLabel', 'All types');
                };
                const txTrendTitle = (categories, types) => {
                    const parts = [];
                    if (categories.length > 0) {
                        parts.push(categories.join(', '));
                    }
                    if (types.length > 0) {
                        parts.push(types.join(', '));
                    }
                    return parts.length > 0 ? 'Total Transactions — ' + parts.join(' · ') :
                        'Total Transactions';
                };
                const txTrendPageUrl = (categories, types) => {
                    const pageUrl = new URL(window.location.href);
                    ['tx_category', 'tx_category[]', 'tx_type', 'tx_type[]'].forEach((key) =>
                        pageUrl.searchParams.delete(key));
                    categories.forEach((v) => pageUrl.searchParams.append('tx_category[]', v));
                    types.forEach((v) => pageUrl.searchParams.append('tx_type[]', v));
                    return pageUrl.toString();
                };
                // Ignore stale responses when several boxes are ticked quickly.
                let txTrendRequestId = 0;
                // Refresh only the graph (no page reload); fall back to a
                // full reload only if the data request itself fails.
                const reloadTxTrend = async function() {
                    const categories = txMultiState('tx-category-check').values;
                    const types = txMultiState('tx-type-check').values;
                    const myRequest = ++txTrendRequestId;
                    try {
                        const url = new URL('{{ route('dashboard.transaction-trend') }}', window
                            .location.origin);
                        categories.forEach((v) => url.searchParams.append('tx_category[]', v));
                        types.forEach((v) => url.searchParams.append('tx_type[]', v));
                        const res = await fetch(url.toString(), {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const payload = await res.json();
                        if (myRequest !== txTrendRequestId) {
                            return;
                        }
                        if (!res.ok || !payload.success) {
                            throw new Error(payload.message || 'Failed to load graph data.');
                        }
                        if (txChart) {
                            txChart.data.labels = payload.labels;
                            txChart.data.datasets = txDatasets(payload.datasets, payload.labels);
                            txChart.update();
                        }
                        const titleEl = document.getElementById('txTrendTitle');
                        if (titleEl) {
                            titleEl.textContent = txTrendTitle(categories, types);
                        }
                        window.history.replaceState(null, '', txTrendPageUrl(categories, types));
                    } catch (error) {
                        if (myRequest === txTrendRequestId) {
                            window.location.href = txTrendPageUrl(categories, types);
                        }
                    }
                };
                // Cascading menus: the Type list shows only types occurring in
                // the selected categories. Hidden options are unchecked (they
                // contribute zero rows under the current categories anyway).
                let txTypesRequestId = 0;
                const txApplyTypeVisibility = async () => {
                    const myRequest = ++txTypesRequestId;
                    try {
                        const categories = txMultiState('tx-category-check').values;
                        const url = new URL('{{ route('dashboard.transaction-trend.types') }}',
                            window.location.origin);
                        categories.forEach((v) => url.searchParams.append('tx_category[]', v));
                        const res = await fetch(url.toString(), {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const payload = await res.json();
                        if (myRequest !== txTypesRequestId || !res.ok || !payload.success) {
                            return;
                        }
                        const allowed = new Set(payload.types || []);
                        document.querySelectorAll('.tx-type-check').forEach((box) => {
                            const row = box.closest('.form-check');
                            const show = allowed.has(box.value);
                            if (row) {
                                row.style.display = show ? '' : 'none';
                            }
                            if (!show) {
                                box.checked = false;
                            }
                        });
                        txSyncMultiLabels();
                    } catch (error) {
                        // Menu stays as-is; the chart reload still applies the filter.
                    }
                };
                document.querySelectorAll('.tx-category-check').forEach((box) => {
                    box.addEventListener('change', async () => {
                        txSyncMultiLabels();
                        await txApplyTypeVisibility();
                        reloadTxTrend();
                    });
                });
                document.querySelectorAll('.tx-type-check').forEach((box) => {
                    box.addEventListener('change', () => {
                        txSyncMultiLabels();
                        reloadTxTrend();
                    });
                });
                [
                    ['txCategoryAll', 'tx-category-check', true],
                    ['txTypeAll', 'tx-type-check', false]
                ].forEach(([allId, cls, isCategory]) => {
                    const allBox = document.getElementById(allId);
                    if (allBox) {
                        allBox.addEventListener('change', async function() {
                            if (txUpdatingAll) {
                                return;
                            }
                            // The Type "All" only covers visible options.
                            document.querySelectorAll('.' + cls).forEach((b) => {
                                const row = b.closest('.form-check');
                                if (!row || row.style.display !== 'none') {
                                    b.checked = this.checked;
                                }
                            });
                            txSyncMultiLabels();
                            if (isCategory) {
                                await txApplyTypeVisibility();
                            }
                            reloadTxTrend();
                        });
                    }
                });
                // Initialize labels/master state on page load.
                txSyncMultiLabels();
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const caravanCanvas = document.getElementById('caravanTrendChart');
                if (caravanCanvas) {
                    const caravanLabels = @json($caravanTrend['labels']);
                    const caravanData = @json($caravanTrend['data']);

                    new Chart(caravanCanvas, {
                        type: 'line',
                        data: {
                            labels: caravanLabels,
                            datasets: [{
                                label: 'CARAVAN Transactions',
                                data: caravanData,
                                borderColor: '#405189',
                                backgroundColor: 'rgba(64, 81, 137, 0.12)',
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#405189',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        boxWidth: 12,
                                        boxHeight: 12,
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return ' ' + context.parsed.y + ' caravan transactions';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const canvas = document.getElementById('serviceCategoryChart');
                if (!canvas) return;

                const sourceLabels = @json($chartLabels);
                const sourceData = @json($chartData);
                const servicePalette = @json($chartColors);
                // Keep each category's original palette color (same as the
                // Service Categories cards below) so colors stay stable when sorted.
                const rows = sourceLabels.map((label, i) => ({
                    label,
                    count: Number(sourceData[i]) || 0,
                    color: servicePalette[i % servicePalette.length]
                })).sort((a, b) => b.count - a.count);
                const serviceLabels = rows.map(row => row.label);
                const serviceData = rows.map(row => row.count);
                const serviceColors = rows.map(row => row.color);
                const total = serviceData.reduce((sum, count) => sum + count, 0);
                const valueLabel = count => count.toLocaleString() + ' (' +
                    (total ? count / total * 100 : 0).toFixed(1) + '%)';
                const valueLabels = {
                    id: 'serviceCategoryValues',
                    afterDatasetsDraw(chart) {
                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.font = '12px sans-serif';
                        ctx.fillStyle = getComputedStyle(canvas).color;
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        chart.getDatasetMeta(0).data.forEach((bar, i) => {
                            ctx.fillText(valueLabel(serviceData[i]), bar.x + 8, bar.y);
                        });
                        ctx.restore();
                    }
                };
                // Reserve enough space for the complete count and percentage.
                const measure = canvas.getContext('2d');
                measure.font = '12px sans-serif';
                const labelWidth = Math.max(...serviceData.map(count => measure.measureText(valueLabel(count)).width));
                new Chart(canvas, {
                    type: 'bar',
                    plugins: [valueLabels],
                    data: {
                        labels: serviceLabels,
                        datasets: [{
                            label: 'Transactions',
                            data: serviceData,
                            backgroundColor: serviceColors,
                            borderRadius: 4,
                            maxBarThickness: 26
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                right: labelWidth + 16
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'Transactions'
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    autoSkip: false,
                                    callback(value) {
                                        const label = this.getLabelForValue(value);
                                        return label.match(/.{1,22}(?:\s|$)|.{1,22}/g) || label;
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: context => ' Transactions: ' + valueLabel(context.parsed.x)
                                }
                            }
                        }
                    }
                });
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const clientCanvas = document.getElementById('clientCategoryChart');
                const clientPlot = document.getElementById('clientCategoryPlot');
                const clientEmpty = document.getElementById('clientCategoryEmpty');
                if (!clientCanvas) return;

                const clientPalette = @json($chartColors);
                const buildRows = (labels, data) => (labels || []).map((label, i) => ({
                        label,
                        count: Number((data || [])[i]) || 0
                    }))
                    .sort((a, b) => b.count - a.count);
                let currentRows = buildRows(@json($clientCategoryDistribution['labels']), @json($clientCategoryDistribution['data']));
                let clientLabels = currentRows.map(row => row.label);
                let clientData = currentRows.map(row => row.count);
                let clientTotal = clientData.reduce((sum, count) => sum + count, 0);
                const valueLabel = count => Number(count).toLocaleString() + ' (' +
                    (clientTotal ? Number(count) / clientTotal * 100 : 0).toFixed(1) + '%)';
                const valueLabels = {
                    id: 'clientCategoryValues',
                    afterDatasetsDraw(chart) {
                        const ctx = chart.ctx;
                        const liveData = chart.data.datasets[0]?.data || [];
                        const liveTotal = liveData.reduce((sum, count) => sum + (Number(count) || 0), 0);
                        ctx.save();
                        ctx.font = '12px sans-serif';
                        ctx.fillStyle = getComputedStyle(clientCanvas).color;
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        chart.getDatasetMeta(0).data.forEach((bar, i) => {
                            const count = Number(liveData[i]) || 0;
                            ctx.fillText(count.toLocaleString() + ' (' +
                                (liveTotal ? count / liveTotal * 100 : 0).toFixed(1) + '%)', bar.x + 8, bar.y);
                        });
                        ctx.restore();
                    }
                };
                // Reserve enough space for the complete count and percentage.
                const measure = clientCanvas.getContext('2d');
                measure.font = '12px sans-serif';
                const measureWidth = (labels, data) => {
                    const total = (data || []).reduce((sum, count) => sum + (Number(count) || 0), 0);
                    const widths = (data || []).map(count => measure.measureText(
                        Number(count).toLocaleString() + ' (' +
                        (total ? Number(count) / total * 100 : 0).toFixed(1) + '%)').width);
                    return widths.length ? Math.max(...widths) : 80;
                };
                const distChart = new Chart(clientCanvas, {
                    type: 'bar',
                    plugins: [valueLabels],
                    data: {
                        labels: clientLabels,
                        datasets: [{
                            label: 'Transactions',
                            data: clientData,
                            backgroundColor: clientLabels.map((_, i) => clientPalette[i % clientPalette
                                .length]),
                            borderRadius: 4,
                            maxBarThickness: 26
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                right: measureWidth(clientLabels, clientData) + 16
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'Transactions'
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    autoSkip: false,
                                    callback(value) {
                                        const label = this.getLabelForValue(value);
                                        return label.match(/.{1,22}(?:\s|$)|.{1,22}/g) || label;
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: context => ' Transactions: ' + valueLabel(context.parsed.x)
                                }
                            }
                        }
                    }
                });

                const setDistData = (labels, data) => {
                    currentRows = buildRows(labels, data);
                    clientLabels = currentRows.map(row => row.label);
                    clientData = currentRows.map(row => row.count);
                    clientTotal = clientData.reduce((sum, count) => sum + count, 0);
                    const hasData = clientTotal > 0;
                    clientCanvas.classList.toggle('d-none', !hasData);
                    if (clientEmpty) clientEmpty.classList.toggle('d-none', hasData);
                    if (clientPlot) {
                        clientPlot.style.height = Math.max(200, clientData.length * 25 + 40) + 'px';
                    }
                    if (!hasData) return;
                    distChart.data.labels = clientLabels;
                    distChart.data.datasets[0].data = clientData;
                    distChart.data.datasets[0].backgroundColor = clientLabels.map((_, i) => clientPalette[i %
                        clientPalette.length]);
                    distChart.options.layout.padding.right = measureWidth(clientLabels, clientData) + 16;
                    distChart.resize();
                    distChart.update();
                };

                // Multi-select dropdowns mirror the Total Transactions chart:
                // an "All" master checkbox plus one box per option. Unticking
                // everything and ticking everything both mean "no filter".
                let distUpdatingAll = false; // guard against circular updates
                const distMultiState = (boxCls) => {
                    const boxes = [...document.querySelectorAll('.' + boxCls)].filter((box) => {
                        const row = box.closest('.form-check');
                        return !row || row.style.display !== 'none';
                    });
                    const checked = boxes.filter((b) => b.checked).map((b) => b.value);
                    return {
                        boxes,
                        values: checked.length === boxes.length ? [] : checked
                    };
                };
                const distUpdateMultiLabel = (boxCls, allBoxId, labelId, allText) => {
                    // Hidden options (filtered out by the other dropdown) don't count.
                    const boxes = [...document.querySelectorAll('.' + boxCls)].filter((b) => {
                        const row = b.closest('.form-check');
                        return !row || row.style.display !== 'none';
                    });
                    const labelEl = document.getElementById(labelId);
                    const allBox = document.getElementById(allBoxId);
                    const checkedCount = boxes.filter((b) => b.checked).length;
                    if (labelEl) {
                        labelEl.textContent = (checkedCount === 0 || checkedCount === boxes
                                .length) ? allText :
                            (checkedCount === 1 ? boxes.find((b) => b.checked).value :
                                checkedCount + ' selected');
                    }
                    if (allBox && !distUpdatingAll) {
                        distUpdatingAll = true;
                        allBox.checked = checkedCount === 0 || checkedCount === boxes.length;
                        allBox.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
                        distUpdatingAll = false;
                    }
                };
                const distSyncMultiLabels = () => {
                    distUpdateMultiLabel('dist-category-check', 'distCategoryAll', 'distCategoryLabel',
                        'All categories');
                    distUpdateMultiLabel('dist-type-check', 'distTypeAll', 'distTypeLabel', 'All types');
                };
                const distPageUrl = (categories, types) => {
                    const pageUrl = new URL(window.location.href);
                    ['distribution_category', 'distribution_category[]', 'distribution_type', 'distribution_type[]'].forEach((key) =>
                        pageUrl.searchParams.delete(key));
                    [...pageUrl.searchParams.keys()].filter(key => key.startsWith('distribution_category[') || key.startsWith('distribution_type['))
                        .forEach(key => pageUrl.searchParams.delete(key));
                    categories.forEach((v) => pageUrl.searchParams.append('distribution_category[]', v));
                    types.forEach((v) => pageUrl.searchParams.append('distribution_type[]', v));
                    return pageUrl.toString();
                };
                // Ignore stale responses when several boxes are ticked quickly.
                let distRequestId = 0;
                // Refresh only the graph (no page reload); fall back to a
                // full reload only if the data request itself fails.
                const reloadDistChart = async function() {
                    const categories = distMultiState('dist-category-check').values;
                    const types = distMultiState('dist-type-check').values;
                    const myRequest = ++distRequestId;
                    try {
                        const url = new URL('{{ route('dashboard.client-distribution') }}', window
                            .location.origin);
                        categories.forEach((v) => url.searchParams.append('distribution_category[]', v));
                        types.forEach((v) => url.searchParams.append('distribution_type[]', v));
                        const res = await fetch(url.toString(), {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const payload = await res.json();
                        if (myRequest !== distRequestId) {
                            return;
                        }
                        if (!res.ok || !payload.success) {
                            throw new Error(payload.message || 'Failed to load graph data.');
                        }
                        setDistData(payload.labels, payload.data);
                        window.history.replaceState(null, '', distPageUrl(categories, types));
                    } catch (error) {
                        if (myRequest === distRequestId) {
                            window.location.href = distPageUrl(categories, types);
                        }
                    }
                };
                // Cascading menus: the Type list shows only types occurring in
                // the selected categories. Hidden options are unchecked (they
                // contribute zero rows under the current categories anyway).
                let distTypesRequestId = 0;
                const distApplyTypeVisibility = async () => {
                    const myRequest = ++distTypesRequestId;
                    try {
                        const categories = distMultiState('dist-category-check').values;
                        const url = new URL('{{ route('dashboard.client-distribution.types') }}',
                            window.location.origin);
                        categories.forEach((v) => url.searchParams.append('distribution_category[]', v));
                        const res = await fetch(url.toString(), {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const payload = await res.json();
                        if (myRequest !== distTypesRequestId || !res.ok || !payload.success) {
                            return;
                        }
                        const allowed = new Set(payload.types || []);
                        document.querySelectorAll('.dist-type-check').forEach((box) => {
                            const row = box.closest('.form-check');
                            const show = allowed.has(box.value);
                            if (row) {
                                row.style.display = show ? '' : 'none';
                            }
                            if (!show) {
                                box.checked = false;
                            }
                        });
                        distSyncMultiLabels();
                    } catch (error) {
                        // Menu stays as-is; the chart reload still applies the filter.
                    }
                };
                document.querySelectorAll('.dist-category-check').forEach((box) => {
                    box.addEventListener('change', async () => {
                        distSyncMultiLabels();
                        await distApplyTypeVisibility();
                        reloadDistChart();
                    });
                });
                document.querySelectorAll('.dist-type-check').forEach((box) => {
                    box.addEventListener('change', () => {
                        distSyncMultiLabels();
                        reloadDistChart();
                    });
                });
                [
                    ['distCategoryAll', 'dist-category-check', true],
                    ['distTypeAll', 'dist-type-check', false]
                ].forEach(([allId, cls, isCategory]) => {
                    const allBox = document.getElementById(allId);
                    if (allBox) {
                        allBox.addEventListener('change', async function() {
                            if (distUpdatingAll) {
                                return;
                            }
                            // The Type "All" only covers visible options.
                            document.querySelectorAll('.' + cls).forEach((b) => {
                                const row = b.closest('.form-check');
                                if (!row || row.style.display !== 'none') {
                                    b.checked = this.checked;
                                }
                            });
                            distSyncMultiLabels();
                            if (isCategory) {
                                await distApplyTypeVisibility();
                            }
                            reloadDistChart();
                        });
                    }
                });
                // Initialize labels/master state on page load.
                distSyncMultiLabels();
            });
        </script>

        <!-- Dashboard Analog Clock & Calendar -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // â”€â”€ Analog Clock â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                const clockCanvas = document.getElementById('dashAnalogClock');
                const digitalClock = document.getElementById('dashDigitalClock');
                const digitalDate = document.getElementById('dashDigitalDate');

                if (clockCanvas && clockCanvas.getContext) {
                    const cCtx = clockCanvas.getContext('2d');
                    const radius = clockCanvas.width / 2;

                    function drawClock() {
                        const now = new Date();
                        const h = now.getHours() % 12;
                        const m = now.getMinutes();
                        const s = now.getSeconds();

                        cCtx.save();
                        cCtx.clearRect(0, 0, clockCanvas.width, clockCanvas.height);
                        cCtx.translate(radius, radius);

                        // Face
                        cCtx.beginPath();
                        cCtx.arc(0, 0, radius - 4, 0, 2 * Math.PI);
                        cCtx.fillStyle = '#fff';
                        cCtx.fill();
                        cCtx.lineWidth = 3;
                        cCtx.strokeStyle = '#405189';
                        cCtx.stroke();

                        // Hour markers
                        for (let i = 0; i < 12; i++) {
                            const ang = (i * Math.PI) / 6;
                            const isMajor = i % 3 === 0;
                            const outerR = radius - 10;
                            const innerR = isMajor ? radius - 26 : radius - 20;
                            cCtx.beginPath();
                            cCtx.moveTo(Math.cos(ang) * innerR, Math.sin(ang) * innerR);
                            cCtx.lineTo(Math.cos(ang) * outerR, Math.sin(ang) * outerR);
                            cCtx.lineWidth = isMajor ? 3 : 1.5;
                            cCtx.strokeStyle = '#405189';
                            cCtx.stroke();
                        }

                        // Minute markers
                        for (let i = 0; i < 60; i++) {
                            if (i % 5 !== 0) {
                                const ang = (i * Math.PI) / 30;
                                cCtx.beginPath();
                                cCtx.arc(Math.cos(ang) * (radius - 12), Math.sin(ang) * (radius - 12), 1, 0, 2 * Math
                                    .PI);
                                cCtx.fillStyle = '#adb5bd';
                                cCtx.fill();
                            }
                        }

                        // Hour numbers
                        cCtx.font = 'bold 13px sans-serif';
                        cCtx.fillStyle = '#000';
                        cCtx.textAlign = 'center';
                        cCtx.textBaseline = 'middle';
                        for (let n = 1; n <= 12; n++) {
                            const ang = (n * Math.PI) / 6 - Math.PI / 2;
                            const nr = radius - 34;
                            cCtx.fillText(n.toString(), Math.cos(ang) * nr, Math.sin(ang) * nr);
                        }

                        // Hands helper
                        function drawHand(angle, length, width, color) {
                            cCtx.beginPath();
                            cCtx.lineWidth = width;
                            cCtx.lineCap = 'round';
                            cCtx.strokeStyle = color;
                            cCtx.moveTo(0, 0);
                            cCtx.lineTo(Math.cos(angle) * length, Math.sin(angle) * length);
                            cCtx.stroke();
                        }

                        // Hour hand
                        const hAngle = ((h + m / 60) * Math.PI) / 6 - Math.PI / 2;
                        drawHand(hAngle, radius * 0.5, 5, '#405189');

                        // Minute hand
                        const mAngle = ((m + s / 60) * Math.PI) / 30 - Math.PI / 2;
                        drawHand(mAngle, radius * 0.7, 3, '#405189');

                        // Second hand
                        const sAngle = (s * Math.PI) / 30 - Math.PI / 2;
                        drawHand(sAngle, radius * 0.78, 1.5, '#0ab39c');

                        // Center dot
                        cCtx.beginPath();
                        cCtx.arc(0, 0, 4, 0, 2 * Math.PI);
                        cCtx.fillStyle = '#0ab39c';
                        cCtx.fill();

                        cCtx.restore();

                        // Digital readout
                        if (digitalClock) {
                            digitalClock.textContent = now.toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit'
                            });
                        }
                        if (digitalDate) {
                            digitalDate.textContent = now.toLocaleDateString([], {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                        }
                    }

                    drawClock();
                    setInterval(drawClock, 1000);
                }

                // â”€â”€ Calendar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                const calBody = document.getElementById('dashCalBody');
                const calMonthYear = document.getElementById('dashCalMonthYear');
                const calPrev = document.getElementById('dashCalPrev');
                const calNext = document.getElementById('dashCalNext');

                if (calBody && calMonthYear && calPrev && calNext) {
                    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August',
                        'September', 'October', 'November', 'December'
                    ];
                    let calDate = new Date();

                    function renderCalendar() {
                        const today = new Date();
                        const year = calDate.getFullYear();
                        const month = calDate.getMonth();
                        const firstDay = new Date(year, month, 1).getDay();
                        const daysInMonth = new Date(year, month + 1, 0).getDate();

                        calMonthYear.textContent = months[month] + ' ' + year;
                        calBody.innerHTML = '';

                        let row = document.createElement('tr');
                        for (let i = 0; i < firstDay; i++) {
                            row.appendChild(document.createElement('td'));
                        }

                        for (let d = 1; d <= daysInMonth; d++) {
                            const td = document.createElement('td');
                            td.textContent = d;
                            td.style.cursor = 'default';
                            td.style.borderRadius = '50%';
                            td.style.padding = '20px 0';

                            if (d === today.getDate() && month === today.getMonth() && year === today.getFullYear())
                                Object.assign(td.style, {
                                    background: '#0ab39c',
                                    color: '#fff',
                                    fontWeight: 'bold',
                                    borderRadius: '50%',
                                    width: '26px',
                                    height: '26px',
                                    lineHeight: '26px',
                                    padding: '0',
                                    margin: 'auto',
                                    display: 'inline-block',
                                    marginTop: '18px'
                                });

                            row.appendChild(td);
                            if ((firstDay + d) % 7 === 0) {
                                calBody.appendChild(row);
                                row = document.createElement('tr');
                            }
                        }

                        if (row.children.length > 0) {
                            while (row.children.length < 7) {
                                row.appendChild(document.createElement('td'));
                            }
                            calBody.appendChild(row);
                        }
                    }

                    calPrev.addEventListener('click', function() {
                        calDate.setMonth(calDate.getMonth() - 1);
                        renderCalendar();
                    });

                    calNext.addEventListener('click', function() {
                        calDate.setMonth(calDate.getMonth() + 1);
                        renderCalendar();
                    });

                    renderCalendar();
                }
            });
        </script>
    @endpush
