<?php
require __DIR__ . '/../layouts/header.php';
/** @var array $errors */ /** @var array $old */
$errors = $errors ?? [];
$old    = $old ?? [];
?>
<div class="row justify-content-center py-4">
  <div class="col-md-8 col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h1 class="h4 mb-1"><i class="bi bi-person-plus"></i> Daftar Akun Pasien</h1>
        <p class="text-secondary small">
          Akun terdaftar menyimpan riwayat diagnosa Anda. Fitur "lupa kata sandi" <em>tidak dikerjakan pada versi 1</em>
          (lihat AGENTS.md) — simpan kata sandi Anda dengan aman.
        </p>
        <?php if (isset($_SESSION['session_token'])): ?>
          <div class="alert alert-info small" role="alert">
            <i class="bi bi-info-circle"></i> Anda saat ini berjalan sebagai <strong>tamu</strong>.
            Setelah mendaftar, diagnosa Anda akan tersimpan di riwayat.
          </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('register')) ?>" autocomplete="off">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="nama" name="nama" value="<?= e($old['nama'] ?? '') ?>"
                     maxlength="100" required>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="email" name="email" value="<?= e($old['email'] ?? '') ?>"
                     maxlength="120" required>
              <div class="form-text">Email wajib unik dan tidak pernah dibagikan.</div>
            </div>
            <div class="col-md-4">
              <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
              <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir"
                     value="<?= e($old['tanggal_lahir'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label for="jenis_kelamin" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
              <select class="form-select" id="jenis_kelamin" name="jenis_kelamin" required>
                <option value="">— pilih —</option>
                <option value="L" <?= isset($old['jenis_kelamin']) && $old['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= isset($old['jenis_kelamin']) && $old['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="password" class="form-label">Kata Sandi <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="password" name="password"
                     minlength="8" autocomplete="new-password" required>
              <div class="form-text">Minimal 8 karakter.</div>
            </div>
            <div class="col-12">
              <label for="password_confirm" class="form-label">Ulangi Kata Sandi <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                     minlength="8" autocomplete="new-password" required>
            </div>
          </div>
          <div class="alert alert-info small mt-3 mb-3" role="alert">
            <i class="bi bi-shield-check"></i> Data kesehatan Anda diperlakukan sebagai data sensitif:
            kata sandi disimpan dalam bentuk hash, dan data gejala hanya dipakai untuk skrining serta pembelajaran
            basis kasus yang dianonimkan (tanpa identitas).
          </div>
          <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-check"></i> Daftar Sekarang</button>
        </form>

        <hr>
        <p class="small text-center mb-0">Sudah punya akun? <a href="<?= e(url('login')) ?>">Login di sini</a></p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php' ?>
