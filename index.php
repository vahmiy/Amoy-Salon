<?php include 'class/koneksi.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Amoy Salon - Customer</title>
    <link rel="icon" type="png" href="asset/logo.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- TAMBAHAN: Bootstrap Icons (Wajib agar icon centang dan search muncul) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #f8f9fa; }
        .card-booking { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #6c5ce7; border: none; }
        .btn-primary:hover { background-color: #a29bfe; }

        /* Sembunyikan checkbox asli */
        .service-check-input {
            display: none;
        }

        /* Gaya dasar kartu */
        .service-card {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            border: 2px solid #e9ecef !important;
            border-radius: 12px !important;
        }

        /* Efek saat kartu di-hover */
        .service-card:hover {
            border-color: #0d6efd !important;
            background-color: #f8f9ff;
        }

        /* Efek saat checkbox terpilih (checked) */
        .service-check-input:checked + .service-card {
            border-color: #0d6efd !important;
            background-color: #e7f1ff;
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.15) !important;
        }

        /* Icon centang yang muncul saat terpilih */
        .check-icon {
            color: #0d6efd;
            display: none;
        }
        .service-check-input:checked + .service-card .check-icon {
            display: inline-block;
        }
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

                <form action="class/proses_booking.php" method="POST">
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
                        <label class="form-label fw-bold">Pilih Layanan / Paket:</label>
                        
                        <!-- Input Pencarian -->
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchService" class="form-control" placeholder="Cari layanan (misal: Haircut, Creambath...)" onkeyup="filterServices()">
                        </div>

                        <!-- Container Layanan dengan Scroll Area (Mobile Friendly) -->
                        <div id="serviceContainer" class="row g-2" style="max-height: 400px; overflow-y: auto; overflow-x: hidden; padding: 5px;">
                            <?php
                            // Ambil data layanan
                            $query = mysqli_query($conn, "SELECT * FROM services ORDER BY nama_layanan ASC");
                            while($data = mysqli_fetch_array($query)){
                            ?>
                            <div class="col-12 col-md-6 service-item">
                                <!-- Input ditaruh di atas label -->
                                <input class="service-check-input" type="checkbox" 
                                       name="layanan[]" 
                                       value="<?= $data['id_service']; ?>" 
                                       id="svc<?= $data['id_service']; ?>"
                                       data-name="<?= strtolower($data['nama_layanan']); ?>">
                                
                                <!-- Label membungkus seluruh desain kartu -->
                                <label class="card h-100 shadow-sm service-card" for="svc<?= $data['id_service']; ?>">
                                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <!-- Icon kecil atau Bulatan sebagai pengganti checkbox visual -->
                                            <div class="me-3 d-flex align-items-center justify-content-center border rounded-circle" style="width: 24px; height: 24px; background: white;">
                                                <i class="bi bi-check-lg check-icon"></i>
                                            </div>
                                            <span class="fw-bold text-dark" style="font-size: 0.95rem;">
                                                <?= $data['nama_layanan']; ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Harga -->
                                        <!-- <div class="text-end">
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">Mulai dari</small>
                                            <span class="text-primary fw-bold" style="font-size: 0.9rem;">
                                                Rp <?= number_format($data['harga'], 0, ',', '.'); ?>
                                            </span>
                                        </div> -->
                                    </div>
                                </label>
                            </div>
                            <?php } ?>
                        </div>
                        
                        <!-- Notifikasi jika tidak ditemukan -->
                        <div id="noService" class="text-center py-3 d-none">
                            <small class="text-muted fst-italic">Layanan tidak ditemukan...</small>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="submit_booking" class="btn btn-primary btn-lg">Booking</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
function filterServices() {
    // Ambil input pencarian
    let input = document.getElementById('searchService').value.toLowerCase();
    let items = document.getElementsByClassName('service-item');
    let noService = document.getElementById('noService');
    let visibleCount = 0;

    // Loop semua item layanan
    for (let i = 0; i < items.length; i++) {
        // PERBAIKAN: Target pencarian diubah ke .service-check-input
        let checkbox = items[i].querySelector('.service-check-input'); 
        
        // Pastikan elemen ditemukan agar tidak error
        if(checkbox) {
            let serviceName = checkbox.getAttribute('data-name');

            if (serviceName.includes(input)) {
                items[i].classList.remove('d-none');
                visibleCount++;
            } else {
                items[i].classList.add('d-none');
            }
        }
    }

    // Tampilkan pesan jika tidak ada yang cocok
    if (visibleCount === 0) {
        noService.classList.remove('d-none');
    } else {
        noService.classList.add('d-none');
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>