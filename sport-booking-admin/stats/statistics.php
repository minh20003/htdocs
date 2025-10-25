<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/database.php'; // Ensure path is correct
// Set timezone for PHP date functions if needed (should match DB session)
date_default_timezone_set('Asia/Ho_Chi_Minh');
$conn->query("SET time_zone = '+07:00'"); // Ensure DB session timezone is correct

// --- Fetch Statistics ---

// Calculate Total Revenue (Completed)
$totalRevenue = 0;
$revenueSql = "SELECT SUM(total_price) AS total FROM bookings WHERE status = 'completed'";
$revenueResult = $conn->query($revenueSql);
if ($revenueResult && $revenueResult->num_rows > 0) {
    $row = $revenueResult->fetch_assoc();
    $totalRevenue = $row['total'] ?? 0;
}

// Count Total Bookings by Status
$bookingCounts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
$countSql = "SELECT status, COUNT(*) AS count FROM bookings GROUP BY status";
$countResult = $conn->query($countSql);
if ($countResult && $countResult->num_rows > 0) {
    while($row = $countResult->fetch_assoc()){
        if(isset($bookingCounts[$row['status']])){
            $bookingCounts[$row['status']] = $row['count'];
        }
    }
}

// Count Total Users
$totalUsers = 0;
$userSql = "SELECT COUNT(*) AS total FROM users WHERE role = 'user'";
$userResult = $conn->query($userSql);
if ($userResult && $userResult->num_rows > 0) {
    $row = $userResult->fetch_assoc();
    $totalUsers = $row['total'] ?? 0;
}

// --- Fetch REAL Monthly Revenue Data for Bar Chart ---
$monthlyRevenueLabels = [];
$monthlyRevenueData = [];
$monthlySql = "SELECT
                   DATE_FORMAT(booking_date, '%Y-%m') AS month_year,
                   SUM(total_price) AS monthly_total
               FROM
                   bookings
               WHERE
                   status = 'completed'
                   AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
               GROUP BY
                   month_year
               ORDER BY
                   month_year ASC";

$monthlyResult = $conn->query($monthlySql);
if ($monthlyResult && $monthlyResult->num_rows > 0) {
    while ($row = $monthlyResult->fetch_assoc()) {
        $dateObj = DateTime::createFromFormat('Y-m', $row['month_year']);
        if ($dateObj) {
             $monthlyRevenueLabels[] = "Tháng " . $dateObj->format('n/Y');
             $monthlyRevenueData[] = (float)$row['monthly_total'];
        }
    }
}
if (empty($monthlyRevenueLabels)) {
    $monthlyRevenueLabels = ['Không có dữ liệu'];
    $monthlyRevenueData = [0];
}
$jsMonthlyLabels = json_encode($monthlyRevenueLabels);
$jsMonthlyData = json_encode($monthlyRevenueData);

// --- Prepare Data for Status Pie Chart ---
$statusLabels = array_keys($bookingCounts);
$statusData = array_values($bookingCounts);
$jsStatusLabels = json_encode($statusLabels);
$jsStatusData = json_encode($statusData);

// --- Fetch Data for Most Booked Fields Chart ---
$topFieldsLabels = [];
$topFieldsData = [];
$topFieldsSql = "SELECT
                     sf.name AS field_name,
                     COUNT(b.id) AS booking_count
                 FROM
                     bookings AS b
                 JOIN
                     sport_fields AS sf ON b.field_id = sf.id
                 GROUP BY
                     b.field_id, sf.name
                 ORDER BY
                     booking_count DESC
                 LIMIT 5"; // Get top 5 most booked fields

$topFieldsResult = $conn->query($topFieldsSql);
if ($topFieldsResult && $topFieldsResult->num_rows > 0) {
    while ($row = $topFieldsResult->fetch_assoc()) {
        $topFieldsLabels[] = $row['field_name'];
        $topFieldsData[] = (int)$row['booking_count'];
    }
} else {
    $topFieldsLabels = ['Không có dữ liệu'];
    $topFieldsData = [0];
}
$jsTopFieldsLabels = json_encode($topFieldsLabels);
$jsTopFieldsData = json_encode($topFieldsData);

