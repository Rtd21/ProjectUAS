<?php
session_start();
require 'supabase.php';
require 'mailer.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format email tidak valid.";
        $message_type = 'error';
    } else {
        $user_result = supabase_request("GET", "/rest/v1/users?email=eq.$email&limit=1");

        if ($user_result['data'] && count($user_result['data']) > 0) {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires_at = (new DateTime('+1 hour'))->format(DateTime::ATOM);

            supabase_request("PATCH", "/rest/v1/users?email=eq.$email", [
                'reset_token' => $token_hash,
                'reset_token_expires_at' => $expires_at
            ]);

            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=$token";
            
            $subject = "Permintaan Reset Password";
            $body = "
                <p>Halo,</p>
                <p>Kami menerima permintaan untuk mereset password akun Anda. Silakan klik link di bawah ini untuk melanjutkan:</p>
                <p><a href='$reset_link' style='padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                <p>Jika tombol tidak berfungsi, salin dan tempel URL berikut di browser Anda:<br>$reset_link</p>
                <p>Link ini akan kedaluwarsa dalam 1 jam. Jika Anda tidak merasa meminta reset password, silakan abaikan email ini.</p>
                <br>
                <p>Hormat kami,<br><strong>Tim " . ($_ENV['MAIL_FROM_NAME'] ?? 'Aplikasi Anda') . "</strong></p>
            ";

            send_email($email, $subject, $body);
        }
        
        $message = "Jika email Anda terdaftar, link reset password akan dikirim. Silakan periksa kotak masuk (dan folder spam) Anda.";
        $message_type = 'success';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lupa Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="auth-container">
        <h2>Lupa Password</h2>
        <p>Masukkan alamat email Anda. Kami akan mengirimkan link untuk mengatur ulang password Anda.</p>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="auth-form" action="lupa-password.php">
            <input type="email" name="email" placeholder="Email" required />
            <button type="submit">Kirim Link Reset</button>
        </form>
        <div class="auth-footer">
            <a href="index.php">Kembali ke Login</a>
        </div>
    </div>
</body>
</html>