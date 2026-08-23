<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
if (empty($startDate) || empty($endDate)) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate   = date('Y-m-d');
}

$daysRange = (strtotime($endDate) - strtotime($startDate)) / 86400;
if ($daysRange > 366) {
    die("Date range too large. Please select up to 1 year only.");
}
if ($daysRange <= 31) {
    $dateFormat = "%Y-%m-%d";
} elseif ($daysRange <= 180) {
    $dateFormat = "%Y-%m";
} else {
    $dateFormat = "%Y";
}

$selectedLocation = $_GET['location'] ?? 'all';
$locationFilter = "";
if ($selectedLocation !== 'all') {
    $locationFilter = " AND st.location = '" . mysqli_real_escape_string($conn, $selectedLocation) . "'";
}

$queryLocations = "SELECT DISTINCT location FROM store ORDER BY location";
$resultLocations = mysqli_query($conn, $queryLocations);
$locations = [];
while ($row = mysqli_fetch_assoc($resultLocations)) {
    $locations[] = $row['location'];
}

/* ── Sales over time + discount impact (same source query, two charts) ── */
$querySalesOverTime = "
    SELECT
        DATE_FORMAT(o.order_date, '$dateFormat') AS period,
        SUM(o.total_amount) AS net_sales,
        SUM(o.total_amount + o.discount_amount) AS gross_sales,
        SUM(o.discount_amount) AS discount_amt
    FROM orders o
    JOIN store st ON o.store_id = st.store_id
    WHERE o.order_date >= '$startDate' AND o.order_date <= '$endDate'
    $locationFilter
    GROUP BY DATE_FORMAT(o.order_date, '$dateFormat')
    ORDER BY period ASC
";
$resultSalesOverTime = mysqli_query($conn, $querySalesOverTime);
$salesOverTimeData = [];
while ($row = mysqli_fetch_assoc($resultSalesOverTime)) {
    $salesOverTimeData[] = $row;
}
if (count($salesOverTimeData) > 120) {
    $salesOverTimeData = array_slice($salesOverTimeData, -120);
}

/* ── Sales by store ── */
$querySalesByStore = "
    SELECT st.location AS store_location, SUM(o.total_amount) AS store_sales, COUNT(*) AS order_count
    FROM orders o
    JOIN store st ON o.store_id = st.store_id
    WHERE o.order_date >= '$startDate' AND o.order_date <= '$endDate'
    $locationFilter
    GROUP BY st.location
    ORDER BY store_sales DESC
";
$resultSalesByStore = mysqli_query($conn, $querySalesByStore);
$salesByStoreData = [];
while ($row = mysqli_fetch_assoc($resultSalesByStore)) {
    $salesByStoreData[] = $row;
}

/* ── Discount type breakdown ── */
$queryDiscountType = "
    SELECT COALESCE(o.discount_type, 'No Discount') AS dtype,
           COUNT(*) AS order_count,
           SUM(o.discount_amount) AS total_discount
    FROM orders o
    JOIN store st ON o.store_id = st.store_id
    WHERE o.order_date >= '$startDate' AND o.order_date <= '$endDate'
    $locationFilter
    GROUP BY dtype
";
$resultDiscountType = mysqli_query($conn, $queryDiscountType);
$discountTypeData = [];
while ($row = mysqli_fetch_assoc($resultDiscountType)) {
    $discountTypeData[] = $row;
}

/* ── KPI: today / month ── */
$queryTodaySales = "
    SELECT SUM(o.total_amount) AS today_sales, COUNT(*) AS today_orders
    FROM orders o JOIN store st ON o.store_id = st.store_id
    WHERE DATE(o.order_date) = CURDATE() $locationFilter
";
$todaySales = mysqli_fetch_assoc(mysqli_query($conn, $queryTodaySales));

$queryMonthSales = "
    SELECT SUM(o.total_amount) AS month_sales, COUNT(*) AS month_orders
    FROM orders o JOIN store st ON o.store_id = st.store_id
    WHERE MONTH(o.order_date) = MONTH(CURDATE()) AND YEAR(o.order_date) = YEAR(CURDATE())
    $locationFilter
";
$monthSales = mysqli_fetch_assoc(mysqli_query($conn, $queryMonthSales));

