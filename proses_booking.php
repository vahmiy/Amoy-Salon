<?php
include 'koneksi.php';

if(isset($_POST['submit_booking'])){
    $nama     = $_POST['nama'];
    $whatsapp = $_POST['whatsapp'];
    $tgl      = $_POST['tgl'];
    $jam      = $_POST['jam'];
    $layanan  = $_POST['layanan']; // Ini berupa array

    // 1. Generate ID Booking Unik (Contoh: SLN-20240520-XXXX)
    $tgl_code   = date('Ymd', strtotime($tgl));
    $random_str = strtoupper(substr(md5(time()), 0, 4));
    $id_booking = "SLN-" . $tgl_code . "-" . $random_str;

    $total_biaya = 0;

    // 2. Simpan dulu ke tabel bookings (kosongkan total biaya dulu)
    $insert_booking = mysqli_query($conn, "INSERT INTO bookings (id_booking, nama_customer, whatsapp_customer, tgl_booking, jam_booking) 
                                           VALUES ('$id_booking', '$nama', '$whatsapp', '$tgl', '$jam')");

    if($insert_booking){
        // 3. Loop layanan yang dipilih untuk simpan ke detail dan hitung total
        foreach($layanan as $id_service){
            // Ambil harga dari database
            $get_service = mysqli_query($conn, "SELECT harga FROM services WHERE id_service = '$id_service'");
            $s = mysqli_fetch_array($get_service);
            $harga = $s['harga'];
            $total_biaya += $harga;

            // Simpan ke booking_details
            mysqli_query($conn, "INSERT INTO booking_details (id_booking, id_service, subtotal) 
                                 VALUES ('$id_booking', '$id_service', '$harga')");
        }

        // 4. Update total biaya di tabel bookings
        mysqli_query($conn, "UPDATE bookings SET total_biaya = '$total_biaya' WHERE id_booking = '$id_booking'");

        // 5. Redirect ke halaman Invoice (Halaman Sukses)
        echo "<script>alert('Booking Berhasil! Kode Anda: $id_booking'); window.location='invoice.php?id=$id_booking';</script>";
    }
}
?>