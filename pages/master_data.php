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

    // ── 1. GET SERVICE ──────────────────────────
    if ($action === 'get_service') {
        $id = (int)($_POST['id_service'] ?? 0);
        if (!$id) { echo json_encode(['error'=>'ID tidak valid']); exit; }
        $q = mysqli_query($conn, "SELECT * FROM services WHERE id_service=$id");
        $row = mysqli_fetch_assoc($q);
        echo $row ? json_encode($row) : json_encode(['error'=>'Tidak ditemukan']);
        exit;
    }

    // ── 2. SAVE / UPDATE SERVICE ────────────────
    if ($action === 'save_service') {
        $nama    = mysqli_real_escape_string($conn, trim($_POST['nama_layanan']    ?? ''));
        $harga   = (int)($_POST['harga']         ?? 0);
        $komisi  = (float)($_POST['komisi_persen'] ?? 0);
        $id      = (int)($_POST['id_service']     ?? 0);

        if (!$nama) { echo json_encode(['success'=>false,'message'=>'Nama layanan tidak boleh kosong']); exit; }
        if ($harga <= 0) { echo json_encode(['success'=>false,'message'=>'Harga harus lebih dari 0']); exit; }
        if ($komisi < 0 || $komisi > 100) { echo json_encode(['success'=>false,'message'=>'Komisi harus antara 0–100%']); exit; }

        if ($id > 0) {
            mysqli_query($conn,
                "UPDATE services SET nama_layanan='$nama', harga=$harga, komisi_persen=$komisi WHERE id_service=$id"
            );
            $msg = 'Layanan berhasil diperbarui';
        } else {
            mysqli_query($conn,
                "INSERT INTO services (nama_layanan, harga, komisi_persen) VALUES ('$nama', $harga, $komisi)"
            );
            $msg = 'Layanan baru berhasil ditambahkan';
        }
        echo json_encode(['success'=>true,'message'=>$msg]);
        exit;
    }

    // ── 3. DELETE SERVICE ───────────────────────
    if ($action === 'delete_service') {
        $id = (int)($_POST['id_service'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
        // Cek apakah layanan dipakai di booking_details
        $cek = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM booking_details WHERE id_service=$id"));
        if ($cek['c'] > 0) {
            echo json_encode(['success'=>false,'message'=>'Layanan tidak dapat dihapus karena sudah digunakan di '.$cek['c'].' transaksi']);
            exit;
        }
        mysqli_query($conn, "DELETE FROM services WHERE id_service=$id");
        echo json_encode(['success'=>true,'message'=>'Layanan berhasil dihapus']);
        exit;
    }

    // ── 4. GET EMPLOYEE ─────────────────────────
    if ($action === 'get_employee') {
        $id = (int)($_POST['id_employee'] ?? 0);
        if (!$id) { echo json_encode(['error'=>'ID tidak valid']); exit; }
        $q = mysqli_query($conn, "SELECT * FROM employees WHERE id_employee=$id");
        $row = mysqli_fetch_assoc($q);
        echo $row ? json_encode($row) : json_encode(['error'=>'Tidak ditemukan']);
        exit;
    }

    // ── 5. SAVE / UPDATE EMPLOYEE ───────────────
    if ($action === 'save_employee') {
        $nama    = mysqli_real_escape_string($conn, trim($_POST['nama_karyawan'] ?? ''));
        $spesial = mysqli_real_escape_string($conn, trim($_POST['spesialisasi']  ?? ''));
        $status  = mysqli_real_escape_string($conn, ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active');
        $id      = (int)($_POST['id_employee'] ?? 0);

        if (!$nama) { echo json_encode(['success'=>false,'message'=>'Nama karyawan tidak boleh kosong']); exit; }

        if ($id > 0) {
            mysqli_query($conn,
                "UPDATE employees SET nama_karyawan='$nama', spesialisasi='$spesial', status='$status' WHERE id_employee=$id"
            );
            $msg = 'Data karyawan berhasil diperbarui';
        } else {
            mysqli_query($conn,
                "INSERT INTO employees (nama_karyawan, spesialisasi, status) VALUES ('$nama', '$spesial', 'active')"
            );
            $msg = 'Karyawan baru berhasil ditambahkan';
        }
        echo json_encode(['success'=>true,'message'=>$msg]);
        exit;
    }

    // ── 6. DELETE EMPLOYEE ──────────────────────
    if ($action === 'delete_employee') {
        $id = (int)($_POST['id_employee'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
        // Cek apakah karyawan dipakai di booking
        $cek = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM booking_details WHERE id_employee=$id"));
        if ($cek['c'] > 0) {
            // Nonaktifkan saja, jangan hapus untuk menjaga integritas data
            mysqli_query($conn, "UPDATE employees SET status='inactive' WHERE id_employee=$id");
            echo json_encode(['success'=>true,'message'=>'Karyawan memiliki '.$cek['c'].' riwayat transaksi, status diubah menjadi Nonaktif']);
        } else {
            mysqli_query($conn, "DELETE FROM employees WHERE id_employee=$id");
            echo json_encode(['success'=>true,'message'=>'Karyawan berhasil dihapus']);
        }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali']);
    exit;
}

// ═══════════════════════════════════════════════
//  DATA FETCH
// ═══════════════════════════════════════════════
$services  = [];
$qs = mysqli_query($conn, "SELECT s.*, COUNT(d.id_detail) AS jml_digunakan
    FROM services s
    LEFT JOIN booking_details d ON s.id_service = d.id_service
    GROUP BY s.id_service
    ORDER BY s.nama_layanan ASC");
while ($r = mysqli_fetch_assoc($qs)) $services[] = $r;

$employees = [];
$qe = mysqli_query($conn, "SELECT e.*,
    COUNT(DISTINCT d.id_booking) AS jml_booking,
    COALESCE(SUM(CASE WHEN b.status_pembayaran='lunas' AND b.status_kerja='selesai'
                 THEN d.subtotal * svc.komisi_persen / 100 ELSE 0 END), 0) AS total_komisi
    FROM employees e
    LEFT JOIN booking_details d ON e.id_employee = d.id_employee
    LEFT JOIN bookings b ON d.id_booking = b.id_booking
    LEFT JOIN services svc ON d.id_service = svc.id_service
    GROUP BY e.id_employee
    ORDER BY e.status ASC, e.nama_karyawan ASC");
while ($r = mysqli_fetch_assoc($qe)) $employees[] = $r;

// Summary counts
$total_svc   = count($services);
$total_emp   = count(array_filter($employees, fn($e) => $e['status'] === 'active'));
$total_emp_i = count(array_filter($employees, fn($e) => $e['status'] === 'inactive'));
$avg_komisi_pct = $total_svc > 0
    ? array_sum(array_column($services, 'komisi_persen')) / $total_svc
    : 0;
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Data — Amoy Salon</title>
<link rel="icon" type="image/png" href="../asset/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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
    gap: 16px; margin-bottom: 24px;
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
    font-size: 18px; margin-bottom: 12px; position: relative; z-index: 1;
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

/* ── TAB NAVIGATION ── */
.tab-nav-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden;
    box-shadow: var(--shadow-card);
}
.tab-header {
    display: flex; align-items: center;
    padding: 0 20px;
    border-bottom: 1px solid var(--border);
    background: var(--bg-stripe);
    gap: 4px;
}
.tab-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 14px 18px;
    font-size: .84rem; font-weight: 700;
    color: var(--text-muted);
    border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: var(--trans);
    white-space: nowrap;
}
.tab-btn:hover { color: var(--text); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent) }
.tab-actions {
    margin-left: auto;
    padding: 10px 0;
    display: flex; gap: 8px; align-items: center;
}
.tab-content-area { padding: 24px }
.tab-pane { display: none }
.tab-pane.active { display: block }

/* ── SEARCH BAR ── */
.search-bar {
    background: var(--bg-input); border: 1px solid var(--border-input);
    border-radius: 8px; padding: 8px 12px;
    display: flex; align-items: center; gap: 8px;
    color: var(--text-muted); font-size: .84rem; width: 240px;
}
.search-bar input {
    border: none; background: transparent; outline: none;
    color: var(--text); font-family: var(--font); font-size: .84rem; flex: 1;
}
.search-bar input::placeholder { color: var(--text-muted) }

/* ── TABLE ── */
.table-scroll { overflow-x: auto }
table.dash-table {
    width: 100%; border-collapse: collapse;
    min-width: 500px;
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
.td-mono { font-family: var(--mono); font-weight: 700 }
.td-green { font-family: var(--mono); font-weight: 800; color: var(--green) }

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
.bs-active   { background: var(--green-bg);  color: var(--green) }
.bs-inactive { background: var(--red-bg);    color: var(--red) }
.bs-komisi   { background: var(--accent-bg); color: var(--accent) }
.bs-yellow   { background: var(--yellow-bg); color: var(--yellow) }

/* ── ACTION BUTTONS ── */
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
.btn-sm { padding: 5px 12px !important; font-size: .78rem !important }
.btn-xs { padding: 3px 9px !important; font-size: .74rem !important; border-radius: 6px !important }
.btn-act-edit {
    background: var(--blue-bg) !important; border: 1px solid var(--blue-border) !important;
    color: var(--blue) !important;
}
.btn-act-edit:hover { background: var(--blue) !important; color: #fff !important }
.btn-act-hapus {
    background: var(--red-bg) !important; border: 1px solid var(--red-border) !important;
    color: var(--red) !important;
}
.btn-act-hapus:hover { background: var(--red) !important; color: #fff !important }
.btn-outline-secondary {
    background: transparent !important;
    border: 1px solid var(--border-input) !important;
    color: var(--text-sub) !important;
}
.btn-outline-secondary:hover { background: var(--bg-hover) !important; color: var(--text) !important }
.act-group { display: flex; gap: 5px; align-items: center; flex-wrap: nowrap }

/* ── MODALS ── */
.modal-content {
    background: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    border-radius: var(--radius-xl) !important;
    color: var(--text) !important; overflow: hidden;
    box-shadow: var(--shadow-lg) !important;
}
.modal-header {
    background: var(--bg-stripe) !important;
    border-bottom: 1px solid var(--border) !important;
    padding: 16px 22px !important;
}
.modal-title { font-size: .95rem !important; font-weight: 800 !important; color: var(--text) !important }
.modal-subtitle { font-size: .75rem; color: var(--text-muted); margin-top: 2px }
.btn-close { filter: var(--close-filter, none) }
[data-theme="dark"] .btn-close { filter: invert(1) brightness(.6) }
.modal-body { padding: 22px 22px !important }
.modal-footer {
    background: var(--bg-stripe) !important;
    border-top: 1px solid var(--border) !important;
    padding: 14px 22px !important; gap: 8px !important;
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
.modal-loading {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 48px 24px; gap: 12px;
    color: var(--text-muted); font-size: .85rem;
}
.spinner-s {
    width: 32px; height: 32px; border: 3px solid var(--border);
    border-top-color: var(--accent); border-radius: 50%;
    animation: spin .65s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg) } }

/* ── EMPLOYEE CARD GRID (alternate view) ── */
.emp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 14px;
}
.emp-card {
    background: var(--bg-input); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 16px;
    display: flex; flex-direction: column; gap: 10px;
    transition: var(--trans);
}
.emp-card:hover { border-color: var(--accent-border); box-shadow: var(--shadow) }
.emp-card.inactive { opacity: .65 }
.emp-avatar {
    width: 44px; height: 44px; border-radius: 12px;
    background: linear-gradient(135deg, var(--accent), var(--pink));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.1rem; font-weight: 900;
    flex-shrink: 0;
}
.emp-avatar.inactive-av {
    background: var(--bg-hover);
    color: var(--text-muted);
}
.emp-info { flex: 1 }
.emp-name { font-size: .92rem; font-weight: 800; line-height: 1.3 }
.emp-spec { font-size: .75rem; color: var(--text-muted); margin-top: 2px }
.emp-stats { display: flex; gap: 14px }
.emp-stat-item { font-size: .73rem; color: var(--text-muted) }
.emp-stat-val  { font-family: var(--mono); font-weight: 700; color: var(--text); font-size: .8rem }
.emp-actions   { display: flex; gap: 6px }
.view-toggle { display: flex; gap: 4px }
.view-btn {
    width: 32px; height: 32px; border-radius: 7px;
    border: 1px solid var(--border-input); background: var(--bg-input);
    color: var(--text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; transition: var(--trans);
}
.view-btn.active, .view-btn:hover {
    background: var(--accent-bg); border-color: var(--accent-border);
    color: var(--accent);
}

/* ── TOAST & LOADER ── */
.toast-wrap {
    position: fixed; bottom: 22px; right: 22px; z-index: 9999;
    display: flex; flex-direction: column; gap: 8px; pointer-events: none;
}
.toast-item {
    display: flex; align-items: center; gap: 10px;
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 12px; padding: 12px 16px;
    min-width: 260px; max-width: 360px;
    box-shadow: var(--shadow-lg);
    font-size: .84rem; font-weight: 600;
    animation: toastIn .3s ease; pointer-events: all;
}
.toast-item.ok  { border-left: 3px solid var(--green) }
.toast-item.err { border-left: 3px solid var(--red) }
.toast-ic-ok  { color: var(--green); font-size: 17px }
.toast-ic-err { color: var(--red); font-size: 17px }
@keyframes toastIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }
#gLoader {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 9998;
    align-items: center; justify-content: center;
}
#gLoader.on { display: flex }
#gLoader .spinner-s { width: 40px; height: 40px; border-width: 4px }

/* ── EMPTY STATE ── */
.empty-row td { padding: 56px 20px !important; text-align: center }
.empty-icon   { font-size: 2.8rem; opacity: .25; margin-bottom: 12px }
.empty-txt    { color: var(--text-muted); font-size: .85rem }

/* ── SECTION DIVIDER ── */
.sdivider { height: 1px; background: var(--border); margin: 14px 0 }

@media(max-width:768px){
    .page-container { padding: 16px 12px }
    .page-head { flex-direction: column; align-items: flex-start }
    .stat-grid { grid-template-columns: 1fr 1fr }
    .tab-header { overflow-x: auto; padding: 0 12px }
    .tab-actions { min-width: max-content }
    .tab-content-area { padding: 16px }
    .search-bar { width: 180px }
}
</style>
</head>
<body>

<!-- LOADER -->
<div id="gLoader"><div class="spinner-s"></div></div>

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
        <li class="nav-item"><a class="nav-link active text-warning" href="master_data.php"><i class="bi bi-gear"></i>Master Data</a></li>
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
      <h1 class="page-title"><i class="bi bi-gear me-2" style="color:var(--yellow)"></i>Master Data Salon</h1>
      <p class="page-subtitle">Kelola layanan, tarif, dan data karyawan Amoy Salon</p>
    </div>
    <div class="date-chip"><i class="bi bi-calendar3"></i><?= date('l, d F Y') ?></div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-grid">
    <div class="stat-card" style="--c:var(--accent);--c-soft:var(--accent-bg)">
      <div class="stat-icon"><i class="bi bi-scissors"></i></div>
      <div class="stat-label">Total Layanan</div>
      <div class="stat-value"><?= $total_svc ?></div>
      <div class="stat-note">Jenis layanan tersedia</div>
    </div>
    <div class="stat-card" style="--c:var(--green);--c-soft:var(--green-bg)">
      <div class="stat-icon"><i class="bi bi-people"></i></div>
      <div class="stat-label">Karyawan Aktif</div>
      <div class="stat-value"><?= $total_emp ?></div>
      <div class="stat-note"><?= $total_emp_i ?> nonaktif</div>
    </div>
    <div class="stat-card" style="--c:var(--yellow);--c-soft:var(--yellow-bg)">
      <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
      <div class="stat-label">Rata-rata Komisi</div>
      <div class="stat-value"><?= number_format($avg_komisi_pct, 1) ?>%</div>
      <div class="stat-note">Dari seluruh layanan</div>
    </div>
    <div class="stat-card" style="--c:var(--blue);--c-soft:var(--blue-bg)">
      <div class="stat-icon"><i class="bi bi-tag"></i></div>
      <div class="stat-label">Harga Tertinggi</div>
      <div class="stat-value sm">Rp&nbsp;<?= !empty($services) ? number_format(max(array_column($services,'harga')),0,',','.') : '0' ?></div>
      <div class="stat-note">Dari daftar layanan</div>
    </div>
  </div>

  <!-- TAB CARD -->
  <div class="tab-nav-card">
    <!-- TAB HEADER -->
    <div class="tab-header">
      <button class="tab-btn active" data-tab="services" onclick="switchTab('services')">
        <i class="bi bi-scissors"></i>
        Layanan
        <span style="font-family:var(--mono);font-size:.72rem;background:var(--accent-bg);color:var(--accent);padding:1px 7px;border-radius:20px;font-weight:700"><?= $total_svc ?></span>
      </button>
      <button class="tab-btn" data-tab="employees" onclick="switchTab('employees')">
        <i class="bi bi-person-badge"></i>
        Karyawan
        <span style="font-family:var(--mono);font-size:.72rem;background:var(--green-bg);color:var(--green);padding:1px 7px;border-radius:20px;font-weight:700"><?= $total_emp + $total_emp_i ?></span>
      </button>
      <div class="tab-actions">
        <!-- Search (JS filter) -->
        <div class="search-bar d-none d-md-flex" id="searchBar">
          <i class="bi bi-search" style="font-size:13px"></i>
          <input type="text" id="searchInput" placeholder="Cari..." oninput="filterTable()">
        </div>
        <button class="btn btn-primary btn-sm" id="btnTambah" onclick="openModalTambah()">
          <i class="bi bi-plus-lg"></i> Tambah Baru
        </button>
      </div>
    </div>

    <!-- TAB CONTENT: LAYANAN -->
    <div class="tab-pane active" id="tab-services">
      <div class="tab-content-area">
        <div class="table-scroll">
          <table class="dash-table" id="tblServices">
            <thead>
              <tr>
                <th>#</th>
                <th>Nama Layanan</th>
                <th>Harga Standar</th>
                <th>Komisi Karyawan</th>
                <th>Pemakaian</th>
                <th style="width:120px">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($services)): ?>
              <tr class="empty-row">
                <td colspan="6">
                  <div class="empty-icon">✂️</div>
                  <div class="empty-txt">Belum ada layanan. Tambahkan layanan pertama.</div>
                </td>
              </tr>
              <?php else: ?>
              <?php foreach($services as $i => $s): ?>
              <tr data-searchkey="<?= strtolower(htmlspecialchars($s['nama_layanan'])) ?>">
                <td style="color:var(--text-muted);font-family:var(--mono);font-size:.76rem"><?= $i+1 ?></td>
                <td>
                  <div style="font-weight:800;font-size:.88rem"><?= htmlspecialchars($s['nama_layanan']) ?></div>
                  <div style="font-size:.73rem;color:var(--text-muted)">ID #<?= $s['id_service'] ?></div>
                </td>
                <td class="td-mono">Rp <?= number_format($s['harga'], 0, ',', '.') ?></td>
                <td>
                  <span class="badge-s bs-komisi"><?= $s['komisi_persen'] ?>%</span>
                  <div style="font-size:.72rem;color:var(--text-muted);margin-top:3px;font-family:var(--mono)">
                    ≈ Rp <?= number_format($s['harga'] * $s['komisi_persen'] / 100, 0, ',', '.') ?>
                  </div>
                </td>
                <td>
                  <span class="badge-s" style="background:var(--blue-bg);color:var(--blue)">
                    <?= $s['jml_digunakan'] ?> transaksi
                  </span>
                </td>
                <td>
                  <div class="act-group">
                    <button class="btn btn-act-edit btn-xs" onclick="openEditService(<?= $s['id_service'] ?>)" title="Edit">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-act-hapus btn-xs" onclick="hapusService(<?= $s['id_service'] ?>, '<?= addslashes(htmlspecialchars($s['nama_layanan'])) ?>')" title="Hapus">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach ?>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB CONTENT: KARYAWAN -->
    <div class="tab-pane" id="tab-employees">
      <div class="tab-content-area">
        <!-- View toggle: table / card -->
        <div class="d-flex justify-content-end mb-3 gap-2 align-items-center">
          <span style="font-size:.75rem;color:var(--text-muted)">Tampilan:</span>
          <div class="view-toggle">
            <button class="view-btn active" id="btnViewTable" onclick="switchEmpView('table')" title="Tampilan Tabel"><i class="bi bi-table"></i></button>
            <button class="view-btn" id="btnViewCard" onclick="switchEmpView('card')" title="Tampilan Kartu"><i class="bi bi-grid-3x3-gap"></i></button>
          </div>
        </div>

        <!-- TABLE VIEW -->
        <div id="empTableView">
          <div class="table-scroll">
            <table class="dash-table" id="tblEmployees">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nama Karyawan</th>
                  <th>Spesialisasi</th>
                  <th>Total Booking</th>
                  <th>Total Komisi Earned</th>
                  <th>Status</th>
                  <th style="width:120px">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($employees)): ?>
                <tr class="empty-row">
                  <td colspan="7">
                    <div class="empty-icon">👩‍🎤</div>
                    <div class="empty-txt">Belum ada karyawan. Tambahkan karyawan pertama.</div>
                  </td>
                </tr>
                <?php else: ?>
                <?php foreach($employees as $i => $e): ?>
                <tr data-searchkey="<?= strtolower(htmlspecialchars($e['nama_karyawan'].' '.$e['spesialisasi'])) ?>">
                  <td style="color:var(--text-muted);font-family:var(--mono);font-size:.76rem"><?= $i+1 ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px">
                      <div class="emp-avatar <?= $e['status']==='inactive'?'inactive-av':'' ?>" style="width:36px;height:36px;border-radius:10px;font-size:.9rem">
                        <?= mb_strtoupper(mb_substr($e['nama_karyawan'],0,1)) ?>
                      </div>
                      <div>
                        <div style="font-weight:800;font-size:.88rem"><?= htmlspecialchars($e['nama_karyawan']) ?></div>
                        <div style="font-size:.73rem;color:var(--text-muted)">ID #<?= $e['id_employee'] ?></div>
                      </div>
                    </div>
                  </td>
                  <td style="font-size:.82rem;color:var(--text-sub)"><?= htmlspecialchars($e['spesialisasi'] ?: '—') ?></td>
                  <td>
                    <span style="font-family:var(--mono);font-weight:700"><?= number_format($e['jml_booking']) ?></span>
                    <span style="color:var(--text-muted);font-size:.74rem"> booking</span>
                  </td>
                  <td class="td-green">Rp <?= number_format($e['total_komisi'], 0, ',', '.') ?></td>
                  <td>
                    <span class="badge-s <?= $e['status']==='active'?'bs-active':'bs-inactive' ?>">
                      <?= $e['status']==='active' ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                  </td>
                  <td>
                    <div class="act-group">
                      <button class="btn btn-act-edit btn-xs" onclick="openEditEmployee(<?= $e['id_employee'] ?>)" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-act-hapus btn-xs" onclick="hapusEmployee(<?= $e['id_employee'] ?>, '<?= addslashes(htmlspecialchars($e['nama_karyawan'])) ?>')" title="Hapus / Nonaktifkan">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach ?>
                <?php endif ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- CARD VIEW -->
        <div id="empCardView" style="display:none">
          <div class="emp-grid" id="empCardGrid">
            <?php foreach($employees as $e): ?>
            <div class="emp-card <?= $e['status']==='inactive'?'inactive':'' ?>" data-searchkey="<?= strtolower(htmlspecialchars($e['nama_karyawan'].' '.$e['spesialisasi'])) ?>">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="emp-avatar <?= $e['status']==='inactive'?'inactive-av':'' ?>">
                  <?= mb_strtoupper(mb_substr($e['nama_karyawan'],0,1)) ?>
                </div>
                <div class="emp-info">
                  <div class="emp-name"><?= htmlspecialchars($e['nama_karyawan']) ?></div>
                  <div class="emp-spec"><?= htmlspecialchars($e['spesialisasi'] ?: 'Umum') ?></div>
                </div>
                <span class="badge-s <?= $e['status']==='active'?'bs-active':'bs-inactive' ?>" style="align-self:flex-start">
                  <?= $e['status']==='active' ? 'Aktif' : 'Nonaktif' ?>
                </span>
              </div>
              <div class="sdivider"></div>
              <div class="emp-stats">
                <div class="emp-stat-item">
                  <div class="emp-stat-val"><?= number_format($e['jml_booking']) ?></div>
                  <div>Total Booking</div>
                </div>
                <div class="emp-stat-item">
                  <div class="emp-stat-val" style="color:var(--green);font-size:.75rem">Rp <?= number_format($e['total_komisi'],0,',','.') ?></div>
                  <div>Total Komisi</div>
                </div>
              </div>
              <div class="emp-actions">
                <button class="btn btn-act-edit btn-sm" onclick="openEditEmployee(<?= $e['id_employee'] ?>)" style="flex:1">
                  <i class="bi bi-pencil"></i> Edit
                </button>
                <button class="btn btn-act-hapus btn-sm" onclick="hapusEmployee(<?= $e['id_employee'] ?>, '<?= addslashes(htmlspecialchars($e['nama_karyawan'])) ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>
            <?php endforeach ?>
          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- end page-container -->

<!-- ════════════════════════
     MODAL: LAYANAN
════════════════════════ -->
<div class="modal fade" id="mService" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="mServiceTitle">Tambah Layanan</h5>
          <div class="modal-subtitle" id="mServiceSubtitle">Isi detail layanan baru</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="mService-body">
        <input type="hidden" id="svc-id">
        <div class="mb-3">
          <label class="form-label">Nama Layanan <span style="color:var(--red)">*</span></label>
          <input type="text" id="svc-nama" class="form-control" placeholder="Contoh: Potong Rambut Wanita" maxlength="100">
        </div>
        <div class="row g-3">
          <div class="col-7">
            <label class="form-label">Harga Standar <span style="color:var(--red)">*</span></label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="number" id="svc-harga" class="form-control" placeholder="0" min="0" oninput="previewKomisi()">
            </div>
          </div>
          <div class="col-5">
            <label class="form-label">Komisi Karyawan <span style="color:var(--red)">*</span></label>
            <div class="input-group">
              <input type="number" id="svc-komisi" class="form-control" placeholder="0" min="0" max="100" step="0.5" oninput="previewKomisi()">
              <span class="input-group-text">%</span>
            </div>
          </div>
        </div>
        <div id="komisi-preview" style="margin-top:10px;display:none;background:var(--green-bg);border:1px solid var(--green-border);border-radius:8px;padding:10px 14px;font-size:.82rem">
          <span style="color:var(--text-sub)">Estimasi komisi per layanan:</span>
          <span id="komisi-preview-val" style="font-family:var(--mono);font-weight:800;color:var(--green);margin-left:8px"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" onclick="simpanService()">
          <i class="bi bi-check-lg"></i> Simpan
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════
     MODAL: KARYAWAN
════════════════════════ -->
<div class="modal fade" id="mEmployee" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="mEmpTitle">Tambah Karyawan</h5>
          <div class="modal-subtitle" id="mEmpSubtitle">Isi detail karyawan baru</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="mEmp-body">
        <input type="hidden" id="emp-id">
        <div class="mb-3">
          <label class="form-label">Nama Karyawan <span style="color:var(--red)">*</span></label>
          <input type="text" id="emp-nama" class="form-control" placeholder="Nama lengkap karyawan" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label">Spesialisasi</label>
          <input type="text" id="emp-spesial" class="form-control" placeholder="Contoh: Rambut, Kuku, Make Up..." maxlength="100">
        </div>
        <div class="mb-3" id="emp-status-wrap" style="display:none">
          <label class="form-label">Status Karyawan</label>
          <select id="emp-status" class="form-select">
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" onclick="simpanEmployee()">
          <i class="bi bi-check-lg"></i> Simpan
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap & Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── THEME TOGGLE ── */
(function(){
    const saved = localStorage.getItem('amoy-theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    document.querySelectorAll('.theme-toggle').forEach(b => b.textContent = saved==='dark'?'☀️':'🌙');
})();
function toggleTheme(){
    const current = document.documentElement.getAttribute('data-theme');
    const next = current==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('amoy-theme', next);
    document.querySelectorAll('.theme-toggle').forEach(b => b.textContent = next==='dark'?'☀️':'🌙');
}

/* ── STATE ── */
let currentTab = 'services';
const BSService  = new bootstrap.Modal(document.getElementById('mService'));
const BSEmployee = new bootstrap.Modal(document.getElementById('mEmployee'));

/* ── TAB SWITCH ── */
function switchTab(tab){
    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab===tab));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('active', p.id==='tab-'+tab));
    document.getElementById('searchInput').value = '';
    filterTable();
    // Update tambah button label
    const labels = {services:'Tambah Layanan', employees:'Tambah Karyawan'};
    document.getElementById('btnTambah').innerHTML = '<i class="bi bi-plus-lg"></i> ' + labels[tab];
}

