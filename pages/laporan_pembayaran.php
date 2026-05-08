<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

include '../class/koneksi.php';

// Fitur Filter Tanggal (Default: Bulan Ini)
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-t');

// Query untuk mengambil data pembayaran
$sql = "SELECT * FROM bookings 
        WHERE (tgl_booking BETWEEN '$tgl_awal' AND '$tgl_akhir') 
        AND status_pembayaran != 'batal' 
        ORDER BY tgl_booking DESC";
$query = mysqli_query($conn, $sql);

// Hitung Total Akumulasi untuk Ringkasan
$total_cash = 0;
$total_transfer = 0;
$total_omzet = 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembukuan - Amoy Salon</title>
        <link rel="icon" type="png" href="../asset/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .bg-cash { background-color: #d1edda; color: #155724; }
        .bg-transfer { background-color: #cce5ff; color: #004085; }
        .navbar { background-color: #2f3542; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin.php">✨ Amoy Salon Dashboard</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if ($_SESSION['level'] <= 2): ?>
                    <li class="nav-item"><a class="nav-link active" href="admin.php">Daftar Booking</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['level'] <= 1): ?>
                    <li class="nav-item"><a class="nav-link text-warning" href="master_data.php">⚙️ Master Data</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['level'] <= 2): ?>
                    <li class="nav-item"><a class="nav-link text-info" href="laporan.php">📊 Pendapatan</a></li>
                    <li class="nav-item"><a class="nav-link text-warning" href="komisi.php">💰 Komisi</a></li>
                    <li class="nav-item"><a class="nav-link text-info" href="laporan_pembayaran.php">📖 Pembukuan</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['level'] <= 1): ?>
                    <li class="nav-item"><a class="nav-link active text-warning" href="../class/user_manage.php">👥 Kelola Akun</a></li>
                <?php endif; ?>
                
                <li class="nav-item ms-lg-3"><a class="btn btn-sm btn-outline-light mt-1" href="../index.php" target="_blank">Booking Online</a></li>
                <li class="nav-item ms-lg-3">
                    <a class="nav-link text-danger fw-bold" href="../class/logout.php" onclick="return confirm('Yakin ingin keluar?')">
                        <i class="bi bi-box-arrow-right"></i> Keluar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="bi bi-book-half"></i> Pembukuan Salon</h3>
            <p class="text-muted small mb-0">Rekapitulasi pembayaran Cash & Transfer</p>
        </div>
    </div>

    <div class="card p-3 mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Dari Tanggal</label>
                <input type="date" name="tgl_awal" class="form-control" value="<?= $tgl_awal; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Sampai Tanggal</label>
                <input type="date" name="tgl_akhir" class="form-control" value="<?= $tgl_akhir; ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Tampilkan Laporan</button>
            </div>
        </form>
    </div>

    <div class="card p-4 mb-4">
        <div class="table-responsive">
            <table id="tabelLaporan" class="table table-hover align-middle">
                <thead class="table-light text-uppercase small">
                    <tr>
                        <th>Tgl / ID</th>
                        <th>Customer</th>
                        <th class="text-end">Tunai (Cash)</th>
                        <th class="text-end">Transfer Bank</th>
                        <th class="text-end">Total Bayar</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_array($query)) : 
                        $total_cash += $row['bayar_cash'];
                        $total_transfer += $row['bayar_transfer'];
                        $total_row = $row['bayar_cash'] + $row['bayar_transfer'];
                        $total_omzet += $total_row;
                    ?>
                    <tr>
                        <td>
                            <span class="fw-bold"><?= date('d/m/y', strtotime($row['tgl_booking'])); ?></span><br>
                            <small class="text-primary">#<?= $row['id_booking']; ?></small>
                        </td>
                        <td><?= $row['nama_customer']; ?></td>
                        <td class="text-end bg-cash">Rp <?= number_format($row['bayar_cash'], 0, ',', '.'); ?></td>
                        <td class="text-end bg-transfer">Rp <?= number_format($row['bayar_transfer'], 0, ',', '.'); ?></td>
                        <td class="text-end fw-bold">Rp <?= number_format($total_row, 0, ',', '.'); ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status_pembayaran'] == 'lunas' ? 'success' : 'warning'; ?>">
                                <?= strtoupper($row['status_pembayaran']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <th colspan="2" class="text-center">TOTAL REKAPITULASI</th>
                        <th class="text-end">Rp <?= number_format($total_cash, 0, ',', '.'); ?></th>
                        <th class="text-end">Rp <?= number_format($total_transfer, 0, ',', '.'); ?></th>
                        <th class="text-end">Rp <?= number_format($total_omzet, 0, ',', '.'); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card p-4 text-center border-bottom border-success border-5">
                <h6 class="text-muted small text-uppercase">Total Pendapatan Tunai</h6>
                <h2 class="fw-bold text-success">Rp <?= number_format($total_cash, 0, ',', '.'); ?></h2>
                <i class="bi bi-cash-stack display-6 opacity-25 mt-2"></i>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4 text-center border-bottom border-primary border-5">
                <h6 class="text-muted small text-uppercase">Total Masuk Rekening</h6>
                <h2 class="fw-bold text-primary">Rp <?= number_format($total_transfer, 0, ',', '.'); ?></h2>
                <i class="bi bi-credit-card display-6 opacity-25 mt-2"></i>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#tabelLaporan').DataTable({
            "order": [[ 0, "desc" ]],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json" }
        });
    });
</script>
</body>
</html>