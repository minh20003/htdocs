<?php
// Các header cần thiết
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Yêu cầu các file cần thiết
require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Tự động load thư viện từ Composer
use \Firebase\JWT\JWT;

// Lấy dữ liệu từ body của request
$data = json_decode(file_get_contents("php://input"));

// Kiểm tra email và password có được gửi lên không
if (!empty($data->email) && !empty($data->password)) {
    $email = $data->email;
    $password = $data->password;

    // Tìm người dùng trong database theo email
    $query = "SELECT id, full_name, password FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = $row['id'];
        $full_name = $row['full_name'];
        $password_hash = $row['password'];

        // Xác thực mật khẩu
        if (password_verify($password, $password_hash)) {
            // Mật khẩu chính xác, tạo JWT token
            $secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#";
            $issuer_claim = "http://localhost";
            $audience_claim = "THE_AUDIENCE";
            $issuedat_claim = time();
            $notbefore_claim = $issuedat_claim;
            $expire_claim = $issuedat_claim + (3600 * 24); // Token hết hạn sau 24 giờ

            $payload = array(
                "iss" => $issuer_claim,
                "aud" => $audience_claim,
                "iat" => $issuedat_claim,
                "nbf" => $notbefore_claim,
                "exp" => $expire_claim,
                "data" => array(
                "id" => $user_id,
                "full_name" => $full_name, 
                "email" => $email
    )
            );

            // Tạo token
            $jwt = JWT::encode($payload, $secret_key, 'HS256');

            http_response_code(200);
            echo json_encode(
                array(
                    "message" => "Đăng nhập thành công.",
                    "token" => $jwt,
                    "user" => array(
                        "id" => $user_id,
                        "full_name" => $full_name,
                        "email" => $email
                    )
                )
            );
        } else {
            // Mật khẩu không đúng
            http_response_code(401);
            echo json_encode(array("message" => "Đăng nhập thất bại. Sai mật khẩu."));
        }
    } else {
        // Không tìm thấy email
        http_response_code(404);
        echo json_encode(array("message" => "Đăng nhập thất bại. Không tìm thấy tài khoản."));
    }
} else {
    // Dữ liệu không đầy đủ
    http_response_code(400);
    echo json_encode(array("message" => "Dữ liệu không đầy đủ."));
}
?>