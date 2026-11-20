<?php
declare(strict_types=1);

/**
 * app/controllers/PengaturanController.php — parameter CBR (FR-A7).
 *
 * Nilai dibaca/ditulis dari tabel `pengaturan`; tidak ada ambang yang
 * di-hardcode. Validasi logis memakai Pengaturan::validasi() (fungsi murni).
 */
class PengaturanController extends Controller
{
    protected const ACTIONS = ['index', 'save'];

    private Pengaturan $model;

    public function __construct()
    {
        require_role(['pakar', 'admin']);
        $this->model = new Pengaturan();
    }

    public function indexAction(): void
    {
        $this->render($this->model->semua(), []);
    }

    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $input  = [
            'threshold_reuse'    => (string) ($_POST['threshold_reuse'] ?? ''),
            'threshold_revise'   => (string) ($_POST['threshold_revise'] ?? ''),
            'threshold_duplikat' => (string) ($_POST['threshold_duplikat'] ?? ''),
            'top_k'              => (string) ($_POST['top_k'] ?? ''),
            'metode_similarity'  => (string) ($_POST['metode_similarity'] ?? ''),
        ];

        $errors = Pengaturan::validasi($input);
        if ($errors !== []) {
            $this->render($input, $errors);
            return;
        }

        $perubahan = $this->model->simpan(Pengaturan::rapikan($input));

        if ($perubahan === []) {
            flash('info', 'Tidak ada parameter yang berubah.');
        } else {
            log_aktivitas('pengaturan_ubah', 'pengaturan', null, null, [
                'perubahan' => $perubahan,
            ]);
            flash('sukses', count($perubahan) . ' parameter CBR berhasil diperbarui.');
        }

        redirect('pengaturan');
    }

    private function render(array $nilai, array $errors): void
    {
        $this->view('pengaturan/index', [
            'pageTitle'    => 'Pengaturan CBR',
            'halamanAktif' => 'pengaturan',
            'useDataTables' => false,
            'nilai'        => $nilai,
            'errors'       => $errors,
        ]);
    }
}
