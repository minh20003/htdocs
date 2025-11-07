<?php
// ===============================================
// API: Gửi tin nhắn trong chat room
// File: sport-booking-api/api/chat/send_message.php
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
use Google\Client as GoogleClient;
use Google\Service\FirebaseCloudMessaging as FCM;

// Hàm gửi FCM notification (giống như trong join.php)
function sendFCMNotificationV1($targetToken, $title, $body, $room_id) {
    try {
        $serviceAccountKeyFile = __DIR__ . '/../../config/firebase_credentials.json';

        if (!file_exists($serviceAccountKeyFile)) {
            error_log("FCM V1 Error: Service account key file not found");
            return false;
        }

        $client = new GoogleClient();
        $client->setAuthConfig($serviceAccountKeyFile);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $fcmService = new FCM($client);

        $notification = new FCM\Notification();
        $notification->setTitle($title);
        $notification->setBody($body);

        $message = new FCM\Message();
        $message->setToken($targetToken);
        $message->setNotification($notification);
        $message->setData([
            'notification_type' => 'chat',
            'room_id' => (string)$room_id
        ]);

        $request = new FCM\SendMessageRequest();
        $request->setMessage($message);

        $keyFileData = json_decode(file_get_contents($serviceAccountKeyFile), true);
        $projectId = $keyFileData['project_id'] ?? null;

        if (!$projectId) {
            error_log("FCM V1 Error: Could not get project_id");
            return false;
        }

        $parent = 'projects/' . $projectId;
        $response = $fcmService->projects_messages->send($parent, $request);

        error_log("FCM V1 Success: Chat notification sent to token " . substr($targetToken, 0, 10));
        return true;

    } catch (\Exception $e) {
        error_log("FCM V1 Exception: " . $e->getMessage());
        return false;
    }
}

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
    $sender_id = $decoded->data->id;
    $sender_name = $decoded->data->full_name ?? 'Người dùng';

    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->room_id) || !isset($data->message)) {
        http_response_code(400);
        echo json_encode(["message" => "Thiếu thông tin room_id hoặc message."]);
        exit();
    }

    $room_id = $data->room_id;
    $message = trim($data->message);

    if (empty($message)) {
        http_response_code(400);
        echo json_encode(["message" => "Tin nhắn không được để trống."]);
        exit();
    }

    // Kiểm tra user có quyền chat trong room này không
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

    if ($sender_id != $room['user1_id'] && $sender_id != $room['user2_id']) {
        http_response_code(403);
        echo json_encode(["message" => "Bạn không có quyền gửi tin nhắn trong room này."]);
        exit();
    }

    // Xác định receiver_id
    $receiver_id = ($sender_id == $room['user1_id']) ? $room['user2_id'] : $room['user1_id'];

    // Lưu tin nhắn vào database
    $insert_query = "INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iis", $room_id, $sender_id, $message);

    if (!$insert_stmt->execute()) {
        $insert_stmt->close();
        http_response_code(500);
        echo json_encode(["message" => "Không thể gửi tin nhắn."]);
        exit();
    }

    $message_id = $conn->insert_id;
    $insert_stmt->close();

    // Cập nhật last_message trong chat_rooms
    $update_query = "UPDATE chat_rooms SET last_message = ?, last_message_time = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $message, $room_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Lấy FCM token của receiver và gửi notification
    $fcm_query = "SELECT fcm_token FROM users WHERE id = ?";
    $fcm_stmt = $conn->prepare($fcm_query);
    $fcm_stmt->bind_param("i", $receiver_id);
    $fcm_stmt->execute();
    $fcm_result = $fcm_stmt->get_result();
    
    if ($fcm_result->num_rows > 0) {
        $receiver = $fcm_result->fetch_assoc();
        if (!empty($receiver['fcm_token'])) {
            $notif_title = "Tin nhắn mới từ " . $sender_name;
            $notif_body = substr($message, 0, 100); // Giới hạn 100 ký tự
            sendFCMNotificationV1($receiver['fcm_token'], $notif_title, $notif_body, $room_id);
        }
    }
    $fcm_stmt->close();

    // Tạo notification trong database
    $notif_query = "INSERT INTO notifications (user_id, type, title, message, data) VALUES (?, 'chat', ?, ?, ?)";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_title = "Tin nhắn mới từ " . $sender_name;
    $notif_data = json_encode(['room_id' => $room_id]);
    $notif_stmt->bind_param("isss", $receiver_id, $notif_title, $message, $notif_data);
    $notif_stmt->execute();
    $notif_stmt->close();

    $conn->close();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Gửi tin nhắn thành công.",
        "message_id" => $message_id
    ]);

} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token đã hết hạn."]);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    http_response_code(401);
    echo json_encode(["message" => "Token không hợp lệ."]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in send_message.php: " . $e->getMessage());
    echo json_encode(["message" => "Có lỗi xảy ra."]);
}
?>