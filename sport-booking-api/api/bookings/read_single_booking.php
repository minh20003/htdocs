<?php
// =====================================================================
// API: Lấy chi tiết đơn đặt sân theo ID
// URL: GET /api/bookings/read_single_booking.php?id=1
// =====================================================================

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/database.php';

// Lấy booking_id từ URL
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "ID không hợp lệ"));
    exit;
}

// Query lấy thông tin đầy đủ
$query = "SELECT 
            b.id,
            b.booking_date,
            b.time_slot_start,
            b.time_slot_end,
            b.total_price,
            b.status,
            b.payment_status,
            b.payment_method,
            b.created_at,
            sf.name as field_name,
            sf.address as field_address,
            sf.sport_type,
            u.full_name as user_name,
            u.email as user_email
          FROM bookings b
          JOIN sport_fields sf ON b.field_id = sf.id
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ?
          LIMIT 1";

$stmt = $conn->prepare($query);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(array("message" => "Lỗi chuẩn bị query", "error" => $conn->error));
    exit;
}

$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $booking = $result->fetch_assoc();
    
    // Format thời gian
    $booking['time_slot_start'] = substr($booking['time_slot_start'], 0, 5); // HH:MM
    $booking['time_slot_end'] = substr($booking['time_slot_end'], 0, 5); // HH:MM
    
    http_response_code(200);
    echo json_encode($booking);
} else {
    http_response_code(404);
    echo json_encode(array("message" => "Không tìm thấy đơn đặt sân"));
}

$stmt->close();
$conn->close();
?>