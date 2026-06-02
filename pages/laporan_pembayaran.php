<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
$user_level = $_SESSION['level'];
$user_nama  = $_SESSION['nama'] ?? $_SESSION['username'];
include '../class/koneksi.php';

// ─── FILTER TANGGAL ─────────────────────────────────────────────────────
$tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-t');
$tgl_awal_esc  = mysqli_real_escape_string($conn, $tgl_awal);
$tgl_akhir_esc = mysqli_real_escape_string($conn, $tgl_akhir);

// Periode sebelumnya (untuk perbandingan growth)
$days_diff  = (strtotime($tgl_akhir) - strtotime($tgl_awal)) / 86400 + 1;
$prev_awal  = date('Y-m-d', strtotime($tgl_awal) - $days_diff * 86400);
$prev_akhir = date('Y-m-d', strtotime($tgl_awal) - 86400);

$cond_current = "tgl_booking BETWEEN '$tgl_awal_esc' AND '$tgl_akhir_esc' AND status_pembayaran != 'batal'";
$cond_prev    = "tgl_booking BETWEEN '$prev_awal' AND '$prev_akhir' AND status_pembayaran != 'batal'";

// ─── KPI SUMMARY ────────────────────────────────────────────────────────
$r = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        COALESCE(SUM(bayar_cash),0)                   AS total_cash,
        COALESCE(SUM(bayar_transfer),0)               AS total_transfer,
        COALESCE(SUM(bayar_cash+bayar_transfer),0)    AS total_omzet,
        COUNT(*)                                       AS total_booking,
        COUNT(DISTINCT nama_customer)                  AS total_customer,
        COALESCE(AVG(bayar_cash+bayar_transfer),0)    AS avg_order,
        COALESCE(SUM(CASE WHEN status_pembayaran='lunas' THEN bayar_cash+bayar_transfer ELSE 0 END),0) AS total_lunas,
        COALESCE(SUM(CASE WHEN status_pembayaran IN('dp','pending') THEN total_biaya-(bayar_cash+bayar_transfer) ELSE 0 END),0) AS total_piutang
     FROM bookings WHERE $cond_current"));

$r_prev = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(bayar_cash+bayar_transfer),0) AS total_omzet,
            COUNT(*) AS total_booking
     FROM bookings WHERE $cond_prev"));

$growth_omzet   = $r_prev['total_omzet']   > 0 ? round(($r['total_omzet']   - $r_prev['total_omzet'])   / $r_prev['total_omzet']   * 100, 1) : 0;
$growth_booking = $r_prev['total_booking'] > 0 ? round(($r['total_booking'] - $r_prev['total_booking']) / $r_prev['total_booking'] * 100, 1) : 0;

$total_cash     = (float)$r['total_cash'];
$total_transfer = (float)$r['total_transfer'];
$total_omzet    = (float)$r['total_omzet'];
$total_booking  = (int)$r['total_booking'];
$total_customer = (int)$r['total_customer'];
$avg_order      = (float)$r['avg_order'];
$total_lunas    = (float)$r['total_lunas'];
$total_piutang  = (float)$r['total_piutang'];

$pct_cash     = $total_omzet > 0 ? round($total_cash / $total_omzet * 100, 1) : 0;
$pct_transfer = $total_omzet > 0 ? round($total_transfer / $total_omzet * 100, 1) : 0;

// ─── STATUS BREAKDOWN ────────────────────────────────────────────────────
$q_status = mysqli_query($conn,
    "SELECT status_pembayaran, COUNT(*) as cnt, COALESCE(SUM(bayar_cash+bayar_transfer),0) as total
     FROM bookings WHERE tgl_booking BETWEEN '$tgl_awal_esc' AND '$tgl_akhir_esc'
     GROUP BY status_pembayaran");
$status_breakdown = [];
while ($row = mysqli_fetch_assoc($q_status)) $status_breakdown[$row['status_pembayaran']] = $row;

// ─── REVENUE TREND (harian) ──────────────────────────────────────────────
$q_trend = mysqli_query($conn,
    "SELECT DATE_FORMAT(tgl_booking,'%d %b') AS tgl_label,
            tgl_booking,
            COALESCE(SUM(bayar_cash),0)     AS cash,
            COALESCE(SUM(bayar_transfer),0) AS transfer,
            COALESCE(SUM(bayar_cash+bayar_transfer),0) AS total
     FROM bookings WHERE $cond_current
     GROUP BY tgl_booking ORDER BY tgl_booking ASC");
$trend_labels = $trend_cash = $trend_transfer = $trend_total = [];
while ($rw = mysqli_fetch_assoc($q_trend)) {
    $trend_labels[]   = $rw['tgl_label'];
    $trend_cash[]     = (float)$rw['cash'];
    $trend_transfer[] = (float)$rw['transfer'];
    $trend_total[]    = (float)$rw['total'];
}

// ─── EMPLOYEE PERFORMANCE ─────────────────────────────────────────────────
$q_emp = mysqli_query($conn,
    "SELECT e.nama_karyawan,
            COUNT(DISTINCT bd.id_booking)   AS total_booking,
            COALESCE(SUM(bd.subtotal),0)    AS total_revenue,
            COALESCE(SUM(bk.nominal_komisi),0) AS total_komisi
     FROM employees e
     LEFT JOIN booking_details bd ON e.id_employee = bd.id_employee
     LEFT JOIN bookings b ON bd.id_booking = b.id_booking AND $cond_current
     LEFT JOIN booking_komisi bk ON bd.id_detail = bk.id_detail AND bk.id_employee = e.id_employee
     WHERE e.status = 'active'
     GROUP BY e.id_employee ORDER BY total_revenue DESC");
$emp_rows = [];
while ($rw = mysqli_fetch_assoc($q_emp)) $emp_rows[] = $rw;
$emp_labels  = array_column($emp_rows,'nama_karyawan');
$emp_revenue = array_map('floatval', array_column($emp_rows,'total_revenue'));
$emp_booking = array_map('intval', array_column($emp_rows,'total_booking'));

// ─── DETAIL PEMBAYARAN ────────────────────────────────────────────────────
$sql_detail = "SELECT b.*,
                GROUP_CONCAT(s.nama_layanan ORDER BY s.nama_layanan SEPARATOR ', ') AS layanan_list,
                e.nama_karyawan
               FROM bookings b
               LEFT JOIN booking_details bd ON b.id_booking = bd.id_booking
               LEFT JOIN services s ON bd.id_service = s.id_service
               LEFT JOIN employees e ON b.id_employee = e.id_employee
               WHERE $cond_current
               GROUP BY b.id_booking
               ORDER BY b.tgl_booking DESC";
$q_detail = mysqli_query($conn, $sql_detail);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembukuan — Amoy Salon</title>
<link rel="icon" type="image/png" href="../asset/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

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
  --chart-grid:   rgba(0,0,0,.06);
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
  --chart-grid:   rgba(255,255,255,.06);
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
  --cyan:          #4fd1c5;
  --cyan-bg:       rgba(79,209,197,.1);
  --radius:        10px;
  --radius-lg:     14px;
  --radius-xl:     18px;
  --font:          'Nunito', sans-serif;
  --mono:          'Fira Code', monospace;
  --trans:         all .18s ease;
}

*,*::before,*::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
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
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-input); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--accent); }

