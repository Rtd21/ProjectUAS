<?php
session_start();
require 'supabase.php';
date_default_timezone_set('Asia/Jakarta');

// Pastikan user sudah login saja
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- PERUBAHAN DIMULAI DI SINI ---

// 1. Dapatkan kategori pendaftaran pengguna yang sedang login
$user_kategori = null;
$pendaftaran_result = supabase_request("GET", "/rest/v1/pendaftaran?user_id=eq.$user_id&select=kategori&limit=1");
if ($pendaftaran_result['data'] && count($pendaftaran_result['data']) > 0) {
    $user_kategori = $pendaftaran_result['data'][0]['kategori'];
}

// 2. Bangun endpoint query jadwal seleksi
$endpoint = "/rest/v1/jadwal_seleksi?order=tanggal.asc";

// 3. Tambahkan filter kategori jika pengguna sudah memiliki data pendaftaran
if ($user_kategori) {
    $endpoint .= "&kategori=eq.$user_kategori";
}

// Ambil jadwal seleksi sesuai endpoint yang sudah difilter
$jadwal_result = supabase_request("GET", $endpoint);
$jadwal = $jadwal_result['data'];

// --- AKHIR PERUBAHAN ---

?>
<!DOCTYPE html>
<html>
<head>
    <title>Lihat Jadwal Seleksi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <h2>Daftar Jadwal Seleksi
        <?php 
            // Tambahkan judul dinamis berdasarkan kategori
            if ($user_kategori) {
                echo "(Kategori " . ucfirst($user_kategori) . ")";
            }
        ?>
    </h2>
    <a href="dashboard.php">Kembali ke Dashboard</a>

    <?php if ($jadwal && count($jadwal) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Nama Jadwal</th>
                    <th>Kategori</th>
                    <th>Tanggal & Waktu (WIB)</th>
                    <th>Nilai Minimal Lulus</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jadwal as $j): ?>
                    <tr>
                        <td><?= htmlspecialchars($j['nama']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($j['kategori'])) ?></td>
                        <td>
                            <?php
                                if (!empty($j['tanggal'])) {
                                    echo (new DateTime($j['tanggal'], new DateTimeZone('UTC')))
                                        ->setTimezone(new DateTimeZone('Asia/Jakarta'))
                                        ->format('d F Y, H:i');
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($j['nilai_min_lulus'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="margin-top: 20px;">
            <?php
                if ($user_kategori) {
                    echo "Tidak ada jadwal seleksi yang tersedia untuk kategori Anda saat ini.";
                } else {
                    echo "Anda harus mengisi formulir pendaftaran terlebih dahulu untuk melihat jadwal yang relevan.";
                }
            ?>
        </p>
    <?php endif; ?>
</body>
</html>