<?php
// Các header cần thiết
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Yêu cầu file kết nối database
require_once '../../config/database.php';

// Kiểm tra xem ID có được gửi lên không
// Chúng ta sẽ lấy ID từ URL, ví dụ: .../read_single.php?id=1
$id = isset($_GET['id']) ? $_GET['id'] : die();

// Câu lệnh SQL để lấy một sân duy nhất
$query = "SELECT id, name, sport_type, address, description, images, amenities, status FROM sport_fields WHERE id = ? LIMIT 1";

$stmt = $conn->prepare($query);

// Gắn ID vào câu lệnh (i = integer)
$stmt->bind_param("i", $id);

// Thực thi câu lệnh
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Lấy dòng dữ liệu
    $row = $result->fetch_assoc();

    // Trích xuất dữ liệu
    extract($row);

    // Tạo mảng chứa thông tin sân
    $field_item = array(
        "id" => $id,
        "name" => $name,
        "sport_type" => $sport_type,
        "address" => $address,
        "description" => $description,
        "images" => json_decode($images),
        "amenities" => json_decode($amenities)
    );

    // Đặt mã phản hồi 200 - OK
    http_response_code(200);

    // Trả về dữ liệu dưới dạng JSON
    echo json_encode($field_item);
} else {
    // Nếu không có sân nào, trả về mã 404 - Not found
    http_response_code(404);
    echo json_encode(
        array("message" => "Không tìm thấy sân với ID = {$id}.")
    );
}
?>