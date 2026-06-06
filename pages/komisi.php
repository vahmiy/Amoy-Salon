<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$user_level = $_SESSION['level'];
$user_nama  = $_SESSION['nama'] ?? $_SESSION['username'];
include '../class/koneksi.php';

// ═══════════════════════════════════════════════
//  FILTER PARAMS
// ═══════════════════════════════════════════════
$filter_periode  = isset($_GET['periode'])     ? $_GET['periode']     : date('Y-m');
$filter_karyawan = isset($_GET['id_employee']) ? $_GET['id_employee'] : '';

$tahun = date('Y', strtotime($filter_periode . '-01'));
$bulan = date('m', strtotime($filter_periode . '-01'));

// ═══════════════════════════════════════════════
//  SUMMARY STATS (bulan yang dipilih)
// ═══════════════════════════════════════════════
$where_base = "WHERE b.status_kerja='selesai' AND b.status_pembayaran='lunas'
               AND MONTH(b.tgl_booking)='$bulan' AND YEAR(b.tgl_booking)='$tahun'";
$where_emp  = ($filter_karyawan != '')
    ? " AND d.id_employee='" . mysqli_real_escape_string($conn, $filter_karyawan) . "'"
    : '';

// Total komisi & jumlah transaksi periode ini
$q_stat = mysqli_query($conn,
    "SELECT COUNT(d.id_detail) AS jml_transaksi,
            COALESCE(SUM(d.subtotal * s.komisi_persen / 100), 0) AS total_komisi,
            COALESCE(SUM(d.subtotal), 0) AS total_omset,
            COUNT(DISTINCT d.id_employee) AS jml_petugas
     FROM booking_details d
     JOIN bookings b ON d.id_booking = b.id_booking
     JOIN services s ON d.id_service = s.id_service
     $where_base $where_emp"
);
$stat = mysqli_fetch_assoc($q_stat);

// Rata-rata komisi per transaksi
$avg_komisi = ($stat['jml_transaksi'] > 0)
    ? ($stat['total_komisi'] / $stat['jml_transaksi'])
    : 0;

// Top karyawan bulan ini (semua, bukan hanya filter)
$q_top = mysqli_query($conn,
    "SELECT e.nama_karyawan,
            COALESCE(SUM(d.subtotal * s.komisi_persen / 100), 0) AS total_komisi,
            COUNT(d.id_detail) AS jml_job
     FROM booking_details d
     JOIN bookings b ON d.id_booking = b.id_booking
     JOIN services s ON d.id_service = s.id_service
     JOIN employees e ON d.id_employee = e.id_employee
     WHERE b.status_kerja='selesai' AND b.status_pembayaran='lunas'
       AND MONTH(b.tgl_booking)='$bulan' AND YEAR(b.tgl_booking)='$tahun'
     GROUP BY d.id_employee
     ORDER BY total_komisi DESC
     LIMIT 5"
);

// Komisi per layanan (bulan ini)
$q_svc = mysqli_query($conn,
    "SELECT s.nama_layanan,
            COUNT(d.id_detail) AS jml,
            COALESCE(SUM(d.subtotal * s.komisi_persen / 100), 0) AS komisi_svc
     FROM booking_details d
     JOIN bookings b ON d.id_booking = b.id_booking
     JOIN services s ON d.id_service = s.id_service
     WHERE b.status_kerja='selesai' AND b.status_pembayaran='lunas'
       AND MONTH(b.tgl_booking)='$bulan' AND YEAR(b.tgl_booking)='$tahun'
     GROUP BY d.id_service
     ORDER BY komisi_svc DESC
     LIMIT 5"
);

// ═══════════════════════════════════════════════
//  DATA TABEL UTAMA
// ═══════════════════════════════════════════════
$where_tabel = $where_base . $where_emp;
$sql = "SELECT b.tgl_booking, e.nama_karyawan, s.nama_layanan,
               d.subtotal AS harga_real,
               s.komisi_persen,
               (d.subtotal * s.komisi_persen / 100) AS rupiah_komisi
        FROM booking_details d
        JOIN bookings b ON d.id_booking = b.id_booking
        JOIN services s ON d.id_service = s.id_service
        JOIN employees e ON d.id_employee = e.id_employee
        $where_tabel
        ORDER BY b.tgl_booking DESC";

