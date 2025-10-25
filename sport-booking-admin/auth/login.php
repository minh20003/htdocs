<?php
session_start(); // Bắt đầu session để lưu trạng thái đăng nhập

// Nếu admin đã đăng nhập, chuyển hướng đến trang dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f4; }
        .login-container { background: #fff; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); width: 300px; }
        .login-container h2 { text-align: center; margin-bottom: 20px; }
        .login-container label { display: block; margin-bottom: 5px; }
        .login-container input[type="email"],
        .login-container input[type="password"] { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        .login-container button { width: 100%; padding: 10px; background-color: #5cb85c; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 16px; }
        .login-container button:hover { background-color: #4cae4c; }
        .error { color: red; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php
        // Hiển thị thông báo lỗi nếu có
        if (isset($_SESSION['login_error'])) {
            echo '<p class="error">' . $_SESSION['login_error'] . '</p>';
            unset($_SESSION['login_error']); // Xóa thông báo lỗi sau khi hiển thị
        }
        ?>
        <form action="process_login.php" method="post">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>