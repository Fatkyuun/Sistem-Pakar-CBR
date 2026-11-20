<?php
require __DIR__ . '/../layouts/header.php';
/**
 * app/views/diagnosa/hasil.php — FR-U3 Halaman Hasil Diagnosa (ramah pasien).
 *
 * Urutan informasi untuk pasien awam:
 *   1. Peringatan darurat (bila ada red-flag) — nama keluhan saja.
 *   2. Hasil utama + saran tindakan, penyebab, dan pencegahan.
 *   3. Kemungkinan lain yang mirip.
 *   4. Rincian teknis CBR (kasus rujukan + tabel perhitungan) dalam <details>.
 *
 * Variabel: $d, $detailRows, $hasil, $kasusTeratas, $perbandingan,
 *           $kontribusi, $metode, $redflag, $pemilik, $penyakitTop
 */
$similarity = $d['similarity_tertinggi'] !== null ? (float) $d['similarity_tertinggi'] : null;
$persen = $similarity !== null ? $similarity * 100 : null;

if ($similarity === null) {
    $keyakinan = ['label' => 'Belum ada data', 'class' => 'secondary'];
} elseif ($similarity >= 0.80) {
    $keyakinan = ['label' => 'Sangat mirip', 'class' => 'success'];
} elseif ($similarity >= 0.60) {
    $keyakinan = ['label' => 'Cukup mirip', 'class' => 'warning'];
} else {
    $keyakinan = ['label' => 'Kurang mirip', 'class' => 'danger'];
}

// Status CBR diterjemahkan ke bahasa pasien (istilah teknis hanya di bagian rincian).
$statusInfo = [
    'reuse' => [
        'label'  => 'Hasil ini didukung kasus yang mirip',
        'class'  => 'success',
        'icon'   => 'check-circle',
        'pesan'  => 'Sistem menemukan catatan kasus lama dengan keluhan yang sangat mirip dengan Anda, '
            . 'sehingga hasil skrining ini cukup dapat diandalkan.',
    ],
    'revise' => [
        'label'  => 'Hasil sedang ditinjau ulang oleh dokter/pakar',
        'class'  => 'warning',
        'icon'   => 'hourglass-split',
        'pesan'  => 'Keluhan Anda cukup mirip dengan kasus yang ada, tetapi belum cukup kuat untuk dipastikan. '
            . 'Dokter/pakar akan memeriksa ulang agar hasilnya lebih akurat.',
    ],
    'baru' => [
        'label'  => 'Keluhan ini belum banyak kemiripannya',
        'class'  => 'secondary',
        'icon'   => 'stars',
        'pesan'  => 'Belum ada kasus lama yang benar-benar mirip dengan keluhan Anda. '
            . 'Hasil ini akan dipelajari dokter/pakar sebelum dipakai sebagai rujukan.',
    ],
][$d['status_cbr'] ?? 'baru'] ?? [
    'label' => 'Status hasil tidak diketahui',
    'class' => 'secondary',
    'icon'  => 'question-circle',
    'pesan' => 'Silakan konsultasikan keluhan Anda dengan dokter.',
];

$labelMetode = $metode === 'jaccard' ? 'Weighted Jaccard' : 'Nearest Neighbor terbobot';
$gejalaTerpilih = array_values(array_filter($detailRows, static fn (array $r): bool => (int) $r['nilai'] > 0));
$jumlahKontribusi = array_sum(array_column($kontribusi, 'kontribusi'));
$jumlahPenyebut   = array_sum(array_column($kontribusi, 'penyebut'));

/* Hasil validasi pakar (bila sudah ada). Bila diputuskan "revisi", penyakit
   final + solusi revisi dipakai sebagai tampilan utama untuk pasien. */
$validasi      = $validasi ?? null;
$keputusanPak  = $validasi['keputusan'] ?? null;
$adaValidasi   = $keputusanPak !== null;
$direvisi      = $keputusanPak === 'revisi' && ($validasi['nama_final'] ?? null) !== null;

