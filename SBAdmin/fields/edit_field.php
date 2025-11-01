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
    $_SESSION['manage_field_error'] = "ID sân không hợp lệ.";
    header("Location: manage_fields.php");
    exit;
}

// Fetch the field data from the database
$stmt = $conn->prepare("SELECT * FROM sport_fields WHERE id = ? LIMIT 1");
if (!$stmt) {
    $_SESSION['manage_field_error'] = "Lỗi hệ thống khi lấy thông tin sân.";
    header("Location: manage_fields.php");
    exit;
}
$stmt->bind_param("i", $field_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
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

$page_title = "Sửa thông tin Sân";
include '../includes/header.php';
?>

<style>
    .form-container {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        max-width: 800px;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-control,
    .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        transition: all 0.3s ease;
        width: 100%;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        outline: none;
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border: none;
        padding: 0.875rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        color: white;
        font-size: 1rem;
        transition: all 0.3s ease;
        width: 100%;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .btn-back {
        background: #64748b;
        color: white;
        text-decoration: none;
        padding: 0.625rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-back:hover {
        background: #475569;
        color: white;
        transform: translateY(-2px);
    }

    .image-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .image-item {
        position: relative;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .image-item:hover {
        border-color: var(--primary);
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .image-item img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        display: block;
    }

    .image-item .delete-btn {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        border: none;
        border-radius: 6px;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .image-item .delete-btn:hover {
        background: #dc2626;
        transform: scale(1.1);
    }
</style>

<div class="page-header">
    <h1 class="page-title mb-0">
        <i class="bi bi-pencil"></i>
        <span>Sửa thông tin Sân: <?php echo htmlspecialchars($field['name']); ?></span>
    </h1>
    <a href="manage_fields.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>Quay lại danh sách
    </a>
</div>

<?php
if (isset($_SESSION['edit_field_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['edit_field_error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['edit_field_error']);
}
?>

<div class="form-container">
    <form action="process_edit_field.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $field['id']; ?>">

        <div class="form-group">
            <label for="name" class="form-label">Tên Sân *</label>
            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($field['name']); ?>" required>
        </div>

        <div class="form-group">
            <label for="sport_type" class="form-label">Loại Sân *</label>
            <select id="sport_type" name="sport_type" class="form-select" required>
                <?php foreach ($allowed_sport_types as $type): ?>
                    <option value="<?php echo $type; ?>" <?php echo ($field['sport_type'] == $type) ? 'selected' : ''; ?>>
                        <?php echo ucfirst($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="address" class="form-label">Địa chỉ</label>
            <textarea id="address" name="address" class="form-control"><?php echo htmlspecialchars($field['address'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="description" class="form-label">Mô tả</label>
            <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($field['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="status" class="form-label">Trạng thái *</label>
            <select id="status" name="status" class="form-select" required>
                <?php foreach ($allowed_statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo ($field['status'] == $status) ? 'selected' : ''; ?>>
                        <?php echo ucfirst($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Hình ảnh hiện tại</label>
            <?php
            $currentImages = $field['images'] ? json_decode($field['images'], true) : [];
            if (!empty($currentImages) && is_array($currentImages)):
            ?>
                <div class="image-gallery">
                    <?php foreach ($currentImages as $imageName): 
                        $imageUrl = '../uploads/fields/' . htmlspecialchars($imageName);
                    ?>
                        <div class="image-item">
                            <img src="<?php echo $imageUrl; ?>" alt="Field Image">
                            <a href="process_delete_image.php?field_id=<?php echo $field['id']; ?>&image_name=<?php echo urlencode($imageName); ?>"
                               class="delete-btn"
                               onclick="return confirm('Bạn có chắc muốn xóa ảnh này?');">
                                <i class="bi bi-trash"></i> Xóa
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Chưa có ảnh nào.</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="field_images" class="form-label">Thêm hình ảnh mới (tùy chọn)</label>
            <input type="file" id="field_images" name="field_images[]" class="form-control" multiple accept="image/*">
            <small class="text-muted">Bạn có thể chọn nhiều ảnh cùng lúc</small>
        </div>

        <div class="form-group mt-4">
            <button type="submit" class="btn-submit">
                <i class="bi bi-check-circle me-2"></i>Lưu Thay Đổi
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
