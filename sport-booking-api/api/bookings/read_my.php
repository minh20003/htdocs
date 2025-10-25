<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// Includes
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Lấy token từ header
$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#";
$jwt = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if ($authHeader) {
    $arr = explode(" ", $authHeader);
    $jwt = $arr[1];
}

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        $user_id = $decoded->data->id;

        // --- Lấy dữ liệu đặt sân của người dùng ---

        // Câu lệnh SQL với JOIN để lấy thêm tên sân và địa chỉ
        $query = "SELECT 
                    b.id,
                    b.field_id,
                    b.booking_date,
                    b.time_slot_start,
                    b.total_price,
                    b.status,
                    sf.name as field_name,
                    sf.address as field_address
                  FROM 
                    bookings as b
                  JOIN 
                    sport_fields as sf ON b.field_id = sf.id
                  WHERE 
                    b.user_id = ?
                  ORDER BY 
                    b.booking_date DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $bookings_arr = array();
            $bookings_arr["records"] = array();

            while ($row = $result->fetch_assoc()) {
                extract($row);
                $booking_item = array(
                    "id" => $id,
                    "field_id" => $field_id,
                    "booking_date" => $booking_date,
                    "time_slot_start" => $time_slot_start,
                    "total_price" => $total_price,
                    "status" => $status,
                    "field_name" => $field_name,
                    "field_address" => $field_address
                );
                array_push($bookings_arr["records"], $booking_item);
            }
            http_response_code(200);
            echo json_encode($bookings_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Không tìm thấy đơn đặt sân nào."));
        }

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(array("message" => "Truy cập bị từ chối.", "error" => $e->getMessage()));
    }
} else {
    http_response_code(401);
    echo json_encode(array("message" => "Truy cập bị từ chối. Yêu cầu xác thực."));
}
?>