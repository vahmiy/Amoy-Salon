<?php
session_start();
include 'koneksi.php';

if (isset($_POST['tambah_user'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $level = $_POST['level'];

    $query = mysqli_query($conn, "INSERT INTO users (username, password, nama_lengkap, level) VALUES ('$user', '$pass', '$nama', '$level')");

    if ($query) {
        header("Location: user_manage.php");
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>