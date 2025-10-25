<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Get the field ID from the URL parameter
$field_id = $_GET['id'] ?? null;
if (!$field_id || !is_numeric($field_id)) {
    // Redirect if ID is missing or invalid
    $_SESSION['manage_field_error'] = "ID sân không hợp lệ."; // We'll display this message later
    header("Location: manage_fields.php");
    exit;
}

// Fetch the field data from the database
$stmt = $conn->prepare("SELECT * FROM sport_fields WHERE id = ? LIMIT 1");
if (!$stmt) {
    // Handle prepare error
    $_SESSION['manage_field_error'] = "Lỗi hệ thống khi lấy thông tin sân.";
    header("Location: manage_fields.php");
    exit;
}
$stmt->bind_param("i", $field_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Field not found
    $_SESSION['manage_field_error'] = "Không tìm thấy sân với ID này.";
    header("Location: manage_fields.php");
    exit;
}

// Get the field data
$field = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Define allowed values for dropdowns
$allowed_sport_types = ['football', 'badminton', 'tennis', 'basketball'];
$allowed_statuses = ['active', 'maintenance', 'inactive'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sửa thông tin Sân</title>
    <style>
        /* Re-use styles from add_field.php */
        body { font-family: sans-serif; }
        .container { padding: 20px; max-width: 600px; margin: auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        .form-group textarea { min-height: 80px; }
        .form-group button { padding: 10px 15px; background-color: #5cb85c; color: white; border: none; border-radius: 3px; cursor: pointer; }
        .form-group button:hover { background-color: #4cae4c; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sửa thông tin Sân: <?php echo htmlspecialchars($field['name']); ?></h1>
        <a href="manage_fields.php">Quay lại danh sách</a>
        <hr>

        <?php
        // Display potential errors from the processing script
        if (isset($_SESSION['edit_field_error'])) {
            echo '<p class="error">' . $_SESSION['edit_field_error'] . '</p>';
            unset($_SESSION['edit_field_error']);
        }
        ?>

        <form action="process_edit_field.php" method="post">
            <input type="hidden" name="id" value="<?php echo $field['id']; ?>">

            <div class="form-group">
                <label for="name">Tên Sân:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($field['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="sport_type">Loại Sân:</label>
                <select id="sport_type" name="sport_type" required>
                    <?php foreach ($allowed_sport_types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo ($field['sport_type'] == $type) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type); // Capitalize first letter ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="form-group">
                <label for="address">Địa chỉ:</label>
                <textarea id="address" name="address"><?php echo htmlspecialchars($field['address'] ?? ''); ?></textarea>
            </div>
             <div class="form-group">
                <label for="description">Mô tả:</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($field['description'] ?? ''); ?></textarea>
            </div>
             <div class="form-group">
                 <label for="status">Trạng thái:</label>
                 <select id="status" name="status" required>
                      <?php foreach ($allowed_statuses as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo ($field['status'] == $status) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($status); ?>
                        </option>
                    <?php endforeach; ?>
                 </select>
             </div>
             <div class="form-group">
                <button type="submit">Lưu Thay Đổi</button>
            </div>
        </form>
    </div>
</body>
</html>