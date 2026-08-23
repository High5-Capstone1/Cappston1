<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', 'MrSoftyCapstone2025SecureKey!@#$');
    define('ENCRYPTION_METHOD', 'AES-256-CBC');
}

function decryptData($data) {
    if (empty($data)) return $data;
    $data = base64_decode($data);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return $data;
    list($iv, $encrypted) = $parts;
    $result = openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return $result !== false ? $result : $data;
}

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

$selectedLocation = $_GET['location'] ?? 'all';
$locationFilter = "";
if ($selectedLocation !== 'all') {
    $locationFilter = " AND location = '" . mysqli_real_escape_string($conn, $selectedLocation) . "'";
}

$queryLocations = "SELECT DISTINCT location FROM store ORDER BY location";
$resultLocations = mysqli_query($conn, $queryLocations);
$locations = [];
while ($row = mysqli_fetch_assoc($resultLocations)) {
    $locations[] = $row['location'];
}

/* ── Attendance over time (same source used previously in reports.php) ── */
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
    SELECT COUNT(*) as total_attendance, SUM(count_of_late) as total_late, COUNT(DISTINCT user_id) as unique_employees
    FROM attendance_summary
    WHERE date = CURDATE()
    $locationFilter
";
$todayStats = mysqli_fetch_assoc(mysqli_query($conn, $queryTodayStats));

$queryMonthStats = "
    SELECT COUNT(*) as total_attendance, SUM(count_of_late) as total_late, COUNT(DISTINCT user_id) as unique_employees
    FROM attendance_summary
    WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
    $locationFilter
";
$monthStats = mysqli_fetch_assoc(mysqli_query($conn, $queryMonthStats));

/* ── Top late employees (leaderboard) ── */
$queryTopLate = "
    SELECT
        user_id,
        MAX(employee_name) AS employee_name,
        MAX(location) AS location,
        MAX(role) AS role,
        SUM(count_of_late) AS total_late,
        COUNT(*) AS days_recorded
    FROM attendance_summary
    WHERE date >= '$startDate' AND date <= '$endDate'
    $locationFilter
    GROUP BY user_id
    HAVING total_late > 0
    ORDER BY total_late DESC
    LIMIT 10
";
$resultTopLate = mysqli_query($conn, $queryTopLate);
$topLateData = [];
while ($row = mysqli_fetch_assoc($resultTopLate)) {
    $row['employee_name'] = decryptData($row['employee_name']);
    $topLateData[] = $row;
}

$datesJson           = json_encode(array_column($attendanceOverTimeData, 'attendance_date'));
$totalAttendanceJson = json_encode(array_map('intval', array_column($attendanceOverTimeData, 'total_attendance')));
$totalLateJson       = json_encode(array_map('intval', array_column($attendanceOverTimeData, 'total_late')));

$onTimeData = [];
$onTimeRateData = [];
foreach ($attendanceOverTimeData as $data) {
    $onTime = (int)$data['total_attendance'] - (int)$data['total_late'];
    $onTimeData[] = $onTime;
    $onTimeRateData[] = $data['total_attendance'] > 0 ? round(($onTime / $data['total_attendance']) * 100, 1) : 0;
}
$onTimeJson     = json_encode($onTimeData);
$onTimeRateJson = json_encode($onTimeRateData);

$totalAttRange  = array_sum(array_column($attendanceOverTimeData, 'total_attendance'));
$totalLateRange = array_sum(array_column($attendanceOverTimeData, 'total_late'));
$rangeAttDays   = max(count($attendanceOverTimeData), 1);
$avgDailyAtt    = round($totalAttRange / $rangeAttDays, 1);
$overallLateRate = $totalAttRange > 0 ? round(($totalLateRange / $totalAttRange) * 100, 1) : 0;

$todayAtt  = (int)($todayStats['total_attendance'] ?? 0);
$todayLate = (int)($todayStats['total_late'] ?? 0);
$monthAtt  = (int)($monthStats['total_attendance'] ?? 0);
$monthLate = (int)($monthStats['total_late'] ?? 0);
$monthLateRate = $monthAtt > 0 ? round(($monthLate / $monthAtt) * 100, 1) : 0;

$peakAtt = count($attendanceOverTimeData) ? max(array_column($attendanceOverTimeData, 'total_attendance')) : 0;
$peakAttIdx = count($attendanceOverTimeData) ? array_search($peakAtt, array_column($attendanceOverTimeData, 'total_attendance')) : 0;
$peakAttDate = count($attendanceOverTimeData) ? $attendanceOverTimeData[$peakAttIdx]['attendance_date'] : 'N/A';

