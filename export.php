<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

require 'supabase.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pendaftar_result = supabase_request("GET", "/rest/v1/pendaftaran?select=*,jadwal_seleksi(nama)");
$pendaftar = $pendaftar_result['data'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Peserta');

$headers = [
    'A1' => 'Nama Lengkap', 'B1' => 'Email', 'C1' => 'No. HP', 'D1' => 'Kategori',
    'E1' => 'Jurusan', 'F1' => 'Jenjang', 'G1' => 'Status', 'H1' => 'Nilai',
    'I1' => 'Jadwal Seleksi'
];
foreach($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
    $sheet->getStyle($cell)->getFont()->setBold(true);
}

$row = 2;
if ($pendaftar) {
    foreach ($pendaftar as $p) {
        $nama_jadwal = $p['jadwal_seleksi']['nama'] ?? 'Belum Ditentukan';
        $sheet->setCellValue('A'.$row, $p['nama_lengkap']);
        $sheet->setCellValue('B'.$row, $p['email']);
        $sheet->setCellValue('C'.$row, $p['no_hp']);
        $sheet->setCellValue('D'.$row, $p['kategori']);
        $sheet->setCellValue('E'.$row, $p['jurusan']);
        $sheet->setCellValue('F'.$row, $p['jenjang']);
        $sheet->setCellValue('G'.$row, $p['status']);
        $sheet->setCellValue('H'.$row, $p['nilai']);
        $sheet->setCellValue('I'.$row, $nama_jadwal);
        $row++;
    }
}

foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="data_peserta_pendaftaran_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>