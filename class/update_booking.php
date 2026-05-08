<?php
include 'koneksi.php';

if(isset($_POST['update_admin'])){
    $id_booking = $_POST['id_booking'];
    $status_kerja = $_POST['status_kerja'];
    $status_pembayaran = $_POST['status_pembayaran'];

    // Ambil input nominal (Gunakan nilai 0 jika kosong)
    $cash = !empty($_POST['bayar_cash']) ? $_POST['bayar_cash'] : 0;
    $transfer = !empty($_POST['bayar_transfer']) ? $_POST['bayar_transfer'] : 0;
    $total_masuk = $cash + $transfer; 

    // 1. Update status utama dan nominal uang dalam SATU query saja
    // Pastikan nama variabel sesuai: $status_kerja dan $status_pembayaran
    $query = "UPDATE bookings SET 
              status_kerja = '$status_kerja', 
              status_pembayaran = '$status_pembayaran',
              bayar_cash = '$cash',
              bayar_transfer = '$transfer',
              jumlah_terbayar = '$total_masuk'
              WHERE id_booking = '$id_booking'";
    
    mysqli_query($conn, $query);

    // 2. Update karyawan per layanan (Logika ini sudah benar)
    if(isset($_POST['id_detail'])){
        $details = $_POST['id_detail']; 
        $employees = $_POST['id_employee']; 

        foreach($details as $index => $id_detail){
            $id_emp = $employees[$index];
            $val_emp = ($id_emp == "") ? "NULL" : "'$id_emp'";
            
            $sql_det = "UPDATE booking_details SET id_employee = $val_emp WHERE id_detail = '$id_detail'";
            mysqli_query($conn, $sql_det);
        }
    }

    header("Location: ../pages/admin.php?status=success");
}
?>