/* ── EMPLOYEE VIEW TOGGLE ── */
function switchEmpView(mode){
    const tbl = document.getElementById('empTableView');
    const crd = document.getElementById('empCardView');
    const btnT = document.getElementById('btnViewTable');
    const btnC = document.getElementById('btnViewCard');
    if(mode==='card'){
        tbl.style.display='none'; crd.style.display='block';
        btnT.classList.remove('active'); btnC.classList.add('active');
    } else {
        tbl.style.display='block'; crd.style.display='none';
        btnT.classList.add('active'); btnC.classList.remove('active');
    }
}

/* ── SEARCH / FILTER ── */
function filterTable(){
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    const tblId = currentTab === 'services' ? '#tblServices tbody tr' : '#tblEmployees tbody tr';
    document.querySelectorAll(tblId).forEach(tr => {
        if(tr.classList.contains('empty-row')) return;
        const key = (tr.dataset.searchkey || '').toLowerCase();
        tr.style.display = (!q || key.includes(q)) ? '' : 'none';
    });
    // Also filter card view
    document.querySelectorAll('#empCardGrid .emp-card').forEach(card => {
        const key = (card.dataset.searchkey || '').toLowerCase();
        card.style.display = (!q || key.includes(q)) ? '' : 'none';
    });
}

