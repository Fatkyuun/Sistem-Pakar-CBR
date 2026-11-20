<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * tests/ValidasiDbTest.php — uji integrasi siklus Revise & Retain dengan database.
 *
 * Menguji alur penuh Validasi::putuskan():
 *   - keputusan 'terima' (retain kasus baru aktif),
 *   - keputusan 'revisi' dengan koreksi gejala dan penyakit final,
 *   - keputusan 'tolak' (kasus berstatus 'arsip'),
 *   - deteksi duplikat saat retain (meningkatkan frekuensi_digunakan),
 *   - validasi aturan bisnis (catatan tolak wajib, minimal 3 gejala, dll).
 *
 * PENTING:
 * Seluruh pengujian dibungkus di dalam transaksi PDO:
 * setUp() memulai beginTransaction() dan tearDown() memanggil rollBack(),
 * sehingga 100% data uji tidak pernah tersimpan permanen di database.
 */
final class ValidasiDbTest extends TestCase
{
    private PDO $pdo;
    private Validasi $validasi;
    private int $idPakar;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->beginTransaction();

        $this->validasi = new Validasi();

        // Ambil pakar dari basis data atau gunakan fallback
        $stmt = $this->pdo->query("SELECT id_user FROM users WHERE role = 'pakar' AND is_active = 1 LIMIT 1");
        $pakarId = $stmt->fetchColumn();
        if (!$pakarId) {
            $stmtAdmin = $this->pdo->query("SELECT id_user FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
            $pakarId = $stmtAdmin->fetchColumn();
        }
        $this->idPakar = (int) $pakarId;
        self::assertGreaterThan(0, $this->idPakar, 'Harus ada akun pakar/admin aktif di basis data untuk pengujian.');
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Buat satu baris diagnosa uji sementara dengan gejala positifnya.
     *
     * @param int $idPenyakitHasil
     * @param array<int,int> $gejala [id_gejala => nilai]
     * @param string $statusValidasi
     * @return int id_diagnosa baru
     */
    private function buatDiagnosaUji(int $idPenyakitHasil, array $gejala, string $statusValidasi = 'menunggu'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO diagnosa
                (id_user, usia, jenis_kelamin, status_merokok, id_penyakit_hasil,
                 similarity_tertinggi, status_cbr, status_validasi, created_at)
             VALUES (NULL, 30, 'L', 'tidak', ?, 0.7500, 'revise', ?, NOW())"
        );
        $stmt->execute([$idPenyakitHasil > 0 ? $idPenyakitHasil : null, $statusValidasi]);
        $idDiagnosa = (int) $this->pdo->lastInsertId();

        $stmtDetail = $this->pdo->prepare(
            'INSERT INTO detail_diagnosa (id_diagnosa, id_gejala, nilai) VALUES (?, ?, ?)'
        );
        foreach ($gejala as $idGejala => $nilai) {
            $stmtDetail->execute([$idDiagnosa, $idGejala, $nilai]);
        }

        return $idDiagnosa;
    }

    /**
     * Uji alur keputusan 'terima':
     * Kasus baru di-retain berstatus 'aktif', diagnosa menjadi 'terkonfirmasi',
     * dan tercatat pada validasi_kasus serta log_aktivitas.
     */
    public function testPutuskanTerimaRetainKasusAktif(): void
    {
        // 3 gejala unik agar tidak persis 100% sama dengan kasus lama
        $gejalaUji = [1 => 1, 3 => 2, 5 => 1]; // Demam, Nyeri Tenggorokan, Sakit Kepala
        $idDiagnosa = $this->buatDiagnosaUji(1, $gejalaUji, 'menunggu');

        $hasil = $this->validasi->putuskan($idDiagnosa, $this->idPakar, 'terima', [
            'catatan' => 'Sesuai dengan gambaran klinis Common Cold.',
        ]);

        self::assertSame('terima', $hasil['keputusan']);
        self::assertSame('terkonfirmasi', $hasil['status']);
        self::assertFalse($hasil['duplikat']);
        self::assertNotNull($hasil['id_kasus_baru']);
        self::assertNotEmpty($hasil['kode_kasus']);
        self::assertGreaterThanOrEqual(3, $hasil['jumlah_gejala']);

        // Verifikasi status diagnosa di DB
        $stmtD = $this->pdo->prepare('SELECT status_validasi FROM diagnosa WHERE id_diagnosa = ?');
        $stmtD->execute([$idDiagnosa]);
        self::assertSame('terkonfirmasi', $stmtD->fetchColumn());

        // Verifikasi validasi_kasus
        $stmtV = $this->pdo->prepare('SELECT * FROM validasi_kasus WHERE id_diagnosa = ?');
        $stmtV->execute([$idDiagnosa]);
        $val = $stmtV->fetch();
        self::assertNotEmpty($val);
        self::assertSame('terima', $val['keputusan']);
        self::assertSame(1, (int) $val['id_penyakit_final']);
        self::assertSame($hasil['id_kasus_baru'], (int) $val['id_kasus_baru']);

        // Verifikasi kasus baru di-retain berstatus aktif
        $stmtK = $this->pdo->prepare('SELECT * FROM kasus WHERE id_kasus = ?');
        $stmtK->execute([$hasil['id_kasus_baru']]);
        $kasusBaru = $stmtK->fetch();
        self::assertNotEmpty($kasusBaru);
        self::assertSame('aktif', $kasusBaru['status']);
        self::assertSame('retain', $kasusBaru['sumber']);
        self::assertSame(1, (int) $kasusBaru['id_penyakit']);

        // Verifikasi detail_kasus terisi
        $stmtDk = $this->pdo->prepare('SELECT COUNT(*) FROM detail_kasus WHERE id_kasus = ? AND nilai > 0');
        $stmtDk->execute([$hasil['id_kasus_baru']]);
        self::assertSame(3, (int) $stmtDk->fetchColumn());
    }

