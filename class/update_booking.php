<?php
/**
 * update_booking.php — Handler update operasional & pembayaran booking.
 *
 * ══════════════════════════════════════════════════════════════════════
 * PERBAIKAN UTAMA (Multi-Petugas):
 *
 * BUG LAMA:
 *   1. `if ($persen <= 0) continue;` — memblokir penyimpanan petugas
 *      yang tugasnya pada layanan tanpa komisi (persen = 0).
 *      Akibatnya, hanya petugas dengan komisi > 0 yang tersimpan,
 *      dan menyesatkan output laporan.
 *
 *   2. Akses array dengan kunci integer vs string — potensi miss jika
 *      PHP tidak auto-coerce pada versi tertentu.
 *
 * FIX:
 *   1. Petugas dengan persen = 0 tetap disimpan (nominal_komisi = 0).
 *      Yang di-skip hanya baris dengan id_employee kosong/0.
 *   2. Gunakan strval() saat membanding kunci array untuk konsistensi.
 *   3. Tambah sanitasi menyeluruh pada semua input POST.
 * ══════════════════════════════════════════════════════════════════════
 */

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/admin.php');
    exit;
}

include 'koneksi.php';

$id_booking = mysqli_real_escape_string($conn, trim($_POST['id_booking'] ?? ''));
$type       = trim($_POST['update_type'] ?? '');

if (empty($id_booking)) {
    header('Location: ../pages/admin.php?status=error');
    exit;
}

/* ════════════════════════════════════════════════════════════════════
   UPDATE OPERASIONAL (Kelola Layanan + Petugas + Komisi)
════════════════════════════════════════════════════════════════════ */
if ($type === 'operasional') {

    $status_kerja    = mysqli_real_escape_string($conn, $_POST['status_kerja'] ?? 'menunggu');
    $total_biaya_baru = 0;

    // Ambil array id_detail dari form
    $id_details = (isset($_POST['id_detail']) && is_array($_POST['id_detail']))
                  ? $_POST['id_detail']
                  : [];

    foreach ($id_details as $raw_id_detail) {

        $id_detail = (int)$raw_id_detail;
        if ($id_detail <= 0) continue;

        /* ── 1. Harga layanan ── */
        // Cari di POST dengan kunci string maupun integer (PHP biasanya auto-coerce,
        // tapi kita eksplisit demi kompatibilitas penuh)
        $harga_raw = 0;
        if (isset($_POST['harga_layanan'][$id_detail])) {
            $harga_raw = $_POST['harga_layanan'][$id_detail];
        } elseif (isset($_POST['harga_layanan'][(string)$id_detail])) {
            $harga_raw = $_POST['harga_layanan'][(string)$id_detail];
        }
        $harga = max(0, (float)$harga_raw);
        $total_biaya_baru += $harga;

        /* ── 2. Ambil array petugas & komisi untuk detail ini ── */
        $raw_employees = [];
        $raw_komisis   = [];

        // Coba dengan kunci integer dulu, lalu string (keduanya merujuk ke data yang sama di PHP)
        foreach ([$id_detail, (string)$id_detail] as $key) {
            if (!empty($raw_employees)) break;
            if (isset($_POST['id_employee'][$key]) && is_array($_POST['id_employee'][$key])) {
                $raw_employees = $_POST['id_employee'][$key];
            }
        }
        foreach ([$id_detail, (string)$id_detail] as $key) {
            if (!empty($raw_komisis)) break;
            if (isset($_POST['komisi_persen'][$key]) && is_array($_POST['komisi_persen'][$key])) {
                $raw_komisis = $_POST['komisi_persen'][$key];
            }
        }

        /* ── 3. Bangun daftar petugas valid ── */
        /*
         * FIX UTAMA:
         * LAMA: `if ($persen <= 0) continue;`
         *   → Petugas yang mengerjakan layanan tanpa komisi (persen=0)
         *     tidak tersimpan sama sekali. Ini bug!
         *
         * BARU: Skip HANYA jika id_employee tidak valid (0 atau kosong).
         *       Persen boleh 0 — nominal_komisi akan 0, tapi row tetap tersimpan
         *       sehingga laporan & tracking petugas tetap akurat.
         */
        $valid_employees   = [];
        $first_employee_id = null;

        foreach ($raw_employees as $idx => $id_emp_raw) {

            $id_emp = (int)$id_emp_raw;

            // Skip jika tidak ada employee dipilih
            if ($id_emp <= 0) continue;

            // Komisi persen (boleh 0)
            $persen = isset($raw_komisis[$idx]) ? (float)$raw_komisis[$idx] : 0;
            $persen = max(0, $persen);

            // Nominal komisi (0 jika persen = 0)
            $nominal_komisi = ($persen > 0)
                              ? (int)round($harga * ($persen / 100))
                              : 0;

            $valid_employees[] = [
                'id_emp'         => $id_emp,
                'persen'         => $persen,
                'nominal_komisi' => $nominal_komisi,
            ];

            if ($first_employee_id === null) {
                $first_employee_id = $id_emp;
            }
        }

        /* ── 4. Update booking_details ── */
        $val_emp = ($first_employee_id !== null)
                   ? "'" . (int)$first_employee_id . "'"
                   : "NULL";

        mysqli_query($conn, "
            UPDATE booking_details
            SET    id_employee = $val_emp,
                   subtotal    = '$harga'
            WHERE  id_detail   = '$id_detail'
        ");

        /* ── 5. Hapus komisi lama, insert ulang SEMUA petugas valid ── */
        mysqli_query($conn, "DELETE FROM booking_komisi WHERE id_detail = '$id_detail'");

        foreach ($valid_employees as $ve) {
            $ie   = (int)$ve['id_emp'];
            $ip   = mysqli_real_escape_string($conn, (string)$ve['persen']);
            $inm  = (int)$ve['nominal_komisi'];

            mysqli_query($conn, "
                INSERT INTO booking_komisi
                    (id_booking, id_detail, id_employee, persen_komisi, nominal_komisi)
                VALUES
                    ('$id_booking', '$id_detail', '$ie', '$ip', '$inm')
            ");
        }

    } // end foreach id_details

    /* ── 6. Update status & total di tabel bookings ── */
    mysqli_query($conn, "
        UPDATE bookings
        SET    status_kerja = '$status_kerja',
               total_biaya  = '$total_biaya_baru'
        WHERE  id_booking   = '$id_booking'
    ");

/* ════════════════════════════════════════════════════════════════════
   UPDATE PEMBAYARAN (Kasir)
════════════════════════════════════════════════════════════════════ */
} elseif ($type === 'pembayaran') {

    $status_pembayaran = mysqli_real_escape_string($conn, $_POST['status_pembayaran'] ?? 'pending');
    $cash              = max(0, (float)($_POST['bayar_cash']     ?? 0));
    $transfer          = max(0, (float)($_POST['bayar_transfer'] ?? 0));
    $total_masuk       = $cash + $transfer;

    mysqli_query($conn, "
        UPDATE bookings
        SET    status_pembayaran = '$status_pembayaran',
               bayar_cash        = '$cash',
               bayar_transfer    = '$transfer',
               jumlah_terbayar   = '$total_masuk'
        WHERE  id_booking        = '$id_booking'
    ");
}

header('Location: ../pages/admin.php?status=success');
exit;