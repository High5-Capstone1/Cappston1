<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

$store_id = $_SESSION['store_id'];
$cashier_name = $_SESSION['username'] ?? 'Cashier';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Store <?= htmlspecialchars($store_id) ?></title>
    
   
    <script src="https://cdn.tailwindcss.com"></script>
    
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #0EA5E9 0%, #0284C7 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .nav-link {
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            transform: translateX(8px);
        }
    </style>
</head>
<body class="bg-gray-50">

 
    <aside class="fixed left-0 top-0 h-screen w-72 bg-gradient-to-b from-gray-900 to-gray-800 text-white shadow-2xl z-50">
        
        <div class="p-6 border-b border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-sky-400 to-blue-500 flex items-center justify-center">
                    <i class="fas fa-cash-register text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold">Cashier Panel</h2>
                    <p class="text-xs text-gray-400">Point of Sale System</p>
                </div>
            </div>
        </div>
        
        
        <div class="mx-6 mt-6 p-4 rounded-xl bg-gradient-to-br from-sky-500/20 to-blue-500/20 border border-sky-400/30">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-sky-400 to-blue-500 flex items-center justify-center text-lg font-bold">
                    <?= strtoupper(substr($cashier_name, 0, 1)) ?>
                </div>
                <div>
                    <h3 class="font-semibold text-sm"><?= htmlspecialchars($cashier_name) ?></h3>
                    <p class="text-xs text-gray-400">Store #<?= htmlspecialchars($store_id) ?></p>
                </div>
            </div>
        </div>
        
 
        <nav class="mt-8 px-4">
            <ul class="space-y-2">
                <li>
                    <a href="dashboard.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-lg bg-sky-500/20 text-sky-300 border border-sky-400/30">
                        <i class="fas fa-home text-lg w-5"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="attendance.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-gray-700/50 text-gray-300 hover:text-white">
                        <i class="fas fa-clock text-lg w-5"></i>
                        <span class="font-medium">Attendance</span>
                    </a>
                </li>
                <li>
                    <a href="addSales.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-gray-700/50 text-gray-300 hover:text-white">
                        <i class="fas fa-cart-plus text-lg w-5"></i>
                        <span class="font-medium">New Sale</span>
                    </a>
                </li>
                <li>
                    <a href="salesHistory.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-gray-700/50 text-gray-300 hover:text-white">
                        <i class="fas fa-chart-line text-lg w-5"></i>
                        <span class="font-medium">Sales History</span>
                    </a>
                </li>
            </ul>
        </nav>
  
        <div class="absolute bottom-6 left-4 right-4">
            <form action="../../logout.php" method="POST">
                <button type="submit" class="w-full flex items-center justify-center gap-3 px-4 py-3 rounded-lg bg-red-500/10 hover:bg-red-500 text-red-400 hover:text-white border border-red-500/30 hover:border-red-500 transition-all duration-300 font-medium">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>


    <main class="ml-72 min-h-screen">
        
        
        <header class="bg-white shadow-sm sticky top-0 z-40">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Welcome Back, <?= htmlspecialchars($cashier_name) ?>! 👋</h1>
                    <p class="text-sm text-gray-500 mt-1">Start your day with a smile at Mr.Softy (:</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Current Time</p>
                        <p class="text-sm font-semibold text-gray-700" id="currentTime"></p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-sky-400 to-blue-500 flex items-center justify-center text-white font-bold">
                        <?= strtoupper(substr($cashier_name, 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>


        <div class="p-8">
            

            <div class="gradient-bg rounded-2xl p-8 mb-8 text-white shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-3xl font-bold mb-2">Ready to Make Sales!</h2>
                        <p class="text-sky-100 text-lg">You can record attendance and manage sales here</p>
                        <div class="mt-4 flex items-center gap-2 text-sm">
                            <i class="fas fa-store"></i>
                            <span>Store ID: <?= htmlspecialchars($store_id) ?></span>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-cash-register text-8xl opacity-20"></i>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                
              
                <a href="attendance.php" class="card-hover bg-white rounded-xl p-6 shadow-lg border border-gray-100 hover:border-sky-300 group">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-sky-400 to-blue-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-clock text-2xl text-white"></i>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400 group-hover:text-sky-600 group-hover:translate-x-1 transition-all"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Record Attendance</h3>
                    <p class="text-sm text-gray-500">Clock in/out and manage your work hours</p>
                </a>

                <a href="addSales.php" class="card-hover bg-white rounded-xl p-6 shadow-lg border border-gray-100 hover:border-green-300 group">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-green-500 to-emerald-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-cart-plus text-2xl text-white"></i>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400 group-hover:text-green-600 group-hover:translate-x-1 transition-all"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Create New Sale</h3>
                    <p class="text-sm text-gray-500">Process a new transaction quickly</p>
                </a>

                
                <a href="salesHistory.php" class="card-hover bg-white rounded-xl p-6 shadow-lg border border-gray-100 hover:border-blue-300 group">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-chart-line text-2xl text-white"></i>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400 group-hover:text-blue-600 group-hover:translate-x-1 transition-all"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Sales History</h3>
                    <p class="text-sm text-gray-500">View and analyze past transactions</p>
                </a>
            </div>
        </div>
    </main>

    <script>
        function updateTime() {
            const now = new Date();
            const options = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', options);
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    </script>

</body>
</html>