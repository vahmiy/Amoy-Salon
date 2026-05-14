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

        /* Styling untuk tombol scan di dalam search bar */
    .search-container { position: relative; }
    #btn-scan-qr {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        z-index: 10;
        background: #fff;
        border: none;
        color: #0d6efd;
        font-size: 1.2rem;
    }
    /* Kotak Kamera */
    #reader {
        width: 100%;
        border-radius: 10px;
        overflow: hidden;
        border: none !important;
    }
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
    
    <div class="row mb-4 g-3">
    <?php 
    $today = date('Y-m-d');
    // Query diperbarui untuk menghitung jumlah customer (booking)
    $sum_q = mysqli_query($conn, "SELECT 
        COUNT(id_booking) as total_booking,
        SUM(total_biaya) as tagihan, 
        SUM(jumlah_terbayar) as masuk 
        FROM bookings 
        WHERE tgl_booking = '$today' AND status_pembayaran != 'batal'");
    $sum_d = mysqli_fetch_assoc($sum_q);
    
    $total_booking = $sum_d['total_booking'] ?? 0;
    $total_tagihan = $sum_d['tagihan'] ?? 0;
    $total_masuk = $sum_d['masuk'] ?? 0;
    $total_piutang = $total_tagihan - $total_masuk;
    ?>
    <!-- Fitur Baru: Jumlah Booking -->
    <div class="col-md-3">
<div class="card p-3 border-start border-info border-4 text-info">
    <small class="fw-bold text-uppercase">Jumlah Booking</small>
    <h4 id="display-total-orang" class="mb-0 fw-bold"><?= $total_booking; ?> <small class="fs-6 fw-normal text-muted">Orang</small></h4>
</div>
    </div>
    
    <div class="col-md-3">
        <div class="card p-3 border-start border-primary border-4 text-primary">
            <small class="fw-bold text-uppercase">Omzet Hari Ini</small>
            <h4 class="mb-0 fw-bold">Rp <?= number_format($total_tagihan, 0, ',', '.'); ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 border-start border-success border-4 text-success">
            <small class="fw-bold text-uppercase">Uang Masuk Hari Ini</small>
            <h4 class="mb-0 fw-bold">Rp <?= number_format($total_masuk, 0, ',', '.'); ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 border-start border-danger border-4 text-danger">
            <small class="fw-bold text-uppercase">Total Piutang</small>
            <h4 class="mb-0 fw-bold">Rp <?= number_format($total_piutang, 0, ',', '.'); ?></h4>
        </div>
    </div>
</div>

<!-- BUTTON SCAN -->
<div class="mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scanModal">
        <i class="bi bi-qr-code-scan"></i> Scan QR Invoice
    </button>
</div>

<div class="modal fade" id="scanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-camera"></i> Scan QR Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="reader" style="width: 100%; min-height: 250px; background: #000; border-radius: 8px;"></div>
                <p class="text-muted small mt-3 mb-0">Arahkan kamera ke QR Code pada Invoice</p>
            </div>
        </div>
    </div>
</div>

    <div class="card p-4">
        <h4 class="mb-4">Daftar Booking Customer</h4>
        
        <div class="table-responsive">

<!-- SORTING -->
<div class="row mb-3 g-2 align-items-end">
    <div class="col-md-3">
        <label class="form-label small fw-bold">Filter Dari Tanggal:</label>
        <input type="date" id="min" class="form-control form-control-sm">
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-bold">Sampai Tanggal:</label>
        <input type="date" id="max" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
        <button id="btn-reset" class="btn btn-sm btn-secondary w-100"><i class="bi bi-arrow-clockwise"></i> Reset</button>
    </div>
</div>

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
                                    <span>Bayar:</span>
                                    <span>Rp <?= number_format($terbayar, 0, ',', '.'); ?></span>
                                </div>
                            <div class="d-flex justify-content-between border-top mt-1 pt-1 
                                <?= $sisa > 0 ? 'text-danger fw-bold' : ($sisa < 0 ? 'text-primary fw-bold' : 'text-muted'); ?>">
                                
                                <span><?= $sisa >= 0 ? 'Kurang:' : 'Kembali:'; ?></span>
                                
                                <span>
                                    <?php if ($sisa != 0): ?>
                                        Rp <?= number_format(abs($sisa), 0, ',', '.'); ?>
                                    <?php else: ?>
                                        <i class="bi bi-check-all"></i> LUNAS
                                    <?php endif; ?>
                                </span>
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
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#modalKelola<?= $id_b; ?>" title="Kelola Layanan & Petugas">
                                <i class="bi bi-gear-fill"></i> Kelola
                            </button>

                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalBayar<?= $id_b; ?>" title="Input Pembayaran">
                                <i class="bi bi-cash-stack"></i> Bayar
                            </button>

                            <a href="cetak_nota.php?id=<?= $id_b; ?>" target="_blank" class="btn btn-sm btn-info text-white" title="Cetak Nota">
                                <i class="bi bi-printer-fill"></i> Nota
                            </a>

                            <a href="../class/delete_booking.php?id=<?= $id_b; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus permanen data booking ini?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>

<!-- Menu Baru -->
<div class="modal fade" id="modalKelola<?= $id_b; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="../class/update_booking.php" method="POST">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Operasional Booking #<?= $id_b; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_booking" value="<?= $id_b; ?>">
                    <input type="hidden" name="update_type" value="operasional">

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase">Status Pengerjaan</label>
                        <select name="status_kerja" class="form-select shadow-sm">
                            <option value="menunggu" <?= $row['status_kerja'] == 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="diproses" <?= $row['status_kerja'] == 'diproses' ? 'selected' : ''; ?>>Sedang Diproses</option>
                            <option value="selesai" <?= $row['status_kerja'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        </select>
                    </div>

                    <h6 class="small fw-bold text-primary mb-3">DETAIL LAYANAN & PETUGAS</h6>
                    <?php 
                    $det = mysqli_query($conn, "SELECT d.*, s.nama_layanan FROM booking_details d JOIN services s ON d.id_service = s.id_service WHERE d.id_booking = '$id_b'");
                    while($ld = mysqli_fetch_array($det)){
                    ?>
                        <div class="mb-3 p-3 border rounded-3 bg-white shadow-sm">
                            <label class="form-label small fw-bold d-block mb-1"><?= $ld['nama_layanan']; ?></label>
                            <input type="hidden" name="id_detail[]" value="<?= $ld['id_detail']; ?>">
                            <div class="row g-2">
                                <div class="col-7">
                                    <select name="id_employee[]" class="form-select form-select-sm">
                                        <option value="">-- Pilih Petugas --</option>
                                        <?php 
                                        $emp = mysqli_query($conn, "SELECT * FROM employees");
                                        while($e = mysqli_fetch_array($emp)){
                                            $sel = ($e['id_employee'] == $ld['id_employee']) ? 'selected' : '';
                                            echo "<option value='".$e['id_employee']."' $sel>".$e['nama_karyawan']."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" name="harga_layanan[]" class="form-control" value="<?= $ld['subtotal']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Update Data Operasional</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Menu baru Bayar -->
<div class="modal fade" id="modalBayar<?= $id_b; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="../class/update_booking.php" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title fw-bold">Kasir Pembayaran #<?= $id_b; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_booking" value="<?= $id_b; ?>">
                    <input type="hidden" name="update_type" value="pembayaran">

                    <!-- Area Total Tagihan -->
                    <div class="text-center mb-4 py-3 bg-light rounded-3">
                        <span class="text-muted small text-uppercase">Total Tagihan</span>
                        <!-- ID 'total_val_...' digunakan sebagai referensi di JS -->
                        <h2 class="fw-bold mb-0 text-dark" id="total_val_<?= $id_b; ?>" data-nominal="<?= $total_biaya; ?>">
                            Rp <?= number_format($total_biaya, 0, ',', '.'); ?>
                        </h2>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label small fw-bold">BAYAR CASH (RP)</label>
                            <!-- ID 'cash_...' harus sama dengan di script JS -->
                            <input type="number" name="bayar_cash" id="cash_<?= $id_b; ?>" class="form-control input-pembayaran" 
                                   value="<?= $row['bayar_cash'] ?? 0; ?>" data-id="<?= $id_b; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">TRANSFER (RP)</label>
                            <!-- ID 'trf_...' harus sama dengan di script JS -->
                            <input type="number" name="bayar_transfer" id="trf_<?= $id_b; ?>" class="form-control input-pembayaran" 
                                   value="<?= $row['bayar_transfer'] ?? 0; ?>" data-id="<?= $id_b; ?>">
                        </div>
                    </div>

                    <!-- Indikator Kembalian -->
                    <div id="box_kembalian_<?= $id_b; ?>" class="p-3 bg-warning bg-opacity-10 rounded-3 border border-warning border-dashed text-center mb-4">
                        <span class="small fw-bold text-uppercase d-block mb-1" id="label_selisih_<?= $id_b; ?>">Uang Kembalian</span>
                        <h3 class="fw-bold text-success mb-0" id="change_text_<?= $id_b; ?>">Rp 0</h3>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold">STATUS PEMBAYARAN</label>
                        <select name="status_pembayaran" id="status_p_<?= $id_b; ?>" class="form-select fw-bold">
                            <option value="pending" <?= $row['status_pembayaran'] == 'pending' ? 'selected' : ''; ?>>Pending (Belum Bayar)</option>
                            <option value="dp" <?= $row['status_pembayaran'] == 'dp' ? 'selected' : ''; ?>>DP (Bayar Sebagian)</option>
                            <option value="lunas" <?= $row['status_pembayaran'] == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="update_admin" class="btn btn-success w-100 fw-bold py-2 shadow">Simpan Pembayaran</button>
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

<script src="https://unpkg.com/html5-qrcode"></script>

<script>$(document).ready(function() {
    // ==========================================
    // 1. INISIALISASI DATATABLES
    // ==========================================
    var table = $('#tabelBooking').DataTable({
        "order": [[ 0, "desc" ]],
        "language": { 
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json" 
        },
        "drawCallback": function(settings) {
            var api = this.api();
            
            // A. Hitung Total Orang (Baris yang terlihat setelah filter)
            var totalOrang = api.rows({ filter: 'applied' }).count();
            $('#display-total-orang').html(totalOrang + ' <small class="fs-6 fw-normal text-muted">Orang</small>');

            // B. Hitung Total Omzet Otomatis
            // Kita asumsikan kolom "Total Biaya" ada di indeks ke-3 (kolom ke-4)
            // Sesuaikan angka 3 jika posisi kolom Anda berbeda
            var totalOmzet = api.column(3, { filter: 'applied' }).data().reduce(function (a, b) {
                // Bersihkan string dari "Rp", titik, dsb untuk dikonversi ke angka
                var cleanA = typeof a === 'string' ? a.replace(/[^\d]/g, '') : a;
                var cleanB = typeof b === 'string' ? b.replace(/[^\d]/g, '') : b;
                return (parseInt(cleanA) || 0) + (parseInt(cleanB) || 0);
            }, 0);

            $('#display-total-omzet').html('Rp ' + totalOmzet.toLocaleString('id-ID'));
        }
    });

    // ==========================================
    // 2. FILTER TANGGAL CUSTOM
    // ==========================================
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            var min = $('#min').val(); 
            var max = $('#max').val(); 
            var rowNode = table.cell(dataIndex, 2).node(); // Indeks 2 adalah kolom tanggal
            var date = rowNode ? rowNode.getAttribute('data-sort') : "";

            if (
                (min === "" && max === "") ||
                (min === "" && date <= max) ||
                (min <= date && max === "") ||
                (min <= date && date <= max)
            ) {
                return true;
            }
            return false;
        }
    );

    $('#min, #max').on('change', function() {
        table.draw();
    });

    $('#btn-reset').on('click', function() {
        $('#min').val('');
        $('#max').val('');
        table.draw();
    });

    // ==========================================
    // 3. LOGIKA QR SCANNER
    // ==========================================
    let html5QrCode = null;

    $('#scanModal').on('shown.bs.modal', function () {
        html5QrCode = new Html5Qrcode("reader");
        const qrConfig = { fps: 10, qrbox: { width: 250, height: 250 } };

        html5QrCode.start(
            { facingMode: "environment" }, 
            qrConfig,
            (decodedText) => {
                table.search(decodedText).draw(); 
                $('#scanModal').modal('hide');
            }
        ).catch(err => console.error("Kamera gagal:", err));
    });

    $('#scanModal').on('hidden.bs.modal', function () {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
            }).catch(err => console.warn("Gagal stop scanner:", err));
        }
    });

    // ==========================================
    // 4. LOGIKA MODAL KELOLA (Update Harga & Total)
    // ==========================================
    $(document).on('input', '.harga-input', function() {
        var modal = $(this).closest('.modal');
        var totalBaru = 0;

        // Hitung semua input harga di dalam modal tersebut
        modal.find('.harga-input').each(function() {
            totalBaru += parseInt($(this).val()) || 0;
        });

        // Update tampilan teks total tagihan di modal kelola
        modal.find('.display-total-tagihan').text('Rp ' + totalBaru.toLocaleString('id-ID'));
        // Simpan angka murni di atribut data agar mudah dibaca oleh modal bayar
        modal.find('.display-total-tagihan').data('value', totalBaru);
    });

    // ==========================================
    // 5. LOGIKA MODAL BAYAR (Kembalian & Status)
    // ==========================================
    $(document).ready(function() {
    // Fungsi utama hitung kembalian
    $(document).on('input', '.input-pembayaran', function() {
        // Ambil ID booking dari atribut data-id
        var id = $(this).data('id');
        
        // Ambil nominal angka murni dari atribut data-nominal
        var totalTagihan = parseInt($('#total_val_' + id).data('nominal')) || 0;
        
        // Ambil nilai input cash dan transfer
        var cash = parseInt($('#cash_' + id).val()) || 0;
        var trf = parseInt($('#trf_' + id).val()) || 0;
        
        var totalBayar = cash + trf;
        var selisih = totalBayar - totalTagihan;

        // Target elemen display
        var changeText = $('#change_text_' + id);
        var labelSelisih = $('#label_selisih_' + id);
        var selectStatus = $('#status_p_' + id);

        if (selisih >= 0) {
            // Jika Uang Pas atau Lebih (Kembalian)
            labelSelisih.text("Uang Kembalian");
            changeText.text("Rp " + new Intl.NumberFormat('id-ID').format(selisih));
            changeText.removeClass('text-danger').addClass('text-success');
            
            // Auto-set Lunas jika uang cukup
            if (totalTagihan > 0) selectStatus.val('lunas');
        } else {
            // Jika Uang Kurang
            labelSelisih.text("Kekurangan Bayar");
            changeText.text("Rp " + new Intl.NumberFormat('id-ID').format(Math.abs(selisih)));
            changeText.removeClass('text-success').addClass('text-danger');
            
            // Auto-set DP jika sudah ada uang masuk tapi belum cukup
            if (totalBayar > 0) {
                selectStatus.val('dp');
            } else {
                selectStatus.val('pending');
            }
        }
    });
});
});
</script>
</body>
</html>