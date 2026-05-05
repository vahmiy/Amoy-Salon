<?php
session_start();
// Cek jika belum login
if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

include 'koneksi.php';

// --- LOGIKA LAYANAN ---

// 1. Tambah Layanan
if (isset($_POST['save_service'])) {
    $nama   = mysqli_real_escape_string($conn, $_POST['nama_layanan']);
    $harga  = $_POST['harga'];
    $komisi = $_POST['komisi_persen'];

    $query = "INSERT INTO services (nama_layanan, harga, komisi_persen) VALUES ('$nama', '$harga', '$komisi')";
    mysqli_query($conn, $query);
    header("Location: ../pages/master_data.php");
    exit;
}

// 2. Edit Layanan (PENTING: Ini yang sebelumnya hilang)
if (isset($_POST['edit_service'])) {
    $id     = $_POST['id_service'];
    $nama   = mysqli_real_escape_string($conn, $_POST['nama_layanan']);
    $harga  = $_POST['harga'];
    $komisi = $_POST['komisi_persen'];

    $query = "UPDATE services SET 
              nama_layanan = '$nama', 
              harga = '$harga', 
              komisi_persen = '$komisi' 
              WHERE id_service = '$id'";
    
    mysqli_query($conn, $query);
    header("Location: ../pages/master_data.php");
    exit;
}


// --- LOGIKA KARYAWAN ---

// 3. Tambah Karyawan
if (isset($_POST['save_employee'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_karyawan']);
    $spesialisasi = mysqli_real_escape_string($conn, $_POST['spesialisasi']);
    
    mysqli_query($conn, "INSERT INTO employees (nama_karyawan, spesialisasi) VALUES ('$nama', '$spesialisasi')");
    header("Location: ../pages/master_data.php");
    exit;
}

// 4. Edit Karyawan (PENTING: Ini juga sebelumnya hilang)
if (isset($_POST['edit_employee'])) {
    $id   = $_POST['id_employee'];
    $nama = mysqli_real_escape_string($conn, $_POST['nama_karyawan']);
    $spesialisasi = mysqli_real_escape_string($conn, $_POST['spesialisasi']);

    $query = "UPDATE employees SET 
              nama_karyawan = '$nama', 
              spesialisasi = '$spesialisasi' 
              WHERE id_employee = '$id'";
    
    mysqli_query($conn, $query);
    header("Location: ../pages/master_data.php");
    exit;
}

// Jika file diakses tanpa POST yang jelas, kembalikan ke halaman master
header("Location: ../pages/master_data.php");
exit;
?>