<?php
declare(strict_types=1);

/**
 * app/models/Validasi.php — antrean validasi pakar & siklus Revise/Retain
 * (PRD FR-A6, bagian 3.3.4–3.3.5).
 *
 * Keputusan pakar (terima/revisi/tolak) dijalankan dalam SATU transaksi PDO:
 *   - cek status & kunci baris diagnosa (FOR UPDATE),
 *   - Retain (insert kasus + detail) ATAU arsip kasus untuk penolakan,
 *   - insert validasi_kasus,
 *   - update diagnosa.status_validasi,
 *   - tulis log_aktivitas.
 *
 * Retain hanya berjalan di sini (setelah validasi pakar) — tidak ada
 * auto-retain sesuai AGENTS.md.
 */
class Validasi
{
    public const KEPUTUSAN = ['terima', 'revisi', 'tolak'];

    public const STATUS_FINAL = ['terkonfirmasi', 'direvisi', 'ditolak'];

    public const MAP_STATUS = [
        'terima' => 'terkonfirmasi',
        'revisi' => 'direvisi',
        'tolak'  => 'ditolak',
    ];

    public const LABEL_KEPUTUSAN = [
        'terima' => 'Diterima',
        'revisi' => 'Direvisi',
        'tolak'  => 'Ditolak',
    ];

    /* ============================================================
       BACA
       ============================================================ */

    /**
     * Antrean diagnosa berstatus 'menunggu'.
     * $urut: 'tanggal' (FIFO) atau 'similarity' (terendah lebih dulu).
     */
    public function antrean(string $urut = 'tanggal'): array
    {
        $order = $urut === 'similarity'
            ? 'd.similarity_tertinggi ASC, d.created_at ASC, d.id_diagnosa ASC'
            : 'd.created_at ASC, d.id_diagnosa ASC';

        $sql = "SELECT d.id_diagnosa, d.created_at, d.usia, d.jenis_kelamin, d.status_merokok,
                       d.similarity_tertinggi, d.status_cbr, d.status_validasi,
                       d.id_penyakit_hasil,
                       p.kode_penyakit, p.nama_penyakit, p.tingkat_urgensi,
                       (SELECT COUNT(*) FROM detail_diagnosa dd
                         WHERE dd.id_diagnosa = d.id_diagnosa AND dd.nilai > 0) AS jumlah_gejala
                FROM diagnosa d
                LEFT JOIN penyakit p ON p.id_penyakit = d.id_penyakit_hasil
                WHERE d.status_validasi = 'menunggu'
                ORDER BY " . $order;

        return db()->query($sql)->fetchAll();
    }

