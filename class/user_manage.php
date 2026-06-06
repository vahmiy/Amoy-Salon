<?php
session_start();
include '../class/koneksi.php';

// ═══════════════════════════════════════════════
//  PROTEKSI AKSES — Level 0 (Super User) & Level 1 (Admin)
// ═══════════════════════════════════════════════
if (!isset($_SESSION['login']) || $_SESSION['level'] > 1) {
    echo "<script>alert('Akses Ditolak! Hanya Super User atau Admin yang dapat mengakses halaman ini.'); window.location='admin.php';</script>";
    exit;
}

$user_level = (int)$_SESSION['level'];
$user_nama  = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengguna';

// ═══════════════════════════════════════════════
//  AJAX HANDLER
// ═══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax_action'];

    // ── 1. TAMBAH USER ────────────────────────
    if ($action === 'tambah_user') {
        $nama     = trim(mysqli_real_escape_string($conn, $_POST['nama_lengkap'] ?? ''));
        $username = trim(mysqli_real_escape_string($conn, $_POST['username']     ?? ''));
        $password = trim($_POST['password'] ?? '');
        $level    = (int)($_POST['level'] ?? 3);

        if (!$nama || !$username || !$password) {
            echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']); exit;
        }
        // Super User hanya bisa dibuat oleh Super User
        if ($level === 0 && $user_level !== 0) {
            echo json_encode(['success' => false, 'message' => 'Hanya Super User yang bisa membuat akun Super User.']); exit;
        }

        // Cek duplikat username
        $cek = mysqli_query($conn, "SELECT id_user FROM users WHERE username='$username' LIMIT 1");
        if (mysqli_num_rows($cek) > 0) {
            echo json_encode(['success' => false, 'message' => "Username '$username' sudah digunakan."]); exit;
        }

        $pass_hash  = password_hash($password, PASSWORD_DEFAULT);
        $pass_esc   = mysqli_real_escape_string($conn, $pass_hash);
        $created_at = date('Y-m-d');
        $res = mysqli_query($conn,
            "INSERT INTO users (nama_lengkap, username, password, level, created_at) VALUES ('$nama','$username','$pass_esc',$level,'$created_at')"
        );
        if ($res) {
            $new_id = mysqli_insert_id($conn);
            echo json_encode(['success' => true, 'message' => "Akun '$username' berhasil ditambahkan.", 'id' => $new_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // ── 2. GET USER (untuk edit) ──────────────
    if ($action === 'get_user') {
        $id = (int)($_POST['id_user'] ?? 0);
        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id_user,nama_lengkap,username,level FROM users WHERE id_user=$id LIMIT 1"));
        if ($row) {
            echo json_encode(['success' => true, 'user' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
        }
        exit;
    }

    // ── 3. EDIT USER ──────────────────────────
    if ($action === 'edit_user') {
        $id       = (int)($_POST['id_user'] ?? 0);
        $nama     = trim(mysqli_real_escape_string($conn, $_POST['nama_lengkap'] ?? ''));
        $username = trim(mysqli_real_escape_string($conn, $_POST['username']     ?? ''));
        $level    = (int)($_POST['level'] ?? 3);
        $password = trim($_POST['password'] ?? '');

        if (!$id || !$nama || !$username) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']); exit;
        }
        if ($level === 0 && $user_level !== 0) {
            echo json_encode(['success' => false, 'message' => 'Hanya Super User yang bisa mengatur level Super User.']); exit;
        }

        // Cek duplikat username (selain diri sendiri)
        $cek = mysqli_query($conn, "SELECT id_user FROM users WHERE username='$username' AND id_user!=$id LIMIT 1");
        if (mysqli_num_rows($cek) > 0) {
            echo json_encode(['success' => false, 'message' => "Username '$username' sudah digunakan akun lain."]); exit;
        }

        if ($password !== '') {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $pass_esc  = mysqli_real_escape_string($conn, $pass_hash);
            $sql = "UPDATE users SET nama_lengkap='$nama', username='$username', password='$pass_esc', level=$level WHERE id_user=$id";
        } else {
            $sql = "UPDATE users SET nama_lengkap='$nama', username='$username', level=$level WHERE id_user=$id";
        }

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => "Akun '$username' berhasil diperbarui."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // ── 4. RESET PASSWORD ─────────────────────
    if ($action === 'reset_password') {
        $id          = (int)($_POST['id_user'] ?? 0);
        $new_pass    = trim($_POST['new_password'] ?? '');
        $confirm     = trim($_POST['confirm_password'] ?? '');

        if (!$id || !$new_pass) {
            echo json_encode(['success' => false, 'message' => 'Password baru tidak boleh kosong.']); exit;
        }
        if ($new_pass !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Konfirmasi password tidak cocok.']); exit;
        }
        if (strlen($new_pass) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter.']); exit;
        }

        $pass_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $pass_esc  = mysqli_real_escape_string($conn, $pass_hash);
        if (mysqli_query($conn, "UPDATE users SET password='$pass_esc' WHERE id_user=$id")) {
            echo json_encode(['success' => true, 'message' => 'Password berhasil direset.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mereset password.']);
        }
        exit;
    }

    // ── 5. HAPUS USER ─────────────────────────
    if ($action === 'hapus_user') {
        $id = (int)($_POST['id_user'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid.']); exit;
        }
        if ($id === (int)$_SESSION['id_user']) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak bisa menghapus akun sendiri.']); exit;
        }
        // Cek level target — tidak bisa hapus Super User jika bukan Super User
        $row_target = mysqli_fetch_assoc(mysqli_query($conn, "SELECT level, username FROM users WHERE id_user=$id LIMIT 1"));
        if (!$row_target) {
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']); exit;
        }
        if ($row_target['level'] === 0 && $user_level !== 0) {
            echo json_encode(['success' => false, 'message' => 'Hanya Super User yang dapat menghapus akun Super User.']); exit;
        }

        if (mysqli_query($conn, "DELETE FROM users WHERE id_user=$id")) {
            echo json_encode(['success' => true, 'message' => "Akun '{$row_target['username']}' berhasil dihapus."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . mysqli_error($conn)]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
    exit;
}

// ═══════════════════════════════════════════════
//  STATISTIK USER
// ═══════════════════════════════════════════════
$stat_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'] ?? 0;
$stat_super   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE level=0"))['c'] ?? 0;
$stat_admin   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE level=1"))['c'] ?? 0;
$stat_pegawai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE level=2"))['c'] ?? 0;
$stat_user    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE level=3"))['c'] ?? 0;

// ═══════════════════════════════════════════════
//  QUERY USERS DENGAN FILTER & PAGINATION
// ═══════════════════════════════════════════════
$f_level  = isset($_GET['level'])  && $_GET['level'] !== '' ? (int)$_GET['level']  : null;
$f_q      = trim($_GET['q'] ?? '');

$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where = ["1=1"];
if ($f_level !== null) $where[] = "level=" . $f_level;
if ($f_q !== '') {
    $qs = mysqli_real_escape_string($conn, $f_q);
    $where[] = "(nama_lengkap LIKE '%$qs%' OR username LIKE '%$qs%')";
}
$where_sql = implode(' AND ', $where);

$total_rows  = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE $where_sql"))['c'] ?? 0);
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$q_users = mysqli_query($conn, "SELECT id_user, nama_lengkap, username, level, created_at FROM users WHERE $where_sql ORDER BY level ASC, id_user ASC LIMIT $per_page OFFSET $offset");
$users   = [];
while ($u = mysqli_fetch_assoc($q_users)) $users[] = $u;

// Level meta
$lvl_label = [0 => 'Super User', 1 => 'Admin',  2 => 'Pegawai', 3 => 'User'];
$lvl_color = [0 => 'danger',     1 => 'warning', 2 => 'info',    3 => 'secondary'];
$lvl_icon  = [0 => 'shield-fill-check', 1 => 'person-badge-fill', 2 => 'person-workspace', 3 => 'person-fill'];
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Akun — Amoy Salon</title>
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
    --bg:            #f1f2f6;
    --bg-card:       #ffffff;
    --bg-input:      #f8f9fc;
    --bg-hover:      #f0f1f8;
    --bg-stripe:     #fafbff;
    --border:        #e2e5f0;
    --border-input:  #d0d4e8;
    --text:          #1a1d2e;
    --text-sub:      #5a5f7d;
    --text-muted:    #9499b8;
    --nav-bg:        #2f3542;
    --nav-text:      #cdd1e0;
    --nav-active:    #ffffff;
    --shadow-sm:     0 1px 4px rgba(0,0,0,.06);
    --shadow:        0 4px 16px rgba(0,0,0,.08);
    --shadow-lg:     0 8px 32px rgba(0,0,0,.12);
    --shadow-card:   0 2px 8px rgba(67,75,120,.08);
    --modal-overlay: rgba(10,10,20,.45);
}
/* ═══════════════════════════════════════
   DESIGN TOKENS — DARK
═══════════════════════════════════════ */
[data-theme="dark"] {
    --bg:            #0e0f18;
    --bg-card:       #161825;
    --bg-input:      #1d1f30;
    --bg-hover:      #1f2135;
    --bg-stripe:     #171928;
    --border:        rgba(255,255,255,.07);
    --border-input:  rgba(255,255,255,.1);
    --text:          #eef0fb;
    --text-sub:      #8c90b0;
    --text-muted:    #555974;
    --nav-bg:        #111220;
    --nav-text:      #9498b8;
    --nav-active:    #ffffff;
    --shadow-sm:     0 1px 4px rgba(0,0,0,.3);
    --shadow:        0 4px 16px rgba(0,0,0,.4);
    --shadow-lg:     0 8px 32px rgba(0,0,0,.6);
    --shadow-card:   0 2px 8px rgba(0,0,0,.3);
    --modal-overlay: rgba(0,0,8,.7);
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
    --teal:          #00cec9;
    --radius:        10px;
    --radius-lg:     14px;
    --radius-xl:     18px;
    --font:          'Nunito', sans-serif;
    --mono:          'Fira Code', monospace;
    --trans:         all .18s ease;
}

/* ═══════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════ */
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
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

/* ═══════════════════════════════════════
   NAVBAR
═══════════════════════════════════════ */
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
.nav-link.text-success { color: #55efc4 !important; }
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
    flex-shrink: 0;
}
.theme-toggle:hover { background: rgba(255,255,255,.15); }

/* ═══════════════════════════════════════
   PAGE LAYOUT
═══════════════════════════════════════ */
.page-container { max-width: 1400px; margin: 0 auto; padding: 28px 20px; }
.page-head {
    display: flex; align-items: flex-end; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 28px;
}
.page-title { font-size: 1.4rem; font-weight: 900; letter-spacing: -.4px; margin: 0; }
.page-subtitle { font-size: .82rem; color: var(--text-muted); margin: 2px 0 0; }

/* ═══════════════════════════════════════
   STAT CARDS
═══════════════════════════════════════ */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px; margin-bottom: 22px;
}
@media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 600px)  { .stat-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 16px 18px 14px;
    position: relative; overflow: hidden;
    box-shadow: var(--shadow-card);
    transition: var(--trans);
    cursor: pointer;
    border-top: 3px solid var(--c, var(--accent));
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.stat-card::after {
    content: ''; position: absolute;
    bottom: -20px; right: -20px;
    width: 70px; height: 70px; border-radius: 50%;
    background: var(--c-soft, var(--accent-bg)); opacity: .8;
}
.stat-icon {
    width: 36px; height: 36px; border-radius: 9px;
    background: var(--c-soft, var(--accent-bg));
    color: var(--c, var(--accent));
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; margin-bottom: 10px;
    position: relative; z-index: 1;
}
.stat-label {
    font-size: .7rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .6px; color: var(--text-muted); margin-bottom: 2px;
}
.stat-value {
    font-size: 1.6rem; font-weight: 900; color: var(--text);
    font-family: var(--mono); letter-spacing: -.5px; line-height: 1.1;
}

/* ═══════════════════════════════════════
   FORM CONTROLS
═══════════════════════════════════════ */
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
.form-control::placeholder { color: var(--text-muted) !important; }
.form-select option { background: var(--bg-card); color: var(--text); }
.form-label {
    font-size: .78rem; font-weight: 700; color: var(--text-sub);
    margin-bottom: 5px; display: block;
}
.input-group-text {
    background: var(--bg-hover) !important;
    border: 1px solid var(--border-input) !important;
    color: var(--text-sub) !important;
    font-size: .845rem !important;
}
.password-wrapper { position: relative; }
.password-wrapper .form-control { padding-right: 42px !important; }
.pwd-toggle {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--text-muted);
    cursor: pointer; font-size: 15px; padding: 0; line-height: 1;
    transition: color .15s;
}
.pwd-toggle:hover { color: var(--accent); }

/* ═══════════════════════════════════════
   BUTTONS
═══════════════════════════════════════ */
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
.btn-success-custom {
    background: var(--green) !important; border: none !important;
    color: #fff !important; padding: 8px 18px !important;
}
.btn-success-custom:hover { background: #00a381 !important; }
.btn-outline-secondary {
    background: transparent !important;
    border: 1px solid var(--border-input) !important;
    color: var(--text-sub) !important; padding: 7px 14px !important;
}
.btn-outline-secondary:hover { background: var(--bg-hover) !important; color: var(--text) !important; }
.btn-sm { padding: 5px 12px !important; font-size: .78rem !important; }
.btn-xs { padding: 3px 9px !important; font-size: .75rem !important; border-radius: 6px !important; }

.btn-act-edit {
    background: var(--accent-bg) !important; border: 1px solid var(--accent-border) !important;
    color: var(--accent) !important;
}
.btn-act-edit:hover { background: var(--accent) !important; color: #fff !important; }
.btn-act-pw {
    background: var(--yellow-bg) !important; border: 1px solid var(--yellow-border) !important;
    color: var(--yellow) !important;
}
.btn-act-pw:hover { background: var(--yellow) !important; color: #fff !important; }
.btn-act-del {
    background: var(--red-bg) !important; border: 1px solid var(--red-border) !important;
    color: var(--red) !important;
}
.btn-act-del:hover { background: var(--red) !important; color: #fff !important; }

/* ═══════════════════════════════════════
   FILTER & SEARCH BAR
═══════════════════════════════════════ */
.filter-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 16px 20px;
    margin-bottom: 16px; box-shadow: var(--shadow-card);
}

/* ═══════════════════════════════════════
   TABLE CARD
═══════════════════════════════════════ */
.table-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    box-shadow: var(--shadow-card);
}
.table-card-head {
    padding: 16px 20px 14px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    flex-wrap: wrap;
}
.table-card-title {
    font-size: .88rem; font-weight: 800; letter-spacing: -.2px;
}
.table { margin: 0; }
.table thead th {
    background: var(--bg-stripe) !important;
    color: var(--text-muted) !important;
    font-size: .7rem !important; font-weight: 800 !important;
    text-transform: uppercase; letter-spacing: .7px;
    border-bottom: 1px solid var(--border) !important;
    padding: 11px 16px !important; white-space: nowrap;
    border-top: none !important;
}
.table tbody tr {
    border-bottom: 1px solid var(--border) !important;
    transition: background .13s;
    --bs-table-bg: transparent;
    --bs-table-striped-bg: transparent;
    --bs-table-hover-bg: transparent;
    --bs-table-color: var(--text);
    --bs-table-striped-color: var(--text);
    --bs-table-hover-color: var(--text);
}
.table tbody tr:last-child { border-bottom: none !important; }
.table tbody tr:hover { background: var(--bg-hover) !important; }
.table tbody td {
    color: var(--text) !important;
    background-color: transparent !important;
    font-size: .845rem !important;
    padding: 12px 16px !important; vertical-align: middle;
    border: none !important;
}
/* Override semua Bootstrap table color variables agar ikut CSS theme */
.table {
    --bs-table-bg: transparent;
    --bs-table-color: var(--text);
    --bs-table-border-color: var(--border);
    --bs-table-striped-bg: var(--bg-stripe);
    --bs-table-striped-color: var(--text);
    --bs-table-hover-bg: var(--bg-hover);
    --bs-table-hover-color: var(--text);
    color: var(--text) !important;
}
.table-responsive { background: var(--bg-card); }
.table tbody td code {
    font-family: var(--mono); font-size: .8rem;
    color: var(--accent-2); background: var(--accent-bg);
    padding: 2px 7px; border-radius: 5px;
}

/* ═══════════════════════════════════════
   LEVEL BADGES
═══════════════════════════════════════ */
.level-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    font-size: .72rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .4px;
    white-space: nowrap;
}
.lb-0 { background: var(--red-bg);    color: var(--red);    border: 1px solid var(--red-border); }
.lb-1 { background: var(--yellow-bg); color: var(--yellow); border: 1px solid var(--yellow-border); }
.lb-2 { background: var(--blue-bg);   color: var(--blue);   border: 1px solid var(--blue-border); }
.lb-3 { background: var(--bg-hover);  color: var(--text-sub); border: 1px solid var(--border-input); }

/* ═══════════════════════════════════════
   AVATAR
═══════════════════════════════════════ */
.user-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 900; flex-shrink: 0;
    text-transform: uppercase; letter-spacing: -.5px;
}
.av-0 { background: var(--red-bg);    color: var(--red);    border: 2px solid var(--red-border); }
.av-1 { background: var(--yellow-bg); color: var(--yellow); border: 2px solid var(--yellow-border); }
.av-2 { background: var(--blue-bg);   color: var(--blue);   border: 2px solid var(--blue-border); }
.av-3 { background: var(--accent-bg); color: var(--accent); border: 2px solid var(--accent-border); }

/* ═══════════════════════════════════════
   SELF HIGHLIGHT
═══════════════════════════════════════ */
tr.is-self td { background: var(--accent-bg) !important; }
tr.is-self td:first-child {
    border-left: 3px solid var(--accent) !important;
}
.you-chip {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: .67rem; font-weight: 800; padding: 1px 6px;
    background: var(--accent-bg); color: var(--accent);
    border: 1px solid var(--accent-border); border-radius: 10px;
    vertical-align: middle; margin-left: 4px; letter-spacing: .3px;
}

/* ═══════════════════════════════════════
   PAGINATION
═══════════════════════════════════════ */
.pagi-wrap { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.pagi-info { font-size: .78rem; color: var(--text-muted); }
.pagi { display: flex; gap: 4px; }
.pagi a, .pagi span {
    min-width: 32px; height: 32px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem; font-weight: 700; text-decoration: none;
    transition: var(--trans);
    border: 1px solid var(--border-input);
    color: var(--text-sub); background: var(--bg-input);
}
.pagi a:hover { background: var(--accent-bg); color: var(--accent); border-color: var(--accent-border); }
.pagi span.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.pagi span.disabled { opacity: .35; pointer-events: none; }

/* ═══════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════ */
.empty-state {
    text-align: center; padding: 52px 20px;
    color: var(--text-muted);
}
.empty-state i { font-size: 3rem; opacity: .25; display: block; margin-bottom: 12px; }
.empty-state p { font-size: .875rem; }

/* ═══════════════════════════════════════
   MODAL OVERRIDES
═══════════════════════════════════════ */
.modal-content {
    background: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    border-radius: var(--radius-xl) !important;
    box-shadow: var(--shadow-lg) !important;
    color: var(--text) !important;
}
.modal-header {
    border-bottom: 1px solid var(--border) !important;
    padding: 18px 22px 14px !important;
}
.modal-title {
    font-weight: 900 !important; font-size: 1rem !important; letter-spacing: -.2px;
    display: flex; align-items: center; gap: 9px;
}
.modal-title-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.modal-body   { padding: 20px 22px !important; }
.modal-footer { border-top: 1px solid var(--border) !important; padding: 14px 22px !important; }
.btn-close { filter: var(--close-filter, none) !important; }
[data-theme="dark"] .btn-close { filter: invert(1) !important; }
.modal-backdrop { backdrop-filter: blur(3px); }

/* ═══════════════════════════════════════
   DIVIDER
═══════════════════════════════════════ */
.sdivider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }

/* ═══════════════════════════════════════
   STRENGTH METER
═══════════════════════════════════════ */
.strength-bar {
    height: 4px; border-radius: 2px; margin-top: 6px;
    background: var(--border-input); overflow: hidden; transition: var(--trans);
}
.strength-bar-fill {
    height: 100%; border-radius: 2px;
    transition: width .3s ease, background .3s ease;
    width: 0;
}
.strength-label { font-size: .72rem; color: var(--text-muted); margin-top: 3px; }

/* ═══════════════════════════════════════
   TOAST
═══════════════════════════════════════ */
#toastWrap {
    position: fixed; bottom: 22px; right: 22px;
    z-index: 9999; display: flex; flex-direction: column; gap: 8px;
    pointer-events: none;
}
.toast-item {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 16px; border-radius: 10px;
    font-size: .835rem; font-weight: 700;
    box-shadow: var(--shadow-lg);
    animation: toastIn .22s ease; pointer-events: all;
    max-width: 320px; min-width: 220px;
}
.toast-item.ok  { background: var(--green-bg); border: 1px solid var(--green-border); color: var(--green); }
.toast-item.err { background: var(--red-bg);   border: 1px solid var(--red-border);   color: var(--red); }
.toast-item.out { opacity: 0; transform: translateX(24px); transition: all .22s ease; }
@keyframes toastIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: translateX(0); } }

/* ═══════════════════════════════════════
   LOADER OVERLAY
═══════════════════════════════════════ */
#globalLoader {
    position: fixed; inset: 0; background: var(--modal-overlay);
    display: none; align-items: center; justify-content: center;
    z-index: 9998; backdrop-filter: blur(2px);
}
#globalLoader .spin {
    width: 42px; height: 42px; border-radius: 50%;
    border: 3px solid var(--border-input);
    border-top-color: var(--accent);
    animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ═══════════════════════════════════════
   CONFIRM MODAL
═══════════════════════════════════════ */
.confirm-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--red-bg); border: 2px solid var(--red-border);
    color: var(--red); font-size: 24px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.confirm-target {
    font-family: var(--mono); font-size: .9rem;
    color: var(--accent); background: var(--accent-bg);
    padding: 2px 8px; border-radius: 5px;
}

