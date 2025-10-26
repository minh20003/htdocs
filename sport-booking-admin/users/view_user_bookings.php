<?php
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
    $_SESSION['manage_user_error'] = "ID người dùng không hợp lệ."; // Message for user list page
    header("Location: manage_users.php");
    exit;
}

// Fetch user's name for display
$user_name_to_view = "Người dùng không xác định";
$stmt_user = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
if($stmt_user){
    $stmt_user->bind_param("i", $user_id_to_view);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if($user_row = $result_user->fetch_assoc()){
        $user_name_to_view = $user_row['full_name'];
    }
    $stmt_user->close();
}


// Fetch bookings for this specific user
$bookings = [];
$sql = "SELECT
            b.id AS booking_id, b.booking_date, b.time_slot_start, b.total_price,
            b.status AS booking_status, b.payment_status, b.created_at AS booking_time,
            sf.name AS field_name
        FROM bookings AS b
        JOIN sport_fields AS sf ON b.field_id = sf.id
        WHERE b.user_id = ? -- Filter by user_id
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $user_id_to_view); // Bind the user ID
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
    } else {
        error_log("Error fetching user bookings: " . $stmt->error);
        $_SESSION['user_booking_error'] = "Lỗi khi tải lịch sử đặt sân."; // Error message for this page
    }
    $stmt->close();
} else {
     error_log("Error preparing user bookings query: " . $conn->error);
     $_SESSION['user_booking_error'] = "Lỗi hệ thống khi chuẩn bị tải lịch sử.";
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lịch sử Đặt sân của <?php echo htmlspecialchars($user_name_to_view); ?></title>
    <style>
        /* Reuse styles from manage_bookings.php */
        body { font-family: sans-serif; }
        .container { padding: 20px; max-width: 1000px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-pending { color: orange; font-weight: bold; }
        .status-confirmed { color: green; font-weight: bold; }
        .status-completed { color: blue; }
        .status-cancelled { color: red; text-decoration: line-through; }
        .payment-paid { color: green; }
        .payment-unpaid { color: grey; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Lịch sử Đặt sân của: <?php echo htmlspecialchars($user_name_to_view); ?> (ID: <?php echo htmlspecialchars($user_id_to_view); ?>)</h1>
        <a href="manage_users.php">Quay lại danh sách người dùng</a> | <a href="../index.php">Dashboard</a>
        <hr>

         <?php
        // Display errors if any
        if (isset($_SESSION['user_booking_error'])) {
            echo '<p class="error">' . $_SESSION['user_booking_error'] . '</p>';
            unset($_SESSION['user_booking_error']);
        }
        ?>

        <table>
            <thead>
                <tr>
                    <th>ID Đơn</th>
                    <th>Tên Sân</th>
                    <th>Ngày Đặt</th>
                    <th>Giờ Bắt Đầu</th>
                    <th>Tổng Tiền</th>
                    <th>Trạng Thái TT</th>
                    <th>Trạng Thái Đơn</th>
                    <th>Thời Gian Tạo</th>
                    </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">Người dùng này chưa có đơn đặt sân nào.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                            <td><?php echo htmlspecialchars($booking['field_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                            <td><?php echo htmlspecialchars(substr($booking['time_slot_start'], 0, 5)); ?></td>
                            <td><?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ</td>
                            <td>
                                <span class="payment-<?php echo htmlspecialchars($booking['payment_status']); ?>">
                                    <?php echo ($booking['payment_status'] == 'paid') ? 'Đã TT' : 'Chưa TT'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-<?php echo htmlspecialchars($booking['booking_status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($booking['booking_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($booking['booking_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>