<?php
declare(strict_types=1);

/**
 * app/services/CBREngine.php — mesin Case-Based Reasoning (Retrieve & Reuse).
 *
 * MURNI LOGIKA DOMAIN: tidak memakai sesi, HTML, atau redirect, sehingga
 * seluruh perhitungan dapat diuji langsung dengan PHPUnit. Hanya
 * retrieve() yang menyentuh database (membaca kasus aktif + pengaturan).
 *
 * Rumus (AGENTS.md + PRD 3.3.2):
 *  - Nearest Neighbor terbobot:
 *      Sim(T,S) = Σ(w_i × sim_i) / Σ w_i
 *    dihitung HANYA atas gabungan (union) gejala bernilai > 0 pada
 *    kasus baru (T) atau kasus lama (S). Bobot = gejala.bobot global.
 *  - Weighted Jaccard (metode alternatif sesuai pengaturan):
 *      J_w(T,S) = Σ(w_i × min(T_i,S_i)) / Σ(w_i × max(T_i,S_i))
 *    juga atas union gejala > 0 (gejala 0 pada keduanya tidak menambah).
 *  - sim_i lokal:
 *      biner           : 1 jika sama, selain itu 0
 *      skala 0-3       : 1 − |a − b| / 3
 *      durasi (0-3)    : 1 − |idx_T − idx_S| / 3
 *
 * Jalur keputusan dibaca dari tabel `pengaturan` (tidak di-hardcode):
 *   similarity >= threshold_reuse                      -> reuse / tidak_perlu
 *   threshold_revise <= similarity < threshold_reuse    -> revise / menunggu
 *   similarity < threshold_revise                       -> baru / menunggu
 */
class CBREngine
{
    public const METODE_VALID = ['nearest_neighbor', 'jaccard'];

    /* ============================================================
       BAGIAN MURNI (tanpa database) — dipakai PHPUnit
       ============================================================ */

    /** Kemiripan lokal satu gejala sesuai tipe inputnya (0.0–1.0). */
    public static function similarityLokal(string $tipe, int $a, int $b): float
    {
        if ($tipe === 'biner') {
            return $a === $b ? 1.0 : 0.0;
        }
        // skala & durasi sama-sama memakai indeks 0-3.
        return 1.0 - abs($a - $b) / 3.0;
    }

    /**
     * Gabungan id gejala yang bernilai > 0 pada T atau S.
     * Gejala bernilai 0 di kedua sisi TIDAK ikut (tidak menggelembungkan skor).
     *
     * @return int[]
     */
    public static function unionAktif(array $t, array $s): array
    {
        $union = [];
        foreach ($t as $id => $nilai) {
            if ((int) $nilai > 0) {
                $union[(int) $id] = true;
            }
        }
        foreach ($s as $id => $nilai) {
            if ((int) $nilai > 0) {
                $union[(int) $id] = true;
            }
        }
        return array_keys($union);
    }

    /**
     * Similarity terbobot Nearest Neighbor: Σ(w_i × sim_i) / Σ w_i.
     *
     * @param array<int,int>  $t    vektor kasus baru  [id_gejala => nilai]
     * @param array<int,int>  $s    vektor kasus lama  [id_gejala => nilai]
     * @param array<int,array> $meta [id_gejala => ['bobot'=>float,'tipe'=>string,...]]
     */
    public static function similarityTerbobot(array $t, array $s, array $meta): float
    {
        $pembilang = 0.0;
        $penyebut  = 0.0;
        foreach (self::unionAktif($t, $s) as $id) {
            $m = $meta[$id] ?? null;
            if ($m === null) {
                continue;
            }
            $w   = (float) ($m['bobot'] ?? 1.0);
            $sim = self::similarityLokal(
                (string) ($m['tipe'] ?? 'skala'),
                (int) ($t[$id] ?? 0),
                (int) ($s[$id] ?? 0)
            );
            $pembilang += $w * $sim;
            $penyebut  += $w;
        }
        return $penyebut > 0.0 ? $pembilang / $penyebut : 0.0;
    }

