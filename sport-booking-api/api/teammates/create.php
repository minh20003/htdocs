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

$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#"; // Nhớ dùng key của bạn
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

        // SỬA LẠI TÊN THUỘC TÍNH Ở ĐÂY (dùng dấu gạch dưới)
        if (
            !empty($data->sport_type) &&
            !empty($data->play_date) &&
            !empty($data->time_slot) &&
            !empty($data->players_needed)
        ) {
            $query = "INSERT INTO find_teammates (user_id, sport_type, play_date, time_slot, players_needed, description) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);

            // SỬA LẠI TÊN THUỘC TÍNH Ở ĐÂY
            $stmt->bind_param("isssis", 
                $user_id, 
                $data->sport_type, 
                $data->play_date, 
                $data->time_slot, 
                $data->players_needed, 
                $data->description
            );

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(array("message" => "Đăng tin thành công."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Không thể đăng tin."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Dữ liệu không đầy đủ."));
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