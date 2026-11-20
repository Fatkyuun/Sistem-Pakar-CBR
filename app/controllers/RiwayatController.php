<?php
declare(strict_types=1);

/**
 * app/controllers/RiwayatController.php — riwayat diagnosa pasien (FR-U4).
 *
 * Semua query difilter id_user milik sesi (anti-IDOR); pasien tidak pernah
 * dapat membuka atau menghapus riwayat pengguna lain. Hapus hanya untuk
 * riwayat yang belum divalidasi pakar (lihat Diagnosa::hapusMilik).
 */
class RiwayatController extends Controller
{
    protected const ACTIONS = ['index', 'hapus'];

    private Diagnosa $model;

    public function __construct()
    {
        require_login();
        $this->model = new Diagnosa();
    }

    public function indexAction(): void
    {
        $user   = current_user();
        $idUser = (int) $user['id_user'];

        $filter = [
            'dari'        => $this->tanggalValid((string) ($_GET['dari'] ?? '')),
            'sampai'      => $this->tanggalValid((string) ($_GET['sampai'] ?? '')),
            'id_penyakit' => max(0, (int) ($_GET['penyakit'] ?? 0)),
        ];

        $perHalaman = 10;
        $total      = $this->model->hitungRiwayat($idUser, $filter);
        $totalHal   = max(1, (int) ceil($total / $perHalaman));
        $hal        = min($totalHal, max(1, (int) ($_GET['hal'] ?? 1)));

        $rows = $this->model->riwayatByUser(
            $idUser,
            $filter,
            $perHalaman,
            ($hal - 1) * $perHalaman
        );

        $this->view('diagnosa/riwayat', [
            'pageTitle' => 'Riwayat Saya',
            'rows'      => $rows,
            'filter'    => $filter,
            'total'     => $total,
            'hal'       => $hal,
            'totalHal'  => $totalHal,
            'penyakitList' => (new Penyakit())->listAktif(),
        ]);
    }

    public function hapusAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $id     = max(0, (int) ($_POST['id'] ?? 0));
        $idUser = (int) current_user()['id_user'];

        if ($this->model->findMilik($id, $idUser) === null) {
            respond_not_found(); // bukan milik pengguna: jangan bocorkan keberadaan
        }

        try {
            $this->model->hapusMilik($id, $idUser);
            flash('sukses', 'Riwayat diagnosa berhasil dihapus.');
        } catch (DomainException $e) {
            flash('galat', $e->getMessage());
        } catch (Throwable $e) {
            flash('galat', 'Riwayat gagal dihapus: ' . $e->getMessage());
        }

        redirect('riwayat');
    }

    /** Terima hanya tanggal Y-m-d yang sah; selain itu diabaikan. */
    private function tanggalValid(string $teks): string
    {
        $teks = trim($teks);
        if ($teks === '') {
            return '';
        }
        $d = DateTime::createFromFormat('Y-m-d', $teks);
        return $d !== false && $d->format('Y-m-d') === $teks ? $teks : '';
    }
}