    /**
     * Uji alur keputusan 'revisi':
     * Pakar mengoreksi penyakit final (dari P01 ke P02) dan mengoreksi intensitas gejala.
     */
    public function testPutuskanRevisiKoreksiGejalaDanPenyakit(): void
    {
        $gejalaAwal = [1 => 1, 2 => 1, 3 => 1];
        $idDiagnosa = $this->buatDiagnosaUji(1, $gejalaAwal, 'menunggu');

        // Koreksi: ubah penyakit final ke ID 2 (Influenza), ubah nilai gejala 1 dari 1 menjadi 3, tambah gejala 4 nilai 2
        $gejalaKoreksi = [
            1 => 3, // diubah ke berat
            2 => 1,
            3 => 1,
            4 => 2, // ditambahkan
        ];

        $hasil = $this->validasi->putuskan($idDiagnosa, $this->idPakar, 'revisi', [
            'id_penyakit_final' => 2,
            'solusi_revisi'     => 'Istirahat total dan konsumsi antivirus sesuai anjuran dokter.',
            'catatan'           => 'Pasien mengalami demam tinggi dan nyeri otot khas influenza.',
            'gejala'            => $gejalaKoreksi,
        ]);

        self::assertSame('revisi', $hasil['keputusan']);
        self::assertSame('direvisi', $hasil['status']);
        self::assertNotNull($hasil['id_kasus_baru']);
        self::assertGreaterThanOrEqual(1, $hasil['gejala_dikoreksi']);

        // Cek status diagnosa
        $stmtD = $this->pdo->prepare('SELECT status_validasi FROM diagnosa WHERE id_diagnosa = ?');
        $stmtD->execute([$idDiagnosa]);
        self::assertSame('direvisi', $stmtD->fetchColumn());

        // Cek validasi_kasus
        $stmtV = $this->pdo->prepare('SELECT * FROM validasi_kasus WHERE id_diagnosa = ?');
        $stmtV->execute([$idDiagnosa]);
        $val = $stmtV->fetch();
        self::assertSame('revisi', $val['keputusan']);
        self::assertSame(2, (int) $val['id_penyakit_final']);
        self::assertSame('Istirahat total dan konsumsi antivirus sesuai anjuran dokter.', $val['solusi_revisi']);

        // Cek kasus retain
        $stmtK = $this->pdo->prepare('SELECT * FROM kasus WHERE id_kasus = ?');
        $stmtK->execute([$hasil['id_kasus_baru']]);
        $kasus = $stmtK->fetch();
        self::assertSame(2, (int) $kasus['id_penyakit']);
        self::assertSame('aktif', $kasus['status']);

        // Cek bahwa detail_kasus menyimpan nilai hasil koreksi (id_gejala 1 nilainya 3)
        $stmtG1 = $this->pdo->prepare('SELECT nilai FROM detail_kasus WHERE id_kasus = ? AND id_gejala = 1');
        $stmtG1->execute([$hasil['id_kasus_baru']]);
        self::assertSame(3, (int) $stmtG1->fetchColumn());
    }

