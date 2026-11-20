<?php
declare(strict_types=1);

/**
 * public/index.php — front controller (satu-satunya titik masuk web).
 *
 * Request dari .htaccess (vhost sistem-pakar.test maupun
 * localhost/sistem-pakar-cbr) dipetakan ke controller di app/controllers/
 * dengan skema ?page=X&action=Y, tanpa framework.
 */

define('BASE_PATH', dirname(__DIR__));
date_default_timezone_set('Asia/Jakarta');

error_reporting(E_ALL);
ini_set('display_errors', '1'); // ditumpuk di bawah bila APP_ENV=production

require BASE_PATH . '/config/database.php';
require BASE_PATH . '/app/helpers.php';

if (env('APP_ENV', 'development') === 'production') {
    ini_set('display_errors', '0');
}

/* ------------------------------------------------------------
   Sesi aman: HttpOnly, SameSite, use_strict_mode.
   Regenerasi ID sesi dilakukan saat login/logout (AuthController).
   ------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    session_name('SPCBR_SESS');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

send_security_headers();

/* ------------------------------------------------------------
   Autoloader sederhana (tanpa Composer): mencari kelas di
   app/controllers, app/models, app/services.
   ------------------------------------------------------------ */
spl_autoload_register(static function (string $class): void {
    foreach (['controllers', 'models', 'services'] as $dir) {
        $file = BASE_PATH . '/app/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

/* ------------------------------------------------------------
   Router: halaman → controller + aksi.
   ------------------------------------------------------------ */
$map = [
    'index'    => ['class' => HomeController::class,     'action' => 'index'],
    'login'    => ['class' => AuthController::class,     'action' => 'login'],
    'register' => ['class' => AuthController::class,     'action' => 'register'],
    'guest'    => ['class' => AuthController::class,     'action' => 'guest'],
    'logout'   => ['class' => AuthController::class,     'action' => 'logout'],
    'penyakit' => ['class' => PenyakitController::class, 'action' => (string) ($_GET['action'] ?? 'index')],
    'gejala'   => ['class' => GejalaController::class,   'action' => (string) ($_GET['action'] ?? 'index')],
    'kasus'    => ['class' => KasusController::class,    'action' => (string) ($_GET['action'] ?? 'index')],
    'bobot'    => ['class' => BobotController::class,    'action' => (string) ($_GET['action'] ?? 'index')],
    'diagnosa'  => ['class' => DiagnosaController::class,  'action' => (string) ($_GET['action'] ?? 'form')],
    'dashboard' => ['class' => DashboardController::class, 'action' => 'index'],
    'validasi'  => ['class' => ValidasiController::class,  'action' => (string) ($_GET['action'] ?? 'index')],
    'riwayat'   => ['class' => RiwayatController::class,   'action' => (string) ($_GET['action'] ?? 'index')],
    'pengaturan' => ['class' => PengaturanController::class, 'action' => (string) ($_GET['action'] ?? 'index')],
    'users'      => ['class' => UserController::class,      'action' => (string) ($_GET['action'] ?? 'index')],
    'evaluasi'   => ['class' => EvaluasiController::class,  'action' => (string) ($_GET['action'] ?? 'index')],
];

$page = (string) ($_GET['page'] ?? 'index');
if (!isset($map[$page])) {
    respond_not_found();
}

$action = $map[$page]['action'];
if (!preg_match('/^[a-z]{1,15}$/', $action)) {
    respond_not_found();
}

try {
    (new $map[$page]['class']())->dispatch($action);
} catch (Throwable $e) {
    http_response_code(500);
    $detail = env('APP_ENV', 'development') === 'production'
        ? 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.'
        : $e->getMessage();
    require BASE_PATH . '/app/views/errors/500.php';
    exit;
}
