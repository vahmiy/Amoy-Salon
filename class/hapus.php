<?php
session_start();
// Cek jika belum login
if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

// Ambil level untuk kemudahan pengecekan
$user_level = $_SESSION['level'];
include 'koneksi.php';

// Pastikan ada parameter 'type' dan 'id' di URL
if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id   = $_GET['id'];

    if ($type == 'service') {
        // Hapus Layanan
        $query = "DELETE FROM services WHERE id_service = '$id'";
    } elseif ($type == 'employee') {
        // Hapus Karyawan
        $query = "DELETE FROM employees WHERE id_employee = '$id'";
    }

    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Data berhasil dihapus!'); window.location='../pages/master_data.php';</script>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
} else {
    header("Location: ../pages/master_data.php");
}
?>