<?php
declare(strict_types=1);

/**
 * app/models/Penyakit.php — CRUD master penyakit (tabel penyakit).
 * Semua query memakai PDO prepared statements.
 */
class Penyakit
{
    public const URGENSI = ['ringan', 'waspada', 'segera', 'darurat'];

    public const LABEL_URGENSI = [
        'ringan'   => 'Ringan',
        'waspada'  => 'Waspada',
        'segera'   => 'Segera ke dokter',
        'darurat'  => 'Darurat',
    ];

    /** Semua penyakit + hitungan kasus terkait (untuk list & peringatan soft delete). */
    public function list(): array
    {
        $sql = 'SELECT p.*,
                    (SELECT COUNT(*) FROM kasus k WHERE k.id_penyakit = p.id_penyakit) AS jumlah_kasus,
                    (SELECT COUNT(*) FROM diagnosa d WHERE d.id_penyakit_hasil = p.id_penyakit)
                      + (SELECT COUNT(*) FROM hasil_diagnosa hd WHERE hd.id_penyakit = p.id_penyakit)
                      + (SELECT COUNT(*) FROM validasi_kasus vk WHERE vk.id_penyakit_final = p.id_penyakit)
                    AS jumlah_referensi
                FROM penyakit p
                ORDER BY p.kode_penyakit ASC';
        return db()->query($sql)->fetchAll();
    }

    public function jumlahAktif(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM penyakit WHERE is_active = 1')->fetchColumn();
    }

    /** Hanya penyakit aktif (untuk dropdown pilihan solusi kasus). */
    public function listAktif(): array
    {
        return db()->query(
            'SELECT id_penyakit, kode_penyakit, nama_penyakit FROM penyakit
             WHERE is_active = 1 ORDER BY kode_penyakit ASC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM penyakit WHERE id_penyakit = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Cari berdasar kode (tidak peka huruf besar/kecil), bisa mengecualikan satu id. */
    public function findByKode(string $kode, int $kecualiId = 0): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM penyakit WHERE UPPER(kode_penyakit) = UPPER(?) AND id_penyakit <> ? LIMIT 1'
        );
        $stmt->execute([$kode, $kecualiId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $d): int
    {
        $stmt = db()->prepare(
            'INSERT INTO penyakit (kode_penyakit, nama_penyakit, deskripsi, penyebab, solusi, pencegahan, tingkat_urgensi, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['kode_penyakit'], $d['nama_penyakit'], $d['deskripsi'], $d['penyebab'],
            $d['solusi'], $d['pencegahan'], $d['tingkat_urgensi'], $d['is_active'],
        ]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $stmt = db()->prepare(
            'UPDATE penyakit
             SET kode_penyakit = ?, nama_penyakit = ?, deskripsi = ?, penyebab = ?,
                 solusi = ?, pencegahan = ?, tingkat_urgensi = ?, is_active = ?
             WHERE id_penyakit = ?'
        );
        $stmt->execute([
            $d['kode_penyakit'], $d['nama_penyakit'], $d['deskripsi'], $d['penyebab'],
            $d['solusi'], $d['pencegahan'], $d['tingkat_urgensi'], $d['is_active'], $id,
        ]);
    }

    /** Soft delete / aktifkan kembali: hanya mengubah is_active. */
    public function setAktif(int $id, bool $aktif): void
    {
        $stmt = db()->prepare('UPDATE penyakit SET is_active = ? WHERE id_penyakit = ?');
        $stmt->execute([$aktif ? 1 : 0, $id]);
    }

    /** Berapa kasus (semua status) yang memakai penyakit ini. */
    public function jumlahKasus(int $id): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM kasus WHERE id_penyakit = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Berapa rekaman riwayat lain yang merujuk penyakit ini
     * (diagnosa, hasil_diagnosa, validasi_kasus) — blokirasi hapus permanen.
     */
    public function jumlahReferensiRiwayat(int $id): int
    {
        $stmt = db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM diagnosa        WHERE id_penyakit_hasil = ?) +
                (SELECT COUNT(*) FROM hasil_diagnosa  WHERE id_penyakit       = ?) +
                (SELECT COUNT(*) FROM validasi_kasus  WHERE id_penyakit_final = ?)
             AS jumlah'
        );
        $stmt->execute([$id, $id, $id]);
        return (int) $stmt->fetchColumn();
    }

    /** Hapus permanen. Panggil HANYA setelah memastikan jumlah kasus & referensi = 0. */
    public function deletePermanen(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM penyakit WHERE id_penyakit = ?');
        $stmt->execute([$id]);
    }
}
