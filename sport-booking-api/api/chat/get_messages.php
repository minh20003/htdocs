<?php
// ===============================================
// API: Lấy tin nhắn trong chat room
// File: sport-booking-api/api/chat/get_messages.php
// ===============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
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
    $user_id = $decoded->data->id;

    $room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = ($page - 1) * $limit;

    if ($room_id <= 0) {
        http_response_code(400);
        echo json_encode(["message" => "Thiếu thông tin room_id."]);
        exit();
    }

    // Kiểm tra quyền truy cập room
    $check_query = "SELECT user1_id, user2_id FROM chat_rooms WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $room_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows == 0) {
        http_response_code(404);
        echo json_encode(["message" => "Chat room không tồn tại."]);
        exit();
    }

    $room = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($user_id != $room['user1_id'] && $user_id != $room['user2_id']) {
        http_response_code(403);
        echo json_encode(["message" => "Bạn không có quyền xem tin nhắn trong room này."]);
        exit();
    }

    // Lấy tin nhắn
    $query = "SELECT cm.id, cm.sender_id, cm.message, cm.is_read, cm.created_at,
                     u.full_name as sender_name
              FROM chat_messages cm
              LEFT JOIN users u ON cm.sender_id = u.id
              WHERE cm.room_id = ?
              ORDER BY cm.created_at ASC
              LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $room_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'sender_name' => $row['sender_name'],
            'message' => $row['message'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'is_mine' => ($row['sender_id'] == $user_id)
        ];
    }

    $stmt->close();

    // Đánh dấu tin nhắn là đã đọc (tin nhắn của người khác gửi)
    $update_query = "UPDATE chat_messages SET is_read = 1 
                     WHERE room_id = ? AND sender_id != ? AND is_read = 0";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $room_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();

    $conn->close();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => $messages
    ]);

} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token đã hết hạn."]);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token không hợp lệ."]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_messages.php: " . $e->getMessage());
    echo json_encode(["message" => "Có lỗi xảy ra."]);
}
?>