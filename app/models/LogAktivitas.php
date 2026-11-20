<?php
declare(strict_types=1);

/**
 * app/models/LogAktivitas.php — pembacaan audit trail (tabel log_aktivitas).
 * Penulisan log tetap melalui helper log_aktivitas() di app/helpers.php.
 */
class LogAktivitas
{
    /** Log terbaru + nama pengguna (bila ada). */
    public function terbaru(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT l.id_log, l.aksi, l.tabel, l.id_rekaman, l.data_lama, l.data_baru,
                       l.ip_address, l.created_at, u.nama AS nama_user, u.role
                FROM log_aktivitas l
                LEFT JOIN users u ON u.id_user = l.id_user
                ORDER BY l.id_log DESC
                LIMIT ' . $limit;
        return db()->query($sql)->fetchAll();
    }

    /** Jumlah seluruh baris log (untuk keterangan tabel). */
    public function jumlah(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM log_aktivitas')->fetchColumn();
    }
}
