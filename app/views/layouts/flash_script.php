<?php
/**
 * app/views/layouts/flash_script.php — potongan skrip SweetAlert2
 * untuk menampilkan pesan flash. Dipakai footer.php dan admin_footer.php.
 * Variabel: $flashes (array tipe+pesan atau kosong).
 */
if (!empty($flashes)) :
    $ikon = ['sukses' => 'success', 'galat' => 'error', 'peringatan' => 'warning', 'info' => 'info'];
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php foreach ($flashes as $f): ?>
    Swal.fire({
        icon: <?= json_encode($ikon[$f['tipe']] ?? 'info') ?>,
        title: <?= json_encode($f['pesan'], JSON_UNESCAPED_UNICODE) ?>,
        timer: 4000,
        timerProgressBar: true
    });
    <?php endforeach; ?>
});
</script>
<?php endif; ?>
