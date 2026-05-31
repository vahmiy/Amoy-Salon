<?php
/**
 * dashboard_admin.php — Amoy Salon · Admin Dashboard (REBUILD)
 * Letakkan di folder: pages/
 *
 * FIX UTAMA:
 *  1. Multi-petugas per layanan → buildPetugasRowHTML() dari scratch, bukan .clone()
 *  2. Modal footer tidak lagi mengambang → struktur form di dalam .modal-content
 *  3. Layout stabil di desktop dan mobile
 */
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}
$user_level = (int)($_SESSION['level'] ?? 99);
include '../class/koneksi.php';

/* ─── Statistik Hari Ini ─── */
$today = date('Y-m-d');
$qst   = mysqli_query($conn, "
    SELECT
        COUNT(id_booking) AS total,
        COALESCE(SUM(CASE WHEN status_pembayaran!='batal' THEN total_biaya     END),0) AS tagihan,
        COALESCE(SUM(CASE WHEN status_pembayaran!='batal' THEN jumlah_terbayar END),0) AS masuk,
        COUNT(CASE WHEN status_kerja='menunggu' AND status_pembayaran!='batal' THEN 1 END) AS jml_m,
        COUNT(CASE WHEN status_kerja='diproses' AND status_pembayaran!='batal' THEN 1 END) AS jml_p,
        COUNT(CASE WHEN status_kerja='selesai'  AND status_pembayaran!='batal' THEN 1 END) AS jml_s
    FROM bookings WHERE tgl_booking='$today'
");
$stat = mysqli_fetch_assoc($qst);
$piutang = $stat['tagihan'] - $stat['masuk'];

/* ─── Preload karyawan → JSON untuk JS ─── */
$qe  = mysqli_query($conn, "SELECT id_employee, nama_karyawan FROM employees WHERE status='active' ORDER BY nama_karyawan ASC");
$emp = [];
while ($e = mysqli_fetch_assoc($qe)) $emp[] = $e;
$emp_json = json_encode($emp, JSON_UNESCAPED_UNICODE);

/* ─── Toast dari redirect ─── */
$toast = ''; $toast_type = 'success';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') $toast = 'Perubahan berhasil disimpan!';
    if ($_GET['status'] === 'deleted') { $toast = 'Booking berhasil dihapus.'; $toast_type = 'danger'; }
    if ($_GET['status'] === 'error')   { $toast = 'Terjadi kesalahan.'; $toast_type = 'danger'; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard · Amoy Salon</title>
<link rel="icon" type="image/png" href="../asset/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
/* ═══════════ TOKENS ═══════════ */
:root{
  --bg:#f0f2f8;--surf:#fff;--nav:#1c1e2b;--nav-h:#2a2d3e;
  --acc:#7c3aed;--acc2:#ec4899;--suc:#10b981;--warn:#f59e0b;--dan:#ef4444;--inf:#3b82f6;
  --txt:#1e293b;--mut:#64748b;--brd:#e2e8f0;
  --r:14px;--rs:9px;--sh:0 2px 12px rgba(0,0,0,.07);--sh2:0 8px 32px rgba(0,0,0,.14);--sh3:0 20px 60px rgba(0,0,0,.2);
  --fn:'DM Sans',sans-serif;--fd:'Playfair Display',serif;--tr:.18s ease;
}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:var(--fn);background:var(--bg);color:var(--txt);font-size:14px;min-height:100vh}

/* ─── NAV ─── */
.topnav{
  background:var(--nav);height:58px;display:flex;align-items:center;
  padding:0 20px;gap:8px;position:sticky;top:0;z-index:1040;
  box-shadow:0 2px 16px rgba(0,0,0,.32);
}
.brand{font-family:var(--fd);color:#fff;font-size:1.1rem;text-decoration:none;white-space:nowrap;flex-shrink:0}
.brand em{color:var(--acc2);font-style:normal}
.nav-links{display:flex;align-items:center;gap:3px;margin-left:auto}
.nav-links a{
  color:rgba(255,255,255,.68);font-size:12.5px;font-weight:500;
  padding:6px 11px;border-radius:8px;text-decoration:none;transition:var(--tr);white-space:nowrap;
}
.nav-links a:hover,.nav-links a.cur{color:#fff;background:var(--nav-h)}
.nav-links a.ao{background:var(--acc);color:#fff;margin-left:4px}
.nav-links a.al{color:#fca5a5}
.nav-links a.al:hover{background:rgba(239,68,68,.15);color:var(--dan)}
.nav-tog{display:none;background:none;border:none;color:#fff;font-size:1.35rem;margin-left:auto;cursor:pointer}
@media(max-width:900px){
  .nav-tog{display:block}
  .nav-links{
    display:none;position:absolute;top:58px;left:0;right:0;
    background:var(--nav);flex-direction:column;align-items:stretch;
    padding:10px 16px 18px;gap:3px;border-top:1px solid rgba(255,255,255,.07);z-index:1039;
  }
  .nav-links.open{display:flex}
}

/* ─── PAGE ─── */
.pw{max-width:1440px;margin:0 auto;padding:22px 18px 40px}
@media(max-width:600px){.pw{padding:14px 12px 50px}}

/* ─── STAT CARDS ─── */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
@media(max-width:780px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:400px){.stat-grid{grid-template-columns:1fr 1fr}}
.scard{
  background:var(--surf);border-radius:var(--r);padding:16px 18px;
  box-shadow:var(--sh);border-top:3px solid transparent;
  transition:transform var(--tr),box-shadow var(--tr);
}
.scard:hover{transform:translateY(-2px);box-shadow:var(--sh2)}
.scard.c1{border-color:var(--inf)}.scard.c2{border-color:var(--acc)}
.scard.c3{border-color:var(--suc)}.scard.c4{border-color:var(--dan)}
.scard .ico{
  width:36px;height:36px;border-radius:9px;display:flex;align-items:center;
  justify-content:center;font-size:16px;margin-bottom:8px;
}
.c1 .ico{background:#eff6ff;color:var(--inf)}.c2 .ico{background:#f5f3ff;color:var(--acc)}
.c3 .ico{background:#f0fdf4;color:var(--suc)}.c4 .ico{background:#fff1f2;color:var(--dan)}
.scard .lbl{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--mut);margin-bottom:4px}
.scard .val{font-size:1.4rem;font-weight:700;line-height:1;color:var(--txt)}
.scard .sub{font-size:11px;color:var(--mut);margin-top:3px}

/* ─── STATUS MINI ─── */
.smini-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px}
.smini{
  background:var(--surf);border-radius:var(--rs);padding:11px 13px;
  box-shadow:var(--sh);display:flex;align-items:center;gap:9px;
}
.smini .dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.s-m .dot{background:#94a3b8}
.s-p .dot{background:var(--warn);box-shadow:0 0 5px var(--warn)}
.s-s .dot{background:var(--suc);box-shadow:0 0 5px var(--suc)}
.smini .sn{font-size:1.25rem;font-weight:700;line-height:1}
.smini .sl{font-size:11px;color:var(--mut);font-weight:600}

/* ─── MAIN CARD ─── */
.mcard{background:var(--surf);border-radius:var(--r);box-shadow:var(--sh);padding:22px}
.mcard-header{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.mcard-title{font-family:var(--fd);font-size:1.15rem;margin:0;flex:1}

/* ─── FILTER ─── */
.frow{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px}
.fg{display:flex;flex-direction:column;gap:3px}
.fg label{font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--mut)}
.fg input,.fg select{
  border:1.5px solid var(--brd);border-radius:var(--rs);padding:7px 10px;
  font-family:var(--fn);font-size:13px;color:var(--txt);background:#faf9fc;outline:none;
  transition:border-color var(--tr);
}
.fg input:focus,.fg select:focus{border-color:var(--acc);background:#fff}
.btn-f{
  border:none;border-radius:var(--rs);padding:8px 16px;font-family:var(--fn);
  font-size:13px;font-weight:600;cursor:pointer;transition:var(--tr);
}
.bf-rst{background:#f1f5f9;color:var(--mut)}.bf-rst:hover{background:#e2e8f0}
.bf-scan{background:var(--nav);color:#fff}.bf-scan:hover{opacity:.85}

/* ─── TABLE ─── */
#tblB{width:100% !important}
#tblB thead th{
  background:#f8fafc;color:var(--mut);font-size:11px;font-weight:700;
  text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--brd);
  padding:11px 13px;white-space:nowrap;
}
#tblB tbody tr{transition:background var(--tr)}
#tblB tbody tr:hover{background:#f8f6ff}
#tblB tbody td{padding:11px 13px;border-bottom:1px solid #f4f2fa;vertical-align:middle}
.id-b{font-family:'Courier New',monospace;font-weight:800;color:var(--acc);font-size:12px}
.cn{font-weight:700}.cw{font-size:11px;color:var(--mut);margin-top:1px}
.spill{
  display:inline-block;background:#f5f3ff;color:var(--acc);
  border-radius:20px;padding:2px 8px;font-size:11px;font-weight:600;margin:1px 1px 1px 0;
}
.finbox{
  margin-top:6px;background:#f8fafc;border-radius:7px;padding:6px 9px;
  border:1px solid var(--brd);font-size:11px;
}
.fr{display:flex;justify-content:space-between;padding:1px 0}
.fr.kur{color:var(--dan);font-weight:700}.fr.kmb{color:var(--inf);font-weight:700}
.fr.lns{color:var(--suc);font-weight:700}
.petline{font-size:11.5px;padding:2px 0}
.petline strong{color:var(--acc);font-size:10.5px;display:block}
.petline em{color:var(--mut)}

/* ─── BADGES ─── */
.bs{
  display:inline-flex;align-items:center;gap:4px;padding:4px 10px;
  border-radius:20px;font-size:11px;font-weight:700;border:1.5px solid transparent;white-space:nowrap;
}
.bs-menunggu{background:#f1f5f9;color:#475569;border-color:#cbd5e1}
.bs-diproses{background:#fef3c7;color:#92400e;border-color:#fde68a}
.bs-selesai {background:#d1fae5;color:#065f46;border-color:#6ee7b7}
.bs-pending {background:#fee2e2;color:#991b1b;border-color:#fca5a5}
.bs-dp     {background:#dbeafe;color:#1e40af;border-color:#93c5fd}
.bs-lunas  {background:#d1fae5;color:#065f46;border-color:#6ee7b7}
.bs-batal  {background:#f1f5f9;color:#94a3b8;border-color:#e2e8f0}

/* ─── ACTION BUTTONS ─── */
.abtns{display:flex;gap:5px;flex-wrap:wrap}
.bact{
  border:none;border-radius:8px;padding:6px 11px;font-size:12px;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:4px;text-decoration:none;
  transition:filter var(--tr),transform .1s;font-family:var(--fn);white-space:nowrap;
}
.bact:hover{filter:brightness(.88);transform:translateY(-1px)}
.bact:active{transform:none}
.ba-k{background:var(--nav);color:#fff}
.ba-b{background:var(--suc);color:#fff}
.ba-n{background:var(--inf);color:#fff}
.ba-d{background:#fff;color:var(--dan);border:1.5px solid var(--dan) !important}

/* ─── MODAL BASE ─── */
.modal-content{
  border-radius:18px !important;border:none !important;
  box-shadow:var(--sh3) !important;font-family:var(--fn);
  /* Bootstrap sudah display:flex;flex-direction:column — jangan override */
}
.modal-header{padding:16px 20px;border-bottom:1px solid var(--brd) !important;flex-shrink:0}
.modal-title{font-size:15px;font-weight:700;color:var(--txt)}
/* PENTING: modal-body scroll mandiri, footer tetap di bawah */
.modal-body{
  padding:18px 20px;
  overflow-y:auto;
  overflow-x:hidden;
  /* Max-height diatur per modal via class */
}
.mbody-kelola{max-height:60vh}
.mbody-bayar {max-height:70vh}
.modal-footer{
  padding:12px 20px;
  border-top:1px solid var(--brd) !important;
  flex-shrink:0;
  display:flex;
  gap:8px;
  /* flex-shrink:0 memastikan footer tidak tergeser */
}
.modal-footer .btn{font-family:var(--fn);font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px}

/* Modal Kelola Header */
.mh-kelola{background:linear-gradient(135deg,#1c1e2b,#2d1b5e) !important}
.mh-kelola .modal-title,.mh-kelola code{color:#fff !important}
.mh-kelola .btn-close{filter:invert(1) opacity(.7)}

/* Modal Bayar Header */
.mh-bayar{background:linear-gradient(135deg,#065f46,#10b981) !important}
.mh-bayar .modal-title{color:#fff !important}
.mh-bayar .btn-close{filter:invert(1) opacity(.7)}

/* ─── KELOLA: Sections ─── */
.slbl{
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
  color:var(--mut);margin-bottom:9px;display:flex;align-items:center;gap:5px;
}

/* Status radio */
.srs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.rw input[type=radio]{display:none}
.rw .rl{
  display:inline-flex;align-items:center;gap:5px;padding:6px 13px;
  border-radius:20px;cursor:pointer;border:2px solid var(--brd);
  font-size:12px;font-weight:700;transition:var(--tr);background:var(--bg);color:var(--mut);
}
.rw input[type=radio]:checked + .rl{border-color:var(--acc);background:#f5f3ff;color:var(--acc)}

/* Layanan Block */
.svb{
  background:#fafafe;border:1.5px solid var(--brd);border-radius:11px;
  padding:13px;margin-bottom:12px;
}
.svb-name{font-weight:700;font-size:13.5px;color:var(--txt);margin-bottom:10px;display:flex;align-items:center;gap:5px}
.svb-name i{color:var(--acc2)}

/* Harga input row */
.hw{display:flex;align-items:stretch;margin-bottom:11px}
.hw .pfx{
  background:#ede9fe;color:var(--acc);font-weight:700;font-size:13px;
  padding:8px 11px;border-radius:8px 0 0 8px;border:1.5px solid #c4b5fd;
  border-right:none;display:flex;align-items:center;white-space:nowrap;flex-shrink:0;
}
.hw .hinp{
  border:1.5px solid #c4b5fd;border-radius:0 8px 8px 0;
  padding:8px 11px;font-family:var(--fn);font-size:13px;font-weight:700;
  color:var(--txt);outline:none;width:100%;background:#fff;transition:border-color var(--tr);
}
.hw .hinp:focus{border-color:var(--acc);z-index:1}

/* Komisi Area */
.karea{background:#fff;border:1.5px solid #e0dbff;border-radius:9px;padding:11px}
.kheader{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;gap:6px;flex-wrap:wrap}
.kheader .khl{font-size:11.5px;font-weight:700;color:var(--mut)}

/* Progress */
.kpw{margin-bottom:9px}
.kbar{background:#ede9fe;border-radius:20px;height:5px;overflow:hidden;margin-bottom:3px}
.kfill{background:linear-gradient(90deg,var(--acc),var(--acc2));height:100%;border-radius:20px;transition:width .3s ease}
.kfill.over{background:var(--dan)}
.ktxt{font-size:10.5px;color:var(--mut);font-weight:600}

/* Petugas row */
.prow{display:flex;gap:7px;align-items:center;margin-bottom:7px}
.prow select{
  flex:1;border:1.5px solid var(--brd);border-radius:8px;
  padding:7px 9px;font-family:var(--fn);font-size:13px;color:var(--txt);
  outline:none;background:#fff;transition:border-color var(--tr);min-width:0;
}
.prow select:focus{border-color:var(--acc)}
.ppw{display:flex;align-items:stretch;flex-shrink:0}
.ppw .pi{
  width:64px;border:1.5px solid var(--brd);border-radius:8px 0 0 8px;
  padding:7px 7px;font-family:var(--fn);font-size:13px;font-weight:700;
  color:var(--txt);outline:none;background:#fff;text-align:center;
  transition:border-color var(--tr);
}
.ppw .pi:focus{border-color:var(--acc)}
.ppw .ps{
  background:#f5f3ff;color:var(--acc);font-weight:700;font-size:12px;
  padding:7px 8px;border-radius:0 8px 8px 0;border:1.5px solid var(--brd);
  border-left:none;display:flex;align-items:center;
}
.btn-dr{
  background:#fff1f2;color:var(--dan);border:1.5px solid #fca5a5;
  border-radius:8px;padding:6px 8px;cursor:pointer;font-size:13px;
  flex-shrink:0;transition:var(--tr);line-height:1;
}
.btn-dr:hover{background:var(--dan);color:#fff;border-color:var(--dan)}
.btn-dr:disabled{opacity:.3;cursor:not-allowed;pointer-events:none}
.btn-ar{
  background:#f5f3ff;color:var(--acc);border:1.5px dashed #c4b5fd;
  border-radius:8px;padding:7px 14px;cursor:pointer;font-size:12px;font-weight:700;
  width:100%;margin-top:5px;transition:var(--tr);display:flex;align-items:center;
  justify-content:center;gap:5px;font-family:var(--fn);background-color:#f5f3ff;
}
.btn-ar:hover{background:#ede9fe;border-style:solid}
.btn-sp{
  background:#ede9fe;color:var(--acc);border:none;border-radius:6px;
  padding:4px 9px;font-size:11px;font-weight:700;cursor:pointer;font-family:var(--fn);
  transition:var(--tr);
}
.btn-sp:hover{background:#ddd6fe}
.kwarn{
  background:#fee2e2;color:#991b1b;border-radius:7px;padding:6px 10px;
  font-size:11.5px;font-weight:600;margin-top:5px;display:none;align-items:center;gap:6px;
}
.kwarn.show{display:flex}

/* Total display */
.tdisp{
  background:linear-gradient(135deg,#1c1e2b,#312e81);
  border-radius:10px;padding:13px 16px;color:#fff;
  display:flex;justify-content:space-between;align-items:center;margin-top:4px;
}
.tdisp .tdl{font-size:11px;font-weight:600;opacity:.8;text-transform:uppercase;letter-spacing:.3px}
.tdisp .tdv{font-size:1.25rem;font-weight:800}

/* Submit button */
.bsub{
  flex:1;border:none;border-radius:9px;padding:11px 16px;font-family:var(--fn);
  font-size:13.5px;font-weight:700;cursor:pointer;transition:opacity var(--tr),transform .1s;
  display:flex;align-items:center;justify-content:center;gap:7px;
}
.bsub:hover{opacity:.9}.bsub:active{transform:scale(.98)}
.bsk{background:linear-gradient(135deg,var(--acc),var(--acc2));color:#fff}
.bsb{background:linear-gradient(135deg,#065f46,var(--suc));color:#fff}

/* ─── BAYAR Modal ─── */
.tbbox{
  background:#f0fdf4;border:2px solid #6ee7b7;border-radius:11px;
  padding:14px;text-align:center;margin-bottom:14px;
}
.tbbox .tbl{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--mut)}
.tbbox .tbv{font-size:1.75rem;font-weight:800;color:var(--txt)}
.pgrid{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-bottom:12px}
@media(max-width:440px){.pgrid{grid-template-columns:1fr}}
.pw2 label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--mut);display:block;margin-bottom:4px}
.pw2 .piw{position:relative}
.pw2 .piw i{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--mut);font-size:13px}
.pw2 input{
  width:100%;border:1.5px solid var(--brd);border-radius:8px;
  padding:9px 9px 9px 30px;font-family:var(--fn);font-size:14px;
  font-weight:700;color:var(--txt);outline:none;transition:border-color var(--tr);
}
.pw2 input:focus{border-color:var(--suc)}
.cbox{
  border-radius:10px;padding:12px;text-align:center;margin-bottom:12px;
  border:2px solid var(--brd);background:#f8fafc;transition:all .2s;
}
.cbox.ok {border-color:#6ee7b7;background:#f0fdf4}
.cbox.bad{border-color:#fca5a5;background:#fff1f2}
.cbox .cl{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--mut)}
.cbox .cv{font-size:1.4rem;font-weight:800;color:var(--txt)}
.cbox.ok  .cv{color:var(--suc)}.cbox.bad .cv{color:var(--dan)}
.ssel label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--mut);display:block;margin-bottom:4px}
.ssel select{
  width:100%;border:1.5px solid var(--brd);border-radius:8px;
  padding:9px 11px;font-family:var(--fn);font-size:13px;font-weight:700;
  outline:none;transition:border-color var(--tr);
}
.ssel select:focus{border-color:var(--suc)}

/* ─── TOAST ─── */
#toast-c{position:fixed;bottom:22px;right:18px;z-index:9999;display:flex;flex-direction:column;gap:9px}
.toast-i{
  background:#1e293b;color:#fff;border-radius:12px;padding:13px 17px;
  font-size:13px;font-weight:600;display:flex;align-items:center;gap:9px;
  box-shadow:var(--sh2);min-width:250px;animation:tIn .3s ease;
}
.toast-i.success .tic{color:#6ee7b7}.toast-i.danger .tic{color:#fca5a5}
@keyframes tIn{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
@keyframes tOut{from{opacity:1}to{opacity:0;transform:translateY(10px)}}

/* ─── DATATABLE ─── */
.dataTables_wrapper .dataTables_filter input{
  border:1.5px solid var(--brd) !important;border-radius:8px !important;
  padding:6px 11px !important;font-family:var(--fn) !important;font-size:13px !important;outline:none !important;
}
.dataTables_wrapper .dataTables_filter input:focus{border-color:var(--acc) !important}
.dataTables_wrapper .dataTables_length select{
  border:1.5px solid var(--brd) !important;border-radius:8px !important;
  padding:4px 8px !important;font-family:var(--fn) !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{
  background:var(--acc) !important;color:#fff !important;border:none !important;border-radius:7px !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled){
  background:#f5f3ff !important;color:var(--acc) !important;
  border:1px solid var(--brd) !important;border-radius:7px !important;
}
.dataTables_info{font-size:12px;color:var(--mut)}

/* ─── MODAL SIZE ─── */
.mdlg{max-width:660px}
@media(max-width:680px){.mdlg{margin:8px;max-width:calc(100% - 16px)}}

/* ─── MOBILE TABLE ─── */
@media(max-width:768px){
  .mcard{padding:14px}
  #tblB thead th{display:none}
  #tblB tbody td{display:block;padding:5px 11px;border-bottom:none}
  #tblB tbody td::before{
    content:attr(data-label);font-weight:700;font-size:10px;
    text-transform:uppercase;color:var(--mut);display:block;margin-bottom:2px;
  }
  #tblB tbody tr{
    border:1.5px solid var(--brd);border-radius:12px;
    margin-bottom:11px;background:#fff;display:block;padding:8px 0;
  }
  .abtns{padding:4px 11px 8px}
}

/* QR */
#qrr{width:100% !important;border-radius:10px !important;overflow:hidden}
#qrr video{border-radius:10px !important}
</style>
</head>
<body>

<!-- ═══════════ TOPNAV ═══════════ -->
<nav class="topnav">
  <a href="dashboard_admin.php" class="brand">✦ <em>Amoy</em> Salon</a>
  <button class="nav-tog" id="nt"><i class="bi bi-list"></i></button>
  <div class="nav-links" id="nl">
    <?php if($user_level<=2):?>
    <a href="dashboard_admin.php" class="cur"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Dashboard</a>
    <?php endif;if($user_level<=1):?>
    <a href="master_data.php"><i class="bi bi-gear-fill me-1"></i>Master Data</a>
    <?php endif;if($user_level<=2):?>
    <a href="laporan.php"><i class="bi bi-bar-chart-fill me-1"></i>Pendapatan</a>
    <a href="komisi.php"><i class="bi bi-wallet2 me-1"></i>Komisi</a>
    <a href="laporan_pembayaran.php"><i class="bi bi-book-fill me-1"></i>Pembukuan</a>
    <?php endif;if($user_level<=1):?>
    <a href="../class/user_manage.php"><i class="bi bi-people-fill me-1"></i>Kelola Akun</a>
    <?php endif;?>
    <a href="../index.php" target="_blank" class="ao"><i class="bi bi-box-arrow-up-right me-1"></i>Booking Online</a>
    <a href="../class/logout.php" class="al" onclick="return confirm('Yakin keluar?')"><i class="bi bi-box-arrow-right me-1"></i>Keluar</a>
  </div>
</nav>

<!-- ═══════════ PAGE ═══════════ -->
<div class="pw">

  <!-- STAT CARDS -->
  <div class="stat-grid">
    <div class="scard c1">
      <div class="ico"><i class="bi bi-calendar-check-fill"></i></div>
      <div class="lbl">Booking Hari Ini</div>
      <div class="val" id="live-n"><?= $stat['total'] ?></div>
      <div class="sub">customer</div>
    </div>
    <div class="scard c2">
      <div class="ico"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="lbl">Total Tagihan</div>
      <div class="val" style="font-size:1.1rem">Rp <?= number_format($stat['tagihan'],0,',','.') ?></div>
      <div class="sub">hari ini</div>
    </div>
    <div class="scard c3">
      <div class="ico"><i class="bi bi-cash-coin"></i></div>
      <div class="lbl">Uang Masuk</div>
      <div class="val" style="font-size:1.1rem">Rp <?= number_format($stat['masuk'],0,',','.') ?></div>
      <div class="sub">terbayar</div>
    </div>
    <div class="scard c4">
      <div class="ico"><i class="bi bi-exclamation-circle-fill"></i></div>
      <div class="lbl">Piutang</div>
      <div class="val" style="font-size:1.1rem">Rp <?= number_format($piutang,0,',','.') ?></div>
      <div class="sub">belum lunas</div>
    </div>
  </div>

  <!-- STATUS MINI -->
  <div class="smini-grid">
    <div class="smini s-m">
      <div class="dot"></div>
      <div><div class="sn"><?= $stat['jml_m'] ?></div><div class="sl">Menunggu</div></div>
    </div>
    <div class="smini s-p">
      <div class="dot"></div>
      <div><div class="sn"><?= $stat['jml_p'] ?></div><div class="sl">Diproses</div></div>
    </div>
    <div class="smini s-s">
      <div class="dot"></div>
      <div><div class="sn"><?= $stat['jml_s'] ?></div><div class="sl">Selesai</div></div>
    </div>
  </div>

  <!-- MAIN CARD -->
  <div class="mcard">
    <div class="mcard-header">
      <h2 class="mcard-title"><i class="bi bi-list-ul me-2" style="color:var(--acc)"></i>Daftar Booking</h2>
      <button class="btn-f bf-scan" data-bs-toggle="modal" data-bs-target="#mScan">
        <i class="bi bi-qr-code-scan me-1"></i>Scan QR
      </button>
    </div>

    <!-- Filter -->
    <div class="frow">
      <div class="fg">
        <label>Dari Tanggal</label>
        <input type="date" id="fMin">
      </div>
      <div class="fg">
        <label>Sampai</label>
        <input type="date" id="fMax">
      </div>
      <div class="fg">
        <label>Status Kerja</label>
        <select id="fSt">
          <option value="">Semua</option>
          <option value="menunggu">Menunggu</option>
          <option value="diproses">Diproses</option>
          <option value="selesai">Selesai</option>
        </select>
      </div>
      <button class="btn-f bf-rst" id="btnRst"><i class="bi bi-arrow-clockwise"></i> Reset</button>
    </div>

    <div class="table-responsive">
      <table id="tblB" class="table">
        <thead><tr>
          <th>ID</th><th>Customer</th><th>Jadwal</th>
          <th>Layanan &amp; Biaya</th><th>Petugas</th>
          <th>Status Kerja</th><th>Status Bayar</th><th class="no-sort">Aksi</th>
        </tr></thead>
        <tbody>
<?php
/* ─── Query semua booking ─── */
$sk_ico=['menunggu'=>'bi-hourglass-split','diproses'=>'bi-lightning-charge-fill','selesai'=>'bi-check-circle-fill'];
$sp_ico=['pending'=>'bi-clock-history','dp'=>'bi-cash-stack','lunas'=>'bi-check-circle-fill','batal'=>'bi-x-circle'];
$allq=mysqli_query($conn,"SELECT * FROM bookings ORDER BY created_at DESC");

while($row=mysqli_fetch_assoc($allq)):
  $id_b   = $row['id_booking'];
  $total  = $row['total_biaya'];
  $bayar  = $row['jumlah_terbayar'] ?? 0;
  $sisa   = $total - $bayar;
  $sk     = $row['status_kerja'];
  $sp     = $row['status_pembayaran'];
  $sislbl = $sisa>0 ? 'kur' : ($sisa<0 ? 'kmb' : 'lns');
  $sistxt = $sisa>0 ? 'Kurang' : ($sisa<0 ? 'Kembali' : '✓ Lunas');
?>
<tr>
  <td data-label="ID"><span class="id-b"><?=htmlspecialchars($id_b)?></span></td>
  <td data-label="Customer">
    <div class="cn"><?=htmlspecialchars($row['nama_customer'])?></div>
    <div class="cw"><i class="bi bi-whatsapp text-success" style="font-size:10px"></i> <?=htmlspecialchars($row['whatsapp_customer'])?></div>
  </td>
  <td data-sort="<?=$row['tgl_booking']?>" data-label="Jadwal">
    <div style="font-weight:700"><?=date('d/m/Y',strtotime($row['tgl_booking']))?></div>
    <span class="bs bs-menunggu" style="font-size:10px;padding:2px 7px;margin-top:3px;display:inline-flex"><?=date('H:i',strtotime($row['jam_booking']))?></span>
  </td>
  <td data-label="Layanan">
    <?php
    $ql=mysqli_query($conn,"SELECT s.nama_layanan FROM booking_details d JOIN services s ON d.id_service=s.id_service WHERE d.id_booking='$id_b'");
    while($l=mysqli_fetch_assoc($ql)) echo '<span class="spill">'.htmlspecialchars($l['nama_layanan']).'</span>';
    ?>
    <div class="finbox">
      <div class="fr"><span>Total</span><strong>Rp <?=number_format($total,0,',','.')?></strong></div>
      <div class="fr"><span>Bayar</span><span>Rp <?=number_format($bayar,0,',','.')?></span></div>
      <div class="fr <?=$sislbl?>"><span><?=$sistxt?></span><span><?=$sisa!=0?'Rp '.number_format(abs($sisa),0,',','.'):'Lunas'?></span></div>
    </div>
  </td>
  <td data-label="Petugas">
    <?php
    $qp=mysqli_query($conn,"
      SELECT s.nama_layanan,
             GROUP_CONCAT(e.nama_karyawan ORDER BY bk.id_komisi SEPARATOR ' &amp; ') AS pet
      FROM booking_details d
      JOIN services s ON d.id_service=s.id_service
      LEFT JOIN booking_komisi bk ON bk.id_detail=d.id_detail
      LEFT JOIN employees e ON bk.id_employee=e.id_employee
      WHERE d.id_booking='$id_b'
      GROUP BY d.id_detail,s.nama_layanan
    ");
    while($p=mysqli_fetch_assoc($qp)){
      $pt=!empty($p['pet'])?htmlspecialchars($p['pet']):'<em>Belum set</em>';
      echo "<div class='petline'><strong>{$p['nama_layanan']}:</strong> $pt</div>";
    }
    ?>
  </td>
  <td data-label="Status Kerja" data-sk="<?=$sk?>">
    <span class="bs bs-<?=$sk?>"><i class="bi <?=$sk_ico[$sk]??'bi-circle'?>"></i> <?=ucfirst($sk)?></span>
  </td>
  <td data-label="Status Bayar">
    <span class="bs bs-<?=$sp?>"><i class="bi <?=$sp_ico[$sp]??'bi-circle'?>"></i> <?=strtoupper($sp)?></span>
  </td>
  <td data-label="Aksi">
    <div class="abtns">
      <button class="bact ba-k" data-bs-toggle="modal" data-bs-target="#mk<?=$id_b?>"><i class="bi bi-gear-fill"></i> Kelola</button>
      <button class="bact ba-b" data-bs-toggle="modal" data-bs-target="#mb<?=$id_b?>"><i class="bi bi-cash-stack"></i> Bayar</button>
      <a href="../pages/cetak_nota.php?id=<?=$id_b?>" target="_blank" class="bact ba-n" title="Cetak Nota"><i class="bi bi-printer-fill"></i></a>
      <a href="../class/delete_booking.php?id=<?=$id_b?>" class="bact ba-d"
         onclick="return confirm('Hapus booking <?=$id_b?> secara permanen?')"><i class="bi bi-trash3-fill"></i></a>
    </div>
  </td>
</tr>

<!-- ═══════════════════════════════
     MODAL KELOLA
     STRUKTUR KUNCI:
       .modal > .modal-dialog > .modal-content
         .modal-content = flex column (Bootstrap default)
         form ada di DALAM .modal-content
         .modal-header, .modal-body, .modal-footer
         header & footer: flex-shrink:0 → TIDAK ikut scroll
         body: overflow-y:auto → HANYA body yang scroll
     Ini memastikan footer SELALU di bawah.
═══════════════════════════════ -->
<div class="modal fade" id="mk<?=$id_b?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered mdlg">
    <div class="modal-content">
      <form action="../class/update_booking.php" method="POST" class="form-klg d-flex flex-column" style="min-height:0;flex:1">
        <!-- HEADER -->
        <div class="modal-header mh-kelola">
          <h5 class="modal-title">
            <i class="bi bi-gear-fill me-2"></i>Kelola Booking
            <code style="font-size:11.5px;background:rgba(255,255,255,.15);padding:2px 8px;border-radius:5px;margin-left:6px"><?=$id_b?></code>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- BODY (scroll mandiri) -->
        <div class="modal-body mbody-kelola">
          <input type="hidden" name="id_booking" value="<?=$id_b?>">
          <input type="hidden" name="update_type" value="operasional">

          <!-- Status Kerja -->
          <div class="slbl"><i class="bi bi-activity"></i> Status Pengerjaan</div>
          <div class="srs mb-3">
            <?php foreach(['menunggu'=>['bi-hourglass-split','Menunggu'],'diproses'=>['bi-lightning-charge-fill','Diproses'],'selesai'=>['bi-check-circle-fill','Selesai']] as $sv=>[$ic,$lb]):?>
            <label class="rw">
              <input type="radio" name="status_kerja" value="<?=$sv?>" <?=$row['status_kerja']==$sv?'checked':''?>>
              <span class="rl bs bs-<?=$sv?>"><i class="bi <?=$ic?>"></i> <?=$lb?></span>
            </label>
            <?php endforeach;?>
          </div>

          <!-- Detail Layanan -->
          <div class="slbl"><i class="bi bi-scissors"></i> Layanan &amp; Petugas</div>
          <?php
          /* Ambil detail layanan + komisi yang sudah ada */
          $qd=mysqli_query($conn,"
            SELECT d.id_detail,d.subtotal,s.nama_layanan,s.komisi_persen AS mkm
            FROM booking_details d
            JOIN services s ON d.id_service=s.id_service
            WHERE d.id_booking='$id_b'
            ORDER BY d.id_detail ASC
          ");
          while($ld=mysqli_fetch_assoc($qd)):
            $dtl_id=(int)$ld['id_detail'];
            $max_k=(int)$ld['mkm'];

            /* Ambil petugas tersimpan */
            $qkm=mysqli_query($conn,"SELECT id_employee,persen_komisi FROM booking_komisi WHERE id_detail='$dtl_id' ORDER BY id_komisi ASC");
            $krows=mysqli_fetch_all($qkm,MYSQLI_ASSOC);

            /* Fallback jika belum ada */
            if(empty($krows)){
              $krows=[['id_employee'=>$ld['id_employee']??'','persen_komisi'=>$max_k]];
            }
            $used_p=array_sum(array_column($krows,'persen_komisi'));
            $bar_p=$max_k>0?min(100,round(($used_p/$max_k)*100)):0;
          ?>
          <div class="svb">
            <div class="svb-name"><i class="bi bi-scissors"></i><?=htmlspecialchars($ld['nama_layanan'])?></div>
            <input type="hidden" name="id_detail[]" value="<?=$dtl_id?>">

            <!-- Harga -->
            <div class="hw">
              <div class="pfx">Rp</div>
              <input type="number" name="harga_layanan[<?=$dtl_id?>]"
                     class="hinp harga-inp"
                     value="<?=(int)$ld['subtotal']?>"
                     data-bid="<?=$id_b?>">
            </div>

            <!-- Komisi -->
            <div class="karea">
              <div class="kheader">
                <span class="khl">Petugas &amp; Komisi · Maks: <?=$max_k?>%</span>
                <button type="button" class="btn-sp" data-d="<?=$dtl_id?>" data-max="<?=$max_k?>">
                  <i class="bi bi-distribute-vertical"></i> Bagi Rata
                </button>
              </div>

              <!-- Progress -->
              <div class="kpw">
                <div class="kbar">
                  <div class="kfill <?=$used_p>$max_k?'over':''?>"
                       id="kf<?=$dtl_id?>"
                       style="width:<?=$bar_p?>%"></div>
                </div>
                <div class="ktxt" id="kt<?=$dtl_id?>">
                  <?=$used_p?>% / <?=$max_k?>% terisi · Sisa: <?=$max_k-$used_p?>%
                </div>
              </div>

              <!--
                CONTAINER PETUGAS
                name="id_employee[DTLID][]" dan "komisi_persen[DTLID][]"
                Baris baru dibuat via JS (buildRow), BUKAN .clone()
              -->
              <div id="pc<?=$dtl_id?>" data-max="<?=$max_k?>" data-d="<?=$dtl_id?>">
                <?php foreach($krows as $kr):?>
                <div class="prow">
                  <select name="id_employee[<?=$dtl_id?>][]">
                    <option value="">— Pilih Petugas —</option>
                    <?php foreach($emp as $e):?>
                    <option value="<?=$e['id_employee']?>" <?=(string)$e['id_employee']===(string)$kr['id_employee']?'selected':''?>>
                      <?=htmlspecialchars($e['nama_karyawan'])?>
                    </option>
                    <?php endforeach;?>
                  </select>
                  <div class="ppw">
                    <input type="number" step="0.1" min="0" max="100"
                           name="komisi_persen[<?=$dtl_id?>][]"
                           class="pi pct-inp"
                           placeholder="0"
                           value="<?=$kr['persen_komisi']?>"
                           data-d="<?=$dtl_id?>">
                    <span class="ps">%</span>
                  </div>
                  <button type="button" class="btn-dr" <?=count($krows)<=1?'disabled':''?>>
                    <i class="bi bi-x-lg"></i>
                  </button>
                </div>
                <?php endforeach;?>
              </div>

              <div class="kwarn <?=$used_p>$max_k?'show':''?>" id="kw<?=$dtl_id?>">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Total komisi melebihi batas <strong><?=$max_k?>%</strong>
              </div>

              <button type="button" class="btn-ar" data-d="<?=$dtl_id?>">
                <i class="bi bi-plus-circle-fill"></i> Tambah Petugas
              </button>
            </div><!-- /karea -->
          </div><!-- /svb -->
          <?php endwhile;?>

          <!-- Total -->
          <div class="tdisp" id="td<?=$id_b?>">
            <div class="tdl">Total Biaya Baru</div>
            <div class="tdv" id="tv<?=$id_b?>">Rp <?=number_format($total,0,',','.')?></div>
          </div>
        </div><!-- /modal-body -->

        <!-- FOOTER (selalu di bawah, tidak scroll) -->
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="bsub bsk">
            <i class="bi bi-save2-fill"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div><!-- /modal-content -->
  </div>
</div>

<!-- ═══════════════════════════════
     MODAL BAYAR
═══════════════════════════════ -->
<div class="modal fade" id="mb<?=$id_b?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <div class="modal-content">
      <form action="../class/update_booking.php" method="POST" class="d-flex flex-column" style="min-height:0;flex:1">
        <!-- HEADER -->
        <div class="modal-header mh-bayar">
          <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Pembayaran &nbsp;
            <code style="font-size:11px;background:rgba(255,255,255,.2);padding:2px 7px;border-radius:4px"><?=$id_b?></code>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- BODY -->
        <div class="modal-body mbody-bayar">
          <input type="hidden" name="id_booking" value="<?=$id_b?>">
          <input type="hidden" name="update_type" value="pembayaran">

          <!-- Tagihan -->
          <div class="tbbox">
            <div class="tbl">Total Tagihan</div>
            <div class="tbv" id="tb<?=$id_b?>" data-n="<?=$total?>">
              Rp <?=number_format($total,0,',','.')?>
            </div>
          </div>

          <!-- Nominal Bayar -->
          <div class="pgrid">
            <div class="pw2">
              <label><i class="bi bi-cash me-1"></i>Tunai (Cash)</label>
              <div class="piw">
                <i class="bi bi-cash"></i>
                <input type="number" id="cs<?=$id_b?>" name="bayar_cash"
                       class="bay-inp" data-id="<?=$id_b?>"
                       value="<?=$row['bayar_cash']??0?>" placeholder="0">
              </div>
            </div>
            <div class="pw2">
              <label><i class="bi bi-credit-card me-1"></i>Transfer</label>
              <div class="piw">
                <i class="bi bi-credit-card"></i>
                <input type="number" id="tf<?=$id_b?>" name="bayar_transfer"
                       class="bay-inp" data-id="<?=$id_b?>"
                       value="<?=$row['bayar_transfer']??0?>" placeholder="0">
              </div>
            </div>
          </div>

          <!-- Kembalian -->
          <div class="cbox" id="cb<?=$id_b?>">
            <div class="cl" id="cl<?=$id_b?>">Masukkan nominal pembayaran</div>
            <div class="cv" id="cv<?=$id_b?>">—</div>
          </div>

          <!-- Status -->
          <div class="ssel">
            <label><i class="bi bi-toggle-on me-1"></i>Status Pembayaran</label>
            <select name="status_pembayaran" id="ss<?=$id_b?>">
              <?php foreach(['pending'=>'Pending (Belum Bayar)','dp'=>'DP (Bayar Sebagian)','lunas'=>'Lunas','batal'=>'Batal'] as $v=>$lb):?>
              <option value="<?=$v?>" <?=$sp==$v?'selected':''?>><?=$lb?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div><!-- /modal-body -->

        <!-- FOOTER -->
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="bsub bsb">
            <i class="bi bi-check-circle-fill"></i> Simpan Pembayaran
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endwhile; /* end while booking */ ?>
        </tbody>
      </table>
    </div>
  </div><!-- /mcard -->
</div><!-- /pw -->

<!-- ═══════════ MODAL SCAN QR ═══════════ -->
<div class="modal fade" id="mScan" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-qr-code-scan me-2"></i>Scan QR Invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="qrr"></div>
        <p class="text-center text-muted small mt-3 mb-0">Arahkan kamera ke QR Code pada invoice customer</p>
      </div>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast-c"></div>

<!-- ═══════════ SCRIPTS ═══════════ -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
/* ════════════════════════════════════════════════════════
   1. DATA KARYAWAN — preloaded dari PHP sebagai JSON
   Digunakan untuk membangun baris petugas BARU dari scratch.
   TIDAK ada jQuery .clone() — ini fix utama bug multi-petugas.
════════════════════════════════════════════════════════ */
const EMPS = <?= $emp_json ?>;

/* Bangun string <option> untuk select karyawan */
function empOptions(selectedId) {
    let o = '<option value="">— Pilih Petugas —</option>';
    EMPS.forEach(function(e) {
        const s = String(e.id_employee) === String(selectedId) ? ' selected' : '';
        o += `<option value="${e.id_employee}"${s}>${e.nama_karyawan}</option>`;
    });
    return o;
}

/*
 * buildRow(dtlId)
 * Kembalikan HTML satu baris petugas baru.
 * Name attribute selalu benar: id_employee[dtlId][] dan komisi_persen[dtlId][]
 * Karena dibangun dari string — bukan .clone() — tidak ada risiko name hilang.
 */
function buildRow(dtlId) {
    return `
    <div class="prow">
        <select name="id_employee[${dtlId}][]">
            ${empOptions('')}
        </select>
        <div class="ppw">
            <input type="number" step="0.1" min="0" max="100"
                   name="komisi_persen[${dtlId}][]"
                   class="pi pct-inp" placeholder="0" data-d="${dtlId}">
            <span class="ps">%</span>
        </div>
        <button type="button" class="btn-dr"><i class="bi bi-x-lg"></i></button>
    </div>`;
}

/* ════════════════════════════════════════════════════════
   2. KOMISI PROGRESS
════════════════════════════════════════════════════════ */
function updateProgress(d) {
    const $c   = $(`#pc${d}`);
    const max  = parseFloat($c.data('max')) || 0;
    let total  = 0;
    $c.find('.pct-inp').each(function() { total += parseFloat($(this).val()) || 0; });
    const sisa = max - total;
    const pct  = max > 0 ? Math.min(100, (total / max) * 100) : 0;
    $(`#kf${d}`).css('width', pct + '%').toggleClass('over', total > max);
    $(`#kt${d}`).text(`${+total.toFixed(1)}% / ${max}% terisi · Sisa: ${+sisa.toFixed(1)}%`);
    $(`#kw${d}`).toggleClass('show', total > max);
    return total <= max;
}

function refreshDel($c) {
    const n = $c.find('.prow').length;
    $c.find('.btn-dr').prop('disabled', n <= 1);
}

/* ════════════════════════════════════════════════════════
   3. TOTAL BIAYA REAL-TIME
════════════════════════════════════════════════════════ */
$(document).on('input', '.harga-inp', function() {
    const bid = $(this).data('bid');
    let sum = 0;
    $(this).closest('form').find('.harga-inp').each(function() { sum += parseInt($(this).val()) || 0; });
    $(`#tv${bid}`).text('Rp ' + sum.toLocaleString('id-ID'));
});

/* ════════════════════════════════════════════════════════
   4. EVENT: input komisi %
════════════════════════════════════════════════════════ */
$(document).on('input', '.pct-inp', function() { updateProgress($(this).data('d')); });

/* ════════════════════════════════════════════════════════
   5. EVENT: Tambah Petugas
════════════════════════════════════════════════════════ */
$(document).on('click', '.btn-ar', function() {
    const d    = $(this).data('d');
    const $c   = $(`#pc${d}`);
    $c.append(buildRow(d));               // HTML baru dari scratch
    refreshDel($c);
    updateProgress(d);
    $c.find('.prow:last select').focus();
});

/* ════════════════════════════════════════════════════════
   6. EVENT: Hapus Petugas
════════════════════════════════════════════════════════ */
$(document).on('click', '.btn-dr', function() {
    const $c = $(this).closest('[id^="pc"]');
    $(this).closest('.prow').remove();
    refreshDel($c);
    updateProgress($c.data('d'));
});

/* ════════════════════════════════════════════════════════
   7. EVENT: Bagi Rata
════════════════════════════════════════════════════════ */
$(document).on('click', '.btn-sp', function() {
    const d   = $(this).data('d');
    const max = parseFloat($(this).data('max')) || 0;
    const $c  = $(`#pc${d}`);
    const n   = $c.find('.prow').length;
    if (!n) return;
    const per = +(max / n).toFixed(1);
    $c.find('.pct-inp').val(per);
    updateProgress(d);
});

/* ════════════════════════════════════════════════════════
   8. SUBMIT: soft-warn jika komisi melebihi batas
════════════════════════════════════════════════════════ */
$(document).on('submit', '.form-klg', function(e) {
    let warn = false;
    $(this).find('[id^="pc"]').each(function() {
        if (!updateProgress($(this).data('d'))) warn = true;
    });
    if (warn && !confirm('⚠️ Komisi melebihi batas. Tetap simpan?')) e.preventDefault();
});

/* ════════════════════════════════════════════════════════
   9. PEMBAYARAN: Kembalian real-time
════════════════════════════════════════════════════════ */
$(document).on('input', '.bay-inp', function() {
    const id      = $(this).data('id');
    const tagihan = parseInt($(`#tb${id}`).data('n')) || 0;
    const cash    = parseInt($(`#cs${id}`).val()) || 0;
    const trf     = parseInt($(`#tf${id}`).val()) || 0;
    const total   = cash + trf;
    const selisih = total - tagihan;
    const fmt     = n => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(n));

    const $b  = $(`#cb${id}`);
    const $lbl= $(`#cl${id}`);
    const $val= $(`#cv${id}`);
    const $ss = $(`#ss${id}`);

    if (selisih >= 0) {
        $lbl.text('Kembalian'); $val.text(fmt(selisih));
        $b.removeClass('bad').addClass('ok');
        if (tagihan > 0) $ss.val('lunas');
    } else {
        $lbl.text('Kekurangan'); $val.text(fmt(selisih));
        $b.removeClass('ok').addClass('bad');
        if (total > 0) $ss.val('dp'); else $ss.val('pending');
    }
});

/* ════════════════════════════════════════════════════════
   10. DATATABLE
════════════════════════════════════════════════════════ */
const dt = $('#tblB').DataTable({
    order: [[2,'desc']],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
    columnDefs: [{ orderable: false, targets: 7 }],
    drawCallback: function() {
        $('#live-n').text(this.api().rows({ filter: 'applied' }).count());
    }
});

/* Filter tanggal + status kerja */
$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    const min = $('#fMin').val(), max = $('#fMax').val(), st = $('#fSt').val();
    const dateNode = dt.cell(dataIndex, 2).node();
    const skNode   = dt.cell(dataIndex, 5).node();
    const date = dateNode ? dateNode.getAttribute('data-sort') : '';
    const sk   = skNode   ? skNode.getAttribute('data-sk')    : '';
    let dateOk = !min && !max || (!min && date <= max) || (date >= min && !max) || (date >= min && date <= max);
    let stOk   = !st || sk === st;
    return dateOk && stOk;
});
$('#fMin,#fMax,#fSt').on('change input', function() { dt.draw(); });
$('#btnRst').on('click', function() { $('#fMin,#fMax').val(''); $('#fSt').val(''); dt.draw(); });

/* ════════════════════════════════════════════════════════
   11. QR SCANNER
════════════════════════════════════════════════════════ */
let qr = null;
$('#mScan').on('shown.bs.modal', function() {
    qr = new Html5Qrcode('qrr');
    qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 220, height: 220 } },
        function(text) { dt.search(text).draw(); $('#mScan').modal('hide'); }
    ).catch(console.warn);
});
$('#mScan').on('hidden.bs.modal', function() {
    if (qr && qr.isScanning) qr.stop().then(() => qr.clear()).catch(() => {});
});

/* ════════════════════════════════════════════════════════
   12. NAV TOGGLE (mobile)
════════════════════════════════════════════════════════ */
document.getElementById('nt').addEventListener('click', function() {
    document.getElementById('nl').classList.toggle('open');
});

/* ════════════════════════════════════════════════════════
   13. TOAST
════════════════════════════════════════════════════════ */
function showToast(msg, type) {
    const ic = type === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
    const $t = $(`<div class="toast-i ${type}"><i class="bi ${ic} tic"></i><span>${msg}</span></div>`);
    $('#toast-c').append($t);
    setTimeout(() => {
        $t.css('animation', 'tOut .3s ease forwards');
        setTimeout(() => $t.remove(), 320);
    }, 3500);
}
<?php if ($toast): ?>showToast('<?= addslashes($toast) ?>', '<?= $toast_type ?>');<?php endif; ?>
</script>
</body>
</html>