$query = mysqli_query($conn, $sql);
$rows  = [];
while ($row = mysqli_fetch_assoc($query)) {
    $rows[] = $row;
}

$grand_total_komisi = array_sum(array_column($rows, 'rupiah_komisi'));
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Komisi Karyawan — Amoy Salon</title>
<link rel="icon" type="image/png" href="../asset/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<style>
/* ═══════════════════════════════════════
   DESIGN TOKENS — identical to admin.php
═══════════════════════════════════════ */
[data-theme="light"] {
    --bg:           #f1f2f6;
    --bg-card:      #ffffff;
    --bg-input:     #f8f9fc;
    --bg-hover:     #f0f1f8;
    --bg-stripe:    #fafbff;
    --border:       #e2e5f0;
    --border-input: #d0d4e8;
    --text:         #1a1d2e;
    --text-sub:     #5a5f7d;
    --text-muted:   #9499b8;
    --nav-bg:       #2f3542;
    --nav-text:     #cdd1e0;
    --nav-active:   #ffffff;
    --shadow-sm:    0 1px 4px rgba(0,0,0,.06);
    --shadow:       0 4px 16px rgba(0,0,0,.08);
    --shadow-lg:    0 8px 32px rgba(0,0,0,.12);
    --shadow-card:  0 2px 8px rgba(67,75,120,.08);
}
[data-theme="dark"] {
    --bg:           #0e0f18;
    --bg-card:      #161825;
    --bg-input:     #1d1f30;
    --bg-hover:     #1f2135;
    --bg-stripe:    #171928;
    --border:       rgba(255,255,255,.07);
    --border-input: rgba(255,255,255,.1);
    --text:         #eef0fb;
    --text-sub:     #8c90b0;
    --text-muted:   #555974;
    --nav-bg:       #111220;
    --nav-text:     #9498b8;
    --nav-active:   #ffffff;
    --shadow-sm:    0 1px 4px rgba(0,0,0,.3);
    --shadow:       0 4px 16px rgba(0,0,0,.4);
    --shadow-lg:    0 8px 32px rgba(0,0,0,.6);
    --shadow-card:  0 2px 8px rgba(0,0,0,.3);
}
:root {
    --accent:        #6c5ce7;
    --accent-2:      #a29bfe;
    --accent-bg:     rgba(108,92,231,.1);
    --accent-border: rgba(108,92,231,.25);
    --green:         #00b894;
    --green-bg:      rgba(0,184,148,.1);
    --green-border:  rgba(0,184,148,.25);
    --yellow:        #f39c12;
    --yellow-bg:     rgba(243,156,18,.1);
    --yellow-border: rgba(243,156,18,.25);
    --red:           #e74c3c;
    --red-bg:        rgba(231,76,60,.1);
    --red-border:    rgba(231,76,60,.25);
    --blue:          #3498db;
    --blue-bg:       rgba(52,152,219,.1);
    --blue-border:   rgba(52,152,219,.25);
    --pink:          #e84393;
    --pink-bg:       rgba(232,67,147,.1);
    --radius:        10px;
    --radius-lg:     14px;
    --radius-xl:     18px;
    --font:          'Nunito', sans-serif;
    --mono:          'Fira Code', monospace;
    --trans:         all .18s ease;
}

*,*::before,*::after { box-sizing: border-box }
html { scroll-behavior: smooth }
body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    font-size: 14px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    transition: background .25s ease, color .25s ease;
}
::-webkit-scrollbar { width: 5px; height: 5px }
::-webkit-scrollbar-track { background: transparent }
::-webkit-scrollbar-thumb { background: var(--border-input); border-radius: 3px }
::-webkit-scrollbar-thumb:hover { background: var(--accent) }

