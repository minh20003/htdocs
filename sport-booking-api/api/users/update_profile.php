<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST"); // Use POST for updates
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Includes
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#"; // Nhớ dùng key bí mật của bạn
$jwt = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if ($authHeader) {
    $arr = explode(" ", $authHeader);
    $jwt = $arr[1];
}

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        $user_id = $decoded->data->id;

        // Lấy dữ liệu từ body request
        $data = json_decode(file_get_contents("php://input"));

        // Kiểm tra dữ liệu đầu vào (ít nhất phải có tên)
        if (!empty($data->full_name)) {
            $full_name = $data->full_name;
            // Cho phép số điện thoại có thể rỗng
            $phone = $data->phone ?? ''; 

            // Câu lệnh UPDATE
            $query = "UPDATE users SET full_name = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $full_name, $phone, $user_id);

            // Thực thi
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(array("message" => "Cập nhật thông tin thành công."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Không thể cập nhật thông tin.", "error" => $stmt->error));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Dữ liệu không đầy đủ (thiếu tên)."));
        }

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(array("message" => "Truy cập bị từ chối.", "error" => $e->getMessage()));
    }
} else {
    http_response_code(401);
    echo json_encode(array("message" => "Yêu cầu xác thực."));
}
?>