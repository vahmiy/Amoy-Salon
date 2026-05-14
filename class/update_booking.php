<?php
include 'koneksi.php';

// Pastikan ada request POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id_booking = $_POST['id_booking'];
    // Gunakan update_type untuk menentukan tombol mana yang ditekan
    $type = $_POST['update_type'] ?? ''; 

    if ($type == 'operasional') {
        // ==========================================
        // LOGIKA TOMBOL KELOLA
        // ==========================================
        $status_kerja = $_POST['status_kerja'];
        $total_biaya_baru = 0;

        if (isset($_POST['id_detail'])) {
            $details = $_POST['id_detail'];
            $employees = $_POST['id_employee'];
            $hargas = $_POST['harga_layanan'];

            foreach ($details as $index => $id_detail) {
                $id_emp = $employees[$index];
                $harga = $hargas[$index];
                $total_biaya_baru += $harga; // Hitung ulang total biaya

                $val_emp = ($id_emp == "") ? "NULL" : "'$id_emp'";
                
                // Update Karyawan DAN Harga (subtotal) per item layanan
                $sql_det = "UPDATE booking_details SET 
                            id_employee = $val_emp, 
                            subtotal = '$harga' 
                            WHERE id_detail = '$id_detail'";
                mysqli_query($conn, $sql_det);
            }
        }

        // Update status kerja dan total biaya hasil hitung ulang di tabel utama
        $query = "UPDATE bookings SET 
                  status_kerja = '$status_kerja',
                  total_biaya = '$total_biaya_baru' 
                  WHERE id_booking = '$id_booking'";
        mysqli_query($conn, $query);

    } elseif ($type == 'pembayaran') {
        // ==========================================
        // LOGIKA TOMBOL BAYAR
        // ==========================================
        $status_pembayaran = $_POST['status_pembayaran'];
        $cash = !empty($_POST['bayar_cash']) ? $_POST['bayar_cash'] : 0;
        $transfer = !empty($_POST['bayar_transfer']) ? $_POST['bayar_transfer'] : 0;
        $total_masuk = $cash + $transfer;

        $query = "UPDATE bookings SET 
                  status_pembayaran = '$status_pembayaran',
                  bayar_cash = '$cash',
                  bayar_transfer = '$transfer',
                  jumlah_terbayar = '$total_masuk'
                  WHERE id_booking = '$id_booking'";
        mysqli_query($conn, $query);
    }

    // Redirect kembali ke halaman admin
    header("Location: ../pages/admin.php?status=success");
    exit(); // Selalu gunakan exit setelah header agar script berhenti
} else {
    // Jika diakses tanpa POST, kembalikan ke admin
    header("Location: ../pages/admin.php");
    exit();
}
?>