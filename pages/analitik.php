<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
$user_level = $_SESSION['level'];
$user_nama  = $_SESSION['nama'] ?? $_SESSION['username'];
include '../class/koneksi.php';

// ─── FILTER ─────────────────────────────────────────────────────────────
$tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-t');
$tgl_awal_esc  = mysqli_real_escape_string($conn, $tgl_awal);
$tgl_akhir_esc = mysqli_real_escape_string($conn, $tgl_akhir);
$days_diff = max(1, (int)((strtotime($tgl_akhir) - strtotime($tgl_awal)) / 86400) + 1);
$prev_awal  = date('Y-m-d', strtotime($tgl_awal) - $days_diff * 86400);
$prev_akhir = date('Y-m-d', strtotime($tgl_awal) - 86400);

// Condition strings — selalu tanpa alias 'b.' untuk subquery sederhana
$cond_b = "b.tgl_booking BETWEEN '$tgl_awal_esc' AND '$tgl_akhir_esc' AND b.status_pembayaran != 'batal'";
$cond_plain = "tgl_booking BETWEEN '$tgl_awal_esc' AND '$tgl_akhir_esc' AND status_pembayaran != 'batal'";

// ═══════════════════════════════════════════════════════════════════════════
// 1. KPI SUMMARY
// ═══════════════════════════════════════════════════════════════════════════
$r_kpi = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        COALESCE(SUM(bayar_cash+bayar_transfer),0) AS omzet,
        COUNT(*) AS bookings,
        COUNT(DISTINCT nama_customer) AS customers,
        COALESCE(AVG(bayar_cash+bayar_transfer),0) AS avg_order
     FROM bookings
     WHERE $cond_plain"));

