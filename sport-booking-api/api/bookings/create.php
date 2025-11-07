<?php
// =====================================================================
// API: Tạo đơn đặt sân với VALIDATION
// URL: POST /api/bookings/create.php
// =====================================================================

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// =====================================================================
// BƯỚC 1: XÁC THỰC NGƯỜI DÙNG
// =====================================================================
$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#";
$jwt = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if ($authHeader) {
    $arr = explode(" ", $authHeader);
    $jwt = $arr[1] ?? null;
}

if (!$jwt) {
    http_response_code(401);
    echo json_encode(array("message" => "Truy cập bị từ chối. Yêu cầu xác thực."));
    exit;
}

try {
    // Giải mã token
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user_id = $decoded->data->id;

    // =====================================================================
    // BƯỚC 2: NHẬN DỮ LIỆU TỪ CLIENT
    // =====================================================================
    $data = json_decode(file_get_contents("php://input"));

    // Validate input
    if (
        empty($data->field_id) ||
        empty($data->booking_date) ||
        empty($data->time_slot_start) ||
        empty($data->total_price)
    ) {
        http_response_code(400);
        echo json_encode(array("message" => "Dữ liệu không đầy đủ."));
        exit;
    }

    $field_id = intval($data->field_id);
    $booking_date = trim($data->booking_date);
    $time_slot_start = trim($data->time_slot_start);
    $total_price = floatval($data->total_price);
    
    // Tính time_slot_end (mỗi slot 1 tiếng)
    $time_slot_end = date('H:i:s', strtotime($time_slot_start . ' +1 hour'));

    // =====================================================================
    // BƯỚC 3: VALIDATE DỮ LIỆU
    // =====================================================================
    
    // 3.1: Kiểm tra định dạng ngày
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) {
        http_response_code(400);
        echo json_encode(array("message" => "Định dạng ngày không hợp lệ."));
        exit;
    }

    // 3.2: Kiểm tra ngày không được trong quá khứ
    $today = date('Y-m-d');
    if ($booking_date < $today) {
        http_response_code(400);
        echo json_encode(array("message" => "Không thể đặt sân cho ngày trong quá khứ."));
        exit;
    }

    // 3.3: Kiểm tra sân có tồn tại không
    $stmt_check_field = $conn->prepare("SELECT id FROM sport_fields WHERE id = ? AND status = 'active'");
    $stmt_check_field->bind_param("i", $field_id);
    $stmt_check_field->execute();
    $result_field = $stmt_check_field->get_result();
    
    if ($result_field->num_rows === 0) {
        http_response_code(404);
        echo json_encode(array("message" => "Sân không tồn tại hoặc không hoạt động."));
        exit;
    }
    $stmt_check_field->close();

    // =====================================================================
    // BƯỚC 4: KIỂM TRA KHUNG GIỜ CÓ CÒN TRỐNG KHÔNG
    // =====================================================================
    $stmt_check_availability = $conn->prepare(
        "SELECT id FROM bookings 
         WHERE field_id = ? 
         AND booking_date = ? 
         AND time_slot_start = ?
         AND status NOT IN ('cancelled')"
    );
    
    $stmt_check_availability->bind_param("iss", $field_id, $booking_date, $time_slot_start);
    $stmt_check_availability->execute();
    $result_availability = $stmt_check_availability->get_result();
    
    if ($result_availability->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(array(
            "message" => "Khung giờ này đã được đặt. Vui lòng chọn khung giờ khác.",
            "error_code" => "SLOT_ALREADY_BOOKED"
        ));
        exit;
    }
    $stmt_check_availability->close();

    // =====================================================================
    // BƯỚC 5: TẠO ĐƠN ĐẶT SÂN
    // =====================================================================
    $stmt_insert = $conn->prepare(
        "INSERT INTO bookings 
         (user_id, field_id, booking_date, time_slot_start, time_slot_end, total_price, status) 
         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
    );
    
    $stmt_insert->bind_param(
        "iisssd", 
        $user_id, 
        $field_id, 
        $booking_date, 
        $time_slot_start, 
        $time_slot_end, 
        $total_price
    );

    if ($stmt_insert->execute()) {
        $last_id = $conn->insert_id;
        
        http_response_code(201);
        echo json_encode(array(
            "message" => "Đặt sân thành công.",
            "id" => $last_id,
            "booking_date" => $booking_date,
            "time_slot_start" => $time_slot_start,
            "time_slot_end" => $time_slot_end,
            "total_price" => $total_price
        ));
    } else {
        http_response_code(503);
        echo json_encode(array(
            "message" => "Không thể đặt sân.",
            "error" => $stmt_insert->error
        ));
    }
    
    $stmt_insert->close();

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(array(
        "message" => "Truy cập bị từ chối.",
        "error" => $e->getMessage()
    ));
}

$conn->close();
?>