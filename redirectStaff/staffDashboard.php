<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$store_id = $_SESSION['store_id'];
$staff_name = $_SESSION['username'] ?? 'Staff Member';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Store <?= htmlspecialchars($store_id) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'softy-blue': '#0EA5E9',
                        'softy-dark': '#0284C7',
                        'softy-light': '#38BDF8',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-sky-400 via-blue-500 to-blue-600 min-h-screen">


    <aside class="fixed left-0 top-0 h-screen w-72 bg-white shadow-2xl z-50 border-r-4 border-sky-400">
      
        <div class="p-6 bg-gradient-to-r from-sky-400 to-blue-500 border-b-4 border-blue-600">
            <h2 class="text-white text-2xl font-bold flex items-center gap-3">
                <i class="fas fa-ice-cream text-white"></i>
                Mr.Softy Staff
            </h2>
            <p class="text-white/90 text-sm mt-1">Inventory Management</p>
        </div>

        <div class="mx-5 my-6 p-4 bg-gradient-to-r from-sky-100 to-blue-100 rounded-xl border-l-4 border-sky-500 shadow-md">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-sky-400 to-blue-500 flex items-center justify-center text-white text-xl font-bold shadow-lg">
                    <?= strtoupper(substr($staff_name, 0, 1)) ?>
                </div>
                <div>
                    <h3 class="text-gray-800 font-bold"><?= htmlspecialchars($staff_name) ?></h3>
                    <p class="text-sky-600 text-sm font-medium">Store #<?= htmlspecialchars($store_id) ?></p>
                </div>
            </div>
        </div>


        <nav class="px-5 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-4 px-4 py-3 text-sky-600 bg-sky-50 rounded-lg font-semibold transition-all hover:bg-sky-100 hover:translate-x-1 border-l-4 border-sky-500">
                <i class="fas fa-home w-6 text-center text-lg"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="attendance.php" class="flex items-center gap-4 px-4 py-3 text-gray-600 rounded-lg font-medium transition-all hover:bg-gray-50 hover:text-sky-600 hover:translate-x-1">
                <i class="fas fa-clock w-6 text-center text-lg"></i>
                <span>Attendance</span>
            </a>
            
            <a href="inventoryStaff.php" class="flex items-center gap-4 px-4 py-3 text-gray-600 rounded-lg font-medium transition-all hover:bg-gray-50 hover:text-sky-600 hover:translate-x-1">
                <i class="fas fa-boxes w-6 text-center text-lg"></i>
                <span>Inventory</span>
            </a>
        </nav>

   
        <div class="absolute bottom-6 left-5 right-5">
            <form action="../logout.php" method="POST">
                <button type="submit" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-red-50 border-2 border-red-300 text-red-600 rounded-lg font-semibold transition-all hover:bg-red-500 hover:text-white hover:border-red-500 hover:-translate-y-1 hover:shadow-lg">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

 
    <main class="ml-72 p-8">
    
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8 border-t-4 border-sky-400">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">Welcome Back, <?= htmlspecialchars($staff_name) ?>! 🍦</h1>
                    <p class="text-gray-600 mt-1 font-medium">Manage your inventory and keep our sweet treats stocked!</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">
                        <i class="far fa-calendar text-sky-500"></i>
                        <?= date('l, F j, Y') ?>
                    </p>
                    <p class="text-2xl font-bold text-sky-500 mt-1">
                        <i class="far fa-clock"></i>
                        <span id="currentTime"></span>
                    </p>
                </div>
            </div>
        </div>


        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
   
            <div class="bg-white rounded-2xl shadow-xl p-6 hover:shadow-2xl hover:-translate-y-1 transition-all border-t-4 border-sky-400">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-sky-400 to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-ice-cream text-2xl text-white"></i>
                    </div>
                    <span class="text-xs font-bold text-sky-600 bg-sky-100 px-3 py-1 rounded-full">
                        Active
                    </span>
                </div>
                <h3 class="text-gray-500 text-sm font-semibold mb-1 uppercase">Total Products</h3>
                <p class="text-4xl font-bold bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">0</p>
                <p class="text-xs text-gray-400 mt-2 font-medium">In inventory</p>
            </div>


            <div class="bg-white rounded-2xl shadow-xl p-6 hover:shadow-2xl hover:-translate-y-1 transition-all border-t-4 border-orange-400">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-orange-400 to-red-500 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-exclamation-triangle text-2xl text-white"></i>
                    </div>
                    <span class="text-xs font-bold text-orange-600 bg-orange-100 px-3 py-1 rounded-full">
                        Alert
                    </span>
                </div>
                <h3 class="text-gray-500 text-sm font-semibold mb-1 uppercase">Low Stock Items</h3>
                <p class="text-4xl font-bold bg-gradient-to-r from-orange-500 to-red-600 bg-clip-text text-transparent">0</p>
                <p class="text-xs text-gray-400 mt-2 font-medium">Need restock</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-6 hover:shadow-2xl hover:-translate-y-1 transition-all border-t-4 border-purple-400">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-400 to-pink-500 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-clock text-2xl text-white"></i>
                    </div>
                    <span class="text-xs font-bold text-purple-600 bg-purple-100 px-3 py-1 rounded-full">
                        Active
                    </span>
                </div>
                <h3 class="text-gray-500 text-sm font-semibold mb-1 uppercase">Hours Logged</h3>
                <p class="text-4xl font-bold bg-gradient-to-r from-purple-500 to-pink-600 bg-clip-text text-transparent">0h 0m</p>
                <p class="text-xs text-gray-400 mt-2 font-medium">This shift</p>
            </div>

           
            <div class="bg-white rounded-2xl shadow-xl p-6 hover:shadow-2xl hover:-translate-y-1 transition-all border-t-4 border-green-400">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-green-400 to-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-store text-2xl text-white"></i>
                    </div>
                    <span class="text-xs font-bold text-green-600 bg-green-100 px-3 py-1 rounded-full">
                        Open
                    </span>
                </div>
                <h3 class="text-gray-500 text-sm font-semibold mb-1 uppercase">Store Status</h3>
                <p class="text-4xl font-bold bg-gradient-to-r from-green-500 to-emerald-600 bg-clip-text text-transparent">Active</p>
                <p class="text-xs text-gray-400 mt-2 font-medium">Operating normally</p>
            </div>
        </div>

  
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
           
            <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-sky-400">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-gradient-to-br from-sky-400 to-blue-500 rounded-lg flex items-center justify-center shadow-lg">
                        <i class="fas fa-bolt text-white"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Quick Actions</h2>
                </div>
                
                <div class="space-y-3">
                    <a href="attendance.php" class="block p-4 bg-gradient-to-r from-sky-50 to-blue-50 rounded-xl hover:shadow-lg transition-all group border-2 border-sky-200 hover:border-sky-400">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-sky-400 to-blue-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform shadow-md">
                                    <i class="fas fa-clock text-white text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800">Clock In/Out</h3>
                                    <p class="text-sm text-gray-600">Record your attendance</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-sky-500 group-hover:translate-x-2 transition-transform"></i>
                        </div>
                    </a>

                    <a href="inventoryStaff.php" class="block p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl hover:shadow-lg transition-all group border-2 border-purple-200 hover:border-purple-400">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-pink-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform shadow-md">
                                    <i class="fas fa-boxes text-white text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800">Manage Inventory</h3>
                                    <p class="text-sm text-gray-600">Update stock levels</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-purple-500 group-hover:translate-x-2 transition-transform"></i>
                        </div>
                    </a>

                    <a href="inventoryStaff.php?action=check" class="block p-4 bg-gradient-to-r from-orange-50 to-red-50 rounded-xl hover:shadow-lg transition-all group border-2 border-orange-200 hover:border-orange-400">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-red-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform shadow-md">
                                    <i class="fas fa-clipboard-check text-white text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800">Stock Check</h3>
                                    <p class="text-sm text-gray-600">Verify inventory levels</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-orange-500 group-hover:translate-x-2 transition-transform"></i>
                        </div>
                    </a>
                </div>
            </div>

           
            <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-purple-400">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-pink-500 rounded-lg flex items-center justify-center shadow-lg">
                        <i class="fas fa-tasks text-white"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Today's Tasks</h2>
                </div>

                <div class="space-y-3">
                    <div class="p-4 bg-gradient-to-r from-sky-50 to-blue-50 rounded-xl border-2 border-sky-200">
                        <div class="flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-gradient-to-br from-sky-400 to-blue-500 rounded-full flex items-center justify-center shadow-md">
                                    <i class="fas fa-check text-white text-sm"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">🍦 Morning Stock Check</h4>
                                <p class="text-sm text-gray-600">Verify ice cream and toppings inventory</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl border-2 border-purple-200">
                        <div class="flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-gradient-to-br from-purple-400 to-pink-500 rounded-full flex items-center justify-center shadow-md">
                                    <i class="fas fa-box text-white text-sm"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">📦 Update Inventory</h4>
                                <p class="text-sm text-gray-600">Record new deliveries and stock movements</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gradient-to-r from-orange-50 to-red-50 rounded-xl border-2 border-orange-200">
                        <div class="flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center shadow-md">
                                    <i class="fas fa-exclamation text-white text-sm"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">⚠️ Low Stock Alert</h4>
                                <p class="text-sm text-gray-600">Check items that need reordering</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border-2 border-green-200">
                        <div class="flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center shadow-md">
                                    <i class="fas fa-snowflake text-white text-sm"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">❄️ Check Freezers</h4>
                                <p class="text-sm text-gray-600">Ensure proper temperature maintenance</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-br from-sky-400 to-blue-500 rounded-2xl shadow-xl p-6 text-white border-4 border-white">
                <div class="flex items-center gap-3 mb-3">
                    <i class="fas fa-clipboard-list text-3xl"></i>
                    <h3 class="font-bold text-xl">Daily Checklist</h3>
                </div>
                <p class="text-white/90 text-sm font-medium">Complete inventory tasks and attendance records daily</p>
            </div>

            <div class="bg-gradient-to-br from-purple-400 to-pink-500 rounded-2xl shadow-xl p-6 text-white border-4 border-white">
                <div class="flex items-center gap-3 mb-3">
                    <i class="fas fa-thermometer-half text-3xl"></i>
                    <h3 class="font-bold text-xl">Temperature Check</h3>
                </div>
                <p class="text-white/90 text-sm font-medium">Monitor freezer temperatures to maintain quality</p>
            </div>

            <div class="bg-gradient-to-br from-orange-400 to-red-500 rounded-2xl shadow-xl p-6 text-white border-4 border-white">
                <div class="flex items-center gap-3 mb-3">
                    <i class="fas fa-bell text-3xl"></i>
                    <h3 class="font-bold text-xl">Stay Updated</h3>
                </div>
                <p class="text-white/90 text-sm font-medium">Check notifications and important announcements</p>
            </div>
        </div>

       
        <div class="mt-8 bg-white rounded-2xl shadow-xl p-6 border-t-4 border-green-400">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse shadow-lg"></div>
                    <span class="text-gray-600 font-semibold">System Status: <span class="text-green-600 font-bold">Online & Ready</span></span>
                </div>
                <div class="flex gap-6 text-sm text-gray-600 font-medium">
                    <span><i class="fas fa-server text-green-500"></i> Server: Active</span>
                    <span><i class="fas fa-database text-blue-500"></i> Database: Connected</span>
                    <span><i class="fas fa-shield-alt text-sky-500"></i> Security: Enabled</span>
                </div>
            </div>
        </div>
    </main>

    <script>
        
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        updateTime();
        setInterval(updateTime, 1000);

        
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('nav a').forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html>