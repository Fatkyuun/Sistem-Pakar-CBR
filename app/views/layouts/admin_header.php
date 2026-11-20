<?php
/**
 * app/views/layouts/admin_header.php — kepala halaman panel Pakar/Admin.
 *
 * Layout Flexbox: sidebar 260 px (flex-shrink-0, bg-white) di kiri,
 * area konten (flex-grow-1, overflow-auto) di kanan. Di layar < lg
 * sidebar berubah menjadi offcanvas yang dibuka dari tombol topbar.
 *
 * Variabel: $pageTitle, $halamanAktif ('dashboard'|'penyakit'|'gejala'|'kasus'|'bobot'|'validasi'|'pengaturan'|'users'), $useDataTables (opsional).
 */
$useDataTables = $useDataTables ?? true;
$halamanAktif  = $halamanAktif ?? '';
$user          = current_user();

// Jumlah antrean validasi untuk badge sidebar (aman bila query gagal).
$jumlahMenunggu = 0;
try {
    $jumlahMenunggu = (int) db()->query(
        "SELECT COUNT(*) FROM diagnosa WHERE status_validasi = 'menunggu'"
    )->fetchColumn();
} catch (Throwable $e) {
    $jumlahMenunggu = 0;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Panel Pakar') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php if ($useDataTables): ?>
<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body class="spc-admin-body">
<div class="app-shell d-flex vh-100 overflow-hidden">

  <!-- ================= SIDEBAR (260 px; offcanvas di layar kecil) ================= -->
  <aside class="offcanvas-lg offcanvas-start sidebar spc-admin-sidebar flex-shrink-0"
         tabindex="-1" id="adminSidebar" aria-label="Menu panel pakar">
    <div class="offcanvas-header justify-content-between d-lg-none border-bottom py-3 px-3">
      <div class="d-flex align-items-center gap-2">
        <span class="spc-brand-icon"><i class="bi bi-lungs"></i></span>
        <span class="fw-semibold text-slate-800">Sistem Pakar CBR</span>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
              data-bs-target="#adminSidebar" aria-label="Tutup menu"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column h-100 p-0">
      <!-- Header Sidebar Desktop -->
      <div class="d-none d-lg-block p-3 border-bottom">
        <a href="<?= e(url('dashboard')) ?>" class="text-decoration-none d-flex align-items-center gap-2">
          <span class="spc-brand-icon"><i class="bi bi-lungs"></i></span>
          <div>
            <div class="fw-bold text-slate-900 lh-sm" style="font-size: 0.95rem;">Sistem Pakar CBR</div>
            <span class="badge spc-badge-pastel-blue mt-1" style="font-size: 0.72rem;">Panel <?= e(ucfirst($user['role'] ?? '')) ?></span>
          </div>
        </a>
      </div>

      <!-- Menu Navigasi Terkelompok -->
      <nav class="flex-grow-1 overflow-y-auto px-3 py-3" style="scrollbar-width: thin;">
        <!-- KELOMPOK UTAMA -->
        <div class="spc-sidebar-category">UTAMA</div>
        <ul class="nav flex-column gap-1 mb-3">
          <li class="nav-item">
            <a class="nav-link spc-sidebar-link <?= $halamanAktif === 'dashboard' ? 'active' : '' ?>"
               href="<?= e(url('dashboard')) ?>">
              <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link spc-sidebar-link d-flex justify-content-between align-items-center <?= $halamanAktif === 'validasi' ? 'active' : '' ?>"
               href="<?= e(url('validasi')) ?>">
              <span><i class="bi bi-check2-square me-2"></i> Validasi Kasus</span>
              <?php if ($jumlahMenunggu > 0): ?>
                <span class="badge rounded-pill spc-badge-pastel-yellow"><?= e($jumlahMenunggu) ?></span>
              <?php endif; ?>
            </a>
          </li>
        </ul>

        <!-- KELOMPOK BASIS PENGETAHUAN -->
        <div class="spc-sidebar-category">BASIS PENGETAHUAN</div>
        <ul class="nav flex-column gap-1 mb-3">
          <li class="nav-item">
            <a class="nav-link spc-sidebar-link <?= $halamanAktif === 'penyakit' ? 'active' : '' ?>"
               href="<?= e(url('penyakit')) ?>">
              <i class="bi bi-hospital me-2"></i> Master Penyakit
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link spc-sidebar-link <?= $halamanAktif === 'gejala' ? 'active' : '' ?>"
               href="<?= e(url('gejala')) ?>">
              <i class="bi bi-thermometer-half me-2"></i> Master Gejala
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link spc-sidebar-link <?= $halamanAktif === 'kasus' ? 'active' : '' ?>"
               href="<?= e(url('kasus')) ?>">
              <i class="bi bi-folder2-open me-2"></i> Basis Kasus
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link spc-sidebar-link <?= $halamanAktif === 'bobot' ? 'active' : '' ?>"
               href="<?= e(url('bobot')) ?>">
              <i class="bi bi-sliders me-2"></i> Bobot Gejala
            </a>
          </li>
        </ul>

        <!-- KELOMPOK SISTEM -->
        <div class="spc-sidebar-category">SISTEM</div>
        <ul class="nav flex-column gap-1 mb-2">
          <li class="nav-item">
            <a class="nav-link spc-sidebar-link <?= $halamanAktif === 'evaluasi' ? 'active' : '' ?>"
               href="<?= e(url('evaluasi')) ?>">
              <i class="bi bi-clipboard2-data me-2"></i> Evaluasi CBR
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link spc-sidebar-link <?= $halamanAktif === 'pengaturan' ? 'active' : '' ?>"
               href="<?= e(url('pengaturan')) ?>">
              <i class="bi bi-gear me-2"></i> Pengaturan CBR
            </a>
          </li>
          <?php if (($user['role'] ?? '') === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link spc-sidebar-link <?= $halamanAktif === 'users' ? 'active' : '' ?>"
                 href="<?= e(url('users')) ?>">
                <i class="bi bi-people me-2"></i> Manajemen Pengguna
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>

      <!-- Footer Bawah Sidebar -->
      <div class="p-3 border-top bg-white mt-auto">
        <a class="nav-link spc-sidebar-link mb-2 text-secondary" href="<?= e(url('index')) ?>">
          <i class="bi bi-house me-2"></i> Beranda Publik
        </a>
        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
          <div class="text-truncate me-2" style="max-width: 140px;">
            <div class="small fw-semibold text-slate-800 text-truncate" title="<?= e($user['nama'] ?? '') ?>">
              <?= e($user['nama'] ?? '') ?>
            </div>
            <div class="text-muted" style="font-size: 0.72rem;"><?= e(ucfirst($user['role'] ?? '')) ?></div>
          </div>
          <form method="post" action="<?= e(url('logout')) ?>" class="m-0">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm px-2 py-1" type="submit" title="Keluar">
              <i class="bi bi-box-arrow-right"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
  </aside>

  <!-- ================= AREA KONTEN KANAN ================= -->
  <div class="flex-grow-1 d-flex flex-column flex-main overflow-hidden bg-slate-50">
    <!-- Topbar Mobile -->
    <nav class="navbar navbar-light bg-white border-bottom d-lg-none py-2 px-3 flex-shrink-0">
      <div class="container-fluid p-0">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#adminSidebar" aria-controls="adminSidebar">
          <i class="bi bi-list"></i> Menu
        </button>
        <span class="small fw-semibold text-slate-800">
          <i class="bi bi-lungs text-primary"></i> Panel <?= e(ucfirst($user['role'] ?? '')) ?>
        </span>
      </div>
    </nav>

    <!-- Topbar Desktop Minimalis -->
    <header class="d-none d-lg-flex align-items-center justify-content-between px-4 py-3 bg-white border-bottom flex-shrink-0">
      <h1 class="h5 mb-0 fw-bold text-slate-800"><?= e($pageTitle ?? '') ?></h1>
      <div class="d-flex align-items-center gap-2">
        <span class="text-secondary small">
          <i class="bi bi-person-circle text-primary me-1"></i>
          <strong><?= e($user['nama'] ?? '') ?></strong> (<?= e(ucfirst($user['role'] ?? '')) ?>)
        </span>
      </div>
    </header>

    <main class="flex-grow-1 p-4 overflow-auto">
