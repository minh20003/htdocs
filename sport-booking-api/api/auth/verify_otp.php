<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../../config/database.php'; // Ensure path is correct

// Check DB connection
if ($conn->connect_error) { /* ... handle connection error ... */ }

// Read data from POST
if (isset($_POST['email']) && isset($_POST['otp'])) {
    $email = $_POST['email'];
    $otp = $_POST['otp'];

    if (empty($email) || empty($otp)) {
        http_response_code(400); echo json_encode(["message" => "Thiếu email hoặc OTP."]); exit();
    }

    // Check if OTP is valid and not expired for the given email
    $query = "SELECT id FROM users WHERE email = ? AND reset_otp = ? AND otp_expiry > NOW() LIMIT 1";
    $stmt = $conn->prepare($query);

    if (!$stmt) { /* ... handle prepare error ... */ }
    $stmt->bind_param("ss", $email, $otp);
    if (!$stmt->execute()) { /* ... handle execute error ... */ }
    $result = $stmt->get_result();
    if ($result === false) { /* ... handle get_result error ... */ }

    if ($result->num_rows > 0) {
        // OTP is valid
        http_response_code(200);
        echo json_encode(array("message" => "Mã OTP hợp lệ."));
    } else {
        // OTP is invalid or expired
        http_response_code(400);
        echo json_encode(array("message" => "Mã OTP không hợp lệ hoặc đã hết hạn."));
    }
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Dữ liệu không đầy đủ."));
}
$conn->close();
?>