/* ── MODAL: TAMBAH ── */
function openModalTambah(){
    if(currentTab === 'services') openModalTambahService();
    else openModalTambahEmployee();
}
function openModalTambahService(){
    document.getElementById('mServiceTitle').textContent = 'Tambah Layanan';
    document.getElementById('mServiceSubtitle').textContent = 'Isi detail layanan baru';
    document.getElementById('svc-id').value = '';
    document.getElementById('svc-nama').value = '';
    document.getElementById('svc-harga').value = '';
    document.getElementById('svc-komisi').value = '';
    document.getElementById('komisi-preview').style.display = 'none';
    BSService.show();
    setTimeout(()=>document.getElementById('svc-nama').focus(), 300);
}
function openModalTambahEmployee(){
    document.getElementById('mEmpTitle').textContent = 'Tambah Karyawan';
    document.getElementById('mEmpSubtitle').textContent = 'Isi detail karyawan baru';
    document.getElementById('emp-id').value = '';
    document.getElementById('emp-nama').value = '';
    document.getElementById('emp-spesial').value = '';
    document.getElementById('emp-status-wrap').style.display = 'none';
    BSEmployee.show();
    setTimeout(()=>document.getElementById('emp-nama').focus(), 300);
}

/* ── MODAL: EDIT SERVICE ── */
async function openEditService(id){
    BSService.show();
    document.getElementById('mService-body').innerHTML = `
        <div class="modal-loading">
            <div class="spinner-s"></div>
            <span>Memuat data layanan...</span>
        </div>`;
    try {
        const fd = new FormData();
        fd.append('ajax_action','get_service');
        fd.append('id_service', id);
        const res = await fetch(location.href, {method:'POST',body:fd});
        const data = await res.json();
        if(data.error){ toast(data.error,'err'); BSService.hide(); return; }

        document.getElementById('mServiceTitle').textContent = 'Edit Layanan';
        document.getElementById('mServiceSubtitle').textContent = data.nama_layanan;
        document.getElementById('mService-body').innerHTML = `
            <input type="hidden" id="svc-id" value="${data.id_service}">
            <div class="mb-3">
                <label class="form-label">Nama Layanan <span style="color:var(--red)">*</span></label>
                <input type="text" id="svc-nama" class="form-control" value="${escHtml(data.nama_layanan)}" maxlength="100">
            </div>
            <div class="row g-3">
                <div class="col-7">
                    <label class="form-label">Harga Standar <span style="color:var(--red)">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" id="svc-harga" class="form-control" value="${data.harga}" min="0" oninput="previewKomisi()">
                    </div>
                </div>
                <div class="col-5">
                    <label class="form-label">Komisi <span style="color:var(--red)">*</span></label>
                    <div class="input-group">
                        <input type="number" id="svc-komisi" class="form-control" value="${data.komisi_persen}" min="0" max="100" step="0.5" oninput="previewKomisi()">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
            <div id="komisi-preview" style="margin-top:10px;background:var(--green-bg);border:1px solid var(--green-border);border-radius:8px;padding:10px 14px;font-size:.82rem">
                <span style="color:var(--text-sub)">Estimasi komisi per layanan:</span>
                <span id="komisi-preview-val" style="font-family:var(--mono);font-weight:800;color:var(--green);margin-left:8px"></span>
            </div>
        `;
        previewKomisi();
    } catch(e){
        toast('Gagal memuat data: '+e.message,'err');
        BSService.hide();
    }
}

