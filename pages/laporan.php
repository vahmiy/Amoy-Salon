<?php
session_start();
// Cek jika belum login
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

// Ambil level untuk kemudahan pengecekan
$user_level = $_SESSION['level'];
include '../class/koneksi.php';

$today = date('Y-m-d');
$tgl_pilih = $_GET['tgl'] ?? $today;

// Hitung total pendapatan hari terpilih (Hanya yang Lunas)
$q_total = mysqli_query($conn, "SELECT SUM(total_biaya) as grand_total FROM bookings WHERE tgl_booking = '$tgl_pilih' AND status_pembayaran = 'lunas'");
$res_total = mysqli_fetch_array($q_total);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pendapatan - Amoy Salon</title>
        <link rel="icon" type="png" href="../asset/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f1f2f6; }
        .navbar { background-color: #2f3542; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table thead { background-color: #57606f; color: white; }
        .text-nominal { font-family: 'Courier New', Courier, monospace; font-weight: bold; }
        .badge-karyawan { background-color: #e9ecef; color: #495057; border: 1px solid #dee2e6; margin-right: 4px; padding: 2px 8px; border-radius: 4px; font-size: 0.85rem; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin.php">✨ Amoy Salon Dashboard</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <!-- Level 0, 1, 2 bisa melihat Daftar Booking -->
                <?php if ($_SESSION['level'] <= 2): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin.php">Daftar Booking</a>
                    </li>
                <?php endif; ?>

                <!-- HANYA Level 0 (Super User) dan Level 1 (Admin) yang bisa melihat Master Data -->
                <?php if ($_SESSION['level'] <= 1): ?>
                    <li class="nav-item">
                        <a class="nav-link text-warning" href="master_data.php">⚙️ Master Data</a>
                    </li>
                <?php endif; ?>

                <!-- Level 0, 1, dan 2 bisa melihat Pendapatan & Komisi -->
                <?php if ($_SESSION['level'] <= 2): ?>
                    <li class="nav-item">
                        <a class="nav-link text-info" href="laporan.php">📊 Pendapatan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning" href="komisi.php">💰 Komisi</a>
                    </li>
                <?php endif; ?>

                <!-- Link eksternal untuk semua level yang sudah login -->
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-sm btn-outline-light mt-1" href="../index.php" target="_blank">Booking Online</a>
                </li>

                <!-- Tombol Logout -->
                <li class="nav-item ms-lg-3">
                    <a class="nav-link text-danger fw-bold" href="../class/logout.php" onclick="return confirm('Yakin ingin keluar?')">
                        <i class="bi bi-box-arrow-right"></i> Keluar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="card p-4 border-0 shadow-sm">
        <h3 class="mb-4 text-center">📊 Laporan Pendapatan Harian</h3>
        
        <form action="" method="GET" class="row g-2 mb-4 justify-content-center">
            <div class="col-auto">
                <input type="date" name="tgl" class="form-control" value="<?= $tgl_pilih; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-dark">Cek Laporan</button>
            </div>
        </form>

        <div class="row text-center mb-4">
            <div class="col-md-12">
                <div class="p-3 bg-primary text-white rounded">
                    <h5>Total Pendapatan (Lunas)</h5>
                    <h2 class="fw-bold">Rp <?= number_format($res_total['grand_total'] ?? 0, 0, ',', '.'); ?></h2>
                </div>
            </div>
        </div>

        <h5>Rincian Transaksi:</h5>
        <div class="table-responsive">
            <table class="table table-striped mt-3 align-middle">
                <thead>
                    <tr>
                        <th>ID Booking</th>
                        <th>Customer</th>
                        <th>Karyawan / Petugas</th>
                        <th>Total Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query untuk menggabungkan nama-nama karyawan dari booking_details
                    $sql = "SELECT 
                                b.id_booking, 
                                b.nama_customer, 
                                b.total_biaya,
                                GROUP_CONCAT(DISTINCT e.nama_karyawan SEPARATOR ', ') as daftar_karyawan
                            FROM bookings b 
                            LEFT JOIN booking_details d ON b.id_booking = d.id_booking
                            LEFT JOIN employees e ON d.id_employee = e.id_employee 
                            WHERE b.tgl_booking = '$tgl_pilih' AND b.status_pembayaran = 'lunas'
                            GROUP BY b.id_booking
                            ORDER BY b.id_booking DESC";

                    $q_list = mysqli_query($conn, $sql);
                    
                    if (mysqli_num_rows($q_list) > 0) {
                        while($l = mysqli_fetch_array($q_list)){
                            $petugas = !empty($l['daftar_karyawan']) ? $l['daftar_karyawan'] : '<span class="text-muted small italic">Belum ditentukan</span>';
                            
                            echo "<tr>
                                    <td class='fw-bold text-primary'>#{$l['id_booking']}</td>
                                    <td>{$l['nama_customer']}</td>
                                    <td>{$petugas}</td>
                                    <td class='text-nominal'>Rp ".number_format($l['total_biaya'], 0, ',', '.')."</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center py-4 text-muted'>Tidak ada data transaksi lunas pada tanggal ini.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            <a href="admin.php" class="btn btn-outline-secondary">Kembali ke Dashboard</a>
            <button onclick="window.print()" class="btn btn-success float-end"><i class="bi bi-printer me-2"></i>Cetak</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>