/* ═══ NAVBAR ═══ */
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
.nav-link.text-warning { color: #f39c12 !important; }
.nav-link.text-info    { color: #74b9ff !important; }
.btn-booking-online {
  font-size: .8rem; font-weight: 700; padding: .3rem .9rem;
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 7px; color: #fff !important;
  transition: var(--trans);
}
.btn-booking-online:hover { background: rgba(255,255,255,.12); }
.btn-logout { color: #ff6b6b !important; font-weight: 700; }
.btn-logout:hover { background: rgba(255,107,107,.12) !important; }
.theme-toggle {
  width: 36px; height: 36px; border-radius: 8px;
  border: 1px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.06);
  color: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; transition: var(--trans);
}
.theme-toggle:hover { background: rgba(255,255,255,.15); }

/* ═══ PAGE TABS ═══ */
.page-tabs {
  display: flex; gap: 4px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 4px;
  width: fit-content;
}
.page-tab {
  padding: 7px 20px; border-radius: var(--radius);
  font-size: .84rem; font-weight: 700;
  color: var(--text-muted);
  cursor: pointer; transition: var(--trans);
  text-decoration: none; display: flex; align-items: center; gap: 6px;
  white-space: nowrap;
}
.page-tab:hover { color: var(--text); background: var(--bg-hover); }
.page-tab.active {
  background: var(--accent); color: #fff !important;
  box-shadow: 0 2px 8px rgba(108,92,231,.35);
}

/* ═══ LAYOUT ═══ */
.page-container { max-width: 1600px; margin: 0 auto; padding: 28px 20px; }
.page-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  flex-wrap: wrap; gap: 16px; margin-bottom: 28px;
}
.page-title { font-size: 1.4rem; font-weight: 900; letter-spacing: -.4px; margin: 0; }
.page-subtitle { font-size: .82rem; color: var(--text-muted); margin: 2px 0 0; }
.date-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 14px; border-radius: 20px;
  background: var(--bg-card); border: 1px solid var(--border);
  font-size: .78rem; font-weight: 700; color: var(--text-sub);
  font-family: var(--mono); box-shadow: var(--shadow-sm);
}