/* ── MODAL: EDIT EMPLOYEE ── */
async function openEditEmployee(id){
    BSEmployee.show();
    document.getElementById('mEmp-body').innerHTML = `
        <div class="modal-loading">
            <div class="spinner-s"></div>
            <span>Memuat data karyawan...</span>
        </div>`;
    try {
        const fd = new FormData();
        fd.append('ajax_action','get_employee');
        fd.append('id_employee', id);
        const res = await fetch(location.href, {method:'POST',body:fd});
        const data = await res.json();
        if(data.error){ toast(data.error,'err'); BSEmployee.hide(); return; }

        document.getElementById('mEmpTitle').textContent = 'Edit Karyawan';
        document.getElementById('mEmpSubtitle').textContent = data.nama_karyawan;
        document.getElementById('mEmp-body').innerHTML = `
            <input type="hidden" id="emp-id" value="${data.id_employee}">
            <div class="mb-3">
                <label class="form-label">Nama Karyawan <span style="color:var(--red)">*</span></label>
                <input type="text" id="emp-nama" class="form-control" value="${escHtml(data.nama_karyawan)}" maxlength="100">
            </div>
            <div class="mb-3">
                <label class="form-label">Spesialisasi</label>
                <input type="text" id="emp-spesial" class="form-control" value="${escHtml(data.spesialisasi||'')}" maxlength="100">
            </div>
            <div class="mb-3">
                <label class="form-label">Status Karyawan</label>
                <select id="emp-status" class="form-select">
                    <option value="active" ${data.status==='active'?'selected':''}>Aktif</option>
                    <option value="inactive" ${data.status==='inactive'?'selected':''}>Nonaktif</option>
                </select>
            </div>
        `;
    } catch(e){
        toast('Gagal memuat data: '+e.message,'err');
        BSEmployee.hide();
    }
}

