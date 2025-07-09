<?php
session_start();
require 'supabase.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $pass = $_POST["password"];
    $result = supabase_request("GET", "/rest/v1/users?email=eq.$email");
    
    if ($result['data'] && count($result['data']) > 0 && password_verify($pass, $result['data'][0]['password_hash'])) {
        $_SESSION['user_id'] = $result['data'][0]['id'];
        $_SESSION['role'] = $result['data'][0]['role'];
        $_SESSION['username'] = $result['data'][0]['username'];
        header("Location: dashboard.php"); 
        exit();
    } else {
        $error = "Email atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Pendaftaran Online</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="auth-container">
        <h2>Login</h2>

        <?php if($error): ?><div class='message error'><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if($error_message): ?><div class='message error'><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
        <?php if($success_message): ?><div class='message success'><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

        <form method="post" class="auth-form" action="index.php">
            <input type="email" name="email" placeholder="Email" required />
            <input type="password" name="password" placeholder="Password" required />
            <button type="submit">Login</button>
        </form>
        <div class="auth-footer">
            Belum punya akun? <a href="register.php">Daftar</a>
            <span style="margin: 0 5px;">|</span>
            <a href="lupa-password.php">Lupa Password?</a>
        </div>
    </div>
    <script src="assets/js/script.js"></script>
</body>
</html>