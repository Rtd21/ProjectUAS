<?php
session_start();
require 'supabase.php';
require 'mailer.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$user_id = $_SESSION['user_id'];

function upload_to_supabase_storage($file, $sub_folder = '') {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = ($sub_folder ? $sub_folder . '/' : '') . uniqid() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', basename($file['name'], '.' . $file_extension)) . '.' . $file_extension;
        
        $upload_url = $_ENV['SUPABASE_URL'] . "/storage/v1/object/" . $_ENV['SUPABASE_STORAGE_BUCKET'] . "/" . $file_name;
        $file_data = file_get_contents($file['tmp_name']);

        $ch = curl_init($upload_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $_ENV['SUPABASE_KEY'],
            "apikey: " . $_ENV['SUPABASE_KEY'],
            "Content-Type: " . mime_content_type($file['tmp_name']),
            "x-upsert: true"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status == 200) {
            return $_ENV['SUPABASE_URL'] . "/storage/v1/object/public/" . $_ENV['SUPABASE_STORAGE_BUCKET'] . "/" . $file_name;
        } else {
            error_log("Supabase Upload Error: Status " . $status . " Response: " . $response);
        }
    }
    return null;
}

$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['form_data'] = $_POST;
    $errors = [];
    
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    
    if (empty($nama_lengkap)) $errors[] = "Nama lengkap wajib diisi.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid.";
    if (!empty($no_hp) && !preg_match('/^[0-9\-\+\s\(\)]+$/', $no_hp)) $errors[] = "Format Nomor HP tidak valid.";
    if (empty($_FILES['berkas']['name'][0])) $errors[] = "Minimal satu berkas wajib diunggah.";
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        header('Location: daftar.php');
        exit();
    }
    
    $berkas_urls = [];
    if (isset($_FILES['berkas']) && is_array($_FILES['berkas']['name'])) {
        $file_count = count($_FILES['berkas']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['berkas']['error'][$i] === UPLOAD_ERR_OK) {
                $single_file_info = ['name' => $_FILES['berkas']['name'][$i], 'tmp_name' => $_FILES['berkas']['tmp_name'][$i], 'error' => $_FILES['berkas']['error'][$i]];
                $url = upload_to_supabase_storage($single_file_info, 'berkas-pendaftaran');
                if ($url) $berkas_urls[] = $url;
            }
        }
    }

    // --- PERUBAHAN BARU: Logika untuk upload foto profil saat pendaftaran ---
    $foto_url = null;
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $foto_url = upload_to_supabase_storage($_FILES['foto_profil'], 'foto-profil');
    }
    // --- AKHIR PERUBAHAN ---
    
    $jadwal_id = null;
    $status_awal = 'Menunggu Verifikasi'; 
    $kategori = $_POST['kategori'] ?? 'umum';
    $waktu_sekarang_utc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s');
    $endpoint_jadwal = "/rest/v1/jadwal_seleksi?kategori=eq.$kategori&tanggal=gte.$waktu_sekarang_utc&order=tanggal.asc&limit=1";
    $jadwal_result = supabase_request("GET", $endpoint_jadwal);

    if ($jadwal_result['data'] && count($jadwal_result['data']) > 0) {
        $jadwal_id = $jadwal_result['data'][0]['id'];
    }
    
    $jurusan = $_POST['jurusan'] ?? '';
    $jenjang = $_POST['jenjang'] ?? '';
    $sistem_kuliah = $_POST['sistem_kuliah'] ?? '';

    $data_to_insert = [
        'user_id' => $user_id,
        'nama_lengkap' => $nama_lengkap,
        'ttl' => $_POST['ttl'] ?? '',
        'jenis_kelamin' => $_POST['jenis_kelamin'] ?? '',
        'alamat' => $_POST['alamat'] ?? '',
        'no_hp' => $no_hp,
        'email' => $email,
        'pendidikan' => $_POST['pendidikan'] ?? '',
        'jurusan' => $jurusan,
        'jenjang' => $jenjang,
        'sistem_kuliah' => $sistem_kuliah,
        'kategori' => $kategori,
        'nama_ortu' => $_POST['nama_ortu'] ?? '',
        'pendapatan_ortu' => $_POST['pendapatan_ortu'] ?? '',
        'berkas_url' => implode(',', $berkas_urls),
        'foto_url' => $foto_url, // PERUBAHAN BARU: Menyimpan URL foto
        'status' => $status_awal,
        'jadwal_id' => $jadwal_id,
    ];

    $response_db = supabase_request("POST", "/rest/v1/pendaftaran", $data_to_insert);
    
    if ($response_db['data']) {
        $subject = "Konfirmasi Pendaftaran di " . ($_ENV['MAIL_FROM_NAME'] ?? 'Aplikasi Pendaftaran');
        $body = "
            <p>Yth. <strong>" . htmlspecialchars($nama_lengkap) . "</strong>,</p>
            <p>Terima kasih telah melakukan pendaftaran. Data Anda telah berhasil kami terima.</p>
            <p>Status pendaftaran Anda saat ini adalah <strong>".htmlspecialchars($status_awal)."</strong>.</p>
        ";
        send_email($email, $subject, $body);

        unset($_SESSION['form_data']);
        $_SESSION['success_message'] = "Pendaftaran berhasil disimpan!";
        header('Location: dashboard.php');
        exit();
    } else {
        $_SESSION['error_message'] = "Terjadi kesalahan saat menyimpan data: " . ($response_db['error']['message'] ?? 'Gagal menyimpan data.');
        header('Location: daftar.php');
        exit();
    }
}

