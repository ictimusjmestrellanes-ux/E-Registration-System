<?php $__env->startSection('title', 'ERS | ' . $labels . ' Transactions'); ?>
<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h4 class="mb-1"><?php echo e($labels); ?> Transactions</h4>
                                <p class="text-muted mb-0"><?php echo e($total); ?> transaction(s)<?php echo e($category ? ' under this service category' : ''); ?></p>
                            </div>
                            <a href="<?php echo e(route('dashboard')); ?>" class="btn btn-light btn-sm">
                                <i class="ri-arrow-left-line me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="border rounded-4 p-3 mb-3" id="transactionFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-0">
                                <div>
                                    <div class="fw-bold fs-5">Filter Transactions</div>
                                    <div class="text-muted small">Narrow records by keyword, status, client category,
                                        service category, and transaction date range. Search covers all pages.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        id="transactionFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <a href="<?php echo e($category ? route('transactions.category', $category) : route('transactions.index')); ?>"
                                        class="btn btn-sm btn-soft-secondary" id="transactionFiltersResetBtn">Reset</a>
                                </div>
                            </div>

                            <form method="GET" id="transactionFiltersForm" class="mt-3 <?php echo e(request()->anyFilled(['search', 'status', 'client_category', 'category_filter', 'date_from', 'date_to']) ? '' : 'd-none'); ?>">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="transactionKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="transactionKeywordInput"
                                                name="search" placeholder="Transaction ID, clerk, or type..."
                                                value="<?php echo e(request('search')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="transactionStatusFilter"
                                            class="form-label fw-semibold text-uppercase small">Status</label>
                                        <select class="form-select" id="transactionStatusFilter" name="status">
                                            <option value="">All Status</option>
                                            <?php $__currentLoopData = ($filterStatuses ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($status); ?>" <?php echo e(request('status') === $status ? 'selected' : ''); ?>>
                                                    <?php echo e($status); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="transactionClientCategoryFilter"
                                            class="form-label fw-semibold text-uppercase small">Client Category</label>
                                        <select class="form-select" id="transactionClientCategoryFilter" name="client_category">
                                            <option value="">All Client Categories</option>
                                            <?php $__currentLoopData = ($filterClientCategories ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clientCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($clientCategory); ?>" <?php echo e(request('client_category') === $clientCategory ? 'selected' : ''); ?>>
                                                    <?php echo e($clientCategory); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    <?php if(!$category): ?>
                                        <div class="col-12 col-md-6 col-xl-2">
                                            <label for="transactionCategoryFilter"
                                                class="form-label fw-semibold text-uppercase small">Category</label>
                                            <select class="form-select" id="transactionCategoryFilter" name="category_filter">
                                                <option value="">All Categories</option>
                                                <?php $__currentLoopData = ($filterCategories ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($key); ?>" <?php echo e(request('category_filter') === $key ? 'selected' : ''); ?>>
                                                        <?php echo e($label); ?></option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="transactionDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="transactionDateFrom" name="date_from"
                                            value="<?php echo e(request('date_from')); ?>">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="transactionDateTo"
                                            class="form-label fw-semibold text-uppercase small">Date To</label>
                                        <input type="date" class="form-control" id="transactionDateTo" name="date_to"
                                            value="<?php echo e(request('date_to')); ?>">
                                    </div>
                                </div>

                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 d-flex gap-2 justify-content-end">
                                        <button type="submit" class="btn btn-sm btn-primary px-4" id="transactionDateApplyBtn">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-3" id="transactionSearchSummary">
                                    <?php echo e(request()->anyFilled(['search', 'status', 'client_category', 'category_filter', 'date_from', 'date_to']) ? 'Filtered transactions are shown below.' : 'Showing all transactions.'); ?>

                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Client</th>
                                        <th>Transaction ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Clerk</th>
                                        <th style="width: 110px; text-align: center;">Status</th>
                                        <th style="width: 90px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php
                                            $isApproved = strtolower($transaction->status ?? 'Pending') === 'approved';
                                            $client = App\Models\Client::where('client_id', $transaction->client_id)->first();
                                            $clientName = $client->full_name ?? $transaction->client_id;
                                        ?>
                                        <tr class="<?php echo e($isApproved ? '' : 'table-warning'); ?>" data-transaction-row
                                            data-search-transaction-id="<?php echo e(strtolower($transaction->transaction_id ?? '')); ?>"
                                            data-search-client="<?php echo e(strtolower($clientName)); ?>"
                                            data-search-client-id="<?php echo e(strtolower($transaction->client_id ?? '')); ?>"
                                            data-search-category="<?php echo e(strtolower($transaction->category ?? '')); ?>"
                                            data-search-category-key="<?php echo e(strtolower(App\Models\TransactionHistory::normalizeCategory($transaction->category) ?? '')); ?>"
                                            data-search-type="<?php echo e(strtolower($transaction->type ?? '')); ?>"
                                            data-search-clerk="<?php echo e(strtolower($transaction->clerk ?? '')); ?>"
                                            data-search-status="<?php echo e(strtolower($transaction->status ?? 'pending')); ?>"
                                            data-search-client-category="<?php echo e(strtolower($transaction->client_category ?? '')); ?>"
                                            data-search-date="<?php echo e($transaction->transaction_date?->format('Y-m-d')); ?>"
                                            data-search-all="<?php echo e(strtolower(($transaction->transaction_id ?? '') . ' ' . $clientName . ' ' . ($transaction->client_id ?? '') . ' ' . ($transaction->category ?? '') . ' ' . ($transaction->type ?? '') . ' ' . ($transaction->clerk ?? '') . ' ' . ($transaction->status ?? '') . ' ' . ($transaction->client_category ?? ''))); ?>">
                                            <td class="fw-semibold"><?php echo e($clientName); ?></td>
                                            <td class="small">
                                                <a href="<?php echo e(route('transactions.show', $transaction->id)); ?>"
                                                    class="text-primary"><?php echo e($transaction->transaction_id); ?></a>
                                            </td>
                                            <td class="small"><?php echo e($transaction->transaction_date?->format('M d, Y')); ?></td>
                                            <td class="small"><?php echo e($transaction->type_label); ?></td>
                                            <td class="small"><?php echo e($transaction->clerk ?? '-'); ?></td>
                                            <td class="text-center">
                                                <span
                                                    class="badge <?php echo e($isApproved ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'); ?> px-3 py-2">
                                                    <?php echo e($transaction->status ?? 'Pending'); ?>

                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if($client): ?>
                                                    <a href="<?php echo e(route('clients.show', $client)); ?>"
                                                        class="btn btn-sm btn-soft-primary" title="View client details">
                                                        <i class="ri-eye-line"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
                                                No transactions found<?php echo e($category ? ' under this category' : ''); ?>.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                        <tr id="transactionSearchNoResultsRow" class="d-none">
                                            <td colspan="7" class="text-center text-muted py-5">
                                                No transactions match the current filters.
                                            </td>
                                        </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <?php echo e($transactions->links('pagination::bootstrap-5')); ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const transactionFiltersToggleBtn = document.getElementById('transactionFiltersToggleBtn');
            const transactionFiltersFormEl = document.getElementById('transactionFiltersForm');
            const transactionKeywordInput = document.getElementById('transactionKeywordInput');
            const transactionStatusFilter = document.getElementById('transactionStatusFilter');
            const transactionClientCategoryFilter = document.getElementById('transactionClientCategoryFilter');
            const transactionCategoryFilter = document.getElementById('transactionCategoryFilter');
            const transactionDateFrom = document.getElementById('transactionDateFrom');
            const transactionDateTo = document.getElementById('transactionDateTo');
            const transactionSearchSummary = document.getElementById('transactionSearchSummary');
            const transactionSearchNoResultsRow = document.getElementById('transactionSearchNoResultsRow');

            if (!transactionFiltersToggleBtn || !transactionFiltersFormEl || !transactionKeywordInput ||
                !transactionStatusFilter || !transactionClientCategoryFilter || !transactionDateFrom ||
                !transactionDateTo || !transactionSearchSummary
            ) {
                return;
            }

            const transactionRows = Array.from(document.querySelectorAll('[data-transaction-row]'));
            let filtersVisible = !transactionFiltersFormEl.classList.contains('d-none');

            const setFiltersVisibility = (visible) => {
                filtersVisible = visible;
                transactionFiltersFormEl.classList.toggle('d-none', !visible);
                transactionFiltersToggleBtn.innerHTML = visible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };

            const syncToggleLabel = () => {
                transactionFiltersToggleBtn.innerHTML = filtersVisible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };
            syncToggleLabel();

            transactionFiltersToggleBtn.addEventListener('click', function() {
                setFiltersVisibility(!filtersVisible);
            });

            const filterTransactionList = () => {
                const query = transactionKeywordInput.value.trim().toLowerCase();
                const status = transactionStatusFilter.value.trim().toLowerCase();
                const clientCategory = transactionClientCategoryFilter.value.trim().toLowerCase();
                const category = transactionCategoryFilter ? transactionCategoryFilter.value.trim().toLowerCase() : '';
                const dateFrom = transactionDateFrom.value;
                const dateTo = transactionDateTo.value;
                let visibleCount = 0;

                transactionRows.forEach((row) => {
                    const searchableValue = row.dataset.searchAll || '';
                    const rowStatus = (row.dataset.searchStatus || '').toLowerCase();
                    const rowClientCategory = (row.dataset.searchClientCategory || '').toLowerCase();
                    const rowCategory = (row.dataset.searchCategory || '').toLowerCase();
                    const rowCategoryKey = (row.dataset.searchCategoryKey || '').toLowerCase();
                    const rowDate = row.dataset.searchDate || '';
                    const matchesSearch = !query || searchableValue.includes(query);
                    const matchesStatus = !status || rowStatus === status;
                    const matchesClientCategory = !clientCategory || rowClientCategory === clientCategory;
                    const matchesCategory = !category || rowCategoryKey === category || rowCategory === category;
                    const matchesDate = (!dateFrom || rowDate >= dateFrom) && (!dateTo || rowDate <= dateTo);
                    const matches = matchesSearch && matchesStatus && matchesClientCategory &&
                        matchesCategory && matchesDate;
                    row.classList.toggle('d-none', !matches);

                    if (matches) {
                        visibleCount += 1;
                    }
                });

                if (transactionSearchNoResultsRow) {
                    transactionSearchNoResultsRow.classList.toggle('d-none', visibleCount !== 0);
                }

                if (transactionSearchSummary) {
                    const activeFilters = [query, status, clientCategory, category, dateFrom, dateTo].filter(
                        Boolean).length;
                    if (!activeFilters) {
                        transactionSearchSummary.textContent = 'Showing all transactions.';
                    } else {
                        transactionSearchSummary.textContent =
                            `Showing ${visibleCount} matching transaction${visibleCount === 1 ? '' : 's'}.`;
                    }
                }
            };

            transactionKeywordInput.addEventListener('input', filterTransactionList);
            transactionStatusFilter.addEventListener('change', filterTransactionList);
            transactionClientCategoryFilter.addEventListener('change', filterTransactionList);
            if (transactionCategoryFilter) {
                transactionCategoryFilter.addEventListener('change', filterTransactionList);
            }
            transactionDateFrom.addEventListener('change', filterTransactionList);
            transactionDateTo.addEventListener('change', filterTransactionList);
        });
    </script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\client_transaction\transactionCategoryList.blade.php ENDPATH**/ ?>