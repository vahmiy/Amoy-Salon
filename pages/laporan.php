<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$user_level = $_SESSION['level'];
$user_nama  = $_SESSION['nama'] ?? $_SESSION['username'];
include '../class/koneksi.php';

$today     = date('Y-m-d');
$tgl_pilih = $_GET['tgl'] ?? $today;

// ── Total pendapatan hari terpilih (Lunas) ──
$q_total = mysqli_query($conn, "
    SELECT 
        SUM(total_biaya)       AS grand_total,
        COUNT(*)               AS jml_lunas,
        SUM(bayar_cash)        AS total_cash,
        SUM(bayar_transfer)    AS total_transfer
    FROM bookings 
    WHERE tgl_booking = '$tgl_pilih' AND status_pembayaran = 'lunas'
");
$res_total = mysqli_fetch_array($q_total);

// ── Statistik semua status hari terpilih ──
$q_stat = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_booking,
        SUM(CASE WHEN status_pembayaran='lunas'   THEN 1 ELSE 0 END) AS jml_lunas,
        SUM(CASE WHEN status_pembayaran='dp'      THEN 1 ELSE 0 END) AS jml_dp,
        SUM(CASE WHEN status_pembayaran='pending' THEN 1 ELSE 0 END) AS jml_pending,
        SUM(CASE WHEN status_pembayaran='batal'   THEN 1 ELSE 0 END) AS jml_batal,
        COALESCE(SUM(CASE WHEN status_pembayaran='lunas'   THEN total_biaya ELSE 0 END), 0) AS omset_lunas,
        COALESCE(SUM(CASE WHEN status_pembayaran='dp'      THEN jumlah_terbayar ELSE 0 END), 0) AS uang_dp,
        COALESCE(SUM(CASE WHEN status_pembayaran IN ('pending','dp') THEN (total_biaya - jumlah_terbayar) ELSE 0 END), 0) AS piutang
    FROM bookings
    WHERE tgl_booking = '$tgl_pilih' AND status_pembayaran != 'batal'
");
$stat = mysqli_fetch_assoc($q_stat);

// ── Rincian transaksi ──
$sql_list = "
    SELECT 
        b.id_booking, 
        b.nama_customer,
        b.whatsapp_customer,
        b.total_biaya,
        b.bayar_cash,
        b.bayar_transfer,
        b.status_pembayaran,
        b.status_kerja,
        b.jam_booking,
        GROUP_CONCAT(DISTINCT s.nama_layanan ORDER BY d.id_detail SEPARATOR '||') AS daftar_layanan,
        GROUP_CONCAT(DISTINCT e.nama_karyawan ORDER BY e.nama_karyawan SEPARATOR '||') AS daftar_karyawan
    FROM bookings b 
    LEFT JOIN booking_details d ON b.id_booking = d.id_booking
    LEFT JOIN services s ON d.id_service = s.id_service
    LEFT JOIN employees e ON d.id_employee = e.id_employee 
    WHERE b.tgl_booking = '$tgl_pilih' AND b.status_pembayaran = 'lunas'
    GROUP BY b.id_booking
    ORDER BY b.id_booking DESC
";
$q_list = mysqli_query($conn, $sql_list);

// ── Top layanan hari ini ──
$q_top = mysqli_query($conn, "
    SELECT s.nama_layanan, COUNT(*) AS jml, SUM(d.subtotal) AS total
    FROM booking_details d
    JOIN bookings b ON d.id_booking = b.id_booking
    JOIN services s ON d.id_service = s.id_service
    WHERE b.tgl_booking = '$tgl_pilih' AND b.status_pembayaran = 'lunas'
    GROUP BY d.id_service
    ORDER BY total DESC
    LIMIT 5
");

// ── Kinerja karyawan hari ini ──
$q_emp = mysqli_query($conn, "
    SELECT e.nama_karyawan, COUNT(DISTINCT d.id_booking) AS jml_booking, SUM(d.subtotal) AS total_omset
    FROM booking_details d
    JOIN bookings b ON d.id_booking = b.id_booking
    JOIN employees e ON d.id_employee = e.id_employee
    WHERE b.tgl_booking = '$tgl_pilih' AND b.status_pembayaran = 'lunas'
    GROUP BY d.id_employee
    ORDER BY total_omset DESC
");
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Pendapatan — Amoy Salon</title>
<link rel="icon" type="image/png" href="../asset/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* ═══════════════════════════════════════
   DESIGN TOKENS — LIGHT
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
    --table-head:   #f3f4f9;
}
/* ═══════════════════════════════════════
   DESIGN TOKENS — DARK
═══════════════════════════════════════ */
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
    --table-head:   #1a1d2e;
}
/* ═══════════════════════════════════════
   ACCENT PALETTE
═══════════════════════════════════════ */
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
    --radius:        10px;
    --radius-lg:     14px;
    --font:          'Nunito', sans-serif;
    --mono:          'Fira Code', monospace;
    --trans:         all .18s ease;
}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    font-family:var(--font);background:var(--bg);color:var(--text);
    min-height:100vh;font-size:14px;line-height:1.6;
    -webkit-font-smoothing:antialiased;
    transition:background .25s ease,color .25s ease;
}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-input);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:var(--accent)}

