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
            b.*, -- Select all columns from bookings table
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chi tiết Đơn đặt #<?php echo htmlspecialchars($booking['id']); ?></title>
    <style>
        body { font-family: sans-serif; }
        .container { padding: 20px; max-width: 700px; margin: auto; }
        .detail-item { margin-bottom: 10px; }
        .detail-item strong { display: inline-block; min-width: 150px; }
        .status-pending { color: orange; font-weight: bold; }
        .status-confirmed { color: green; font-weight: bold; }
        .status-completed { color: blue; }
        .status-cancelled { color: red; text-decoration: line-through; }
        .payment-paid { color: green; }
        .payment-unpaid { color: grey; }
        .action-buttons a { margin-right: 10px; text-decoration: none; padding: 8px 12px; border-radius: 4px; color: white; }
        .confirm-btn { background-color: #28a745; }
        .reject-btn { background-color: #dc3545; }
        .complete-btn { background-color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Chi tiết Đơn đặt #<?php echo htmlspecialchars($booking['id']); ?></h1>
        <a href="manage_bookings.php">Quay lại danh sách</a>
        <hr>

        <div class="detail-item"><strong>ID Đơn:</strong> <?php echo htmlspecialchars($booking['id']); ?></div>
        <div class="detail-item"><strong>Thời gian tạo:</strong> <?php echo htmlspecialchars($booking['created_at']); ?></div>
        <hr>
        <h3>Thông tin Sân</h3>
        <div class="detail-item"><strong>Tên sân:</strong> <?php echo htmlspecialchars($booking['field_name']); ?></div>
        <div class="detail-item"><strong>Loại sân:</strong> <?php echo ucfirst(htmlspecialchars($booking['sport_type'])); ?></div>
        <div class="detail-item"><strong>Địa chỉ sân:</strong> <?php echo htmlspecialchars($booking['field_address']); ?></div>
        <hr>
        <h3>Thông tin Người đặt</h3>
        <div class="detail-item"><strong>Tên:</strong> <?php echo htmlspecialchars($booking['user_name']); ?></div>
        <div class="detail-item"><strong>Email:</strong> <?php echo htmlspecialchars($booking['user_email']); ?></div>
        <div class="detail-item"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($booking['user_phone'] ?? 'Chưa cung cấp'); ?></div>
        <hr>
        <h3>Chi tiết Đặt sân</h3>
        <div class="detail-item"><strong>Ngày đặt:</strong> <?php echo htmlspecialchars($booking['booking_date']); ?></div>
        <div class="detail-item"><strong>Khung giờ:</strong> <?php echo htmlspecialchars(substr($booking['time_slot_start'], 0, 5)) . ' - ' . htmlspecialchars(substr($booking['time_slot_end'], 0, 5)); ?></div>
        <div class="detail-item"><strong>Tổng tiền:</strong> <?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ</div>
        <div class="detail-item"><strong>Phương thức TT:</strong> <?php echo ucfirst(htmlspecialchars($booking['payment_method'])); ?></div>
        <div class="detail-item"><strong>Trạng thái TT:</strong> <span class="payment-<?php echo $booking['payment_status']; ?>"><?php echo ($booking['payment_status'] == 'paid') ? 'Đã thanh toán' : 'Chưa thanh toán'; ?></span></div>
        <div class="detail-item"><strong>Mã giao dịch VNPay:</strong> <?php echo htmlspecialchars($booking['vnpay_transaction_id'] ?? 'N/A'); ?></div>
        <div class="detail-item"><strong>Trạng thái Đơn:</strong> <span class="status-<?php echo $booking['booking_status']; ?>"><?php echo ucfirst(htmlspecialchars($booking['booking_status'])); ?></span></div>
        <div class="detail-item"><strong>Ghi chú:</strong> <?php echo nl2br(htmlspecialchars($booking['notes'] ?? 'Không có')); ?></div>
        <hr>

        <h3>Hành động</h3>
        <div class="action-buttons">
            <?php if ($booking['booking_status'] == 'pending'): ?>
                <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=confirmed" class="confirm-btn" onclick="return confirm('Xác nhận đơn đặt này?');">Xác nhận</a>
                <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=cancelled" class="reject-btn" onclick="return confirm('Từ chối đơn đặt này?');">Từ chối</a>
            <?php elseif ($booking['booking_status'] == 'confirmed'): ?>
                 <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=completed" class="complete-btn" onclick="return confirm('Đánh dấu đơn này là đã hoàn thành?');">Đánh dấu Hoàn thành</a>
                 <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=cancelled" class="reject-btn" onclick="return confirm('Hủy đơn đặt đã xác nhận này?');">Hủy đơn</a>
            <?php else: ?>
                <p>Không có hành động nào cho trạng thái này.</p>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>