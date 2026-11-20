<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var ?array $p */ /** @var array $errors */ /** @var array $old */
$p      = $p ?? null;
$errors = $errors ?? [];
$old    = $old ?? [];

/** Ambil nilai formulir: prioritas input terakhir ($old) lalu data DB ($p). */
$val = function (string $key, string $default = '') use ($old, $p): string {
    if (array_key_exists($key, $old)) {
        if (is_scalar($old[$key])) {
            return (string) $old[$key];
        }
    }
    $keyDb = $key;
    if ($p !== null && array_key_exists($keyDb, $p)) {
        return (string) $p[$keyDb];
    }
    return $default;
};
?>
<?php if ($errors !== []): ?>
  <div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php if ($p !== null): ?>
  <div class="alert alert-light border small mb-3">
    <i class="bi bi-database"></i> Penyakit ini dipakai oleh <strong><?= e($jumlahKasus ?? 0) ?> kasus</strong>.
    Menonaktifkan penyakit <em>tidak</em> menghapus kasus lama.
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">
    <i class="bi <?= $p === null ? 'bi-plus-lg' : 'bi-pencil' ?>"></i>
    <?= $p === null ? 'Tambah Penyakit Baru' : 'Ubah Penyakit ' . e($p['kode_penyakit']) ?>
  </div>
  <div class="card-body">
    <form method="post" action="<?= e(url('penyakit', ['action' => 'save'])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e($p === null ? 0 : (int) $p['id_penyakit']) ?>">
      <div class="row g-3">
        <div class="col-md-3">
          <label for="kode_penyakit" class="form-label">Kode <span class="text-danger">*</span></label>
          <input type="text" class="form-control text-uppercase" id="kode_penyakit" name="kode_penyakit"
                 value="<?= e($val('kode_penyakit')) ?>" maxlength="10" placeholder="P01" required
                 pattern="[A-Za-z0-9]{1,10}">
          <div class="form-text">1–10 karakter, huruf/angka.</div>
        </div>
        <div class="col-md-9">
          <label for="nama_penyakit" class="form-label">Nama Penyakit <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="nama_penyakit" name="nama_penyakit"
                 value="<?= e($val('nama_penyakit')) ?>" maxlength="120" required>
        </div>
        <div class="col-12">
          <label for="deskripsi" class="form-label">Deskripsi</label>
          <textarea class="form-control" id="deskripsi" name="deskripsi" rows="2"><?= e($val('deskripsi')) ?></textarea>
        </div>
        <div class="col-md-6">
          <label for="penyebab" class="form-label">Penyebab Umum</label>
          <textarea class="form-control" id="penyebab" name="penyebab" rows="3"><?= e($val('penyebab')) ?></textarea>
        </div>
        <div class="col-md-6">
          <label for="solusi" class="form-label">Solusi / Saran Penanganan</label>
          <textarea class="form-control" id="solusi" name="solusi" rows="3"><?= e($val('solusi')) ?></textarea>
          <div class="form-text">Ditampilkan pada hasil diagnosa (perawatan mandiri, kapan ke dokter).</div>
        </div>
        <div class="col-md-6">
          <label for="pencegahan" class="form-label">Pencegahan</label>
          <textarea class="form-control" id="pencegahan" name="pencegahan" rows="3"><?= e($val('pencegahan')) ?></textarea>
        </div>
        <div class="col-md-3">
          <label for="tingkat_urgensi" class="form-label">Tingkat Urgensi <span class="text-danger">*</span></label>
          <select class="form-select" id="tingkat_urgensi" name="tingkat_urgensi" required>
            <?php foreach (Penyakit::URGENSI as $u): ?>
              <option value="<?= e($u) ?>" <?= $val('tingkat_urgensi', 'ringan') === $u ? 'selected' : '' ?>>
                <?= e(Penyakit::LABEL_URGENSI[$u]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   <?= ($old['is_active'] ?? ($p['is_active'] ?? 1)) == 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Aktif (tampil di sistem)</label>
          </div>
        </div>
      </div>
      <hr>
      <div class="d-flex gap-2 justify-content-end">
        <a class="btn btn-outline-secondary" href="<?= e(url('penyakit')) ?>"><i class="bi bi-x-lg"></i> Batal</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> <?= $p === null ? 'Simpan Penyakit' : 'Perbarui Penyakit' ?></button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
