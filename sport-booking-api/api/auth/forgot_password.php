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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Ho_Chi_Minh');
$conn->query("SET time_zone = '+07:00'");

if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    sendJsonResponse(500, ["message" => "Lỗi kết nối database"]);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$email = $data['email'] ?? $_POST['email'] ?? null;

if (empty($email)) {
    sendJsonResponse(400, ["message" => "Vui lòng nhập email"]);
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    sendJsonResponse(500, ["message" => "Lỗi server"]);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    sendJsonResponse(404, ["message" => "Email không tồn tại"]);
}

$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

$update_stmt = $conn->prepare("UPDATE users SET reset_otp = ?, otp_expiry = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE email = ?");
if (!$update_stmt) {
    error_log("Prepare update failed: " . $conn->error);
    sendJsonResponse(500, ["message" => "Lỗi server"]);
}

$update_stmt->bind_param("ss", $otp, $email);

if (!$update_stmt->execute()) {
    error_log("Execute update failed: " . $update_stmt->error);
    $update_stmt->close();
    $stmt->close();
    $conn->close();
    sendJsonResponse(500, ["message" => "Không thể tạo OTP"]);
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'minhdqhe170267@fpt.edu.vn';
    $mail->Password = 'fxwf qttt duiw oyxg';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('minhdqhe170267@fpt.edu.vn', 'Sport Booking');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Mã OTP đặt lại mật khẩu';
    $mail->Body = 'Mã OTP của bạn là: <b>' . $otp . '</b>. Hết hạn sau 10 phút.';

    $mail->send();
    
    error_log("OTP sent to $email: $otp");
    
    $update_stmt->close();
    $stmt->close();
    $conn->close();
    
    sendJsonResponse(200, ["message" => "Đã gửi OTP đến email"]);

} catch (Exception $e) {
    error_log("Mail error: " . $mail->ErrorInfo);
    
    $update_stmt->close();
    $stmt->close();
    $conn->close();
    
    sendJsonResponse(500, ["message" => "Không thể gửi email"]);
}
