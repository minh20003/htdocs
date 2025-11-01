<?php
// Bật ghi log lỗi (quan trọng để debug)
ini_set('display_errors', 0); // Tắt hiển thị lỗi ra output (an toàn hơn)
ini_set('log_errors', 1);
// Đặt đường dẫn file log nếu cần: ini_set('error_log', '/path/to/your/php-error.log');
error_reporting(E_ALL);

// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Includes - Đảm bảo đường dẫn chính xác
require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Composer autoload

// Các class cần thiết
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Google\Client as GoogleClient;
use Google\Service\FirebaseCloudMessaging\SendMessageRequest;
use Google\Service\FirebaseCloudMessaging\Message;
use Google\Service\FirebaseCloudMessaging\Notification;

// --- Hàm gửi thông báo FCM (phiên bản V1) ---
function sendFCMNotificationV1($targetToken, $title, $body, $post_id) {
    try {
        // Đường dẫn đến file JSON key
        $serviceAccountKeyFile = __DIR__ . '/../../config/firebase_credentials.json';

        if (!file_exists($serviceAccountKeyFile)) {
            error_log("FCM V1 Error: Service account key file not found at " . $serviceAccountKeyFile);
            return false;
        }

        // Tạo Google Client và xác thực
        $client = new GoogleClient();
        $client->setAuthConfig($serviceAccountKeyFile);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        // Tạo dịch vụ FCM
        $fcmService = new Google\Service\FirebaseCloudMessaging($client);

        // Tạo thông báo
        $notification = new Notification();
        $notification->setTitle($title);
        $notification->setBody($body);

        // Tạo Message
        $message = new Message();
        $message->setToken($targetToken);
        $message->setNotification($notification);
        $message->setData([
            'notification_type' => 'teammate_join',
            'post_id' => (string)$post_id // Gửi ID tin đăng dạng chuỗi
        ]);

        // Tạo Request
        $request = new SendMessageRequest();
        $request->setMessage($message);

        // Lấy Project ID từ file key
        $keyFileData = json_decode(file_get_contents($serviceAccountKeyFile), true);
        $projectId = $keyFileData['project_id'] ?? null;

        if (!$projectId) {
            error_log("FCM V1 Error: Could not get project_id from JSON key file.");
            return false;
        }
        $parent = 'projects/' . $projectId;

        // Gửi thông báo
        $response = $fcmService->projects_messages->send($parent, $request);

        error_log("FCM V1 Success: Message sent to token starting with " . substr($targetToken, 0, 10) . ". Response name: " . ($response->name ?? 'N/A'));
        return true;

    } catch (\Google\Exception $e) { // Bắt lỗi cụ thể của Google API Client
        error_log("FCM V1 Google API Exception: " . $e->getMessage() . " Code: " . $e->getCode() . " Errors: " . json_encode($e->getErrors()));
        return false;
    } catch (\Exception $e) { // Bắt các lỗi chung khác
        error_log("FCM V1 General Exception: " . $e->getMessage());
        return false;
    }
}

// --- Xử lý logic Join ---
$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#"; // <<-- THAY BẰNG KEY BÍ MẬT CỦA BẠN
$jwt = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

// Tách token từ header "Bearer ..."
if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $jwt = $matches[1];
}

// Kiểm tra kết nối database
if ($conn->connect_error) {
    http_response_code(500); error_log("DB Connection failed: " . $conn->connect_error); echo json_encode(["message" => "Lỗi kết nối database"]); exit();
}
// Đồng bộ múi giờ (nếu cần)
$conn->query("SET time_zone = '+07:00'");

