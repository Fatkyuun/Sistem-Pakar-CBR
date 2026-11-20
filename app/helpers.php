<?php
declare(strict_types=1);

/**
 * app/helpers.php — helper keamanan & utilitas transversal.
 *
 * Memuat: escaping output, token CSRF, flash message, pengaturan peran
 * (RBAC), pencatatan log_aktivitas, header keamanan, dan utilitas URL.
 * Logika bisnis CBR TIDAK berada di sini (lihat app/services/, Fase 3).
 *
 * Kebiasaan aman sesuai AGENTS.md:
 * - Semua output ke HTML melewati e() (htmlspecialchars).
 * - Semua formulir POST memakai csrf_field() dan dicek csrf_check().
 * - Halaman admin memanggil require_role(['pakar','admin']) di controller.
 * - Setiap aksi penting dicatat ke log_aktivitas.
 */

/* ============================================================
   URL & redirection
   ============================================================ */

/**
 * Akar URL aplikasi tanpa '/public', otomatis mengikuti skrip aktif,
 * sehingga identik untuk:
 *   http://sistem-pakar-cbr.test            → ''
 *   http://localhost/sistem-pakar-cbr       → '/sistem-pakar-cbr'
 * Bisa ditimpa dengan APP_URL di config/.env bila deploy di domain khusus.
 */
function base_root(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }
    $appUrl = env('APP_URL', '');
    if ($appUrl !== '') {
        return $root = rtrim($appUrl, '/');
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -strlen('/public'));
    }
    return $root = $dir;
}

/** Bangun URL aplikasi: url('penyakit', ['action' => 'save', 'id' => 3]). */
function url(string $page = 'index', array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return base_root() . '/?' . http_build_query($params);
}

/** URL aset statis di public/ (css, js, img) — selalu via /public agar
 *  MIME type benar tanpa bergantung pada aturan rewrite di .htaccess. */
function asset_url(string $path = ''): string
{
    return base_root() . '/public/' . ltrim($path, '/');
}

/** Dorong browser ke satu halaman aplikasi lalu hentikan eksekusi. */
function redirect(string $page = 'index', array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

/* ============================================================
   Output & CSRF
   ============================================================ */

/** Escape output ke HTML (anti XSS). WAJIB dipakai untuk semua output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ambil (atau buat sekali per sesi) token CSRF. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Tulis input hidden token CSRF untuk formulir POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validasi token CSRF pada setiap POST. Berhenti (403) bila tidak cocok,
 * percobaan dicatat ke log_aktivitas.
 */
function csrf_check(): void
{
    $sent = (string) ($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        log_aktivitas('csrf_gagal');
        http_response_code(403);
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>403</title></head>'
            . '<body style="font-family:system-ui;padding:2rem"><h2>403 — Token keamanan tidak valid</h2>'
            . '<p>Sesi Anda mungkin sudah berakhir. Kembali ke <a href="' . e(url('index')) . '">beranda</a>, '
            . 'muat ulang halaman, lalu coba lagi.</p></body></html>';
        exit;
    }
}

/* ============================================================
   Flash message (dipindah lewat sesi, ditampilkan sebagai SweetAlert)
   ============================================================ */

/** Simpan pesan sekali-tampil. $tipe: sukses|galat|peringatan|info. */
function flash(string $tipe, string $pesan): void
{
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];
}

/** Ambil semua pesan flash dan bersihkan sekue sesi. */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ============================================================
   Sesi & RBAC (Role-Based Access Control)
   ============================================================ */

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id_user']);
}

/** Data pengguna yang login: id_user, nama, email, role. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Token identitas tamu (64 karakter hex) untuk diagnosa tanpa akun.
 * Token dibuat sekali per sesi lalu dipakai sebagai session_token pada
 * tabel diagnosa, sehingga tamu tetap dapat membuka hasil miliknya.
 */
function guest_token(): string
{
    if (empty($_SESSION['session_token'])) {
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['session_token'];
}

/** Hentikan dan lempar ke halaman login untuk pengunjung anonim. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('info', 'Silakan login terlebih dahulu.');
        redirect('login');
    }
}

/** Wajib peran tertentu (mis. ['pakar','admin']) untuk halaman admin. */
function require_role(array $roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        log_aktivitas('akses_ditolak', null, null, null, [
            'peran_memiliki' => $user['role'],
            'peran_butuh'    => implode(',', $roles),
        ]);
        respond_403();
    }
}

function respond_403(): never
{
    http_response_code(403);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>403</title></head>'
        . '<body style="font-family:system-ui;padding:2rem"><h2>403 — Akses ditolak</h2>'
        . '<p>Halaman ini hanya untuk peran Admin/Pakar. '
        . '<a href="' . e(url('index')) . '">Kembali ke beranda</a></p></body></html>';
    exit;
}

