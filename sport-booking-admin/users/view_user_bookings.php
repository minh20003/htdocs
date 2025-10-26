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

// Get User ID from URL
$user_id_to_view = $_GET['user_id'] ?? null;
if (!$user_id_to_view || !is_numeric($user_id_to_view)) {
    $_SESSION['manage_user_error'] = "ID người dùng không hợp lệ.";
    header("Location: manage_users.php");
    exit;
}

// Fetch user's name for display
$user_name_to_view = "Người dùng không xác định";
$user_email = "";
$stmt_user = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
if($stmt_user){
    $stmt_user->bind_param("i", $user_id_to_view);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if($user_row = $result_user->fetch_assoc()){
        $user_name_to_view = $user_row['full_name'];
        $user_email = $user_row['email'];
    }
    $stmt_user->close();
}

// Fetch bookings for this specific user
$bookings = [];
$sql = "SELECT
            b.id AS booking_id, b.booking_date, b.time_slot_start, b.time_slot_end, b.total_price,
            b.status AS booking_status, b.payment_status, b.created_at AS booking_time,
            sf.name AS field_name
        FROM bookings AS b
        JOIN sport_fields AS sf ON b.field_id = sf.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $user_id_to_view);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
    } else {
        error_log("Error fetching user bookings: " . $stmt->error);
        $_SESSION['user_booking_error'] = "Lỗi khi tải lịch sử đặt sân.";
    }
    $stmt->close();
} else {
     error_log("Error preparing user bookings query: " . $conn->error);
     $_SESSION['user_booking_error'] = "Lỗi hệ thống khi chuẩn bị tải lịch sử.";
}

$conn->close();

$page_title = "Lịch sử Đặt sân";
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

    .user-info-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }

    .user-info-card h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .user-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .info-label {
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 500;
    }

    .info-value {
        font-size: 1rem;
        color: #1e293b;
        font-weight: 600;
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

    .btn-back {
        background: #64748b;
        color: white;
        text-decoration: none;
        padding: 0.625rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-back:hover {
        background: #475569;
        color: white;
        transform: translateY(-2px);
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
        <i class="bi bi-clock-history"></i>
        <span>Lịch sử Đặt sân</span>
    </h1>
    <a href="manage_users.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>Quay lại danh sách
    </a>
</div>

<div class="user-info-card">
    <h3>
        <i class="bi bi-person-circle"></i>
        Thông tin Người dùng
    </h3>
    <div class="user-info-grid">
        <div class="info-item">
            <span class="info-label">Họ Tên</span>
            <span class="info-value"><?php echo htmlspecialchars($user_name_to_view); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Email</span>
            <span class="info-value"><?php echo htmlspecialchars($user_email); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">ID Người dùng</span>
            <span class="info-value">#<?php echo htmlspecialchars($user_id_to_view); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Tổng số đơn</span>
            <span class="info-value"><?php echo count($bookings); ?> đơn</span>
        </div>
    </div>
</div>

<?php
if (isset($_SESSION['user_booking_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['user_booking_error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['user_booking_error']);
}
?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID Đơn</th>
                <th>Tên Sân</th>
                <th>Ngày Đặt</th>
                <th>Khung Giờ</th>
                <th>Tổng Tiền</th>
                <th>TT Thanh Toán</th>
                <th>Trạng Thái</th>
                <th>Thời Gian Tạo</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>Người dùng này chưa có đơn đặt sân nào.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($booking['booking_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($booking['field_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                        <td>
                            <?php 
                            $start_time = substr($booking['time_slot_start'], 0, 5);
                            $end_time = isset($booking['time_slot_end']) ? substr($booking['time_slot_end'], 0, 5) : '';
                            echo $start_time . ($end_time ? ' - ' . $end_time : '');
                            ?>
                        </td>
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
                        <td><?php echo date('d/m/Y H:i', strtotime($booking['booking_time'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
