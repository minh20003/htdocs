<?php
// Headers, Includes, và xác thực JWT... (tương tự các file create khác)
// ...
// Code này giả định bạn đã có user_id sau khi xác thực token

$data = json_decode(file_get_contents("php://input"));
if ($jwt && !empty($data->fcm_token)) {
    try {
        // ... (code giải mã token để lấy user_id) ...

        $fcm_token = $data->fcm_token;
        $query = "UPDATE users SET fcm_token = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $fcm_token, $user_id);

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "FCM token updated successfully."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Unable to update FCM token."));
        }
    } catch (Exception $e) { /* ... */ }
}
// ...
?>