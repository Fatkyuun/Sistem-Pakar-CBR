<?php
declare(strict_types=1);

/**
 * app/controllers/GejalaController.php — CRUD Master Gejala.
 *
 * Halaman khusus Pakar/Admin (RBAC dicek server-side di konstruktor).
 * Aturan soft delete (PRD FR-A3): gejala yang masih dipakai kasus
 * (detail_kasus) ATAU masih dirujuk riwayat diagnosa (detail_diagnosa)
 * tidak boleh dihapus permanen — hanya is_active = 0. Menonaktifkan
 * gejala menyembunyikannya dari formulir tanpa merusak kasus lama.
 */
class GejalaController extends Controller
{
    protected const ACTIONS = ['index', 'form', 'save', 'toggle', 'delete'];

    private Gejala $model;

    public function __construct()
    {
        require_role(['pakar', 'admin']);
        $this->model = new Gejala();
    }

    public function indexAction(): void
    {
        $this->view('gejala/index', [
            'pageTitle'    => 'Master Gejala',
            'halamanAktif' => 'gejala',
            'rows'         => $this->model->list(),
            'jumlahAktif'  => $this->model->jumlahAktif(),
        ]);
    }

    public function formAction(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $g = null;
        if ($id > 0) {
            $g = $this->model->find($id);
            if ($g === null) {
                respond_not_found();
            }
        }
        $this->view('gejala/form', [
            'pageTitle'    => $g === null ? 'Tambah Gejala' : 'Ubah Gejala — ' . $g['kode_gejala'],
            'halamanAktif' => 'gejala',
            'g'            => $g,
            'errors'       => [],
            'old'          => [],
            'kategori'     => $this->model->listKategori(),
        ]);
    }

    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $id = max(0, (int) ($_POST['id'] ?? 0));

        // Data lama dipakai untuk membandingkan tipe input (lihat validasi).
        $lama = null;
        if ($id > 0) {
            $lama = $this->model->find($id);
            if ($lama === null) {
                respond_not_found();
            }
        }

        $bobot = $_POST['bobot'] ?? '';
        $data = [
            'kode_gejala' => strtoupper(trim((string) ($_POST['kode_gejala'] ?? ''))),
            'nama_gejala' => trim((string) ($_POST['nama_gejala'] ?? '')),
            'deskripsi'   => trim((string) ($_POST['deskripsi'] ?? '')),
            'kategori'    => trim((string) ($_POST['kategori'] ?? '')),
            'tipe_input'  => (string) ($_POST['tipe_input'] ?? 'biner'),
            'bobot'       => is_scalar($bobot) && is_numeric($bobot) ? round((float) $bobot, 2) : 0.0,
            'is_redflag'  => !empty($_POST['is_redflag']) ? 1 : 0,
            'is_active'   => !empty($_POST['is_active']) ? 1 : 0,
        ];

        $errors = [];
        if (!preg_match('/^[A-Z0-9]{1,10}$/', $data['kode_gejala'])) {
            $errors[] = 'Kode wajib 1–10 karakter alfanumerik, contoh: G01.';
        } elseif ($this->model->findByKode($data['kode_gejala'], $id) !== null) {
            $errors[] = 'Kode ' . $data['kode_gejala'] . ' sudah dipakai gejala lain.';
        }
        if ($data['nama_gejala'] === '' || mb_strlen($data['nama_gejala']) > 150) {
            $errors[] = 'Nama gejala wajib diisi (maksimal 150 karakter).';
        }
        if ($data['kategori'] === '' || mb_strlen($data['kategori']) > 50) {
            $errors[] = 'Kategori wajib diisi (maksimal 50 karakter).';
        }
        if (!in_array($data['tipe_input'], Gejala::TIPE_INPUT, true)) {
            $errors[] = 'Tipe input tidak valid.';
        } elseif ($lama !== null && (string) $lama['tipe_input'] !== $data['tipe_input']) {
            // Mengubah tipe input mengubah arti angka yang sudah tersimpan
            // (mis. biner "1" vs skala "1"), jadi hanya boleh selama gejala
            // belum dipakai kasus maupun riwayat diagnosa.
            $dipakai = $this->model->jumlahKasus($id) + $this->model->jumlahReferensiRiwayat($id);
            if ($dipakai > 0) {
                $errors[] = 'Tipe input tidak dapat diubah karena gejala ini sudah dipakai '
                    . $dipakai . ' rekaman (kasus/riwayat). Nonaktifkan gejala ini lalu buat gejala baru '
                    . 'bila perlu tipe input berbeda.';
            }
        }
        if ($data['bobot'] < 1.0 || $data['bobot'] > 10.0) {
            $errors[] = 'Bobot global wajib bernilai 1–10 (batas schema CHECK).';
        }

