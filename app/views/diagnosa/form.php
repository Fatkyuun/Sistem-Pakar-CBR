<?php
require __DIR__ . '/../layouts/header.php';
/**
 * app/views/diagnosa/form.php — FR-U2 Formulir Input Gejala (ramah pasien awam).
 *
 * Penyederhanaan UX:
 *  - Tanpa kode gejala (G01…) dan tanpa istilah tipe input (biner/skala/durasi).
 *  - Semua pilihan memakai tombol pill sekali klik (tanpa dropdown/angka 0–3).
 *  - Navigasi langkah per kategori gejala + filter "sudah saya pilih".
 *  - Data pendukung opsional diringkas menjadi satu baris.
 *
 * Variabel: $gejalaAktif, $profil, $nilai, $errors, $consent
 */
$errors  = $errors ?? [];
$profil  = $profil ?? [];
$nilai   = $nilai ?? [];
$consent = $consent ?? false;

// Kelompokkan gejala per kategori, lalu urutkan kategori dengan alur yang
// paling mudah dipahami pasien (kategori lain menyusul secara alfabetis).
$grup = [];
foreach ($gejalaAktif as $g) {
    $grup[$g['kategori']][] = $g;
}
$urutanKategori = ['Gejala Umum', 'Batuk', 'Hidung dan Tenggorokan', 'Sesak Napas', 'Nyeri'];
uksort($grup, static function (string $a, string $b) use ($urutanKategori): int {
    $ia = array_search($a, $urutanKategori, true);
    $ib = array_search($b, $urutanKategori, true);
    $ia = $ia === false ? PHP_INT_MAX : $ia;
    $ib = $ib === false ? PHP_INT_MAX : $ib;
    return $ia <=> $ib ?: strcmp($a, $b);
});

$jumlahKategori = count($grup);
$jumlahGejala   = count($gejalaAktif);

