<?php include 'koneksi.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Amoy Salon - Customer</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card-booking { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #6c5ce7; border: none; }
        .btn-primary:hover { background-color: #a29bfe; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card card-booking p-4">
                <h3 class="text-center mb-4">✨ Booking Amoy Salon ✨</h3>
                <p class="text-muted text-center">Silakan isi form di bawah untuk reservasi</p>
                <hr>

                <form action="proses_booking.php" method="POST">
                    <!-- Data Diri -->
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-control" placeholder="Contoh: Budi Santoso" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor WhatsApp</label>
                        <input type="number" name="whatsapp" class="form-control" placeholder="0812xxxx" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Kedatangan</label>
                            <input type="date" name="tgl" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam</label>
                            <input type="time" name="jam" class="form-control" required>
                        </div>
                    </div>

                    <!-- Pilih Layanan -->
                    <div class="mb-4">
                        <label class="form-label d-block">Pilih Layanan / Paket:</label>
                        <div class="row">
                            <?php
                            $query = mysqli_query($conn, "SELECT * FROM services");
                            while($data = mysqli_fetch_array($query)){
                            ?>
                            <div class="col-md-6 mb-2">
                                <div class="form-check p-2 border rounded">
                                    <input class="form-check-input ms-1" type="checkbox" name="layanan[]" value="<?= $data['id_service']; ?>" id="svc<?= $data['id_service']; ?>">
                                    <label class="form-check-label ms-2" for="svc<?= $data['id_service']; ?>">
                                        <?= $data['nama_layanan']; ?> <br>
                                        <small class="text-primary fw-bold">Rp <?= number_format($data['harga'], 0, ',', '.'); ?></small>
                                    </label>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="submit_booking" class="btn btn-primary btn-lg">Buat Booking Sekarang</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>