/* ── NAVBAR ── */
.navbar {
    background: var(--nav-bg) !important;
    padding: .5rem 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.15);
    position: sticky; top: 0; z-index: 1040;
    transition: background .25s;
}
.navbar-brand {
    font-weight: 900; font-size: 1.05rem; letter-spacing: -.3px;
    color: var(--nav-active) !important;
    display: flex; align-items: center; gap: 8px;
}
.brand-icon {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, var(--accent), var(--pink));
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.nav-link {
    color: var(--nav-text) !important;
    font-size: .85rem; font-weight: 600;
    padding: .4rem .75rem !important;
    border-radius: 7px;
    transition: var(--trans);
    display: flex; align-items: center; gap: 5px;
}
.nav-link:hover, .nav-link.active {
    background: rgba(255,255,255,.08);
    color: var(--nav-active) !important;
}
.nav-link.nav-komisi { color: #fdcb6e !important }
.nav-link.text-warning { color: #f39c12 !important }
.nav-link.text-info { color: #74b9ff !important }
.btn-booking-online {
    font-size: .8rem; font-weight: 700; padding: .3rem .9rem;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 7px; color: #fff !important;
    transition: var(--trans);
}
.btn-booking-online:hover { background: rgba(255,255,255,.12) }
.btn-logout { color: #ff6b6b !important; font-weight: 700 }
.btn-logout:hover { background: rgba(255,107,107,.12) !important }
.theme-toggle {
    width: 36px; height: 36px; border-radius: 8px;
    border: 1px solid rgba(255,255,255,.15);
    background: rgba(255,255,255,.06);
    color: #fff; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; transition: var(--trans);
}
.theme-toggle:hover { background: rgba(255,255,255,.15) }

/* ── LAYOUT ── */
.page-container {
    max-width: 1600px; margin: 0 auto;
    padding: 28px 20px;
}
.page-head {
    display: flex; align-items: flex-end; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 28px;
}
.page-title { font-size: 1.4rem; font-weight: 900; letter-spacing: -.4px; margin: 0 }
.page-subtitle { font-size: .82rem; color: var(--text-muted); margin: 2px 0 0 }
.date-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 20px;
    background: var(--bg-card); border: 1px solid var(--border);
    font-size: .78rem; font-weight: 600; color: var(--text-sub);
    box-shadow: var(--shadow-sm);
}

/* ── STAT CARDS ── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px; margin-bottom: 20px;
}
@media(max-width:1100px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:576px){.stat-grid{grid-template-columns:1fr 1fr}}
.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 18px 20px 16px;
    position: relative; overflow: hidden;
    box-shadow: var(--shadow-card);
    transition: var(--trans);
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow) }
.stat-card::after {
    content: ''; position: absolute;
    top: -30px; right: -30px;
    width: 90px; height: 90px; border-radius: 50%;
    background: var(--c-soft); opacity: .6;
}
.stat-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: var(--c-soft); color: var(--c);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 12px;
    position: relative; z-index: 1;
}
.stat-label {
    font-size: .72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; color: var(--text-muted); margin-bottom: 2px;
}
.stat-value {
    font-size: 1.35rem; font-weight: 900; color: var(--text);
    font-family: var(--mono); letter-spacing: -.5px; line-height: 1.2;
}
.stat-value.sm { font-size: 1rem }
.stat-note { font-size: .73rem; color: var(--text-muted); margin-top: 3px }

/* ── SIDE PANELS ── */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px; margin-bottom: 20px;
}
@media(max-width:900px){.info-grid{grid-template-columns:1fr}}
.panel-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 0;
    box-shadow: var(--shadow-card);
    overflow: hidden;
}
.panel-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    font-size: .78rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .7px;
    color: var(--text-muted);
    display: flex; align-items: center; gap: 7px;
    background: var(--bg-stripe);
}
.panel-body { padding: 14px 18px }
.rank-item {
    display: flex; align-items: center; gap: 12px;
    padding: 9px 0;
    border-bottom: 1px solid var(--border);
}
.rank-item:last-child { border-bottom: none }
.rank-num {
    width: 24px; height: 24px; border-radius: 50%;
    background: var(--bg-stripe); border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: .72rem; font-weight: 800; font-family: var(--mono);
    color: var(--text-muted); flex-shrink: 0;
}
.rank-num.gold   { background: var(--yellow-bg); border-color: var(--yellow-border); color: var(--yellow) }
.rank-num.silver { background: var(--bg-hover); border-color: var(--border-input); color: var(--text-sub) }
.rank-num.bronze { background: rgba(196,135,86,.1); border-color: rgba(196,135,86,.25); color: #c48756 }
.rank-name { flex: 1; font-size: .85rem; font-weight: 700; color: var(--text) }
.rank-sub  { font-size: .73rem; color: var(--text-muted) }
.rank-val  { font-family: var(--mono); font-size: .85rem; font-weight: 800; color: var(--green) }
.svc-bar-item { padding: 7px 0; border-bottom: 1px solid var(--border) }
.svc-bar-item:last-child { border-bottom: none }
.svc-bar-label {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 5px;
    font-size: .8rem;
}
.svc-bar-name { font-weight: 700; color: var(--text) }
.svc-bar-val  { font-family: var(--mono); font-size: .78rem; color: var(--accent); font-weight: 700 }
.svc-bar-track {
    height: 5px; background: var(--bg-stripe);
    border-radius: 20px; overflow: hidden;
}
.svc-bar-fill {
    height: 100%; background: linear-gradient(90deg, var(--accent), var(--pink));
    border-radius: 20px; transition: width .6s ease;
}

/* ── FILTER CARD ── */
.filter-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 18px 20px;
    margin-bottom: 18px; box-shadow: var(--shadow-card);
}
.filter-title {
    font-size: .73rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .7px; color: var(--text-muted); margin-bottom: 14px;
    display: flex; align-items: center; gap: 6px;
}

