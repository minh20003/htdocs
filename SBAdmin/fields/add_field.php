<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

$page_title = "Thêm Sân Mới";
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
</style>

<div class="page-header">
    <h1 class="page-title mb-0">
        <i class="bi bi-plus-circle"></i>
        <span>Thêm Sân Thể Thao Mới</span>
    </h1>
    <a href="manage_fields.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>Quay lại danh sách
    </a>
</div>

<?php
if (isset($_SESSION['add_field_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['add_field_error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['add_field_error']);
}
if (isset($_SESSION['add_field_success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>' . $_SESSION['add_field_success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['add_field_success']);
}
?>

<div class="form-container">
    <form action="process_add_field.php" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name" class="form-label">Tên Sân *</label>
            <input type="text" id="name" name="name" class="form-control" required placeholder="Nhập tên sân">
        </div>

        <div class="form-group">
            <label for="sport_type" class="form-label">Loại Sân *</label>
            <select id="sport_type" name="sport_type" class="form-select" required>
                <option value="">Chọn loại sân</option>
                <option value="football">Bóng đá</option>
                <option value="badminton">Cầu lông</option>
                <option value="tennis">Tennis</option>
                <option value="basketball">Bóng rổ</option>
            </select>
        </div>

        <div class="form-group">
            <label for="address" class="form-label">Địa chỉ</label>
            <textarea id="address" name="address" class="form-control" placeholder="Nhập địa chỉ sân"></textarea>
        </div>

        <div class="form-group">
            <label for="description" class="form-label">Mô tả</label>
            <textarea id="description" name="description" class="form-control" placeholder="Nhập mô tả về sân"></textarea>
        </div>

        <div class="form-group">
            <label for="status" class="form-label">Trạng thái *</label>
            <select id="status" name="status" class="form-select" required>
                <option value="active">Hoạt động (Active)</option>
                <option value="maintenance">Bảo trì (Maintenance)</option>
                <option value="inactive">Ngừng hoạt động (Inactive)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="field_images" class="form-label">Hình ảnh (chọn nhiều ảnh)</label>
            <input type="file" id="field_images" name="field_images[]" class="form-control" multiple accept="image/*">
            <small class="text-muted">Bạn có thể chọn nhiều ảnh cùng lúc</small>
        </div>

        <div class="form-group mt-4">
            <button type="submit" class="btn-submit">
                <i class="bi bi-check-circle me-2"></i>Lưu Sân
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
