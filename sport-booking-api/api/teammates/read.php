<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/database.php'; // Đảm bảo đường dẫn đúng

// Kiểm tra kết nối DB
if ($conn->connect_error) {
    http_response_code(500); error_log("DB Connection failed: " . $conn->connect_error); echo json_encode(["message" => "Lỗi kết nối database"]); exit();
}
$conn->query("SET time_zone = '+07:00'"); // Đồng bộ múi giờ nếu cần

// Lấy tất cả các tin đang ở trạng thái 'open'
// Join với bảng users để lấy tên VÀ SỐ ĐIỆN THOẠI của người đăng
$query = "SELECT
            p.id,
            p.user_id,
            p.sport_type,
            p.play_date,
            p.time_slot,
            p.players_needed,
            p.description,
            p.created_at,
            u.full_name as poster_name,
            u.phone as poster_phone -- <<-- LẤY THÊM SỐ ĐIỆN THOẠI
          FROM
            find_teammates as p
          JOIN
            users as u ON p.user_id = u.id
          WHERE
            p.status = 'open'
          ORDER BY
            p.created_at DESC";

$stmt = $conn->prepare($query);

// Kiểm tra lỗi prepare
if (!$stmt) {
    http_response_code(500); error_log("Prepare failed (read teammates): (" . $conn->errno . ") " . $conn->error); echo json_encode(["message" => "Lỗi server khi chuẩn bị truy vấn."]); exit();
}

// Thực thi
if (!$stmt->execute()) {
    http_response_code(500); error_log("Execute failed (read teammates): (" . $stmt->errno . ") " . $stmt->error); echo json_encode(["message" => "Lỗi server khi thực thi truy vấn."]); exit();
}

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $posts_arr = array();
    $posts_arr["records"] = array();

    while ($row = $result->fetch_assoc()) {
        extract($row);
        $post_item = array(
            "id" => $id,
            "user_id" => $user_id,
            "sport_type" => $sport_type,
            "play_date" => $play_date,
            "time_slot" => $time_slot,
            "players_needed" => $players_needed,
            "description" => $description,
            "created_at" => $created_at,
            "poster_name" => $poster_name,
            "poster_phone" => $poster_phone ?? 'Chưa cung cấp' // <<-- THÊM SỐ ĐIỆN THOẠI VÀO KẾT QUẢ
        );
        array_push($posts_arr["records"], $post_item);
    }
    http_response_code(200);
    echo json_encode($posts_arr);
} else {
    http_response_code(404);
    echo json_encode(array("message" => "Không tìm thấy tin nào."));
}

$stmt->close();
$conn->close();
?>