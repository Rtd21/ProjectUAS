<?php
session_start();
require 'supabase.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Logika pengambilan data statistik (tidak ada perubahan)
$all_data_res = supabase_request("GET", "/rest/v1/pendaftaran?select=kategori,status");
$all_data = $all_data_res['data'] ?? [];

$total_pendaftar = count($all_data);
$kategori_counts = ['Umum' => 0, 'Beasiswa' => 0];
$status_counts = [
    'Menunggu Verifikasi' => 0, 
    'Berkas Tidak Lengkap' => 0, 
    'Lulus' => 0, 
    'Tidak Lulus' => 0
];

if ($all_data) {
    foreach ($all_data as $item) {
        if (isset($item['kategori'])) {
            if ($item['kategori'] == 'umum') {
                $kategori_counts['Umum']++;
            } elseif ($item['kategori'] == 'beasiswa') {
                $kategori_counts['Beasiswa']++;
            }
        }
        if (isset($item['status']) && array_key_exists($item['status'], $status_counts)) {
            $status_counts[$item['status']]++;
        }
    }
}

// Siapkan data untuk atribut data-*
$kategori_labels_json = json_encode(array_keys($kategori_counts));
$kategori_values_json = json_encode(array_values($kategori_counts));
$status_labels_json = json_encode(array_keys($status_counts));
$status_values_json = json_encode(array_values($status_counts));

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="content-wrapper" style="max-width: 1200px;">
        <h2>Dashboard Admin</h2>
        
        <div class="dashboard-grid">
            <div class="stat-card">
                <h3>Total Pendaftar</h3>
                <p><?= $total_pendaftar ?></p>
            </div>
            <div class="stat-card" style="border-left-color: var(--success-color);">
                <h3>Lulus</h3>
                <p><?= $status_counts['Lulus'] ?></p>
            </div>
            <div class="stat-card" style="border-left-color: var(--error-color);">
                <h3>Tidak Lulus</h3>
                <p><?= $status_counts['Tidak Lulus'] ?></p>
            </div>
            <div class="stat-card" style="border-left-color: #fd7e14;">
                <h3>Menunggu Verifikasi</h3>
                <p><?= $status_counts['Menunggu Verifikasi'] ?></p>
            </div>
        </div>
        
        <div class="quick-shortcuts">
            <a href="peserta.php">Kelola Data Peserta</a>
            <a href="jadwal.php">Kelola Jadwal Seleksi</a>
            <a href="logout.php">Logout</a>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 1fr 2fr; align-items: flex-start;">
            <div class="chart-container">
                <h3>Komposisi Kategori</h3>
                <canvas 
                    id="kategoriChart" 
                    data-labels='<?= $kategori_labels_json ?>' 
                    data-values='<?= $kategori_values_json ?>'
                ></canvas>
            </div>
            <div class="chart-container">
                <h3>Distribusi Status Pendaftaran</h3>
                <canvas 
                    id="statusChart"
                    data-labels='<?= $status_labels_json ?>'
                    data-values='<?= $status_values_json ?>'
                ></canvas>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>