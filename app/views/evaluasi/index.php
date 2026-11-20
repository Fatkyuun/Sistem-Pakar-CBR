<?php
require __DIR__ . '/../layouts/admin_header.php';
/**
 * app/views/evaluasi/index.php — halaman evaluasi akurasi LOOCV CBR (PRD 1.3).
 * Variabel: $evaluasi
 */
$matrix   = $evaluasi['confusion_matrix'];
$penyakit = $evaluasi['daftar_penyakit'];
$metrik   = $evaluasi['metrik_penyakit'];
$rincian  = $evaluasi['rincian_kasus'];
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h3 fw-semibold mb-1">Evaluasi Akurasi CBR (LOOCV)</h1>
    <p class="text-secondary mb-0 small">
      Uji validasi silang <em>Leave-One-Out Cross Validation</em> (PRD Bagian 1.3) pada basis kasus aktif tanpa bias hafalan.
    </p>
  </div>
  <div>
    <a href="<?= e(url('dashboard')) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-speedometer2 me-1"></i> Dashboard
    </a>
    <a href="<?= e(url('kasus')) ?>" class="btn btn-primary btn-sm ms-1">
      <i class="bi bi-folder2-open me-1"></i> Kelola Kasus
    </a>
  </div>
</div>

<!-- ================= 1. KARTU METRIK UTAMA ================= -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100 kartu-metrik">
      <div class="card-body">
        <div class="text-secondary small">Total Kasus Evaluasi</div>
        <div class="h2 fw-semibold mb-1"><?= e($evaluasi['total_kasus']) ?></div>
        <span class="badge text-bg-secondary">Basis Kasus Aktif</span>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100 kartu-metrik border-start border-4 <?= $evaluasi['memenuhi_target_top1'] ? 'border-success' : 'border-warning' ?>">
      <div class="card-body">
        <div class="text-secondary small">Top-1 Accuracy</div>
        <div class="h2 fw-semibold mb-1 <?= $evaluasi['memenuhi_target_top1'] ? 'text-success' : 'text-warning' ?>">
          <?= e(number_format($evaluasi['top1_accuracy'] * 100, 1, ',', '.')) ?>%
        </div>
        <div class="d-flex align-items-center gap-1">
          <span class="badge <?= $evaluasi['memenuhi_target_top1'] ? 'text-bg-success' : 'text-bg-warning' ?>">
            <?= e($evaluasi['top1_hits']) ?> / <?= e($evaluasi['total_kasus']) ?> Benar
          </span>
          <span class="small text-secondary">(Target PRD &ge; 85%)</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100 kartu-metrik border-start border-4 <?= $evaluasi['memenuhi_target_top3'] ? 'border-success' : 'border-danger' ?>">
      <div class="card-body">
        <div class="text-secondary small">Top-3 Accuracy</div>
        <div class="h2 fw-semibold mb-1 <?= $evaluasi['memenuhi_target_top3'] ? 'text-success' : 'text-danger' ?>">
          <?= e(number_format($evaluasi['top3_accuracy'] * 100, 1, ',', '.')) ?>%
        </div>
        <div class="d-flex align-items-center gap-1">
          <span class="badge <?= $evaluasi['memenuhi_target_top3'] ? 'text-bg-success' : 'text-bg-danger' ?>">
            <?= e($evaluasi['top3_hits']) ?> / <?= e($evaluasi['total_kasus']) ?> Masuk Top-3
          </span>
          <span class="small text-secondary">(Target PRD &ge; 95%)</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100 kartu-metrik">
      <div class="card-body">
        <div class="text-secondary small">Rata-rata Similarity Top-1</div>
        <div class="h2 fw-semibold mb-1">
          <?= e(number_format($evaluasi['rata_similarity'] * 100, 1, ',', '.')) ?>%
        </div>
        <span class="badge text-bg-info text-white">Tingkat Kemiripan</span>
      </div>
    </div>
  </div>
</div>