    /** Riwayat keputusan pakar (opsional filter satu status final). */
    public function riwayat(?string $status = null): array
    {
        $where  = "d.status_validasi IN ('terkonfirmasi','direvisi','ditolak')";
        $params = [];
        if ($status !== null && in_array($status, self::STATUS_FINAL, true)) {
            $where    = 'd.status_validasi = ?';
            $params[] = $status;
        }

        $sql = 'SELECT d.id_diagnosa, d.created_at, d.status_validasi, d.status_cbr,
                       d.similarity_tertinggi,
                       ph.kode_penyakit AS kode_hasil, ph.nama_penyakit AS nama_hasil,
                       pf.kode_penyakit AS kode_final, pf.nama_penyakit AS nama_final,
                       v.id_validasi, v.keputusan, v.solusi_revisi, v.catatan,
                       v.created_at AS divalidasi_pada, v.id_kasus_baru,
                       u.nama AS nama_pakar,
                       kb.kode_kasus
                FROM diagnosa d
                JOIN validasi_kasus v ON v.id_diagnosa = d.id_diagnosa
                LEFT JOIN penyakit ph ON ph.id_penyakit = d.id_penyakit_hasil
                LEFT JOIN penyakit pf ON pf.id_penyakit = v.id_penyakit_final
                LEFT JOIN users u     ON u.id_user = v.id_pakar
                LEFT JOIN kasus kb    ON kb.id_kasus = v.id_kasus_baru
                WHERE ' . $where . '
                ORDER BY v.created_at DESC, v.id_validasi DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Satu diagnosa lengkap dengan info penyakit hasil & validasi (bila ada). */
    public function find(int $idDiagnosa): ?array
    {
        $stmt = db()->prepare(
            'SELECT d.*,
                    ph.kode_penyakit AS kode_hasil, ph.nama_penyakit AS nama_hasil,
                    ph.tingkat_urgensi AS urgensi_hasil,
                    v.id_validasi, v.keputusan, v.id_penyakit_final, v.solusi_revisi,
                    v.catatan, v.id_kasus_baru, v.created_at AS divalidasi_pada,
                    pf.kode_penyakit AS kode_final, pf.nama_penyakit AS nama_final,
                    u.nama AS nama_pakar,
                    kb.kode_kasus AS kode_kasus_baru
             FROM diagnosa d
             LEFT JOIN penyakit ph      ON ph.id_penyakit = d.id_penyakit_hasil
             LEFT JOIN validasi_kasus v ON v.id_diagnosa = d.id_diagnosa
             LEFT JOIN penyakit pf      ON pf.id_penyakit = v.id_penyakit_final
             LEFT JOIN users u          ON u.id_user = v.id_pakar
             LEFT JOIN kasus kb         ON kb.id_kasus = v.id_kasus_baru
             WHERE d.id_diagnosa = ?'
        );
        $stmt->execute([$idDiagnosa]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** 5 antrean terbaru untuk dashboard. */
    public function antreanTerbaru(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $sql = 'SELECT d.id_diagnosa, d.created_at, d.similarity_tertinggi, d.status_cbr,
                       p.nama_penyakit
                FROM diagnosa d
                LEFT JOIN penyakit p ON p.id_penyakit = d.id_penyakit_hasil
                WHERE d.status_validasi = \'menunggu\'
                ORDER BY d.created_at DESC, d.id_diagnosa DESC
                LIMIT ' . $limit;
        return db()->query($sql)->fetchAll();
    }

    /** Statistik validasi: menunggu, jumlah tiap keputusan, akurasi. */
    public function statistik(): array
    {
        $menunggu = (int) db()->query(
            "SELECT COUNT(*) FROM diagnosa WHERE status_validasi = 'menunggu'"
        )->fetchColumn();

        $row = db()->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(keputusan = 'terima'), 0) AS terima,
                    COALESCE(SUM(keputusan = 'revisi'), 0) AS revisi,
                    COALESCE(SUM(keputusan = 'tolak'), 0)  AS tolak
             FROM validasi_kasus"
        )->fetch() ?: [];

        $terima  = (int) ($row['terima'] ?? 0);
        $revisi  = (int) ($row['revisi'] ?? 0);
        $dasar   = $terima + $revisi;

        return [
            'menunggu' => $menunggu,
            'total'    => (int) ($row['total'] ?? 0),
            'terima'   => $terima,
            'revisi'   => $revisi,
            'tolak'    => (int) ($row['tolak'] ?? 0),
            'akurasi'  => $dasar > 0 ? $terima / $dasar : null,
        ];
    }

    /* ============================================================
       KEPUTUSAN PAKAR (transaksi atomik)
       ============================================================ */

    /** Ambil string dari nilai skalar dengan aman (anti Type Error bila form dimanipulasi). */
    private static function teks(mixed $nilai): string
    {
        return is_scalar($nilai) ? (string) $nilai : '';
    }

    /**
     * Proses keputusan pakar + Retain/arsip dalam satu transaksi.
     *
     * @param array $data id_penyakit_final, solusi_revisi, catatan,
     *                    gejala (opsional: [id_gejala => nilai] koreksi)
     * @return array{id_validasi:int,keputusan:string,status:string,
     *   duplikat:bool,id_kasus_baru:?int,kode_kasus:?string,
     *   id_kasus_duplikat:?int,kode_kasus_duplikat:?string,
     *   jumlah_gejala:int,gejala_dikoreksi:int,arsip_dilewati:bool}
     *
     * @throws InvalidArgumentException aturan bisnis tidak terpenuhi
     * @throws RuntimeException kegagalan database
     */
    public function putuskan(int $idDiagnosa, int $idPakar, string $keputusan, array $data = []): array
    {
        if (!in_array($keputusan, self::KEPUTUSAN, true)) {
            throw new InvalidArgumentException('Keputusan validasi tidak valid.');
        }

        $catatan      = trim(self::teks($data['catatan'] ?? ''));
        $solusiRevisi = trim(self::teks($data['solusi_revisi'] ?? ''));
        $idFinal      = (int) self::teks($data['id_penyakit_final'] ?? '0');

        if (mb_strlen($catatan) > 2000) {
            throw new InvalidArgumentException('Catatan maksimal 2000 karakter.');
        }
        if (mb_strlen($solusiRevisi) > 5000) {
            throw new InvalidArgumentException('Solusi revisi maksimal 5000 karakter.');
        }
        if ($keputusan === 'tolak' && $catatan === '') {
            throw new InvalidArgumentException('Alasan penolakan (catatan) wajib diisi.');
        }

        $pdo = db();
        $transaksiLuar = $pdo->inTransaction();
        if (!$transaksiLuar) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT validasi_putuskan');
        }
        try {
            // ---- kunci baris & pastikan masih menunggu ----
            $stmt = $pdo->prepare(
                'SELECT * FROM diagnosa WHERE id_diagnosa = ? FOR UPDATE'
            );
            $stmt->execute([$idDiagnosa]);
            $d = $stmt->fetch();
            if ($d === false) {
                throw new InvalidArgumentException('Data diagnosa tidak ditemukan.');
            }
            if ((string) $d['status_validasi'] !== 'menunggu') {
                throw new InvalidArgumentException(
                    'Diagnosa ini sudah diproses (status: ' . $d['status_validasi'] . ').'
                );
            }
            $cek = $pdo->prepare('SELECT COUNT(*) FROM validasi_kasus WHERE id_diagnosa = ?');
            $cek->execute([$idDiagnosa]);
            if ((int) $cek->fetchColumn() > 0) {
                throw new InvalidArgumentException('Diagnosa ini sudah memiliki catatan validasi.');
            }

            // ---- tentukan penyakit final ----
            if ($keputusan === 'terima') {
                $idFinal = (int) ($d['id_penyakit_hasil'] ?? 0);
                if ($idFinal <= 0) {
                    throw new InvalidArgumentException(
                        'Hasil sistem belum memiliki penyakit. Gunakan keputusan Revisi untuk memilih penyakit final.'
                    );
                }
            } elseif ($keputusan === 'revisi') {
                if ($idFinal <= 0) {
                    throw new InvalidArgumentException('Pilih penyakit final hasil revisi.');
                }
                $cekPenyakit = $pdo->prepare(
                    'SELECT COUNT(*) FROM penyakit WHERE id_penyakit = ? AND is_active = 1'
                );
                $cekPenyakit->execute([$idFinal]);
                if ((int) $cekPenyakit->fetchColumn() === 0) {
                    throw new InvalidArgumentException('Penyakit final tidak ditemukan atau sudah nonaktif.');
                }
            } else {
                $idFinal      = 0;
                $solusiRevisi = '';
            }

            // Solusi revisi hanya bermakna untuk keputusan Revisi.
            if ($keputusan !== 'revisi') {
                $solusiRevisi = '';
            }

            // ---- vektor gejala (dengan koreksi pakar untuk revisi) ----
            $engine = new CBREngine();
            $meta   = $engine->metaAktif();

            $stmtDetail = $pdo->prepare(
                'SELECT id_gejala, nilai FROM detail_diagnosa WHERE id_diagnosa = ?'
            );
            $stmtDetail->execute([$idDiagnosa]);
            $peta = [];
            foreach ($stmtDetail->fetchAll() as $row) {
                $peta[(int) $row['id_gejala']] = (int) $row['nilai'];
            }

            $koreksi = [];
            if ($keputusan === 'revisi' && isset($data['gejala']) && is_array($data['gejala'])) {
                foreach ($data['gejala'] as $idGejala => $nilai) {
                    // Abaikan nilai non-skalar (array dari manipulasi form).
                    if (!is_scalar($nilai)) {
                        continue;
                    }
                    $idGejala = (int) $idGejala;
                    if (!isset($meta[$idGejala])) {
                        continue;
                    }
                    $baru = max(0, min(3, (int) $nilai));
                    if ($meta[$idGejala]['tipe'] === 'biner' && $baru > 1) {
                        $baru = 1;
                    }
                    if ($baru !== (int) ($peta[$idGejala] ?? 0)) {
                        $koreksi[$idGejala] = ['lama' => (int) ($peta[$idGejala] ?? 0), 'baru' => $baru];
                    }
                    $peta[$idGejala] = $baru;
                }
            }

            $vektor = [];
            foreach ($meta as $idGejala => $m) {
                $nilai = max(0, min(3, (int) ($peta[$idGejala] ?? 0)));
                if ($m['tipe'] === 'biner' && $nilai > 1) {
                    $nilai = 1;
                }
                $vektor[(int) $idGejala] = $nilai;
            }

            if ($keputusan !== 'tolak') {
                $jumlahTerpilih = count(array_filter($vektor, static fn (int $n): bool => $n > 0));
                if ($jumlahTerpilih < 3) {
                    throw new InvalidArgumentException(
                        'Kasus yang akan di-retain harus memiliki minimal 3 gejala bernilai > 0.'
                    );
                }
            }

            // ---- Retain / arsip ----
            $retain         = null;
            $idKasusBaru    = null;
            $kodeKasus      = null;
            $idKasusDuplikat = null;
            $kodeDuplikat   = null;
            $arsipDilewati  = false;

            if ($keputusan === 'tolak') {
                $idPenyakitArsip = (int) ($d['id_penyakit_hasil'] ?? 0);
                if ($idPenyakitArsip > 0) {
                    $idKasusBaru = (new Kasus())->createDalamTransaksi($pdo, [
                        'id_penyakit'      => $idPenyakitArsip,
                        'sumber'           => 'retain',
                        'status'           => 'arsip',
                        'id_diagnosa_asal' => $idDiagnosa,
                        'id_validator'     => $idPakar,
                        'validated_at'     => date('Y-m-d H:i:s'),
                    ], $vektor);
                    $stmtKode = $pdo->prepare('SELECT kode_kasus FROM kasus WHERE id_kasus = ?');
                    $stmtKode->execute([$idKasusBaru]);
                    $kodeKasus = (string) $stmtKode->fetchColumn();
                } else {
                    // Jalur 'baru' tanpa kandidat penyakit: tidak ada label kasus.
                    $arsipDilewati = true;
                }
            } else {
                $retain = $engine->retain($pdo, $vektor, $idFinal, $idDiagnosa, $idPakar);
                $idKasusBaru     = $retain['id_kasus'];
                $kodeKasus       = $retain['kode_kasus'];
                $idKasusDuplikat = $retain['id_kasus_duplikat'];
                $kodeDuplikat    = $retain['kode_kasus_duplikat'];
            }

            // ---- simpan keputusan ----
            $statusBaru = self::MAP_STATUS[$keputusan];
            $stmtV = $pdo->prepare(
                'INSERT INTO validasi_kasus
                    (id_diagnosa, id_pakar, keputusan, id_penyakit_final,
                     solusi_revisi, catatan, id_kasus_baru)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtV->execute([
                $idDiagnosa,
                $idPakar,
                $keputusan,
                $idFinal > 0 ? $idFinal : null,
                $solusiRevisi !== '' ? $solusiRevisi : null,
                $catatan !== '' ? $catatan : null,
                $idKasusBaru,
            ]);
            $idValidasi = (int) $pdo->lastInsertId();

            $pdo->prepare('UPDATE diagnosa SET status_validasi = ? WHERE id_diagnosa = ?')
                ->execute([$statusBaru, $idDiagnosa]);

            log_aktivitas('validasi_' . $keputusan, 'validasi_kasus', $idValidasi, null, [
                'id_diagnosa'        => $idDiagnosa,
                'id_pakar'           => $idPakar,
                'id_penyakit_final'  => $idFinal > 0 ? $idFinal : null,
                'id_kasus_baru'      => $idKasusBaru,
                'duplikat'           => $retain['duplikat'] ?? false,
                'id_kasus_duplikat'  => $idKasusDuplikat,
                'gejala_dikoreksi'   => $koreksi,
                'arsip_dilewati'     => $arsipDilewati,
            ]);

            if (!$transaksiLuar) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT validasi_putuskan');
            }

            return [
                'id_validasi'         => $idValidasi,
                'keputusan'           => $keputusan,
                'status'              => $statusBaru,
                'duplikat'            => (bool) ($retain['duplikat'] ?? false),
                'id_kasus_baru'       => $idKasusBaru,
                'kode_kasus'          => $kodeKasus,
                'id_kasus_duplikat'   => $idKasusDuplikat,
                'kode_kasus_duplikat' => $kodeDuplikat,
                'jumlah_gejala'       => count(array_filter($vektor, static fn (int $n): bool => $n > 0)),
                'gejala_dikoreksi'    => count($koreksi),
                'arsip_dilewati'      => $arsipDilewati,
            ];
        } catch (Throwable $e) {
            if (!$transaksiLuar) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                try {
                    $pdo->exec('ROLLBACK TO SAVEPOINT validasi_putuskan');
                } catch (Throwable) {
                    // Abaikan jika status transaksi/koneksi tidak mendukung
                }
            }
            if ($e instanceof InvalidArgumentException || $e instanceof DomainException) {
                throw $e;
            }
            throw new RuntimeException('Gagal memproses validasi: ' . $e->getMessage(), 0, $e);
        }
    }
}
