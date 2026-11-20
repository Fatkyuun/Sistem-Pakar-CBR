<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * tests/ValidasiTest.php — uji aturan Fase 4 yang MURNI (tanpa database):
 *   - Pengaturan::validasi()  : kombinasi ambang batas CBR
 *   - CBREngine::putusanDuplikat() : batas inklusif Retain
 *   - CBREngine::bandingkanGejala(): pengelompokan perbandingan gejala
 *
 * Data meta gejala DUMMY, bukan data medis.
 */
final class ValidasiTest extends TestCase
{
    private function meta(): array
    {
        return [
            1 => ['kode_gejala' => 'G01', 'nama_gejala' => 'Demam',  'tipe' => 'skala', 'bobot' => 6.0, 'is_redflag' => 0],
            2 => ['kode_gejala' => 'G10', 'nama_gejala' => 'Batuk',  'tipe' => 'skala', 'bobot' => 8.0, 'is_redflag' => 0],
            3 => ['kode_gejala' => 'G14', 'nama_gejala' => 'Sesak',  'tipe' => 'skala', 'bobot' => 9.0, 'is_redflag' => 0],
            4 => ['kode_gejala' => 'G12', 'nama_gejala' => 'Batuk Darah', 'tipe' => 'biner', 'bobot' => 10.0, 'is_redflag' => 1],
        ];
    }

    /* ============================================================
       PENGATURAN
       ============================================================ */

    private function pengaturanValid(): array
    {
        return [
            'threshold_reuse'    => '0.80',
            'threshold_revise'   => '0.50',
            'threshold_duplikat' => '0.98',
            'top_k'              => '3',
            'metode_similarity'  => 'nearest_neighbor',
        ];
    }

    public function testPengaturanValidTidakAdaGalat(): void
    {
        self::assertSame([], Pengaturan::validasi($this->pengaturanValid()));
    }

    public function testAmbangRevisiHarusLebihKecilDariReuse(): void
    {
        $nilai = $this->pengaturanValid();
        $nilai['threshold_revise'] = '0.90';

        $errors = Pengaturan::validasi($nilai);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Revisi', implode(' ', $errors));
    }

    public function testAmbangDuplikatTidakBolehDiBawahReuse(): void
    {
        $nilai = $this->pengaturanValid();
        $nilai['threshold_duplikat'] = '0.70';

        $errors = Pengaturan::validasi($nilai);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Duplikat', implode(' ', $errors));
    }

    public function testAmbangDiLuarRentangDitolak(): void
    {
        foreach ([['threshold_reuse', '0'], ['threshold_reuse', '1.01'], ['threshold_revise', '-0.2']] as [$kunci, $isi]) {
            $nilai = $this->pengaturanValid();
            $nilai[$kunci] = $isi;
            self::assertNotEmpty(Pengaturan::validasi($nilai), $kunci . ' = ' . $isi . ' seharusnya ditolak');
        }
    }

    public function testTopKHarusBulatSatuSampaiSepuluh(): void
    {
        foreach (['0', '11', '2.5', 'abc', ''] as $topK) {
            $nilai = $this->pengaturanValid();
            $nilai['top_k'] = $topK;
            self::assertNotEmpty(Pengaturan::validasi($nilai), 'top_k ' . $topK . ' seharusnya ditolak');
        }

        foreach (['1', '5', '10'] as $topK) {
            $nilai = $this->pengaturanValid();
            $nilai['top_k'] = $topK;
            self::assertSame([], Pengaturan::validasi($nilai), 'top_k ' . $topK . ' seharusnya diterima');
        }
    }

    public function testMetodeSimilarityTidakDikenalDitolak(): void
    {
        $nilai = $this->pengaturanValid();
        $nilai['metode_similarity'] = 'cosine';
        self::assertNotEmpty(Pengaturan::validasi($nilai));
    }

    public function testRapikanMembulatkanDuaDesimal(): void
    {
        $rapi = Pengaturan::rapikan([
            'threshold_reuse'    => '0.8',
            'threshold_revise'   => '0.456',
            'threshold_duplikat' => '0.99',
            'top_k'              => '20',
            'metode_similarity'  => 'jaccard',
        ]);

        self::assertSame('0.80', $rapi['threshold_reuse']);
        self::assertSame('0.46', $rapi['threshold_revise']);
        self::assertSame('10', $rapi['top_k']);
        self::assertSame('jaccard', $rapi['metode_similarity']);
    }

    /* ============================================================
       DUPLIKAT RETAIN
       ============================================================ */

    public function testPutusanDuplikatBatasInklusif(): void
    {
        $batas = CBREngine::putusanDuplikat(0.98, 0.98);
        self::assertTrue($batas['duplikat'], 'similarity == threshold harus dianggap duplikat');

        $lebih = CBREngine::putusanDuplikat(0.985, 0.98);
        self::assertTrue($lebih['duplikat']);

        $kurang = CBREngine::putusanDuplikat(0.9799, 0.98);
        self::assertFalse($kurang['duplikat']);
    }

    /* ============================================================
       PERBANDINGAN GEJALA (halaman hasil & validasi)
       ============================================================ */

    public function testBandingkanGejalaMengelompokkanEmpatKasus(): void
    {
        $t = [1 => 2, 2 => 3, 3 => 1];
        $s = [1 => 2, 2 => 1, 4 => 3, 5 => 3]; // gejala 5 tidak ada di meta

        $hasil = CBREngine::bandingkanGejala($t, $s, $this->meta());

        self::assertCount(1, $hasil['cocok']);
        self::assertSame('G01', $hasil['cocok'][0]['kode_gejala']);

        self::assertCount(1, $hasil['beda']);
        self::assertSame('G10', $hasil['beda'][0]['kode_gejala']);

        self::assertCount(1, $hasil['hanya_input']);
        self::assertSame('G14', $hasil['hanya_input'][0]['kode_gejala']);

        self::assertCount(1, $hasil['hanya_kasus']);
        self::assertSame('G12', $hasil['hanya_kasus'][0]['kode_gejala'],
            'gejala 5 tidak ada di meta sehingga diabaikan');
    }

    public function testBandingkanGejalaMengabaikanNilaiNolKeduanya(): void
    {
        $hasil = CBREngine::bandingkanGejala([], [], $this->meta());
        foreach ($hasil as $kelompok) {
            self::assertSame([], $kelompok);
        }
    }
}
