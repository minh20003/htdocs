<?php
// Các header cần thiết
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Yêu cầu file kết nối database
require_once '../../config/database.php';

// Câu lệnh SQL để lấy tất cả các sân
$query = "SELECT id, name, sport_type, address, description, images, amenities, status FROM sport_fields ORDER BY name ASC";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Nếu có dữ liệu, tạo một mảng để chứa các sân
    $fields_arr = array();
    $fields_arr["records"] = array();

    // Lặp qua tất cả các dòng kết quả
    while ($row = $result->fetch_assoc()) {
        // Trích xuất dữ liệu từ dòng
        extract($row);

        $field_item = array(
            "id" => $id,
            "name" => $name,
            "sport_type" => $sport_type,
            "address" => $address,
            "description" => $description,
            // Chuyển đổi chuỗi JSON thành mảng/đối tượng thực sự
            "images" => json_decode($images), 
            "amenities" => json_decode($amenities)
        );

        // Thêm sân vào mảng "records"
        array_push($fields_arr["records"], $field_item);
    }

    // Đặt mã phản hồi 200 - OK
    http_response_code(200);

    // Trả về dữ liệu dưới dạng JSON
    echo json_encode($fields_arr);
} else {
    // Nếu không có sân nào, trả về mã 404 - Not found
    http_response_code(404);
    echo json_encode(
        array("message" => "Không tìm thấy sân nào.")
    );
}
?>