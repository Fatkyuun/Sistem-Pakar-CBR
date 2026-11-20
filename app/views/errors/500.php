<?php
/** app/views/errors/500.php — halaman galat server (variabel: $detail). */
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>500 — Kesalahan Server</title>
<link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body style="font-family:system-ui;padding:2rem">
<h2>500 — Terjadi kesalahan</h2>
<p><?= e($detail ?? 'Terjadi kesalahan pada server.') ?></p>
<p><a href="<?= e(url('index')) ?>">Kembali ke beranda</a></p>
</body>
</html>
