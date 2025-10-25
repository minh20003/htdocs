<?php
// Headers và Includes
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

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

        // Dữ liệu từ Android sẽ có snake_case
        if (!empty($data->id) && !empty($data->sport_type) && !empty($data->play_date) && !empty($data->time_slot) && !empty($data->players_needed)) {
            $post_id = $data->id;

            // KIỂM TRA QUYỀN SỞ HỮU
            $check_query = "SELECT user_id FROM find_teammates WHERE id = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $post_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['user_id'] == $user_id) {
                    // NẾU ĐÚNG CHỦ SỞ HỮU, TIẾN HÀNH CẬP NHẬT
                    $update_query = "UPDATE find_teammates SET sport_type = ?, play_date = ?, time_slot = ?, players_needed = ?, description = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    
                    // <<-- SỬA LỖI CHÍNH Ở ĐÂY -->>
                    // Đúng thứ tự: string, string, string, integer, string, integer
                    $update_stmt->bind_param("ssssii", 
                        $data->sport_type, 
                        $data->play_date, 
                        $data->time_slot, 
                        $data->players_needed, 
                        $data->description,
                        $post_id
                    );

                    if ($update_stmt->execute()) {
                        http_response_code(200);
                        echo json_encode(array("message" => "Cập nhật tin thành công."));
                    } else {
                        http_response_code(503);
                        echo json_encode(array("message" => "Không thể cập nhật tin.", "error" => $update_stmt->error));
                    }
                } else {
                    http_response_code(403); // Forbidden
                    echo json_encode(array("message" => "Bạn không có quyền sửa tin này."));
                }
            } else {
                http_response_code(404); // Not Found
                echo json_encode(array("message" => "Không tìm thấy tin để cập nhật."));
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