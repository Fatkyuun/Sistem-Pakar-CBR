<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var ?array $hasil */ /** @var array $errors */ /** @var array $gejalaAktif */
$errors = $errors ?? [];
$hasil  = $hasil ?? null;
?>
<?php if ($errors !== []): ?>
  <div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php if ($hasil !== null): ?>
  <div class="alert alert-<?= $hasil['gagal'] === 0 ? 'success' : 'warning' ?>" role="alert">
    <strong>Ringkasan impor:</strong>
    <?= e((int) $hasil['sukses']) ?> kasus berhasil disimpan,
    <?= e((int) $hasil['duplikat']) ?> baris dilewati karena duplikat (≥ ambang batas),
    <?= e((int) $hasil['gagal']) ?> baris gagal divalidasi.
  </div>

  <?php if (!empty($hasil['dupRows'])): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold small"><i class="bi bi-skip-forward"></i> Baris Duplikat (dilewati otomatis)</div>
      <div class="card-body py-2">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Baris</th><th>Mirip dengan kasus</th><th>Similarity</th></tr></thead>
          <tbody>
          <?php foreach ($hasil['dupRows'] as $d): ?>
            <tr>
              <td><?= e($d['baris']) ?></td>
              <td><strong><?= e($d['kasus']) ?></strong></td>
              <td><?= e(number_format($d['similarity'] * 100, 1, ',', '.')) ?>%</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($hasil['gagalRows'])): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold small text-danger"><i class="bi bi-x-octagon"></i> Baris Gagal</div>
      <div class="card-body py-2">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Baris</th><th>Alasan</th></tr></thead>
          <tbody>
          <?php foreach ($hasil['gagalRows'] as $g): ?>
            <tr><td><?= e($g['baris']) ?></td><td><?= e($g['alasan']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-upload"></i> Impor Kasus dari CSV</div>
      <div class="card-body">
        <form method="post" action="<?= e(url('kasus', ['action' => 'import'])) ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label for="berkas" class="form-label">Berkas CSV (.csv / .txt, maksimal 2 MB) <span class="text-danger">*</span></label>
            <input type="file" class="form-control" id="berkas" name="berkas" accept=".csv,.txt,text/csv,text/plain" required>
          </div>
          <div class="form-check mb-3 small text-secondary">
            Format: kolom pertama <code>kode_penyakit</code>, disusul satu kolom per kode gejala
            (<?= e(count($gejalaAktif)) ?> kolom). Nilai 0-3; pemisah <code>;</code> atau <code>,</code> dideteksi otomatis.
            <a href="<?= e(url('kasus', ['action' => 'template'])) ?>"><i class="bi bi-file-earmark-arrow-down"></i> Unduh template</a>
          </div>
          <div class="alert alert-warning small mb-3">
            <i class="bi bi-exclamation-triangle"></i> Baris dengan similarity ≥
            <?= e(number_format((float) pengaturan('threshold_duplikat', '0.98') * 100, 1, ',', '.')) ?>%
            terhadap kasus aktif penyakit sama akan <strong>dilewati otomatis</strong> dan dilaporkan di ringkasan.
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-box-arrow-in-down"></i> Proses Impor</button>
          <a class="btn btn-outline-secondary" href="<?= e(url('kasus')) ?>">Batal</a>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-list-columns"></i> Kolom Template Saat Ini</div>
      <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-1">
          <span class="badge text-bg-primary">kode_penyakit</span>
          <?php foreach ($gejalaAktif as $g): ?>
            <span class="badge text-bg-light" title="<?= e($g['nama_gejala']) ?>"><?= e($g['kode_gejala']) ?></span>
          <?php endforeach; ?>
        </div>
        <p class="small text-secondary mt-2 mb-0">
          Kolom mengikuti master gejala aktif saat ini. Untuk gejala <em>biner</em> isi 0/1;
          <em>skala</em> 0-3 (ringan-berat); <em>durasi</em> 0-3 (tidak ada, &lt;3 hari, 3-7 hari, &gt;2 minggu).
        </p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
