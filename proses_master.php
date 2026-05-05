<?php
include 'koneksi.php';

if (isset($_POST['save_service'])) {
    $nama   = mysqli_real_escape_string($conn, $_POST['nama_layanan']);
    $harga  = $_POST['harga'];
    $komisi = $_POST['komisi_persen'];

    $query = "INSERT INTO services (nama_layanan, harga, komisi_persen) VALUES ('$nama', '$harga', '$komisi')";
    
    if (mysqli_query($conn, $query)) {
        header("Location: master_data.php");
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

if(isset($_POST['save_employee'])){
    $nama = $_POST['nama_karyawan'];
    $spesialisasi = $_POST['spesialisasi'];
    mysqli_query($conn, "INSERT INTO employees (nama_karyawan, spesialisasi) VALUES ('$nama', '$spesialisasi')");
}

header("Location: master_data.php");
?>