<?php
// Headers, require_once database, require_once vendor/autoload như cũ
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Composer autoload

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Google\Client as GoogleClient; // <<-- Thêm Google Client
use Google\Service\FirebaseCloudMessaging\SendMessageRequest; // <<-- Thêm FCM Request
use Google\Service\FirebaseCloudMessaging\Message; // <<-- Thêm FCM Message
use Google\Service\FirebaseCloudMessaging\Notification; // <<-- Thêm FCM Notification

// Hàm gửi thông báo FCM sử dụng API V1 và Service Account
function sendFCMNotificationV1($targetToken, $title, $body) {
    try {
        // Đường dẫn đến file JSON key của bạn (đặt trong thư mục config)
        $serviceAccountKeyFile = __DIR__ . '/../../config/firebase_credentials.json';

        // Tạo Google Client và xác thực bằng file key
        $client = new GoogleClient();
        $client->setAuthConfig($serviceAccountKeyFile);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $httpClient = $client->authorize(); // Lấy HTTP client đã được xác thực

        // Tạo đối tượng dịch vụ FCM
        $fcmService = new Google\Service\FirebaseCloudMessaging($client);

        // Tạo cấu trúc thông báo
        $notification = new Notification();
        $notification->setTitle($title);
        $notification->setBody($body);

        // Tạo đối tượng Message
        $message = new Message();
        $message->setToken($targetToken); // Token của thiết bị nhận
        $message->setNotification($notification);

        // Tạo đối tượng Request
        $request = new SendMessageRequest();
        $request->setMessage($message);

        // Gửi yêu cầu - Cần Project ID của bạn
        // Lấy Project ID từ file JSON key
        $keyFileData = json_decode(file_get_contents($serviceAccountKeyFile), true);
        $projectId = $keyFileData['project_id'] ?? null;

        if (!$projectId) {
            error_log("FCM V1 Error: Could not get project_id from JSON key file.");
            return false;
        }
        $parent = 'projects/' . $projectId;

        // Gửi thông báo
        $response = $fcmService->projects_messages->send($parent, $request);

        error_log("FCM V1 Success: Message sent. Response: " . json_encode($response));
        return true;

    } catch (\Google\Exception $e) {
        error_log("FCM V1 Google Exception: " . $e->getMessage());
        return false;
    } catch (\Exception $e) {
        error_log("FCM V1 General Exception: " . $e->getMessage());
        return false;
    }
}

// --- Phần xử lý logic Join (giữ nguyên như trước) ---
$secret_key = "YOUR_SUPER_SECRET_KEY"; // Nhớ dùng key bí mật của bạn
$jwt = null;
// ... (code lấy token từ header) ...

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        $joiner_id = $decoded->data->id;
        // Lấy tên người tham gia từ token (cần đảm bảo token có chứa full_name)
        $joiner_name = $decoded->data->full_name ?? 'Một người dùng'; 

        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->post_id)) {
            $post_id = $data->post_id;

            // ... (code kiểm tra tự tham gia, kiểm tra đã tham gia chưa) ...
            // 1. Kiểm tra xem người dùng có đang tự tham gia tin của chính mình không
            $post_owner_query = "SELECT user_id FROM find_teammates WHERE id = ? LIMIT 1";
            // ... (code prepare, bind, execute, check) ...
             if ($post_row['user_id'] == $joiner_id) {
                 http_response_code(400); echo json_encode(array("message" => "Bạn không thể tham gia tin của chính mình.")); exit();
             }

            // 2. Kiểm tra xem người dùng đã tham gia tin này trước đó chưa
            $check_query = "SELECT id FROM find_teammates_participants WHERE post_id = ? AND user_id = ? LIMIT 1";
             // ... (code prepare, bind, execute, check) ...
             if ($check_stmt->get_result()->num_rows > 0) {
                 http_response_code(409); echo json_encode(array("message" => "Bạn đã tham gia tin này rồi.")); exit();
             }


            // --- THÊM VÀO BẢNG THAM GIA ---
            $insert_query = "INSERT INTO find_teammates_participants (post_id, user_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ii", $post_id, $joiner_id);

            if ($insert_stmt->execute()) {
                http_response_code(200);
                echo json_encode(array("message" => "Tham gia thành công."));

                // <<-- GỬI THÔNG BÁO BẰNG API V1 -->>
                // 1. Lấy user_id và fcm_token của người đăng tin
                $get_owner_query = "SELECT u.fcm_token FROM find_teammates p JOIN users u ON p.user_id = u.id WHERE p.id = ?";
                $owner_stmt = $conn->prepare($get_owner_query);
                $owner_stmt->bind_param("i", $post_id);
                $owner_stmt->execute();
                $owner_result = $owner_stmt->get_result();
                if($owner_result->num_rows > 0) {
                    $owner_row = $owner_result->fetch_assoc();
                    $owner_fcm_token = $owner_row['fcm_token'];

                    // 2. Gửi thông báo nếu có token
                    if($owner_fcm_token) {
                        $title = "Có người mới tham gia!";
                        $body = $joiner_name . " vừa tham gia vào tin tìm người chơi của bạn.";
                        // Gọi hàm gửi thông báo V1 mới
                        sendFCMNotificationV1($owner_fcm_token, $title, $body);
                    } else {
                        error_log("FCM V1: Could not send notification for post_id $post_id because owner has no FCM token.");
                    }
                }
                $owner_stmt->close(); // Close statement

            } else { 
                 http_response_code(503); echo json_encode(array("message" => "Không thể tham gia."));
            }
            $insert_stmt->close(); // Close statement
        } else { 
            http_response_code(400); echo json_encode(array("message" => "Dữ liệu không đầy đủ.")); 
        }
    } catch (Exception $e) { 
        http_response_code(401); echo json_encode(array("message" => "Truy cập bị từ chối.")); 
    } finally {
         // Đóng các statement khác nếu có
         if (isset($post_stmt) && $post_stmt instanceof mysqli_stmt) $post_stmt->close();
         if (isset($check_stmt) && $check_stmt instanceof mysqli_stmt) $check_stmt->close();
         $conn->close(); // Đóng kết nối DB
    }
} else { 
    http_response_code(401); echo json_encode(array("message" => "Yêu cầu xác thực.")); 
}
?>