/* ═══ FILTER CARD ═══ */
.filter-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 18px 20px;
  margin-bottom: 24px; box-shadow: var(--shadow-card);
}
.filter-label {
  font-size: .72rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .6px; color: var(--text-muted); margin-bottom: 4px; display: block;
}
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
.form-control[type="date"]::-webkit-calendar-picker-indicator {
  filter: var(--date-icon-filter, none);
  opacity: .6; cursor: pointer;
}
[data-theme="dark"] .form-control[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
.form-select option { background: var(--bg-card); color: var(--text); }
.input-group-text {
  background: var(--bg-hover) !important;
  border: 1px solid var(--border-input) !important;
  color: var(--text-sub) !important; font-size: .845rem !important;
}

/* ═══ BUTTONS ═══ */
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
.btn-primary:hover { background: #5a4bd1 !important; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(108,92,231,.35) !important; }
.btn-outline-secondary {
  background: transparent !important; border: 1px solid var(--border-input) !important;
  color: var(--text-sub) !important; padding: 7px 14px !important;
}
.btn-outline-secondary:hover { background: var(--bg-hover) !important; color: var(--text) !important; }
.btn-success-soft { background: var(--green-bg) !important; border: 1px solid var(--green-border) !important; color: var(--green) !important; }
.btn-success-soft:hover { background: var(--green) !important; color: #fff !important; }

/* ═══ KPI GRID ═══ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px; margin-bottom: 24px;
}
.kpi-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 18px 20px;
  position: relative; overflow: hidden;
  box-shadow: var(--shadow-card);
  transition: var(--trans);
  border-left: 3px solid var(--kpi-accent, var(--accent));
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.kpi-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 17px; margin-bottom: 12px;
  background: var(--kpi-bg, var(--accent-bg));
  color: var(--kpi-accent, var(--accent));
}
.kpi-label {
  font-size: .7rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .6px; color: var(--text-muted); margin-bottom: 4px;
}
.kpi-value {
  font-size: 1.3rem; font-weight: 900; letter-spacing: -.4px;
  color: var(--text); line-height: 1.15;
}
.kpi-value.mono { font-family: var(--mono); font-size: 1.05rem; }
.kpi-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: .7rem; font-weight: 700; padding: 3px 8px;
  border-radius: 20px; margin-top: 6px;
}
.kpi-badge.up   { background: var(--green-bg);   color: var(--green); }
.kpi-badge.down { background: var(--red-bg);     color: var(--red); }
.kpi-badge.neutral { background: var(--bg-stripe); color: var(--text-muted); }
.kpi-sub { font-size: .73rem; color: var(--text-muted); margin-top: 4px; }

/* ═══ CHART CARD ═══ */
.chart-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 20px 22px;
  height: 100%; display: flex; flex-direction: column;
  box-shadow: var(--shadow-card);
}
.chart-card-title {
  font-size: .9rem; font-weight: 800; color: var(--text);
  display: flex; align-items: center; gap: 8px; margin-bottom: 4px;
}
.chart-card-sub { font-size: .75rem; color: var(--text-muted); margin-bottom: 16px; }
.chart-body { flex: 1; min-height: 0; position: relative; }

/* ═══ DATA TABLE CARD ═══ */
.table-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-xl); overflow: hidden;
  box-shadow: var(--shadow-card); margin-bottom: 28px;
}
.table-card-header {
  padding: 16px 22px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
}
.table-card-title { font-size: .95rem; font-weight: 900; margin: 0; }
.table-scroll { overflow-x: auto; }

/* DataTable specific overrides */
.dataTables_wrapper {
  padding: 0 !important;
  color: var(--text) !important;
}
.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select {
  background: var(--bg-input) !important;
  border: 1px solid var(--border-input) !important;
  color: var(--text) !important;
  border-radius: 7px !important;
  padding: 5px 10px !important;
  font-family: var(--font) !important;
  font-size: .82rem !important;
}
.dataTables_wrapper .dataTables_filter { padding: 12px 16px 0 !important; }
.dataTables_wrapper .dataTables_length { padding: 12px 0 0 16px !important; }
.dataTables_wrapper .dataTables_info  { padding: 12px 16px !important; font-size: .75rem !important; color: var(--text-muted) !important; }
.dataTables_wrapper .dataTables_paginate { padding: 10px 16px !important; }
.dataTables_wrapper .paginate_button {
  background: var(--bg-input) !important; border: 1px solid var(--border-input) !important;
  color: var(--text-sub) !important; border-radius: 6px !important;
  font-size: .78rem !important; font-weight: 700 !important; padding: 4px 10px !important;
  margin: 0 2px !important;
}
.dataTables_wrapper .paginate_button:hover {
  background: var(--accent-bg) !important; border-color: var(--accent-border) !important;
  color: var(--accent) !important;
}
.dataTables_wrapper .paginate_button.current,
.dataTables_wrapper .paginate_button.current:hover {
  background: var(--accent) !important; border-color: var(--accent) !important;
  color: #fff !important;
}
.dataTables_wrapper .paginate_button.disabled,
.dataTables_wrapper .paginate_button.disabled:hover {
  opacity: .3 !important; cursor: default !important;
}

table.salon-table { width: 100% !important; border-collapse: collapse; }
table.salon-table thead th {
  background: var(--bg-stripe) !important;
  border-bottom: 2px solid var(--border) !important;
  border-top: none !important;
  font-size: .72rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .6px; color: var(--text-muted) !important;
  padding: 11px 14px !important; white-space: nowrap;
}
table.salon-table thead th.sorting::after,
table.salon-table thead th.sorting_asc::after,
table.salon-table thead th.sorting_desc::after {
  color: var(--text-muted) !important;
}
table.salon-table tbody tr {
  border-bottom: 1px solid var(--border) !important;
  transition: background .12s;
}
table.salon-table tbody tr:hover { background: var(--bg-stripe) !important; }
table.salon-table tbody td {
  padding: 10px 14px !important; font-size: .83rem;
  color: var(--text) !important; border-top: none !important;
  vertical-align: middle;
}
table.salon-table tfoot tr {
  background: var(--bg-stripe) !important;
  border-top: 2px solid var(--border) !important;
}
table.salon-table tfoot td {
  padding: 11px 14px !important; font-weight: 800; font-size: .83rem;
  color: var(--text) !important;
}

/* ═══ BADGES ═══ */
.badge-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 20px;
  font-size: .73rem; font-weight: 700; white-space: nowrap;
}
.badge-pill::before {
  content: ''; width: 6px; height: 6px; border-radius: 50%;
  background: currentColor; flex-shrink: 0;
}
.bp-lunas   { background: var(--green-bg);  color: var(--green); }
.bp-dp      { background: var(--yellow-bg); color: var(--yellow); }
.bp-pending { background: rgba(148,153,184,.12); color: var(--text-sub); }
.bp-batal   { background: var(--red-bg);    color: var(--red); }

