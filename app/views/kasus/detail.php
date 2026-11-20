<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var array $k */ /** @var array $detail */ /** @var int $referensi */
$warnaStatus = ['aktif' => 'success', 'nonaktif' => 'secondary', 'arsip' => 'dark'];
$warnaSumber = ['pakar' => 'info', 'retain' => 'warning', 'import' => 'light'];
$labelTipe = Gejala::LABEL_TIPE;
?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle"></i> Metadata</div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-5">Kode Kasus</dt><dd class="col-7"><strong><?= e($k['kode_kasus']) ?></strong></dd>
          <dt class="col-5">Penyakit</dt><dd class="col-7"><?= e($k['kode_penyakit']) ?> — <?= e($k['nama_penyakit']) ?></dd>
          <dt class="col-5">Sumber</dt><dd class="col-7"><span class="badge text-bg-<?= $warnaSumber[$k['sumber']] ?? 'secondary' ?>"><?= e($k['sumber']) ?></span></dd>
          <dt class="col-5">Status</dt><dd class="col-7"><span class="badge text-bg-<?= $warnaStatus[$k['status']] ?? 'secondary' ?>"><?= e($k['status']) ?></span></dd>
          <dt class="col-5">Frekuensi</dt><dd class="col-7"><?= e((int) $k['frekuensi_digunakan']) ?>× jadi rujukan</dd>
          <dt class="col-5">Dibuat</dt><dd class="col-7"><?= e($k['created_at']) ?></dd>
          <dt class="col-5">Referensi</dt><dd class="col-7"><?= e($referensi) ?> rekaman riwayat</dd>
        </dl>
        <?php if (!empty($k['solusi'])): ?>
          <hr>
          <div class="fw-semibold small mb-1">Solusi (dari master penyakit)</div>
          <p class="small text-secondary mb-0"><?= nl2br(e($k['solusi'])) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-pulse"></i> Vektor Gejala</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr><th>Kode</th><th>Gejala</th><th>Kategori</th><th>Tipe</th><th>Bobot</th><th class="text-center">Nilai</th></tr>
            </thead>
            <tbody>
            <?php foreach ($detail as $d): ?>
              <tr class="<?= (int) $d['nilai'] === 0 ? 'text-secondary' : '' ?>">
                <td class="text-nowrap"><strong><?= e($d['kode_gejala']) ?></strong></td>
                <td class="small"><?= e($d['nama_gejala']) ?>
                  <?php if ((int) $d['is_redflag'] === 1): ?><i class="bi bi-exclamation-triangle-fill text-danger" title="Red-flag"></i><?php endif; ?>
                </td>
                <td class="small"><?= e($d['kategori']) ?></td>
                <td class="small"><?= e($labelTipe[$d['tipe_input']] ?? $d['tipe_input']) ?></td>
                <td class="small"><?= e(rtrim(rtrim(number_format((float) $d['bobot'], 2), '0'), '.')) ?></td>
                <td class="text-center">
                  <?php if ((int) $d['nilai'] > 0): ?>
                    <span class="badge text-bg-primary fs-6"><?= e((int) $d['nilai']) ?></span>
                  <?php else: ?>
                    <span class="text-secondary small">0</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('kasus')) ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
        <a class="btn btn-warning btn-sm" href="<?= e(url('kasus', ['action' => 'form', 'id' => (int) $k['id_kasus']])) ?>"><i class="bi bi-pencil"></i> Ubah Kasus</a>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
