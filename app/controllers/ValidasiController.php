<?php
declare(strict_types=1);

/**
 * app/controllers/ValidasiController.php — antrean validasi pakar
 * (PRD FR-A6, siklus Revise → Retain pada 3.3.4–3.3.5).
 *
 * Aksi:
 *   index    (GET)  antrean 'menunggu' + tab riwayat keputusan
 *   detail   (GET)  data pasien, hasil sistem, kasus rujukan, form keputusan
 *   putuskan (POST) terima / revisi / tolak dalam satu transaksi (Validasi)
 */
class ValidasiController extends Controller
{
    protected const ACTIONS = ['index', 'detail', 'putuskan'];

    private Validasi $model;
    private Diagnosa $diagnosa;
    private CBREngine $engine;

    public function __construct()
    {
        require_role(['pakar', 'admin']);
        $this->model    = new Validasi();
        $this->diagnosa = new Diagnosa();
        $this->engine   = new CBREngine();
    }

    /* ============================================================
       ANTREAN & RIWAYAT
       ============================================================ */
    public function indexAction(): void
    {
        $tab = (string) ($_GET['tab'] ?? 'menunggu');
        if (!in_array($tab, array_merge(['menunggu'], Validasi::STATUS_FINAL), true)) {
            $tab = 'menunggu';
        }
        $urut = (string) ($_GET['urut'] ?? 'tanggal') === 'similarity' ? 'similarity' : 'tanggal';

        $rows = $tab === 'menunggu'
            ? $this->model->antrean($urut)
            : $this->model->riwayat($tab);

        $this->view('validasi/index', [
            'pageTitle'    => 'Validasi Kasus',
            'halamanAktif' => 'validasi',
            'useDataTables' => false,
            'tab'          => $tab,
            'urut'         => $urut,
            'rows'         => $rows,
            'stat'         => $this->model->statistik(),
        ]);
    }

    /* ============================================================
       DETAIL VALIDASI
       ============================================================ */
    public function detailAction(): void
    {
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $d = $this->model->find($id);
        if ($d === null) {
            respond_not_found();
        }

        $detailRows = $this->diagnosa->detail($id);
        $peta = [];
        foreach ($detailRows as $row) {
            $peta[(int) $row['id_gejala']] = (int) $row['nilai'];
        }

        $hasil = $this->diagnosa->hasil($id);
        $meta  = $this->engine->metaAktif();
        $redflag = CBREngine::deteksiRedflag($peta, $meta);

        $metode = (string) pengaturan('metode_similarity', 'nearest_neighbor');
        if (!in_array($metode, CBREngine::METODE_VALID, true)) {
            $metode = 'nearest_neighbor';
        }

        $kasusTeratas = $hasil[0] ?? null;
        $kasusGejala  = [];
        $perbandingan = ['cocok' => [], 'beda' => [], 'hanya_input' => [], 'hanya_kasus' => []];
        $kontribusi   = [];
        if ($kasusTeratas !== null) {
            $kasusGejala  = (new Kasus())->petaNilai((int) $kasusTeratas['id_kasus']);
            $perbandingan = CBREngine::bandingkanGejala($peta, $kasusGejala, $meta);
            $kontribusi   = CBREngine::kontribusi($metode, $peta, $kasusGejala, $meta);
        }

        $this->view('validasi/detail', [
            'pageTitle'    => 'Validasi Diagnosa #' . $id,
            'halamanAktif' => 'validasi',
            'useDataTables' => false,
            'd'            => $d,
            'detailRows'   => $detailRows,
            'peta'         => $peta,
            'hasil'        => $hasil,
            'kasusTeratas' => $kasusTeratas,
            'perbandingan' => $perbandingan,
            'kontribusi'   => $kontribusi,
            'metode'       => $metode,
            'redflag'      => $redflag,
            'penyakitList' => (new Penyakit())->listAktif(),
        ]);
    }

    /* ============================================================
       PROSES KEPUTUSAN
       ============================================================ */
    public function putuskanAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($this->model->find($id) === null) {
            respond_not_found();
        }

        $user = current_user();
        if ($user === null) {
            require_login();
        }

        $keputusan = self::inputTeks($_POST, 'keputusan');
        $data = [
            'id_penyakit_final' => (int) self::inputTeks($_POST, 'id_penyakit_final', '0'),
            'solusi_revisi'     => self::inputTeks($_POST, 'solusi_revisi'),
            'catatan'           => self::inputTeks($_POST, 'catatan'),
        ];
        if ($keputusan === 'revisi' && isset($_POST['gejala']) && is_array($_POST['gejala'])) {
            // Nilai non-skalar dibuang di model (Validasi::putuskan) — lihat teks().
            $data['gejala'] = $_POST['gejala'];
        }

        try {
            $hasil = $this->model->putuskan($id, (int) $user['id_user'], $keputusan, $data);
        } catch (InvalidArgumentException | DomainException $e) {
            flash('galat', $e->getMessage());
            redirect('validasi', ['action' => 'detail', 'id' => $id]);
        } catch (Throwable $e) {
            flash('galat', 'Validasi gagal diproses: ' . $e->getMessage());
            redirect('validasi', ['action' => 'detail', 'id' => $id]);
        }

        if ($hasil['keputusan'] === 'tolak') {
            $pesan = $hasil['kode_kasus'] !== null
                ? 'Diagnosa ditolak. Kasus disimpan sebagai arsip ' . $hasil['kode_kasus'] . ' untuk audit.'
                : 'Diagnosa ditolak dan dicatat. Tidak ada kasus baru karena hasil sistem belum memiliki penyakit.';
            if ($hasil['arsip_dilewati']) {
                $pesan .= ' Arsip kasus dilewati (tanpa label penyakit).';
            }
        } elseif ($hasil['duplikat']) {
            $pesan = 'Keputusan tersimpan. Kasus mirip ' . $hasil['kode_kasus_duplikat']
                . ' sudah ada — frekuensi pemakaiannya ditambah, tanpa menggandakan kasus.';
        } else {
            $pesan = 'Keputusan tersimpan dan kasus baru ' . $hasil['kode_kasus'] . ' masuk basis kasus.';
        }

        flash('sukses', $pesan);
        redirect('validasi', ['action' => 'detail', 'id' => $id]);
    }
}
