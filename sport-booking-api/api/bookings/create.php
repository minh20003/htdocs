<?php
// Các header cần thiết
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Yêu cầu các file cần thiết
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// --- BƯỚC 1: XÁC THỰC NGƯỜI DÙNG ---
$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#"; // Phải giống hệt key trong file login.php
$jwt = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if ($authHeader) {
    $arr = explode(" ", $authHeader);
    $jwt = $arr[1];
}

if ($jwt) {
    try {
        // Giải mã token
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        $user_id = $decoded->data->id; // Lấy user_id từ token đã giải mã

        // --- BƯỚC 2: XỬ LÝ TẠO ĐƠN ĐẶT SÂN ---
        $data = json_decode(file_get_contents("php://input"));

        // Kiểm tra dữ liệu đầu vào
        if (
            !empty($data->field_id) &&
            !empty($data->booking_date) &&
            !empty($data->time_slot_start) &&
            !empty($data->total_price)
        ) {
            // Gán dữ liệu
            $field_id = $data->field_id;
            $booking_date = $data->booking_date;
            $time_slot_start = $data->time_slot_start;
            // Giả sử mỗi suất đặt sân là 1 tiếng
            $time_slot_end = date('H:i:s', strtotime($time_slot_start . ' +1 hour'));
            $total_price = $data->total_price;
            
            // Câu lệnh INSERT
            $query = "INSERT INTO bookings (user_id, field_id, booking_date, time_slot_start, time_slot_end, total_price) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);

            // Gắn dữ liệu
            $stmt->bind_param("iisssd", $user_id, $field_id, $booking_date, $time_slot_start, $time_slot_end, $total_price);

            // Thực thi
            if ($stmt->execute()) {
                // LẤY ID CỦA ĐƠN HÀNG VỪA TẠO
                $last_id = $conn->insert_id;
                
                http_response_code(201);
                // TRẢ VỀ CẢ MESSAGE VÀ ID
                echo json_encode(array(
                    "message" => "Đặt sân thành công.", 
                    "id" => $last_id
                ));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Không thể đặt sân.", "error" => $stmt->error));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Dữ liệu không đầy đủ."));
        }
    } catch (Exception $e) {
        // Lỗi khi giải mã token (token sai, hết hạn,...)
        http_response_code(401);
        echo json_encode(array(
            "message" => "Truy cập bị từ chối.",
            "error" => $e->getMessage()
        ));
    }
} else {
    // Không có token
    http_response_code(401);
    echo json_encode(array("message" => "Truy cập bị từ chối. Yêu cầu xác thực."));
}
?>