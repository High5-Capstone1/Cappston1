<?php

$activeSection = $activeSection ?? '';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <span class="sidebar-logo-icon">📊</span>
        <span class="sidebar-logo-text">Analytics</span>
    </div>
    <nav class="sidebar-nav">
        <a href="salesDashboard.php" class="sidebar-link <?= $activeSection === 'sales' ? 'active' : '' ?>">
            <span class="sidebar-icon">💰</span><span>Sales</span>
        </a>
        <a href="productDashboard.php" class="sidebar-link <?= $activeSection === 'products' ? 'active' : '' ?>">
            <span class="sidebar-icon">📦</span><span>Products</span>
        </a>
        <a href="attendanceDashboard.php" class="sidebar-link <?= $activeSection === 'attendance' ? 'active' : '' ?>">
            <span class="sidebar-icon">📅</span><span>Attendance</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="adminDashboard.php" class="sidebar-back-link"
           style="display:block;padding:12px 16px;color:#fff;text-decoration:none;border-radius:4px;
                  background-color:#0d47a1;font-size:14px;text-align:left;position:fixed;bottom:20px;
                  left:20px;width:140px;z-index:100;box-sizing:border-box;border:2px solid #1976d2;
                  cursor:pointer;transition:all .3s ease;"
           onmouseover="this.style.backgroundColor='#1565c0'" onmouseout="this.style.backgroundColor='#0d47a1'">
           ← Back to Admin
        </a>
    </div>
</aside>