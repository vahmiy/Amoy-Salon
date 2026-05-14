<?php
include 'koneksi.php';

if (isset($_GET['id'])) {
    $id_booking = mysqli_real_escape_string($conn, $_GET['id']);

    // 1. Hapus detailnya dulu (Foreign Key)
    mysqli_query($conn, "DELETE FROM booking_details WHERE id_booking = '$id_booking'");

    // 2. Hapus data utamanya
    $delete = mysqli_query($conn, "DELETE FROM bookings WHERE id_booking = '$id_booking'");

    if ($delete) {
        header("Location: ../pages/admin.php?status=deleted");
    } else {
        echo "Gagal menghapus: " . mysqli_error($conn);
    }
} else {
    header("Location: ../pages/admin.php");
}
exit();