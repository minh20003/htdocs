<?php
// ===============================================
// API: Tạo hoặc lấy chat room giữa 2 users
// File: sport-booking-api/api/chat/create_or_get_room.php
// ===============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#"; // THAY BẰNG KEY CỦA BẠN
$jwt = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $jwt = $matches[1];
}

if (!$jwt) {
    http_response_code(401);
    echo json_encode(["message" => "Yêu cầu xác thực."]);
    exit();
}

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $current_user_id = $decoded->data->id;

    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->other_user_id)) {
        http_response_code(400);
        echo json_encode(["message" => "Thiếu thông tin other_user_id."]);
        exit();
    }

    $other_user_id = $data->other_user_id;
    $post_id = $data->post_id ?? null; // Optional

    // Không thể chat với chính mình
    if ($current_user_id == $other_user_id) {
        http_response_code(400);
        echo json_encode(["message" => "Không thể chat với chính mình."]);
        exit();
    }

    // Đảm bảo user1_id luôn nhỏ hơn user2_id để dễ query
    $user1_id = min($current_user_id, $other_user_id);
    $user2_id = max($current_user_id, $other_user_id);

    // Kiểm tra xem room đã tồn tại chưa
    $check_query = "SELECT id FROM chat_rooms 
                    WHERE user1_id = ? AND user2_id = ? AND (post_id = ? OR (post_id IS NULL AND ? IS NULL))
                    LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("iiii", $user1_id, $user2_id, $post_id, $post_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Room đã tồn tại
        $room = $check_result->fetch_assoc();
        $check_stmt->close();
        $conn->close();

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Chat room đã tồn tại.",
            "room_id" => $room['id']
        ]);
    } else {
        // Tạo room mới
        $check_stmt->close();

        $insert_query = "INSERT INTO chat_rooms (post_id, user1_id, user2_id) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iii", $post_id, $user1_id, $user2_id);

        if ($insert_stmt->execute()) {
            $new_room_id = $conn->insert_id;
            $insert_stmt->close();
            $conn->close();

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Tạo chat room thành công.",
                "room_id" => $new_room_id
            ]);
        } else {
            $insert_stmt->close();
            $conn->close();
            http_response_code(500);
            echo json_encode(["message" => "Không thể tạo chat room."]);
        }
    }

} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token đã hết hạn."]);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token không hợp lệ."]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in create_or_get_room.php: " . $e->getMessage());
    echo json_encode(["message" => "Có lỗi xảy ra."]);
}
?>