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
// If status is 'completed', also update payment_status to 'paid'
if ($new_status == 'completed') {
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = 'paid' WHERE id = ? AND (status = 'pending' OR status = 'confirmed')");
} else {
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND (status = 'pending' OR status = 'confirmed')");
}

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
    if ($stmt->affected_rows > 0) {
        $_SESSION['manage_booking_success'] = "Cập nhật thành công!";
        
        // GỬI NOTIFICATION CHO USER
        require_once '../config/database.php';
        
        // Lấy thông tin user từ booking
        $get_user_query = "SELECT b.user_id, u.fcm_token, u.full_name 
                          FROM bookings b 
                          LEFT JOIN users u ON b.user_id = u.id 
                          WHERE b.id = ?";
        $get_user_stmt = $conn->prepare($get_user_query);
        $get_user_stmt->bind_param("i", $booking_id);
        $get_user_stmt->execute();
        $user_result = $get_user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_row = $user_result->fetch_assoc();
            $user_id = $user_row['user_id'];
            $fcm_token = $user_row['fcm_token'];
            
            // Xác định title và message dựa vào status
            if ($new_status == 'confirmed') {
                $notif_title = "Đơn đặt sân đã được xác nhận!";
                $notif_message = "Đơn đặt #$booking_id của bạn đã được xác nhận. Hãy đến sân đúng giờ nhé!";
            } elseif ($new_status == 'cancelled') {
                $notif_title = "Đơn đặt sân đã bị hủy";
                $notif_message = "Đơn đặt #$booking_id của bạn đã bị hủy. Vui lòng liên hệ để biết thêm chi tiết.";
            } elseif ($new_status == 'completed') {
                $notif_title = "Đơn đặt sân hoàn thành";
                $notif_message = "Cảm ơn bạn đã sử dụng dịch vụ! Hãy đánh giá sân để giúp chúng tôi cải thiện.";
            }
            
            // Lưu vào database
            $notif_query = "INSERT INTO notifications (user_id, type, title, message, data) 
                           VALUES (?, 'booking', ?, ?, ?)";
            $notif_stmt = $conn->prepare($notif_query);
            $notif_data = json_encode(['booking_id' => $booking_id]);
            $notif_stmt->bind_param("isss", $user_id, $notif_title, $notif_message, $notif_data);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Gửi FCM nếu có token
            if (!empty($fcm_token)) {
                // Include hàm sendFCMNotificationV1 từ join.php hoặc tạo file riêng
                // sendFCMNotificationV1($fcm_token, $notif_title, $notif_message, $booking_id);
            }
        }
        $get_user_stmt->close();

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