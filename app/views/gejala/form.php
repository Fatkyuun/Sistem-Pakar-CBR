<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var ?array $g */ /** @var array $errors */ /** @var array $old */ /** @var array $kategori */
$g        = $g ?? null;
$errors   = $errors ?? [];
$old      = $old ?? [];
$kategori = $kategori ?? [];

/** Ambil nilai formulir: prioritas input terakhir ($old) lalu data DB ($g). */
$val = static function (string $key, string $default = '') use ($old, $g): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($g !== null && array_key_exists($key, $g)) {
        return (string) $g[$key];
    }
    return $default;
};
?>
<?php if ($errors !== []): ?>
  <div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">
    <i class="bi <?= $g === null ? 'bi-plus-lg' : 'bi-pencil' ?>"></i>
    <?= $g === null ? 'Tambah Gejala Baru' : 'Ubah Gejala ' . e($g['kode_gejala']) ?>
  </div>
  <div class="card-body">
    <?php if ($g !== null): ?>
      <div class="alert alert-light border small mb-3">
        <i class="bi bi-database"></i> Gejala ini dipakai oleh <strong><?= e((int) $g['jumlah_kasus']) ?> kasus</strong>.
        Mengubah bobot/tipe input memengaruhi perhitungan similarity CBR; menonaktifkan gejala hanya
        menjauhatkannya dari formulir tanpa merusak kasus lama.
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('gejala', ['action' => 'save'])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e($g === null ? 0 : (int) $g['id_gejala']) ?>">
      <div class="row g-3">
        <div class="col-md-3">
          <label for="kode_gejala" class="form-label">Kode <span class="text-danger">*</span></label>
          <input type="text" class="form-control text-uppercase" id="kode_gejala" name="kode_gejala"
                 value="<?= e($val('kode_gejala')) ?>" maxlength="10" placeholder="G01" required
                 pattern="[A-Za-z0-9]{1,10}">
          <div class="form-text">1–10 karakter, huruf/angka.</div>
        </div>
        <div class="col-md-9">
          <label for="nama_gejala" class="form-label">Nama Gejala <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="nama_gejala" name="nama_gejala"
                 value="<?= e($val('nama_gejala')) ?>" maxlength="150" required>
        </div>
        <div class="col-md-4">
          <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="kategori" name="kategori" list="listKategori"
                 value="<?= e($val('kategori')) ?>" maxlength="50" required>
          <div class="form-text">Pengelompokan pada formulir diagnosa.</div>
          <datalist id="listKategori">
            <?php foreach ($kategori as $k): ?><option value="<?= e($k) ?>"></option><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-4">
          <label for="tipe_input" class="form-label">Tipe Input <span class="text-danger">*</span></label>
          <select class="form-select" id="tipe_input" name="tipe_input" required>
            <?php foreach (Gejala::TIPE_INPUT as $tipe): ?>
              <option value="<?= e($tipe) ?>" <?= $val('tipe_input', 'biner') === $tipe ? 'selected' : '' ?>>
                <?= e(Gejala::LABEL_TIPE[$tipe]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="bobot" class="form-label">Bobot Global (1–10) <span class="text-danger">*</span></label>
          <input type="number" class="form-control" id="bobot" name="bobot" value="<?= e($val('bobot', '1.00')) ?>"
                 min="1" max="10" step="0.5" required>
          <div class="form-text">Semakin tinggi, semakin menentukan hasil similarity. Versi ini memakai
            bobot global (<code>gejala.bobot</code>); bobot per penyakit belum dipakai mesin CBR.</div>
        </div>
        <div class="col-md-6">
          <label for="deskripsi" class="form-label">Deskripsi Bahasa Awam</label>
          <input type="text" class="form-control" id="deskripsi" name="deskripsi"
                 value="<?= e($val('deskripsi')) ?>" maxlength="255"
                 placeholder="Jelaskan gejala ini secara sederhana untuk pasien">
          <div class="form-text">Ditampilkan sebagai penjelasan/tooltip di formulir diagnosa.</div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_redflag" name="is_redflag" value="1"
                   <?= ($old['is_redflag'] ?? ($g['is_redflag'] ?? 0)) == 1 ? 'checked' : '' ?>>
            <label class="form-check-label text-danger" for="is_redflag">
              <i class="bi bi-exclamation-triangle"></i> Red-Flag (gejala darurat)
            </label>
            <div class="form-text">Selalu memunculkan peringatan segera ke IGD.</div>
          </div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   <?= ($old['is_active'] ?? ($g['is_active'] ?? 1)) == 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Aktif (ikut formulir diagnosa)</label>
            <div class="form-text">Nonaktif = disembunyikan dari formulir, kasus lama tetap utuh.</div>
          </div>
        </div>
      </div>
      <hr>
      <div class="d-flex gap-2 justify-content-end">
        <a class="btn btn-outline-secondary" href="<?= e(url('gejala')) ?>"><i class="bi bi-x-lg"></i> Batal</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> <?= $g === null ? 'Simpan Gejala' : 'Perbarui Gejala' ?></button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
