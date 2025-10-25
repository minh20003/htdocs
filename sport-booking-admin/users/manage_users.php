<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/database.php';

// Fetch all regular users
$users = [];
$sql = "SELECT id, full_name, email, phone, loyalty_points, membership_tier, created_at
        FROM users
        WHERE role = 'user'
        ORDER BY created_at DESC";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quản lý Người Dùng</title>
    <style>
        /* Basic styling - similar to other manage pages */
        body { font-family: sans-serif; }
        .container { padding: 20px; max-width: 1100px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-links a { margin-right: 10px; text-decoration: none; } /* Add styles later if needed */
    </style>
</head>
<body>
    <div class="container">
        <h1>Quản lý Người Dùng</h1>
        <a href="../index.php">Quay lại Dashboard</a>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Họ Tên</th>
                    <th>Email</th>
                    <th>Số Điện Thoại</th>
                    <th>Điểm Tích Lũy</th>
                    <th>Hạng</th>
                    <th>Ngày Đăng Ký</th>
                    </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Chưa có người dùng nào.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['loyalty_points']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($user['membership_tier'])); ?></td>
                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>