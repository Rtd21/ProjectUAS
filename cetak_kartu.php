<?php
session_start();
require 'supabase.php';
require 'fpdf.php'; // Panggil library FPDF yang sudah diunduh

if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$user_id = $_SESSION['user_id'];

// Ambil data lengkap pendaftar, termasuk jadwal dan foto
$pendaftaran_result = supabase_request("GET", "/rest/v1/pendaftaran?user_id=eq.$user_id&select=*,jadwal_seleksi(nama,tanggal),users(username)");
if (!$pendaftaran_result['data']) {
    die("Data pendaftaran tidak ditemukan.");
}
$data = $pendaftaran_result['data'][0];

// Inisialisasi PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// Header Kartu
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'KARTU PESERTA UJIAN SELEKSI', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 7, 'PENERIMAAN MAHASISWA BARU', 0, 1, 'C');
$pdf->Line(10, 30, 200, 30);
$pdf->Ln(10); // Spasi

// Tampilkan Foto Profil
if (!empty($data['foto_url'])) {
    // FPDF memerlukan path lokal atau data gambar, kita ambil dari URL
    $imageData = @file_get_contents($data['foto_url']);
    if ($imageData) {
        $pdf->Image('data:image/jpeg;base64,'.base64_encode($imageData), 150, 35, 40, 0, 'JPG');
    }
}

// Detail Peserta
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(50, 8, 'No. Pendaftaran', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0, 'C');
$pdf->Cell(0, 8, $data['id'], 0, 1);

$pdf->Cell(50, 8, 'Nama Lengkap', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0, 'C');
$pdf->Cell(0, 8, $data['nama_lengkap'], 0, 1);

$pdf->Cell(50, 8, 'Program Studi', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0, 'C');
$pdf->Cell(0, 8, $data['jurusan'], 0, 1);

$pdf->Cell(50, 8, 'Jenjang', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0, 'C');
$pdf->Cell(0, 8, $data['jenjang'], 0, 1);

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Jadwal Ujian', 0, 1);
$pdf->SetFont('Arial', '', 11);

if ($data['jadwal_seleksi']) {
    $jadwal = $data['jadwal_seleksi'];
    $tanggal_ujian = (new DateTime($jadwal['tanggal'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d F Y, H:i \W\I\B');
    
    $pdf->Cell(50, 8, 'Nama Ujian', 0, 0);
    $pdf->Cell(5, 8, ':', 0, 0, 'C');
    $pdf->Cell(0, 8, $jadwal['nama'], 0, 1);

    $pdf->Cell(50, 8, 'Tanggal & Waktu', 0, 0);
    $pdf->Cell(5, 8, ':', 0, 0, 'C');
    $pdf->Cell(0, 8, $tanggal_ujian, 0, 1);
} else {
    $pdf->Cell(0, 8, 'Jadwal belum ditentukan. Harap hubungi panitia.', 0, 1);
}

// Output PDF untuk di-download
$pdf->Output('D', 'Kartu-Peserta-' . $data['nama_lengkap'] . '.pdf');
exit;