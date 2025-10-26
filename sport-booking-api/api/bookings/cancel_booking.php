<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST"); // Dùng POST để thay đổi dữ liệu
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Includes
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// --- Xác thực JWT ---
$secret_key = "DayLaChuoiBiMatCuaRiengToi_KhongAiBiet123!@#"; // <<-- NHỚ DÙNG KEY BÍ MẬT CỦA BẠN
$jwt = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $jwt = $matches[1];
}

// Kiểm tra kết nối DB
if ($conn->connect_error) { /* ... */ }
$conn->query("SET time_zone = '+07:00'");

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        $user_id = $decoded->data->id;

        // Lấy booking_id từ body request (gửi dạng JSON)
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->booking_id) && is_numeric($data->booking_id)) {
            $booking_id = $data->booking_id;

            // --- KIỂM TRA QUYỀN VÀ TRẠNG THÁI ---
            // Lấy trạng thái hiện tại và user_id của đơn hàng
            $check_query = "SELECT user_id, status FROM bookings WHERE id = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            if (!$check_stmt) throw new Exception("Prepare failed (check booking): " . $conn->error);

            $check_stmt->bind_param("i", $booking_id);
            if (!$check_stmt->execute()) throw new Exception("Execute failed (check booking): " . $check_stmt->error);

            $result = $check_stmt->get_result();
            if ($result->num_rows > 0) {
                $booking = $result->fetch_assoc();
                $check_stmt->close(); // Đóng statement sau khi dùng xong

                // Kiểm tra xem người dùng có phải chủ đơn hàng không
                if ($booking['user_id'] == $user_id) {
                    // Kiểm tra xem trạng thái có cho phép hủy không (Ví dụ: chỉ cho hủy 'pending' hoặc 'confirmed')
                    if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed') {
                        // --- TIẾN HÀNH HỦY ---
                        $update_query = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        if (!$update_stmt) throw new Exception("Prepare failed (cancel booking): " . $conn->error);

                        $update_stmt->bind_param("i", $booking_id);

                        if ($update_stmt->execute()) {
                            http_response_code(200);
                            echo json_encode(["message" => "Hủy đơn đặt sân thành công."]);
                            // TODO: Gửi thông báo hủy cho Admin (nếu cần)
                        } else {
                             throw new Exception("Execute failed (cancel booking): " . $update_stmt->error);
                        }
                        $update_stmt->close(); // Đóng statement
                    } else {
                        // Trạng thái không cho phép hủy
                        http_response_code(400);
                        echo json_encode(["message" => "Không thể hủy đơn đặt sân ở trạng thái '" . $booking['status'] . "'. Chỉ có thể hủy đơn đang chờ hoặc đã xác nhận."]);
                    }
                } else {
                    // Không phải chủ đơn hàng
                    http_response_code(403); // Forbidden
                    echo json_encode(["message" => "Bạn không có quyền hủy đơn đặt sân này."]);
                }
            } else {
                // Không tìm thấy đơn hàng
                 if(isset($check_stmt)) $check_stmt->close(); // Đảm bảo đóng nếu đã mở
                http_response_code(404);
                echo json_encode(["message" => "Không tìm thấy đơn đặt sân."]);
            }
        } else {
            // Thiếu booking_id
            http_response_code(400);
            echo json_encode(["message" => "Dữ liệu không đầy đủ (thiếu booking_id)."]);
        }
    // Bắt lỗi JWT và lỗi chung
    } catch (\Firebase\JWT\ExpiredException $e) { /* ... */ }
    catch (\Firebase\JWT\SignatureInvalidException $e) { /* ... */ }
    catch (Exception $e) {
        error_log("Error in cancel_booking.php: " . $e->getMessage());
        http_response_code(500); echo json_encode(["message" => "Có lỗi xảy ra, vui lòng thử lại."]);
    } finally {
         $conn->close();
    }
} else {
    // Thiếu token
    http_response_code(401); echo json_encode(["message" => "Yêu cầu xác thực."]);
    // Không cần đóng $conn vì có thể chưa mở nếu token thiếu
}
?>