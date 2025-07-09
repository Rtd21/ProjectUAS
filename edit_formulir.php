<?php
session_start();
require 'supabase.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
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

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    if (empty($nama_lengkap)) $errors[] = "Nama lengkap wajib diisi.";
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        header('Location: edit_formulir.php');
        exit();
    }

    $update_data = [
        'nama_lengkap'    => $nama_lengkap,
        'email'           => trim($_POST['email'] ?? ''),
        'kategori'        => trim($_POST['kategori'] ?? ''),
        'ttl'             => trim($_POST['ttl'] ?? ''),
        'jenis_kelamin'   => trim($_POST['jenis_kelamin'] ?? ''),
        'alamat'          => trim($_POST['alamat'] ?? ''),
        'no_hp'           => trim($_POST['no_hp'] ?? ''),
        'pendidikan'      => trim($_POST['pendidikan'] ?? ''),
        'jurusan'         => trim($_POST['jurusan'] ?? ''),
        'jenjang'         => trim($_POST['jenjang'] ?? ''),
        'sistem_kuliah'   => trim($_POST['sistem_kuliah'] ?? ''),
        'nama_ortu'       => trim($_POST['nama_ortu'] ?? ''),
        'pendapatan_ortu' => trim($_POST['pendapatan_ortu'] ?? '')
    ];

    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $new_photo_url = upload_to_supabase_storage($_FILES['foto_profil'], 'foto-profil');
        if ($new_photo_url) {
            $update_data['foto_url'] = $new_photo_url;
        }
    }

    $new_berkas_urls = [];
    if (isset($_FILES['berkas_baru']) && !empty($_FILES['berkas_baru']['name'][0])) {
        $file_count = count($_FILES['berkas_baru']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['berkas_baru']['error'][$i] === UPLOAD_ERR_OK) {
                $single_file_info = ['name' => $_FILES['berkas_baru']['name'][$i], 'tmp_name' => $_FILES['berkas_baru']['tmp_name'][$i], 'error' => $_FILES['berkas_baru']['error'][$i]];
                $url = upload_to_supabase_storage($single_file_info, 'berkas-tambahan');
                if ($url) $new_berkas_urls[] = $url;
            }
        }
    }

    $existing_berkas_urls = isset($_POST['existing_berkas_urls']) && !empty($_POST['existing_berkas_urls']) ? explode(',', $_POST['existing_berkas_urls']) : [];
    $all_berkas_urls = array_merge($existing_berkas_urls, $new_berkas_urls);
    $update_data['berkas_url'] = implode(',', array_filter($all_berkas_urls));

    $response = supabase_request("PATCH", "/rest/v1/pendaftaran?user_id=eq.$user_id", $update_data);
    
    if (isset($response['error'])) {
        $_SESSION['error_message'] = "Gagal mengupdate data: " . ($response['error']['message'] ?? 'Unknown error');
    } else {
        $_SESSION['success_message'] = "Data formulir berhasil diupdate!";
    }
    header("Location: dashboard.php");
    exit;
}

