<?php
declare(strict_types=1);

/**
 * app/controllers/EvaluasiController.php — evaluasi akurasi sistem CBR (PRD 1.3).
 *
 * Menghitung dan menyajikan pengujian Leave-One-Out Cross Validation (LOOCV)
 * atas 20 kasus aktif untuk mengukur Top-1 Accuracy, Top-3 Accuracy,
 * Confusion Matrix per penyakit, serta metrik Precision, Recall, dan F1-Score.
 */
class EvaluasiController extends Controller
{
    protected const ACTIONS = ['index'];

    public function __construct()
    {
        require_role(['pakar', 'admin']);
    }

    public function indexAction(): void
    {
        $engine = new CBREngine();
        $hasil = $engine->evaluasiLOOCV();

        $this->view('evaluasi/index', [
            'pageTitle'     => 'Evaluasi Akurasi CBR (LOOCV)',
            'halamanAktif'  => 'evaluasi',
            'useDataTables' => true,
            'evaluasi'      => $hasil,
        ]);
    }
}