/* ═══════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════ */
@media (max-width: 768px) {
    .page-container { padding: 16px 12px; }
    .table-card-head { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════
     GLOBAL LOADER
═══════════════════════════════════════ -->
<div id="globalLoader"><div class="spin"></div></div>
<div id="toastWrap"></div>

<!-- ═══════════════════════════════════════
     NAVBAR
═══════════════════════════════════════ -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid px-2">
        <a class="navbar-brand" href="../pages/admin.php">
            <span class="brand-icon">✨</span>
            Amoy Salon
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto d-lg-none">
            <button class="theme-toggle" id="themeToggleMobile" title="Ganti Tema">
                <i class="bi bi-moon-fill" id="themeIconMobile"></i>
            </button>
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="bi bi-list fs-5"></i>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <?php if ($user_level <= 2): ?>
                <li class="nav-item">
                    <a class="nav-link" href="../pages/admin.php">
                        <i class="bi bi-calendar2-check"></i> Booking
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($user_level <= 1): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="../pages/master_data.php">
                        <i class="bi bi-gear"></i> Master Data
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($user_level <= 2): ?>
                <li class="nav-item">
                    <a class="nav-link text-info" href="../pages/laporan.php">
                        <i class="bi bi-bar-chart-line"></i> Pendapatan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../pages/komisi.php">
                        <i class="bi bi-cash-coin"></i> Komisi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-info" href="../pages/laporan_pembayaran.php">
                        <i class="bi bi-book-half"></i> Pembukuan
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($user_level <= 1): ?>
                <li class="nav-item">
                    <a class="nav-link active text-warning" href="user_manage.php">
                        <i class="bi bi-people"></i> Akun
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <div class="nav-divider d-none d-lg-block" style="width:1px;height:22px;background:rgba(255,255,255,.12);margin:0 4px"></div>
                </li>
                <li class="nav-item">
                    <a href="../index.php" target="_blank" class="nav-link btn-booking-online">
                        <i class="bi bi-globe2"></i> Booking Online
                    </a>
                </li>
                <li class="nav-item d-none d-lg-flex align-items-center ms-2">
                    <button class="theme-toggle" id="themeToggle" title="Ganti Tema">
                        <i class="bi bi-moon-fill" id="themeIcon"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn-logout" href="logout.php" onclick="return confirm('Yakin ingin keluar?')">
                        <i class="bi bi-box-arrow-right"></i> Keluar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ═══════════════════════════════════════
     MAIN CONTENT
═══════════════════════════════════════ -->
<div class="page-container">

    <!-- Page Head -->
    <div class="page-head">
        <div>
            <h1 class="page-title"><i class="bi bi-people-fill me-2" style="color:var(--accent)"></i>Manajemen Akun</h1>
            <p class="page-subtitle">Kelola pengguna sistem Amoy Salon — login sebagai <strong><?= htmlspecialchars($user_nama) ?></strong></p>
        </div>
        <button class="btn btn-primary" onclick="openModalTambah()">
            <i class="bi bi-person-plus-fill"></i> Tambah Akun Baru
        </button>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card" style="--c:#6c5ce7;--c-soft:rgba(108,92,231,.1)" onclick="filterLevel('')">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div class="stat-label">Total Akun</div>
            <div class="stat-value"><?= $stat_total ?></div>
        </div>
        <div class="stat-card" style="--c:var(--red);--c-soft:var(--red-bg)" onclick="filterLevel(0)">
            <div class="stat-icon"><i class="bi bi-shield-fill-check"></i></div>
            <div class="stat-label">Super User</div>
            <div class="stat-value"><?= $stat_super ?></div>
        </div>
        <div class="stat-card" style="--c:var(--yellow);--c-soft:var(--yellow-bg)" onclick="filterLevel(1)">
            <div class="stat-icon"><i class="bi bi-person-badge-fill"></i></div>
            <div class="stat-label">Admin</div>
            <div class="stat-value"><?= $stat_admin ?></div>
        </div>
        <div class="stat-card" style="--c:var(--blue);--c-soft:var(--blue-bg)" onclick="filterLevel(2)">
            <div class="stat-icon"><i class="bi bi-person-workspace"></i></div>
            <div class="stat-label">Pegawai</div>
            <div class="stat-value"><?= $stat_pegawai ?></div>
        </div>
        <div class="stat-card" style="--c:var(--text-sub);--c-soft:var(--bg-hover)" onclick="filterLevel(3)">
            <div class="stat-icon"><i class="bi bi-person-fill"></i></div>
            <div class="stat-label">User</div>
            <div class="stat-value"><?= $stat_user ?></div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-card">
        <div class="row g-2 align-items-end">
            <div class="col-md-5 col-sm-12">
                <label class="form-label"><i class="bi bi-search me-1"></i>Cari Nama / Username</label>
                <input type="text" id="searchInput" class="form-control" placeholder="Ketik nama atau username..."
                       value="<?= htmlspecialchars($f_q) ?>" oninput="debounceSearch()">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label"><i class="bi bi-funnel me-1"></i>Filter Level</label>
                <select id="levelFilter" class="form-select" onchange="applyFilter()">
                    <option value="">— Semua Level —</option>
                    <?php foreach([0=>'Super User',1=>'Admin',2=>'Pegawai',3=>'User'] as $lv=>$ln): ?>
                    <option value="<?= $lv ?>" <?= $f_level === $lv ? 'selected' : '' ?>><?= $lv ?> — <?= $ln ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-3">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilter()">
                    <i class="bi bi-x-circle"></i> Reset
                </button>
            </div>
            <div class="col-md-2 col-sm-3">
                <button class="btn btn-primary w-100" onclick="applyFilter()">
                    <i class="bi bi-search"></i> Cari
                </button>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-card-head">
            <div>
                <div class="table-card-title"><i class="bi bi-list-ul me-2" style="color:var(--accent)"></i>Daftar Pengguna Sistem</div>
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px">
                    Menampilkan <?= count($users) ?> dari <?= $total_rows ?> pengguna
                    <?php if ($f_level !== null || $f_q): ?>
                    <span style="color:var(--accent)">• Filter aktif</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:46px">#</th>
                        <th>Pengguna</th>
                        <th>Username</th>
                        <th>Level Akses</th>
                        <th>Bergabung</th>
                        <th style="width:160px;text-align:center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-person-x"></i>
                                <p>Tidak ada pengguna ditemukan.</p>
                                <?php if ($f_level !== null || $f_q): ?>
                                <button class="btn btn-outline-secondary btn-sm mt-2" onclick="resetFilter()">
                                    <i class="bi bi-x-circle"></i> Hapus Filter
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $i => $u):
                        $isSelf = ((int)$u['id_user'] === (int)($_SESSION['id_user'] ?? 0));
                        $lv     = (int)$u['level'];
                        $initials = strtoupper(substr($u['nama_lengkap'], 0, 1) . (strpos($u['nama_lengkap'], ' ') !== false ? substr(strrchr($u['nama_lengkap'], ' '), 1, 1) : ''));
                        $joined = $u['created_at'] ? date('d M Y', strtotime($u['created_at'])) : '—';
                    ?>
                    <tr class="<?= $isSelf ? 'is-self' : '' ?>">
                        <td>
                            <span style="font-family:var(--mono);font-size:.78rem;color:var(--text-muted)">
                                <?= ($offset + $i + 1) ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="user-avatar av-<?= $lv ?>"><?= htmlspecialchars($initials ?: '?') ?></div>
                                <div>
                                    <div style="font-weight:700;font-size:.875rem">
                                        <?= htmlspecialchars($u['nama_lengkap']) ?>
                                        <?php if ($isSelf): ?><span class="you-chip"><i class="bi bi-person-check-fill"></i> Anda</span><?php endif; ?>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--text-muted)">ID #<?= $u['id_user'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                        <td>
                            <span class="level-badge lb-<?= $lv ?>">
                                <i class="bi bi-<?= $lvl_icon[$lv] ?>"></i>
                                <?= $lvl_label[$lv] ?>
                            </span>
                        </td>
                        <td style="font-size:.8rem;color:var(--text-sub)"><?= $joined ?></td>
                        <td>
                            <div style="display:flex;gap:5px;justify-content:center">
                                <button class="btn btn-xs btn-act-edit" title="Edit Akun"
                                        onclick="openModalEdit(<?= $u['id_user'] ?>)">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-xs btn-act-pw" title="Reset Password"
                                        onclick="openModalResetPw(<?= $u['id_user'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                                    <i class="bi bi-key-fill"></i>
                                </button>
                                <?php if (!$isSelf): ?>
                                <button class="btn btn-xs btn-act-del" title="Hapus Akun"
                                        onclick="confirmHapus(<?= $u['id_user'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-xs" style="opacity:.25;cursor:not-allowed;background:var(--bg-hover)!important;border:1px solid var(--border)!important;color:var(--text-muted)!important" disabled title="Tidak bisa menghapus akun sendiri">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagi-wrap">
            <span class="pagi-info">Halaman <?= $page ?> dari <?= $total_pages ?></span>
            <div class="pagi">
                <?php
                $base_url = '?level=' . ($f_level ?? '') . '&q=' . urlencode($f_q) . '&page=';
                ?>
                <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $base_url . ($page - 1) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="active"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= $base_url . $p ?>"><?= $p ?></a>
                <?php endif; ?>
                <?php endfor; ?>
                <a class="<?= $page >= $total_pages ? 'disabled' : '' ?>" href="<?= $base_url . ($page + 1) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div><!-- /page-container -->


