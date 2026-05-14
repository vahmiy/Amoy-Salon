<?php
include '../class/koneksi.php';

$id_b = $_GET['id'];

// Ambil data utama booking
$query = mysqli_query($conn, "SELECT * FROM bookings WHERE id_booking = '$id_b'");
$data = mysqli_fetch_array($query);

// Hitung keuangan
$total = $data['total_biaya'];
$bayar = $data['bayar_cash'] + $data['bayar_transfer'];
$selisih = $bayar - $total;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota #<?= $id_b; ?></title>
    <style>
        /* Pengaturan Kertas Thermal 80mm */
        @page { size: 80mm auto; margin: 0; }
        body { 
            font-family: 'Courier New', Courier, monospace; 
            width: 72mm; /* Area cetak bersih */
            margin: 0 auto; 
            padding: 10px;
            font-size: 12px;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .line { border-bottom: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        .header h2 { margin: 0; font-size: 18px; }
        .header p { margin: 2px 0; font-size: 10px; }
    </style>
</head>
<body onload="window.print();">

    <div class="header text-center">
        <h2>AMOY SALON</h2>
        <p>Kp Dangdeur No.001/008, Kiangroke, Kec. Banjaran Kabupaten Bandung</p>
        <p>WA: 0812-3456-7890</p>
    </div>

    <div class="line"></div>

   <table>
    <tr>
        <td>Nota: #<?= $id_b; ?></td>
        <!-- Bagian ini diubah untuk menampilkan waktu cetak saat ini -->
        <td class="text-right">
            Dicetak: <?= date('d/m/Y H:i:s'); ?>
        </td>
    </tr>
    <tr>
        <td colspan="2">Cust: <?= $data['nama_customer']; ?></td>
    </tr>
    <tr>
        <td colspan="2">
            <medium> Reservasi: <?= date('d/m/Y', strtotime($data['tgl_booking'])); ?></small>
        </td>
    </tr>
</table>

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th align="left">Layanan</th>
                <th align="right">Harga</th>
            </tr>
        </thead>
<tbody>
    <?php 
    $details = mysqli_query($conn, "SELECT d.*, s.nama_layanan FROM booking_details d 
                                    JOIN services s ON d.id_service = s.id_service 
                                    WHERE d.id_booking = '$id_b'");
    while($ld = mysqli_fetch_array($details)){
        // Perbaikan: Menggunakan tanda kutip tunggal untuk class HTML agar tidak bentrok
        echo "<tr>
                <td>".$ld['nama_layanan']."</td>
                <td class='text-right'>".number_format($ld['subtotal'], 0, ',', '.')."</td>
              </tr>";
    }
    ?>
</tbody>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td>Total Tagihan</td>
            <td class="text-right">Rp <?= number_format($total, 0, ',', '.'); ?></td>
        </tr>
        <tr>
            <td>Total Bayar</td>
            <td class="text-right">Rp <?= number_format($bayar, 0, ',', '.'); ?></td>
        </tr>
        <tr style="font-weight: bold;">
            <td><?= $selisih >= 0 ? 'Kembali' : 'Sisa Kurang'; ?></td>
            <td class="text-right">Rp <?= number_format(abs($selisih), 0, ',', '.'); ?></td>
        </tr>
    </table>

    <div class="line"></div>
    
    <div class="text-center" style="margin-top: 10px;">
        <p>Terima Kasih Atas Kunjungan Anda</p>
        <p>Ini adalah bukti pembarayan sah, Barang/Jasa yang sudah dibeli tidak dapat ditukar</p>
    </div>

</body>
</html>