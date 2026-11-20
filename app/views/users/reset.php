<?php
require __DIR__ . '/../layouts/admin_header.php';
/**
 * app/views/users/reset.php — reset kata sandi pengguna (FR-A7).
 * Variabel: $target, $errors
 */
$errors = $errors ?? [];
?>
<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-key"></i> Reset Kata Sandi</span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('users')) ?>">
          <i class="bi bi-arrow-left"></i> Kembali
        </a>
      </div>
      <div class="card-body">
        <div class="alert alert-light border small">
          Akun: <strong><?= e($target['nama']) ?></strong> &middot; <?= e($target['email']) ?>
          <span class="badge text-bg-<?= e($target['role'] === 'admin' ? 'danger' : ($target['role'] === 'pakar' ? 'primary' : 'secondary')) ?>">
            <?= e($target['role']) ?>
          </span>
        </div>

        <?php if ($errors !== []): ?>
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3 small">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('users', ['action' => 'reset', 'id' => (int) $target['id_user']])) ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((int) $target['id_user']) ?>">
          <div class="mb-3">
            <label class="form-label" for="password">Kata sandi baru</label>
            <input type="password" class="form-control" id="password" name="password" minlength="8" required
                   autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label" for="password_confirm">Ulangi kata sandi baru</label>
            <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                   minlength="8" required autocomplete="new-password">
          </div>
          <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle"></i> Sampaikan kata sandi baru secara langsung kepada pemilik akun.
            Sandi lama langsung tidak berlaku. Aksi ini dicatat ke log aktivitas.
          </div>
          <div class="d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(url('users')) ?>">Batal</a>
            <button class="btn btn-warning" type="submit"><i class="bi bi-key"></i> Reset Sandi</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
