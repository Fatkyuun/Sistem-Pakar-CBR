<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * tests/CBREngineTest.php — uji mesin CBR (Retrieve & Reuse).
 *
 * Seluruh uji memakai fungsi MURNI (tanpa database) dengan data DUMMY,
 * sesuai AGENTS.md: "Tulis uji PHPUnit untuk CBREngine".
 *
 * Data meta gejala di bawah adalah DUMMY untuk pengujian, bukan data medis.
 */
final class CBREngineTest extends TestCase
{
    /** Meta gejala DUMMY: [id => kode, nama, tipe, bobot, redflag]. */
    private function meta(): array
    {
        return [
            1 => ['kode_gejala' => 'G01', 'nama_gejala' => 'Demam',            'tipe' => 'skala',  'bobot' => 6.0,  'is_redflag' => 0],
            2 => ['kode_gejala' => 'G10', 'nama_gejala' => 'Batuk',            'tipe' => 'durasi', 'bobot' => 8.0,  'is_redflag' => 0],
            3 => ['kode_gejala' => 'G14', 'nama_gejala' => 'Sesak Beraktivitas', 'tipe' => 'skala', 'bobot' => 9.0, 'is_redflag' => 0],
            4 => ['kode_gejala' => 'G12', 'nama_gejala' => 'Batuk Darah',      'tipe' => 'biner',  'bobot' => 10.0, 'is_redflag' => 1],
            5 => ['kode_gejala' => 'G15', 'nama_gejala' => 'Sesak Istirahat',  'tipe' => 'skala',  'bobot' => 10.0, 'is_redflag' => 1],
            6 => ['kode_gejala' => 'G18', 'nama_gejala' => 'Nyeri Dada',       'tipe' => 'skala',  'bobot' => 5.0,  'is_redflag' => 1],
        ];
    }

    /**
     * UJI WAJIB AGENTS.md: bobot demam 6, batuk 8, sesak 9;
     * pasien (2, 3, 1) vs kasus (2, 3, 2) => similarity 0,870.
     */
    public function testSimilarityTerbobotWajibAgents(): void
    {
        $t = [1 => 2, 2 => 3, 3 => 1];
        $s = [1 => 2, 2 => 3, 3 => 2];

        $sim = CBREngine::similarityTerbobot($t, $s, $this->meta());

        self::assertSame(0.870, round($sim, 3));
        // Rincian: demam 6×1 + batuk 8×1 + sesak 9×(2/3) = 20; total bobot 23.
        self::assertEqualsWithDelta(20 / 23, $sim, 1e-9);
    }

    /** Gejala bernilai 0 pada KEDUA sisi tidak boleh menggelembungkan skor. */
    public function testGejalaNolPadaKeduaSisiTidakMasukUnion(): void
    {
        $meta = $this->meta();
        $tanpaNol = CBREngine::similarityTerbobot([1 => 2], [1 => 1], $meta);
        $denganNol = CBREngine::similarityTerbobot([1 => 2, 6 => 0], [1 => 1, 6 => 0], $meta);

        self::assertSame([1], CBREngine::unionAktif([1 => 2, 6 => 0], [1 => 1, 6 => 0]));
        self::assertEqualsWithDelta($tanpaNol, $denganNol, 1e-9);
        self::assertEqualsWithDelta(1 - 1 / 3, $denganNol, 1e-9);
    }

    /** Gejala > 0 pada satu sisi tetap dihitung: sim 0 dan bobot masuk penyebut. */
    public function testGejalaHanyaPadaSatuSisiMenurunkanSkor(): void
    {
        $sim = CBREngine::similarityTerbobot([1 => 2], [1 => 2, 6 => 3], $this->meta());

        // (6×1 + 5×0) / (6+5) = 6/11
        self::assertEqualsWithDelta(6 / 11, $sim, 1e-9);
    }

    /** Gejala biner: sama = 1, berbeda = 0. */
    public function testKemiripanBiner(): void
    {
        $meta = $this->meta();

        self::assertSame(1.0, CBREngine::similarityLokal('biner', 1, 1));
        self::assertSame(0.0, CBREngine::similarityLokal('biner', 1, 0));
        self::assertSame(1.0, CBREngine::similarityTerbobot([4 => 1], [4 => 1], $meta));
        self::assertSame(0.0, CBREngine::similarityTerbobot([4 => 1], [4 => 0], $meta));
    }

    /** Durasi memakai indeks 0-3: selisih 2 tingkat => 1 − 2/3. */
    public function testKemiripanDurasi(): void
    {
        self::assertEqualsWithDelta(1 / 3, CBREngine::similarityLokal('durasi', 1, 3), 1e-9);
        self::assertEqualsWithDelta(1 / 3, CBREngine::similarityTerbobot([2 => 1], [2 => 3], $this->meta()), 1e-9);
        self::assertSame(1.0, CBREngine::similarityLokal('durasi', 3, 3));
    }

