<?php
/**
 * app/views/layouts/footer.php — kaki halaman publik.
 * Memuat pustaka CDN (Bootstrap 5.3, SweetAlert2) dan skrip pesan flash.
 */
$flashes = $flashes ?? get_flashes();
require __DIR__ . '/flash_script.php';
?>
</main>

<footer class="py-4 mt-auto bg-white border-top">
  <div class="container">
    <div class="spc-disclaimer" role="alert">
      <i class="bi bi-info-circle"></i>
      <div>
        <strong>Disclaimer medis:</strong> sistem ini hanya alat bantu skrining awal gangguan pernapasan
        berbasis Case-Based Reasoning. Hasil <strong>bukan pengganti</strong> diagnosis dan pemeriksaan dokter.
        Bila kondisi memburuk atau ada gejala darurat, segera berkunjung ke fasilitas kesehatan/IGD.
      </div>
    </div>
    <p class="small text-muted mb-0 mt-2">Sistem Pakar Penyakit Pernapasan (CBR) — skrining awal berbasis kasus.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
