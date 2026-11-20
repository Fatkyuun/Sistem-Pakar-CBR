<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var array $rows */ /** @var int $jumlahAktif */
$labelUrgensi = Penyakit::LABEL_URGENSI;
$warnaUrgensi = ['ringan' => 'success', 'waspada' => 'warning', 'segera' => 'danger', 'darurat' => 'dark'];
?>
<?php if ($jumlahAktif === 0): ?>
  <div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-triangle"></i> Belum ada penyakit aktif. Tambahkan master penyakit terlebih dahulu.</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span class="fw-semibold"><i class="bi bi-hospital"></i> Daftar Penyakit</span>
    <a class="btn btn-primary btn-sm" href="<?= e(url('penyakit', ['action' => 'form'])) ?>">
      <i class="bi bi-plus-lg"></i> Tambah Penyakit
    </a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table id="tabelMaster" class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Kode</th>
            <th>Nama Penyakit</th>
            <th>Tingkat Urgensi</th>
            <th>Kasus</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $aktif = (int) $r['is_active'] === 1; ?>
          <tr>
            <td class="text-nowrap"><strong><?= e($r['kode_penyakit']) ?></strong></td>
            <td><?= e($r['nama_penyakit']) ?></td>
            <td>
              <span class="badge text-bg-<?= $warnaUrgensi[$r['tingkat_urgensi']] ?? 'secondary' ?>">
                <?= e($labelUrgensi[$r['tingkat_urgensi']] ?? $r['tingkat_urgensi']) ?>
              </span>
            </td>
            <td>
              <span class="badge text-bg-light" title="Jumlah kasus yang memakai penyakit ini (semua status)">
                <?= e((int) $r['jumlah_kasus']) ?> kasus
              </span>
            </td>
            <td>
              <span class="badge <?= $aktif ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= $aktif ? 'Aktif' : 'Tidak Aktif' ?>
              </span>
            </td>
            <td class="text-nowrap">
              <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('penyakit', ['action' => 'form', 'id' => (int) $r['id_penyakit']])) ?>"
                 title="Ubah data penyakit"><i class="bi bi-pencil"></i> Edit</a>

              <form method="post" action="<?= e(url('penyakit', ['action' => 'toggle'])) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((int) $r['id_penyakit']) ?>">
                <button class="btn btn-outline-secondary btn-sm"
                        title="<?= $aktif ? 'Nonaktifkan (soft delete): disembunyikan dari sistem, kasus lama tetap utuh' : 'Aktifkan kembali' ?>">
                  <i class="bi <?= $aktif ? 'bi-pause-circle' : 'bi-check-circle' ?>"></i>
                  <?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?>
                </button>
              </form>

              <form method="post" action="<?= e(url('penyakit', ['action' => 'delete'])) ?>" class="d-inline"
                    data-konfirmasi-hapus
                    data-nama="Penyakit <?= e($r['kode_penyakit'] . ' — ' . $r['nama_penyakit']) ?>"
                    data-jumlah-kasus="<?= e((int) $r['jumlah_kasus']) ?>"
                    data-referensi="<?= e((int) $r['jumlah_referensi']) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((int) $r['id_penyakit']) ?>">
                <button class="btn btn-outline-danger btn-sm" title="Hapus / nonaktifkan sesuai aturan soft delete">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small text-muted mb-0">
      <i class="bi bi-info-circle"></i> Aturan integritas (PRD FR-A2): penyakit yang <strong>sudah dipakai kasus</strong>
      tidak dapat dihapus permanen — hanya dinonaktifkan (<code>is_active = 0</code>), dan jumlah kasus terdampak
      ditampilkan pada peringatan sebelum hapus.
    </p>
  </div>
</div>

<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