$worstLateDay = 'N/A'; $worstLateCount = 0;
foreach ($attendanceOverTimeData as $row) {
    if ((int)$row['total_late'] > $worstLateCount) {
        $worstLateCount = (int)$row['total_late'];
        $worstLateDay = $row['attendance_date'];
    }
}

$insights = [
    [
        'icon' => '👥', 'color' => 'blue', 'title' => 'Total Attendance (Range)',
        'value' => number_format($totalAttRange),
        'desc' => 'Over <strong>' . $rangeAttDays . ' day(s)</strong> with <strong>' . number_format($totalLateRange) . '</strong> late arrivals.',
    ],
    [
        'icon' => '⏰', 'color' => $overallLateRate > 20 ? 'rose' : 'teal', 'title' => 'Overall Late Rate',
        'value' => $overallLateRate . '%',
        'desc' => $overallLateRate > 20 ? '<strong>Elevated</strong> — worth reviewing shift schedules.' : '<strong>Healthy</strong> — team is arriving mostly on time.',
    ],
    [
        'icon' => '📅', 'color' => 'emerald', 'title' => 'Peak Attendance Day',
        'value' => htmlspecialchars($peakAttDate),
        'desc' => number_format($peakAtt) . ' check-in(s) recorded — your busiest staffing day this range.',
    ],
    [
        'icon' => '⚠️', 'color' => 'amber', 'title' => 'Worst Day for Lateness',
        'value' => htmlspecialchars($worstLateDay),
        'desc' => $worstLateDay !== 'N/A' ? number_format($worstLateCount) . ' late arrival(s) on this day.' : 'No notable late spikes this range.',
    ],
];

$recommendations = [];

if ($overallLateRate > 20) {
    $recommendations[] = [
        'icon' => '⏰', 'color' => 'rose', 'title' => 'Reduce Late Arrivals',
        'value' => $overallLateRate . '% late',
        'desc' => 'Late rate is elevated this range. Consider reviewing shift start times or setting clock-in reminders for staff.',
    ];
} else {
    $recommendations[] = [
        'icon' => '✅', 'color' => 'teal', 'title' => 'Attendance Is Healthy',
        'value' => $overallLateRate . '% late',
        'desc' => 'Punctuality is in a good range. Keep the current schedule and monitor for any upward trend.',
    ];
}

if ($worstLateDay !== 'N/A' && $worstLateCount > 0) {
    $recommendations[] = [
        'icon' => '🔍', 'color' => 'amber', 'title' => 'Investigate the Worst Late Day',
        'value' => htmlspecialchars($worstLateDay),
        'desc' => number_format($worstLateCount) . ' staff were late that day. Check if it was a one-off (traffic, weather) or a pattern tied to that shift.',
    ];
}

if ($peakAttDate !== 'N/A') {
    $recommendations[] = [
        'icon' => '🗓️', 'color' => 'emerald', 'title' => 'Plan Staffing Around Peak Days',
        'value' => htmlspecialchars($peakAttDate),
        'desc' => 'This was your highest check-in day this range. Consider scheduling extra staff on similar days going forward (weekends, paydays, etc.).',
    ];
}

if (empty($recommendations)) {
    $recommendations[] = [
        'icon' => '💡', 'color' => 'blue', 'title' => 'Not Enough Data Yet',
        'value' => '—',
        'desc' => 'Keep recording attendance — recommendations will appear once there is enough activity in the selected range.',
    ];
}