/* ── SIMPAN SERVICE ── */
async function simpanService(){
    const id     = document.getElementById('svc-id')?.value || '';
    const nama   = document.getElementById('svc-nama')?.value.trim() || '';
    const harga  = parseFloat(document.getElementById('svc-harga')?.value) || 0;
    const komisi = parseFloat(document.getElementById('svc-komisi')?.value) || 0;

    if(!nama)       { toast('Nama layanan tidak boleh kosong','err'); return; }
    if(harga <= 0)  { toast('Harga harus lebih dari 0','err'); return; }
    if(komisi < 0 || komisi > 100) { toast('Komisi harus antara 0–100%','err'); return; }

    loader(true);
    try {
        const fd = new FormData();
        fd.append('ajax_action','save_service');
        fd.append('id_service', id);
        fd.append('nama_layanan', nama);
        fd.append('harga', harga);
        fd.append('komisi_persen', komisi);
        const res = await fetch(location.href, {method:'POST',body:fd});
        const data = await res.json();
        if(data.success){
            toast(data.message,'ok');
            BSService.hide();
            setTimeout(()=>location.reload(), 700);
        } else {
            toast(data.message||'Gagal menyimpan','err');
        }
    } catch(e){
        toast('Error: '+e.message,'err');
    } finally {
        loader(false);
    }
}

