<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data
    $field_id = trim($_POST['field_id'] ?? '');
    $time_slot = trim($_POST['time_slot'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $day_of_week = trim($_POST['day_of_week'] ?? 'all');
    // Checkbox sends '1' if checked, otherwise it's not sent. Use isset()
    $is_peak_hour = isset($_POST['is_peak_hour']) ? 1 : 0; // Store as 1 (true) or 0 (false)

    // Basic Validation
    if (empty($field_id) || !is_numeric($field_id) || empty($time_slot) || empty($price) || !is_numeric($price) || $price < 0 || empty($day_of_week)) {
        $_SESSION['pricing_error'] = "Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.";
        header("Location: edit_field_pricing.php?field_id=" . urlencode($field_id));
        exit;
    }

    // Validate day_of_week
    $allowed_days = ['all', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    if (!in_array($day_of_week, $allowed_days)) {
        $_SESSION['pricing_error'] = "Ngày áp dụng không hợp lệ.";
        header("Location: edit_field_pricing.php?field_id=" . urlencode($field_id));
        exit;
    }

    // Prepare SQL to insert
    $stmt = $conn->prepare("INSERT INTO field_prices (field_id, time_slot, price, day_of_week, is_peak_hour) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare failed (add price): (" . $conn->errno . ") " . $conn->error);
        $_SESSION['pricing_error'] = "Lỗi hệ thống khi chuẩn bị thêm giá.";
        header("Location: edit_field_pricing.php?field_id=" . urlencode($field_id));
        exit;
    }

    // Bind parameters (i=integer, s=string, d=double/decimal, i=integer(boolean))
    $stmt->bind_param("isdsi", $field_id, $time_slot, $price, $day_of_week, $is_peak_hour);

    // Execute
    if ($stmt->execute()) {
        $_SESSION['pricing_success'] = "Thêm khung giờ thành công!";
    } else {
        error_log("Execute failed (add price): (" . $stmt->errno . ") " . $stmt->error);
        // Check for duplicate entry error (unique key violation) if you have constraints
        if ($stmt->errno == 1062) { // Error code for duplicate entry
             $_SESSION['pricing_error'] = "Khung giờ cho ngày này đã tồn tại.";
        } else {
            $_SESSION['pricing_error'] = "Không thể thêm khung giờ. Lỗi: " . $stmt->error;
        }
    }

    $stmt->close();
    $conn->close();

} else {
    // If not POST, redirect back
     $_SESSION['pricing_error'] = "Yêu cầu không hợp lệ.";
}

// Redirect back to the pricing page for the specific field
header("Location: edit_field_pricing.php?field_id=" . urlencode($field_id));
exit;
?>