    /**
     * Weighted Jaccard: Σ(w_i × min(T_i,S_i)) / Σ(w_i × max(T_i,S_i)).
     * Sesuai PRD 3.3.2 poin 4 — tetap memakai bobot global w_i.
     */
    public static function similarityJaccard(array $t, array $s, array $meta): float
    {
        $pembilang = 0.0;
        $penyebut  = 0.0;
        foreach (self::unionAktif($t, $s) as $id) {
            $m = $meta[$id] ?? null;
            if ($m === null) {
                continue;
            }
            $w = (float) ($m['bobot'] ?? 1.0);
            $a = (int) ($t[$id] ?? 0);
            $b = (int) ($s[$id] ?? 0);
            $pembilang += $w * min($a, $b);
            $penyebut  += $w * max($a, $b);
        }
        return $penyebut > 0.0 ? $pembilang / $penyebut : 0.0;
    }

    /** Hitung similarity dengan metode terpilih (fallback: nearest_neighbor). */
    public static function hitung(string $metode, array $t, array $s, array $meta): float
    {
        return $metode === 'jaccard'
            ? self::similarityJaccard($t, $s, $meta)
            : self::similarityTerbobot($t, $s, $meta);
    }

    /**
     * Rincian kontribusi tiap gejala untuk transparansi halaman hasil
     * ("Lihat detail perhitungan").
     *
     * @return array<int,array{kode_gejala:string,nama_gejala:string,bobot:float,
     *   nilai_t:int,nilai_s:int,sim:float,kontribusi:float,penyebut:float}>
     */
    public static function kontribusi(string $metode, array $t, array $s, array $meta): array
    {
        $ids = self::unionAktif($t, $s);
        sort($ids);

        $rows = [];
        foreach ($ids as $id) {
            $m = $meta[$id] ?? null;
            if ($m === null) {
                continue;
            }
            $w = (float) ($m['bobot'] ?? 1.0);
            $a = (int) ($t[$id] ?? 0);
            $b = (int) ($s[$id] ?? 0);

            if ($metode === 'jaccard') {
                $sim       = max($a, $b) > 0 ? min($a, $b) / max($a, $b) : 0.0;
                $kontribusi = $w * min($a, $b);
                $penyebut   = $w * max($a, $b);
            } else {
                $sim        = self::similarityLokal((string) ($m['tipe'] ?? 'skala'), $a, $b);
                $kontribusi = $w * $sim;
                $penyebut   = $w;
            }

            $rows[] = [
                'kode_gejala' => (string) ($m['kode_gejala'] ?? ''),
                'nama_gejala' => (string) ($m['nama_gejala'] ?? ''),
                'bobot'       => $w,
                'nilai_t'     => $a,
                'nilai_s'     => $b,
                'sim'         => $sim,
                'kontribusi'  => $kontribusi,
                'penyebut'    => $penyebut,
            ];
        }
        return $rows;
    }

    /**
     * Tentukan jalur CBR dari similarity tertinggi + ambang batas.
     *
     * @return array{status_cbr:string,status_validasi:string}
     */
    public static function tentukanJalur(float $similarity, float $thresholdReuse, float $thresholdRevise): array
    {
        if ($similarity >= $thresholdReuse) {
            return ['status_cbr' => 'reuse',  'status_validasi' => 'tidak_perlu'];
        }
        if ($similarity >= $thresholdRevise) {
            return ['status_cbr' => 'revise', 'status_validasi' => 'menunggu'];
        }
        return ['status_cbr' => 'baru', 'status_validasi' => 'menunggu'];
    }

    /**
     * Agregasi skor per penyakit: skor penyakit = similarity TERTINGGI
     * dari kasus penyakit tersebut. Urut menurun, ambil top-K penyakit.
     */
    public static function agregasiPenyakit(array $skorKasus, int $topK): array
    {
        $perPenyakit = [];
        foreach ($skorKasus as $row) {
            $id = (int) $row['id_penyakit'];
            if (!isset($perPenyakit[$id])
                || (float) $row['similarity'] > (float) $perPenyakit[$id]['similarity']) {
                $perPenyakit[$id] = $row;
            }
        }

        $hasil = array_values($perPenyakit);
        usort($hasil, static function (array $a, array $b): int {
            return ((float) $b['similarity'] <=> (float) $a['similarity'])
                ?: strcmp((string) $a['kode_penyakit'], (string) $b['kode_penyakit']);
        });

        return $topK > 0 ? array_slice($hasil, 0, $topK) : $hasil;
    }

