<?php
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);

include  '../DBconnect.php';

date_default_timezone_set('Asia/Manila');
//date default
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

if (empty($startDate) || empty($endDate)) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate   = date('Y-m-d');
}

$daysRange = (strtotime($endDate) - strtotime($startDate)) / 86400;
$MAX_DAYS = 366;

if ($daysRange > $MAX_DAYS) {
    die("Date range too large. Please select up to 1 year only.");
}
if ($daysRange <= 31) {
    $salesGroupBy = "DATE(s.sale_date)";
    $salesDateFormat = "%Y-%m-%d";
} elseif ($daysRange <= 180) {
    $salesGroupBy = "YEAR(s.sale_date), MONTH(s.sale_date)";
    $salesDateFormat = "%Y-%m";
} else {
    $salesGroupBy = "YEAR(s.sale_date)";
    $salesDateFormat = "%Y";
}

$selectedLocation = isset($_GET['location']) ? $_GET['location'] : 'all';

$locationFilter = "";
if ($selectedLocation != 'all') {
    $locationFilter = " AND location = '" . mysqli_real_escape_string($conn, $selectedLocation) . "'";
}


//shows all stores 
$queryLocations = "SELECT DISTINCT location FROM store ORDER BY location";
$resultLocations = mysqli_query($conn, $queryLocations);
$locations = [];
while ($row = mysqli_fetch_assoc($resultLocations)) {
    $locations[] = $row['location'];
}
//for late
$queryAttendanceOverTime = "
    SELECT 
        DATE(date) as attendance_date,
        COUNT(*) as total_attendance,
        SUM(count_of_late) as total_late
    FROM attendance_summary
    WHERE date >= '$startDate' AND date <= '$endDate'
    $locationFilter
    GROUP BY DATE(date)
    ORDER BY attendance_date ASC
";
$resultAttendanceOverTime = mysqli_query($conn, $queryAttendanceOverTime);
$attendanceOverTimeData = [];
while ($row = mysqli_fetch_assoc($resultAttendanceOverTime)) {
    $attendanceOverTimeData[] = $row;
}

$queryTodayStats = "
    SELECT 
        COUNT(*) as total_attendance,
        SUM(count_of_late) as total_late,
        COUNT(DISTINCT user_id) as unique_employees
    FROM attendance_summary
    WHERE date = CURDATE()
    $locationFilter
";
$resultTodayStats = mysqli_query($conn, $queryTodayStats);
$todayStats = mysqli_fetch_assoc($resultTodayStats);

//month late stats
$queryMonthStats = "
    SELECT 
        COUNT(*) as total_attendance,
        SUM(count_of_late) as total_late,
        COUNT(DISTINCT user_id) as unique_employees
    FROM attendance_summary
    WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
    $locationFilter
";
$resultMonthStats = mysqli_query($conn, $queryMonthStats);
$monthStats = mysqli_fetch_assoc($resultMonthStats);

$datesJson = json_encode(array_column($attendanceOverTimeData, 'attendance_date'));
$totalAttendanceJson = json_encode(array_column($attendanceOverTimeData, 'total_attendance'));
$totalLateJson = json_encode(array_column($attendanceOverTimeData, 'total_late'));


$onTimeData = [];
foreach ($attendanceOverTimeData as $data) {
    $onTimeData[] = $data['total_attendance'] - $data['total_late'];
}
$onTimeJson = json_encode($onTimeData);


$locationFilterSales = "";
if ($selectedLocation != 'all') {
    $locationFilterSales = " AND st.location = '" . mysqli_real_escape_string($conn, $selectedLocation) . "'";
}

$querySalesOverTime = "
    SELECT 
        DATE_FORMAT(s.sale_date, '$salesDateFormat') AS sale_period,
        SUM(s.subtotal) AS daily_sales
    FROM sales s
    JOIN store st ON s.store_id = st.store_id
    WHERE s.is_deleted = 0
    AND s.sale_date >= '$startDate' AND s.sale_date <= '$endDate'
    $locationFilterSales
    GROUP BY DATE_FORMAT(s.sale_date, '$salesDateFormat')  
    ORDER BY sale_period ASC
