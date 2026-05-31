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
//  AJAX HANDLER
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax_action'];

    // ── 1. SIMPAN KELOLA ────────────────────────
    if ($action === 'simpan_kelola') {
        $id_booking   = mysqli_real_escape_string($conn, trim($_POST['id_booking'] ?? ''));
        $status_kerja = mysqli_real_escape_string($conn, trim($_POST['status_kerja'] ?? 'menunggu'));

        if (!$id_booking) { echo json_encode(['success'=>false,'message'=>'ID Booking tidak valid']); exit; }

        // Update status kerja
        mysqli_query($conn, "UPDATE bookings SET status_kerja='$status_kerja' WHERE id_booking='$id_booking'");

        // Loop setiap detail layanan
        $detail_ids   = $_POST['detail_id']           ?? [];
        $subtotals    = $_POST['subtotal']             ?? [];
        $emp_utama    = $_POST['id_employee_utama']    ?? []; // petugas utama per detail
        $komisi_utama = $_POST['komisi_persen_utama']  ?? []; // komisi utama
        $extra_data   = $_POST['extra_data']           ?? []; // JSON: [{id_employee, persen_komisi}]

        foreach ($detail_ids as $i => $raw_detail_id) {
            $id_detail    = (int)$raw_detail_id;
            $harga_baru   = (float)($subtotals[$i] ?? 0);
            $id_emp_utama = (int)($emp_utama[$i] ?? 0);

            // Update booking_details
            $emp_val = $id_emp_utama > 0 ? $id_emp_utama : 'NULL';
            mysqli_query($conn, "UPDATE booking_details SET subtotal=$harga_baru, id_employee=$emp_val WHERE id_detail=$id_detail");

            // Hapus komisi lama
            mysqli_query($conn, "DELETE FROM booking_komisi WHERE id_detail=$id_detail");

            // Ambil komisi_persen max dari services untuk validasi
            $q_svc = mysqli_query($conn,
                "SELECT s.komisi_persen FROM booking_details bd
                 JOIN services s ON bd.id_service=s.id_service
                 WHERE bd.id_detail=$id_detail LIMIT 1");
            $svc_row = mysqli_fetch_assoc($q_svc);
            $max_komisi = (float)($svc_row['komisi_persen'] ?? 100);

            // Insert komisi petugas utama
            $pct_utama = (float)($komisi_utama[$i] ?? 0);
            $pct_utama = min($pct_utama, $max_komisi); // tidak melebihi max
            if ($id_emp_utama > 0 && $pct_utama > 0 && $harga_baru > 0) {
                $nominal = (int)round($harga_baru * $pct_utama / 100);
                $pct_db  = round($pct_utama, 2);
                mysqli_query($conn,
                    "INSERT INTO booking_komisi (id_booking, id_detail, id_employee, persen_komisi, nominal_komisi)
                     VALUES ('$id_booking', $id_detail, $id_emp_utama, $pct_db, $nominal)"
                );
            }

            // Insert komisi petugas tambahan
            $extras = json_decode($extra_data[$i] ?? '[]', true);
            if (is_array($extras)) {
                foreach ($extras as $ext) {
                    $ext_emp_id = (int)($ext['id_employee'] ?? 0);
                    $ext_pct    = (float)($ext['persen_komisi'] ?? 0);
                    $ext_pct    = min($ext_pct, $max_komisi); // tidak melebihi max
                    if ($ext_emp_id > 0 && $ext_emp_id !== $id_emp_utama && $ext_pct > 0 && $harga_baru > 0) {
                        $nominal_ext = (int)round($harga_baru * $ext_pct / 100);
                        $pct_ext_db  = round($ext_pct, 2);
                        mysqli_query($conn,
                            "INSERT INTO booking_komisi (id_booking, id_detail, id_employee, persen_komisi, nominal_komisi)
                             VALUES ('$id_booking', $id_detail, $ext_emp_id, $pct_ext_db, $nominal_ext)"
                        );
                    }
                }
            }
        }

        // Recalculate total_biaya
        $q_tot = mysqli_query($conn, "SELECT SUM(subtotal) AS tot FROM booking_details WHERE id_booking='$id_booking'");
        $r_tot = mysqli_fetch_assoc($q_tot);
        $new_total = (float)($r_tot['tot'] ?? 0);
        mysqli_query($conn, "UPDATE bookings SET total_biaya='$new_total' WHERE id_booking='$id_booking'");

        echo json_encode(['success'=>true,'message'=>'Perubahan berhasil disimpan.','new_total'=>$new_total]);
        exit;
    }

    // ── 2. SIMPAN PEMBAYARAN ──────────────────
    if ($action === 'simpan_bayar') {
        $id_booking  = mysqli_real_escape_string($conn, trim($_POST['id_booking'] ?? ''));
        $bayar_cash  = (float)($_POST['bayar_cash'] ?? 0);
        $bayar_tf    = (float)($_POST['bayar_transfer'] ?? 0);
        $total_bayar = $bayar_cash + $bayar_tf;
        $set_batal   = ($_POST['set_batal'] ?? '0') === '1';

        if (!$id_booking) { echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }

        $qt = mysqli_query($conn, "SELECT total_biaya FROM bookings WHERE id_booking='$id_booking'");
        $rt = mysqli_fetch_assoc($qt);
        $total_biaya = (float)($rt['total_biaya'] ?? 0);

        if ($set_batal) {
            $status_baru = 'batal';
        } elseif ($total_bayar <= 0) {
            $status_baru = 'pending';
        } elseif ($total_bayar < $total_biaya) {
            $status_baru = 'dp';
        } else {
            $status_baru = 'lunas';
        }

        $status_esc = mysqli_real_escape_string($conn, $status_baru);
        mysqli_query($conn,
            "UPDATE bookings SET
                bayar_cash='$bayar_cash',
                bayar_transfer='$bayar_tf',
                jumlah_terbayar='$total_bayar',
                status_pembayaran='$status_esc'
             WHERE id_booking='$id_booking'"
        );

        echo json_encode(['success'=>true,'message'=>'Pembayaran berhasil disimpan.','status'=>$status_baru]);
        exit;
    }

    // ── 3. GET DETAIL KELOLA ──────────────────
    if ($action === 'get_detail') {
        $id_booking = mysqli_real_escape_string($conn, trim($_POST['id_booking'] ?? ''));
        if (!$id_booking) { echo json_encode(['error'=>'ID tidak valid']); exit; }

        $qb = mysqli_query($conn, "SELECT * FROM bookings WHERE id_booking='$id_booking'");
        $booking = mysqli_fetch_assoc($qb);
        if (!$booking) { echo json_encode(['error'=>'Booking tidak ditemukan']); exit; }

        $qd = mysqli_query($conn,
            "SELECT d.id_detail, d.id_service, d.id_employee, d.subtotal,
                    s.nama_layanan, s.harga AS harga_default, s.komisi_persen AS komisi_max
             FROM booking_details d
             JOIN services s ON d.id_service=s.id_service
             WHERE d.id_booking='$id_booking'
             ORDER BY d.id_detail ASC"
        );
        $details = [];
        while ($r = mysqli_fetch_assoc($qd)) {
            // Ambil komisi list (petugas utama + tambahan)
            $qk = mysqli_query($conn,
                "SELECT bk.id_employee, bk.persen_komisi, bk.nominal_komisi, e.nama_karyawan
                 FROM booking_komisi bk
                 JOIN employees e ON bk.id_employee=e.id_employee
                 WHERE bk.id_detail={$r['id_detail']}
                 ORDER BY bk.id_komisi ASC"
            );
            $r['komisi_list'] = [];
            while ($rk = mysqli_fetch_assoc($qk)) $r['komisi_list'][] = $rk;
            $details[] = $r;
        }

        $qe = mysqli_query($conn, "SELECT id_employee, nama_karyawan, spesialisasi FROM employees WHERE status='active' ORDER BY nama_karyawan");
        $employees = [];
        while ($re = mysqli_fetch_assoc($qe)) $employees[] = $re;

        echo json_encode(['booking'=>$booking,'details'=>$details,'employees'=>$employees]);
        exit;
    }

    // ── 4. GET DATA BAYAR ─────────────────────
    if ($action === 'get_bayar') {
        $id_booking = mysqli_real_escape_string($conn, trim($_POST['id_booking'] ?? ''));
        $qb = mysqli_query($conn, "SELECT * FROM bookings WHERE id_booking='$id_booking'");
        $booking = mysqli_fetch_assoc($qb);
        if (!$booking) { echo json_encode(['error'=>'Booking tidak ditemukan']); exit; }

        $qd = mysqli_query($conn,
            "SELECT s.nama_layanan, d.subtotal
             FROM booking_details d JOIN services s ON d.id_service=s.id_service
             WHERE d.id_booking='$id_booking' ORDER BY d.id_detail ASC"
        );
        $layanan = [];
        while ($r = mysqli_fetch_assoc($qd)) $layanan[] = $r;

        echo json_encode(['booking'=>$booking,'layanan'=>$layanan]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali']);
    exit;
}

// ═══════════════════════════════════════════════
//  STATISTIK HARIAN
// ═══════════════════════════════════════════════
$today = date('Y-m-d');

$q_stat = mysqli_query($conn,
    "SELECT
        COUNT(*) AS jml_booking,
        COALESCE(SUM(CASE WHEN status_pembayaran='lunas' THEN total_biaya ELSE 0 END), 0) AS omset,
        COALESCE(SUM(jumlah_terbayar), 0) AS uang_masuk,
        COALESCE(SUM(CASE WHEN status_pembayaran IN ('pending','dp') THEN (total_biaya - jumlah_terbayar) ELSE 0 END), 0) AS piutang
     FROM bookings WHERE tgl_booking='$today' AND status_pembayaran != 'batal'"
);
$stat = mysqli_fetch_assoc($q_stat);

// Pipeline counts (semua tanggal aktif)
$cnt = [];
foreach (['menunggu','diproses','selesai'] as $sk) {
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM bookings WHERE status_kerja='$sk' AND status_pembayaran != 'batal'"));
    $cnt[$sk] = $r['c'];
}

// ═══════════════════════════════════════════════
//  FILTER & PAGINATION
// ═══════════════════════════════════════════════
$f_tgl    = trim($_GET['tgl']          ?? '');
$f_sk     = trim($_GET['status_kerja'] ?? '');
$f_sb     = trim($_GET['status_bayar'] ?? '');
$f_emp    = trim($_GET['id_employee']  ?? '');
$f_q      = trim($_GET['q']            ?? '');

$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where = ["1=1"];
if ($f_tgl) $where[] = "b.tgl_booking='"  .mysqli_real_escape_string($conn,$f_tgl)."'";
if ($f_sk)  $where[] = "b.status_kerja='" .mysqli_real_escape_string($conn,$f_sk). "'";
if ($f_sb)  $where[] = "b.status_pembayaran='".mysqli_real_escape_string($conn,$f_sb)."'";
if ($f_emp) $where[] = "EXISTS(SELECT 1 FROM booking_details bd2 WHERE bd2.id_booking=b.id_booking AND bd2.id_employee=".(int)$f_emp.")";
if ($f_q) {
    $qs = mysqli_real_escape_string($conn,$f_q);
    $where[] = "(b.id_booking LIKE '%$qs%' OR b.nama_customer LIKE '%$qs%' OR b.whatsapp_customer LIKE '%$qs%')";
}
$where_sql = implode(' AND ', $where);

$total_rows  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM bookings b WHERE $where_sql"))['c'];
$total_pages = (int)ceil($total_rows / $per_page);

// Main query — ambil petugas dari booking_komisi (lebih akurat)
$sql_main = "
SELECT
    b.*,
    GROUP_CONCAT(DISTINCT s.nama_layanan ORDER BY d.id_detail SEPARATOR '||') AS daftar_layanan,
    GROUP_CONCAT(DISTINCT bk_emp.nama_karyawan ORDER BY bk_emp.nama_karyawan SEPARATOR '||') AS daftar_petugas_komisi
FROM bookings b
LEFT JOIN booking_details d ON b.id_booking=d.id_booking
LEFT JOIN services s ON d.id_service=s.id_service
LEFT JOIN booking_komisi bk ON d.id_detail=bk.id_detail
LEFT JOIN employees bk_emp ON bk.id_employee=bk_emp.id_employee
WHERE $where_sql
GROUP BY b.id_booking
ORDER BY b.created_at DESC
LIMIT $per_page OFFSET $offset";

$q_main = mysqli_query($conn, $sql_main);

// Karyawan aktif untuk filter & modal
$q_emp_all = mysqli_query($conn,"SELECT id_employee,nama_karyawan,spesialisasi FROM employees WHERE status='active' ORDER BY nama_karyawan");
$all_employees = [];
while ($re=mysqli_fetch_assoc($q_emp_all)) $all_employees[]=$re;
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Amoy Salon</title>
<link rel="icon" type="image/png" href="../asset/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* ═══════════════════════════════════════
   DESIGN TOKENS — LIGHT (default)
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
}
/* ═══════════════════════════════════════
   ACCENT PALETTE (theme-independent)
═══════════════════════════════════════ */
:root {
    --accent:       #6c5ce7;
    --accent-2:     #a29bfe;
    --accent-bg:    rgba(108,92,231,.1);
    --accent-border:rgba(108,92,231,.25);
    --green:        #00b894;
    --green-bg:     rgba(0,184,148,.1);
    --green-border: rgba(0,184,148,.25);
    --yellow:       #f39c12;
    --yellow-bg:    rgba(243,156,18,.1);
    --yellow-border:rgba(243,156,18,.25);
    --red:          #e74c3c;
    --red-bg:       rgba(231,76,60,.1);
    --red-border:   rgba(231,76,60,.25);
    --blue:         #3498db;
    --blue-bg:      rgba(52,152,219,.1);
    --blue-border:  rgba(52,152,219,.25);
    --pink:         #e84393;
    --pink-bg:      rgba(232,67,147,.1);
    --radius:       10px;
    --radius-lg:    14px;
    --radius-xl:    18px;
    --font:         'Nunito', sans-serif;
    --mono:         'Fira Code', monospace;
    --trans:        all .18s ease;
}

/* ═══════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    font-family:var(--font);
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    font-size:14px;
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
    transition:background .25s ease,color .25s ease;
}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-input);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:var(--accent)}

/* ═══════════════════════════════════════
   NAVBAR
═══════════════════════════════════════ */
.navbar{
    background:var(--nav-bg)!important;
    padding:.5rem 1.25rem;
    box-shadow:0 2px 12px rgba(0,0,0,.15);
    position:sticky;top:0;z-index:1040;
    transition:background .25s;
}
.navbar-brand{
    font-weight:900;font-size:1.05rem;letter-spacing:-.3px;
    color:var(--nav-active)!important;
    display:flex;align-items:center;gap:8px;
}
.brand-icon{
    width:32px;height:32px;
    background:linear-gradient(135deg,var(--accent),var(--pink));
    border-radius:8px;display:flex;align-items:center;justify-content:center;
    font-size:15px;flex-shrink:0;
}
.nav-link{
    color:var(--nav-text)!important;
    font-size:.85rem;font-weight:600;
    padding:.4rem .75rem!important;
    border-radius:7px;
    transition:var(--trans);
    display:flex;align-items:center;gap:5px;
}
.nav-link:hover,.nav-link.active{
    background:rgba(255,255,255,.08);
    color:var(--nav-active)!important;
}
.nav-link.text-warning{color:#f39c12!important}
.nav-link.text-info{color:#74b9ff!important}
.btn-booking-online{
    font-size:.8rem;font-weight:700;padding:.3rem .9rem;
    border:1px solid rgba(255,255,255,.2);
    border-radius:7px;color:#fff!important;
    transition:var(--trans);
}
.btn-booking-online:hover{background:rgba(255,255,255,.12)}
.btn-logout{color:#ff6b6b!important;font-weight:700}
.btn-logout:hover{background:rgba(255,107,107,.12)!important}

/* Theme Toggle Button */
.theme-toggle{
    width:36px;height:36px;border-radius:8px;
    border:1px solid rgba(255,255,255,.15);
    background:rgba(255,255,255,.06);
    color:#fff;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    font-size:16px;transition:var(--trans);
}
.theme-toggle:hover{background:rgba(255,255,255,.15)}

/* ═══════════════════════════════════════
   PAGE LAYOUT
═══════════════════════════════════════ */
.page-container{
    max-width:1600px;margin:0 auto;
    padding:28px 20px;
}
.page-head{
    display:flex;align-items:flex-end;justify-content:space-between;
    flex-wrap:wrap;gap:12px;margin-bottom:28px;
}
.page-title{font-size:1.4rem;font-weight:900;letter-spacing:-.4px;margin:0}
.page-subtitle{font-size:.82rem;color:var(--text-muted);margin:2px 0 0}
.date-chip{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 14px;border-radius:20px;
    background:var(--bg-card);border:1px solid var(--border);
    font-size:.78rem;font-weight:600;color:var(--text-sub);
    box-shadow:var(--shadow-sm);
}

/* ═══════════════════════════════════════
   STAT CARDS
═══════════════════════════════════════ */
.stat-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;margin-bottom:20px;
}
@media(max-width:1100px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:576px){.stat-grid{grid-template-columns:1fr 1fr}}

.stat-card{
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:var(--radius-lg);
    padding:18px 20px 16px;
    position:relative;overflow:hidden;
    box-shadow:var(--shadow-card);
    transition:var(--trans);
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.stat-card::after{
    content:'';position:absolute;
    top:-30px;right:-30px;
    width:90px;height:90px;border-radius:50%;
    background:var(--c-soft);opacity:.6;
}
.stat-icon{
    width:40px;height:40px;border-radius:10px;
    background:var(--c-soft);color:var(--c);
    display:flex;align-items:center;justify-content:center;
    font-size:18px;margin-bottom:12px;
    position:relative;z-index:1;
}
.stat-label{
    font-size:.72rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.6px;color:var(--text-muted);margin-bottom:2px;
}
.stat-value{
    font-size:1.35rem;font-weight:900;color:var(--text);
    font-family:var(--mono);letter-spacing:-.5px;line-height:1.2;
}
.stat-value.sm{font-size:1rem}
.stat-note{font-size:.73rem;color:var(--text-muted);margin-top:3px}

/* ═══════════════════════════════════════
   PIPELINE CARDS
═══════════════════════════════════════ */
.pipe-grid{
    display:grid;grid-template-columns:repeat(3,1fr);
    gap:14px;margin-bottom:20px;
}
@media(max-width:768px){.pipe-grid{grid-template-columns:1fr}}
.pipe-card{
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:14px 18px;
    display:flex;align-items:center;gap:14px;
    box-shadow:var(--shadow-card);transition:var(--trans);
    border-left:4px solid var(--c);
}
.pipe-card:hover{background:var(--bg-hover)}
.pipe-dot{
    width:42px;height:42px;border-radius:10px;
    background:var(--c-soft);color:var(--c);
    display:flex;align-items:center;justify-content:center;
    font-size:20px;flex-shrink:0;
}
.pipe-count{
    font-size:1.8rem;font-weight:900;font-family:var(--mono);
    color:var(--c);line-height:1;
}
.pipe-label{font-size:.78rem;font-weight:700;color:var(--text-sub);margin-top:2px}

/* ═══════════════════════════════════════
   FILTER BAR
═══════════════════════════════════════ */
.filter-card{
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:18px 20px;
    margin-bottom:18px;box-shadow:var(--shadow-card);
}
.filter-title{
    font-size:.73rem;font-weight:800;text-transform:uppercase;
    letter-spacing:.7px;color:var(--text-muted);margin-bottom:14px;
    display:flex;align-items:center;gap:6px;
}

/* ═══════════════════════════════════════
   FORM CONTROLS
═══════════════════════════════════════ */
.form-control,.form-select{
    background:var(--bg-input)!important;
    border:1px solid var(--border-input)!important;
    color:var(--text)!important;
    border-radius:8px!important;
    font-size:.845rem!important;
    font-family:var(--font)!important;
    padding:8px 12px!important;
    transition:var(--trans)!important;
}
.form-control:focus,.form-select:focus{
    border-color:var(--accent)!important;
    box-shadow:0 0 0 3px var(--accent-bg)!important;
    outline:none!important;
}
.form-control::placeholder{color:var(--text-muted)!important}
.form-select option{background:var(--bg-card);color:var(--text)}
.form-label{
    font-size:.78rem;font-weight:700;color:var(--text-sub);
    margin-bottom:5px;display:block;
}
.input-group-text{
    background:var(--bg-hover)!important;
    border:1px solid var(--border-input)!important;
    color:var(--text-sub)!important;font-size:.845rem!important;
}

/* ═══════════════════════════════════════
   BUTTONS
═══════════════════════════════════════ */
.btn{
    font-family:var(--font)!important;
    font-weight:700!important;font-size:.82rem!important;
    border-radius:8px!important;
    transition:var(--trans)!important;
    display:inline-flex!important;align-items:center!important;gap:5px!important;
}
.btn-primary{
    background:var(--accent)!important;border:none!important;
    color:#fff!important;padding:8px 18px!important;
}
.btn-primary:hover{background:#5a4bd1!important;transform:translateY(-1px);box-shadow:0 4px 14px rgba(108,92,231,.35)!important}
.btn-outline-secondary{
    background:transparent!important;
    border:1px solid var(--border-input)!important;
    color:var(--text-sub)!important;padding:7px 14px!important;
}
.btn-outline-secondary:hover{background:var(--bg-hover)!important;color:var(--text)!important}
.btn-light-custom{
    background:var(--bg-hover)!important;border:1px solid var(--border)!important;
    color:var(--text-sub)!important;padding:7px 14px!important;
}
.btn-light-custom:hover{background:var(--border)!important}
.btn-sm{padding:5px 12px!important;font-size:.78rem!important}
.btn-xs{padding:3px 9px!important;font-size:.75rem!important;border-radius:6px!important}
.btn-reset{
    background:transparent!important;border:1px solid var(--border-input)!important;
    color:var(--text-muted)!important;
}
.btn-reset:hover{border-color:var(--red)!important;color:var(--red)!important;background:var(--red-bg)!important}

.btn-act-kelola{background:var(--blue-bg)!important;border:1px solid var(--blue-border)!important;color:var(--blue)!important}
.btn-act-kelola:hover{background:var(--blue)!important;color:#fff!important}
.btn-act-bayar{background:var(--green-bg)!important;border:1px solid var(--green-border)!important;color:var(--green)!important}
.btn-act-bayar:hover{background:var(--green)!important;color:#fff!important}
.btn-act-nota{background:var(--yellow-bg)!important;border:1px solid var(--yellow-border)!important;color:var(--yellow)!important}
.btn-act-nota:hover{background:var(--yellow)!important;color:#fff!important}
.btn-act-hapus{background:var(--red-bg)!important;border:1px solid var(--red-border)!important;color:var(--red)!important}
.btn-act-hapus:hover{background:var(--red)!important;color:#fff!important}

/* ═══════════════════════════════════════
   TABLE
═══════════════════════════════════════ */
.table-card{
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:var(--radius-xl);overflow:hidden;
    box-shadow:var(--shadow-card);
}
.table-head-row{
    padding:16px 20px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:10px;
}
.table-card-title{
    font-size:.95rem;font-weight:900;color:var(--text);margin:0;
}
.table-count-chip{
    font-size:.74rem;font-family:var(--mono);font-weight:500;
    color:var(--text-muted);background:var(--bg-stripe);
    border:1px solid var(--border);padding:3px 10px;border-radius:20px;
}
.table-scroll{overflow-x:auto}
table.dash-table{
    width:100%;border-collapse:collapse;
    min-width:1120px;
}
table.dash-table thead tr{
    background:var(--bg-stripe);
    border-bottom:2px solid var(--border);
}
table.dash-table thead th{
    font-size:.72rem;font-weight:800;text-transform:uppercase;
    letter-spacing:.6px;color:var(--text-muted);
    padding:12px 16px;white-space:nowrap;
}
table.dash-table tbody tr{
    border-bottom:1px solid var(--border);
    transition:background .12s;
}
table.dash-table tbody tr:last-child{border-bottom:none}
table.dash-table tbody tr:hover{background:var(--bg-stripe)}
table.dash-table tbody td{
    padding:12px 16px;vertical-align:middle;
    font-size:.84rem;color:var(--text);
}

/* Column specifics */
.td-id{font-family:var(--mono);font-size:.76rem;color:var(--accent);font-weight:600}
.td-date{font-size:.73rem;color:var(--text-muted);margin-top:1px}
.td-name{font-weight:800;font-size:.88rem}
.td-wa{font-size:.75rem;color:var(--text-muted);margin-top:1px}
.td-sched-date{font-weight:700;font-size:.85rem}
.td-sched-time{font-size:.76rem;font-family:var(--mono);color:var(--text-muted)}
.td-price{font-family:var(--mono);font-weight:800;font-size:.88rem}
.td-paid{font-size:.75rem;color:var(--text-muted);font-family:var(--mono)}
.td-lack{font-size:.73rem;color:var(--red);font-family:var(--mono)}

/* Service Pills */
.svc-pills{display:flex;flex-direction:column;gap:3px}
.svc-pill{
    display:inline-block;font-size:.72rem;font-weight:600;
    padding:2px 8px;border-radius:20px;white-space:nowrap;
    background:var(--bg-stripe);border:1px solid var(--border);
    color:var(--text-sub);
}
.svc-more{
    background:var(--accent-bg);border-color:var(--accent-border);
    color:var(--accent);
}

/* Petugas cluster in table */
.petugas-cluster{display:flex;flex-direction:column;gap:4px;min-width:140px}
.petugas-item{
    display:inline-flex;align-items:center;gap:5px;
    font-size:.78rem;font-weight:700;
    color:var(--text-sub);
}
.petugas-dot{
    width:7px;height:7px;border-radius:50%;
    flex-shrink:0;
}
.petugas-empty{font-size:.76rem;color:var(--text-muted);font-style:italic}

/* Action column */
.act-group{display:flex;flex-direction:column;gap:4px;align-items:flex-start}

/* ═══════════════════════════════════════
   BADGES
═══════════════════════════════════════ */
.badge-s{
    display:inline-flex;align-items:center;gap:5px;
    padding:3px 10px;border-radius:20px;
    font-size:.74rem;font-weight:700;white-space:nowrap;
}
.badge-s::before{
    content:'';width:6px;height:6px;border-radius:50%;
    background:currentColor;flex-shrink:0;
}
.bs-menunggu{background:var(--yellow-bg);color:var(--yellow)}
.bs-diproses{background:var(--blue-bg);color:var(--blue)}
.bs-selesai{background:var(--green-bg);color:var(--green)}
.bs-pending{background:rgba(148,153,184,.12);color:var(--text-sub)}
.bs-dp{background:var(--yellow-bg);color:var(--yellow)}
.bs-lunas{background:var(--green-bg);color:var(--green)}
.bs-batal{background:var(--red-bg);color:var(--red)}

/* ═══════════════════════════════════════
   PAGINATION
═══════════════════════════════════════ */
.pg-bar{
    padding:14px 20px;border-top:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:10px;
}
.pg-info{font-size:.76rem;color:var(--text-muted);font-family:var(--mono)}
.pg-links{display:flex;gap:4px;list-style:none;padding:0;margin:0}
.pg-links li a,.pg-links li span{
    display:flex;align-items:center;justify-content:center;
    width:33px;height:33px;border-radius:7px;
    font-size:.8rem;font-weight:700;font-family:var(--mono);
    background:var(--bg-input);border:1px solid var(--border);
    color:var(--text-sub);text-decoration:none;transition:var(--trans);
}
.pg-links li a:hover{border-color:var(--accent);color:var(--accent)}
.pg-links li.active span{background:var(--accent);border-color:var(--accent);color:#fff}
.pg-links li.disabled span{opacity:.3;pointer-events:none}

/* ═══════════════════════════════════════
   MODALS
═══════════════════════════════════════ */
.modal-content{
    background:var(--bg-card)!important;
    border:1px solid var(--border)!important;
    border-radius:var(--radius-xl)!important;
    color:var(--text)!important;overflow:hidden;
    box-shadow:var(--shadow-lg)!important;
}
.modal-header{
    background:var(--bg-stripe)!important;
    border-bottom:1px solid var(--border)!important;
    padding:16px 22px!important;
}
.modal-title{font-size:.95rem!important;font-weight:800!important;color:var(--text)!important}
.modal-subtitle{font-size:.75rem;color:var(--text-muted);font-family:var(--mono);margin-top:2px}
.btn-close{filter:var(--close-filter,none)}
[data-theme="dark"] .btn-close{filter:invert(1) brightness(.6)}
.modal-body{padding:22px 22px!important}
.modal-footer{
    background:var(--bg-stripe)!important;
    border-top:1px solid var(--border)!important;
    padding:14px 22px!important;gap:8px!important;
}
.modal-loading{
    display:flex;flex-direction:column;align-items:center;
    justify-content:center;padding:48px 24px;gap:12px;
    color:var(--text-muted);font-size:.85rem;
}
.spinner-s{
    width:32px;height:32px;border:3px solid var(--border);
    border-top-color:var(--accent);border-radius:50%;
    animation:spin .65s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── KELOLA MODAL ── */
.k-info-box{
    background:var(--bg-input);border:1px solid var(--border);
    border-radius:var(--radius);padding:14px 16px;margin-bottom:18px;
}
.k-section-title{
    font-size:.73rem;font-weight:800;text-transform:uppercase;
    letter-spacing:.6px;color:var(--text-muted);margin-bottom:12px;
    display:flex;align-items:center;gap:6px;
}
.detail-box{
    background:var(--bg-input);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:16px;margin-bottom:12px;
    position:relative;
}
.detail-box-header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:14px;flex-wrap:wrap;gap:8px;
}
.detail-svc-name{
    display:flex;align-items:center;gap:8px;
    font-size:.88rem;font-weight:800;color:var(--text);
}
.svc-num{
    width:22px;height:22px;border-radius:50%;
    background:var(--accent-bg);color:var(--accent);
    display:flex;align-items:center;justify-content:center;
    font-size:.7rem;font-weight:800;font-family:var(--mono);flex-shrink:0;
}
.komisi-max-badge{
    font-size:.73rem;font-weight:700;
    background:var(--yellow-bg);color:var(--yellow);
    border:1px solid var(--yellow-border);
    padding:2px 9px;border-radius:20px;
}

/* Petugas row dalam modal kelola */
.petugas-section{
    border:1px solid var(--border);border-radius:var(--radius);
    padding:12px 14px;margin-top:10px;
}
.petugas-section-title{
    font-size:.73rem;font-weight:800;color:var(--text-muted);
    text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;
}
.p-row{
    display:flex;align-items:center;gap:8px;
    padding:8px 10px;border-radius:8px;
    background:var(--bg-card);border:1px solid var(--border);
    margin-bottom:7px;
    flex-wrap:wrap;
}
.p-row:last-child{margin-bottom:0}
.p-label{
    font-size:.75rem;font-weight:700;color:var(--text-sub);
    min-width:65px;flex-shrink:0;
}
.p-name{font-size:.82rem;font-weight:800;color:var(--text);flex:1;min-width:100px}
.p-komisi-wrap{
    display:flex;align-items:center;gap:6px;
    flex-shrink:0;
}
.p-komisi-input{
    width:72px!important;text-align:right!important;
    font-family:var(--mono)!important;font-weight:700!important;
    padding:4px 8px!important;font-size:.8rem!important;
}
.p-nominal{
    font-size:.75rem;font-family:var(--mono);font-weight:600;
    color:var(--green);min-width:90px;
}
.p-remove{
    cursor:pointer;color:var(--red);opacity:.6;font-size:16px;
    transition:opacity .15s;flex-shrink:0;
    background:none;border:none;padding:0 2px;line-height:1;
}
.p-remove:hover{opacity:1}

.add-petugas-row{
    display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;
}
.komisi-total-bar{
    background:var(--green-bg);border:1px solid var(--green-border);
    border-radius:8px;padding:8px 12px;
    display:flex;justify-content:space-between;align-items:center;
    font-size:.78rem;margin-top:10px;
}
.komisi-total-label{font-weight:700;color:var(--text-sub)}
.komisi-total-val{font-family:var(--mono);font-weight:800;color:var(--green);font-size:.88rem}

/* ── BAYAR MODAL ── */
.bill-box{
    background:var(--bg-input);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:16px;margin-bottom:16px;
}
.bill-row{
    display:flex;justify-content:space-between;align-items:center;
    padding:4px 0;font-size:.84rem;
}
.bill-row.total-row{
    border-top:2px solid var(--border);margin-top:6px;
    padding-top:10px;font-weight:900;font-size:1rem;
}
.bill-amount{font-family:var(--mono);font-weight:700}
.pay-result{
    padding:12px 16px;border-radius:10px;
    text-align:center;font-family:var(--mono);
    font-weight:800;font-size:.88rem;margin-top:12px;display:none;
}
.pay-kembalian{background:var(--green-bg);border:1px solid var(--green-border);color:var(--green)}
.pay-kurang{background:var(--yellow-bg);border:1px solid var(--yellow-border);color:var(--yellow)}
.pay-lunas-exact{background:var(--accent-bg);border:1px solid var(--accent-border);color:var(--accent)}
.batal-warning{
    background:var(--red-bg);border:1px solid var(--red-border);
    border-radius:8px;padding:10px 14px;
    font-size:.8rem;font-weight:700;color:var(--red);
    display:none;margin-top:10px;
}

/* ═══════════════════════════════════════
   TOAST
═══════════════════════════════════════ */
.toast-wrap{
    position:fixed;bottom:22px;right:22px;z-index:9999;
    display:flex;flex-direction:column;gap:8px;pointer-events:none;
}
.toast-item{
    display:flex;align-items:center;gap:10px;
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:12px;padding:12px 16px;
    min-width:260px;max-width:340px;
    box-shadow:var(--shadow-lg);
    font-size:.84rem;font-weight:600;
    animation:toastIn .3s ease;
    pointer-events:all;
}
.toast-item.ok{border-left:3px solid var(--green)}
.toast-item.err{border-left:3px solid var(--red)}
.toast-ic-ok{color:var(--green);font-size:17px}
.toast-ic-err{color:var(--red);font-size:17px}
@keyframes toastIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}

/* ═══════════════════════════════════════
   OVERLAY LOADER
═══════════════════════════════════════ */
#gLoader{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,.45);z-index:9998;
    align-items:center;justify-content:center;
}
#gLoader.on{display:flex}
#gLoader .spinner-s{width:40px;height:40px;border-width:4px}

/* ═══════════════════════════════════════
   SECTION DIVIDER
═══════════════════════════════════════ */
.sdivider{height:1px;background:var(--border);margin:16px 0}

/* ═══════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════ */
.empty-row td{padding:56px 20px!important;text-align:center}
.empty-icon{font-size:2.8rem;opacity:.25;margin-bottom:12px}
.empty-txt{color:var(--text-muted);font-size:.85rem}

/* ═══════════════════════════════════════
   RESPONSIVE TWEAKS
═══════════════════════════════════════ */
@media(max-width:768px){
    .page-container{padding:16px 12px}
    .page-head{flex-direction:column;align-items:flex-start}
    .stat-grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>

<!-- LOADER -->
<div id="gLoader"><div class="spinner-s"></div></div>

<!-- TOAST -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ════════════════════════════
     NAVBAR
════════════════════════════ -->
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
        <?php if($user_level<=2): ?>
        <li class="nav-item"><a class="nav-link active" href="admin.php"><i class="bi bi-calendar2-check"></i>Booking</a></li>
        <?php endif; ?>
        <?php if($user_level<=1): ?>
        <li class="nav-item"><a class="nav-link text-warning" href="master_data.php"><i class="bi bi-gear"></i>Master Data</a></li>
        <?php endif; ?>
        <?php if($user_level<=2): ?>
        <li class="nav-item"><a class="nav-link text-info" href="laporan.php"><i class="bi bi-bar-chart-line"></i>Pendapatan</a></li>
        <li class="nav-item"><a class="nav-link" href="komisi.php"><i class="bi bi-cash-coin"></i>Komisi</a></li>
        <li class="nav-item"><a class="nav-link text-info" href="laporan_pembayaran.php"><i class="bi bi-book-half"></i>Pembukuan</a></li>
        <?php endif; ?>
        <?php if($user_level<=1): ?>
        <li class="nav-item"><a class="nav-link text-warning" href="../class/user_manage.php"><i class="bi bi-people"></i>Akun</a></li>
        <?php endif; ?>
        <li class="nav-item"><div class="nav-divider d-none d-lg-block" style="width:1px;height:22px;background:rgba(255,255,255,.12);margin:0 4px"></div></li>
        <li class="nav-item"><a href="../index.php" target="_blank" class="nav-link btn-booking-online"><i class="bi bi-globe2"></i>Booking Online</a></li>
        <li class="nav-item d-none d-lg-flex align-items-center ms-2">
          <button class="theme-toggle" onclick="toggleTheme()" id="themeBtnDesktop" title="Ganti Tema">🌙</button>
        </li>
        <li class="nav-item"><a class="nav-link btn-logout" href="../class/logout.php" onclick="return confirm('Yakin keluar?')"><i class="bi bi-box-arrow-right"></i>Keluar</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- ════════════════════════════
     PAGE CONTENT
════════════════════════════ -->
<div class="page-container">

  <!-- PAGE HEAD -->
  <div class="page-head">
    <div>
      <h1 class="page-title">Dashboard Booking</h1>
      <p class="page-subtitle">Selamat datang, <strong><?=htmlspecialchars($user_nama)?></strong> — Kelola semua transaksi salon</p>
    </div>
    <div class="date-chip"><i class="bi bi-calendar3"></i><?=date('l, d F Y')?></div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-grid">
    <div class="stat-card" style="--c:var(--accent);--c-soft:var(--accent-bg)">
      <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
      <div class="stat-label">Booking Hari Ini</div>
      <div class="stat-value"><?=number_format($stat['jml_booking']??0)?></div>
      <div class="stat-note"><?=date('d M Y')?></div>
    </div>
    <div class="stat-card" style="--c:var(--green);--c-soft:var(--green-bg)">
      <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="stat-label">Omset Hari Ini</div>
      <div class="stat-value sm">Rp&nbsp;<?=number_format($stat['omset']??0,0,',','.')?></div>
      <div class="stat-note">Transaksi lunas saja</div>
    </div>
    <div class="stat-card" style="--c:var(--blue);--c-soft:var(--blue-bg)">
      <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
      <div class="stat-label">Uang Masuk</div>
      <div class="stat-value sm">Rp&nbsp;<?=number_format($stat['uang_masuk']??0,0,',','.')?></div>
      <div class="stat-note">Total terbayar hari ini</div>
    </div>
    <div class="stat-card" style="--c:var(--red);--c-soft:var(--red-bg)">
      <div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div>
      <div class="stat-label">Total Piutang</div>
      <div class="stat-value sm">Rp&nbsp;<?=number_format($stat['piutang']??0,0,',','.')?></div>
      <div class="stat-note">Belum lunas hari ini</div>
    </div>
  </div>

  <!-- PIPELINE -->
  <div class="pipe-grid">
    <div class="pipe-card" style="--c:var(--yellow);--c-soft:var(--yellow-bg)">
      <div class="pipe-dot"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="pipe-count"><?=$cnt['menunggu']?></div>
        <div class="pipe-label">Menunggu Pelayanan</div>
      </div>
    </div>
    <div class="pipe-card" style="--c:var(--blue);--c-soft:var(--blue-bg)">
      <div class="pipe-dot"><i class="bi bi-scissors"></i></div>
      <div>
        <div class="pipe-count"><?=$cnt['diproses']?></div>
        <div class="pipe-label">Sedang Diproses</div>
      </div>
    </div>
    <div class="pipe-card" style="--c:var(--green);--c-soft:var(--green-bg)">
      <div class="pipe-dot"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="pipe-count"><?=$cnt['selesai']?></div>
        <div class="pipe-label">Selesai</div>
      </div>
    </div>
  </div>

  <!-- FILTER -->
  <div class="filter-card">
    <div class="filter-title"><i class="bi bi-funnel"></i>Filter &amp; Pencarian</div>
    <form method="GET" id="filterForm">
      <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
          <label class="form-label">Cari Booking / Customer</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control" placeholder="ID, Nama, atau WA..." value="<?=htmlspecialchars($f_q)?>">
          </div>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label">Tanggal</label>
          <input type="date" name="tgl" class="form-control" value="<?=htmlspecialchars($f_tgl)?>">
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Status Kerja</label>
          <select name="status_kerja" class="form-select">
            <option value="">Semua</option>
            <option value="menunggu" <?=$f_sk=='menunggu'?'selected':''?>>Menunggu</option>
            <option value="diproses" <?=$f_sk=='diproses'?'selected':''?>>Diproses</option>
            <option value="selesai"  <?=$f_sk=='selesai' ?'selected':''?>>Selesai</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Status Bayar</label>
          <select name="status_bayar" class="form-select">
            <option value="">Semua</option>
            <option value="pending" <?=$f_sb=='pending'?'selected':''?>>Pending</option>
            <option value="dp"      <?=$f_sb=='dp'     ?'selected':''?>>DP</option>
            <option value="lunas"   <?=$f_sb=='lunas'  ?'selected':''?>>Lunas</option>
            <option value="batal"   <?=$f_sb=='batal'  ?'selected':''?>>Batal</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Petugas</label>
          <select name="id_employee" class="form-select">
            <option value="">Semua Petugas</option>
            <?php foreach($all_employees as $emp): ?>
            <option value="<?=$emp['id_employee']?>" <?=$f_emp==$emp['id_employee']?'selected':''?>>
              <?=htmlspecialchars($emp['nama_karyawan'])?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-1 col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
          <a href="admin.php" class="btn btn-reset"><i class="bi bi-x-lg"></i></a>
        </div>
      </div>
    </form>
  </div>

  <!-- TABLE -->
  <div class="table-card">
    <div class="table-head-row">
      <div>
        <h2 class="table-card-title">Daftar Booking</h2>
      </div>
      <span class="table-count-chip"><?=$total_rows?> data &nbsp;·&nbsp; Hal <?=$page?>/<?=max(1,$total_pages)?></span>
    </div>

    <div class="table-scroll">
      <table class="dash-table">
        <thead>
          <tr>
            <th>ID Booking</th>
            <th>Customer</th>
            <th>Jadwal</th>
            <th>Layanan</th>
            <th>Detail Pembayaran</th>
            <th>Petugas</th>
            <th>Status Kerja</th>
            <th>Status Bayar</th>
            <th style="text-align:center">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if(mysqli_num_rows($q_main)===0): ?>
          <tr class="empty-row">
            <td colspan="9">
              <div class="empty-icon">📋</div>
              <p class="empty-txt">Tidak ada data booking ditemukan.<br>Coba ubah filter atau tambahkan booking baru.</p>
            </td>
          </tr>
        <?php else: ?>
        <?php while($row=mysqli_fetch_assoc($q_main)):
            $layanan_arr = array_filter(explode('||', $row['daftar_layanan']??''));
            // Ambil petugas dari booking_komisi (sumber lebih akurat)
            $pet_arr     = array_unique(array_filter(explode('||', $row['daftar_petugas_komisi']??'')));
            $terbayar    = (float)$row['jumlah_terbayar'];
            $total_b     = (float)$row['total_biaya'];
            $kekurangan  = $total_b - $terbayar;
        ?>
          <tr>
            <td>
              <div class="td-id"><?=htmlspecialchars($row['id_booking'])?></div>
              <div class="td-date"><?=date('d/m/y', strtotime($row['created_at']))?></div>
            </td>
            <td>
              <div class="td-name"><?=htmlspecialchars($row['nama_customer'])?></div>
              <div class="td-wa"><i class="bi bi-whatsapp" style="color:#25D366;font-size:.72rem"></i> <?=htmlspecialchars($row['whatsapp_customer'])?></div>
            </td>
            <td>
              <div class="td-sched-date"><?=date('d M Y',strtotime($row['tgl_booking']))?></div>
              <div class="td-sched-time"><?=date('H:i',strtotime($row['jam_booking']))?> WIB</div>
            </td>
            <td>
              <div class="svc-pills">
                <?php foreach(array_slice($layanan_arr,0,3) as $l): ?>
                  <span class="svc-pill"><?=htmlspecialchars($l)?></span>
                <?php endforeach; ?>
                <?php if(count($layanan_arr)>3): ?>
                  <span class="svc-pill svc-more">+<?=count($layanan_arr)-3?> lagi</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <div class="td-price">Rp <?=number_format($total_b,0,',','.')?></div>
              <div class="td-paid">Bayar: Rp <?=number_format($terbayar,0,',','.')?></div>
              <?php if($kekurangan>0 && $row['status_pembayaran']!='lunas' && $row['status_pembayaran']!='batal'): ?>
              <div class="td-lack">Kurang: Rp <?=number_format($kekurangan,0,',','.')?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if(count($pet_arr)>0): ?>
              <div class="petugas-cluster">
                <?php
                $colors=['var(--accent)','var(--green)','var(--blue)','var(--yellow)','var(--pink)','var(--red)'];
                foreach($pet_arr as $ci=>$pn):
                  $c=$colors[$ci%count($colors)];
                ?>
                <div class="petugas-item">
                  <div class="petugas-dot" style="background:<?=$c?>"></div>
                  <?=htmlspecialchars($pn)?>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <span class="petugas-empty">Belum ditentukan</span>
              <?php endif; ?>
            </td>
            <td><span class="badge-s bs-<?=$row['status_kerja']?>"><?=ucfirst($row['status_kerja'])?></span></td>
            <td><span class="badge-s bs-<?=$row['status_pembayaran']?>"><?=strtoupper($row['status_pembayaran'])?></span></td>
            <td>
              <div class="act-group">
                <button class="btn btn-xs btn-act-kelola" onclick="openKelola('<?=addslashes($row['id_booking'])?>')">
                  <i class="bi bi-tools"></i> Kelola
                </button>
                <button class="btn btn-xs btn-act-bayar" onclick="openBayar('<?=addslashes($row['id_booking'])?>')">
                  <i class="bi bi-credit-card"></i> Bayar
                </button>
                <a href="cetak_nota.php?id=<?=urlencode($row['id_booking'])?>" target="_blank" class="btn btn-xs btn-act-nota">
                  <i class="bi bi-printer"></i> Nota
                </a>
                <a href="../class/hapus.php?type=booking&id=<?=urlencode($row['id_booking'])?>"
                   class="btn btn-xs btn-act-hapus"
                   onclick="return confirm('Hapus booking <?=addslashes($row['id_booking'])?>?\nTindakan ini tidak dapat dibatalkan!')">
                  <i class="bi bi-trash3"></i> Hapus
                </a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if($total_pages>1): ?>
    <div class="pg-bar">
      <div class="pg-info">
        Menampilkan <?=($offset+1)?>–<?=min($offset+$per_page,$total_rows)?> dari <?=$total_rows?> data
      </div>
      <ul class="pg-links">
        <li class="<?=$page<=1?'disabled':''?>">
          <?php if($page>1): ?><a href="?<?=http_build_query(array_merge($_GET,['page'=>$page-1]))?>"><i class="bi bi-chevron-left" style="font-size:.68rem"></i></a>
          <?php else: ?><span><i class="bi bi-chevron-left" style="font-size:.68rem"></i></span><?php endif; ?>
        </li>
        <?php
        $sp=max(1,$page-2);$ep=min($total_pages,$page+2);
        if($sp>1){echo '<li><a href="?'.http_build_query(array_merge($_GET,['page'=>1])).'">1</a></li>';if($sp>2) echo '<li><span style="border:none;background:none;width:auto;padding:0 3px;color:var(--text-muted)">…</span></li>';}
        for($pg=$sp;$pg<=$ep;$pg++){
            if($pg==$page) echo '<li class="active"><span>'.$pg.'</span></li>';
            else echo '<li><a href="?'.http_build_query(array_merge($_GET,['page'=>$pg])).'">'.$pg.'</a></li>';
        }
        if($ep<$total_pages){if($ep<$total_pages-1) echo '<li><span style="border:none;background:none;width:auto;padding:0 3px;color:var(--text-muted)">…</span></li>';echo '<li><a href="?'.http_build_query(array_merge($_GET,['page'=>$total_pages])).'">'.$total_pages.'</a></li>';}
        ?>
        <li class="<?=$page>=$total_pages?'disabled':''?>">
          <?php if($page<$total_pages): ?><a href="?<?=http_build_query(array_merge($_GET,['page'=>$page+1]))?>"><i class="bi bi-chevron-right" style="font-size:.68rem"></i></a>
          <?php else: ?><span><i class="bi bi-chevron-right" style="font-size:.68rem"></i></span><?php endif; ?>
        </li>
      </ul>
    </div>
    <?php endif; ?>
  </div><!-- /table-card -->

</div><!-- /page-container -->


<!-- ════════════════════════════
     MODAL KELOLA
════════════════════════════ -->
<div class="modal fade" id="mKelola" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><i class="bi bi-tools me-2"></i>Kelola Booking</h5>
          <div class="modal-subtitle" id="mKelola-id"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="mKelola-body">
        <div class="modal-loading"><div class="spinner-s"></div><span>Memuat data...</span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i>Batal
        </button>
        <button type="button" class="btn btn-primary" onclick="doSimpanKelola()">
          <i class="bi bi-check2-circle"></i>Simpan Perubahan
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════
     MODAL BAYAR
════════════════════════════ -->
<div class="modal fade" id="mBayar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i>Pembayaran</h5>
          <div class="modal-subtitle" id="mBayar-id"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="mBayar-body">
        <div class="modal-loading"><div class="spinner-s"></div><span>Memuat data...</span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i>Batal
        </button>
        <button type="button" class="btn btn-primary" onclick="doSimpanBayar()">
          <i class="bi bi-save"></i>Simpan Pembayaran
        </button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ════════════════════════════════
   THEME TOGGLE
════════════════════════════════ */
(function(){
    const saved = localStorage.getItem('salon_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcons(saved);
})();

function toggleTheme(){
    const cur = document.documentElement.getAttribute('data-theme');
    const nxt = cur==='light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', nxt);
    localStorage.setItem('salon_theme', nxt);
    updateThemeIcons(nxt);
}
function updateThemeIcons(theme){
    const ico = theme==='dark' ? '☀️' : '🌙';
    document.querySelectorAll('#themeBtn,#themeBtnDesktop').forEach(b=>{ if(b) b.textContent=ico; });
}

/* ════════════════════════════════
   GLOBALS
════════════════════════════════ */
let curBookingId  = '';
let curEmployees  = [];
let curDetails    = [];
let curBooking    = {};
let isBatalMode   = false;

const BSKelola = new bootstrap.Modal(document.getElementById('mKelola'));
const BSBayar  = new bootstrap.Modal(document.getElementById('mBayar'));

/* ════════════════════════════════
   HELPERS
════════════════════════════════ */
const fmt = n => Math.round(+n).toLocaleString('id-ID');

function toast(msg, type='ok'){
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className = `toast-item ${type}`;
    const ico = type==='ok'
        ? '<i class="bi bi-check-circle-fill toast-ic-ok"></i>'
        : '<i class="bi bi-x-circle-fill toast-ic-err"></i>';
    el.innerHTML = ico + '<span>' + msg + '</span>';
    wrap.appendChild(el);
    setTimeout(()=>{ el.style.animation='toastIn .3s ease reverse'; setTimeout(()=>el.remove(),300); }, 3500);
}

function loader(on){ document.getElementById('gLoader').classList.toggle('on',on); }

async function post(data){
    const fd = new FormData();
    Object.entries(data).forEach(([k,v])=>fd.append(k,v));
    const r = await fetch(location.href, {method:'POST',body:fd});
    return r.json();
}

function fmtDate(s){
    return new Date(s).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'});
}

/* ════════════════════════════════
   OPEN KELOLA MODAL
════════════════════════════════ */
async function openKelola(id){
    curBookingId = id;
    document.getElementById('mKelola-id').textContent = '#' + id;
    document.getElementById('mKelola-body').innerHTML = '<div class="modal-loading"><div class="spinner-s"></div><span>Memuat data...</span></div>';
    BSKelola.show();

    try {
        const data = await post({ajax_action:'get_detail', id_booking:id});
        if(data.error){ throw new Error(data.error); }
        curEmployees = data.employees;
        curDetails   = data.details;
        curBooking   = data.booking;
        renderKelola(data);
    } catch(e){
        document.getElementById('mKelola-body').innerHTML =
            '<div class="modal-loading"><p style="color:var(--red)">Gagal memuat: '+e.message+'</p></div>';
    }
}

/* ════════════════════════════════
   RENDER KELOLA
════════════════════════════════ */
function renderKelola({booking, details, employees}){
    const statusOpts = ['menunggu','diproses','selesai'].map(s=>
        `<option value="${s}" ${booking.status_kerja===s?'selected':''}>${s.charAt(0).toUpperCase()+s.slice(1)}</option>`
    ).join('');

    let detailsHTML = details.map((d,idx) => {
        // Tentukan petugas utama: ambil dari id_employee booking_details
        // Tentukan komisi utama: cari di komisi_list yg id_employee === d.id_employee
        const mainKomisi = d.komisi_list.find(k => +k.id_employee === +d.id_employee);
        const mainPct    = mainKomisi ? parseFloat(mainKomisi.persen_komisi) : parseFloat(d.komisi_max);

        // Extra: semua komisi_list selain main
        const extraList  = d.komisi_list.filter(k => +k.id_employee !== +d.id_employee);

        const empOpts = employees.map(e=>
            `<option value="${e.id_employee}" ${+e.id_employee===+d.id_employee?'selected':''}>${e.nama_karyawan}</option>`
        ).join('');

        const extraRows = extraList.map((ex,xi) => buildExtraRow(idx, xi, ex, employees, d.komisi_max)).join('');

        return `
        <div class="detail-box" id="dbox-${idx}">
          <div class="detail-box-header">
            <div class="detail-svc-name">
              <div class="svc-num">${idx+1}</div>
              ${escHtml(d.nama_layanan)}
            </div>
            <span class="komisi-max-badge">Komisi Maks: ${d.komisi_max}%</span>
          </div>

          <input type="hidden" name="detail_id[]" value="${d.id_detail}">

          <!-- Harga Layanan -->
          <div class="row g-2 mb-3">
            <div class="col-md-5">
              <label class="form-label">Harga Layanan (Rp)</label>
              <div class="input-group">
                <span class="input-group-text" style="font-size:.75rem">Rp</span>
                <input type="number" name="subtotal[]" data-idx="${idx}"
                       class="form-control sub-inp"
                       value="${parseFloat(d.subtotal)}" min="0"
                       oninput="recalcDetail(${idx})">
              </div>
            </div>
          </div>

          <!-- Petugas Section -->
          <div class="petugas-section">
            <div class="petugas-section-title"><i class="bi bi-people-fill me-1"></i>Penugasan & Komisi</div>

            <!-- Petugas Utama -->
            <div class="p-row" id="putama-row-${idx}">
              <div class="p-label">Utama</div>
              <div class="p-name" style="min-width:0">
                <select name="id_employee_utama[]" data-idx="${idx}"
                        class="form-select form-select-sm putama-sel"
                        style="min-width:130px;max-width:200px;"
                        onchange="recalcDetail(${idx})">
                  <option value="">-- Pilih --</option>
                  ${empOpts}
                </select>
              </div>
              <div class="p-komisi-wrap">
                <span style="font-size:.73rem;color:var(--text-muted)">Komisi</span>
                <input type="number" name="komisi_persen_utama[]" data-idx="${idx}"
                       class="form-control p-komisi-input putama-komisi"
                       value="${mainPct}"
                       min="0" max="${d.komisi_max}" step="0.5"
                       oninput="enforcMax(this,${d.komisi_max});recalcDetail(${idx})">
                <span style="font-size:.73rem;color:var(--text-muted)">%</span>
              </div>
              <div class="p-nominal" id="nom-utama-${idx}">Rp 0</div>
            </div>

            <!-- Extra Petugas -->
            <div id="extra-rows-${idx}">${extraRows}</div>

            <!-- Extra Init Data -->
            <input type="hidden" name="extra_data[]" id="extra-data-${idx}"
                   value='${JSON.stringify(extraList.map(ex=>({id_employee:+ex.id_employee,persen_komisi:parseFloat(ex.persen_komisi)})))}'>

            <!-- Tambah Petugas -->
            <div class="add-petugas-row">
              <select id="add-sel-${idx}" class="form-select" style="max-width:185px;font-size:.8rem">
                <option value="">+ Tambah petugas...</option>
                ${employees.map(e=>`<option value="${e.id_employee}" data-nama="${escHtml(e.nama_karyawan)}">${escHtml(e.nama_karyawan)}</option>`).join('')}
              </select>
              <button type="button" class="btn btn-sm btn-outline-secondary"
                      onclick="addExtra(${idx},${d.komisi_max})">
                <i class="bi bi-plus-circle"></i> Tambah
              </button>
            </div>

            <!-- Total Komisi Layanan ini -->
            <div class="komisi-total-bar" id="ktotal-${idx}">
              <span class="komisi-total-label"><i class="bi bi-calculator me-1"></i>Total Komisi Layanan</span>
              <span class="komisi-total-val" id="ktotal-val-${idx}">Rp 0</span>
            </div>
          </div>
        </div>`;
    }).join('');

    document.getElementById('mKelola-body').innerHTML = `
      <div class="row g-3 mb-3">
        <div class="col-md-7">
          <div class="k-info-box">
            <div class="k-section-title"><i class="bi bi-person-lines-fill"></i>Info Customer</div>
            <div style="font-size:.88rem">
              <strong>${escHtml(booking.nama_customer)}</strong> &nbsp;
              <span style="color:var(--text-muted)"><i class="bi bi-whatsapp" style="color:#25D366"></i> ${escHtml(booking.whatsapp_customer)}</span><br>
              <span style="font-size:.8rem;color:var(--text-sub)">
                <i class="bi bi-calendar3"></i> ${fmtDate(booking.tgl_booking)} &nbsp;
                <i class="bi bi-clock"></i> ${booking.jam_booking.substring(0,5)} WIB
              </span>
            </div>
          </div>
        </div>
        <div class="col-md-5">
          <label class="form-label">Status Pengerjaan</label>
          <select id="k-status-kerja" class="form-select">
            ${statusOpts}
          </select>
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:5px">
            <i class="bi bi-info-circle"></i> Ubah untuk melacak progress layanan
          </div>
        </div>
      </div>

      <div class="sdivider"></div>
      <div class="k-section-title" style="margin-bottom:14px">
        <i class="bi bi-scissors"></i>Detail Layanan, Petugas &amp; Komisi
      </div>
      ${detailsHTML}
    `;

    // Init kalkulasi semua detail
    details.forEach((_,idx) => recalcDetail(idx));
}

/* ═══════════════════════════════
   BUILD EXTRA ROW
═══════════════════════════════ */
function buildExtraRow(idx, xi, ex, employees, komisiMax){
    const selOpts = employees.map(e=>
        `<option value="${e.id_employee}" ${+e.id_employee===+ex.id_employee?'selected':''}>${escHtml(e.nama_karyawan)}</option>`
    ).join('');
    const pct = ex ? parseFloat(ex.persen_komisi) : 0;
    return `
    <div class="p-row" id="extra-row-${idx}-${xi}" data-xi="${xi}">
      <div class="p-label">Tambahan</div>
      <div style="flex:1;min-width:120px">
        <select class="form-select form-select-sm extra-emp-sel"
                data-idx="${idx}" data-xi="${xi}"
                style="min-width:130px;max-width:200px;"
                onchange="syncExtraData(${idx});recalcDetail(${idx})">
          <option value="">-- Pilih --</option>
          ${selOpts}
        </select>
      </div>
      <div class="p-komisi-wrap">
        <span style="font-size:.73rem;color:var(--text-muted)">Komisi</span>
        <input type="number" class="form-control p-komisi-input extra-komisi-inp"
               data-idx="${idx}" data-xi="${xi}"
               value="${pct}" min="0" max="${komisiMax}" step="0.5"
               oninput="enforcMax(this,${komisiMax});syncExtraData(${idx});recalcDetail(${idx})">
        <span style="font-size:.73rem;color:var(--text-muted)">%</span>
      </div>
      <div class="p-nominal" id="nom-extra-${idx}-${xi}">Rp 0</div>
      <button type="button" class="p-remove" onclick="removeExtra(${idx},${xi})" title="Hapus petugas ini">×</button>
    </div>`;
}

/* ═══════════════════════════════
   ADD EXTRA PETUGAS
═══════════════════════════════ */
function addExtra(idx, komisiMax){
    const sel = document.getElementById(`add-sel-${idx}`);
    if(!sel.value){ toast('Pilih petugas terlebih dahulu','err'); return; }

    const empId   = sel.value;
    const empNama = sel.options[sel.selectedIndex].getAttribute('data-nama');

    // Cek duplikasi utama
    const utamaSel = document.querySelector(`select[name="id_employee_utama[]"][data-idx="${idx}"]`);
    if(utamaSel && utamaSel.value === empId){
        toast('Petugas sudah terdaftar sebagai Petugas Utama','err'); return;
    }

    // Cek duplikasi extra
    const existExtras = document.querySelectorAll(`#extra-rows-${idx} .extra-emp-sel`);
    for(const es of existExtras){
        if(es.value===empId){ toast('Petugas sudah ditambahkan','err'); return; }
    }

    const container = document.getElementById(`extra-rows-${idx}`);
    const xi = container.querySelectorAll('.p-row').length;

    const row = document.createElement('div');
    row.innerHTML = buildExtraRow(idx, xi, {id_employee:empId, persen_komisi:0}, curEmployees, komisiMax);
    container.appendChild(row.firstElementChild);

    syncExtraData(idx);
    recalcDetail(idx);
    sel.value = '';
}

/* ═══════════════════════════════
   REMOVE EXTRA PETUGAS
═══════════════════════════════ */
function removeExtra(idx, xi){
    const row = document.getElementById(`extra-row-${idx}-${xi}`);
    if(row) row.remove();
    // Re-index xi attributes
    const container = document.getElementById(`extra-rows-${idx}`);
    container.querySelectorAll('.p-row').forEach((r,newXi)=>{
        r.id = `extra-row-${idx}-${newXi}`;
        r.setAttribute('data-xi', newXi);
        r.querySelectorAll('[data-xi]').forEach(el => el.setAttribute('data-xi', newXi));
        const nomEl = r.querySelector('.p-nominal');
        if(nomEl) nomEl.id = `nom-extra-${idx}-${newXi}`;
        const btn = r.querySelector('.p-remove');
        if(btn) btn.setAttribute('onclick',`removeExtra(${idx},${newXi})`);
    });
    syncExtraData(idx);
    recalcDetail(idx);
}

/* ═══════════════════════════════
   SYNC EXTRA DATA → hidden input
═══════════════════════════════ */
function syncExtraData(idx){
    const container = document.getElementById(`extra-rows-${idx}`);
    const rows = container.querySelectorAll('.p-row');
    const data = [];
    rows.forEach(r=>{
        const empSel = r.querySelector('.extra-emp-sel');
        const pctInp = r.querySelector('.extra-komisi-inp');
        if(empSel && pctInp && empSel.value){
            data.push({id_employee:+empSel.value, persen_komisi:parseFloat(pctInp.value)||0});
        }
    });
    document.getElementById(`extra-data-${idx}`).value = JSON.stringify(data);
}

/* ═══════════════════════════════
   RECALC KOMISI DISPLAY
═══════════════════════════════ */
function recalcDetail(idx){
    const subInp = document.querySelector(`input[name="subtotal[]"][data-idx="${idx}"]`);
    if(!subInp) return;
    const subtotal = parseFloat(subInp.value)||0;

    // Utama nominal
    const utamaPct = parseFloat(document.querySelector(`input[name="komisi_persen_utama[]"][data-idx="${idx}"]`)?.value)||0;
    const utamaNom = Math.round(subtotal * utamaPct / 100);
    const utamaNomEl = document.getElementById(`nom-utama-${idx}`);
    if(utamaNomEl) utamaNomEl.textContent = 'Rp ' + fmt(utamaNom);

    // Extra nominals
    let totalKomisi = utamaNom;
    const container = document.getElementById(`extra-rows-${idx}`);
    if(container){
        container.querySelectorAll('.p-row').forEach((r,xi)=>{
            const pctInp = r.querySelector('.extra-komisi-inp');
            const nomEl  = r.querySelector('.p-nominal');
            if(pctInp && nomEl){
                const pct = parseFloat(pctInp.value)||0;
                const nom = Math.round(subtotal * pct / 100);
                nomEl.textContent = 'Rp ' + fmt(nom);
                totalKomisi += nom;
            }
        });
    }

    const kTotalEl = document.getElementById(`ktotal-val-${idx}`);
    if(kTotalEl) kTotalEl.textContent = 'Rp ' + fmt(totalKomisi);
}

function enforcMax(inp, max){
    const v = parseFloat(inp.value);
    if(v > max) inp.value = max;
    if(v < 0)   inp.value = 0;
}

/* ═══════════════════════════════
   SIMPAN KELOLA
═══════════════════════════════ */
async function doSimpanKelola(){
    loader(true);
    try {
        const statusKerja = document.getElementById('k-status-kerja')?.value || 'menunggu';
        const fd = new FormData();
        fd.append('ajax_action','simpan_kelola');
        fd.append('id_booking', curBookingId);
        fd.append('status_kerja', statusKerja);

        // Collect arrays
        document.querySelectorAll('input[name="detail_id[]"]').forEach((el,i)=>{
            fd.append('detail_id[]', el.value);
        });
        document.querySelectorAll('input[name="subtotal[]"]').forEach(el=>{
            fd.append('subtotal[]', el.value);
        });
        document.querySelectorAll('select[name="id_employee_utama[]"]').forEach(el=>{
            fd.append('id_employee_utama[]', el.value);
        });
        document.querySelectorAll('input[name="komisi_persen_utama[]"]').forEach(el=>{
            fd.append('komisi_persen_utama[]', el.value);
        });
        document.querySelectorAll('input[name="extra_data[]"]').forEach(el=>{
            fd.append('extra_data[]', el.value);
        });

        const res = await fetch(location.href,{method:'POST',body:fd});
        const data = await res.json();

        if(data.success){
            toast(data.message,'ok');
            BSKelola.hide();
            setTimeout(()=>location.reload(),700);
        } else {
            toast(data.message||'Gagal menyimpan','err');
        }
    } catch(e){
        toast('Terjadi kesalahan: '+e.message,'err');
    } finally {
        loader(false);
    }
}

/* ════════════════════════════════
   OPEN BAYAR MODAL
════════════════════════════════ */
async function openBayar(id){
    curBookingId = id;
    isBatalMode  = false;
    document.getElementById('mBayar-id').textContent = '#' + id;
    document.getElementById('mBayar-body').innerHTML = '<div class="modal-loading"><div class="spinner-s"></div><span>Memuat data...</span></div>';
    BSBayar.show();

    try {
        const data = await post({ajax_action:'get_bayar', id_booking:id});
        if(data.error) throw new Error(data.error);
        renderBayar(data);
    } catch(e){
        document.getElementById('mBayar-body').innerHTML =
            '<div class="modal-loading"><p style="color:var(--red)">Gagal memuat: '+e.message+'</p></div>';
    }
}

/* ════════════════════════════════
   RENDER BAYAR
════════════════════════════════ */
function renderBayar({booking, layanan}){
    const total    = parseFloat(booking.total_biaya);
    const sudah    = parseFloat(booking.jumlah_terbayar);
    const sisa     = total - sudah;

    const lRows = layanan.map(l=>
        `<div class="bill-row">
          <span style="color:var(--text-sub)">${escHtml(l.nama_layanan)}</span>
          <span class="bill-amount">Rp ${fmt(l.subtotal)}</span>
        </div>`
    ).join('');

    document.getElementById('mBayar-body').innerHTML = `
    <div class="row g-4">
      <div class="col-md-6">
        <div class="bill-box">
          <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:10px">
            Rincian Layanan
          </div>
          ${lRows}
          <div class="bill-row total-row">
            <span>Total Tagihan</span>
            <span class="bill-amount" style="color:var(--accent)">Rp ${fmt(total)}</span>
          </div>
        </div>
        <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:4px">
          Status saat ini:
          <span class="badge-s bs-${booking.status_pembayaran} ms-1">${booking.status_pembayaran.toUpperCase()}</span>
        </div>
        ${sudah>0?`<div style="font-size:.78rem;color:var(--text-muted)">Sudah dibayar: <strong style="color:var(--green);font-family:var(--mono)">Rp ${fmt(sudah)}</strong></div>`:''}
        ${sisa>0?`<div style="font-size:.78rem;color:var(--red)">Sisa tagihan: <strong style="font-family:var(--mono)">Rp ${fmt(sisa)}</strong></div>`:''}
      </div>

      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Bayar Tunai (Cash)</label>
          <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" id="inp-cash" class="form-control"
                   value="${parseFloat(booking.bayar_cash)||0}" min="0" placeholder="0"
                   oninput="calcBayar(${total})">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Bayar Transfer</label>
          <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" id="inp-tf" class="form-control"
                   value="${parseFloat(booking.bayar_transfer)||0}" min="0" placeholder="0"
                   oninput="calcBayar(${total})">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Total Pembayaran</label>
          <div style="font-size:1.5rem;font-weight:900;font-family:var(--mono);color:var(--text)" id="disp-total">
            Rp ${fmt(sudah)}
          </div>
        </div>

        <div id="pay-result"></div>

        <div class="sdivider"></div>

        <div id="auto-status-wrap" style="margin-bottom:12px">
          <label class="form-label">Status Terdeteksi Otomatis</label>
          <div id="auto-status">—</div>
        </div>

        <div>
          <label class="form-label">Opsi Pembatalan</label><br>
          <button type="button" id="btn-set-batal" class="btn btn-sm btn-act-hapus"
                  onclick="toggleBatal(${total})">
            <i class="bi bi-x-octagon"></i> Batalkan Booking
          </button>
          <div class="batal-warning" id="batal-warn">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Status booking akan diubah menjadi <strong>BATAL</strong>
          </div>
        </div>
      </div>
    </div>`;

    calcBayar(total);
}

function calcBayar(total){
    if(isBatalMode) return;
    const cash = parseFloat(document.getElementById('inp-cash')?.value)||0;
    const tf   = parseFloat(document.getElementById('inp-tf')?.value)||0;
    const bayar = cash + tf;

    const dispEl = document.getElementById('disp-total');
    if(dispEl) dispEl.textContent = 'Rp ' + fmt(bayar);

    const resEl = document.getElementById('pay-result');
    if(!resEl) return;
    const selisih = bayar - total;
    resEl.style.display = 'block';
    resEl.className = 'pay-result';

    if(bayar <= 0){ resEl.style.display='none'; }
    else if(selisih > 0){
        resEl.classList.add('pay-kembalian');
        resEl.innerHTML = '<i class="bi bi-arrow-return-left me-2"></i>Kembalian: Rp '+fmt(selisih);
    } else if(selisih < 0){
        resEl.classList.add('pay-kurang');
        resEl.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Kekurangan: Rp '+fmt(Math.abs(selisih));
    } else {
        resEl.classList.add('pay-lunas-exact');
        resEl.innerHTML = '<i class="bi bi-check-circle me-2"></i>Pembayaran tepat — LUNAS';
    }

    updateAutoStatus(bayar, total);
}

function updateAutoStatus(bayar, total){
    const el = document.getElementById('auto-status');
    if(!el) return;
    let cls, label;
    if(isBatalMode){         cls='bs-batal';  label='BATAL'; }
    else if(bayar<=0){       cls='bs-pending';label='PENDING'; }
    else if(bayar<total){    cls='bs-dp';     label='DP'; }
    else{                    cls='bs-lunas';  label='LUNAS'; }
    el.innerHTML = `<span class="badge-s ${cls}">${label}</span>`;
}

function toggleBatal(total){
    isBatalMode = !isBatalMode;
    const btn  = document.getElementById('btn-set-batal');
    const warn = document.getElementById('batal-warn');
    if(btn){
        btn.innerHTML = isBatalMode
            ? '<i class="bi bi-arrow-counterclockwise"></i> Batalkan Pembatalan'
            : '<i class="bi bi-x-octagon"></i> Batalkan Booking';
    }
    if(warn) warn.style.display = isBatalMode ? 'block' : 'none';
    updateAutoStatus(0, total);
}

/* ════════════════════════════════
   SIMPAN BAYAR
════════════════════════════════ */
async function doSimpanBayar(){
    loader(true);
    try {
        const cash = parseFloat(document.getElementById('inp-cash')?.value)||0;
        const tf   = parseFloat(document.getElementById('inp-tf')?.value)||0;

        const fd = new FormData();
        fd.append('ajax_action','simpan_bayar');
        fd.append('id_booking', curBookingId);
        fd.append('bayar_cash', cash);
        fd.append('bayar_transfer', tf);
        fd.append('set_batal', isBatalMode ? '1' : '0');

        const res = await fetch(location.href,{method:'POST',body:fd});
        const data = await res.json();

        if(data.success){
            toast('Pembayaran berhasil disimpan!','ok');
            BSBayar.hide();
            isBatalMode = false;
            setTimeout(()=>location.reload(),700);
        } else {
            toast(data.message||'Gagal menyimpan','err');
        }
    } catch(e){
        toast('Terjadi kesalahan: '+e.message,'err');
    } finally {
        loader(false);
    }
}

// Reset batal flag saat modal ditutup
document.getElementById('mBayar').addEventListener('hidden.bs.modal',()=>{ isBatalMode=false; });

/* ════════════════════════════════
   UTILS
════════════════════════════════ */
function escHtml(s){
    const d=document.createElement('div');
    d.appendChild(document.createTextNode(s||''));
    return d.innerHTML;
}
</script>
</body>
</html>