<?php
require __DIR__ . '/../layouts/admin_header.php';
/**
 * app/views/validasi/detail.php — detail & keputusan validasi pakar.
 * Variabel: $d, $detailRows, $peta, $hasil, $kasusTeratas, $perbandingan,
 *           $kontribusi, $metode, $redflag, $penyakitList
 */
$statusValidasi  = (string) $d['status_validasi'];
$sudahDivalidasi = in_array($statusValidasi, Validasi::STATUS_FINAL, true);
$tidakPerlu      = $statusValidasi === 'tidak_perlu';
$gejalaTerpilih  = array_values(array_filter($detailRows, static fn (array $r): bool => (int) $r['nilai'] > 0));
$warnaJalur      = ['reuse' => 'success', 'revise' => 'warning', 'baru' => 'secondary'];
$labelKeputusan  = Validasi::LABEL_KEPUTUSAN;
$warnaKeputusan  = ['terima' => 'success', 'revisi' => 'primary', 'tolak' => 'danger'];
$tipePerKode    = [];
foreach ($detailRows as $row) {
    $tipePerKode[(string) $row['kode_gejala']] = (string) $row['tipe_input'];
}
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <a class="btn btn-sm btn-outline-secondary mb-2" href="<?= e(url('validasi')) ?>">
      <i class="bi bi-arrow-left"></i> Kembali ke antrean
    </a>
    <h2 class="h4 mb-1">Diagnosa #<?= e((int) $d['id_diagnosa']) ?></h2>
    <div class="small text-secondary">
      <?= e(date('d/m/Y H:i', strtotime((string) $d['created_at']))) ?>
      · <?= e($d['usia'] !== null ? $d['usia'] . ' tahun' : 'usia tidak diisi') ?>
      · <?= e($d['jenis_kelamin'] === 'L' ? 'Laki-laki' : ($d['jenis_kelamin'] === 'P' ? 'Perempuan' : 'jenis kelamin tidak diisi')) ?>
      · <?= e(label_merokok($d['status_merokok'] ?? null)) ?>
    </div>
  </div>
  <div class="text-end">
    <span class="badge text-bg-<?= $sudahDivalidasi ? e($warnaKeputusan[$d['keputusan']] ?? 'secondary') : ($tidakPerlu ? 'success' : 'warning') ?> fs-6">
      <?= $sudahDivalidasi
          ? 'Sudah divalidasi: ' . e($labelKeputusan[$d['keputusan']] ?? $d['keputusan'])
          : ($tidakPerlu ? 'Tidak perlu validasi (jalur Reuse)' : 'Menunggu validasi') ?>
    </span>
    <div class="small text-secondary mt-1">
      Jalur CBR:
      <span class="badge text-bg-<?= e($warnaJalur[$d['status_cbr']] ?? 'secondary') ?>"><?= e($d['status_cbr'] ?? '—') ?></span>
    </div>
  </div>
</div>

<?php if ($redflag !== []): ?>
  <div class="alert alert-danger" role="alert">
    <strong><i class="bi bi-exclamation-octagon-fill"></i> Gejala darurat pada pasien:</strong>
    <?= e(implode(', ', array_column($redflag, 'nama_gejala'))) ?>.
    Pasien sudah diarahkan ke IGD pada halaman hasil.
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <!-- ================= DATA PASIEN ================= -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-pulse"></i> Gejala yang Diinput Pasien</div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3 small">
          <span class="badge text-bg-light border">Usia: <?= e($d['usia'] !== null ? $d['usia'] . ' th' : '—') ?></span>
          <span class="badge text-bg-light border">JK: <?= e($d['jenis_kelamin'] ?? '—') ?></span>
          <span class="badge text-bg-light border"><?= e(label_merokok($d['status_merokok'] ?? null)) ?></span>
          <span class="badge text-bg-light border"><?= e(count($gejalaTerpilih)) ?> gejala &gt; 0</span>
        </div>

        <?php if ($gejalaTerpilih === []): ?>
          <p class="text-secondary mb-0">Tidak ada gejala bernilai &gt; 0.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0 small">
            <?php foreach ($gejalaTerpilih as $g): $nilai = (int) $g['nilai']; $tipe = (string) $g['tipe_input']; ?>
              <li class="d-flex justify-content-between align-items-start gap-2 py-1 border-bottom">
                <span>
                  <span class="text-secondary"><?= e($g['kode_gejala']) ?></span>
                  <?= e($g['nama_gejala']) ?>
                  <?php if ((int) $g['is_redflag'] === 1): ?>
                    <i class="bi bi-exclamation-triangle-fill text-danger" title="Gejala darurat"></i>
                  <?php endif; ?>
                  <span class="d-block text-secondary" style="font-size:.78rem"><?= e($g['kategori']) ?></span>
                </span>
                <span class="badge <?= e(kelas_nilai_gejala($tipe, $nilai)) ?> text-nowrap">
                  <?= e(label_nilai_gejala($tipe, $nilai)) ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ================= HASIL SISTEM ================= -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-robot"></i> Hasil &amp; Kandidat Sistem</div>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <div class="text-secondary small">Hasil teratas</div>
            <div class="h5 mb-0"><?= e($d['nama_hasil'] ?? 'Belum ada kandidat') ?></div>
            <?php if ($d['urgensi_hasil'] !== null): ?>
              <span class="badge text-bg-light border">Urgensi: <?= e($d['urgensi_hasil']) ?></span>
            <?php endif; ?>
          </div>
          <div class="text-end">
            <div class="display-6 fw-semibold text-primary lh-1">
              <?= $d['similarity_tertinggi'] !== null
                  ? e(number_format((float) $d['similarity_tertinggi'] * 100, 1, ',', '.')) . '%'
                  : '—' ?>
            </div>
            <div class="small text-secondary">similarity tertinggi</div>
          </div>
        </div>

        <?php if ($hasil === []): ?>
          <p class="text-secondary small mb-0">Belum ada hasil Retrieve tersimpan.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
              <thead class="table-light">
                <tr>
                  <th>#</th><th>Kasus</th><th>Penyakit</th><th class="text-end">Similarity</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($hasil as $h): ?>
                  <tr>
                    <td class="text-secondary"><?= e((int) $h['peringkat']) ?></td>
                    <td><strong><?= e($h['kode_kasus']) ?></strong></td>
                    <td><?= e($h['nama_penyakit']) ?></td>
                    <td class="text-end"><?= e(number_format((float) $h['similarity'] * 100, 1, ',', '.')) ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ================= PERBANDINGAN KASUS RUJUKAN (COLLAPSIBLE) ================= -->