function respond_not_found(): never
{
    http_response_code(404);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>404</title></head>'
        . '<body style="font-family:system-ui;padding:2rem"><h2>404 — Halaman tidak ditemukan</h2>'
        . '<p><a href="' . e(url('index')) . '">Kembali ke beranda</a></p></body></html>';
    exit;
}

/* ============================================================
   Pengaturan sistem (tabel pengaturan) — ambang batas CBR, dsb.
   ============================================================ */

/** Ambil satu nilai pengaturan dari tabel pengaturan (jangan di-hardcode). */
function pengaturan(string $kunci, ?string $default = null): ?string
{
    try {
        $stmt = db()->prepare('SELECT nilai FROM pengaturan WHERE kunci = ?');
        $stmt->execute([$kunci]);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : (string) $val;
    } catch (Throwable $e) {
        return $default;
    }
}

/* ============================================================
   Penyajian gejala untuk pasien awam (tanpa istilah teknis)
   ============================================================ */

/**
 * Bersihkan deskripsi gejala dari keterangan teknis bernilai 0-3
 * (mis. "(0 tidak, 1 ringan, 2 sedang, 3 berat)") dan kalimat
 * "TERMASUK GEJALA DARURAT" (sudah diganti badge tersendiri).
 */
function deskripsi_awam(string $teks): string
{
    $teks = preg_replace('/\s*\((?:[^()]*(?:0\s*(?:tidak|hingga)[^()]*))\)/i', '', $teks) ?? $teks;
    $teks = preg_replace('/\s*(?:Gejala berat\s*)?TERMASUK (?:GEJALA )?DARURAT\.?/i', '', $teks) ?? $teks;
    $teks = preg_replace('/\s{2,}/', ' ', $teks) ?? $teks;
    return trim($teks, " \t\n\r\0\x0B.");
}

/** Label tingkat/pilihan gejala dalam bahasa pasien. */
function label_nilai_gejala(string $tipe, int $nilai): string
{
    if ($tipe === 'biner') {
        return $nilai > 0 ? 'Ya' : 'Tidak';
    }
    if ($tipe === 'durasi') {
        return [
            0 => 'Tidak mengalami',
            1 => '< 3 hari',
            2 => '3–7 hari',
            3 => '> 1 minggu',
        ][$nilai] ?? '—';
    }
    return [
        0 => 'Tidak ada',
        1 => 'Ringan',
        2 => 'Sedang',
        3 => 'Berat',
    ][$nilai] ?? '—';
}

/** Kelas badge Bootstrap untuk nilai gejala (ringan/sedang/berat/ya). */
function kelas_nilai_gejala(string $tipe, int $nilai): string
{
    if ($nilai <= 0) {
        return 'text-bg-light border text-secondary';
    }
    if ($tipe === 'biner') {
        return 'text-bg-primary';
    }
    return [
        1 => 'text-bg-info',
        2 => 'text-bg-warning',
        3 => 'text-bg-danger',
    ][$nilai] ?? 'text-bg-secondary';
}

/** Sebutan status merokok dalam bahasa sehari-hari. */
function label_merokok(?string $kode): string
{
    return [
        'tidak'  => 'Tidak merokok',
        'mantan' => 'Mantan perokok',
        'aktif'  => 'Perokok aktif',
    ][(string) $kode] ?? 'Tidak diisi';
}

/* ============================================================
   Log aktivitas (audit trail ke tabel log_aktivitas)
   ============================================================ */

/**
 * Catat satu aksi ke tabel log_aktivitas. Dipakai untuk login,
 * CRUD master data, validasi, dsb. Kegagalan log tidak memutus
 * alur utama (dicatat ke error_log perangkat).
 */
function log_aktivitas(
    string $aksi,
    ?string $tabel = null,
    ?int $idRekaman = null,
    ?array $dataLama = null,
    ?array $dataBaru = null
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO log_aktivitas (id_user, aksi, tabel, id_rekaman, data_lama, data_baru, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $user = $_SESSION['user'] ?? null; // aman dipanggil tanpa sesi (mis. CLI)
        $stmt->execute([
            $user['id_user'] ?? null,
            $aksi,
            $tabel,
            $idRekaman,
            $dataLama !== null ? json_encode($dataLama, JSON_UNESCAPED_UNICODE) : null,
            $dataBaru !== null ? json_encode($dataBaru, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('[sistem-pakar-cbr] log_aktivitas gagal: ' . $e->getMessage());
    }
}

/* ============================================================
   Header keamanan HTTP
   ============================================================ */

/** Kirim header keamanan dasar (CSP mengizinkan CDN pustaka antarmuka). */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://fonts.googleapis.com; "
        . "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdn.datatables.net; "
        . "img-src 'self' data: https://cdn.jsdelivr.net https://cdn.datatables.net; "
        . "connect-src 'self' https://cdn.jsdelivr.net https://cdn.datatables.net https://code.jquery.com");
}
