<?php
declare(strict_types=1);

/**
 * app/controllers/AuthController.php
 *
 * Autentikasi lengkap Fase 1:
 *  - Registrasi pasien (email unik, kata sandi minimal 8 karakter, password_hash).
 *  - Login dengan rate limiting: percobaan gagal dicatat sebagai aksi
 *    'login_gagal' di tabel log_aktivitas (per IP, jendela LOGIN_LOCKOUT_MINUTES
 *    menit, batas LOGIN_MAX_ATTEMPTS dari config/.env) — tanpa tabel baru.
 *  - session_regenerate_id(true) saat login & logout (AGENTS.md).
 *  - Logout dengan destroy sesi + cookie.
 *  - Mode tamu: session_token 64 karakter acak disimpan di sesi;
 *    token ini nantinya dipakai tabel diagnosa pada Fase 3.
 */
class AuthController extends Controller
{
    protected const ACTIONS = ['login', 'register', 'guest', 'logout'];

    private User $users;

    public function __construct()
    {
        $this->users = new User();
    }

    /* ------------------------------------------------------------
       LOGIN
       ------------------------------------------------------------ */
    public function loginAction(): void
    {
        if (is_logged_in()) {
            flash('info', 'Anda sudah login.');
            redirect('index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();
            $email = trim((string) ($_POST['email'] ?? ''));
            $pass  = (string) ($_POST['password'] ?? '');
            $old   = ['email' => $email];

            [$jumlahGagal, $sisaMenit] = $this->infoRateLimit();

            // Rate limiting: kunci sementara bila terlalu banyak gagal per IP.
            if ($jumlahGagal >= max(1, (int) env('LOGIN_MAX_ATTEMPTS', '5'))) {
                log_aktivitas('login_ditolak', null, null, null, ['email' => $email]);
                $this->view('auth/login', [
                    'pageTitle'     => 'Login',
                    'errors'        => [],
                    'old'           => $old,
                    'lockoutSisa'   => max(1, $sisaMenit),
                    'jumlahGagal'   => $jumlahGagal,
                ]);
                return;
            }

            $user = $email !== '' ? $this->users->findByEmail($email) : null;

            if ($user === null || !password_verify($pass, (string) $user['password_hash'])) {
                log_aktivitas('login_gagal', 'users', null, null, ['email' => $email]);
                [$jumlahGagal, $sisaMenit] = $this->infoRateLimit();
                $errors = ['Email atau kata sandi salah.'];
                $lockoutSisa = null;
                if ($jumlahGagal >= max(1, (int) env('LOGIN_MAX_ATTEMPTS', '5'))) {
                    $errors = ['Terlalu banyak percobaan gagal. Login dikunci sementara.'];
                    $lockoutSisa = max(1, $sisaMenit);
                }
                $this->view('auth/login', [
                    'pageTitle'    => 'Login',
                    'errors'       => $errors,
                    'old'          => $old,
                    'lockoutSisa'  => $lockoutSisa,
                    'jumlahGagal'  => $jumlahGagal,
                ]);
                return;
            }

            if ((int) $user['is_active'] !== 1) {
                log_aktivitas('login_ditolak', 'users', (int) $user['id_user']);
                $this->view('auth/login', [
                    'pageTitle'   => 'Login',
                    'errors'      => ['Akun Anda nonaktif. Hubungi admin.'],
                    'old'         => $old,
                    'lockoutSisa' => null,
                    'jumlahGagal' => $jumlahGagal,
                ]);
                return;
            }

            // Sukses: regenerasi ID sesi (cegah session fixation), lalu simpan data singkat.
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id_user' => (int) $user['id_user'],
                'nama'    => $user['nama'],
                'email'   => $user['email'],
                'role'    => $user['role'],
            ];
            log_aktivitas('login_sukses', 'users', (int) $user['id_user']);
            flash('sukses', 'Selamat datang, ' . $user['nama'] . '.');

            // Admin/pakar langsung ke dashboard panel.
            if (in_array($user['role'], ['pakar', 'admin'], true)) {
                redirect('dashboard');
            }
            redirect('index');
        }

        // GET: tampilkan formulir login + info percobaan gagal bila ada.
        [$jumlahGagal, $sisaMenit] = $this->infoRateLimit();
        $this->view('auth/login', [
            'pageTitle'    => 'Login',
            'errors'       => [],
            'old'          => [],
            'lockoutSisa'  => $jumlahGagal >= max(1, (int) env('LOGIN_MAX_ATTEMPTS', '5')) ? max(1, $sisaMenit) : null,
            'jumlahGagal'  => $jumlahGagal,
        ]);
    }