/* ═══ TABLE CONTENT ═══ */
.td-id   { font-family: var(--mono); font-size: .76rem; color: var(--accent); font-weight: 600; }
.td-date { font-size: .73rem; color: var(--text-muted); margin-top: 1px; }
.td-name { font-weight: 800; }
.td-wa   { font-size: .75rem; color: var(--text-muted); margin-top: 1px; }
.td-amt  { font-family: var(--mono); font-weight: 700; font-size: .82rem; }
.td-cash     { color: var(--green); }
.td-transfer { color: var(--cyan); }
.td-total    { color: var(--text); }
.svc-tag {
  display: inline-block; font-size: .72rem; font-weight: 600;
  padding: 2px 8px; border-radius: 20px; white-space: nowrap;
  background: var(--bg-stripe); border: 1px solid var(--border);
  color: var(--text-sub); margin: 1px 2px 1px 0;
}

/* ═══ SECTION HEADER ═══ */
.section-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
}
.section-title {
  font-size: 1.1rem; font-weight: 900; letter-spacing: -.3px;
  display: flex; align-items: center; gap: 8px;
}
.section-badge {
  font-size: .63rem; font-weight: 700; padding: 2px 8px;
  border-radius: 4px; background: var(--accent-bg); color: var(--accent);
  letter-spacing: .5px; text-transform: uppercase;
}

/* ═══ PROGRESS BAR ═══ */
.prog-bar {
  height: 7px; border-radius: 99px;
  background: var(--border); overflow: hidden;
}
.prog-fill {
  height: 100%; border-radius: 99px;
  background: linear-gradient(90deg, var(--accent), var(--cyan));
  transition: width .4s ease;
}

