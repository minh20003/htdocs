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

        if (!empty($data->post_id)) {
            $post_id = $data->post_id;

            // --- KIỂM TRA QUYỀN SỞ HỮU ---
            $check_query = "SELECT user_id FROM find_teammates WHERE id = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $post_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['user_id'] == $user_id) {
                    // --- NẾU ĐÚNG CHỦ SỞ HỮU, TIẾN HÀNH XÓA ---
                    $delete_query = "DELETE FROM find_teammates WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_query);
                    $delete_stmt->bind_param("i", $post_id);

                    if ($delete_stmt->execute()) {
                        http_response_code(200);
                        echo json_encode(array("message" => "Xóa tin thành công."));
                    } else {
                        http_response_code(503);
                        echo json_encode(array("message" => "Không thể xóa tin."));
                    }
                } else {
                    // Không phải chủ sở hữu
                    http_response_code(403); // Forbidden
                    echo json_encode(array("message" => "Bạn không có quyền xóa tin này."));
                }
            } else {
                http_response_code(404); // Not Found
                echo json_encode(array("message" => "Không tìm thấy tin để xóa."));
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