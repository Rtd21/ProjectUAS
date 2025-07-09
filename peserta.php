<?php
session_start();
require 'supabase.php';
require 'mailer.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

$daftar_status = [
    'Menunggu Verifikasi', 
    'Berkas Tidak Lengkap', 
    'Lulus Administrasi',
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $redirect_url = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter($_GET));
    
    if (isset($_POST['luluskan_terpilih'])) {
        $ids_to_update = $_POST['pendaftar_ids'] ?? [];
        if (!empty($ids_to_update)) {
            $quoted_ids = [];
            foreach ($ids_to_update as $id) {
                $quoted_ids[] = '"' . trim($id) . '"';
            }
            $id_filter_string = implode(',', $quoted_ids);

            $get_endpoint = "/rest/v1/pendaftaran?id=in.($id_filter_string)&select=nama_lengkap,users(email)";
            $peserta_to_notify_result = supabase_request("GET", $get_endpoint);

            $patch_endpoint = "/rest/v1/pendaftaran?id=in.($id_filter_string)";
            $update_result = supabase_request("PATCH", $patch_endpoint, ['status' => 'Lulus Administrasi']);
            
            if (!isset($update_result['error'])) {
                if ($peserta_to_notify_result['data']) {
                    $subject = "Selamat! Anda Lulus Seleksi Administrasi";
                    foreach ($peserta_to_notify_result['data'] as $p) {
                        if (!empty($p['users']['email'])) {
                            send_email($p['users']['email'], $subject, "<p>Yth. ".htmlspecialchars($p['nama_lengkap']).",</p><p>Selamat! Berkas Anda telah diverifikasi dan Anda dinyatakan <b>Lulus Seleksi Administrasi</b>. Silakan tunggu informasi jadwal ujian selanjutnya.</p>");
                        }
                    }
                }
                $_SESSION['success_message'] = count($ids_to_update) . " peserta berhasil diluluskan administrasinya.";
            } else {
                $_SESSION['error_message'] = "Gagal mengupdate status: " . ($update_result['error']['message'] ?? 'Unknown Error');
            }

        } else {
            $_SESSION['error_message'] = "Tidak ada peserta yang dipilih.";
        }
    }
    elseif (isset($_POST['update_nilai_individu'])) {
        $id = $_POST['update_nilai_individu']; 
        $nilai = $_POST['nilai'][$id] === '' ? null : floatval($_POST['nilai'][$id]);
        
        $pendaftaran_result = supabase_request("GET", "/rest/v1/pendaftaran?id=eq.$id&select=*,jadwal_seleksi(*),users(email,username)");
        if ($pendaftaran_result['data']) {
            $p = $pendaftaran_result['data'][0];
            $jadwal = $p['jadwal_seleksi'] ?? null;
            $status = 'Sudah Diuji'; 
            if ($jadwal && isset($jadwal['nilai_min_lulus']) && $nilai !== null) {
                $status = $nilai >= floatval($jadwal['nilai_min_lulus']) ? 'Lulus' : 'Tidak Lulus';
            }

            supabase_request("PATCH", "/rest/v1/pendaftaran?id=eq.$id", ['nilai' => $nilai, 'status' => $status]);
            
            if (isset($p['users']['email'])) {
                $subject = "Pembaruan Hasil Seleksi Anda";
                $body = "
                    <p>Yth. <strong>" . htmlspecialchars($p['nama_lengkap']) . "</strong>,</p>
                    <p>Hasil seleksi Anda telah diperbarui. Berikut adalah rinciannya:</p>
                    <ul>
                        <li>Nilai Akhir: <strong>" . ($nilai ?? 'N/A') . "</strong></li>
                        <li>Status Kelulusan: <strong>" . htmlspecialchars($status) . "</strong></li>
                    </ul>
                    <p>Silakan login ke dashboard Anda untuk informasi lebih lanjut.</p>
                ";
                send_email($p['users']['email'], $subject, $body);
                $_SESSION['success_message'] = "Nilai untuk ".htmlspecialchars($p['nama_lengkap'])." berhasil diupdate dan notifikasi dikirim.";
            } else {
                $_SESSION['success_message'] = "Nilai untuk ".htmlspecialchars($p['nama_lengkap'])." berhasil diupdate.";
            }

        } else {
            $_SESSION['error_message'] = "Gagal menemukan data peserta.";
        }
    } 
    // PERUBAHAN: Logika Hapus yang sebelumnya sudah ada, sekarang akan digunakan kembali
    elseif (isset($_POST['hapus_peserta'])) {
        // ID peserta diambil dari 'value' tombol hapus yang diklik
        $id = $_POST['hapus_peserta'];
        supabase_request("DELETE", "/rest/v1/pendaftaran?id=eq.$id");
        $_SESSION['success_message'] = "Peserta berhasil dihapus.";
        $redirect_url = 'peserta.php';
    }
    header('Location: ' . $redirect_url);
    exit;
}

