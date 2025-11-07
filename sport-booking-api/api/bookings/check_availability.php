<?php
// =====================================================================
// API: Kiểm tra khung giờ còn trống cho một sân trong một ngày
// URL: /api/bookings/check_availability.php?field_id=1&date=2025-11-08
// =====================================================================

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/database.php';

// Lấy tham số từ URL
$field_id = isset($_GET['field_id']) ? intval($_GET['field_id']) : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

// Validate input
if ($field_id <= 0 || empty($date)) {
    http_response_code(400);
    echo json_encode(array("message" => "Thiếu field_id hoặc date."));
    exit;
}

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(array("message" => "Định dạng ngày không hợp lệ. Sử dụng YYYY-MM-DD."));
    exit;
}

// =====================================================================
// BƯỚC 1: LẤY TẤT CẢ KHUNG GIỜ CÓ GIÁ CHO SÂN NÀY
// =====================================================================
$available_slots = array();

// Lấy thứ trong tuần từ date
$day_of_week = strtolower(date('l', strtotime($date))); // Ví dụ: 'friday'

// Query để lấy giá theo thứ và 'all'
$query_prices = "SELECT DISTINCT time_slot, price, is_peak_hour 
                 FROM field_prices 
                 WHERE field_id = ? 
                 AND (day_of_week = ? OR day_of_week = 'all')
                 ORDER BY time_slot ASC";

$stmt_prices = $conn->prepare($query_prices);
$stmt_prices->bind_param("is", $field_id, $day_of_week);
$stmt_prices->execute();
$result_prices = $stmt_prices->get_result();

if ($result_prices->num_rows > 0) {
    while ($row = $result_prices->fetch_assoc()) {
        $available_slots[] = array(
            "time_slot" => substr($row['time_slot'], 0, 5), // HH:MM format
            "price" => floatval($row['price']),
            "is_peak_hour" => boolval($row['is_peak_hour']),
            "is_available" => true // Mặc định là available, sẽ check sau
        );
    }
}
$stmt_prices->close();

// =====================================================================
// BƯỚC 2: KIỂM TRA KHUNG GIỜ NÀO ĐÃ ĐƯỢC ĐẶT
// =====================================================================
$query_bookings = "SELECT time_slot_start, time_slot_end 
                   FROM bookings 
                   WHERE field_id = ? 
                   AND booking_date = ? 
                   AND status NOT IN ('cancelled')
                   ORDER BY time_slot_start ASC";

$stmt_bookings = $conn->prepare($query_bookings);
$stmt_bookings->bind_param("is", $field_id, $date);
$stmt_bookings->execute();
$result_bookings = $stmt_bookings->get_result();

$booked_slots = array();
if ($result_bookings->num_rows > 0) {
    while ($row = $result_bookings->fetch_assoc()) {
        $booked_slots[] = substr($row['time_slot_start'], 0, 5); // HH:MM format
    }
}
$stmt_bookings->close();

// =====================================================================
// BƯỚC 3: CẬP NHẬT TRẠNG THÁI AVAILABLE
// =====================================================================
foreach ($available_slots as &$slot) {
    if (in_array($slot['time_slot'], $booked_slots)) {
        $slot['is_available'] = false;
    }
}
unset($slot); // Hủy reference

// =====================================================================
// BƯỚC 4: NẾU KHÔNG CÓ KHUNG GIỜ NÀO, TẠO KHUNG GIỜ MẶC ĐỊNH
// =====================================================================
if (empty($available_slots)) {
    // Tạo khung giờ từ 6:00 đến 22:00
    for ($hour = 6; $hour <= 21; $hour++) {
        $time_slot = sprintf("%02d:00", $hour);
        
        // Kiểm tra xem đã được đặt chưa
        $is_available = !in_array($time_slot, $booked_slots);
        
        // Giá mặc định
        $is_peak = ($hour >= 17 && $hour <= 21); // 17:00 - 21:00 là giờ cao điểm
        $price = $is_peak ? 200000 : 150000;
        
        $available_slots[] = array(
            "time_slot" => $time_slot,
            "price" => $price,
            "is_peak_hour" => $is_peak,
            "is_available" => $is_available
        );
    }
}

// =====================================================================
// BƯỚC 5: TRẢ VỀ KẾT QUẢ
// =====================================================================
$conn->close();

http_response_code(200);
echo json_encode(array(
    "field_id" => $field_id,
    "date" => $date,
    "day_of_week" => $day_of_week,
    "slots" => $available_slots
));
?>