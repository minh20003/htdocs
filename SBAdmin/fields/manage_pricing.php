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

$page_title = "Quản lý Giá & Khung Giờ";
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
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-pricing {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
    }

    .btn-pricing:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        color: white;
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
        <i class="bi bi-cash-stack"></i>
        <span>Chọn Sân để Quản lý Giá</span>
    </h1>
</div>

<?php
if (isset($_SESSION['manage_pricing_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['manage_pricing_error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['manage_pricing_error']);
}
if (isset($_SESSION['manage_pricing_success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>' . $_SESSION['manage_pricing_success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['manage_pricing_success']);
}
?>

<div class="table-container">
    <table class="table">
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
                    <td colspan="4" class="empty-state">
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
                        <td>
                            <div class="action-buttons">
                                <a href="edit_field_pricing.php?field_id=<?php echo $field['id']; ?>" class="btn btn-sm btn-pricing">
                                    <i class="bi bi-cash-coin"></i> Quản lý giá
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