$items_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$filter_values = ['filter' => trim($_GET['filter'] ?? ''), 'kategori' => trim($_GET['kategori'] ?? ''), 'status' => trim($_GET['status'] ?? '')];
$filter_params = [];
if($filter_values['filter']) $filter_params[] = "nama_lengkap=ilike.*{$filter_values['filter']}*";
if($filter_values['kategori']) $filter_params[] = "kategori=eq.{$filter_values['kategori']}";
if($filter_values['status']) $filter_params[] = "status=eq.{$filter_values['status']}";
$query_string = implode('&', $filter_params);
$count_header = ['Prefer: count=exact'];
$count_result = supabase_request_with_headers("GET", "/rest/v1/pendaftaran?" . $query_string, null, $count_header);
$total_items = $count_result['count'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);
$range_header = ["Range: $offset-" . ($offset + $items_per_page - 1)];
$pendaftar_result = supabase_request_with_headers("GET", "/rest/v1/pendaftaran?select=*,jadwal_seleksi(nama,tanggal)&" . $query_string . "&order=created_at.desc", null, $range_header);
$pendaftar = $pendaftar_result['data'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Peserta (Admin)</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<h2>Data Peserta</h2>
<a href="admin_dashboard.php">Dashboard Admin</a> | <a href="export.php">Export Excel</a> | <a href="logout.php">Logout</a>

<?php if ($success_message): ?><div class='message success'><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
<?php if ($error_message): ?><div class='message error'><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

<form method="get" action="peserta.php" style="margin: 15px 0;">
    <input type="text" name="filter" placeholder="Cari nama..." value="<?= htmlspecialchars($filter_values['filter']) ?>" />
    <select name="status">
        <option value="">Semua Status Manual</option>
        <?php foreach($daftar_status as $s): ?>
            <option value="<?= $s ?>" <?= ($filter_values['status'] == $s) ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
        <?php if(in_array($filter_values['status'], ['Lulus', 'Tidak Lulus', 'Sudah Diuji'])): ?>
            <option value="<?= htmlspecialchars($filter_values['status'])?>" selected><?= htmlspecialchars($filter_values['status'])?></option>
        <?php endif; ?>
    </select>
    <button type="submit">Filter</button>
    <a href="peserta.php" style="margin-left: 10px;">Reset</a>
</form>

<form method="post" action="peserta.php?<?= http_build_query($_GET) ?>">
    <button type="submit" name="luluskan_terpilih" onclick="return confirm('Yakin ingin meluluskan administrasi semua peserta yang dipilih?')">
        Luluskan Administrasi untuk yang Terpilih
    </button>
    <hr style="margin: 15px 0;">
    <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="pilihSemua"></th>
                <th>Nama</th>
                <th>Status</th>
                <th>Berkas</th>
                <th>Input Nilai</th>
                <th>Aksi</th> </tr>
        </thead>
        <tbody>
            <?php if ($pendaftar && count($pendaftar) > 0): ?>
                <?php foreach($pendaftar as $p): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="pendaftar_ids[]" value="<?= $p['id'] ?>" class="pilih-satu">
                    </td>
                    <td><?= htmlspecialchars($p['nama_lengkap']) ?><br><small>Kategori: <?= htmlspecialchars($p['kategori']) ?></small></td>
                    <td>
                        <?= htmlspecialchars($p['status']) ?>
                        <?php if(isset($p['nilai'])): ?>
                            <br><strong>Nilai: <?= htmlspecialchars($p['nilai']) ?></strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($p['berkas_url'])): ?>
                            <button type="button" class="lihat-berkas-btn" data-berkas="<?= htmlspecialchars($p['berkas_url']) ?>">Lihat</button>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="white-space: nowrap;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <input type="number" step="0.01" name="nilai[<?= $p['id'] ?>]" value="<?= htmlspecialchars($p['nilai'] ?? '') ?>" placeholder="Input Nilai" style="width:100px; margin-bottom: 0;">
                            <button type="submit" name="update_nilai_individu" value="<?= $p['id'] ?>">Simpan</button>
                        </div>
                    </td>
                    <td>
                        <button type="submit" name="hapus_peserta" value="<?= $p['id'] ?>" onclick="return confirm('Yakin ingin menghapus peserta ini secara permanen?');">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">Tidak ada data yang cocok dengan filter.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</form> 

<div class="pagination">
    <?php
    $filter_query_for_pagination = http_build_query($filter_values);
    ?>
    <?php if ($current_page > 1): ?>
        <a href="?page=<?= $current_page - 1 ?>&<?= $filter_query_for_pagination ?>">&laquo; Sebelumnya</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>&<?= $filter_query_for_pagination ?>" class="<?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($current_page < $total_pages): ?>
        <a href="?page=<?= $current_page + 1 ?>&<?= $filter_query_for_pagination ?>">Selanjutnya &raquo;</a>
    <?php endif; ?>
</div>

<div id="berkasModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h4>Daftar Berkas</h4>
        <div id="berkasList"></div>
    </div>
</div>

<script src="assets/js/script.js"></script>
</body>
</html>