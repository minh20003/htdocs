<?php
require_once '../../config/database.php';
require_once '../../config/vnpay_config.php';

$vnp_SecureHash = $_GET['vnp_SecureHash'];
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kết quả thanh toán</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
        .container { max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: #4CAF50; }
        .fail { color: #f44336; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Kết quả thanh toán</h1>
        <?php
        if ($secureHash == $vnp_SecureHash) {
            if ($_GET['vnp_ResponseCode'] == '00') {
                // Giao dịch thành công
                $booking_id = $_GET['vnp_TxnRef'];
                $vnp_TransactionNo = $_GET['vnp_TransactionNo']; // Mã giao dịch của VNPay
                
                // Cập nhật trạng thái thanh toán trong database
                $query = "UPDATE bookings SET payment_status = 'paid', status = 'confirmed', vnpay_transaction_id = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $vnp_TransactionNo, $booking_id);
                $stmt->execute();
                
                echo "<h2 class='success'>Thanh toán thành công!</h2>";
                echo "<p>Cảm ơn bạn đã sử dụng dịch vụ.</p>";
                echo "<p>Mã đơn hàng: " . htmlspecialchars($booking_id) . "</p>";

            } else {
                // Giao dịch thất bại
                echo "<h2 class='fail'>Thanh toán không thành công!</h2>";
                echo "<p>Lý do: " . htmlspecialchars($_GET['vnp_ResponseCode']) . "</p>";
            }
        } else {
            echo "<h2 class='fail'>Chữ ký không hợp lệ!</h2>";
        }
        ?>
        <p><a href="#">Quay lại ứng dụng</a></p> </div>
</body>
</html>