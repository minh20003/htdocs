<?php
session_start();

// Check if admin is logged in, otherwise redirect to login page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: auth/login.php");
    exit;
}

// Get admin name from session
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .header { background-color: #333; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .header a { color: #fff; text-decoration: none; padding: 5px 10px; background-color: #dc3545; border-radius: 3px; }
        .header a:hover { background-color: #c82333; }
        .content { padding: 20px; }
        .content h2 { margin-top: 0; }
        /* Add more styles later for dashboard elements */
    </style>
</head>
<body>
    <div class="header">
        <h1>Admin Dashboard</h1>
        <span>Chào mừng, <?php echo htmlspecialchars($admin_name); ?>!</span>
        <a href="auth/logout.php">Logout</a>
    </div>

    <div class="content">
        <h2>Tổng quan</h2>
        <p>Đây là trang quản trị. Các chức năng quản lý sẽ được thêm vào đây.</p>

        <hr>
        <h3>Quản lý</h3>
        <ul>
            <li><a href="fields/manage_fields.php">Quản lý Sân Thể Thao</a></li>
        <li><a href="fields/manage_pricing.php">Quản lý Giá & Khung Giờ</a></li> <li><a href="bookings/manage_bookings.php">Quản lý Đơn Đặt Sân</a></li>
        <li><a href="stats/statistics.php">Thống kê & Báo cáo</a></li>
        <li><a href="users/manage_users.php">Quản lý Người Dùng</a></li>
            </ul>
        </div>

</body>
</html>