<!-- ═══════════════════════════════════════
     MODAL: TAMBAH AKUN
═══════════════════════════════════════ -->
<div class="modal fade" id="mTambah" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="modal-title-icon" style="background:var(--accent-bg);color:var(--accent)">
                        <i class="bi bi-person-plus-fill"></i>
                    </span>
                    Tambah Akun Baru
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap <span style="color:var(--red)">*</span></label>
                    <input type="text" id="t-nama" class="form-control" placeholder="cth. Budi Santoso" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Username <span style="color:var(--red)">*</span></label>
                    <input type="text" id="t-username" class="form-control" placeholder="cth. budi123" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password <span style="color:var(--red)">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="t-password" class="form-control" placeholder="Min. 6 karakter"
                               autocomplete="new-password" oninput="checkStrength('t-password','t-strength','t-strength-label')">
                        <button class="pwd-toggle" type="button" onclick="togglePwd('t-password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="strength-bar"><div class="strength-bar-fill" id="t-strength"></div></div>
                    <div class="strength-label" id="t-strength-label"></div>
                </div>
                <div class="mb-1">
                    <label class="form-label">Level Akses <span style="color:var(--red)">*</span></label>
                    <select id="t-level" class="form-select">
                        <?php if ($user_level === 0): ?>
                        <option value="0">0 — Super User</option>
                        <?php endif; ?>
                        <option value="1">1 — Admin</option>
                        <option value="2" selected>2 — Pegawai</option>
                        <option value="3">3 — User</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" onclick="doTambah()">
                    <i class="bi bi-plus-circle-fill"></i> Simpan Akun
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     MODAL: EDIT AKUN
═══════════════════════════════════════ -->
<div class="modal fade" id="mEdit" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="modal-title-icon" style="background:var(--accent-bg);color:var(--accent)">
                        <i class="bi bi-pencil-square"></i>
                    </span>
                    Edit Akun
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="e-id">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap <span style="color:var(--red)">*</span></label>
                    <input type="text" id="e-nama" class="form-control" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Username <span style="color:var(--red)">*</span></label>
                    <input type="text" id="e-username" class="form-control" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Baru <span style="color:var(--text-muted);font-weight:400">(kosongkan jika tidak diubah)</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="e-password" class="form-control" placeholder="Kosongkan jika tidak diubah"
                               autocomplete="new-password" oninput="checkStrength('e-password','e-strength','e-strength-label')">
                        <button class="pwd-toggle" type="button" onclick="togglePwd('e-password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="strength-bar"><div class="strength-bar-fill" id="e-strength"></div></div>
                    <div class="strength-label" id="e-strength-label"></div>
                </div>
                <div class="mb-1">
                    <label class="form-label">Level Akses <span style="color:var(--red)">*</span></label>
                    <select id="e-level" class="form-select">
                        <?php if ($user_level === 0): ?>
                        <option value="0">0 — Super User</option>
                        <?php endif; ?>
                        <option value="1">1 — Admin</option>
                        <option value="2">2 — Pegawai</option>
                        <option value="3">3 — User</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" onclick="doEdit()">
                    <i class="bi bi-check-circle-fill"></i> Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     MODAL: RESET PASSWORD
