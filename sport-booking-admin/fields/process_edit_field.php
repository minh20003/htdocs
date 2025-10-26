<?php
session_start();
require_once '../config/database.php'; // Đường dẫn đến file config

// Bật ghi log lỗi
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_error'] = "Bạn cần đăng nhập để thực hiện hành động này.";
    header("Location: ../auth/login.php");
    exit;
}

// Kiểm tra phương thức POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu form
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $sport_type = trim($_POST['sport_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // --- XỬ LÝ UPLOAD ẢNH MỚI ---
    $newUploadedImageNames = [];
    $uploadDir = '../uploads/fields/';
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Tạo thư mục nếu chưa tồn tại (phòng trường hợp bị xóa)
    if (!file_exists($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        error_log("Failed to create upload directory: " . $uploadDir);
        $_SESSION['edit_field_error'] = "Lỗi server: Không thể tạo thư mục lưu ảnh.";
        header("Location: edit_field.php?id=" . urlencode($id));
        exit;
    }

    if (isset($_FILES['field_images']) && is_array($_FILES['field_images']['name']) && !empty($_FILES['field_images']['name'][0])) {
        $totalFiles = count($_FILES['field_images']['name']);
        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['field_images']['error'][$i] === UPLOAD_ERR_OK) {
                 $fileName = basename($_FILES['field_images']['name'][$i]); // Lấy tên file an toàn
                 $fileTmpName = $_FILES['field_images']['tmp_name'][$i];
                 $fileSize = $_FILES['field_images']['size'][$i];
                 $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                 if (in_array($fileType, $allowedTypes) && $fileSize < 5 * 1024 * 1024) { // Max 5MB
                     $newFileName = uniqid('field_', true) . '.' . $fileType;
                     $uploadPath = $uploadDir . $newFileName;
                     if (move_uploaded_file($fileTmpName, $uploadPath)) {
                         $newUploadedImageNames[] = $newFileName;
                         error_log("Edit: Successfully uploaded: " . $newFileName);
                     } else { error_log("Edit: Failed move " . $fileName . " to " . $uploadPath); }
                 } else { error_log("Edit: Invalid file type/size " . $fileName); }
            } elseif ($_FILES['field_images']['error'][$i] !== UPLOAD_ERR_NO_FILE) { // Bỏ qua lỗi "không có file"
                error_log("Edit: Upload error " . $_FILES['field_images']['error'][$i] . " for " . ($fileName ?? 'unknown'));
            }
        }
    }
    // --- KẾT THÚC XỬ LÝ UPLOAD ẢNH MỚI ---

    // Validation dữ liệu khác
    if (empty($id) || !is_numeric($id) || empty($name) || empty($sport_type) || empty($status)) {
        $_SESSION['edit_field_error'] = "ID, Tên sân, loại sân, và trạng thái là bắt buộc.";
        header("Location: edit_field.php?id=" . urlencode($id));
        exit;
    }
    $allowed_sport_types = ['football', 'badminton', 'tennis', 'basketball'];
    $allowed_statuses = ['active', 'maintenance', 'inactive'];
    if (!in_array($sport_type, $allowed_sport_types) || !in_array($status, $allowed_statuses)) {
         $_SESSION['edit_field_error'] = "Loại sân hoặc trạng thái không hợp lệ.";
         header("Location: edit_field.php?id=" . urlencode($id));
         exit;
    }

    // --- LẤY DANH SÁCH ẢNH CŨ VÀ GỘP VỚI ẢNH MỚI ---
    $imagesJsonToSave = null;
    $conn->begin_transaction(); // Bắt đầu transaction
    try {
        // Lấy chuỗi JSON ảnh hiện tại từ DB (khóa để cập nhật)
        $stmt_get = $conn->prepare("SELECT images FROM sport_fields WHERE id = ? FOR UPDATE");
        if(!$stmt_get) throw new Exception("Prepare failed (get images): " . $conn->error);
        $stmt_get->bind_param("i", $id);
        if(!$stmt_get->execute()) throw new Exception("Execute failed (get images): " . $stmt_get->error);
        $result_get = $stmt_get->get_result();
        $existingImagesJson = null;
        if($row_get = $result_get->fetch_assoc()){
            $existingImagesJson = $row_get['images'];
        } else {
             throw new Exception("Field with ID $id not found for update."); // Không tìm thấy sân
        }
        $stmt_get->close();

        // Decode JSON cũ thành mảng
        $existingImageNames = $existingImagesJson ? json_decode($existingImagesJson, true) : [];
        if (!is_array($existingImageNames)) { $existingImageNames = []; }

        // Gộp mảng ảnh cũ và ảnh mới
        $allImageNames = array_merge($existingImageNames, $newUploadedImageNames);

        // Encode lại thành JSON để lưu (chỉ lưu nếu có ảnh)
        $imagesJsonToSave = !empty($allImageNames) ? json_encode(array_values($allImageNames)) : null;

        // Prepare SQL - Cập nhật cả cột 'images'
        $stmt = $conn->prepare("UPDATE sport_fields SET name = ?, sport_type = ?, address = ?, description = ?, status = ?, images = ? WHERE id = ?");

        // <<-- XỬ LÝ LỖI PREPARE ĐẦY ĐỦ -->>
        if (!$stmt) {
             throw new Exception("Prepare failed (edit field): " . $conn->error);
        }

        // Bind parameters - Thêm 's' cho images, 'i' cho id ở cuối
        if (!$stmt->bind_param("ssssssi", $name, $sport_type, $address, $description, $status, $imagesJsonToSave, $id)) {
             throw new Exception("Binding parameters failed: " . $stmt->error);
        }


        // Execute
        if ($stmt->execute()) {
            $conn->commit(); // Hoàn tất transaction thành công
            $_SESSION['manage_field_success'] = "Cập nhật sân '" . htmlspecialchars($name) . "' thành công!";
            $stmt->close();
            $conn->close();
            header("Location: manage_fields.php");
            exit;
        } else {
             throw new Exception("Execute failed (edit field): " . $stmt->error);
        }

    } catch (Exception $e) {
        $conn->rollback(); // Hoàn tác nếu có lỗi
        error_log("Error in process_edit_field.php: " . $e->getMessage());
        $_SESSION['edit_field_error'] = "Không thể cập nhật sân. Lỗi: " . $e->getMessage();
        // Xóa ảnh MỚI đã upload nếu có lỗi xảy ra
        foreach ($newUploadedImageNames as $imgName) { if (file_exists($uploadDir . $imgName)) { unlink($uploadDir . $imgName); } }
        // Đóng statement nếu đã mở
        if (isset($stmt_get) && $stmt_get instanceof mysqli_stmt) $stmt_get->close();
        if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
        $conn->close();
        header("Location: edit_field.php?id=" . urlencode($id));
        exit;
    }

} else {
    // Nếu không phải POST, chuyển hướng về trang danh sách
    header("Location: manage_fields.php");
    exit;
}
?>