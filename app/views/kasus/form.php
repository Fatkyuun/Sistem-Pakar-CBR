<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var ?array $k */ /** @var array $nilai */ /** @var array $old */
/** @var array $errors */ /** @var ?array $duplikat */ /** @var array $gejalaAktif */ /** @var array $penyakitAktif */
$errors    = $errors ?? [];
$old       = $old ?? [];
$duplikat  = $duplikat ?? null;
$nilai     = $nilai ?? [];

// Kelompokkan gejala per kategori (sudah urut kode di dalam kategori).
$grup = [];
foreach ($gejalaAktif as $g) {
    $grup[$g['kategori']][] = $g;
}
?>
<?php if ($errors !== []): ?>
  <div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">
    <i class="bi <?= $k === null ? 'bi-plus-lg' : 'bi-pencil' ?>"></i>
    <?= $k === null ? 'Tambah Kasus Baru' : 'Ubah Kasus ' . e($k['kode_kasus']) ?>
  </div>
  <div class="card-body">
    <form id="formKasus" method="post" action="<?= e(url('kasus', ['action' => 'save'])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e($k === null ? 0 : (int) $k['id_kasus']) ?>">
      <input type="hidden" name="paksa_simpan" id="paksa_simpan" value="0">

      <?php if ($duplikat !== null): ?>
        <div class="alert alert-warning" role="alert">
          <i class="bi bi-exclamation-triangle"></i>
          <strong>Kemungkinan duplikat terdeteksi!</strong> Similarity
          <strong><?= e(number_format($duplikat['similarity'] * 100, 1, ',', '.')) ?>%</strong>
          (ambang <?= e(number_format((float) pengaturan('threshold_duplikat', '0.98') * 100, 1, ',', '.')) ?>%)
          dengan kasus aktif <strong><?= e($duplikat['kode_kasus']) ?></strong> pada penyakit yang sama.
          Anda dapat melanjutkan bila memang kasus ini berbeda makna.
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.Swal) {
                Swal.fire({
                    title: 'Kemungkinan duplikat',
                    html: 'Similarity <strong><?= e(number_format($duplikat['similarity'] * 100, 1, ',', '.')) ?>%</strong> dengan kasus aktif <strong><?= e($duplikat['kode_kasus']) ?></strong> (penyakit sama).<br>Lanjutkan tetap menyimpan?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Tetap simpan',
                    cancelButtonText: 'Batalkan',
                    confirmButtonColor: '#dc3545'
                }).then(function (r) {
                    if (r.isConfirmed) {
                        document.getElementById('paksa_simpan').value = '1';
                        document.getElementById('formKasus').submit();
                    } else {
                        window.history.back();
                    }
                });
            }
        });
        </script>
      <?php endif; ?>

      <div class="row g-3 mb-1">
        <div class="col-md-3">
          <label for="kode" class="form-label">Kode Kasus</label>
          <input type="text" class="form-control" id="kode" value="<?= e($k['kode_kasus'] ?? 'otomatis') ?>" disabled>
        </div>
        <div class="col-md-6">
          <label for="id_penyakit" class="form-label">Penyakit (solusi kasus) <span class="text-danger">*</span></label>
          <select class="form-select" id="id_penyakit" name="id_penyakit" required>
            <option value="">— pilih penyakit aktif —</option>
            <?php
            $dipilih = (int) ($old['id_penyakit'] ?? ($k['id_penyakit'] ?? 0));
            $adaDipilih = false;
            foreach ($penyakitAktif as $p):
                if ((int) $p['id_penyakit'] === $dipilih) { $adaDipilih = true; }
            ?>
              <option value="<?= e((int) $p['id_penyakit']) ?>" <?= (int) $p['id_penyakit'] === $dipilih ? 'selected' : '' ?>>
                <?= e($p['kode_penyakit'] . ' — ' . $p['nama_penyakit']) ?>
              </option>
            <?php endforeach; ?>
            <?php if (!$adaDipilih && $dipilih > 0): ?>
              <option value="<?= e($dipilih) ?>" selected><?= e($k['kode_penyakit'] ?? '') ?> — <?= e($k['nama_penyakit'] ?? 'penyakit nonaktif') ?> (nonaktif)</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
          <select class="form-select" id="status" name="status" required>
            <?php foreach (Kasus::STATUS as $s): ?>
              <option value="<?= e($s) ?>" <?= (string) ($old['status'] ?? ($k['status'] ?? 'aktif')) === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr class="mt-4">
      <h2 class="h6 fw-semibold mb-3"><i class="bi bi-sliders"></i> Nilai Gejala (0–3 sesuai tipe input)</h2>

      <?php foreach ($grup as $kategori => $daftar): ?>
        <div class="mb-3">
          <div class="fw-semibold small text-primary text-uppercase mb-1"><?= e($kategori) ?></div>
          <div class="row g-2">
            <?php foreach ($daftar as $g):
                $id = (int) $g['id_gejala'];
                $val = (int) ($nilai[$id] ?? 0);
            ?>
              <div class="col-md-6 col-xl-4">
                <div class="border rounded-3 p-2 h-100 <?= $g['is_redflag'] ? 'border-danger-subtle' : '' ?>">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <strong class="small"><?= e($g['kode_gejala']) ?></strong> <span class="small"><?= e($g['nama_gejala']) ?></span>
                      <?php if ((int) $g['is_redflag'] === 1): ?>
                        <i class="bi bi-exclamation-triangle-fill text-danger" title="Red-flag (gejala darurat)"></i>
                      <?php endif; ?>
                      <div class="form-text mb-0"><?= e($g['deskripsi']) ?></div>
                    </div>
                  </div>
                  <div class="mt-2">
                    <?php if ($g['tipe_input'] === 'biner'): ?>
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="g<?= e($id) ?>"
                               name="gejala[<?= e($id) ?>]" value="1" <?= $val === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="g<?= e($id) ?>">Ada (1) / tidak (0)</label>
                      </div>
                    <?php elseif ($g['tipe_input'] === 'skala'): ?>
                      <div class="d-flex gap-3 small">
                        <?php foreach ([0, 1, 2, 3] as $n): ?>
                          <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" id="g<?= e($id) ?>_<?= $n ?>"
                                   name="gejala[<?= e($id) ?>]" value="<?= $n ?>" <?= $val === $n ? 'checked' : '' ?>>
                            <label class="form-check-label" for="g<?= e($id) ?>_<?= $n ?>"><?= $n ?></label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <select class="form-select form-select-sm" name="gejala[<?= e($id) ?>]">
                        <option value="0" <?= $val === 0 ? 'selected' : '' ?>>Tidak ada</option>
                        <option value="1" <?= $val === 1 ? 'selected' : '' ?>>&lt; 3 hari</option>
                        <option value="2" <?= $val === 2 ? 'selected' : '' ?>>3–7 hari</option>
                        <option value="3" <?= $val === 3 ? 'selected' : '' ?>>&gt; 2 minggu</option>
                      </select>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <hr>
      <div class="d-flex gap-2 justify-content-end">
        <a class="btn btn-outline-secondary" href="<?= e(url('kasus')) ?>"><i class="bi bi-x-lg"></i> Batal</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> <?= $k === null ? 'Simpan Kasus' : 'Perbarui Kasus' ?></button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
