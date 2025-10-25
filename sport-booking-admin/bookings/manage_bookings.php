<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/database.php';

// Fetch all bookings, joining with user and field info
$bookings = [];
// Select necessary fields and join tables
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
            b.created_at DESC"; // Show newest bookings first

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quản lý Đơn Đặt Sân</title>
    <style>
        /* Basic styling */
        body { font-family: sans-serif; }
        .container { padding: 20px; max-width: 1200px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: middle;} /* Align middle */
        th { background-color: #f2f2f2; }

        /* Action link styles */
        .action-links a {
            display: inline-block; /* Allows padding and margin */
            margin-right: 5px;
            margin-bottom: 5px; /* Spacing if links wrap */
            padding: 5px 10px; /* Padding for button look */
            border-radius: 4px; /* Rounded corners */
            text-decoration: none; /* Remove underline */
            color: white; /* White text */
            font-size: 0.9em;
            text-align: center;
            line-height: 1.5; /* Vertical alignment */
        }
        .confirm-link { background-color: #28a745; }
        .confirm-link:hover { background-color: #218838; }
        .reject-link { background-color: #dc3545; }
        .reject-link:hover { background-color: #c82333; }
        .view-link { background-color: #007bff; }
        .view-link:hover { background-color: #0056b3; }

        /* Status styles */
        .status-pending { color: orange; font-weight: bold; }
        .status-confirmed { color: green; font-weight: bold; }
        .status-completed { color: blue; }
        .status-cancelled { color: red; text-decoration: line-through; }
        .payment-paid { color: green; font-weight: bold;}
        .payment-unpaid { color: grey; }

        /* Message styles */
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Quản lý Đơn Đặt Sân</h1>

        <?php
        if (isset($_SESSION['manage_booking_success'])) {
            echo '<p class="message success">' . $_SESSION['manage_booking_success'] . '</p>';
            unset($_SESSION['manage_booking_success']);
        }
        if (isset($_SESSION['manage_booking_error'])) {
            echo '<p class="message error">' . $_SESSION['manage_booking_error'] . '</p>';
            unset($_SESSION['manage_booking_error']);
        }
        ?>

        <a href="../index.php">Quay lại Dashboard</a>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Sân</th>
                    <th>Người Đặt</th>
                    <th>Email</th>
                    <th>Ngày Đặt</th>
                    <th>Giờ Bắt Đầu</th>
                    <th>Tổng Tiền</th>
                    <th>Trạng Thái TT</th>
                    <th>Trạng Thái Đơn</th>
                    <th>Thời Gian Tạo</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center;">Chưa có đơn đặt sân nào.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                            <td><?php echo htmlspecialchars($booking['field_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['user_email']); ?></td>
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
                            <td class="action-links">
                                <?php if ($booking['booking_status'] == 'pending'): ?>
                                    <a href="update_booking_status.php?id=<?php echo $booking['booking_id']; ?>&status=confirmed" class="confirm-link" onclick="return confirm('Xác nhận đơn đặt này?');">Xác nhận</a>
                                    <a href="update_booking_status.php?id=<?php echo $booking['booking_id']; ?>&status=cancelled" class="reject-link" onclick="return confirm('Từ chối đơn đặt này?');">Từ chối</a>
                                <?php endif; ?>
                                <a href="view_booking.php?id=<?php echo $booking['booking_id']; ?>" class="view-link">Xem</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>