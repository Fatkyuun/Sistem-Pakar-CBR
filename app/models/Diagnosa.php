<?php
declare(strict_types=1);

/**
 * app/models/Diagnosa.php — sesi diagnosa pasien (tabel diagnosa,
 * detail_diagnosa, hasil_diagnosa).
 *
 * Penulisan sesi diagnosa + detail gejala + top-K hasil + penambahan
 * frekuensi_digunakan kasus rujukan dilakukan dalam SATU transaksi PDO.
 *
 * Kepemilikan: id_user untuk pasien login, session_token (64 hex) untuk
 * tamu. Metode baca/ubah rating yang sensitif menyertakan pemilik pada
 * klausa WHERE agar pasien tidak dapat menyentuh data pasien lain.
 */
class Diagnosa
{
    /* ============================================================
       TULIS
       ============================================================ */

    /**
     * Simpan satu sesi diagnosa lengkap dalam satu transaksi.
     *
     * @param array $data   id_user, session_token, usia, jenis_kelamin,
     *                      status_merokok, id_penyakit_hasil,
     *                      similarity_tertinggi, status_cbr, status_validasi
     * @param array $detail [id_gejala => nilai] (vektor lengkap gejala aktif)
     * @param array $hasil  daftar ['id_kasus','id_penyakit','similarity','peringkat']
     * @param int|null $idKasusReuse kasus rujukan utama (hanya saat jalur reuse)
     */
    public function create(array $data, array $detail, array $hasil, ?int $idKasusReuse = null): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // NULL dipakai untuk tamu (id_user) / pasien login (session_token).
            $stmt = $pdo->prepare(
                'INSERT INTO diagnosa
                    (id_user, session_token, usia, jenis_kelamin, status_merokok,
                     id_penyakit_hasil, similarity_tertinggi, status_cbr, status_validasi)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['id_user'] ?? null,
                $data['session_token'] ?? null,
                $data['usia'] ?? null,
                $data['jenis_kelamin'] ?? null,
                $data['status_merokok'] ?? null,
                $data['id_penyakit_hasil'] ?? null,
                $data['similarity_tertinggi'] ?? null,
                $data['status_cbr'] ?? null,
                $data['status_validasi'] ?? 'tidak_perlu',
            ]);
            $idDiagnosa = (int) $pdo->lastInsertId();

            $stmtDetail = $pdo->prepare(
                'INSERT INTO detail_diagnosa (id_diagnosa, id_gejala, nilai) VALUES (?, ?, ?)'
            );
            foreach ($detail as $idGejala => $nilai) {
                $stmtDetail->execute([
                    $idDiagnosa,
                    (int) $idGejala,
                    max(0, min(3, (int) $nilai)),
                ]);
            }

            $stmtHasil = $pdo->prepare(
                'INSERT INTO hasil_diagnosa
                    (id_diagnosa, id_kasus, id_penyakit, similarity, peringkat)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($hasil as $row) {
                $stmtHasil->execute([
                    $idDiagnosa,
                    (int) $row['id_kasus'],
                    (int) $row['id_penyakit'],
                    (float) $row['similarity'],
                    (int) $row['peringkat'],
                ]);
            }

            // Reuse: kasus rujukan utama dipakai sekali lagi.
            if ($idKasusReuse !== null && $idKasusReuse > 0) {
                $pdo->prepare(
                    'UPDATE kasus SET frekuensi_digunakan = frekuensi_digunakan + 1 WHERE id_kasus = ?'
                )->execute([$idKasusReuse]);
            }

            $pdo->commit();
            return $idDiagnosa;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('Gagal menyimpan diagnosa: ' . $e->getMessage(), 0, $e);
        }
    }

    /** Simpan rating pengguna (1-5) dengan proteksi kepemilikan. */
    public function updateRating(int $id, int $rating, ?int $idUser, ?string $sessionToken): bool
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Rating harus 1-5.');
        }

        [$klausaPemilik, $params] = $this->klausaPemilik($idUser, $sessionToken);

        $stmt = db()->prepare(
            'UPDATE diagnosa SET rating_pengguna = ? WHERE id_diagnosa = ? AND ' . $klausaPemilik
        );
        $stmt->execute(array_merge([$rating, $id], $params));
        return $stmt->rowCount() > 0;
    }

    /* ============================================================
       BACA
       ============================================================ */

    /** Satu sesi diagnosa + data penyakit hasil (LEFT JOIN: hasil bisa kosong). */
    public function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT d.*,
                    p.kode_penyakit, p.nama_penyakit, p.deskripsi, p.penyebab,
                    p.solusi, p.pencegahan, p.tingkat_urgensi
             FROM diagnosa d
             LEFT JOIN penyakit p ON p.id_penyakit = d.id_penyakit_hasil
             WHERE d.id_diagnosa = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Detail gejala yang diinput, join master gejala. */
    public function detail(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT dd.id_gejala, dd.nilai, g.kode_gejala, g.nama_gejala, g.kategori,
                    g.tipe_input, g.bobot, g.is_redflag
             FROM detail_diagnosa dd
             JOIN gejala g ON g.id_gejala = dd.id_gejala
             WHERE dd.id_diagnosa = ?
             ORDER BY g.kode_gejala ASC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /** Peta id_gejala => nilai untuk satu diagnosa. */
    public function petaNilai(int $id): array
    {
        $map = [];
        foreach ($this->detail($id) as $row) {
            $map[(int) $row['id_gejala']] = (int) $row['nilai'];
        }
        return $map;
    }

    /** Top-K hasil retrieve (join kasus + penyakit), urut peringkat. */
    public function hasil(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT hd.*, k.kode_kasus,
                    p.kode_penyakit, p.nama_penyakit, p.tingkat_urgensi
             FROM hasil_diagnosa hd
             JOIN kasus k    ON k.id_kasus = hd.id_kasus
             JOIN penyakit p ON p.id_penyakit = hd.id_penyakit
             WHERE hd.id_diagnosa = ?
             ORDER BY hd.peringkat ASC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /** Apakah sesi ini milik pengguna/tamu yang sedang aktif. */
    public function milik(array $row, ?int $idUser, ?string $sessionToken): bool
    {
        if ($idUser !== null) {
            return $row['id_user'] !== null && (int) $row['id_user'] === $idUser;
        }
        if ($sessionToken === null || $sessionToken === '' || empty($row['session_token'])) {
            return false;
        }
        return hash_equals((string) $row['session_token'], $sessionToken);
    }

    /** Klausa WHERE kepemilikan untuk UPDATE (prepared). */
    private function klausaPemilik(?int $idUser, ?string $sessionToken): array
    {
        if ($idUser !== null) {
            return ['id_user = ?', [$idUser]];
        }
        if ($sessionToken === null || $sessionToken === '') {
            return ['1 = 0', []]; // tanpa identitas: tidak boleh mengubah apa pun
        }
        return ["id_user IS NULL AND session_token = ?", [$sessionToken]];
    }

    /* ============================================================
       RIWAYAT PASIEN (FR-U4) — selalu difilter id_user
       ============================================================ */

    /**
     * Daftar riwayat milik satu pengguna + hasil validasi pakar.
     * Filter opsional: dari (Y-m-d), sampai (Y-m-d), id_penyakit.
     */
    public function riwayatByUser(int $idUser, array $filter = [], int $limit = 10, int $offset = 0): array
    {
        [$where, $params] = $this->whereRiwayat($idUser, $filter);
        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT d.id_diagnosa, d.created_at, d.similarity_tertinggi, d.status_cbr,
                       d.status_validasi, d.rating_pengguna,
                       ph.kode_penyakit AS kode_hasil, ph.nama_penyakit AS nama_hasil,
                       pf.kode_penyakit AS kode_final, pf.nama_penyakit AS nama_final,
                       v.keputusan, v.catatan, v.solusi_revisi,
                       v.created_at AS divalidasi_pada, v.id_kasus_baru,
                       kb.kode_kasus
                FROM diagnosa d
                LEFT JOIN penyakit ph        ON ph.id_penyakit = d.id_penyakit_hasil
                LEFT JOIN validasi_kasus v   ON v.id_diagnosa = d.id_diagnosa
                LEFT JOIN penyakit pf        ON pf.id_penyakit = v.id_penyakit_final
                LEFT JOIN kasus kb           ON kb.id_kasus = v.id_kasus_baru
                WHERE ' . $where . '
                ORDER BY d.created_at DESC, d.id_diagnosa DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Jumlah baris riwayat (untuk paginasi). */
    public function hitungRiwayat(int $idUser, array $filter = []): int
    {
        [$where, $params] = $this->whereRiwayat($idUser, $filter);
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM diagnosa d
             LEFT JOIN validasi_kasus v ON v.id_diagnosa = d.id_diagnosa
             WHERE ' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Bangun WHERE + parameter riwayat (prepared). */
    private function whereRiwayat(int $idUser, array $filter): array
    {
        $where  = ['d.id_user = ?'];
        $params = [$idUser];

        $dari = trim((string) ($filter['dari'] ?? ''));
        if ($dari !== '') {
            $where[]  = 'd.created_at >= ?';
            $params[] = $dari . ' 00:00:00';
        }
        $sampai = trim((string) ($filter['sampai'] ?? ''));
        if ($sampai !== '') {
            $where[]  = 'd.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $sampai . ' 00:00:00';
        }
        $idPenyakit = (int) ($filter['id_penyakit'] ?? 0);
        if ($idPenyakit > 0) {
            $where[]  = 'COALESCE(v.id_penyakit_final, d.id_penyakit_hasil) = ?';
            $params[] = $idPenyakit;
        }

        return [implode(' AND ', $where), $params];
    }

    /** Satu baris riwayat yang dipastikan milik pengguna (anti-IDOR). */
    public function findMilik(int $id, int $idUser): ?array
    {
        $stmt = db()->prepare(
            'SELECT id_diagnosa, created_at, status_validasi, status_cbr
             FROM diagnosa WHERE id_diagnosa = ? AND id_user = ?'
        );
        $stmt->execute([$id, $idUser]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Hapus riwayat milik pengguna dalam satu transaksi.
     * Riwayat yang sudah punya baris validasi_kasus TIDAK dihapus
     * (FK tanpa cascade; data dipakai untuk audit).
     *
     * @throws DomainException bila bukan milik pengguna atau sudah divalidasi
     */
    public function hapusMilik(int $id, int $idUser): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id_diagnosa FROM diagnosa WHERE id_diagnosa = ? AND id_user = ? FOR UPDATE'
            );
            $stmt->execute([$id, $idUser]);
            if ($stmt->fetchColumn() === false) {
                throw new DomainException('Riwayat tidak ditemukan atau bukan milik Anda.');
            }

            $cek = $pdo->prepare('SELECT COUNT(*) FROM validasi_kasus WHERE id_diagnosa = ?');
            $cek->execute([$id]);
            if ((int) $cek->fetchColumn() > 0) {
                throw new DomainException(
                    'Riwayat ini sudah divalidasi pakar sehingga disimpan untuk audit dan tidak dapat dihapus.'
                );
            }

            $pdo->prepare('DELETE FROM diagnosa WHERE id_diagnosa = ? AND id_user = ?')
                ->execute([$id, $idUser]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        log_aktivitas('riwayat_hapus', 'diagnosa', $id, null, ['id_user' => $idUser]);
    }

    /** Hasil validasi pakar untuk satu diagnosa (null bila belum divalidasi). */
    public function validasi(int $idDiagnosa): ?array
    {
        $stmt = db()->prepare(
            'SELECT v.*, u.nama AS nama_pakar,
                    pf.kode_penyakit AS kode_final, pf.nama_penyakit AS nama_final,
                    pf.deskripsi AS deskripsi_final, pf.penyebab AS penyebab_final,
                    pf.solusi AS solusi_final, pf.pencegahan AS pencegahan_final,
                    pf.tingkat_urgensi AS urgensi_final,
                    kb.kode_kasus
             FROM validasi_kasus v
             LEFT JOIN users u    ON u.id_user = v.id_pakar
             LEFT JOIN penyakit pf ON pf.id_penyakit = v.id_penyakit_final
             LEFT JOIN kasus kb   ON kb.id_kasus = v.id_kasus_baru
             WHERE v.id_diagnosa = ?'
        );
        $stmt->execute([$idDiagnosa]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* ============================================================
       STATISTIK DASHBOARD
       ============================================================ */

    /** Ringkasan diagnosa untuk kartu dashboard. */
    public function statistik(): array
    {
        $row = db()->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(DATE(created_at) = CURDATE()), 0) AS hari_ini,
                    COALESCE(SUM(YEAR(created_at) = YEAR(CURDATE())
                             AND MONTH(created_at) = MONTH(CURDATE())), 0) AS bulan_ini,
                    AVG(similarity_tertinggi) AS rata_similarity
             FROM diagnosa"
        )->fetch() ?: [];

        return [
            'total'           => (int) ($row['total'] ?? 0),
            'hari_ini'        => (int) ($row['hari_ini'] ?? 0),
            'bulan_ini'       => (int) ($row['bulan_ini'] ?? 0),
            'rata_similarity' => $row['rata_similarity'] !== null ? (float) $row['rata_similarity'] : null,
        ];
    }

    /** Distribusi penyakit hasil diagnosa (grafik Chart.js). */
    public function distribusiPenyakit(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $sql = 'SELECT p.nama_penyakit, COUNT(*) AS jumlah
                FROM diagnosa d
                JOIN penyakit p ON p.id_penyakit = d.id_penyakit_hasil
                GROUP BY p.id_penyakit, p.nama_penyakit
                ORDER BY jumlah DESC, p.nama_penyakit ASC
                LIMIT ' . $limit;
        return db()->query($sql)->fetchAll();
    }
}
