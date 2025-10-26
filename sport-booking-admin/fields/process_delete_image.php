<?php
session_start();
require_once '../config/database.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Lấy thông tin từ URL
$field_id = $_GET['field_id'] ?? null;
$image_name_to_delete = $_GET['image_name'] ?? null;
$uploadDir = '../uploads/fields/';

// Validate
if (empty($field_id) || !is_numeric($field_id) || empty($image_name_to_delete)) {
    $_SESSION['pricing_error'] = "Thông tin không hợp lệ để xóa ảnh."; // Reuse session key
    header("Location: edit_field_pricing.php?field_id=" . urlencode($field_id)); // Redirect back
    exit;
}

$conn->begin_transaction();
try {
    // Lấy danh sách ảnh hiện tại (khóa để cập nhật)
    $stmt_get = $conn->prepare("SELECT images FROM sport_fields WHERE id = ? FOR UPDATE");
    if (!$stmt_get) throw new Exception("Prepare failed (get images): " . $conn->error);
    $stmt_get->bind_param("i", $field_id);
    if (!$stmt_get->execute()) throw new Exception("Execute failed (get images): " . $stmt_get->error);
    $result_get = $stmt_get->get_result();
    $existingImagesJson = null;
    if ($row_get = $result_get->fetch_assoc()) {
        $existingImagesJson = $row_get['images'];
    } else {
        throw new Exception("Field not found.");
    }
    $stmt_get->close();

    // Decode JSON thành mảng
    $imageNames = $existingImagesJson ? json_decode($existingImagesJson, true) : [];
    if (!is_array($imageNames)) { $imageNames = []; }

    // Tìm và xóa tên file ảnh khỏi mảng
    $key = array_search($image_name_to_delete, $imageNames);
    if ($key !== false) {
        unset($imageNames[$key]); // Xóa khỏi mảng

        // Encode lại mảng thành JSON
        $newImagesJson = !empty($imageNames) ? json_encode(array_values($imageNames)) : null; // array_values để reset keys

        // Cập nhật lại database
        $stmt_update = $conn->prepare("UPDATE sport_fields SET images = ? WHERE id = ?");
        if (!$stmt_update) throw new Exception("Prepare failed (update images): " . $conn->error);
        $stmt_update->bind_param("si", $newImagesJson, $field_id);
        if (!$stmt_update->execute()) throw new Exception("Execute failed (update images): " . $stmt_update->error);
        $stmt_update->close();

        // Nếu cập nhật DB thành công, xóa file ảnh vật lý
        $filePath = $uploadDir . $image_name_to_delete;
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                 error_log("Successfully deleted image file: " . $filePath);
            } else {
                 error_log("Failed to delete image file: " . $filePath);
                 // Có thể không cần báo lỗi nghiêm trọng nếu DB đã cập nhật
            }
        } else {
             error_log("Image file not found for deletion: " . $filePath);
        }

        $conn->commit(); // Hoàn tất transaction
        $_SESSION['pricing_success'] = "Xóa ảnh thành công!"; // Reuse session key

    } else {
        // Không tìm thấy tên ảnh trong danh sách JSON (có thể đã bị xóa)
        $conn->rollback(); // Không cần commit vì không có thay đổi DB
        $_SESSION['pricing_error'] = "Không tìm thấy ảnh cần xóa trong danh sách.";
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error deleting image: " . $e->getMessage());
    $_SESSION['pricing_error'] = "Lỗi khi xóa ảnh: " . $e->getMessage();
    // Đóng các statement nếu đã mở
    if (isset($stmt_get) && $stmt_get instanceof mysqli_stmt) $stmt_get->close();
    if (isset($stmt_update) && $stmt_update instanceof mysqli_stmt) $stmt_update->close();
} finally {
    $conn->close();
}

// Chuyển hướng về trang edit
header("Location: edit_field_pricing.php?field_id=" . urlencode($field_id));
exit;