    /** Urutkan seluruh skor kasus menurun, ambil top-K kasus. */
    public static function topKKasus(array $skorKasus, int $topK): array
    {
        usort($skorKasus, static function (array $a, array $b): int {
            return ((float) $b['similarity'] <=> (float) $a['similarity'])
                ?: strcmp((string) $a['kode_kasus'], (string) $b['kode_kasus']);
        });
        return $topK > 0 ? array_slice($skorKasus, 0, $topK) : $skorKasus;
    }

    /**
     * Gejala darurat (is_redflag = 1) yang bernilai > 0 pada input pasien.
     * Selalu dikembalikan terlepas dari hasil CBR.
     *
     * @return array<int,array{kode_gejala:string,nama_gejala:string,nilai:int}>
     */
    public static function deteksiRedflag(array $t, array $meta): array
    {
        $rows = [];
        foreach ($t as $id => $nilai) {
            $nilai = (int) $nilai;
            if ($nilai <= 0) {
                continue;
            }
            $m = $meta[(int) $id] ?? null;
            if ($m === null || (int) ($m['is_redflag'] ?? 0) !== 1) {
                continue;
            }
            $rows[] = [
                'kode_gejala' => (string) ($m['kode_gejala'] ?? ''),
                'nama_gejala' => (string) ($m['nama_gejala'] ?? ''),
                'nilai'       => $nilai,
            ];
        }
        return $rows;
    }

    /**
     * Keputusan duplikat saat Retain: kasus baru dianggap duplikat bila
     * similarity tertinggi terhadap kasus aktif penyakit yang sama
     * >= threshold_duplikat (inklusif, sesuai PRD 3.3.5). Fungsi murni.
     *
     * @return array{duplikat:bool,similarity:float,threshold:float}
     */
    public static function putusanDuplikat(float $similarity, float $threshold): array
    {
        return [
            'duplikat'   => $similarity >= $threshold,
            'similarity' => $similarity,
            'threshold'  => $threshold,
        ];
    }

    /**
     * Bandingkan gejala pasien (T) dengan kasus rujukan (S) untuk halaman
     * hasil & validasi: cocok / beda tingkat / hanya pada satu sisi.
     *
     * @return array{cocok:array,beda:array,hanya_input:array,hanya_kasus:array}
     */
    public static function bandingkanGejala(array $t, array $s, array $meta): array
    {
        $hasil = ['cocok' => [], 'beda' => [], 'hanya_input' => [], 'hanya_kasus' => []];

        foreach ($meta as $idGejala => $m) {
            $a = (int) ($t[$idGejala] ?? 0);
            $b = (int) ($s[$idGejala] ?? 0);
            if ($a <= 0 && $b <= 0) {
                continue;
            }
            $baris = [
                'kode_gejala' => (string) ($m['kode_gejala'] ?? ''),
                'nama_gejala' => (string) ($m['nama_gejala'] ?? ''),
                'nilai_input' => $a,
                'nilai_kasus' => $b,
            ];
            if ($a > 0 && $b > 0) {
                $hasil[$a === $b ? 'cocok' : 'beda'][] = $baris;
            } elseif ($a > 0) {
                $hasil['hanya_input'][] = $baris;
            } else {
                $hasil['hanya_kasus'][] = $baris;
            }
        }

        return $hasil;
    }

    /* ============================================================
       BAGIAN DATABASE (Retrieve)
       ============================================================ */

    /** Metadata seluruh gejala aktif: [id_gejala => bobot/tipe/redflag/nama]. */
    public function metaAktif(): array
    {
        $rows = db()->query(
            'SELECT id_gejala, kode_gejala, nama_gejala, kategori, tipe_input, bobot, is_redflag
             FROM gejala WHERE is_active = 1 ORDER BY kode_gejala ASC'
        )->fetchAll();

        $meta = [];
        foreach ($rows as $g) {
            $meta[(int) $g['id_gejala']] = [
                'kode_gejala' => (string) $g['kode_gejala'],
                'nama_gejala' => (string) $g['nama_gejala'],
                'kategori'    => (string) $g['kategori'],
                'tipe'        => (string) $g['tipe_input'],
                'bobot'       => (float) $g['bobot'],
                'is_redflag'  => (int) $g['is_redflag'],
            ];
        }
        return $meta;
    }

