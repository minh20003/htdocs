<?php
// Bắt đầu session ở đầu mỗi trang cần xác thực
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel'; ?></title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --light: #f8fafc;
            --sidebar-width: 260px;
            --navbar-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            padding-top: var(--navbar-height);
            transition: all 0.3s ease;
        }

        /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            height: var(--navbar-height);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 0 2rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            font-size: 1.8rem;
        }

        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar-text {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
        }

        .btn-logout {
            background-color: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-logout:hover {
            background-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: var(--navbar-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--navbar-height));
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            overflow-y: auto;
            z-index: 999;
            transition: all 0.3s ease;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .sidebar-menu {
            padding: 1.5rem 0;
        }

        .sidebar-menu .nav-item {
            margin: 0.25rem 1rem;
        }

        .sidebar-menu .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: #64748b;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
            position: relative;
        }

        .sidebar-menu .nav-link i {
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-menu .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--primary);
            transform: translateX(5px);
        }

        .sidebar-menu .nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .sidebar-menu .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: white;
            border-radius: 0 4px 4px 0;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: calc(100vh - var(--navbar-height));
            transition: all 0.3s ease;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
        }

        .card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem 1.5rem;
            border-radius: 16px 16px 0 0 !important;
            font-weight: 600;
            color: var(--dark);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1001;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            body {
                padding-top: 0;
            }

            .navbar {
                position: relative;
            }
        }

        /* Page Title */
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            color: var(--primary);
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
        }

        /* Badges */
        .badge {
            padding: 0.5rem 0.875rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
<?php // Chỉ hiển thị Navbar và Sidebar nếu admin đã đăng nhập
  if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true):
      $current_page = basename($_SERVER['PHP_SELF']); // Lấy tên file hiện tại
      
      // Tính toán base_url dựa trên đường dẫn hiện tại
      $script_path = dirname($_SERVER['PHP_SELF']);
      if (strpos($script_path, '/fields') !== false || strpos($script_path, '/bookings') !== false || 
          strpos($script_path, '/users') !== false || strpos($script_path, '/stats') !== false) {
          $base_url = '../';
      } else {
          $base_url = '';
      }
?>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $base_url; ?>index.php">
                <i class="bi bi-shield-check"></i>
                <span>Admin Panel</span>
            </a>
            <button class="navbar-toggler d-md-none" type="button" onclick="toggleSidebar()" style="border: none; background: rgba(255,255,255,0.2); padding: 0.5rem; border-radius: 6px;">
                <i class="bi bi-list text-white" style="font-size: 1.5rem;"></i>
            </button>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="navbar-text">
                            <i class="bi bi-person-circle me-2"></i>
                            Chào, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>!
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-logout" href="<?php echo $base_url; ?>auth/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="d-flex">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-menu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>index.php">
                            <i class="bi bi-house-door"></i>
                            <span>Tổng quan</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/fields/') !== false && strpos($_SERVER['REQUEST_URI'], 'pricing') === false) ? 'active' : ''; ?>" href="<?php echo $base_url; ?>fields/manage_fields.php">
                            <i class="bi bi-geo-alt"></i>
                            <span>Quản lý Sân</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'pricing') !== false) ? 'active' : ''; ?>" href="<?php echo $base_url; ?>fields/manage_pricing.php">
                            <i class="bi bi-cash-stack"></i>
                            <span>Quản lý Giá</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/bookings/') !== false) ? 'active' : ''; ?>" href="<?php echo $base_url; ?>bookings/manage_bookings.php">
                            <i class="bi bi-calendar-check"></i>
                            <span>Quản lý Đơn đặt</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/users/') !== false) ? 'active' : ''; ?>" href="<?php echo $base_url; ?>users/manage_users.php">
                            <i class="bi bi-people"></i>
                            <span>Quản lý Người dùng</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/stats/') !== false) ? 'active' : ''; ?>" href="<?php echo $base_url; ?>stats/statistics.php">
                            <i class="bi bi-graph-up"></i>
                            <span>Thống kê</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="main-content flex-grow-1">
<?php else: ?>
    <main class="main-content flex-grow-1">
<?php endif; ?>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}
</script>