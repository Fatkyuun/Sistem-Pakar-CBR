<?php
declare(strict_types=1);

/**
 * app/controllers/UserController.php — manajemen pengguna & log aktivitas
 * (FR-A7, khusus admin).
 *
 * Aksi:
 *   index  (GET)  daftar pengguna + statistik peran + log terbaru
 *   form   (GET)  formulir tambah akun pakar/admin
 *   save   (POST) simpan akun baru (password_hash, email unik)
 *   toggle (POST) aktif/nonaktifkan akun (tidak boleh akun sendiri)
 *   reset  (GET/POST) reset kata sandi pengguna
 */
class UserController extends Controller
{
    protected const ACTIONS = ['index', 'form', 'save', 'toggle', 'reset'];

    private User $model;

    public function __construct()
    {
        require_role(['admin']);
        $this->model = new User();
    }

    public function indexAction(): void
    {
        $this->view('users/index', [
            'pageTitle'    => 'Manajemen Pengguna',
            'halamanAktif' => 'users',
            'rows'         => $this->model->list(),
            'stat'         => $this->model->statistikPeran(),
            'log'          => (new LogAktivitas())->terbaru(20),
            'jumlahLog'    => (new LogAktivitas())->jumlah(),
        ]);
    }

    public function formAction(): void
    {
        $this->renderForm([], []);
    }

    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $old = [
            'nama'  => trim((string) ($_POST['nama'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'role'  => (string) ($_POST['role'] ?? ''),
        ];
        $password   = (string) ($_POST['password'] ?? '');
        $konfirmasi = (string) ($_POST['password_confirm'] ?? '');

        $errors = $this->validasiAkun($old, $password, $konfirmasi, 0);
        if ($errors !== []) {
            $this->renderForm($old, $errors);
            return;
        }

        $id = $this->model->create([
            'nama'          => $old['nama'],
            'email'         => $old['email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $old['role'],
            'is_active'     => 1,
        ]);
        log_aktivitas('user_tambah', 'users', $id, null, [
            'email' => $old['email'],
            'role'  => $old['role'],
        ]);
        flash('sukses', 'Akun ' . $old['role'] . ' baru berhasil dibuat.');
        redirect('users');
    }

    public function toggleAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        $target = $this->model->find($id);
        if ($target === null) {
            respond_not_found();
        }

        $saya = (int) current_user()['id_user'];
        if ($id === $saya) {
            flash('galat', 'Anda tidak dapat menonaktifkan akun sendiri.');
            redirect('users');
        }

        $aktif = (int) $target['is_active'] !== 1;
        $this->model->setActive($id, $aktif);
        log_aktivitas($aktif ? 'user_aktif' : 'user_nonaktif', 'users', $id, null, [
            'email' => $target['email'],
            'role'  => $target['role'],
        ]);
        flash('sukses', 'Akun ' . $target['email'] . ' berhasil ' . ($aktif ? 'diaktifkan' : 'dinonaktifkan') . '.');
        redirect('users');
    }

    public function resetAction(): void
    {
        $id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
        $target = $this->model->find($id);
        if ($target === null) {
            respond_not_found();
        }

        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();

            $password   = (string) ($_POST['password'] ?? '');
            $konfirmasi = (string) ($_POST['password_confirm'] ?? '');
            if (strlen($password) < 8) {
                $errors[] = 'Kata sandi minimal 8 karakter.';
            }
            if ($password !== $konfirmasi) {
                $errors[] = 'Konfirmasi kata sandi tidak sama.';
            }

            if ($errors === []) {
                $this->model->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
                log_aktivitas('user_reset_sandi', 'users', $id, null, ['email' => $target['email']]);
                flash('sukses', 'Kata sandi ' . $target['email'] . ' berhasil direset.');
                redirect('users');
            }
        }

        $this->view('users/reset', [
            'pageTitle'    => 'Reset Kata Sandi',
            'halamanAktif' => 'users',
            'useDataTables' => false,
            'target'       => $target,
            'errors'       => $errors,
        ]);
    }

    /* ============================================================
       BANTUAN
       ============================================================ */

    private function renderForm(array $old, array $errors): void
    {
        $this->view('users/form', [
            'pageTitle'    => 'Tambah Akun Staf',
            'halamanAktif' => 'users',
            'useDataTables' => false,
            'old'          => $old,
            'errors'       => $errors,
        ]);
    }

    private function validasiAkun(array $old, string $password, string $konfirmasi, int $kecualiId): array
    {
        $errors = [];

        if ($old['nama'] === '' || mb_strlen($old['nama']) > 100) {
            $errors[] = 'Nama wajib diisi (maksimal 100 karakter).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) {
            $errors[] = 'Format email tidak valid (maksimal 120 karakter).';
        } elseif ($this->model->emailExists($old['email'], $kecualiId)) {
            $errors[] = 'Email sudah dipakai akun lain.';
        }
        if (!in_array($old['role'], ['pakar', 'admin'], true)) {
            $errors[] = 'Peran akun harus Pakar atau Admin.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Kata sandi minimal 8 karakter.';
        }
        if ($password !== $konfirmasi) {
            $errors[] = 'Konfirmasi kata sandi tidak sama.';
        }

        return $errors;
    }
}
