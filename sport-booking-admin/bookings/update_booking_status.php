<?php
session_start();
require_once '../config/database.php'; // Go up one level for config

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_error'] = "Bạn cần đăng nhập để thực hiện hành động này.";
    header("Location: ../auth/login.php");
    exit;
}

// Get parameters from the URL
$booking_id = $_GET['id'] ?? null;
$new_status = $_GET['status'] ?? null;

// Validate input
$allowed_statuses = ['confirmed', 'cancelled', 'completed']; // Allowed target statuses
if (empty($booking_id) || !is_numeric($booking_id) || empty($new_status) || !in_array($new_status, $allowed_statuses)) {
    $_SESSION['manage_booking_error'] = "Yêu cầu cập nhật trạng thái không hợp lệ.";
    header("Location: manage_bookings.php");
    exit;
}

// Prepare SQL statement to update the status
// Allow update if status is 'pending' (to confirm/cancel) OR 'confirmed' (to complete/cancel)
$stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND (status = 'pending' OR status = 'confirmed')");

if (!$stmt) {
    // Handle prepare error
    error_log("Prepare failed (update booking status): (" . $conn->errno . ") " . $conn->error);
    $_SESSION['manage_booking_error'] = "Lỗi hệ thống khi chuẩn bị cập nhật.";
    header("Location: manage_bookings.php");
    exit;
}

// Bind parameters (s = string for status, i = integer for id)
$stmt->bind_param("si", $new_status, $booking_id);

// Execute the statement
if ($stmt->execute()) {
    // Check if any row was actually updated (prevents updating already completed/cancelled bookings with the same status)
    if ($stmt->affected_rows > 0) {
        $_SESSION['manage_booking_success'] = "Cập nhật trạng thái đơn đặt #" . htmlspecialchars($booking_id) . " thành '" . htmlspecialchars($new_status) . "' thành công!";

        // TODO: (Optional but Recommended) Send notification to user about status change
        // You would need to:
        // 1. Fetch the user_id and fcm_token associated with this booking_id.
        // 2. Use the sendFCMNotification function (similar to the one in teammates/join.php)
        //    to send a notification about the status update.

    } else {
        $_SESSION['manage_booking_error'] = "Không thể cập nhật trạng thái đơn đặt #" . htmlspecialchars($booking_id) . ". Đơn có thể không ở trạng thái 'pending'/'confirmed', không tồn tại, hoặc trạng thái mới giống trạng thái cũ.";
    }
} else {
    // Handle execution error
    error_log("Execute failed (update booking status): (" . $stmt->errno . ") " . $stmt->error);
    $_SESSION['manage_booking_error'] = "Không thể cập nhật trạng thái. Lỗi: " . htmlspecialchars($stmt->error);
}

// Close statement and connection
$stmt->close();
$conn->close();

// Redirect back to the manage bookings page
header("Location: manage_bookings.php");
exit;
?>