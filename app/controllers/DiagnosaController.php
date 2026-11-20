<?php
declare(strict_types=1);

/**
 * app/controllers/DiagnosaController.php — alur pasien (FR-U2 & FR-U3).
 *
 * Aksi:
 *   form   (GET)  formulir gejala + data pendukung + consent
 *   proses (POST) validasi → CBREngine::retrieve → simpan transaksi → redirect
 *   hasil  (GET)  halaman hasil (banner red-flag, top-K, detail hitung, rating)
 *   rating (POST) simpan rating 1-5 (hanya pemilik diagnosa)
 *
 * Dapat dipakai pasien login (id_user) maupun tamu (session_token 64 hex).
 * Logika CBR seluruhnya di app/services/CBREngine.php.
 */
class DiagnosaController extends Controller
{
    protected const ACTIONS = ['form', 'proses', 'hasil', 'rating'];

    private Diagnosa $model;
    private Gejala $gejala;
    private CBREngine $engine;

    public function __construct()
    {
        $this->model  = new Diagnosa();
        $this->gejala = new Gejala();
        $this->engine = new CBREngine();
    }

    /* ============================================================
       FORMULIR (FR-U2)
       ============================================================ */
    public function formAction(): void
    {
        guest_token(); // siapkan identitas tamu sejak formulir dibuka
        $this->renderForm([], [], []);
    }

    /** Render ulang formulir dengan data lama (setelah validasi gagal). */
    private function renderForm(array $profil, array $nilai, array $errors, bool $consent = false): void
    {
        $this->view('diagnosa/form', [
            'pageTitle'    => 'Mulai Diagnosa',
            'gejalaAktif'  => $this->gejala->listAktif(),
            'profil'       => $profil,
            'nilai'        => $nilai,
            'errors'       => $errors,
            'consent'      => $consent,
        ]);
    }

    /* ============================================================
       PROSES: validasi → Retrieve & Reuse → simpan
       ============================================================ */
    public function prosesAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $errors = [];

        // ---- data pendukung opsional ----
        $usiaMentah = trim(self::inputTeks($_POST, 'usia'));
        $usia = $usiaMentah === '' ? null : (int) $usiaMentah;
        if ($usia !== null && ($usia < 1 || $usia > 120)) {
            $errors[] = 'Usia harus berada pada rentang 1–120 tahun (atau kosongkan).';
        }
        $jk = self::inputTeks($_POST, 'jenis_kelamin');
        if (!in_array($jk, ['', 'L', 'P'], true)) {
            $errors[] = 'Jenis kelamin tidak valid.';
        }
        $merokok = self::inputTeks($_POST, 'status_merokok');
        if (!in_array($merokok, ['', 'tidak', 'mantan', 'aktif'], true)) {
            $errors[] = 'Status merokok tidak valid.';
        }
        $profil = [
            'usia'          => $usia,
            'jenis_kelamin' => $jk === '' ? null : $jk,
            'status_merokok' => $merokok === '' ? null : $merokok,
        ];

        // ---- gejala: seluruh gejala aktif, nilai 0-3 (biner 0/1) ----
        $inputForm = $_POST['gejala'] ?? [];
        if (!is_array($inputForm)) {
            $inputForm = [];
            $errors[] = 'Format nilai gejala tidak valid.';
        }
        $input = [];
        foreach ($this->gejala->listAktif() as $g) {
            $id = (int) $g['id_gejala'];
            $mentah = $inputForm[$id] ?? 0;
            if (!is_scalar($mentah)) {
                $errors[] = 'Nilai gejala ' . $g['kode_gejala'] . ' tidak valid.';
                $input[$id] = 0;
                continue;
            }
            $nilai = (int) round((float) $mentah);
            if ($nilai < 0 || $nilai > 3) {
                $errors[] = 'Nilai gejala ' . $g['kode_gejala'] . ' harus 0–3.';
                $nilai = 0;
            }
            if ($g['tipe_input'] === 'biner' && !in_array($nilai, [0, 1], true)) {
                $errors[] = 'Gejala ' . $g['kode_gejala'] . ' hanya bernilai Ada (1) / Tidak (0).';
                $nilai = 0;
            }
            $input[$id] = $nilai;
        }

        $terpilih = count(array_filter($input, static fn (int $n): bool => $n > 0));
        if ($terpilih < 3) {
            $errors[] = 'Pilih minimal 3 gejala yang Anda rasakan (baru ' . $terpilih . ' terpilih).';
        }

        $consent = !empty($_POST['consent']);
        if (!$consent) {
            $errors[] = 'Anda wajib menyetujui pernyataan persetujuan dan disclaimer medis.';
        }

        if ($errors !== []) {
            $this->renderForm($profil, $input, $errors, $consent);
            return;
        }

        // ---- Retrieve & Reuse ----
        $hasilRetrieve = $this->engine->retrieve($input);
        $teratas = $hasilRetrieve['kasus_teratas'];

        $user = current_user();
        $idUser = $user !== null ? (int) $user['id_user'] : null;
        $sessionToken = $idUser === null ? guest_token() : null;

        $hasilRows = [];
        foreach ($hasilRetrieve['kasus'] as $i => $k) {
            $hasilRows[] = [
                'id_kasus'   => (int) $k['id_kasus'],
                'id_penyakit' => (int) $k['id_penyakit'],
                'similarity' => (float) $k['similarity'],
                'peringkat'  => $i + 1,
            ];
        }

