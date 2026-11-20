/* ============================================================
   public/js/app.js — skrip umum panel admin
   1. Inisialisasi DataTables (integrasi Bootstrap 5) dengan
      terjemahan Indonesia INLINE (bebas blokir CSP, tetap jalan offline).
      dataTables.bootstrap5.min.js otomatis menambah class Bootstrap
      (form-control, form-select, pagination) pada kontrol tabel.
   2. Konfirmasi hapus (SweetAlert2) untuk setiap
      form[data-konfirmasi-hapus] dengan info jumlah kasus terdampak:
      - belum dipakai               → peringatan hapus permanen;
      - sudah dipakai (jumlah > 0)  → dijelaskan hanya soft delete
        (is_active = 0) sesuai aturan PRD FR-A2/FR-A3.
   ============================================================ */
(function () {
    'use strict';

    /* Escape teks dari dataset sebelum dimasukkan ke HTML SweetAlert2.
       Dataset berasal dari atribut yang sudah di-escape server, tetapi
       browser mengembalikannya sebagai teks mentah — jadi harus di-escape
       lagi agar nama data tidak dapat menyisipkan HTML/script. */
    function escHtml(nilai) {
        return String(nilai).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    /* ---------- 1. DataTables ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('tabelMaster');
        if (el && window.DataTable) {
            // Data (input) type: pastikan tabel lebar penuh tanpa scrollbar liar.
            el.classList.add('w-100');
            window.DataTable = window.DataTable;

            new window.DataTable('#tabelMaster', {
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                autoWidth: false,
                language: {
                    search: 'Cari:',
                    searchPlaceholder: 'ketik untuk mencari...',
                    lengthMenu: 'Tampilkan _MENU_ baris per halaman',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ baris',
                    infoEmpty: 'Tidak ada data untuk ditampilkan',
                    infoFiltered: '(disaring dari _MAX_ total baris)',
                    emptyTable: 'Belum ada data',
                    zeroRecords: 'Tidak ada data yang cocok dengan pencarian',
                    processing: 'Memproses...',
                    paginate: {
                        first: 'Pertama',
                        last: 'Terakhir',
                        next: 'Berikutnya',
                        previous: 'Sebelumnya'
                    }
                }
            });
        }

        /* ---------- 2. Konfirmasi hapus ---------- */
        document.querySelectorAll('form[data-konfirmasi-hapus]').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();

                var jumlahKasus = Number(form.dataset.jumlahKasus || 0);
                var jumlahRef   = Number(form.dataset.referensi   || 0);
                var nama        = escHtml(form.dataset.nama || 'data ini');

                // Label daftar terdampak bisa diubah per halaman (Basis Kasus
                // memakai label berbeda dari Master Penyakit/Gejala).
                var labelKasus = escHtml(form.dataset.labelKasus || 'kasus di basis pengetahuan');
                var labelRef   = escHtml(form.dataset.labelReferensi || 'rekaman riwayat lainnya');

                // Baris daftar hanya untuk jumlah > 0 (mis. kasus tidak punya
                // "kasus di basis pengetahuan", hanya referensi riwayat).
                var daftar = [];
                if (jumlahKasus > 0) { daftar.push('<li>' + jumlahKasus + ' ' + labelKasus + '</li>'); }
                if (jumlahRef > 0)   { daftar.push('<li>' + jumlahRef + ' ' + labelRef + '</li>'); }

                var isi;
                if (daftar.length > 0) {
                    // Teks soft delete bisa disesuaikan (kasus → status "arsip").
                    var teksSoft = form.dataset.softdeleteTeks
                        || 'Sesuai aturan integritas data, yang diterapkan hanya <strong>soft delete (is_active = 0)</strong>. Kasus dan riwayat lama tetap utuh.';
                    isi = '<div class="text-start fs-6">'
                        + '<p class="mb-1"><strong>' + nama + '</strong> sedang dipakai oleh:</p>'
                        + '<ul class="mb-2">' + daftar.join('') + '</ul>'
                        + '<p class="text-warning-emphasis mb-0">' + teksSoft + '</p></div>';
                } else {
                    isi = '<div class="text-start fs-6">'
                        + '<p class="mb-1"><strong>' + nama + '</strong> belum dipakai kasus atau riwayat apa pun.</p>'
                        + '<p class="mb-0">Data akan dihapus <strong>permanen</strong> dari database. Lanjutkan?</p></div>';
                }

                window.Swal.fire({
                    title: 'Konfirmasi hapus',
                    html: isi,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, lanjutkan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc3545'
                }).then(function (hasil) {
                    if (hasil.isConfirmed) { form.submit(); }
                });
            });
        });
    });
})();
