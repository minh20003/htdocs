<?php
// ===============================================
// API: Lấy danh sách chat rooms của user
// File: sport-booking-api/api/chat/get_rooms.php
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

    // Lấy danh sách chat rooms
    $query = "SELECT 
                cr.id as room_id,
                cr.post_id,
                cr.last_message,
                cr.last_message_time,
                CASE 
                    WHEN cr.user1_id = ? THEN cr.user2_id
                    ELSE cr.user1_id
                END as other_user_id,
                CASE 
                    WHEN cr.user1_id = ? THEN u2.full_name
                    ELSE u1.full_name
                END as other_user_name,
                CASE 
                    WHEN cr.user1_id = ? THEN u2.phone
                    ELSE u1.phone
                END as other_user_phone,
                ft.sport_type as sport_name,
                ft.play_date as play_date,
                (SELECT COUNT(*) FROM chat_messages cm 
                 WHERE cm.room_id = cr.id AND cm.sender_id != ? AND cm.is_read = 0) as unread_count
              FROM chat_rooms cr
              LEFT JOIN users u1 ON cr.user1_id = u1.id
              LEFT JOIN users u2 ON cr.user2_id = u2.id
              LEFT JOIN find_teammates ft ON cr.post_id = ft.id
              WHERE cr.user1_id = ? OR cr.user2_id = ?
              ORDER BY cr.last_message_time DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $chat_rooms = [];
    while ($row = $result->fetch_assoc()) {
        $chat_rooms[] = [
            'room_id' => $row['room_id'],
            'post_id' => $row['post_id'],
            'other_user_id' => $row['other_user_id'],
            'other_user_name' => $row['other_user_name'],
            'other_user_phone' => $row['other_user_phone'],
            'sport_name' => $row['sport_name'],
            'play_date' => $row['play_date'],
            'last_message' => $row['last_message'],
            'last_message_time' => $row['last_message_time'],
            'unread_count' => (int)$row['unread_count']
        ];
    }

    $stmt->close();
    $conn->close();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => $chat_rooms
    ]);

} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token đã hết hạn."]);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token không hợp lệ."]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_rooms.php: " . $e->getMessage());
    echo json_encode(["message" => "Có lỗi xảy ra."]);
}
?>