$utama = [
    'nama'       => $direvisi ? (string) $validasi['nama_final'] : $d['nama_penyakit'],
    'deskripsi'  => $direvisi && ($validasi['deskripsi_final'] ?? null) !== null
        ? (string) $validasi['deskripsi_final'] : $d['deskripsi'],
    'penyebab'   => $direvisi && ($validasi['penyebab_final'] ?? null) !== null
        ? (string) $validasi['penyebab_final'] : $d['penyebab'],
    'solusi'     => $direvisi && trim((string) ($validasi['solusi_revisi'] ?? '')) !== ''
        ? (string) $validasi['solusi_revisi']
        : ($direvisi && ($validasi['solusi_final'] ?? null) !== null ? (string) $validasi['solusi_final'] : $d['solusi']),
    'pencegahan' => $direvisi && ($validasi['pencegahan_final'] ?? null) !== null
        ? (string) $validasi['pencegahan_final'] : $d['pencegahan'],
    'urgensi'    => $direvisi && ($validasi['urgensi_final'] ?? null) !== null
        ? (string) $validasi['urgensi_final'] : $d['tingkat_urgensi'],
];

$urgensi = [
    'ringan'  => ['success', 'Ringan', 'Anda dapat memantau keluhan di rumah sambil menjaga istirahat dan asupan cairan.'],
    'waspada' => ['warning', 'Perlu waspada', 'Sebaiknya periksa ke dokter dalam 1–2 hari bila keluhan tidak membaik.'],
    'segera'  => ['danger', 'Segera periksa ke dokter', 'Jangan menunda: periksa ke dokter atau klinik hari ini.'],
    'darurat' => ['danger', 'Darurat — segera ke IGD', 'Segera menuju IGD atau hubungi layanan gawat darurat.'],
][$utama['urgensi'] ?? 'ringan'] ?? ['secondary', ucfirst((string) ($utama['urgensi'] ?? '-')), ''];