    /**
     * RETRIEVE: hitung similarity input pasien terhadap SEMUA kasus aktif
     * (satu query JOIN), lalu kembalikan top-K kasus, agregat penyakit,
     * status jalur, dan daftar red-flag.
     *
     * @param array<int,int> $inputMentah [id_gejala => nilai]
     */
    public function retrieve(array $inputMentah, ?int $topK = null): array
    {
        $meta  = $this->metaAktif();
        $input = [];
        foreach ($inputMentah as $id => $nilai) {
            $id = (int) $id;
            if (!isset($meta[$id])) {
                continue; // gejala nonaktif/tidak dikenal diabaikan
            }
            $nilai = max(0, min(3, (int) $nilai));
            if ($meta[$id]['tipe'] === 'biner' && $nilai > 1) {
                $nilai = 1;
            }
            $input[$id] = $nilai;
        }

        // ---- pengaturan dinamis (jangan di-hardcode) ----
        $metode = (string) pengaturan('metode_similarity', 'nearest_neighbor');
        if (!in_array($metode, self::METODE_VALID, true)) {
            $metode = 'nearest_neighbor';
        }
        $thresholdReuse  = (float) pengaturan('threshold_reuse', '0.80');
        $thresholdRevise = (float) pengaturan('threshold_revise', '0.50');
        $topK = $topK ?? max(1, (int) pengaturan('top_k', '3'));

        // ---- satu query JOIN seluruh kasus aktif ----
        $stmt = db()->prepare(
            "SELECT k.id_kasus, k.kode_kasus, k.id_penyakit,
                    p.kode_penyakit, p.nama_penyakit, p.tingkat_urgensi,
                    dk.id_gejala, dk.nilai
             FROM kasus k
             JOIN penyakit p     ON p.id_penyakit = k.id_penyakit
             JOIN detail_kasus dk ON dk.id_kasus = k.id_kasus
             WHERE k.status = 'aktif' AND p.is_active = 1
             ORDER BY k.id_kasus ASC, dk.id_gejala ASC"
        );
        $stmt->execute();

        $kasus = [];
        foreach ($stmt->fetchAll() as $row) {
            $idKasus = (int) $row['id_kasus'];
            if (!isset($kasus[$idKasus])) {
                $kasus[$idKasus] = [
                    'id_kasus'        => $idKasus,
                    'kode_kasus'      => (string) $row['kode_kasus'],
                    'id_penyakit'     => (int) $row['id_penyakit'],
                    'kode_penyakit'   => (string) $row['kode_penyakit'],
                    'nama_penyakit'   => (string) $row['nama_penyakit'],
                    'tingkat_urgensi' => (string) $row['tingkat_urgensi'],
                    'gejala'          => [],
                ];
            }
            $kasus[$idKasus]['gejala'][(int) $row['id_gejala']] = (int) $row['nilai'];
        }

        // ---- skor similarity per kasus ----
        $skor = [];
        foreach ($kasus as $k) {
            $skor[] = [
                'id_kasus'        => $k['id_kasus'],
                'kode_kasus'      => $k['kode_kasus'],
                'id_penyakit'     => $k['id_penyakit'],
                'kode_penyakit'   => $k['kode_penyakit'],
                'nama_penyakit'   => $k['nama_penyakit'],
                'tingkat_urgensi' => $k['tingkat_urgensi'],
                'similarity'      => round(self::hitung($metode, $input, $k['gejala'], $meta), 4),
            ];
        }

        $kandidatKasus = self::topKKasus($skor, $topK);
        $kandidatPenyakit = self::agregasiPenyakit($skor, $topK);
        $teratas = $kandidatKasus[0] ?? null;
        $similarityTertinggi = $teratas === null ? null : (float) $teratas['similarity'];

        $jalur = $similarityTertinggi === null
            ? ['status_cbr' => 'baru', 'status_validasi' => 'menunggu']
            : self::tentukanJalur($similarityTertinggi, $thresholdReuse, $thresholdRevise);

        // Rincian kontribusi untuk kasus paling mirip.
        if ($teratas !== null) {
            $teratas['kontribusi'] = self::kontribusi(
                $metode,
                $input,
                $kasus[$teratas['id_kasus']]['gejala'],
                $meta
            );
        }

        return [
            'input'                => $input,
            'meta'                 => $meta,
            'metode'               => $metode,
            'threshold_reuse'      => $thresholdReuse,
            'threshold_revise'     => $thresholdRevise,
            'top_k'                => $topK,
            'status_cbr'           => $jalur['status_cbr'],
            'status_validasi'      => $jalur['status_validasi'],
            'similarity_tertinggi' => $similarityTertinggi,
            'kasus'                => $kandidatKasus,
            'penyakit'             => $kandidatPenyakit,
            'kasus_teratas'        => $teratas,
            'redflag'              => self::deteksiRedflag($input, $meta),
            'total_kasus_aktif'    => count($kasus),
        ];
    }