/* ── FORM CONTROLS ── */
.form-control, .form-select {
    background: var(--bg-input) !important;
    border: 1px solid var(--border-input) !important;
    color: var(--text) !important;
    border-radius: 8px !important;
    font-size: .845rem !important;
    font-family: var(--font) !important;
    padding: 8px 12px !important;
    transition: var(--trans) !important;
}
.form-control:focus, .form-select:focus {
    border-color: var(--accent) !important;
    box-shadow: 0 0 0 3px var(--accent-bg) !important;
    outline: none !important;
}
.form-control::placeholder { color: var(--text-muted) !important }
.form-select option { background: var(--bg-card); color: var(--text) }
.form-label {
    font-size: .78rem; font-weight: 700; color: var(--text-sub);
    margin-bottom: 5px; display: block;
}
.input-group-text {
    background: var(--bg-hover) !important;
    border: 1px solid var(--border-input) !important;
    color: var(--text-sub) !important; font-size: .845rem !important;
}

/* ── BUTTONS ── */
.btn {
    font-family: var(--font) !important;
    font-weight: 700 !important; font-size: .82rem !important;
    border-radius: 8px !important;
    transition: var(--trans) !important;
    display: inline-flex !important; align-items: center !important; gap: 5px !important;
}
.btn-primary {
    background: var(--accent) !important; border: none !important;
    color: #fff !important; padding: 8px 18px !important;
}
.btn-primary:hover { background: #5a4bd1 !important; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(108,92,231,.35) !important }
.btn-outline-secondary {
    background: transparent !important;
    border: 1px solid var(--border-input) !important;
    color: var(--text-sub) !important; padding: 7px 14px !important;
}
.btn-outline-secondary:hover { background: var(--bg-hover) !important; color: var(--text) !important }
.btn-outline-success {
    background: transparent !important;
    border: 1px solid var(--green-border) !important;
    color: var(--green) !important; padding: 7px 14px !important;
}
.btn-outline-success:hover { background: var(--green-bg) !important }
.btn-sm { padding: 5px 12px !important; font-size: .78rem !important }
.btn-reset {
    background: transparent !important; border: 1px solid var(--border-input) !important;
    color: var(--text-muted) !important;
}
.btn-reset:hover { border-color: var(--red) !important; color: var(--red) !important; background: var(--red-bg) !important }

/* ── TABLE CARD ── */
.table-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden;
    box-shadow: var(--shadow-card);
}
.table-head-row {
    padding: 16px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
}
.table-card-title {
    font-size: .95rem; font-weight: 900; color: var(--text); margin: 0;
}
.table-count-chip {
    font-size: .74rem; font-family: var(--mono); font-weight: 500;
    color: var(--text-muted); background: var(--bg-stripe);
    border: 1px solid var(--border); padding: 3px 10px; border-radius: 20px;
}
.table-scroll { overflow-x: auto }
table.dash-table {
    width: 100%; border-collapse: collapse;
    min-width: 780px;
}
table.dash-table thead tr {
    background: var(--bg-stripe);
    border-bottom: 2px solid var(--border);
}
table.dash-table thead th {
    font-size: .72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .6px; color: var(--text-muted);
    padding: 12px 16px; white-space: nowrap;
}
table.dash-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .12s;
}
table.dash-table tbody tr:last-child { border-bottom: none }
table.dash-table tbody tr:hover { background: var(--bg-stripe) }
table.dash-table tbody td {
    padding: 12px 16px; vertical-align: middle;
    font-size: .84rem; color: var(--text);
}
table.dash-table tfoot tr {
    border-top: 2px solid var(--border);
    background: var(--bg-stripe);
}
table.dash-table tfoot td, table.dash-table tfoot th {
    padding: 14px 16px; font-size: .88rem;
}
.td-mono { font-family: var(--mono); font-weight: 700 }
.td-komisi { font-family: var(--mono); font-weight: 800; color: var(--green); font-size: .9rem }
.td-grand  { font-family: var(--mono); font-weight: 900; color: var(--accent); font-size: 1rem }

