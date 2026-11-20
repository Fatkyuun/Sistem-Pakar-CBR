<?php
declare(strict_types=1);

/**
 * app/models/Gejala.php — CRUD master gejala (tabel gejala).
 * Semua query memakai PDO prepared statements.
 * Bobot global 1–10 (gejala.bobot) dipakai mesin CBR versi ini;
 * tabel bobot_gejala_penyakit DIBIARKAN TANPA ISI (keputusan AGENTS.md).
 */
class Gejala
{
    public const TIPE_INPUT = ['biner', 'skala', 'durasi'];

    public const LABEL_TIPE = [
        'biner'  => 'Biner (Ya/Tidak)',
        'skala'  => 'Skala 0–3 (ringan–berat)',
        'durasi' => 'Durasi (<3 hari, 3–7 hari, 1–2 minggu, >2 minggu)',
    ];

    /** Semua gejala + hitungan kasus terkait (list + peringatan soft delete). */
    public function list(): array
    {
        $sql = 'SELECT g.*,
                    (SELECT COUNT(*) FROM detail_kasus dk WHERE dk.id_gejala = g.id_gejala) AS jumlah_kasus,
                    (SELECT COUNT(*) FROM detail_diagnosa dd WHERE dd.id_gejala = g.id_gejala) AS jumlah_referensi
                FROM gejala g
                ORDER BY g.kode_gejala ASC';
        return db()->query($sql)->fetchAll();
    }

    public function jumlahAktif(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM gejala WHERE is_active = 1')->fetchColumn();
    }

    /** Hanya gejala aktif (untuk matriks input kasus & formulir diagnosa). */
    public function listAktif(): array
    {
        return db()->query(
            'SELECT * FROM gejala WHERE is_active = 1 ORDER BY kategori ASC, kode_gejala ASC'
        )->fetchAll();
    }

    public function listKategori(): array
    {
        return db()->query('SELECT DISTINCT kategori FROM gejala ORDER BY kategori ASC')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM gejala WHERE id_gejala = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByKode(string $kode, int $kecualiId = 0): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM gejala WHERE UPPER(kode_gejala) = UPPER(?) AND id_gejala <> ? LIMIT 1'
        );
        $stmt->execute([$kode, $kecualiId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $d): int
    {
        $stmt = db()->prepare(
            'INSERT INTO gejala (kode_gejala, nama_gejala, deskripsi, kategori, tipe_input, bobot, is_redflag, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['kode_gejala'], $d['nama_gejala'], $d['deskripsi'], $d['kategori'],
            $d['tipe_input'], $d['bobot'], $d['is_redflag'], $d['is_active'],
        ]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $stmt = db()->prepare(
            'UPDATE gejala
             SET kode_gejala = ?, nama_gejala = ?, deskripsi = ?, kategori = ?,
                 tipe_input = ?, bobot = ?, is_redflag = ?, is_active = ?
             WHERE id_gejala = ?'
        );
        $stmt->execute([
            $d['kode_gejala'], $d['nama_gejala'], $d['deskripsi'], $d['kategori'],
            $d['tipe_input'], $d['bobot'], $d['is_redflag'], $d['is_active'], $id,
        ]);
    }

    /** Nonaktifkan / aktifkan kembali: hanya mengubah is_active. */
    public function setAktif(int $id, bool $aktif): void
    {
        $stmt = db()->prepare('UPDATE gejala SET is_active = ? WHERE id_gejala = ?');
        $stmt->execute([$aktif ? 1 : 0, $id]);
    }

    /** Berapa kasus memakai gejala ini (through detail_kasus, semua status). */
    public function jumlahKasus(int $id): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM detail_kasus WHERE id_gejala = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /** Berapa rekaman riwayat diagnosa pasien memakai gejala ini (detail_diagnosa). */
    public function jumlahReferensiRiwayat(int $id): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM detail_diagnosa WHERE id_gejala = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /** Hapus permanen. Panggil HANYA setelah memastikan jumlah kasus & referensi = 0. */
    public function deletePermanen(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM gejala WHERE id_gejala = ?');
        $stmt->execute([$id]);
    }
}