═══════════════════════════════════════ -->
<div class="modal fade" id="mResetPw" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="modal-title-icon" style="background:var(--yellow-bg);color:var(--yellow)">
                        <i class="bi bi-key-fill"></i>
                    </span>
                    Reset Password
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rp-id">
                <div style="font-size:.84rem;color:var(--text-sub);margin-bottom:14px;padding:10px 14px;background:var(--yellow-bg);border:1px solid var(--yellow-border);border-radius:8px">
                    <i class="bi bi-info-circle-fill me-2" style="color:var(--yellow)"></i>
                    Mereset password untuk akun: <strong id="rp-username" style="font-family:var(--mono);color:var(--accent)"></strong>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Baru <span style="color:var(--red)">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="rp-new" class="form-control" placeholder="Min. 6 karakter"
                               autocomplete="new-password" oninput="checkStrength('rp-new','rp-strength','rp-strength-label')">
                        <button class="pwd-toggle" type="button" onclick="togglePwd('rp-new', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="strength-bar"><div class="strength-bar-fill" id="rp-strength"></div></div>
                    <div class="strength-label" id="rp-strength-label"></div>
                </div>
                <div class="mb-1">
                    <label class="form-label">Konfirmasi Password <span style="color:var(--red)">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="rp-confirm" class="form-control" placeholder="Ulangi password baru"
                               autocomplete="new-password">
                        <button class="pwd-toggle" type="button" onclick="togglePwd('rp-confirm', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn" style="background:var(--yellow)!important;border:none!important;color:#fff!important" onclick="doResetPw()">
                    <i class="bi bi-check-circle-fill"></i> Reset Password
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     MODAL: KONFIRMASI HAPUS
═══════════════════════════════════════ -->
<div class="modal fade" id="mHapus" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:none!important;padding-bottom:0!important">
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center" style="padding-top:8px!important">
                <input type="hidden" id="h-id">
                <div class="confirm-icon"><i class="bi bi-trash-fill"></i></div>
                <div style="font-size:1rem;font-weight:900;margin-bottom:6px">Hapus Akun?</div>
                <div style="font-size:.84rem;color:var(--text-sub);line-height:1.5">
                    Akun <span class="confirm-target" id="h-username"></span> akan dihapus secara permanen dan tidak dapat dipulihkan.
                </div>
            </div>
            <div class="modal-footer" style="justify-content:center;gap:10px">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" style="min-width:100px">Batal</button>
                <button class="btn btn-act-del" onclick="doHapus()" style="min-width:100px;background:var(--red)!important;color:#fff!important;border:none!important">
                    <i class="bi bi-trash-fill"></i> Hapus
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ════════════════════════════════════════════
   THEME TOGGLE
