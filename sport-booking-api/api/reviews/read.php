<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/database.php';

// Lấy field_id từ URL (ví dụ: ?field_id=1)
$field_id = isset($_GET['field_id']) ? $_GET['field_id'] : die();

// Câu lệnh SQL để lấy tất cả đánh giá của một sân
// Join với bảng users để lấy tên người đánh giá
$query = "SELECT 
            r.id,
            r.rating,
            r.comment,
            r.created_at,
            u.full_name as reviewer_name
          FROM 
            reviews as r
          JOIN
            users as u ON r.user_id = u.id
          WHERE
            r.field_id = ?
          ORDER BY
            r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $field_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $reviews_arr = array();
    $reviews_arr["records"] = array();

    while ($row = $result->fetch_assoc()) {
        extract($row);
        $review_item = array(
            "id" => $id,
            "rating" => $rating,
            "comment" => $comment,
            "created_at" => $created_at,
            "reviewer_name" => $reviewer_name
        );
        array_push($reviews_arr["records"], $review_item);
    }
    http_response_code(200);
    echo json_encode($reviews_arr);
} else {
    http_response_code(404);
    echo json_encode(array("message" => "Chưa có đánh giá nào cho sân này."));
}
?>