/* ── NAVBAR ── */
.navbar{
    background:var(--nav-bg)!important;padding:.5rem 1.25rem;
    box-shadow:0 2px 12px rgba(0,0,0,.15);
    position:sticky;top:0;z-index:1040;transition:background .25s;
}
.navbar-brand{
    font-weight:900;font-size:1.05rem;letter-spacing:-.3px;
    color:var(--nav-active)!important;display:flex;align-items:center;gap:8px;
}
.brand-icon{
    width:32px;height:32px;
    background:linear-gradient(135deg,var(--accent),var(--pink));
    border-radius:8px;display:flex;align-items:center;justify-content:center;
    font-size:15px;flex-shrink:0;
}
.nav-link{
    color:var(--nav-text)!important;font-size:.85rem;font-weight:600;
    padding:.4rem .75rem!important;border-radius:7px;transition:var(--trans);
    display:flex;align-items:center;gap:5px;
}
.nav-link:hover,.nav-link.active-page{
    background:rgba(255,255,255,.08);color:var(--nav-active)!important;
}
.nav-link.c-warning{color:#f39c12!important}
.nav-link.c-info{color:#74b9ff!important}
.nav-link.c-danger{color:#ff6b6b!important;font-weight:700}
.nav-link.c-danger:hover{background:rgba(255,107,107,.12)!important}
.theme-toggle{
    width:36px;height:36px;border-radius:8px;
    border:1px solid rgba(255,255,255,.15);
    background:rgba(255,255,255,.06);color:#fff;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    font-size:16px;transition:var(--trans);
}
.theme-toggle:hover{background:rgba(255,255,255,.15)}

/* ── PAGE LAYOUT ── */
.page-container{max-width:1400px;margin:0 auto;padding:28px 20px}
.page-head{
    display:flex;align-items:flex-end;justify-content:space-between;
    flex-wrap:wrap;gap:12px;margin-bottom:24px;
}
.page-title{font-size:1.4rem;font-weight:900;letter-spacing:-.4px;margin:0}
.page-subtitle{font-size:.82rem;color:var(--text-muted);margin:2px 0 0}

/* ── CARDS ── */
.card{
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:var(--radius-lg);box-shadow:var(--shadow-card);
    transition:var(--trans);
}
.card-header-area{
    padding:18px 22px 14px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:10px;
}
.card-title{font-size:.92rem;font-weight:800;color:var(--text);margin:0}
.card-body-area{padding:20px 22px}

/* ── STAT CARDS ── */
.stat-grid{
    display:grid;grid-template-columns:repeat(4,1fr);
    gap:16px;margin-bottom:20px;
}
@media(max-width:1100px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:576px){.stat-grid{grid-template-columns:1fr 1fr}}
.stat-card{
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:18px 20px 16px;
    position:relative;overflow:hidden;box-shadow:var(--shadow-card);
    transition:var(--trans);
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.stat-card::after{
    content:'';position:absolute;top:-30px;right:-30px;
    width:90px;height:90px;border-radius:50%;
    background:var(--c-soft);opacity:.6;
}
.stat-icon{
    width:40px;height:40px;border-radius:10px;
    background:var(--c-soft);color:var(--c);
    display:flex;align-items:center;justify-content:center;
    font-size:18px;margin-bottom:12px;position:relative;z-index:1;
}
.stat-label{
    font-size:.72rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.6px;color:var(--text-muted);margin-bottom:2px;
}
.stat-value{
    font-size:1.3rem;font-weight:900;color:var(--text);
    font-family:var(--mono);letter-spacing:-.5px;line-height:1.2;
}
.stat-value.sm{font-size:.95rem}
.stat-note{font-size:.73rem;color:var(--text-muted);margin-top:3px}
.stat-badge{
    display:inline-flex;align-items:center;gap:4px;
    padding:2px 8px;border-radius:20px;font-size:.72rem;
    font-weight:700;margin-top:4px;
    background:var(--c-soft);color:var(--c);
}

/* ── FILTER BAR ── */
.filter-card{
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:16px 20px;
    margin-bottom:18px;box-shadow:var(--shadow-card);
}
.filter-label{
    font-size:.72rem;font-weight:800;text-transform:uppercase;
    letter-spacing:.6px;color:var(--text-muted);margin-bottom:12px;
    display:flex;align-items:center;gap:6px;
}

/* ── FORM CONTROLS ── */
.form-control,.form-select{
    background:var(--bg-input)!important;border:1px solid var(--border-input)!important;
    color:var(--text)!important;border-radius:8px!important;
    font-size:.845rem!important;font-family:var(--font)!important;
    padding:8px 12px!important;transition:var(--trans)!important;
}
.form-control:focus,.form-select:focus{
    border-color:var(--accent)!important;
    box-shadow:0 0 0 3px var(--accent-bg)!important;outline:none!important;
}
.form-control::placeholder{color:var(--text-muted)!important}
.form-label{font-size:.78rem;font-weight:700;color:var(--text-sub);margin-bottom:5px;display:block}
.input-group-text{
    background:var(--bg-hover)!important;border:1px solid var(--border-input)!important;
    color:var(--text-sub)!important;font-size:.845rem!important;
}

/* ── BUTTONS ── */
.btn{
    font-family:var(--font)!important;font-weight:700!important;
    font-size:.82rem!important;border-radius:8px!important;
    transition:var(--trans)!important;
    display:inline-flex!important;align-items:center!important;gap:5px!important;
}
.btn-primary{
    background:var(--accent)!important;border:none!important;
    color:#fff!important;padding:8px 18px!important;
}
.btn-primary:hover{background:#5a4bd1!important;transform:translateY(-1px);box-shadow:0 4px 14px rgba(108,92,231,.35)!important}
.btn-outline-secondary{
    background:transparent!important;border:1px solid var(--border-input)!important;
    color:var(--text-sub)!important;padding:7px 14px!important;
}
.btn-outline-secondary:hover{background:var(--bg-hover)!important;color:var(--text)!important}
.btn-success-custom{
    background:var(--green)!important;border:none!important;
    color:#fff!important;padding:7px 16px!important;
}
.btn-success-custom:hover{background:#00a381!important;transform:translateY(-1px)}
.btn-sm{padding:5px 12px!important;font-size:.78rem!important}

/* ── TABLE ── */
.table-wrap{
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-card);
}
.table{margin:0;border-collapse:collapse}
.table>thead>tr>th{
    background:var(--table-head)!important;color:var(--text-sub)!important;
    font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;
    padding:12px 16px;border:none;border-bottom:2px solid var(--border);
    white-space:nowrap;
}
.table>tbody>tr>td{
    padding:13px 16px;border:none;
    border-bottom:1px solid var(--border);
    color:var(--text);font-size:.86rem;vertical-align:middle;
    transition:background .12s;
}
.table>tbody>tr:last-child>td{border-bottom:none}
.table>tbody>tr:hover>td{background:var(--bg-hover)}
.table>tfoot>tr>td,.table>tfoot>tr>th{
    padding:13px 16px;border-top:2px solid var(--border);
    font-size:.86rem;background:var(--table-head);
}
.table-striped>tbody>tr:nth-of-type(even)>td{background:var(--bg-stripe)}

/* ── BADGES ── */
.badge-s{
    display:inline-flex;align-items:center;gap:4px;
    padding:3px 10px;border-radius:20px;font-size:.72rem;
    font-weight:800;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;
}
.bs-lunas  {background:var(--green-bg);  color:var(--green);  border:1px solid var(--green-border)}
.bs-dp     {background:var(--yellow-bg); color:var(--yellow); border:1px solid var(--yellow-border)}
.bs-pending{background:var(--blue-bg);   color:var(--blue);   border:1px solid var(--blue-border)}
.bs-batal  {background:var(--red-bg);    color:var(--red);    border:1px solid var(--red-border)}
.badge-svc{
    display:inline-block;padding:2px 8px;border-radius:5px;font-size:.72rem;
    font-weight:700;background:var(--accent-bg);color:var(--accent);
    border:1px solid var(--accent-border);margin:1px;
}

/* ── PROGRESS BAR ── */
.progress-custom{
    height:6px;border-radius:3px;background:var(--border);overflow:hidden;margin-top:4px;
}
.progress-custom .fill{
    height:100%;border-radius:3px;
    background:linear-gradient(90deg,var(--accent),var(--accent-2));
    transition:width .6s ease;
}

/* ── SUMMARY HERO ── */
.hero-card{
    background:linear-gradient(135deg,#6c5ce7 0%,#a29bfe 50%,#e84393 100%);
    border-radius:var(--radius-lg);padding:28px 32px;
    color:#fff;position:relative;overflow:hidden;
    box-shadow:0 8px 32px rgba(108,92,231,.3);margin-bottom:20px;
}
.hero-card::before{
    content:'';position:absolute;top:-60px;right:-60px;
    width:200px;height:200px;border-radius:50%;
    background:rgba(255,255,255,.08);
}
.hero-card::after{
    content:'';position:absolute;bottom:-40px;right:80px;
    width:120px;height:120px;border-radius:50%;
    background:rgba(255,255,255,.05);
}
.hero-date{font-size:.78rem;font-weight:700;opacity:.8;margin-bottom:4px;letter-spacing:.5px;text-transform:uppercase}
.hero-amount{font-size:2.8rem;font-weight:900;font-family:var(--mono);line-height:1.1;position:relative;z-index:1}
.hero-sub{font-size:.85rem;opacity:.85;margin-top:6px}
.hero-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;position:relative;z-index:1}
.hero-chip{
    background:rgba(255,255,255,.18);backdrop-filter:blur(4px);
    border:1px solid rgba(255,255,255,.25);border-radius:8px;
    padding:6px 14px;font-size:.78rem;font-weight:700;
    display:flex;align-items:center;gap:6px;
}

/* ── KARYAWAN RANK ── */
.emp-rank-item{
    display:flex;align-items:center;gap:12px;
    padding:10px 0;border-bottom:1px solid var(--border);
}
.emp-rank-item:last-child{border-bottom:none}
.emp-avatar{
    width:36px;height:36px;border-radius:10px;
    background:linear-gradient(135deg,var(--accent),var(--accent-2));
    color:#fff;display:flex;align-items:center;justify-content:center;
    font-size:.8rem;font-weight:900;flex-shrink:0;
}
.emp-name{font-size:.86rem;font-weight:700;color:var(--text)}
.emp-meta{font-size:.75rem;color:var(--text-muted)}
.emp-amount{font-family:var(--mono);font-weight:800;font-size:.88rem;color:var(--green);margin-left:auto;flex-shrink:0}

/* ── TOP LAYANAN ── */
.top-svc-item{
    display:flex;align-items:center;justify-content:space-between;
    padding:9px 0;border-bottom:1px solid var(--border);gap:10px;
}
.top-svc-item:last-child{border-bottom:none}
.top-svc-rank{
    width:24px;height:24px;border-radius:6px;
    background:var(--accent-bg);color:var(--accent);
    display:flex;align-items:center;justify-content:center;
    font-size:.72rem;font-weight:900;flex-shrink:0;
}
.top-svc-name{font-size:.84rem;font-weight:700;color:var(--text);flex:1}
.top-svc-right{text-align:right;flex-shrink:0}
.top-svc-amount{font-family:var(--mono);font-size:.82rem;font-weight:800;color:var(--text)}
.top-svc-count{font-size:.72rem;color:var(--text-muted)}

/* ── PRINT ── */
@media print{
    .navbar,.filter-card,.no-print{display:none!important}
    body{background:#fff!important;color:#000!important}
    .hero-card{background:#6c5ce7!important;-webkit-print-color-adjust:exact}
    .stat-card,.table-wrap,.card{box-shadow:none!important;border:1px solid #ddd!important}
    .page-container{padding:0!important}
}

/* ── RESPONSIVE ── */
@media(max-width:768px){
    .page-container{padding:16px 12px}
    .hero-amount{font-size:2rem}
    .stat-grid{gap:10px}
}

/* ── ANIMATIONS ── */
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .3s ease forwards}
.fade-up-1{animation-delay:.05s;opacity:0}
.fade-up-2{animation-delay:.1s;opacity:0}
.fade-up-3{animation-delay:.15s;opacity:0}
</style>
</head>
<body>

<!-- ═══════════════ NAVBAR ═══════════════ -->
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container-fluid px-3">
    <a class="navbar-brand" href="admin.php">
      <div class="brand-icon">✨</div>
      Amoy Salon
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
        <?php if ($user_level <= 2): ?>
        <li class="nav-item"><a class="nav-link" href="admin.php"><i class="bi bi-calendar-check"></i> Daftar Booking</a></li>
        <?php endif; ?>
        <?php if ($user_level <= 1): ?>
        <li class="nav-item"><a class="nav-link c-warning" href="master_data.php"><i class="bi bi-gear"></i> Master Data</a></li>
        <?php endif; ?>
        <?php if ($user_level <= 2): ?>
        <li class="nav-item"><a class="nav-link c-info active-page" href="laporan.php"><i class="bi bi-bar-chart"></i> Pendapatan</a></li>
        <li class="nav-item"><a class="nav-link c-warning" href="komisi.php"><i class="bi bi-cash-coin"></i> Komisi</a></li>
        <li class="nav-item"><a class="nav-link c-info" href="laporan_pembayaran.php"><i class="bi bi-book"></i> Pembukuan</a></li>
        <?php endif; ?>
        <?php if ($user_level <= 1): ?>
        <li class="nav-item"><a class="nav-link c-warning" href="../class/user_manage.php"><i class="bi bi-people"></i> Kelola Akun</a></li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link" href="../index.php" target="_blank" style="opacity:.7;font-size:.8rem;border:1px solid rgba(255,255,255,.2);border-radius:7px;padding:5px 10px!important">
            <i class="bi bi-box-arrow-up-right"></i> Booking Online
          </a>
        </li>
        <li class="nav-item">
          <button class="theme-toggle" id="themeBtn" title="Toggle Dark Mode">
            <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
          </button>
        </li>
        <li class="nav-item">
          <a class="nav-link c-danger" href="../class/logout.php" onclick="return confirm('Yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right"></i> Keluar
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ═══════════════ MAIN CONTENT ═══════════════ -->
<div class="page-container">

  <!-- Page Header -->
  <div class="page-head">
    <div>
      <h1 class="page-title"><i class="bi bi-bar-chart-line me-2" style="color:var(--accent)"></i>Laporan Pendapatan Harian</h1>
      <p class="page-subtitle">Rekap transaksi & analisis pendapatan salon</p>
    </div>
    <button class="btn btn-success-custom no-print" onclick="window.print()">
      <i class="bi bi-printer"></i> Cetak Laporan
    </button>
  </div>

  <!-- Filter Bar -->
  <div class="filter-card no-print fade-up">
    <div class="filter-label"><i class="bi bi-funnel"></i> Filter Tanggal</div>
    <form action="" method="GET" class="d-flex flex-wrap gap-2 align-items-end">
      <div>
        <label class="form-label">Pilih Tanggal</label>
        <input type="date" name="tgl" class="form-control" value="<?= $tgl_pilih ?>" style="width:180px">
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Cek Laporan</button>
        <a href="laporan.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Hari Ini</a>
      </div>
      <div class="ms-auto d-flex gap-2">
        <?php
        $prev_day = date('Y-m-d', strtotime($tgl_pilih . ' -1 day'));
        $next_day = date('Y-m-d', strtotime($tgl_pilih . ' +1 day'));
        ?>
        <a href="?tgl=<?= $prev_day ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i> Kemarin</a>
        <?php if ($next_day <= $today): ?>
        <a href="?tgl=<?= $next_day ?>" class="btn btn-outline-secondary btn-sm">Besok <i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Hero Card -->
  <div class="hero-card fade-up fade-up-1">
    <div class="hero-date"><i class="bi bi-calendar3 me-1"></i><?= date('l, d F Y', strtotime($tgl_pilih)) ?></div>
    <div class="hero-amount">Rp <?= number_format($res_total['grand_total'] ?? 0, 0, ',', '.') ?></div>
    <div class="hero-sub">Total pendapatan lunas • <?= $stat['jml_lunas'] ?? 0 ?> transaksi terbayar</div>
    <div class="hero-chips">
      <div class="hero-chip"><i class="bi bi-cash"></i> Cash: Rp <?= number_format($res_total['total_cash'] ?? 0, 0, ',', '.') ?></div>
      <div class="hero-chip"><i class="bi bi-phone"></i> Transfer: Rp <?= number_format($res_total['total_transfer'] ?? 0, 0, ',', '.') ?></div>
      <div class="hero-chip"><i class="bi bi-clock"></i> DP Masuk: Rp <?= number_format($stat['uang_dp'] ?? 0, 0, ',', '.') ?></div>
      <div class="hero-chip"><i class="bi bi-exclamation-triangle"></i> Piutang: Rp <?= number_format($stat['piutang'] ?? 0, 0, ',', '.') ?></div>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stat-grid fade-up fade-up-2">
    <div class="stat-card" style="--c:var(--green);--c-soft:var(--green-bg)">
      <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div class="stat-label">Transaksi Lunas</div>
      <div class="stat-value"><?= $stat['jml_lunas'] ?? 0 ?></div>
      <div class="stat-note">booking terbayar penuh</div>
    </div>
    <div class="stat-card" style="--c:var(--yellow);--c-soft:var(--yellow-bg)">
      <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
      <div class="stat-label">Bayar DP</div>
      <div class="stat-value"><?= $stat['jml_dp'] ?? 0 ?></div>
      <div class="stat-note">Rp <?= number_format($stat['uang_dp'] ?? 0, 0, ',', '.') ?> terkumpul</div>
    </div>
    <div class="stat-card" style="--c:var(--blue);--c-soft:var(--blue-bg)">
      <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-label">Masih Pending</div>
      <div class="stat-value"><?= $stat['jml_pending'] ?? 0 ?></div>
      <div class="stat-note">menunggu pembayaran</div>
    </div>
    <div class="stat-card" style="--c:var(--accent);--c-soft:var(--accent-bg)">
      <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
      <div class="stat-label">Total Booking</div>
      <div class="stat-value"><?= $stat['total_booking'] ?? 0 ?></div>
      <div class="stat-note">semua booking aktif</div>
    </div>
  </div>

  <!-- Main Content Grid -->
  <div class="row g-4 fade-up fade-up-3">

    <!-- Tabel Rincian Transaksi -->
    <div class="col-xl-8">
      <div class="table-wrap">
        <div class="card-header-area">
          <div>
            <div class="card-title"><i class="bi bi-receipt me-2" style="color:var(--accent)"></i>Rincian Transaksi</div>
            <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px">
              Transaksi dengan status <span class="badge-s bs-lunas">Lunas</span> pada <?= date('d/m/Y', strtotime($tgl_pilih)) ?>
            </div>
          </div>
          <div style="font-size:.78rem;color:var(--text-muted)">
            <?= mysqli_num_rows($q_list) ?> transaksi
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Layanan</th>
                <th>Petugas</th>
                <th style="text-align:right">Total</th>
                <th style="text-align:center">Bayar</th>
              </tr>
            </thead>
            <tbody>
              <?php
              mysqli_data_seek($q_list, 0);
              if (mysqli_num_rows($q_list) > 0):
                while ($l = mysqli_fetch_array($q_list)):
                  $layanan_arr  = !empty($l['daftar_layanan'])  ? explode('||', $l['daftar_layanan'])  : [];
                  $petugas_arr  = !empty($l['daftar_karyawan']) ? explode('||', $l['daftar_karyawan']) : [];
              ?>
              <tr>
                <td>
                  <span style="font-family:var(--mono);font-weight:800;color:var(--accent);font-size:.82rem">#<?= $l['id_booking'] ?></span>
                  <?php if ($l['jam_booking']): ?>
                  <div style="font-size:.7rem;color:var(--text-muted)"><?= date('H:i', strtotime($l['jam_booking'])) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-weight:700"><?= htmlspecialchars($l['nama_customer']) ?></div>
                  <?php if ($l['whatsapp_customer']): ?>
                  <div style="font-size:.73rem;color:var(--text-muted)"><?= htmlspecialchars($l['whatsapp_customer']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display:flex;flex-wrap:wrap;gap:2px">
                    <?php foreach ($layanan_arr as $svc): ?>
                    <span class="badge-svc"><?= htmlspecialchars(trim($svc)) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($layanan_arr)): ?>
                    <span style="color:var(--text-muted);font-size:.78rem">—</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php if (!empty($petugas_arr)): ?>
                  <?php foreach ($petugas_arr as $p): ?>
                  <div style="font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:4px">
                    <span style="width:6px;height:6px;border-radius:50%;background:var(--green);display:inline-block;flex-shrink:0"></span>
                    <?= htmlspecialchars(trim($p)) ?>
                  </div>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <span style="color:var(--text-muted);font-size:.78rem;font-style:italic">Belum ditentukan</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right">
                  <span style="font-family:var(--mono);font-weight:800;color:var(--text)">
                    Rp <?= number_format($l['total_biaya'], 0, ',', '.') ?>
                  </span>
                </td>
                <td style="text-align:center">
                  <?php
                  $cash = $l['bayar_cash'] > 0 ? '<span style="font-size:.7rem;color:var(--green)"><i class="bi bi-cash"></i></span>' : '';
                  $tf   = $l['bayar_transfer'] > 0 ? '<span style="font-size:.7rem;color:var(--blue)"><i class="bi bi-phone"></i></span>' : '';
                  echo $cash . ' ' . $tf;
                  if (!$cash && !$tf) echo '<span style="color:var(--text-muted)">—</span>';
                  ?>
                </td>
              </tr>
              <?php endwhile; else: ?>
              <tr>
                <td colspan="6" class="text-center py-5">
                  <div style="color:var(--text-muted)">
                    <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4"></i>
                    Tidak ada transaksi lunas pada tanggal ini
                  </div>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="4" style="text-align:right;font-weight:800;font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub)">
                  TOTAL PENDAPATAN LUNAS
                </td>
                <td style="text-align:right">
                  <span style="font-family:var(--mono);font-weight:900;font-size:1rem;color:var(--green)">
                    Rp <?= number_format($res_total['grand_total'] ?? 0, 0, ',', '.') ?>
                  </span>
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Sidebar: Kinerja & Top Layanan -->
    <div class="col-xl-4">

      <!-- Top Layanan -->
      <div class="card mb-4">
        <div class="card-header-area">
          <div class="card-title"><i class="bi bi-trophy me-2" style="color:var(--yellow)"></i>Top Layanan</div>
        </div>
        <div class="card-body-area">
          <?php
          $top_rows = [];
          while ($t = mysqli_fetch_assoc($q_top)) $top_rows[] = $t;
          $max_top = !empty($top_rows) ? $top_rows[0]['total'] : 1;

          if (!empty($top_rows)):
            foreach ($top_rows as $i => $t):
          ?>
          <div class="top-svc-item">
            <div class="top-svc-rank"><?= $i+1 ?></div>
            <div class="top-svc-name"><?= htmlspecialchars($t['nama_layanan']) ?></div>
            <div class="top-svc-right">
              <div class="top-svc-amount">Rp <?= number_format($t['total'], 0, ',', '.') ?></div>
              <div class="top-svc-count"><?= $t['jml'] ?>× dikerjakan</div>
            </div>
          </div>
          <div class="progress-custom" style="margin-bottom:4px">
            <div class="fill" style="width:<?= round($t['total']/$max_top*100) ?>%"></div>
          </div>
          <?php endforeach; else: ?>
          <div style="text-align:center;padding:20px 0;color:var(--text-muted)">
            <i class="bi bi-inbox" style="font-size:1.5rem;opacity:.4;display:block;margin-bottom:6px"></i>
            Belum ada data
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Kinerja Karyawan -->
      <div class="card">
        <div class="card-header-area">
          <div class="card-title"><i class="bi bi-people me-2" style="color:var(--accent)"></i>Kinerja Karyawan</div>
        </div>
        <div class="card-body-area">
          <?php
          $emp_rows = [];
          while ($e = mysqli_fetch_assoc($q_emp)) $emp_rows[] = $e;

          if (!empty($emp_rows)):
            foreach ($emp_rows as $e):
              $inisial = strtoupper(substr($e['nama_karyawan'], 0, 2));
          ?>
          <div class="emp-rank-item">
            <div class="emp-avatar"><?= $inisial ?></div>
            <div style="flex:1;min-width:0">
              <div class="emp-name"><?= htmlspecialchars($e['nama_karyawan']) ?></div>
              <div class="emp-meta"><?= $e['jml_booking'] ?> booking dikerjakan</div>
            </div>
            <div class="emp-amount">Rp <?= number_format($e['total_omset'], 0, ',', '.') ?></div>
          </div>
          <?php endforeach; else: ?>
          <div style="text-align:center;padding:20px 0;color:var(--text-muted)">
            <i class="bi bi-person-x" style="font-size:1.5rem;opacity:.4;display:block;margin-bottom:6px"></i>
            Belum ada data karyawan
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div><!-- /row -->

  <!-- Action Footer -->
  <div class="d-flex justify-content-between align-items-center mt-4 no-print" style="flex-wrap:wrap;gap:10px">
    <a href="admin.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
    <button onclick="window.print()" class="btn btn-success-custom"><i class="bi bi-printer"></i> Cetak Laporan</button>
  </div>

</div><!-- /page-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Dark Mode Toggle
const root = document.documentElement;
const btn  = document.getElementById('themeBtn');
const icon = document.getElementById('themeIcon');
const saved = localStorage.getItem('amoy-theme') || 'light';
root.setAttribute('data-theme', saved);
icon.className = saved === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';

btn.addEventListener('click', () => {
  const cur  = root.getAttribute('data-theme');
  const next = cur === 'light' ? 'dark' : 'light';
  root.setAttribute('data-theme', next);
  icon.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
  localStorage.setItem('amoy-theme', next);
});

// Animate progress bars on load
window.addEventListener('load', () => {
  document.querySelectorAll('.progress-custom .fill').forEach(el => {
    const w = el.style.width;
    el.style.width = '0';
    setTimeout(() => el.style.width = w, 200);
  });
});
</script>
</body>
</html>