<?php
declare(strict_types=1);

/**
 * config/database.php
 *
 * Memuat konfigurasi dari config/.env lalu menyediakan koneksi PDO tunggal.
 * Kredensial database HANYA berada di config/.env (tidak di-commit),
 * lihat config/.env.example sebagai contoh.
 *
 * Aturan AGENTS.md: semua akses database memakai PDO prepared statements.
 */

if (!function_exists('env_load')) {

    /**
     * Baca seluruh pasangan KEY=VALUE dari berkas .env (di-cache per berkas).
     * Sengaja sederhana (tanpa pustaka tambahan): satu variabel per baris.
     *
     * @return array<string,string>
     */
    function env_load(string $path): array
    {
        static $cache = [];
        if (isset($cache[$path])) {
            return $cache[$path];
        }
        $vars = [];
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Buang kutip pembungkus bila ada: NAMA="nilai"
                $len = strlen($val);
                if ($len >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[$len - 1] === $val[0]) {
                    $val = substr($val, 1, -1);
                }
                $vars[$key] = $val;
            }
        }
        return $cache[$path] = $vars;
    }
}

if (!function_exists('env')) {

    /** Ambil satu variabel konfigurasi dari config/.env. */
    function env(string $key, ?string $default = null): ?string
    {
        $vars = env_load(__DIR__ . '/.env');
        $val = $vars[$key] ?? null;
        return ($val !== null && $val !== '') ? $val : $default;
    }
}

if (!function_exists('db')) {

    /**
     * Koneksi PDO tunggal (lazy) ke database sistem_pakar_cbr.
     * Semua model dan helper memakai fungsi ini.
     */
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $envPath = __DIR__ . '/.env';
        if (!is_file($envPath)) {
            throw new RuntimeException(
                'Berkas config/.env belum ada. Salin config/.env.example menjadi config/.env lalu isi kredensial database.'
            );
        }

        $env = env_load($envPath);
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_NAME'] ?? 'sistem_pakar_cbr'
        );

        try {
            $pdo = new PDO($dsn, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements asli, anti SQL injection
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Gagal terhubung ke database: ' . $e->getMessage(), 0, $e);
        }

        return $pdo;
    }
}
