<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Thêm Sân Mới</title>
    <style>
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
        <h1>Thêm Sân Thể Thao Mới</h1>
        <a href="manage_fields.php">Quay lại danh sách</a>
        <hr>

        <?php
        if (isset($_SESSION['add_field_error'])) {
            echo '<p class="error">' . $_SESSION['add_field_error'] . '</p>';
            unset($_SESSION['add_field_error']);
        }
        if (isset($_SESSION['add_field_success'])) {
            echo '<p style="color: green;">' . $_SESSION['add_field_success'] . '</p>';
            unset($_SESSION['add_field_success']);
        }
        ?>

        <form action="process_add_field.php" method="post">
            <div class="form-group">
                <label for="name">Tên Sân:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="sport_type">Loại Sân:</label>
                <select id="sport_type" name="sport_type" required>
                    <option value="football">Bóng đá</option>
                    <option value="badminton">Cầu lông</option>
                    <option value="tennis">Tennis</option>
                    <option value="basketball">Bóng rổ</option>
                </select>
            </div>
             <div class="form-group">
                <label for="address">Địa chỉ:</label>
                <textarea id="address" name="address"></textarea>
            </div>
             <div class="form-group">
                <label for="description">Mô tả:</label>
                <textarea id="description" name="description"></textarea>
            </div>
             <div class="form-group">
                 <label for="status">Trạng thái:</label>
                 <select id="status" name="status" required>
                     <option value="active">Hoạt động (Active)</option>
                     <option value="maintenance">Bảo trì (Maintenance)</option>
                     <option value="inactive">Ngừng hoạt động (Inactive)</option>
                 </select>
             </div>
             <div class="form-group">
                <button type="submit">Lưu Sân</button>
            </div>
        </form>
    </div>
</body>
</html>