<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$vnp_TmnCode = "JPM54ZEY"; // Thay bằng TmnCode của bạn
$vnp_HashSecret = "AZHEI6RHI813FBL5WQ3YZM2NQZ27KR4O"; // Thay bằng HashSecret của bạn
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl = "http://localhost/sport-booking-api/api/payment/vnpay_return.php"; // URL VNPay sẽ trả về
$vnp_apiUrl = "http://sandbox.vnpayment.vn/merchant_webapi/merchant.html";
$apiUrl = "https://sandbox.vnpayment.vn/merchant_webapi/api/transaction";