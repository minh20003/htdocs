<?php
// ===============================================
// API: Đánh dấu thông báo đã đọc
// File: sport-booking-api/api/notifications/mark_read.php
// ===============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
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

    $data = json_decode(file_get_contents("php://input"));

    if (isset($data->notification_id)) {
        // Đánh dấu 1 thông báo cụ thể
        $notification_id = $data->notification_id;
        
        $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $notification_id, $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Đã đánh dấu đã đọc."]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Không tìm thấy thông báo."]);
        }
        
        $stmt->close();
        
    } else if (isset($data->mark_all) && $data->mark_all === true) {
        // Đánh dấu tất cả đã đọc
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Đã đánh dấu tất cả thông báo là đã đọc."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Không thể cập nhật."]);
        }
        
        $stmt->close();
        
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Thiếu thông tin notification_id hoặc mark_all."]);
    }

    $conn->close();

} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token đã hết hạn."]);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token không hợp lệ."]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in mark_read.php: " . $e->getMessage());
    echo json_encode(["message" => "Có lỗi xảy ra."]);
}
?>