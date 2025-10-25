<?php
ob_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function sendJsonResponse($status_code, $data) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    
    http_response_code($status_code);
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Content-Length: " . strlen($json));
    header("Connection: close");
    
    echo $json;
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    exit();
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$conn->query("SET time_zone = '+07:00'");

if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    sendJsonResponse(500, ["message" => "Lỗi kết nối database"]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ["message" => "Method not allowed"]);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$email = $data['email'] ?? $_POST['email'] ?? null;
$otp = $data['otp'] ?? $_POST['otp'] ?? null;
$new_password = $data['new_password'] ?? $_POST['new_password'] ?? null;

if (empty($email) || empty($otp) || empty($new_password)) {
    sendJsonResponse(400, ["message" => "Dữ liệu không đầy đủ"]);
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_otp = ? AND otp_expiry > NOW() LIMIT 1");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    sendJsonResponse(500, ["message" => "Lỗi hệ thống"]);
}

$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['id'];
    
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_otp = NULL, otp_expiry = NULL WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        error_log("Password reset SUCCESS for user $user_id");
        $update_stmt->close();
        $stmt->close();
        $conn->close();
        
        sendJsonResponse(200, ["message" => "Mật khẩu đã được đặt lại thành công"]);
    } else {
        error_log("Update failed: " . $update_stmt->error);
        sendJsonResponse(500, ["message" => "Không thể cập nhật mật khẩu"]);
    }
} else {
    $stmt->close();
    $conn->close();
    
    sendJsonResponse(400, ["message" => "OTP không hợp lệ hoặc đã hết hạn"]);
}