// ═══════════════════════════════════════════════════════════════════════════
// 2. REVENUE TREND ANALYSIS — daily + 7-day moving average
// ═══════════════════════════════════════════════════════════════════════════
$q_trend = mysqli_query($conn,
    "SELECT DATE_FORMAT(tgl_booking,'%d %b') AS lbl,
            tgl_booking AS raw_date,
            COALESCE(SUM(bayar_cash+bayar_transfer),0) AS revenue,
            COUNT(*) AS bookings,
            COALESCE(AVG(bayar_cash+bayar_transfer),0) AS avg_rev
     FROM bookings
     WHERE $cond_plain
     GROUP BY tgl_booking
     ORDER BY tgl_booking ASC");

$trend_rows = [];
while ($rw = mysqli_fetch_assoc($q_trend)) $trend_rows[] = $rw;

$trend_labels = $trend_rev = $trend_bk = $trend_ma7 = [];
foreach ($trend_rows as $i => $rw) {
    $trend_labels[] = $rw['lbl'];
    $trend_rev[]    = (float)$rw['revenue'];
    $trend_bk[]     = (int)$rw['bookings'];
    $start  = max(0, $i - 6);
    $window = array_slice($trend_rows, $start, $i - $start + 1);
    $trend_ma7[] = count($window) > 0
        ? round(array_sum(array_column($window, 'revenue')) / count($window))
        : 0;
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. SERVICE POPULARITY (top 12) — tanpa kolom 'kategori' yang tidak ada
// ═══════════════════════════════════════════════════════════════════════════
$q_svc = mysqli_query($conn,
    "SELECT s.nama_layanan,
            COUNT(bd.id_detail)         AS freq,
            COALESCE(SUM(bd.subtotal),0) AS revenue,
            COALESCE(AVG(bd.subtotal),0) AS avg_price
     FROM services s
     JOIN booking_details bd ON s.id_service = bd.id_service
     JOIN bookings b ON bd.id_booking = b.id_booking
     WHERE $cond_b
     GROUP BY s.id_service
     ORDER BY freq DESC
     LIMIT 12");
$svc_rows = [];
while ($rw = mysqli_fetch_assoc($q_svc)) $svc_rows[] = $rw;
$svc_labels  = array_column($svc_rows, 'nama_layanan');
$svc_freq    = array_map('intval',   array_column($svc_rows, 'freq'));
$svc_revenue = array_map('floatval', array_column($svc_rows, 'revenue'));

// ═══════════════════════════════════════════════════════════════════════════
// 4. SERVICE REVENUE CONTRIBUTION (Donut) — top 8 + others
// ═══════════════════════════════════════════════════════════════════════════
$q_contrib = mysqli_query($conn,
    "SELECT s.nama_layanan, COALESCE(SUM(bd.subtotal),0) AS revenue
     FROM services s
     JOIN booking_details bd ON s.id_service = bd.id_service
     JOIN bookings b ON bd.id_booking = b.id_booking
     WHERE $cond_b
     GROUP BY s.id_service
     ORDER BY revenue DESC");
$contrib_rows = [];
while ($rw = mysqli_fetch_assoc($q_contrib)) $contrib_rows[] = $rw;
$total_svc_rev = array_sum(array_column($contrib_rows, 'revenue'));
$top8 = array_slice($contrib_rows, 0, 8);
$others_rev = array_sum(array_column(array_slice($contrib_rows, 8), 'revenue'));
if ($others_rev > 0) $top8[] = ['nama_layanan' => 'Lainnya', 'revenue' => $others_rev];
$contrib_labels = array_column($top8, 'nama_layanan');
$contrib_vals   = array_map('floatval', array_column($top8, 'revenue'));

// ═══════════════════════════════════════════════════════════════════════════
// 5. CUSTOMER FREQUENCY ANALYSIS
// ═══════════════════════════════════════════════════════════════════════════
$q_cust = mysqli_query($conn,
    "SELECT nama_customer,
            COUNT(*)                                       AS visits,
            COALESCE(SUM(bayar_cash+bayar_transfer),0)    AS total_spend,
            COALESCE(AVG(bayar_cash+bayar_transfer),0)    AS avg_spend,
            MAX(tgl_booking) AS last_visit,
            MIN(tgl_booking) AS first_visit
     FROM bookings
     WHERE $cond_plain
     GROUP BY nama_customer
     ORDER BY visits DESC, total_spend DESC");
$cust_rows = [];
$freq_dist = ['1' => 0, '2' => 0, '3-5' => 0, '6+' => 0];
while ($rw = mysqli_fetch_assoc($q_cust)) {
    $cust_rows[] = $rw;
    $v = (int)$rw['visits'];
    if      ($v == 1) $freq_dist['1']++;
    elseif  ($v == 2) $freq_dist['2']++;
    elseif  ($v <= 5) $freq_dist['3-5']++;
    else              $freq_dist['6+']++;
}
$total_custs = count($cust_rows);
$loyal_custs = count(array_filter($cust_rows, fn($c) => (int)$c['visits'] >= 3));
$new_custs   = count(array_filter($cust_rows, fn($c) => (int)$c['visits'] == 1));

// ═══════════════════════════════════════════════════════════════════════════
// 6. PARETO ANALYSIS
// ═══════════════════════════════════════════════════════════════════════════
$pareto_services = $contrib_rows; // already sorted desc by revenue
$pareto_cumulative = [];
$running = 0;
foreach ($pareto_services as $ps) {
    $running += (float)$ps['revenue'];
    $pareto_cumulative[] = $total_svc_rev > 0
        ? round($running / $total_svc_rev * 100, 1) : 0;
}
$pareto_80_idx = 0;
foreach ($pareto_cumulative as $i => $pct) {
    if ($pct >= 80) { $pareto_80_idx = $i + 1; break; }
}
$p_slice       = array_slice($pareto_services,   0, 12);
$p_cum_slice   = array_slice($pareto_cumulative, 0, 12);
$pareto_labels = array_column($p_slice, 'nama_layanan');
$pareto_revs   = array_map(fn($r) => (float)$r['revenue'], $p_slice);
$pareto_cum    = $p_cum_slice;

// ═══════════════════════════════════════════════════════════════════════════
// 7. SEASONALITY ANALYSIS — jam + hari
// ═══════════════════════════════════════════════════════════════════════════
$q_hour = mysqli_query($conn,
    "SELECT HOUR(jam_booking) AS hour_slot,
            COUNT(*) AS cnt,
            COALESCE(SUM(bayar_cash+bayar_transfer),0) AS rev
     FROM bookings
     WHERE $cond_plain
     GROUP BY hour_slot
     ORDER BY hour_slot ASC");
$hour_map = [];
while ($rw = mysqli_fetch_assoc($q_hour)) $hour_map[(int)$rw['hour_slot']] = $rw;
$hour_labels = $hour_cnt = $hour_rev = [];
for ($h = 8; $h <= 20; $h++) {
    $hour_labels[] = sprintf('%02d:00', $h);
    $hour_cnt[]    = (int)($hour_map[$h]['cnt'] ?? 0);
    $hour_rev[]    = (float)($hour_map[$h]['rev'] ?? 0);
}

$q_dow = mysqli_query($conn,
    "SELECT DAYOFWEEK(tgl_booking) AS dow,
            COUNT(*) AS cnt,
            COALESCE(SUM(bayar_cash+bayar_transfer),0) AS rev
     FROM bookings
     WHERE $cond_plain
     GROUP BY dow
     ORDER BY dow ASC");
$hari_id = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$dow_map = [];
while ($rw = mysqli_fetch_assoc($q_dow)) $dow_map[(int)$rw['dow']] = $rw;
$dow_labels = $dow_cnt = $dow_rev = [];
for ($d = 1; $d <= 7; $d++) {
    if (isset($dow_map[$d])) {
        $dow_labels[] = $hari_id[$d - 1];
        $dow_cnt[]    = (int)$dow_map[$d]['cnt'];
        $dow_rev[]    = (float)$dow_map[$d]['rev'];
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 8. DEMAND FORECASTING — Linear Regression
// ═══════════════════════════════════════════════════════════════════════════
$n = count($trend_rows);
$forecast_labels = $trend_labels; // start with actual labels
$forecast_actual = $trend_rev;
$forecast_pred   = [];
$slope = 0; $intercept = 0;
$trend_direction = 'stabil'; $trend_pct = 0;

if ($n >= 2) {
    $x_vals = range(0, $n - 1);
    $y_vals = $trend_rev;
    $x_mean = array_sum($x_vals) / $n;
    $y_mean = array_sum($y_vals) / $n;
    $num = $den = 0;
    foreach ($x_vals as $i => $x) {
        $num += ($x - $x_mean) * ($y_vals[$i] - $y_mean);
        $den += ($x - $x_mean) ** 2;
    }
    $slope     = $den != 0 ? $num / $den : 0;
    $intercept = $y_mean - $slope * $x_mean;
    foreach ($x_vals as $x) $forecast_pred[] = max(0, round($intercept + $slope * $x));

    for ($f = 0; $f < 7; $f++) {
        $xi = $n + $f;
        $forecast_labels[] = 'F+' . ($f + 1);
        $forecast_actual[] = null;
        $forecast_pred[]   = max(0, round($intercept + $slope * $xi));
    }
    $trend_direction = $slope > 0 ? 'naik' : ($slope < 0 ? 'turun' : 'stabil');
    $trend_pct = $y_mean > 0 ? round(abs($slope / $y_mean * 100), 1) : 0;
} else {
    $forecast_pred = $trend_rev;
}

// ═══════════════════════════════════════════════════════════════════════════
// 9. MONTHLY FORECAST
// ═══════════════════════════════════════════════════════════════════════════
$q_monthly = mysqli_query($conn,
    "SELECT DATE_FORMAT(tgl_booking,'%Y-%m') AS ym,
            DATE_FORMAT(tgl_booking,'%b %Y') AS lbl,
            COALESCE(SUM(bayar_cash+bayar_transfer),0) AS revenue,
            COUNT(*) AS bookings
     FROM bookings
     WHERE status_pembayaran != 'batal'
     GROUP BY ym
     ORDER BY ym ASC
     LIMIT 12");
$monthly_rows = [];
while ($rw = mysqli_fetch_assoc($q_monthly)) $monthly_rows[] = $rw;
$monthly_labels  = array_column($monthly_rows, 'lbl');
$monthly_revenue = array_map('floatval', array_column($monthly_rows, 'revenue'));
$nm = count($monthly_rows);
$proj_revenue = [];
if ($nm >= 2) {
    $mn_x = range(0, $nm - 1); $mn_y = $monthly_revenue;
    $mx = array_sum($mn_x) / $nm; $my = array_sum($mn_y) / $nm;
    $mn_num = $mn_den = 0;
    foreach ($mn_x as $i => $x) { $mn_num += ($x-$mx)*($mn_y[$i]-$my); $mn_den += ($x-$mx)**2; }
    $mn_slope = $mn_den != 0 ? $mn_num / $mn_den : 0;
    $mn_int   = $my - $mn_slope * $mx;
    foreach ($mn_x as $x) $proj_revenue[] = max(0, round($mn_int + $mn_slope * $x));
    for ($f = 1; $f <= 3; $f++) {
        $monthly_labels[]  = 'Proyeksi +' . $f . ' bln';
        $monthly_revenue[] = null;
        $proj_revenue[]    = max(0, round($mn_int + $mn_slope * ($nm - 1 + $f)));
    }
} else {
    $proj_revenue = $monthly_revenue;
}

// ═══════════════════════════════════════════════════════════════════════════
// 10. RFM ANALYSIS
// ═══════════════════════════════════════════════════════════════════════════
$q_rfm = mysqli_query($conn,
    "SELECT nama_customer,
            DATEDIFF('$tgl_akhir_esc', MAX(tgl_booking)) AS recency,
            COUNT(*) AS frequency,
            COALESCE(SUM(bayar_cash+bayar_transfer),0) AS monetary
     FROM bookings
     WHERE status_pembayaran != 'batal' AND tgl_booking <= '$tgl_akhir_esc'
     GROUP BY nama_customer");
$rfm_rows = [];
while ($rw = mysqli_fetch_assoc($q_rfm)) $rfm_rows[] = $rw;

function rfm_score_fn($val, array $vals, bool $reverse = false): int {
    if (empty($vals)) return 3;
    $sorted = $vals; sort($sorted); $n = count($sorted);
    [$q1,$q2,$q3,$q4] = [
        $sorted[(int)($n*0.2)], $sorted[(int)($n*0.4)],
        $sorted[(int)($n*0.6)], $sorted[(int)($n*0.8)],
    ];
    if ($reverse) {
        if ($val <= $q1) return 5; if ($val <= $q2) return 4;
        if ($val <= $q3) return 3; if ($val <= $q4) return 2; return 1;
    } else {
        if ($val <= $q1) return 1; if ($val <= $q2) return 2;
        if ($val <= $q3) return 3; if ($val <= $q4) return 4; return 5;
    }
}
$r_vals = array_map('intval',   array_column($rfm_rows, 'recency'));
$f_vals = array_map('intval',   array_column($rfm_rows, 'frequency'));
$m_vals = array_map('floatval', array_column($rfm_rows, 'monetary'));

$rfm_scored = [];
foreach ($rfm_rows as $rw) {
    $r = rfm_score_fn((int)$rw['recency'],    $r_vals, true);
    $f = rfm_score_fn((int)$rw['frequency'],  $f_vals, false);
    $m = rfm_score_fn((float)$rw['monetary'], $m_vals, false);
    $total = $r + $f + $m;
    if      ($total >= 13)              $seg = 'Champions';
    elseif  ($total >= 10)              $seg = 'Loyal Customers';
    elseif  ($r >= 4 && $f <= 2)       $seg = 'New Customers';
    elseif  ($r <= 2 && $f >= 3)       $seg = 'At Risk';
    elseif  ($r <= 2)                   $seg = 'Lost';
    else                                $seg = 'Potential Loyalists';
    $rfm_scored[] = array_merge($rw, ['r'=>$r,'f'=>$f,'m'=>$m,'segment'=>$seg,'score'=>$total]);
}
usort($rfm_scored, fn($a, $b) => $b['score'] <=> $a['score']);

$seg_colors = [
    'Champions'           => '#6c5ce7',
    'Loyal Customers'     => '#00b894',
    'Potential Loyalists' => '#0984e3',
    'New Customers'       => '#fdcb6e',
    'At Risk'             => '#e17055',
    'Lost'                => '#b2bec3',
];
$rfm_segments = [];
foreach ($rfm_scored as $rw) {
    $seg = $rw['segment'];
    if (!isset($rfm_segments[$seg])) {
        $rfm_segments[$seg] = ['count' => 0, 'revenue' => 0, 'color' => $seg_colors[$seg] ?? '#999'];
    }
    $rfm_segments[$seg]['count']++;
    $rfm_segments[$seg]['revenue'] += (float)$rw['monetary'];
}

// ═══════════════════════════════════════════════════════════════════════════
// 11. MARKET BASKET ANALYSIS
// ═══════════════════════════════════════════════════════════════════════════
$q_basket = mysqli_query($conn,
    "SELECT bd.id_booking,
            GROUP_CONCAT(s.nama_layanan ORDER BY s.id_service SEPARATOR '|||') AS services,
            COUNT(bd.id_detail) AS svc_count
     FROM booking_details bd
     JOIN services s ON bd.id_service = s.id_service
     JOIN bookings b ON bd.id_booking = b.id_booking
     WHERE $cond_b
     GROUP BY bd.id_booking
     HAVING svc_count > 1");
$pair_counts = [];
$total_baskets = 0;
while ($rw = mysqli_fetch_assoc($q_basket)) {
    $total_baskets++;
    $svcs = array_unique(explode('|||', $rw['services']));
    for ($i = 0; $i < count($svcs); $i++) {
        for ($j = $i + 1; $j < count($svcs); $j++) {
            $key = $svcs[$i] . ' + ' . $svcs[$j];
            $pair_counts[$key] = ($pair_counts[$key] ?? 0) + 1;
        }
    }
}
arsort($pair_counts);
$basket_pairs  = array_slice($pair_counts, 0, 10, true);
$basket_labels = array_keys($basket_pairs);
$basket_vals   = array_values($basket_pairs);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analitik Bisnis — Amoy Salon</title>
<link rel="icon" type="image/png" href="../asset/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
/* ═══════════════════════════════════════
   DESIGN TOKENS — selaras laporan_pembayaran.php
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
  --cyan:          #4fd1c5;
  --cyan-bg:       rgba(79,209,197,.1);
  --orange:        #e17055;
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
  border-radius: 7px; transition: var(--trans);
  display: flex; align-items: center; gap: 5px;
}
.nav-link:hover, .nav-link.active { background: rgba(255,255,255,.08); color: var(--nav-active) !important; }
.nav-link.text-warning { color: #f39c12 !important; }
.nav-link.text-info    { color: #74b9ff !important; }
.btn-booking-online {
  font-size: .8rem; font-weight: 700; padding: .3rem .9rem;
  border: 1px solid rgba(255,255,255,.2); border-radius: 7px;
  color: #fff !important; transition: var(--trans);
}
.btn-booking-online:hover { background: rgba(255,255,255,.12); }
.btn-logout { color: #ff6b6b !important; font-weight: 700; }
.btn-logout:hover { background: rgba(255,107,107,.12) !important; }
.theme-toggle {
  width: 36px; height: 36px; border-radius: 8px;
  border: 1px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.06); color: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; transition: var(--trans);
}
.theme-toggle:hover { background: rgba(255,255,255,.15); }

/* ═══ PAGE TABS ═══ */
.page-tabs {
  display: flex; gap: 4px;
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 4px; width: fit-content;
}
.page-tab {
  padding: 7px 20px; border-radius: var(--radius);
  font-size: .84rem; font-weight: 700; color: var(--text-muted);
  cursor: pointer; transition: var(--trans);
  text-decoration: none; display: flex; align-items: center; gap: 6px;
  white-space: nowrap;
}
.page-tab:hover { color: var(--text); background: var(--bg-hover); }
.page-tab.active { background: var(--accent); color: #fff !important; box-shadow: 0 2px 8px rgba(108,92,231,.35); }

/* ═══ LAYOUT ═══ */
.page-container { max-width: 1600px; margin: 0 auto; padding: 28px 20px; }
.page-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  flex-wrap: wrap; gap: 16px; margin-bottom: 28px;
}
.page-title { font-size: 1.4rem; font-weight: 900; letter-spacing: -.4px; margin: 0; }
.page-subtitle { font-size: .82rem; color: var(--text-muted); margin: 2px 0 0; }

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
  background: var(--bg-input) !important; border: 1px solid var(--border-input) !important;
  color: var(--text) !important; border-radius: 8px !important;
  font-size: .845rem !important; font-family: var(--font) !important;
  padding: 8px 12px !important; transition: var(--trans) !important;
}
.form-control:focus, .form-select:focus {
  border-color: var(--accent) !important;
  box-shadow: 0 0 0 3px var(--accent-bg) !important; outline: none !important;
}
[data-theme="dark"] .form-control[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }

/* ═══ BUTTONS ═══ */
.btn {
  font-family: var(--font) !important; font-weight: 700 !important;
  font-size: .82rem !important; border-radius: 8px !important;
  transition: var(--trans) !important;
  display: inline-flex !important; align-items: center !important; gap: 5px !important;
}
.btn-primary { background: var(--accent) !important; border: none !important; color: #fff !important; padding: 8px 18px !important; }
.btn-primary:hover { background: #5a4bd1 !important; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(108,92,231,.35) !important; }
.btn-outline-secondary { background: transparent !important; border: 1px solid var(--border-input) !important; color: var(--text-sub) !important; padding: 7px 14px !important; }
.btn-outline-secondary:hover { background: var(--bg-hover) !important; color: var(--text) !important; }
.btn-success-soft { background: var(--green-bg) !important; border: 1px solid var(--green-border) !important; color: var(--green) !important; }
.btn-success-soft:hover { background: var(--green) !important; color: #fff !important; }
.btn-danger-soft { background: var(--red-bg) !important; border: 1px solid var(--red-border) !important; color: var(--red) !important; }
.btn-danger-soft:hover { background: var(--red) !important; color: #fff !important; }

/* ═══ KPI STRIP ═══ */
.kpi-strip {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr));
  gap: 14px; margin-bottom: 28px;
}
.kpi-mini {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 18px 20px;
  border-left: 4px solid var(--kc, var(--accent));
  box-shadow: var(--shadow-card); transition: var(--trans);
}
.kpi-mini:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.kpi-mini-label { font-size:.69rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); margin-bottom:4px; }
.kpi-mini-val { font-size:1.2rem; font-weight:900; color:var(--text); letter-spacing:-.3px; }
.kpi-mini-val.mono { font-family:var(--mono); font-size:1rem; }
.kpi-mini-sub { font-size:.71rem; color:var(--text-muted); margin-top:3px; }

/* ═══ SECTION NAV ═══ */
.section-nav {
  display: flex; gap: 6px; flex-wrap: wrap;
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 8px 12px;
  margin-bottom: 28px; box-shadow: var(--shadow-card);
  position: sticky; top: 58px; z-index: 100;
}
.sec-nav-btn {
  padding: 5px 13px; border-radius: 7px; font-size: .77rem; font-weight: 700;
  color: var(--text-muted); cursor: pointer; transition: var(--trans);
  border: none; background: transparent;
  display: flex; align-items: center; gap: 5px; white-space: nowrap;
}
.sec-nav-btn:hover { background: var(--bg-hover); color: var(--text); }
.sec-nav-btn.active { background: var(--accent-bg); color: var(--accent); }

/* ═══ ANALYTICS SECTION ═══ */
.analytics-section { margin-bottom: 36px; }
.section-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px; margin-bottom: 16px;
}
.section-title {
  font-size: 1rem; font-weight: 900; letter-spacing: -.3px;
  display: flex; align-items: center; gap: 8px;
}
.section-num {
  width: 26px; height: 26px; border-radius: 7px;
  background: var(--accent-bg); color: var(--accent);
  font-size: .78rem; font-weight: 900; font-family: var(--mono);
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.section-badge {
  font-size: .63rem; font-weight: 700; padding: 3px 9px; border-radius: 4px;
  background: var(--accent-bg); color: var(--accent);
  letter-spacing: .5px; text-transform: uppercase;
}
.section-badge.green  { background: var(--green-bg);  color: var(--green); }
.section-badge.yellow { background: var(--yellow-bg); color: var(--yellow); }
.section-badge.red    { background: var(--red-bg);    color: var(--red); }
.section-badge.blue   { background: var(--blue-bg);   color: var(--blue); }
.section-badge.cyan   { background: var(--cyan-bg);   color: var(--cyan); }
.section-badge.purple { background: rgba(183,148,244,.15); color: #b794f4; }

/* ═══ CHART CARD ═══ */
.chart-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 20px 22px;
  height: 100%; display: flex; flex-direction: column;
  box-shadow: var(--shadow-card);
}
.chart-card-title { font-size: .88rem; font-weight: 800; color: var(--text); margin-bottom: 3px; }
.chart-card-sub   { font-size: .74rem; color: var(--text-muted); margin-bottom: 16px; }
.chart-body       { flex: 1; min-height: 0; position: relative; }

/* ═══ INSIGHT BOX ═══ */
.insight-box {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 14px; border-radius: 10px; margin-top: 14px;
  background: var(--accent-bg); border: 1px solid var(--accent-border);
  font-size: .8rem; color: var(--text-sub);
}
.insight-box.green  { background: var(--green-bg);  border-color: var(--green-border); }
.insight-box.yellow { background: var(--yellow-bg); border-color: var(--yellow-border); }
.insight-box.red    { background: var(--red-bg);    border-color: var(--red-border); }
.insight-box.blue   { background: var(--blue-bg);   border-color: var(--blue-border); }
.insight-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
.insight-text strong { font-weight: 800; color: var(--text); }

/* ═══ PROGRESS BAR ═══ */
.prog-bar { height: 7px; border-radius: 99px; background: var(--border); overflow: hidden; }
.prog-fill { height: 100%; border-radius: 99px; transition: width .5s ease; }

/* ═══ PAIR ITEM (Market Basket) ═══ */
.pair-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-radius: 10px;
  background: var(--bg-stripe); border: 1px solid var(--border);
  margin-bottom: 8px; transition: var(--trans);
}
.pair-item:hover { background: var(--bg-hover); border-color: var(--accent-border); }
.pair-rank {
  width: 26px; height: 26px; border-radius: 7px;
  background: var(--accent-bg); color: var(--accent);
  font-size: .75rem; font-weight: 900; font-family: var(--mono);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.pair-badge {
  font-size: .73rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
  background: var(--accent-bg); color: var(--accent); font-family: var(--mono);
}

/* ═══ TABLE ═══ */
.table-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-card);
}
.table-card-header {
  padding: 14px 20px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px;
}
.table-card-title { font-size: .9rem; font-weight: 900; margin: 0; }
.tbl-scroll { overflow-x: auto; }

table.at { width: 100%; border-collapse: collapse; }
table.at thead th {
  background: var(--bg-stripe) !important;
  border-bottom: 2px solid var(--border) !important; border-top: none !important;
  font-size: .69rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .6px; color: var(--text-muted) !important;
  padding: 10px 14px !important; white-space: nowrap;
}
table.at tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
table.at tbody tr:hover { background: var(--bg-stripe) !important; }
table.at tbody td { padding: 9px 14px !important; font-size: .82rem; vertical-align: middle; color: var(--text) !important; }

/* ═══ HEATMAP ═══ */
.heatmap-grid { display: grid; gap: 3px; align-items: center; }
.heat-cell {
  height: 36px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  font-size: .72rem; font-weight: 700; cursor: default; transition: var(--trans);
}
.heat-cell:hover { transform: scale(1.08); z-index: 1; position: relative; }
.heat-label {
  font-size: .71rem; font-weight: 700; color: var(--text-muted);
  display: flex; align-items: center; padding-right: 8px; height: 36px; white-space: nowrap;
}

/* ═══ MODAL ═══ */
.modal-content {
  background: var(--bg-card) !important; border: 1px solid var(--border) !important;
  border-radius: var(--radius-xl) !important; box-shadow: var(--shadow-lg) !important;
}
.modal-header { border-bottom: 1px solid var(--border) !important; padding: 18px 22px !important; }
.modal-title { font-weight: 900 !important; font-size: .95rem !important; color: var(--text) !important; }
.modal-body { padding: 20px 22px !important; color: var(--text); }
.modal-footer { border-top: 1px solid var(--border) !important; padding: 14px 22px !important; }
[data-theme="dark"] .btn-close { filter: invert(1) brightness(.6); }

/* ═══ INFO TAG ═══ */
.info-tag {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 12px; border-radius: 20px; font-size: .74rem; font-weight: 700;
  background: var(--bg-stripe); border: 1px solid var(--border); color: var(--text-sub);
}

/* ═══ TREND COLORS ═══ */
.trend-up   { color: var(--green); }
.trend-down { color: var(--red); }
.trend-flat { color: var(--text-muted); }

/* ═══ DATATABLE OVERRIDES ═══ */
.dataTables_wrapper { padding: 0 !important; color: var(--text) !important; }
.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select {
  background: var(--bg-input) !important; border: 1px solid var(--border-input) !important;
  color: var(--text) !important; border-radius: 7px !important;
  padding: 5px 10px !important; font-family: var(--font) !important; font-size: .82rem !important;
}
.dataTables_wrapper .dataTables_filter  { padding: 12px 16px 0 !important; }
.dataTables_wrapper .dataTables_length  { padding: 12px 0 0 16px !important; }
.dataTables_wrapper .dataTables_info    { padding: 12px 16px !important; font-size: .75rem !important; color: var(--text-muted) !important; }
.dataTables_wrapper .dataTables_paginate { padding: 10px 16px !important; }
.dataTables_wrapper .paginate_button {
  background: var(--bg-input) !important; border: 1px solid var(--border-input) !important;
  color: var(--text-sub) !important; border-radius: 6px !important;
  font-size: .78rem !important; font-weight: 700 !important; padding: 4px 10px !important; margin: 0 2px !important;
}
.dataTables_wrapper .paginate_button:hover { background: var(--accent-bg) !important; border-color: var(--accent-border) !important; color: var(--accent) !important; }
.dataTables_wrapper .paginate_button.current,
.dataTables_wrapper .paginate_button.current:hover { background: var(--accent) !important; border-color: var(--accent) !important; color: #fff !important; }
.dataTables_wrapper .paginate_button.disabled { opacity: .35 !important; cursor: default !important; }

/* ═══ PRINT ═══ */
@media print {
  .no-print { display: none !important; }
  .page-container { padding: 0; }
  [data-theme] { --bg:#fff; --bg-card:#fff; --bg-stripe:#f9f9f9; --text:#000; --text-sub:#333; --text-muted:#666; --border:#ddd; }
}

/* ═══ RESPONSIVE ═══ */
@media (max-width: 768px) {
  .page-container { padding: 16px 12px; }
  .kpi-strip { grid-template-columns: 1fr 1fr; }
  .page-head { flex-direction: column; }
  .section-nav { top: 56px; }
}
@media (max-width: 480px) { .kpi-strip { grid-template-columns: 1fr; } }

/* Scrollable table in modal */
.modal .tbl-scroll { max-height: 65vh; overflow-y: auto; }
</style>
</head>
<body>

<!-- ═══ NAVBAR ═══ -->
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
        <li class="nav-item"><a class="nav-link" href="laporan_pembayaran.php"><i class="bi bi-book-half"></i>Pembukuan</a></li>
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

<!-- ═══ PAGE BODY ═══ -->
<div class="page-container">

  <!-- Page Head -->
  <div class="page-head">
    <div>
      <h1 class="page-title">
        <i class="bi bi-graph-up me-2" style="color:var(--accent)"></i>Analitik Bisnis
      </h1>
      <p class="page-subtitle">Data-driven insights · <?= date('d M Y', strtotime($tgl_awal)) ?> – <?= date('d M Y', strtotime($tgl_akhir)) ?></p>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="page-tabs no-print">
        <a href="laporan_pembayaran.php<?= isset($_GET['tgl_awal']) ? '?tgl_awal='.$tgl_awal.'&tgl_akhir='.$tgl_akhir : '' ?>" class="page-tab">
          <i class="bi bi-journal-text"></i> Pembukuan
        </a>
        <a href="analitik.php<?= isset($_GET['tgl_awal']) ? '?tgl_awal='.$tgl_awal.'&tgl_akhir='.$tgl_akhir : '' ?>" class="page-tab active">
          <i class="bi bi-graph-up"></i> Analitik Bisnis
        </a>
      </div>
      <div class="d-flex gap-2 no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer"></i> Cetak</button>
        <button onclick="exportExcel()" class="btn btn-success-soft btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        <button onclick="exportPDF()" class="btn btn-danger-soft btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
      </div>
    </div>
  </div>

  <!-- Filter -->
  <div class="filter-card no-print">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-lg-4 col-sm-6">
        <label class="filter-label"><i class="bi bi-calendar3 me-1"></i>Dari Tanggal</label>
        <input type="date" name="tgl_awal" class="form-control" value="<?= htmlspecialchars($tgl_awal) ?>">
      </div>
      <div class="col-lg-4 col-sm-6">
        <label class="filter-label"><i class="bi bi-calendar3 me-1"></i>Sampai Tanggal</label>
        <input type="date" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tgl_akhir) ?>">
      </div>
      <div class="col-lg-4">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel-fill"></i> Analisis Sekarang</button>
          <a href="analitik.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
      </div>
      <div class="col-12">
        <div style="font-size:.75rem;color:var(--text-muted)">
          <i class="bi bi-info-circle me-1"></i>
          Periode: <strong style="color:var(--text-sub)"><?= date('d M Y', strtotime($tgl_awal)) ?></strong>
          s/d <strong style="color:var(--text-sub)"><?= date('d M Y', strtotime($tgl_akhir)) ?></strong>
          (<?= $days_diff ?> hari) — <?= count($rfm_scored) ?> customer dianalisis
        </div>
      </div>
    </form>
  </div>

  <!-- KPI Strip -->
  <div class="kpi-strip">
    <div class="kpi-mini" style="--kc:var(--accent)">
      <div class="kpi-mini-label">Total Omzet</div>
      <div class="kpi-mini-val mono">Rp <?= number_format((float)$r_kpi['omzet'],0,',','.') ?></div>
      <div class="kpi-mini-sub">periode terpilih</div>
    </div>
    <div class="kpi-mini" style="--kc:var(--yellow)">
      <div class="kpi-mini-label">Total Booking</div>
      <div class="kpi-mini-val"><?= number_format((int)$r_kpi['bookings']) ?></div>
      <div class="kpi-mini-sub">transaksi aktif</div>
    </div>
    <div class="kpi-mini" style="--kc:#b794f4">
      <div class="kpi-mini-label">Customer Unik</div>
      <div class="kpi-mini-val"><?= number_format((int)$r_kpi['customers']) ?></div>
      <div class="kpi-mini-sub">individu unik</div>
    </div>
    <div class="kpi-mini" style="--kc:var(--blue)">
      <div class="kpi-mini-label">Avg. Transaksi</div>
      <div class="kpi-mini-val mono">Rp <?= number_format((float)$r_kpi['avg_order'],0,',','.') ?></div>
      <div class="kpi-mini-sub">per booking</div>
    </div>
    <div class="kpi-mini" style="--kc:var(--green)">
      <div class="kpi-mini-label">Tren Revenue</div>
      <div class="kpi-mini-val <?= $slope > 0 ? 'trend-up' : ($slope < 0 ? 'trend-down' : 'trend-flat') ?>">
        <?= $slope > 0 ? '↑' : ($slope < 0 ? '↓' : '→') ?> <?= $trend_direction ?>
      </div>
      <div class="kpi-mini-sub"><?= $trend_pct ?>% per hari</div>
    </div>
    <div class="kpi-mini" style="--kc:var(--cyan)">
      <div class="kpi-mini-label">Customer Loyal</div>
      <div class="kpi-mini-val"><?= $loyal_custs ?></div>
      <div class="kpi-mini-sub"><?= $total_custs > 0 ? round($loyal_custs/$total_custs*100,1) : 0 ?>% dari total</div>
    </div>
  </div>

  <!-- Section Quick Nav -->
  <nav class="section-nav no-print" aria-label="Section navigation">
    <span style="font-size:.69rem;font-weight:800;color:var(--text-muted);padding:5px 4px;white-space:nowrap;flex-shrink:0">LOMPAT:</span>
    <?php
    $sections = [
      ['id'=>'s1','icon'=>'graph-up-arrow','label'=>'Trend'],
      ['id'=>'s2','icon'=>'scissors','label'=>'Layanan'],
      ['id'=>'s3','icon'=>'pie-chart','label'=>'Kontribusi'],
      ['id'=>'s4','icon'=>'people','label'=>'Customer'],
      ['id'=>'s5','icon'=>'bar-chart-steps','label'=>'Pareto'],
      ['id'=>'s6','icon'=>'calendar-week','label'=>'Musiman'],
      ['id'=>'s7','icon'=>'lightning','label'=>'Forecast Harian'],
      ['id'=>'s8','icon'=>'currency-dollar','label'=>'Forecast Bulanan'],
      ['id'=>'s9','icon'=>'award','label'=>'RFM'],
      ['id'=>'s10','icon'=>'basket','label'=>'Market Basket'],
    ];
    foreach ($sections as $s):
    ?>
    <button class="sec-nav-btn" data-target="<?= $s['id'] ?>">
      <i class="bi bi-<?= $s['icon'] ?>"></i><?= $s['label'] ?>
    </button>
    <?php endforeach; ?>
  </nav>

  <!-- ══ S1: REVENUE TREND ══════════════════════════════════════════════ -->
  <div class="analytics-section" id="s1">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">01</span>
        Revenue Trend Harian
        <span class="section-badge"><?= $days_diff ?> hari</span>
      </div>
      <button class="btn btn-outline-secondary btn-sm no-print" data-bs-toggle="modal" data-bs-target="#modalTrend">
        <i class="bi bi-table"></i> Tabel Detail
      </button>
    </div>
    <div class="chart-card">
      <div class="chart-card-title">Pendapatan Harian + Moving Average 7 Hari</div>
      <div class="chart-card-sub">Bar = revenue harian · Garis ungu = MA-7 · Garis hijau = rata-rata keseluruhan</div>
      <div class="chart-body" style="height:280px"><canvas id="chartTrend1"></canvas></div>
      <div class="insight-box <?= $slope >= 0 ? 'green' : 'red' ?>">
        <span class="insight-icon"><?= $slope >= 0 ? '📈' : '📉' ?></span>
        <div class="insight-text">
          <strong>Insight:</strong>
          Tren revenue <?= $slope >= 0 ? 'menunjukkan pertumbuhan' : 'mengalami penurunan' ?>
          dengan laju <strong><?= $trend_pct ?>%/hari</strong>.
          <?php if (!empty($trend_rev) && max($trend_rev) > 0):
            $max_rev_idx = array_search(max($trend_rev), $trend_rev);
          ?>
          Hari terbaik: <strong><?= $trend_labels[$max_rev_idx] ?? '-' ?></strong>
          (Rp <?= number_format(max($trend_rev), 0, ',', '.') ?>).
          <?php endif; ?>
          <?= $slope >= 0 ? 'Pertahankan momentum positif ini.' : 'Perlu strategi promosi untuk membalik tren.' ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ S2: SERVICE POPULARITY ═════════════════════════════════════════ -->
  <div class="analytics-section" id="s2">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">02</span>
        Service Popularity
        <span class="section-badge green">Top <?= count($svc_rows) ?></span>
      </div>
      <button class="btn btn-outline-secondary btn-sm no-print" data-bs-toggle="modal" data-bs-target="#modalSvc">
        <i class="bi bi-table"></i> Tabel Lengkap
      </button>
    </div>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="chart-card">
          <div class="chart-card-title">Frekuensi Pemesanan</div>
          <div class="chart-card-sub">Jumlah kali tiap layanan dipesan dalam periode</div>
          <div class="chart-body" style="height:300px"><canvas id="chartSvcFreq"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="chart-card">
          <div class="chart-card-title">Revenue per Layanan</div>
          <div class="chart-card-sub">Total pendapatan yang dihasilkan tiap layanan</div>
          <div class="chart-body" style="height:300px"><canvas id="chartSvcRev"></canvas></div>
        </div>
      </div>
    </div>
    <?php if (!empty($svc_rows)): ?>
    <div class="insight-box">
      <span class="insight-icon">💡</span>
      <div class="insight-text">
        Layanan terpopuler: <strong><?= htmlspecialchars($svc_rows[0]['nama_layanan']) ?></strong>
        dipesan <strong><?= $svc_rows[0]['freq'] ?>×</strong> dengan total revenue
        <strong>Rp <?= number_format($svc_rows[0]['revenue'],0,',','.') ?></strong>.
        <?php if (count($svc_rows) > 1): ?>
        Runner-up: <strong><?= htmlspecialchars($svc_rows[1]['nama_layanan']) ?></strong> (<?= $svc_rows[1]['freq'] ?>×).
        <?php endif; ?>
        Fokuskan promosi pada layanan frekuensi rendah yang memiliki potensi revenue tinggi.
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ S3: REVENUE CONTRIBUTION ══════════════════════════════════════ -->
  <div class="analytics-section" id="s3">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">03</span>
        Revenue Contribution
        <span class="section-badge yellow">Donut Chart</span>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="chart-card">
          <div class="chart-card-title">Distribusi Kontribusi</div>
          <div class="chart-card-sub">Proporsi tiap layanan terhadap total omzet</div>
          <div class="chart-body" style="height:300px"><canvas id="chartContrib"></canvas></div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="chart-card">
          <div class="chart-card-title">Breakdown per Layanan</div>
          <div class="chart-card-sub">Detail revenue dan persentase kontribusi</div>
          <div style="max-height:320px;overflow-y:auto;padding-right:4px">
            <?php
            $donut_colors = ['#6c5ce7','#00b894','#4fd1c5','#f39c12','#3498db','#e84393','#e17055','#b794f4','#8c90b0'];
            foreach ($top8 as $ci => $ct):
              $pct_val = $total_svc_rev > 0 ? round((float)$ct['revenue']/$total_svc_rev*100,1) : 0;
              $clr     = $donut_colors[$ci % count($donut_colors)];
            ?>
            <div style="margin-bottom:12px">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                <div style="display:flex;align-items:center;gap:8px">
                  <span style="width:10px;height:10px;border-radius:3px;background:<?= $clr ?>;flex-shrink:0;display:inline-block"></span>
                  <span style="font-size:.83rem;font-weight:700;color:var(--text)"><?= htmlspecialchars($ct['nama_layanan']) ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                  <span style="font-size:.78rem;font-family:var(--mono);font-weight:700;color:<?= $clr ?>"><?= $pct_val ?>%</span>
                  <span style="font-size:.74rem;font-family:var(--mono);color:var(--text-muted)">Rp <?= number_format((float)$ct['revenue'],0,',','.') ?></span>
                </div>
              </div>
              <div class="prog-bar">
                <div class="prog-fill" style="width:<?= $pct_val ?>%;background:<?= $clr ?>"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ S4: CUSTOMER FREQUENCY ════════════════════════════════════════ -->
  <div class="analytics-section" id="s4">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">04</span>
        Customer Frequency
        <span class="section-badge cyan">Retensi</span>
      </div>
      <button class="btn btn-outline-secondary btn-sm no-print" data-bs-toggle="modal" data-bs-target="#modalCust">
        <i class="bi bi-people"></i> Daftar Lengkap
      </button>
    </div>
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="chart-card">
          <div class="chart-card-title">Distribusi Frekuensi Kunjungan</div>
          <div class="chart-card-sub">Berapa kali customer datang dalam periode ini</div>
          <div class="chart-body" style="height:220px"><canvas id="chartFreqDist"></canvas></div>
          <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div style="background:var(--green-bg);border:1px solid var(--green-border);border-radius:10px;padding:12px;text-align:center">
              <div style="font-size:.7rem;font-weight:700;color:var(--green)">Customer Loyal</div>
              <div style="font-size:1.3rem;font-weight:900;color:var(--green)"><?= $loyal_custs ?></div>
              <div style="font-size:.68rem;color:var(--text-muted)">≥3 kunjungan</div>
            </div>
            <div style="background:var(--yellow-bg);border:1px solid var(--yellow-border);border-radius:10px;padding:12px;text-align:center">
              <div style="font-size:.7rem;font-weight:700;color:var(--yellow)">Customer Baru</div>
              <div style="font-size:1.3rem;font-weight:900;color:var(--yellow)"><?= $new_custs ?></div>
              <div style="font-size:.68rem;color:var(--text-muted)">1× kunjungan</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="table-card">
          <div class="table-card-header">
            <div class="table-card-title"><i class="bi bi-trophy me-2" style="color:var(--yellow)"></i>Top 30 Customer</div>
          </div>
          <div class="tbl-scroll" style="max-height:380px;overflow-y:auto">
            <table class="at w-100">
              <thead>
                <tr>
                  <th>#</th><th>Nama</th><th class="text-center">Kunjungan</th>
                  <th class="text-end">Total Spend</th><th class="text-end">Avg/Visit</th><th>Terakhir</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($cust_rows,0,30) as $ci => $cust):
                  $avg_v    = (int)$cust['visits'] > 0 ? (float)$cust['total_spend'] / (int)$cust['visits'] : 0;
                  $days_ago = (int)floor((time() - strtotime($cust['last_visit'])) / 86400);
                  $rec_lbl  = $days_ago <= 7 ? '🟢' : ($days_ago <= 30 ? '🟡' : '🔴');
                ?>
                <tr>
                  <td style="font-family:var(--mono);font-size:.72rem;color:var(--text-muted)"><?= $ci+1 ?></td>
                  <td style="font-weight:800"><?= htmlspecialchars($cust['nama_customer']) ?></td>
                  <td style="text-align:center">
                    <span style="background:var(--accent-bg);color:var(--accent);padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;font-family:var(--mono)"><?= $cust['visits'] ?>×</span>
                  </td>
                  <td style="text-align:right;font-family:var(--mono);font-size:.8rem;font-weight:700;color:var(--accent)">Rp <?= number_format((float)$cust['total_spend'],0,',','.') ?></td>
                  <td style="text-align:right;font-family:var(--mono);font-size:.77rem;color:var(--text-sub)">Rp <?= number_format($avg_v,0,',','.') ?></td>
                  <td style="font-size:.77rem"><?= $rec_lbl ?> <?= date('d/m/Y', strtotime($cust['last_visit'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="insight-box">
      <span class="insight-icon">🎯</span>
      <div class="insight-text">
        <strong>Retensi Customer:</strong>
        <?= $total_custs > 0 ? round($loyal_custs/$total_custs*100,1) : 0 ?>% customer melakukan ≥3 kunjungan.
        <?= $new_custs ?> customer baru berpotensi dikonversi menjadi pelanggan tetap dengan welcome program yang tepat.
      </div>
    </div>
  </div>

  <!-- ══ S5: PARETO ════════════════════════════════════════════════════ -->
  <div class="analytics-section" id="s5">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">05</span>
        Pareto Analysis (80/20)
        <span class="section-badge red">Prioritas</span>
      </div>
      <?php if ($pareto_80_idx > 0): ?>
      <span class="info-tag" style="background:var(--red-bg);border-color:var(--red-border);color:var(--red)">
        <i class="bi bi-bar-chart-steps"></i> <?= $pareto_80_idx ?> layanan → 80% revenue
      </span>
      <?php endif; ?>
    </div>
    <div class="chart-card">
      <div class="chart-card-title">Pareto Chart — Revenue Kumulatif per Layanan</div>
      <div class="chart-card-sub">Bar merah = revenue per layanan · Garis kuning = % kumulatif · Garis putus = batas 80%</div>
      <div class="chart-body" style="height:300px"><canvas id="chartPareto"></canvas></div>
      <div class="insight-box red">
        <span class="insight-icon">⚡</span>
        <div class="insight-text">
          <strong>Prinsip 80/20:</strong>
          <?php if ($pareto_80_idx > 0): ?>
          <strong><?= $pareto_80_idx ?> layanan teratas</strong> dari <?= count($pareto_services) ?> layanan menghasilkan 80% revenue.
          Fokuskan resource dan promosi pada layanan ini untuk efisiensi maksimal.
          <?php else: ?>
          Belum cukup data untuk analisis Pareto yang akurat. Tambahkan lebih banyak transaksi layanan.
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ S6: SEASONALITY ══════════════════════════════════════════════ -->
  <div class="analytics-section" id="s6">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">06</span>
        Seasonality Analysis
        <span class="section-badge blue">Pola Waktu</span>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="chart-card">
          <div class="chart-card-title">Heatmap Jam Operasional (08:00–20:00)</div>
          <div class="chart-card-sub">Intensitas warna = jumlah booking — hover untuk detail</div>
          <div id="heatmapHour" style="padding:8px 0"></div>
          <?php
          $peak_hour_idx = !empty($hour_cnt) ? array_search(max($hour_cnt), $hour_cnt) : 0;
          $peak_hour_cnt = !empty($hour_cnt) ? max($hour_cnt) : 0;
          $peak_hour_lbl = $hour_labels[$peak_hour_idx] ?? '—';
          ?>
          <div class="insight-box">
            <span class="insight-icon">🕐</span>
            <div class="insight-text">
              <strong>Jam Puncak: <?= $peak_hour_lbl ?></strong> dengan <?= $peak_hour_cnt ?> booking.
              Siapkan karyawan dan stok produk ekstra pada jam ini.
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="chart-card">
          <div class="chart-card-title">Pola Hari dalam Seminggu</div>
          <div class="chart-card-sub">Identifikasi hari tersibuk dan hari sepi</div>
          <div class="chart-body" style="height:220px"><canvas id="chartDow"></canvas></div>
          <?php
          $peak_dow_idx = !empty($dow_cnt) ? array_search(max($dow_cnt), $dow_cnt) : 0;
          $peak_dow_cnt = !empty($dow_cnt) ? max($dow_cnt) : 0;
          $peak_dow_lbl = $dow_labels[$peak_dow_idx] ?? '—';
          ?>
          <div class="insight-box green">
            <span class="insight-icon">📅</span>
            <div class="insight-text">
              <strong>Tersibuk: <?= $peak_dow_lbl ?></strong> (<?= $peak_dow_cnt ?> booking).
              Manfaatkan hari sepi untuk flash sale atau diskon early bird.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ S7: DEMAND FORECASTING ════════════════════════════════════════ -->
  <div class="analytics-section" id="s7">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">07</span>
        Demand Forecasting
        <span class="section-badge">7 Hari ke Depan</span>
      </div>
      <div class="d-flex gap-2 align-items-center no-print">
        <span class="info-tag">Linear Regression</span>
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalForecastInfo">
          <i class="bi bi-info-circle"></i> Metodologi
        </button>
      </div>
    </div>
    <div class="chart-card">
      <div class="chart-card-title">Prediksi Permintaan Harian</div>
      <div class="chart-card-sub">
        Bar biru = aktual · Garis oranye = fitted + proyeksi
        <span style="background:var(--yellow-bg);color:var(--yellow);padding:2px 8px;border-radius:4px;font-size:.7rem;font-weight:700;margin-left:6px">F+1 s/d F+7 = PROYEKSI</span>
      </div>
      <div class="chart-body" style="height:280px"><canvas id="chartForecast"></canvas></div>
      <?php
      $proj_vals_7  = array_slice($forecast_pred, $n, 7);
      $proj_avg     = count($proj_vals_7) > 0 ? array_sum($proj_vals_7)/count($proj_vals_7) : 0;
      $last_actual  = !empty($trend_rev) ? end($trend_rev) : 0;
      $vs_pct       = $last_actual > 0 ? round(($proj_avg - $last_actual) / $last_actual * 100, 1) : 0;
      ?>
      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <div style="background:var(--bg-stripe);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
            <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);margin-bottom:4px">SLOPE (Rp/hari)</div>
            <div style="font-size:1rem;font-weight:900;font-family:var(--mono);color:<?= $slope>=0?'var(--green)':'var(--red)' ?>">
              <?= ($slope >= 0 ? '+' : '') . number_format(round($slope),0,',','.') ?>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div style="background:var(--bg-stripe);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
            <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);margin-bottom:4px">PROYEKSI AVG 7H</div>
            <div style="font-size:1rem;font-weight:900;font-family:var(--mono);color:var(--accent)">Rp <?= number_format(round($proj_avg),0,',','.') ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div style="background:var(--bg-stripe);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
            <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);margin-bottom:4px">VS AKTUAL TERAKHIR</div>
            <div style="font-size:1rem;font-weight:900;font-family:var(--mono);color:<?= $vs_pct>=0?'var(--green)':'var(--red)' ?>">
              <?= $last_actual > 0 ? ($vs_pct >= 0 ? '+' : '') . $vs_pct . '%' : 'N/A' ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ S8: MONTHLY FORECAST ══════════════════════════════════════════ -->
  <div class="analytics-section" id="s8">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">08</span>
        Sales Forecasting Bulanan
        <span class="section-badge green">+3 Bulan</span>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="chart-card">
          <div class="chart-card-title">Proyeksi Revenue Bulanan</div>
          <div class="chart-card-sub">Bar biru = historis aktual · Garis oranye = linear fit + proyeksi 3 bulan</div>
          <div class="chart-body" style="height:280px"><canvas id="chartSalesForecast"></canvas></div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="chart-card h-100">
          <div class="chart-card-title">Ringkasan Proyeksi</div>
          <div class="chart-card-sub">Estimasi 3 bulan ke depan berdasarkan tren historis</div>
          <?php
          $proj3    = array_slice($proj_revenue, $nm);
          $labels3  = array_slice($monthly_labels, $nm);
          $hist_avg = $nm > 0 ? array_sum(array_slice($monthly_revenue, 0, $nm)) / $nm : 0;
          if (!empty($proj3)):
            foreach ($proj3 as $pi => $pv):
              $pv = (float)($pv ?? 0);
              $growth_p = $hist_avg > 0 ? round(($pv - $hist_avg) / $hist_avg * 100, 1) : 0;
          ?>
          <div style="background:var(--bg-stripe);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:10px">
            <div style="font-size:.75rem;font-weight:700;color:var(--text-muted)"><?= htmlspecialchars($labels3[$pi] ?? 'Proyeksi '.($pi+1)) ?></div>
            <div style="font-size:1.05rem;font-weight:900;font-family:var(--mono);color:var(--accent)">Rp <?= number_format($pv,0,',','.') ?></div>
            <div style="font-size:.75rem;color:<?= $growth_p>=0?'var(--green)':'var(--red)' ?>;margin-top:2px">
              <?= $growth_p>=0?'↑':'↓' ?> <?= abs($growth_p) ?>% vs rata-rata historis
            </div>
          </div>
          <?php endforeach; else: ?>
          <div style="text-align:center;color:var(--text-muted);padding:30px;font-size:.82rem">
            Data historis bulanan belum cukup untuk proyeksi. Minimal 2 bulan data diperlukan.
          </div>
          <?php endif; ?>
          <div class="insight-box yellow" style="margin-top:8px">
            <span class="insight-icon">⚠️</span>
            <div class="insight-text" style="font-size:.74rem">
              Proyeksi bersifat estimasi linear. Faktor eksternal (hari besar, musim) dapat mempengaruhi hasil aktual.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ S9: RFM ══════════════════════════════════════════════════════ -->
  <div class="analytics-section" id="s9">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">09</span>
        RFM Analysis
        <span class="section-badge purple">Segmentasi</span>
      </div>
      <div class="d-flex gap-2 no-print">
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRFM">
          <i class="bi bi-table"></i> Detail RFM
        </button>
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRFMHelp">
          <i class="bi bi-question-circle"></i> Panduan
        </button>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="chart-card">
          <div class="chart-card-title">Distribusi Segmen RFM</div>
          <div class="chart-card-sub">Recency · Frequency · Monetary — skor 1–5 tiap dimensi</div>
          <div class="chart-body" style="height:280px"><canvas id="chartRFM"></canvas></div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="chart-card h-100">
          <div class="chart-card-title">Segmen &amp; Rekomendasi Aksi</div>
          <div class="chart-card-sub">Strategi marketing berdasarkan posisi customer dalam matriks RFM</div>
          <?php
          $rfm_actions = [
            'Champions'           => ['icon'=>'🏆','color'=>'#6c5ce7','action'=>'Reward mereka. Program VIP, early access, ucapan terima kasih personal.'],
            'Loyal Customers'     => ['icon'=>'💚','color'=>'#00b894','action'=>'Tingkatkan nilai. Upsell layanan premium, tawarkan membership.'],
            'Potential Loyalists' => ['icon'=>'💙','color'=>'#0984e3','action'=>'Nurture. Penawaran personal, program referral, edukasi produk.'],
            'New Customers'       => ['icon'=>'⭐','color'=>'#fdcb6e','action'=>'Welcome program. Onboarding khusus, diskon kunjungan kedua.'],
            'At Risk'             => ['icon'=>'⚠️','color'=>'#e17055','action'=>'Win-back campaign. Penawaran spesial, reminder, survei kepuasan.'],
            'Lost'                => ['icon'=>'😴','color'=>'#b2bec3','action'=>'Re-engagement. Promo agresif atau evaluasi ulang strategi.'],
          ];
          $total_rfm_customers = array_sum(array_column($rfm_segments, 'count'));
          if (!empty($rfm_segments)):
            foreach ($rfm_segments as $seg => $data):
              $info    = $rfm_actions[$seg] ?? ['icon'=>'•','color'=>'var(--text-muted)','action'=>''];
              $seg_pct = $total_rfm_customers > 0 ? round($data['count']/$total_rfm_customers*100,1) : 0;
          ?>
          <div style="border:1px solid var(--border);border-radius:10px;padding:11px 14px;margin-bottom:8px;border-left:3px solid <?= $info['color'] ?>;background:var(--bg-stripe)">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
              <div style="display:flex;align-items:center;gap:7px">
                <span><?= $info['icon'] ?></span>
                <span style="font-size:.84rem;font-weight:800"><?= htmlspecialchars($seg) ?></span>
                <span style="font-size:.71rem;font-weight:700;padding:2px 8px;border-radius:20px;background:<?= $info['color'] ?>22;color:<?= $info['color'] ?>">
                  <?= $data['count'] ?> orang · <?= $seg_pct ?>%
                </span>
              </div>
              <span style="font-family:var(--mono);font-size:.77rem;font-weight:700;color:var(--accent)">
                Rp <?= number_format((float)$data['revenue'],0,',','.') ?>
              </span>
            </div>
            <div style="font-size:.76rem;color:var(--text-muted)"><?= htmlspecialchars($info['action']) ?></div>
          </div>
          <?php endforeach; else: ?>
          <div style="text-align:center;color:var(--text-muted);padding:40px;font-size:.82rem">Belum ada data RFM.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ S10: MARKET BASKET ════════════════════════════════════════════ -->
  <div class="analytics-section" id="s10">
    <div class="section-header">
      <div class="section-title">
        <span class="section-num">10</span>
        Market Basket Analysis
        <span class="section-badge green">Cross-sell</span>
      </div>
      <span class="info-tag no-print"><i class="bi bi-basket"></i> <?= $total_baskets ?> booking multi-layanan</span>
    </div>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="chart-card">
          <div class="chart-card-title">Top Kombinasi Layanan</div>
          <div class="chart-card-sub">Pasangan yang paling sering dipesan bersama dalam 1 transaksi</div>
          <?php if (!empty($basket_pairs)): ?>
          <?php $bi = 0; foreach ($basket_pairs as $pair => $cnt): $bi++;
            $supp = $total_baskets > 0 ? round($cnt/$total_baskets*100,1) : 0;
            $max_cnt_b = max($basket_vals ?: [1]);
          ?>
          <div class="pair-item">
            <div class="pair-rank"><?= $bi ?></div>
            <div style="flex:1;min-width:0">
              <div style="font-size:.82rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= htmlspecialchars($pair) ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
                <div class="prog-bar" style="flex:1">
                  <div class="prog-fill" style="width:<?= $max_cnt_b>0?round($cnt/$max_cnt_b*100):0 ?>%;background:linear-gradient(90deg,var(--accent),var(--cyan))"></div>
                </div>
                <span style="font-size:.71rem;color:var(--text-muted);white-space:nowrap">Support: <?= $supp ?>%</span>
              </div>
            </div>
            <div class="pair-badge"><?= $cnt ?>×</div>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div style="text-align:center;color:var(--text-muted);padding:50px 20px;font-size:.83rem">
            <i class="bi bi-basket" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px"></i>
            Belum cukup data booking multi-layanan.
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="chart-card">
          <div class="chart-card-title">Visualisasi Co-occurrence</div>
          <div class="chart-card-sub">Frekuensi pasangan layanan yang dipesan bersamaan</div>
          <?php if (!empty($basket_labels)): ?>
          <div class="chart-body" style="height:<?= max(200, count($basket_labels)*40) ?>px">
            <canvas id="chartBasket"></canvas>
          </div>
          <?php else: ?>
          <div style="text-align:center;color:var(--text-muted);padding:50px;font-size:.82rem">Tidak ada data kombinasi.</div>
          <?php endif; ?>
          <div class="insight-box green">
            <span class="insight-icon">🛒</span>
            <div class="insight-text">
              <?php if (!empty($basket_pairs)): ?>
              Kombinasi terpopuler: <strong><?= htmlspecialchars(array_key_first($basket_pairs)) ?></strong>
              dipesan bersama <strong><?= reset($basket_pairs) ?>×</strong>.
              Buat paket bundling untuk meningkatkan nilai transaksi rata-rata.
              <?php else: ?>
              Dorong customer untuk mencoba kombinasi layanan dengan paket bundling spesial.
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div style="text-align:center;color:var(--text-muted);font-size:.72rem;padding:1rem 0 2.5rem" class="no-print">
    <i class="bi bi-cpu me-1"></i>
    Digenerate <?= date('d M Y H:i') ?> — Amoy Salon Analytics · Linear Regression + RFM + MBA
  </div>
</div>

<!-- ═══ MODALS ═══ -->

<!-- Modal: Trend Detail Table -->
<div class="modal fade" id="modalTrend" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-graph-up-arrow me-2" style="color:var(--accent)"></i>Detail Revenue Trend Harian</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="tbl-scroll">
          <table class="at w-100" id="dtTrend">
            <thead><tr><th>Tanggal</th><th class="text-end">Revenue</th><th class="text-end">Booking</th><th class="text-end">Avg/Booking</th><th class="text-end">MA-7</th><th>Δ Kemarin</th></tr></thead>
            <tbody>
              <?php foreach ($trend_rows as $ti => $tr):
                $prev_rev_t = $ti > 0 ? (float)$trend_rows[$ti-1]['revenue'] : (float)$tr['revenue'];
                $diff_t = (float)$tr['revenue'] - $prev_rev_t;
              ?>
              <tr>
                <td style="font-weight:700"><?= htmlspecialchars($tr['lbl']) ?></td>
                <td style="text-align:right;font-family:var(--mono);font-weight:700;color:var(--accent)">Rp <?= number_format((float)$tr['revenue'],0,',','.') ?></td>
                <td style="text-align:right"><?= (int)$tr['bookings'] ?></td>
                <td style="text-align:right;font-family:var(--mono);color:var(--text-sub)">Rp <?= number_format((float)$tr['avg_rev'],0,',','.') ?></td>
                <td style="text-align:right;font-family:var(--mono);color:#b794f4">Rp <?= number_format($trend_ma7[$ti],0,',','.') ?></td>
                <td>
                  <?php if ($ti === 0): ?>
                  <span class="trend-flat">—</span>
                  <?php elseif ($diff_t > 0): ?>
                  <span class="trend-up">↑ +Rp <?= number_format($diff_t,0,',','.') ?></span>
                  <?php elseif ($diff_t < 0): ?>
                  <span class="trend-down">↓ Rp <?= number_format(abs($diff_t),0,',','.') ?></span>
                  <?php else: ?>
                  <span class="trend-flat">→ stabil</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Service Full Table -->
<div class="modal fade" id="modalSvc" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-scissors me-2" style="color:var(--green)"></i>Popularitas Layanan — Tabel Lengkap</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="tbl-scroll">
          <table class="at w-100" id="dtSvc">
            <thead><tr><th>#</th><th>Nama Layanan</th><th class="text-center">Frekuensi</th><th class="text-end">Revenue</th><th class="text-end">Avg Harga</th><th>Kontribusi</th></tr></thead>
            <tbody>
              <?php foreach ($svc_rows as $si => $svc):
                $pct_s = $total_svc_rev > 0 ? round((float)$svc['revenue']/$total_svc_rev*100,1) : 0;
              ?>
              <tr>
                <td style="font-family:var(--mono);font-size:.72rem;color:var(--text-muted)"><?= $si+1 ?></td>
                <td style="font-weight:800"><?= htmlspecialchars($svc['nama_layanan']) ?></td>
                <td style="text-align:center"><span style="background:var(--accent-bg);color:var(--accent);padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;font-family:var(--mono)"><?= (int)$svc['freq'] ?>×</span></td>
                <td style="text-align:right;font-family:var(--mono);font-weight:700;color:var(--accent)">Rp <?= number_format((float)$svc['revenue'],0,',','.') ?></td>
                <td style="text-align:right;font-family:var(--mono);color:var(--text-sub)">Rp <?= number_format((float)$svc['avg_price'],0,',','.') ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px">
                    <div class="prog-bar" style="flex:1">
                      <div class="prog-fill" style="width:<?= $pct_s ?>%;background:var(--green)"></div>
                    </div>
                    <span style="font-size:.72rem;font-weight:700;color:var(--green);width:38px;text-align:right"><?= $pct_s ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Customer Full Table -->
<div class="modal fade" id="modalCust" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-people me-2" style="color:var(--cyan)"></i>Daftar Semua Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="tbl-scroll">
          <table class="at w-100" id="dtCust">
            <thead><tr><th>#</th><th>Nama</th><th class="text-center">Kunjungan</th><th class="text-end">Total Spend</th><th class="text-end">Avg/Visit</th><th>Pertama</th><th>Terakhir</th><th class="text-center">Aktifitas</th></tr></thead>
            <tbody>
              <?php foreach ($cust_rows as $ci => $cust):
                $avg_v2   = (int)$cust['visits'] > 0 ? (float)$cust['total_spend'] / (int)$cust['visits'] : 0;
                $days2    = (int)floor((time() - strtotime($cust['last_visit'])) / 86400);
                $st2      = $days2 <= 7 ? '🟢 Aktif' : ($days2 <= 30 ? '🟡 Normal' : '🔴 Pasif');
              ?>
              <tr>
                <td style="font-family:var(--mono);font-size:.72rem;color:var(--text-muted)"><?= $ci+1 ?></td>
                <td style="font-weight:800"><?= htmlspecialchars($cust['nama_customer']) ?></td>
                <td style="text-align:center;font-family:var(--mono);font-weight:700"><?= (int)$cust['visits'] ?></td>
                <td style="text-align:right;font-family:var(--mono);font-weight:700;color:var(--accent)">Rp <?= number_format((float)$cust['total_spend'],0,',','.') ?></td>
                <td style="text-align:right;font-family:var(--mono);color:var(--text-sub)">Rp <?= number_format($avg_v2,0,',','.') ?></td>
                <td style="font-size:.77rem"><?= date('d/m/Y', strtotime($cust['first_visit'])) ?></td>
                <td style="font-size:.77rem"><?= date('d/m/Y', strtotime($cust['last_visit'])) ?></td>
                <td style="text-align:center;font-size:.77rem"><?= $st2 ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Forecast Methodology -->
<div class="modal fade" id="modalForecastInfo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-info-circle me-2" style="color:var(--accent)"></i>Metodologi Forecasting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="font-size:.84rem;color:var(--text-sub)">
        <p><strong style="color:var(--text)">Linear Regression (Least Squares)</strong> digunakan untuk mengestimasi tren jangka pendek berdasarkan data historis.</p>
        <div style="background:var(--bg-stripe);border:1px solid var(--border);border-radius:8px;padding:14px;font-family:var(--mono);font-size:.8rem;margin:12px 0;line-height:1.9">
          ŷ = β₀ + β₁x<br>
          β₁ (slope) = Σ[(x−x̄)(y−ȳ)] / Σ[(x−x̄)²]<br>
          β₀ (intercept) = ȳ − β₁ × x̄
        </div>
        <ul style="padding-left:18px;line-height:1.9">
          <li><strong>x</strong> = indeks hari (0, 1, 2, …)</li>
          <li><strong>y</strong> = revenue aktual per hari</li>
          <li><strong>Slope positif</strong> = tren naik; negatif = tren turun</li>
          <li><strong>Proyeksi</strong> = ekstrapolasi 7 hari ke depan</li>
        </ul>
        <div class="insight-box yellow" style="margin-top:8px">
          <span class="insight-icon">⚠️</span>
          <div class="insight-text">Model ini cocok untuk tren jangka pendek. Untuk akurasi lebih tinggi pada dataset besar, gunakan ARIMA atau model ML.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Mengerti</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: RFM Detail Table -->
<div class="modal fade" id="modalRFM" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-award me-2" style="color:#b794f4"></i>Detail Skor RFM per Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="tbl-scroll">
          <table class="at w-100" id="dtRFM">
            <thead>
              <tr>
                <th>#</th><th>Customer</th>
                <th class="text-center">Recency (hr)</th><th class="text-center">R</th>
                <th class="text-center">Freq</th><th class="text-center">F</th>
                <th class="text-end">Monetary</th><th class="text-center">M</th>
                <th class="text-center">Total</th><th>Segmen</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rfm_scored as $ri => $rfm):
                $sc = $seg_colors[$rfm['segment']] ?? '#999';
                $badge_style = fn($score, $high=4) => $score>=$high
                    ? "background:{$sc}22;color:{$sc};padding:1px 8px;border-radius:4px;font-size:.75rem;font-weight:700;font-family:var(--mono)"
                    : "background:var(--bg-stripe);color:var(--text-muted);padding:1px 8px;border-radius:4px;font-size:.75rem;font-weight:700;font-family:var(--mono)";
              ?>
              <tr>
                <td style="font-family:var(--mono);font-size:.72rem;color:var(--text-muted)"><?= $ri+1 ?></td>
                <td style="font-weight:700"><?= htmlspecialchars($rfm['nama_customer']) ?></td>
                <td style="text-align:center;font-family:var(--mono)"><?= (int)$rfm['recency'] ?></td>
                <td style="text-align:center"><span style="<?= $badge_style($rfm['r']) ?>"><?= $rfm['r'] ?></span></td>
                <td style="text-align:center;font-family:var(--mono)"><?= (int)$rfm['frequency'] ?></td>
                <td style="text-align:center"><span style="<?= $badge_style($rfm['f']) ?>"><?= $rfm['f'] ?></span></td>
                <td style="text-align:right;font-family:var(--mono);font-size:.78rem;color:var(--accent)">Rp <?= number_format((float)$rfm['monetary'],0,',','.') ?></td>
                <td style="text-align:center"><span style="<?= $badge_style($rfm['m']) ?>"><?= $rfm['m'] ?></span></td>
                <td style="text-align:center;font-family:var(--mono);font-weight:900;font-size:.85rem;color:<?= $rfm['score']>=12?'var(--accent)':'var(--text-sub)' ?>"><?= $rfm['score'] ?></td>
                <td><span style="padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700;background:<?= $sc ?>22;color:<?= $sc ?>"><?= htmlspecialchars($rfm['segment']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success-soft btn-sm" onclick="exportRFMcsv()"><i class="bi bi-download"></i> Export CSV</button>
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: RFM Help -->
<div class="modal fade" id="modalRFMHelp" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-question-circle me-2" style="color:#b794f4"></i>Panduan RFM Analysis</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="font-size:.84rem">
        <p style="color:var(--text-sub)">RFM mensegmentasi customer berdasarkan tiga dimensi perilaku:</p>
        <div style="display:flex;flex-direction:column;gap:10px">
          <div style="background:var(--green-bg);border-radius:10px;padding:12px;border-left:3px solid var(--green)">
            <strong style="color:var(--green)">R — Recency</strong><br>
            <span style="color:var(--text-sub)">Seberapa baru customer terakhir bertransaksi. Skor tinggi = datang baru-baru ini.</span>
          </div>
          <div style="background:var(--accent-bg);border-radius:10px;padding:12px;border-left:3px solid var(--accent)">
            <strong style="color:var(--accent)">F — Frequency</strong><br>
            <span style="color:var(--text-sub)">Seberapa sering customer datang. Skor tinggi = sering mengunjungi.</span>
          </div>
          <div style="background:var(--yellow-bg);border-radius:10px;padding:12px;border-left:3px solid var(--yellow)">
            <strong style="color:var(--yellow)">M — Monetary</strong><br>
            <span style="color:var(--text-sub)">Total pengeluaran customer. Skor tinggi = spending besar.</span>
          </div>
        </div>
        <p style="margin-top:14px;color:var(--text-sub)">Skor 1–5 berdasarkan kuintil data. Total skor 13–15 = Champions, 10–12 = Loyal, dst.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Mengerti</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ SCRIPTS ═══ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
/* ══ THEME ══════════════════════════════════════════ */
(function () {
    const saved = localStorage.getItem('salon_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    _setThemeIcons(saved);
})();
function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme');
    const nxt = cur === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', nxt);
    localStorage.setItem('salon_theme', nxt);
    _setThemeIcons(nxt);
    _refreshAllCharts();
    buildHeatmap();
}
function _setThemeIcons(t) {
    document.querySelectorAll('#themeBtn,#themeBtnDesktop').forEach(b => {
        if (b) b.textContent = t === 'dark' ? '☀️' : '🌙';
    });
}

/* ══ CHART UTILS ════════════════════════════════════ */
const isDark    = () => document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor = () => isDark() ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
const tickColor = () => isDark() ? '#8c90b0' : '#9499b8';

Chart.defaults.font.family = "'Nunito', sans-serif";
Chart.defaults.font.size   = 11;

function mkTooltip() {
    return {
        backgroundColor: isDark() ? '#1c1f30' : '#fff',
        borderColor:     isDark() ? 'rgba(255,255,255,.1)' : '#e2e5f0',
        borderWidth: 1,
        titleColor:  isDark() ? '#eef0fb' : '#1a1d2e',
        bodyColor:   isDark() ? '#8c90b0' : '#5a5f7d',
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
}
function mkAxes(opts = {}) {
    const axes = {};
    if (!opts.noX) axes.x = { grid: { color: gridColor() }, ticks: { color: tickColor() }, stacked: opts.stacked };
    if (!opts.noY) axes.y = {
        grid: { color: gridColor() }, stacked: opts.stacked,
        ticks: {
            color: tickColor(),
            callback: opts.yRaw ? undefined : (v => {
                if (v >= 1e6) return 'Rp' + (v / 1e6).toFixed(1) + 'M';
                if (v >= 1e3) return 'Rp' + (v / 1e3).toFixed(0) + 'K';
                return v;
            })
        }
    };
    return axes;
}

/* ── PHP DATA ── */
const DATA = {
    trendLabels:  <?= json_encode($forecast_labels) ?>,
    trendActual:  <?= json_encode($forecast_actual) ?>,
    trendPred:    <?= json_encode($forecast_pred) ?>,
    trendMA7:     <?= json_encode(array_merge($trend_ma7, array_fill(0, 7, null))) ?>,
    trendRevOnly: <?= json_encode($trend_rev) ?>,
    nActual:      <?= $n ?>,

    svcLabels:  <?= json_encode($svc_labels) ?>,
    svcFreq:    <?= json_encode($svc_freq) ?>,
    svcRevenue: <?= json_encode($svc_revenue) ?>,

    contribLabels: <?= json_encode($contrib_labels) ?>,
    contribVals:   <?= json_encode($contrib_vals) ?>,

    freqDistKeys: <?= json_encode(array_keys($freq_dist)) ?>,
    freqDistVals: <?= json_encode(array_values($freq_dist)) ?>,

    paretoLabels: <?= json_encode($pareto_labels) ?>,
    paretoRevs:   <?= json_encode($pareto_revs) ?>,
    paretoCum:    <?= json_encode($pareto_cum) ?>,

    hourLabels: <?= json_encode($hour_labels) ?>,
    hourCnt:    <?= json_encode($hour_cnt) ?>,
    hourRev:    <?= json_encode($hour_rev) ?>,

    dowLabels: <?= json_encode($dow_labels) ?>,
    dowCnt:    <?= json_encode($dow_cnt) ?>,

    rfmSegLabels:  <?= json_encode(array_keys($rfm_segments)) ?>,
    rfmSegCounts:  <?= json_encode(array_column($rfm_segments, 'count')) ?>,
    rfmSegColors:  <?= json_encode(array_column($rfm_segments, 'color')) ?>,

    basketLabels: <?= json_encode($basket_labels) ?>,
    basketVals:   <?= json_encode($basket_vals) ?>,

    monthlyLabels:  <?= json_encode($monthly_labels) ?>,
    monthlyRevenue: <?= json_encode($monthly_revenue) ?>,
    projRevenue:    <?= json_encode($proj_revenue) ?>,
    nmHistoric:     <?= $nm ?>,
};

const COLORS = ['#6c5ce7','#00b894','#4fd1c5','#f39c12','#3498db','#e84393','#e17055','#b794f4','#8c90b0'];
const trendAvg = DATA.trendRevOnly.length
    ? DATA.trendRevOnly.reduce((a, b) => a + b, 0) / DATA.trendRevOnly.length : 0;
const trendAvgArr = DATA.trendLabels.map(() => Math.round(trendAvg));

/* ══ CHARTS REGISTRY ════════════════════════════════ */
const registry = {};

function mkChart(id, config) {
    const el = document.getElementById(id);
    if (!el) return null;
    const ch = new Chart(el.getContext('2d'), config);
    registry[id] = ch;
    return ch;
}

/* ── 1. Trend ── */
mkChart('chartTrend1', {
    type: 'bar',
    data: {
        labels: DATA.trendLabels,
        datasets: [
            {
                label: 'Revenue Aktual', data: DATA.trendActual,
                backgroundColor: ctx => ctx.raw === null ? 'transparent' : 'rgba(108,92,231,.55)',
                borderRadius: 5, borderSkipped: false,
            },
            {
                label: 'MA-7', data: DATA.trendMA7, type: 'line',
                borderColor: '#b794f4', borderWidth: 2.5,
                pointRadius: 2, pointBackgroundColor: '#b794f4', fill: false, tension: .35,
            },
            {
                label: 'Rata-rata', data: trendAvgArr, type: 'line',
                borderColor: 'rgba(0,184,148,.55)', borderWidth: 1.5, borderDash: [6, 4],
                pointRadius: 0, fill: false,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { boxWidth: 12, padding: 16, color: tickColor() } }, tooltip: mkTooltip() },
        scales: mkAxes()
    }
});

/* ── 2a. Service Freq ── */
mkChart('chartSvcFreq', {
    type: 'bar',
    data: {
        labels: DATA.svcLabels,
        datasets: [{ label: 'Frekuensi', data: DATA.svcFreq, backgroundColor: COLORS.map(c => c + 'cc'), borderRadius: 5, borderSkipped: false }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        plugins: { legend: { display: false }, tooltip: { ...mkTooltip(), callbacks: { label: ctx => ` ${ctx.raw}× dipesan` } } },
        scales: {
            x: { grid: { color: gridColor() }, ticks: { color: tickColor(), callback: v => v + '×' } },
            y: { grid: { display: false }, ticks: { color: tickColor() } }
        }
    }
});

/* ── 2b. Service Revenue ── */
mkChart('chartSvcRev', {
    type: 'bar',
    data: {
        labels: DATA.svcLabels,
        datasets: [{ label: 'Revenue', data: DATA.svcRevenue, backgroundColor: COLORS.map(c => c + 'aa'), borderRadius: 5, borderSkipped: false }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        plugins: { legend: { display: false }, tooltip: mkTooltip() },
        scales: {
            x: { grid: { color: gridColor() }, ticks: { color: tickColor(), callback: v => 'Rp' + (v / 1000).toFixed(0) + 'K' } },
            y: { grid: { display: false }, ticks: { color: tickColor() } }
        }
    }
});

/* ── 3. Donut Contribution ── */
mkChart('chartContrib', {
    type: 'doughnut',
    data: {
        labels: DATA.contribLabels,
        datasets: [{ data: DATA.contribVals, backgroundColor: COLORS.map(c => c + 'cc'), borderColor: 'transparent', hoverOffset: 8 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '60%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, color: tickColor(), font: { size: 10 } } },
            tooltip: {
                ...mkTooltip(), callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a, b) => a + (b || 0), 0);
                        const pct = total > 0 ? Math.round(ctx.raw / total * 1000) / 10 : 0;
                        return ` Rp ${ctx.raw.toLocaleString('id-ID')} (${pct}%)`;
                    }
                }
            }
        }
    }
});

/* ── 4. Freq Distribution ── */
mkChart('chartFreqDist', {
    type: 'doughnut',
    data: {
        labels: DATA.freqDistKeys.map(l => l + ' kunjungan'),
        datasets: [{ data: DATA.freqDistVals, backgroundColor: ['#f39c12cc', '#3498dbcc', '#6c5ce7cc', '#00b894cc'], borderColor: 'transparent', hoverOffset: 6 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '55%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, color: tickColor(), font: { size: 10 } } },
            tooltip: { ...mkTooltip(), callbacks: { label: ctx => ` ${ctx.raw} customer` } }
        }
    }
});

/* ── 5. Pareto ── */
(function () {
    const ch = mkChart('chartPareto', {
        type: 'bar',
        data: {
            labels: DATA.paretoLabels,
            datasets: [
                {
                    label: 'Revenue', data: DATA.paretoRevs,
                    backgroundColor: '#e74c3c99', borderColor: '#e74c3c', borderWidth: 1,
                    borderRadius: 5, borderSkipped: false, yAxisID: 'y',
                },
                {
                    label: '% Kumulatif', data: DATA.paretoCum, type: 'line',
                    borderColor: '#f39c12', borderWidth: 2.5,
                    pointBackgroundColor: '#f39c12', pointRadius: 4,
                    fill: false, tension: .2, yAxisID: 'y2',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, padding: 16, color: tickColor() } },
                tooltip: {
                    ...mkTooltip(), callbacks: {
                        label: ctx => ctx.datasetIndex === 0
                            ? ` Rp ${ctx.raw.toLocaleString('id-ID')}`
                            : ` ${ctx.raw}% kumulatif`
                    }
                }
            },
            scales: {
                x: { grid: { color: gridColor() }, ticks: { color: tickColor(), maxRotation: 30 } },
                y: { grid: { color: gridColor() }, ticks: { color: tickColor(), callback: v => 'Rp' + (v / 1000).toFixed(0) + 'K' }, position: 'left' },
                y2: { position: 'right', min: 0, max: 100, grid: { display: false }, ticks: { color: '#f39c12', callback: v => v + '%' } }
            }
        }
    });

    // 80% reference line plugin (scoped to this chart)
    Chart.register({
        id: 'pareto80',
        afterDraw(chart) {
            if (chart.canvas.id !== 'chartPareto') return;
            const y2 = chart.scales.y2;
            if (!y2) return;
            const ctx2 = chart.ctx, y80 = y2.getPixelForValue(80);
            ctx2.save();
            ctx2.setLineDash([6, 4]);
            ctx2.strokeStyle = 'rgba(253,203,110,.7)';
            ctx2.lineWidth   = 1.5;
            ctx2.beginPath();
            ctx2.moveTo(chart.chartArea.left,  y80);
            ctx2.lineTo(chart.chartArea.right, y80);
            ctx2.stroke();
            ctx2.setLineDash([]);
            ctx2.fillStyle   = '#f39c12';
            ctx2.font        = '700 10px Nunito';
            ctx2.fillText('80%', chart.chartArea.right + 4, y80 + 4);
            ctx2.restore();
        }
    });
})();

/* ── 6. Heatmap (DOM, not Chart.js) ── */
function buildHeatmap() {
    const container = document.getElementById('heatmapHour');
    if (!container) return;
    container.innerHTML = '';
    const cols = DATA.hourLabels.length;
    const max  = Math.max(...DATA.hourCnt, 1);
    const grid = document.createElement('div');
    grid.className = 'heatmap-grid';
    grid.style.gridTemplateColumns = 'auto repeat(' + cols + ', 1fr)';

    // Header row
    const corner = document.createElement('div');
    corner.className = 'heat-label';
    corner.style.paddingRight = '6px';
    corner.textContent = 'Jam';
    grid.appendChild(corner);
    DATA.hourLabels.forEach(h => {
        const lbl = document.createElement('div');
        lbl.className = 'heat-label';
        lbl.style.justifyContent = 'center';
        lbl.style.fontSize = '.66rem';
        lbl.textContent = h.split(':')[0];
        grid.appendChild(lbl);
    });

    // Data row label
    const rowLbl = document.createElement('div');
    rowLbl.className = 'heat-label';
    rowLbl.style.paddingRight = '6px';
    rowLbl.textContent = 'Bkg';
    grid.appendChild(rowLbl);

    DATA.hourCnt.forEach((cnt, i) => {
        const alpha = max > 0 ? cnt / max : 0;
        const r = Math.round(108 + (231 - 108) * alpha);
        const g = Math.round(92  - 32 * alpha);
        const b = Math.round(231 - 171 * alpha);
        const cell = document.createElement('div');
        cell.className = 'heat-cell';
        cell.style.background = `rgba(${r},${g},${b},${0.12 + alpha * 0.78})`;
        cell.style.color       = alpha > 0.5 ? '#fff' : 'var(--text-sub)';
        cell.textContent       = cnt > 0 ? cnt : '';
        cell.title             = `${DATA.hourLabels[i]}: ${cnt} booking · Rp ${DATA.hourRev[i].toLocaleString('id-ID')}`;
        grid.appendChild(cell);
    });
    container.appendChild(grid);
}
buildHeatmap();

/* ── 6b. Day of Week ── */
mkChart('chartDow', {
    type: 'bar',
    data: {
        labels: DATA.dowLabels,
        datasets: [{
            label: 'Booking', data: DATA.dowCnt,
            backgroundColor: DATA.dowCnt.map((v, _, arr) => v === Math.max(...arr) ? '#6c5ce7' : 'rgba(108,92,231,.35)'),
            borderRadius: 6, borderSkipped: false,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { ...mkTooltip(), callbacks: { label: ctx => ` ${ctx.raw} booking` } } },
        scales: {
            x: { grid: { display: false }, ticks: { color: tickColor() } },
            y: { grid: { color: gridColor() }, ticks: { color: tickColor(), stepSize: 1 } }
        }
    }
});

/* ── 7. Demand Forecast ── */
mkChart('chartForecast', {
    type: 'bar',
    data: {
        labels: DATA.trendLabels,
        datasets: [
            {
                label: 'Aktual', data: DATA.trendActual,
                backgroundColor: ctx => ctx.raw === null ? 'transparent' : 'rgba(52,152,219,.5)',
                borderColor: '#3498db', borderWidth: 1, borderRadius: 4, borderSkipped: false,
            },
            {
                label: 'Fitted / Proyeksi', data: DATA.trendPred, type: 'line',
                borderColor: '#e17055', borderWidth: 2.5,
                pointRadius: DATA.trendPred.map((_, i) => i >= DATA.nActual ? 5 : 2),
                pointBackgroundColor: DATA.trendPred.map((_, i) => i >= DATA.nActual ? '#e17055' : 'rgba(225,112,85,.4)'),
                pointBorderColor: '#e17055',
                fill: false, tension: 0,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { boxWidth: 12, padding: 16, color: tickColor() } }, tooltip: mkTooltip() },
        scales: mkAxes()
    }
});

/* ── 8. Sales Forecast Monthly ── */
mkChart('chartSalesForecast', {
    type: 'bar',
    data: {
        labels: DATA.monthlyLabels,
        datasets: [
            {
                label: 'Revenue Aktual', data: DATA.monthlyRevenue,
                backgroundColor: ctx => ctx.raw === null ? 'transparent' : 'rgba(52,152,219,.6)',
                borderRadius: 5, borderSkipped: false,
            },
            {
                label: 'Tren / Proyeksi', data: DATA.projRevenue, type: 'line',
                borderColor: '#e17055', borderWidth: 2.5, borderDash: [5, 3],
                pointRadius: 4,
                pointBackgroundColor: DATA.projRevenue.map((_, i) => i >= DATA.nmHistoric ? '#e17055' : 'rgba(225,112,85,.4)'),
                fill: false, tension: 0,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { boxWidth: 12, padding: 16, color: tickColor() } }, tooltip: mkTooltip() },
        scales: mkAxes()
    }
});

/* ── 9. RFM Doughnut ── */
mkChart('chartRFM', {
    type: 'doughnut',
    data: {
        labels: DATA.rfmSegLabels,
        datasets: [{
            data: DATA.rfmSegCounts,
            backgroundColor: DATA.rfmSegColors.map(c => c + 'cc'),
            borderColor: 'transparent', hoverOffset: 8,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '55%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, color: tickColor(), font: { size: 10 } } },
            tooltip: { ...mkTooltip(), callbacks: { label: ctx => ` ${ctx.raw} customer` } }
        }
    }
});

/* ── 10. Market Basket ── */
if (DATA.basketLabels.length > 0) {
    mkChart('chartBasket', {
        type: 'bar',
        data: {
            labels: DATA.basketLabels,
            datasets: [{
                label: 'Co-occurrence', data: DATA.basketVals,
                backgroundColor: COLORS.map(c => c + 'bb'), borderRadius: 5, borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
            plugins: { legend: { display: false }, tooltip: { ...mkTooltip(), callbacks: { label: ctx => ` ${ctx.raw}× bersama` } } },
            scales: {
                x: { grid: { color: gridColor() }, ticks: { color: tickColor(), stepSize: 1 } },
                y: { grid: { display: false }, ticks: { color: tickColor(), font: { size: 10 } } }
            }
        }
    });
}

/* ══ THEME REFRESH ══════════════════════════════════ */
function _refreshAllCharts() {
    Object.values(registry).forEach(ch => {
        if (!ch) return;
        ['x', 'y', 'y2'].forEach(ax => {
            if (ch.options.scales?.[ax]) {
                if (ch.options.scales[ax].grid)  ch.options.scales[ax].grid.color  = gridColor();
                if (ch.options.scales[ax].ticks) ch.options.scales[ax].ticks.color = tickColor();
            }
        });
        if (ch.options.plugins?.legend?.labels) ch.options.plugins.legend.labels.color = tickColor();
        ch.update('none');
    });
}

/* ══ SECTION NAV ════════════════════════════════════ */
document.querySelectorAll('.sec-nav-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id  = btn.dataset.target;
        const el  = document.getElementById(id);
        if (!el) return;
        const top = el.getBoundingClientRect().top + window.scrollY - 120;
        window.scrollTo({ top, behavior: 'smooth' });
    });
});

// Scroll spy
const sectionIds = ['s1','s2','s3','s4','s5','s6','s7','s8','s9','s10'];
const navBtns    = document.querySelectorAll('.sec-nav-btn');
window.addEventListener('scroll', () => {
    let cur = 0;
    sectionIds.forEach((id, i) => {
        const el = document.getElementById(id);
        if (el && el.getBoundingClientRect().top <= 160) cur = i;
    });
    navBtns.forEach((b, i) => b.classList.toggle('active', i === cur));
}, { passive: true });

/* ══ DATATABLES (init once per modal show) ═════════ */
const dtInited = {};
function initDT(modalId, tableId, order = [[0, 'asc']]) {
    document.getElementById(modalId)?.addEventListener('shown.bs.modal', function () {
        if (dtInited[tableId]) return;
        dtInited[tableId] = true;
        $('#' + tableId).DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
            pageLength: 25, order,
            dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>t<'row'<'col-sm-6'i><'col-sm-6'p>>"
        });
    });
}
initDT('modalRFM',   'dtRFM',  [[8, 'desc']]);
initDT('modalCust',  'dtCust', [[3, 'desc']]);
initDT('modalTrend', 'dtTrend',[[0, 'asc']]);
initDT('modalSvc',   'dtSvc',  [[2, 'desc']]);

/* ══ EXPORT EXCEL ═══════════════════════════════════ */
function exportExcel() {
    const wb = XLSX.utils.book_new();

    // Revenue trend sheet
    const trendData = [['Tanggal', 'Revenue', 'Booking', 'MA-7']];
    <?php foreach ($trend_rows as $ti => $tr): ?>
    trendData.push([<?= json_encode($tr['lbl']) ?>, <?= (float)$tr['revenue'] ?>, <?= (int)$tr['bookings'] ?>, <?= $trend_ma7[$ti] ?>]);
    <?php endforeach; ?>
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(trendData), 'Revenue Trend');

    // Service popularity sheet
    const svcData = [['Layanan', 'Frekuensi', 'Revenue', 'Avg Harga']];
    <?php foreach ($svc_rows as $svc): ?>
    svcData.push([<?= json_encode($svc['nama_layanan']) ?>, <?= (int)$svc['freq'] ?>, <?= (float)$svc['revenue'] ?>, <?= round((float)$svc['avg_price']) ?>]);
    <?php endforeach; ?>
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(svcData), 'Layanan');

    // Customer sheet
    const custData = [['Customer', 'Kunjungan', 'Total Spend', 'Avg/Visit', 'Terakhir']];
    <?php foreach ($cust_rows as $cust): ?>
    custData.push([<?= json_encode($cust['nama_customer']) ?>, <?= (int)$cust['visits'] ?>, <?= (float)$cust['total_spend'] ?>, <?= round((float)$cust['avg_spend']) ?>, <?= json_encode($cust['last_visit']) ?>]);
    <?php endforeach; ?>
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(custData), 'Customer');

    // RFM sheet
    const rfmData = [['Customer', 'Recency', 'R', 'Frequency', 'F', 'Monetary', 'M', 'Total', 'Segmen']];
    <?php foreach ($rfm_scored as $rfm): ?>
    rfmData.push([<?= json_encode($rfm['nama_customer']) ?>, <?= (int)$rfm['recency'] ?>, <?= $rfm['r'] ?>, <?= (int)$rfm['frequency'] ?>, <?= $rfm['f'] ?>, <?= (float)$rfm['monetary'] ?>, <?= $rfm['m'] ?>, <?= $rfm['score'] ?>, <?= json_encode($rfm['segment']) ?>]);
    <?php endforeach; ?>
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(rfmData), 'RFM');

    // Market basket sheet
    const mbData = [['Kombinasi', 'Frekuensi']];
    <?php foreach ($basket_pairs as $pair => $cnt): ?>
    mbData.push([<?= json_encode($pair) ?>, <?= $cnt ?>]);
    <?php endforeach; ?>
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(mbData), 'Market Basket');

    XLSX.writeFile(wb, 'analitik_amoy_<?= date('Ymd') ?>.xlsx');
}

/* ══ EXPORT PDF ═════════════════════════════════════ */
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

    // Cover
    doc.setFillColor(44, 53, 66);
    doc.rect(0, 0, 210, 42, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(18); doc.setFont('helvetica', 'bold');
    doc.text('Analitik Bisnis — Amoy Salon', 14, 18);
    doc.setFontSize(9); doc.setFont('helvetica', 'normal');
    doc.text('Periode: <?= date("d M Y", strtotime($tgl_awal)) ?> – <?= date("d M Y", strtotime($tgl_akhir)) ?>', 14, 28);
    doc.text('Digenerate: ' + new Date().toLocaleDateString('id-ID'), 14, 35);

    // KPI
    doc.setTextColor(30, 30, 50);
    doc.setFontSize(11); doc.setFont('helvetica', 'bold'); doc.text('KPI Summary', 14, 54);
    doc.autoTable({
        startY: 57,
        head: [['Metrik', 'Nilai']],
        body: [
            ['Total Omzet', 'Rp <?= number_format((float)$r_kpi['omzet'],0,',','.') ?>'],
            ['Total Booking', '<?= (int)$r_kpi['bookings'] ?>'],
            ['Customer Unik', '<?= (int)$r_kpi['customers'] ?>'],
            ['Avg. Transaksi', 'Rp <?= number_format((float)$r_kpi['avg_order'],0,',','.') ?>'],
            ['Tren Revenue', '<?= $trend_direction ?> (<?= $trend_pct ?>%/hari)'],
            ['Customer Loyal', '<?= $loyal_custs ?> / <?= $total_custs ?>'],
        ],
        styles: { fontSize: 9, cellPadding: 3 },
        headStyles: { fillColor: [108, 92, 231], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [248, 249, 252] },
        margin: { left: 14, right: 14 }
    });

    // Pareto
    doc.addPage();
    doc.setFontSize(11); doc.setFont('helvetica', 'bold'); doc.text('Pareto Analysis', 14, 20);
    const paretoBody = <?php
        $pb = []; $run = 0;
        foreach (array_slice($pareto_services, 0, 12) as $ps) {
            $run += (float)$ps['revenue'];
            $pc   = $total_svc_rev > 0 ? round($run/$total_svc_rev*100,1) : 0;
            $pb[] = [$ps['nama_layanan'], 'Rp '.number_format((float)$ps['revenue'],0,',','.'), $pc.'%'];
        }
        echo json_encode($pb);
    ?>;
    doc.autoTable({
        startY: 23,
        head: [['Layanan', 'Revenue', 'Kumulatif %']],
        body: paretoBody,
        styles: { fontSize: 9, cellPadding: 3 },
        headStyles: { fillColor: [231, 76, 60], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [248, 249, 252] },
        margin: { left: 14, right: 14 }
    });

    // RFM
    const rfmY = doc.lastAutoTable.finalY + 14;
    doc.setFontSize(11); doc.setFont('helvetica', 'bold'); doc.text('RFM Segmentasi', 14, rfmY);
    const rfmBody = <?php
        $rb = [];
        foreach ($rfm_segments as $seg => $data) {
            $rb[] = [$seg, $data['count'].' orang', 'Rp '.number_format((float)$data['revenue'],0,',','.')];
        }
        echo json_encode($rb);
    ?>;
    doc.autoTable({
        startY: rfmY + 3,
        head: [['Segmen', 'Customer', 'Revenue']],
        body: rfmBody,
        styles: { fontSize: 9, cellPadding: 3 },
        headStyles: { fillColor: [108, 92, 231], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [248, 249, 252] },
        margin: { left: 14, right: 14 }
    });

    // Market Basket
    if (DATA.basketLabels.length > 0) {
        doc.addPage();
        doc.setFontSize(11); doc.setFont('helvetica', 'bold'); doc.text('Market Basket Analysis', 14, 20);
        doc.autoTable({
            startY: 23,
            head: [['Kombinasi Layanan', 'Frekuensi']],
            body: DATA.basketLabels.map((l, i) => [l, DATA.basketVals[i] + '×']),
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [0, 184, 148], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [248, 249, 252] },
            margin: { left: 14, right: 14 }
        });
    }

    // Footer
    const pageCount = doc.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(7); doc.setTextColor(180, 180, 180); doc.setFont('helvetica', 'normal');
        doc.text('Amoy Salon Analytics · Hal ' + i + ' / ' + pageCount, 14, 290);
        doc.text('Digenerate <?= date('d M Y') ?>', 140, 290);
    }
    doc.save('analitik_amoy_<?= date('Ymd') ?>.pdf');
}

/* ══ EXPORT RFM CSV ═════════════════════════════════ */
function exportRFMcsv() {
    const rows = [['Customer', 'Recency', 'R', 'Freq', 'F', 'Monetary', 'M', 'Total', 'Segmen']];
    <?php foreach ($rfm_scored as $rfm): ?>
    rows.push([<?= json_encode($rfm['nama_customer']) ?>, <?= (int)$rfm['recency'] ?>, <?= $rfm['r'] ?>, <?= (int)$rfm['frequency'] ?>, <?= $rfm['f'] ?>, <?= (float)$rfm['monetary'] ?>, <?= $rfm['m'] ?>, <?= $rfm['score'] ?>, <?= json_encode($rfm['segment']) ?>]);
    <?php endforeach; ?>
    const csv  = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\r\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = 'rfm_amoy_<?= date('Ymd') ?>.csv';
    a.click();
}
</script>
</body>
</html>