<?php
session_start();
include 'koneksi.php';

$id = $_GET['id'];
$data = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id_user='$id'"));

if (isset($_POST['edit_user'])) {
    $nama = $_POST['nama_lengkap'];
    $level = $_POST['level'];
    
    // Update data dasar
    mysqli_query($conn, "UPDATE users SET nama_lengkap='$nama', level='$level' WHERE id_user='$id'");

    // Jika password diisi, maka update password
    if (!empty($_POST['password'])) {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password='$pass' WHERE id_user='$id'");
    }

    header("Location: user_manage.php");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Akun</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="card mx-auto" style="max-width: 500px;">
        <div class="card-body">
            <h5 class="fw-bold mb-4">Edit Akun: <?= $data['username']; ?></h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="small fw-bold">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= $data['nama_lengkap']; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Level Akses</label>
                    <select name="level" class="form-select">
                        <option value="0" <?= $data['level']==0?'selected':''; ?>>0 - Super User</option>
                        <option value="1" <?= $data['level']==1?'selected':''; ?>>1 - Admin</option>
                        <option value="2" <?= $data['level']==2?'selected':''; ?>>2 - Pegawai</option>
                        <option value="3" <?= $data['level']==3?'selected':''; ?>>3 - User</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Password Baru (Kosongkan jika tidak diganti)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="edit_user" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="user_manage.php" class="btn btn-light">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>