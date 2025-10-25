<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Includes
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Lấy token
$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#";
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

        $data = json_decode(file_get_contents("php://input"));

        // Kiểm tra dữ liệu đầu vào
        if (!empty($data->booking_id) && !empty($data->rating)) {
            $booking_id = $data->booking_id;
            $rating = $data->rating;
            $comment = $data->comment ?? '';

            // --- KIỂM TRA QUYỀN ĐÁNH GIÁ ---
            // Người dùng chỉ có thể đánh giá đơn đặt sân của chính họ và đã ở trạng thái 'completed'
            $check_query = "SELECT field_id FROM bookings WHERE id = ? AND user_id = ? AND status = 'completed' LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ii", $booking_id, $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $field_id = $row['field_id'];

                // --- TIẾN HÀNH LƯU ĐÁNH GIÁ ---
                $insert_query = "INSERT INTO reviews (user_id, field_id, booking_id, rating, comment) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("iiiis", $user_id, $field_id, $booking_id, $rating, $comment);
                
                if ($insert_stmt->execute()) {
                    http_response_code(201);
                    echo json_encode(array("message" => "Gửi đánh giá thành công."));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Không thể gửi đánh giá."));
                }
            } else {
                http_response_code(403); // Forbidden
                echo json_encode(array("message" => "Bạn không có quyền đánh giá đơn đặt sân này hoặc đơn chưa hoàn thành."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Dữ liệu không đầy đủ."));
        }

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(array("message" => "Truy cập bị từ chối."));
    }
} else {
    http_response_code(401);
    echo json_encode(array("message" => "Yêu cầu xác thực."));
}
?>