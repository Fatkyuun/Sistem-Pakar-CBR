/* ============================================================
   public/js/diagnosa.js — interaksi formulir gejala (FR-U2)
   1. Penghitung keluhan terpilih + penanda kartu terpilih.
   2. Navigasi langkah per kelompok keluhan (stepper) + tombol
      "Kelompok Berikutnya".
   3. Pencarian keluhan dan filter "tampilkan yang sudah dipilih".
   4. Peringatan darurat (red-flag) langsung, memakai nama keluhan.
   5. Validasi klien: minimal 3 keluhan > 0 dan consent dicentang.

   Seluruh teks yang disisipkan lewat innerHTML bersifat statis;
   data dari server selalu ditulis sebagai text node.
   ============================================================ */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('formDiagnosa');
        if (!form) { return; }

        var items        = Array.prototype.slice.call(document.querySelectorAll('.gejala-item'));
        var sections     = Array.prototype.slice.call(document.querySelectorAll('.langkah-gejala'));
        var navButtons   = Array.prototype.slice.call(document.querySelectorAll('.langkah-nav'));
        var counter      = document.getElementById('hitungGejala');
        var cari         = document.getElementById('cariGejala');
        var bersihkan    = document.getElementById('bersihkanCari');
        var filterBtn    = document.getElementById('filterTerpilih');
        var redflagBox   = document.getElementById('peringatanRedflag');
        var validasi     = document.getElementById('validasiKlien');
        var consent      = document.getElementById('consent');
        var tombol       = document.getElementById('tombolProses');
        var labelLangkah = document.getElementById('labelLangkah');
        var progres      = document.getElementById('progresLangkah');
        var tidakAda     = document.getElementById('tidakAdaHasil');
        var wadah        = document.getElementById('wadahLangkah');

        var jumlahLangkah = sections.length;
        var langkahAktif  = 0;
        var filterAktif   = false;

        /* ---------- nilai satu kartu keluhan ---------- */
        function nilaiItem(item) {
            var radio = item.querySelector('input[type="radio"]:checked');
            if (radio) { return Number(radio.value) || 0; }
            var checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) { return checkbox.checked ? 1 : 0; }
            return 0;
        }

        function hitungTerpilih() {
            var jumlah = 0;
            var red = [];
            var perBagian = sections.map(function () { return 0; });

            items.forEach(function (item) {
                var nilai = nilaiItem(item);
                var bagian = sections.indexOf(item.closest('.langkah-gejala'));
                if (nilai > 0) {
                    jumlah++;
                    item.classList.add('terpilih');
                    if (bagian >= 0) { perBagian[bagian] += 1; }
                    if (item.dataset.redflag === '1') {
                        red.push(item.dataset.nama || 'gejala darurat');
                    }
                } else {
                    item.classList.remove('terpilih');
                }
            });

            return { jumlah: jumlah, red: red, perBagian: perBagian };
        }

        /* ---------- penghitung + jumlah per kelompok ---------- */
        function perbarui() {
            var hasil = hitungTerpilih();

            if (counter) {
                counter.textContent = hasil.jumlah >= 3
                    ? hasil.jumlah + ' keluhan dipilih, siap diproses'
                    : hasil.jumlah + ' dari 3 keluhan dipilih';
                counter.className = 'badge fs-6 ' + (hasil.jumlah >= 3 ? 'text-bg-success' : 'text-bg-secondary');
            }

            sections.forEach(function (sec, i) {
                var badge = sec.querySelector('[data-jumlah-kategori]');
                if (badge) {
                    badge.textContent = hasil.perBagian[i] + ' dipilih';
                    badge.className = 'badge ' + (hasil.perBagian[i] > 0
                        ? 'text-bg-primary'
                        : 'text-bg-light border');
                }
                var navBadge = navButtons[i] ? navButtons[i].querySelector('[data-jumlah-nav]') : null;
                if (navBadge) {
                    navBadge.textContent = hasil.perBagian[i];
                    navBadge.classList.toggle('d-none', hasil.perBagian[i] === 0);
                }
            });

            if (redflagBox) {
                if (hasil.red.length > 0) {
                    redflagBox.innerHTML =
                        '<strong><i class="bi bi-exclamation-octagon-fill"></i> PERINGATAN DARURAT</strong><br>';
                    var span = document.createElement('span');
                    span.textContent = 'Anda memilih keluhan darurat: ' + hasil.red.join(', ') +
                        '. Segera menuju IGD atau hubungi layanan gawat darurat — jangan menunggu hasil sistem ini.';
                    redflagBox.appendChild(span);
                    redflagBox.classList.remove('d-none');
                } else {
                    redflagBox.innerHTML = '';
                    redflagBox.classList.add('d-none');
                }
            }
        }

        /* ---------- mode jelajah: cari / filter ---------- */
        function modeJelajah() {
            return (cari && cari.value.trim() !== '') || filterAktif;
        }

        function itemCocok(item) {
            var q = cari ? cari.value.trim().toLowerCase() : '';
            var cocokCari = q === '' || (item.dataset.teks || '').indexOf(q) !== -1;
            var cocokFilter = !filterAktif || nilaiItem(item) > 0;
            return cocokCari && cocokFilter;
        }

        function terapkanVisibilitas() {
            var jelajah = modeJelajah();
            var totalCocok = 0;

            sections.forEach(function (sec, i) {
                var ada = 0;
                Array.prototype.forEach.call(sec.querySelectorAll('.gejala-item'), function (item) {
                    var cocok = itemCocok(item);
                    item.classList.toggle('d-none', !cocok);
                    if (cocok) { ada++; }
                });

                var pesan = sec.querySelector('[data-tidak-ada]');
                if (pesan) { pesan.classList.add('d-none'); }

                if (jelajah) {
                    sec.hidden = ada === 0;
                    if (ada === 0 && pesan) { pesan.classList.remove('d-none'); }
                    totalCocok += ada > 0 ? 1 : 0;
                } else {
                    sec.hidden = i !== langkahAktif;
                    if (i === langkahAktif) { totalCocok += 1; }
                }
            });

            if (tidakAda) {
                var kosong = jelajah && totalCocok === 0;
                if (kosong) {
                    tidakAda.textContent = filterAktif
                        ? 'Belum ada keluhan yang Anda pilih. Matikan filter untuk melihat semua keluhan.'
                        : 'Tidak ada keluhan yang cocok dengan kata pencarian Anda. Coba kata lain, misalnya "batuk" atau "demam".';
                }
                tidakAda.classList.toggle('d-none', !kosong);
            }

            perbarui();
        }

        /* ---------- stepper ---------- */
        function tampilkanLangkah(i, geser) {
            if (jumlahLangkah === 0) { return; }
            langkahAktif = Math.max(0, Math.min(jumlahLangkah - 1, i));

            navButtons.forEach(function (btn, idx) {
                var aktif = idx === langkahAktif;
                btn.classList.toggle('active', aktif);
                if (aktif) {
                    btn.setAttribute('aria-current', 'step');
                } else {
                    btn.removeAttribute('aria-current');
                }
            });

            if (labelLangkah) {
                labelLangkah.textContent = modeJelajah()
                    ? 'Menampilkan semua kelompok keluhan'
                    : 'Langkah ' + (langkahAktif + 1) + ' dari ' + jumlahLangkah;
            }
            if (progres) {
                progres.style.width = ((langkahAktif + 1) / jumlahLangkah * 100).toFixed(2) + '%';
            }
            sections.forEach(function (sec, idx) {
                var prev = sec.querySelector('[data-langkah-prev]');
                if (prev) { prev.disabled = idx === 0; }
            });

            terapkanVisibilitas();

            if (geser && wadah) {
                wadah.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function keluarModeJelajah() {
            filterAktif = false;
            if (filterBtn) {
                filterBtn.setAttribute('aria-pressed', 'false');
                filterBtn.classList.remove('active');
            }
            if (cari) { cari.value = ''; }
            if (bersihkan) { bersihkan.classList.add('d-none'); }
        }

        navButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var ke = Number(btn.dataset.langkah) || 0;
                keluarModeJelajah();
                tampilkanLangkah(ke, true);
                if (cari) { cari.blur(); }
            });
        });

        form.addEventListener('click', function (ev) {
            var next = ev.target.closest('[data-langkah-next]');
            if (next) {
                if (next.dataset.selesai === '1') {
                    var tujuan = document.getElementById('bagianPersetujuan');
                    if (tujuan) { tujuan.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                    if (consent) { consent.focus({ preventScroll: true }); }
                    return;
                }
                keluarModeJelajah();
                tampilkanLangkah(langkahAktif + 1, true);
                return;
            }
            var prev = ev.target.closest('[data-langkah-prev]');
            if (prev && !prev.disabled) {
                keluarModeJelajah();
                tampilkanLangkah(langkahAktif - 1, true);
            }
        });

        /* ---------- pencarian ---------- */
        function terapkanCari() {
            if (bersihkan) { bersihkan.classList.toggle('d-none', !cari || cari.value.trim() === ''); }
            if (cari && cari.value.trim() !== '') {
                // Cari di semua kelompok: tampilkan yang cocok saja.
                terapkanVisibilitas();
                if (labelLangkah) { labelLangkah.textContent = 'Hasil pencarian'; }
            } else {
                tampilkanLangkah(langkahAktif, false);
            }
        }

        if (cari) {
            cari.addEventListener('input', terapkanCari);
            cari.addEventListener('search', terapkanCari);
        }
        if (bersihkan) {
            bersihkan.addEventListener('click', function () {
                if (cari) { cari.value = ''; }
                terapkanCari();
                if (cari) { cari.focus(); }
            });
        }

        /* ---------- filter "sudah saya pilih" ---------- */
        if (filterBtn) {
            filterBtn.addEventListener('click', function () {
                var jumlah = hitungTerpilih().jumlah;
                if (!filterAktif && jumlah === 0) {
                    tampilValidasi('Belum ada keluhan yang Anda pilih. Pilih keluhan terlebih dahulu, lalu gunakan filter ini.');
                    return;
                }
                filterAktif = !filterAktif;
                filterBtn.setAttribute('aria-pressed', filterAktif ? 'true' : 'false');
                filterBtn.classList.toggle('active', filterAktif);
                if (filterAktif && cari) { cari.value = ''; }
                terapkanVisibilitas();
                if (labelLangkah) {
                    labelLangkah.textContent = filterAktif ? 'Menampilkan keluhan yang sudah Anda pilih' : 'Langkah ' + (langkahAktif + 1) + ' dari ' + jumlahLangkah;
                }
            });
        }

        form.addEventListener('change', perbarui);
        form.addEventListener('input', perbarui);

        /* ---------- validasi klien ---------- */
        function tampilValidasi(pesan) {
            if (!validasi) { return; }
            validasi.textContent = pesan;
            validasi.classList.remove('d-none');
            validasi.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        if (consent) {
            consent.addEventListener('change', function () {
                if (consent.checked && validasi) { validasi.classList.add('d-none'); }
            });
        }

        form.addEventListener('submit', function (ev) {
            var hasil = hitungTerpilih();

            if (hasil.jumlah < 3) {
                ev.preventDefault();
                tampilValidasi('Pilih minimal 3 keluhan yang Anda rasakan (baru ' + hasil.jumlah + ' keluhan terpilih).');
                return;
            }
            if (!consent || !consent.checked) {
                ev.preventDefault();
                tampilValidasi('Centang pernyataan persetujuan dan disclaimer medis sebelum melihat hasil.');
                return;
            }

            if (validasi) { validasi.classList.add('d-none'); }
            if (tombol) {
                tombol.disabled = true;
                tombol.innerHTML =
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses…';
            }
        });

        /* ---------- inisialisasi ---------- */
        // Bila formulir dikirim ulang karena galat validasi, buka kelompok
        // pertama yang sudah memiliki jawaban agar pasien mudah melanjutkan.
        var awal = 0;
        sections.some(function (sec, i) {
            var ada = Array.prototype.some.call(sec.querySelectorAll('.gejala-item'), function (it) {
                return nilaiItem(it) > 0;
            });
            if (ada) { awal = i; return true; }
            return false;
        });
        tampilkanLangkah(awal, false);
    });
})();
