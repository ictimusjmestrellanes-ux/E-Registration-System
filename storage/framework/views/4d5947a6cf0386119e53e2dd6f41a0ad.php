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
        </div>
        <!--end welcome row-->

        <div class="row">
            <!-- Left: Main Content -->
            <div class="col-lg-8">
                <!-- Stat Cards -->
                <div class="row">
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
                <!--end stat cards row-->

                <!-- Client Trend Chart -->
                <div class="row mt-4">
                    <div class="col-12">
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

                <!-- Transaction Trend + Service Category Charts -->
                <div class="row mt-4">
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
            </div>
            <!--end col-lg-8 main content-->

            <!-- Right Sidebar: Clock & Calendar -->
            <div class="col-lg-4">
                <!-- Analog Clock Card -->
                <div class="card material-shadow">
                    <div class="card-body p-4">
                        <h6 class="text-muted text-uppercase fw-semibold mb-3 text-center">
                            <i class="ri-time-line me-1"></i> Clock
                        </h6>
                        <div class="d-flex justify-content-center">
                            <canvas id="dashAnalogClock" width="200" height="200" style="max-width: 100%;"></canvas>
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
                <div class="card material-shadow">
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
                                        <th>Su</th><th>Mo</th><th>Tu</th><th>We</th><th>Th</th><th>Fr</th><th>Sa</th>
                                    </tr>
                                </thead>
                                <tbody id="dashCalBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!--end calendar card-->
            </div>
            <!--end col-lg-4 right sidebar-->
        </div>

        <div class="row mt-4" id="service-categories">
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

    <!-- Dashboard Analog Clock & Calendar -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // ── Analog Clock ─────────────────────────────────────────────
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
                        cCtx.arc(Math.cos(ang) * (radius - 12), Math.sin(ang) * (radius - 12), 1, 0, 2 * Math.PI);
                        cCtx.fillStyle = '#adb5bd';
                        cCtx.fill();
                    }
                }

                // Hour numbers
                cCtx.font = 'bold 13px sans-serif';
                cCtx.fillStyle = '#405189';
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
                drawHand(mAngle, radius * 0.7, 3, '#0ab39c');

                // Second hand
                const sAngle = (s * Math.PI) / 30 - Math.PI / 2;
                drawHand(sAngle, radius * 0.78, 1.5, '#f06548');

                // Center dot
                cCtx.beginPath();
                cCtx.arc(0, 0, 4, 0, 2 * Math.PI);
                cCtx.fillStyle = '#405189';
                cCtx.fill();

                cCtx.restore();

                // Digital readout
                if (digitalClock) {
                    digitalClock.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
                if (digitalDate) {
                    digitalDate.textContent = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                }
            }

            drawClock();
            setInterval(drawClock, 1000);
        }

        // ── Calendar ─────────────────────────────────────────────────
        const calBody = document.getElementById('dashCalBody');
        const calMonthYear = document.getElementById('dashCalMonthYear');
        const calPrev = document.getElementById('dashCalPrev');
        const calNext = document.getElementById('dashCalNext');

        if (calBody && calMonthYear && calPrev && calNext) {
            const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
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
                    td.style.padding = '4px 0';

                    const isToday = d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                    if (isToday) {
                        td.style.background = '#405189';
                        td.style.color = '#fff';
                        td.style.fontWeight = 'bold';
                        td.style.borderRadius = '50%';
                    }

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

            calPrev.addEventListener('click', function () {
                calDate.setMonth(calDate.getMonth() - 1);
                renderCalendar();
            });

            calNext.addEventListener('click', function () {
                calDate.setMonth(calDate.getMonth() + 1);
                renderCalendar();
            });

            renderCalendar();
        }
    });
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/dashboard.blade.php ENDPATH**/ ?>