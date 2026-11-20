<?php
require __DIR__ . '/../layouts/admin_header.php';
/**
 * app/views/validasi/index.php — antrean & riwayat validasi pakar (FR-A6).
 * Variabel: $tab, $urut, $rows, $stat
 */
$labelTab = [
    'menunggu'      => 'Menunggu Validasi',
    'terkonfirmasi' => 'Diterima',
    'direvisi'      => 'Direvisi',
    'ditolak'       => 'Ditolak',
];
$warnaStatus = [
    'terkonfirmasi' => 'success',
    'direvisi'      => 'warning',
    'ditolak'       => 'danger',
];
$warnaJalur = ['reuse' => 'success', 'revise' => 'warning', 'baru' => 'secondary'];
$jumlahTab = [
    'menunggu'      => (int) ($stat['menunggu'] ?? 0),
    'terkonfirmasi' => (int) ($stat['terima'] ?? 0),
    'direvisi'      => (int) ($stat['revisi'] ?? 0),
    'ditolak'       => (int) ($stat['tolak'] ?? 0),
];
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary small">Menunggu validasi</div>
        <div class="display-6 fw-semibold text-warning"><?= e($stat['menunggu']) ?></div>
        <div class="small text-secondary">diagnosa dalam antrean</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary small">Diterima</div>
        <div class="display-6 fw-semibold text-success"><?= e($stat['terima']) ?></div>
        <div class="small text-secondary">hasil sistem terkonfirmasi</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary small">Direvisi</div>
        <div class="display-6 fw-semibold text-primary"><?= e($stat['revisi']) ?></div>
        <div class="small text-secondary">penyakit final diubah pakar</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary small">Tingkat akurasi</div>
        <div class="display-6 fw-semibold">
          <?= $stat['akurasi'] !== null ? e(number_format((float) $stat['akurasi'] * 100, 1, ',', '.')) . '%' : '—' ?>
        </div>
        <div class="small text-secondary">terima / (terima + revisi)</div>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <ul class="nav nav-pills gap-1" role="tablist" aria-label="Kelompok validasi">
        <?php foreach ($labelTab as $kunci => $label): ?>
          <li class="nav-item">
            <a class="nav-link <?= $tab === $kunci ? 'active' : 'text-body' ?>"
               href="<?= e(url('validasi', ['tab' => $kunci])) ?>">
              <?= e($label) ?>
              <span class="badge rounded-pill <?= $tab === $kunci ? 'text-bg-light' : 'text-bg-secondary' ?> ms-1">
                <?= e($jumlahTab[$kunci]) ?>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($tab === 'menunggu'): ?>
        <div class="btn-group btn-group-sm" role="group" aria-label="Urutkan antrean">
          <a class="btn <?= $urut === 'tanggal' ? 'btn-primary' : 'btn-outline-primary' ?>"
             href="<?= e(url('validasi', ['tab' => 'menunggu', 'urut' => 'tanggal'])) ?>">
            <i class="bi bi-clock-history"></i> Tanggal (terlama)
          </a>
          <a class="btn <?= $urut === 'similarity' ? 'btn-primary' : 'btn-outline-primary' ?>"
             href="<?= e(url('validasi', ['tab' => 'menunggu', 'urut' => 'similarity'])) ?>">
            <i class="bi bi-sort-down"></i> Similarity terendah
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body">
    <?php if ($rows === []): ?>
      <div class="text-center text-secondary py-5">
        <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success"></i>
        <?= $tab === 'menunggu'
            ? 'Tidak ada diagnosa yang menunggu validasi. Antrean bersih!'
            : 'Belum ada riwayat validasi pada kelompok ini.' ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Tanggal</th>
              <?php if ($tab === 'menunggu'): ?>
                <th>Data Pasien</th>
                <th>Hasil Sistem</th>
                <th class="text-end">Similarity</th>
                <th class="text-center">Gejala</th>
              <?php else: ?>
                <th>Hasil Sistem</th>
                <th>Penyakit Final</th>
                <th>Keputusan</th>
                <th>Kasus</th>
                <th>Pakar</th>
              <?php endif; ?>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td class="text-secondary"><?= e($i + 1) ?></td>
              <td class="text-nowrap small">
                <?= e(date('d/m/Y H:i', strtotime((string) ($r['divalidasi_pada'] ?? $r['created_at'])))) ?>
              </td>

              <?php if ($tab === 'menunggu'): ?>
                <td class="small">
                  <?= e($r['usia'] !== null ? $r['usia'] . ' th' : 'Usia —') ?>
                  · <?= e($r['jenis_kelamin'] === 'L' ? 'Laki-laki' : ($r['jenis_kelamin'] === 'P' ? 'Perempuan' : '—')) ?>
                  <div class="text-secondary"><?= e(label_merokok($r['status_merokok'] ?? null)) ?></div>
                </td>
                <td>
                  <?php if ($r['nama_penyakit'] !== null): ?>
                    <strong><?= e($r['nama_penyakit']) ?></strong>
                    <div class="small text-secondary">
                      <span class="badge text-bg-<?= e($warnaJalur[$r['status_cbr']] ?? 'secondary') ?>">
                        <?= e($r['status_cbr']) ?>
                      </span>
                    </div>
                  <?php else: ?>
                    <span class="text-secondary">Belum ada kandidat</span>
                  <?php endif; ?>
                </td>
                <td class="text-end fw-semibold">
                  <?= $r['similarity_tertinggi'] !== null
                      ? e(number_format((float) $r['similarity_tertinggi'] * 100, 1, ',', '.')) . '%'
                      : '—' ?>
                </td>
                <td class="text-center"><span class="badge text-bg-light border"><?= e($r['jumlah_gejala']) ?></span></td>
              <?php else: ?>
                <td class="small">
                  <?= e($r['nama_hasil'] ?? '—') ?>
                  <?php if ($r['similarity_tertinggi'] !== null): ?>
                    <div class="text-secondary"><?= e(number_format((float) $r['similarity_tertinggi'] * 100, 1, ',', '.')) ?>%</div>
                  <?php endif; ?>
                </td>
                <td>
                  <?= e($r['nama_final'] ?? '—') ?>
                  <?php if ($r['kode_final'] !== null && $r['nama_final'] !== $r['nama_hasil']): ?>
                    <div class="small text-warning-emphasis"><i class="bi bi-arrow-repeat"></i> diubah pakar</div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge text-bg-<?= e($warnaStatus[$r['status_validasi']] ?? 'secondary') ?>">
                    <?= e(Validasi::LABEL_KEPUTUSAN[$r['keputusan']] ?? $r['keputusan']) ?>
                  </span>
                </td>
                <td class="small text-nowrap">
                  <?= $r['kode_kasus'] !== null ? e($r['kode_kasus']) : '<span class="text-secondary">duplikat</span>' ?>
                </td>
                <td class="small"><?= e($r['nama_pakar'] ?? '—') ?></td>
              <?php endif; ?>

              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-primary" href="<?= e(url('validasi', ['action' => 'detail', 'id' => (int) $r['id_diagnosa']])) ?>">
                  <i class="bi bi-eye"></i> <?= $tab === 'menunggu' ? 'Validasi' : 'Lihat' ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card-footer bg-white small text-secondary">
    <i class="bi bi-info-circle"></i>
    Retain hanya berjalan setelah keputusan pakar. Kasus yang ditolak disimpan sebagai
    <code>arsip</code> (bukan dihapus) untuk audit; kasus yang mirip dengan kasus lama tidak digandakan.
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
