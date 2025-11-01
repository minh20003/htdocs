<?php
// Start output buffering to prevent any output before headers
ob_start();
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
$days_vn = [
    'all' => 'Tất cả',
    'monday' => 'Thứ Hai',
    'tuesday' => 'Thứ Ba',
    'wednesday' => 'Thứ Tư',
    'thursday' => 'Thứ Năm',
    'friday' => 'Thứ Sáu',
    'saturday' => 'Thứ Bảy',
    'sunday' => 'Chủ Nhật'
];

$page_title = "Quản lý Giá cho: " . $field_name;
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

    .btn-back {
        background: linear-gradient(135deg, #64748b, #475569);
        color: white;
        border: none;
        padding: 0.625rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-back:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(100, 116, 139, 0.3);
        color: white;
    }

    .table-container {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
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

    .badge-peak {
        background-color: #fef3c7;
        color: #92400e;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-block;
    }

    .badge-normal {
        background-color: #dbeafe;
        color: #1e40af;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-block;
    }

    .btn-delete-price {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-delete-price:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
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

    .form-add-price {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-add-price h2 {
        margin-top: 0;
        margin-bottom: 1.5rem;
        color: var(--dark);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-add-price h2 i {
        color: var(--primary);
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-weight: 600;
        color: #475569;
        font-size: 0.875rem;
    }

    .form-group input[type="time"],
    .form-group input[type="number"],
    .form-group select {
        padding: 0.75rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 0;
    }

    .checkbox-group input[type="checkbox"] {
        width: 1.25rem;
        height: 1.25rem;
        cursor: pointer;
        accent-color: var(--primary);
    }

    .checkbox-group label {
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        margin: 0;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--success), #059669);
        border: none;
        padding: 0.875rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        color: white;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }

    .price-amount {
        font-weight: 700;
        color: var(--success);
        font-size: 1.1rem;
    }
</style>

<div class="page-header">
    <h1 class="page-title mb-0">
        <i class="bi bi-cash-coin"></i>
        <span>Quản lý Giá: <?php echo htmlspecialchars($field_name); ?></span>
    </h1>
    <a href="manage_pricing.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>Quay lại
    </a>
</div>

<?php
// Display messages
if (isset($_SESSION['pricing_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['pricing_error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['pricing_error']);
}
if (isset($_SESSION['pricing_success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>' . $_SESSION['pricing_success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['pricing_success']);
}
?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Giờ Bắt Đầu</th>
                <th>Giá (VNĐ)</th>
                <th>Ngày Áp Dụng</th>
                <th>Loại</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($price_slots)): ?>
                <tr>
                    <td colspan="5" class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <p>Chưa có khung giá nào được thiết lập.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($price_slots as $slot): ?>
                    <tr>
                        <td>
                            <strong><i class="bi bi-clock"></i> <?php echo htmlspecialchars(substr($slot['time_slot'], 0, 5)); ?></strong>
                        </td>
                        <td>
                            <span class="price-amount"><?php echo number_format($slot['price'], 0, ',', '.'); ?> VNĐ</span>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $days_vn[$slot['day_of_week']] ?? ucfirst(htmlspecialchars($slot['day_of_week'])); ?></span>
                        </td>
                        <td>
                            <?php if ($slot['is_peak_hour']): ?>
                                <span class="badge-peak">
                                    <i class="bi bi-star-fill"></i> Giờ Vàng
                                </span>
                            <?php else: ?>
                                <span class="badge-normal">
                                    <i class="bi bi-clock-history"></i> Thường
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="process_delete_price.php?id=<?php echo $slot['id']; ?>&field_id=<?php echo $field_id; ?>" 
                               class="btn-delete-price"
                               onclick="return confirm('Bạn có chắc muốn xóa khung giờ này?');">
                                <i class="bi bi-trash"></i> Xóa
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="form-add-price">
    <h2>
        <i class="bi bi-plus-circle"></i>
        <span>Thêm Khung Giờ Mới</span>
    </h2>
    <form action="process_add_price.php" method="post">
        <input type="hidden" name="field_id" value="<?php echo $field_id; ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label for="time_slot"><i class="bi bi-clock me-1"></i> Giờ bắt đầu</label>
                <input type="time" id="time_slot" name="time_slot" step="3600" required>
            </div>
            
            <div class="form-group">
                <label for="price"><i class="bi bi-cash me-1"></i> Giá (VNĐ)</label>
                <input type="number" id="price" name="price" min="0" step="10000" placeholder="Nhập giá..." required>
            </div>
            
            <div class="form-group">
                <label for="day_of_week"><i class="bi bi-calendar-day me-1"></i> Ngày áp dụng</label>
                <select id="day_of_week" name="day_of_week" required>
                    <?php foreach ($days_of_week as $day): ?>
                        <option value="<?php echo $day; ?>"><?php echo $days_vn[$day] ?? ucfirst($day); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="checkbox-group">
            <input type="checkbox" id="is_peak_hour" name="is_peak_hour" value="1">
            <label for="is_peak_hour">
                <i class="bi bi-star-fill"></i> Đánh dấu là Giờ Vàng
            </label>
        </div>
        
        <button type="submit" class="btn-submit">
            <i class="bi bi-plus-circle"></i>
            Thêm Khung Giờ
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>