/* ── SIMPAN EMPLOYEE ── */
async function simpanEmployee(){
    const id     = document.getElementById('emp-id')?.value || '';
    const nama   = document.getElementById('emp-nama')?.value.trim() || '';
    const spesial= document.getElementById('emp-spesial')?.value.trim() || '';
    const status = document.getElementById('emp-status')?.value || 'active';

    if(!nama){ toast('Nama karyawan tidak boleh kosong','err'); return; }

    loader(true);
    try {
        const fd = new FormData();
        fd.append('ajax_action','save_employee');
        fd.append('id_employee', id);
        fd.append('nama_karyawan', nama);
        fd.append('spesialisasi', spesial);
        fd.append('status', status);
        const res = await fetch(location.href, {method:'POST',body:fd});
        const data = await res.json();
        if(data.success){
            toast(data.message,'ok');
            BSEmployee.hide();
            setTimeout(()=>location.reload(), 700);
        } else {
            toast(data.message||'Gagal menyimpan','err');
        }
    } catch(e){
        toast('Error: '+e.message,'err');
    } finally {
        loader(false);
    }
}

/* ── HAPUS SERVICE ── */
async function hapusService(id, nama){
    if(!confirm(`Hapus layanan "${nama}"?\n\nJika layanan sudah digunakan di transaksi, penghapusan akan ditolak.`)) return;
    loader(true);
    try {
        const fd = new FormData();
        fd.append('ajax_action','delete_service');
        fd.append('id_service', id);
        const res = await fetch(location.href, {method:'POST',body:fd});
        const data = await res.json();
        if(data.success){
            toast(data.message,'ok');
            setTimeout(()=>location.reload(), 700);
        } else {
            toast(data.message||'Gagal menghapus','err');
        }
    } catch(e){
        toast('Error: '+e.message,'err');
    } finally {
        loader(false);
    }
}

