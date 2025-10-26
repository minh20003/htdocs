<?php
session_start();

// Check if admin is logged in, otherwise redirect to login page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: auth/login.php");
    exit;
}

require_once 'config/database.php';

// Get admin name from session
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Fetch dashboard statistics
$stats = [];

// Total Bookings
$result = $conn->query("SELECT COUNT(*) as total FROM bookings");
$stats['total_bookings'] = $result->fetch_assoc()['total'] ?? 0;

// Pending Bookings
$result = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
$stats['pending_bookings'] = $result->fetch_assoc()['total'] ?? 0;

// Total Revenue (completed bookings)
$result = $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE status = 'completed'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Total Users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['total_users'] = $result->fetch_assoc()['total'] ?? 0;

// Total Fields
$result = $conn->query("SELECT COUNT(*) as total FROM sport_fields");
$stats['total_fields'] = $result->fetch_assoc()['total'] ?? 0;

// Active Fields
$result = $conn->query("SELECT COUNT(*) as total FROM sport_fields WHERE status = 'active'");
$stats['active_fields'] = $result->fetch_assoc()['total'] ?? 0;

$conn->close();

$page_title = "Dashboard";
include 'includes/header.php';
?>

<style>
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

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 2rem;
    }

    .action-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        border-color: var(--primary);
        color: inherit;
    }

    .action-card-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
    }

    .action-card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        text-align: center;
    }

    .icon-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
    .icon-success { background: linear-gradient(135deg, #10b981, #059669); }
    .icon-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .icon-info { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .icon-danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
</style>

<div class="page-title">
    <i class="bi bi-speedometer2"></i>
    <span>Dashboard</span>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Tổng Đơn Đặt</p>
                <h3 class="stat-card-value"><?php echo number_format($stats['total_bookings']); ?></h3>
            </div>
            <div class="stat-card-icon icon-primary">
                <i class="bi bi-calendar-check"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Đơn Chờ Xử Lý</p>
                <h3 class="stat-card-value"><?php echo number_format($stats['pending_bookings']); ?></h3>
            </div>
            <div class="stat-card-icon icon-warning">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Tổng Doanh Thu</p>
                <h3 class="stat-card-value"><?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?> đ</h3>
            </div>
            <div class="stat-card-icon icon-success">
                <i class="bi bi-currency-dollar"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Tổng Người Dùng</p>
                <h3 class="stat-card-value"><?php echo number_format($stats['total_users']); ?></h3>
            </div>
            <div class="stat-card-icon icon-info">
                <i class="bi bi-people"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Tổng Số Sân</p>
                <h3 class="stat-card-value"><?php echo number_format($stats['total_fields']); ?></h3>
            </div>
            <div class="stat-card-icon icon-success">
                <i class="bi bi-geo-alt"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <p class="stat-card-title">Sân Đang Hoạt Động</p>
                <h3 class="stat-card-value"><?php echo number_format($stats['active_fields']); ?></h3>
            </div>
            <div class="stat-card-icon icon-success">
                <i class="bi bi-check-circle"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Chức năng quản lý</h5>
    </div>
    <div class="card-body p-4">
        <div class="quick-actions">
            <a href="fields/manage_fields.php" class="action-card">
                <div class="action-card-icon icon-primary">
                    <i class="bi bi-geo-alt"></i>
                </div>
                <h6 class="action-card-title">Quản lý Sân</h6>
            </a>

            <a href="fields/manage_pricing.php" class="action-card">
                <div class="action-card-icon icon-info">
                    <i class="bi bi-tag"></i>
                </div>
                <h6 class="action-card-title">Quản lý Giá & Khung Giờ</h6>
            </a>

            <a href="bookings/manage_bookings.php" class="action-card">
                <div class="action-card-icon icon-warning">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h6 class="action-card-title">Quản lý Đơn Đặt</h6>
            </a>

            <a href="users/manage_users.php" class="action-card">
                <div class="action-card-icon icon-success">
                    <i class="bi bi-people"></i>
                </div>
                <h6 class="action-card-title">Quản lý Người Dùng</h6>
            </a>

            <a href="stats/statistics.php" class="action-card">
                <div class="action-card-icon icon-danger">
                    <i class="bi bi-graph-up"></i>
                </div>
                <h6 class="action-card-title">Thống kê & Báo cáo</h6>
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
