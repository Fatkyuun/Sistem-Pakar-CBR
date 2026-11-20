<?php
declare(strict_types=1);

/**
 * app/models/Kasus.php — CRUD basis kasus (tabel kasus + detail_kasus).
 *
 * Semua query memakai PDO prepared statements; penulisan kasus + detail
 * selalu dalam SATU transaksi (beginTransaction/commit/rollBack).
 *
 * Catatan arsitektur: perhitungan similarity (termasuk untuk DETEKSI
 * DUPLIKAT) memakai rumus dari CBREngine di app/services/ — satu sumber
 * kebenaran sejak Fase 3. Metode similarityTertinggi() di berkas ini
 * hanya mengumpulkan kandidat kasus lalu mendelegasikan skornya.
 */
class Kasus
{
    public const STATUS = ['aktif', 'nonaktif', 'arsip'];
    public const SUMBER = ['pakar', 'retain', 'import'];

    /* ============================================================
       BACA (list, find, detail)
       ============================================================ */

    /** Daftar kasus + filter: id_penyakit, status, sumber, cari (kode/nama). */
    public function list(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['id_penyakit'])) {
            $where[]  = 'k.id_penyakit = ?';
            $params[] = (int) $filter['id_penyakit'];
        }
        if (!empty($filter['status']) && in_array($filter['status'], self::STATUS, true)) {
            $where[]  = 'k.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['sumber']) && in_array($filter['sumber'], self::SUMBER, true)) {
            $where[]  = 'k.sumber = ?';
            $params[] = $filter['sumber'];
        }
        if (!empty($filter['cari'])) {
            $where[]  = '(k.kode_kasus LIKE ? OR p.nama_penyakit LIKE ?)';
            $params[] = '%' . $filter['cari'] . '%';
            $params[] = '%' . $filter['cari'] . '%';
        }

        $sql = 'SELECT k.*, p.kode_penyakit, p.nama_penyakit,
                    (SELECT COUNT(*) FROM detail_kasus dk
                     WHERE dk.id_kasus = k.id_kasus AND dk.nilai > 0) AS jumlah_gejala,
                    (SELECT COUNT(*) FROM hasil_diagnosa hd WHERE hd.id_kasus = k.id_kasus)
                      + (SELECT COUNT(*) FROM validasi_kasus vk WHERE vk.id_kasus_baru = k.id_kasus)
                    AS jumlah_referensi
                FROM kasus k
                JOIN penyakit p ON p.id_penyakit = k.id_penyakit'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY k.id_kasus DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT k.*, p.kode_penyakit, p.nama_penyakit, p.solusi
             FROM kasus k
             JOIN penyakit p ON p.id_penyakit = k.id_penyakit
             WHERE k.id_kasus = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Seluruh baris detail_kasus milik satu kasus (join gejala). */
    public function detailGejala(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT dk.id_gejala, dk.nilai, g.kode_gejala, g.nama_gejala, g.kategori,
                    g.tipe_input, g.bobot, g.is_redflag
             FROM detail_kasus dk
             JOIN gejala g ON g.id_gejala = dk.id_gejala
             WHERE dk.id_kasus = ?
             ORDER BY g.kode_gejala ASC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /** Peta id_gejala => nilai untuk satu kasus (praktis untuk form edit). */
    public function petaNilai(int $id): array
    {
        $map = [];
        foreach ($this->detailGejala($id) as $row) {
            $map[(int) $row['id_gejala']] = (int) $row['nilai'];
        }
        return $map;
    }

    /* ============================================================
       TULIS (create, update, status, delete) — SELALU TRANSAKSI
       ============================================================ */

    /** Kode kasus berikutnya: K001, K002, ... */
    public function kodeBerikut(?PDO $pdo = null): string
    {
        $pdo = $pdo ?? db();
        $last = (string) $pdo->query(
            'SELECT kode_kasus FROM kasus ORDER BY LENGTH(kode_kasus) DESC, kode_kasus DESC LIMIT 1'
        )->fetchColumn();
        $n = $last !== '' ? (int) substr($last, 1) : 0;
        return 'K' . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Simpan kasus baru + detail di dalam transaksi milik pemanggil.
     *
     * Dipakai Retain (Validasi) dan arsip penolakan agar tidak terjadi
     * nested transaction dengan Validasi::putuskan().
     *
     * @param array $kasus  id_penyakit, sumber, status, opsional
     *                      id_diagnosa_asal, id_validator, validated_at
     * @param array $detail [id_gejala => nilai 0-3]
     */
    public function createDalamTransaksi(PDO $pdo, array $kasus, array $detail): int
    {
        $sumber = in_array($kasus['sumber'] ?? '', self::SUMBER, true) ? $kasus['sumber'] : 'pakar';
        $status = in_array($kasus['status'] ?? '', self::STATUS, true) ? $kasus['status'] : 'aktif';
        $validatedAt = $kasus['validated_at'] ?? ($sumber === 'retain' ? date('Y-m-d H:i:s') : null);

        $stmt = $pdo->prepare(
            'INSERT INTO kasus
                (kode_kasus, id_penyakit, sumber, status, frekuensi_digunakan,
                 id_diagnosa_asal, id_validator, validated_at)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?)'
        );
        $stmt->execute([
            $this->kodeBerikut($pdo),
            (int) $kasus['id_penyakit'],
            $sumber,
            $status,
            $kasus['id_diagnosa_asal'] ?? null,
            $kasus['id_validator'] ?? null,
            $validatedAt,
        ]);
        $idKasus = (int) $pdo->lastInsertId();

        $this->simpanDetail($idKasus, $detail, $pdo);
        return $idKasus;
    }

    /**
     * Simpan kasus baru + detail gejala dalam satu transaksi.
     * $kasus kunci: id_penyakit, sumber, status. $detail: [id_gejala => nilai].
     * Mengembalikan id_kasus baru.
     */
    public function create(array $kasus, array $detail): int
    {
        $pdo = db();
        $kode = $this->kodeBerikut();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO kasus (kode_kasus, id_penyakit, sumber, status, frekuensi_digunakan)
                 VALUES (?, ?, ?, ?, 0)'
            );
            $stmt->execute([
                $kode,
                (int) $kasus['id_penyakit'],
                in_array($kasus['sumber'] ?? '', self::SUMBER, true) ? $kasus['sumber'] : 'pakar',
                in_array($kasus['status'] ?? '', self::STATUS, true) ? $kasus['status'] : 'aktif',
            ]);
            $idKasus = (int) $pdo->lastInsertId();

            $this->simpanDetail($idKasus, $detail);
            $pdo->commit();
            return $idKasus;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('Gagal menyimpan kasus: ' . $e->getMessage(), 0, $e);
        }
    }

    /** Perbarui kasus: label penyakit/status + timpa seluruh detail gejala. */
    public function update(int $id, array $kasus, array $detail): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE kasus SET id_penyakit = ?, status = ? WHERE id_kasus = ?'
            );
            $stmt->execute([
                (int) $kasus['id_penyakit'],
                in_array($kasus['status'] ?? '', self::STATUS, true) ? $kasus['status'] : 'aktif',
                $id,
            ]);

            $pdo->prepare('DELETE FROM detail_kasus WHERE id_kasus = ?')->execute([$id]);
            $this->simpanDetail($id, $detail);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('Gagal memperbarui kasus: ' . $e->getMessage(), 0, $e);
        }
    }

    /** Ubah status: aktif / nonaktif / arsip (soft delete). */
    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUS, true)) {
            throw new InvalidArgumentException('Status kasus tidak valid.');
        }
        $stmt = db()->prepare('UPDATE kasus SET status = ? WHERE id_kasus = ?');
        $stmt->execute([$status, $id]);
    }

    /** Jumlah kasus berstatus aktif (metrik dashboard). */
    public function jumlahAktif(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM kasus WHERE status = 'aktif'")->fetchColumn();
    }

    /** Berapa rekaman riwayat merujuk kasus ini (hasil_diagnosa + validasi_kasus). */
    public function jumlahReferensi(int $id): int
    {
        $stmt = db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM hasil_diagnosa WHERE id_kasus = ?) +
                (SELECT COUNT(*) FROM validasi_kasus WHERE id_kasus_baru = ?)
             AS jumlah'
        );
        $stmt->execute([$id, $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Hapus permanen. detail_kasus ikut terhapus (ON DELETE CASCADE).
     * Pemanggil (controller) wajib memastikan jumlah_referensi = 0;
     * bila sudah dirujuk, gunakan setStatus('arsip').
     */
    public function deletePermanen(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM kasus WHERE id_kasus = ?');
        $stmt->execute([$id]);
    }

    /** Tulis detail_kasus dalam transaksi yang sedang berjalan. */
    private function simpanDetail(int $idKasus, array $detail, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?? db();
        $stmt = $pdo->prepare(
            'INSERT INTO detail_kasus (id_kasus, id_gejala, nilai) VALUES (?, ?, ?)'
        );
        foreach ($detail as $idGejala => $nilai) {
            $stmt->execute([$idKasus, (int) $idGejala, max(0, min(3, (int) $nilai))]);
        }
    }

    /* ============================================================
       DETEKSI DUPLIKAT (similarity >= threshold_duplikat)
       ============================================================ */

    /**
     * Cari similarity tertinggi antara vektor input dan kasus AKTIF
     * dengan penyakit yang sama. Dipakai saat simpan kasus & impor CSV.
     *
     * Sejak Fase 3 rumus TIDAK lagi ditulis di sini: perhitungan didelegasikan
     * ke CBREngine (satu sumber kebenaran). Metode yang dipakai tetap
     * nearest_neighbor terbobot agar perilaku deteksi duplikat Fase 2 stabil
     * (threshold_duplikat dikalibrasi dengan metode tersebut).
     *
     * @param array $input [id_gejala => nilai 0-3]
     * @return array{similarity:float,id_kasus:int,kode_kasus:string}|null
     */
    public function similarityTertinggi(array $input, int $idPenyakit, int $kecualiKasus = 0): ?array
    {
        $stmt = db()->prepare(
            "SELECT k.id_kasus, k.kode_kasus, dk.id_gejala, dk.nilai
             FROM kasus k
             JOIN detail_kasus dk ON dk.id_kasus = k.id_kasus
             WHERE k.id_penyakit = ? AND k.status = 'aktif' AND k.id_kasus <> ?
             ORDER BY k.id_kasus ASC, dk.id_gejala ASC"
        );
        $stmt->execute([$idPenyakit, $kecualiKasus]);

        $kasus = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['id_kasus'];
            if (!isset($kasus[$id])) {
                $kasus[$id] = [
                    'id_kasus'   => $id,
                    'kode_kasus' => (string) $row['kode_kasus'],
                    'gejala'     => [],
                ];
            }
            $kasus[$id]['gejala'][(int) $row['id_gejala']] = (int) $row['nilai'];
        }
        if ($kasus === []) {
            return null;
        }

        $meta = (new CBREngine())->metaAktif();

        $terbaik = null;
        foreach ($kasus as $k) {
            $similarity = CBREngine::similarityTerbobot($input, $k['gejala'], $meta);
            if ($terbaik === null || $similarity > $terbaik['similarity']) {
                $terbaik = [
                    'similarity' => $similarity,
                    'id_kasus'   => $k['id_kasus'],
                    'kode_kasus' => $k['kode_kasus'],
                ];
            }
        }

        return $terbaik;
    }
}
