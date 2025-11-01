<?php
session_start(); // Start the session at the very beginning
require_once '../config/database.php'; // Go up one level to find the config folder

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Vui lòng nhập email và mật khẩu.";
        header("Location: login.php");
        exit;
    }

    // Prepare SQL statement to prevent SQL injection
    // Check for email AND role = 'admin'
    $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
    if (!$stmt) {
        // Handle prepare error (e.g., log it)
        $_SESSION['login_error'] = "Lỗi hệ thống, vui lòng thử lại sau.";
        header("Location: login.php");
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        // Verify the password
        if (password_verify($password, $admin['password'])) {
            // Password is correct, set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];

            // Redirect to the admin dashboard
            header("Location: ../index.php");
            exit;
        } else {
            // Incorrect password
            $_SESSION['login_error'] = "Sai mật khẩu.";
            header("Location: login.php");
            exit;
        }
    } else {
        // Email not found or user is not an admin
        $_SESSION['login_error'] = "Email không tồn tại hoặc không phải tài khoản admin.";
        header("Location: login.php");
        exit;
    }

    $stmt->close();
    $conn->close();

} else {
    // If not a POST request, redirect to login page
    header("Location: login.php");
    exit;
}
?>