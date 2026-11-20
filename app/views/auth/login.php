<?php
require __DIR__ . '/../layouts/header.php';
/** @var array $errors */ /** @var array $old */ /** @var int|null $lockoutSisa */ /** @var int $jumlahGagal */
$errors       = $errors ?? [];
$old          = $old ?? [];
$lockoutSisa  = $lockoutSisa ?? null;
$jumlahGagal  = $jumlahGagal ?? 0;
?>
<div class="row justify-content-center py-4">
  <div class="col-md-6 col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h1 class="h4 mb-1"><i class="bi bi-box-arrow-in-right"></i> Login</h1>
        <p class="text-secondary small">Masuk untuk mencatat riwayat diagnosa atau membuka panel pakar/admin.</p>

        <?php if ($lockoutSisa !== null): ?>
          <div class="alert alert-danger" role="alert">
            <i class="bi bi-shield-lock"></i> Terlalu banyak percobaan gagal dari perangkat ini.
            Login <strong>dikunci sementara</strong>. Coba lagi dalam ± <strong><?= e($lockoutSisa) ?> menit</strong>.
          </div>
        <?php elseif ($jumlahGagal > 0): ?>
          <div class="alert alert-warning small" role="alert">
            <i class="bi bi-shield-exclamation"></i> Terdapat <?= e($jumlahGagal) ?> percobaan login gagal tercatat
            dalam jangka waktu terakhir. Setelah beberapa kali gagal, login sementara dikunci.
          </div>
        <?php endif; ?>

        <?php if ($errors !== [] && $lockoutSisa === null): ?>
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login')) ?>" autocomplete="off">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= e($old['email'] ?? '') ?>" maxlength="120" required>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">Kata Sandi</label>
            <input type="password" class="form-control" id="password" name="password"
                   autocomplete="current-password" required>
          </div>
          <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Masuk</button>
        </form>

        <hr>
        <p class="small text-center mb-1">Belum punya akun? <a href="<?= e(url('register')) ?>">Daftar pasien</a></p>
        <p class="small text-center mb-0">
          Sekadar ingin mencoba? <a href="<?= e(url('index')) ?>">Kembali ke beranda</a>
          untuk <form method="post" action="<?= e(url('guest')) ?>" class="d-inline"><?= csrf_field() ?>
          <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">mode tamu</button></form>.
        </p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php' ?>
