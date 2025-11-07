<?php
// Set timezone để tránh lỗi clock skew
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Includes
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

// Các class cần thiết
use Google\Client as GoogleClient;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// --- Cấu hình ---
$google_client_id = '415762522921-7ramrtuthqbeel9ghef0nghm2u73l4r0.apps.googleusercontent.com';

// ⭐ DÙNG CÙNG SECRET KEY VỚI login.php VÀ create.php
$jwt_secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#";

$jwt_issuer = "http://localhost";
$jwt_audience = "THE_AUDIENCE";
$jwt_expiry_hours = 24;

// ⭐ LOG: Bắt đầu xử lý
error_log("=== GOOGLE LOGIN START ===");

// Lấy ID Token từ POST request
$id_token = null;
if (!empty($_POST['idToken'])) {
    $id_token = $_POST['idToken'];
    error_log("ID Token received from POST (form-data)");
} else {
    $data = json_decode(file_get_contents("php://input"));
    if (!empty($data->idToken)) {
        $id_token = $data->idToken;
        error_log("ID Token received from JSON body");
    }
}

// Kiểm tra có ID Token không
if (!$id_token) {
    error_log("ERROR: No ID Token provided");
    http_response_code(400);
    echo json_encode(["message" => "Thiếu Google ID Token."]);
    exit();
}

error_log("ID Token length: " . strlen($id_token));
error_log("ID Token first 50 chars: " . substr($id_token, 0, 50) . "...");

// --- Xác thực Google ID Token ---
$client = new GoogleClient(['client_id' => $google_client_id]);

// ⭐ QUAN TRỌNG: Set để không verify qua internet (nếu server không có internet)
// Nếu server CÓ internet, comment dòng này lại
// $client->setDeferredAccessTokenWithRefreshToken(false);

try {
    error_log("Starting Google ID Token verification...");
    
    // Thêm leeway để xử lý clock skew (chênh lệch thời gian)
    // JWT library có thể từ chối token nếu thời gian server khác với thời gian issue token
    \Firebase\JWT\JWT::$leeway = 60; // 60 giây leeway
    
    $payload = $client->verifyIdToken($id_token);
    
    if ($payload) {
        error_log("Google ID Token verified successfully!");
        
        $google_user_id = $payload['sub'];
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? 'Người dùng Google';

        error_log("Google User Info: email=$email, name=$name, google_id=$google_user_id");

        if (!$email) {
             throw new Exception("Không thể lấy email từ Google Token.");
        }

        // --- Tìm hoặc Tạo User trong Database ---
        $user_id = null;
        $full_name = $name;

        // Kiểm tra xem email đã tồn tại chưa
        $stmt_check = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? LIMIT 1");
        if(!$stmt_check) {
            error_log("ERROR: Prepare failed (check user): " . $conn->error);
            throw new Exception("Prepare failed (check user): " . $conn->error);
        }
        
        $stmt_check->bind_param("s", $email);
        if(!$stmt_check->execute()) {
            error_log("ERROR: Execute failed (check user): " . $stmt_check->error);
            throw new Exception("Execute failed (check user): " . $stmt_check->error);
        }
        
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // User đã tồn tại, lấy thông tin
            $user = $result_check->fetch_assoc();
            $user_id = $user['id'];
            $full_name = $user['full_name'];
            $stmt_check->close();
            error_log("Google Sign-In: User found with email $email, user_id=$user_id");
        } else {
            // User chưa tồn tại, tạo user mới
            $stmt_check->close();
            $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

            $stmt_insert = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'user')");
            if(!$stmt_insert) {
                error_log("ERROR: Prepare failed (insert user): " . $conn->error);
                throw new Exception("Prepare failed (insert user): " . $conn->error);
            }
            
            $stmt_insert->bind_param("sss", $name, $email, $random_password);
            if ($stmt_insert->execute()) {
                $user_id = $conn->insert_id;
                error_log("Google Sign-In: New user created with email $email, user_id=$user_id");
            } else {
                error_log("ERROR: Execute failed (insert user): " . $stmt_insert->error);
                throw new Exception("Execute failed (insert user): " . $stmt_insert->error);
            }
            $stmt_insert->close();
        }

        // --- Tạo JWT Token của Hệ thống ---
        if ($user_id) {
            $issuedat_claim = time();
            $expire_claim = $issuedat_claim + (3600 * $jwt_expiry_hours);

            $token_payload = array(
                "iss" => $jwt_issuer,
                "aud" => $jwt_audience,
                "iat" => $issuedat_claim,
                "exp" => $expire_claim,
                "data" => array(
                    "id" => $user_id,
                    "full_name" => $full_name,
                    "email" => $email
                )
            );

            $jwt = JWT::encode($token_payload, $jwt_secret_key, 'HS256');
            
            error_log("JWT created successfully for user_id=$user_id");
            error_log("JWT token length: " . strlen($jwt));

            http_response_code(200);
            echo json_encode(
                array(
                    "message" => "Đăng nhập Google thành công.",
                    "token" => $jwt,
                    "user" => array(
                        "id" => $user_id,
                        "full_name" => $full_name,
                        "email" => $email
                    )
                )
            );
        } else {
             throw new Exception("Không thể lấy hoặc tạo User ID.");
        }

    } else {
        // ID token không hợp lệ
        error_log("ERROR: Google verifyIdToken returned FALSE/NULL");
        throw new Exception("Google ID Token không hợp lệ hoặc đã hết hạn.");
    }
} catch (Exception $e) {
    // Xử lý lỗi (xác thực token thất bại, lỗi DB,...)
    error_log("=== GOOGLE LOGIN ERROR ===");
    error_log("Error Type: " . get_class($e));
    error_log("Error Message: " . $e->getMessage());
    error_log("Error File: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    http_response_code(401);
    echo json_encode(array(
        "message" => "Xác thực Google thất bại hoặc có lỗi xảy ra.",
        "error" => $e->getMessage(),
        "error_type" => get_class($e)
    ));
} finally {
    // Đóng kết nối DB
    if(isset($conn)) {
        $conn->close();
    }
    error_log("=== GOOGLE LOGIN END ===");
}
?>