        if ($errors !== []) {
            $g = $id > 0 ? $this->model->find($id) : null;
            $this->view('gejala/form', [
                'pageTitle'    => $id > 0 ? 'Ubah Gejala' : 'Tambah Gejala',
                'halamanAktif' => 'gejala',
                'g'            => $g,
                'errors'       => $errors,
                'old'          => $data,
                'kategori'     => $this->model->listKategori(),
            ]);
            return;
        }

        if ($id > 0) {
            $this->model->update($id, $data);
            log_aktivitas('gejala_ubah', 'gejala', $id, $lama, $data);
            flash('sukses', 'Gejala ' . $data['kode_gejala'] . ' berhasil diperbarui.');
        } else {
            $idBaru = $this->model->create($data);
            log_aktivitas('gejala_tambah', 'gejala', $idBaru, null, $data);
            flash('sukses', 'Gejala ' . $data['kode_gejala'] . ' berhasil ditambahkan.');
        }
        redirect('gejala');
    }

    /** Soft delete / aktifkan kembali: hanya mengubah is_active. */
    public function toggleAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $g = $this->model->find($id);
        if ($g === null) {
            respond_not_found();
        }
        $baru = ((int) $g['is_active'] === 1) ? false : true;
        $this->model->setAktif($id, $baru);
        log_aktivitas($baru ? 'gejala_aktifkan' : 'gejala_nonaktif', 'gejala', $id, ['is_active' => $g['is_active']], ['is_active' => $baru ? 1 : 0]);
        flash('sukses', 'Gejala ' . $g['kode_gejala'] . ($baru ? ' diaktifkan kembali.' : ' dinonaktifkan (soft delete).'));
        redirect('gejala');
    }

    /**
     * Hapus dengan aturan integritas: 0 kasus & 0 referensi riwayat →
     * hapus permanen; selain itu → soft delete + pesan data terdampak.
     */
    public function deleteAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $g = $this->model->find($id);
        if ($g === null) {
            respond_not_found();
        }

        $jumlahKasus = $this->model->jumlahKasus($id);
        $jumlahRef   = $this->model->jumlahReferensiRiwayat($id);

        if ($jumlahKasus === 0 && $jumlahRef === 0) {
            log_aktivitas('gejala_hapus', 'gejala', $id, $g, null);
            $this->model->deletePermanen($id);
            flash('sukses', 'Gejala ' . $g['kode_gejala'] . ' dihapus permanen (belum dipakai kasus atau riwayat apa pun).');
        } else {
            $this->model->setAktif($id, false);
            log_aktivitas('gejala_nonaktif', 'gejala', $id, ['is_active' => $g['is_active']], [
                'is_active'     => 0,
                'jumlah_kasus'  => $jumlahKasus,
                'referensi'     => $jumlahRef,
            ]);
            flash('peringatan', 'Gejala ' . $g['kode_gejala'] . ' dipakai oleh ' . $jumlahKasus . ' kasus (+' . $jumlahRef
                . ' rekaman riwayat) — hanya dinonaktifkan, tidak dihapus permanen.');
        }
        redirect('gejala');
    }
}
