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

/* ── Top 10 by quantity sold ── */
$queryTopQty = "
    SELECT p.product_name, SUM(s.quantity) AS total_qty
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN store st ON s.store_id = st.store_id
    WHERE s.sale_date >= '$startDate' AND s.sale_date <= '$endDate' AND s.is_deleted = 0
    $locationFilter
    GROUP BY p.product_name
    ORDER BY total_qty DESC
    LIMIT 10
";
$resultTopQty = mysqli_query($conn, $queryTopQty);
$topQtyData = [];
while ($row = mysqli_fetch_assoc($resultTopQty)) {
    $topQtyData[] = $row;
}

/* ── Top 10 by revenue ── */
$queryTopRevenue = "
    SELECT p.product_name, SUM(s.subtotal) AS total_revenue
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN store st ON s.store_id = st.store_id
    WHERE s.sale_date >= '$startDate' AND s.sale_date <= '$endDate' AND s.is_deleted = 0
    $locationFilter
    GROUP BY p.product_name
    ORDER BY total_revenue DESC
    LIMIT 10
";
$resultTopRevenue = mysqli_query($conn, $queryTopRevenue);
$topRevenueData = [];
while ($row = mysqli_fetch_assoc($resultTopRevenue)) {
    $topRevenueData[] = $row;
}

/* ── Sales by size ── */
$querySize = "
    SELECT p.size, SUM(s.quantity) AS qty, SUM(s.subtotal) AS revenue
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN store st ON s.store_id = st.store_id
    WHERE s.sale_date >= '$startDate' AND s.sale_date <= '$endDate' AND s.is_deleted = 0
    $locationFilter
    GROUP BY p.size
    ORDER BY revenue DESC
";
$resultSize = mysqli_query($conn, $querySize);
$sizeData = [];
while ($row = mysqli_fetch_assoc($resultSize)) {
    $sizeData[] = $row;
}

/* ── Sales by flavor family (grouped from product_name, since category is uniformly 'icecream') ── */
$queryFlavor = "
    SELECT p.product_name AS flavor, SUM(s.subtotal) AS revenue
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN store st ON s.store_id = st.store_id
    WHERE s.sale_date >= '$startDate' AND s.sale_date <= '$endDate' AND s.is_deleted = 0
    $locationFilter
    GROUP BY p.product_name
    ORDER BY revenue DESC
";
$resultFlavor = mysqli_query($conn, $queryFlavor);
$flavorData = [];
while ($row = mysqli_fetch_assoc($resultFlavor)) {
    $flavorData[] = $row;
}

/* ── KPIs ── */
$queryActiveCount = "SELECT COUNT(*) AS active_count FROM products WHERE status = 'active'";
$activeCount = mysqli_fetch_assoc(mysqli_query($conn, $queryActiveCount))['active_count'] ?? 0;

$queryAvgPrice = "SELECT AVG(price) AS avg_price FROM products WHERE status = 'active'";
$avgPrice = mysqli_fetch_assoc(mysqli_query($conn, $queryAvgPrice))['avg_price'] ?? 0;

$queryUnitsSold = "
    SELECT SUM(s.quantity) AS total_units, SUM(s.subtotal) AS total_revenue
    FROM sales s
    JOIN store st ON s.store_id = st.store_id
    WHERE s.sale_date >= '$startDate' AND s.sale_date <= '$endDate' AND s.is_deleted = 0
    $locationFilter
";
$unitsSoldRow = mysqli_fetch_assoc(mysqli_query($conn, $queryUnitsSold));
$totalUnits = (int)($unitsSoldRow['total_units'] ?? 0);
$totalProductRevenue = (float)($unitsSoldRow['total_revenue'] ?? 0);

$bestSeller    = count($topQtyData) ? $topQtyData[0]['product_name'] : 'N/A';
$bestSellerQty = count($topQtyData) ? (int)$topQtyData[0]['total_qty'] : 0;