════════════════════════════════════════════ */
const html            = document.documentElement;
const themeBtn        = document.getElementById('themeToggle');
const themeIcon       = document.getElementById('themeIcon');
const themeBtnMobile  = document.getElementById('themeToggleMobile');
const themeIconMobile = document.getElementById('themeIconMobile');
const savedTheme      = localStorage.getItem('amoy_theme') || 'light';

function applyTheme(t) {
    html.setAttribute('data-theme', t);
    const iconClass = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    if (themeIcon)       themeIcon.className       = iconClass;
    if (themeIconMobile) themeIconMobile.className = iconClass;
    localStorage.setItem('amoy_theme', t);
}
applyTheme(savedTheme);

function toggleThemeHandler() {
    applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
}
if (themeBtn)       themeBtn.addEventListener('click', toggleThemeHandler);
if (themeBtnMobile) themeBtnMobile.addEventListener('click', toggleThemeHandler);

/* ════════════════════════════════════════════
   BOOTSTRAP MODAL INSTANCES
════════════════════════════════════════════ */
const BSModalTambah  = new bootstrap.Modal(document.getElementById('mTambah'));
const BSModalEdit    = new bootstrap.Modal(document.getElementById('mEdit'));
const BSModalResetPw = new bootstrap.Modal(document.getElementById('mResetPw'));
const BSModalHapus   = new bootstrap.Modal(document.getElementById('mHapus'));