$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Formulir Pendaftaran</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="content-wrapper">
    <h2>Formulir Pendaftaran</h2>

    <?php if ($error_message): ?><div class="message error"><?= $error_message ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="daftar.php">
        <label>Nama Lengkap:</label><br>
        <input type="text" name="nama_lengkap" required value="<?= htmlspecialchars($form_data['nama_lengkap'] ?? '') ?>"><br>

        <label>Upload Foto Profil (Opsional):</label><br>
        <input type="file" name="foto_profil" accept="image/jpeg, image/png"><br><br>

        <label>Tempat, Tanggal Lahir:</label><br>
        <input type="text" name="ttl" required value="<?= htmlspecialchars($form_data['ttl'] ?? '') ?>"><br>

        <label>Jenis Kelamin:</label><br>
        <select name="jenis_kelamin" required>
            <option value="">--Pilih--</option>
            <option value="Laki-laki" <?= (($form_data['jenis_kelamin'] ?? '') == 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
            <option value="Perempuan" <?= (($form_data['jenis_kelamin'] ?? '') == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
        </select><br>

        <label>Alamat:</label><br>
        <textarea name="alamat" required><?= htmlspecialchars($form_data['alamat'] ?? '') ?></textarea><br>
        
        <label>No HP:</label><br>
        <input type="text" name="no_hp" required value="<?= htmlspecialchars($form_data['no_hp'] ?? '') ?>"><br>

        <label>Email:</label><br>
        <input type="email" name="email" required value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"><br>

        <label>Pendidikan Terakhir:</label><br>
        <select name="pendidikan" required>
            <option value="">--Pilih--</option>
            <option value="SMK/SMA Sederajat" <?= (($form_data['pendidikan'] ?? '') == 'SMK/SMA Sederajat') ? 'selected' : '' ?>>SMK/SMA Sederajat</option>
            <option value="D1" <?= (($form_data['pendidikan'] ?? '') == 'D1') ? 'selected' : '' ?>>D1</option>
            <option value="D2" <?= (($form_data['pendidikan'] ?? '') == 'D2') ? 'selected' : '' ?>>D2</option>
            <option value="D3" <?= (($form_data['pendidikan'] ?? '') == 'D3') ? 'selected' : '' ?>>D3</option>
            <option value="S1" <?= (($form_data['pendidikan'] ?? '') == 'S1') ? 'selected' : '' ?>>S1</option>
            <option value="S2" <?= (($form_data['pendidikan'] ?? '') == 'S2') ? 'selected' : '' ?>>S2</option>
        </select><br>

        <label>Jurusan:</label><br>
        <select name="jurusan" required>
            <option value="">--Pilih--</option>
            <option value="Informatika" <?= (($form_data['jurusan'] ?? '') == 'Informatika') ? 'selected' : '' ?>>Informatika</option>
            <option value="Sistem Informasi" <?= (($form_data['jurusan'] ?? '') == 'Sistem Informasi') ? 'selected' : '' ?>>Sistem Informasi</option>
            <option value="RPL" <?= (($form_data['jurusan'] ?? '') == 'RPL') ? 'selected' : '' ?>>RPL</option>
            <option value="Manajemen" <?= (($form_data['jurusan'] ?? '') == 'Manajemen') ? 'selected' : '' ?>>Manajemen</option>
            <option value="Kewirausahaan" <?= (($form_data['jurusan'] ?? '') == 'Kewirausahaan') ? 'selected' : '' ?>>Kewirausahaan</option>
        </select><br>

        <label>Jenjang:</label><br>
        <select name="jenjang" required>
            <option value="">--Pilih--</option>
            <option value="D1" <?= (($form_data['jenjang'] ?? '') == 'D1') ? 'selected' : '' ?>>D1</option>
            <option value="D2" <?= (($form_data['jenjang'] ?? '') == 'D2') ? 'selected' : '' ?>>D2</option>
            <option value="D3" <?= (($form_data['jenjang'] ?? '') == 'D3') ? 'selected' : '' ?>>D3</option>
            <option value="S1" <?= (($form_data['jenjang'] ?? '') == 'S1') ? 'selected' : '' ?>>S1</option>
            <option value="S2" <?= (($form_data['jenjang'] ?? '') == 'S2') ? 'selected' : '' ?>>S2</option>
            <option value="S3" <?= (($form_data['jenjang'] ?? '') == 'S3') ? 'selected' : '' ?>>S3</option>
        </select><br>

        <label>Sistem Kuliah:</label><br>
        <select name="sistem_kuliah" required>
            <option value="">--Pilih--</option>
            <option value="Kelas Reguler" <?= (($form_data['sistem_kuliah'] ?? '') == 'Kelas Reguler') ? 'selected' : '' ?>>Kelas Reguler</option>
            <option value="Kelas Karyawan Malam" <?= (($form_data['sistem_kuliah'] ?? '') == 'Kelas Karyawan Malam') ? 'selected' : '' ?>>Kelas Karyawan Malam</option>
            <option value="Karyawan Jumat Sabtu" <?= (($form_data['sistem_kuliah'] ?? '') == 'Karyawan Jumat Sabtu') ? 'selected' : '' ?>>Karyawan Jumat Sabtu</option>
        </select><br>

        <label>Kategori:</label><br>
        <select name="kategori" required>
            <option value="">--Pilih Kategori--</option>
            <option value="umum" <?= (($form_data['kategori'] ?? '') == 'umum') ? 'selected' : '' ?>>Umum</option>
            <option value="beasiswa" <?= (($form_data['kategori'] ?? '') == 'beasiswa') ? 'selected' : '' ?>>Beasiswa</option>
        </select><br>

        <label>Nama Orang Tua / Wali:</label><br>
        <input type="text" name="nama_ortu" required value="<?= htmlspecialchars($form_data['nama_ortu'] ?? '') ?>"><br>

        <label>Pendapatan Orang Tua:</label><br>
        <input type="text" name="pendapatan_ortu" required value="<?= htmlspecialchars($form_data['pendapatan_ortu'] ?? '') ?>"><br>

        <label>Upload Berkas Pendaftaran (Bisa Pilih Lebih dari 1, Wajib):</label><br>
        <input type="file" name="berkas[]" accept="image/*,application/pdf" multiple required><br>

        <button type="submit">Kirim Pendaftaran</button>
    </form>
</div>
<script src="assets/js/script.js"></script>
</body>
</html>