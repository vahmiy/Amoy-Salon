<?php
include '../class/koneksi.php';

$id_b = $_GET['id'];

// Ambil data utama booking
$query = mysqli_query($conn, "SELECT * FROM bookings WHERE id_booking = '$id_b'");
$data = mysqli_fetch_array($query);

// Hitung keuangan
$total   = $data['total_biaya'];
$bayar   = $data['bayar_cash'] + $data['bayar_transfer'];
$selisih = $bayar - $total;

// Ambil detail layanan
$details_arr = [];
$details_q = mysqli_query($conn, "SELECT d.*, s.nama_layanan FROM booking_details d 
                                   JOIN services s ON d.id_service = s.id_service 
                                   WHERE d.id_booking = '$id_b'");
while ($ld = mysqli_fetch_array($details_q)) {
    $details_arr[] = $ld;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Nota #<?= htmlspecialchars($id_b) ?></title>
    <style>
        /* ── PRINT STYLES (58mm thermal) ── */
        @page {
            margin: 0;
            size: 58mm auto;
        }
        @media print {
            body {
                font-family: Arial, Helvetica, sans-serif;
                width: 44mm;
                margin-left: 2mm;
                margin-right: 0;
                padding: 5px 0;
                font-size: 11px;
                color: #000;
                line-height: 1.2;
            }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }

        /* ── SCREEN STYLES ── */
        @media screen {
            :root {
                --bg:      #0e0f18;
                --card:    #161825;
                --border:  rgba(255,255,255,.08);
                --accent:  #6c5ce7;
                --green:   #00b894;
                --yellow:  #f39c12;
                --red:     #e74c3c;
                --text:    #eef0fb;
                --sub:     #8c90b0;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                background: var(--bg);
                color: var(--text);
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 20px 16px 40px;
            }

            .page-header {
                width: 100%; max-width: 480px;
                display: flex; align-items: center; gap: 12px;
                margin-bottom: 24px;
            }
            .back-btn {
                display: inline-flex; align-items: center; gap: 6px;
                padding: 8px 16px; border-radius: 8px;
                background: rgba(255,255,255,.06);
                border: 1px solid var(--border);
                color: var(--sub); font-size: 13px; font-weight: 600;
                text-decoration: none; transition: all .18s;
            }
            .back-btn:hover { background: rgba(255,255,255,.12); color: var(--text); }

            /* Nota preview card */
            .nota-preview {
                background: #fff;
                color: #000;
                width: 220px;
                border-radius: 8px;
                box-shadow: 0 8px 32px rgba(0,0,0,.5);
                padding: 12px 10px;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 10px;
                line-height: 1.3;
                margin-bottom: 24px;
            }
            .nota-preview h2 {
                font-size: 14px; font-weight: bold;
                text-align: center; margin-bottom: 2px;
            }
            .nota-preview .np-center { text-align: center; font-size: 9px; }
            .nota-preview .dashed {
                border-bottom: 1px dashed #000; margin: 6px 0;
            }
            .nota-preview table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            .nota-preview td { vertical-align: top; padding: 2px 0; word-wrap: break-word; }
            .nota-preview .tr { text-align: right; }
            .nota-preview .tc { text-align: center; }
            .nota-preview .bold { font-weight: bold; }

            /* Action panel */
            .action-panel {
                width: 100%; max-width: 480px;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 14px;
                padding: 22px;
            }
            .action-panel h3 {
                font-size: 15px; font-weight: 700;
                margin-bottom: 18px; color: var(--text);
            }
            .method-grid {
                display: grid; grid-template-columns: 1fr 1fr;
                gap: 10px; margin-bottom: 16px;
            }
            @media(max-width: 420px) { .method-grid { grid-template-columns: 1fr; } }

            .method-btn {
                display: flex; flex-direction: column;
                align-items: center; justify-content: center;
                gap: 6px; padding: 16px 10px;
                border-radius: 10px; border: 1.5px solid var(--border);
                background: rgba(255,255,255,.03);
                cursor: pointer; transition: all .18s;
                font-size: 13px; font-weight: 700; color: var(--sub);
                text-align: center;
            }
            .method-btn svg { opacity: .6; transition: opacity .18s; }
            .method-btn:hover { border-color: var(--accent); color: var(--text); background: rgba(108,92,231,.08); }
            .method-btn:hover svg { opacity: 1; }
            .method-btn.primary { border-color: var(--accent); background: rgba(108,92,231,.12); color: var(--text); }
            .method-btn.primary svg { opacity: 1; }

            /* BT Status */
            .bt-status {
                display: flex; align-items: center; gap: 8px;
                padding: 10px 14px; border-radius: 8px;
                background: rgba(255,255,255,.04);
                border: 1px solid var(--border);
                font-size: 12px; color: var(--sub);
                margin-bottom: 12px;
            }
            .bt-dot {
                width: 8px; height: 8px; border-radius: 50%;
                background: var(--sub); flex-shrink: 0;
                transition: background .3s;
            }
            .bt-dot.connected { background: var(--green); box-shadow: 0 0 6px var(--green); }
            .bt-dot.connecting { background: var(--yellow); animation: pulse .8s infinite; }
            .bt-dot.error { background: var(--red); }
            @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

            .info-box {
                padding: 10px 14px; border-radius: 8px;
                background: rgba(108,92,231,.08);
                border: 1px solid rgba(108,92,231,.2);
                font-size: 11.5px; color: var(--sub);
                line-height: 1.5; margin-top: 12px;
            }
            .info-box strong { color: var(--accent); }

            /* Print button (large) */
            .btn-print-bt {
                width: 100%; padding: 14px;
                background: var(--accent);
                border: none; border-radius: 10px;
                color: #fff; font-size: 14px; font-weight: 700;
                cursor: pointer; transition: all .18s;
                display: flex; align-items: center; justify-content: center; gap: 8px;
                margin-top: 10px;
            }
            .btn-print-bt:hover { background: #5a4bd1; transform: translateY(-1px); }
            .btn-print-bt:active { transform: translateY(0); }
            .btn-print-bt:disabled { opacity: .4; cursor: not-allowed; transform: none; }

            .btn-secondary {
                width: 100%; padding: 11px;
                background: transparent;
                border: 1px solid var(--border);
                border-radius: 10px;
                color: var(--sub); font-size: 13px; font-weight: 600;
                cursor: pointer; transition: all .18s;
                display: flex; align-items: center; justify-content: center; gap: 8px;
                margin-top: 8px;
            }
            .btn-secondary:hover { background: rgba(255,255,255,.05); color: var(--text); }

            .print-only { display: none; }
        }
    </style>
</head>
<body>

<!-- ══ SCREEN ONLY: Preview + Panel ══ -->
<div class="no-print">

    <div class="page-header">
        <a href="javascript:history.back()" class="back-btn">
            ← Kembali
        </a>
        <span style="font-size:14px;font-weight:700;color:var(--text,#fff)">Cetak Nota #<?= htmlspecialchars($id_b) ?></span>
    </div>

    <!-- Nota Preview -->
    <div class="nota-preview" id="notaPreview">
        <h2>AMOY SALON</h2>
        <div class="np-center">Kp Dangdeur No.001/008, Kiangroke</div>
        <div class="np-center">Kec. Banjaran, Kab. Bandung</div>
        <div class="np-center">WA: 0812-3456-7890</div>
        <div class="dashed"></div>
        <table>
            <tr>
                <td class="bold">#<?= htmlspecialchars($id_b) ?></td>
                <td class="tr" style="font-size:9px"><?= date('d/m/y H:i') ?></td>
            </tr>
            <tr><td colspan="2">Cust: <strong><?= htmlspecialchars($data['nama_customer']) ?></strong></td></tr>
            <tr><td colspan="2" style="font-size:9px">Reservasi: <?= date('d/m/Y', strtotime($data['tgl_booking'])) ?></td></tr>
        </table>
        <div class="dashed"></div>
        <table>
            <tr><td class="bold">Layanan</td><td class="tr bold">Harga</td></tr>
            <?php foreach ($details_arr as $ld): ?>
            <tr>
                <td><?= htmlspecialchars($ld['nama_layanan']) ?></td>
                <td class="tr"><?= number_format($ld['subtotal'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div class="dashed"></div>
        <table>
            <tr>
                <td>Total</td>
                <td class="tr bold">Rp <?= number_format($total, 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Bayar</td>
                <td class="tr">Rp <?= number_format($bayar, 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td><?= $selisih >= 0 ? 'Kembali' : 'Kurang' ?></td>
                <td class="tr bold">Rp <?= number_format(abs($selisih), 0, ',', '.') ?></td>
            </tr>
        </table>
        <div class="dashed"></div>
        <div class="tc" style="margin-top:6px;font-size:9px">
            <strong>Terima Kasih Atas Kunjungan Anda</strong><br>
            Barang/Jasa yang sudah dibeli<br>tidak dapat ditukar
        </div>
    </div>

    <!-- Action Panel -->
    <div class="action-panel">
        <h3>🖨️ Opsi Cetak</h3>

        <!-- BT Status -->
        <div class="bt-status" id="btStatus">
            <div class="bt-dot" id="btDot"></div>
            <span id="btStatusText">Belum terhubung ke printer</span>
        </div>

        <div class="method-grid">
            <!-- Bluetooth -->
            <button class="method-btn primary" onclick="connectBluetooth()">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6.5 6.5 17.5 17.5 12 23 12 1 17.5 6.5 6.5 17.5"></polyline>
                </svg>
                Bluetooth<br><span style="font-size:10px;font-weight:500">C58BT-Pro</span>
            </button>
            <!-- Browser Print -->
            <button class="method-btn" onclick="window.print()">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Browser<br><span style="font-size:10px;font-weight:500">window.print()</span>
            </button>
        </div>

        <button class="btn-print-bt" id="btnCetak" onclick="printViaBluetooth()" disabled>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="6.5 6.5 17.5 17.5 12 23 12 1 17.5 6.5 6.5 17.5"></polyline>
            </svg>
            Cetak via Bluetooth
        </button>

        <button class="btn-secondary" onclick="disconnectBluetooth()">
            Putuskan Koneksi
        </button>

        <div class="info-box">
            <strong>Petunjuk Bluetooth:</strong><br>
            1. Pastikan printer <strong>C58BT-Pro</strong> menyala &amp; Bluetooth aktif.<br>
            2. Tap <em>Bluetooth C58BT-Pro</em> → pilih printer dari daftar.<br>
            3. Setelah terhubung (indikator hijau), tap <em>Cetak via Bluetooth</em>.<br>
            4. Jika Bluetooth API tidak tersedia, gunakan tombol <em>Browser</em> dan pilih printer dari dialog sistem.
        </div>
    </div>
</div>

<!-- ══ PRINT ONLY: Actual thermal layout ══ -->
<div class="print-only" id="printArea">
    <div style="font-family:Arial,Helvetica,sans-serif;width:44mm;margin-left:2mm;padding:5px 0;font-size:11px;color:#000;line-height:1.2;text-align:center">
        <h2 style="margin:0 0 3px;font-size:16px;font-weight:bold">AMOY SALON</h2>
        <p style="margin:0;font-size:10px">Kp Dangdeur No.001/008, Kiangroke</p>
        <p style="margin:0;font-size:10px">Kec. Banjaran, Kab. Bandung</p>
        <p style="margin:0;font-size:10px">WA: 0812-3456-7890</p>
    </div>
    <div style="border-bottom:1px dashed #000;margin:5px 0"></div>
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;font-family:Arial,Helvetica,sans-serif;font-size:11px">
        <tr>
            <td style="font-weight:bold;vertical-align:top;padding:2px 0">#<?= htmlspecialchars($id_b) ?></td>
            <td style="text-align:right;font-size:10px;vertical-align:top;padding:2px 0"><?= date('d/m/y H:i') ?></td>
        </tr>
        <tr><td colspan="2" style="padding:2px 0">Cust: <strong><?= htmlspecialchars($data['nama_customer']) ?></strong></td></tr>
        <tr><td colspan="2" style="font-size:10px;padding:2px 0">Reservasi: <?= date('d/m/Y', strtotime($data['tgl_booking'])) ?></td></tr>
    </table>
    <div style="border-bottom:1px dashed #000;margin:5px 0"></div>
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;font-family:Arial,Helvetica,sans-serif;font-size:11px">
        <tr>
            <th style="text-align:left;font-weight:bold;padding:2px 0">Layanan</th>
            <th style="text-align:right;font-weight:bold;padding:2px 0">Harga</th>
        </tr>
        <?php foreach ($details_arr as $ld): ?>
        <tr>
            <td style="vertical-align:top;padding:2px 0;word-wrap:break-word"><?= htmlspecialchars($ld['nama_layanan']) ?></td>
            <td style="text-align:right;vertical-align:top;padding:2px 0"><?= number_format($ld['subtotal'], 0, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div style="border-bottom:1px dashed #000;margin:5px 0"></div>
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;font-family:Arial,Helvetica,sans-serif;font-size:11px">
        <tr>
            <td style="padding:2px 0">Total</td>
            <td style="text-align:right;font-weight:bold;padding:2px 0">Rp <?= number_format($total, 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td style="padding:2px 0">Bayar</td>
            <td style="text-align:right;padding:2px 0">Rp <?= number_format($bayar, 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td style="padding:2px 0"><?= $selisih >= 0 ? 'Kembali' : 'Kurang' ?></td>
            <td style="text-align:right;font-weight:bold;padding:2px 0">Rp <?= number_format(abs($selisih), 0, ',', '.') ?></td>
        </tr>
    </table>
    <div style="border-bottom:1px dashed #000;margin:5px 0"></div>
    <div style="text-align:center;margin-top:10px;font-family:Arial,Helvetica,sans-serif;font-size:10px">
        <strong>Terima Kasih Atas Kunjungan Anda</strong><br>
        Barang/Jasa yang sudah dibeli<br>tidak dapat ditukar
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════
   WEB BLUETOOTH ESC/POS — C58BT-Pro
   Service UUID yang umum dipakai printer thermal BT:
   - e7810a71-73ae-499d-8c15-faa9aef0c3f2  (PrinterService)
   - 49535343-fe7d-4ae5-8fa9-9fafd205e455  (Sering dipakai C58-series)
   - 000018f0-0000-1000-8000-00805f9b34fb  (BLE Serial)
   Coba urutan ini. Jika gagal, scan semua service.
══════════════════════════════════════════════════════ */

const PRINTER_SERVICES = [
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    '000018f0-0000-1000-8000-00805f9b34fb',
];
const WRITE_CHARS = [
    'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f',  // C58-Pro common write
    '49535343-8841-43f4-a8d4-ecbe34729bb3',
    '49535343-aca3-481c-91ec-d85e28a60318',
    '00002af1-0000-1000-8000-00805f9b34fb',
];

let btDevice    = null;
let btServer    = null;
let writeChar   = null;

// PHP data untuk ESC/POS
const notaData = {
    id:        <?= json_encode($id_b) ?>,
    customer:  <?= json_encode($data['nama_customer']) ?>,
    tglBook:   <?= json_encode(date('d/m/Y', strtotime($data['tgl_booking']))) ?>,
    jamBook:   <?= json_encode(substr($data['jam_booking'], 0, 5)) ?>,
    total:     <?= json_encode((float)$total) ?>,
    bayar:     <?= json_encode((float)$bayar) ?>,
    selisih:   <?= json_encode((float)$selisih) ?>,
    details:   <?= json_encode(array_map(fn($d) => ['nama' => $d['nama_layanan'], 'harga' => (float)$d['subtotal']], $details_arr)) ?>
};

/* ── UI helpers ── */
function setBtStatus(state, msg) {
    const dot  = document.getElementById('btDot');
    const text = document.getElementById('btStatusText');
    const btn  = document.getElementById('btnCetak');
    dot.className = 'bt-dot ' + state;
    text.textContent = msg;
    btn.disabled = (state !== 'connected');
}

/* ══ CONNECT BLUETOOTH ══ */
async function connectBluetooth() {
    if (!navigator.bluetooth) {
        alert('Web Bluetooth tidak didukung di browser ini.\nGunakan Chrome untuk Android, atau tombol Browser untuk mencetak.');
        return;
    }
    setBtStatus('connecting', 'Mencari printer...');
    try {
        btDevice = await navigator.bluetooth.requestDevice({
            filters: [
                { namePrefix: 'C58' },
                { namePrefix: 'MTP' },
                { namePrefix: 'Printer' },
                { namePrefix: 'RPP' },
                { namePrefix: 'BTP' },
            ],
            optionalServices: PRINTER_SERVICES,
            // acceptAllDevices: true,  // uncomment jika filter di atas tidak menemukan
        });

        setBtStatus('connecting', 'Menghubungkan ke ' + btDevice.name + '...');
        btDevice.addEventListener('gattserverdisconnected', onDisconnected);

        btServer = await btDevice.gatt.connect();
        writeChar = await findWriteCharacteristic(btServer);

        if (!writeChar) throw new Error('Karakteristik tulis tidak ditemukan. Coba putuskan & hubungkan ulang.');

        setBtStatus('connected', '✓ Terhubung: ' + btDevice.name);
    } catch (e) {
        setBtStatus('error', 'Gagal: ' + e.message);
        console.error(e);
    }
}

/* ── Cari characteristic yang writable ── */
async function findWriteCharacteristic(server) {
    for (const svcUUID of PRINTER_SERVICES) {
        try {
            const svc = await server.getPrimaryService(svcUUID);
            const chars = await svc.getCharacteristics();
            for (const c of chars) {
                if (c.properties.write || c.properties.writeWithoutResponse) {
                    console.log('Found writable char:', c.uuid, 'in service:', svcUUID);
                    return c;
                }
            }
            // Coba UUID spesifik
            for (const charUUID of WRITE_CHARS) {
                try {
                    const c = await svc.getCharacteristic(charUUID);
                    if (c.properties.write || c.properties.writeWithoutResponse) return c;
                } catch {}
            }
        } catch {}
    }
    // Fallback: scan semua service
    try {
        const services = await server.getPrimaryServices();
        for (const svc of services) {
            const chars = await svc.getCharacteristics();
            for (const c of chars) {
                if (c.properties.write || c.properties.writeWithoutResponse) return c;
            }
        }
    } catch {}
    return null;
}

function onDisconnected() {
    setBtStatus('error', 'Printer terputus. Hubungkan ulang.');
    writeChar = null;
}

async function disconnectBluetooth() {
    if (btDevice && btDevice.gatt.connected) {
        btDevice.gatt.disconnect();
    }
    btDevice = null; btServer = null; writeChar = null;
    setBtStatus('', 'Belum terhubung ke printer');
    document.getElementById('btnCetak').disabled = true;
}

/* ══ ESC/POS BUILDER ══ */
function buildEscPos() {
    const ESC = 0x1B, GS = 0x1D, LF = 0x0A;

    const bytes = [];
    const push = (...b) => bytes.push(...b);
    const text = (str) => { for (const c of str) bytes.push(c.charCodeAt(0) & 0xFF); };
    const nl   = (n = 1) => { for (let i = 0; i < n; i++) push(LF); };

    // Initialize
    push(ESC, 0x40);  // ESC @ — init printer
    // Code page latin (ISO 8859-1)
    push(ESC, 0x74, 0x06);

    // ── HEADER: Center, Bold, Double size ──
    push(ESC, 0x61, 0x01);  // center align
    push(ESC, 0x45, 0x01);  // bold on
    push(GS,  0x21, 0x11);  // double width + height
    text('AMOY SALON');
    nl();
    push(GS,  0x21, 0x00);  // normal size
    push(ESC, 0x45, 0x00);  // bold off
    text('Kp Dangdeur No.001/008');
    nl();
    text('Kec. Banjaran, Kab. Bandung');
    nl();
    text('WA: 0812-3456-7890');
    nl();

    // ── Garis ──
    push(ESC, 0x61, 0x00);  // left align
    text('--------------------------------');
    nl();

    // ── Info booking ──
    const idLabel  = '#' + notaData.id;
    const tglCetak = new Date().toLocaleDateString('id-ID', {day:'2-digit', month:'2-digit', year:'2-digit'})
                   + ' ' + new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
    text(padRight(idLabel, 16) + padLeft(tglCetak, 16));
    nl();
    text('Cust: ' + notaData.customer);
    nl();
    text('Reservasi: ' + notaData.tglBook);
    nl();

    // ── Garis ──
    text('--------------------------------');
    nl();

    // ── Layanan ──
    push(ESC, 0x45, 0x01);  // bold
    text(padRight('Layanan', 22) + padLeft('Harga', 10));
    nl();
    push(ESC, 0x45, 0x00);  // bold off

    for (const d of notaData.details) {
        const harga = 'Rp ' + fmt(d.harga);
        const nama  = d.nama.substring(0, 22);
        text(padRight(nama, 22) + padLeft(harga, 10));
        nl();
    }

    // ── Garis ──
    text('--------------------------------');
    nl();

    // ── Total ──
    const totalStr = 'Rp ' + fmt(notaData.total);
    const bayarStr = 'Rp ' + fmt(notaData.bayar);
    const sisaLabel = notaData.selisih >= 0 ? 'Kembali' : 'Kurang';
    const sisaStr   = 'Rp ' + fmt(Math.abs(notaData.selisih));

    text(padRight('Total', 22) + padLeft(totalStr, 10));
    nl();
    text(padRight('Bayar', 22) + padLeft(bayarStr, 10));
    nl();
    push(ESC, 0x45, 0x01);
    text(padRight(sisaLabel, 22) + padLeft(sisaStr, 10));
    push(ESC, 0x45, 0x00);
    nl();

    // ── Garis ──
    text('--------------------------------');
    nl();

    // ── Footer ──
    push(ESC, 0x61, 0x01);  // center
    push(ESC, 0x45, 0x01);  // bold
    text('Terima Kasih!');
    nl();
    push(ESC, 0x45, 0x00);
    text('Barang/Jasa sudah dibeli');
    nl();
    text('tidak dapat ditukar');
    nl();

    // Feed & cut
    nl(3);
    push(GS, 0x56, 0x41, 0x10);  // Partial cut

    return new Uint8Array(bytes);
}

function fmt(n) {
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
function padRight(str, len) {
    return String(str).substring(0, len).padEnd(len, ' ');
}
function padLeft(str, len) {
    return String(str).substring(0, len).padStart(len, ' ');
}

/* ══ SEND TO PRINTER ══ */
async function printViaBluetooth() {
    if (!writeChar) {
        alert('Printer belum terhubung. Tap Bluetooth terlebih dahulu.');
        return;
    }
    const btn = document.getElementById('btnCetak');
    btn.disabled = true;
    btn.innerHTML = '⏳ Mengirim data...';
    try {
        const data   = buildEscPos();
        const CHUNK  = 512;  // kirim per 512 byte agar buffer tidak overflow
        for (let i = 0; i < data.length; i += CHUNK) {
            const chunk = data.slice(i, i + CHUNK);
            if (writeChar.properties.writeWithoutResponse) {
                await writeChar.writeValueWithoutResponse(chunk);
            } else {
                await writeChar.writeValue(chunk);
            }
            await new Promise(r => setTimeout(r, 60));  // jeda antar chunk
        }
        btn.innerHTML = '✓ Berhasil Dicetak!';
        setTimeout(() => {
            btn.innerHTML = '🖨 Cetak via Bluetooth';
            btn.disabled = false;
        }, 2500);
    } catch (e) {
        alert('Gagal mencetak: ' + e.message);
        btn.innerHTML = '🖨 Cetak via Bluetooth';
        btn.disabled = false;
        setBtStatus('error', 'Error saat cetak: ' + e.message);
    }
}
</script>
</body>
</html>