    /* ============================================================
       RETAIN (setelah validasi pakar)
       ============================================================ */

    /**
     * RETAIN: simpan kasus tervalidasi ke basis kasus, atau tambah
     * frekuensi kasus lama bila terdeteksi duplikat.
     *
     * PENTING: metode ini TIDAK membuka transaksi sendiri. Pemanggil
     * (Validasi::putuskan) wajib sudah berada di dalam transaksi PDO,
     * sehingga insert kasus + detail + validasi_kasus + log bersifat atomik.
     *
     * Deteksi duplikat memakai Nearest Neighbor terbobot (sama dengan
     * Kasus::similarityTertinggi) agar threshold_duplikat tetap konsisten.
     *
     * @param array<int,int> $vektor [id_gejala => nilai]
     * @return array{duplikat:bool,id_kasus:?int,kode_kasus:?string,
     *   id_kasus_duplikat:?int,kode_kasus_duplikat:?string,similarity:float,
     *   threshold:float,jumlah_kandidat:int,vektor:array}
     */
    public function retain(
        PDO $pdo,
        array $vektor,
        int $idPenyakit,
        int $idDiagnosa,
        int $idValidator
    ): array {
        $meta = $this->metaAktif();

        // Vektor dinormalkan & dilengkapi seluruh gejala aktif (0 bila kosong).
        $bersih = [];
        foreach ($meta as $idGejala => $m) {
            $nilai = max(0, min(3, (int) ($vektor[$idGejala] ?? 0)));
            if ($m['tipe'] === 'biner' && $nilai > 1) {
                $nilai = 1;
            }
            $bersih[(int) $idGejala] = $nilai;
        }

        // Kandidat: kasus aktif berpenyakit sama.
        $stmt = $pdo->prepare(
            "SELECT k.id_kasus, k.kode_kasus, dk.id_gejala, dk.nilai
             FROM kasus k
             JOIN detail_kasus dk ON dk.id_kasus = k.id_kasus
             WHERE k.id_penyakit = ? AND k.status = 'aktif'
             ORDER BY k.id_kasus ASC, dk.id_gejala ASC"
        );
        $stmt->execute([$idPenyakit]);

        $kandidat = [];
        foreach ($stmt->fetchAll() as $row) {
            $idKasus = (int) $row['id_kasus'];
            if (!isset($kandidat[$idKasus])) {
                $kandidat[$idKasus] = [
                    'id_kasus'   => $idKasus,
                    'kode_kasus' => (string) $row['kode_kasus'],
                    'gejala'     => [],
                ];
            }
            $kandidat[$idKasus]['gejala'][(int) $row['id_gejala']] = (int) $row['nilai'];
        }

        $terbaik = null;
        foreach ($kandidat as $k) {
            $sim = self::similarityTerbobot($bersih, $k['gejala'], $meta);
            if ($terbaik === null || $sim > $terbaik['similarity']) {
                $terbaik = [
                    'similarity' => $sim,
                    'id_kasus'   => $k['id_kasus'],
                    'kode_kasus' => $k['kode_kasus'],
                ];
            }
        }

        $threshold = (float) pengaturan('threshold_duplikat', '0.98');
        $putusan = self::putusanDuplikat((float) ($terbaik['similarity'] ?? 0.0), $threshold);

        // ---- Duplikat: tidak menambah kasus, cukup menambah frekuensi ----
        if ($terbaik !== null && $putusan['duplikat']) {
            $pdo->prepare(
                'UPDATE kasus SET frekuensi_digunakan = frekuensi_digunakan + 1 WHERE id_kasus = ?'
            )->execute([$terbaik['id_kasus']]);

            return [
                'duplikat'            => true,
                'id_kasus'            => null,
                'kode_kasus'          => null,
                'id_kasus_duplikat'   => $terbaik['id_kasus'],
                'kode_kasus_duplikat' => $terbaik['kode_kasus'],
                'similarity'          => round($terbaik['similarity'], 4),
                'threshold'           => $threshold,
                'jumlah_kandidat'     => count($kandidat),
                'vektor'              => $bersih,
            ];
        }

        // ---- Bukan duplikat: buat kasus baru (anonim, tanpa identitas) ----
        $idKasusBaru = (new Kasus())->createDalamTransaksi($pdo, [
            'id_penyakit'      => $idPenyakit,
            'sumber'           => 'retain',
            'status'           => 'aktif',
            'id_diagnosa_asal' => $idDiagnosa,
            'id_validator'     => $idValidator,
            'validated_at'     => date('Y-m-d H:i:s'),
        ], $bersih);

        $stmtKode = $pdo->prepare('SELECT kode_kasus FROM kasus WHERE id_kasus = ?');
        $stmtKode->execute([$idKasusBaru]);

        return [
            'duplikat'            => false,
            'id_kasus'            => $idKasusBaru,
            'kode_kasus'          => (string) $stmtKode->fetchColumn(),
            'id_kasus_duplikat'   => null,
            'kode_kasus_duplikat' => null,
            'similarity'          => round((float) ($terbaik['similarity'] ?? 0.0), 4),
            'threshold'           => $threshold,
            'jumlah_kandidat'     => count($kandidat),
            'vektor'              => $bersih,
        ];
    }

