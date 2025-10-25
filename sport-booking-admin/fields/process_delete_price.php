<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Get IDs from URL
$price_id = $_GET['id'] ?? null;
$field_id = $_GET['field_id'] ?? null; // Needed for redirecting back

// Validate IDs
if (empty($price_id) || !is_numeric($price_id) || empty($field_id) || !is_numeric($field_id)) {
    $_SESSION['pricing_error'] = "ID không hợp lệ để xóa.";
    // Try to redirect back if field_id is known, otherwise go to manage pricing index
    $redirect_url = $field_id ? "edit_field_pricing.php?field_id=" . urlencode($field_id) : "manage_pricing.php";
    header("Location: " . $redirect_url);
    exit;
}

// Prepare SQL to delete
$stmt = $conn->prepare("DELETE FROM field_prices WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed (delete price): (" . $conn->errno . ") " . $conn->error);
    $_SESSION['pricing_error'] = "Lỗi hệ thống khi chuẩn bị xóa giá.";
    header("Location: edit_field_pricing.php?field_id=" . urlencode($field_id));
    exit;
}

$stmt->bind_param("i", $price_id);

// Execute
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $_SESSION['pricing_success'] = "Xóa khung giờ thành công!";
    } else {
        $_SESSION['pricing_error'] = "Không tìm thấy khung giờ để xóa.";
    }
} else {
    error_log("Execute failed (delete price): (" . $stmt->errno . ") " . $stmt->error);
    $_SESSION['pricing_error'] = "Không thể xóa khung giờ. Lỗi: " . $stmt->error;
}

$stmt->close();
$conn->close();

// Redirect back to the pricing page for the specific field
header("Location: edit_field_pricing.php?field_id=" . urlencode($field_id));
exit;
?>
