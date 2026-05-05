<?php
session_start();
include 'koneksi.php';

// Proteksi: Hanya Level 0 (Super User) yang boleh masuk
if (!isset($_SESSION['login']) || $_SESSION['level'] != 0) {
    echo "<script>alert('Akses Ditolak! Hanya Super User yang boleh mengelola akun.'); window.location='admin.php';</script>";
    exit;
}

// Proses Hapus User
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    if ($id == $_SESSION['id_user']) {
        echo "<script>alert('Anda tidak bisa menghapus akun sendiri!');</script>";
    } else {
        mysqli_query($conn, "DELETE FROM users WHERE id_user='$id'");
        header("Location: user_manage.php");
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Akun - Amoy Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f1f2f6; }
        .navbar { background-color: #2f3542; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin.php">✨ Amoy Salon Dashboard</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="admin.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active text-warning" href="user_manage.php">👥 Kelola Akun</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Keluar</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row">
        <!-- Form Tambah User -->
        <div class="col-md-4 mb-4">
            <div class="card p-4">
                <h5 class="fw-bold mb-3">Tambah Akun Baru</h5>
                <form action="user_proses.php" method="POST">
                    <div class="mb-3">
                        <label class="small fw-bold">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Level Akses</label>
                        <select name="level" class="form-select">
                            <option value="0">0 - Super User</option>
                            <option value="1">1 - Admin</option>
                            <option value="2">2 - Pegawai</option>
                            <option value="3">3 - User</option>
                        </select>
                    </div>
                    <button type="submit" name="tambah_user" class="btn btn-primary w-100">Simpan Akun</button>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar User -->
        <div class="col-md-8">
            <div class="card p-4">
                <h5 class="fw-bold mb-3">Daftar Pengguna Sistem</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Level</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = mysqli_query($conn, "SELECT * FROM users ORDER BY level ASC");
                            while ($u = mysqli_fetch_array($sql)) {
                                $lbl = ['0'=>'Super', '1'=>'Admin', '2'=>'Pegawai', '3'=>'User'];
                                $color = ['0'=>'danger', '1'=>'warning', '2'=>'info', '3'=>'secondary'];
                            ?>
                            <tr>
                                <td><?= $u['nama_lengkap']; ?></td>
                                <td><code><?= $u['username']; ?></code></td>
                                <td><span class="badge bg-<?= $color[$u['level']]; ?>"><?= $lbl[$u['level']]; ?></span></td>
                                <td>
                                    <a href="user_edit.php?id=<?= $u['id_user']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="user_manage.php?hapus=<?= $u['id_user']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus akun ini?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>