/* ── HAPUS EMPLOYEE ── */
async function hapusEmployee(id, nama){
    if(!confirm(`Hapus / nonaktifkan karyawan "${nama}"?\n\nJika memiliki riwayat transaksi, status akan diubah menjadi Nonaktif.`)) return;
    loader(true);
    try {
        const fd = new FormData();
        fd.append('ajax_action','delete_employee');
        fd.append('id_employee', id);
        const res = await fetch(location.href, {method:'POST',body:fd});
        const data = await res.json();
        if(data.success){
            toast(data.message,'ok');
            setTimeout(()=>location.reload(), 700);
        } else {
            toast(data.message||'Gagal menghapus','err');
        }
    } catch(e){
        toast('Error: '+e.message,'err');
    } finally {
        loader(false);
    }
}

/* ── KOMISI PREVIEW ── */
function previewKomisi(){
    const harga  = parseFloat(document.getElementById('svc-harga')?.value) || 0;
    const komisi = parseFloat(document.getElementById('svc-komisi')?.value) || 0;
    const prev   = document.getElementById('komisi-preview');
    const val    = document.getElementById('komisi-preview-val');
    if(!prev) return;
    if(harga > 0 && komisi > 0){
        const nominal = harga * komisi / 100;
        val.textContent = 'Rp ' + nominal.toLocaleString('id-ID');
        prev.style.display = 'block';
    } else {
        prev.style.display = 'none';
    }
}

