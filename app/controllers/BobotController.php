<?php
declare(strict_types=1);

/**
 * app/controllers/BobotController.php — Pengaturan Bobot Global Gejala (PRD FR-A4).
 *
 * Versi ini memakai BOBOT GLOBAL (gejala.bobot, skala 1-10) sesuai keputusan
 * AGENTS.md; tabel bobot_gejala_penyakit tetap tidak dipakai. Fitur P2
 * "simulasi dampak" sengaja TIDAK dikerjakan.
 *
 * Perubahan bobot dicatat ke log_aktivitas: nilai lama -> nilai baru.
 */
class BobotController extends Controller
{
    protected const ACTIONS = ['index', 'save'];

    private Gejala $model;

    public function __construct()
    {
        require_role(['pakar', 'admin']);
        $this->model = new Gejala();
    }

    public function indexAction(): void
    {
        $this->view('bobot/index', [
            'pageTitle'    => 'Bobot Gejala',
            'halamanAktif' => 'bobot',
            'rows'         => $this->model->list(),
        ]);
    }

    /** Simpan massal: hanya gejala yang nilainya berubah ikut diperbarui & dicatat. */
    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $input = $_POST['bobot'] ?? [];
        if (!is_array($input) || $input === []) {
            flash('galat', 'Tidak ada data bobot yang dikirim.');
            redirect('bobot');
        }

        $errors = [];
        $diperbarui = 0;
        $valid = [];

        foreach ($input as $id => $nilai) {
            $id = (int) $id;
            $nilai = (float) str_replace(',', '.', (string) $nilai);
            if ($nilai < 1.0 || $nilai > 10.0) {
                $errors[] = 'Bobot id ' . $id . ' harus 1-10.';
                continue;
            }
            $valid[$id] = round($nilai, 2);
        }

        if ($errors !== []) {
            flash('galat', implode(' ', array_slice($errors, 0, 3)));
            redirect('bobot');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE gejala SET bobot = ? WHERE id_gejala = ?');
            foreach ($valid as $id => $nilai) {
                $lama = $this->model->find($id);
                if ($lama === null) {
                    continue;
                }
                $bobotLama = (float) $lama['bobot'];
                if (abs($bobotLama - $nilai) < 0.001) {
                    continue; // tidak berubah
                }
                $stmt->execute([$nilai, $id]);
                log_aktivitas('bobot_ubah', 'gejala', $id,
                    ['bobot' => $bobotLama], ['bobot' => $nilai]);
                $diperbarui++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('galat', 'Gagal menyimpan bobot: ' . $e->getMessage());
            redirect('bobot');
        }

        flash('sukses', $diperbarui > 0
            ? $diperbarui . ' bobot gejala berhasil diperbarui.'
            : 'Tidak ada perubahan bobot untuk disimpan.');
        redirect('bobot');
    }
}
