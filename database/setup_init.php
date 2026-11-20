<?php
declare(strict_types=1);

/**
 * database/setup_init.php — skrip penyiapan awal (Fase 1 + Fase 2).
 *
 * Tugas:
 *  1. Memasukkan data DUMMY penyakit & gejala dari database/seed.sql
 *     (dilewati bila tabelnya sudah berisi data).
 *  2. Membuat 1 akun Admin dan 1 akun Pakar pertama memakai
 *     password_hash() — hash maupun kata sandi asli TIDAK berada di
 *     berkas skema/seed (aturan schema.sql & AGENTS.md). Kata sandi
 *     dibuat acak dan hanya ditampilkan sekali di layar.
 *  3. (Fase 2, hanya dengan --with-cases) Memasukkan 20 kasus DUMMY
 *     dari database/seed_kasus.sql bila tabel kasus masih kosong.
 *
 * Cara memakai (dari root proyek):
 *     php database/setup_init.php               (master data saja)
 *     php database/setup_init.php --with-cases  (+ 20 kasus DUMMY)
 * Skrip ini berjalan di CLI maupun browser; setelah selesai, disarankan
 * dihapus dari server produksi.
 */

$isCli = PHP_SAPI === 'cli';

/* Bendera --with-cases (Fase 2): masukkan kasus DUMMY dari seed_kasus.sql. */
$withCases = $isCli
    ? in_array('--with-cases', $_SERVER['argv'] ?? [], true)
    : !empty($_GET['with_cases']);

/* Helper printer yang ramah CLI maupun browser. */
$print = static function (string $msg = '') use ($isCli): void {
    echo $isCli ? $msg . PHP_EOL : '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
};

$print('=== setup_init.php — penyiapan awal sistem_pakar_cbr (Fase 1) ===');

/* ---------- Koneksi PDO dari config/.env ---------- */
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers.php'; // untuk log_aktivitas()

try {
    $pdo = db();
} catch (Throwable $e) {
    $print('GAGAL terhubung ke database: ' . $e->getMessage());
    $print('Periksa MySQL/MariaDB berjalan, dan config/.env sudah diisi.');
    exit(1);
}
$print('Koneksi database: OK (' . db()->query('SELECT VERSION() AS v')->fetch()['v'] . ')');

/* ---------- 1. Impor seed DUMMY ---------- */
$print('');

/**
 * Jalankan satu berkas SQL (seed) dengan parser sederhana:
 * buang baris komentar '--', pecah per ';', lewati perintah USE.
 */
$jalankanSqlFile = static function (string $berkas, PDO $pdo): int {
    $sql = file_get_contents($berkas);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Berkas SQL tidak terbaca: ' . $berkas);
    }
    $sql = preg_replace('/^--[^\n]*$/m', '', $sql) ?? '';
    $dieksekusi = 0;
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) {
        if ($q === '' || preg_match('/^USE\s/si', $q)) {
            continue; // database sudah dibuka oleh config/.env
        }
        $pdo->exec($q);
        $dieksekusi++;
    }
    return $dieksekusi;
};

$jumlahPenyakit = (int) $pdo->query('SELECT COUNT(*) FROM penyakit')->fetchColumn();
$jumlahGejala   = (int) $pdo->query('SELECT COUNT(*) FROM gejala')->fetchColumn();

if ($jumlahPenyakit === 0 && $jumlahGejala === 0) {
    try {
        $dieksekusi = $jalankanSqlFile(__DIR__ . '/seed.sql', $pdo);
        $print("Seed DUMMY dimasukkan: {$dieksekusi} pernyataan SQL (penyakit + gejala).");
    } catch (Throwable $e) {
        $print('GAGAL memasukkan seed.sql: ' . $e->getMessage());
        exit(1);
    }
} else {
    $print("Seed master dilewati — tabel mengandung data (penyakit: {$jumlahPenyakit} baris, gejala: {$jumlahGejala} baris).");
}

/* ---------- 1b. Kasus DUMMY (Fase 2, hanya dengan --with-cases) ---------- */
if ($withCases) {
    $jumlahKasus = (int) $pdo->query('SELECT COUNT(*) FROM kasus')->fetchColumn();
    if ($jumlahKasus === 0) {
        try {
            $dieksekusi = $jalankanSqlFile(__DIR__ . '/seed_kasus.sql', $pdo);
            $totalKasus = (int) $pdo->query('SELECT COUNT(*) FROM kasus')->fetchColumn();
            $totalDetail = (int) $pdo->query('SELECT COUNT(*) FROM detail_kasus')->fetchColumn();
            $print("Seed kasus DUMMY dimasukkan: {$dieksekusi} pernyataan SQL ({$totalKasus} kasus, {$totalDetail} baris detail gejala).");
        } catch (Throwable $e) {
            $print('GAGAL memasukkan seed_kasus.sql: ' . $e->getMessage());
            exit(1);
        }
    } else {
        $print("Seed kasus dilewati — tabel kasus sudah berisi {$jumlahKasus} baris (idempoten).");
    }
} else {
    $print('Impor kasus DUMMY dilewati — jalankan ulang dengan --with-cases bila diperlukan.');
}

/* ---------- 2. Akun Admin & Pakar pertama ---------- */
$print('');
$buatAkun = static function (string $nama, string $email, string $role) use ($pdo, $print): void {
    $tabel = ['admin' => 'Admin', 'pakar' => 'Pakar'];
    $stmt = $pdo->prepare('SELECT id_user FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $ada = $stmt->fetch();

    if ($ada !== false) {
        $print("Akun {$tabel[$role]} ({$email}) sudah ada — dibuat tidak diganti. Gunakan kata sandi yang sudah Anda simpan.");
        return;
    }

    // Kata sandi acak 12 karakter (tanpa karakter mudah tertukar: l, I, O, 0, 1).
    $kumpulan = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $panjang = 12;
    $sandi = '';
    for ($i = 0; $i < $panjang; $i++) {
        $sandi .= $kumpulan[random_int(0, strlen($kumpulan) - 1)];
    }

    $id = (int) db()->lastInsertId();
    $ins = $pdo->prepare(
        'INSERT INTO users (nama, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    $ins->execute([$nama, $email, password_hash($sandi, PASSWORD_DEFAULT), $role]);
    $id = (int) $pdo->lastInsertId();

    log_aktivitas('setup_init', 'users', $id, null, ['email' => $email, 'role' => $role]);

    $print("Akun {$tabel[$role]} berhasil dibuat.");
    $print("  EMAIL      : {$email}");
    $print("  KATA SANDI : {$sandi}  <-- simpan sekarang, tidak akan dimunculkan lagi");
};

$buatAkun('Administrator Sistem', 'admin@sistem-pakar.test', 'admin');
$buatAkun('Pakar Pernapasan', 'pakar@sistem-pakar.test', 'pakar');

/* ---------- Selesai ---------- */
$print('');
$print('Selesai. Langkah berikutnya:');
$print('  1. Buka http://sistem-pakar-cbr.test (atau http://localhost/sistem-pakar-cbr).');
$print('  2. Login dengan salah satu akun di atas → panel pakar/admin Master Penyakit & Gejala.');
$print('  3. Simpan kredensial di tempat aman dan hapus setup_init.php dari server produksi.');
