<?php
require __DIR__ . '/../layouts/admin_header.php';
/**
 * app/views/pengaturan/index.php — parameter CBR (FR-A7).
 * Variabel: $nilai, $errors
 */
$errors = $errors ?? [];
$nilai  = $nilai ?? [];
?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-sliders"></i> Parameter Mesin CBR
      </div>
      <div class="card-body">
        <?php if ($errors !== []): ?>
          <div class="alert alert-danger" role="alert">
            <strong><i class="bi bi-exclamation-octagon"></i> Pengaturan belum disimpan:</strong>
            <ul class="mb-0 ps-3 mt-1 small">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('pengaturan', ['action' => 'save'])) ?>">
          <?= csrf_field() ?>

          <?php foreach (['threshold_reuse', 'threshold_revise', 'threshold_duplikat'] as $kunci):
              $def = Pengaturan::DEFINISI[$kunci]; ?>
            <div class="mb-3">
              <label class="form-label fw-semibold" for="<?= e($kunci) ?>"><?= e($def['label']) ?></label>
              <input type="number" class="form-control" id="<?= e($kunci) ?>" name="<?= e($kunci) ?>"
                     min="0.01" max="1" step="0.01" inputmode="decimal" required
                     value="<?= e($nilai[$kunci] ?? $def['default']) ?>">
              <div class="form-text"><?= e($def['bantuan']) ?> Rentang 0–1, contoh <code>0.80</code>.</div>
            </div>
          <?php endforeach; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="top_k">Jumlah Kasus Teratas (top-K)</label>
            <input type="number" class="form-control" id="top_k" name="top_k"
                   min="1" max="10" step="1" inputmode="numeric" required
                   value="<?= e($nilai['top_k'] ?? '3') ?>">
            <div class="form-text">Bilangan bulat 1–10. Dipakai untuk daftar kasus paling mirip dan agregasi penyakit.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="metode_similarity">Metode Similarity</label>
            <select class="form-select" id="metode_similarity" name="metode_similarity" required>
              <option value="nearest_neighbor" <?= ($nilai['metode_similarity'] ?? '') === 'nearest_neighbor' ? 'selected' : '' ?>>
                Nearest Neighbor terbobot — Sim = Σ(w·sim) / Σw
              </option>
              <option value="jaccard" <?= ($nilai['metode_similarity'] ?? '') === 'jaccard' ? 'selected' : '' ?>>
                Weighted Jaccard — Σ(w·min) / Σ(w·max)
              </option>
            </select>
            <div class="form-text">Perubahan langsung dipakai pada diagnosa berikutnya.</div>
          </div>

          <div class="d-flex justify-content-between align-items-center gap-2">
            <p class="small text-secondary mb-0">
              <i class="bi bi-clock-history"></i> Setiap perubahan dicatat ke log aktivitas (nilai lama → baru).
            </p>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan Pengaturan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3"></i> Cara Kerja Ambang Batas</div>
      <div class="card-body small">
        <p class="mb-2">
          Similarity tertinggi menentukan jalur keputusan:
        </p>
        <ul class="mb-3 ps-3">
          <li class="mb-1"><strong>≥ Ambang Reuse</strong> → hasil langsung dipakai (Reuse), tidak masuk antrean.</li>
          <li class="mb-1"><strong>Ambang Revisi s/d Ambang Reuse</strong> → ditampilkan sebagai hasil sementara dan masuk antrean validasi (Revise).</li>
          <li><strong>&lt; Ambang Revisi</strong> → kasus baru, tetap masuk antrean validasi.</li>
        </ul>

        <p class="mb-2"><strong>Ambang Duplikat</strong> dipakai saat Retain:</p>
        <ul class="mb-3 ps-3">
          <li class="mb-1">Similarity ≥ ambang duplikat → kasus <em>tidak</em> digandakan, frekuensi kasus lama ditambah.</li>
          <li>Di bawahnya → kasus baru disimpan sebagai <code>sumber = retain</code>.</li>
        </ul>

        <div class="alert alert-warning small mb-0">
          <i class="bi bi-shield-exclamation"></i>
          Aturan validasi: Ambang Revisi &lt; Ambang Reuse, dan Ambang Duplikat ≥ Ambang Reuse.
          Kombinasi lain ditolak agar alur CBR tetap logis.
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body small text-secondary">
        <p class="mb-2"><i class="bi bi-info-circle"></i>
          Nilai tersimpan di tabel <code>pengaturan</code> — bukan di kode program, sehingga dapat diubah
          tanpa deploy ulang dan seluruh mesin CBR langsung mengikuti.</p>
        <a href="<?= e(url('bobot')) ?>"><i class="bi bi-arrow-right"></i> Atur bobot global gejala</a>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