/* ── KPI: discounts + AOV in range ── */
$queryRangeStats = "
    SELECT
        COUNT(*) AS order_count,
        SUM(o.total_amount) AS net_revenue,
        SUM(o.total_amount + o.discount_amount) AS gross_revenue,
        SUM(o.discount_amount) AS total_discount,
        SUM(CASE WHEN o.discount_amount > 0 THEN 1 ELSE 0 END) AS discount_orders,
        AVG(o.total_amount) AS avg_order_value
    FROM orders o
    JOIN store st ON o.store_id = st.store_id
    WHERE o.order_date >= '$startDate' AND o.order_date <= '$endDate'
    $locationFilter
";
$rangeStats = mysqli_fetch_assoc(mysqli_query($conn, $queryRangeStats));

$netRevenue     = (float)($rangeStats['net_revenue'] ?? 0);
$grossRevenue   = (float)($rangeStats['gross_revenue'] ?? 0);
$totalDiscount  = (float)($rangeStats['total_discount'] ?? 0);
$discountOrders = (int)($rangeStats['discount_orders'] ?? 0);
$orderCount     = (int)($rangeStats['order_count'] ?? 0);
$avgOrderValue  = (float)($rangeStats['avg_order_value'] ?? 0);
$discountRate   = $grossRevenue > 0 ? round(($totalDiscount / $grossRevenue) * 100, 1) : 0;

$periodsJson  = json_encode(array_column($salesOverTimeData, 'period'));
$netJson      = json_encode(array_map('floatval', array_column($salesOverTimeData, 'net_sales')));
$grossJson    = json_encode(array_map('floatval', array_column($salesOverTimeData, 'gross_sales')));
$discAmtJson  = json_encode(array_map('floatval', array_column($salesOverTimeData, 'discount_amt')));

$storeNamesJson = json_encode(array_column($salesByStoreData, 'store_location'));
$storeSalesJson = json_encode(array_map('floatval', array_column($salesByStoreData, 'store_sales')));

$discTypeLabelsJson = json_encode(array_column($discountTypeData, 'dtype'));
$discTypeAmtJson    = json_encode(array_map('floatval', array_column($discountTypeData, 'total_discount')));

/* ── Insights ── */
$peakPeriodSales = 0; $peakPeriod = 'N/A';
foreach ($salesOverTimeData as $row) {
    if ((float)$row['net_sales'] > $peakPeriodSales) {
        $peakPeriodSales = (float)$row['net_sales'];
        $peakPeriod = $row['period'];
    }
}
$topStore = count($salesByStoreData) ? $salesByStoreData[0]['store_location'] : 'N/A';
$topStoreSales = count($salesByStoreData) ? (float)$salesByStoreData[0]['store_sales'] : 0;

$insights = [
    [
        'icon' => '💵', 'color' => 'emerald', 'title' => 'Net Revenue (Range)',
        'value' => '₱' . number_format($netRevenue, 2),
        'desc' => 'Across <strong>' . $orderCount . ' order(s)</strong> from ' . htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate) . '.',
    ],
    [
        'icon' => '🏷️', 'color' => $discountRate > 10 ? 'rose' : 'teal', 'title' => 'Discount Impact',
        'value' => $discountRate . '% of gross',
        'desc' => '₱' . number_format($totalDiscount, 2) . ' given back across <strong>' . $discountOrders . ' discounted order(s)</strong>.',
    ],
    [
        'icon' => '🏪', 'color' => 'blue', 'title' => 'Top-Performing Store',
        'value' => htmlspecialchars($topStore),
        'desc' => '₱' . number_format($topStoreSales, 2) . ' in net sales this range — the highest of all locations.',
    ],
    [
        'icon' => '📈', 'color' => 'amber', 'title' => 'Best Sales Period',
        'value' => htmlspecialchars($peakPeriod),
        'desc' => 'Highest single-period net revenue: <strong>₱' . number_format($peakPeriodSales, 2) . '</strong>.',
    ],
    [
        'icon' => '🧾', 'color' => 'blue', 'title' => 'Average Order Value',
        'value' => '₱' . number_format($avgOrderValue, 2),
        'desc' => 'Mean net total per order across the selected range.',
    ],
];