/* ════════════════════════════════════════════
   LOADER & TOAST
════════════════════════════════════════════ */
function loader(show) {
    document.getElementById('globalLoader').style.display = show ? 'flex' : 'none';
}
function toast(msg, type = 'ok') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className = 'toast-item ' + type;
    el.innerHTML = `<i class="bi bi-${type === 'ok' ? 'check-circle-fill' : 'exclamation-circle-fill'}"></i>${escHtml(msg)}`;
    wrap.appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 250); }, 3200);
}

/* ════════════════════════════════════════════
   PASSWORD TOGGLE & STRENGTH
════════════════════════════════════════════ */
function togglePwd(inputId, btn) {
    const inp = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function checkStrength(inputId, barId, labelId) {
    const val   = document.getElementById(inputId).value;
    const bar   = document.getElementById(barId);
    const label = document.getElementById(labelId);
    if (!bar || !label) return;
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val))   score++;
    if (/[0-9]/.test(val))   score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { w: '0%',   bg: 'transparent', text: '' },
        { w: '20%',  bg: '#e74c3c', text: 'Sangat Lemah' },
        { w: '40%',  bg: '#f39c12', text: 'Lemah' },
        { w: '60%',  bg: '#f1c40f', text: 'Cukup' },
        { w: '80%',  bg: '#2ecc71', text: 'Kuat' },
        { w: '100%', bg: '#00b894', text: 'Sangat Kuat' },
    ];
    const lv = val.length === 0 ? 0 : Math.max(1, Math.min(score, 5));
    bar.style.width      = levels[lv].w;
    bar.style.background = levels[lv].bg;
    label.textContent    = levels[lv].text;
    label.style.color    = levels[lv].bg;
}

