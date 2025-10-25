<?php
session_start();
require_once '../config/database.php'; // Go up one level for config

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_error'] = "Bạn cần đăng nhập để thực hiện hành động này.";
    header("Location: ../auth/login.php");
    exit;
}

// Get the field ID from the URL parameter
$field_id = $_GET['id'] ?? null;

// Basic Validation
if (empty($field_id) || !is_numeric($field_id)) {
    $_SESSION['manage_field_error'] = "ID sân không hợp lệ để xóa.";
    header("Location: manage_fields.php");
    exit;
}

// Prepare SQL statement to delete the field
// Note: Associated bookings might need to be handled depending on requirements (ON DELETE CASCADE is set in the DB script)
$stmt = $conn->prepare("DELETE FROM sport_fields WHERE id = ?");

if (!$stmt) {
    // Handle prepare error
    error_log("Prepare failed (delete field): (" . $conn->errno . ") " . $conn->error);
    $_SESSION['manage_field_error'] = "Lỗi hệ thống khi chuẩn bị xóa sân.";
    header("Location: manage_fields.php");
    exit;
}

// Bind parameter (i = integer)
$stmt->bind_param("i", $field_id);

// Execute the statement
if ($stmt->execute()) {
    // Check if any row was actually deleted
    if ($stmt->affected_rows > 0) {
        $_SESSION['manage_field_success'] = "Xóa sân thành công!";
    } else {
        $_SESSION['manage_field_error'] = "Không tìm thấy sân để xóa (có thể đã bị xóa trước đó).";
    }
} else {
    // Handle execution error
    error_log("Execute failed (delete field): (" . $stmt->errno . ") " . $stmt->error);
    $_SESSION['manage_field_error'] = "Không thể xóa sân khỏi cơ sở dữ liệu. Lỗi: " . $stmt->error;
}

$stmt->close();
$conn->close();

// Redirect back to the manage fields page regardless of outcome
header("Location: manage_fields.php");
exit;
?>