$conn->close(); // Close connection after ALL queries
?>
<!DOCTYPE html>
<html>
<head>
    <title>Thống kê & Báo cáo</title>
    <style>
        body { font-family: sans-serif; background-color: #f8f9fa; color: #333; }
        .container { padding: 20px; max-width: 1100px; margin: 20px auto; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #0056b3; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        hr { border: 0; height: 1px; background-color: #eee; margin: 20px 0; }
        .stats-container { display: flex; justify-content: space-around; flex-wrap: wrap; margin-bottom: 30px;}
        .stat-box { border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 5px; background-color: #f9f9f9; text-align: center; flex-basis: calc(33% - 20px); box-sizing: border-box; }
        .stat-box h3 { margin-top: 0; color: #555; font-size: 1.1em;}
        .stat-value { font-size: 26px; font-weight: bold; color: #0056b3; margin: 10px 0;}
        .stat-box ul { list-style: none; padding: 0; margin: 10px 0 0 0; text-align: left; display: inline-block;}
        .stat-box li { margin-bottom: 5px; }
        .charts-row { display: flex; flex-wrap: wrap; justify-content: space-between; margin-top: 30px; }
        .chart-container { position: relative; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #fff; margin-bottom: 20px; box-sizing: border-box;}
        .chart-container canvas { max-width: 100%; }
        .full-width-chart { width: 100%; height: 400px; }
        .half-width-chart { width: calc(50% - 10px); height: 400px; }

        @media (max-width: 992px) {
            .half-width-chart { width: 100%; }
        }
        @media (max-width: 768px) {
            .stat-box { flex-basis: calc(50% - 20px); }
        }
        @media (max-width: 480px) {
            .stat-box { flex-basis: 100%; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Thống kê & Báo cáo</h1>
        <a href="../index.php">Quay lại Dashboard</a>
        <hr>

        <div class="stats-container">
            <div class="stat-box">
                <h3>Tổng Doanh Thu (Hoàn thành)</h3>
                <p class="stat-value"><?php echo number_format($totalRevenue, 0, ',', '.'); ?> đ</p>
            </div>
            <div class="stat-box">
                <h3>Tổng Số Đơn Đặt</h3>
                <ul>
                    <li>Chờ xác nhận: <?php echo $bookingCounts['pending']; ?></li>
                    <li>Đã xác nhận: <?php echo $bookingCounts['confirmed']; ?></li>
                    <li>Đã hoàn thành: <?php echo $bookingCounts['completed']; ?></li>
                    <li>Đã hủy: <?php echo $bookingCounts['cancelled']; ?></li>
                </ul>
            </div>
            <div class="stat-box">
                <h3>Tổng Số Người Dùng</h3>
                <p class="stat-value"><?php echo $totalUsers; ?></p>
            </div>
        </div>

        <hr>

        <div class="charts-row">
            <div class="chart-container full-width-chart">
                 <h2>Doanh thu Theo Tháng (12 tháng gần nhất)</h2>
                 <canvas id="revenueChart"></canvas>
            </div>
            <div class="chart-container half-width-chart">
                 <h2>Tỷ lệ Trạng thái Đơn Đặt</h2>
                 <canvas id="statusPieChart"></canvas>
            </div>
            <div class="chart-container half-width-chart">
                 <h2>Sân được đặt nhiều nhất (Top 5)</h2>
                 <canvas id="topFieldsChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // --- Bar Chart for Monthly Revenue ---
        const ctxBar = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?php echo $jsMonthlyLabels; ?>,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: <?php echo $jsMonthlyData; ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (typeof value === 'number') { return value.toLocaleString('vi-VN') + ' đ'; }
                                return value;
                            }
                        }
                    }
                },
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, title: { display: false } }
            }
        });

        // --- Pie Chart for Booking Status ---
        const ctxPie = document.getElementById('statusPieChart').getContext('2d');
        const statusPieChart = new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: <?php echo $jsStatusLabels; ?>,
                datasets: [{
                    label: 'Số lượng đơn',
                    data: <?php echo $jsStatusData; ?>,
                    backgroundColor: [
                        'rgba(255, 159, 64, 0.7)', // Orange
                        'rgba(75, 192, 192, 0.7)', // Teal
                        'rgba(54, 162, 235, 0.7)', // Blue
                        'rgba(255, 99, 132, 0.7)'  // Red
                    ],
                    borderColor: [ /* Colors */ ], borderWidth: 1
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' }, title: { display: false } }
            }
        });

         // --- Bar Chart for Top Fields ---
         const ctxTopFields = document.getElementById('topFieldsChart').getContext('2d');
            const topFieldsChart = new Chart(ctxTopFields, {
                type: 'bar',
                data: {
                    labels: <?php echo $jsTopFieldsLabels; ?>,
                    datasets: [{
                        label: 'Số lượt đặt',
                        data: <?php echo $jsTopFieldsData; ?>,
                        backgroundColor: 'rgba(153, 102, 255, 0.6)', // Purple
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y', // Horizontal bars
                    scales: { x: { beginAtZero: true } },
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, title: { display: false } }
                }
            });
    </script>

</body>
</html>