/* ── BADGES ── */
.badge-s {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.badge-s::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: currentColor; flex-shrink: 0;
}
.bs-komisi  { background: var(--accent-bg); color: var(--accent) }
.bs-green   { background: var(--green-bg);  color: var(--green) }
.bs-yellow  { background: var(--yellow-bg); color: var(--yellow) }

/* ── PRINT STYLE ── */
@media print {
    .navbar, .filter-card, .info-grid, .stat-grid, .page-head,
    .btn, .no-print { display: none !important }
    .table-card { box-shadow: none !important; border: 1px solid #ddd }
    body { background: #fff !important; color: #000 !important }
    .page-container { padding: 0 !important }
}

/* ── TOAST ── */
.toast-wrap {
    position: fixed; bottom: 22px; right: 22px; z-index: 9999;
    display: flex; flex-direction: column; gap: 8px; pointer-events: none;
}
.toast-item {
    display: flex; align-items: center; gap: 10px;
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 12px; padding: 12px 16px;
    min-width: 260px; max-width: 340px;
    box-shadow: var(--shadow-lg);
    font-size: .84rem; font-weight: 600;
    animation: toastIn .3s ease; pointer-events: all;
}
.toast-item.ok  { border-left: 3px solid var(--green) }
.toast-ic-ok    { color: var(--green); font-size: 17px }
@keyframes toastIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }

/* ── EMPTY STATE ── */
.empty-row td { padding: 56px 20px !important; text-align: center }
.empty-icon   { font-size: 2.8rem; opacity: .25; margin-bottom: 12px }
.empty-txt    { color: var(--text-muted); font-size: .85rem }

/* ── DATATABLES OVERRIDE ── */
.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select {
    background: var(--bg-input) !important;
    border: 1px solid var(--border-input) !important;
    color: var(--text) !important;
    border-radius: 8px !important;
    font-family: var(--font) !important;
    font-size: .82rem !important;
    padding: 5px 10px !important;
}
.dataTables_wrapper .dataTables_filter label,
.dataTables_wrapper .dataTables_length label,
.dataTables_wrapper .dataTables_info {
    color: var(--text-muted) !important;
    font-size: .78rem !important;
    font-family: var(--font) !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 7px !important; font-family: var(--mono) !important;
    font-size: .78rem !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: var(--accent) !important; border-color: var(--accent) !important;
    color: #fff !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--bg-hover) !important; border-color: var(--border) !important;
    color: var(--text) !important;
}

@media(max-width:768px){
    .page-container { padding: 16px 12px }
    .page-head { flex-direction: column; align-items: flex-start }
    .stat-grid { grid-template-columns: 1fr 1fr }
}
</style>
</head>
<body>

<!-- TOAST -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ════════════════════════
     NAVBAR
════════════════════════ -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid px-2">
    <a class="navbar-brand" href="admin.php">
      <div class="brand-icon">✨</div>
      Amoy Salon
    </a>
    <div class="d-flex align-items-center gap-2 ms-auto d-lg-none">
      <button class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">🌙</button>
      <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <i class="bi bi-list fs-5"></i>
      </button>
    </div>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
        <?php if($user_level<=2): ?>
        <li class="nav-item"><a class="nav-link" href="admin.php"><i class="bi bi-calendar2-check"></i>Booking</a></li>
        <?php endif; ?>
        <?php if($user_level<=1): ?>
        <li class="nav-item"><a class="nav-link text-warning" href="master_data.php"><i class="bi bi-gear"></i>Master Data</a></li>
        <?php endif; ?>
        <?php if($user_level<=2): ?>
        <li class="nav-item"><a class="nav-link text-info" href="laporan.php"><i class="bi bi-bar-chart-line"></i>Pendapatan</a></li>
        <li class="nav-item"><a class="nav-link active nav-komisi" href="komisi.php"><i class="bi bi-cash-coin"></i>Komisi</a></li>
        <li class="nav-item"><a class="nav-link text-info" href="laporan_pembayaran.php"><i class="bi bi-book-half"></i>Pembukuan</a></li>
        <?php endif; ?>
        <?php if($user_level<=1): ?>
        <li class="nav-item"><a class="nav-link text-warning" href="../class/user_manage.php"><i class="bi bi-people"></i>Akun</a></li>
        <?php endif; ?>
        <li class="nav-item"><div class="nav-divider d-none d-lg-block" style="width:1px;height:22px;background:rgba(255,255,255,.12);margin:0 4px"></div></li>
        <li class="nav-item"><a href="../index.php" target="_blank" class="nav-link btn-booking-online"><i class="bi bi-globe2"></i>Booking Online</a></li>
        <li class="nav-item d-none d-lg-flex align-items-center ms-2">
          <button class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">🌙</button>
        </li>
        <li class="nav-item"><a class="nav-link btn-logout" href="../class/logout.php" onclick="return confirm('Yakin keluar?')"><i class="bi bi-box-arrow-right"></i>Keluar</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- ════════════════════════
     PAGE CONTENT
════════════════════════ -->
<div class="page-container">

  <!-- PAGE HEAD -->
  <div class="page-head">
    <div>
      <h1 class="page-title"><i class="bi bi-cash-coin me-2" style="color:var(--yellow)"></i>Laporan Komisi Karyawan</h1>
      <p class="page-subtitle">Periode: <strong><?= date('F Y', strtotime($filter_periode . '-01')) ?></strong><?= $filter_karyawan ? ' — ' . htmlspecialchars(mysqli_fetch_assoc(mysqli_query($conn,"SELECT nama_karyawan FROM employees WHERE id_employee='".mysqli_real_escape_string($conn,$filter_karyawan)."'"))['nama_karyawan'] ?? '') : ' — Semua Karyawan' ?></p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <div class="date-chip"><i class="bi bi-calendar3"></i><?= date('l, d F Y') ?></div>
      <button class="btn btn-outline-success btn-sm no-print" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak Laporan
      </button>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-grid">
    <div class="stat-card" style="--c:var(--yellow);--c-soft:var(--yellow-bg)">
      <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
      <div class="stat-label">Total Komisi</div>
      <div class="stat-value sm">Rp&nbsp;<?= number_format($stat['total_komisi'] ?? 0, 0, ',', '.') ?></div>
      <div class="stat-note">Periode <?= date('M Y', strtotime($filter_periode . '-01')) ?></div>
    </div>
    <div class="stat-card" style="--c:var(--green);--c-soft:var(--green-bg)">
      <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="stat-label">Total Omset</div>
      <div class="stat-value sm">Rp&nbsp;<?= number_format($stat['total_omset'] ?? 0, 0, ',', '.') ?></div>
      <div class="stat-note">Dari transaksi lunas</div>
    </div>
    <div class="stat-card" style="--c:var(--blue);--c-soft:var(--blue-bg)">
      <div class="stat-icon"><i class="bi bi-receipt-cutoff"></i></div>
      <div class="stat-label">Jumlah Job</div>
      <div class="stat-value"><?= number_format($stat['jml_transaksi'] ?? 0) ?></div>
      <div class="stat-note">Layanan diselesaikan</div>
    </div>
    <div class="stat-card" style="--c:var(--accent);--c-soft:var(--accent-bg)">
      <div class="stat-icon"><i class="bi bi-calculator"></i></div>
      <div class="stat-label">Rata-rata/Job</div>
      <div class="stat-value sm">Rp&nbsp;<?= number_format($avg_komisi, 0, ',', '.') ?></div>
      <div class="stat-note"><?= number_format($stat['jml_petugas'] ?? 0) ?> petugas aktif</div>
    </div>
  </div>

  <!-- INFO PANELS: TOP KARYAWAN + KOMISI PER LAYANAN -->
  <?php
  $top_rows = [];
  while ($tr = mysqli_fetch_assoc($q_top)) $top_rows[] = $tr;
  $max_top = !empty($top_rows) ? $top_rows[0]['total_komisi'] : 1;

  $svc_rows = [];
  while ($sr = mysqli_fetch_assoc($q_svc)) $svc_rows[] = $sr;
  $max_svc = !empty($svc_rows) ? $svc_rows[0]['komisi_svc'] : 1;
  ?>
  <div class="info-grid">
    <!-- Top Karyawan -->
    <div class="panel-card">
      <div class="panel-head">
        <i class="bi bi-trophy"></i> Top Karyawan — <?= date('F Y', strtotime($filter_periode . '-01')) ?>
      </div>
      <div class="panel-body">
        <?php if(empty($top_rows)): ?>
          <div class="empty-txt text-center py-3">Belum ada data</div>
        <?php else: ?>
          <?php foreach($top_rows as $i => $tr): ?>
          <?php $rnk = ['gold','silver','bronze'][$i] ?? '' ?>
          <div class="rank-item">
            <div class="rank-num <?= $rnk ?>"><?= $i+1 ?></div>
            <div style="flex:1">
              <div class="rank-name"><?= htmlspecialchars($tr['nama_karyawan']) ?></div>
              <div class="rank-sub"><?= number_format($tr['jml_job']) ?> job selesai</div>
            </div>
            <div class="rank-val">Rp <?= number_format($tr['total_komisi'], 0, ',', '.') ?></div>
          </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>
    </div>

    <!-- Komisi per Layanan -->
    <div class="panel-card">
      <div class="panel-head">
        <i class="bi bi-scissors"></i> Komisi per Layanan — <?= date('F Y', strtotime($filter_periode . '-01')) ?>
      </div>
      <div class="panel-body">
        <?php if(empty($svc_rows)): ?>
          <div class="empty-txt text-center py-3">Belum ada data</div>
        <?php else: ?>
          <?php foreach($svc_rows as $sr): ?>
          <?php $pct = $max_svc > 0 ? round($sr['komisi_svc'] / $max_svc * 100) : 0 ?>
          <div class="svc-bar-item">
            <div class="svc-bar-label">
              <span class="svc-bar-name"><?= htmlspecialchars($sr['nama_layanan']) ?> <span style="color:var(--text-muted);font-weight:500">(<?= $sr['jml'] ?>x)</span></span>
              <span class="svc-bar-val">Rp <?= number_format($sr['komisi_svc'], 0, ',', '.') ?></span>
            </div>
            <div class="svc-bar-track">
              <div class="svc-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>
    </div>
  </div>

  <!-- FILTER -->
  <div class="filter-card no-print">
    <div class="filter-title"><i class="bi bi-funnel"></i>Filter Laporan</div>
    <form method="GET" id="filterForm">
      <div class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
          <label class="form-label">Pilih Karyawan</label>
          <select name="id_employee" class="form-select">
            <option value="">— Semua Karyawan —</option>
            <?php
            $emp_q = mysqli_query($conn, "SELECT * FROM employees ORDER BY nama_karyawan");
            while($e = mysqli_fetch_assoc($emp_q)):
              $sel = ($filter_karyawan == $e['id_employee']) ? 'selected' : '';
            ?>
            <option value="<?= $e['id_employee'] ?>" <?= $sel ?>><?= htmlspecialchars($e['nama_karyawan']) ?></option>
            <?php endwhile ?>
          </select>
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label">Periode Bulan</label>
          <input type="month" name="periode" class="form-control" value="<?= htmlspecialchars($filter_periode) ?>">
        </div>
        <div class="col-lg-2 col-md-4">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i>Cari Data</button>
        </div>
        <div class="col-lg-2 col-md-4">
          <a href="komisi.php" class="btn btn-reset w-100"><i class="bi bi-arrow-counterclockwise"></i>Reset</a>
        </div>
      </div>
    </form>
  </div>

  <!-- TABLE -->
  <div class="table-card">
    <div class="table-head-row">
      <div>
        <div class="table-card-title">History Komisi Pekerjaan</div>
      </div>
      <div class="d-flex align-items-center gap-10 flex-wrap" style="gap:8px">
        <span class="table-count-chip"><?= count($rows) ?> record</span>
      </div>
    </div>
    <div class="table-scroll">
      <table id="tabelKomisi" class="dash-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Karyawan</th>
            <th>Layanan Dikerjakan</th>
            <th>Harga Paket</th>
            <th>Komisi (%)</th>
            <th>Pendapatan Komisi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($rows)): ?>
          <tr class="empty-row">
            <td colspan="6">
              <div class="empty-icon">💸</div>
              <div class="empty-txt">Tidak ada data komisi untuk periode ini</div>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach($rows as $row): ?>
          <tr>
            <td>
              <div style="font-weight:700;font-size:.85rem"><?= date('d M Y', strtotime($row['tgl_booking'])) ?></div>
              <div style="font-size:.73rem;color:var(--text-muted);font-family:var(--mono)"><?= date('l', strtotime($row['tgl_booking'])) ?></div>
            </td>
            <td>
              <div style="font-weight:800;font-size:.88rem"><?= htmlspecialchars($row['nama_karyawan']) ?></div>
            </td>
            <td>
              <div style="font-size:.84rem"><?= htmlspecialchars($row['nama_layanan']) ?></div>
            </td>
            <td class="td-mono">Rp <?= number_format($row['harga_real'], 0, ',', '.') ?></td>
            <td>
              <span class="badge-s bs-komisi"><?= $row['komisi_persen'] ?>%</span>
            </td>
            <td class="td-komisi">Rp <?= number_format($row['rupiah_komisi'], 0, ',', '.') ?></td>
          </tr>
          <?php endforeach ?>
          <?php endif ?>
        </tbody>
        <?php if(!empty($rows)): ?>
        <tfoot>
          <tr>
            <td colspan="4" style="text-align:right;font-weight:800;color:var(--text-sub)">TOTAL KOMISI TERKUMPUL :</td>
            <td></td>
            <td class="td-grand">Rp <?= number_format($grand_total_komisi, 0, ',', '.') ?></td>
          </tr>
        </tfoot>
        <?php endif ?>
      </table>
    </div>
  </div>