// Nếu có token JWT
if ($jwt) {
    $conn->begin_transaction(); // Bắt đầu transaction để đảm bảo tính nhất quán
    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        $joiner_id = $decoded->data->id;
        $joiner_name = $decoded->data->full_name ?? 'Một người dùng'; // Lấy tên từ token

        $data = json_decode(file_get_contents("php://input"));

        // Kiểm tra post_id có được gửi không
        if (!empty($data->post_id) && is_numeric($data->post_id)) {
            $post_id = $data->post_id;

            // 1. Kiểm tra xem tin đăng có tồn tại và người đăng có phải người tham gia không
            $post_owner_id = null;
            $post_owner_query = "SELECT user_id FROM find_teammates WHERE id = ? LIMIT 1";
            $post_stmt = $conn->prepare($post_owner_query);
            if (!$post_stmt) throw new Exception("Prepare failed (check owner): " . $conn->error);
            $post_stmt->bind_param("i", $post_id);
            if (!$post_stmt->execute()) throw new Exception("Execute failed (check owner): " . $post_stmt->error);
            $post_result = $post_stmt->get_result();
            if ($post_result->num_rows === 0) {
                 $conn->rollback(); http_response_code(404); echo json_encode(["message" => "Tin đăng không tồn tại."]); exit();
            }
            $post_row = $post_result->fetch_assoc();
            $post_owner_id = $post_row['user_id'];
            $post_stmt->close();

            if ($post_owner_id == $joiner_id) {
                $conn->rollback(); http_response_code(400); echo json_encode(["message" => "Bạn không thể tham gia tin của chính mình."]); exit();
            }

            // 2. Kiểm tra xem người dùng đã tham gia chưa
            $check_query = "SELECT id FROM find_teammates_participants WHERE post_id = ? AND user_id = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
             if (!$check_stmt) throw new Exception("Prepare failed (check participant): " . $conn->error);
            $check_stmt->bind_param("ii", $post_id, $joiner_id);
            if (!$check_stmt->execute()) throw new Exception("Execute failed (check participant): " . $check_stmt->error);
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $conn->rollback(); http_response_code(409); echo json_encode(["message" => "Bạn đã tham gia tin này rồi."]); exit();
            }
            $check_stmt->close();

            // --- THÊM VÀO BẢNG THAM GIA ---
            $insert_query = "INSERT INTO find_teammates_participants (post_id, user_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            if (!$insert_stmt) throw new Exception("Prepare failed (insert participant): " . $conn->error);
            $insert_stmt->bind_param("ii", $post_id, $joiner_id);

            if ($insert_stmt->execute()) {
                // Gửi thông báo sau khi thêm thành công
                $owner_fcm_token = null;
                $get_owner_query = "SELECT fcm_token FROM users WHERE id = ?";
                $owner_stmt = $conn->prepare($get_owner_query);
                 if (!$owner_stmt) {
                     error_log("Prepare failed (get owner token): (" . $conn->errno . ") " . $conn->error);
                 } else {
                     $owner_stmt->bind_param("i", $post_owner_id); // Dùng $post_owner_id đã lấy ở trên
                     if ($owner_stmt->execute()) {
                         $owner_result = $owner_stmt->get_result();
                         if ($owner_result->num_rows > 0) {
                             $owner_row = $owner_result->fetch_assoc();
                             $owner_fcm_token = $owner_row['fcm_token'];
                         }
                     } else { error_log("Execute failed (get owner token): " . $owner_stmt->error); }
                     $owner_stmt->close();
                 }

                if (!empty($owner_fcm_token)) {
                    $title = "Có người mới tham gia!";
                    $body = $joiner_name . " vừa tham gia vào tin tìm người chơi của bạn.";
                    sendFCMNotificationV1($owner_fcm_token, $title, $body, $post_id);
                } else {
                     error_log("FCM V1: Could not send notification for post_id $post_id, owner_id $post_owner_id - No FCM token.");
                }

                $conn->commit(); // Hoàn tất transaction thành công
                http_response_code(200);
                echo json_encode(["message" => "Tham gia thành công."]);

            } else {
                // Lỗi khi thêm người tham gia
                 throw new Exception("Execute failed (insert participant): " . $insert_stmt->error);
            }
            $insert_stmt->close();

        } else {
            // Dữ liệu post_id không hợp lệ
            $conn->rollback(); http_response_code(400); echo json_encode(["message" => "Dữ liệu không đầy đủ hoặc không hợp lệ (thiếu post_id)."]);
        }
    // Bắt các lỗi JWT
    } catch (\Firebase\JWT\ExpiredException $e) {
        $conn->rollback(); http_response_code(401); echo json_encode(["message" => "Token đã hết hạn."]);
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        $conn->rollback(); http_response_code(401); echo json_encode(["message" => "Token không hợp lệ."]);
    } catch (Exception $e) { // Bắt các lỗi khác (SQL, logic,...)
        $conn->rollback(); // Hoàn tác các thay đổi nếu có lỗi
        error_log("Error in join.php: " . $e->getMessage());
        http_response_code(500); echo json_encode(["message" => "Có lỗi xảy ra, vui lòng thử lại."]);
    } finally {
         // Luôn đóng kết nối DB
         $conn->close();
    }
} else {
    // Không có token JWT
    http_response_code(401); echo json_encode(["message" => "Yêu cầu xác thực."]);
    $conn->close(); // Đóng kết nối
}
?>