<?php if ($kasusTeratas !== null): ?>
  <div class="card spc-admin-card mb-3">
    <div class="card-body p-0">
      <details class="spc-details">
        <summary class="spc-details-summary p-3 d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-files text-primary"></i>
            <span class="fw-semibold text-slate-800">Perbandingan dengan Kasus Rujukan Terdekat</span>
            <span class="badge spc-badge-pastel-blue">
              <?= e($kasusTeratas['kode_kasus']) ?> · <?= e($kasusTeratas['nama_penyakit']) ?> · <?= e(count($perbandingan['cocok'])) ?> gejala cocok
            </span>
          </div>
          <span class="small text-secondary spc-details-hint"><i class="bi bi-chevron-down"></i></span>
        </summary>
        <div class="px-3 pb-3 border-top pt-3">
          <div class="row g-3 small">
            <div class="col-md-6">
              <div class="fw-semibold text-success mb-1"><i class="bi bi-check2-circle"></i> Sama (<?= e(count($perbandingan['cocok'])) ?>)</div>
              <?php if ($perbandingan['cocok'] === []): ?><p class="text-secondary mb-0">Tidak ada.</p><?php endif; ?>
              <ul class="mb-0 ps-3">
                <?php foreach ($perbandingan['cocok'] as $b): ?>
                  <li><?= e($b['kode_gejala']) ?> <?= e($b['nama_gejala']) ?> —
                    <?= e(label_nilai_gejala($tipePerKode[(string) $b['kode_gejala']] ?? 'skala', (int) $b['nilai_input'])) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="col-md-6">
              <div class="fw-semibold text-warning mb-1"><i class="bi bi-shuffle"></i> Beda tingkat (<?= e(count($perbandingan['beda'])) ?>)</div>
              <?php if ($perbandingan['beda'] === []): ?><p class="text-secondary mb-0">Tidak ada.</p><?php endif; ?>
              <ul class="mb-0 ps-3">
                <?php foreach ($perbandingan['beda'] as $b): ?>
                  <li><?= e($b['kode_gejala']) ?> <?= e($b['nama_gejala']) ?> —
                    pasien <?= e(label_nilai_gejala($tipePerKode[(string) $b['kode_gejala']] ?? 'skala', (int) $b['nilai_input'])) ?>,
                    kasus <?= e(label_nilai_gejala($tipePerKode[(string) $b['kode_gejala']] ?? 'skala', (int) $b['nilai_kasus'])) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="col-md-6">
              <div class="fw-semibold text-secondary mb-1"><i class="bi bi-plus-circle"></i> Hanya pada pasien (<?= e(count($perbandingan['hanya_input'])) ?>)</div>
              <?php if ($perbandingan['hanya_input'] === []): ?><p class="text-secondary mb-0">Tidak ada.</p><?php endif; ?>
              <ul class="mb-0 ps-3">
                <?php foreach ($perbandingan['hanya_input'] as $b): ?>
                  <li><?= e($b['kode_gejala']) ?> <?= e($b['nama_gejala']) ?>
                    (<?= e(label_nilai_gejala($tipePerKode[(string) $b['kode_gejala']] ?? 'skala', (int) $b['nilai_input'])) ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="col-md-6">
              <div class="fw-semibold text-secondary mb-1"><i class="bi bi-dash-circle"></i> Hanya pada kasus (<?= e(count($perbandingan['hanya_kasus'])) ?>)</div>
              <?php if ($perbandingan['hanya_kasus'] === []): ?><p class="text-secondary mb-0">Tidak ada.</p><?php endif; ?>
              <ul class="mb-0 ps-3">
                <?php foreach ($perbandingan['hanya_kasus'] as $b): ?>
                  <li><?= e($b['kode_gejala']) ?> <?= e($b['nama_gejala']) ?>
                    (<?= e(label_nilai_gejala($tipePerKode[(string) $b['kode_gejala']] ?? 'skala', (int) $b['nilai_kasus'])) ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </details>
    </div>
  </div>
<?php endif; ?>

<!-- ================= KEPUTUSAN ================= -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-check2-square"></i> Keputusan Pakar</div>
  <div class="card-body">

    <?php if ($sudahDivalidasi): ?>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="text-secondary small">Keputusan</div>
          <div class="h5">
            <span class="badge text-bg-<?= e($warnaKeputusan[$d['keputusan']] ?? 'secondary') ?> fs-6">
              <?= e($labelKeputusan[$d['keputusan']] ?? $d['keputusan']) ?>
            </span>
          </div>
          <div class="small text-secondary">
            oleh <?= e($d['nama_pakar'] ?? '—') ?><br>
            <?= e(date('d/m/Y H:i', strtotime((string) $d['divalidasi_pada']))) ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary small">Penyakit final</div>
          <div class="fw-semibold"><?= e($d['nama_final'] ?? '— (tidak ada)') ?></div>
          <div class="small text-secondary mt-1">
            Kasus baru: <?= e($d['kode_kasus_baru'] ?? 'tidak ada (duplikat / arsip)') ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary small">Catatan</div>
          <div class="small"><?= $d['catatan'] !== null && $d['catatan'] !== '' ? nl2br(e($d['catatan'])) : '<span class="text-secondary">—</span>' ?></div>
        </div>
        <?php if ($d['keputusan'] === 'revisi' && $d['solusi_revisi'] !== null && $d['solusi_revisi'] !== ''): ?>
          <div class="col-12">
            <div class="saran-blok">
              <div class="fw-semibold mb-1"><i class="bi bi-clipboard2-check text-primary"></i> Solusi revisi pakar</div>
              <div class="small"><?= nl2br(e($d['solusi_revisi'])) ?></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php elseif ($tidakPerlu): ?>
      <div class="alert alert-success small mb-0">
        <i class="bi bi-check2-circle"></i>
        Diagnosa ini tidak memerlukan validasi pakar karena hasil sistem sudah cukup kuat
        (jalur <strong>Reuse</strong>) dan langsung memakai kasus rujukan yang ada.
      </div>
    <?php else: ?>

      <form method="post" action="<?= e(url('validasi', ['action' => 'putuskan'])) ?>" id="formValidasi" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((int) $d['id_diagnosa']) ?>">

        <div class="row g-2 mb-3">
          <?php
          $opsi = [
              'terima' => ['Terima', 'Hasil sistem sudah tepat. Penyakit final = hasil sistem, lalu kasus di-retain.', 'success', 'check-circle'],
              'revisi' => ['Revisi', 'Penyakit final diubah/dikoreksi, boleh menambah solusi & koreksi gejala.', 'primary', 'pencil-square'],
              'tolak'  => ['Tolak', 'Hasil tidak dipakai. Alasan wajib diisi; kasus disimpan sebagai arsip.', 'danger', 'x-circle'],
          ];
          foreach ($opsi as $nilai => [$judul, $ket, $warna, $ikon]): ?>
            <div class="col-md-4">
              <input type="radio" class="btn-check" name="keputusan" id="kep<?= e($nilai) ?>"
                     value="<?= e($nilai) ?>" <?= $nilai === 'terima' ? 'checked' : '' ?> autocomplete="off">
              <label class="card h-100 border-2 keputusan-kartu" for="kep<?= e($nilai) ?>">
                <div class="card-body p-3">
                  <div class="fw-semibold text-<?= e($warna) ?> mb-1">
                    <i class="bi bi-<?= e($ikon) ?>"></i> <?= e($judul) ?>
                  </div>
                  <div class="small text-secondary"><?= e($ket) ?></div>
                </div>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Blok Terima -->
        <div data-blok-keputusan="terima">
          <div class="alert alert-success small mb-3">
            Penyakit final: <strong><?= e($d['nama_hasil'] ?? '—') ?></strong>.
            Kasus akan di-retain otomatis; bila terdeteksi duplikat, frekuensi kasus lama ditambah.
          </div>
        </div>

        <!-- Blok Revisi -->
        <div data-blok-keputusan="revisi" hidden>
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label small mb-1" for="id_penyakit_final">Penyakit final <span class="text-danger">*</span></label>
              <select class="form-select" id="id_penyakit_final" name="id_penyakit_final" required>
                <option value="0">— pilih penyakit —</option>
                <?php foreach ($penyakitList as $p): ?>
                  <option value="<?= e((int) $p['id_penyakit']) ?>" <?= (int) $d['id_penyakit_hasil'] === (int) $p['id_penyakit'] ? 'selected' : '' ?>>
                    <?= e($p['kode_penyakit'] . ' — ' . $p['nama_penyakit']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">
                Penyakit belum ada?
                <a href="<?= e(url('penyakit', ['action' => 'form'])) ?>" target="_blank" rel="noopener">
                  Tambah penyakit baru
                </a>
                di tab baru, lalu muat ulang halaman ini.
              </div>
            </div>
            <div class="col-md-7">
              <label class="form-label small mb-1" for="solusi_revisi">Solusi / saran revisi (opsional)</label>
              <textarea class="form-control" id="solusi_revisi" name="solusi_revisi" rows="2"
                        placeholder="Saran penanganan yang lebih tepat menurut pakar…"></textarea>
            </div>
          </div>

          <div class="mt-3">
            <div class="fw-semibold small mb-1"><i class="bi bi-sliders"></i> Koreksi nilai gejala (opsional)</div>
            <p class="text-secondary small mb-2">
              Nilai awal pasien ditampilkan. Ubah bila pakar menemukan ketidaksesuaian;
              nilai terkoreksi hanya dipakai untuk kasus yang di-retain, riwayat pasien tetap utuh.
            </p>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                  <tr><th>Gejala</th><th style="width:14rem">Nilai</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($gejalaTerpilih as $g):
                      $idG   = (int) $g['id_gejala'];
                      $tipe  = (string) $g['tipe_input'];
                      $nilai = (int) $g['nilai'];
                      $maks  = $tipe === 'biner' ? 1 : 3;
                  ?>
                    <tr>
                      <td>
                        <span class="text-secondary"><?= e($g['kode_gejala']) ?></span>
                        <?= e($g['nama_gejala']) ?>
                      </td>
                      <td>
                        <select class="form-select form-select-sm" name="gejala[<?= e($idG) ?>]"
                                aria-label="Nilai <?= e($g['nama_gejala']) ?>">
                          <?php for ($n = 0; $n <= $maks; $n++): ?>
                            <option value="<?= e($n) ?>" <?= $nilai === $n ? 'selected' : '' ?>>
                              <?= e(label_nilai_gejala($tipe, $n)) ?>
                            </option>
                          <?php endfor; ?>
                        </select>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label small mb-1" for="catatan">
            Catatan pakar <span class="text-danger" id="wajibCatatan">*</span>
          </label>
          <textarea class="form-control" id="catatan" name="catatan" rows="2"
                    placeholder="Alasan penolakan wajib diisi; untuk terima/revisi boleh dikosongkan."></textarea>
          <div class="form-text">Catatan ditampilkan kepada pasien pada halaman hasil dan riwayat.</div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <p class="small text-secondary mb-0">
            <i class="bi bi-shield-lock"></i> Keputusan, Retain, dan log dicatat dalam satu transaksi.
          </p>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-save"></i> Simpan Keputusan
          </button>
        </div>
      </form>

      <script>
      (function () {
          'use strict';
          var form = document.getElementById('formValidasi');
          if (!form) { return; }
          var blok = Array.prototype.slice.call(form.querySelectorAll('[data-blok-keputusan]'));
          var catatan = document.getElementById('catatan');
          var wajibCatatan = document.getElementById('wajibCatatan');

          function atur() {
              var radio = form.querySelector('input[name="keputusan"]:checked');
              var nilai = radio ? radio.value : 'terima';
              blok.forEach(function (el) {
                  var aktif = el.dataset.blokKeputusan === nilai;
                  el.hidden = !aktif;
                  Array.prototype.forEach.call(el.querySelectorAll('input,select,textarea'), function (f) {
                      f.disabled = !aktif;
                  });
              });
              if (wajibCatatan) { wajibCatatan.hidden = nilai !== 'tolak'; }
              if (catatan) { catatan.required = nilai === 'tolak'; }
          }

          Array.prototype.forEach.call(form.querySelectorAll('input[name="keputusan"]'), function (r) {
              r.addEventListener('change', atur);
          });
          atur();
      })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
