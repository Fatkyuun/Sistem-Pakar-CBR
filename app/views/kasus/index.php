<?php
require __DIR__ . '/../layouts/admin_header.php';
/** @var array $rows */ /** @var array $filter */ /** @var array $daftarPenyakit */
$warnaStatus  = ['aktif' => 'success', 'nonaktif' => 'secondary', 'arsip' => 'dark'];
$warnaSumber  = ['pakar' => 'info', 'retain' => 'warning', 'import' => 'light'];
?>
<!-- Filter (FR-A5) -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?= e(base_root()) ?>/" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="kasus">
      <div class="col-md-3">
        <label class="form-label small mb-1">Penyakit</label>
        <select name="penyakit" class="form-select form-select-sm">
          <option value="0">— semua penyakit —</option>
          <?php foreach ($daftarPenyakit as $p): ?>
            <option value="<?= e((int) $p['id_penyakit']) ?>" <?= (int) $filter['id_penyakit'] === (int) $p['id_penyakit'] ? 'selected' : '' ?>>
              <?= e($p['kode_penyakit'] . ' — ' . $p['nama_penyakit']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">— semua —</option>
          <?php foreach (Kasus::STATUS as $s): ?>
            <option value="<?= e($s) ?>" <?= $filter['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Sumber</label>
        <select name="sumber" class="form-select form-select-sm">
          <option value="">— semua —</option>
          <?php foreach (Kasus::SUMBER as $s): ?>
            <option value="<?= e($s) ?>" <?= $filter['sumber'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Cari</label>
        <input type="text" name="cari" class="form-control form-control-sm" value="<?= e($filter['cari']) ?>" placeholder="kode kasus / nama penyakit">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel"></i> Terapkan</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('kasus')) ?>" title="Bersihkan filter"><i class="bi bi-x-circle"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span class="fw-semibold"><i class="bi bi-folder2-open"></i> Daftar Kasus (<?= e(count($rows)) ?>)</span>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('kasus', ['action' => 'import'])) ?>">
        <i class="bi bi-upload"></i> Impor CSV
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('kasus', ['action' => 'template'])) ?>">
        <i class="bi bi-file-earmark-arrow-down"></i> Template
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('kasus', ['action' => 'export'])) ?>">
        <i class="bi bi-download"></i> Ekspor
      </a>
      <a class="btn btn-primary btn-sm" href="<?= e(url('kasus', ['action' => 'form'])) ?>">
        <i class="bi bi-plus-lg"></i> Tambah Kasus
      </a>
    </div>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table id="tabelMaster" class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Kode</th>
            <th>Penyakit</th>
            <th>Gejala &gt; 0</th>
            <th>Sumber</th>
            <th>Status</th>
            <th>Frek.</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-nowrap"><strong><?= e($r['kode_kasus']) ?></strong></td>
            <td><span class="small"><?= e($r['kode_penyakit']) ?></span> <?= e($r['nama_penyakit']) ?></td>
            <td><span class="badge text-bg-light"><?= e((int) $r['jumlah_gejala']) ?> gejala</span></td>
            <td><span class="badge text-bg-<?= $warnaSumber[$r['sumber']] ?? 'secondary' ?>"><?= e($r['sumber']) ?></span></td>
            <td><span class="badge text-bg-<?= $warnaStatus[$r['status']] ?? 'secondary' ?>"><?= e($r['status']) ?></span></td>
            <td class="text-center"><?= e((int) $r['frekuensi_digunakan']) ?></td>
            <td class="text-nowrap">
              <div class="d-inline-flex align-items-center gap-1 flex-nowrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('kasus', ['action' => 'detail', 'id' => (int) $r['id_kasus']])) ?>" title="Lihat detail"><i class="bi bi-eye"></i></a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('kasus', ['action' => 'form', 'id' => (int) $r['id_kasus']])) ?>" title="Ubah"><i class="bi bi-pencil"></i></a>

                <form method="post" action="<?= e(url('kasus', ['action' => 'status'])) ?>" class="d-inline-flex gap-1 align-items-center mb-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e((int) $r['id_kasus']) ?>">
                  <select name="status" class="form-select form-select-sm" style="width:6rem" aria-label="Status kasus <?= e($r['kode_kasus']) ?>">
                    <?php foreach (Kasus::STATUS as $s): ?>
                      <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-outline-primary btn-sm" type="submit" title="Simpan status"><i class="bi bi-check2"></i></button>
                </form>

                <form method="post" action="<?= e(url('kasus', ['action' => 'delete'])) ?>" class="d-inline-flex mb-0"
                      data-konfirmasi-hapus
                      data-nama="Kasus <?= e($r['kode_kasus'] . ' — ' . $r['nama_penyakit']) ?>"
                      data-jumlah-kasus="0"
                      data-referensi="<?= e((int) $r['jumlah_referensi']) ?>"
                      data-softdelete-teks="Kasus tidak dihapus permanen — statusnya diubah menjadi arsip.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e((int) $r['id_kasus']) ?>">
                  <button class="btn btn-outline-danger btn-sm" title="Hapus / arsipkan sesuai aturan integritas"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small text-muted mb-0">
      <i class="bi bi-info-circle"></i> Aturan integritas: kasus yang <strong>sudah dirujuk</strong> hasil diagnosa
      atau validasi pakar tidak dihapus permanen — diubah menjadi <code>arsip</code>. Kasus belum dirujuk dapat
      dihapus permanen beserta detail gejalanya (satu transaksi).
    </p>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