$topQtyNamesJson = json_encode(array_column($topQtyData, 'product_name'));
$topQtyValsJson  = json_encode(array_map('intval', array_column($topQtyData, 'total_qty')));

$topRevNamesJson = json_encode(array_column($topRevenueData, 'product_name'));
$topRevValsJson  = json_encode(array_map('floatval', array_column($topRevenueData, 'total_revenue')));

$sizeLabelsJson = json_encode(array_column($sizeData, 'size'));
$sizeQtyJson    = json_encode(array_map('intval', array_column($sizeData, 'qty')));
$sizeRevJson    = json_encode(array_map('floatval', array_column($sizeData, 'revenue')));

$flavorLabelsJson = json_encode(array_column($flavorData, 'flavor'));
$flavorRevJson    = json_encode(array_map('floatval', array_column($flavorData, 'revenue')));

/* ── Insights ── */
$lowestSeller = count($topQtyData) ? end($topQtyData) : null;
$topSize = count($sizeData) ? $sizeData[0]['size'] : 'N/A';
$topSizeRevenue = count($sizeData) ? (float)$sizeData[0]['revenue'] : 0;
$avgRevenuePerUnit = $totalUnits > 0 ? $totalProductRevenue / $totalUnits : 0;

$insights = [
    [
        'icon' => '🏆', 'color' => 'emerald', 'title' => 'Best-Selling Product',
        'value' => htmlspecialchars($bestSeller),
        'desc' => '<strong>' . number_format($bestSellerQty) . ' units</strong> sold in the selected range.',
    ],
    [
        'icon' => '📦', 'color' => 'blue', 'title' => 'Active Menu Items',
        'value' => number_format($activeCount),
        'desc' => 'Currently marked <strong>active</strong> and available for sale across all sizes.',
    ],
    [
        'icon' => '🍦', 'color' => 'amber', 'title' => 'Best-Selling Size',
        'value' => htmlspecialchars($topSize),
        'desc' => '₱' . number_format($topSizeRevenue, 2) . ' in revenue — customers favor this size most.',
    ],
    [
        'icon' => '💵', 'color' => 'teal', 'title' => 'Avg Revenue per Unit',
        'value' => '₱' . number_format($avgRevenuePerUnit, 2),
        'desc' => 'Based on ' . number_format($totalUnits) . ' total unit(s) sold in range.',
    ],
];

/* ── Recommendations ── */
$recommendations = [];

if ($bestSeller !== 'N/A') {
    $recommendations[] = [
        'icon' => '📦', 'color' => 'emerald', 'title' => "Protect Your Best-Seller's Stock",
        'value' => number_format($bestSellerQty) . ' units',
        'desc' => '<strong>' . htmlspecialchars($bestSeller) . '</strong> is your top mover. Prioritize restocking its ingredients first to avoid running out during peak hours.',
    ];
}

if ($lowestSeller && $lowestSeller['product_name'] !== $bestSeller) {
    $recommendations[] = [
        'icon' => '📉', 'color' => 'amber', 'title' => 'Review Your Slowest Mover',
        'value' => htmlspecialchars($lowestSeller['product_name']),
        'desc' => 'Only ' . number_format($lowestSeller['total_qty']) . ' unit(s) sold in range among your top 10. Consider a bundle, a limited-time discount, or repositioning it on the menu board.',
    ];
}

if ($topSize !== 'N/A') {
    $recommendations[] = [
        'icon' => '🍦', 'color' => 'blue', 'title' => 'Lean Into the Popular Size',
        'value' => htmlspecialchars($topSize),
        'desc' => 'The ' . htmlspecialchars($topSize) . ' size drives the most revenue. Make sure cups/containers for this size are always well-stocked, and consider featuring it in promos.',
    ];
}

