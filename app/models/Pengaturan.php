<?php
declare(strict_types=1);

/**
 * app/models/Pengaturan.php — parameter CBR pada tabel `pengaturan`.
 *
 * Nilai ambang TIDAK boleh di-hardcode di mesin CBR (AGENTS.md); halaman
 * pengaturan hanya mengubah baris pada tabel ini. Validasi logis dilakukan
 * oleh fungsi murni validasi() agar dapat diuji PHPUnit tanpa database.
 */
class Pengaturan
{
    /** Definisi parameter: label, keterangan awam, nilai bawaan, jenis isian. */
    public const DEFINISI = [
        'threshold_reuse' => [
            'label'    => 'Ambang Reuse',
            'bantuan'  => 'Kemiripan ≥ nilai ini dianggap kuat; hasil langsung dipakai sebagai rujukan (Reuse).',
            'default'  => '0.80',
            'jenis'    => 'desimal',
        ],
        'threshold_revise' => [
            'label'    => 'Ambang Revisi',
            'bantuan'  => 'Kemiripan antara ambang ini dan Ambang Reuse masuk antrean validasi pakar (Revise).',
            'default'  => '0.50',
            'jenis'    => 'desimal',
        ],
        'threshold_duplikat' => [
            'label'    => 'Ambang Duplikat',
            'bantuan'  => 'Saat Retain, kasus baru yang kemiripannya ≥ nilai ini tidak digandakan; kasus lama yang dipakai.',
            'default'  => '0.98',
            'jenis'    => 'desimal',
        ],
        'top_k' => [
            'label'    => 'Jumlah Kasus Teratas (top-K)',
            'bantuan'  => 'Banyaknya kasus paling mirip yang ditampilkan dan dipakai untuk agregasi penyakit.',
            'default'  => '3',
            'jenis'    => 'bulat',
        ],
        'metode_similarity' => [
            'label'    => 'Metode Similarity',
            'bantuan'  => 'Rumus perhitungan kemiripan: Nearest Neighbor terbobot atau Weighted Jaccard.',
            'default'  => 'nearest_neighbor',
            'jenis'    => 'metode',
        ],
    ];

    /** Seluruh parameter (lengkap dengan nilai bawaan bila baris tidak ada). */
    public function semua(): array
    {
        $hasil = [];
        foreach (self::DEFINISI as $kunci => $def) {
            $hasil[$kunci] = $def['default'];
        }
        foreach (db()->query('SELECT kunci, nilai FROM pengaturan')->fetchAll() as $row) {
            if (isset($hasil[(string) $row['kunci']])) {
                $hasil[(string) $row['kunci']] = (string) $row['nilai'];
            }
        }
        return $hasil;
    }

    /**
     * Simpan hanya parameter yang berubah, dalam satu transaksi.
     *
     * @param array<string,string> $nilai nilai yang SUDAH lolos validasi()
     * @return array<string,array{lama:string,baru:string}> daftar perubahan
     */
    public function simpan(array $nilai): array
    {
        $lama = $this->semua();
        $perubahan = [];

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
            );
            foreach (self::DEFINISI as $kunci => $def) {
                $baru = trim((string) ($nilai[$kunci] ?? ''));
                if ($baru === '' || $baru === $lama[$kunci]) {
                    continue;
                }
                $stmt->execute([$kunci, $baru]);
                $perubahan[$kunci] = ['lama' => $lama[$kunci], 'baru' => $baru];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Gagal menyimpan pengaturan: ' . $e->getMessage(), 0, $e);
        }

        return $perubahan;
    }

    /**
     * Validasi logis seluruh parameter. Fungsi murni (tanpa DB).
     *
     * @return string[] daftar pesan galat; kosong berarti valid
     */
    public static function validasi(array $nilai): array
    {
        $errors = [];
        $angka = [];

        foreach (['threshold_reuse', 'threshold_revise', 'threshold_duplikat'] as $kunci) {
            $teks = trim((string) ($nilai[$kunci] ?? ''));
            if ($teks === '' || !is_numeric($teks)) {
                $errors[] = self::DEFINISI[$kunci]['label'] . ' harus berupa angka desimal (contoh 0.80).';
                continue;
            }
            $v = (float) $teks;
            if ($v <= 0.0 || $v > 1.0) {
                $errors[] = self::DEFINISI[$kunci]['label'] . ' harus lebih dari 0 dan paling besar 1.';
                continue;
            }
            $angka[$kunci] = $v;
        }

        $urutanSalah = false;
        if (isset($angka['threshold_revise'], $angka['threshold_reuse'])
            && $angka['threshold_revise'] >= $angka['threshold_reuse']) {
            $errors[] = 'Ambang Revisi harus lebih kecil daripada Ambang Reuse.';
            $urutanSalah = true;
        }
        if (isset($angka['threshold_duplikat'], $angka['threshold_reuse'])
            && $angka['threshold_duplikat'] < $angka['threshold_reuse']) {
            $errors[] = 'Ambang Duplikat tidak boleh lebih kecil daripada Ambang Reuse.';
            $urutanSalah = true;
        }

        // Nilai disimpan 2 desimal (lihat rapikan()). Pastikan pembulatan tidak
        // membuat Ambang Revisi menyusul Ambang Reuse — mis. revise 0.499 dan
        // reuse 0.50 sama-sama menjadi 0.50 sehingga zona Revise hilang.
        if (!$urutanSalah && isset($angka['threshold_revise'], $angka['threshold_reuse'])) {
            $reuseRapi  = (float) number_format($angka['threshold_reuse'], 2, '.', '');
            $reviseRapi = (float) number_format($angka['threshold_revise'], 2, '.', '');
            if ($reviseRapi >= $reuseRapi) {
                $errors[] = 'Setelah dibulatkan 2 desimal, Ambang Revisi harus tetap lebih kecil '
                    . 'daripada Ambang Reuse (contoh: 0.49 dan 0.50).';
            }
        }

        $topK = trim((string) ($nilai['top_k'] ?? ''));
        if ($topK === '' || !preg_match('/^\d+$/', $topK) || (int) $topK < 1 || (int) $topK > 10) {
            $errors[] = 'Jumlah kasus teratas (top-K) harus bilangan bulat 1–10.';
        }

        $metode = (string) ($nilai['metode_similarity'] ?? '');
        if (!in_array($metode, CBREngine::METODE_VALID, true)) {
            $errors[] = 'Metode similarity tidak valid.';
        }

        return $errors;
    }

    /** Rapikan nilai yang sudah valid ke bentuk penyimpanan (2 desimal, dsb.). */
    public static function rapikan(array $nilai): array
    {
        $hasil = [];
        foreach (['threshold_reuse', 'threshold_revise', 'threshold_duplikat'] as $kunci) {
            $hasil[$kunci] = number_format((float) ($nilai[$kunci] ?? 0), 2, '.', '');
        }
        $hasil['top_k'] = (string) max(1, min(10, (int) ($nilai['top_k'] ?? 3)));
        $metode = (string) ($nilai['metode_similarity'] ?? '');
        $hasil['metode_similarity'] = in_array($metode, CBREngine::METODE_VALID, true)
            ? $metode
            : 'nearest_neighbor';
        return $hasil;
    }
}
