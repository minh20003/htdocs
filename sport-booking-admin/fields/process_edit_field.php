<?php
session_start();
require_once '../config/database.php'; // Go up one level for config

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_error'] = "Bạn cần đăng nhập để thực hiện hành động này.";
    header("Location: ../auth/login.php");
    exit;
}

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize data
    $id = trim($_POST['id'] ?? ''); // Get the hidden ID
    $name = trim($_POST['name'] ?? '');
    $sport_type = trim($_POST['sport_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // Basic Validation
    if (empty($id) || !is_numeric($id) || empty($name) || empty($sport_type) || empty($status)) {
        $_SESSION['edit_field_error'] = "ID, Tên sân, loại sân, và trạng thái là bắt buộc.";
        // Redirect back to edit form with the ID if possible
        header("Location: edit_field.php?id=" . urlencode($id));
        exit;
    }

    // Validate sport_type and status against allowed values
    $allowed_sport_types = ['football', 'badminton', 'tennis', 'basketball'];
    $allowed_statuses = ['active', 'maintenance', 'inactive'];
    if (!in_array($sport_type, $allowed_sport_types) || !in_array($status, $allowed_statuses)) {
         $_SESSION['edit_field_error'] = "Loại sân hoặc trạng thái không hợp lệ.";
         header("Location: edit_field.php?id=" . urlencode($id));
         exit;
    }

    // Prepare SQL statement to update data
    $stmt = $conn->prepare("UPDATE sport_fields SET name = ?, sport_type = ?, address = ?, description = ?, status = ? WHERE id = ?");

    if (!$stmt) {
        // Handle prepare error
        error_log("Prepare failed (edit field): (" . $conn->errno . ") " . $conn->error);
        $_SESSION['edit_field_error'] = "Lỗi hệ thống khi chuẩn bị cập nhật sân.";
        header("Location: edit_field.php?id=" . urlencode($id));
        exit;
    }

    // Bind parameters (s = string, i = integer)
    $stmt->bind_param("sssssi", $name, $sport_type, $address, $description, $status, $id);

    // Execute the statement
    if ($stmt->execute()) {
        $_SESSION['manage_field_success'] = "Cập nhật sân '" . htmlspecialchars($name) . "' thành công!"; // Message for the list page
        header("Location: manage_fields.php"); // Redirect to the list after success
        exit;
    } else {
        // Handle execution error
        error_log("Execute failed (edit field): (" . $stmt->errno . ") " . $stmt->error);
        $_SESSION['edit_field_error'] = "Không thể cập nhật sân vào cơ sở dữ liệu.";
        header("Location: edit_field.php?id=" . urlencode($id)); // Redirect back to edit form
        exit;
    }

    $stmt->close();
    $conn->close();

} else {
    // If not a POST request, redirect back to the list
    header("Location: manage_fields.php");
    exit;
}
?>