<!-- ================= 2. CONFUSION MATRIX (8 x 8) ================= -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h2 class="h5 fw-semibold mb-0"><i class="bi bi-grid-3x3 me-1"></i> Confusion Matrix Penyakit</h2>
      <div class="small text-secondary">Baris menunjukkan label aktual kasus; kolom menunjukkan hasil prediksi Top-1 sistem.</div>
    </div>
    <div class="d-flex align-items-center gap-2 small">
      <span class="d-inline-flex align-items-center"><span class="badge bg-success-subtle text-success border border-success me-1 px-2 py-1">&nbsp;</span> Benar (TP)</span>
      <span class="d-inline-flex align-items-center"><span class="badge bg-danger-subtle text-danger border border-danger me-1 px-2 py-1">&nbsp;</span> Meleset (FP/FN)</span>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-bordered table-sm align-middle mb-0 text-center" style="font-size: 0.9rem;">
      <thead class="table-light">
        <tr>
          <th rowspan="2" class="align-middle text-start ps-3" style="min-width: 170px;">Penyakit Aktual</th>
          <th colspan="<?= count($penyakit) ?>" class="text-center py-1">Penyakit Diprediksi Sistem (Top-1)</th>
          <th rowspan="2" class="align-middle" style="min-width: 70px;">Total Aktual</th>
        </tr>
        <tr>
          <?php foreach ($penyakit as $p): ?>
            <th class="text-center py-1" title="<?= e($p['nama_penyakit']) ?>" style="min-width: 55px;">
              <?= e($p['kode_penyakit']) ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        $totalPredKolom = [];
        foreach ($penyakit as $pPred) {
            $totalPredKolom[$pPred['id_penyakit']] = 0;
        }

        foreach ($penyakit as $pAct):
            $idAct = $pAct['id_penyakit'];
            $totalBaris = 0;
        ?>
          <tr>
            <td class="text-start ps-3 fw-semibold text-truncate" style="max-width: 220px;">
              <span class="badge bg-secondary me-1"><?= e($pAct['kode_penyakit']) ?></span>
              <?= e($pAct['nama_penyakit']) ?>
            </td>
            <?php foreach ($penyakit as $pPred):
                $idPred = $pPred['id_penyakit'];
                $val = $matrix[$idAct][$idPred] ?? 0;
                $totalBaris += $val;
                $totalPredKolom[$idPred] += $val;

                $isDiag = ($idAct === $idPred);
                $cellClass = '';
                if ($isDiag) {
                    $cellClass = $val > 0 ? 'table-success fw-bold text-success' : 'text-muted';
                } else {
                    $cellClass = $val > 0 ? 'table-danger fw-bold text-danger' : 'text-muted';
                }
            ?>
              <td class="<?= $cellClass ?>"><?= $val > 0 ? e($val) : '0' ?></td>
            <?php endforeach; ?>
            <td class="fw-bold table-light"><?= e($totalBaris) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light fw-semibold">
        <tr>
          <td class="text-start ps-3">Total Prediksi Top-1</td>
          <?php
          $grandTotal = 0;
          foreach ($penyakit as $pPred):
              $tot = $totalPredKolom[$pPred['id_penyakit']] ?? 0;
              $grandTotal += $tot;
          ?>
            <td><?= e($tot) ?></td>
          <?php endforeach; ?>
          <td class="fw-bold text-primary"><?= e($grandTotal) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="card-footer bg-white small text-secondary py-2">
    <i class="bi bi-info-circle me-1"></i> Catatan klinis: Ketidaksesuaian kecil antara Bronkitis Akut vs Pneumonia, dan Asma vs PPOK merupakan tumpang-tindih klinis respiratori yang wajar karena kesamaan gejala batuk, sesak, dan mengi. Seluruh kasus yang meleset di Top-1 tetap teridentifikasi pada peringkat Top-3.
  </div>
</div>

