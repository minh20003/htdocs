<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/database.php';

// Fetch all sport fields from the database
$fields = [];
$sql = "SELECT id, name, sport_type, address, status FROM sport_fields ORDER BY name ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $fields[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quản lý Sân Thể Thao</title>
    <style>
        /* Basic styling - can be improved later */
        body { font-family: sans-serif; }
        .container { padding: 20px; max-width: 1000px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-links a { margin-right: 10px; text-decoration: none; }
        .add-button { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #5cb85c; color: white; text-decoration: none; border-radius: 3px; }
        .add-button:hover { background-color: #4cae4c; }
        .status-active { color: green; }
        .status-inactive { color: red; }
        .status-maintenance { color: orange; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Quản lý Sân Thể Thao</h1>

        <?php
        if (isset($_SESSION['manage_field_success'])) {
            echo '<p style="color: green; font-weight: bold;">' . $_SESSION['manage_field_success'] . '</p>';
            unset($_SESSION['manage_field_success']);
        }
        if (isset($_SESSION['manage_field_error'])) {
            echo '<p style="color: red; font-weight: bold;">' . $_SESSION['manage_field_error'] . '</p>';
            unset($_SESSION['manage_field_error']);
        }
        ?>
        <a href="add_field.php" class="add-button">Thêm Sân Mới</a>
        <a href="../index.php">Quay lại Dashboard</a>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Sân</th>
                    <th>Loại Sân</th>
                    <th>Địa chỉ</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fields)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Chưa có sân nào.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fields as $field): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($field['id']); ?></td>
                            <td><?php echo htmlspecialchars($field['name']); ?></td>
                            <td><?php echo htmlspecialchars($field['sport_type']); ?></td>
                            <td><?php echo htmlspecialchars($field['address']); ?></td>
                            <td>
                                <span class="status-<?php echo htmlspecialchars($field['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($field['status'])); // Capitalize first letter ?>
                                </span>
                            </td>
                            <td class="action-links">
                                <a href="edit_field.php?id=<?php echo $field['id']; ?>">Sửa</a>
                                <a href="delete_field.php?id=<?php echo $field['id']; ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa sân này?');">Xóa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>