/* ════════════════════════════════════════════
   FILTER & SEARCH
════════════════════════════════════════════ */
let searchTimer = null;
function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilter, 400);
}
function applyFilter() {
    const q   = document.getElementById('searchInput').value.trim();
    const lv  = document.getElementById('levelFilter').value;
    const url = new URL(location.href);
    url.searchParams.set('q', q);
    if (lv !== '') url.searchParams.set('level', lv);
    else url.searchParams.delete('level');
    url.searchParams.set('page', '1');
    location.href = url.toString();
}
function resetFilter() {
    location.href = 'user_manage.php';
}
function filterLevel(lv) {
    const url = new URL(location.href);
    if (lv === '') url.searchParams.delete('level');
    else url.searchParams.set('level', lv);
    url.searchParams.delete('q');
    url.searchParams.set('page', '1');
    location.href = url.toString();
}

/* ════════════════════════════════════════════
   AJAX HELPER
════════════════════════════════════════════ */
async function ajaxPost(data) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    const res  = await fetch(location.href, { method: 'POST', body: fd });
    return await res.json();
}

/* ════════════════════════════════════════════
   TAMBAH AKUN
════════════════════════════════════════════ */
function openModalTambah() {
    document.getElementById('t-nama').value     = '';
    document.getElementById('t-username').value = '';
    document.getElementById('t-password').value = '';
    document.getElementById('t-level').value    = '2';
    // Reset strength bars
    ['t-strength','t-strength-label'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.tagName === 'DIV') { el.style.width = '0'; }
        else if (el) el.textContent = '';
    });
    checkStrength('t-password', 't-strength', 't-strength-label');
    BSModalTambah.show();
    setTimeout(() => document.getElementById('t-nama').focus(), 300);
}