// Peta kode gejala => tipe input, agar label nilai pada bagian teknis konsisten.
$tipePerKode = [];
foreach ($detailRows as $row) {
    $tipePerKode[(string) $row['kode_gejala']] = (string) $row['tipe_input'];
}
$labelNilai = static function (string $kode, int $nilai) use ($tipePerKode): string {
    return label_nilai_gejala($tipePerKode[$kode] ?? 'skala', $nilai);
};
?>
<div class="row g-4">

  <!-- ================= KOLOM UTAMA ================= -->
  <div class="col-lg-8">

    <?php if ($redflag !== []): ?>
      <div class="alert alert-danger border-2 shadow-sm" role="alert" id="peringatanDarurat">
        <h2 class="h5 alert-heading mb-2">
          <i class="bi bi-exclamation-octagon-fill"></i> Mohon segera ke IGD
        </h2>
        <p class="mb-1">Anda memilih keluhan yang termasuk <strong>tanda bahaya</strong>:</p>
        <ul class="mb-2 fw-semibold">
          <?php foreach ($redflag as $rf): ?>
            <li><?= e($rf['nama_gejala']) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="mb-0">
          Keluhan seperti ini bisa membahayakan jiwa. Segera menuju IGD atau hubungi layanan
          gawat darurat setempat — <strong>jangan menunggu hasil sistem ini</strong>.
        </p>
      </div>
    <?php endif; ?>

    <?php if ($adaValidasi): ?>
      <?php
      $infoValidasi = [
          'terima' => ['Hasil dikonfirmasi pakar', 'success', 'patch-check',
              'Dokter/pakar memeriksa hasil sistem dan menyatakan hasilnya sudah sesuai.'],
          'revisi' => ['Hasil direvisi pakar', 'primary', 'pencil-square',
              'Dokter/pakar menyesuaikan hasil sistem. Hasil revisi di bawah adalah rujukan yang dianjurkan.'],
          'tolak'  => ['Hasil ditolak pakar', 'danger', 'x-circle',
              'Dokter/pakar menilai hasil sistem belum dapat dipakai. Ikuti catatan pakar dan periksakan diri langsung.'],
      ][$keputusanPak] ?? ['Sudah ditinjau pakar', 'secondary', 'person-check', ''];
      ?>
      <div class="card border-0 shadow-sm mb-4 kotak-validasi border-start border-4 border-<?= e($infoValidasi[1]) ?>"
           id="hasilValidasi">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
              <div class="text-uppercase small text-secondary fw-semibold">Hasil Validasi Pakar</div>
              <h2 class="h5 mb-1 text-<?= e($infoValidasi[1]) ?>">
                <i class="bi bi-<?= e($infoValidasi[2]) ?>"></i> <?= e($infoValidasi[0]) ?>
              </h2>
            </div>
            <div class="small text-secondary text-end">
              <?php if (!empty($validasi['nama_pakar'])): ?>
                oleh <strong><?= e($validasi['nama_pakar']) ?></strong><br>
              <?php endif; ?>
              <?php if (!empty($validasi['created_at'])): ?>
                <?= e(date('d/m/Y H:i', strtotime((string) $validasi['created_at']))) ?> WIB
              <?php endif; ?>
            </div>
          </div>

          <p class="small text-secondary mb-2"><?= e($infoValidasi[3]) ?></p>

          <?php if ($direvisi): ?>
            <div class="alert alert-primary small mb-2" role="status">
              <i class="bi bi-arrow-repeat"></i>
              Hasil sistem semula: <strong><?= e($d['nama_penyakit'] ?? 'belum ada') ?></strong>.
              Penyakit final menurut pakar: <strong><?= e($validasi['nama_final']) ?></strong>.
            </div>
          <?php endif; ?>

          <?php if ($keputusanPak === 'tolak'): ?>
            <div class="alert alert-danger small mb-2" role="alert">
              <i class="bi bi-exclamation-triangle"></i>
              Hasil sistem sebaiknya <strong>tidak dipakai sebagai rujukan</strong>.
              Mohon ikuti catatan pakar di bawah dan periksakan diri ke fasilitas kesehatan.
            </div>
          <?php endif; ?>

          <?php if (trim((string) ($validasi['catatan'] ?? '')) !== ''): ?>
            <div class="saran-blok mb-0">
              <div class="fw-semibold mb-1"><i class="bi bi-chat-left-text text-secondary"></i> Catatan pakar</div>
              <div class="small"><?= nl2br(e((string) $validasi['catatan'])) ?></div>
            </div>
          <?php endif; ?>

          <?php if ($direvisi && trim((string) ($validasi['solusi_revisi'] ?? '')) !== ''): ?>
            <div class="saran-blok saran-cegah mt-2 mb-0">
              <div class="fw-semibold mb-1"><i class="bi bi-clipboard2-check text-success"></i> Saran tambahan dari pakar</div>
              <div class="small"><?= nl2br(e((string) $validasi['solusi_revisi'])) ?></div>
            </div>
          <?php endif; ?>

          <?php if (!empty($validasi['kode_kasus'])): ?>
            <p class="small text-secondary mb-0 mt-2">
              <i class="bi bi-folder2-open"></i> Kasus ini tersimpan sebagai rujukan
              <strong><?= e($validasi['kode_kasus']) ?></strong> untuk diagnosa berikutnya.
            </p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Hasil utama + saran tindakan -->
    <div class="card border-0 shadow-sm mb-4 hasil-utama">
      <div class="card-body">

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
          <div class="text-uppercase small text-secondary fw-semibold">Hasil skrining awal Anda</div>
          <span class="badge text-bg-<?= e($statusInfo['class']) ?> py-2 px-3">
            <i class="bi bi-<?= e($statusInfo['icon']) ?>"></i> <?= e($statusInfo['label']) ?>
          </span>
        </div>

        <?php if ($utama['nama'] !== null): ?>
          <h1 class="h2 mb-1"><?= e($utama['nama']) ?></h1>
          <?php if ($direvisi): ?>
            <span class="badge text-bg-primary mb-2"><i class="bi bi-pencil-square"></i> Hasil revisi pakar</span>
          <?php endif; ?>
          <p class="mb-3">
            <span class="badge text-bg-<?= e($urgensi[0]) ?> fs-6"><?= e($urgensi[1]) ?></span>
            <?php if ($urgensi[2] !== ''): ?>
              <span class="text-secondary small ms-1"><?= e($urgensi[2]) ?></span>
            <?php endif; ?>
          </p>
        <?php else: ?>
          <h1 class="h2 mb-2">Belum ada gambaran yang jelas</h1>
          <p class="text-secondary mb-3">
            Belum ada kasus lama yang cukup mirip dengan keluhan Anda. Sebaiknya periksakan diri
            ke dokter agar keluhan dapat diperiksa langsung.
          </p>
        <?php endif; ?>

        <?php if ($persen !== null): ?>
          <div class="d-flex align-items-end gap-2 mb-1">
            <span class="display-5 fw-bold text-primary lh-1"><?= e(number_format($persen, 1, ',', '.')) ?>%</span>
            <span class="badge text-bg-<?= e($keyakinan['class']) ?> mb-2"><?= e($keyakinan['label']) ?></span>
          </div>
          <div class="progress mb-2" style="height: 12px" role="progressbar"
               aria-valuenow="<?= e(number_format($persen, 1, '.', '')) ?>" aria-valuemin="0" aria-valuemax="100"
               aria-label="Kemiripan dengan kasus paling mirip">
            <div class="progress-bar bg-<?= e($keyakinan['class']) ?>" style="width: <?= e(number_format($persen, 1, '.', '')) ?>%"></div>
          </div>
          <p class="small text-secondary mb-3">
            Tingkat kemiripan keluhan Anda dengan kasus yang pernah ditangani sebelumnya.
          </p>
        <?php endif; ?>

        <div class="alert alert-<?= e($statusInfo['class']) ?> small mb-4" role="status">
          <i class="bi bi-<?= e($statusInfo['icon']) ?>"></i> <?= e($statusInfo['pesan']) ?>
        </div>

        <?php if ($utama['nama'] !== null): ?>

          <?php if (trim((string) $utama['solusi']) !== ''): ?>
            <div class="saran-blok mb-3">
              <h2 class="h6 mb-1"><i class="bi bi-clipboard2-check text-primary"></i> Apa yang sebaiknya Anda lakukan</h2>
              <div class="small mb-0"><?= nl2br(e((string) $utama['solusi'])) ?></div>
            </div>
          <?php endif; ?>

          <?php if (trim((string) $utama['penyebab']) !== ''): ?>
            <div class="saran-blok saran-penyebab mb-3">
              <h2 class="h6 mb-1"><i class="bi bi-question-circle text-warning-emphasis"></i> Kemungkinan penyebab</h2>
              <div class="small mb-0"><?= nl2br(e((string) $utama['penyebab'])) ?></div>
            </div>
          <?php endif; ?>

          <?php if (trim((string) $utama['pencegahan']) !== ''): ?>
            <div class="saran-blok saran-cegah mb-3">
              <h2 class="h6 mb-1"><i class="bi bi-shield-check text-success"></i> Cara mencegah agar tidak berulang</h2>
              <div class="small mb-0"><?= nl2br(e((string) $utama['pencegahan'])) ?></div>
            </div>
          <?php endif; ?>

          <?php if (trim((string) $utama['deskripsi']) !== ''): ?>
            <p class="small text-secondary mb-0 mt-3">
              <i class="bi bi-info-circle"></i> <strong>Tentang kondisi ini:</strong>
              <?= nl2br(e((string) $utama['deskripsi'])) ?>
            </p>
          <?php endif; ?>

        <?php endif; ?>

        <p class="small text-secondary mb-0 mt-3 pt-3 border-top">
          <i class="bi bi-shield-exclamation"></i> Hasil ini hanya <strong>skrining awal</strong>,
          bukan diagnosis pasti. Tetap periksakan diri ke dokter untuk memastikan kondisi Anda.
        </p>
      </div>
    </div>

    <!-- Kemungkinan lain -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-bar-chart-line"></i> Kemungkinan lain yang perlu dipertimbangkan
      </div>
      <div class="card-body">
        <?php if ($penyakitTop === []): ?>
          <p class="text-secondary small mb-0">Belum ada kemungkinan lain karena data kasus masih kosong.</p>
        <?php else: ?>
          <?php foreach ($penyakitTop as $i => $p): $pct = (float) $p['similarity'] * 100; ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center small mb-1">
                <span>
                  <span class="badge text-bg-light border me-1"><?= e($i + 1) ?></span>
                  <strong><?= e($p['nama_penyakit']) ?></strong>
                </span>
                <span class="fw-semibold"><?= e(number_format($pct, 1, ',', '.')) ?>%</span>
              </div>
              <div class="progress" style="height: 9px" role="progressbar"
                   aria-valuenow="<?= e(number_format($pct, 1, '.', '')) ?>" aria-valuemin="0" aria-valuemax="100"
                   aria-label="Kemiripan <?= e($p['nama_penyakit']) ?>">
                <div class="progress-bar <?= $i === 0 ? 'bg-primary' : 'bg-secondary' ?>"
                     style="width: <?= e(number_format($pct, 1, '.', '')) ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
          <p class="small text-secondary mb-0">
            Persentase menunjukkan seberapa mirip keluhan Anda dengan kasus yang pernah tercatat.
            Semakin tinggi, semakin mendekati kasus tersebut.
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Rincian teknis (untuk dosen/pakar; pasien tidak perlu membuka) -->
    <?php if ($kasusTeratas !== null || $kontribusi !== []): ?>
      <details class="card border-0 shadow-sm mb-4 detail-teknis">
        <summary class="card-header bg-white fw-semibold">
          <i class="bi bi-search text-primary"></i> Lihat Rincian Analisis &amp; Perhitungan Sistem (CBR)
        </summary>
        <div class="card-body">
          <p class="small text-secondary">
            Bagian ini menampilkan proses <em>Case-Based Reasoning</em> secara transparan untuk
            keperluan pemeriksaan oleh dosen/pakar. Pasien tidak perlu membacanya.
          </p>

          <?php if ($kasusTeratas !== null): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
              <h2 class="h6 mb-0"><i class="bi bi-files"></i> Kasus rujukan paling mirip</h2>
              <span class="badge text-bg-light border">
                <?= e($kasusTeratas['kode_kasus']) ?> ·
                <?= e(number_format((float) $kasusTeratas['similarity'] * 100, 1, ',', '.')) ?>% kemiripan
              </span>
            </div>

            <div class="row g-3 small">
              <div class="col-md-6">
                <div class="fw-semibold mb-1 text-success">
                  <i class="bi bi-check2-circle"></i> Keluhan sama (<?= e(count($perbandingan['cocok'])) ?>)
                </div>
                <?php if ($perbandingan['cocok'] === []): ?><p class="text-secondary mb-0">Tidak ada.</p><?php endif; ?>
                <ul class="mb-0 ps-3">
                  <?php foreach ($perbandingan['cocok'] as $b): ?>
                    <li><?= e($b['kode_gejala']) ?> <?= e($b['nama_gejala']) ?> — <?= e($labelNilai((string) $b['kode_gejala'], (int) $b['nilai_input'])) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold mb-1 text-warning-emphasis">
                  <i class="bi bi-shuffle"></i> Berbeda tingkat (<?= e(count($perbandingan['beda'])) ?>)
                </div>
                <?php if ($perbandingan['beda'] === []): ?><p class="text-secondary mb-0">Tidak ada.</p><?php endif; ?>
                <ul class="mb-0 ps-3">
                  <?php foreach ($perbandingan['beda'] as $b): ?>
                    <li><?= e($b['kode_gejala']) ?> <?= e($b['nama_gejala']) ?> — Anda:
                      <?= e($labelNilai((string) $b['kode_gejala'], (int) $b['nilai_input'])) ?>,
                      kasus: <?= e($labelNilai((string) $b['kode_gejala'], (int) $b['nilai_kasus'])) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold mb-1 text-secondary">
                  <i class="bi bi-plus-circle"></i> Hanya pada Anda (<?= e(count($perbandingan['hanya_input'])) ?>)
                </div>
                <?php if ($perbandingan['hanya_input'] === []): ?><p class="text-secondary mb-0">Tidak ada.</p><?php endif; ?>
                <ul class="mb-0 ps-3">
                  <?php foreach ($perbandingan['hanya_input'] as $b): ?>
                    <li><?= e($b['kode_gejala']) ?> <?= e($b['nama_gejala']) ?> — <?= e($labelNilai((string) $b['kode_gejala'], (int) $b['nilai_input'])) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold mb-1 text-secondary">
                  <i class="bi bi-dash-circle"></i> Hanya pada kasus (<?= e(count($perbandingan['hanya_kasus'])) ?>)
                </div>
                <?php if ($perbandingan['hanya_kasus'] === []): ?><p class="text-secondary mb-0">Tidak ada.</p><?php endif; ?>
                <ul class="mb-0 ps-3">
                  <?php foreach ($perbandingan['hanya_kasus'] as $b): ?>
                    <li><?= e($b['kode_gejala']) ?> <?= e($b['nama_gejala']) ?> — <?= e($labelNilai((string) $b['kode_gejala'], (int) $b['nilai_kasus'])) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($kontribusi !== []): ?>
            <h2 class="h6 mt-4 mb-2">
              <i class="bi bi-calculator"></i> Tabel detail perhitungan similarity
              <span class="text-secondary fw-normal">(<?= e($labelMetode) ?>)</span>
            </h2>
            <div class="table-responsive">
              <table class="table table-sm table-striped align-middle mb-2 small">
                <thead class="table-light">
                  <tr>
                    <th>Gejala</th>
                    <th class="text-end">Bobot (w<sub>i</sub>)</th>
                    <th class="text-end">Nilai Anda</th>
                    <th class="text-end">Nilai Kasus</th>
                    <?php if ($metode === 'jaccard'): ?>
                      <th class="text-end">w·min</th>
                      <th class="text-end">w·max</th>
                    <?php else: ?>
                      <th class="text-end">Sim (sim<sub>i</sub>)</th>
                      <th class="text-end">w<sub>i</sub> × sim<sub>i</sub></th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($kontribusi as $row): ?>
                    <tr>
                      <td><strong><?= e($row['kode_gejala']) ?></strong>
                        <span class="text-secondary"><?= e($row['nama_gejala']) ?></span></td>
                      <td class="text-end"><?= e(rtrim(rtrim(number_format($row['bobot'], 2, ',', '.'), '0'), ',')) ?></td>
                      <td class="text-end"><?= e($row['nilai_t']) ?></td>
                      <td class="text-end"><?= e($row['nilai_s']) ?></td>
                      <?php if ($metode === 'jaccard'): ?>
                        <td class="text-end"><?= e(rtrim(rtrim(number_format($row['kontribusi'], 3, ',', '.'), '0'), ',')) ?></td>
                        <td class="text-end"><?= e(rtrim(rtrim(number_format($row['penyebut'], 3, ',', '.'), '0'), ',')) ?></td>
                      <?php else: ?>
                        <td class="text-end"><?= e(number_format($row['sim'], 3, ',', '.')) ?></td>
                        <td class="text-end"><?= e(rtrim(rtrim(number_format($row['kontribusi'], 3, ',', '.'), '0'), ',')) ?></td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-semibold">
                  <tr>
                    <td colspan="4" class="text-end">Jumlah</td>
                    <?php if ($metode === 'jaccard'): ?>
                      <td class="text-end"><?= e(rtrim(rtrim(number_format($jumlahKontribusi, 3, ',', '.'), '0'), ',')) ?></td>
                      <td class="text-end"><?= e(rtrim(rtrim(number_format($jumlahPenyebut, 3, ',', '.'), '0'), ',')) ?></td>
                    <?php else: ?>
                      <td></td>
                      <td class="text-end"><?= e(rtrim(rtrim(number_format($jumlahKontribusi, 3, ',', '.'), '0'), ',')) ?></td>
                    <?php endif; ?>
                  </tr>
                </tfoot>
              </table>
            </div>
            <?php if ($metode === 'jaccard'): ?>
              <p class="small text-secondary mb-0">
                Weighted Jaccard = Σ(w<sub>i</sub>·min(T,S)) / Σ(w<sub>i</sub>·max(T,S)) =
                <?= e(rtrim(rtrim(number_format($jumlahKontribusi, 3, ',', '.'), '0'), ',')) ?> /
                <?= e(rtrim(rtrim(number_format($jumlahPenyebut, 3, ',', '.'), '0'), ',')) ?> =
                <strong><?= e(number_format($jumlahPenyebut > 0 ? $jumlahKontribusi / $jumlahPenyebut : 0, 4, ',', '.')) ?></strong>.
                (Metode perhitungan mengikuti pengaturan sistem saat ini.)
              </p>
            <?php else: ?>
              <p class="small text-secondary mb-0">
                Sim(T,S) = Σ(w<sub>i</sub>×sim<sub>i</sub>) / Σ w<sub>i</sub> =
                <?= e(rtrim(rtrim(number_format($jumlahKontribusi, 3, ',', '.'), '0'), ',')) ?> /
                <?= e(rtrim(rtrim(number_format($jumlahPenyebut, 3, ',', '.'), '0'), ',')) ?> =
                <strong><?= e(number_format($jumlahPenyebut > 0 ? $jumlahKontribusi / $jumlahPenyebut : 0, 4, ',', '.')) ?></strong>.
                Hanya gejala bernilai &gt; 0 pada salah satu sisi yang dihitung.
              </p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>

  </div>

  <!-- ================= SIDEBAR ================= -->
  <div class="col-lg-4">

    <div class="card border-0 shadow-sm mb-4 ringkasan-gejala">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-clipboard-data"></i> Ringkasan Gejala Anda
      </div>
      <div class="card-body small">
        <dl class="row mb-3">
          <dt class="col-5 text-secondary fw-normal">Tanggal</dt>
          <dd class="col-7 mb-1"><?= e(date('d/m/Y H:i', strtotime((string) $d['created_at']))) ?></dd>
          <dt class="col-5 text-secondary fw-normal">Usia</dt>
          <dd class="col-7 mb-1"><?= $d['usia'] !== null ? e($d['usia']) . ' tahun' : '—' ?></dd>
          <dt class="col-5 text-secondary fw-normal">Jenis kelamin</dt>
          <dd class="col-7 mb-1"><?= $d['jenis_kelamin'] === 'L' ? 'Laki-laki' : ($d['jenis_kelamin'] === 'P' ? 'Perempuan' : '—') ?></dd>
          <dt class="col-5 text-secondary fw-normal">Merokok</dt>
          <dd class="col-7 mb-0"><?= e(label_merokok($d['status_merokok'] ?? null)) ?></dd>
        </dl>

        <div class="fw-semibold mb-2">
          <?= e(count($gejalaTerpilih)) ?> keluhan yang Anda pilih
        </div>
        <?php if ($gejalaTerpilih === []): ?>
          <p class="text-secondary mb-0">Tidak ada keluhan yang dipilih.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($gejalaTerpilih as $g):
                $nilai = (int) $g['nilai'];
                $tipe  = (string) $g['tipe_input'];
            ?>
              <li class="d-flex justify-content-between align-items-start gap-2 py-1 border-bottom">
                <span>
                  <?php if ((int) $g['is_redflag'] === 1): ?>
                    <i class="bi bi-exclamation-triangle-fill text-danger" title="Keluhan darurat"></i>
                  <?php endif; ?>
                  <?= e($g['nama_gejala']) ?>
                </span>
                <span class="badge <?= e(kelas_nilai_gejala($tipe, $nilai)) ?> text-nowrap">
                  <?= e(label_nilai_gejala($tipe, $nilai)) ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($pemilik): ?>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-star"></i> Umpan Balik</div>
        <div class="card-body">
          <?php if ($d['rating_pengguna'] !== null): ?>
            <p class="small text-success mb-2">
              <i class="bi bi-check2-circle"></i> Anda sudah menilai hasil ini
              <strong><?= e($d['rating_pengguna']) ?>/5</strong>. Silakan ubah bila berubah pikiran.
            </p>
          <?php else: ?>
            <p class="small text-secondary">Apakah hasil ini membantu Anda?</p>
          <?php endif; ?>
          <form method="post" action="<?= e(url('diagnosa', ['action' => 'rating'])) ?>" class="mb-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((int) $d['id_diagnosa']) ?>">
            <div class="d-flex gap-1 mb-2 flex-wrap" role="group" aria-label="Nilai 1 sampai 5 bintang">
              <?php for ($b = 1; $b <= 5; $b++): ?>
                <input type="radio" class="btn-check" name="rating" id="rating<?= $b ?>" value="<?= $b ?>"
                       autocomplete="off" <?= (int) ($d['rating_pengguna'] ?? 0) === $b ? 'checked' : '' ?> required>
                <label class="btn btn-outline-warning btn-tap px-2" for="rating<?= $b ?>" title="<?= $b ?> bintang">
                  <?= str_repeat('★', $b) ?><span class="visually-hidden"> <?= $b ?> bintang</span>
                </label>
              <?php endfor; ?>
            </div>
            <button class="btn btn-warning btn-sm w-100" type="submit"><i class="bi bi-send"></i> Kirim Penilaian</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-body small">
        <p class="mb-2"><i class="bi bi-shield-check text-success"></i>
          Data Anda hanya dapat diakses oleh Anda<?= $pemilik ? '' : ' (mode peninjauan)' ?> dan pengelola sistem.</p>
        <a class="btn btn-outline-primary btn-sm w-100 mb-2" href="<?= e(url('diagnosa')) ?>">
          <i class="bi bi-plus-circle"></i> Diagnosa Baru
        </a>
        <a class="btn btn-outline-secondary btn-sm w-100" href="<?= e(url('index')) ?>">
          <i class="bi bi-house"></i> Kembali ke Beranda
        </a>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php' ?>