// Pilihan pill per tipe gejala (label bahasa awam, tanpa angka 0–3).
$pilihan = [
    'biner'  => [0 => 'Tidak', 1 => 'Ya, saya mengalaminya'],
    'skala'  => [0 => 'Tidak ada', 1 => 'Ringan', 2 => 'Sedang', 3 => 'Berat'],
    'durasi' => [0 => 'Tidak mengalami', 1 => '< 3 hari', 2 => '3–7 hari', 3 => '> 1 minggu'],
];
?>
<div class="row justify-content-center">
  <div class="col-xl-10">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h1 class="h3 mb-1"><i class="bi bi-clipboard2-pulse text-primary"></i> Mulai Diagnosa</h1>
        <p class="text-secondary mb-0 small">
          Pilih keluhan yang Anda rasakan. Tidak perlu tahu istilah medis —
          cukup jawab sesuai yang Anda alami.
        </p>
      </div>
      <span class="badge text-bg-light border">
        <i class="bi bi-person"></i> <?= current_user() !== null ? 'Pasien terdaftar — hasil tersimpan di riwayat' : 'Mode tamu — simpan tautan hasil Anda' ?>
      </span>
    </div>

    <?php if ($errors !== []): ?>
      <div class="alert alert-danger" role="alert" id="galatServer">
        <strong><i class="bi bi-exclamation-octagon"></i> Periksa kembali isian Anda:</strong>
        <ul class="mb-0 ps-3 mt-1">
          <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Peringatan darurat LIVE (diisi JavaScript) -->
    <div class="alert alert-danger d-none" role="alert" id="peringatanRedflag"></div>

    <form id="formDiagnosa" method="post" action="<?= e(url('diagnosa', ['action' => 'proses'])) ?>" novalidate>
      <?= csrf_field() ?>

      <!-- Data pendukung opsional — ringkas satu baris -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
              <div class="small fw-semibold"><i class="bi bi-person-vcard text-secondary"></i> Data pendukung</div>
              <div class="text-secondary small">Opsional, boleh dikosongkan.</div>
            </div>
            <div class="col-6 col-md-2">
              <label for="usia" class="form-label small mb-1">Usia (tahun)</label>
              <input type="number" class="form-control" id="usia" name="usia" min="1" max="120"
                     inputmode="numeric" placeholder="mis. 27"
                     value="<?= e($profil['usia'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-3">
              <label for="jenis_kelamin" class="form-label small mb-1">Jenis kelamin</label>
              <select class="form-select" id="jenis_kelamin" name="jenis_kelamin">
                <option value="">— tidak diisi —</option>
                <option value="L" <?= ($profil['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= ($profil['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label for="status_merokok" class="form-label small mb-1">Status merokok</label>
              <select class="form-select" id="status_merokok" name="status_merokok">
                <option value="">— tidak diisi —</option>
                <option value="tidak" <?= ($profil['status_merokok'] ?? '') === 'tidak' ? 'selected' : '' ?>>Tidak merokok</option>
                <option value="mantan" <?= ($profil['status_merokok'] ?? '') === 'mantan' ? 'selected' : '' ?>>Mantan perokok</option>
                <option value="aktif" <?= ($profil['status_merokok'] ?? '') === 'aktif' ? 'selected' : '' ?>>Perokok aktif</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Gejala -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body pb-0">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
              <h2 class="h5 mb-1">Apa yang Anda rasakan?</h2>
              <p class="text-secondary small mb-0">
                Pilih <strong>minimal 3 keluhan</strong>. Anda bisa menjawab satu per satu
                sesuai kelompok keluhan di bawah ini.
              </p>
            </div>
            <span class="badge text-bg-secondary fs-6" id="hitungGejala" aria-live="polite">
              0 dari 3 keluhan dipilih
            </span>
          </div>

          <div class="input-group mt-3">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="cariGejala"
                   placeholder="Cari keluhan (mis. batuk, demam, sesak)…"
                   aria-label="Cari keluhan" autocomplete="off">
            <button class="btn btn-outline-secondary d-none" type="button" id="bersihkanCari"
                    aria-label="Bersihkan pencarian">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>

          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="filterTerpilih" aria-pressed="false">
              <i class="bi bi-check2-square"></i> Tampilkan yang sudah saya pilih saja
            </button>
            <span class="small text-secondary" id="labelLangkah" aria-live="polite">
              Langkah 1 dari <?= e($jumlahKategori) ?>
            </span>
          </div>

          <div class="progress mt-2" style="height: 6px" role="progressbar"
               aria-label="Kemajuan pengisian langkah"
               aria-valuemin="1" aria-valuemax="<?= e($jumlahKategori) ?>" aria-valuenow="1">
            <div class="progress-bar" id="progresLangkah"
                 style="width: <?= e(number_format(100 / max(1, $jumlahKategori), 2, '.', '')) ?>%"></div>
          </div>

          <!-- Navigasi langkah per kategori -->
          <ul class="nav nav-pills nav-langkah gap-1 mt-3 pb-1" id="navLangkah"
              aria-label="Pilih kelompok keluhan">
            <?php $noKat = 0; foreach ($grup as $kategori => $daftar): $noKat++; ?>
              <li class="nav-item">
                <button type="button" class="nav-link langkah-nav <?= $noKat === 1 ? 'active' : '' ?>"
                        data-langkah="<?= e($noKat - 1) ?>" aria-controls="langkah<?= $noKat ?>"
                        <?= $noKat === 1 ? 'aria-current="step"' : '' ?>>
                  <span class="langkah-nomor"><?= e($noKat) ?></span>
                  <?= e($kategori) ?>
                  <span class="badge rounded-pill text-bg-light border ms-1 d-none" data-jumlah-nav>0</span>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <!-- Isi tiap langkah/kategori -->
        <div id="wadahLangkah" class="pb-3">
          <?php $noKat = 0; foreach ($grup as $kategori => $daftar): $noKat++; $terakhir = $noKat === $jumlahKategori; ?>
            <section id="langkah<?= $noKat ?>" class="langkah-gejala px-3 pt-3"
                     data-langkah="<?= e($noKat - 1) ?>" aria-labelledby="judulLangkah<?= $noKat ?>"
                     <?= $noKat === 1 ? '' : 'hidden' ?>>
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h3 class="h6 mb-0" id="judulLangkah<?= $noKat ?>">
                  <i class="bi bi-<?= $noKat === 1 ? 'body-text' : 'dot' ?> text-primary"></i>
                  <?= e($kategori) ?>
                </h3>
                <span class="badge text-bg-light border" data-jumlah-kategori>0 dipilih</span>
              </div>

              <div class="row g-2">
                <?php foreach ($daftar as $g):
                    $id         = (int) $g['id_gejala'];
                    $val        = (int) ($nilai[$id] ?? 0);
                    $tipe       = (string) $g['tipe_input'];
                    $daftarPil  = $pilihan[$tipe] ?? $pilihan['biner'];
                    $teksCari   = strtolower($g['kode_gejala'] . ' ' . $g['nama_gejala'] . ' ' . $g['deskripsi'] . ' ' . $g['kategori']);
                    $merah      = (int) $g['is_redflag'] === 1;
                ?>
                  <div class="col-md-6 gejala-item" data-teks="<?= e($teksCari) ?>"
                       data-redflag="<?= $merah ? '1' : '0' ?>" data-nama="<?= e($g['nama_gejala']) ?>">
                    <div class="card h-100 gejala-card <?= $merah ? 'border-danger-subtle' : '' ?>">
                      <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                          <div class="fw-semibold"><?= e($g['nama_gejala']) ?></div>
                          <?php if ($merah): ?>
                            <span class="badge text-bg-danger text-nowrap" title="Keluhan darurat — segera ke IGD bila mengalaminya">
                              <i class="bi bi-exclamation-triangle-fill"></i> Darurat
                            </span>
                          <?php endif; ?>
                        </div>
                        <p class="small text-secondary mb-2"><?= e(deskripsi_awam((string) $g['deskripsi'])) ?></p>

                        <div class="d-flex flex-wrap gap-1" role="group"
                             aria-label="Pilih kondisi <?= e($g['nama_gejala']) ?>">
                          <?php foreach ($daftarPil as $n => $teks): ?>
                            <input type="radio" class="btn-check" name="gejala[<?= e($id) ?>]"
                                   id="g<?= e($id) ?>_<?= e($n) ?>" value="<?= e($n) ?>" autocomplete="off"
                                   <?= $val === $n ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary btn-pil" for="g<?= e($id) ?>_<?= e($n) ?>">
                              <?= e($teks) ?>
                            </label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <p class="text-secondary small mt-2 mb-0 d-none" data-tidak-ada>
                Tidak ada keluhan pada kelompok ini yang cocok dengan pencarian.
              </p>

              <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-tap" data-langkah-prev
                        <?= $noKat === 1 ? 'disabled' : '' ?>>
                  <i class="bi bi-arrow-left me-1"></i> Sebelumnya
                </button>
                <button type="button" class="btn btn-primary btn-tap" data-langkah-next
                        data-selesai="<?= $terakhir ? '1' : '0' ?>">
                  <?= $terakhir ? 'Selesai, lanjut ke persetujuan' : 'Kelompok Berikutnya' ?>
                  <i class="bi bi-arrow-right ms-1"></i>
                </button>
              </div>
            </section>
          <?php endforeach; ?>
        </div>

        <div class="px-3 pb-2">
          <p class="alert alert-info mb-0 d-none" id="tidakAdaHasil" role="status"></p>
        </div>

        <div class="card-body border-top" id="bagianPersetujuan">
          <div class="alert alert-warning py-2 d-none" id="validasiKlien" role="alert"></div>
          <div class="form-check mb-3">
            <input class="form-check-input form-check-input-tap" type="checkbox" id="consent" name="consent" value="1"
                   <?= $consent ? 'checked' : '' ?> required>
            <label class="form-check-label small" for="consent">
              Saya memahami bahwa hasil sistem ini hanya <strong>skrining awal</strong>, bukan diagnosis pasti,
              dan saya akan berkonsultasi dengan tenaga kesehatan untuk memastikan kondisi saya.
              Data yang saya isi disimpan untuk keperluan riwayat dan peningkatan basis kasus.
            </label>
          </div>
          <div class="d-flex flex-wrap gap-2 justify-content-end">
            <a class="btn btn-outline-secondary" href="<?= e(url('index')) ?>">Batal</a>
            <button class="btn btn-primary btn-tap px-4" type="submit" id="tombolProses">
              <i class="bi bi-search-heart"></i> Lihat Hasil Skrining
            </button>
          </div>
        </div>
      </div>
    </form>

    <p class="small text-secondary">
      <i class="bi bi-info-circle"></i> Bila Anda mengalami sesak berat saat istirahat,
      bibir kebiruan, nyeri dada hebat, atau batuk darah — <strong>segera ke IGD</strong>;
      jangan menunggu hasil sistem ini.
      <span class="d-block d-md-inline text-secondary">Total <?= e($jumlahGejala) ?> keluhan pada <?= e($jumlahKategori) ?> kelompok.</span>
    </p>
  </div>
</div>
<script src="<?= e(asset_url('js/diagnosa.js')) ?>"></script>
<?php require __DIR__ . '/../layouts/footer.php' ?>
