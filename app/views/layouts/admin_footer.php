<?php
/**
 * app/views/layouts/admin_footer.php — kaki halaman panel.
 * Menutup struktur layout admin, memuat pustaka CDN, dan app.js
 * (inisialisasi DataTables + konfirmasi hapus SweetAlert2).
 */
$flashes = $flashes ?? get_flashes();
?>
    </main>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e(asset_url('js/app.js')) ?>"></script>
<?php foreach (($extraJs ?? []) as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
<?php require __DIR__ . '/flash_script.php'; ?>
</body>
</html>