    /** Penentuan jalur ambang batas (inklusif di batas bawah). */
    public function testPenentuanJalurAmbangBatas(): void
    {
        self::assertSame(
            ['status_cbr' => 'reuse', 'status_validasi' => 'tidak_perlu'],
            CBREngine::tentukanJalur(0.80, 0.80, 0.50)
        );
        self::assertSame('reuse', CBREngine::tentukanJalur(0.95, 0.80, 0.50)['status_cbr']);

        self::assertSame(
            ['status_cbr' => 'revise', 'status_validasi' => 'menunggu'],
            CBREngine::tentukanJalur(0.7999, 0.80, 0.50)
        );
        self::assertSame('revise', CBREngine::tentukanJalur(0.50, 0.80, 0.50)['status_cbr']);

        self::assertSame(
            ['status_cbr' => 'baru', 'status_validasi' => 'menunggu'],
            CBREngine::tentukanJalur(0.4999, 0.80, 0.50)
        );
    }

    /** Weighted Jaccard: Σ(w·min) / Σ(w·max) — memakai bobot, bukan himpunan polos. */
    public function testJaccardTerbobot(): void
    {
        $meta = $this->meta();

        // T={G01:2, G18:0}, S={G01:1, G18:3}
        // num = 6·1 + 5·0 = 6 ; den = 6·2 + 5·3 = 27 -> 6/27 = 2/9
        $sim = CBREngine::similarityJaccard([1 => 2, 6 => 0], [1 => 1, 6 => 3], $meta);
        self::assertEqualsWithDelta(2 / 9, $sim, 1e-9);

        // Jaccard tanpa bobot akan memberi 1/2 = 0.5 — pastikan berbeda.
        self::assertNotEqualsWithDelta(0.5, $sim, 1e-6);

        // Vektor identik => 1.0.
        self::assertSame(1.0, CBREngine::similarityJaccard([1 => 2, 6 => 3], [1 => 2, 6 => 3], $meta));
        // Tanpa gejala > 0 sama sekali => 0.0 (tidak ada pembagi).
        self::assertSame(0.0, CBREngine::similarityJaccard([1 => 0], [1 => 0], $meta));
    }

    /** Deteksi red-flag hanya untuk gejala is_redflag=1 bernilai > 0. */
    public function testDeteksiRedflag(): void
    {
        $hasil = CBREngine::deteksiRedflag([1 => 3, 4 => 1, 6 => 0], $this->meta());

        self::assertCount(1, $hasil);
        self::assertSame('G12', $hasil[0]['kode_gejala']);
        self::assertSame(1, $hasil[0]['nilai']);

        self::assertSame([], CBREngine::deteksiRedflag([1 => 3, 4 => 0], $this->meta()));
    }

    /** Agregasi top-K penyakit memakai similarity tertinggi per penyakit. */
    public function testAgregasiPenyakitMemakaiSkorTertinggi(): void
    {
        $skor = [
            ['id_penyakit' => 1, 'kode_penyakit' => 'P01', 'nama_penyakit' => 'A', 'similarity' => 0.50],
            ['id_penyakit' => 1, 'kode_penyakit' => 'P01', 'nama_penyakit' => 'A', 'similarity' => 0.90],
            ['id_penyakit' => 2, 'kode_penyakit' => 'P02', 'nama_penyakit' => 'B', 'similarity' => 0.70],
            ['id_penyakit' => 3, 'kode_penyakit' => 'P03', 'nama_penyakit' => 'C', 'similarity' => 0.95],
        ];

        $top2 = CBREngine::agregasiPenyakit($skor, 2);

        self::assertCount(2, $top2);
        self::assertSame('P03', $top2[0]['kode_penyakit']);
        self::assertSame('P01', $top2[1]['kode_penyakit']);
        self::assertEqualsWithDelta(0.90, (float) $top2[1]['similarity'], 1e-9);

        // topKKasus mengurutkan menurun dan memotong K.
        $kasus = [];
        foreach ([0.4, 0.9, 0.6] as $i => $sim) {
            $kasus[] = ['id_kasus' => $i + 1, 'kode_kasus' => 'K00' . ($i + 1), 'similarity' => $sim];
        }
        $teratas = CBREngine::topKKasus($kasus, 2);
        self::assertCount(2, $teratas);
        self::assertSame('K002', $teratas[0]['kode_kasus']);
        self::assertSame('K003', $teratas[1]['kode_kasus']);
    }

    /** Kontribusi tiap gejala: Σ(w·sim) harus sama dengan similarity × Σw. */
    public function testKontribusiKonsistenDenganSimilarity(): void
    {
        $t = [1 => 2, 2 => 3, 3 => 1];
        $s = [1 => 2, 2 => 3, 3 => 2];
        $meta = $this->meta();

        $rows = CBREngine::kontribusi('nearest_neighbor', $t, $s, $meta);
        $jumlahKontribusi = array_sum(array_column($rows, 'kontribusi'));
        $jumlahBobot = array_sum(array_column($rows, 'penyebut'));
        $sim = CBREngine::similarityTerbobot($t, $s, $meta);

        self::assertCount(3, $rows);
        self::assertEqualsWithDelta($sim * $jumlahBobot, $jumlahKontribusi, 1e-9);
    }
}
