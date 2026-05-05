<?php
include 'koneksi.php';

if(isset($_POST['update_admin'])){
    $id_booking = $_POST['id_booking'];
    $status_kerja = $_POST['status_kerja'];
    $status_pembayaran = $_POST['status_pembayaran'];

    // 1. Update status utama booking
    $sql_main = "UPDATE bookings SET 
                 status_kerja = '$status_kerja', 
                 status_pembayaran = '$status_pembayaran' 
                 WHERE id_booking = '$id_booking'";
    mysqli_query($conn, $sql_main);

    // 2. Update karyawan per layanan
    $details = $_POST['id_detail']; // Array ID Detail
    $employees = $_POST['id_employee']; // Array ID Karyawan

    foreach($details as $index => $id_detail){
        $id_emp = $employees[$index];
        // Jika tidak pilih karyawan, set NULL di database
        $val_emp = ($id_emp == "") ? "NULL" : "'$id_emp'";
        
        $sql_det = "UPDATE booking_details SET id_employee = $val_emp WHERE id_detail = '$id_detail'";
        mysqli_query($conn, $sql_det);
    }

    header("Location: admin.php?status=success");
}
?>