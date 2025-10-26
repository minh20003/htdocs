<?php
// Bật ghi log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

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

error_log("DEBUG update_fcm_token.php: Script started."); // Log start

// Check DB connection
if ($conn->connect_error) { /* ... log và exit ... */ }
$conn->query("SET time_zone = '+07:00'");
error_log("DEBUG update_fcm_token.php: DB Connected.");

// --- JWT Authentication ---
$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#"; // <<-- NHỚ DÙNG KEY BÍ MẬT CỦA BẠN
$jwt = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $jwt = $matches[1];
}
error_log("DEBUG update_fcm_token.php: Checking JWT.");

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        $user_id = $decoded->data->id;
        error_log("DEBUG update_fcm_token.php: JWT Decoded OK for user_id=" . $user_id);

        // --- Read fcm_token from POST data ---
        error_log("DEBUG update_fcm_token.php: Checking POST data.");
        if (isset($_POST['fcm_token']) && !empty($_POST['fcm_token'])) {
            $fcm_token = $_POST['fcm_token'];
            error_log("DEBUG update_fcm_token.php: Received fcm_token=" . substr($fcm_token, 0, 10) . "..."); // Log received token

            // Prepare update statement
            $query = "UPDATE users SET fcm_token = ? WHERE id = ?";
            $stmt = $conn->prepare($query);

            if (!$stmt) { /* ... log và exit ... */ }
            error_log("DEBUG update_fcm_token.php: Prepare update OK.");

            $stmt->bind_param("si", $fcm_token, $user_id);
            error_log("DEBUG update_fcm_token.php: Bind update OK.");

            // Execute statement
            if ($stmt->execute()) {
                error_log("DEBUG update_fcm_token.php: Execute update OK for user_id=" . $user_id);
                http_response_code(200);
                echo json_encode(array("message" => "Cập nhật FCM token thành công."));
            } else {
                 error_log("DEBUG update_fcm_token.php: Execute update FAILED: " . $stmt->error); // Log DB error
                 http_response_code(503); echo json_encode(["message" => "Không thể cập nhật FCM token."]);
            }
            $stmt->close();

        } else {
            error_log("DEBUG update_fcm_token.php: fcm_token missing in POST data.");
            http_response_code(400); echo json_encode(["message" => "Thiếu thông tin fcm_token."]);
        }

    } catch (Exception $e) { /* ... log lỗi JWT và exit ... */ }
    finally { $conn->close(); }
} else {
    error_log("DEBUG update_fcm_token.php: JWT token missing or invalid format.");
    http_response_code(401); echo json_encode(["message" => "Yêu cầu xác thực."]);
}
error_log("DEBUG update_fcm_token.php: Script finished."); // Log end
?>