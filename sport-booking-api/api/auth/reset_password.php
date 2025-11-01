<?php
// =================================================================
// RESET PASSWORD API - FIXED VERSION
// =================================================================

// 1. CORS Headers - PHẢI ĐẶT ĐẦU TIÊN, TRƯỚC BẤT KỲ OUTPUT NÀO
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// 2. Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 3. Error Reporting (Development only - tắt khi production)
ini_set('display_errors', 0); // TẮT display lỗi ra màn hình
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
error_reporting(E_ALL);

// 4. Start Output Buffering
ob_start();

// 5. Error Handler Function
function sendError($code, $message, $details = null) {
    http_response_code($code);
    $response = [
        "success" => false,
        "message" => $message
    ];
    if ($details !== null && ini_get('display_errors')) {
        $response['details'] = $details;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit();
}

// 6. Success Handler Function
function sendSuccess($message, $data = null) {
    http_response_code(200);
    $response = [
        "success" => true,
        "message" => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit();
}

// 7. Log Function
function logDebug($message) {
    error_log("[RESET_PASSWORD] " . $message);
}

// =================================================================
// MAIN LOGIC
// =================================================================

try {
    logDebug("Script started");
    
    // 8. Include Database
    if (!file_exists('../../config/database.php')) {
        throw new Exception("Database config file not found");
    }
    require_once '../../config/database.php';
    logDebug("Database config loaded");
    
    // 9. Check Database Connection
    if (!isset($conn)) {
        throw new Exception("Database connection not initialized");
    }
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    logDebug("Database connected successfully");
    
    // 10. Get Request Data (Support both JSON and Form Data)
    $email = null;
    $otp = null;
    $new_password = null;
    
    // Try JSON first
    $raw_input = file_get_contents('php://input');
    logDebug("Raw input length: " . strlen($raw_input));
    
    if (!empty($raw_input)) {
        $json_data = json_decode($raw_input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
            $email = $json_data['email'] ?? null;
            $otp = $json_data['otp'] ?? null;
            $new_password = $json_data['new_password'] ?? null;
            logDebug("Data from JSON");
        }
    }
    
    // Fallback to POST
    if (empty($email)) {
        $email = $_POST['email'] ?? null;
        $otp = $_POST['otp'] ?? null;
        $new_password = $_POST['new_password'] ?? null;
        logDebug("Data from POST");
    }
    
    logDebug("Received - Email: " . ($email ?? 'NULL') . ", OTP: " . ($otp ?? 'NULL'));
    
    // 11. Validate Input
    if (empty($email) || empty($otp) || empty($new_password)) {
        sendError(400, "Thiếu thông tin: email, OTP hoặc mật khẩu mới");
    }
    
    // Validate email format
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError(400, "Địa chỉ email không hợp lệ");
    }
    
    // Validate OTP format
    $otp = trim($otp);
    if (!preg_match('/^\d{6}$/', $otp)) {
        sendError(400, "Mã OTP phải là 6 chữ số");
    }
    
    // Validate password length
    if (strlen($new_password) < 6) {
        sendError(400, "Mật khẩu mới phải có ít nhất 6 ký tự");
    }
    
    logDebug("Input validation passed");
    
    // 12. Check OTP in Database
    $check_query = "SELECT id, full_name FROM users 
                    WHERE email = ? 
                    AND reset_otp = ? 
                    AND otp_expiry > NOW() 
                    LIMIT 1";
    
    logDebug("Preparing OTP check query");
    $stmt = $conn->prepare($check_query);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare OTP check statement: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $email, $otp);
    logDebug("Executing OTP check query");
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute OTP check: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $num_rows = $result->num_rows;
    logDebug("OTP check result: " . $num_rows . " row(s)");
    
    // 13. Verify OTP
    if ($num_rows === 0) {
        $stmt->close();
        
        // Check if OTP exists but expired
        $expired_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_otp = ? LIMIT 1");
        $expired_check->bind_param("ss", $email, $otp);
        $expired_check->execute();
        $expired_result = $expired_check->get_result();
        
        if ($expired_result->num_rows > 0) {
            $expired_check->close();
            sendError(400, "Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới");
        }
        
        $expired_check->close();
        sendError(400, "Mã OTP không đúng hoặc đã hết hạn");
    }
    
    // 14. Get User Info
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $user_name = $user['full_name'];
    $stmt->close();
    
    logDebug("Valid OTP for user ID: " . $user_id . " (" . $user_name . ")");
    
    // 15. Hash New Password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    if ($hashed_password === false) {
        throw new Exception("Failed to hash password");
    }
    
    logDebug("Password hashed successfully");
    
    // 16. Update Password in Database
    $update_query = "UPDATE users 
                     SET password = ?, 
                         reset_otp = NULL, 
                         otp_expiry = NULL 
                     WHERE id = ?";
    
    logDebug("Preparing password update query");
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        throw new Exception("Failed to prepare update statement: " . $conn->error);
    }
    
    $update_stmt->bind_param("si", $hashed_password, $user_id);
    logDebug("Executing password update");
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update password: " . $update_stmt->error);
    }
    
    $affected_rows = $update_stmt->affected_rows;
    $update_stmt->close();
    
    logDebug("Password updated successfully. Affected rows: " . $affected_rows);
    
    // 17. Close Database Connection
    $conn->close();
    logDebug("Database connection closed");
    
    // 18. Send Success Response
    logDebug("Sending success response");
    sendSuccess("Mật khẩu đã được đặt lại thành công. Bạn có thể đăng nhập bằng mật khẩu mới", [
        "email" => $email
    ]);
    
} catch (Exception $e) {
    // Log error
    logDebug("ERROR: " . $e->getMessage());
    
    // Close any open connections
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($update_stmt) && $update_stmt instanceof mysqli_stmt) {
        $update_stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    
    // Send error response
    sendError(500, "Lỗi hệ thống. Vui lòng thử lại sau", $e->getMessage());
}

// This should never be reached, but just in case
ob_end_flush();
?>