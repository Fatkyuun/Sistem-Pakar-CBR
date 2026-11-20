<?php
declare(strict_types=1);

/**
 * app/controllers/PenyakitController.php — CRUD Master Penyakit.
 *
 * Halaman khusus Pakar/Admin (RBAC dicek server-side di konstruktor).
 * Aturan soft delete (PRD FR-A2): penyakit yang masih dipakai kasus
 * ATAU masih dirujuk riwayat (diagnosa/hasil/validasi) tidak boleh
 * dihapus permanen — hanya is_active = 0. Jumlah data terdampak
 * dikirim ke view untuk peringatan SweetAlert2.
 */
class PenyakitController extends Controller
{
    protected const ACTIONS = ['index', 'form', 'save', 'toggle', 'delete'];

    private Penyakit $model;

    public function __construct()
    {
        require_role(['pakar', 'admin']);
        $this->model = new Penyakit();
    }

    public function indexAction(): void
    {
        $this->view('penyakit/index', [
            'pageTitle'    => 'Master Penyakit',
            'halamanAktif' => 'penyakit',
            'rows'         => $this->model->list(),
            'jumlahAktif'  => $this->model->jumlahAktif(),
        ]);
    }

    public function formAction(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $p = null;
        $jumlahKasus = null;
        if ($id > 0) {
            $p = $this->model->find($id);
            if ($p === null) {
                respond_not_found();
            }
            $jumlahKasus = $this->model->jumlahKasus($id);
        }
        $this->view('penyakit/form', [
            'pageTitle'    => $p === null ? 'Tambah Penyakit' : 'Ubah Penyakit — ' . $p['kode_penyakit'],
            'halamanAktif' => 'penyakit',
            'p'            => $p,
            'errors'       => [],
            'old'          => [],
            'jumlahKasus'  => $jumlahKasus,
        ]);
    }

    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $id = max(0, (int) ($_POST['id'] ?? 0));

        $data = [
            'kode_penyakit'   => strtoupper(trim((string) ($_POST['kode_penyakit'] ?? ''))),
            'nama_penyakit'   => trim((string) ($_POST['nama_penyakit'] ?? '')),
            'deskripsi'       => trim((string) ($_POST['deskripsi'] ?? '')),
            'penyebab'        => trim((string) ($_POST['penyebab'] ?? '')),
            'solusi'          => trim((string) ($_POST['solusi'] ?? '')),
            'pencegahan'      => trim((string) ($_POST['pencegahan'] ?? '')),
            'tingkat_urgensi' => (string) ($_POST['tingkat_urgensi'] ?? 'ringan'),
            'is_active'       => !empty($_POST['is_active']) ? 1 : 0,
        ];

        $errors = [];
        if (!preg_match('/^[A-Z0-9]{1,10}$/', $data['kode_penyakit'])) {
            $errors[] = 'Kode wajib 1–10 karakter alfanumerik, contoh: P01.';
        } elseif ($this->model->findByKode($data['kode_penyakit'], $id) !== null) {
            $errors[] = 'Kode ' . $data['kode_penyakit'] . ' sudah dipakai penyakit lain.';
        }
        if ($data['nama_penyakit'] === '' || mb_strlen($data['nama_penyakit']) > 120) {
            $errors[] = 'Nama penyakit wajib diisi (maksimal 120 karakter).';
        }
        if (!in_array($data['tingkat_urgensi'], Penyakit::URGENSI, true)) {
            $errors[] = 'Tingkat urgensi tidak valid.';
        }

        // Bila gagal validasi: kembali ke form dengan input lama.
        if ($errors !== []) {
            $p = $id > 0 ? $this->model->find($id) : null;
            $this->view('penyakit/form', [
                'pageTitle'    => $id > 0 ? 'Ubah Penyakit' : 'Tambah Penyakit',
                'halamanAktif' => 'penyakit',
                'p'            => $p,
                'errors'       => $errors,
                'old'          => $data,
                'jumlahKasus'  => $id > 0 ? $this->model->jumlahKasus($id) : null,
            ]);
            return;
        }

        if ($id > 0) {
            $lama = $this->model->find($id);
            if ($lama === null) {
                respond_not_found();
            }
            $this->model->update($id, $data);
            $this->model->setAktif($id, (bool) $data['is_active']);
            log_aktivitas('penyakit_ubah', 'penyakit', $id, $lama, $data);
            flash('sukses', 'Penyakit ' . $data['kode_penyakit'] . ' berhasil diperbarui.');
        } else {
            $idBaru = $this->model->create($data);
            log_aktivitas('penyakit_tambah', 'penyakit', $idBaru, null, $data);
            flash('sukses', 'Penyakit ' . $data['kode_penyakit'] . ' berhasil ditambahkan.');
        }
        redirect('penyakit');
    }

    /** Soft delete / aktifkan kembali: hanya mengubah is_active. */
    public function toggleAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $p = $this->model->find($id);
        if ($p === null) {
            respond_not_found();
        }
        $baru = ((int) $p['is_active'] === 1) ? false : true;
        $this->model->setAktif($id, $baru);
        log_aktivitas($baru ? 'penyakit_aktifkan' : 'penyakit_nonaktif', 'penyakit', $id, ['is_active' => $p['is_active']], ['is_active' => $baru ? 1 : 0]);
        flash('sukses', 'Penyakit ' . $p['kode_penyakit'] . ($baru ? ' diaktifkan kembali.' : ' dinonaktifkan (soft delete).'));
        redirect('penyakit');
    }

    /**
     * Hapus dengan aturan integritas:
     *  - 0 kasus & 0 referensi riwayat → hapus permanen.
     *  - selain itu                    → soft delete (is_active = 0),
     *    dengan pesan jumlah kasus terdampak.
     */
    public function deleteAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $p = $this->model->find($id);
        if ($p === null) {
            respond_not_found();
        }

        $jumlahKasus = $this->model->jumlahKasus($id);
        $jumlahRef   = $this->model->jumlahReferensiRiwayat($id);

        if ($jumlahKasus === 0 && $jumlahRef === 0) {
            log_aktivitas('penyakit_hapus', 'penyakit', $id, $p, null);
            $this->model->deletePermanen($id);
            flash('sukses', 'Penyakit ' . $p['kode_penyakit'] . ' dihapus permanen (belum dipakai kasus atau riwayat apa pun).');
        } else {
            $this->model->setAktif($id, false);
            log_aktivitas('penyakit_nonaktif', 'penyakit', $id, ['is_active' => $p['is_active']], [
                'is_active'   => 0,
                'jumlah_kasus' => $jumlahKasus,
                'referensi'    => $jumlahRef,
            ]);
            flash('peringatan', 'Penyakit ' . $p['kode_penyakit'] . ' dipakai oleh ' . $jumlahKasus . ' kasus (+' . $jumlahRef
                . ' rekaman riwayat) — hanya dinonaktifkan, tidak dihapus permanen.');
        }
        redirect('penyakit');
    }
}
