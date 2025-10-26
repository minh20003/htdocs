<?php
// Start output buffering to prevent any output before headers
ob_start();
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

$page_title = "Quản lý Sân";
include '../includes/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .table-container {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .table {
        margin-bottom: 0;
    }

    .table thead {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .table thead th {
        border: none;
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }

    .table tbody td {
        padding: 1.25rem 1.5rem;
        vertical-align: middle;
        border-top: 1px solid #e2e8f0;
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    .badge-status {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-block;
    }

    .badge-active {
        background-color: #d1fae5;
        color: #065f46;
    }

    .badge-inactive {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .badge-maintenance {
        background-color: #fef3c7;
        color: #92400e;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        border: none;
        transition: all 0.3s ease;
    }

    .btn-edit {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }

    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-delete {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .btn-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #64748b;
    }

    .empty-state i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }
</style>

<div class="page-header">
    <h1 class="page-title mb-0">
        <i class="bi bi-geo-alt"></i>
        <span>Quản lý Sân</span>
    </h1>
    <a href="add_field.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Thêm Sân Mới
    </a>
</div>

<?php
if (isset($_SESSION['manage_field_success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>' . $_SESSION['manage_field_success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['manage_field_success']);
}
if (isset($_SESSION['manage_field_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['manage_field_error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['manage_field_error']);
}
?>

<div class="table-container">
    <table class="table">
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
                    <td colspan="6" class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>Chưa có sân nào. Hãy thêm sân mới để bắt đầu!</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($fields as $field): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($field['id']); ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($field['name']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($field['sport_type'])); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($field['address'] ?: 'N/A'); ?></td>
                        <td>
                            <?php
                            $statusClass = 'badge-inactive';
                            $statusText = 'Ngừng hoạt động';
                            if ($field['status'] == 'active') {
                                $statusClass = 'badge-active';
                                $statusText = 'Hoạt động';
                            } elseif ($field['status'] == 'maintenance') {
                                $statusClass = 'badge-maintenance';
                                $statusText = 'Bảo trì';
                            }
                            ?>
                            <span class="badge-status <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="edit_field.php?id=<?php echo $field['id']; ?>" class="btn btn-sm btn-edit">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>
                                <a href="delete_field.php?id=<?php echo $field['id']; ?>" 
                                   class="btn btn-sm btn-delete"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa sân này?');">
                                    <i class="bi bi-trash"></i> Xóa
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
