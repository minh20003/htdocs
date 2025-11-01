<?php
// Thông tin kết nối đến cơ sở dữ liệu
$servername = "localhost";
$username = "root"; // Tên người dùng mặc định của XAMPP
$password = "";     // Mật khẩu mặc định của XAMPP là rỗng
$dbname = "sport_booking_db"; // Tên database bạn đã tạo

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);
mysqli_query($conn, "SET time_zone = '+07:00'"); // Set múi giờ của session MySQL thành giờ Việt Nam
// Kiểm tra kết nối
if ($conn->connect_error) {
    // Nếu kết nối thất bại, dừng chương trình và báo lỗi
    die("Connection failed: " . $conn->connect_error);
}

// Thiết lập bảng mã utf8 để hỗ trợ tiếng Việt
$conn->set_charset("utf8");
?>