<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * tests/EvaluasiTest.php — uji evaluasi akurasi Leave-One-Out Cross Validation (LOOCV).
 *
 * Menguji CBREngine::evaluasiLOOCV():
 *   - integritas struktur hasil evaluasi (metrik utama, confusion matrix, metrik per penyakit, rincian kasus),
 *   - konsistensi matematika:
 *       * total kasus = 20,
 *       * top-3 accuracy >= top-1 accuracy,
 *       * total sel confusion matrix = total kasus,
 *       * jumlah support per penyakit = total kasus,
 *       * precision, recall, f1-score berada pada rentang [0.0, 1.0].
 */
final class EvaluasiTest extends TestCase
{
    private CBREngine $engine;

    protected function setUp(): void
    {
        $this->engine = new CBREngine();
    }

    public function testEvaluasiLOOCVMenghasilkanStrukturDanMetrikValid(): void
    {
        $hasil = $this->engine->evaluasiLOOCV();

        // 1. Verifikasi metrik agregat
        self::assertIsArray($hasil);
        self::assertSame(20, $hasil['total_kasus'], 'Evaluasi LOOCV harus memproses 20 kasus aktif.');
        self::assertGreaterThanOrEqual(0, $hasil['top1_hits']);
        self::assertLessThanOrEqual(20, $hasil['top1_hits']);
        self::assertGreaterThanOrEqual(0, $hasil['top3_hits']);
        self::assertLessThanOrEqual(20, $hasil['top3_hits']);

        // Top-3 accuracy secara matematis harus >= Top-1 accuracy
        self::assertGreaterThanOrEqual(
            $hasil['top1_accuracy'],
            $hasil['top3_accuracy'],
            'Top-3 Accuracy harus lebih besar atau sama dengan Top-1 Accuracy.'
        );

        self::assertGreaterThan(0.0, $hasil['rata_similarity']);
        self::assertLessThanOrEqual(1.0, $hasil['rata_similarity']);

        // Target PRD 1.3
        self::assertSame(0.85, $hasil['target_top1']);
        self::assertSame(0.95, $hasil['target_top3']);
        self::assertIsBool($hasil['memenuhi_target_top1']);
        self::assertIsBool($hasil['memenuhi_target_top3']);

        // 2. Verifikasi Confusion Matrix
        self::assertIsArray($hasil['confusion_matrix']);
        $totalSelMatrix = 0;
        foreach ($hasil['confusion_matrix'] as $actualId => $predRow) {
            self::assertIsArray($predRow);
            foreach ($predRow as $predId => $count) {
                self::assertGreaterThanOrEqual(0, $count);
                $totalSelMatrix += $count;
            }
        }
        self::assertSame(20, $totalSelMatrix, 'Jumlah seluruh elemen pada Confusion Matrix harus sama dengan total kasus (20).');

        // 3. Verifikasi Metrik per Penyakit
        self::assertIsArray($hasil['metrik_penyakit']);
        $totalSupport = 0;
        foreach ($hasil['metrik_penyakit'] as $pId => $m) {
            self::assertArrayHasKey('kode_penyakit', $m);
            self::assertArrayHasKey('nama_penyakit', $m);
            self::assertArrayHasKey('support', $m);
            self::assertArrayHasKey('tp', $m);
            self::assertArrayHasKey('fp', $m);
            self::assertArrayHasKey('fn', $m);
            self::assertArrayHasKey('precision', $m);
            self::assertArrayHasKey('recall', $m);
            self::assertArrayHasKey('f1_score', $m);

            self::assertGreaterThanOrEqual(0.0, $m['precision']);
            self::assertLessThanOrEqual(1.0, $m['precision']);
            self::assertGreaterThanOrEqual(0.0, $m['recall']);
            self::assertLessThanOrEqual(1.0, $m['recall']);
            self::assertGreaterThanOrEqual(0.0, $m['f1_score']);
            self::assertLessThanOrEqual(1.0, $m['f1_score']);

            $totalSupport += $m['support'];
        }
        self::assertSame(20, $totalSupport, 'Total support per penyakit harus berjumlah 20.');

        // 4. Verifikasi Rincian per Kasus
        self::assertCount(20, $hasil['rincian_kasus']);
        foreach ($hasil['rincian_kasus'] as $r) {
            self::assertNotEmpty($r['kode_kasus']);
            self::assertNotEmpty($r['kode_aktual']);
            self::assertNotEmpty($r['kode_pred']);
            self::assertGreaterThan(0.0, $r['similarity_pred']);
            self::assertIsBool($r['match_top1']);
            self::assertIsBool($r['match_top3']);
            self::assertNotEmpty($r['top3_ringkasan']);
        }
    }
}
