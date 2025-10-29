<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Includes
require_once '../../config/database.php'; // Đường dẫn CSDL
require_once '../../vendor/autoload.php'; // Autoload Composer
require_once '../../config/vnpay_config.php'; // File này chứa $vnp_HashSecret làm secret key JWT tạm thời, hoặc định nghĩa key riêng

// Các class cần thiết
use Google\Client as GoogleClient;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// --- Cấu hình ---
$google_client_id = '415762522921-7ramrtuthqbeel9ghef0nghm2u73l4r0.apps.googleusercontent.com'; // <<<=== THAY BẰNG CLIENT ID CỦA BẠN
$jwt_secret_key = $vnp_HashSecret; // Tạm dùng key này, bạn nên tạo key riêng cho JWT
$jwt_issuer = "http://localhost"; // Server của bạn
$jwt_audience = "THE_AUDIENCE";
$jwt_expiry_hours = 24; // Token hết hạn sau 24 giờ

// Lấy ID Token từ POST request (gửi dạng form-data hoặc JSON đều được)
$id_token = null;
if (!empty($_POST['idToken'])) {
    $id_token = $_POST['idToken'];
} else {
    $data = json_decode(file_get_contents("php://input"));
    if (!empty($data->idToken)) {
        $id_token = $data->idToken;
    }
}

// Kiểm tra có ID Token không
if (!$id_token) {
    http_response_code(400);
    echo json_encode(["message" => "Thiếu Google ID Token."]);
    exit();
}

// --- Xác thực Google ID Token ---
$client = new GoogleClient(['client_id' => $google_client_id]);
try {
    $payload = $client->verifyIdToken($id_token);
    if ($payload) {
        $google_user_id = $payload['sub']; // ID người dùng Google
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? 'Người dùng Google';
        // $picture = $payload['picture']; // Có thể lấy ảnh đại diện nếu cần

        if (!$email) {
             throw new Exception("Không thể lấy email từ Google Token.");
        }

        // --- Tìm hoặc Tạo User trong Database ---
        $user_id = null;
        $full_name = $name;

        // Kiểm tra xem email đã tồn tại chưa
        $stmt_check = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? LIMIT 1");
        if(!$stmt_check) throw new Exception("Prepare failed (check user): " . $conn->error);
        $stmt_check->bind_param("s", $email);
        if(!$stmt_check->execute()) throw new Exception("Execute failed (check user): " . $stmt_check->error);
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // User đã tồn tại, lấy thông tin
            $user = $result_check->fetch_assoc();
            $user_id = $user['id'];
            $full_name = $user['full_name']; // Giữ tên cũ nếu đã có
            $stmt_check->close();
            error_log("Google Sign-In: User found with email $email, user_id=$user_id");
        } else {
            // User chưa tồn tại, tạo user mới
            $stmt_check->close();
            // Tạo mật khẩu ngẫu nhiên (không dùng để đăng nhập) hoặc để trống
            $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

            $stmt_insert = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'user')");
             if(!$stmt_insert) throw new Exception("Prepare failed (insert user): " . $conn->error);
            $stmt_insert->bind_param("sss", $name, $email, $random_password);
            if ($stmt_insert->execute()) {
                $user_id = $conn->insert_id; // Lấy ID của user mới tạo
                error_log("Google Sign-In: New user created with email $email, user_id=$user_id");
            } else {
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
                    "full_name" => $full_name, // Dùng tên lấy được hoặc tên mới
                    "email" => $email
                )
            );

            $jwt = JWT::encode($token_payload, $jwt_secret_key, 'HS256');

            http_response_code(200);
            echo json_encode(
                array(
                    "message" => "Đăng nhập Google thành công.",
                    "token" => $jwt, // Trả về JWT của hệ thống
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
        throw new Exception("Google ID Token không hợp lệ.");
    }
} catch (Exception $e) {
    // Xử lý lỗi (xác thực token thất bại, lỗi DB,...)
    error_log("Google Sign-In Error: " . $e->getMessage());
    http_response_code(401); // Unauthorized hoặc 500 nếu lỗi server
    echo json_encode(array("message" => "Xác thực Google thất bại hoặc có lỗi xảy ra.", "error" => $e->getMessage()));
} finally {
    // Đóng kết nối DB
    $conn->close();
}
?>