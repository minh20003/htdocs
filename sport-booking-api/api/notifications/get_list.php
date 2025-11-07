<?php
// ===============================================
// API: Lấy danh sách thông báo của user
// File: sport-booking-api/api/notifications/get_list.php
// ===============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// JWT Authentication
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

    // Lấy parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;

    // Đếm tổng số thông báo
    $count_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    // Đếm số thông báo chưa đọc
    $unread_query = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("i", $user_id);
    $unread_stmt->execute();
    $unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'];
    $unread_stmt->close();

    // Lấy danh sách thông báo
    $query = "SELECT id, type, title, message, data, is_read, created_at 
              FROM notifications 
              WHERE user_id = ? 
              ORDER BY created_at DESC 
              LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'data' => $row['data'] ? json_decode($row['data'], true) : null,
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }

    $stmt->close();
    $conn->close();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => $notifications,
        "pagination" => [
            "total" => $total,
            "unread_count" => $unread_count,
            "page" => $page,
            "limit" => $limit,
            "total_pages" => ceil($total / $limit)
        ]
    ]);

} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token đã hết hạn."]);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token không hợp lệ."]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_list.php: " . $e->getMessage());
    echo json_encode(["message" => "Có lỗi xảy ra."]);
}
?>