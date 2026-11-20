<?php
declare(strict_types=1);

/**
 * tests/bootstrap.php — bootstrap untuk PHPUnit.
 *
 * Menyiapkan autoloader sederhana (Controller, Model, Service)
 * sehingga kelas bisa dipakai dalam uji tanpa antarmuka web.
 *
 * Catatan AGENTS.md: uji PHPUnit yang WAJIB adalah untuk CBREngine
 * (di app/services/, Fase 3), bukan uji antarmuka. File uji CBR
 * akan ditambahkan pada Fase 3.
 *
 * Konvensi pengujian: framework diuji dengan data DUMMY dari
 * database/seed.sql, tidak pernah data medis sungguhan.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/config/database.php';
require BASE_PATH . '/app/helpers.php';

spl_autoload_register(static function (string $class): void {
    foreach (['controllers', 'models', 'services'] as $dir) {
        $file = BASE_PATH . '/app/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