/* ── Recommendations ── */
$recommendations = [];

if ($discountRate > 15) {
    $recommendations[] = [
        'icon' => '⚠️', 'color' => 'rose', 'title' => 'Audit Discount Usage',
        'value' => $discountRate . '% of gross',
        'desc' => 'Discounts are taking a notably large bite out of revenue this range. Spot-check ID number entries and confirm the 12% rate is being applied only to eligible Senior/PWD transactions.',
    ];
} else {
    $recommendations[] = [
        'icon' => '✅', 'color' => 'teal', 'title' => 'Discount Usage Looks Healthy',
        'value' => $discountRate . '% of gross',
        'desc' => 'Discount impact on revenue is within a normal range. No corrective action needed right now.',
    ];
}

if (count($salesByStoreData) > 1) {
    $lowestStore = end($salesByStoreData);
    $recommendations[] = [
        'icon' => '🏪', 'color' => 'amber', 'title' => 'Look Into Underperforming Store',
        'value' => htmlspecialchars($lowestStore['store_location']),
        'desc' => 'This location trails the others in net sales (₱' . number_format($lowestStore['store_sales'], 2) . '). Consider comparing staffing, foot traffic, or promo visibility against <strong>' . htmlspecialchars($topStore) . '</strong>.',
    ];
}

if ($avgOrderValue > 0 && $avgOrderValue < 60) {
    $recommendations[] = [
        'icon' => '🍦', 'color' => 'blue', 'title' => 'Consider an Upsell Prompt',
        'value' => '₱' . number_format($avgOrderValue, 2),
        'desc' => 'Average order value is on the lower side. A cashier prompt to add a topping or size up could lift this without adding new menu items.',
    ];
}

if ($peakPeriod !== 'N/A') {
    $recommendations[] = [
        'icon' => '🗓️', 'color' => 'emerald', 'title' => 'Replicate Your Best Period',
        'value' => htmlspecialchars($peakPeriod),
        'desc' => 'Revenue peaked around <strong>' . htmlspecialchars($peakPeriod) . '</strong>. Check what was different that day/period (staffing, promo, weather) and try to repeat it.',
    ];
}

if (empty($recommendations)) {
    $recommendations[] = [
        'icon' => '💡', 'color' => 'blue', 'title' => 'Not Enough Data Yet',
        'value' => '—',
        'desc' => 'Keep recording sales — recommendations will appear once there is enough activity in the selected range.',
    ];
}

$activeSection = 'sales';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard - Mr. Softy</title>
    <link rel="stylesheet" href="../Design/forReports.css">
    <script src="../assets/js/chart.umd.js"></script>
    <script src="../assets/js/chartjs-plugin-datalabels.min.js"></script>