    /* ============================================================
       EVALUASI AKURASI LOOCV (Leave-One-Out Cross Validation, PRD 1.3)
       ============================================================ */

    /**
     * Evaluasi akurasi sistem CBR dengan metode Leave-One-Out Cross Validation (LOOCV).
     *
     * Setiap kasus aktif k diuji terhadap basis kasus yang tersisa (N - 1 kasus).
     * Menghitung:
     *   - Top-1 Accuracy (% kecocokan penyakit peringkat pertama, target PRD >= 85%)
     *   - Top-3 Accuracy (% penyakit aktual berada di 3 peringkat teratas, target PRD >= 95%)
     *   - Rata-rata Similarity prediksi teratas
     *   - Confusion Matrix (Penyakit Aktual vs Penyakit Prediksi)
     *   - Metrik per penyakit (Support, TP, FP, FN, Precision, Recall, F1-Score)
     *   - Rincian prediksi tiap kasus
     *
     * @param PDO|null $pdo
     * @return array{
     *   total_kasus: int,
     *   top1_hits: int,
     *   top1_accuracy: float,
     *   top3_hits: int,
     *   top3_accuracy: float,
     *   target_top1: float,
     *   target_top3: float,
     *   memenuhi_target_top1: bool,
     *   memenuhi_target_top3: bool,
     *   rata_similarity: float,
     *   daftar_penyakit: array<int, array>,
     *   confusion_matrix: array<int, array<int, int>>,
     *   metrik_penyakit: array<int, array>,
     *   rincian_kasus: array<int, array>
     * }
     */
    public function evaluasiLOOCV(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();
        $meta = $this->metaAktif();

        // 1. Ambil master penyakit aktif
        $stmtP = $pdo->query(
            "SELECT id_penyakit, kode_penyakit, nama_penyakit, tingkat_urgensi
             FROM penyakit
             WHERE is_active = 1
             ORDER BY id_penyakit ASC"
        );
        $penyakitMap = [];
        foreach ($stmtP->fetchAll() as $p) {
            $penyakitMap[(int) $p['id_penyakit']] = [
                'id_penyakit'     => (int) $p['id_penyakit'],
                'kode_penyakit'   => (string) $p['kode_penyakit'],
                'nama_penyakit'   => (string) $p['nama_penyakit'],
                'tingkat_urgensi' => (string) $p['tingkat_urgensi'],
            ];
        }

        // 2. Ambil seluruh kasus aktif beserta vektor gejalanya
        $stmtK = $pdo->query(
            "SELECT k.id_kasus, k.kode_kasus, k.id_penyakit,
                    dk.id_gejala, dk.nilai
             FROM kasus k
             JOIN detail_kasus dk ON dk.id_kasus = k.id_kasus
             WHERE k.status = 'aktif'
             ORDER BY k.id_kasus ASC, dk.id_gejala ASC"
        );

        $kasusList = [];
        foreach ($stmtK->fetchAll() as $row) {
            $id = (int) $row['id_kasus'];
            if (!isset($kasusList[$id])) {
                $kasusList[$id] = [
                    'id_kasus'    => $id,
                    'kode_kasus'  => (string) $row['kode_kasus'],
                    'id_penyakit' => (int) $row['id_penyakit'],
                    'gejala'      => [],
                ];
            }
            $kasusList[$id]['gejala'][(int) $row['id_gejala']] = (int) $row['nilai'];
        }

        $totalKasus = count($kasusList);
        if ($totalKasus < 2) {
            return [
                'total_kasus'          => $totalKasus,
                'top1_hits'            => 0,
                'top1_accuracy'        => 0.0,
                'top3_hits'            => 0,
                'top3_accuracy'        => 0.0,
                'target_top1'          => 0.85,
                'target_top3'          => 0.95,
                'memenuhi_target_top1' => false,
                'memenuhi_target_top3' => false,
                'rata_similarity'      => 0.0,
                'daftar_penyakit'      => $penyakitMap,
                'confusion_matrix'     => [],
                'metrik_penyakit'      => [],
                'rincian_kasus'        => [],
            ];
        }

        // Inisialisasi Confusion Matrix: [actual_id][pred_id] => 0
        $matrix = [];
        foreach ($penyakitMap as $actualId => $_a) {
            $matrix[$actualId] = [];
            foreach ($penyakitMap as $predId => $_b) {
                $matrix[$actualId][$predId] = 0;
            }
        }

        $top1Hits = 0;
        $top3Hits = 0;
        $sumSimilarity = 0.0;
        $rincian = [];

        // 3. Eksekusi LOOCV (Leave-One-Out)
        foreach ($kasusList as $idTest => $testCase) {
            $actualPenyakitId = $testCase['id_penyakit'];

            // Basis kasus latih: semua kasus aktif KECUALI testCase
            $trainCases = [];
            foreach ($kasusList as $idTrain => $trainCase) {
                if ($idTrain === $idTest) {
                    continue;
                }
                $trainCases[] = $trainCase;
            }

            // Hitung similarity terbobot ke setiap kasus latih
            $scores = [];
            foreach ($trainCases as $tc) {
                $sim = self::similarityTerbobot($testCase['gejala'], $tc['gejala'], $meta);
                $scores[] = [
                    'id_kasus'    => $tc['id_kasus'],
                    'kode_kasus'  => $tc['kode_kasus'],
                    'id_penyakit' => $tc['id_penyakit'],
                    'similarity'  => $sim,
                ];
            }

            // Urutkan skor tertinggi
            usort($scores, static fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

            // Agregasi penyakit: ambil similarity tertinggi per jenis penyakit
            $agregasi = [];
            foreach ($scores as $s) {
                $pId = $s['id_penyakit'];
                if (!isset($agregasi[$pId])) {
                    $agregasi[$pId] = [
                        'id_penyakit' => $pId,
                        'similarity'  => (float) $s['similarity'],
                        'kode_kasus'  => $s['kode_kasus'],
                    ];
                }
            }
            $peringkatPenyakit = array_values($agregasi);

            $pred1 = $peringkatPenyakit[0] ?? null;
            $pred1Id = $pred1['id_penyakit'] ?? 0;
            $pred1Sim = (float) ($pred1['similarity'] ?? 0.0);
            $sumSimilarity += $pred1Sim;

            // Ambil 3 kandidat teratas
            $top3 = array_slice($peringkatPenyakit, 0, 3);
            $top3Ids = array_column($top3, 'id_penyakit');

            $matchTop1 = ($pred1Id === $actualPenyakitId);
            $matchTop3 = in_array($actualPenyakitId, $top3Ids, true);

            if ($matchTop1) {
                $top1Hits++;
            }
            if ($matchTop3) {
                $top3Hits++;
            }

            // Catat di matriks konfusi
            if (isset($matrix[$actualPenyakitId][$pred1Id])) {
                $matrix[$actualPenyakitId][$pred1Id]++;
            } else {
                $matrix[$actualPenyakitId][$pred1Id] = 1;
            }

            // Rincian per kasus
            $top3Labels = [];
            foreach ($top3 as $t) {
                $pInfo = $penyakitMap[$t['id_penyakit']] ?? null;
                $pKode = $pInfo ? $pInfo['kode_penyakit'] : 'P?';
                $top3Labels[] = sprintf('%s (%.1f%%)', $pKode, $t['similarity'] * 100);
            }

            $actualInfo = $penyakitMap[$actualPenyakitId] ?? null;
            $pred1Info  = $penyakitMap[$pred1Id] ?? null;

            $rincian[] = [
                'id_kasus'           => $testCase['id_kasus'],
                'kode_kasus'         => $testCase['kode_kasus'],
                'id_penyakit_aktual' => $actualPenyakitId,
                'kode_aktual'        => $actualInfo['kode_penyakit'] ?? '—',
                'nama_aktual'        => $actualInfo['nama_penyakit'] ?? '—',
                'id_penyakit_pred'   => $pred1Id,
                'kode_pred'          => $pred1Info['kode_penyakit'] ?? '—',
                'nama_pred'          => $pred1Info['nama_penyakit'] ?? '—',
                'similarity_pred'    => round($pred1Sim, 4),
                'kasus_pencocok'     => $pred1['kode_kasus'] ?? '—',
                'match_top1'         => $matchTop1,
                'match_top3'         => $matchTop3,
                'top3_ringkasan'     => implode(', ', $top3Labels),
            ];
        }

        $top1Acc = round($top1Hits / $totalKasus, 4);
        $top3Acc = round($top3Hits / $totalKasus, 4);
        $avgSim  = round($sumSimilarity / $totalKasus, 4);

        // 4. Hitung Metrik per Penyakit (Support, TP, FP, FN, Precision, Recall, F1-Score)
        $metrikPenyakit = [];
        foreach ($penyakitMap as $pId => $p) {
            $tp = $matrix[$pId][$pId] ?? 0;

            // Support: Total aktual penyakit ini
            $support = 0;
            foreach ($matrix[$pId] as $_pPred => $count) {
                $support += $count;
            }

            // Predicted Total: Total penyakit ini diprediksi sebagai Top-1
            $predTotal = 0;
            foreach ($matrix as $_pAct => $row) {
                $predTotal += ($row[$pId] ?? 0);
            }

            $fp = $predTotal - $tp;
            $fn = $support - $tp;

            $precision = $predTotal > 0 ? round($tp / $predTotal, 4) : 0.0;
            $recall    = $support > 0 ? round($tp / $support, 4) : 0.0;
            $f1Score   = ($precision + $recall) > 0 ? round((2 * $precision * $recall) / ($precision + $recall), 4) : 0.0;

            $metrikPenyakit[$pId] = [
                'id_penyakit'   => $pId,
                'kode_penyakit' => $p['kode_penyakit'],
                'nama_penyakit' => $p['nama_penyakit'],
                'support'       => $support,
                'tp'            => $tp,
                'fp'            => $fp,
                'fn'            => $fn,
                'precision'     => $precision,
                'recall'        => $recall,
                'f1_score'      => $f1Score,
            ];
        }

        return [
            'total_kasus'          => $totalKasus,
            'top1_hits'            => $top1Hits,
            'top1_accuracy'        => $top1Acc,
            'top3_hits'            => $top3Hits,
            'top3_accuracy'        => $top3Acc,
            'target_top1'          => 0.85,
            'target_top3'          => 0.95,
            'memenuhi_target_top1' => ($top1Acc >= 0.85),
            'memenuhi_target_top3' => ($top3Acc >= 0.95),
            'rata_similarity'      => $avgSim,
            'daftar_penyakit'      => $penyakitMap,
            'confusion_matrix'     => $matrix,
            'metrik_penyakit'      => $metrikPenyakit,
            'rincian_kasus'        => $rincian,
        ];
    }
}
