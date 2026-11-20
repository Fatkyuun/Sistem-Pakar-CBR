<?php
declare(strict_types=1);

/**
 * app/controllers/DashboardController.php — dashboard pakar/admin (FR-A1).
 *
 * Metrik diambil dari model (bukan ditulis di view):
 *   - Diagnosa::statistik()      : hari ini, bulan ini, rata-rata similarity
 *   - Validasi::statistik()      : menunggu, terima/revisi/tolak, akurasi
 *   - Kasus::jumlahAktif()       : ukuran basis kasus aktif
 *   - Diagnosa::distribusiPenyakit() : data grafik Chart.js
 */
class DashboardController extends Controller
{
    protected const ACTIONS = ['index'];

    public function __construct()
    {
        require_role(['pakar', 'admin']);
    }

    public function indexAction(): void
    {
        $diagnosa = new Diagnosa();
        $validasi = new Validasi();

        $this->view('dashboard/index', [
            'pageTitle'     => 'Dashboard',
            'halamanAktif'  => 'dashboard',
            'useDataTables' => false,
            'statDiagnosa' => $diagnosa->statistik(),
            'statValidasi' => $validasi->statistik(),
            'kasusAktif'   => (new Kasus())->jumlahAktif(),
            'distribusi'   => $diagnosa->distribusiPenyakit(8),
            'antrean'      => $validasi->antreanTerbaru(5),
        ]);
    }
}