/* ── UTILS ── */
function loader(on){ document.getElementById('gLoader').classList.toggle('on', on) }

function toast(msg, type){
    const w = document.getElementById('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast-item ' + (type||'ok');
    const ic = type==='err'
        ? '<i class="bi bi-x-circle-fill toast-ic-err"></i>'
        : '<i class="bi bi-check-circle-fill toast-ic-ok"></i>';
    t.innerHTML = ic + `<span>${msg}</span>`;
    w.appendChild(t);
    setTimeout(()=>t.remove(), 3500);
}

function escHtml(s){
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(s||''));
    return d.innerHTML;
}

/* ── RESET MODAL ON CLOSE ── */
document.getElementById('mService').addEventListener('hidden.bs.modal', ()=>{
    // Restore default body structure if it was replaced
    document.getElementById('mServiceTitle').textContent = 'Tambah Layanan';
    document.getElementById('mServiceSubtitle').textContent = 'Isi detail layanan baru';
    document.getElementById('mService-body').innerHTML = `
        <input type="hidden" id="svc-id">
        <div class="mb-3">
            <label class="form-label">Nama Layanan <span style="color:var(--red)">*</span></label>
            <input type="text" id="svc-nama" class="form-control" placeholder="Contoh: Potong Rambut Wanita" maxlength="100">
        </div>
        <div class="row g-3">
            <div class="col-7">
                <label class="form-label">Harga Standar <span style="color:var(--red)">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" id="svc-harga" class="form-control" placeholder="0" min="0" oninput="previewKomisi()">
                </div>
            </div>
            <div class="col-5">
                <label class="form-label">Komisi <span style="color:var(--red)">*</span></label>
                <div class="input-group">
                    <input type="number" id="svc-komisi" class="form-control" placeholder="0" min="0" max="100" step="0.5" oninput="previewKomisi()">
                    <span class="input-group-text">%</span>
                </div>
            </div>
        </div>
        <div id="komisi-preview" style="margin-top:10px;display:none;background:var(--green-bg);border:1px solid var(--green-border);border-radius:8px;padding:10px 14px;font-size:.82rem">
            <span style="color:var(--text-sub)">Estimasi komisi per layanan:</span>
            <span id="komisi-preview-val" style="font-family:var(--mono);font-weight:800;color:var(--green);margin-left:8px"></span>
        </div>
    `;
});

document.getElementById('mEmployee').addEventListener('hidden.bs.modal', ()=>{
    document.getElementById('mEmpTitle').textContent = 'Tambah Karyawan';
    document.getElementById('mEmpSubtitle').textContent = 'Isi detail karyawan baru';
    document.getElementById('mEmp-body').innerHTML = `
        <input type="hidden" id="emp-id">
        <div class="mb-3">
            <label class="form-label">Nama Karyawan <span style="color:var(--red)">*</span></label>
            <input type="text" id="emp-nama" class="form-control" placeholder="Nama lengkap karyawan" maxlength="100">
        </div>
        <div class="mb-3">
            <label class="form-label">Spesialisasi</label>
            <input type="text" id="emp-spesial" class="form-control" placeholder="Contoh: Rambut, Kuku, Make Up..." maxlength="100">
        </div>
        <div class="mb-3" id="emp-status-wrap" style="display:none">
            <label class="form-label">Status Karyawan</label>
            <select id="emp-status" class="form-select">
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>
    `;
});

/* ── KEYBOARD SHORTCUT ── */
document.addEventListener('keydown', e => {
    if(e.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)){
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
});
</script>
</body>
</html>