/* ═══ PRINT ═══ */
@media print {
  .no-print  { display: none !important; }
  .page-container { padding: 0; }
  .chart-card, .table-card, .kpi-card { box-shadow: none !important; border: 1px solid #ddd !important; }
  [data-theme] {
    --bg: #fff; --bg-card: #fff; --bg-stripe: #f9f9f9;
    --text: #000; --text-sub: #333; --text-muted: #666;
    --border: #ddd; --chart-grid: rgba(0,0,0,.08);
  }
}

/* ═══ RESPONSIVE ═══ */
@media (max-width: 768px) {
  .page-container { padding: 16px 12px; }
  .kpi-grid { grid-template-columns: 1fr 1fr; }
  .page-head { flex-direction: column; }
}
@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ═══ NAVBAR ═══════════════════════════════════ -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid px-2">
    <a class="navbar-brand" href="admin.php">
      <div class="brand-icon">✨</div>
      Amoy Salon
    </a>
    <div class="d-flex align-items-center gap-2 ms-auto d-lg-none">
      <button class="theme-toggle" onclick="toggleTheme()" id="themeBtn" title="Ganti Tema">🌙</button>
      <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <i class="bi bi-list fs-5"></i>
      </button>
    </div>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
        <?php if ($user_level <= 2): ?>
        <li class="nav-item"><a class="nav-link" href="admin.php"><i class="bi bi-calendar2-check"></i>Booking</a></li>
        <?php endif; ?>
        <?php if ($user_level <= 1): ?>
        <li class="nav-item"><a class="nav-link text-warning" href="master_data.php"><i class="bi bi-gear"></i>Master Data</a></li>
        <?php endif; ?>
        <?php if ($user_level <= 2): ?>
        <li class="nav-item"><a class="nav-link text-info" href="laporan.php"><i class="bi bi-bar-chart-line"></i>Pendapatan</a></li>
        <li class="nav-item"><a class="nav-link" href="komisi.php"><i class="bi bi-cash-coin"></i>Komisi</a></li>
        <li class="nav-item"><a class="nav-link active" href="laporan_pembayaran.php"><i class="bi bi-book-half"></i>Pembukuan</a></li>
        <?php endif; ?>
        <?php if ($user_level <= 1): ?>
        <li class="nav-item"><a class="nav-link text-warning" href="../class/user_manage.php"><i class="bi bi-people"></i>Akun</a></li>
        <?php endif; ?>
        <li class="nav-item">
          <div class="nav-divider d-none d-lg-block" style="width:1px;height:22px;background:rgba(255,255,255,.12);margin:0 4px"></div>
        </li>
        <li class="nav-item"><a href="../index.php" target="_blank" class="nav-link btn-booking-online"><i class="bi bi-globe2"></i>Booking Online</a></li>
        <li class="nav-item d-none d-lg-flex align-items-center ms-2">
          <button class="theme-toggle" onclick="toggleTheme()" id="themeBtnDesktop" title="Ganti Tema">🌙</button>
        </li>
        <li class="nav-item">
          <a class="nav-link btn-logout" href="../class/logout.php" onclick="return confirm('Yakin keluar?')">
            <i class="bi bi-box-arrow-right"></i>Keluar
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ═══ PAGE BODY ════════════════════════════════ -->
<div class="page-container">

  <!-- Page Head -->
  <div class="page-head">
    <div>
      <h1 class="page-title"><i class="bi bi-book-half me-2" style="color:var(--accent)"></i>Pembukuan</h1>
      <p class="page-subtitle">Rekap transaksi & laporan keuangan — Amoy Salon</p>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="page-tabs no-print">
        <a href="laporan_pembayaran.php<?= isset($_GET['tgl_awal']) ? '?tgl_awal='.$tgl_awal.'&tgl_akhir='.$tgl_akhir : '' ?>" class="page-tab active">
          <i class="bi bi-journal-text"></i> Pembukuan
        </a>
        <a href="analitik.php<?= isset($_GET['tgl_awal']) ? '?tgl_awal='.$tgl_awal.'&tgl_akhir='.$tgl_akhir : '' ?>" class="page-tab">
          <i class="bi bi-graph-up"></i> Analitik Bisnis
        </a>
      </div>
      <div class="d-flex gap-2 no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-printer"></i> Cetak
        </button>
        <button onclick="exportCSV()" class="btn btn-success-soft btn-sm">
          <i class="bi bi-file-earmark-excel"></i> Export CSV
        </button>
      </div>
    </div>
  </div>

  <!-- Filter -->
  <div class="filter-card no-print">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-lg-4 col-sm-6">
        <label class="filter-label"><i class="bi bi-calendar3 me-1"></i>Dari Tanggal</label>
        <input type="date" name="tgl_awal" class="form-control" value="<?= $tgl_awal ?>">
      </div>
      <div class="col-lg-4 col-sm-6">
        <label class="filter-label"><i class="bi bi-calendar3 me-1"></i>Sampai Tanggal</label>
        <input type="date" name="tgl_akhir" class="form-control" value="<?= $tgl_akhir ?>">
      </div>
      <div class="col-lg-4">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bi bi-funnel-fill"></i> Tampilkan Laporan
          </button>
          <a href="laporan_pembayaran.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg"></i>
          </a>
        </div>
      </div>
      <div class="col-12">
        <div style="font-size:.75rem;color:var(--text-muted)">
          <i class="bi bi-info-circle me-1"></i>
          Periode: <strong style="color:var(--text-sub)"><?= date('d M Y', strtotime($tgl_awal)) ?></strong>
          s/d <strong style="color:var(--text-sub)"><?= date('d M Y', strtotime($tgl_akhir)) ?></strong>
          (<?= $days_diff ?> hari) — Dibandingkan dengan periode sebelumnya
        </div>
      </div>
    </form>
  </div>

  <!-- ─── KPI CARDS ─────────────────────────────── -->
  <div class="kpi-grid">
    <div class="kpi-card" style="--kpi-accent:var(--accent);--kpi-bg:var(--accent-bg)">
      <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="kpi-label">Total Omzet</div>
      <div class="kpi-value mono">Rp <?= number_format($total_omzet, 0, ',', '.') ?></div>
      <div class="kpi-badge <?= $growth_omzet >= 0 ? 'up' : 'down' ?>">
        <i class="bi bi-arrow-<?= $growth_omzet >= 0 ? 'up' : 'down' ?>"></i>
        <?= number_format(abs($growth_omzet), 1) ?>% vs periode lalu
      </div>
    </div>
    <div class="kpi-card" style="--kpi-accent:var(--green);--kpi-bg:var(--green-bg)">
      <div class="kpi-icon"><i class="bi bi-check-circle"></i></div>
      <div class="kpi-label">Total Lunas</div>
      <div class="kpi-value mono">Rp <?= number_format($total_lunas, 0, ',', '.') ?></div>
      <div class="kpi-sub"><?= $total_omzet > 0 ? round($total_lunas/$total_omzet*100,1) : 0 ?>% dari total omzet</div>
    </div>
    <div class="kpi-card" style="--kpi-accent:var(--red);--kpi-bg:var(--red-bg)">
      <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
      <div class="kpi-label">Piutang</div>
      <div class="kpi-value mono">Rp <?= number_format($total_piutang, 0, ',', '.') ?></div>
      <div class="kpi-sub">Belum terbayar penuh</div>
    </div>
    <div class="kpi-card" style="--kpi-accent:var(--green);--kpi-bg:var(--green-bg)">
      <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
      <div class="kpi-label">Tunai (Cash)</div>
      <div class="kpi-value mono">Rp <?= number_format($total_cash, 0, ',', '.') ?></div>
      <div class="kpi-sub"><?= $pct_cash ?>% dari total</div>
    </div>
    <div class="kpi-card" style="--kpi-accent:var(--cyan);--kpi-bg:var(--cyan-bg)">
      <div class="kpi-icon"><i class="bi bi-credit-card"></i></div>
      <div class="kpi-label">Transfer Bank</div>
      <div class="kpi-value mono">Rp <?= number_format($total_transfer, 0, ',', '.') ?></div>
      <div class="kpi-sub"><?= $pct_transfer ?>% dari total</div>
    </div>
    <div class="kpi-card" style="--kpi-accent:var(--yellow);--kpi-bg:var(--yellow-bg)">
      <div class="kpi-icon"><i class="bi bi-calendar-check"></i></div>
      <div class="kpi-label">Total Booking</div>
      <div class="kpi-value"><?= number_format($total_booking) ?></div>
      <div class="kpi-badge <?= $growth_booking >= 0 ? 'up' : 'down' ?>">
        <i class="bi bi-arrow-<?= $growth_booking >= 0 ? 'up' : 'down' ?>"></i>
        <?= number_format(abs($growth_booking), 1) ?>% vs periode lalu
      </div>
    </div>
    <div class="kpi-card" style="--kpi-accent:#b794f4;--kpi-bg:rgba(183,148,244,.1)">
      <div class="kpi-icon"><i class="bi bi-people"></i></div>
      <div class="kpi-label">Customer Unik</div>
      <div class="kpi-value"><?= number_format($total_customer) ?></div>
      <div class="kpi-sub">pada periode ini</div>
    </div>
    <div class="kpi-card" style="--kpi-accent:var(--blue);--kpi-bg:var(--blue-bg)">
      <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
      <div class="kpi-label">Avg. Per Transaksi</div>
      <div class="kpi-value mono">Rp <?= number_format($avg_order, 0, ',', '.') ?></div>
      <div class="kpi-sub">rata-rata order value</div>
    </div>
  </div>

  <!-- ─── STATUS BREAKDOWN + TREND CHART ────────── -->
  <div class="row g-3 mb-4">
    <div class="col-lg-4">
      <div class="chart-card h-100">
        <div class="chart-card-title">
          <i class="bi bi-pie-chart" style="color:var(--accent)"></i> Breakdown Status
        </div>
        <div class="chart-card-sub">Distribusi status pembayaran dalam periode</div>
        <!-- Status Breakdown List -->
        <?php
        $statuses = [
          'lunas'   => ['label'=>'Lunas',   'color'=>'var(--green)',  'icon'=>'check-circle'],
          'dp'      => ['label'=>'DP',      'color'=>'var(--yellow)', 'icon'=>'clock-history'],
          'pending' => ['label'=>'Pending', 'color'=>'var(--text-muted)', 'icon'=>'hourglass-split'],
          'batal'   => ['label'=>'Batal',   'color'=>'var(--red)',    'icon'=>'x-circle'],
        ];
        $max_cnt = max(array_column($status_breakdown, 'cnt') ?: [1]);
        foreach ($statuses as $key => $info):
          $cnt   = $status_breakdown[$key]['cnt']   ?? 0;
          $tot   = $status_breakdown[$key]['total'] ?? 0;
          $pct   = $max_cnt > 0 ? round($cnt / $max_cnt * 100) : 0;
        ?>
        <div style="margin-bottom:16px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <div style="display:flex;align-items:center;gap:8px;font-size:.84rem;font-weight:700">
              <i class="bi bi-<?= $info['icon'] ?>" style="color:<?= $info['color'] ?>"></i>
              <?= $info['label'] ?>
              <span style="font-family:var(--mono);font-size:.75rem;font-weight:600;
                background:var(--bg-stripe);padding:1px 7px;border-radius:99px;
                color:var(--text-muted)"><?= $cnt ?>×</span>
            </div>
            <span style="font-family:var(--mono);font-size:.78rem;font-weight:700;color:var(--text-sub)">
              Rp <?= number_format($tot, 0, ',', '.') ?>
            </span>
          </div>
          <div class="prog-bar">
            <div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $info['color'] ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Payment method mini summary -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
          <div style="font-size:.73rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:10px">
            Metode Pembayaran
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div style="background:var(--green-bg);border:1px solid var(--green-border);border-radius:10px;padding:10px 12px;text-align:center">
              <div style="font-size:.72rem;font-weight:700;color:var(--green);margin-bottom:2px">💵 Cash</div>
              <div style="font-size:1.05rem;font-weight:900;font-family:var(--mono);color:var(--green)"><?= $pct_cash ?>%</div>
              <div style="font-size:.7rem;color:var(--text-muted)">Rp <?= number_format($total_cash,0,',','.') ?></div>
            </div>
            <div style="background:var(--cyan-bg);border:1px solid rgba(79,209,197,.25);border-radius:10px;padding:10px 12px;text-align:center">
              <div style="font-size:.72rem;font-weight:700;color:var(--cyan);margin-bottom:2px">🏦 Transfer</div>
              <div style="font-size:1.05rem;font-weight:900;font-family:var(--mono);color:var(--cyan)"><?= $pct_transfer ?>%</div>
              <div style="font-size:.7rem;color:var(--text-muted)">Rp <?= number_format($total_transfer,0,',','.') ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="chart-card">
        <div class="chart-card-title">
          <i class="bi bi-bar-chart-line" style="color:var(--accent)"></i> Trend Pendapatan Harian
        </div>
        <div class="chart-card-sub">Cash vs Transfer per hari dalam periode terpilih</div>
        <div class="chart-body" style="height:260px">
          <canvas id="chartTrend"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── EMPLOYEE PERFORMANCE ──────────────────── -->
  <div class="row g-3 mb-4">
    <div class="col-12">
      <div class="chart-card">
        <div class="chart-card-title">
          <i class="bi bi-person-badge" style="color:var(--yellow)"></i> Performa Karyawan
        </div>
        <div class="chart-card-sub">Revenue dan jumlah booking yang dihasilkan setiap karyawan aktif</div>
        <div class="row g-0">
          <div class="col-lg-7">
            <div style="height:220px;padding-top:8px">
              <canvas id="chartEmp"></canvas>
            </div>
          </div>
          <div class="col-lg-5" style="padding:8px 0 0 16px">
            <div style="overflow-x:auto">
              <table style="width:100%;border-collapse:collapse">
                <thead>
                  <tr style="border-bottom:1px solid var(--border)">
                    <th style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:6px 8px">Karyawan</th>
                    <th style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:6px 8px;text-align:right">Revenue</th>
                    <th style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:6px 8px;text-align:center">Booking</th>
                    <th style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:6px 8px;text-align:right">Komisi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $emp_colors = ['var(--accent)','var(--green)','var(--blue)','var(--yellow)','var(--pink)','var(--cyan)'];
                  foreach ($emp_rows as $ei => $emp):
                    $c = $emp_colors[$ei % count($emp_colors)];
                  ?>
                  <tr style="border-bottom:1px solid var(--border)">
                    <td style="padding:7px 8px;font-size:.82rem;font-weight:700;display:flex;align-items:center;gap:6px">
                      <span style="width:8px;height:8px;border-radius:50%;background:<?= $c ?>;flex-shrink:0"></span>
                      <?= htmlspecialchars($emp['nama_karyawan']) ?>
                    </td>
                    <td style="padding:7px 8px;font-size:.78rem;font-family:var(--mono);font-weight:700;text-align:right;color:var(--accent)">
                      Rp <?= number_format($emp['total_revenue'], 0, ',', '.') ?>
                    </td>
                    <td style="padding:7px 8px;font-size:.78rem;font-weight:700;text-align:center">
                      <?= $emp['total_booking'] ?>
                    </td>
                    <td style="padding:7px 8px;font-size:.78rem;font-family:var(--mono);font-weight:700;text-align:right;color:var(--green)">
                      Rp <?= number_format($emp['total_komisi'], 0, ',', '.') ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($emp_rows)): ?>
                  <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted);font-size:.82rem">Tidak ada data</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── DETAIL PEMBUKUAN TABLE ─────────────────── -->
  <div class="section-header">
    <div class="section-title">
      <i class="bi bi-table" style="color:var(--accent)"></i>
      Rekap Transaksi Detail
      <span class="section-badge">Tabel</span>
    </div>
    <div class="date-badge no-print">
      <i class="bi bi-calendar-range"></i>
      <?= date('d M Y', strtotime($tgl_awal)) ?> — <?= date('d M Y', strtotime($tgl_akhir)) ?>
    </div>
  </div>

  <div class="table-card">
    <div class="table-card-header no-print">
      <div>
        <h3 class="table-card-title"><i class="bi bi-journal-check me-2" style="color:var(--green)"></i>Pembukuan Transaksi</h3>
        <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px">
          Semua transaksi (kecuali batal) dalam periode terpilih
        </div>
      </div>
      <button onclick="exportCSV()" class="btn btn-success-soft btn-sm">
        <i class="bi bi-download"></i> Export CSV
      </button>
    </div>
    <div style="padding:0 0 8px">
      <div class="table-scroll">
        <table id="tabelDetail" class="salon-table w-100">
          <thead>
            <tr>
              <th>Tanggal / ID</th>
              <th>Customer</th>
              <th>Layanan</th>
              <th class="text-end">Cash</th>
              <th class="text-end">Transfer</th>
              <th class="text-end">Total Bayar</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            mysqli_data_seek($q_detail, 0);
            $row_total_cash     = 0;
            $row_total_transfer = 0;
            $row_total_bayar    = 0;
            $all_rows = [];
            while ($row = mysqli_fetch_assoc($q_detail)) $all_rows[] = $row;
            foreach ($all_rows as $row):
              $total_row = $row['bayar_cash'] + $row['bayar_transfer'];
              $row_total_cash     += $row['bayar_cash'];
              $row_total_transfer += $row['bayar_transfer'];
              $row_total_bayar    += $total_row;
              $chip_class = match($row['status_pembayaran']) {
                'lunas'   => 'bp-lunas',
                'dp'      => 'bp-dp',
                'pending' => 'bp-pending',
                'batal'   => 'bp-batal',
                default   => 'bp-pending',
              };
              $layanan_list = $row['layanan_list'] ?? '';
              $layanan_parts = $layanan_list ? explode(', ', $layanan_list) : [];
            ?>
            <tr>
              <td>
                <div class="td-id">#<?= htmlspecialchars($row['id_booking']) ?></div>
                <div class="td-date"><?= date('d/m/Y', strtotime($row['tgl_booking'])) ?> &middot; <?= date('H:i', strtotime($row['jam_booking'])) ?></div>
              </td>
              <td>
                <div class="td-name"><?= htmlspecialchars($row['nama_customer']) ?></div>
                <div class="td-wa"><i class="bi bi-whatsapp" style="color:#25D366;font-size:.72rem"></i> <?= htmlspecialchars($row['whatsapp_customer']) ?></div>
              </td>
              <td style="max-width:220px">
                <?php foreach (array_slice($layanan_parts, 0, 3) as $l): ?>
                  <span class="svc-tag"><?= htmlspecialchars(trim($l)) ?></span>
                <?php endforeach; ?>
                <?php if (count($layanan_parts) > 3): ?>
                  <span class="svc-tag" style="background:var(--accent-bg);border-color:var(--accent-border);color:var(--accent)">+<?= count($layanan_parts)-3 ?> lagi</span>
                <?php endif; ?>
              </td>
              <td class="text-end"><span class="td-amt td-cash">Rp <?= number_format($row['bayar_cash'], 0, ',', '.') ?></span></td>
              <td class="text-end"><span class="td-amt td-transfer">Rp <?= number_format($row['bayar_transfer'], 0, ',', '.') ?></span></td>
              <td class="text-end"><span class="td-amt td-total" style="font-weight:900">Rp <?= number_format($total_row, 0, ',', '.') ?></span></td>
              <td class="text-center"><span class="badge-pill <?= $chip_class ?>"><?= strtoupper($row['status_pembayaran']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" style="color:var(--text-muted);font-size:.72rem;letter-spacing:.5px;font-weight:700">
                TOTAL REKAPITULASI (<?= count($all_rows) ?> transaksi)
              </td>
              <td class="text-end td-amt td-cash">Rp <?= number_format($row_total_cash, 0, ',', '.') ?></td>
              <td class="text-end td-amt td-transfer">Rp <?= number_format($row_total_transfer, 0, ',', '.') ?></td>
              <td class="text-end td-amt td-total" style="font-size:.9rem;font-weight:900">Rp <?= number_format($row_total_bayar, 0, ',', '.') ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div style="text-align:center;color:var(--text-muted);font-size:.72rem;padding:1rem 0 2rem">
    <i class="bi bi-printer me-1"></i>
    Dicetak <?= date('d M Y H:i') ?> — Amoy Salon Pembukuan
  </div>

</div><!-- end .page-container -->

<!-- ═══ SCRIPTS ═══════════════════════════════════ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
/* ══════════════════════════════════
   THEME TOGGLE
══════════════════════════════════ */
(function(){
  const saved = localStorage.getItem('salon_theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  updateThemeIcons(saved);
})();
function toggleTheme(){
  const cur = document.documentElement.getAttribute('data-theme');
  const nxt = cur === 'light' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', nxt);
  localStorage.setItem('salon_theme', nxt);
  updateThemeIcons(nxt);
}
function updateThemeIcons(t){
  document.querySelectorAll('#themeBtn,#themeBtnDesktop').forEach(b => { if(b) b.textContent = t==='dark'?'☀️':'🌙'; });
}

/* ══════════════════════════════════
   CHART DEFAULTS
══════════════════════════════════ */
const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor  = () => isDark() ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
const textColor  = () => isDark() ? '#8c90b0' : '#9499b8';

Chart.defaults.font.family = "'Nunito', sans-serif";
Chart.defaults.font.size   = 11;

const tooltipCfg = {
  backgroundColor: () => isDark() ? '#1c1f30' : '#fff',
  borderColor:     () => isDark() ? 'rgba(255,255,255,.1)' : '#e2e5f0',
  borderWidth: 1,
  titleColor:  () => isDark() ? '#eef0fb' : '#1a1d2e',
  bodyColor:   () => isDark() ? '#8c90b0' : '#5a5f7d',
  padding: 10, cornerRadius: 8,
  callbacks: {
    label: ctx => {
      const v = ctx.raw;
      if (v === null || v === undefined) return '';
      if (typeof v === 'number' && v > 999) return ` Rp ${v.toLocaleString('id-ID')}`;
      return ` ${v}`;
    }
  }
};

/* ── 1. REVENUE TREND ── */
const ctxTrend = document.getElementById('chartTrend').getContext('2d');
new Chart(ctxTrend, {
  type: 'bar',
  data: {
    labels: <?= json_encode($trend_labels) ?>,
    datasets: [
      {
        label: 'Cash',
        data: <?= json_encode($trend_cash) ?>,
        backgroundColor: 'rgba(0,184,148,.75)',
        borderRadius: 4, borderSkipped: false,
        stack: 'stack',
      },
      {
        label: 'Transfer',
        data: <?= json_encode($trend_transfer) ?>,
        backgroundColor: 'rgba(79,209,197,.75)',
        borderRadius: 4, borderSkipped: false,
        stack: 'stack',
      },
      {
        label: 'Total',
        data: <?= json_encode($trend_total) ?>,
        type: 'line',
        borderColor: '#b794f4', borderWidth: 2.5,
        pointBackgroundColor: '#b794f4', pointRadius: 3,
        fill: false, tension: .35,
        stack: undefined,
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 12, padding: 16 } }, tooltip: tooltipCfg },
    scales: {
      x: { grid: { color: gridColor() }, stacked: true },
      y: { grid: { color: gridColor() }, stacked: true,
           ticks: { callback: v => 'Rp'+(v/1000).toFixed(0)+'K' } }
    }
  }
});

/* ── 2. EMPLOYEE CHART ── */
const ctxEmp = document.getElementById('chartEmp').getContext('2d');
new Chart(ctxEmp, {
  type: 'bar',
  data: {
    labels: <?= json_encode($emp_labels) ?>,
    datasets: [{
      label: 'Revenue',
      data: <?= json_encode($emp_revenue) ?>,
      backgroundColor: ['rgba(108,92,231,.8)','rgba(0,184,148,.8)','rgba(52,152,219,.8)','rgba(243,156,18,.8)','rgba(232,67,147,.8)','rgba(79,209,197,.8)'],
      borderRadius: 6, borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false }, tooltip: tooltipCfg },
    scales: {
      x: { grid: { color: gridColor() }, ticks: { callback: v => 'Rp'+(v/1000).toFixed(0)+'K' } },
      y: { grid: { display: false } }
    }
  }
});

/* ══════════════════════════════════
   DATATABLES
══════════════════════════════════ */
$(document).ready(function(){
  $('#tabelDetail').DataTable({
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
    pageLength: 25,
    order: [[0,'desc']],
    columnDefs: [
      { orderable: false, targets: [2, 6] }
    ],
    dom: "<'row align-items-center'<'col-sm-6'l><'col-sm-6'f>>t<'row align-items-center'<'col-sm-6'i><'col-sm-6'p>>",
  });
});

/* ══════════════════════════════════
   EXPORT CSV
══════════════════════════════════ */
function exportCSV(){
  const rows = [['ID Booking','Tanggal','Jam','Customer','WA','Layanan','Cash','Transfer','Total','Status']];
  <?php
  mysqli_data_seek($q_detail, 0);
  foreach ($all_rows as $row):
    $total_row = $row['bayar_cash'] + $row['bayar_transfer'];
  ?>
  rows.push([
    '<?= addslashes($row['id_booking']) ?>',
    '<?= date('d/m/Y', strtotime($row['tgl_booking'])) ?>',
    '<?= date('H:i', strtotime($row['jam_booking'])) ?>',
    '<?= addslashes($row['nama_customer']) ?>',
    '<?= addslashes($row['whatsapp_customer']) ?>',
    '<?= addslashes($row['layanan_list'] ?? '') ?>',
    '<?= $row['bayar_cash'] ?>',
    '<?= $row['bayar_transfer'] ?>',
    '<?= $total_row ?>',
    '<?= $row['status_pembayaran'] ?>'
  ]);
  <?php endforeach; ?>

  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\r\n');
  const blob = new Blob(['\uFEFF'+csv], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `pembukuan_amoy_<?= date('Ymd') ?>.csv`;
  a.click();
}
</script>
</body>
</html>