if ($activeCount > 0 && count($topQtyData) > 0) {
    $soldNames = array_column($topQtyData, 'product_name');
    $recommendations[] = [
        'icon' => '🧊', 'color' => 'rose', 'title' => 'Check for Slow-Moving Inventory',
        'value' => $activeCount . ' active items',
        'desc' => 'Cross-check your full active product list against sales — any active item with little to no movement this range is worth reviewing for a promo or removal.',
    ];
}

if (empty($recommendations)) {
    $recommendations[] = [
        'icon' => '💡', 'color' => 'blue', 'title' => 'Not Enough Data Yet',
        'value' => '—',
        'desc' => 'Keep recording sales — recommendations will appear once there is enough activity in the selected range.',
    ];
}

$activeSection = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Dashboard - Mr. Softy</title>
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
                <h1>📦 Products Dashboard</h1>
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
                    <div class="kpi-banner-icon">📦</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Active Products</div>
                        <div class="kpi-banner-value"><?php echo number_format($activeCount); ?></div>
                        <div class="kpi-banner-sub">Currently on the menu</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-blue">
                    <div class="kpi-banner-icon">🏆</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Best Seller</div>
                        <div class="kpi-banner-value" style="font-size:16px;"><?php echo htmlspecialchars($bestSeller); ?></div>
                        <div class="kpi-banner-sub"><?php echo number_format($bestSellerQty); ?> units in range</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-teal">
                    <div class="kpi-banner-icon">🧮</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Total Units Sold</div>
                        <div class="kpi-banner-value"><?php echo number_format($totalUnits); ?></div>
                        <div class="kpi-banner-sub">In selected range</div>
                    </div>
                </div>
                <div class="kpi-banner kpi-rose">
                    <div class="kpi-banner-icon">💵</div>
                    <div class="kpi-banner-body">
                        <div class="kpi-banner-label">Avg Menu Price</div>
                        <div class="kpi-banner-value">₱<?php echo number_format($avgPrice, 2); ?></div>
                        <div class="kpi-banner-sub">Across active products</div>
                    </div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>📊 Top 10 by Quantity Sold</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchProductChart('qty','bar',this)">Bar</button>
                            <button class="ct-btn" onclick="switchProductChart('qty','doughnut',this)">Donut</button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="topQtyChart"></canvas></div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header">
                        <h2>💰 Top 10 by Revenue</h2>
                        <div class="chart-type-toggle">
                            <button class="ct-btn active" onclick="switchProductChart('rev','bar',this)">Bar</button>
                            <button class="ct-btn" onclick="switchProductChart('rev','doughnut',this)">Donut</button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="topRevChart"></canvas></div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header"><h2>🍦 Sales by Size</h2></div>
                    <div class="chart-container"><canvas id="sizeChart"></canvas></div>
                </div>

                <div class="chart-card">
                    <div class="chart-card-header"><h2>🧁 Revenue by Flavor</h2></div>
                    <div class="chart-container"><canvas id="flavorChart"></canvas></div>
                </div>
            </div>

            <div class="section-divider"><span class="section-divider-label">💡 Smart Insights</span></div>
            <div style="background:#fff; border-radius:10px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:24px;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="text-align:left; padding:12px 18px; font-size:0.8rem; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0; width:36px;">#</th>
                            <th style="text-align:left; padding:12px 18px; font-size:0.8rem; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Insight</th>
                            <th style="text-align:left; padding:12px 18px; font-size:0.8rem; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Details</th>
                            <th style="text-align:right; padding:12px 18px; font-size:0.8rem; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($insights as $i => $ins): ?>
                        <tr style="<?php echo $i < count($insights) - 1 ? 'border-bottom:1px solid #f1f5f9;' : ''; ?>">
                            <td style="padding:14px 18px; color:#94a3b8; font-size:0.85rem; vertical-align:top;"><?php echo $i + 1; ?></td>
                            <td style="padding:14px 18px; vertical-align:top; white-space:nowrap;">
                                <span style="margin-right:6px;"><?php echo $ins['icon']; ?></span>
                                <strong style="color:#1e293b; font-size:0.92rem;"><?php echo htmlspecialchars($ins['title']); ?></strong>
                            </td>
                            <td style="padding:14px 18px; color:#475569; font-size:0.88rem; line-height:1.5;"><?php echo $ins['desc']; ?></td>
                            <td style="padding:14px 18px; text-align:right; font-weight:700; color:#1e293b; font-size:0.92rem; white-space:nowrap; vertical-align:top;"><?php echo $ins['value']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section-divider"><span class="section-divider-label">🎯 Recommendations</span></div>
            <div style="background:#fff; border-radius:10px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="text-align:left; padding:12px 18px; font-size:0.8rem; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0; width:36px;">#</th>
                            <th style="text-align:left; padding:12px 18px; font-size:0.8rem; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Recommendation</th>
                            <th style="text-align:left; padding:12px 18px; font-size:0.8rem; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Details</th>
                            <th style="text-align:right; padding:12px 18px; font-size:0.8rem; color:#64748b; font-weight:700; border-bottom:1px solid #e2e8f0;">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recommendations as $i => $rec): ?>
                        <tr style="<?php echo $i < count($recommendations) - 1 ? 'border-bottom:1px solid #f1f5f9;' : ''; ?>">
                            <td style="padding:14px 18px; color:#94a3b8; font-size:0.85rem; vertical-align:top;"><?php echo $i + 1; ?></td>
                            <td style="padding:14px 18px; vertical-align:top; white-space:nowrap;">
                                <span style="margin-right:6px;"><?php echo $rec['icon']; ?></span>
                                <strong style="color:#1e293b; font-size:0.92rem;"><?php echo htmlspecialchars($rec['title']); ?></strong>
                            </td>
                            <td style="padding:14px 18px; color:#475569; font-size:0.88rem; line-height:1.5;"><?php echo $rec['desc']; ?></td>
                            <td style="padding:14px 18px; text-align:right; font-weight:700; color:#1e293b; font-size:0.92rem; white-space:nowrap; vertical-align:top;"><?php echo $rec['value']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

    const topQtyNames = <?php echo $topQtyNamesJson; ?>;
    const topQtyVals  = <?php echo $topQtyValsJson; ?>;
    const topRevNames = <?php echo $topRevNamesJson; ?>;
    const topRevVals  = <?php echo $topRevValsJson; ?>;
    const sizeLabels  = <?php echo $sizeLabelsJson; ?>;
    const sizeQty     = <?php echo $sizeQtyJson; ?>;
    const sizeRev     = <?php echo $sizeRevJson; ?>;
    const flavorLabels= <?php echo $flavorLabelsJson; ?>;
    const flavorRev   = <?php echo $flavorRevJson; ?>;

    const PALETTE = ['#0ea5e9','#06b6d4','#6366f1','#f43f5e','#f59e0b','#10b981','#8b5cf6','#ec4899','#14b8a6','#84cc16'];
    const chartRegistry = {};
    function buildChart(id, config) {
        if (chartRegistry[id]) chartRegistry[id].destroy();
        chartRegistry[id] = new Chart(document.getElementById(id), config);
        return chartRegistry[id];
    }

    function buildQtyChart(type) {
        const isDonut = type === 'doughnut';
        buildChart('topQtyChart', {
            type: type,
            data: { labels: topQtyNames, datasets: [{ label: 'Qty Sold', data: topQtyVals, backgroundColor: PALETTE, borderRadius: isDonut ? 0 : 5, borderWidth: isDonut ? 2 : 0, borderColor: '#fff' }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: isDonut ? undefined : 'y',
                plugins: {
                    legend: { display: isDonut, position: 'right', labels: { font: { size: 11 } } },
                    datalabels: isDonut ? { color: '#fff', font: { weight: 'bold', size: 10 }, formatter: v => v }
                        : { anchor: 'end', align: 'right', color: '#333', font: { weight: 'bold', size: 11 }, formatter: v => v }
                },
                scales: isDonut ? {} : {
                    x: { beginAtZero: true, ticks: { stepSize: 1, color: '#0369a1' }, grid: { color: 'rgba(14,165,233,0.08)' } },
                    y: { ticks: { color: '#0369a1', font: { size: 11 } }, grid: { color: 'rgba(14,165,233,0.08)' } }
                }
            },
            plugins: [ChartDataLabels]
        });
    }

    function buildRevChart(type) {
        const isDonut = type === 'doughnut';
        buildChart('topRevChart', {
            type: type,
            data: { labels: topRevNames, datasets: [{ label: 'Revenue (₱)', data: topRevVals, backgroundColor: PALETTE, borderRadius: isDonut ? 0 : 5, borderWidth: isDonut ? 2 : 0, borderColor: '#fff' }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: isDonut ? undefined : 'y',
                plugins: {
                    legend: { display: isDonut, position: 'right', labels: { font: { size: 11 } } },
                    datalabels: isDonut ? {
                        color: '#fff', font: { weight: 'bold', size: 10 },
                        formatter: (v, ctx) => {
                            const total = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            return total > 0 ? ((v / total) * 100).toFixed(1) + '%' : '0%';
                        }
                    } : { anchor: 'end', align: 'right', color: '#333', font: { weight: 'bold', size: 11 }, formatter: v => '₱' + Number(v).toLocaleString() },
                    tooltip: isDonut ? {
                        callbacks: { label: (ctx) => ctx.label + ': ₱' + Number(ctx.parsed).toLocaleString() }
                    } : undefined
                },
                scales: isDonut ? {} : {
                    x: { beginAtZero: true, ticks: { color: '#0369a1', callback: v => '₱' + v.toLocaleString() }, grid: { color: 'rgba(14,165,233,0.08)' } },
                    y: { ticks: { color: '#0369a1', font: { size: 11 } }, grid: { color: 'rgba(14,165,233,0.08)' } }
                }
            },
            plugins: [ChartDataLabels]
        });
    }

    function switchProductChart(which, type, btn) {
        btn.closest('.chart-type-toggle').querySelectorAll('.ct-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (which === 'qty') buildQtyChart(type); else buildRevChart(type);
    }

    buildQtyChart('bar');
    buildRevChart('bar');

    // Sales by Size
    buildChart('sizeChart', {
        type: 'bar',
        data: {
            labels: sizeLabels,
            datasets: [
                { label: 'Units Sold', data: sizeQty, backgroundColor: '#0ea5e9', borderRadius: 6, yAxisID: 'y' },
                { label: 'Revenue (₱)', data: sizeRev, backgroundColor: '#10b981', borderRadius: 6, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true, position: 'top' }, datalabels: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1' } },
                y:  { position: 'left', beginAtZero: true, grid: { color: 'rgba(14,165,233,0.08)' }, ticks: { color: '#0369a1' }, title: { display: true, text: 'Units' } },
                y1: { position: 'right', beginAtZero: true, grid: { display: false }, ticks: { color: '#059669', callback: v => '₱' + v.toLocaleString() }, title: { display: true, text: 'Revenue' } }
            }
        }
    });

    // Revenue by Flavor
    buildChart('flavorChart', {
        type: 'doughnut',
        data: { labels: flavorLabels, datasets: [{ data: flavorRev, backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'right', labels: { font: { size: 11 } } },
                datalabels: {
                    color: '#fff', font: { weight: 'bold', size: 10 },
                    formatter: (v, ctx) => {
                        const total = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        return total > 0 ? ((v / total) * 100).toFixed(1) + '%' : '0%';
                    }
                },
                tooltip: {
                    callbacks: { label: (ctx) => ctx.label + ': ₱' + Number(ctx.parsed).toLocaleString() }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
    </script>
</body>
</html>