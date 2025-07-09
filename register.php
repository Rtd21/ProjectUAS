<?php
session_start();
require 'supabase.php';
require 'mailer.php';

$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $password_confirm = $_POST["password_confirm"]; // BARIS BARU: Ambil data konfirmasi password
    $errors = [];
    $_SESSION['form_data'] = $_POST;

    // --- BLOK VALIDASI ---
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }
    if (empty($username)) {
        $errors[] = "Nama lengkap tidak boleh kosong.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password minimal harus 6 karakter.";
    }
    // BARIS BARU: Cek apakah password dan konfirmasinya cocok
    if ($password !== $password_confirm) {
        $errors[] = "Konfirmasi password tidak cocok.";
    }
    // --- AKHIR BLOK VALIDASI ---

    if (empty($errors)) {
        $check_result = supabase_request("GET", "/rest/v1/users?email=eq.$email");
        if ($check_result['data'] && count($check_result['data']) > 0) {
            $_SESSION['error_message'] = "Email sudah terdaftar!";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $data = [
                'username' => $username,
                'email' => $email,
                'password_hash' => $password_hash,
                'role' => 'pendaftar'
            ];
            $res = supabase_request("POST", "/rest/v1/users", $data);

            if ($res['data']) {
                $subject = "Selamat Datang di " . ($_ENV['MAIL_FROM_NAME'] ?? 'Aplikasi Pendaftaran');
                $body = "
                    <p>Yth. <strong>" . htmlspecialchars($username) . "</strong>,</p>
                    <p>Selamat! Akun Anda telah berhasil dibuat. Silakan login untuk melanjutkan pendaftaran.</p>
                    <p>Hormat kami,<br><strong>Tim Pendaftaran</strong></p>";
                send_email($email, $subject, $body);

                unset($_SESSION['form_data']);
                $_SESSION['success_message'] = "Pendaftaran akun berhasil! Silakan login.";
                header("Location: index.php");
                exit();
            } else {
                 $_SESSION['error_message'] = "Terjadi kesalahan saat membuat akun: " . ($res['error']['message'] ?? 'Unknown error');
            }
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    header("Location: register.php");
    exit();
}

$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registrasi Akun</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="register-page">
    <div class="auth-container">
        <h2>Daftar Akun Baru</h2>
        
        <?php if($error_message): ?>
             <div class='message error'><?= $error_message ?></div>
        <?php endif; ?>

        <form method="post" id="registerForm" class="auth-form" action="register.php">
            <input type="text" name="username" placeholder="Nama Lengkap" required value="<?= htmlspecialchars($form_data['username'] ?? '') ?>" />
            <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" />
            
            <input type="password" name="password" id="password" placeholder="Password (min. 6 karakter)" required />
            
            <input type="password" name="password_confirm" id="password_confirm" placeholder="Konfirmasi Password" required />
            
            <button type="submit">Daftar</button>
        </form>
        
        <div class="auth-footer">
            Sudah punya akun? <a href="index.php">Login</a>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>