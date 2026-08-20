<?php $__env->startSection('title', 'ERS | Dashboard'); ?>
<?php $__env->startSection('content'); ?>
    <?php
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
        $totalCategoryTransactions = array_sum($categoryCounts);

        $chartLabels = array_values($categories);
        $chartData = [];
        foreach ($categories as $key => $label) {
            $chartData[] = $categoryCounts[$key] ?? 0;
        }
        $chartColors = [
            '#405189',
            '#0ac074',
            '#299cdb',
            '#e9b876',
            '#a8a29a',
            '#f06548',
            '#405189',
            '#0ac074',
            '#299cdb',
            '#e9b876',
            '#a8a29a',
        ];
    ?>

    <style>
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
                                <p class="text-muted mb-0">Welcome back, <?php echo e(auth()->user()?->name ?? 'User'); ?>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <a href="<?php echo e(route('client.list')); ?>" class="text-decoration-none">
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
                                    <h3 class="mb-0"><?php echo e($totalClients); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-lg-4">
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
                                    <h3 class="mb-0"><?php echo e(count($categories)); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-lg-4">
                <a href="<?php echo e(route('transactions.index')); ?>" class="text-decoration-none">
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
                                    <h3 class="mb-0"><?php echo e($totalCategoryTransactions); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card material-shadow h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Total Registered Clients</h5>
                        <p class="text-muted mb-0">Client registrations per month (January 2026 - present)</p>
                    </div>
                    <div class="card-body">
                        <div class="w-100" style="height: 320px; position: relative;">
                            <canvas id="clientTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card material-shadow h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Total Transactions</h5>
                        <p class="text-muted mb-0">Transactions per month (January 2026 - present)</p>
                    </div>
                    <div class="card-body">
                        <div class="w-100" style="height: 320px; position: relative;">
                            <canvas id="transactionTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card material-shadow h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Service Categories Distribution</h5>
                        <p class="text-muted mb-0">Share of transactions per service category</p>
                    </div>
                    <div class="card-body">
                        <div class="w-100" style="height: 320px; position: relative;">
                            <canvas id="serviceCategoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" id="service-categories">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="mb-0">Service Categories</h5>
                        <p class="text-muted mb-0">Overview of client service requests</p>
                    </div>
                </div>
            </div>

            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    [$icon, $color] = $categoryMeta[$key] ?? ['fa-circle', 'secondary'];
                    $count = $categoryCounts[$key] ?? 0;
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <a href="<?php echo e(route('transactions.category', $key)); ?>" class="text-decoration-none">
                        <div class="card material-shadow h-100 category-card">
                            <div class="card-body text-center">
                                <div
                                    class="avatar-md bg-<?php echo e($color); ?> bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="fa-solid <?php echo e($icon); ?> text-<?php echo e($color); ?> fs-3"></i>
                                </div>
                                <h4 class="mb-1"><?php echo e($count); ?></h4>
                                <p class="text-muted mb-0"><?php echo e($label); ?></p>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('assets/libs/chart.js/chart.umd.min.js')); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const trendCanvas = document.getElementById('clientTrendChart');
            if (trendCanvas) {
                const trendLabels = <?php echo json_encode($clientTrend['labels'], 15, 512) ?>;
                const trendData = <?php echo json_encode($clientTrend['data'], 15, 512) ?>;

                new Chart(trendCanvas, {
                    type: 'line',
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
            if (txCanvas) {
                const txLabels = <?php echo json_encode($transactionTrend['labels'], 15, 512) ?>;
                const txData = <?php echo json_encode($transactionTrend['data'], 15, 512) ?>;

                new Chart(txCanvas, {
                    type: 'line',
                    data: {
                        labels: txLabels,
                        datasets: [{
                            label: 'Transactions',
                            data: txData,
                            borderColor: '#0ac074',
                            backgroundColor: 'rgba(10, 192, 116, 0.12)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#0ac074',
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
                                        return ' ' + context.parsed.y + ' transactions';
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

            const labels = <?php echo json_encode($chartLabels, 15, 512) ?>;
            const data = <?php echo json_encode($chartData, 15, 512) ?>;
            const colors = <?php echo json_encode($chartColors, 15, 512) ?>.slice(0, labels.length);

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderColor: colors,
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                boxHeight: 12,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 12
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? (context.parsed / total * 100).toFixed(1) :
                                        0;
                                    return ' ' + context.label + ': ' + context.parsed + ' (' + pct +
                                        '%)';
                                }
                            }
                        }
                    },
                    cutout: '62%'
                }
            });
        });
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/dashboard.blade.php ENDPATH**/ ?>