<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var array $rows */ /** @var int $jumlahAktif */
$labelTipe  = Gejala::LABEL_TIPE;
?>
<?php if ($jumlahAktif === 0): ?>
  <div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-triangle"></i> Belum ada gejala aktif. Tambahkan master gejala terlebih dahulu.</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span class="fw-semibold"><i class="bi bi-thermometer-half"></i> Daftar Gejala</span>
    <a class="btn btn-primary btn-sm" href="<?= e(url('gejala', ['action' => 'form'])) ?>">
      <i class="bi bi-plus-lg"></i> Tambah Gejala
    </a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table id="tabelMaster" class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Kode</th>
            <th>Nama Gejala</th>
            <th>Kategori</th>
            <th>Tipe Input</th>
            <th>Bobot</th>
            <th>Red-Flag</th>
            <th>Kasus</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $aktif = (int) $r['is_active'] === 1; ?>
          <tr>
            <td class="text-nowrap"><strong><?= e($r['kode_gejala']) ?></strong></td>
            <td><?= e($r['nama_gejala']) ?></td>
            <td><?= e($r['kategori']) ?></td>
            <td><span class="small"><?= e($labelTipe[$r['tipe_input']] ?? $r['tipe_input']) ?></span></td>
            <td class="text-center"><span class="badge text-bg-info text-dark"><?= e(rtrim(rtrim(number_format((float) $r['bobot'], 2), '0'), '.')) ?></span></td>
            <td class="text-center">
              <?php if ((int) $r['is_redflag'] === 1): ?>
                <i class="bi bi-exclamation-triangle-fill text-danger" title="Gejala darurat (red-flag)"></i><span class="visually-hidden">Red-flag</span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <span class="badge text-bg-light" title="Jumlah kasus yang memakai gejala ini (semua status)">
                <?= e((int) $r['jumlah_kasus']) ?> kasus
              </span>
            </td>
            <td>
              <span class="badge <?= $aktif ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= $aktif ? 'Aktif' : 'Tidak Aktif' ?>
              </span>
            </td>
            <td class="text-nowrap">
              <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('gejala', ['action' => 'form', 'id' => (int) $r['id_gejala']])) ?>"
                 title="Ubah data gejala"><i class="bi bi-pencil"></i> Edit</a>

              <form method="post" action="<?= e(url('gejala', ['action' => 'toggle'])) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((int) $r['id_gejala']) ?>">
                <button class="btn btn-outline-secondary btn-sm"
                        title="<?= $aktif ? 'Nonaktifkan (soft delete): disembunyikan dari formulir, kasus lama tetap utuh' : 'Aktifkan kembali' ?>">
                  <i class="bi <?= $aktif ? 'bi-pause-circle' : 'bi-check-circle' ?>"></i>
                  <?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?>
                </button>
              </form>

              <form method="post" action="<?= e(url('gejala', ['action' => 'delete'])) ?>" class="d-inline"
                    data-konfirmasi-hapus
                    data-nama="Gejala <?= e($r['kode_gejala'] . ' — ' . $r['nama_gejala']) ?>"
                    data-jumlah-kasus="<?= e((int) $r['jumlah_kasus']) ?>"
                    data-referensi="<?= e((int) $r['jumlah_referensi']) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((int) $r['id_gejala']) ?>">
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
      <i class="bi bi-info-circle"></i> Aturan integritas (PRD FR-A3): gejala yang <strong>sudah dipakai kasus</strong>
      tidak dapat dihapus permanen — hanya dinonaktifkan, sehingga menyembunyikan dari formulir tanpa merusak kasus lama.
      Jumlah kasus terdampak ditampilkan pada peringatan sebelum hapus.
    </p>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