        try {
            $idDiagnosa = $this->model->create(
                [
                    'id_user'              => $idUser,
                    'session_token'        => $sessionToken,
                    'usia'                 => $profil['usia'],
                    'jenis_kelamin'        => $profil['jenis_kelamin'],
                    'status_merokok'       => $profil['status_merokok'],
                    'id_penyakit_hasil'    => $teratas['id_penyakit'] ?? null,
                    'similarity_tertinggi' => $hasilRetrieve['similarity_tertinggi'],
                    'status_cbr'           => $hasilRetrieve['status_cbr'],
                    'status_validasi'      => $hasilRetrieve['status_validasi'],
                ],
                $input,
                $hasilRows,
                $hasilRetrieve['status_cbr'] === 'reuse' && $teratas !== null ? (int) $teratas['id_kasus'] : null
            );
        } catch (Throwable $e) {
            $this->renderForm($profil, $input, ['Gagal menyimpan hasil diagnosa: ' . $e->getMessage()], $consent);
            return;
        }

        log_aktivitas('diagnosa_proses', 'diagnosa', $idDiagnosa, null, [
            'status_cbr'      => $hasilRetrieve['status_cbr'],
            'similarity'      => $hasilRetrieve['similarity_tertinggi'],
            'jumlah_gejala'   => $terpilih,
            'redflag'         => count($hasilRetrieve['redflag']),
            'identitas'       => $idUser !== null ? 'user' : 'tamu',
        ]);

        redirect('diagnosa', ['action' => 'hasil', 'id' => $idDiagnosa]);
    }

    /* ============================================================
       HALAMAN HASIL (FR-U3)
       ============================================================ */
    public function hasilAction(): void
    {
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $d = $this->model->find($id);
        if ($d === null) {
            respond_not_found();
        }

        $user = current_user();
        $idUser = $user !== null ? (int) $user['id_user'] : null;
        $token  = $_SESSION['session_token'] ?? null;

        // Pengguna hanya boleh membuka riwayat miliknya. Pakar/admin boleh
        // meninjau (dibutuhkan antrean validasi Fase 4) — tanpa form rating.
        $pemilik = $this->model->milik($d, $idUser, $token);
        $staf = $user !== null && in_array($user['role'], ['pakar', 'admin'], true);
        if (!$pemilik && !$staf) {
            respond_not_found();
        }

        $detailRows = $this->model->detail($id);
        $detailMap = [];
        foreach ($detailRows as $row) {
            $detailMap[(int) $row['id_gejala']] = (int) $row['nilai'];
        }

        $hasil = $this->model->hasil($id);
        $meta = $this->engine->metaAktif();
        $redflag = CBREngine::deteksiRedflag($detailMap, $meta);

        // Agregasi top-K penyakit dari hasil tersimpan (skor = kemiripan tertinggi).
        $skorKasus = [];
        foreach ($hasil as $h) {
            $skorKasus[] = [
                'id_penyakit'     => (int) $h['id_penyakit'],
                'kode_penyakit'   => (string) $h['kode_penyakit'],
                'nama_penyakit'   => (string) $h['nama_penyakit'],
                'tingkat_urgensi' => (string) $h['tingkat_urgensi'],
                'similarity'      => (float) $h['similarity'],
            ];
        }
        $penyakitTop = CBREngine::agregasiPenyakit(
            $skorKasus,
            max(1, (int) pengaturan('top_k', '3'))
        );

        $metode = (string) pengaturan('metode_similarity', 'nearest_neighbor');
        if (!in_array($metode, CBREngine::METODE_VALID, true)) {
            $metode = 'nearest_neighbor';
        }

        // Kasus paling mirip + perbandingan gejala (cocok / berbeda).
        $kasusTeratas = null;
        $kasusGejala = [];
        $kontribusi = [];
        $perbandingan = ['cocok' => [], 'beda' => [], 'hanya_input' => [], 'hanya_kasus' => []];

        if ($hasil !== []) {
            $kasusTeratas = $hasil[0];
            $modelKasus = new Kasus();
            $kasusGejala = $modelKasus->petaNilai((int) $kasusTeratas['id_kasus']);
            $kontribusi = CBREngine::kontribusi($metode, $detailMap, $kasusGejala, $meta);
            $perbandingan = CBREngine::bandingkanGejala($detailMap, $kasusGejala, $meta);
        }

        // Kotak "Hasil Validasi" pada halaman hasil (bila pakar sudah memutuskan).
        $validasi = $this->model->validasi($id);

        $this->view('diagnosa/hasil', [
            'pageTitle'      => 'Hasil Diagnosa',
            'd'              => $d,
            'detailRows'     => $detailRows,
            'hasil'          => $hasil,
            'kasusTeratas'   => $kasusTeratas,
            'perbandingan'   => $perbandingan,
            'kontribusi'     => $kontribusi,
            'metode'         => $metode,
            'redflag'        => $redflag,
            'pemilik'        => $pemilik,
            'penyakitTop'    => $penyakitTop,
            'validasi'       => $validasi,
        ]);
    }

    /* ============================================================
       RATING (1-5) — hanya pemilik
       ============================================================ */
    public function ratingAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        $rating = (int) ($_POST['rating'] ?? 0);
        $d = $this->model->find($id);
        if ($d === null) {
            respond_not_found();
        }

        $user  = current_user();
        $idUser = $user !== null ? (int) $user['id_user'] : null;
        $token  = $_SESSION['session_token'] ?? null;

        if (!$this->model->milik($d, $idUser, $token)) {
            respond_not_found(); // proteksi kepemilikan user/session_token
        }
        if ($rating < 1 || $rating > 5) {
            flash('galat', 'Rating harus bernilai 1–5.');
            redirect('diagnosa', ['action' => 'hasil', 'id' => $id]);
        }

        $this->model->updateRating($id, $rating, $idUser, $token);
        flash('sukses', 'Terima kasih, penilaian Anda sudah tersimpan.');
        redirect('diagnosa', ['action' => 'hasil', 'id' => $id]);
    }
}
