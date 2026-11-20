<?php
require __DIR__ . '/../layouts/admin_header.php';
/**
 * app/views/users/index.php — manajemen pengguna & log aktivitas (FR-A7).
 * Variabel: $rows, $stat, $log, $jumlahLog
 */
$warnaPeran = ['admin' => 'danger', 'pakar' => 'primary', 'pasien' => 'secondary'];
$saya = (int) current_user()['id_user'];
?>
<div class="row g-3 mb-3">
  <?php
  $ringkas = [
      ['Pasien', $stat['pasien'], 'secondary', 'people'],
      ['Pakar', $stat['pakar'], 'primary', 'person-badge'],
      ['Admin', $stat['admin'], 'danger', 'shield-lock'],
      ['Nonaktif', $stat['nonaktif'], 'dark', 'person-slash'],
  ];
  foreach ($ringkas as [$label, $jumlah, $warna, $ikon]): ?>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-secondary small"><?= e($label) ?></div>
            <div class="h3 mb-0"><?= e($jumlah) ?></div>
          </div>
          <i class="bi bi-<?= e($ikon) ?> fs-2 text-<?= e($warna) ?>"></i>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="fw-semibold"><i class="bi bi-people"></i> Daftar Pengguna (<?= e(count($rows)) ?>)</span>
    <a class="btn btn-primary btn-sm" href="<?= e(url('users', ['action' => 'form'])) ?>">
      <i class="bi bi-person-plus"></i> Tambah Akun Staf
    </a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table id="tabelMaster" class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Nama</th>
            <th>Email</th>
            <th>Peran</th>
            <th>Status</th>
            <th>Terdaftar</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): $id = (int) $r['id_user']; $aktif = (int) $r['is_active'] === 1; ?>
          <tr>
            <td>
              <?= e($r['nama']) ?>
              <?php if ($id === $saya): ?><span class="badge text-bg-light border">Anda</span><?php endif; ?>
            </td>
            <td class="small"><?= e($r['email']) ?></td>
            <td><span class="badge text-bg-<?= e($warnaPeran[$r['role']] ?? 'secondary') ?>"><?= e($r['role']) ?></span></td>
            <td>
              <span class="badge text-bg-<?= $aktif ? 'success' : 'secondary' ?>">
                <?= $aktif ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
            <td class="small text-nowrap"><?= e(date('d/m/Y', strtotime((string) $r['created_at']))) ?></td>
            <td class="text-end text-nowrap">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-outline-primary btn-sm" title="Reset kata sandi"
                   href="<?= e(url('users', ['action' => 'reset', 'id' => $id])) ?>">
                  <i class="bi bi-key"></i>
                </a>
                <form method="post" action="<?= e(url('users', ['action' => 'toggle'])) ?>" class="mb-0"
                      data-konfirmasi-toggle
                      data-nama="<?= e($r['nama'] . ' (' . $r['email'] . ')') ?>"
                      data-aksi="<?= $aktif ? 'nonaktifkan' : 'aktifkan' ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e($id) ?>">
                  <button class="btn btn-outline-<?= $aktif ? 'danger' : 'success' ?> btn-sm"
                          type="submit" <?= $id === $saya ? 'disabled title="Akun sendiri tidak dapat dinonaktifkan"' : '' ?>>
                    <i class="bi bi-<?= $aktif ? 'person-slash' : 'person-check' ?>"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small text-secondary mb-0">
      <i class="bi bi-shield-lock"></i> Admin tidak dapat menonaktifkan akunnya sendiri.
      Kata sandi disimpan sebagai hash dan tidak pernah ditampilkan.
    </p>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="fw-semibold"><i class="bi bi-clock-history"></i> Log Aktivitas Terbaru</span>
    <span class="small text-secondary">20 terakhir dari <?= e($jumlahLog) ?> baris</span>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Waktu</th>
            <th>Pengguna</th>
            <th>Aksi</th>
            <th>Tabel</th>
            <th>Rekaman</th>
            <th>IP</th>
            <th>Detail</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($log as $l): ?>
          <tr>
            <td class="text-nowrap"><?= e(date('d/m/Y H:i:s', strtotime((string) $l['created_at']))) ?></td>
            <td>
              <?= e($l['nama_user'] ?? '—') ?>
              <?php if ($l['role'] !== null): ?>
                <span class="badge text-bg-light border"><?= e($l['role']) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="badge text-bg-light border font-monospace"><?= e($l['aksi']) ?></span></td>
            <td><?= e($l['tabel'] ?? '—') ?></td>
            <td><?= $l['id_rekaman'] !== null ? e($l['id_rekaman']) : '—' ?></td>
            <td class="text-nowrap"><?= e($l['ip_address'] ?? '—') ?></td>
            <td>
              <?php if ($l['data_lama'] !== null || $l['data_baru'] !== null): ?>
                <details>
                  <summary class="small">Lihat</summary>
                  <?php foreach (['data_lama' => 'Lama', 'data_baru' => 'Baru'] as $kol => $label): ?>
                    <?php if ($l[$kol] !== null): ?>
                      <div class="fw-semibold mt-1" style="font-size:.75rem"><?= e($label) ?></div>
                      <pre class="mb-0 bg-light border rounded p-1" style="font-size:.7rem;max-width:26rem;white-space:pre-wrap"><?php
                        $decoded = json_decode((string) $l[$kol], true);
                        echo e($decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $l[$kol]);
                      ?></pre>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </details>
              <?php else: ?>
                <span class="text-secondary">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
/* Konfirmasi aktif/nonaktif akun (tanpa SweetAlert2 tambahan). */
(function () {
    'use strict';

    function escHtml(nilai) {
        return String(nilai).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-konfirmasi-toggle]').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                if (!window.Swal) { return; }
                ev.preventDefault();
                window.Swal.fire({
                    title: 'Konfirmasi',
                    html: '<div class="text-start fs-6"><strong>' + escHtml(form.dataset.nama || 'akun ini') +
                          '</strong> akan <strong>' + escHtml(form.dataset.aksi || 'diubah') + '</strong>. Lanjutkan?</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, lanjutkan',
                    cancelButtonText: 'Batal'
                }).then(function (hasil) {
                    if (hasil.isConfirmed) { form.submit(); }
                });
            });
        });
    });
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