$profile_result = supabase_request("GET", "/rest/v1/pendaftaran?user_id=eq.$user_id&limit=1");
if (!$profile_result['data']) {
    header('Location: daftar.php');
    exit;
}
$profile = $profile_result['data'][0];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Formulir Pendaftaran</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="content-wrapper">
    <h2>Edit Formulir Pendaftaran</h2>
    <?php if ($success_message): ?><div class='message success'><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class='message error'><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        
        <h3>Foto Profil</h3>
        <?php if (!empty($profile['foto_url'])): ?>
            <img src="<?= htmlspecialchars($profile['foto_url']) ?>" alt="Foto Profil" style="max-width: 150px; border-radius: 8px; margin-bottom: 15px; display: block;">
        <?php else: ?>
            <p>Belum ada foto profil.</p>
        <?php endif; ?>
        <label>Ubah Foto Profil (Kosongkan jika tidak ingin mengubah):</label><br>
        <input type="file" name="foto_profil" accept="image/jpeg, image/png"><br><br>
        <hr>
        
        <h3>Data Diri</h3>
        <label>Nama Lengkap</label><br>
        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($profile['nama_lengkap'] ?? '') ?>" required><br><br>

        <label>Tempat, Tanggal Lahir</label><br>
        <input type="text" name="ttl" value="<?= htmlspecialchars($profile['ttl'] ?? '') ?>"><br><br>

        <label>Jenis Kelamin</label><br>
        <select name="jenis_kelamin">
            <option value="Laki-laki" <?= (($profile['jenis_kelamin'] ?? '') == 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
            <option value="Perempuan" <?= (($profile['jenis_kelamin'] ?? '') == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
        </select><br><br>

        <label>Alamat</label><br>
        <textarea name="alamat"><?= htmlspecialchars($profile['alamat'] ?? '') ?></textarea><br><br>

        <label>No HP</label><br>
        <input type="text" name="no_hp" value="<?= htmlspecialchars($profile['no_hp'] ?? '') ?>"><br><br>

        <label>Email</label><br>
        <input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>"><br><br>
        
        <hr>

        <h3>Data Akademik & Pilihan</h3>
        <label>Pendidikan Terakhir:</label><br>
        <select name="pendidikan" required>
            <option value="">--Pilih--</option>
            <option value="SMK/SMA Sederajat" <?= (($profile['pendidikan'] ?? '') == 'SMK/SMA Sederajat') ? 'selected' : '' ?>>SMK/SMA Sederajat</option>
            <option value="D1" <?= (($profile['pendidikan'] ?? '') == 'D1') ? 'selected' : '' ?>>D1</option>
            <option value="D2" <?= (($profile['pendidikan'] ?? '') == 'D2') ? 'selected' : '' ?>>D2</option>
            <option value="D3" <?= (($profile['pendidikan'] ?? '') == 'D3') ? 'selected' : '' ?>>D3</option>
            <option value="S1" <?= (($profile['pendidikan'] ?? '') == 'S1') ? 'selected' : '' ?>>S1</option>
            <option value="S2" <?= (($profile['pendidikan'] ?? '') == 'S2') ? 'selected' : '' ?>>S2</option>
        </select><br><br>

        <label>Jurusan Pilihan:</label><br>
        <select name="jurusan" required>
            <option value="">--Pilih--</option>
            <option value="Informatika" <?= (($profile['jurusan'] ?? '') == 'Informatika') ? 'selected' : '' ?>>Informatika</option>
            <option value="Sistem Informasi" <?= (($profile['jurusan'] ?? '') == 'Sistem Informasi') ? 'selected' : '' ?>>Sistem Informasi</option>
            <option value="RPL" <?= (($profile['jurusan'] ?? '') == 'RPL') ? 'selected' : '' ?>>RPL</option>
            <option value="Manajemen" <?= (($profile['jurusan'] ?? '') == 'Manajemen') ? 'selected' : '' ?>>Manajemen</option>
            <option value="Kewirausahaan" <?= (($profile['jurusan'] ?? '') == 'Kewirausahaan') ? 'selected' : '' ?>>Kewirausahaan</option>
        </select><br><br>

        <label>Jenjang Pilihan:</label><br>
        <select name="jenjang" required>
            <option value="">--Pilih--</option>
            <option value="D1" <?= (($profile['jenjang'] ?? '') == 'D1') ? 'selected' : '' ?>>D1</option>
            <option value="D2" <?= (($profile['jenjang'] ?? '') == 'D2') ? 'selected' : '' ?>>D2</option>
            <option value="D3" <?= (($profile['jenjang'] ?? '') == 'D3') ? 'selected' : '' ?>>D3</option>
            <option value="S1" <?= (($profile['jenjang'] ?? '') == 'S1') ? 'selected' : '' ?>>S1</option>
            <option value="S2" <?= (($profile['jenjang'] ?? '') == 'S2') ? 'selected' : '' ?>>S2</option>
            <option value="S3" <?= (($profile['jenjang'] ?? '') == 'S3') ? 'selected' : '' ?>>S3</option>
        </select><br><br>

        <label>Sistem Kuliah:</label><br>
        <select name="sistem_kuliah" required>
            <option value="">--Pilih--</option>
            <option value="Kelas Reguler" <?= (($profile['sistem_kuliah'] ?? '') == 'Kelas Reguler') ? 'selected' : '' ?>>Kelas Reguler</option>
            <option value="Kelas Karyawan Malam" <?= (($profile['sistem_kuliah'] ?? '') == 'Kelas Karyawan Malam') ? 'selected' : '' ?>>Kelas Karyawan Malam</option>
            <option value="Karyawan Jumat Sabtu" <?= (($profile['sistem_kuliah'] ?? '') == 'Karyawan Jumat Sabtu') ? 'selected' : '' ?>>Karyawan Jumat Sabtu</option>
        </select><br><br>

        <label>Kategori Pendaftaran:</label><br>
        <select name="kategori" required>
            <option value="">--Pilih Kategori--</option>
            <option value="umum" <?= (($profile['kategori'] ?? '') == 'umum') ? 'selected' : '' ?>>Umum</option>
            <option value="beasiswa" <?= (($profile['kategori'] ?? '') == 'beasiswa') ? 'selected' : '' ?>>Beasiswa</option>
        </select><br><br>

        <hr>
        <h3>Data Orang Tua / Wali</h3>
        <label>Nama Orang Tua</label><br>
        <input type="text" name="nama_ortu" value="<?= htmlspecialchars($profile['nama_ortu'] ?? '') ?>"><br><br>

        <label>Pendapatan Orang Tua</label><br>
        <input type="text" name="pendapatan_ortu" value="<?= htmlspecialchars($profile['pendapatan_ortu'] ?? '') ?>"><br><br>
        
        <hr>
        
        <h3>Berkas Pendaftaran</h3>
        <?php
        $berkas_lama = !empty($profile['berkas_url']) ? explode(',', $profile['berkas_url']) : [];
        if (!empty($berkas_lama[0])) {
            echo "<ul>";
            foreach ($berkas_lama as $url) {
                echo "<li><a href='" . htmlspecialchars($url) . "' target='_blank'>" . basename(parse_url($url, PHP_URL_PATH)) . "</a></li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Belum ada berkas yang diunggah.</p>";
        }
        ?>
        <input type="hidden" name="existing_berkas_urls" value="<?= htmlspecialchars($profile['berkas_url'] ?? '') ?>">
        <br>
        <label>Tambah Berkas Baru (Kosongkan jika tidak ingin menambah)</label><br>
        <input type="file" name="berkas_baru[]" accept="image/*,application/pdf" multiple><br><br>

        <button type="submit">Simpan Perubahan</button>
    </form>
    <br>
    <a href="dashboard.php">Kembali ke Dashboard</a>
</div>
<script src="assets/js/script.js"></script>
</body>
</html>