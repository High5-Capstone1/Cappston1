<?php

include  '../DBconnect.php';


date_default_timezone_set('Asia/Manila');
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

$queryLocations = "SELECT DISTINCT location FROM attendance_summary ORDER BY location";
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
        SUM(s.subtotal + IFNULL(sts.total_topping, 0)) AS daily_sales
    FROM sales s
    JOIN store st ON s.store_id = st.store_id
    LEFT JOIN (
        SELECT stt.sale_id, SUM(t.price) AS total_topping
        FROM sale_toppings stt
        JOIN toppings t ON stt.topping_id = t.topping_id
        GROUP BY stt.sale_id
    ) sts ON s.sale_id = sts.sale_id
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

// convert sale 2 Json
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
    <div class="container">
        
        <div class="header">
            <h1>📊 Live Dashboard</h1>
            <div class="header-controls">
                <div class="filter-group">
                    <label for="locationFilter">📍Location:</label>
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
                    <label for="startDate">📅 Start Date:</label>
                    <input type="date" id="startDate" value="<?php echo $startDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filter-group">
                    <label for="endDate">📅 End Date:</label>
                    <input type="date" id="endDate" value="<?php echo $endDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <button class="refresh-btn apply-btn" onclick="applyFilters()">Apply Filters</button>
                <div class="last-update">Last Updated: <span id="lastUpdate"><?php echo date('g:i:s A'); ?></span></div>
                <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>
            </div>
        </div>
        

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Today's Attendance</h3>
                <div class="stat-value"><?php echo $todayStats['total_attendance'] ?? 0; ?></div>
                <div class="stat-label">Total check-ins</div>
            </div>
            
            <div class="stat-card late">
                <h3>Today's Late</h3>
                <div class="stat-value"><?php echo $todayStats['total_late'] ?? 0; ?></div>
                <div class="stat-label">After 9:00 AM</div>
            </div>
            
            <div class="stat-card">
                <h3>This Month</h3>
                <div class="stat-value"><?php echo $monthStats['total_attendance'] ?? 0; ?></div>
                <div class="stat-label">Total attendance</div>
            </div>
            
            <div class="stat-card late">
                <h3>Month's Late</h3>
                <div class="stat-value"><?php echo $monthStats['total_late'] ?? 0; ?></div>
                <div class="stat-label">Late arrivals</div>
            </div>
            
            <div class="stat-card sales">
                <h3>Today's Sales</h3>
                <div class="stat-value">₱<?php echo number_format($todaySales['today_sales'] ?? 0, 2); ?></div>
                <div class="stat-label">Total sales today</div>
            </div>
            
            <div class="stat-card sales">
                <h3>Month's Sales</h3>
                <div class="stat-value">₱<?php echo number_format($monthSales['month_sales'] ?? 0, 2); ?></div>
                <div class="stat-label">Total sales this month</div>
            </div>
        </div>
 
        <div class="chart-grid">
      
            <div class="chart-card">
                <h2>📈 Total Attendance</h2>
                <div class="chart-container">
                    <canvas id="totalAttendanceChart"></canvas>
                </div>
            </div>
            

            <div class="chart-card">
                <h2>⏰ Late vs On-Time Attendance</h2>
                <div class="chart-container">
                    <canvas id="lateVsOnTimeChart"></canvas>
                </div>
            </div>
         
            <div class="chart-card">
                <h2>💰 Total Sales Over Time</h2>
                <div class="chart-container">
                    <canvas id="salesOverTimeChart"></canvas>
                </div>
            </div>
            

            <div class="chart-card">
                <h2>📊 Top 10 Products by Quantity Sold</h2>
                <div class="chart-container">
                    <canvas id="productQuantityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>

        function applyFilters() {
            const location = document.getElementById('locationFilter').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            window.location.href = '?location=' + location + '&start_date=' + startDate + '&end_date=' + endDate;
        }
        
        setTimeout(function() {
            location.reload();
        }, 300000);
        
       
        setInterval(function() {
            const now = new Date();
            document.getElementById('lastUpdate').textContent = now.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric',
                hour12: true
            });
        }, 1000);
        

        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                }
            }
        };
        

        new Chart(document.getElementById('totalAttendanceChart'), {
            type: 'line',
            data: {
                labels: <?php echo $datesJson; ?>,
                datasets: [{
                    label: 'Total Attendance',
                    data: <?php echo $totalAttendanceJson; ?>,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#4F46E5',
                        font: {
                            weight: 'bold',
                            size: 11
                        },
                        formatter: function(value) {
                            return value;
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        new Chart(document.getElementById('lateVsOnTimeChart'), {
            type: 'bar',
            data: {
                labels: <?php echo $datesJson; ?>,
                datasets: [
                    {
                        label: 'On-Time',
                        data: <?php echo $onTimeJson; ?>,
                        backgroundColor: '#10B981',
                        borderRadius: 5
                    },
                    {
                        label: 'Late',
                        data: <?php echo $totalLateJson; ?>,
                        backgroundColor: '#EF4444',
                        borderRadius: 5
                    }
                ]
            },
            options: {
                ...chartOptions,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    datalabels: {
                        anchor: 'center',
                        align: 'center',
                        color: 'white',
                        font: {
                            weight: 'bold',
                            size: 11
                        },
                        formatter: function(value) {
                            return value > 0 ? value : '';
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
        
        new Chart(document.getElementById('salesOverTimeChart'), {
            type: 'line',
            data: {
                labels: <?php echo $salesDatesJson; ?>,
                datasets: [{
                    label: 'Daily Sales (₱)',
                    data: <?php echo $salesAmountJson; ?>,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#10B981',
                        font: {
                            weight: 'bold',
                            size: 11
                        },
                        formatter: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        new Chart(document.getElementById('productQuantityChart'), {
            type: 'bar',
            data: {
                labels: <?php echo $productNamesJson; ?>,
                datasets: [{
                    label: 'Quantity Sold',
                    data: <?php echo $productQuantitiesJson; ?>,
                    backgroundColor: [
                        '#4F46E5',
                        '#10B981',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6',
                        '#EC4899',
                        '#14B8A6',
                        '#F97316',
                        '#06B6D4',
                        '#84CC16'
                    ],
                    borderRadius: 5
                }]
            },
            options: {
                ...chartOptions,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'right',
                        color: '#333',
                        font: {
                            weight: 'bold',
                            size: 11
                        },
                        formatter: function(value) {
                            return value;
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    </script>
</body>
</html>