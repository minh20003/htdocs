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

// Fetch all bookings, joining with user and field info
$bookings = [];
$sql = "SELECT
            b.id AS booking_id,
            b.booking_date,
            b.time_slot_start,
            b.total_price,
            b.status AS booking_status,
            b.payment_status,
            b.created_at AS booking_time,
            u.full_name AS user_name,
            u.email AS user_email,
            sf.name AS field_name
        FROM
            bookings AS b
        JOIN
            users AS u ON b.user_id = u.id
        JOIN
            sport_fields AS sf ON b.field_id = sf.id
        ORDER BY
            b.created_at DESC";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
$conn->close();

$page_title = "Quản lý Đơn Đặt";
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
        min-width: 1000px;
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

    .badge-status {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-block;
    }

    .status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-confirmed {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .status-completed {
        background-color: #d1fae5;
        color: #065f46;
    }

    .status-cancelled {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .payment-paid {
        background-color: #d1fae5;
        color: #065f46;
    }

    .payment-unpaid {
        background-color: #e5e7eb;
        color: #374151;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
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

    .btn-confirm {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .btn-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }

    .btn-reject {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .btn-reject:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        color: white;
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
        <i class="bi bi-calendar-check"></i>
        <span>Quản lý Đơn Đặt</span>
    </h1>
</div>

<?php
if (isset($_SESSION['manage_booking_success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>' . $_SESSION['manage_booking_success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['manage_booking_success']);
}
if (isset($_SESSION['manage_booking_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['manage_booking_error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['manage_booking_error']);
}
?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên Sân</th>
                <th>Người Đặt</th>
                <th>Email</th>
                <th>Ngày Đặt</th>
                <th>Giờ Bắt Đầu</th>
                <th>Tổng Tiền</th>
                <th>TT Thanh Toán</th>
                <th>Trạng Thái</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="10" class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>Chưa có đơn đặt sân nào.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($booking['booking_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($booking['field_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['user_email']); ?></td>
                        <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                        <td><?php echo htmlspecialchars(substr($booking['time_slot_start'], 0, 5)); ?></td>
                        <td><strong><?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ</strong></td>
                        <td>
                            <span class="badge-status payment-<?php echo htmlspecialchars($booking['payment_status']); ?>">
                                <?php echo ($booking['payment_status'] == 'paid') ? 'Đã TT' : 'Chưa TT'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status status-<?php echo htmlspecialchars($booking['booking_status']); ?>">
                                <?php
                                $statusMap = [
                                    'pending' => 'Chờ xác nhận',
                                    'confirmed' => 'Đã xác nhận',
                                    'completed' => 'Hoàn thành',
                                    'cancelled' => 'Đã hủy'
                                ];
                                echo $statusMap[$booking['booking_status']] ?? ucfirst($booking['booking_status']);
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($booking['booking_status'] == 'pending'): ?>
                                    <a href="update_booking_status.php?id=<?php echo $booking['booking_id']; ?>&status=confirmed" 
                                       class="btn-sm btn-confirm"
                                       onclick="return confirm('Xác nhận đơn đặt này?');">
                                        <i class="bi bi-check-circle"></i> Xác nhận
                                    </a>
                                    <a href="update_booking_status.php?id=<?php echo $booking['booking_id']; ?>&status=cancelled" 
                                       class="btn-sm btn-reject"
                                       onclick="return confirm('Từ chối đơn đặt này?');">
                                        <i class="bi bi-x-circle"></i> Từ chối
                                    </a>
                                <?php elseif ($booking['booking_status'] == 'confirmed'): ?>
                                    <a href="update_booking_status.php?id=<?php echo $booking['booking_id']; ?>&status=completed" 
                                       class="btn-sm btn-confirm"
                                       onclick="return confirm('Đánh dấu đơn này là đã hoàn thành?');">
                                        <i class="bi bi-check-all"></i> Hoàn thành
                                    </a>
                                <?php endif; ?>
                                <a href="view_booking.php?id=<?php echo $booking['booking_id']; ?>" class="btn-sm btn-view">
                                    <i class="bi bi-eye"></i> Xem
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
