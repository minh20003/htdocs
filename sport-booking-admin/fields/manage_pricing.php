<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/database.php';

// Fetch all sport fields
$fields = [];
$sql = "SELECT id, name, sport_type FROM sport_fields ORDER BY name ASC";
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
    <title>Quản lý Giá & Khung Giờ</title>
    <style>
        /* Basic styling - reuse from other pages */
        body { font-family: sans-serif; }
        .container { padding: 20px; max-width: 800px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-links a { text-decoration: none; padding: 5px 10px; background-color: #007bff; color: white; border-radius: 3px;}
        .action-links a:hover { background-color: #0056b3;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Chọn Sân để Quản lý Giá & Khung Giờ</h1>
        <a href="../index.php">Quay lại Dashboard</a>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Sân</th>
                    <th>Loại Sân</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fields)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">Chưa có sân nào.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fields as $field): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($field['id']); ?></td>
                            <td><?php echo htmlspecialchars($field['name']); ?></td>
                            <td><?php echo htmlspecialchars($field['sport_type']); ?></td>
                            <td class="action-links">
                                <a href="edit_field_pricing.php?field_id=<?php echo $field['id']; ?>">Quản lý giá</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>