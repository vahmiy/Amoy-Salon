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
        /* Pengaturan Kertas Thermal 58mm */
        @page { 
            margin: 0; 
            size: 58mm auto; 
        }
        
        body { 
            /* Menggunakan Arial/Helvetica karena dirender lebih tebal & jelas di printer thermal */
            font-family: Arial, Helvetica, sans-serif; 
            /* Area cetak bersih untuk kertas 58mm biasanya sekitar 48mm */
            width: 44mm; 
            margin-left: 2mm; /* Geser sedikit dari batas fisik kertas kiri */
            margin-right: 0;
            text-rendering: optimizeLegibility;
            /*--------------------------*/
            padding: 5px 0;
            font-size: 11px;
            color: #000;
            line-height: 1.2;
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: bold; }
        
        /* Garis putus-putus tebal */
        .line { 
            border-bottom: 1px dashed #000; 
            margin: 5px 0; 
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse;
            /* Memaksa tabel tidak melebar keluar dari 44mm */
            table-layout: fixed;
        }
        
        td, th {
            vertical-align: top;
            padding: 2px 0;
            word-wrap: break-word; /* Nama layanan panjang akan turun ke bawah, bukan dorong harga */
        }

        /* Header Nota */
        .header h2 { 
            margin: 0 0 3px 0; 
            font-size: 16px; /* Tidak terlalu besar agar tidak terpotong */
            font-weight: bold;
        }
        .header p { 
            margin: 0; 
            font-size: 10px; 
        }

        /* Footer Nota */
        .footer p {
            margin: 2px 0;
            font-size: 10px;
        }
    </style>
</head>
<body onload="window.print();">

    <div class="header text-center">
        <h2>AMOY SALON</h2>
        <p>Kp Dangdeur No.001/008, Kiangroke</p>
        <p>Kec. Banjaran, Kab. Bandung</p>
        <p>WA: 0812-3456-7890</p>
    </div>

    <div class="line"></div>

    <table>
        <tr>
            <td class="text-left fw-bold">#<?= $id_b; ?></td>
            <td class="text-right" style="font-size: 10px;">
                <?= date('d/m/y H:i'); ?>
            </td>
        </tr>
        <tr>
            <td colspan="2">Cust: <strong><?= $data['nama_customer']; ?></strong></td>
        </tr>
        <tr>
            <td colspan="2" style="font-size: 10px;">
                Reservasi: <?= date('d/m/Y', strtotime($data['tgl_booking'])); ?>
            </td>
        </tr>
    </table>

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th class="text-left">Layanan</th>
                <th class="text-right">Harga</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $details = mysqli_query($conn, "SELECT d.*, s.nama_layanan FROM booking_details d 
                                            JOIN services s ON d.id_service = s.id_service 
                                            WHERE d.id_booking = '$id_b'");
            while($ld = mysqli_fetch_array($details)){
                echo "<tr>
                        <td class='text-left'>".$ld['nama_layanan']."</td>
                        <td class='text-right'>".number_format($ld['subtotal'], 0, ',', '.')."</td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td>Total</td>
            <td class="text-right fw-bold">Rp <?= number_format($total, 0, ',', '.'); ?></td>
        </tr>
        <tr>
            <td>Bayar</td>
            <td class="text-right">Rp <?= number_format($bayar, 0, ',', '.'); ?></td>
        </tr>
        <tr>
            <td><?= $selisih >= 0 ? 'Kembali' : 'Kurang'; ?></td>
            <td class="text-right fw-bold">Rp <?= number_format(abs($selisih), 0, ',', '.'); ?></td>
        </tr>
    </table>

    <div class="line"></div>
    
    <div class="text-center footer" style="margin-top: 10px;">
        <p class="fw-bold">Terima Kasih Atas Kunjungan Anda</p>
        <p>Barang/Jasa yang sudah dibeli<br>tidak dapat ditukar</p>
    </div>

</body>
</html>