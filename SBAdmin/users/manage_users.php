<?php
// Start output buffering to prevent any output before headers
ob_start();
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

$page_title = "Quản lý Người Dùng";
include '../includes/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .table-container {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow-x: auto;
    }

    .table {
        margin-bottom: 0;
        min-width: 900px;
    }

    .table thead {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .table thead th {
        border: none;
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }

    .table tbody td {
        padding: 1.25rem 1.5rem;
        vertical-align: middle;
        border-top: 1px solid #e2e8f0;
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    .badge-tier {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-block;
    }

    .tier-bronze {
        background-color: #fef3c7;
        color: #92400e;
    }

    .tier-silver {
        background-color: #e5e7eb;
        color: #374151;
    }

    .tier-gold {
        background-color: #fef3c7;
        color: #d97706;
    }

    .tier-platinum {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        border: none;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .btn-view {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }

    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #64748b;
    }

    .empty-state i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }
</style>

<div class="page-header">
    <h1 class="page-title mb-0">
        <i class="bi bi-people"></i>
        <span>Quản lý Người Dùng</span>
    </h1>
</div>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Họ Tên</th>
                <th>Email</th>
                <th>Số Điện Thoại</th>
                <th>Điểm Tích Lũy</th>
                <th>Hạng</th>
                <th>Ngày Đăng Ký</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>Chưa có người dùng nào.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($user['id']); ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                        <td><strong><?php echo number_format($user['loyalty_points']); ?></strong></td>
                        <td>
                            <span class="badge-tier tier-<?php echo strtolower($user['membership_tier']); ?>">
                                <?php echo ucfirst(htmlspecialchars($user['membership_tier'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <a href="view_user_bookings.php?user_id=<?php echo $user['id']; ?>" class="btn-sm btn-view">
                                <i class="bi bi-eye"></i> Xem Lịch sử
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