    /**
     * Uji alur keputusan 'tolak':
     * Kasus ditolak pakar dan disimpan dengan status 'arsip' (bukan aktif).
     */
    public function testPutuskanTolakMasukArsip(): void
    {
        $gejalaUji = [1 => 2, 2 => 1, 5 => 2];
        $idDiagnosa = $this->buatDiagnosaUji(1, $gejalaUji, 'menunggu');

        $hasil = $this->validasi->putuskan($idDiagnosa, $this->idPakar, 'tolak', [
            'catatan' => 'Gejala tidak konsisten dengan profil pernapasan, dicurigai kondisi lain.',
        ]);

        self::assertSame('tolak', $hasil['keputusan']);
        self::assertSame('ditolak', $hasil['status']);
        self::assertNotNull($hasil['id_kasus_baru']);

        // Cek di DB diagnosa
        $stmtD = $this->pdo->prepare('SELECT status_validasi FROM diagnosa WHERE id_diagnosa = ?');
        $stmtD->execute([$idDiagnosa]);
        self::assertSame('ditolak', $stmtD->fetchColumn());

        // Cek kasus berstatus arsip
        $stmtK = $this->pdo->prepare('SELECT status, sumber FROM kasus WHERE id_kasus = ?');
        $stmtK->execute([$hasil['id_kasus_baru']]);
        $kasus = $stmtK->fetch();
        self::assertSame('arsip', $kasus['status']);
        self::assertSame('retain', $kasus['sumber']);
    }

    /**
     * Keputusan 'tolak' wajib menyertakan alasan penolakan (catatan).
     */
    public function testPutuskanTolakWajibIsiCatatan(): void
    {
        $idDiagnosa = $this->buatDiagnosaUji(1, [1 => 1, 2 => 1, 3 => 1], 'menunggu');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alasan penolakan (catatan) wajib diisi.');

        $this->validasi->putuskan($idDiagnosa, $this->idPakar, 'tolak', [
            'catatan' => '   ', // kosong
        ]);
    }

    /**
     * Uji deteksi duplikat saat Retain:
     * Jika kasus baru persis sama dengan kasus yang sudah ada (similarity >= threshold_duplikat),
     * tidak membuat baris kasus baru, melainkan menambah frekuensi_digunakan kasus lama.
     */
    public function testRetainDeteksiDuplikatMenambahFrekuensi(): void
    {
        // Ambil kasus aktif pertama (K001) beserta gejalanya
        $stmtK = $this->pdo->query(
            "SELECT k.id_kasus, k.id_penyakit, k.frekuensi_digunakan
             FROM kasus k
             WHERE k.status = 'aktif'
             ORDER BY k.id_kasus ASC LIMIT 1"
        );
        $kasusLama = $stmtK->fetch();
        self::assertNotEmpty($kasusLama);

        $idKasusLama = (int) $kasusLama['id_kasus'];
        $idPenyakit  = (int) $kasusLama['id_penyakit'];
        $frekAwal    = (int) $kasusLama['frekuensi_digunakan'];

        // Ambil gejala persis kasus lama
        $stmtDk = $this->pdo->prepare('SELECT id_gejala, nilai FROM detail_kasus WHERE id_kasus = ?');
        $stmtDk->execute([$idKasusLama]);
        $gejalaIdentik = [];
        foreach ($stmtDk->fetchAll() as $row) {
            $gejalaIdentik[(int) $row['id_gejala']] = (int) $row['nilai'];
        }

        // Buat diagnosa uji dengan gejala dan penyakit yang sama persis
        $idDiagnosa = $this->buatDiagnosaUji($idPenyakit, $gejalaIdentik, 'menunggu');

        $hasil = $this->validasi->putuskan($idDiagnosa, $this->idPakar, 'terima', [
            'catatan' => 'Kasus identik dengan kasus standar.',
        ]);

        self::assertTrue($hasil['duplikat'], 'Seharusnya terdeteksi sebagai duplikat.');
        self::assertNull($hasil['id_kasus_baru'], 'Tidak boleh ada ID kasus baru yang dibuat.');
        self::assertSame($idKasusLama, $hasil['id_kasus_duplikat']);

        // Verifikasi frekuensi_digunakan bertambah 1
        $stmtCek = $this->pdo->prepare('SELECT frekuensi_digunakan FROM kasus WHERE id_kasus = ?');
        $stmtCek->execute([$idKasusLama]);
        $frekAkhir = (int) $stmtCek->fetchColumn();
        self::assertSame($frekAwal + 1, $frekAkhir);
    }

    /**
     * Diagnosa yang statusnya bukan 'menunggu' tidak boleh divalidasi ulang.
     */
    public function testDiagnosaSudahDiprosesTidakBisaDivalidasiUlang(): void
    {
        $idDiagnosa = $this->buatDiagnosaUji(1, [1 => 1, 2 => 1, 3 => 1], 'terkonfirmasi');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sudah diproses');

        $this->validasi->putuskan($idDiagnosa, $this->idPakar, 'terima');
    }

    /**
     * Retain harus ditolak jika gejala bernilai > 0 kurang dari 3.
     */
    public function testRetainDitolakJikaGejalaKurangDariTiga(): void
    {
        // Hanya 1 gejala positif
        $idDiagnosa = $this->buatDiagnosaUji(1, [1 => 2], 'menunggu');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minimal 3 gejala');

        $this->validasi->putuskan($idDiagnosa, $this->idPakar, 'terima');
    }
}
