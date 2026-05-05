<?php 
include 'koneksi.php'; 

// Ambil ID dari URL
$id_booking = $_GET['id'];

// Query data booking dan gabungkan dengan tabel karyawan (jika sudah di-assign)
$query = mysqli_query($conn, "SELECT * FROM bookings WHERE id_booking = '$id_booking'");
$data  = mysqli_fetch_array($query);

// Jika ID tidak ditemukan
if (!$data) {
    echo "Data booking tidak ditemukan.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo $id_booking; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .invoice-box {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 15px;
            border-radius: 20px;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="invoice-box">
                <!-- Header Invoice -->
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <h4 class="text-uppercase fw-bold text-primary">Invoice</h4>
                        <span class="text-muted">Kode Booking:</span>
                        <div class="fw-bold fs-5">#<?php echo $data['id_booking']; ?></div>
                    </div>
                    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                        <h5 class="fw-bold">Salon Kecantikan</h5>
                        <p class="text-muted small">Jl. Raya Cantik No. 123<br>WhatsApp: 0812-3456-7890</p>
                    </div>
                </div>

                <hr>

                <!-- Info Customer -->
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <h6 class="text-muted">Customer:</h6>
                        <p class="fw-bold mb-0"><?php echo $data['nama_customer']; ?></p>
                        <p class="text-muted mb-0"><?php echo $data['whatsapp_customer']; ?></p>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <h6 class="text-muted">Waktu Kedatangan:</h6>
                        <p class="mb-0"><?php echo date('d M Y', strtotime($data['tgl_booking'])); ?></p>
                        <p class="fw-bold text-primary"><?php echo date('H:i', strtotime($data['jam_booking'])); ?> WIB</p>
                    </div>
                </div>

                <!-- Tabel Layanan -->
                <div class="table-responsive mb-4">
                    <table class="table table-borderless">
                        <thead class="table-light">
                            <tr>
                                <th>Layanan</th>
                                <th class="text-end">Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $detail_query = mysqli_query($conn, "SELECT s.nama_layanan, d.subtotal 
                                                                FROM booking_details d 
                                                                JOIN services s ON d.id_service = s.id_service 
                                                                WHERE d.id_booking = '$id_booking'");
                            while($det = mysqli_fetch_array($detail_query)){
                            ?>
                            <tr>
                                <td><?php echo $det['nama_layanan']; ?></td>
                                <td class="text-end text-muted">Rp <?php echo number_format($det['subtotal'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr class="border-top">
                                <td class="fw-bold fs-5">Total Tagihan</td>
                                <td class="text-end fw-bold fs-5 text-primary">Rp <?php echo number_format($data['total_biaya'], 0, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Footer/Catatan -->
                <div class="bg-light p-3 rounded mb-4">
                    <h6 class="fw-bold">💡 Instruksi:</h6>
                    <ul class="small text-muted mb-0">
                        <li>Harap datang 10 menit sebelum jadwal.</li>
                        <li>Tunjukkan Invoice/Kode Booking ini ke kasir saat datang.</li>
                        <li>Pembayaran dilakukan di tempat (Kasir).</li>
                    </ul>
                </div>

                <!-- Tombol Action -->
                <div class="text-center no-print">
                    <button onclick="window.print()" class="btn btn-outline-secondary me-2">Cetak / Simpan PDF</button>
                    <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
                </div>
            </div>
            
            <p class="text-center text-muted small mt-2">Terima kasih telah mempercayakan kecantikan Anda pada kami!</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>