$activeSection = 'attendance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - Mr. Softy</title>
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
                <h1>📅 Attendance Dashboard</h1>
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
                <div class="kpi-banner kpi-blue">
                    <div class="kpi-banner-icon">🏠</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Today's Attendance</div>
                        <div class="kpi-banner-value"><?php echo $todayAtt; ?></div>
                        <div class="kpi-banner-sub">Total check-ins today</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-rose">
                    <div class="kpi-banner-icon">⏰</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Today's Late</div>
                        <div class="kpi-banner-value"><?php echo $todayLate; ?></div>
                        <div class="kpi-banner-sub">After 9:00 AM</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-teal">
                    <div class="kpi-banner-icon">📅</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">This Month</div>
                        <div class="kpi-banner-value"><?php echo $monthAtt; ?></div>
                        <div class="kpi-banner-sub">Total attendance</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-emerald">
                    <div class="kpi-banner-icon">📈</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Month's Late Rate</div>
                        <div class="kpi-banner-value"><?php echo $monthLateRate; ?>%</div>
                        <div class="kpi-banner-sub"><?php echo $monthLate; ?> late arrival(s)</div>
                    </div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>📈 Total Attendance</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchChartType('totalAttendanceChart', 'line', this)">Line</button>
                            <button class="ct-btn" onclick="switchChartType('totalAttendanceChart', 'bar', this)">Bar</button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="totalAttendanceChart"></canvas></div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>⏰ Late vs On-Time</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchStackedType('stacked', this)">Stacked</button>
                            <button class="ct-btn" onclick="switchStackedType('grouped', this)">Grouped</button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="lateVsOnTimeChart"></canvas></div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header"><h2>✅ On-Time Rate Trend</h2></div>
                    <div class="chart-container"><canvas id="onTimeRateChart"></canvas></div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header"><h2>🚩 Most Late Employees</h2></div>
                    <?php if (empty($topLateData)): ?>
                        <div style="padding:40px 10px; text-align:center; color:var(--sky-500); font-size:13px;">
                            No late arrivals recorded in this range. 🎉
                        </div>
                    <?php else: ?>
                        <div style="max-height:300px; overflow-y:auto;">
                            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                <thead>
                                    <tr style="position:sticky; top:0; background:#f8fafc;">
                                        <th style="text-align:left; padding:8px 10px; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0; width:28px;">#</th>
                                        <th style="text-align:left; padding:8px 10px; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Employee</th>
                                        <th style="text-align:left; padding:8px 10px; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Location</th>
                                        <th style="text-align:right; padding:8px 10px; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Late</th>
                                        <th style="text-align:right; padding:8px 10px; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Days</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topLateData as $i => $emp):
                                        $lateShare = $emp['days_recorded'] > 0 ? round(($emp['total_late'] / $emp['days_recorded']) * 100) : 0;
                                    ?>
                                    <tr style="<?php echo $i < count($topLateData) - 1 ? 'border-bottom:1px solid #f1f5f9;' : ''; ?>">
                                        <td style="padding:9px 10px; color:#94a3b8;"><?php echo $i + 1; ?></td>
                                        <td style="padding:9px 10px; color:#1e293b; font-weight:700;">
                                            <?php echo htmlspecialchars($emp['employee_name'] ?? 'Unknown'); ?>
                                            <div style="font-size:10.5px; font-weight:600; color:#94a3b8; text-transform:uppercase;"><?php echo htmlspecialchars($emp['role'] ?? ''); ?></div>
                                        </td>
                                        <td style="padding:9px 10px; color:#475569;"><?php echo htmlspecialchars($emp['location'] ?? '—'); ?></td>
                                        <td style="padding:9px 10px; text-align:right;">
                                            <span style="background:rgba(244,63,94,0.12); color:#e11d48; font-weight:800; padding:3px 9px; border-radius:12px; font-size:12px;">
                                                <?php echo (int)$emp['total_late']; ?>
                                            </span>
                                        </td>
                                        <td style="padding:9px 10px; text-align:right; color:#94a3b8;"><?php echo (int)$emp['days_recorded']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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

    const dates           = <?php echo $datesJson; ?>;
    const totalAttendance = <?php echo $totalAttendanceJson; ?>;
    const totalLate       = <?php echo $totalLateJson; ?>;
    const onTime          = <?php echo $onTimeJson; ?>;
    const onTimeRate      = <?php echo $onTimeRateJson; ?>;

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

    buildChart('totalAttendanceChart', {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Total Attendance', data: totalAttendance,
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
                datalabels: { anchor: 'end', align: 'top', color: '#0369a1', font: { weight: 'bold', size: 11 }, formatter: v => v }
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
                { label: 'On-Time', data: onTime, backgroundColor: '#10b981', borderRadius: 5, _origFill: false },
                { label: 'Late', data: totalLate, backgroundColor: '#f43f5e', borderRadius: 5, _origFill: false }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top' },
                datalabels: { anchor: 'center', align: 'center', color: 'white', font: { weight: 'bold', size: 11 }, formatter: v => v > 0 ? v : '' }
            },
            scales: {
                x: { stacked: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1', font: { size: 11 } } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { stepSize: 1, color: '#0369a1' } }
            }
        },
        plugins: [ChartDataLabels]
    });

    buildChart('onTimeRateChart', {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'On-Time Rate (%)', data: onTimeRate,
                borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.10)',
                fill: true, tension: 0.4, borderWidth: 3,
                pointBackgroundColor: '#10b981', pointBorderColor: '#fff', pointBorderWidth: 2,
                pointRadius: 5, pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top' },
                datalabels: { anchor: 'end', align: 'top', color: '#059669', font: { weight: 'bold', size: 10 }, formatter: v => v + '%' }
            },
            scales: {
                x: { grid: { color: 'rgba(16,185,129,0.08)' }, ticks: { color: '#059669', font: { size: 11 } } },
                y: { beginAtZero: true, max: 100, grid: { color: 'rgba(16,185,129,0.08)' }, ticks: { color: '#059669', callback: v => v + '%' } }
            }
        },
        plugins: [ChartDataLabels]
    });
    </script>
</body>
</html> 