async function doTambah() {
    const nama     = document.getElementById('t-nama').value.trim();
    const username = document.getElementById('t-username').value.trim();
    const password = document.getElementById('t-password').value.trim();
    const level    = document.getElementById('t-level').value;

    if (!nama || !username || !password) { toast('Semua field wajib diisi.', 'err'); return; }
    if (password.length < 6) { toast('Password minimal 6 karakter.', 'err'); return; }

    loader(true);
    try {
        const data = await ajaxPost({ ajax_action:'tambah_user', nama_lengkap:nama, username, password, level });
        if (data.success) {
            toast(data.message, 'ok');
            BSModalTambah.hide();
            setTimeout(() => location.reload(), 800);
        } else {
            toast(data.message || 'Gagal menyimpan.', 'err');
        }
    } catch(e) { toast('Terjadi kesalahan: ' + e.message, 'err'); }
    finally   { loader(false); }
}

/* ════════════════════════════════════════════
   EDIT AKUN
════════════════════════════════════════════ */
async function openModalEdit(id) {
    loader(true);
    try {
        const data = await ajaxPost({ ajax_action: 'get_user', id_user: id });
        if (!data.success) { toast(data.message || 'Gagal memuat data.', 'err'); return; }
        const u = data.user;
        document.getElementById('e-id').value       = u.id_user;
        document.getElementById('e-nama').value     = u.nama_lengkap;
        document.getElementById('e-username').value = u.username;
        document.getElementById('e-password').value = '';
        document.getElementById('e-level').value    = u.level;
        checkStrength('e-password', 'e-strength', 'e-strength-label');
        BSModalEdit.show();
        setTimeout(() => document.getElementById('e-nama').focus(), 300);
    } catch(e) { toast('Terjadi kesalahan: ' + e.message, 'err'); }
    finally   { loader(false); }
}

async function doEdit() {
    const id       = document.getElementById('e-id').value;
    const nama     = document.getElementById('e-nama').value.trim();
    const username = document.getElementById('e-username').value.trim();
    const password = document.getElementById('e-password').value;
    const level    = document.getElementById('e-level').value;

    if (!nama || !username) { toast('Nama dan username tidak boleh kosong.', 'err'); return; }
    if (password !== '' && password.length < 6) { toast('Password minimal 6 karakter.', 'err'); return; }

    loader(true);
    try {
        const data = await ajaxPost({ ajax_action:'edit_user', id_user:id, nama_lengkap:nama, username, password, level });
        if (data.success) {
            toast(data.message, 'ok');
            BSModalEdit.hide();
            setTimeout(() => location.reload(), 800);
        } else {
            toast(data.message || 'Gagal memperbarui.', 'err');
        }
    } catch(e) { toast('Terjadi kesalahan: ' + e.message, 'err'); }
    finally   { loader(false); }
}

/* ════════════════════════════════════════════
   RESET PASSWORD
════════════════════════════════════════════ */
function openModalResetPw(id, username) {
    document.getElementById('rp-id').value         = id;
    document.getElementById('rp-username').textContent = username;
    document.getElementById('rp-new').value        = '';
    document.getElementById('rp-confirm').value    = '';
    checkStrength('rp-new', 'rp-strength', 'rp-strength-label');
    BSModalResetPw.show();
    setTimeout(() => document.getElementById('rp-new').focus(), 300);
}

async function doResetPw() {
    const id      = document.getElementById('rp-id').value;
    const newPw   = document.getElementById('rp-new').value;
    const confirm = document.getElementById('rp-confirm').value;

    if (!newPw)          { toast('Password baru tidak boleh kosong.', 'err'); return; }
    if (newPw.length < 6){ toast('Password minimal 6 karakter.', 'err'); return; }
    if (newPw !== confirm){ toast('Konfirmasi password tidak cocok.', 'err'); return; }

    loader(true);
    try {
        const data = await ajaxPost({ ajax_action:'reset_password', id_user:id, new_password:newPw, confirm_password:confirm });
        if (data.success) {
            toast(data.message, 'ok');
            BSModalResetPw.hide();
        } else {
            toast(data.message || 'Gagal mereset password.', 'err');
        }
    } catch(e) { toast('Terjadi kesalahan: ' + e.message, 'err'); }
    finally   { loader(false); }
}

/* ════════════════════════════════════════════
   HAPUS AKUN
════════════════════════════════════════════ */
function confirmHapus(id, username) {
    document.getElementById('h-id').value           = id;
    document.getElementById('h-username').textContent = username;
    BSModalHapus.show();
}

async function doHapus() {
    const id = document.getElementById('h-id').value;
    loader(true);
    try {
        const data = await ajaxPost({ ajax_action:'hapus_user', id_user:id });
        if (data.success) {
            toast(data.message, 'ok');
            BSModalHapus.hide();
            setTimeout(() => location.reload(), 800);
        } else {
            toast(data.message || 'Gagal menghapus.', 'err');
        }
    } catch(e) { toast('Terjadi kesalahan: ' + e.message, 'err'); }
    finally   { loader(false); }
}

/* ════════════════════════════════════════════
   UTILS
════════════════════════════════════════════ */
function escHtml(s) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(s || ''));
    return d.innerHTML;
}

// Enter key shortcut di form modal
document.getElementById('mTambah').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doTambah(); }
});
document.getElementById('mEdit').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doEdit(); }
});
document.getElementById('mResetPw').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doResetPw(); }
});
</script>
</body>
</html>