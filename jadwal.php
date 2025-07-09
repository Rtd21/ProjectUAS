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

function kirim_notifikasi_jadwal($kategori, $jadwal_nama, $jadwal_tanggal_wib_string) {
    $peserta_result = supabase_request("GET", "/rest/v1/pendaftaran?kategori=eq.$kategori&select=email,nama_lengkap");
    if ($peserta_result['data'] && count($peserta_result['data']) > 0) {
        $peserta_list = $peserta_result['data'];
        $subject = "Pembaruan Jadwal Seleksi: $jadwal_nama";
        foreach ($peserta_list as $p) {
            $body = "
                <p>Yth. <strong>" . htmlspecialchars($p['nama_lengkap']) . "</strong>,</p>
                <p>Kami informasikan bahwa jadwal seleksi untuk kategori pendaftaran Anda telah diperbarui.</p>
                <hr>
                <p><strong>Nama Seleksi:</strong> " . htmlspecialchars($jadwal_nama) . "</p>
                <p><strong>Tanggal & Waktu Baru:</strong> " . htmlspecialchars($jadwal_tanggal_wib_string) . "</p>
                <hr>
                <p>Silakan periksa dashboard Anda secara berkala untuk detail lebih lanjut. Terima kasih.</p>
                <p>Hormat kami,<br><strong>Tim Seleksi " . ($_ENV['MAIL_FROM_NAME'] ?? 'Aplikasi Pendaftaran') . "</strong></p>
            ";
            send_email($p['email'], $subject, $body);
        }
        return count($peserta_list);
    }
    return 0;
}