</div><!-- end page-container -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
/* ── THEME TOGGLE ── */
(function(){
    const saved = localStorage.getItem('amoy-theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    document.querySelectorAll('.theme-toggle').forEach(b => b.textContent = saved==='dark'?'☀️':'🌙');
})();
function toggleTheme(){
    const current = document.documentElement.getAttribute('data-theme');
    const next = current==='dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('amoy-theme', next);
    document.querySelectorAll('.theme-toggle').forEach(b => b.textContent = next==='dark'?'☀️':'🌙');
}

/* ── DATATABLES ── */
$(document).ready(function(){
    if($('#tabelKomisi tbody tr').length && !$('#tabelKomisi tbody tr.empty-row').length){
        $('#tabelKomisi').DataTable({
            order: [[0,'desc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: [] }],
            language: {
                search: '',
                searchPlaceholder: 'Cari data...',
                lengthMenu: 'Tampil _MENU_ baris',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                paginate: { previous: '‹', next: '›' },
                zeroRecords: 'Data tidak ditemukan'
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3 px-1"fl>rt<"d-flex justify-content-between align-items-center mt-3 px-1"ip>',
            drawCallback: function(){
                // Re-hide tfoot on search (DataTables doesn't touch tfoot)
            }
        });
    }
});

/* ── TOAST ── */
function toast(msg, type){
    const w = document.getElementById('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast-item ' + (type||'ok');
    t.innerHTML = `<i class="bi bi-check-circle-fill toast-ic-ok"></i><span>${msg}</span>`;
    w.appendChild(t);
    setTimeout(()=>t.remove(), 3200);
}
</script>
</body>
</html>