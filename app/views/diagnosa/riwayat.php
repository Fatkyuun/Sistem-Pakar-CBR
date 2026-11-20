<?php
require __DIR__ . '/../layouts/header.php';
/**
 * app/views/diagnosa/riwayat.php — riwayat diagnosa milik pengguna (FR-U4).
 * Variabel: $rows, $filter, $total, $hal, $totalHal, $penyakitList
 */
$badgeStatus = [
    'tidak_perlu'   => ['Terkonfirmasi otomatis', 'success', 'check-circle'],
    'menunggu'      => ['Menunggu tinjauan pakar', 'warning', 'hourglass-split'],
    'terkonfirmasi' => ['Terkonfirmasi pakar', 'success', 'patch-check'],
    'direvisi'      => ['Direvisi pakar', 'primary', 'pencil-square'],
    'ditolak'       => ['Ditolak', 'danger', 'x-circle'],
];
$dasar = array_filter([
    'dari'     => $filter['dari'] ?? '',
    'sampai'   => $filter['sampai'] ?? '',
    'penyakit' => !empty($filter['id_penyakit']) ? (string) $filter['id_penyakit'] : '',
], static fn (string $v): bool => $v !== '');
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h3 mb-1"><i class="bi bi-clock-history text-primary"></i> Riwayat Saya</h1>
    <p class="text-secondary mb-0 small">
      Hanya Anda yang dapat melihat riwayat ini. Riwayat yang sudah divalidasi pakar disimpan untuk audit.
    </p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('diagnosa')) ?>">
    <i class="bi bi-plus-circle"></i> Diagnosa Baru
  </a>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?= e(base_root()) ?>/" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="riwayat">
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="dari">Dari tanggal</label>
        <input type="date" class="form-control form-control-sm" id="dari" name="dari" value="<?= e($filter['dari'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="sampai">Sampai tanggal</label>
        <input type="date" class="form-control form-control-sm" id="sampai" name="sampai" value="<?= e($filter['sampai'] ?? '') ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1" for="penyakit">Penyakit</label>
        <select class="form-select form-select-sm" id="penyakit" name="penyakit">
          <option value="0">— semua penyakit —</option>
          <?php foreach ($penyakitList as $p): ?>
            <option value="<?= e((int) $p['id_penyakit']) ?>"
              <?= (int) ($filter['id_penyakit'] ?? 0) === (int) $p['id_penyakit'] ? 'selected' : '' ?>>
              <?= e($p['nama_penyakit']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel"></i> Terapkan</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('riwayat')) ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="fw-semibold"><i class="bi bi-list-check"></i> <?= e($total) ?> riwayat ditemukan</span>
    <?php if ($totalHal > 1): ?>
      <span class="small text-secondary">Halaman <?= e($hal) ?> dari <?= e($totalHal) ?></span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if ($rows === []): ?>
      <div class="text-center text-secondary py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        <?= $total === 0 && $dasar === []
            ? 'Belum ada riwayat diagnosa. Mulai diagnosa pertama Anda.'
            : 'Tidak ada riwayat yang cocok dengan filter. Coba longgarkan filter tanggal atau penyakit.' ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Tanggal</th>
              <th>Penyakit</th>
              <th class="text-end">Similarity</th>
              <th>Status Validasi</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r):
              $status = (string) $r['status_validasi'];
              [$labelStatus, $warnaStatus, $ikonStatus] = $badgeStatus[$status] ?? ['—', 'secondary', 'question-circle'];
              $revisi = $status === 'direvisi' && $r['nama_final'] !== null;
              $namaTampil = $revisi ? $r['nama_final'] : ($r['nama_hasil'] ?? null);
          ?>
            <tr>
              <td class="text-nowrap small">
                <?= e(date('d/m/Y', strtotime((string) $r['created_at']))) ?>
                <div class="text-secondary"><?= e(date('H:i', strtotime((string) $r['created_at']))) ?> WIB</div>
              </td>
              <td>
                <?php if ($namaTampil !== null): ?>
                  <strong><?= e($namaTampil) ?></strong>
                  <?php if ($revisi): ?>
                    <div class="small text-secondary">
                      <i class="bi bi-arrow-repeat"></i> Hasil sistem: <?= e($r['nama_hasil'] ?? '—') ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($r['catatan'] !== null && $r['catatan'] !== ''): ?>
                    <details class="mt-1">
                      <summary class="small text-primary">Catatan pakar</summary>
                      <div class="small text-secondary mt-1"><?= nl2br(e($r['catatan'])) ?></div>
                    </details>
                  <?php endif; ?>
                  <?php if ($r['solusi_revisi'] !== null && $r['solusi_revisi'] !== ''): ?>
                    <details class="mt-1">
                      <summary class="small text-success">Saran revisi pakar</summary>
                      <div class="small text-secondary mt-1"><?= nl2br(e($r['solusi_revisi'])) ?></div>
                    </details>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-secondary">Belum ada hasil</span>
                <?php endif; ?>
              </td>
              <td class="text-end fw-semibold">
                <?= $r['similarity_tertinggi'] !== null
                    ? e(number_format((float) $r['similarity_tertinggi'] * 100, 1, ',', '.')) . '%'
                    : '—' ?>
              </td>
              <td>
                <span class="badge text-bg-<?= e($warnaStatus) ?>">
                  <i class="bi bi-<?= e($ikonStatus) ?>"></i> <?= e($labelStatus) ?>
                </span>
                <?php if ($r['kode_kasus'] !== null): ?>
                  <div class="small text-secondary mt-1">Kasus: <?= e($r['kode_kasus']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end text-nowrap">
                <a class="btn btn-outline-primary btn-sm" title="Lihat hasil"
                   href="<?= e(url('diagnosa', ['action' => 'hasil', 'id' => (int) $r['id_diagnosa']])) ?>">
                  <i class="bi bi-eye"></i>
                </a>
                <?php if ($r['keputusan'] === null): ?>
                  <form method="post" action="<?= e(url('riwayat', ['action' => 'hapus'])) ?>" class="d-inline"
                        data-konfirmasi-hapus
                        data-nama="Riwayat diagnosa <?= e(date('d/m/Y H:i', strtotime((string) $r['created_at']))) ?>"
                        data-referensi="0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((int) $r['id_diagnosa']) ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit" title="Hapus riwayat">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <button class="btn btn-outline-secondary btn-sm" type="button" disabled
                          title="Riwayat yang sudah divalidasi pakar disimpan untuk audit">
                    <i class="bi bi-lock"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($totalHal > 1): ?>
    <div class="card-footer bg-white">
      <nav aria-label="Navigasi halaman riwayat">
        <ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
          <?php for ($i = 1; $i <= $totalHal; $i++): ?>
            <li class="page-item <?= $i === $hal ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(url('riwayat', array_merge($dasar, ['hal' => $i]))) ?>"><?= e($i) ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>

<p class="small text-secondary mt-3 mb-0">
  <i class="bi bi-shield-lock"></i> Riwayat hanya dapat diakses oleh pemilik akun.
  Menghapus riwayat tidak menghapus kasus yang sudah tervalidasi pakar (disimpan sebagai audit).
</p>

<script src="<?= e(asset_url('js/app.js')) ?>"></script>
<?php require __DIR__ . '/../layouts/footer.php' ?>