";


$resultSalesOverTime = mysqli_query($conn, $querySalesOverTime);
$salesOverTimeData = [];
while ($row = mysqli_fetch_assoc($resultSalesOverTime)) {
    $salesOverTimeData[] = $row;
}

if (count($salesOverTimeData) > 120) {
    $salesOverTimeData = array_slice($salesOverTimeData, -120);
}

$queryProductQuantity = "
    SELECT 
        p.product_name,
        SUM(s.quantity) AS total_qty
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN store st ON s.store_id = st.store_id
    WHERE s.sale_date >= '$startDate' AND s.sale_date <= '$endDate'
    $locationFilterSales
    GROUP BY p.product_name
    ORDER BY total_qty DESC
    LIMIT 10
";
$resultProductQuantity = mysqli_query($conn, $queryProductQuantity);
$productQuantityData = [];
while ($row = mysqli_fetch_assoc($resultProductQuantity)) {
    $productQuantityData[] = $row;
}

$queryTodaySales = "
    SELECT 
        SUM(s.subtotal + IFNULL(sts.total_topping, 0)) AS today_sales
    FROM sales s
    JOIN store st ON s.store_id = st.store_id
    LEFT JOIN (
        SELECT stt.sale_id, SUM(t.price) AS total_topping
        FROM sale_toppings stt
        JOIN toppings t ON stt.topping_id = t.topping_id
        GROUP BY stt.sale_id
    ) sts ON s.sale_id = sts.sale_id
    WHERE DATE(s.sale_date) = CURDATE()
    $locationFilterSales
";
$resultTodaySales = mysqli_query($conn, $queryTodaySales);
$todaySales = mysqli_fetch_assoc($resultTodaySales);

$queryMonthSales = "
    SELECT 
        SUM(s.subtotal + IFNULL(sts.total_topping, 0)) AS month_sales
    FROM sales s
    JOIN store st ON s.store_id = st.store_id
    LEFT JOIN (
        SELECT stt.sale_id, SUM(t.price) AS total_topping
        FROM sale_toppings stt
        JOIN toppings t ON stt.topping_id = t.topping_id
        GROUP BY stt.sale_id
    ) sts ON s.sale_id = sts.sale_id
    WHERE MONTH(s.sale_date) = MONTH(CURDATE()) 
    AND YEAR(s.sale_date) = YEAR(CURDATE())
    $locationFilterSales
";
$resultMonthSales = mysqli_query($conn, $queryMonthSales);
$monthSales = mysqli_fetch_assoc($resultMonthSales);

$salesDatesJson = json_encode(array_column($salesOverTimeData, 'sale_period'));
$salesAmountJson = json_encode(array_column($salesOverTimeData, 'daily_sales'));

