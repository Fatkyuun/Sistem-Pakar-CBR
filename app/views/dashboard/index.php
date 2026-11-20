<?php
require __DIR__ . '/../layouts/admin_header.php';
/**
 * app/views/dashboard/index.php — dashboard pakar/admin (FR-A1).
 * Variabel: $statDiagnosa, $statValidasi, $kasusAktif, $distribusi, $antrean
 */
$akurasi = $statValidasi['akurasi'];
$grafik  = [
    'labels' => array_map(static fn (array $r): string => (string) $r['nama_penyakit'], $distribusi),
    'values' => array_map(static fn (array $r): int => (int) $r['jumlah'], $distribusi),
];
?>
<!-- 6 KARTU METRIK: GRID 3 KOLOM x 2 BARIS (DESKTOP col-md-4, TABLET col-sm-6) -->
<div class="row g-3 mb-4">
  <!-- Kartu 1: Diagnosa Hari Ini -->
  <div class="col-sm-6 col-md-4">
    <div class="card spc-admin-card h-100">
      <div class="card-body p-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-secondary small fw-medium">Diagnosa hari ini</span>
          <span class="spc-metric-icon bg-blue-subtle text-primary">
            <i class="bi bi-calendar-event"></i>
          </span>
        </div>
        <div>
          <div class="h2 fw-bold text-slate-800 mb-0"><?= e($statDiagnosa['hari_ini']) ?></div>
          <span class="text-muted" style="font-size: 0.78rem;">Sesi skrining pengguna hari ini</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Kartu 2: Bulan Ini -->
  <div class="col-sm-6 col-md-4">
    <div class="card spc-admin-card h-100">
      <div class="card-body p-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-secondary small fw-medium">Bulan ini</span>
          <span class="spc-metric-icon bg-indigo-subtle text-indigo">
            <i class="bi bi-calendar-month"></i>
          </span>
        </div>
        <div>
          <div class="h2 fw-bold text-slate-800 mb-0"><?= e($statDiagnosa['bulan_ini']) ?></div>
          <span class="text-muted" style="font-size: 0.78rem;">Total skrining pada bulan berjalan</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Kartu 3: Menunggu Validasi -->
  <div class="col-sm-6 col-md-4">
    <div class="card spc-admin-card h-100">
      <div class="card-body p-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-secondary small fw-medium">Menunggu validasi</span>
          <span class="spc-metric-icon bg-warning-subtle text-warning">
            <i class="bi bi-hourglass-split"></i>
          </span>
        </div>
        <div>
          <div class="h2 fw-bold text-warning mb-1"><?= e($statValidasi['menunggu']) ?></div>
          <a class="small text-decoration-none fw-medium text-warning" href="<?= e(url('validasi')) ?>">
            Buka antrean kasus <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Kartu 4: Kasus Aktif -->
  <div class="col-sm-6 col-md-4">
    <div class="card spc-admin-card h-100">
      <div class="card-body p-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-secondary small fw-medium">Kasus aktif</span>
          <span class="spc-metric-icon bg-info-subtle text-info">
            <i class="bi bi-folder2-open"></i>
          </span>
        </div>
        <div>
          <div class="h2 fw-bold text-slate-800 mb-1"><?= e($kasusAktif) ?></div>
          <a class="small text-decoration-none fw-medium text-primary" href="<?= e(url('kasus')) ?>">
            Lihat basis kasus <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Kartu 5: Rata-rata Similarity -->
  <div class="col-sm-6 col-md-4">
    <div class="card spc-admin-card h-100">
      <div class="card-body p-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-secondary small fw-medium">Rata-rata similarity</span>
          <span class="spc-metric-icon bg-teal-subtle text-teal">
            <i class="bi bi-pie-chart"></i>
          </span>
        </div>
        <div>
          <div class="h2 fw-bold text-slate-800 mb-0">
            <?= $statDiagnosa['rata_similarity'] !== null
                ? e(number_format((float) $statDiagnosa['rata_similarity'] * 100, 1, ',', '.')) . '%'
                : '—' ?>
          </div>
          <span class="text-muted" style="font-size: 0.78rem;">Tingkat kecocokan kasus terdekat</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Kartu 6: Tingkat Akurasi -->
  <div class="col-sm-6 col-md-4">
    <div class="card spc-admin-card h-100">
      <div class="card-body p-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-secondary small fw-medium">Tingkat akurasi</span>
          <span class="spc-metric-icon bg-success-subtle text-success">
            <i class="bi bi-check2-circle"></i>
          </span>
        </div>
        <div>
          <div class="h2 fw-bold text-success mb-0">
            <?= $akurasi !== null ? e(number_format((float) $akurasi * 100, 1, ',', '.')) . '%' : '—' ?>
          </div>
          <div class="small text-secondary" style="font-size: 0.78rem;">terima / (terima + revisi)</div>
          <a class="small text-decoration-none fw-medium text-success d-inline-block mt-1" href="<?= e(url('evaluasi')) ?>">
            Evaluasi CBR <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- GRAFIK DAN ANTREAN TERBARU -->
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card spc-admin-card h-100">
      <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-slate-800"><i class="bi bi-bar-chart-line text-primary me-1"></i> Distribusi Penyakit Hasil Diagnosa</span>
        <span class="badge spc-badge-pastel-blue"><?= e($statDiagnosa['total']) ?> diagnosa total</span>
      </div>
      <div class="card-body">
        <?php if ($distribusi === []): ?>
          <p class="text-secondary mb-0 text-center py-5">
            Belum ada diagnosa dengan penyakit hasil. Grafik akan muncul setelah ada data.
          </p>
        <?php else: ?>
          <div style="position:relative;height:320px">
            <canvas id="grafikPenyakit" aria-label="Grafik distribusi penyakit hasil diagnosa" role="img"></canvas>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="card spc-admin-card h-100">
      <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-slate-800"><i class="bi bi-inbox text-primary me-1"></i> 5 Antrean Terbaru</span>
        <a class="small text-decoration-none fw-medium text-primary" href="<?= e(url('validasi')) ?>">Semua antrean <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="card-body p-0">
        <?php if ($antrean === []): ?>
          <p class="text-secondary text-center py-5 mb-0">Antrean validasi kosong.</p>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($antrean as $a): ?>
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 px-3 py-2 border-bottom"
                 href="<?= e(url('validasi', ['action' => 'detail', 'id' => (int) $a['id_diagnosa']])) ?>">
                <div>
                  <span class="fw-semibold text-slate-800">#<?= e((int) $a['id_diagnosa']) ?></span>
                  <span class="d-block small text-secondary">
                    <?= e(date('d/m/Y H:i', strtotime((string) $a['created_at']))) ?>
                    · <?= e($a['nama_penyakit'] ?? 'belum ada kandidat') ?>
                  </span>
                </div>
                <div class="text-end flex-shrink-0">
                  <span class="badge spc-badge-pastel-blue">
                    <?= $a['similarity_tertinggi'] !== null
                        ? e(number_format((float) $a['similarity_tertinggi'] * 100, 1, ',', '.')) . '%'
                        : '—' ?>
                  </span>
                  <span class="d-block small text-muted mt-1 text-uppercase" style="font-size: 0.72rem;"><?= e($a['status_cbr'] ?? '—') ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($distribusi !== []): ?>
  <script type="application/json" id="dataDistribusi">
    <?= json_encode($grafik, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
  </script>
<?php endif; ?>
<?php
$extraJs = [];
if ($distribusi !== []) {
    $extraJs[] = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    $extraJs[] = asset_url('js/dashboard.js');
}
require __DIR__ . '/../layouts/admin_footer.php';
?>
