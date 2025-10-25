<?php
// Các header cần thiết
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Yêu cầu file kết nối database
require_once '../../config/database.php';

// === BƯỚC KIỂM TRA 1: KẾT NỐI DATABASE ===
if (!$conn) {
    http_response_code(500);
    echo json_encode(array("message" => "Lỗi: Không thể kết nối đến cơ sở dữ liệu."));
    exit(); // Dừng chương trình ngay lập tức
}

// Lấy dữ liệu được gửi đến API
$data = json_decode(file_get_contents("php://input"));

// Kiểm tra xem dữ liệu có đầy đủ không
if (!empty($data->full_name) && !empty($data->email) && !empty($data->password)) {
    $full_name = $data->full_name;
    $email = $data->email;
    $password = password_hash($data->password, PASSWORD_BCRYPT);

    $query = "INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);

    // === BƯỚC KIỂM TRA 2: CÂU LỆNH SQL PREPARE ===
    if (!$stmt) {
        http_response_code(500);
        // Báo lỗi chi tiết từ database
        echo json_encode(array("message" => "Lỗi khi chuẩn bị câu lệnh SQL.", "error" => $conn->error));
        exit();
    }
    
    $stmt->bind_param("sss", $full_name, $email, $password);

    // Thực thi câu lệnh và kiểm tra lỗi chi tiết
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array("message" => "Đăng ký tài khoản thành công."));
    } else {
        http_response_code(503);
        // === BƯỚC KIỂM TRA 3: BÁO LỖI KHI THỰC THI ===
        // Báo lỗi chi tiết từ statement
        echo json_encode(array("message" => "Không thể đăng ký.", "error" => $stmt->error));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Đăng ký không thành công. Dữ liệu không đầy đủ."));
}
?>