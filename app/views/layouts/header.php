<?php
/**
 * app/views/layouts/header.php — kepala halaman publik.
 * Variabel: $pageTitle (opsional).
 */
$user = current_user();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Sistem Pakar Penyakit Pernapasan') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg sticky-top spc-navbar">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?= e(url('index')) ?>">
      <span class="spc-brand-icon"><i class="bi bi-lungs"></i></span> Sistem Pakar Pernapasan
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"
            aria-controls="navMain" aria-expanded="false" aria-label="Buka menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= e(url('index')) ?>">Beranda</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('diagnosa')) ?>">Diagnosa</a></li>
        <?php if ($user !== null): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(url('riwayat')) ?>">Riwayat Saya</a></li>
        <?php endif; ?>
        <?php if ($user && in_array($user['role'], ['pakar', 'admin'], true)): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(url('penyakit')) ?>">Panel Pakar</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav align-items-lg-center gap-2">
        <?php if (!empty($_SESSION['session_token']) && $user === null): ?>
          <li class="nav-item"><span class="badge spc-badge-tamu">Mode Tamu</span></li>
        <?php endif; ?>
        <?php if ($user !== null): ?>
          <li class="nav-item d-none d-lg-block">
            <span class="nav-link spc-nav-user"><i class="bi bi-person-circle"></i> <?= e($user['nama']) ?>
              <span class="badge spc-badge-role"><?= e(ucfirst($user['role'])) ?></span></span>
          </li>
          <li class="nav-item">
            <form method="post" action="<?= e(url('logout')) ?>" class="d-inline">
              <?= csrf_field() ?>
              <button class="btn spc-btn-logout btn-sm" type="submit"><i class="bi bi-box-arrow-right"></i> Keluar</button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="btn spc-btn-login btn-sm" href="<?= e(url('login')) ?>">Masuk</a></li>
          <li class="nav-item"><a class="btn spc-btn-register btn-sm" href="<?= e(url('register')) ?>">Daftar</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container py-4 flex-fill">
