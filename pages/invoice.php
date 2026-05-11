<?php 
include '../class/koneksi.php'; 

// Ambil ID dari URL
$id_booking = $_GET['id'];

// Query data booking
$query = mysqli_query($conn, "SELECT * FROM bookings WHERE id_booking = '$id_booking'");
$data  = mysqli_fetch_array($query);

if (!$data) {
    echo "Data booking tidak ditemukan.";
    exit;
}

// FORMAT KODE UNIK UNTUK QR CODE
// Contoh: #SLN-2023001
$kode_unik = $data['id_booking'];

// Link QR Code (Menggunakan QRServer API)
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($kode_unik);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo $id_booking; ?></title>
    <link rel="icon" type="png" href="../asset/logo.png">
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
            position: relative; /* Penting untuk posisi QR */
        }
        .qr-code {
            width: 100px;
            height: 100px;
            border: 1px solid #eee;
            padding: 5px;
            border-radius: 10px;
        }
        @media print {
            .no-print { display: none; }
            .invoice-box { box-shadow: none; margin-top: 0; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="invoice-box">
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <h4 class="text-uppercase fw-bold text-primary">Invoice</h4>
                        <span class="text-muted">Kode Booking:</span>
                        <div class="fw-bold fs-5"><?php echo $kode_unik; ?></div>
                    </div>
                    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                        <img src="<?php echo $qr_api; ?>" alt="QR Code Booking" class="qr-code">
                        <div class="small text-muted mt-1" style="font-size: 0.7rem;">Scan untuk verifikasi</div>
                    </div>
                </div>

                <div class="row mb-4 border-top pt-4">
                    <div class="col-sm-6">
                        <h5 class="fw-bold text-dark">Amoy Salon</h5>
                        <p class="text-muted small">Kp Dangdeur No.001/008, Kiangroke, Kec. Banjaran Kabupaten Bandung<br>WhatsApp: 0812-3456-7890</p>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <h6 class="text-muted">Customer:</h6>
                        <p class="fw-bold mb-0"><?php echo $data['nama_customer']; ?></p>
                        <p class="text-muted mb-0"><?php echo $data['whatsapp_customer']; ?></p>
                    </div>
                </div>

                <div class="row mb-4 bg-light p-3 rounded mx-0">
                    <div class="col-6">
                        <small class="text-muted d-block text-uppercase">Tanggal</small>
                        <span class="fw-bold"><?php echo date('d M Y', strtotime($data['tgl_booking'])); ?></span>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted d-block text-uppercase">Jam Kedatangan</small>
                        <span class="fw-bold text-primary"><?php echo date('H:i', strtotime($data['jam_booking'])); ?> WIB</span>
                    </div>
                </div>

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
                                <td class="border-bottom-dotted"><?php echo $det['nama_layanan']; ?></td>
                                <td class="text-end text-muted border-bottom-dotted">Rp <?php echo number_format($det['subtotal'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="fw-bold fs-5 pt-3">Total Tagihan</td>
                                <td class="text-end fw-bold fs-5 text-primary pt-3">Rp <?php echo number_format($data['total_biaya'], 0, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="border-start border-primary border-4 p-3 bg-light rounded mb-4">
                    <h6 class="fw-bold">💡 Note</h6>
                    <ul class="small text-muted mb-0">
                        <li>Harap datang 10 menit sebelum jadwal.</li>
                        <li>Tunjukkan bukti booking saat tiba di tempat</li>
                        <li>Pembayaran dilakukan di tempat (Kasir).</li>
                    </ul>
                </div>

                <div class="text-center no-print">
                    <button onclick="window.print()" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-printer"></i> Cetak / Simpan PDF
                    </button>
                    <a href="../index.php" class="btn btn-primary">Kembali ke Beranda</a>
                </div>
            </div>
            
            <p class="text-center text-muted small mt-2">Terima kasih telah mempercayakan kecantikan Anda pada kami!</p>
        </div>
    </div>
</div>

</body>
</html>