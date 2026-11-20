<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var array $rows */

// Kelompokkan gejala per kategori agar slider mudah dibaca.
$grup = [];
foreach ($rows as $g) {
    if ((int) $g['is_active'] === 1) {
        $grup[$g['kategori']][] = $g;
    }
}
$jumlahNonaktif = count($rows) - array_sum(array_map('count', $grup));
?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span class="fw-semibold"><i class="bi bi-sliders"></i> Bobot Global Gejala (1–10)</span>
    <span class="small text-secondary">Bobot dipakai mesin CBR pada rumus Sim(T,S) = Σ(w_i × sim_i) / Σ w_i</span>
  </div>
  <div class="card-body">
    <?php if ($jumlahNonaktif > 0): ?>
      <div class="alert alert-secondary small py-2">
        <i class="bi bi-info-circle"></i> <?= e($jumlahNonaktif) ?> gejala nonaktif tidak ditampilkan dan bobotnya tidak berubah.
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('bobot', ['action' => 'save'])) ?>">
      <?= csrf_field() ?>

      <?php foreach ($grup as $kategori => $daftar): ?>
        <h2 class="h6 fw-semibold text-primary text-uppercase mt-3"><?= e($kategori) ?></h2>
        <div class="row g-2">
          <?php foreach ($daftar as $g): $id = (int) $g['id_gejala']; ?>
            <div class="col-md-6 col-xl-4">
              <div class="border rounded-3 p-2 h-100 <?= (int) $g['is_redflag'] === 1 ? 'border-danger-subtle' : '' ?>">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="me-2">
                    <strong class="small"><?= e($g['kode_gejala']) ?></strong> <span class="small"><?= e($g['nama_gejala']) ?></span>
                    <?php if ((int) $g['is_redflag'] === 1): ?>
                      <i class="bi bi-exclamation-triangle-fill text-danger" title="Red-flag (gejala darurat)"></i>
                    <?php endif; ?>
                    <div class="form-text mb-0"><?= e($g['deskripsi']) ?></div>
                    <div class="form-text mb-0">Tipe: <?= e(Gejala::LABEL_TIPE[$g['tipe_input']] ?? $g['tipe_input']) ?></div>
                  </div>
                  <div class="text-center flex-shrink-0">
                    <span class="badge text-bg-primary d-block" id="tampil<?= e($id) ?>"><?= e(rtrim(rtrim(number_format((float) $g['bobot'], 2), '0'), '.')) ?></span>
                    <span class="form-text">bobot</span>
                  </div>
                </div>
                <input type="range" class="form-range mt-1" min="1" max="10" step="1"
                       id="b<?= e($id) ?>" name="bobot[<?= e($id) ?>]"
                       value="<?= e((int) round((float) $g['bobot'])) ?>"
                       oninput="document.getElementById('tampil<?= e($id) ?>').textContent = this.value;"
                       aria-label="Bobot <?= e($g['nama_gejala']) ?>">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <hr class="mt-4">
      <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <p class="small text-secondary mb-0">
          <i class="bi bi-info-circle"></i> Setiap perubahan bobot dicatat ke log aktivitas (nilai lama → nilai baru).
          Fitur simulasi dampak bobot baru dikerjakan pada fase berikutnya.
        </p>
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan Massal</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
