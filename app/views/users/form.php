<?php
require __DIR__ . '/../layouts/admin_header.php';
/**
 * app/views/users/form.php — tambah akun pakar/admin baru (FR-A7).
 * Variabel: $old, $errors
 */
$old    = $old ?? [];
$errors = $errors ?? [];
?>
<div class="row justify-content-center">
  <div class="col-lg-7 col-xl-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-person-plus"></i> Tambah Akun Staf</span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('users')) ?>">
          <i class="bi bi-arrow-left"></i> Kembali
        </a>
      </div>
      <div class="card-body">
        <?php if ($errors !== []): ?>
          <div class="alert alert-danger" role="alert">
            <strong><i class="bi bi-exclamation-octagon"></i> Periksa kembali:</strong>
            <ul class="mb-0 ps-3 mt-1 small">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('users', ['action' => 'save'])) ?>" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="nama">Nama lengkap</label>
            <input type="text" class="form-control" id="nama" name="nama" maxlength="100" required
                   value="<?= e($old['nama'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" maxlength="120" required
                   value="<?= e($old['email'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="role">Peran</label>
            <select class="form-select" id="role" name="role" required>
              <option value="">— pilih peran —</option>
              <option value="pakar" <?= ($old['role'] ?? '') === 'pakar' ? 'selected' : '' ?>>Pakar</option>
              <option value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <div class="form-text">Akun pasien dibuat sendiri melalui halaman Daftar.</div>
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="password">Kata sandi</label>
              <input type="password" class="form-control" id="password" name="password" minlength="8" required
                     autocomplete="new-password">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="password_confirm">Ulangi kata sandi</label>
              <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                     minlength="8" required autocomplete="new-password">
            </div>
          </div>
          <div class="form-text mb-3">Minimal 8 karakter. Kata sandi disimpan sebagai hash.</div>

          <div class="d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(url('users')) ?>">Batal</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Buat Akun</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php' ?>
