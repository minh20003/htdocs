<?php
// Start output buffering to prevent any output before headers
ob_start();
session_start();
// Check login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/database.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$conn->query("SET time_zone = '+07:00'");

// --- HANDLE TIME FILTERS ---
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Build WHERE clause for date range (applies to COMPLETED bookings)
$date_condition = "";
$date_params = [];
$date_types = "";
if (!empty($start_date) && !empty($end_date)) {
    $date_condition = "AND booking_date BETWEEN ? AND ?";
    $date_params = [$start_date, $end_date];
    $date_types = "ss";
} elseif (!empty($start_date)) {
    $date_condition = "AND booking_date >= ?";
    $date_params = [$start_date];
    $date_types = "s";
} elseif (!empty($end_date)) {
    $date_condition = "AND booking_date <= ?";
    $date_params = [$end_date];
    $date_types = "s";
}

// Calculate Total Revenue (Completed)
$totalRevenue = 0;
$revenueSql = "SELECT SUM(total_price) AS total FROM bookings WHERE status = 'completed' {$date_condition}";
$stmt_revenue = $conn->prepare($revenueSql);
if ($stmt_revenue && !empty($date_params)) {
    $stmt_revenue->bind_param($date_types, ...$date_params);
}
if ($stmt_revenue && $stmt_revenue->execute()) {
    $revenueResult = $stmt_revenue->get_result();
    if ($row = $revenueResult->fetch_assoc()) {
        $totalRevenue = $row['total'] ?? 0;
    }
    $stmt_revenue->close();
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
                 LIMIT 5";

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

$conn->close();

$page_title = "Thống kê & Báo cáo";
include '../includes/header.php';
?>

<style>
    .filter-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .filter-form {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-weight: 600;
        color: #475569;
        font-size: 0.875rem;
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.625rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        outline: none;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .stat-card-title {
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 500;
        margin: 0;
    }

    .stat-card-value {
        font-size: 2rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .stat-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .stat-list li {
        padding: 0.5rem 0;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
    }

    .stat-list li:last-child {
        border-bottom: none;
    }

    .chart-container {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
    }

    .chart-container h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
    }

    .chart-wrapper {
        position: relative;
        height: 400px;
    }

    .full-width {
        grid-column: 1 / -1;
    }

    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
    }

    @media (max-width: 768px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <h1 class="page-title mb-0">
        <i class="bi bi-graph-up"></i>
        <span>Thống kê & Báo cáo</span>
    </h1>
</div>

<div class="filter-card">
    <form method="get" action="statistics.php" class="filter-form">
        <div class="form-group">
            <label for="start_date">Từ ngày:</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="end_date">Đến ngày:</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel me-2"></i>Lọc
            </button>
        </div>
        <div class="form-group">
            <a href="statistics.php" class="btn btn-secondary" style="background: #64748b; color: white; text-decoration: none; padding: 0.625rem 1.5rem; border-radius: 10px;">
                <i class="bi bi-arrow-counterclockwise me-2"></i>Xem tất cả
            </a>
        </div>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Tổng Doanh Thu</p>
                <h3 class="stat-card-value"><?php echo number_format($totalRevenue, 0, ',', '.'); ?> đ</h3>
            </div>
            <div class="stat-card-icon icon-success">
                <i class="bi bi-currency-dollar"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Tổng Số Đơn Đặt</p>
                <h3 class="stat-card-value"><?php echo array_sum($bookingCounts); ?></h3>
            </div>
            <div class="stat-card-icon icon-primary">
                <i class="bi bi-calendar-check"></i>
            </div>
        </div>
        <ul class="stat-list">
            <li><span>Chờ xác nhận:</span> <strong><?php echo $bookingCounts['pending']; ?></strong></li>
            <li><span>Đã xác nhận:</span> <strong><?php echo $bookingCounts['confirmed']; ?></strong></li>
            <li><span>Đã hoàn thành:</span> <strong><?php echo $bookingCounts['completed']; ?></strong></li>
            <li><span>Đã hủy:</span> <strong><?php echo $bookingCounts['cancelled']; ?></strong></li>
        </ul>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Tổng Số Người Dùng</p>
                <h3 class="stat-card-value"><?php echo $totalUsers; ?></h3>
            </div>
            <div class="stat-card-icon icon-info">
                <i class="bi bi-people"></i>
            </div>
        </div>
    </div>
</div>

<div class="charts-grid">
    <div class="chart-container full-width">
        <h3>Doanh thu Theo Tháng (12 tháng gần nhất)</h3>
        <div class="chart-wrapper">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <div class="chart-container">
        <h3>Tỷ lệ Trạng thái Đơn Đặt</h3>
        <div class="chart-wrapper">
            <canvas id="statusPieChart"></canvas>
        </div>
    </div>

    <div class="chart-container">
        <h3>Sân được đặt nhiều nhất (Top 5)</h3>
        <div class="chart-wrapper">
            <canvas id="topFieldsChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
    // Bar Chart for Monthly Revenue
    const ctxBar = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?php echo $jsMonthlyLabels; ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?php echo $jsMonthlyData; ?>,
                backgroundColor: 'rgba(99, 102, 241, 0.6)',
                borderColor: 'rgba(99, 102, 241, 1)',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (typeof value === 'number') {
                                return value.toLocaleString('vi-VN') + ' đ';
                            }
                            return value;
                        }
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Pie Chart for Booking Status
    const ctxPie = document.getElementById('statusPieChart').getContext('2d');
    const statusLabelsTranslated = {
        'pending': 'Chờ xác nhận',
        'confirmed': 'Đã xác nhận',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy'
    };
    const originalLabels = <?php echo $jsStatusLabels; ?>;
    const translatedLabels = originalLabels.map(label => statusLabelsTranslated[label] || label);
    
    const statusPieChart = new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: translatedLabels,
            datasets: [{
                label: 'Số lượng đơn',
                data: <?php echo $jsStatusData; ?>,
                backgroundColor: [
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(239, 68, 68, 0.7)'
                ],
                borderColor: [
                    'rgba(245, 158, 11, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Bar Chart for Top Fields
    const ctxTopFields = document.getElementById('topFieldsChart').getContext('2d');
    const topFieldsChart = new Chart(ctxTopFields, {
        type: 'bar',
        data: {
            labels: <?php echo $jsTopFieldsLabels; ?>,
            datasets: [{
                label: 'Số lượt đặt',
                data: <?php echo $jsTopFieldsData; ?>,
                backgroundColor: 'rgba(139, 92, 246, 0.6)',
                borderColor: 'rgba(139, 92, 246, 1)',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            scales: {
                x: { beginAtZero: true }
            },
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
