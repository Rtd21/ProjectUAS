<?php
session_start();
require 'supabase.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role == 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

// Kita tambahkan 'foto_url' ke dalam select untuk memastikan datanya terambil
$pendaftaran_result = supabase_request("GET", "/rest/v1/pendaftaran?user_id=eq.$user_id&select=*,foto_url,jadwal_seleksi(nama,tanggal)");
$pendaftaran = $pendaftaran_result['data'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Pendaftar</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">
    <div class="content-wrapper">
        <div class="dashboard-header">
            <h2>Dashboard Pendaftar</h2>
        </div>
        
        <?php if ($success_message): ?><div class='message success'><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class='message error'><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

        <div class="dashboard-nav">
            <?php if ($pendaftaran && count($pendaftaran) > 0): ?>
                <a href="edit_formulir.php">Edit Formulir</a>
                
                <?php 
                    $p_status = $pendaftaran[0]['status'];
                    $status_boleh_cetak = ['Lulus Administrasi', 'Menunggu Jadwal Ujian', 'Sudah Diuji', 'Lulus', 'Tidak Lulus'];
                    if (in_array($p_status, $status_boleh_cetak)): 
                ?>
                    <a href="cetak_kartu.php" target="_blank" style="background-color: var(--success-color); color: white; border-color: #28a745;">Cetak Kartu Peserta</a>
                <?php endif; ?>

            <?php else: ?>
                <a href="daftar.php">Isi Formulir</a>
            <?php endif; ?>
            <a href="jadwal_peserta.php">Lihat Jadwal Seleksi</a>
            <a href="logout.php">Logout</a>
        </div>

        <?php if ($pendaftaran && count($pendaftaran) > 0):
            $p = $pendaftaran[0]; ?>
            <div class="dashboard-info">
                
                <div style="text-align: center; padding: 20px 0; border-bottom: 1px solid #e9ecef;">
                    <?php if (!empty($p['foto_url'])): ?>
                        <img src="<?= htmlspecialchars($p['foto_url']) ?>" alt="Foto Profil" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div style="width: 150px; height: 150px; border-radius: 50%; background-color: #e9ecef; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: #6c757d; font-weight: 500;">
                            <span>Belum Ada Foto</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="info-row">
                    <span class="info-label">Status Pendaftaran:</span>
                    <span class="info-value status-<?= strtolower(str_replace(' ', '-', $p['status'])) ?>"><?= htmlspecialchars($p['status']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nama:</span>
                    <span class="info-value"><?= htmlspecialchars($p['nama_lengkap']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nilai Seleksi:</span>
                    <span class="info-value"><?= isset($p['nilai']) ? htmlspecialchars($p['nilai']) : '-' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kategori:</span>
                    <span class="info-value"><?= htmlspecialchars($p['kategori']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jadwal Seleksi:</span>
                    <span class="info-value">
                        <?php
                        if (isset($p['jadwal_seleksi']) && !empty($p['jadwal_seleksi'])) {
                            $j = $p['jadwal_seleksi'];
                            $tanggal_utc = new DateTime($j['tanggal'], new DateTimeZone('UTC'));
                            $tanggal_utc->setTimezone(new DateTimeZone('Asia/Jakarta'));
                            echo htmlspecialchars($j['nama']) . ' (' . $tanggal_utc->format('d F Y, H:i') . ')';
                        } else {
                            echo 'Belum ditentukan';
                        }
                        ?>
                    </span>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-info">
                <p class="text-center">Anda belum mengisi formulir pendaftaran.</p>
            </div>
        <?php endif; ?>
    </div>
    <script src="assets/js/script.js"></script>
</body>
</html>