</head>
<body>

    <?php include 'dashboardSidebar.php'; ?>

    <div class="main-wrapper">
        <div class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">☰</button>
            <div class="topbar-title">
                <h1>💰 Sales Dashboard</h1>
                <span class="topbar-sub"><?php echo date('l, F j, Y'); ?></span>
            </div>
            <div class="topbar-controls">
                <div class="filter-group">
                    <label for="locationFilter">📍</label>
                    <select id="locationFilter">
                        <option value="all" <?php echo $selectedLocation == 'all' ? 'selected' : ''; ?>>All Locations</option>
                        <?php foreach ($locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $selectedLocation == $location ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="startDate">From</label>
                    <input type="date" id="startDate" value="<?php echo $startDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filter-group">
                    <label for="endDate">To</label>
                    <input type="date" id="endDate" value="<?php echo $endDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <button class="refresh-btn apply-btn" onclick="applyFilters()">Apply</button>
                <button class="refresh-btn" onclick="location.reload()">🔄</button>
                <div class="last-update">Updated: <span id="lastUpdate"><?php echo date('g:i:s A'); ?></span></div>
            </div>
        </div>

        <div class="container">

            <div class="kpi-banner-row">
                <div class="kpi-banner kpi-emerald">
                    <div class="kpi-banner-icon">💰</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Today's Sales</div>
                        <div class="kpi-banner-value">₱<?php echo number_format($todaySales['today_sales'] ?? 0, 2); ?></div>
                        <div class="kpi-banner-sub"><?php echo (int)($todaySales['today_orders'] ?? 0); ?> order(s) today</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-blue">
                    <div class="kpi-banner-icon">📅</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Month's Sales</div>
                        <div class="kpi-banner-value">₱<?php echo number_format($monthSales['month_sales'] ?? 0, 2); ?></div>
                        <div class="kpi-banner-sub"><?php echo (int)($monthSales['month_orders'] ?? 0); ?> order(s) this month</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-rose">
                    <div class="kpi-banner-icon">🏷️</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Discounts Given (Range)</div>
                        <div class="kpi-banner-value">₱<?php echo number_format($totalDiscount, 2); ?></div>
                        <div class="kpi-banner-sub"><?php echo $discountOrders; ?> discounted order(s)</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-teal">
                    <div class="kpi-banner-icon">🧾</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Avg Order Value (Range)</div>
                        <div class="kpi-banner-value">₱<?php echo number_format($avgOrderValue, 2); ?></div>
                        <div class="kpi-banner-sub"><?php echo $orderCount; ?> order(s) in range</div>
                    </div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>🏷️ Discount Impact on Revenue</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchStacked('discountImpactChart','stacked',this)">Stacked</button>
                            <button class="ct-btn" onclick="switchStacked('discountImpactChart','grouped',this)">Grouped</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="discountImpactChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>💰 Sales Over Time</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchChartType('salesOverTimeChart', 'line', this)">Line</button>
                            <button class="ct-btn" onclick="switchChartType('salesOverTimeChart', 'bar', this)">Bar</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesOverTimeChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>🏪 Sales by Store</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesByStoreChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>🆔 Discount Type Breakdown</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="discountTypeChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="section-divider"><span class="section-divider-label">💡 Smart Insights</span></div>
            <div class="insights-grid">
                <?php foreach ($insights as $ins): ?>
                <div class="insight-card insight-<?php echo $ins['color']; ?>">
                    <div class="insight-icon"><?php echo $ins['icon']; ?></div>
                    <div class="insight-body">
                        <div class="insight-title"><?php echo htmlspecialchars($ins['title']); ?></div>
                        <div class="insight-value"><?php echo $ins['value']; ?></div>
                        <div class="insight-desc"><?php echo $ins['desc']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="section-divider"><span class="section-divider-label">🎯 Recommendations</span></div>
            <div class="insights-grid">
                <?php foreach ($recommendations as $rec): ?>
                <div class="insight-card insight-<?php echo $rec['color']; ?>">
                    <div class="insight-icon"><?php echo $rec['icon']; ?></div>
                    <div class="insight-body">
                        <div class="insight-title"><?php echo htmlspecialchars($rec['title']); ?></div>
                        <div class="insight-value"><?php echo $rec['value']; ?></div>
                        <div class="insight-desc"><?php echo $rec['desc']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="dash-footer">
                <span>Range: <strong><?php echo $startDate; ?></strong> → <strong><?php echo $endDate; ?></strong></span>
                <span class="dash-footer-dot">·</span>
                <span>Data timezone: <strong>Asia/Manila</strong></span>
            </div>

        </div>
    </div>

    <script>
    function applyFilters() {
        const location  = document.getElementById('locationFilter').value;
        const startDate = document.getElementById('startDate').value;
        const endDate   = document.getElementById('endDate').value;
        window.location.href = '?location=' + location + '&start_date=' + startDate + '&end_date=' + endDate;
    }
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
    setInterval(() => {
        document.getElementById('lastUpdate').textContent =
            new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: true });
    }, 1000);
    setTimeout(() => location.reload(), 300000);

    const periods   = <?php echo $periodsJson; ?>;
    const netSales  = <?php echo $netJson; ?>;
    const grossSales= <?php echo $grossJson; ?>;
    const discAmt   = <?php echo $discAmtJson; ?>;
    const storeNames= <?php echo $storeNamesJson; ?>;
    const storeSales= <?php echo $storeSalesJson; ?>;
    const discLabels= <?php echo $discTypeLabelsJson; ?>;
    const discAmts  = <?php echo $discTypeAmtJson; ?>;

    const PALETTE = ['#0ea5e9','#06b6d4','#6366f1','#f43f5e','#f59e0b','#10b981','#8b5cf6','#ec4899'];
    const chartRegistry = {};
    function buildChart(id, config) {
        if (chartRegistry[id]) chartRegistry[id].destroy();
        chartRegistry[id] = new Chart(document.getElementById(id), config);
        return chartRegistry[id];
    }
    function switchChartType(chartId, newType, btn) {
        const chart = chartRegistry[chartId];
        if (!chart) return;
        btn.closest('.chart-type-toggle').querySelectorAll('.ct-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        chart.config.type = newType;
        chart.data.datasets.forEach(ds => {
            ds.fill = newType === 'bar' ? false : (ds._origFill ?? false);
            ds.borderRadius = newType === 'bar' ? 6 : 0;
        });
        chart.update();
    }
    function switchStacked(chartId, mode, btn) {
        const chart = chartRegistry[chartId];
        if (!chart) return;
        btn.closest('.chart-type-toggle').querySelectorAll('.ct-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const stacked = mode === 'stacked';
        chart.options.scales.x.stacked = stacked;
        chart.options.scales.y.stacked = stacked;
        chart.update();
    }

    // Chart 1: Discount Impact — Net Sales stacked with Discount Amount = Gross Revenue
    buildChart('discountImpactChart', {
        type: 'bar',
        data: {
            labels: periods,
            datasets: [
                { label: 'Net Revenue (₱)', data: netSales, backgroundColor: '#10b981', borderRadius: 5 },
                { label: 'Discount Taken (₱)', data: discAmt, backgroundColor: '#f43f5e', borderRadius: 5 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top' },
                datalabels: { display: false },
                tooltip: {
                    callbacks: {
                        footer: (items) => {
                            const i = items[0].dataIndex;
                            return 'Gross: ₱' + Number(grossSales[i]).toLocaleString(undefined, {minimumFractionDigits:2});
                        }
                    }
                }
            },
            scales: {
                x: { stacked: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', font: { size: 11 } } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', callback: v => '₱' + v.toLocaleString() } }
            }
        }
    });

    // Chart 2: Sales Over Time (net)
    buildChart('salesOverTimeChart', {
        type: 'line',
        data: {
            labels: periods,
            datasets: [{
                label: 'Net Sales (₱)', data: netSales,
                borderColor: '#0ea5e9', backgroundColor: 'rgba(14,165,233,0.10)',
                fill: true, tension: 0.4, borderWidth: 3,
                pointBackgroundColor: '#0ea5e9', pointBorderColor: '#fff', pointBorderWidth: 2,
                pointRadius: 5, pointHoverRadius: 7, _origFill: true
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top' },
                datalabels: {
                    anchor: 'end', align: 'top', color: '#0369a1', font: { weight: 'bold', size: 10 },
                    formatter: v => '₱' + Number(v).toLocaleString()
                }
            },
            scales: {
                x: { grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', font: { size: 11 } } },
                y: { beginAtZero: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', callback: v => '₱' + v.toLocaleString() } }
            }
        },
        plugins: [ChartDataLabels]
    });

    // Chart 3: Sales by Store
    buildChart('salesByStoreChart', {
        type: 'bar',
        data: {
            labels: storeNames,
            datasets: [{ label: 'Net Sales (₱)', data: storeSales, backgroundColor: PALETTE, borderRadius: 6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                datalabels: {
                    anchor: 'end', align: 'right', color: '#333', font: { weight: 'bold', size: 11 },
                    formatter: v => '₱' + Number(v).toLocaleString()
                }
            },
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', callback: v => '₱' + v.toLocaleString() } },
                y: { grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', font: { size: 11 } } }
            }
        },
        plugins: [ChartDataLabels]
    });

    // Chart 4: Discount Type Breakdown
    buildChart('discountTypeChart', {
        type: 'doughnut',
        data: {
            labels: discLabels,
            datasets: [{ data: discAmts, backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'right', labels: { font: { size: 11 } } },
                datalabels: {
                    color: '#fff', font: { weight: 'bold', size: 11 },
                    formatter: v => '₱' + Number(v).toLocaleString()
                }
            }
        },
        plugins: [ChartDataLabels]
    });
    </script>
</body>
</html>