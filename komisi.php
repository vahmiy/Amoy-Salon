<?php 
include 'koneksi.php'; 

// Filter berdasarkan periode bulan (Default: Bulan & Tahun saat ini)
$filter_periode = isset($_GET['periode']) ? $_GET['periode'] : date('Y-m');
$filter_karyawan = isset($_GET['id_employee']) ? $_GET['id_employee'] : '';

// Memecah tahun dan bulan untuk query
$tahun = date('Y', strtotime($filter_periode));
$bulan = date('m', strtotime($filter_periode));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Komisi Karyawan - Amoy Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f1f2f6; }
        .navbar { background-color: #2f3542; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table thead { background-color: #57606f; color: white; }
        .text-nominal { font-family: 'Courier New', Courier, monospace; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin.php">✨ Amoy Salon Dashboard</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="admin.php">Daftar Booking</a></li>
                <li class="nav-item"><a class="nav-link text-warning" href="master_data.php">⚙️ Master Data</a></li>
                <li class="nav-item"><a class="nav-link text-info" href="laporan.php">📊 Pendapatan</a></li>
                <li class="nav-item"><a class="nav-link active text-warning" href="komisi.php">💰 Komisi</a></li>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-sm btn-outline-light mt-1" href="index.php" target="_blank">Lihat Web Customer</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row">
        <!-- FILTER BOX -->
        <div class="col-md-12 mb-4">
            <div class="card p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-filter-circle me-2"></i>Filter Laporan</h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Pilih Karyawan</label>
                        <select name="id_employee" class="form-select">
                            <option value="">-- Semua Karyawan --</option>
                            <?php 
                            $emp_q = mysqli_query($conn, "SELECT * FROM employees");
                            while($e = mysqli_fetch_array($emp_q)){
                                $sel = ($filter_karyawan == $e['id_employee']) ? 'selected' : '';
                                echo "<option value='".$e['id_employee']."' $sel>".$e['nama_karyawan']."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Periode Bulan</label>
                        <input type="month" name="periode" class="form-control" value="<?= $filter_periode; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Cari Data</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABEL LAPORAN -->
        <div class="col-md-12">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold m-0">History Komisi Pekerjaan</h4>
                    <button class="btn btn-outline-success btn-sm" onclick="window.print()"><i class="bi bi-printer me-2"></i>Cetak Laporan</button>
                </div>

                <div class="table-responsive">
                    <table id="tabelKomisi" class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Karyawan</th>
                                <th>Layanan Dikerjakan</th>
                                <th>Harga Paket</th>
                                <th>Komisi (%)</th>
                                <th>Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Syarat: Status kerja SELESAI dan Status Pembayaran LUNAS
                            $where = "WHERE b.status_kerja = 'selesai' AND b.status_pembayaran = 'lunas' AND MONTH(b.tgl_booking) = '$bulan' AND YEAR(b.tgl_booking) = '$tahun'";
                            
                            if($filter_karyawan != '') {
                                $where .= " AND d.id_employee = '$filter_karyawan'";
                            }

                            // Query menggabungkan booking_details dengan services (untuk komisi_persen)
                            $sql = "SELECT b.tgl_booking, e.nama_karyawan, s.nama_layanan, s.harga, s.komisi_persen,
                                    (s.harga * s.komisi_persen / 100) as rupiah_komisi
                                    FROM booking_details d
                                    JOIN bookings b ON d.id_booking = b.id_booking
                                    JOIN services s ON d.id_service = s.id_service
                                    JOIN employees e ON d.id_employee = e.id_employee
                                    $where
                                    ORDER BY b.tgl_booking DESC";
                            
                            $query = mysqli_query($conn, $sql);
                            $grand_total_komisi = 0;

                            while($row = mysqli_fetch_array($query)){
                                $grand_total_komisi += $row['rupiah_komisi'];
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['tgl_booking'])); ?></td>
                                <td class="fw-bold"><?= $row['nama_karyawan']; ?></td>
                                <td><?= $row['nama_layanan']; ?></td>
                                <td>Rp <?= number_format($row['harga'], 0, ',', '.'); ?></td>
                                <td><span class="badge bg-secondary"><?= $row['komisi_persen']; ?>%</span></td>
                                <td class="text-nominal text-success">
                                    Rp <?= number_format($row['rupiah_komisi'], 0, ',', '.'); ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="5" class="text-end fw-bold">TOTAL KOMISI TERKUMPUL:</th>
                                <th class="text-nominal text-primary h5">
                                    Rp <?= number_format($grand_total_komisi, 0, ',', '.'); ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelKomisi').DataTable({
        "order": [[ 0, "desc" ]],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
        }
    });
});
</script>
</body>
</html>