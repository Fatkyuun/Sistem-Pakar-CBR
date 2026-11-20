<?php
declare(strict_types=1);

/**
 * app/controllers/KasusController.php — CRUD Basis Kasus (PRD FR-A5).
 *
 * Aksi: index (list+filter), form, save, detail, status, delete,
 * import (CSV), export (unduh CSV), template (unduh contoh CSV).
 *
 * Ketentuan khusus (PRD + AGENTS.md):
 * - penyakit = "solusi" kasus; detail gejala 0-3 sesuai tipe input.
 * - kasus + detail disimpan dalam SATU transaksi PDO.
 * - hapus: bila sudah dirujuk hasil_diagnosa/validasi_kasus → status 'arsip'
 *   (soft delete); bila belum → hapus permanen.
 * - deteksi duplikat: similarity >= threshold_duplikat (tabel pengaturan)
 *   terhadap kasus aktif penyakit sama → peringatan konfirmasi (paksa_simpan).
 */
class KasusController extends Controller
{
    protected const ACTIONS = ['index', 'form', 'save', 'detail', 'status', 'delete', 'import', 'export', 'template'];

    private const UKURAN_MAKS = 2097152; // 2 MB (PRD 5.2: validasi tipe & ukuran unggahan)

    private Kasus $model;
    private Penyakit $penyakit;
    private Gejala $gejala;

    public function __construct()
    {
        require_role(['pakar', 'admin']);
        $this->model    = new Kasus();
        $this->penyakit = new Penyakit();
        $this->gejala   = new Gejala();
    }

    /* ---------------- List + filter ---------------- */
    public function indexAction(): void
    {
        $filter = [
            'id_penyakit' => (int) ($_GET['penyakit'] ?? 0),
            'status'      => (string) ($_GET['status'] ?? ''),
            'sumber'      => (string) ($_GET['sumber'] ?? ''),
            'cari'        => trim((string) ($_GET['cari'] ?? '')),
        ];
        $this->view('kasus/index', [
            'pageTitle'    => 'Basis Kasus',
            'halamanAktif' => 'kasus',
            'rows'         => $this->model->list($filter),
            'filter'       => $filter,
            'daftarPenyakit' => $this->penyakit->list(),
        ]);
    }

    /* ---------------- Form tambah/ubah ---------------- */
    public function formAction(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $k = null;
        $nilai = [];
        if ($id > 0) {
            $k = $this->model->find($id);
            if ($k === null) {
                respond_not_found();
            }
            $nilai = $this->model->petaNilai($id);
        }
        $this->view('kasus/form', [
            'pageTitle'    => $k === null ? 'Tambah Kasus' : 'Ubah Kasus — ' . $k['kode_kasus'],
            'halamanAktif' => 'kasus',
            'k'            => $k,
            'nilai'        => $nilai,
            'old'          => [],
            'errors'       => [],
            'duplikat'     => null,
            'gejalaAktif'  => $this->gejala->listAktif(),
            'penyakitAktif' => $this->penyakit->listAktif(),
        ]);
    }

    /* ---------------- Simpan (create/update) ---------------- */
    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        $paksa = !empty($_POST['paksa_simpan']);

        $kasus = [
            'id_penyakit' => (int) ($_POST['id_penyakit'] ?? 0),
            'status'      => (string) ($_POST['status'] ?? 'aktif'),
            'sumber'      => 'pakar',
        ];

        $errors = [];
        $penyakit = $this->penyakit->find($kasus['id_penyakit']);
        if ($penyakit === null || (int) $penyakit['is_active'] !== 1) {
            $errors[] = 'Penyakit wajib dipilih dari daftar penyakit aktif.';
        }
        if (!in_array($kasus['status'], Kasus::STATUS, true)) {
            $errors[] = 'Status kasus tidak valid.';
        }

        $detail = $this->bacaDetailDariForm($errors);

        // Validasi gagal → kembali ke form.
        if ($errors !== []) {
            $this->renderForm($id, $kasus, null, $errors);
            return;
        }

        // Deteksi duplikat (kecuali kasus yang sedang diedit).
        $threshold = (float) pengaturan('threshold_duplikat', '0.98');
        $dup = $this->model->similarityTertinggi($detail, $kasus['id_penyakit'], $id);
        if (!$paksa && $dup !== null && $dup['similarity'] >= $threshold) {
            $this->renderForm($id, $kasus, $dup, []);
            return;
        }