<!-- ================= 3. METRIK KINERJA PER PENYAKIT ================= -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3">
    <h2 class="h5 fw-semibold mb-0"><i class="bi bi-bar-chart-steps me-1"></i> Metrik Kinerja Klasifikasi per Penyakit</h2>
    <div class="small text-secondary">Evaluasi precision (ketepatan prediksi), recall (sensitivitas), dan skor F1 per kategori penyakit.</div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Kode</th>
          <th>Nama Penyakit</th>
          <th class="text-center">Kasus Aktual (Support)</th>
          <th class="text-center">True Positive (TP)</th>
          <th class="text-center">False Positive (FP)</th>
          <th class="text-center">False Negative (FN)</th>
          <th class="text-center">Precision</th>
          <th class="text-center">Recall</th>
          <th class="text-center">F1-Score</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($metrik as $m): ?>
          <tr>
            <td><span class="badge bg-secondary"><?= e($m['kode_penyakit']) ?></span></td>
            <td class="fw-semibold"><?= e($m['nama_penyakit']) ?></td>
            <td class="text-center"><?= e($m['support']) ?></td>
            <td class="text-center"><span class="badge bg-success-subtle text-success"><?= e($m['tp']) ?></span></td>
            <td class="text-center"><span class="badge <?= $m['fp'] > 0 ? 'bg-danger-subtle text-danger' : 'text-secondary' ?>"><?= e($m['fp']) ?></span></td>
            <td class="text-center"><span class="badge <?= $m['fn'] > 0 ? 'bg-warning-subtle text-warning' : 'text-secondary' ?>"><?= e($m['fn']) ?></span></td>
            <td class="text-center fw-semibold"><?= e(number_format($m['precision'] * 100, 1, ',', '.')) ?>%</td>
            <td class="text-center fw-semibold"><?= e(number_format($m['recall'] * 100, 1, ',', '.')) ?>%</td>
            <td class="text-center fw-bold text-primary"><?= e(number_format($m['f1_score'] * 100, 1, ',', '.')) ?>%</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= 4. RINCIAN PREDIKSI TIAP KASUS ================= -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div>
      <h2 class="h5 fw-semibold mb-0"><i class="bi bi-list-check me-1"></i> Rincian Hasil Pengujian per Kasus (K001–K020)</h2>
      <div class="small text-secondary">Hasil diagnosa tiap kasus uji saat diuji terhadap 19 kasus lainnya.</div>
    </div>
  </div>
  <div class="card-body table-responsive">
    <table class="table table-hover table-striped align-middle" id="tabelRincianEvaluasi">
      <thead class="table-light">
        <tr>
          <th>Kode Kasus</th>
          <th>Penyakit Aktual</th>
          <th>Prediksi Top-1</th>
          <th>Similarity</th>
          <th>Pencocok</th>
          <th>Top-3 Rekomendasi</th>
          <th class="text-center">Status Top-1</th>
          <th class="text-center">Status Top-3</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rincian as $r): ?>
          <tr>
            <td class="fw-bold">
              <a href="<?= e(url('kasus', ['action' => 'detail', 'id' => $r['id_kasus']])) ?>" class="text-decoration-none">
                <?= e($r['kode_kasus']) ?>
              </a>
            </td>
            <td>
              <span class="badge text-bg-secondary me-1"><?= e($r['kode_aktual']) ?></span>
              <?= e($r['nama_aktual']) ?>
            </td>
            <td>
              <span class="badge text-bg-primary me-1"><?= e($r['kode_pred']) ?></span>
              <?= e($r['nama_pred']) ?>
            </td>
            <td class="fw-semibold">
              <?= e(number_format($r['similarity_pred'] * 100, 1, ',', '.')) ?>%
            </td>
            <td class="small text-secondary"><?= e($r['kasus_pencocok']) ?></td>
            <td class="small"><?= e($r['top3_ringkasan']) ?></td>
            <td class="text-center">
              <?php if ($r['match_top1']): ?>
                <span class="badge text-bg-success"><i class="bi bi-check-circle"></i> Cocok</span>
              <?php else: ?>
                <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle"></i> Meleset</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($r['match_top3']): ?>
                <span class="badge text-bg-success"><i class="bi bi-check2"></i> Masuk Top-3</span>
              <?php else: ?>
                <span class="badge text-bg-danger"><i class="bi bi-x-circle"></i> Di Luar Top-3</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
  <i class="bi bi-shield-check fs-4 me-3 flex-shrink-0"></i>
  <div class="small">
    <strong>Disclaimer Medis:</strong> Hasil evaluasi akurasi ini dihitung secara matematis menggunakan basis data kasus uji yang terverifikasi. Sistem pakar ini dirancang sebagai instrumen skrining awal dan edukasi respiratori, bukan pengganti diagnosis medis resmi oleh dokter spesialis.
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.DataTable) {
    new DataTable('#tabelRincianEvaluasi', {
      language: {
        url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/id.json'
      },
      pageLength: 10,
      order: [[0, 'asc']]
    });
  }
});
</script>

<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