function update_jadwal_ke_peserta($kategori, $jadwal_id, $nilai_minimal) {
    $peserta_result = supabase_request("GET", "/rest/v1/pendaftaran?kategori=eq.$kategori&or=(jadwal_id.is.null,jadwal_id.eq.$jadwal_id)");
    if ($peserta_result['data']) {
        foreach ($peserta_result['data'] as $p) {
            $status = 'Menunggu Jadwal Ujian';
            if (isset($p['nilai']) && is_numeric($p['nilai'])) {
                $status = floatval($p['nilai']) >= $nilai_minimal ? 'Lulus' : 'Tidak Lulus';
            }
            supabase_request("PATCH", "/rest/v1/pendaftaran?id=eq.{$p['id']}", [
                'jadwal_id' => $jadwal_id,
                'status' => $status
            ]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_jadwal'])) {
        $nama = trim($_POST['nama']);
        $tanggal = $_POST['tanggal'];
        $kategori = $_POST['kategori'];
        $nilai_min_lulus = floatval($_POST['nilai_min_lulus']);

        $dt = new DateTime($tanggal, new DateTimeZone('Asia/Jakarta'));
        $dt_utc = clone $dt;
        $dt_utc->setTimezone(new DateTimeZone('UTC'));
        $tanggal_utc = $dt_utc->format(DateTime::ATOM);

        $res = supabase_request("POST", "/rest/v1/jadwal_seleksi", ['nama' => $nama, 'tanggal' => $tanggal_utc, 'kategori' => $kategori, 'nilai_min_lulus' => $nilai_min_lulus]);
        
        if ($res['data']) {
            $jadwal_id = $res['data'][0]['id'];
            update_jadwal_ke_peserta($kategori, $jadwal_id, $nilai_min_lulus);
            $jumlah_terkirim = kirim_notifikasi_jadwal($kategori, $nama, $dt->format('d F Y, H:i \W\I\B'));
            $_SESSION['success_message'] = "Jadwal berhasil ditambahkan. Notifikasi dikirim ke $jumlah_terkirim peserta.";
        } else {
            $_SESSION['error_message'] = "Gagal menambah jadwal: " . ($res['error']['message'] ?? 'Unknown error');
        }
    } 
    elseif (isset($_POST['edit_jadwal'])) {
        $id = $_POST['id'];
        $nama = $_POST['nama'];
        $tanggal = $_POST['tanggal'];
        $kategori = $_POST['kategori'];
        $nilai_minimal = isset($_POST['nilai_min_lulus']) ? floatval($_POST['nilai_min_lulus']) : 0;

        $dt = new DateTime($tanggal, new DateTimeZone('Asia/Jakarta'));
        $dt_utc = clone $dt;
        $dt_utc->setTimezone(new DateTimeZone('UTC'));
        $tanggal_utc = $dt_utc->format(DateTime::ATOM);

        supabase_request("PATCH", "/rest/v1/jadwal_seleksi?id=eq.$id", [
            'nama' => $nama,
            'tanggal' => $tanggal_utc,
            'kategori' => $kategori,
            'nilai_min_lulus' => $nilai_minimal
        ]);
        
        update_jadwal_ke_peserta($kategori, $id, $nilai_minimal);
        
        $jumlah_terkirim = kirim_notifikasi_jadwal($kategori, $nama, $dt->format('d F Y, H:i \W\I\B'));
        $_SESSION['success_message'] = "Jadwal berhasil diupdate. Notifikasi pembaruan dikirim ke $jumlah_terkirim peserta.";

    } elseif (isset($_POST['hapus_jadwal'])) {
        $id = $_POST['id'];
        
        supabase_request("PATCH", "/rest/v1/pendaftaran?jadwal_id=eq.$id", [
            'jadwal_id' => null,
            'status' => 'Lulus Administrasi'
        ]);
        
        supabase_request("DELETE", "/rest/v1/jadwal_seleksi?id=eq.$id");
        $_SESSION['success_message'] = "Jadwal berhasil dihapus.";
    }
    header("Location: jadwal.php");
    exit;
}

$semua_jadwal_result = supabase_request("GET", "/rest/v1/jadwal_seleksi?order=tanggal.desc");
$semua_jadwal = $semua_jadwal_result['data'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kelola Jadwal Seleksi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<h2>Kelola Jadwal Seleksi</h2>
<a href="admin_dashboard.php">Dashboard Admin</a>

<?php if ($success_message): ?><div class='message success'><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
<?php if ($error_message): ?><div class='message error'><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

<form method="post" action="jadwal.php" style="margin-bottom: 30px;">
    <input type="text" name="nama" placeholder="Nama Jadwal (cth: Ujian Gelombang 1)" required>
    <input type="datetime-local" name="tanggal" required>
    <select name="kategori" required>
        <option value="umum">Umum</option>
        <option value="beasiswa">Beasiswa</option>
    </select>
    <input type="number" step="0.01" name="nilai_min_lulus" placeholder="Nilai Minimal Lulus" required>
    <button type="submit" name="tambah_jadwal">Tambah Jadwal</button>
</form>

<h3>Daftar Jadwal Seleksi</h3>
<table>
    <thead>
        <tr>
            <th>Nama Jadwal</th>
            <th>Tanggal & Waktu (WIB)</th>
            <th>Kategori</th>
            <th>Nilai Minimal</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if($semua_jadwal): ?>
        <?php foreach ($semua_jadwal as $j): ?>
        <tr>
            <form method="post" action="jadwal.php">
                <td>
                    <input type="text" name="nama" value="<?= htmlspecialchars($j['nama']) ?>" required>
                </td>
                <td>
                    <input type="datetime-local" name="tanggal"
                        value="<?= (new DateTime($j['tanggal'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d\TH:i') ?>" required>
                </td>
                <td>
                    <input type="hidden" name="kategori" value="<?= htmlspecialchars($j['kategori']) ?>">
                    <?= htmlspecialchars(ucfirst($j['kategori'])) ?>
                </td>
                <td>
                    <input type="number" step="0.01" name="nilai_min_lulus" value="<?= htmlspecialchars($j['nilai_min_lulus']) ?>" required>
                </td>
                <td>
                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                    <button type="submit" name="edit_jadwal">Simpan</button>
                    <button type="submit" name="hapus_jadwal" onclick="return confirm('Yakin hapus jadwal ini? Ini akan mereset jadwal pada peserta terkait.')">Hapus</button>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="5" style="text-align: center;">Belum ada jadwal yang dibuat.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>