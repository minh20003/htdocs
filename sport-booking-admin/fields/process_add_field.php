<?php
session_start();
require_once '../config/database.php'; // Go up one level for config

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // If not logged in, perhaps set an error and redirect to login
    $_SESSION['login_error'] = "Bạn cần đăng nhập để thực hiện hành động này.";
    header("Location: ../auth/login.php");
    exit;
}

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize data (basic sanitization)
    $name = trim($_POST['name'] ?? '');
    $sport_type = trim($_POST['sport_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'inactive'); // Default to inactive if not provided

    // Basic Validation
    if (empty($name) || empty($sport_type) || empty($status)) {
        $_SESSION['add_field_error'] = "Tên sân, loại sân, và trạng thái là bắt buộc.";
        header("Location: add_field.php"); // Redirect back to the form
        exit;
    }

    // Validate sport_type and status against allowed values (important for ENUM)
    $allowed_sport_types = ['football', 'badminton', 'tennis', 'basketball'];
    $allowed_statuses = ['active', 'maintenance', 'inactive'];
    if (!in_array($sport_type, $allowed_sport_types) || !in_array($status, $allowed_statuses)) {
         $_SESSION['add_field_error'] = "Loại sân hoặc trạng thái không hợp lệ.";
         header("Location: add_field.php");
         exit;
    }

    // Prepare SQL statement to insert data
    $stmt = $conn->prepare("INSERT INTO sport_fields (name, sport_type, address, description, status) VALUES (?, ?, ?, ?, ?)");

    if (!$stmt) {
        // Handle prepare error
        error_log("Prepare failed (add field): (" . $conn->errno . ") " . $conn->error);
        $_SESSION['add_field_error'] = "Lỗi hệ thống khi chuẩn bị thêm sân.";
        header("Location: add_field.php");
        exit;
    }

    // Bind parameters (s = string)
    // Adjust types if your columns are different (e.g., i for integer)
    $stmt->bind_param("sssss", $name, $sport_type, $address, $description, $status);

    // Execute the statement
    if ($stmt->execute()) {
        $_SESSION['add_field_success'] = "Thêm sân '" . htmlspecialchars($name) . "' thành công!";
        header("Location: manage_fields.php"); // Redirect to the list after success
        exit;
    } else {
        // Handle execution error
        error_log("Execute failed (add field): (" . $stmt->errno . ") " . $stmt->error);
        $_SESSION['add_field_error'] = "Không thể thêm sân vào cơ sở dữ liệu.";
        header("Location: add_field.php");
        exit;
    }

    $stmt->close();
    $conn->close();

} else {
    // If not a POST request, redirect back to the form or list
    header("Location: add_field.php");
    exit;
}
?>