    /**
     * Baca log_aktivitas: jumlah login_gagal per IP dalam jendela waktu,
     * plus perkiraan menit tersisa sampai kunci berakhir.
     *
     * @return array{0:int,1:int} [jumlah percobaan gagal, sisa menit]
     */
    private function infoRateLimit(): array
    {
        $menit = max(1, (int) env('LOGIN_LOCKOUT_MINUTES', '15'));
        $ip    = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return [0, 0];
        }
        try {
            $stmt = db()->prepare(
                "SELECT COUNT(*) AS jumlah, MAX(created_at) AS terakhir
                 FROM log_aktivitas
                 WHERE aksi = 'login_gagal' AND ip_address = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
            );
            $stmt->execute([$ip, $menit]);
            $row = $stmt->fetch();
            $jumlah = (int) ($row['jumlah'] ?? 0);
            $sisa = 0;
            if ($row !== false && $row['terakhir'] !== null) {
                $sisa = $menit - (int) ((strtotime((string) $row['terakhir']) - time()) / -60);
            }
            return [$jumlah, max(0, $sisa)];
        } catch (Throwable $e) {
            error_log('[sistem-pakar-cbr] infoRateLimit gagal: ' . $e->getMessage());
            return [0, 0];
        }
    }

    /* ------------------------------------------------------------
       REGISTRASI PASIEN
       ------------------------------------------------------------ */
    public function registerAction(): void
    {
        if (is_logged_in()) {
            redirect('index');
        }

        $errors = [];
        $old = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();

            $old = [
                'nama'          => trim((string) ($_POST['nama'] ?? '')),
                'email'         => trim((string) ($_POST['email'] ?? '')),
                'tanggal_lahir' => (string) ($_POST['tanggal_lahir'] ?? ''),
                'jenis_kelamin' => (string) ($_POST['jenis_kelamin'] ?? ''),
            ];
            $password   = (string) ($_POST['password'] ?? '');
            $konfirmasi = (string) ($_POST['password_confirm'] ?? '');

            if ($old['nama'] === '' || mb_strlen($old['nama']) > 100) {
                $errors[] = 'Nama wajib diisi (maksimal 100 karakter).';
            }
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) {
                $errors[] = 'Format email tidak valid (maksimal 120 karakter).';
            } elseif ($this->users->emailExists($old['email'])) {
                $errors[] = 'Email sudah terdaftar. Gunakan email lain atau login.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Kata sandi minimal 8 karakter.';
            }
            if ($password !== $konfirmasi) {
                $errors[] = 'Konfirmasi kata sandi tidak sama.';
            }
            if ($old['tanggal_lahir'] !== '') {
                $lahir = DateTime::createFromFormat('Y-m-d', $old['tanggal_lahir']);
                if (!$lahir || $lahir->format('Y-m-d') !== $old['tanggal_lahir'] || $lahir > new DateTime('today')) {
                    $errors[] = 'Tanggal lahir tidak valid.';
                }
            }
            if (!in_array($old['jenis_kelamin'], ['L', 'P'], true)) {
                $errors[] = 'Jenis kelamin wajib dipilih.';
            }

            if ($errors === []) {
                $id = $this->users->create([
                    'nama'          => $old['nama'],
                    'email'         => $old['email'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => 'pasien',
                    'tanggal_lahir' => $old['tanggal_lahir'] !== '' ? $old['tanggal_lahir'] : null,
                    'jenis_kelamin' => $old['jenis_kelamin'],
                    'is_active'     => 1,
                ]);
                log_aktivitas('registrasi', 'users', $id, null, ['email' => $old['email'], 'role' => 'pasien']);

                // Langsung login agar pengguna tidak mengisi ulang.
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id_user' => $id,
                    'nama'    => $old['nama'],
                    'email'   => $old['email'],
                    'role'    => 'pasien',
                ];
                flash('sukses', 'Registrasi berhasil. Selamat datang, ' . $old['nama'] . '!');
                redirect('index');
            }
        }

        $this->view('auth/register', [
            'pageTitle' => 'Daftar Akun',
            'errors'    => $errors,
            'old'       => $old,
        ]);
    }

    /* ------------------------------------------------------------
       MODE TAMU (session_token)
       ------------------------------------------------------------ */
    public function guestAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();
            if (!empty($_SESSION['session_token'])) {
                flash('info', 'Anda sudah berada dalam mode tamu.');
                redirect('index');
            }
            // Token 64 karakter acak; dipakai tabel diagnosa.session_token (Fase 3).
            $_SESSION['session_token'] = bin2hex(random_bytes(32));
            log_aktivitas('mode_tamu', null, null, null, ['panjang_token' => 64]);
            flash('info', 'Mode tamu aktif. Silakan isi formulir gejala; simpan tautan hasil bila ingin membukanya lagi.');
            redirect('diagnosa');
        }
        redirect('index');
    }

    /* ------------------------------------------------------------
       LOGOUT
       ------------------------------------------------------------ */
    public function logoutAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();
            $nama = $_SESSION['user']['nama'] ?? '';

            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires'  => time() - 42000,
                    'path'     => $p['path'],
                    'secure'   => $p['secure'],
                    'httponly' => true,
                    'samesite' => $p['samesite'],
                ]);
            }
            session_destroy();

            // Mulai ulang sesi bersih untuk flash message.
            session_start();
            session_regenerate_id(true);
            flash('sukses', 'Anda telah keluar' . ($nama !== '' ? ', ' . $nama : '') . '. Sampai jumpa!');
            log_aktivitas('logout');
            redirect('index');
        }
        redirect('index');
    }
}
