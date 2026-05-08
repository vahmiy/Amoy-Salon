<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$user_level = $_SESSION['level'];
include '../class/koneksi.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Amoy Salon</title>
    <link rel="icon" type="png" href="../asset/logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- DataTables CSS untuk Sorting -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f1f2f6; }
        .navbar { background-color: #2f3542; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table thead { background-color: #57606f; color: white; }
        .list-petugas { padding-left: 0; list-style: none; margin-bottom: 0; }
        .list-petugas li { font-size: 0.85rem; margin-bottom: 5px; border-bottom: 1px dotted #ddd; padding-bottom: 3px; }
        .list-petugas li:last-child { border-bottom: none; }
        .dataTables_filter { margin-bottom: 15px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0; margin-left: 5px; }
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

<div class="container-fluid px-4">
    <!-- SARAN PENGEMBANGAN: Ringkasan Harian -->
    <div class="row mb-4 g-3">
        <?php 
        $today = date('Y-m-d');
        $sum_q = mysqli_query($conn, "SELECT SUM(total_biaya) as tagihan, SUM(jumlah_terbayar) as masuk FROM bookings WHERE tgl_booking = '$today' AND status_pembayaran != 'batal'");
        $sum_d = mysqli_fetch_assoc($sum_q);
        $total_tagihan = $sum_d['tagihan'] ?? 0;
        $total_masuk = $sum_d['masuk'] ?? 0;
        $total_piutang = $total_tagihan - $total_masuk;
        ?>
        <div class="col-md-4">
            <div class="card p-3 border-start border-primary border-4 text-primary">
                <small class="fw-bold text-uppercase">Omzet Hari Ini</small>
                <h4 class="mb-0 fw-bold">Rp <?= number_format($total_tagihan, 0, ',', '.'); ?></h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-success border-4 text-success">
                <small class="fw-bold text-uppercase">Uang Masuk Hari Ini</small>
                <h4 class="mb-0 fw-bold">Rp <?= number_format($total_masuk, 0, ',', '.'); ?></h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-danger border-4 text-danger">
                <small class="fw-bold text-uppercase">Total Sisa Bayar (Piutang)</small>
                <h4 class="mb-0 fw-bold">Rp <?= number_format($total_piutang, 0, ',', '.'); ?></h4>
            </div>
        </div>
    </div>

    <div class="card p-4">
        <h4 class="mb-4">Daftar Booking Customer</h4>
        
        <div class="table-responsive">
            <table id="tabelBooking" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID Booking</th>
                        <th>Customer</th>
                        <th>Jadwal</th>
                        <th>Layanan (Detail Pembayaran)</th>
                        <th>Petugas</th>
                        <th>Status Kerja</th>
                        <th>Status Bayar</th>
                        <th class="no-sort">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM bookings ORDER BY created_at DESC";
                    $query = mysqli_query($conn, $sql);
                    while($row = mysqli_fetch_array($query)){
                        $id_b = $row['id_booking'];
                        $total_biaya = $row['total_biaya'];
                        $terbayar = $row['jumlah_terbayar'] ?? 0;
                        $sisa = $total_biaya - $terbayar;
                    ?>
                    <tr>
                        <td class="fw-bold text-primary">#<?= $row['id_booking']; ?></td>
                        <td>
                            <?= $row['nama_customer']; ?><br>
                            <small class="text-muted"><?= $row['whatsapp_customer']; ?></small>
                        </td>
                        <td data-sort="<?= $row['tgl_booking']; ?>">
                            <?= date('d/m/y', strtotime($row['tgl_booking'])); ?><br>
                            <span class="badge bg-light text-dark border"><?= date('H:i', strtotime($row['jam_booking'])); ?></span>
                        </td>
                        <td>
                            <ul class="list-petugas mb-2">
                                <?php 
                                $res_l = mysqli_query($conn, "SELECT s.nama_layanan FROM booking_details d JOIN services s ON d.id_service = s.id_service WHERE d.id_booking = '$id_b'");
                                while($l = mysqli_fetch_array($res_l)){ echo "<li>" . $l['nama_layanan'] . "</li>"; }
                                ?>
                            </ul>
                            <!-- Fitur Informasi Pembayaran Baru -->
                            <div class="p-2 bg-light rounded border border-light shadow-sm" style="font-size: 0.8rem;">
                                <div class="d-flex justify-content-between">
                                    <span>Total:</span>
                                    <span class="fw-bold">Rp <?= number_format($total_biaya, 0, ',', '.'); ?></span>
                                </div>
                                <div class="d-flex justify-content-between text-success">
                                    <span>Masuk:</span>
                                    <span>Rp <?= number_format($terbayar, 0, ',', '.'); ?></span>
                                </div>
                                <div class="d-flex justify-content-between <?= $sisa > 0 ? 'text-danger fw-bold' : 'text-muted'; ?> border-top mt-1 pt-1">
                                    <span>Sisa:</span>
                                    <span><?= $sisa > 0 ? 'Rp ' . number_format($sisa, 0, ',', '.') : '<i class="bi bi-check-all"></i> LUNAS'; ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <ul class="list-petugas">
                                <?php 
                                $res_petugas = mysqli_query($conn, "SELECT s.nama_layanan, e.nama_karyawan FROM booking_details d JOIN services s ON d.id_service = s.id_service LEFT JOIN employees e ON d.id_employee = e.id_employee WHERE d.id_booking = '$id_b'");
                                while($p = mysqli_fetch_array($res_petugas)){
                                    $nama_p = $p['nama_karyawan'] ?? '<span class="text-danger">Belum Set</span>';
                                    echo "<li><strong>".$p['nama_layanan'].":</strong><br>".$nama_p."</li>";
                                }
                                ?>
                            </ul>
                        </td>
                        <td>
                            <?php $color = ($row['status_kerja'] == 'selesai') ? 'success' : (($row['status_kerja'] == 'diproses') ? 'warning' : 'secondary'); ?>
                            <span class="badge bg-<?= $color; ?>"><?= strtoupper($row['status_kerja']); ?></span>
                        </td>
                        <td>
                            <?php 
                            $status_p = $row['status_pembayaran'];
                            switch($status_p) {
                                case 'lunas': $color_p = 'success'; $icon_p = 'bi-check-circle-fill'; break;
                                case 'dp': $color_p = 'info'; $icon_p = 'bi-cash-stack'; break;
                                case 'batal': $color_p = 'secondary'; $icon_p = 'bi-x-circle'; break;
                                default: $color_p = 'danger'; $icon_p = 'bi-clock-history'; break;
                            }
                            ?>
                            <span class="badge rounded-pill border border-<?= $color_p; ?> text-<?= $color_p; ?> py-2 px-3">
                                <i class="bi <?= $icon_p; ?> me-1"></i> <?= strtoupper($status_p); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#manageModal<?= $row['id_booking']; ?>">Kelola</button>
                        </td>
                    </tr>

                    <!-- Modal Update -->
                    <div class="modal fade" id="manageModal<?= $row['id_booking']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="../class/update_booking.php" method="POST">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Booking #<?= $row['id_booking']; ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id_booking" value="<?= $row['id_booking']; ?>">
                                        <h6 class="fw-bold mb-3">Petugas per Layanan:</h6>
                                        <?php 
                                        $det_query = mysqli_query($conn, "SELECT d.id_detail, s.nama_layanan, d.id_employee FROM booking_details d JOIN services s ON d.id_service = s.id_service WHERE d.id_booking = '$id_b'");
                                        while($ld = mysqli_fetch_array($det_query)){
                                        ?>
                                            <div class="mb-3 p-2 border rounded bg-light">
                                                <label class="form-label mb-1 small fw-bold"><?= $ld['nama_layanan']; ?></label>
                                                <input type="hidden" name="id_detail[]" value="<?= $ld['id_detail']; ?>">
                                                <select name="id_employee[]" class="form-select form-select-sm">
                                                    <option value="">-- Pilih Petugas --</option>
                                                    <?php 
                                                    $emp_query = mysqli_query($conn, "SELECT * FROM employees");
                                                    while($e = mysqli_fetch_array($emp_query)){
                                                        $sel = ($e['id_employee'] == $ld['id_employee']) ? 'selected' : '';
                                                        echo "<option value='".$e['id_employee']."' $sel>".$e['nama_karyawan']."</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        <?php } ?>
                                        <hr>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-uppercase">Status Kerja:</label>
                                            <select name="status_kerja" class="form-select">
                                                <option value="menunggu" <?= $row['status_kerja'] == 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                                <option value="diproses" <?= $row['status_kerja'] == 'diproses' ? 'selected' : ''; ?>>Sedang Diproses</option>
                                                <option value="selesai" <?= $row['status_kerja'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                            </select>
                                        </div>
                                        <div class="mb-3 text-center p-2 bg-dark text-white rounded small">
                                            Total Tagihan: Rp <?= number_format($total_biaya, 0, ',', '.'); ?>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-uppercase">Status Pembayaran:</label>
                                            <select name="status_pembayaran" class="form-select">
                                                    <option value="pending" <?= $row['status_pembayaran'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="dp" <?= $row['status_pembayaran'] == 'dp' ? 'selected' : ''; ?>>DP</option>
                                                    <option value="lunas" <?= $row['status_pembayaran'] == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                                                    <option value="batal" <?= $row['status_pembayaran'] == 'batal' ? 'selected' : ''; ?>>Batal</option>
                                            </select>
                                        </div>
                                        <div class="row g-2 p-2 border rounded bg-light shadow-sm">
                                            <label class="form-label fw-bold small text-uppercase mb-1 text-primary"><i class="bi bi-wallet2"></i> Catatan Uang Masuk:</label>
                                            <div class="col-md-6">
                                                <label class="form-label small mb-0">Cash (Rp)</label>
                                                <input type="number" name="bayar_cash" class="form-control form-control-sm" value="<?= $row['bayar_cash'] ?? 0; ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small mb-0">Transfer (Rp)</label>
                                                <input type="number" name="bayar_transfer" class="form-control form-control-sm" value="<?= $row['bayar_transfer'] ?? 0; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="update_admin" class="btn btn-primary w-100 fw-bold">Simpan Perubahan</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelBooking').DataTable({
        "order": [[ 0, "desc" ]],
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json" },
        "columnDefs": [ { "orderable": false, "targets": "no-sort" } ]
    });
});
</script>
</body>
</html>