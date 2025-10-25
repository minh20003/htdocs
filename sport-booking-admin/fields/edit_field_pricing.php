<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Get the field ID from the URL parameter
$field_id = $_GET['field_id'] ?? null;
if (!$field_id || !is_numeric($field_id)) {
    $_SESSION['manage_pricing_error'] = "ID sân không hợp lệ."; // Error message for the previous page
    header("Location: manage_pricing.php");
    exit;
}

// Fetch the field name
$field_name = "Sân không xác định";
$stmt_field = $conn->prepare("SELECT name FROM sport_fields WHERE id = ?");
if ($stmt_field) {
    $stmt_field->bind_param("i", $field_id);
    $stmt_field->execute();
    $result_field = $stmt_field->get_result();
    if ($result_field->num_rows > 0) {
        $field = $result_field->fetch_assoc();
        $field_name = $field['name'];
    }
    $stmt_field->close();
}

// Fetch existing price slots for this field
$price_slots = [];
$stmt_prices = $conn->prepare("SELECT id, time_slot, price, is_peak_hour, day_of_week FROM field_prices WHERE field_id = ? ORDER BY day_of_week, time_slot");
if ($stmt_prices) {
    $stmt_prices->bind_param("i", $field_id);
    $stmt_prices->execute();
    $result_prices = $stmt_prices->get_result();
    if ($result_prices->num_rows > 0) {
        while ($row = $result_prices->fetch_assoc()) {
            $price_slots[] = $row;
        }
    }
    $stmt_prices->close();
} else {
     $_SESSION['pricing_error'] = "Lỗi khi lấy danh sách giá."; // Error for this page
}

$conn->close();

// Define allowed days for dropdown
$days_of_week = ['all', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Quản lý Giá cho: <?php echo htmlspecialchars($field_name); ?></title>
    <style>
        /* Basic styling */
        body { font-family: sans-serif; }
        .container { padding: 20px; max-width: 900px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-add-price { border: 1px solid #ccc; padding: 20px; border-radius: 5px; background-color: #f9f9f9; }
        .form-add-price h2 { margin-top: 0; }
        .form-group { margin-bottom: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;}
        .form-group label { min-width: 100px; text-align: right;}
        .form-group input[type="time"],
        .form-group input[type="number"],
        .form-group select { padding: 8px; border: 1px solid #ccc; border-radius: 3px; }
        .form-group input[type="checkbox"] { margin-left: 5px; }
        .form-group button { padding: 10px 15px; background-color: #5cb85c; color: white; border: none; border-radius: 3px; cursor: pointer; margin-left: 110px; /* Align with inputs */ }
        .delete-link { color: red; text-decoration: none; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Quản lý Giá & Khung Giờ cho: <?php echo htmlspecialchars($field_name); ?></h1>
        <a href="manage_pricing.php">Quay lại chọn sân</a> | <a href="../index.php">Dashboard</a>
        <hr>

        <?php
        // Display messages
        if (isset($_SESSION['pricing_error'])) { echo '<p class="error">' . $_SESSION['pricing_error'] . '</p>'; unset($_SESSION['pricing_error']); }
        if (isset($_SESSION['pricing_success'])) { echo '<p class="success">' . $_SESSION['pricing_success'] . '</p>'; unset($_SESSION['pricing_success']); }
        ?>

        <h2>Khung Giờ Hiện Có</h2>
        <table>
            <thead>
                <tr>
                    <th>Giờ Bắt Đầu</th>
                    <th>Giá (VNĐ)</th>
                    <th>Ngày Áp Dụng</th>
                    <th>Giờ Vàng?</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($price_slots)): ?>
                    <tr><td colspan="5" style="text-align: center;">Chưa có khung giá nào được thiết lập.</td></tr>
                <?php else: ?>
                    <?php foreach ($price_slots as $slot): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(substr($slot['time_slot'], 0, 5)); ?></td>
                            <td><?php echo number_format($slot['price'], 0, ',', '.'); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($slot['day_of_week'])); ?></td>
                            <td><?php echo $slot['is_peak_hour'] ? 'Có' : 'Không'; ?></td>
                            <td>
                                <a href="process_delete_price.php?id=<?php echo $slot['id']; ?>&field_id=<?php echo $field_id; ?>" class="delete-link" onclick="return confirm('Bạn có chắc muốn xóa khung giờ này?');">Xóa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <hr>

        <div class="form-add-price">
            <h2>Thêm Khung Giờ Mới</h2>
            <form action="process_add_price.php" method="post">
                <input type="hidden" name="field_id" value="<?php echo $field_id; ?>">
                <div class="form-group">
                    <label for="time_slot">Giờ bắt đầu:</label>
                    <input type="time" id="time_slot" name="time_slot" step="3600" required> </div>
                <div class="form-group">
                    <label for="price">Giá (VNĐ):</label>
                    <input type="number" id="price" name="price" min="0" required>
                </div>
                <div class="form-group">
                    <label for="day_of_week">Ngày áp dụng:</label>
                    <select id="day_of_week" name="day_of_week" required>
                        <?php foreach ($days_of_week as $day): ?>
                            <option value="<?php echo $day; ?>"><?php echo ucfirst($day); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="is_peak_hour">Là giờ vàng?</label>
                    <input type="checkbox" id="is_peak_hour" name="is_peak_hour" value="1"> </div>
                <div class="form-group">
                    <button type="submit">Thêm Khung Giờ</button>
                </div>
            </form>
        </div>

    </div>
</body>
</html>