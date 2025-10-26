<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/database.php';

// Get Booking ID from URL
$booking_id = $_GET['id'] ?? null;
if (!$booking_id || !is_numeric($booking_id)) {
    $_SESSION['manage_booking_error'] = "ID đơn đặt không hợp lệ.";
    header("Location: manage_bookings.php");
    exit;
}

// Fetch booking details along with user and field info
$booking = null;
$sql = "SELECT
            b.*,
            u.full_name AS user_name,
            u.email AS user_email,
            u.phone AS user_phone,
            sf.name AS field_name,
            sf.address AS field_address,
            sf.sport_type
        FROM
            bookings AS b
        JOIN
            users AS u ON b.user_id = u.id
        JOIN
            sport_fields AS sf ON b.field_id = sf.id
        WHERE
            b.id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $booking = $result->fetch_assoc();
    }
    $stmt->close();
}

$conn->close();

// Redirect if booking not found
if ($booking === null) {
    $_SESSION['manage_booking_error'] = "Không tìm thấy đơn đặt sân với ID này.";
    header("Location: manage_bookings.php");
    exit;
}

$page_title = "Chi tiết Đơn đặt";
include '../includes/header.php';
?>

<style>
    .detail-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
    }

    .detail-card h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        padding: 1rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .detail-label {
        font-weight: 600;
        color: #64748b;
        flex: 0 0 200px;
    }

    .detail-value {
        color: #1e293b;
        flex: 1;
        text-align: right;
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
        gap: 1rem;
        flex-wrap: wrap;
    }

    .btn-sm {
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.875rem;
        border: none;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
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

    .btn-complete {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }

    .btn-complete:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
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

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
</style>

<div class="page-header">
    <h1 class="page-title mb-0">
        <i class="bi bi-eye"></i>
        <span>Chi tiết Đơn đặt #<?php echo htmlspecialchars($booking['id']); ?></span>
    </h1>
    <a href="manage_bookings.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>Quay lại danh sách
    </a>
</div>

<div class="detail-card">
    <h3><i class="bi bi-info-circle me-2"></i>Thông tin Đơn</h3>
    <div class="detail-item">
        <span class="detail-label">ID Đơn:</span>
        <span class="detail-value"><strong>#<?php echo htmlspecialchars($booking['id']); ?></strong></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Thời gian tạo:</span>
        <span class="detail-value"><?php echo htmlspecialchars($booking['created_at']); ?></span>
    </div>
</div>

<div class="detail-card">
    <h3><i class="bi bi-geo-alt me-2"></i>Thông tin Sân</h3>
    <div class="detail-item">
        <span class="detail-label">Tên sân:</span>
        <span class="detail-value"><strong><?php echo htmlspecialchars($booking['field_name']); ?></strong></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Loại sân:</span>
        <span class="detail-value"><?php echo ucfirst(htmlspecialchars($booking['sport_type'])); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Địa chỉ sân:</span>
        <span class="detail-value"><?php echo htmlspecialchars($booking['field_address']); ?></span>
    </div>
</div>

<div class="detail-card">
    <h3><i class="bi bi-person me-2"></i>Thông tin Người đặt</h3>
    <div class="detail-item">
        <span class="detail-label">Tên:</span>
        <span class="detail-value"><strong><?php echo htmlspecialchars($booking['user_name']); ?></strong></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Email:</span>
        <span class="detail-value"><?php echo htmlspecialchars($booking['user_email']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Số điện thoại:</span>
        <span class="detail-value"><?php echo htmlspecialchars($booking['user_phone'] ?? 'Chưa cung cấp'); ?></span>
    </div>
</div>

<div class="detail-card">
    <h3><i class="bi bi-calendar3 me-2"></i>Chi tiết Đặt sân</h3>
    <div class="detail-item">
        <span class="detail-label">Ngày đặt:</span>
        <span class="detail-value"><strong><?php echo htmlspecialchars($booking['booking_date']); ?></strong></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Khung giờ:</span>
        <span class="detail-value"><?php echo htmlspecialchars(substr($booking['time_slot_start'], 0, 5)) . ' - ' . htmlspecialchars(substr($booking['time_slot_end'], 0, 5)); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Tổng tiền:</span>
        <span class="detail-value"><strong style="color: var(--success); font-size: 1.1rem;"><?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ</strong></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Phương thức TT:</span>
        <span class="detail-value"><?php echo ucfirst(htmlspecialchars($booking['payment_method'])); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Trạng thái TT:</span>
        <span class="detail-value">
            <span class="badge-status payment-<?php echo $booking['payment_status']; ?>">
                <?php echo ($booking['payment_status'] == 'paid') ? 'Đã thanh toán' : 'Chưa thanh toán'; ?>
            </span>
        </span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Mã giao dịch VNPay:</span>
        <span class="detail-value"><?php echo htmlspecialchars($booking['vnpay_transaction_id'] ?? 'N/A'); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Trạng thái Đơn:</span>
        <span class="detail-value">
            <span class="badge-status status-<?php echo $booking['booking_status']; ?>">
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
        </span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Ghi chú:</span>
        <span class="detail-value"><?php echo nl2br(htmlspecialchars($booking['notes'] ?? 'Không có')); ?></span>
    </div>
</div>

<div class="detail-card">
    <h3><i class="bi bi-gear me-2"></i>Hành động</h3>
    <div class="action-buttons">
        <?php if ($booking['booking_status'] == 'pending'): ?>
            <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=confirmed" 
               class="btn-sm btn-confirm"
               onclick="return confirm('Xác nhận đơn đặt này?');">
                <i class="bi bi-check-circle"></i> Xác nhận
            </a>
            <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=cancelled" 
               class="btn-sm btn-reject"
               onclick="return confirm('Từ chối đơn đặt này?');">
                <i class="bi bi-x-circle"></i> Từ chối
            </a>
        <?php elseif ($booking['booking_status'] == 'confirmed'): ?>
            <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=completed" 
               class="btn-sm btn-complete"
               onclick="return confirm('Đánh dấu đơn này là đã hoàn thành?');">
                <i class="bi bi-check-all"></i> Đánh dấu Hoàn thành
            </a>
            <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=cancelled" 
               class="btn-sm btn-reject"
               onclick="return confirm('Hủy đơn đặt đã xác nhận này?');">
                <i class="bi bi-x-circle"></i> Hủy đơn
            </a>
        <?php else: ?>
            <p class="text-muted">Không có hành động nào cho trạng thái này.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