        try {
            if ($id > 0) {
                $lama = $this->model->find($id);
                if ($lama === null) {
                    respond_not_found();
                }
                $this->model->update($id, $kasus, $detail);
                log_aktivitas('kasus_ubah', 'kasus', $id, $lama, [
                    'id_penyakit' => $kasus['id_penyakit'],
                    'status'      => $kasus['status'],
                    'jumlah_gejala' => count(array_filter($detail, static fn ($n) => $n > 0)),
                ]);
                flash('sukses', 'Kasus ' . $lama['kode_kasus'] . ' berhasil diperbarui.');
            } else {
                $idBaru = $this->model->create($kasus, $detail);
                $baru   = $this->model->find($idBaru);
                $kode   = (string) ($baru['kode_kasus'] ?? '');
                log_aktivitas('kasus_tambah', 'kasus', $idBaru, null, [
                    'kode_kasus' => $kode,
                    'id_penyakit' => $kasus['id_penyakit'],
                    'status' => $kasus['status'],
                    'jumlah_gejala' => count(array_filter($detail, static fn ($n) => $n > 0)),
                ]);
                flash('sukses', 'Kasus ' . $kode . ' berhasil ditambahkan ke basis kasus.');
            }
        } catch (Throwable $e) {
            $this->renderForm($id, $kasus, null, ['Transaksi gagal: ' . $e->getMessage()]);
            return;
        }
        redirect('kasus');
    }

    /** Kumpulkan input gejala dari form (nama="gejala[id_gejala]") dan validasi tipe. */
    private function bacaDetailDariForm(array &$errors): array
    {
        $detail = [];
        $input  = $_POST['gejala'] ?? [];
        if (!is_array($input)) {
            $errors[] = 'Format nilai gejala tidak valid.';
            return $detail;
        }
        foreach ($this->gejala->listAktif() as $g) {
            $id = (int) $g['id_gejala'];
            $mentah = $input[$id] ?? 0;
            // Input berbentuk array/objek tidak boleh lolos (dulu bisa jadi 1).
            if (!is_scalar($mentah)) {
                $errors[] = 'Nilai gejala ' . $g['kode_gejala'] . ' tidak valid.';
                continue;
            }
            $nilai = (int) round((float) $mentah);
            if ($nilai < 0 || $nilai > 3) {
                $errors[] = 'Nilai gejala ' . $g['kode_gejala'] . ' harus 0-3.';
                continue;
            }
            if ($g['tipe_input'] === 'biner' && !in_array($nilai, [0, 1], true)) {
                $errors[] = 'Gejala ' . $g['kode_gejala'] . ' bertipe biner, nilai hanya 0/1.';
                continue;
            }
            $detail[$id] = $nilai;
        }

        // Kasus tanpa gejala sama sekali tidak berguna untuk Retrieve:
        // union gejala kosong membuat similarity 0 terhadap semua kasus.
        if ($errors === [] && array_sum($detail) === 0) {
            $errors[] = 'Isi minimal satu gejala dengan nilai lebih dari 0.';
        }
        return $detail;
    }

    /** Render form kasus dengan data lama + info duplikat (bila ada). */
    private function renderForm(int $id, array $kasus, ?array $duplikat, array $errors): void
    {
        $k = $id > 0 ? $this->model->find($id) : null;
        $nilai = $id > 0 ? $this->model->petaNilai($id) : [];
        // Pertahankan input terakhir pengguna bila validasi/dupecheck gagal.
        // Nilai non-skalar (array dari manipulasi form) diabaikan, bukan dipaksa 1.
        if (isset($_POST['gejala']) && is_array($_POST['gejala'])) {
            foreach ($_POST['gejala'] as $idGejala => $val) {
                if (!is_scalar($val)) {
                    continue;
                }
                $nilai[(int) $idGejala] = max(0, min(3, (int) round((float) $val)));
            }
        }
        $this->view('kasus/form', [
            'pageTitle'    => $id > 0 ? 'Ubah Kasus' : 'Tambah Kasus',
            'halamanAktif' => 'kasus',
            'k'            => $k,
            'nilai'        => $nilai,
            'old'          => $kasus,
            'errors'       => $errors,
            'duplikat'     => $duplikat,
            'gejalaAktif'  => $this->gejala->listAktif(),
            'penyakitAktif' => $this->penyakit->listAktif(),
        ]);
    }

    /* ---------------- Detail kasus ---------------- */
    public function detailAction(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $k = $this->model->find($id);
        if ($k === null) {
            respond_not_found();
        }
        $this->view('kasus/detail', [
            'pageTitle'    => 'Detail Kasus — ' . $k['kode_kasus'],
            'halamanAktif' => 'kasus',
            'k'            => $k,
            'detail'       => $this->model->detailGejala($id),
            'referensi'    => $this->model->jumlahReferensi($id),
        ]);
    }

    /* ---------------- Ubah status ---------------- */
    public function statusAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $status = (string) ($_POST['status'] ?? '');
        $k = $this->model->find($id);
        if ($k === null || !in_array($status, Kasus::STATUS, true)) {
            respond_not_found();
        }
        $this->model->setStatus($id, $status);
        log_aktivitas('kasus_status', 'kasus', $id, ['status' => $k['status']], ['status' => $status]);
        flash('sukses', 'Status kasus ' . $k['kode_kasus'] . ' diubah menjadi ' . $status . '.');
        redirect('kasus');
    }

    /* ---------------- Hapus (soft/permanen) ---------------- */
    public function deleteAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_not_found();
        }
        csrf_check();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $k = $this->model->find($id);
        if ($k === null) {
            respond_not_found();
        }

        $referensi = $this->model->jumlahReferensi($id);
        if ($referensi === 0) {
            log_aktivitas('kasus_hapus', 'kasus', $id, $k, null);
            $this->model->deletePermanen($id);
            flash('sukses', 'Kasus ' . $k['kode_kasus'] . ' dihapus permanen (belum dirujuk riwayat apa pun).');
        } else {
            $this->model->setStatus($id, 'arsip');
            log_aktivitas('kasus_arsip', 'kasus', $id, ['status' => $k['status']], [
                'status' => 'arsip',
                'referensi' => $referensi,
            ]);
            flash('peringatan', 'Kasus ' . $k['kode_kasus'] . ' sudah dirujuk ' . $referensi
                . ' rekaman riwayat — diarsipkan, tidak dihapus permanen.');
        }
        redirect('kasus');
    }

    /* ============================================================
       IMPOR CSV (P1)
       ============================================================ */
    public function importAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->view('kasus/import', [
                'pageTitle'    => 'Impor Kasus',
                'halamanAktif' => 'kasus',
                'hasil'        => null,
                'gejalaAktif'  => $this->gejala->listAktif(),
            ]);
            return;
        }

        csrf_check();
        $berkas = $_FILES['berkas'] ?? null;

        if ($berkas === null || $berkas['error'] !== UPLOAD_ERR_OK) {
            $this->renderImport(null, ['Unggahan gagal diterima. Periksa ukuran berkas (maksimal 2 MB).']);
            return;
        }
        if ($berkas['size'] > self::UKURAN_MAKS) {
            $this->renderImport(null, ['Ukuran berkas melebihi 2 MB.']);
            return;
        }
        $ext = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($berkas['tmp_name']);
        if (!in_array($ext, ['csv', 'txt'], true) || !str_starts_with((string) $mime, 'text/')) {
            $this->renderImport(null, ['Berkas harus CSV/teks (.csv atau .txt).']);
            return;
        }

        // Parse berkas: baris pertama = header (kode_penyakit + kode gejala).
        $lines = file($berkas['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if ($lines === []) {
            $this->renderImport(null, ['Berkas kosong.']);
            return;
        }
        $lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]); // buang BOM UTF-8

        // Deteksi pemisah: ';' (Excel Indonesia) atau ','.
        $delim = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map('trim', str_getcsv($lines[0], $delim));

        // Petakan kolom: satu kolom kode penyakit + kolom kode gejala.
        $idxPenyakit = null;
        $kolomGejala = []; // [idx => id_gejala]
        $semuaGejala = [];
        foreach ($this->gejala->list() as $g) {
            $semuaGejala[strtoupper($g['kode_gejala'])] = $g;
        }
        foreach ($header as $i => $namaKolom) {
            $kolom = strtoupper(ltrim((string) $namaKolom, '#'));
            if ($kolom === 'KODE_PENYAKIT' || $kolom === 'KODE') {
                $idxPenyakit = $i;
                continue;
            }
            if (isset($semuaGejala[$kolom])) {
                $kolomGejala[$i] = $semuaGejala[$kolom];
            }
        }
        if ($idxPenyakit === null || $kolomGejala === []) {
            $this->renderImport(null, ['Header tidak dikenali. Unduh template melalui tombol "Unduh Template" lalu isi sesuai formatnya.']);
            return;
        }

        $threshold = (float) pengaturan('threshold_duplikat', '0.98');
        $sukses = $gagal = $duplikat = 0;
        $catatanGagal = [];
        $catatanDup = [];

        foreach (array_slice($lines, 1) as $no => $line) {
            $noBaris = $no + 2; // +1 header, +1 basis 1
            $row = array_map('trim', str_getcsv($line, $delim));
            if ($row === [null] || implode('', $row) === '') {
                continue;
            }

            $kodePenyakit = strtoupper($row[$idxPenyakit] ?? '');
            $penyakit = $this->penyakit->findByKode($kodePenyakit);
            if ($penyakit === null || (int) $penyakit['is_active'] !== 1) {
                $gagal++;
                $catatanGagal[] = ['baris' => $noBaris, 'alasan' => "Kode penyakit '{$kodePenyakit}' tidak ditemukan/nonaktif."];
                continue;
            }

            $detail = [];
            $adaGejala = false;
            $valid = true;
            foreach ($kolomGejala as $i => $g) {
                $nilai = (int) ($row[$i] !== '' && $row[$i] !== null ? round((float) str_replace(',', '.', $row[$i])) : 0);
                if ($nilai < 0 || $nilai > 3) {
                    $gagal++;
                    $catatanGagal[] = ['baris' => $noBaris, 'alasan' => 'Nilai ' . $g['kode_gejala'] . ' di luar 0-3.'];
                    $valid = false;
                    break;
                }
                if ($g['tipe_input'] === 'biner' && $nilai > 1) {
                    $gagal++;
                    $catatanGagal[] = ['baris' => $noBaris, 'alasan' => $g['kode_gejala'] . ' biner, nilai harus 0/1.'];
                    $valid = false;
                    break;
                }
                if ($nilai > 0) {
                    $adaGejala = true;
                }
                $detail[(int) $g['id_gejala']] = $nilai;
            }
            if (!$valid) {
                continue;
            }
            if (!$adaGejala) {
                $gagal++;
                $catatanGagal[] = ['baris' => $noBaris, 'alasan' => 'Tidak ada gejala bernilai > 0.'];
                continue;
            }

            // Duplikat pada impor: otomatis LEWATI dan laporkan (keputusan Anda).
            $dup = $this->model->similarityTertinggi($detail, (int) $penyakit['id_penyakit']);
            if ($dup !== null && $dup['similarity'] >= $threshold) {
                $duplikat++;
                $catatanDup[] = ['baris' => $noBaris, 'kasus' => $dup['kode_kasus'], 'similarity' => $dup['similarity']];
                continue;
            }

            try {
                $this->model->create([
                    'id_penyakit' => (int) $penyakit['id_penyakit'],
                    'status'      => 'aktif',
                    'sumber'      => 'import',
                ], $detail);
                $sukses++;
            } catch (Throwable $e) {
                $gagal++;
                $catatanGagal[] = ['baris' => $noBaris, 'alasan' => 'Gagal disimpan: ' . $e->getMessage()];
            }
        }

        log_aktivitas('kasus_import', 'kasus', null, null, [
            'berkas' => $berkas['name'],
            'sukses' => $sukses, 'gagal' => $gagal, 'duplikat_dilewati' => $duplikat,
        ]);

        $this->renderImport([
            'sukses'    => $sukses,
            'gagal'     => $gagal,
            'duplikat'  => $duplikat,
            'gagalRows' => $catatanGagal,
            'dupRows'   => $catatanDup,
        ], []);
    }

    private function renderImport(?array $hasil, array $errors): void
    {
        $this->view('kasus/import', [
            'pageTitle'    => 'Impor Kasus',
            'halamanAktif' => 'kasus',
            'hasil'        => $hasil,
            'errors'       => $errors,
            'gejalaAktif'  => $this->gejala->listAktif(),
        ]);
    }

    /* ============================================================
       EKSPOR & TEMPLATE CSV
       ============================================================ */
    private function kirimCsv(string $namaBerkas, callable $tulis): void
    {
        if (ob_get_level() !== 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $namaBerkas . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        $tulis($out);
        fclose($out);
        exit;
    }

    /** Unduh template CSV: header kode_penyakit + seluruh kolom gejala. */
    public function templateAction(): void
    {
        $gejalaAktif = $this->gejala->listAktif();
        $this->kirimCsv('template_kasus.csv', static function ($out) use ($gejalaAktif): void {
            fputcsv($out, array_merge(['kode_penyakit'], array_column($gejalaAktif, 'kode_gejala')), ';');
            // Baris contoh (nol): hanya sebagai penuntun format.
            fputcsv($out, array_merge(['P01'], array_fill(0, count($gejalaAktif), '0')), ';');
        });
    }

    /** Unduh seluruh kasus (termasuk nonaktif/arsip) sebagai CSV. */
    public function exportAction(): void
    {
        $gejalaAktif = $this->gejala->listAktif();
        $rows = $this->model->list();
        log_aktivitas('kasus_ekspor', 'kasus', null, null, ['jumlah' => count($rows)]);

        $this->kirimCsv('kasus_' . date('Ymd_His') . '.csv', static function ($out) use ($gejalaAktif, $rows): void {
            fputcsv($out, array_merge(
                ['kode_kasus', 'kode_penyakit', 'status', 'sumber'],
                array_column($gejalaAktif, 'kode_gejala')
            ), ';');
            foreach ($rows as $r) {
                $peta = (new Kasus())->petaNilai((int) $r['id_kasus']);
                $baris = [$r['kode_kasus'], $r['kode_penyakit'], $r['status'], $r['sumber']];
                foreach ($gejalaAktif as $g) {
                    $baris[] = (string) ($peta[(int) $g['id_gejala']] ?? 0);
                }
                fputcsv($out, $baris, ';');
            }
        });
    }
}
