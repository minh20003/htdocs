<?php
session_start();
require_once '../config/database.php'; // Đường dẫn đến file config

// Kiểm tra đăng nhập admin (giữ nguyên)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_error'] = "Bạn cần đăng nhập.";
    header("Location: ../auth/login.php");
    exit;
}

// Kiểm tra phương thức POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu form (giữ nguyên)
    $name = trim($_POST['name'] ?? '');
    $sport_type = trim($_POST['sport_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'inactive');

    // --- XỬ LÝ UPLOAD ẢNH ---
    $uploadedImageNames = []; // Mảng để lưu tên các file ảnh đã upload thành công
    $uploadDir = '../uploads/fields/'; // Thư mục lưu ảnh (đi từ file hiện tại lên 1 cấp rồi vào uploads/fields)
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // Loại file ảnh cho phép

    // Tạo thư mục nếu chưa tồn tại
    if (!file_exists($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        // Ghi log lỗi thay vì chỉ báo cho người dùng
        error_log("Failed to create upload directory: " . $uploadDir);
        $_SESSION['add_field_error'] = "Lỗi server: Không thể tạo thư mục lưu ảnh.";
        header("Location: add_field.php");
        exit;
    }

    // Kiểm tra xem có file nào được gửi lên không
    if (isset($_FILES['field_images']) && is_array($_FILES['field_images']['name']) && !empty($_FILES['field_images']['name'][0])) {
        $totalFiles = count($_FILES['field_images']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            // Kiểm tra lỗi upload cơ bản
            if ($_FILES['field_images']['error'][$i] !== UPLOAD_ERR_OK) {
                 error_log("Upload error for file " . ($_FILES['field_images']['name'][$i] ?? 'unknown') . ": Error code " . $_FILES['field_images']['error'][$i]);
                 continue; // Bỏ qua file lỗi, xử lý file tiếp theo
            }

            $fileName = $_FILES['field_images']['name'][$i];
            $fileTmpName = $_FILES['field_images']['tmp_name'][$i];
            $fileSize = $_FILES['field_images']['size'][$i];
            $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Kiểm tra loại file
            if (in_array($fileType, $allowedTypes)) {
                // Kiểm tra kích thước (ví dụ: tối đa 5MB)
                if ($fileSize < 5 * 1024 * 1024) {
                    // Tạo tên file mới duy nhất để tránh trùng lặp
                    $newFileName = uniqid('field_', true) . '.' . $fileType;
                    $uploadPath = $uploadDir . $newFileName;

                    // Di chuyển file vào thư mục uploads
                    if (move_uploaded_file($fileTmpName, $uploadPath)) {
                        $uploadedImageNames[] = $newFileName; // Thêm tên file mới vào mảng
                        error_log("Successfully uploaded: " . $newFileName); // Ghi log thành công
                    } else {
                        error_log("Failed to move uploaded file: " . $fileName . " to " . $uploadPath);
                    }
                } else {
                     error_log("File too large: " . $fileName . " (" . $fileSize . " bytes)");
                     $_SESSION['add_field_error'] = "Lỗi: File '" . htmlspecialchars($fileName) . "' quá lớn (tối đa 5MB).";
                     // Không exit ngay, cho phép xử lý các file khác
                }
            } else {
                 error_log("Invalid file type: " . $fileName . " (" . $fileType . ")");
                 $_SESSION['add_field_error'] = "Lỗi: File '" . htmlspecialchars($fileName) . "' không phải định dạng ảnh cho phép.";
            }
        } // Kết thúc vòng lặp for
    } else {
         error_log("No files uploaded or upload error."); // Ghi log nếu không có file
    }
    // Chuyển mảng tên file ảnh thành chuỗi JSON để lưu vào DB (chỉ khi có ảnh)
    $imagesJson = !empty($uploadedImageNames) ? json_encode($uploadedImageNames) : null;
    // --- KẾT THÚC XỬ LÝ UPLOAD ẢNH ---


    // Validation dữ liệu khác (giữ nguyên)
    if (empty($name) || empty($sport_type) || empty($status)) {
        $_SESSION['add_field_error'] = "Tên sân, loại sân, và trạng thái là bắt buộc.";
        header("Location: add_field.php"); exit; // Dừng nếu thiếu thông tin cơ bản
    }
    $allowed_sport_types = ['football', 'badminton', 'tennis', 'basketball'];
    $allowed_statuses = ['active', 'maintenance', 'inactive'];
    if (!in_array($sport_type, $allowed_sport_types) || !in_array($status, $allowed_statuses)) {
         $_SESSION['add_field_error'] = "Loại sân hoặc trạng thái không hợp lệ.";
         header("Location: add_field.php"); exit;
    }

    // Prepare SQL - Thêm cột 'images'
    // Cần đảm bảo bảng sport_fields đã có cột 'images' kiểu TEXT
    $stmt = $conn->prepare("INSERT INTO sport_fields (name, sport_type, address, description, status, images) VALUES (?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
         error_log("Prepare failed (add field): (" . $conn->errno . ") " . $conn->error);
         $_SESSION['add_field_error'] = "Lỗi hệ thống khi chuẩn bị thêm sân.";
         // Xóa ảnh đã upload nếu prepare lỗi
         foreach ($uploadedImageNames as $imgName) { if (file_exists($uploadDir . $imgName)) { unlink($uploadDir . $imgName); } }
         header("Location: add_field.php");
         exit;
    }

    // Bind parameters - Thêm 's' cho cột images (kiểu string/TEXT)
    $stmt->bind_param("ssssss", $name, $sport_type, $address, $description, $status, $imagesJson);

    // Execute (giữ nguyên)
    if ($stmt->execute()) {
        $_SESSION['add_field_success'] = "Thêm sân '" . htmlspecialchars($name) . "' thành công!";
        header("Location: manage_fields.php");
        exit;
    } else {
        error_log("Execute failed (add field): (" . $stmt->errno . ") " . $stmt->error);
        $_SESSION['add_field_error'] = "Không thể thêm sân vào cơ sở dữ liệu.";
        // Xóa ảnh đã upload nếu insert DB thất bại
        foreach ($uploadedImageNames as $imgName) { if (file_exists($uploadDir . $imgName)) { unlink($uploadDir . $imgName); } }
        header("Location: add_field.php");
        exit;
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: add_field.php");
    exit;
}
?>