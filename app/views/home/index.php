<?php
require __DIR__ . '/../layouts/header.php';
/** @var int $jumlahPenyakit */ /** @var int $jumlahGejala */
?>
<div class="row justify-content-center">
  <div class="col-lg-10">

    <!-- HERO — minimalis, latar terbuka -->
    <section class="spc-hero text-center">
      <span class="spc-pill-badge">
        <i class="bi bi-activity"></i> Skrining Awal &bull; Case-Based Reasoning (CBR)
      </span>
      <h1 class="spc-hero-title">Deteksi Dini Keluhan Pernapasan</h1>
      <p class="spc-hero-subtitle mx-auto">
        Sistem membandingkan keluhan Anda dengan kasus terdokumentasi melalui siklus 4R
        — <em>Retrieve, Reuse, Revise, Retain</em> — untuk memberikan
        kemungkinan penyakit dan saran tindakan awal.
      </p>

      <!-- Tombol Aksi Utama -->
      <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
        <?php if (is_logged_in()):
            $user = current_user();
            $adalahPakar = in_array($user['role'], ['pakar', 'admin'], true);
        ?>
          <a class="btn spc-btn-primary btn-lg px-4" href="<?= e(url('diagnosa')) ?>">
            <i class="bi bi-clipboard2-pulse"></i> Mulai Diagnosa <i class="bi bi-arrow-right ms-1"></i>
          </a>
          <?php if ($adalahPakar): ?>
            <a class="btn spc-btn-outline btn-lg px-4" href="<?= e(url('penyakit')) ?>">
              <i class="bi bi-hospital"></i> Buka Panel Pakar
            </a>
          <?php endif; ?>
        <?php else: ?>
          <form method="post" action="<?= e(url('guest')) ?>">
            <?= csrf_field() ?>
            <button class="btn spc-btn-primary btn-lg px-4" type="submit">
              <i class="bi bi-play-circle"></i> Mulai sebagai Tamu <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </form>
          <a class="btn spc-btn-outline btn-lg px-4" href="<?= e(url('register')) ?>">
            <i class="bi bi-person-plus"></i> Daftar (riwayat tersimpan)
          </a>
          <a class="btn spc-btn-ghost btn-lg px-4" href="<?= e(url('login')) ?>">
            <i class="bi bi-box-arrow-in-right"></i> Login
          </a>
        <?php endif; ?>
      </div>

      <!-- Statistik ringkas sebaris -->
      <p class="spc-stats mt-4 mb-0">
        <?= e($jumlahPenyakit) ?> Penyakit Terdaftar
        <span class="spc-stats-dot">&middot;</span>
        <?= e($jumlahGejala) ?> Gejala Utama
        <span class="spc-stats-dot">&middot;</span>
        Hasil Instan
      </p>
    </section>

    <!-- ALUR 3 LANGKAH — Minimalis -->
    <section class="spc-steps">
      <div class="row g-4">
        <div class="col-md-4">
          <div class="spc-step">
            <span class="spc-step-num">1</span>
            <h2 class="spc-step-title">Pilih Keluhan</h2>
            <p class="spc-step-desc">Centang gejala yang Anda rasakan — tanpa perlu istilah medis.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="spc-step">
            <span class="spc-step-num">2</span>
            <h2 class="spc-step-title">Pencocokan Kasus CBR</h2>
            <p class="spc-step-desc">Mesin CBR menghitung kemiripan terhadap kasus terdokumentasi.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="spc-step">
            <span class="spc-step-num">3</span>
            <h2 class="spc-step-title">Lihat Hasil & Saran</h2>
            <p class="spc-step-desc">Dapatkan kemungkinan penyakit beserta saran tindakan awal.</p>
          </div>
        </div>
      </div>
    </section>

  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php' ?>