$productNamesJson = json_encode(array_column($productQuantityData, 'product_name'));
$productQuantitiesJson = json_encode(array_column($productQuantityData, 'total_qty'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Dashboard - Attendance & Sales</title>
    <link rel="stylesheet" href="../Design/forReports.css">
    <script src="../assets/js/chart.umd.js"></script>
    <script src="../assets/js/chartjs-plugin-datalabels.min.js"></script>
</head>
<body>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <span class="sidebar-logo-icon">📊</span>
            <span class="sidebar-logo-text">Analytics</span>
        </div>
        <nav class="sidebar-nav">
            <a href="#section-stats" class="sidebar-link active" data-section="stats">
                <span class="sidebar-icon">🏠</span>
                <span>Overview</span>
            </a>
            <a href="#section-attendance" class="sidebar-link" data-section="attendance">
                <span class="sidebar-icon">📅</span>
                <span>Attendance</span>
            </a>
            <a href="#section-sales" class="sidebar-link" data-section="sales">
                <span class="sidebar-icon">💰</span>
                <span>Sales</span>
            </a>
            <a href="#section-products" class="sidebar-link" data-section="products">
                <span class="sidebar-icon">📦</span>
                <span>Products</span>
            </a>
            <a href="#section-insights" class="sidebar-link" data-section="insights">
                <span class="sidebar-icon">💡</span>
                <span>Insights</span>
            </a>
        </nav>
       <div class="sidebar-footer">
    <a href="adminDashboard.php" class="sidebar-back-link" style="display: block; padding: 12px 16px; color: #fff; text-decoration: none; border-radius: 4px; background-color: #0d47a1; transition: all 0.3s ease; font-size: 14px; text-align: left; position: fixed; bottom: 20px; left: 20px; width: 140px; z-index: 100; box-sizing: border-box; border: 2px solid #1976d2; cursor: pointer;" onmouseover="this.style.backgroundColor='#1565c0'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.3)';" onmouseout="this.style.backgroundColor='#0d47a1'; this.style.boxShadow='none';">← Back to Admin</a>
</div>
    </aside>

    <div class="main-wrapper">

        <div class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">☰</button>
            <div class="topbar-title">
                <h1>📊 Live Dashboard</h1>
                <span class="topbar-sub"><?php echo date('l, F j, Y'); ?></span>
            </div>
            <div class="topbar-controls">
                <div class="filter-group">
                    <label for="locationFilter">📍</label>
                    <select id="locationFilter">
                        <option value="all" <?php echo $selectedLocation == 'all' ? 'selected' : ''; ?>>All Locations</option>
                        <?php foreach ($locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location); ?>"
                                <?php echo $selectedLocation == $location ? 'selected' : ''; ?>>
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
                <div class="last-update">Last Updated: <span id="lastUpdate"><?php echo date('g:i:s A'); ?></span></div>
            </div>
        </div>

        <div class="container">

            <section id="section-stats">

                <div class="kpi-banner-row">
                    <?php
                        $todayAtt  = $todayStats['total_attendance'] ?? 0;
                        $todayLate = $todayStats['total_late'] ?? 0;
                        $todayOnTime = $todayAtt - $todayLate;
                        $lateRate  = $todayAtt > 0 ? round(($todayLate / $todayAtt) * 100, 1) : 0;
                        $onTimeRate = $todayAtt > 0 ? round(($todayOnTime / $todayAtt) * 100, 1) : 0;
                        $todaySalesVal = $todaySales['today_sales'] ?? 0;
                        $monthSalesVal = $monthSales['month_sales'] ?? 0;

                        $totalSalesInRange = array_sum(array_column($salesOverTimeData, 'daily_sales'));
                        $salesDays = max(count($salesOverTimeData), 1);
                        $avgDailySales = $totalSalesInRange / $salesDays;

                        $totalAttRange = array_sum(array_column($attendanceOverTimeData, 'total_attendance'));
                        $totalLateRange = array_sum(array_column($attendanceOverTimeData, 'total_late'));
                        $rangeAttDays = max(count($attendanceOverTimeData), 1);
                        $avgDailyAtt = round($totalAttRange / $rangeAttDays, 1);
                    ?>
                    <div class="kpi-banner kpi-blue">
                        <div class="kpi-banner-icon">👥</div>
                        <div class="kpi-banner-body">
                            <div class="kpi-banner-label">Avg Daily Attendance</div>
                            <div class="kpi-banner-value"><?php echo $avgDailyAtt; ?></div>
                            <div class="kpi-banner-sub">over selected range</div>
                        </div>
                        <div class="kpi-banner-chart-mini">
                            <canvas id="sparkAtt"></canvas>
                        </div>
                    </div>
                    <div class="kpi-banner kpi-rose">
                        <div class="kpi-banner-icon">⏰</div>
                        <div class="kpi-banner-body">
                            <div class="kpi-banner-label">Today's Late Rate</div>
                            <div class="kpi-banner-value"><?php echo $lateRate; ?>%</div>
                            <div class="kpi-banner-sub"><?php echo $todayLate; ?> of <?php echo $todayAtt; ?> check-ins</div>
                        </div>
                        <div class="kpi-donut-wrap">
                            <canvas id="donutLate"></canvas>
                            <span class="kpi-donut-label"><?php echo $lateRate; ?>%</span>
                        </div>
                    </div>
                    <div class="kpi-banner kpi-emerald">
                        <div class="kpi-banner-icon">💰</div>
                        <div class="kpi-banner-body">
                            <div class="kpi-banner-label">Avg Daily Sales</div>
                            <div class="kpi-banner-value">₱<?php echo number_format($avgDailySales, 0); ?></div>
                            <div class="kpi-banner-sub">over selected range</div>
                        </div>
                        <div class="kpi-banner-chart-mini">
                            <canvas id="sparkSales"></canvas>
                        </div>
                    </div>
                    <div class="kpi-banner kpi-teal">
                        <div class="kpi-banner-icon">✅</div>
                        <div class="kpi-banner-body">
                            <div class="kpi-banner-label">On-Time Rate Today</div>
                            <div class="kpi-banner-value"><?php echo $onTimeRate; ?>%</div>
                            <div class="kpi-banner-sub"><?php echo $todayOnTime; ?> on time today</div>
                        </div>
                        <div class="kpi-donut-wrap">
                            <canvas id="donutOnTime"></canvas>
                            <span class="kpi-donut-label"><?php echo $onTimeRate; ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Today's Attendance</h3>
                        <div class="stat-value counter" data-target="<?php echo $todayStats['total_attendance'] ?? 0; ?>">0</div>
                        <div class="stat-label">Total check-ins</div>
                        <div class="stat-progress-bar"><div class="stat-progress-fill" style="width:<?php echo min(100, ($todayStats['total_attendance'] ?? 0)); ?>%"></div></div>
                    </div>
                    <div class="stat-card late">
                        <h3>Today's Late</h3>
                        <div class="stat-value counter" data-target="<?php echo $todayStats['total_late'] ?? 0; ?>">0</div>
                        <div class="stat-label">After 9:00 AM</div>
                        <div class="stat-progress-bar"><div class="stat-progress-fill late" style="width:<?php echo $lateRate; ?>%"></div></div>
                    </div>
                    <div class="stat-card">
                        <h3>This Month</h3>
                        <div class="stat-value counter" data-target="<?php echo $monthStats['total_attendance'] ?? 0; ?>">0</div>
                        <div class="stat-label">Total attendance</div>
                        <div class="stat-progress-bar"><div class="stat-progress-fill" style="width:75%"></div></div>
                    </div>
                    <div class="stat-card late">
                        <h3>Month's Late</h3>
                        <div class="stat-value counter" data-target="<?php echo $monthStats['total_late'] ?? 0; ?>">0</div>
                        <div class="stat-label">Late arrivals</div>
                        <div class="stat-progress-bar"><div class="stat-progress-fill late" style="width:<?php echo ($monthStats['total_attendance'] > 0 ? round(($monthStats['total_late']/$monthStats['total_attendance'])*100,1) : 0); ?>%"></div></div>
                    </div>
                    <div class="stat-card sales">
                        <h3>Today's Sales</h3>
                        <div class="stat-value">₱<span class="counter-float" data-target="<?php echo $todaySales['today_sales'] ?? 0; ?>">0.00</span></div>
                        <div class="stat-label">Total sales today</div>
                        <div class="stat-progress-bar"><div class="stat-progress-fill sales" style="width:60%"></div></div>
                    </div>
                    <div class="stat-card sales">
                        <h3>Month's Sales</h3>
                        <div class="stat-value">₱<span class="counter-float" data-target="<?php echo $monthSales['month_sales'] ?? 0; ?>">0.00</span></div>
                        <div class="stat-label">Total sales this month</div>
                        <div class="stat-progress-bar"><div class="stat-progress-fill sales" style="width:80%"></div></div>
                    </div>
                </div>
            </section>

            <div class="section-divider" id="section-attendance">
                <span class="section-divider-label">📅 Attendance Analytics</span>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>📈 Total Attendance</h2>
                        <div class="chart-type-toggle" id="toggleAttendance">
                            <button class="ct-btn active" onclick="switchChartType('totalAttendanceChart', 'line', this)">Line</button>
                            <button class="ct-btn" onclick="switchChartType('totalAttendanceChart', 'bar', this)">Bar</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="totalAttendanceChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>⏰ Late vs On-Time Attendance</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchStackedType('stacked', this)">Stacked</button>
                            <button class="ct-btn" onclick="switchStackedType('grouped', this)">Grouped</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="lateVsOnTimeChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="section-divider" id="section-sales">
                <span class="section-divider-label">💰 Sales Analytics</span>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>💰 Total Sales Over Time</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchChartType('salesOverTimeChart', 'line', this)">Line</button>
                            <button class="ct-btn" onclick="switchChartType('salesOverTimeChart', 'bar', this)">Bar</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesOverTimeChart"></canvas>
                    </div>
                </div>

                <div class="chart-card" id="section-products">
                    <div class="chart-card-header">
                        <h2>📊 Top 10 Products by Quantity Sold</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchChartType('productQuantityChart', 'bar', this)">Bar</button>
                            <button class="ct-btn" onclick="switchChartType('productQuantityChart', 'doughnut', this)">Donut</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="productQuantityChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="section-divider" id="section-insights">
                <span class="section-divider-label">💡 Smart Insights</span>
            </div>

            <div class="insights-grid">
                <?php
                    $totalSales      = array_sum(array_column($salesOverTimeData, 'daily_sales'));
                    $peakSales       = count($salesOverTimeData) ? max(array_column($salesOverTimeData, 'daily_sales')) : 0;
                    $peakSalesIdx    = count($salesOverTimeData) ? array_search($peakSales, array_column($salesOverTimeData, 'daily_sales')) : 0;
                    $peakSalesDate   = count($salesOverTimeData) ? $salesOverTimeData[$peakSalesIdx]['sale_period'] : 'N/A';

                    $peakAtt         = count($attendanceOverTimeData) ? max(array_column($attendanceOverTimeData, 'total_attendance')) : 0;
                    $peakAttIdx      = count($attendanceOverTimeData) ? array_search($peakAtt, array_column($attendanceOverTimeData, 'total_attendance')) : 0;
                    $peakAttDate     = count($attendanceOverTimeData) ? $attendanceOverTimeData[$peakAttIdx]['attendance_date'] : 'N/A';

                    $overallLateRate = $totalAttRange > 0 ? round(($totalLateRange / $totalAttRange) * 100, 1) : 0;

                    $topProduct      = count($productQuantityData) ? $productQuantityData[0]['product_name'] : 'N/A';
                    $topProductQty   = count($productQuantityData) ? $productQuantityData[0]['total_qty'] : 0;

                    $insights = [
                        [
                            'icon'  => '🏆',
                            'color' => 'emerald',
                            'title' => 'Best Sales Day',
                            'value' => '₱' . number_format($peakSales, 2),
                            'desc'  => 'Peak revenue recorded on <strong>' . htmlspecialchars($peakSalesDate) . '</strong> in the selected range.',
                        ],
                        [
                            'icon'  => '📅',
                            'color' => 'blue',
                            'title' => 'Peak Attendance Day',
                            'value' => $peakAtt . ' check-ins',
                            'desc'  => 'Highest attendance was on <strong>' . htmlspecialchars($peakAttDate) . '</strong>.',
                        ],
                        [
                            'icon'  => '⏰',
                            'color' => $overallLateRate > 20 ? 'rose' : 'teal',
                            'title' => 'Overall Late Rate',
                            'value' => $overallLateRate . '%',
                            'desc'  => $overallLateRate > 20
                                ? '<strong>High late rate</strong> — consider reviewing shift schedules.'
                                : '<strong>Good punctuality</strong> — team is arriving mostly on time.',
                        ],
                        [
                            'icon'  => '📦',
                            'color' => 'amber',
                            'title' => 'Best-Selling Product',
                            'value' => htmlspecialchars($topProduct),
                            'desc'  => '<strong>' . number_format($topProductQty) . ' units</strong> sold in the selected period.',
                        ],
                        [
                            'icon'  => '💵',
                            'color' => 'emerald',
                            'title' => 'Total Sales (Range)',
                            'value' => '₱' . number_format($totalSales, 2),
                            'desc'  => 'Across <strong>' . $salesDays . ' day(s)</strong> in the selected date range.',
                        ],
                        [
                            'icon'  => '👥',
                            'color' => 'blue',
                            'title' => 'Total Attendance (Range)',
                            'value' => number_format($totalAttRange),
                            'desc'  => 'Over <strong>' . $rangeAttDays . ' day(s)</strong> with <strong>' . number_format($totalLateRange) . '</strong> late arrivals.',
                        ],
                    ];
                ?>
                <?php foreach ($insights as $ins): ?>
                <div class="insight-card insight-<?php echo $ins['color']; ?>">
                    <div class="insight-icon"><?php echo $ins['icon']; ?></div>
                    <div class="insight-body">
                        <div class="insight-title"><?php echo $ins['title']; ?></div>
                        <div class="insight-value"><?php echo $ins['value']; ?></div>
                        <div class="insight-desc"><?php echo $ins['desc']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="dash-footer">
                <span>Auto-refreshes every <strong>5 minutes</strong></span>
                <span class="dash-footer-dot">·</span>
                <span>Data timezone: <strong>Asia/Manila</strong></span>
                <span class="dash-footer-dot">·</span>
                <span>Range: <strong><?php echo $startDate; ?></strong> → <strong><?php echo $endDate; ?></strong></span>
            </div>

        </div>
    </div>

    <div id="chartTooltip" class="chart-tooltip hidden"></div>

    <script>
    // redirect filters
    function applyFilters() {
        const location  = document.getElementById('locationFilter').value;
        const startDate = document.getElementById('startDate').value;
        const endDate   = document.getElementById('endDate').value;
        window.location.href = '?location=' + location + '&start_date=' + startDate + '&end_date=' + endDate;
    }

    // quick range
    function setRange(days) {
        const end   = new Date();
        const start = new Date();
        start.setDate(end.getDate() - days);
        document.getElementById('startDate').value = start.toISOString().slice(0, 10);
        document.getElementById('endDate').value   = end.toISOString().slice(0, 10);
        document.querySelectorAll('.qr-btn').forEach(b => b.classList.remove('active'));
        event.target.classList.add('active');
        applyFilters();
    }

    // auto-refresh
    setTimeout(() => location.reload(), 300000);

    // live clock
    setInterval(() => {
        const now = new Date();
        document.getElementById('lastUpdate').textContent =
            now.toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: true });
    }, 1000);

  
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    // track scroll
    const sections = ['section-stats','section-attendance','section-sales','section-products','section-insights'];
    window.addEventListener('scroll', () => {
        let current = sections[0];
        sections.forEach(id => {
            const el = document.getElementById(id);
            if (el && window.scrollY >= el.offsetTop - 120) current = id;
        });
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.classList.toggle('active', link.dataset.section === current.replace('section-',''));
        });
    });

    // animate integers
    function animateCounter(el) {
        const target = parseInt(el.dataset.target) || 0;
        const duration = 1200;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = Math.floor(current).toLocaleString();
            if (current >= target) clearInterval(timer);
        }, 16);
    }

    // animate decimals
    function animateCounterFloat(el) {
        const target = parseFloat(el.dataset.target) || 0;
        const duration = 1400;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (current >= target) clearInterval(timer);
        }, 16);
    }

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.querySelectorAll('.counter').forEach(animateCounter);
                entry.target.querySelectorAll('.counter-float').forEach(animateCounterFloat);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.stats-grid').forEach(el => observer.observe(el));

    const dates           = <?php echo $datesJson; ?>;
    const totalAttendance = <?php echo $totalAttendanceJson; ?>;
    const totalLate       = <?php echo $totalLateJson; ?>;
    const onTime          = <?php echo $onTimeJson; ?>;
    const salesDates      = <?php echo $salesDatesJson; ?>;
    const salesAmounts    = <?php echo $salesAmountJson; ?>;
    const productNames    = <?php echo $productNamesJson; ?>;
    const productQtys     = <?php echo $productQuantitiesJson; ?>;

    const PALETTE = ['#0ea5e9','#06b6d4','#6366f1','#f43f5e','#f59e0b','#10b981','#8b5cf6','#ec4899','#14b8a6','#84cc16'];

    const chartRegistry = {};

    //create chart
    function buildChart(id, config) {
        if (chartRegistry[id]) chartRegistry[id].destroy();
        chartRegistry[id] = new Chart(document.getElementById(id), config);
        return chartRegistry[id];
    }

    //swap chart
    function switchChartType(chartId, newType, btn) {
        const chart = chartRegistry[chartId];
        if (!chart) return;
        btn.closest('.chart-type-toggle').querySelectorAll('.ct-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const datasets = chart.data.datasets;
        const labels   = chart.data.labels;

        if (chartId === 'productQuantityChart') {
            buildProductChart(newType);
            return;
        }

        chart.config.type = newType;
        datasets.forEach(ds => {
            if (newType === 'bar') {
                ds.fill = false;
                ds.borderRadius = 6;
            } else {
                ds.fill = ds._origFill !== undefined ? ds._origFill : false;
                ds.borderRadius = 0;
            }
        });
        chart.update();
    }

    // toggle stacking
    function switchStackedType(mode, btn) {
        const chart = chartRegistry['lateVsOnTimeChart'];
        if (!chart) return;
        btn.closest('.chart-type-toggle').querySelectorAll('.ct-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const stacked = mode === 'stacked';
        chart.options.scales.x.stacked = stacked;
        chart.options.scales.y.stacked = stacked;
        chart.update();
    }

    //mini sparkline
    function buildSparkline(id, data, color) {
        new Chart(document.getElementById(id), {
            type: 'line',
            data: {
                labels: data.map((_, i) => i),
                datasets: [{ data, borderColor: color, backgroundColor: color + '33', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, datalabels: { display: false } },
                scales: { x: { display: false }, y: { display: false } },
                animation: { duration: 1000 }
            },
            plugins: [ChartDataLabels]
        });
    }
    buildSparkline('sparkAtt',   totalAttendance, '#0ea5e9');
    buildSparkline('sparkSales', salesAmounts,    '#10b981');

    // circular percentage
    function buildDonut(id, pct, color, trackColor) {
        new Chart(document.getElementById(id), {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [pct, 100 - pct],
                    backgroundColor: [color, trackColor || '#e0f2fe'],
                    borderWidth: 0,
                    hoverOffset: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '78%',
                plugins: { legend: { display: false }, tooltip: { enabled: false }, datalabels: { display: false } },
                animation: { animateRotate: true, duration: 1200 }
            },
            plugins: [ChartDataLabels]
        });
    }
    buildDonut('donutLate',   <?php echo $lateRate; ?>,   '#f43f5e', '#fee2e2');
    buildDonut('donutOnTime', <?php echo $onTimeRate; ?>, '#0ea5e9', '#e0f2fe');


    //total Attendance
    buildChart('totalAttendanceChart', {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Total Attendance',
                data: totalAttendance,
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14,165,233,0.10)',
                fill: true, tension: 0.4, borderWidth: 3,
                pointBackgroundColor: '#0ea5e9',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                _origFill: true
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top' },
                datalabels: {
                    anchor: 'end', align: 'top', color: '#0369a1',
                    font: { weight: 'bold', size: 11 },
                    formatter: v => v
                }
            },
            scales: {
                x: { grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', font: { size: 11 } } },
                y: { beginAtZero: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { stepSize: 1, color: '#0369a1' } }
            }
        },
        plugins: [ChartDataLabels]
    });

    buildChart('lateVsOnTimeChart', {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'On-Time', data: onTime,
                    backgroundColor: '#10b981', borderRadius: 5, _origFill: false
                },
                {
                    label: 'Late', data: totalLate,
                    backgroundColor: '#f43f5e', borderRadius: 5, _origFill: false
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top' },
                datalabels: {
                    anchor: 'center', align: 'center', color: 'white',
                    font: { weight: 'bold', size: 11 },
                    formatter: v => v > 0 ? v : ''
                }
            },
            scales: {
                x: { stacked: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', font: { size: 11 } } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { stepSize: 1, color: '#0369a1' } }
            }
        },
        plugins: [ChartDataLabels]
    });

    buildChart('salesOverTimeChart', {
        type: 'line',
        data: {
            labels: salesDates,
            datasets: [{
                label: 'Sales (₱)', data: salesAmounts,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.10)',
                fill: true, tension: 0.4, borderWidth: 3,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                _origFill: true
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top' },
                datalabels: {
                    anchor: 'end', align: 'top', color: '#059669',
                    font: { weight: 'bold', size: 10 },
                    formatter: v => '₱' + Number(v).toLocaleString()
                }
            },
            scales: {
                x: { grid: { color: 'rgba(16,185,129,0.08)' }, ticks: { color: '#059669', font: { size: 11 } } },
                y: { beginAtZero: true, grid: { color: 'rgba(16,185,129,0.08)' }, ticks: { color: '#059669', callback: v => '₱' + v.toLocaleString() } }
            }
        },
        plugins: [ChartDataLabels]
    });

    // render products
    function buildProductChart(type) {
        const isDonut = type === 'doughnut';
        buildChart('productQuantityChart', {
            type: type,
            data: {
                labels: productNames,
                datasets: [{
                    label: 'Qty Sold',
                    data: productQtys,
                    backgroundColor: PALETTE,
                    borderRadius: isDonut ? 0 : 5,
                    borderWidth: isDonut ? 2 : 0,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: isDonut ? undefined : 'y',
                plugins: {
                    legend: { display: isDonut, position: 'right', labels: { font: { size: 11 } } },
                    datalabels: isDonut ? {
                        color: '#fff',
                        font: { weight: 'bold', size: 11 },
                        formatter: (v, ctx) => ctx.chart.data.labels[ctx.dataIndex].substring(0, 8) + '\n' + v
                    } : {
                        anchor: 'end', align: 'right',
                        color: '#333', font: { weight: 'bold', size: 11 },
                        formatter: v => v
                    }
                },
                scales: isDonut ? {} : {
                    x: { beginAtZero: true, ticks: { stepSize: 1, color: '#0369a1' }, grid: { color: 'rgba(14,165,233,0.08)' } },
                    y: { ticks: { color: '#0369a1', font: { size: 11 } }, grid: { color: 'rgba(14,165,233,0.08)' } }
                }
            },
            plugins: [ChartDataLabels]
        });
    }
    buildProductChart('bar');

    </script>
</body>
</html>