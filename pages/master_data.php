<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

// Ambil level untuk kemudahan pengecekan
$user_level = $_SESSION['level'];
include '../class/koneksi.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data - Salon</title>
        <link rel="icon" type="png" href="../asset/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f1f2f6; }
        .nav-tabs .nav-link.active { background-color: #6c5ce7; color: white; border: none; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        /* Memastikan Modal memiliki background putih solid */
        .modal-content {
            background-color: #ffffff !important;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        .modal-backdrop { opacity: 0.6 !important; }
        .form-label { font-weight: 600; color: #4b4b4b; font-size: 0.85rem; }

        body { background-color: #f1f2f6; }
        .navbar { background-color: #2f3542; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table thead { background-color: #57606f; color: white; }
        .list-petugas { padding-left: 0; list-style: none; margin-bottom: 0; }
        .list-petugas li { font-size: 0.85rem; margin-bottom: 5px; border-bottom: 1px dotted #ddd; padding-bottom: 3px; }
        .list-petugas li:last-child { border-bottom: none; }
        
        /* Merapikan tampilan search box DataTables */
        .dataTables_filter { margin-bottom: 15px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0; margin-left: 5px; }
    </style>
</head>
<body>

<!-- NAVIGASI ADMIN -->
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">⚙️ Manajemen Salon</h2>
        <a href="admin.php" class="btn btn-secondary px-4">Kembali</a>
    </div>

    <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
        <li class="nav-item"><button class="nav-link active px-4" data-bs-toggle="tab" data-bs-target="#services">Layanan</button></li>
        <li class="nav-item"><button class="nav-link px-4" data-bs-toggle="tab" data-bs-target="#employees">Karyawan</button></li>
    </ul>

    <div class="tab-content">
        <!-- TAB LAYANAN -->
        <div class="tab-pane fade show active" id="services">
            <div class="card p-4">
                <button class="btn btn-primary mb-3 w-auto shadow-sm" data-bs-toggle="modal" data-bs-target="#addService">+ Layanan Baru</button>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Layanan</th>
                                <th>Harga</th>
                                <th>Komisi (%)</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $qs = mysqli_query($conn, "SELECT * FROM services");
                            while($s = mysqli_fetch_array($qs)){
                            ?>
                            <tr>
                                <td><?= $s['nama_layanan']; ?></td>
                                <td>Rp <?= number_format($s['harga'], 0, ',', '.'); ?></td>
                                <td><?= $s['komisi_persen']; ?>%</td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editService<?= $s['id_service']; ?>">Edit</button>
                                    <a href="../class/hapus.php?type=service&id=<?= $s['id_service']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')">Hapus</a>
                                </td>
                            </tr>

                            <!-- MODAL EDIT LAYANAN -->
                            <div class="modal fade" id="editService<?= $s['id_service']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content shadow-lg">
                                        <form action="../class/proses_master.php" method="POST">
                                            <div class="modal-header border-0 bg-light">
                                                <h5 class="modal-title fw-bold">Edit Layanan</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="id_service" value="<?= $s['id_service']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label text-uppercase">Nama Layanan</label>
                                                    <input type="text" name="nama_layanan" class="form-control" value="<?= $s['nama_layanan']; ?>" required>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-7 mb-3">
                                                        <label class="form-label text-uppercase">Harga (Rp)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">Rp</span>
                                                            <input type="number" name="harga" class="form-control" value="<?= $s['harga']; ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-5 mb-3">
                                                        <label class="form-label text-uppercase">Komisi</label>
                                                        <div class="input-group">
                                                            <input type="number" name="komisi_persen" class="form-control" value="<?= $s['komisi_persen']; ?>" required>
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" name="edit_service" class="btn btn-primary px-4">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB KARYAWAN -->
        <div class="tab-pane fade" id="employees">
            <div class="card p-4">
                <button class="btn btn-primary mb-3 w-auto shadow-sm" data-bs-toggle="modal" data-bs-target="#addEmployee">+ Karyawan Baru</button>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Spesialisasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $qe = mysqli_query($conn, "SELECT * FROM employees");
                            while($e = mysqli_fetch_array($qe)){
                            ?>
                            <tr>
                                <td><?= $e['nama_karyawan']; ?></td>
                                <td><?= $e['spesialisasi']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editEmployee<?= $e['id_employee']; ?>">Edit</button>
                                    <a href="../class/hapus.php?type=employee&id=<?= $e['id_employee']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')">Hapus</a>
                                </td>
                            </tr>

                            <!-- MODAL EDIT KARYAWAN -->
                            <div class="modal fade" id="editEmployee<?= $e['id_employee']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content shadow-lg">
                                        <form action="../class/proses_master.php" method="POST">
                                            <div class="modal-header border-0 bg-light">
                                                <h5 class="modal-title fw-bold">Edit Karyawan</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="id_employee" value="<?= $e['id_employee']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label text-uppercase">Nama Karyawan</label>
                                                    <input type="text" name="nama_karyawan" class="form-control" value="<?= $e['nama_karyawan']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label text-uppercase">Spesialisasi</label>
                                                    <input type="text" name="spesialisasi" class="form-control" value="<?= $e['spesialisasi']; ?>">
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" name="edit_employee" class="btn btn-primary px-4">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Layanan -->
<div class="modal fade" id="addService" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="../class/proses_master.php" method="POST">
                <div class="modal-header"><h5>Tambah Layanan</h5></div>
                <div class="modal-body p-4">
                    <input type="text" name="nama_layanan" class="form-control mb-3" placeholder="Nama Layanan" required>
                    <div class="input-group mb-3">
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="harga" class="form-control" placeholder="Harga" required>
                    </div>
                    <div class="input-group mb-3">
                        <input type="number" name="komisi_persen" class="form-control" placeholder="Komisi" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="save_service" class="btn btn-primary w-100">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Karyawan -->
<div class="modal fade" id="addEmployee" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="../class/proses_master.php" method="POST">
                <div class="modal-header"><h5>Tambah Karyawan</h5></div>
                <div class="modal-body p-4">
                    <input type="text" name="nama_karyawan" class="form-control mb-3" placeholder="Nama Karyawan" required>
                    <input type="text" name="spesialisasi" class="form-control mb-3" placeholder="Spesialisasi">
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="save_employee" class="btn btn-primary w-100">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>